<?php
/** إعدادات النظام (لمدير النظام اللي سطّب البرنامج) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim((string)($_POST['site_name'] ?? ''));
    if ($siteName !== '') save_setting('site_name', mb_substr($siteName, 0, 100));
    save_setting('allow_signup', isset($_POST['allow_signup']) ? '1' : '0');
    log_activity('system.update_settings');
    flash('success', t('اتحفظ.'));
    redirect(url('system'));
}

$stats = db()->query("SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM workspaces) AS workspaces")->fetch();

render(t('إعدادات النظام'), 'system', function () use ($stats) {
    $in = 'mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5';
    ?>
    <form method="post" class="max-w-xl bg-white border border-slate-200 rounded-xl p-6 space-y-4">
      <?= csrf_field() ?>
      <p class="text-sm text-slate-500"><?= e(t('على البرنامج:')) ?> <?= (int)$stats['users'] ?> <?= e(t('مستخدم ·')) ?> <?= (int)$stats['workspaces'] ?> <?= e(t('شركة · الإصدار')) ?> <?= e(APP_VERSION) ?></p>
      <label class="block"><span class="text-sm font-semibold"><?= e(t('اسم الموقع')) ?></span><input name="site_name" value="<?= e(setting('site_name', '')) ?>" class="<?= $in ?>"></label>
      <label class="flex items-start gap-3">
        <input type="checkbox" name="allow_signup" value="1" <?= setting('allow_signup', '1') === '1' ? 'checked' : '' ?> class="mt-1 w-4 h-4 accent-indigo-600">
        <span><span class="font-semibold text-sm"><?= e(t('أي حد يقدر يعمل حساب')) ?></span>
        <span class="block text-xs text-slate-500"><?= e(t('لو قفلتها، محدش يقدر يسجل غير اللي معاه لينك دعوة.')) ?></span></span>
      </label>
      <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3 rounded-lg"><?= e(t('حفظ')) ?></button>
      <p class="text-xs text-slate-500 border-t border-slate-100 pt-4"><?= e(t('مدير النظام مش بيشوف شركات الناس. بيشوف بس الشركات اللي هو عضو فيها، زي أي حد.')) ?></p>
    </form>
    <?php
});
