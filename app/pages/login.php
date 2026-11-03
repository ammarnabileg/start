<?php
if (current_user()) {
    after_login_redirect();
}

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if (too_many_attempts($pdo)) {
        flash('error', 'محاولات كتير غلط. استنى 15 دقيقة وجرب تاني.');
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            clear_attempts($pdo);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            log_activity('login');
            after_login_redirect();
        }
        record_failed_attempt($pdo);
        flash('error', 'الإيميل أو كلمة السر غلط.');
    }
}

$canRegister = setting('allow_signup', '1') === '1' || !empty($_SESSION['invite_token']);
auth_page('سجّل دخولك', function () use ($email, $canRegister) {
    ?>
    <form method="post" class="space-y-5">
      <?= csrf_field() ?>
      <?= auth_input('email', 'الإيميل', 'email', $email, true) ?>
      <label class="block"><span class="text-sm font-semibold">كلمة السر</span>
        <input type="password" name="password" required class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none" dir="ltr"></label>
      <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-3">دخول</button>
    </form>
    <?php if ($canRegister): ?>
      <p class="text-center text-sm text-slate-500">معندكش حساب؟ <a href="<?= e(url('register')) ?>" class="text-indigo-700 font-semibold">اعمل حساب</a></p>
    <?php endif;
});
