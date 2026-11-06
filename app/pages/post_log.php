<?php
/** #47 سجل النشر: مين نشر/عدّل/جدول إيه وإمتى (من سجل النشاط) */
$wid = (int)ws('id');
$uf = (int)($_GET['u'] ?? 0);
$kf = preg_replace('/[^a-z_]/', '', (string)($_GET['k'] ?? ''));
$labels = [
    'post.create_pro' => t('أنشأ منشور'), 'post.bulk' => t('رفع جماعي'), 'post.bulk_direct' => t('رفع جماعي'), 'post.duplicate' => t('نسخ منشور'), 'post.reschedule_failed' => t('أعاد جدولة منشور فشل'),
    'post.approval_request' => t('طلب موافقة'), 'post.approve' => t('وافق على منشور'), 'post.reject' => t('رفض منشور'), 'post.evergreen' => t('ظبط Evergreen'), 'post.evergreen_run' => t('Evergreen اتنشر لوحده'),
    'post.tags' => t('حط تاجات'), 'post.note' => t('ملاحظة على منشور'), 'post.goals' => t('عدّل الأهداف'), 'post.delete' => t('مسح منشور'), 'post.edit' => t('عدّل منشور'),
    'comment.reply' => t('رد على تعليق'), 'comment.reply_bulk' => t('رد على تعليق'), 'comment.delete' => t('مسح تعليق'), 'comment.hide_bulk' => t('أخفى تعليقات'), 'comment.autohide' => t('إخفاء تلقائي'),
];
$q = "SELECT a.action, a.details, a.created_at, a.user_id, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.workspace_id = ? AND (a.action LIKE 'post.%' OR a.action LIKE 'comment.%')";
$args = [$wid];
if ($uf) { $q .= ' AND a.user_id = ?'; $args[] = $uf; }
if ($kf === 'post' || $kf === 'comment') { $q .= ' AND a.action LIKE ?'; $args[] = $kf . '.%'; }
$st = db()->prepare($q . ' ORDER BY a.id DESC LIMIT 300'); $st->execute($args); $rows = $st->fetchAll();
$st = db()->prepare("SELECT DISTINCT u.id, u.name FROM activity_log a JOIN users u ON u.id = a.user_id WHERE a.workspace_id = ? AND (a.action LIKE 'post.%' OR a.action LIKE 'comment.%') ORDER BY u.name"); $st->execute([$wid]); $users = $st->fetchAll();
$per = []; foreach ($rows as $r) if (strtotime((string)$r['created_at']) > time() - 7 * 86400) $per[$r['name'] ?: t('النظام')] = ($per[$r['name'] ?: t('النظام')] ?? 0) + 1;
arsort($per);

render(t('سجل النشر'), 'post_log', function () use ($rows, $users, $uf, $kf, $labels, $per) { ?>
  <div class="card p-4 mb-4 flex flex-wrap items-center gap-2 text-sm">
    <form class="flex flex-wrap items-center gap-2"><input type="hidden" name="p" value="post_log">
      <select name="u" class="input !mt-0 !py-1.5 !w-auto text-sm" onchange="this.form.submit()"><option value=""><?= e(t('كل الفريق')) ?></option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $uf === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
      <select name="k" class="input !mt-0 !py-1.5 !w-auto text-sm" onchange="this.form.submit()"><option value=""><?= e(t('كل العمليات')) ?></option><option value="post" <?= $kf === 'post' ? 'selected' : '' ?>><?= e(t('المنشورات')) ?></option><option value="comment" <?= $kf === 'comment' ? 'selected' : '' ?>><?= e(t('التعليقات')) ?></option></select>
    </form>
    <?php if ($per): ?><div class="ms-auto flex flex-wrap gap-1.5 text-xs"><span class="text-ink-500"><?= e(t('آخر 7 أيام:')) ?></span><?php foreach (array_slice($per, 0, 6, true) as $n => $c): ?><span class="rounded-full bg-ink-100 px-2 py-0.5"><?= e($n) ?> · <b><?= (int)$c ?></b></span><?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="card overflow-hidden">
    <?php if (!$rows): ?><p class="p-10 text-center text-sm text-ink-500"><?= e(t('لسه مفيش عمليات نشر متسجلة.')) ?></p><?php endif; ?>
    <div class="divide-y divide-ink-100">
      <?php foreach ($rows as $r): $d = (string)$r['details']; $pid = preg_match('/^[\w-]{12,64}$/', strtok($d, ' ') ?: '') ? strtok($d, ' ') : ''; ?>
        <div class="flex items-center gap-3 px-4 py-2.5 text-sm">
          <?= avatar(null, $r['name'] ?: '⚙', 'w-8 h-8') ?>
          <div class="flex-1 min-w-0"><b class="text-ink-900"><?= e($r['name'] ?: t('النظام')) ?></b> <span class="text-ink-600"><?= e($labels[$r['action']] ?? $r['action']) ?></span>
            <?php if ($d !== ''): ?><div class="text-[11px] text-ink-400 truncate" dir="auto"><?= e(mb_substr($d, 0, 140)) ?></div><?php endif; ?></div>
          <?php if ($pid && strpos($r['action'], 'post.') === 0): ?><a href="<?= e(url('post_edit', ['id' => $pid])) ?>" class="text-xs link"><?= e(t('افتح')) ?></a><?php endif; ?>
          <span class="text-xs text-ink-500 whitespace-nowrap"><?= e(fmt_date($r['created_at'], 'd/m h:i A')) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php });
