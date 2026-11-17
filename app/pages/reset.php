<?php
/* اختيار كلمة سر جديدة من رابط الإيميل */
if (current_user()) after_login_redirect();

$pdo = db();
$tok = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? $_POST['t'] ?? ''));
$row = null;
if (strlen($tok) === 64) {
    $st = $pdo->prepare('SELECT r.id, r.user_id, u.status FROM password_resets r JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW()');
    $st->execute([hash('sha256', $tok)]); $row = $st->fetch() ?: null;
    if ($row && ($row['status'] ?? 'active') !== 'active') $row = null;
}

if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if (mb_strlen($p1) < 8) flash('error', t('كلمة السر لازم تكون 8 حروف على الأقل.'));
    elseif ($p1 !== $p2) flash('error', t('كلمتا السر غير متطابقتين.'));
    else {
        // الاستخدام ذرّي: الرابط بيتحرق قبل تغيير كلمة السر عشان مايتستخدمش مرتين
        $u = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL'); $u->execute([(int)$row['id']]);
        if ($u->rowCount()) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($p1, PASSWORD_DEFAULT), (int)$row['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?')->execute([(int)$row['user_id'], (int)$row['id']]);
            $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, 'auth.password_reset')")->execute([(int)$row['user_id']]);
            clear_attempts($pdo);
            flash('success', t('تم تغيير كلمة السر. سجّل دخولك بالكلمة الجديدة.'));
            redirect(url('login'));
        }
        $row = null;
    }
}

auth_page(t('كلمة سر جديدة'), function () use ($row, $tok) {
    if (!$row): ?>
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-900"><?= e(t('هذا الرابط غير صالح أو انتهت صلاحيته أو استُخدم من قبل. اطلب رابطًا جديدًا.')) ?></div>
      <a href="<?= e(url('forgot')) ?>" class="btn-primary w-full mt-4 justify-center"><?= e(t('اطلب رابطًا جديدًا')) ?></a>
    <?php else: ?>
      <form method="post" class="space-y-5">
        <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($tok) ?>">
        <label class="block"><span class="text-sm font-semibold"><?= e(t('كلمة السر الجديدة')) ?></span>
          <input type="password" name="password" required minlength="8" autocomplete="new-password" class="input" dir="ltr"></label>
        <label class="block"><span class="text-sm font-semibold"><?= e(t('أعد كتابة كلمة السر')) ?></span>
          <input type="password" name="password2" required minlength="8" autocomplete="new-password" class="input" dir="ltr"></label>
        <button class="btn-primary w-full !py-3"><?= e(t('احفظ كلمة السر')) ?></button>
      </form>
    <?php endif;
});
