<?php
/**
 * الرئيسية: لوحة المتابعة اليومية للشركة.
 * كل جزء بيتحمّل لوحده وحسب صلاحية المستخدم، فلو جزء فشل الباقي بيشتغل.
 */
$err = null;
$z = zernio_or_null($err);
$pid = zernio_profile();
$tz = new DateTimeZone(site_timezone());

$try = function (callable $fn) {
    try {
        return [$fn(), null];
    } catch (Throwable $t) {
        return [null, $t->getMessage()];
    }
};

$d = ['accounts' => null, 'scheduled' => null, 'convs' => null, 'comments' => null, 'daily' => null];
$e = [];
if ($z) {
    if (can('accounts.view') || can('posts.create')) [$d['accounts'], $e['accounts']] = $try(function () use ($z, $pid) { return $z->allAccounts($pid); });
    if (can('posts.view')) [$d['scheduled'], $e['scheduled']] = $try(function () use ($z, $pid) {
        return $z->posts(['status' => 'scheduled', 'limit' => 6, 'sortBy' => 'scheduled-asc', 'profileId' => $pid]);
    });
    if (can('inbox.reply')) [$d['convs'], $e['convs']] = $try(function () use ($z, $pid) { return $z->conversations(['limit' => 30, 'profileId' => $pid])['data'] ?? []; });
    if (can('comments.reply')) [$d['comments'], $e['comments']] = $try(function () use ($z, $pid) { return $z->commentedPosts(['limit' => 5, 'profileId' => $pid])['data'] ?? []; });
    if (can('analytics.view') && module_enabled('analytics') && function_exists('an_daily_metrics')) {
        [$d['daily'], $e['daily']] = $try(function () use ($z, $pid) {
            return an_daily_metrics($z, ['profileId' => $pid, 'fromDate' => gmdate('Y-m-d', time() - 13 * 86400), 'toDate' => gmdate('Y-m-d')]);
        });
    }
}

// نشاط الفريق من سجل البرنامج (من غير طلبات خارجية)
$activity = [];
try {
    $st = db()->prepare("SELECT a.action, a.details, a.created_at, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
        WHERE a.workspace_id = ? AND a.action NOT IN ('login', 'logout') AND a.action NOT LIKE 'admin.%' AND a.action NOT LIKE 'system.%' ORDER BY a.id DESC LIMIT 6");
    $st->execute([(int)ws('id')]);
    $activity = $st->fetchAll();
} catch (Throwable $t) {
}
$postedBefore = false;
try {
    $st = db()->prepare("SELECT 1 FROM activity_log WHERE workspace_id = ? AND action = 'post.create' LIMIT 1");
    $st->execute([(int)ws('id')]);
    $postedBefore = (bool)$st->fetchColumn();
} catch (Throwable $t) {
}
$teamCount = 0;
try {
    $st = db()->prepare("SELECT COUNT(*) FROM workspace_users WHERE workspace_id = ? AND status = 'active'");
    $st->execute([(int)ws('id')]);
    $teamCount = (int)$st->fetchColumn();
} catch (Throwable $t) {
}

/** تاريخ باسم اليوم بلغة المستخدم */
function dash_day(?string $iso, bool $time = false): string
{
    if (!$iso) return '';
    $names = [t('الأحد'), t('الاثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت')];
    $w = (int)fmt_date($iso, 'w');
    return $names[$w] . ' ' . fmt_date($iso, 'd/m') . ($time ? ' · ' . fmt_date($iso, 'h:i A') : '');
}

/** وصف مفهوم لأشهر العمليات في السجل */
function dash_action(string $a): string
{
    $map = [
        'post.create' => t('عمل منشور'), 'post.delete' => t('حذف منشور'), 'inbox.reply' => t('رد على رسالة'), 'comment.reply' => t('رد على تعليق'),
        'comment.delete' => t('حذف تعليق'), 'account.connect' => t('بدأ ربط حساب'), 'account.disconnect' => t('فصل حساب'),
        'team.invite' => t('دعا عضو جديد'), 'team.role' => t('غيّر دور عضو'), 'connector.save' => t('حدّث التكاملات'), 'settings.update' => t('عدّل الإعدادات'),
        'team.role_create' => t('عمل دور جديد'), 'team.role_update' => t('عدّل دور'), 'team.role_delete' => t('حذف دور'), 'team.remove' => t('شال عضو من الفريق'),
        'team.transfer_ownership' => t('نقل ملكية الشركة'), 'team.invite_link' => t('عمل لينك دعوة'), 'connector.delete' => t('فصل اتصال'),
        'inbox.private_reply' => t('بعت رد خاص على تعليق'), 'inbox.archive' => t('أرشف محادثة'), 'post.update' => t('عدّل منشور'),
        'broadcast.create' => t('جهّز رسالة جماعية'), 'broadcast.send' => t('بعت رسالة جماعية'), 'invite.accept' => t('انضم للشركة'), 'workspace.add' => t('عمل الشركة'),
    ];
    if (isset($map[$a])) return $map[$a];
    // أي عملية تانية: نعرض قسمها بشكل مفهوم بدل الاسم التقني
    $areas = ['post' => t('المنشورات'), 'inbox' => t('الرسائل'), 'comment' => t('التعليقات'), 'team' => t('الفريق'), 'account' => t('الحسابات'),
        'broadcast' => t('واتساب'), 'sequence' => t('واتساب'), 'template' => t('واتساب'), 'ads' => t('الإعلانات'), 'gmb' => 'Google Business', 'phone' => t('الهاتف')];
    $area = explode('.', $a)[0];
    return t('عمل تغيير في %s', $areas[$area] ?? $area);
}

render(t('الرئيسية'), 'dashboard', function () use ($z, $d, $e, $activity, $teamCount, $tz, $postedBefore) {
    $now = new DateTime('now', $tz);
    $h = (int)$now->format('G');
    $greet = $h < 12 ? t('صباح الخير') : t('مساء الخير');
    $u = current_user();
    $accounts = $d['accounts'] ?? [];
    $bad = array_values(array_filter($accounts, function ($a) { return !empty($a['needsReconnection']) || (isset($a['isActive']) && !$a['isActive']); }));
    $convs = $d['convs'] ?? [];
    $unread = array_values(array_filter($convs, function ($c) { return !empty($c['unreadCount']); }));
    $unreadTotal = array_sum(array_map(function ($c) { return (int)$c['unreadCount']; }, $unread));
    $scheduled = $d['scheduled']['posts'] ?? [];
    $scheduledTotal = (int)($d['scheduled']['pagination']['total'] ?? count($scheduled));

    // الوصول: آخر 7 أيام مقابل الـ 7 اللي قبلهم
    $series = [];
    for ($i = 13; $i >= 0; $i--) $series[gmdate('Y-m-d', time() - $i * 86400)] = 0;
    foreach ((array)$d['daily'] as $row) {
        if (isset($series[$row['date'] ?? ''])) $series[$row['date']] = (float)($row['metrics']['impressions'] ?? 0);
    }
    $vals = array_values($series);
    $last7 = array_sum(array_slice($vals, 7));
    $prev7 = array_sum(array_slice($vals, 0, 7));
    $change = $prev7 > 0 ? round(($last7 - $prev7) / $prev7 * 100) : null;
    $spark = function (array $v) {
        $v = array_slice($v, -7);
        $mx = max($v) ?: 1;
        $pts = [];
        foreach ($v as $i => $x) $pts[] = round($i / 6 * 100, 1) . ',' . round(30 - $x / $mx * 26, 1);
        return '<svg viewBox="0 0 100 32" preserveAspectRatio="none" class="w-24 h-8" dir="ltr"><polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $pts) . '"/></svg>';
    };

    // قائمة البداية للشركة الجديدة
    $steps = [];
    if (is_ws_owner()) {
        $steps = [
            [t('اربط خدمة النشر'), Zernio::configured(), url('connectors')],
            [t('اربط أول حساب سوشيال ميديا'), count($accounts) > 0, url('accounts')],
            [t('اعمل أول منشور'), !empty($scheduled) || $postedBefore, url('compose')],
            [t('ضيف حد من فريقك'), $teamCount > 1, url('team')],
        ];
    }
    $stepsDone = count(array_filter($steps, function ($s) { return $s[1]; }));
    ?>

    <!-- الترحيب -->
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
      <div>
        <p class="text-sm text-ink-500"><?= e(dash_day($now->format('c'))) ?> · <?= e(ws('name')) ?></p>
        <h1 class="text-2xl sm:text-3xl font-semibold text-ink-900 mt-1"><?= e(t('%s، %s', $greet, explode(' ', trim($u['name']))[0])) ?></h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <?php if (can('inbox.reply')): ?><a href="<?= e(url('inbox')) ?>" class="btn-ghost"><?= e(t('الرسائل')) ?><?php if ($unreadTotal): ?> <span class="bg-red-600 text-white text-[11px] rounded-full px-1.5"><?= (int)$unreadTotal ?></span><?php endif; ?></a><?php endif; ?>
        <?php if (can('posts.view')): ?><a href="<?= e(url('calendar')) ?>" class="btn-ghost"><?= e(t('التقويم')) ?></a><?php endif; ?>
        <?php if (can('posts.create')): ?><a href="<?= e(url('compose')) ?>" class="btn-primary"><?= e(t('+ منشور جديد')) ?></a><?php endif; ?>
      </div>
    </div>

    <?php if (!$z): no_api_box(); return; endif; ?>

    <?php if ($steps && $stepsDone < count($steps)): ?>
      <!-- قائمة البداية -->
      <div class="card p-5 mb-6">
        <div class="flex items-center justify-between gap-3 mb-4">
          <div><b class="text-ink-900"><?= e(t('ابدأ بالخطوات دي')) ?></b>
            <p class="text-xs text-ink-500"><?= e(t('%s من %s خلصوا', $stepsDone, count($steps))) ?></p></div>
          <div class="w-40 h-2 rounded-full bg-ink-100"><div class="h-2 rounded-full bg-gradient-to-l from-brand-600 to-lime-500" style="width:<?= (int)round($stepsDone / count($steps) * 100) ?>%"></div></div>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <?php foreach ($steps as $i => [$l, $done, $href]): ?>
            <a href="<?= e($href) ?>" class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm transition <?= $done ? 'border-lime-200 bg-lime-50 text-lime-700' : 'border-ink-200 hover:border-brand-300 hover:bg-brand-50' ?>">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold <?= $done ? 'bg-lime-500 text-white' : 'bg-ink-100 text-ink-600' ?>"><?= $done ? '✓' : $i + 1 ?></span>
              <span class="<?= $done ? 'line-through opacity-70' : 'text-ink-800' ?>"><?= e($l) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($bad): ?>
      <a href="<?= e(url('accounts')) ?>" class="flex items-center gap-3 mb-6 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
        <span class="flex -space-x-2 rtl:space-x-reverse"><?php foreach (array_slice($bad, 0, 4) as $a) echo platform_icon((string)$a['platform'], 'w-6 h-6', 'badge'); ?></span>
        <span class="flex-1"><?= e(t('%s حساب محتاج إعادة ربط عشان النشر والرسائل يشتغلوا عليه.', count($bad))) ?></span>
        <span class="font-medium"><?= e(t('إصلاح ←')) ?></span>
      </a>
    <?php endif; ?>

    <!-- الأرقام -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <?php
      $kpis = [];
      if ($d['accounts'] !== null) {
          $plats = array_unique(array_column($accounts, 'platform'));
          $kpis[] = [t('الحسابات المربوطة'), count($accounts), '<span class="flex -space-x-1.5 rtl:space-x-reverse">' . implode('', array_map(function ($p) { return platform_icon((string)$p, 'w-5 h-5', 'badge'); }, array_slice($plats, 0, 6))) . '</span>', url('accounts')];
      }
      if ($d['scheduled'] !== null) {
          $next = $scheduled[0]['scheduledFor'] ?? null;
          $kpis[] = [t('منشورات مجدولة'), $scheduledTotal, '<span class="text-xs text-ink-500">' . e($next ? t('الجاي: %s', fmt_date($next, 'd/m h:i A')) : t('مفيش حاجة مجدولة')) . '</span>', url('posts', ['status' => 'scheduled'])];
      }
      if ($d['convs'] !== null) {
          $kpis[] = [t('رسائل غير مقروءة'), $unreadTotal, '<span class="text-xs text-ink-500">' . e(t('في %s محادثة', count($unread))) . '</span>', url('inbox')];
      }
      if ($d['daily'] !== null) {
          $chg = $change === null ? '' : '<span class="text-xs font-medium ' . ($change >= 0 ? 'text-lime-700' : 'text-red-600') . '">' . ($change >= 0 ? '▲ ' : '▼ ') . abs($change) . '%</span>';
          $kpis[] = [t('الظهور آخر 7 أيام'), (int)$last7, '<span class="flex items-center gap-2 text-brand-600">' . $spark($vals) . $chg . '</span>', url('analytics')];
      }
      foreach ($kpis as [$l, $v, $extra, $href]): ?>
        <a href="<?= e($href) ?>" class="card p-5 hover:border-brand-300 hover:shadow-md hover:shadow-ink-900/5 transition">
          <div class="text-sm text-ink-500"><?= e($l) ?></div>
          <div class="flex items-end justify-between gap-2 mt-3">
            <div class="text-3xl font-semibold text-ink-900"><?= e(number_format((int)$v)) ?></div>
            <?= $extra ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
      <!-- مستني رد -->
      <?php if ($d['convs'] !== null): ?>
      <div class="card lg:col-span-2 flex flex-col">
        <div class="px-5 py-4 flex items-center justify-between">
          <b class="text-ink-900"><?= e(t('محادثات مستنية رد')) ?></b>
          <a class="link text-sm" href="<?= e(url('inbox')) ?>"><?= e(t('عرض الكل')) ?></a>
        </div>
        <?php $show = $unread ?: array_slice($convs, 0, 5); foreach (array_slice($show, 0, 5) as $c): ?>
          <a href="<?= e(url('inbox', ['c' => $c['id'], 'a' => $c['accountId']])) ?>" class="flex items-center gap-3 px-5 py-3 border-t border-ink-100 hover:bg-ink-50 transition">
            <?= avatar_with_platform($c['participantPicture'] ?? null, $c['participantName'] ?? '', (string)($c['platform'] ?? ''), 'w-10 h-10') ?>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2"><span class="font-medium text-ink-900 truncate"><?= e($c['participantName'] ?? t('بدون اسم')) ?></span>
                <span class="text-[11px] text-ink-400 whitespace-nowrap"><?= e(fmt_date($c['updatedTime'] ?? null, 'd/m h:i A')) ?></span></div>
              <p class="text-sm text-ink-500 truncate"><?= e($c['lastMessage'] ?? '') ?></p>
            </div>
            <?php if (!empty($c['unreadCount'])): ?><span class="bg-red-600 text-white text-[11px] rounded-full px-2 py-0.5"><?= (int)$c['unreadCount'] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if (!$convs): ?><p class="px-5 pb-6 text-sm text-ink-500"><?= e($e['convs'] ? $e['convs'] : t('مفيش رسائل.')) ?></p><?php elseif (!$unread): ?>
          <p class="px-5 py-3 border-t border-ink-100 text-xs text-lime-700"><?= e(t('كل الرسائل مقروءة 👌')) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- الجاي -->
      <?php if ($d['scheduled'] !== null): ?>
      <div class="card flex flex-col">
        <div class="px-5 py-4 flex items-center justify-between">
          <b class="text-ink-900"><?= e(t('المنشورات القادمة')) ?></b>
          <a class="link text-sm" href="<?= e(url('calendar')) ?>"><?= e(t('التقويم')) ?></a>
        </div>
        <div class="px-5 pb-5">
          <?php foreach ($scheduled as $i => $p): ?>
            <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" class="relative flex gap-3 ps-1 pb-4 group">
              <span class="flex flex-col items-center">
                <span class="w-2.5 h-2.5 rounded-full bg-brand-600 ring-4 ring-brand-50 mt-1.5"></span>
                <?php if ($i < count($scheduled) - 1): ?><span class="w-px flex-1 bg-ink-200 mt-1"></span><?php endif; ?>
              </span>
              <span class="flex-1 min-w-0">
                <span class="block text-xs text-ink-500"><?= e(dash_day($p['scheduledFor'] ?? null, true)) ?></span>
                <span class="block text-sm text-ink-800 line-clamp-2 group-hover:text-brand-700"><?= ($p['content'] ?? '') !== '' ? e(mb_substr((string)$p['content'], 0, 140)) : '<span class="text-ink-400">' . e(t('(بدون نص)')) . '</span>' ?></span>
                <span class="flex gap-1 mt-1.5"><?php foreach (array_slice($p['platforms'] ?? [], 0, 5) as $pl) echo platform_icon((string)($pl['platform'] ?? ''), 'w-4 h-4'); ?></span>
              </span>
            </a>
          <?php endforeach; ?>
          <?php if (!$scheduled): ?>
            <div class="text-center py-6">
              <p class="text-sm text-ink-500 mb-3"><?= e(t('مفيش منشورات مجدولة.')) ?></p>
              <?php if (can('posts.create')): ?><a href="<?= e(url('compose')) ?>" class="btn-ghost text-sm"><?= e(t('جدول منشور')) ?></a><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- آخر التعليقات -->
      <?php if ($d['comments'] !== null): ?>
      <div class="card">
        <div class="px-5 py-4 flex items-center justify-between">
          <b class="text-ink-900"><?= e(t('منشورات عليها تعليقات')) ?></b>
          <a class="link text-sm" href="<?= e(url('comments')) ?>"><?= e(t('عرض الكل')) ?></a>
        </div>
        <?php foreach ($d['comments'] as $p): ?>
          <a href="<?= e(url('comments', ['post' => $p['id'], 'a' => $p['accountId']])) ?>" class="flex items-center gap-3 px-5 py-3 border-t border-ink-100 hover:bg-ink-50 transition">
            <span class="relative shrink-0">
              <?php if (!empty($p['picture'])): ?><img src="<?= e($p['picture']) ?>" class="w-10 h-10 rounded-lg object-cover bg-ink-100" loading="lazy" referrerpolicy="no-referrer" alt=""><?php else: ?><span class="w-10 h-10 rounded-lg bg-ink-100 block"></span><?php endif; ?>
              <span class="absolute -bottom-1 -end-1"><?= platform_icon((string)($p['platform'] ?? ''), 'w-4 h-4', 'badge') ?></span>
            </span>
            <span class="flex-1 min-w-0 text-sm text-ink-700 line-clamp-1"><?= ($p['content'] ?? '') !== '' ? e(mb_substr((string)$p['content'], 0, 80)) : e(t('(منشور بدون نص)')) ?></span>
            <span class="badge"><?= (int)($p['commentCount'] ?? 0) ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (!$d['comments']): ?><p class="px-5 pb-5 text-sm text-ink-500"><?= e(t('مفيش منشورات عليها تعليقات.')) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- الحسابات -->
      <?php if ($d['accounts'] !== null && $accounts): ?>
      <div class="card">
        <div class="px-5 py-4 flex items-center justify-between">
          <b class="text-ink-900"><?= e(t('الحسابات المربوطة')) ?></b>
          <a class="link text-sm" href="<?= e(url('accounts')) ?>"><?= e(t('إدارة')) ?></a>
        </div>
        <?php foreach (array_slice($accounts, 0, 6) as $a): $isBad = in_array($a, $bad, true); ?>
          <div class="flex items-center gap-3 px-5 py-2.5 border-t border-ink-100">
            <?= avatar_with_platform($a['profilePicture'] ?? null, (string)($a['displayName'] ?? ''), (string)$a['platform'], 'w-8 h-8') ?>
            <span class="flex-1 min-w-0 text-sm text-ink-800 truncate"><?= e(trim($a['displayName'] ?? $a['username'] ?? '')) ?></span>
            <span class="w-2 h-2 rounded-full <?= $isBad ? 'bg-red-500' : 'bg-lime-500' ?>" title="<?= e($isBad ? t('محتاج إعادة ربط') : t('شغال')) ?>"></span>
          </div>
        <?php endforeach; ?>
        <?php if (count($accounts) > 6): ?><a href="<?= e(url('accounts')) ?>" class="block px-5 py-3 border-t border-ink-100 text-xs link"><?= e(t('+ %s حساب كمان', count($accounts) - 6)) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- نشاط الفريق -->
      <div class="card">
        <div class="px-5 py-4"><b class="text-ink-900"><?= e(t('نشاط الفريق')) ?></b></div>
        <?php foreach ($activity as $a): ?>
          <div class="flex items-start gap-3 px-5 py-2.5 border-t border-ink-100 text-sm">
            <?= avatar(null, (string)($a['name'] ?? '?'), 'w-7 h-7') ?>
            <div class="flex-1 min-w-0">
              <span class="text-ink-800"><b class="font-medium"><?= e($a['name'] ?? t('محذوف')) ?></b> <?= e(dash_action((string)$a['action'])) ?></span>
              <span class="block text-[11px] text-ink-400" dir="ltr" style="text-align:start"><?= e(substr((string)$a['created_at'], 0, 16)) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$activity): ?><p class="px-5 pb-5 text-sm text-ink-500"><?= e(t('مفيش نشاط لسه.')) ?></p><?php endif; ?>
      </div>
    </div>
    <?php
}, ['hideTitle' => true]);
