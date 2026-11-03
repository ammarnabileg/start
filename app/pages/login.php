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
        flash('error', t('محاولات كتير غلط. استنى 15 دقيقة وجرب تاني.'));
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($pass, $u['password_hash']) && ($u['status'] ?? 'active') !== 'active') {
            flash('error', t('الحساب ده موقوف. كلم مدير النظام.'));
        } elseif ($u && password_verify($pass, $u['password_hash']) && setting('maintenance', '0') === '1' && $u['role'] !== 'admin') {
            flash('error', t('المنصة في وضع الصيانة دلوقتي. جرب بعد شوية.'));
        } elseif ($u && password_verify($pass, $u['password_hash'])) {
            clear_attempts($pdo);
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$u['id']]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            log_activity('login');
            after_login_redirect();
        }
        else {
            record_failed_attempt($pdo);
            flash('error', t('الإيميل أو كلمة السر غلط.'));
        }
    }
}

$canRegister = setting('allow_signup', '1') === '1' || !empty($_SESSION['invite_token']);
auth_page(t('سجّل دخولك'), function () use ($email, $canRegister) {
    ?>
    <form method="post" class="space-y-5">
      <?= csrf_field() ?>
      <?= auth_input('email', t('الإيميل'), 'email', $email, true) ?>
      <label class="block"><span class="text-sm font-semibold"><?= e(t('كلمة السر')) ?></span>
        <input type="password" name="password" required class="input" dir="ltr"></label>
      <button class="btn-primary w-full !py-3"><?= e(t('دخول')) ?></button>
    </form>
    <?php if ($canRegister): ?>
      <p class="text-center text-sm text-slate-500"><?= e(t('معندكش حساب؟')) ?> <a href="<?= e(url('register')) ?>" class="link"><?= e(t('اعمل حساب')) ?></a></p>
    <?php endif;
});
