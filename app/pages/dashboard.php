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

// كارت الإعلانات: بيتحمّل بعد الصفحة (طلب منفصل) عشان الرئيسية ما تستناش زيرنيو، ومتخزن 5 دقايق
if (($_GET['ajax'] ?? '') === 'ads') {
    header('Content-Type: text/html; charset=utf-8');
    if (!$z || !can('ads.view') || !module_enabled('ads')) exit;
    $ck = 'dash_ads_' . (int)ws('id');
    if (!isset($_GET['refresh']) && isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 300) { echo $_SESSION[$ck]['h']; exit; }
    $today = (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d');
    [$f7, $t7] = ads_date_range('7');
    try {
        $fxd = getenv('UI_FIXTURES');
        $fx = $fxd && is_file($fxd . '/ads.json') ? json_decode((string)file_get_contents($fxd . '/ads.json'), true) : null;
        $wk = $fx ?: ads_campaigns($z, ['fromDate' => $f7, 'toDate' => $t7, 'limit' => 100]);
        $td = $fx ? ['campaigns' => array_map(function ($c) { foreach ($c['metrics'] as $k => $v) $c['metrics'][$k] = is_numeric($v) && $k !== 'ctr' && $k !== 'cpc' ? $v / 7 : $v; return $c; }, $fx['campaigns'])] : ads_campaigns($z, ['fromDate' => $today, 'toDate' => $today, 'limit' => 100]);
    } catch (ZernioException $ex) {
        echo '<div class="px-5 pb-5 text-sm text-ink-500">' . e($ex->getMessage()) . '</div>'; exit;
    }
    $camps = $wk['campaigns'] ?? [];
    $sum = function (array $list, string $k) { $o = []; foreach ($list as $c) { $cur = (string)($c['currency'] ?? ''); $o[$cur] = ($o[$cur] ?? 0) + (float)($c['metrics'][$k] ?? 0); } return array_filter($o); };
    $todaySpend = $sum($td['campaigns'] ?? [], 'spend'); $weekSpend = $sum($camps, 'spend');
    $active = count(array_filter($camps, function ($c) { return ($c['status'] ?? '') === 'active'; }));
    $res = 0; foreach ($camps as $c) $res += ads_results($c['metrics'] ?? []);
    // محتاجة انتباهك (نفس قواعد صفحة الحملات)
    $tI = 0; $tC = 0; foreach ($camps as $c) { $tI += (int)($c['metrics']['impressions'] ?? 0); $tC += (int)($c['metrics']['clicks'] ?? 0); } $avgCtr = $tI ? $tC / $tI * 100 : 0;
    $iss = [];
    foreach ($camps as $c) {
        $m = $c['metrics'] ?? []; $st = (string)($c['status'] ?? ''); $n = (string)($c['campaignName'] ?? '');
        if ($st === 'rejected') $iss[] = [$c, 'red', t('مرفوضة من المنصة')];
        elseif ((float)($m['spend'] ?? 0) > 0 && !ads_results($m)) $iss[] = [$c, 'red', t('صرفت %s من غير أي نتيجة', ads_money($m['spend'], $c['currency'] ?? null))];
        elseif ((int)($m['impressions'] ?? 0) >= 1000 && $avgCtr && (float)($m['ctr'] ?? 0) < $avgCtr * 0.6) $iss[] = [$c, 'amber', t('نسبة الضغط %s أقل من متوسط حساباتك %s', number_format((float)$m['ctr'], 2) . '%', number_format($avgCtr, 2) . '%')];
        elseif ($st === 'pending_review') $iss[] = [$c, 'amber', t('لسه تحت المراجعة')];
    }
    $money = function (array $byCur) { return $byCur ? array_map(function ($v, $k) { return ads_money($v, $k ?: null); }, $byCur, array_keys($byCur)) : ['0']; };
    ob_start();
    if (!$camps): ?>
      <div class="px-5 pb-5 text-sm text-ink-500"><?= e(t('مفيش حملات شغالة آخر 7 أيام.')) ?>
        <?php if (can('ads.spend')): ?> <a href="<?= e(url('ad_create')) ?>" class="link"><?= e(t('اعمل حملة')) ?></a><?php endif; ?></div>
    <?php else: ?>
      <div class="grid grid-cols-3 gap-2 px-5">
        <?php foreach ([[t('صرف النهارده'), $money($todaySpend)], [t('صرف آخر 7 أيام'), $money($weekSpend)], [t('النتايج آخر 7 أيام'), [ads_num($res)]]] as [$l, $vs]): ?>
          <div class="rounded-xl bg-ink-50 p-3 min-w-0"><div class="text-[11px] text-ink-500"><?= e($l) ?></div>
            <?php foreach ($vs as $i => $v): ?><div class="tabular-nums whitespace-nowrap <?= count($vs) > 1 ? 'text-xs font-medium text-ink-800' : 'font-semibold text-ink-900' ?> <?= $i ? '' : 'mt-1' ?>" dir="ltr" style="text-align:start"><?= e($v) ?></div><?php endforeach; ?></div>
        <?php endforeach; ?>
      </div>
      <div class="px-5 pt-3 text-xs text-ink-500"><?= e(t('%s حملة شغالة من %s', $active, count($camps))) ?></div>
      <div class="px-5 pt-3 pb-5 space-y-2">
        <?php if (!$iss): ?><p class="text-xs text-lime-700 flex items-center gap-1.5"><?= ui_icon('check', 'w-4 h-4') ?><?= e(t('كل الحملات تمام')) ?></p>
        <?php else: foreach (array_slice($iss, 0, 3) as [$c, $tone, $msg]): ?>
          <a href="<?= e(url('ad_campaign', ['id' => $c['platformCampaignId'], 'pf' => $c['platform']])) ?>" class="block rounded-xl border px-3 py-2 text-sm hover:shadow-sm transition <?= $tone === 'red' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">
            <b class="block truncate"><?= e($c['campaignName'] ?? '') ?></b><span class="text-xs"><?= e($msg) ?></span></a>
        <?php endforeach; if (count($iss) > 3): ?><a href="<?= e(url('ads')) ?>" class="link text-xs"><?= e(t('و %s كمان', count($iss) - 3)) ?></a><?php endif; endif; ?>
      </div>
    <?php endif;
    $h = ob_get_clean();
    $_SESSION[$ck] = ['t' => time(), 'h' => $h];
    echo $h; exit;
}

$d = ['accounts' => null, 'scheduled' => null, 'convs' => null, 'comments' => null, 'daily' => null];
$e = [];
if ($z) {
    if (can('accounts.view') || can('posts.create')) [$d['accounts'], $e['accounts']] = $try(function () use ($z, $pid) { return $z->allAccounts($pid); });
    if (can('posts.view')) [$d['scheduled'], $e['scheduled']] = $try(function () use ($z, $pid) {
        return $z->posts(['status' => 'scheduled', 'limit' => 50, 'sortBy' => 'scheduled-asc', 'profileId' => $pid]);
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
    $st = db()->prepare("SELECT a.action, a.details, a.created_at, TIMESTAMPDIFF(SECOND, a.created_at, NOW()) AS ago, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
        WHERE a.workspace_id = ? AND a.action NOT IN ('login', 'logout') AND a.action NOT LIKE 'admin.%' AND a.action NOT LIKE 'system.%' ORDER BY a.id DESC LIMIT 40");
    $st->execute([(int)ws('id')]);
    foreach ($st->fetchAll() as $r) {
        $last = count($activity) - 1;
        if ($last >= 0 && $activity[$last]['action'] === $r['action'] && $activity[$last]['name'] === $r['name']) { $activity[$last]['n']++; $activity[$last]['first'] = $r['created_at']; continue; }
        if (count($activity) >= 7) break;
        $r['n'] = 1; $r['first'] = $r['created_at']; $activity[] = $r;
    }
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
        'kb.save' => t('حدّث قاعدة المعرفة'), 'kb.file' => t('رفع ملف لقاعدة المعرفة'), 'automation.create' => t('عمل رد تلقائي'), 'automation.update' => t('عدّل رد تلقائي'), 'automation.delete' => t('حذف رد تلقائي'),
        'workflow.create' => t('عمل Workflow'), 'workflow.delete' => t('حذف Workflow'), 'campaign.create' => t('بدأ حملة رسائل'), 'comment.reply_bulk' => t('رد على كذا تعليق'), 'comment.hide_bulk' => t('أخفى كذا تعليق'),
        'inbox.assign' => t('أسند محادثة'), 'inbox.bulk' => t('عمل إجراء على كذا محادثة'), 'post.duplicate' => t('كرر منشور'), 'post.retry' => t('أعاد محاولة نشر منشور'), 'post.create_pro' => t('عمل منشور'),
        'ai.auto_reply' => t('جهّز رد تلقائي بالذكاء الاصطناعي'), 'ai.image' => t('ولّد صورة بالذكاء الاصطناعي'), 'review.reply' => t('رد على تقييم'), 'contact.import' => t('استورد جهات اتصال'),
    ];
    if (isset($map[$a])) return $map[$a];
    // أي عملية تانية: نعرض قسمها بشكل مفهوم بدل الاسم التقني
    $areas = ['post' => t('المنشورات'), 'inbox' => t('الرسائل'), 'comment' => t('التعليقات'), 'team' => t('الفريق'), 'account' => t('الحسابات'),
        'broadcast' => t('واتساب'), 'sequence' => t('واتساب'), 'template' => t('واتساب'), 'wa_template' => t('واتساب'), 'wa_profile' => t('واتساب'), 'ads' => t('الإعلانات'), 'gmb' => 'Google Business', 'phone' => t('الهاتف'),
        'kb' => t('قاعدة المعرفة'), 'automation' => t('الردود التلقائية'), 'workflow' => t('الـ Workflows'), 'ai' => t('الذكاء الاصطناعي'), 'connector' => t('التكاملات'), 'contact' => t('جهات الاتصال'), 'queue' => t('مواعيد الطابور'), 'reports' => t('التقارير'), 'alerts' => t('التنبيهات'), 'call' => t('الهاتف'), 'sms' => t('الهاتف'), 'review' => 'Google Business', 'goals' => t('الأهداف'), 'analytics' => t('التحليلات'), 'campaign' => t('الرسائل')];
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
    $allScheduled = $d['scheduled']['posts'] ?? [];
    $scheduled = array_slice($allScheduled, 0, 6);
    $scheduledTotal = (int)($d['scheduled']['pagination']['total'] ?? count($allScheduled));
    // الأسبوع الجاي: عدد المنشورات في كل يوم
    $week = [];
    for ($i = 0; $i < 7; $i++) { $dt = (clone $now)->setTime(0, 0)->modify('+' . $i . ' day'); $week[$dt->format('Y-m-d')] = ['dt' => $dt, 'n' => 0, 'pf' => []]; }
    foreach ($allScheduled as $sp) { $k = fmt_date($sp['scheduledFor'] ?? null, 'Y-m-d'); if (isset($week[$k])) { $week[$k]['n']++; foreach ($sp['platforms'] ?? [] as $pl) $week[$k]['pf'][(string)($pl['platform'] ?? '')] = 1; } }
    $todayN = reset($week)['n'];
    $emptyDays = count(array_filter($week, function ($w) { return !$w['n']; }));

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
        <button type="button" id="dash-edit" class="tb px-3 gap-1.5 text-sm" title="<?= e(t('تخصيص الرئيسية')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg><span class="hidden sm:inline"><?= e(t('تخصيص')) ?></span></button>
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
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold <?= $done ? 'bg-lime-500 text-white' : 'bg-ink-100 text-ink-600' ?>"><?= $done ? '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>' : $i + 1 ?></span>
              <span class="<?= $done ? 'line-through opacity-70' : 'text-ink-800' ?>"><?= e($l) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>


    <?php
    // رحلة المنصة: حالة كل مرحلة بأرقام حقيقية، والمرحلة الأضعف هي "الخطوة الجاية"
    $wid0 = (int)ws('id'); $J = [];
    $kbS = function_exists('kb_schema') ? kb_schema() : []; $kbD = function_exists('kb_get') ? kb_get($wid0) : []; $kbN = 0; foreach ($kbS as $k => $sd) if (!empty($kbD[$k]) && kb_filled((array)$kbD[$k], (string)($sd['type'] ?? 'fields'))) $kbN++;
    $kbP = $kbS ? (int)round($kbN / count($kbS) * 100) : 0; $accN = is_array($d['accounts']) ? count($d['accounts']) : null;
    $J[] = ['🏢', t('شركتك'), $kbP . '%', t('ملف الشركة مكتمل'), $kbP >= 60 && $accN !== 0, $kbP < 60 ? t('كمّل ملف الشركة') : ($accN === 0 ? t('اربط حساباتك') : t('راجع الملف')), $kbP < 60 ? url('knowledge') : url('accounts')];
    $alN = (function_exists('alerts_unseen_count') && module_enabled('analytics') && can('analytics.view')) ? alerts_unseen_count($wid0) : 0;
    $J[] = ['📈', t('السوق والفرص'), (string)$alN, t('تنبيه جديد'), $alN === 0, $alN ? t('شوف التنبيهات') : t('افتح التحليلات'), $alN ? url('alerts') : url('analytics')];
    $wk = 0; foreach ((array)($d['scheduled']['posts'] ?? []) as $sp) { $tt = strtotime((string)($sp['scheduledFor'] ?? '')); if ($tt && $tt < time() + 7 * 86400) $wk++; }
    $J[] = ['✍️', t('المحتوى'), $d['scheduled'] === null ? '—' : (string)$wk, t('منشور في 7 أيام'), $wk >= 3, $wk >= 3 ? t('شوف التقويم') : t('جدول منشورات'), $wk >= 3 ? url('posts', ['view' => 'cal']) : url('compose')];
    $crmOn = module_enabled('crm') && can('crm.view'); $newL = $dueL = $wonL = 0;
    if ($crmOn) { try { $q0 = db()->prepare("SELECT SUM(created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)), SUM(next_at IS NOT NULL AND next_at <= NOW() AND stage NOT IN ('won','lost')), SUM(stage = 'won' AND won_at >= ?) FROM crm_leads WHERE workspace_id = ?"); $q0->execute([date('Y-m-01'), $wid0]); [$newL, $dueL, $wonL] = array_map('intval', $q0->fetch(PDO::FETCH_NUM)); } catch (Throwable $x) {} }
    if ($crmOn) {
      $J[] = ['🧲', t('جمع العملاء'), (string)$newL, t('عميل جديد في 7 أيام'), $newL >= 3, $newL >= 3 ? t('شوف مصادرهم') : t('اعمل فورم أو صفحة هبوط'), $newL >= 3 ? url('crm_reports') : url('crm_capture')];
      $J[] = ['🤝', t('المبيعات'), (string)$dueL, t('متابعة مستحقة'), $dueL === 0, $dueL ? t('ابدأ المتابعات') : t('افتح العملاء'), url('crm_leads') . ($dueL ? '#due' : '')];
    }
    $nextI = null; foreach ($J as $i => $x) if (!$x[4]) { $nextI = $i; break; }
    ?>
    <div class="card p-4 mb-6" data-w="journey">
      <div class="flex items-center gap-2 mb-3"><b class="text-sm text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z"/></svg> <?= e(t('رحلة شركتك')) ?></b>
        <?php if ($nextI !== null): ?><span class="text-xs text-ink-500"><?= e(t('الخطوة الجاية:')) ?></span><a href="<?= e($J[$nextI][6]) ?>" class="text-xs font-semibold text-brand-700 hover:underline"><?= e($J[$nextI][5]) ?> <?= is_rtl() ? '←' : '→' ?></a><?php else: ?><span class="text-xs text-lime-700">✓ <?= e(t('كل المراحل ماشية كويس')) ?></span><?php endif; ?>
        <?php if ($crmOn && $wonL): ?><span class="ms-auto text-xs rounded-full bg-lime-50 text-lime-800 border border-lime-200 px-2 py-0.5"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8v5a4 4 0 01-8 0zM8 6H5a3 3 0 003 4M16 6h3a3 3 0 01-3 4M12 13v4M8 21h8M9 17h6"/></svg> <?= e(t('%s عميل اتقفل الشهر ده', $wonL)) ?></span><?php endif; ?></div>
      <div class="grid grid-cols-2 sm:grid-cols-3 <?= [2 => "lg:grid-cols-2", 3 => "lg:grid-cols-3", 4 => "lg:grid-cols-4", 5 => "lg:grid-cols-5"][count($J)] ?? "lg:grid-cols-5" ?> gap-2">
        <?php foreach ($J as $i => $x): $nx = $i === $nextI; ?>
          <a href="<?= e($x[6]) ?>" class="group relative rounded-xl border p-3 transition hover:-translate-y-0.5 hover:shadow-md <?= $nx ? 'border-brand-300 bg-brand-50 ring-2 ring-brand-500/20' : ($x[4] ? 'border-lime-200 bg-lime-50/40' : 'border-ink-200 bg-white') ?>">
            <div class="flex items-center gap-1.5 text-[11px] text-ink-500"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold <?= $x[4] ? 'bg-lime-500 text-white' : 'bg-ink-200 text-ink-600' ?>"><?= $x[4] ? '✓' : $i + 1 ?></span><?= e($x[1]) ?></div>
            <div class="mt-1.5 flex items-baseline gap-1"><span class="text-2xl font-bold text-ink-900"><?= e($x[2]) ?></span><span class="text-[11px] text-ink-500 leading-tight"><?= e($x[3]) ?></span></div>
            <div class="mt-1 text-[11px] font-medium <?= $nx ? 'text-brand-700' : 'text-ink-400 group-hover:text-brand-700' ?>"><?= e($x[5]) ?> <?= is_rtl() ? '←' : '→' ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($z): ?>
    <!-- ملخص اليوم + منشور سريع -->
    <div class="grid lg:grid-cols-[1fr_24rem] gap-4 mb-6" data-w="brief">
      <div class="card p-5 relative overflow-hidden">
        <div class="absolute -top-16 -end-16 w-56 h-56 rounded-full bg-gradient-to-br from-brand-500/10 to-cyan-400/10 blur-2xl pointer-events-none"></div>
        <b class="text-sm text-ink-900"><?= e(t('النهارده')) ?></b>
        <div class="flex flex-wrap gap-2 mt-3">
          <?php
          $chips = [];
          if ($d['convs'] !== null) $chips[] = [$unreadTotal ? t('%s رسالة مستنية رد', $unreadTotal) : t('كل الرسائل مقروءة'), url('inbox'), $unreadTotal ? 'border-red-200 bg-red-50 text-red-700' : 'border-lime-200 bg-lime-50 text-lime-700', 'chat'];
          if ($d['scheduled'] !== null) $chips[] = [$todayN ? t('%s منشور النهارده', $todayN) : t('مفيش منشورات النهارده'), url('posts', ['view' => 'cal']), $todayN ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-amber-200 bg-amber-50 text-amber-800', 'calendar'];
          if ($d['scheduled'] !== null && $emptyDays) $chips[] = [t('%s أيام فاضية الأسبوع ده', $emptyDays), '#dash-week', 'border-ink-200 bg-white text-ink-700', 'clock'];
          if ($bad) $chips[] = [t('%s حساب محتاج إعادة ربط', count($bad)), url('accounts'), 'border-red-200 bg-red-50 text-red-700', 'alert'];
          foreach ($chips as [$cl, $ch, $cc, $ci]): ?>
            <a href="<?= e($ch) ?>" class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition hover:-translate-y-0.5 hover:shadow-sm <?= $cc ?>"><?= ui_icon($ci, 'w-4 h-4') ?><?= e($cl) ?></a>
          <?php endforeach; ?>
        </div>
        <?php if ($d['scheduled'] !== null): ?>
        <div id="dash-week" class="grid grid-cols-7 gap-1.5 mt-5">
          <?php foreach ($week as $wk => $w): $isT = $wk === $now->format('Y-m-d'); $past = $isT && (int)$now->format('G') >= 22; ?>
            <div class="group relative rounded-xl border <?= $isT ? 'border-brand-300 bg-brand-50/60' : 'border-ink-200 bg-white' ?> p-2 text-center transition hover:border-brand-300">
              <div class="text-[10px] text-ink-500"><?= e($isT ? t('النهارده') : [t('الأحد'), t('الاثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت')][(int)$w['dt']->format('w')]) ?></div>
              <div class="text-sm font-semibold text-ink-900 tabular-nums"><?= $w['dt']->format('j') ?></div>
              <?php if ($w['n']): ?>
                <a href="<?= e(url('posts', ['view' => 'cal'])) ?>" class="mt-1 flex items-center justify-center gap-0.5"><span class="rounded-full bg-brand-600 text-[#fff] text-[10px] px-1.5 tabular-nums"><?= $w['n'] ?></span></a>
                <div class="flex justify-center gap-0.5 mt-1"><?php foreach (array_slice(array_keys($w['pf']), 0, 3) as $pp) echo platform_icon($pp, 'w-3 h-3'); ?></div>
              <?php elseif (can('posts.create') && !$past): ?>
                <a href="<?= e(url('compose', ['when' => $w['dt']->format('Y-m-d') . 'T' . ($isT ? (new DateTime('+1 hour', $tz))->format('H') . ':00' : '10:00')])) ?>" class="mt-1 mx-auto w-6 h-6 rounded-lg border border-dashed border-ink-300 text-ink-400 flex items-center justify-center text-sm transition group-hover:border-brand-400 group-hover:text-brand-600 group-hover:bg-brand-50" title="<?= e(t('منشور جديد في اليوم ده')) ?>">+</a>
              <?php else: ?><div class="mt-1 h-6"></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if (can('posts.create')): ?>
      <form class="card p-4 flex flex-col" id="dash-quick" action="index.php" method="get">
        <input type="hidden" name="p" value="compose">
        <b class="text-sm text-ink-900 flex items-center gap-2"><?= ui_icon('bulb', 'w-4 h-4 text-brand-600') ?><?= e(t('فكرة منشور سريعة')) ?></b>
        <textarea name="dup_text" rows="4" maxlength="3000" class="input mt-2 text-sm flex-1 resize-none" dir="auto" placeholder="<?= e(t('اكتب الفكرة هنا وكمّلها في صفحة المنشور الجديد...')) ?>"></textarea>
        <div class="flex items-center gap-2 mt-2"><span class="dq-n text-[11px] text-ink-400 tabular-nums"></span><button class="btn-primary !py-1.5 !text-xs ms-auto"><?= e(t('كمّل في المنشور الجديد')) ?> <kbd class="hidden sm:inline text-[10px] opacity-70">Ctrl ↵</kbd></button></div>
      </form>
      <?php endif; ?>
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
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6" data-w="kpis">
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
        <a href="<?= e($href) ?>" class="card p-5 hover:border-brand-300 hover:shadow-md hover:shadow-ink-900/5 hover:-translate-y-0.5 transition">
          <div class="text-sm text-ink-500"><?= e($l) ?></div>
          <div class="flex items-end justify-between gap-2 mt-3">
            <div class="text-3xl font-semibold text-ink-900 tabular-nums" data-count="<?= (int)$v ?>"><?= e(number_format((int)$v)) ?></div>
            <?= $extra ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (module_enabled('crm') && can('crm.view') && function_exists('crm_my_day')): $md = crm_my_day((int)(current_user()['id'] ?? 0), can('crm.manage')); if ($md['due'] || $md['new'] || $md['meet']): ?>
    <div class="card p-5 mb-4" data-w="myday">
      <div class="flex flex-wrap items-center gap-3 mb-3"><b class="text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17a5 5 0 100-10 5 5 0 000 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg> <?= e(t('شغلك النهارده في العملاء')) ?></b>
        <span class="ld-stat <?= $md['over'] ? '!border-red-200 !bg-red-50 text-red-700' : '' ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/></svg> <?= e(t('مستحقين')) ?> <b><?= (int)$md['due'] ?></b><?php if ($md['over']): ?> <span class="text-[11px]">(<?= e(t('%s متأخرين', (int)$md['over'])) ?>)</span><?php endif; ?></span>
        <span class="ld-stat"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.5 3.5L17 8l-3.5 1.5L12 13l-1.5-3.5L7 8l3.5-1.5zM5 15l.8 2.2L8 18l-2.2.8L5 21l-.8-2.2L2 18l2.2-.8zM19 13l.7 1.8 1.8.7-1.8.7L19 18l-.7-1.8-1.8-.7 1.8-.7z"/></svg> <?= e(t('جداد النهارده')) ?> <b><?= (int)$md['new'] ?></b></span>
        <span class="ld-stat"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1zM4 10h16M8 3v4M16 3v4"/></svg> <?= e(t('اجتماعات')) ?> <b><?= count($md['meet']) ?></b></span>
        <a class="btn-primary ms-auto" href="<?= e(url('crm_leads', ['go' => 'queue'])) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg> <?= e(t('ابدأ شغل النهارده')) ?></a></div>
      <div class="grid md:grid-cols-2 gap-4">
        <div><div class="text-[11px] text-ink-500 mb-1"><?= e(t('ابدأ بدول (الأهم الأول)')) ?></div><div class="divide-y divide-ink-100">
          <?php foreach ($md['top'] as $x): ?><a href="<?= e(url('crm_leads') . '#lead-' . $x['id']) ?>" class="flex items-center gap-2 py-1.5 text-sm hover:bg-ink-50 rounded px-1"><span class="w-6 text-center text-[11px] font-bold rounded-full <?= $x['score'] >= 60 ? 'bg-lime-500 text-white' : 'bg-ink-100 text-ink-600' ?>"><?= (int)$x['score'] ?></span><span class="font-medium text-ink-900 truncate"><?= e($x['name']) ?></span><span class="text-[11px] truncate flex-1 <?= $x['over'] ? 'text-red-600' : 'text-ink-500' ?>"><?= e($x['note']) ?></span></a><?php endforeach; ?>
          <?php if (!$md['top']): ?><p class="text-xs text-ink-400 py-2"><?= e(t('مفيش متابعات مستحقة 👏')) ?></p><?php endif; ?></div></div>
        <div><div class="text-[11px] text-ink-500 mb-1"><?= e(t('اجتماعات النهارده')) ?></div>
          <?php foreach ($md['meet'] as $mt): ?><a href="<?= e(url('crm_leads') . '#lead-' . (int)$mt['id']) ?>" class="flex items-center gap-2 py-1.5 text-sm hover:bg-ink-50 rounded px-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1zM4 10h16M8 3v4M16 3v4"/></svg> <b><?= e(fmt_date($mt['start_at'], 'h:i A')) ?></b> <span class="truncate"><?= e($mt['name']) ?></span></a><?php endforeach; ?>
          <?php if (!$md['meet']): ?><p class="text-xs text-ink-400 py-2"><?= e(t('مفيش اجتماعات النهارده.')) ?></p><?php endif; ?></div>
      </div>
    </div>
    <?php endif; endif; ?>
    <div class="grid lg:grid-cols-3 gap-4" id="dash-grid">
      <!-- مستني رد -->
      <?php if ($d['convs'] !== null): ?>
      <div class="card lg:col-span-2 flex flex-col" data-w="inbox">
        <div class="px-5 py-4 flex items-center justify-between">
          <b class="text-ink-900"><?= e(t('محادثات مستنية رد')) ?></b>
          <a class="link text-sm" href="<?= e(url('inbox')) ?>"><?= e(t('عرض الكل')) ?></a>
        </div>
        <?php $show = $unread ?: array_slice($convs, 0, 5); foreach (array_slice($show, 0, 5) as $c): ?>
          <a href="<?= e(url('inbox', ['c' => $c['id'], 'a' => $c['accountId']])) ?>" class="flex items-center gap-3 px-5 py-3 border-t border-ink-100 hover:bg-ink-50 transition">
            <?= avatar_with_platform($c['participantPicture'] ?? null, $c['participantName'] ?? '', (string)($c['platform'] ?? ''), 'w-10 h-10') ?>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2"><span class="font-medium text-ink-900 truncate"><?= e($c['participantName'] ?? t('بدون اسم')) ?></span>
                <span class="dash-rel text-[11px] text-ink-400 whitespace-nowrap" data-at="<?= e((string)($c['updatedTime'] ?? '')) ?>"><?= e(fmt_date($c['updatedTime'] ?? null, 'd/m h:i A')) ?></span></div>
              <p class="text-sm text-ink-500 truncate"><?= e($c['lastMessage'] ?? '') ?></p>
            </div>
            <?php if (!empty($c['unreadCount'])): ?><span class="bg-red-600 text-white text-[11px] rounded-full px-2 py-0.5"><?= (int)$c['unreadCount'] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if (!$convs): ?><p class="px-5 pb-6 text-sm text-ink-500"><?= e($e['convs'] ? $e['convs'] : t('مفيش رسائل.')) ?></p><?php elseif (!$unread): ?>
          <p class="px-5 py-3 border-t border-ink-100 text-xs text-lime-700 flex items-center gap-1.5"><?= ui_icon('check', 'w-4 h-4') ?><?= e(trim(str_replace('👌', '', t('كل الرسائل مقروءة 👌')))) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- الإعلانات -->
      <?php if (can('ads.view') && module_enabled('ads')): ?>
      <div class="card flex flex-col" data-w="ads" id="dash-ads">
        <div class="px-5 py-4 flex items-center justify-between">
          <b class="text-ink-900"><?= e(t('الإعلانات')) ?></b>
          <a class="link text-sm" href="<?= e(url('ads')) ?>"><?= e(t('الحملات')) ?></a>
        </div>
        <div id="dash-ads-body" class="px-5 pb-5 space-y-2 animate-pulse" aria-busy="true">
          <div class="grid grid-cols-3 gap-2"><div class="h-14 rounded-xl bg-ink-100"></div><div class="h-14 rounded-xl bg-ink-100"></div><div class="h-14 rounded-xl bg-ink-100"></div></div>
          <div class="h-10 rounded-xl bg-ink-100"></div>
        </div>
        <script>
        (function () {
          var b = document.getElementById('dash-ads-body'); if (!b || b.dataset.on) return; b.dataset.on = '1';
          fetch(<?= json_encode(url('dashboard', ['ajax' => 'ads'])) ?>, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) {
            if (!h.trim()) { document.getElementById('dash-ads').remove(); return; }
            b.outerHTML = '<div>' + h + '</div>';
          }).catch(function () { b.classList.remove('animate-pulse'); b.textContent = <?= json_encode(t('ما قدرناش نحمّل الإعلانات')) ?>; });
        })();
        </script>
      </div>
      <?php endif; ?>

      <!-- الجاي -->
      <?php if ($d['scheduled'] !== null): ?>
      <div class="card flex flex-col" data-w="upcoming">
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
      <div class="card" data-w="comments">
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
      <div class="card" data-w="accounts">
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
      <div class="card" data-w="activity">
        <div class="px-5 py-4"><b class="text-ink-900"><?= e(t('نشاط الفريق')) ?></b></div>
        <?php foreach ($activity as $a): ?>
          <div class="flex items-start gap-3 px-5 py-2.5 border-t border-ink-100 text-sm">
            <?= avatar(null, (string)($a['name'] ?? '?'), 'w-7 h-7') ?>
            <div class="flex-1 min-w-0">
              <span class="text-ink-800"><b class="font-medium"><?= e($a['name'] ?? t('محذوف')) ?></b> <?= e(dash_action((string)$a['action'])) ?><?php if (($a['n'] ?? 1) > 1): ?> <span class="ms-1 rounded-md bg-ink-100 text-ink-600 px-1.5 py-0.5 text-[10px] tabular-nums">×<?= (int)$a['n'] ?></span><?php endif; ?></span>
              <span class="dash-rel block text-[11px] text-ink-400" data-ago="<?= (int)($a['ago'] ?? 0) ?>"><?= e(substr((string)$a['created_at'], 0, 16)) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$activity): ?><p class="px-5 pb-5 text-sm text-ink-500"><?= e(t('مفيش نشاط لسه.')) ?></p><?php endif; ?>
      </div>
    </div>
    <script>window.DSX = <?= json_encode(['ws' => (int)ws('id'), 'tzOff' => (new DateTime('now', $tz))->getOffset(),
      'past' => t('من %s'), 'min' => t('%s دقيقة'), 'hr' => t('%s ساعة'), 'day' => t('%s يوم'), 'now' => t('دلوقتي'), 'chars' => t('حرف'),
      'edit' => t('تخصيص'), 'done' => t('تم'), 'reset' => t('رجّع الترتيب الأصلي'), 'hint' => t('اسحب الأقسام لترتيبها، واضغط العين لإخفاء أو إظهار أي قسم.'),
      'names' => ['brief' => t('النهارده'), 'kpis' => t('الأرقام'), 'inbox' => t('محادثات مستنية رد'), 'upcoming' => t('المنشورات القادمة'), 'comments' => t('منشورات عليها تعليقات'), 'accounts' => t('الحسابات المربوطة'), 'activity' => t('نشاط الفريق')], 'hidden' => t('مخفي')], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/dashboard.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['hideTitle' => true, 'full' => true]);
