<?php
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'editor';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('error', 'اكتب الاسم وإيميل صحيح وكلمة سر 8 حروف على الأقل.');
        } else {
            try {
                $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)")
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
                log_activity('user.add', $email);
                flash('success', 'تمت إضافة ' . $name . '.');
            } catch (PDOException $ex) {
                flash('error', 'الإيميل ده مستخدم قبل كده.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$me['id']) {
            flash('error', 'مينفعش تحذف نفسك.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            log_activity('user.delete', (string)$id);
            flash('success', 'تم حذف المستخدم.');
        }
    } elseif ($action === 'password') {
        $id = (int)($_POST['id'] ?? 0);
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 8) {
            flash('error', 'كلمة السر لازم تكون 8 حروف على الأقل.');
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            log_activity('user.password', (string)$id);
            flash('success', 'تم تغيير كلمة السر.');
        }
    }
    redirect(url('users'));
}

$users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id")->fetchAll();

render('الفريق', 'users', function () use ($users, $me) {
    ?>
    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl">
        <?php foreach ($users as $u): ?>
          <div class="p-5 border-b last:border-0 border-slate-100">
            <div class="flex items-center gap-4">
              <?= avatar(null, $u['name']) ?>
              <div class="flex-1 min-w-0">
                <div class="font-semibold"><?= e($u['name']) ?> <?= (int)$u['id'] === (int)$me['id'] ? '<span class="text-xs text-slate-400">(أنت)</span>' : '' ?></div>
                <div class="text-xs text-slate-500" dir="ltr"><?= e($u['email']) ?></div>
              </div>
              <span class="text-xs rounded-full px-2 py-1 <?= $u['role'] === 'admin' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100' ?>"><?= $u['role'] === 'admin' ? 'مدير' : 'محرر' ?></span>
              <?php if ((int)$u['id'] !== (int)$me['id']): ?>
              <form method="post" onsubmit="return confirm('حذف <?= e($u['name']) ?>؟')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="text-sm text-red-600 hover:bg-red-50 rounded-lg px-3 py-1.5">حذف</button></form>
              <?php endif; ?>
            </div>
            <form method="post" class="flex gap-2 mt-3 mr-14"><?= csrf_field() ?>
              <input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="password" name="password" minlength="8" placeholder="كلمة سر جديدة" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm flex-1 max-w-xs" required>
              <button class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-50">تغيير</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 space-y-3 h-fit">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <h2 class="font-bold">إضافة عضو للفريق</h2>
        <input name="name" placeholder="الاسم" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <input name="email" type="email" placeholder="الإيميل" required class="w-full border border-slate-300 rounded-lg px-3 py-2" dir="ltr">
        <input name="password" type="password" minlength="8" placeholder="كلمة السر (8 حروف على الأقل)" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <select name="role" class="w-full border border-slate-300 rounded-lg px-3 py-2">
          <option value="editor">محرر: ينشر ويرد على الرسائل</option>
          <option value="admin">مدير: كل الصلاحيات + الإعدادات والفريق</option>
        </select>
        <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-2.5">إضافة</button>
      </form>
    </div>
    <?php
});
