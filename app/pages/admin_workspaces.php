<?php
/** إدارة المنصة: كل الشركات (لمدير النظام) */
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $st = $pdo->prepare("SELECT * FROM workspaces WHERE id = ?");
    $st->execute([$id]);
    $w = $st->fetch();
    if ($w && ($w['status'] ?? '') === 'deleted') $w = null; // شركة محذوفة: بياناتها محفوظة للإحصائيات بس، مفيش رجوع ولا دخول

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $ownerId = (int)($_POST['owner'] ?? $me['id']);
        if ($name === '') {
            flash('error', t('اكتب اسم الشركة.'));
        } else {
            $pdo->prepare("INSERT INTO workspaces (name, created_by) VALUES (?, ?)")->execute([mb_substr($name, 0, 120), $me['id']]);
            $nid = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")->execute([$nid, $ownerId ?: $me['id']]);
            log_activity('admin.workspace_create', $name);
            flash('success', t('اتعملت شركة "%s".', $name));
        }
        redirect(url('admin_workspaces'));
    }
    if (!$w) {
        redirect(url('admin_workspaces'));
    }

    switch ($action) {
        case 'apify_cap':
            save_setting('apify_cap_' . $id, (string)max(0, min(1000000, (int)($_POST['cap'] ?? 0))));
            log_activity('admin.apify_cap', $w['name'] . ': ' . (int)($_POST['cap'] ?? 0));
            flash('success', t('اتحفظ.'));
            break;
        case 'enter':
            // المدير بيتضاف كصاحب شركة عشان يقدر يدخلها، والدخول بيتسجل
            $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')
                ON DUPLICATE KEY UPDATE status = 'active', role = 'owner'")->execute([$id, $me['id']]);
            $_SESSION['ws'] = $id; user_workspaces(true); // القائمة كانت متخزنة قبل الإضافة
            log_activity('admin.workspace_enter', $w['name']);
            redirect(url('dashboard'));
            break;
        case 'rename':
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name !== '') {
                $pdo->prepare("UPDATE workspaces SET name = ? WHERE id = ?")->execute([mb_substr($name, 0, 120), $id]);
                log_activity('admin.workspace_rename', $w['name'] . ' -> ' . $name);
                flash('success', t('اتغير الاسم.'));
            }
            break;
        case 'suspend':
        case 'activate':
            $pdo->prepare("UPDATE workspaces SET status = ? WHERE id = ?")->execute([$action === 'suspend' ? 'suspended' : 'active', $id]);
            log_activity('admin.workspace_' . $action, $w['name']);
            flash('success', $action === 'suspend' ? t('الشركة اتوقفت. الأعضاء مش هيقدروا يفتحوها.') : t('الشركة رجعت تشتغل.'));
            break;
        case 'owner':
            $uid = (int)($_POST['user_id'] ?? 0);
            $ex = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
            $ex->execute([$uid]);
            if ($ex->fetchColumn()) {
                $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')
                    ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'")->execute([$id, $uid]);
                log_activity('admin.workspace_owner', $w['name'] . ' +' . $uid);
                flash('success', t('اتضاف صاحب شركة.'));
            }
            break;
        case 'delete':
            if (trim((string)($_POST['confirm'] ?? '')) !== $w['name']) {
                flash('error', t('عشان تحذف، اكتب اسم الشركة بالظبط زي ما هو.'));
            } else {
                require_once ROOT_DIR . '/app/ws_purge.php'; $purged = ws_purge((int)$id); // كل بيانات الشركة وملفاتها، مش صف الشركة بس
                log_activity('admin.workspace_delete', $w['name']);
                flash('success', t('اتحذفت "%s" من البرنامج. حساباتها ومنشوراتها في خدمة النشر زي ما هي.', $w['name']));
            }
            break;
    }
    redirect(url('admin_workspaces', array_filter(['q' => $_GET['q'] ?? null])));
}

$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT w.*,
    (SELECT COUNT(*) FROM workspace_users wu WHERE wu.workspace_id = w.id AND wu.status = 'active') members,
    (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM workspace_users wu JOIN users u ON u.id = wu.user_id WHERE wu.workspace_id = w.id AND wu.role = 'owner' AND wu.status = 'active') owners,
    (SELECT COUNT(*) FROM connectors c WHERE c.workspace_id = w.id) conns,
    (SELECT MAX(a.created_at) FROM activity_log a WHERE a.workspace_id = w.id) last_activity,
    EXISTS(SELECT 1 FROM workspace_users wu WHERE wu.workspace_id = w.id AND wu.user_id = ? AND wu.status = 'active') am_member
    FROM workspaces w WHERE COALESCE(w.status, 'active') <> 'deleted'" . ($q !== '' ? " AND w.name LIKE ?" : '') . " ORDER BY w.name";
$st = $pdo->prepare($sql);
$st->execute($q !== '' ? [$me['id'], '%' . $q . '%'] : [$me['id']]);
$list = $st->fetchAll();
$users = $pdo->query("SELECT id, name, email FROM users WHERE status = 'active' ORDER BY name")->fetchAll();

render(t('الشركات'), 'admin_workspaces', function () use ($list, $users, $q, $me) {
    ?>
    <div class="flex flex-wrap items-center gap-3 mb-5">
      <form method="get" class="flex gap-2 flex-1 min-w-[16rem]">
        <input type="hidden" name="p" value="admin_workspaces">
        <input type="search" name="q" value="<?= e($q) ?>" class="input !mt-0 flex-1" placeholder="<?= e(t('دور على شركة...')) ?>">
        <button class="btn-ghost"><?= e(t('بحث')) ?></button>
      </form>
      <details class="relative" data-dropdown>
        <summary class="btn-primary cursor-pointer"><?= e(t('+ شركة جديدة')) ?></summary>
        <form method="post" class="menu end-0 mt-2 !w-80 p-4 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="create">
          <label class="block"><span class="label"><?= e(t('اسم الشركة')) ?></span><input name="name" required class="input"></label>
          <label class="block"><span class="label"><?= e(t('صاحب الشركة')) ?></span>
            <select name="owner" class="input"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === (int)$me['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
          <button class="btn-primary w-full"><?= e(t('إنشاء')) ?></button>
        </form>
      </details>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-ink-500 text-xs border-b border-ink-200">
          <tr class="text-start">
            <th class="text-start font-medium px-5 py-3"><?= e(t('الشركة')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('أصحاب الشركة')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('الأعضاء')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('الاتصالات')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('آخر نشاط')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('الحالة')) ?></th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $w): $susp = ($w['status'] ?? 'active') !== 'active'; ?>
          <tr class="border-b border-ink-100 last:border-0 align-top">
            <td class="px-5 py-3">
              <div class="font-medium text-ink-900"><?= e($w['name']) ?></div>
              <div class="text-xs text-ink-400"><?= e(t('اتعملت %s', substr((string)$w['created_at'], 0, 10))) ?></div>
            </td>
            <td class="px-3 py-3 text-ink-700"><?= e($w['owners'] ?: '—') ?></td>
            <td class="px-3 py-3"><?= (int)$w['members'] ?></td>
            <td class="px-3 py-3"><?= (int)$w['conns'] ? '<span class="badge badge-green">' . (int)$w['conns'] . '</span>' : '<span class="badge bg-red-50 text-red-600">' . e(t('مش مربوطة')) . '</span>' ?></td>
            <td class="px-3 py-3 text-ink-500 text-xs"><?= e($w['last_activity'] ? substr((string)$w['last_activity'], 0, 16) : '—') ?></td>
            <td class="px-3 py-3"><span class="badge <?= $susp ? 'bg-red-50 text-red-600' : 'badge-green' ?>"><?= e($susp ? t('موقوفة') : t('شغالة')) ?></span></td>
            <td class="px-5 py-3">
              <div class="flex items-center justify-end gap-2">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="enter"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                  <button class="btn-ghost !py-1 text-xs" title="<?= e($w['am_member'] ? '' : t('هتتضاف كصاحب شركة، والدخول بيتسجل')) ?>"><?= e(t('ادخل')) ?></button></form>
                <details class="relative" data-dropdown>
                  <summary class="btn-ghost !py-1 text-xs cursor-pointer">⋯</summary>
                  <div class="menu end-0 mt-2 !w-72 p-3 space-y-3">
                    <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                      <input name="name" value="<?= e($w['name']) ?>" class="input !mt-0 text-xs flex-1"><button class="btn-ghost text-xs"><?= e(t('تغيير')) ?></button></form>
                    <?php if (function_exists('apify_cap')): ?><form method="post" class="flex gap-2 items-center"><?= csrf_field() ?><input type="hidden" name="action" value="apify_cap"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                      <span class="text-xs text-ink-500 shrink-0"><?= e(t('حد Apify الشهري')) ?> (<?= number_format(apify_used((int)$w['id'])) ?>)</span><input name="cap" type="number" min="0" step="100" value="<?= apify_cap((int)$w['id']) ?>" class="input !mt-0 text-xs flex-1" dir="ltr"><button class="btn-ghost text-xs"><?= e(t('حفظ')) ?></button></form><?php endif; ?>
                    <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="owner"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                      <select name="user_id" class="input !mt-0 text-xs flex-1"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?></select>
                      <button class="btn-ghost text-xs"><?= e(t('اعمله صاحب')) ?></button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $susp ? 'activate' : 'suspend' ?>"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                      <button class="menu-item <?= $susp ? '' : 'text-amber-700' ?>"><?= e($susp ? t('تشغيل الشركة') : t('إيقاف الشركة')) ?></button></form>
                    <form method="post" class="border-t border-ink-100 pt-3 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                      <input name="confirm" class="input !mt-0 text-xs" placeholder="<?= e(t('اكتب: %s', $w['name'])) ?>">
                      <button class="btn-danger-ghost !py-1 !px-2.5 !text-xs"><?= e(t('حذف نهائي')) ?></button></form>
                  </div>
                </details>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7" class="px-5 py-8 text-center text-ink-500"><?= e(t('مفيش شركات.')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
});
