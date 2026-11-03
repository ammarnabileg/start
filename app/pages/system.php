<?php
/** إدارة المنصة: إعدادات المنصة (لمدير النظام) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim((string)($_POST['site_name'] ?? ''));
    if ($siteName !== '') save_setting('site_name', mb_substr($siteName, 0, 100));
    save_setting('default_lang', ($_POST['default_lang'] ?? '') === 'ar' ? 'ar' : 'en');
    save_setting('default_theme', ($_POST['default_theme'] ?? '') === 'dark' ? 'dark' : 'light');
    save_setting('allow_signup', isset($_POST['allow_signup']) ? '1' : '0');
    save_setting('maintenance', isset($_POST['maintenance']) ? '1' : '0');
    save_setting('maintenance_message', mb_substr(trim((string)($_POST['maintenance_message'] ?? '')), 0, 500));
    save_setting('announcement', mb_substr(trim((string)($_POST['announcement'] ?? '')), 0, 1000));
    save_setting('announcement_type', ($_POST['announcement_type'] ?? '') === 'warning' ? 'warning' : 'info');
    log_activity('system.update_settings');
    flash('success', t('اتحفظ.'));
    redirect(url('system'));
}

render(t('إعدادات المنصة'), 'system', function () {
    $sel = function ($name, $value, $current) { return $value === $current ? 'selected' : ''; };
    ?>
    <form method="post" class="grid lg:grid-cols-2 gap-5 max-w-5xl">
      <?= csrf_field() ?>
      <div class="card p-6 space-y-4">
        <h2 class="font-semibold text-ink-900"><?= e(t('الهوية والافتراضيات')) ?></h2>
        <label class="block"><span class="label"><?= e(t('اسم المنصة')) ?></span>
          <input name="site_name" value="<?= e(setting('site_name', '')) ?>" class="input"></label>
        <div class="grid grid-cols-2 gap-3">
          <label class="block"><span class="label"><?= e(t('اللغة الافتراضية')) ?></span>
            <select name="default_lang" class="input"><option value="en" <?= $sel('', 'en', setting('default_lang', 'en')) ?>>English</option><option value="ar" <?= $sel('', 'ar', setting('default_lang', 'en')) ?>>العربية</option></select></label>
          <label class="block"><span class="label"><?= e(t('الوضع الافتراضي')) ?></span>
            <select name="default_theme" class="input"><option value="light" <?= $sel('', 'light', setting('default_theme', 'light')) ?>><?= e(t('فاتح')) ?></option><option value="dark" <?= $sel('', 'dark', setting('default_theme', 'light')) ?>><?= e(t('داكن')) ?></option></select></label>
        </div>
        <p class="text-xs text-ink-500"><?= e(t('بيتطبق على أي حد لسه ما اختارش لغة أو وضع بنفسه.')) ?></p>
      </div>

      <div class="card p-6 space-y-4">
        <h2 class="font-semibold text-ink-900"><?= e(t('الدخول والتسجيل')) ?></h2>
        <label class="flex items-start gap-3">
          <input type="checkbox" name="allow_signup" value="1" <?= setting('allow_signup', '1') === '1' ? 'checked' : '' ?> class="mt-1 w-4 h-4">
          <span><span class="font-medium text-sm"><?= e(t('أي حد يقدر يعمل حساب')) ?></span>
          <span class="block text-xs text-ink-500"><?= e(t('لو قفلتها، محدش يقدر يسجل غير اللي معاه لينك دعوة.')) ?></span></span>
        </label>
        <label class="flex items-start gap-3">
          <input type="checkbox" name="maintenance" value="1" <?= setting('maintenance', '0') === '1' ? 'checked' : '' ?> class="mt-1 w-4 h-4">
          <span><span class="font-medium text-sm"><?= e(t('وضع الصيانة')) ?></span>
          <span class="block text-xs text-ink-500"><?= e(t('المنصة بتتقفل على الكل ما عدا مديري النظام. استخدمه وقت التحديثات.')) ?></span></span>
        </label>
        <label class="block"><span class="label"><?= e(t('رسالة الصيانة (اختياري)')) ?></span>
          <input name="maintenance_message" value="<?= e(setting('maintenance_message', '')) ?>" class="input" placeholder="<?= e(t('المنصة في وضع الصيانة دلوقتي. جرب بعد شوية.')) ?>"></label>
      </div>

      <div class="card p-6 space-y-4 lg:col-span-2">
        <h2 class="font-semibold text-ink-900"><?= e(t('رسالة عامة لكل المستخدمين')) ?></h2>
        <textarea name="announcement" rows="3" class="input" placeholder="<?= e(t('مثلاً: هيكون فيه تحديث يوم الجمعة الساعة 10 بالليل.')) ?>"><?= e(setting('announcement', '')) ?></textarea>
        <div class="flex flex-wrap items-center gap-4 text-sm">
          <label class="flex items-center gap-2"><input type="radio" name="announcement_type" value="info" <?= setting('announcement_type', 'info') !== 'warning' ? 'checked' : '' ?>> <?= e(t('معلومة')) ?></label>
          <label class="flex items-center gap-2"><input type="radio" name="announcement_type" value="warning" <?= setting('announcement_type', 'info') === 'warning' ? 'checked' : '' ?>> <?= e(t('تنبيه')) ?></label>
          <span class="text-xs text-ink-500"><?= e(t('بتظهر فوق كل الصفحات. امسح النص عشان تخفيها.')) ?></span>
        </div>
      </div>

      <div class="lg:col-span-2 flex items-center gap-3">
        <button class="btn-primary !px-8"><?= e(t('حفظ')) ?></button>
        <a href="setup.php" class="btn-ghost"><?= e(t('صفحة التحديث')) ?></a>
      </div>
    </form>
    <?php
});
