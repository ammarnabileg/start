<?php
if (current_user()) {
    after_login_redirect();
}

$open = setting('allow_signup', '1') === '1' || !empty($_SESSION['invite_token']);
$old = ['name' => '', 'email' => ''];

if ($open && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $old['name'] = trim((string)($_POST['name'] ?? ''));
    $old['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $errors = [];
    if (too_many_attempts($pdo)) $errors[] = 'محاولات كتير. استنى 15 دقيقة.';
    if ($old['name'] === '') $errors[] = 'اكتب اسمك.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'الإيميل مش صحيح.';
    if (strlen($pass) < 8) $errors[] = 'كلمة السر لازم تكون 8 حروف على الأقل.';
    if ($pass !== (string)($_POST['password2'] ?? '')) $errors[] = 'كلمتين السر مش متطابقين.';
    if (!$errors) {
        $st = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
        $st->execute([$old['email']]);
        if ($st->fetchColumn()) {
            // منحاولش نقول "الإيميل موجود" بشكل يسهّل التخمين، بس لازم نوجهه للدخول
            record_failed_attempt($pdo);
            $errors[] = 'فيه حساب بالإيميل ده. سجل دخول بدل ما تعمل حساب جديد.';
        }
    }
    if (!$errors) {
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')")
            ->execute([mb_substr($old['name'], 0, 120), $old['email'], password_hash($pass, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();
        claim_email_invites($uid, $old['email']);
        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
        log_activity('register');
        if (empty($_SESSION['invite_token'])) {
            redirect(url('workspaces'));
        }
        after_login_redirect();
    }
    foreach ($errors as $m) flash('error', $m);
}

auth_page('حساب جديد', function () use ($open, $old) {
    if (!$open): ?>
      <p class="text-sm text-slate-600 text-center">التسجيل مقفول. اطلب لينك دعوة من صاحب الشركة.</p>
      <a href="<?= e(url('login')) ?>" class="block text-center text-indigo-700 font-semibold">رجوع للدخول</a>
    <?php return; endif; ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <?= auth_input('name', 'اسمك', 'text', $old['name']) ?>
      <?= auth_input('email', 'الإيميل', 'email', $old['email'], true) ?>
      <?= auth_input('password', 'كلمة السر (8 حروف على الأقل)', 'password', '', true) ?>
      <?= auth_input('password2', 'اكتب كلمة السر تاني', 'password', '', true) ?>
      <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-3">إنشاء الحساب</button>
    </form>
    <p class="text-center text-sm text-slate-500">عندك حساب؟ <a href="<?= e(url('login')) ?>" class="text-indigo-700 font-semibold">سجل دخول</a></p>
    <?php
});
