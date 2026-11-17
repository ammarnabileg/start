<?php
/* نسيت كلمة السر: إيميل فيه رابط إعادة تعيين صالح ساعة واحدة ولمرة واحدة */
if (current_user()) after_login_redirect();

$sent = false; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (too_many_attempts($pdo)) {
        flash('error', t('محاولات كثيرة. انتظر 15 دقيقة ثم حاول مرة أخرى.'));
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', t('اكتب بريدًا إلكترونيًا صحيحًا.'));
    } else {
        record_failed_attempt($pdo); // كل طلب بيتحسب: يمنع إغراق الإيميلات وتجربة العناوين
        $st = $pdo->prepare("SELECT id, name, email, status FROM users WHERE email = ?"); $st->execute([$email]); $u = $st->fetch();
        if ($u && ($u['status'] ?? 'active') === 'active') {
            $tok = bin2hex(random_bytes(32));
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')->execute([(int)$u['id']]);
            $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))')->execute([(int)$u['id'], hash('sha256', $tok)]);
            $link = base_url() . '/' . url('reset', ['t' => $tok]);
            $html = '<div style="font-family:Tahoma,Arial,sans-serif;font-size:15px;line-height:1.8;color:#0f172a" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '">'
                . '<p>' . e(t('مرحبًا %s،', (string)$u['name'])) . '</p>'
                . '<p>' . e(t('وصلنا طلب لإعادة تعيين كلمة السر لحسابك. اضغط الزر لاختيار كلمة سر جديدة. الرابط صالح لمدة ساعة ولمرة واحدة.')) . '</p>'
                . '<p><a href="' . e($link) . '" style="display:inline-block;background:#2557e0;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none">' . e(t('إعادة تعيين كلمة السر')) . '</a></p>'
                . '<p style="color:#64748b;font-size:13px">' . e(t('إن لم تطلب ذلك فتجاهل هذه الرسالة، ولن يتغير شيء في حسابك.')) . '</p></div>';
            try { send_mail((string)$u['email'], t('إعادة تعيين كلمة السر'), $html, smtp_platform_config()); log_activity('auth.reset_request', (string)$u['email']); }
            catch (Throwable $e) { error_log('reset mail: ' . $e->getMessage()); }
        }
        $sent = true; // نفس الرد سواء الإيميل موجود أو لأ
    }
}

auth_page(t('نسيت كلمة السر'), function () use ($sent, $email) {
    if ($sent): ?>
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-7 text-emerald-900">
        <?= e(t('إن كان هذا البريد مسجّلًا لدينا، فستصلك رسالة خلال دقائق فيها رابط لإعادة تعيين كلمة السر. تفقّد أيضًا مجلد الرسائل غير المرغوبة.')) ?>
      </div>
      <a href="<?= e(url('login')) ?>" class="btn-ghost w-full mt-4 justify-center"><?= e(t('العودة لتسجيل الدخول')) ?></a>
    <?php else: ?>
      <p class="text-sm text-ink-600 mb-4 leading-7"><?= e(t('اكتب بريدك الإلكتروني وسنرسل لك رابطًا لاختيار كلمة سر جديدة.')) ?></p>
      <form method="post" class="space-y-5">
        <?= csrf_field() ?>
        <?= auth_input('email', t('الإيميل'), 'email', $email, true) ?>
        <button class="btn-primary w-full !py-3"><?= e(t('أرسل رابط إعادة التعيين')) ?></button>
      </form>
      <p class="text-center text-sm text-ink-500 mt-4"><a href="<?= e(url('login')) ?>" class="link"><?= e(t('العودة لتسجيل الدخول')) ?></a></p>
    <?php endif;
});
