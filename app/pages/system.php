<?php
/** إدارة المنصة: إعدادات المنصة (لمدير النظام) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['smtp_save', 'smtp_test'], true)) {
    $host = trim((string)($_POST['smtp_host'] ?? ''));
    save_setting('smtp_host', mb_substr($host, 0, 190));
    save_setting('smtp_port', (string)max(1, min(65535, (int)($_POST['smtp_port'] ?? 587))));
    save_setting('smtp_secure', in_array($_POST['smtp_secure'] ?? '', ['ssl', 'tls', 'none'], true) ? $_POST['smtp_secure'] : 'tls');
    save_setting('smtp_user', mb_substr(trim((string)($_POST['smtp_user'] ?? '')), 0, 190));
    $pw = (string)($_POST['smtp_pass'] ?? '');
    if ($pw !== '') save_setting('smtp_pass', encrypt_secret($pw)); // فاضي = سيب القديم
    $from = trim((string)($_POST['smtp_from'] ?? ''));
    save_setting('smtp_from', filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : '');
    save_setting('smtp_from_name', mb_substr(trim((string)($_POST['smtp_from_name'] ?? '')), 0, 120));
    log_activity('system.smtp_save', $host);
    if ($_POST['action'] === 'smtp_test') {
        $to = trim((string)($_POST['test_to'] ?? '')) ?: (string)(current_user()['email'] ?? '');
        try {
            send_mail($to, t('تجربة إيميل من %s', (string)setting('site_name', '')),
                '<div style="font-family:sans-serif;padding:20px"><h2>✅ ' . e(t('إعدادات الإيميل شغالة')) . '</h2><p>' . e(t('لو الرسالة دي وصلتك، يبقى التقارير والتنبيهات هتوصل كمان.')) . '</p></div>');
            flash('success', t('اتبعت إيميل تجريبي لـ %s. لو ما لقيتوش، بص في الـ Spam.', $to));
        } catch (MailException $ex) {
            flash('error', t('فشل الإرسال: %s', $ex->getMessage()));
        }
    } else {
        flash('success', t('اتحفظت إعدادات الإيميل.'));
    }
    redirect(url('system') . '#smtp');
}

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

    <!-- إعدادات الإيميل (SMTP) -->
    <?php $sm = smtp_config(); ?>
    <form method="post" id="smtp" class="card p-6 space-y-4 max-w-5xl mt-5">
      <?= csrf_field() ?>
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1"><h2 class="font-semibold text-ink-900"><?= e(t('إعدادات الإيميل (SMTP)')) ?></h2>
          <p class="text-xs text-ink-500"><?= e(t('بيستخدمه البرنامج لإرسال التقارير والتنبيهات. لو سبته فاضي، الإيميلات بتتبعت من الاستضافة مباشرة وممكن توصل الـ Spam.')) ?></p></div>
        <span class="badge <?= $sm ? 'badge-green' : '' ?>"><?= e($sm ? t('متظبط') : t('مش متظبط')) ?></span>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="block lg:col-span-2"><span class="label"><?= e(t('سيرفر SMTP')) ?></span>
          <input name="smtp_host" value="<?= e((string)setting('smtp_host', '')) ?>" class="input" dir="ltr" placeholder="smtp.gmail.com / mail.yourdomain.com"></label>
        <label class="block"><span class="label"><?= e(t('البورت')) ?></span>
          <input name="smtp_port" type="number" value="<?= e((string)setting('smtp_port', '587')) ?>" class="input" dir="ltr"></label>
        <label class="block"><span class="label"><?= e(t('التشفير')) ?></span>
          <select name="smtp_secure" class="input" id="smtpSecure">
            <?php $sec = (string)setting('smtp_secure', 'tls'); foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => t('من غير تشفير (25)')] as $k => $l): ?>
              <option value="<?= $k ?>" <?= $sec === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select></label>
        <label class="block lg:col-span-2"><span class="label"><?= e(t('اسم المستخدم')) ?></span>
          <input name="smtp_user" value="<?= e((string)setting('smtp_user', '')) ?>" class="input" dir="ltr" autocomplete="off" placeholder="reports@yourdomain.com"></label>
        <label class="block lg:col-span-2"><span class="label"><?= e(t('كلمة السر')) ?></span>
          <input name="smtp_pass" type="password" class="input" dir="ltr" autocomplete="new-password" placeholder="<?= e(setting('smtp_pass', '') ? t('•••••••••• متخزن (اكتب جديد لو عاوز تغيره)') : '') ?>"></label>
        <label class="block lg:col-span-2"><span class="label"><?= e(t('إيميل المرسل')) ?></span>
          <input name="smtp_from" type="email" value="<?= e((string)setting('smtp_from', '')) ?>" class="input" dir="ltr" placeholder="reports@yourdomain.com"></label>
        <label class="block lg:col-span-2"><span class="label"><?= e(t('اسم المرسل')) ?></span>
          <input name="smtp_from_name" value="<?= e((string)setting('smtp_from_name', '')) ?>" class="input" placeholder="<?= e((string)setting('site_name', '')) ?>"></label>
      </div>
      <details class="text-xs text-ink-500">
        <summary class="cursor-pointer link"><?= e(t('بجيب البيانات دي منين؟')) ?></summary>
        <ul class="list-disc ps-5 mt-2 space-y-1">
          <li><?= e(t('إيميل على نفس الاستضافة (cPanel): السيرفر mail.دومينك، البورت 465 SSL، واسم المستخدم هو الإيميل كامل وكلمة سره.')) ?></li>
          <li><?= e(t('Gmail / Google Workspace: السيرفر smtp.gmail.com، البورت 587 TLS، وكلمة السر لازم تكون App Password مش كلمة سر الحساب.')) ?></li>
          <li><?= e(t('Outlook / Microsoft 365: السيرفر smtp.office365.com، البورت 587 TLS.')) ?></li>
        </ul>
      </details>
      <div class="flex flex-wrap items-end gap-3 pt-2 border-t border-ink-100">
        <button name="action" value="smtp_save" class="btn-primary"><?= e(t('حفظ')) ?></button>
        <label class="block flex-1 min-w-[14rem]"><span class="label"><?= e(t('ابعت تجربة لـ')) ?></span>
          <input name="test_to" type="email" value="<?= e((string)(current_user()['email'] ?? '')) ?>" class="input" dir="ltr"></label>
        <button name="action" value="smtp_test" class="btn-ghost"><?= e(t('حفظ وإرسال تجربة')) ?></button>
      </div>
      <script>
        document.getElementById('smtpSecure').addEventListener('change', function () {
          var p = document.querySelector('[name=smtp_port]'); var map = {tls: 587, ssl: 465, none: 25};
          if (['587', '465', '25', ''].indexOf(p.value) > -1) p.value = map[this.value];
        });
      </script>
    </form>
    <?php
});
