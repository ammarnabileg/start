<?php
/** إدارة المنصة: كل المستخدمين (لمدير النظام) */
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $back = url('admin_users', array_filter(['q' => $_GET['q'] ?? null, 'f' => $_GET['f'] ?? null]));

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $wsId = (int)($_POST['workspace'] ?? 0);
        $role = ($_POST['ws_role'] ?? '') === 'owner' ? 'owner' : 'editor';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('error', t('اكتب الاسم وإيميل صحيح وكلمة سر 8 حروف على الأقل.'));
        } else {
            try {
                $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)")
                    ->execute([mb_substr($name, 0, 120), $email, password_hash($pass, PASSWORD_DEFAULT), !empty($_POST['admin']) ? 'admin' : 'user']);
                $uid = (int)$pdo->lastInsertId();
                if ($wsId) {
                    $pdo->prepare("INSERT IGNORE INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, ?, 'active')")->execute([$wsId, $uid, $role]);
                }
                log_activity('admin.user_create', $email);
                flash('success', t('اتعمل حساب %s.', $email));
            } catch (PDOException $ex) {
                flash('error', t('الإيميل ده مستخدم قبل كده.'));
            }
        }
        redirect($back);
    }

    $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) redirect($back);
    $self = (int)$u['id'] === (int)$me['id'];

    switch ($action) {
        case 'admin':
        case 'unadmin':
            if ($self) { flash('error', t('مينفعش تغيّر صلاحيتك انت.')); break; }
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$action === 'admin' ? 'admin' : 'user', $id]);
            log_activity('admin.user_' . $action, $u['email']);
            flash('success', $action === 'admin' ? t('%s بقى مدير نظام.', $u['name']) : t('%s مبقاش مدير نظام.', $u['name']));
            break;
        case 'suspend':
        case 'activate':
            if ($self) { flash('error', t('مينفعش توقف حسابك انت.')); break; }
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$action === 'suspend' ? 'suspended' : 'active', $id]);
            log_activity('admin.user_' . $action, $u['email']);
            flash('success', $action === 'suspend' ? t('الحساب اتوقف، وهيخرج من البرنامج فوراً.') : t('الحساب رجع يشتغل.'));
            break;
        case 'password':
            $pass = (string)($_POST['password'] ?? '');
            if (strlen($pass) < 8) { flash('error', t('كلمة السر لازم تكون 8 حروف على الأقل.')); break; }
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            log_activity('admin.user_password', $u['email']);
            flash('success', t('اتغيرت كلمة سر %s.', $u['name']));
            break;
        case 'impersonate':
            if ($self || ($u['status'] ?? 'active') !== 'active') break;
            log_activity('admin.impersonate', $u['email']);
            $_SESSION['impersonator'] = (int)$me['id'];
            unset($_SESSION['ws']);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            redirect(url('dashboard'));
            break;
        case 'delete':
            if ($self) { flash('error', t('مينفعش تحذف حسابك انت.')); break; }
            // لو هو صاحب الشركة الوحيد في شركة، منحذفوش عشان الشركة ماتبقاش من غير صاحب
            $orph = $pdo->prepare("SELECT w.name FROM workspace_users wu JOIN workspaces w ON w.id = wu.workspace_id WHERE wu.user_id = ? AND wu.role = 'owner' AND wu.status = 'active'
                AND (SELECT COUNT(*) FROM workspace_users x WHERE x.workspace_id = wu.workspace_id AND x.role = 'owner' AND x.status = 'active') = 1");
            $orph->execute([$id]);
            $names = $orph->fetchAll(PDO::FETCH_COLUMN);
            if ($names) {
                flash('error', t('مينفعش يتحذف لأنه صاحب الشركة الوحيد في: %s. اعمل حد تاني صاحب الأول من تاب الشركات.', implode('، ', $names)));
                break;
            }
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            log_activity('admin.user_delete', $u['email']);
            flash('success', t('اتحذف حساب %s.', $u['email']));
            break;
    }
    redirect($back);
}

$q = trim((string)($_GET['q'] ?? ''));
$f = (string)($_GET['f'] ?? '');
$where = [];
$args = [];
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($f === 'admin') $where[] = "u.role = 'admin'";
if ($f === 'suspended') $where[] = "u.status <> 'active'";
if ($f === 'inactive') $where[] = "(u.last_login_at IS NULL OR u.last_login_at < NOW() - INTERVAL 30 DAY)";
$st = $pdo->prepare("SELECT u.*, (SELECT GROUP_CONCAT(CONCAT(w.name, IF(wu.role = 'owner', ' ★', '')) ORDER BY w.name SEPARATOR ', ') FROM workspace_users wu JOIN workspaces w ON w.id = wu.workspace_id WHERE wu.user_id = u.id AND wu.status = 'active') ws
    FROM users u" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY u.created_at DESC");
$st->execute($args);
$list = $st->fetchAll();
$workspaces = $pdo->query("SELECT id, name FROM workspaces ORDER BY name")->fetchAll();

render(t('المستخدمين'), 'admin_users', function () use ($list, $workspaces, $q, $f, $me) {
    $filters = ['' => t('الكل'), 'admin' => t('مديرين النظام'), 'suspended' => t('موقوفين'), 'inactive' => t('مدخلوش من 30 يوم')];
    ?>
    <div class="flex flex-wrap items-center gap-3 mb-5">
      <form method="get" class="flex gap-2 flex-1 min-w-[16rem]">
        <input type="hidden" name="p" value="admin_users"><?php if ($f): ?><input type="hidden" name="f" value="<?= e($f) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" class="input !mt-0 flex-1" placeholder="<?= e(t('دور بالاسم أو الإيميل...')) ?>">
        <button class="btn-ghost"><?= e(t('بحث')) ?></button>
      </form>
      <div class="seg">
        <?php foreach ($filters as $k => $l): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="<?= e(url('admin_users', array_filter(['f' => $k, 'q' => $q]))) ?>"><?= e($l) ?></a><?php endforeach; ?>
      </div>
      <details class="relative" data-dropdown>
        <summary class="btn-primary cursor-pointer"><?= e(t('+ مستخدم جديد')) ?></summary>
        <form method="post" class="menu end-0 mt-2 !w-80 p-4 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="create">
          <label class="block"><span class="label"><?= e(t('الاسم')) ?></span><input name="name" required class="input"></label>
          <label class="block"><span class="label"><?= e(t('الإيميل')) ?></span><input name="email" type="email" required class="input" dir="ltr"></label>
          <label class="block"><span class="label"><?= e(t('كلمة السر (8 حروف على الأقل)')) ?></span><input name="password" type="password" minlength="8" required class="input" dir="ltr"></label>
          <label class="block"><span class="label"><?= e(t('ضيفه على شركة (اختياري)')) ?></span>
            <select name="workspace" class="input"><option value="0">—</option><?php foreach ($workspaces as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></label>
          <select name="ws_role" class="input"><option value="editor"><?= e(t('محرر')) ?></option><option value="owner"><?= e(t('صاحب الشركة')) ?></option></select>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="admin" value="1"> <?= e(t('مدير نظام')) ?></label>
          <button class="btn-primary w-full"><?= e(t('إنشاء')) ?></button>
        </form>
      </details>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-ink-500 text-xs border-b border-ink-200">
          <tr>
            <th class="text-start font-medium px-5 py-3"><?= e(t('المستخدم')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('الشركات')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('آخر دخول')) ?></th>
            <th class="text-start font-medium px-3 py-3"><?= e(t('الحالة')) ?></th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $u): $self = (int)$u['id'] === (int)$me['id']; $susp = ($u['status'] ?? 'active') !== 'active'; ?>
          <tr class="border-b border-ink-100 last:border-0 align-top">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <?= avatar(null, $u['name'], 'w-9 h-9') ?>
                <div class="min-w-0">
                  <div class="font-medium text-ink-900"><?= e($u['name']) ?><?= $self ? ' <span class="text-xs text-ink-400">' . e(t('(أنت)')) . '</span>' : '' ?>
                    <?php if ($u['role'] === 'admin'): ?><span class="badge bg-brand-50 text-brand-700 ms-1"><?= e(t('مدير نظام')) ?></span><?php endif; ?></div>
                  <div class="text-xs text-ink-500" dir="ltr" style="text-align:start"><?= e($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td class="px-3 py-3 text-ink-700 text-xs max-w-xs"><?= e($u['ws'] ?: '—') ?></td>
            <td class="px-3 py-3 text-ink-500 text-xs"><?= e($u['last_login_at'] ? substr((string)$u['last_login_at'], 0, 16) : t('ولا مرة')) ?></td>
            <td class="px-3 py-3"><span class="badge <?= $susp ? 'bg-red-50 text-red-600' : 'badge-green' ?>"><?= e($susp ? t('موقوف') : t('شغال')) ?></span></td>
            <td class="px-5 py-3">
              <?php if (!$self): ?>
              <div class="flex items-center justify-end gap-2">
                <?php if (!$susp): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('هتدخل بحساب %s وتشوف البرنامج زيه بالظبط. العملية بتتسجل. تكمل؟', $u['name']) ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="impersonate"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn-ghost !py-1 text-xs"><?= e(t('ادخل كـ')) ?></button></form>
                <?php endif; ?>
                <details class="relative" data-dropdown>
                  <summary class="btn-ghost !py-1 text-xs cursor-pointer">⋯</summary>
                  <div class="menu end-0 mt-2 !w-72 p-3 space-y-2">
                    <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <input name="password" type="password" minlength="8" required class="input !mt-0 text-xs flex-1" placeholder="<?= e(t('كلمة سر جديدة')) ?>"><button class="btn-ghost text-xs"><?= e(t('تغيير')) ?></button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $u['role'] === 'admin' ? 'unadmin' : 'admin' ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="menu-item"><?= e($u['role'] === 'admin' ? t('شيل صلاحية مدير النظام') : t('اعمله مدير نظام')) ?></button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $susp ? 'activate' : 'suspend' ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="menu-item <?= $susp ? '' : 'text-amber-700' ?>"><?= e($susp ? t('تشغيل الحساب') : t('إيقاف الحساب')) ?></button></form>
                    <form method="post" onsubmit="return confirm('<?= tjs('حذف حساب %s نهائياً؟', $u['email']) ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="menu-item text-red-600"><?= e(t('حذف الحساب')) ?></button></form>
                  </div>
                </details>
              </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="5" class="px-5 py-8 text-center text-ink-500"><?= e(t('مفيش نتائج.')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
});
