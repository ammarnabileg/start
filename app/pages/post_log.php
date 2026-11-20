<?php
/** #47 سجل النشر: مين نشر/عدّل/جدول إيه وإمتى — بنص المنشور والمنصات والطريقة والميعاد ورابط المنشور */
$wid = (int)ws('id');
$uf = (int)($_GET['u'] ?? 0);
$kf = preg_replace('/[^a-z_]/', '', (string)($_GET['k'] ?? ''));
$mf = preg_replace('/[^a-z]/', '', (string)($_GET['m'] ?? ''));
$pf = preg_replace('/[^a-z_]/', '', (string)($_GET['pl'] ?? ''));
$qf = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
$df = in_array($_GET['d'] ?? '', ['7', '30', '90', 'all'], true) ? (string)$_GET['d'] : '30';
$labels = [
    'post.create_pro' => t('أنشأ منشورًا'), 'post.bulk' => t('رفع جماعي'), 'post.bulk_direct' => t('رفع جماعي'), 'post.duplicate' => t('نسخ منشورًا'), 'post.reschedule_failed' => t('أعاد جدولة منشور فشل'),
    'post.approval_request' => t('طلب موافقة'), 'post.approve' => t('وافق على منشور'), 'post.reject' => t('رفض منشورًا'), 'post.evergreen' => t('ضبط إعادة النشر'), 'post.evergreen_run' => t('إعادة نشر تلقائية'),
    'post.tags' => t('أضاف وسومًا'), 'post.note' => t('أضاف ملاحظة على منشور'), 'post.goals' => t('عدّل الأهداف'), 'post.delete' => t('حذف منشورًا'), 'post.edit' => t('عدّل منشورًا'),
    'comment.reply' => t('ردّ على تعليق'), 'comment.reply_bulk' => t('ردّ على تعليقات'), 'comment.delete' => t('حذف تعليقًا'), 'comment.hide_bulk' => t('أخفى تعليقات'), 'comment.autohide' => t('إخفاء تلقائي لتعليقات'),
];
$modes = ['now' => [t('نُشر فورًا'), 'bg-lime-100 text-lime-800'], 'schedule' => [t('مجدول'), 'bg-sky-100 text-sky-800'], 'queue' => [t('في الطابور'), 'bg-violet-100 text-violet-800'], 'draft' => [t('مسودة'), 'bg-ink-100 text-ink-700']];

/** تفاصيل العملية: الجديدة JSON، والقديمة سطر زي «now 2 accounts x25» */
$parse = function (string $d): array {
    $j = json_decode($d, true);
    if (is_array($j) && ($j['v'] ?? 0) === 2) return $j;
    if (preg_match('/^(now|schedule|queue|draft) (\d+) accounts(?: x(\d+))?/', $d, $m)) return ['mode' => $m[1], 'n' => (int)$m[2], 'x' => (int)($m[3] ?? 1), 'plat' => [], 'text' => '', 'id' => ''];
    $id = preg_match('/^([\w-]{12,64})(?:\s|$)/', $d, $m) ? $m[1] : '';
    return ['raw' => $d, 'id' => $id];
};

$q = "SELECT a.id, a.action, a.details, a.created_at, a.user_id, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.workspace_id = ? AND (a.action LIKE 'post.%' OR a.action LIKE 'comment.%')";
$args = [$wid];
if ($uf) { $q .= ' AND a.user_id = ?'; $args[] = $uf; }
if ($kf === 'post' || $kf === 'comment') { $q .= ' AND a.action LIKE ?'; $args[] = $kf . '.%'; }
if ($df !== 'all') { $q .= ' AND a.created_at >= ?'; $args[] = date('Y-m-d H:i:s', time() - (int)$df * 86400); }
if ($qf !== '') { $q .= ' AND a.details LIKE ?'; $args[] = '%' . $qf . '%'; }
if ($mf !== '' && isset($modes[$mf])) { $q .= ' AND (a.details LIKE ? OR a.details LIKE ?)'; $args[] = '%"mode":"' . $mf . '"%'; $args[] = $mf . ' %'; }
if ($pf !== '') { $q .= ' AND a.details LIKE ?'; $args[] = '%"' . $pf . '"%'; }
$st = db()->prepare($q . ' ORDER BY a.id DESC LIMIT 500'); $st->execute($args); $rows = $st->fetchAll();
$st = db()->prepare("SELECT DISTINCT u.id, u.name FROM activity_log a JOIN users u ON u.id = a.user_id WHERE a.workspace_id = ? AND (a.action LIKE 'post.%' OR a.action LIKE 'comment.%') ORDER BY u.name"); $st->execute([$wid]); $users = $st->fetchAll();

// ملخص آخر 7 أيام: عدد المنشورات بكل طريقة + كل عضو
$sum = ['posts' => 0, 'acc' => 0, 'by' => [], 'mode' => []];
$st = db()->prepare("SELECT a.details, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.workspace_id = ? AND a.action = 'post.create_pro' AND a.created_at >= ?"); $st->execute([$wid, date('Y-m-d H:i:s', time() - 7 * 86400)]);
foreach ($st->fetchAll() as $r) { $x = $parse((string)$r['details']); $k = max(1, (int)($x['x'] ?? 1)); $sum['posts'] += $k; $sum['acc'] += $k * (int)($x['n'] ?? 0);
    $nm = $r['name'] ?: t('النظام'); $sum['by'][$nm] = ($sum['by'][$nm] ?? 0) + $k; if (!empty($x['mode'])) $sum['mode'][$x['mode']] = ($sum['mode'][$x['mode']] ?? 0) + $k; }
arsort($sum['by']);

render(t('سجل النشر'), 'post_log', function () use ($rows, $users, $uf, $kf, $mf, $pf, $qf, $df, $labels, $modes, $parse, $sum) {
    $tz = new DateTimeZone(site_timezone()); $today = (new DateTime('now', $tz))->format('Y-m-d'); $yest = (new DateTime('yesterday', $tz))->format('Y-m-d');
    $n2 = function (int $n) { return $n === 1 ? t('حساب واحد') : ($n === 2 ? t('حسابان') : t('%s حسابات', $n)); };
    ?>
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
    <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('منشورات آخر 7 أيام')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= number_format($sum['posts']) ?></div><div class="text-[11px] text-ink-500"><?= e(t('على %s حسابات', number_format($sum['acc']))) ?></div></div>
    <?php foreach ($modes as $mk => [$ml, $mc]): ?>
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e($ml) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= number_format($sum['mode'][$mk] ?? 0) ?></div></div>
    <?php endforeach; ?>
  </div>

  <form class="card p-3 mb-4 flex flex-wrap items-center gap-2 text-sm" id="pl-f"><input type="hidden" name="p" value="post_log">
    <label class="mk-search !h-9 flex-1 min-w-[12rem]"><input type="search" name="q" value="<?= e($qf) ?>" placeholder="<?= e(t('ابحث في نص المنشورات…')) ?>"></label>
    <select name="d" class="input !mt-0 !py-1.5 !w-auto text-sm"><?php foreach (['7' => t('آخر 7 أيام'), '30' => t('آخر 30 يومًا'), '90' => t('آخر 90 يومًا'), 'all' => t('كل الوقت')] as $k => $l): ?><option value="<?= $k ?>" <?= $df === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select name="u" class="input !mt-0 !py-1.5 !w-auto text-sm"><option value=""><?= e(t('كل الفريق')) ?></option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $uf === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
    <select name="k" class="input !mt-0 !py-1.5 !w-auto text-sm"><option value=""><?= e(t('كل العمليات')) ?></option><option value="post" <?= $kf === 'post' ? 'selected' : '' ?>><?= e(t('المنشورات')) ?></option><option value="comment" <?= $kf === 'comment' ? 'selected' : '' ?>><?= e(t('التعليقات')) ?></option></select>
    <select name="m" class="input !mt-0 !py-1.5 !w-auto text-sm"><option value=""><?= e(t('كل الطرق')) ?></option><?php foreach ($modes as $mk => [$ml]): ?><option value="<?= $mk ?>" <?= $mf === $mk ? 'selected' : '' ?>><?= e($ml) ?></option><?php endforeach; ?></select>
    <select name="pl" class="input !mt-0 !py-1.5 !w-auto text-sm"><option value=""><?= e(t('كل المنصات')) ?></option><?php foreach (['facebook', 'instagram', 'linkedin', 'twitter', 'tiktok', 'youtube', 'threads', 'pinterest', 'googlebusiness'] as $pl): ?><option value="<?= $pl ?>" <?= $pf === $pl ? 'selected' : '' ?>><?= e(platform_label($pl)) ?></option><?php endforeach; ?></select>
    <?php $anyF = $qf !== '' || ($df !== '' && $df !== '7') || $uf || $kf !== '' || $mf !== '' || $pf !== ''; if ($anyF): ?><a href="<?= e(url('post_log')) ?>" class="text-xs text-ink-500 underline hover:text-ink-800 whitespace-nowrap"><?= e(t('مسح الفلاتر')) ?></a><?php endif; ?>
    <?php if ($sum['by']): ?><div class="w-full flex flex-wrap gap-1.5 text-xs pt-1"><span class="text-ink-500"><?= e(t('آخر 7 أيام:')) ?></span><?php foreach (array_slice($sum['by'], 0, 8, true) as $n => $c): ?><span class="rounded-full bg-ink-100 px-2 py-0.5 text-ink-700"><?= e($n) ?> · <b><?= (int)$c ?></b></span><?php endforeach; ?></div><?php endif; ?>
  </form>
  <script>document.getElementById('pl-f').addEventListener('change', function (e) { if (e.target.tagName === 'SELECT') this.submit(); });</script>

  <div class="card overflow-hidden">
    <?php if (!$rows): ?><p class="p-10 text-center text-sm text-ink-500"><?= e(t('لا توجد عمليات مطابقة.')) ?><?php if ($qf !== '' || ($df !== '' && $df !== '7') || $uf || $kf !== '' || $mf !== '' || $pf !== ''): ?> · <a href="<?= e(url('post_log')) ?>" class="underline hover:text-ink-800"><?= e(t('مسح الفلاتر')) ?></a><?php endif; ?></p><?php endif; ?>
    <?php $lastDay = ''; foreach ($rows as $r): $x = $parse((string)$r['details']); $day = fmt_date($r['created_at'], 'Y-m-d');
      if ($day !== $lastDay): $lastDay = $day; ?>
        <div class="px-4 pt-3 pb-1.5 text-[11px] font-semibold text-ink-500 bg-ink-50/60 border-t border-ink-100 first:border-t-0"><?= e($day === $today ? t('اليوم') : ($day === $yest ? t('أمس') : fmt_date($r['created_at'], 'l d/m/Y'))) ?></div>
      <?php endif; $isPost = strpos($r['action'], 'post.') === 0; $m = (string)($x['mode'] ?? ''); ?>
      <div class="flex items-start gap-3 px-4 py-3 text-sm border-t border-ink-100">
        <?= avatar(null, $r['name'] ?: '⚙', 'w-8 h-8') ?>
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-1.5">
            <b class="text-ink-900"><?= e($r['name'] ?: t('النظام')) ?></b> <span class="text-ink-600"><?= e($labels[$r['action']] ?? $r['action']) ?></span>
            <?php if ($m !== '' && isset($modes[$m])): ?><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $modes[$m][1] ?>"><?= e($modes[$m][0]) ?></span><?php endif; ?>
            <?php foreach ((array)($x['plat'] ?? []) as $pl): if ($pl === '') continue; ?><span title="<?= e(platform_label($pl)) ?>"><?= platform_icon($pl, 'w-4 h-4', 'color') ?></span><?php endforeach; ?>
            <?php if (!empty($x['n'])): ?><span class="text-[11px] text-ink-500"><?= e($n2((int)$x['n'])) ?></span><?php endif; ?>
            <?php if ((int)($x['x'] ?? 1) > 1): ?><span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[11px] font-semibold" title="<?= e(t('منشور متكرر')) ?>">×<?= (int)$x['x'] ?></span><?php endif; ?>
            <?php if (!empty($x['media'])): ?><span class="text-[11px] text-ink-500">🖼 <?= (int)$x['media'] ?></span><?php endif; ?>
          </div>
          <?php if (!empty($x['text'])): ?><p class="mt-1 text-ink-700 line-clamp-2 break-words" dir="auto"><?= e($x['text']) ?></p>
          <?php elseif (!empty($x['raw']) && !preg_match('/^[\w-]{12,64}$/', $x['raw'])): ?><p class="mt-0.5 text-[11px] text-ink-500 truncate" dir="auto"><?= e(mb_substr($x['raw'], 0, 140)) ?></p><?php endif; ?>
          <?php if (!empty($x['at']) && $m === 'schedule'): ?><p class="mt-0.5 text-[11px] text-sky-700"><?= e(t('موعد النشر: %s', (new DateTime((string)$x['at'], new DateTimeZone(site_timezone())))->format('d/m h:i A'))) ?></p><?php endif; ?>
        </div>
        <div class="flex flex-col items-end gap-1 shrink-0">
          <span class="text-xs text-ink-500 whitespace-nowrap" dir="ltr"><?= e(fmt_date($r['created_at'], 'h:i A')) ?></span>
          <?php if (!empty($x['id']) && $isPost): ?><a href="<?= e(url('post_edit', ['id' => $x['id']])) ?>" class="text-xs link"><?= e(t('افتح المنشور')) ?></a><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (count($rows) >= 500): ?><p class="p-3 text-center text-xs text-ink-500 border-t border-ink-100"><?= e(t('يُعرض آخر 500 عملية. ضيّق الفترة أو استخدم البحث لرؤية الأقدم.')) ?></p><?php endif; ?>
  </div>
<?php });
