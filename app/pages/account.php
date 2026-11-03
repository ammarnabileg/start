<?php
/** حسابي: الاسم وكلمة السر */
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $st = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $st->execute([$me['id']]);
    if (!password_verify($cur, (string)$st->fetchColumn())) {
        flash('error', t('كلمة السر الحالية غلط.'));
    } elseif ($new !== '' && strlen($new) < 8) {
        flash('error', t('كلمة السر الجديدة لازم تكون 8 حروف على الأقل.'));
    } else {
        $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name !== '' ? mb_substr($name, 0, 120) : $me['name'], $me['id']]);
        if ($new !== '') {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
        }
        log_activity('account.update');
        flash('success', t('اتحفظ.'));
    }
    redirect(url('account'));
}

render(t('حسابي'), 'account', function () use ($me) {
    $in = 'mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5';
    ?>
    <form method="post" class="max-w-md bg-white border border-slate-200 rounded-xl p-6 space-y-4">
      <?= csrf_field() ?>
      <p class="text-sm text-slate-500" dir="ltr"><?= e($me['email']) ?></p>
      <label class="block"><span class="text-sm font-semibold"><?= e(t('الاسم')) ?></span><input name="name" value="<?= e($me['name']) ?>" class="<?= $in ?>"></label>
      <label class="block"><span class="text-sm font-semibold"><?= e(t('كلمة سر جديدة (سيبها فاضية لو مش عاوز تغيرها)')) ?></span><input type="password" name="new" minlength="8" class="<?= $in ?>" dir="ltr"></label>
      <label class="block"><span class="text-sm font-semibold"><?= e(t('كلمة السر الحالية (للتأكيد)')) ?></span><input type="password" name="current" required class="<?= $in ?>" dir="ltr"></label>
      <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3 rounded-lg"><?= e(t('حفظ')) ?></button>
    </form>
    <?php
});
