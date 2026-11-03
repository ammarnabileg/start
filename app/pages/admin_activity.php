<?php
/** إدارة المنصة: سجل النشاط (لمدير النظام) */
$pdo = db();

$uid = (int)($_GET['u'] ?? 0);
$wid = (int)($_GET['w'] ?? 0);
$act = trim((string)($_GET['act'] ?? ''));
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$pageNo = max(1, (int)($_GET['n'] ?? 1));
$per = 50;

$where = [];
$args = [];
if ($uid) { $where[] = 'a.user_id = ?'; $args[] = $uid; }
if ($wid) { $where[] = 'a.workspace_id = ?'; $args[] = $wid; }
if ($act !== '') { $where[] = '(a.action LIKE ? OR a.details LIKE ?)'; $args[] = "%$act%"; $args[] = "%$act%"; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'a.created_at >= ?'; $args[] = $from . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $where[] = 'a.created_at <= ?'; $args[] = $to . ' 23:59:59'; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$base = "FROM activity_log a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN workspaces ws ON ws.id = a.workspace_id" . $w;

// تحميل CSV
if (($_GET['export'] ?? '') === 'csv') {
    $st = $pdo->prepare("SELECT a.created_at, u.email, ws.name, a.action, a.details " . $base . " ORDER BY a.id DESC LIMIT 50000");
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // عشان Excel يفهم العربي
    fputcsv($out, ['time', 'user', 'workspace', 'action', 'details']);
    while ($r = $st->fetch(PDO::FETCH_NUM)) {
        fputcsv($out, $r);
    }
    exit;
}

$st = $pdo->prepare("SELECT COUNT(*) " . $base);
$st->execute($args);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$st = $pdo->prepare("SELECT a.*, u.name uname, u.email uemail, ws.name wname " . $base . " ORDER BY a.id DESC LIMIT $per OFFSET " . (($pageNo - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();

$users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
$workspaces = $pdo->query("SELECT id, name FROM workspaces ORDER BY name")->fetchAll();
$filters = array_filter(['u' => $uid ?: null, 'w' => $wid ?: null, 'act' => $act, 'from' => $from, 'to' => $to]);

render(t('سجل النشاط'), 'admin_activity', function () use ($rows, $total, $pages, $pageNo, $users, $workspaces, $uid, $wid, $act, $from, $to, $filters) {
    ?>
    <form method="get" class="card p-4 mb-5 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
      <input type="hidden" name="p" value="admin_activity">
      <label class="block"><span class="label"><?= e(t('المستخدم')) ?></span>
        <select name="u" class="input"><option value=""><?= e(t('الكل')) ?></option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $uid === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
      <label class="block"><span class="label"><?= e(t('الشركة')) ?></span>
        <select name="w" class="input"><option value=""><?= e(t('الكل')) ?></option><?php foreach ($workspaces as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $wid === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select></label>
      <label class="block"><span class="label"><?= e(t('العملية أو التفاصيل')) ?></span><input name="act" value="<?= e($act) ?>" class="input" dir="ltr" placeholder="login, post.create ..."></label>
      <label class="block"><span class="label"><?= e(t('من')) ?></span><input type="date" name="from" value="<?= e($from) ?>" class="input" dir="ltr"></label>
      <label class="block"><span class="label"><?= e(t('إلى')) ?></span><input type="date" name="to" value="<?= e($to) ?>" class="input" dir="ltr"></label>
      <div class="flex gap-2"><button class="btn-primary flex-1"><?= e(t('عرض')) ?></button>
        <a class="btn-ghost" href="<?= e(url('admin_activity', $filters + ['export' => 'csv'])) ?>" title="CSV">⤓ CSV</a></div>
    </form>

    <p class="text-sm text-ink-500 mb-3"><?= e(t('%s عملية', number_format($total))) ?><?php if ($filters): ?> · <a class="link" href="<?= e(url('admin_activity')) ?>"><?= e(t('مسح الفلاتر')) ?></a><?php endif; ?></p>
    <div class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-ink-500 text-xs border-b border-ink-200"><tr>
          <th class="text-start font-medium px-5 py-3"><?= e(t('الوقت')) ?></th>
          <th class="text-start font-medium px-3 py-3"><?= e(t('المستخدم')) ?></th>
          <th class="text-start font-medium px-3 py-3"><?= e(t('الشركة')) ?></th>
          <th class="text-start font-medium px-3 py-3"><?= e(t('العملية')) ?></th>
          <th class="text-start font-medium px-5 py-3"><?= e(t('التفاصيل')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="border-b border-ink-100 last:border-0">
            <td class="px-5 py-2.5 text-xs text-ink-500 whitespace-nowrap" dir="ltr" style="text-align:start"><?= e(substr((string)$r['created_at'], 0, 16)) ?></td>
            <td class="px-3 py-2.5"><?= e($r['uname'] ?? t('محذوف')) ?></td>
            <td class="px-3 py-2.5 text-ink-600"><?= e($r['wname'] ?? '—') ?></td>
            <td class="px-3 py-2.5"><span class="badge font-mono" dir="ltr"><?= e($r['action']) ?></span></td>
            <td class="px-5 py-2.5 text-ink-500 text-xs max-w-md truncate" title="<?= e((string)$r['details']) ?>"><?= e((string)$r['details']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="px-5 py-8 text-center text-ink-500"><?= e(t('مفيش نشاط بالفلاتر دي.')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
      <div class="flex gap-2 justify-center mt-5 items-center">
        <?php if ($pageNo > 1): ?><a class="btn-ghost" href="<?= e(url('admin_activity', $filters + ['n' => $pageNo - 1])) ?>"><?= e(t('السابق')) ?></a><?php endif; ?>
        <span class="text-sm text-ink-500"><?= e(t('صفحة %s من %s', $pageNo, $pages)) ?></span>
        <?php if ($pageNo < $pages): ?><a class="btn-ghost" href="<?= e(url('admin_activity', $filters + ['n' => $pageNo + 1])) ?>"><?= e(t('التالي')) ?></a><?php endif; ?>
      </div>
    <?php endif;
});
