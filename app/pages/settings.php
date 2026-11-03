<?php
$profiles = [];
$profileError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim((string)($_POST['site_name'] ?? ''));
    $tz = (string)($_POST['timezone'] ?? '');
    $key = trim((string)($_POST['api_key'] ?? ''));
    $profile = trim((string)($_POST['profile_id'] ?? ''));

    if ($siteName !== '') save_setting('site_name', mb_substr($siteName, 0, 100));
    if (in_array($tz, timezone_identifiers_list(), true)) save_setting('timezone', $tz);
    save_setting('profile_id', $profile);

    if ($key !== '') {
        try {
            (new Zernio($key))->profiles(); // تجربة المفتاح قبل الحفظ
            save_setting('zernio_api_key', $key);
            flash('success', 'تم حفظ مفتاح Zernio واتأكدنا إنه شغال.');
        } catch (ZernioException $ex) {
            flash('error', 'المفتاح ما اتحفظش: ' . $ex->getMessage());
            redirect(url('settings'));
        }
    }
    log_activity('settings.update');
    flash('success', 'تم حفظ الإعدادات.');
    redirect(url('settings'));
}

if (Zernio::configured()) {
    try {
        $profiles = (new Zernio())->profiles();
    } catch (ZernioException $ex) {
        $profileError = $ex->getMessage();
    }
}

render('الإعدادات', 'settings', function () use ($profiles, $profileError) {
    $key = (string)setting('zernio_api_key', '');
    $masked = $key ? substr($key, 0, 6) . str_repeat('•', 10) . substr($key, -4) : '';
    $zones = ['Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Amman', 'Europe/London', 'UTC'];
    $cur = site_timezone();
    if (!in_array($cur, $zones, true)) $zones[] = $cur;
    ?>
    <form method="post" class="max-w-2xl space-y-6">
      <?= csrf_field() ?>
      <?= card_open('p-6 space-y-4') ?>
        <h2 class="font-bold text-lg">الربط مع Zernio</h2>
        <label class="block"><span class="text-sm font-semibold">مفتاح API</span>
          <input type="password" name="api_key" autocomplete="off" placeholder="<?= $masked ? e($masked) . ' (سيبه فاضي لو مش عاوز تغيره)' : 'sk_...' ?>" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5" dir="ltr"></label>
        <p class="text-xs text-slate-500">بتجيبه من: zernio.com ← Settings ← API Keys. البرنامج بيجربه قبل ما يحفظه.</p>
        <?php if ($profileError): ?><?php error_box($profileError); ?><?php endif; ?>
        <?php if ($profiles): ?>
          <label class="block"><span class="text-sm font-semibold">البروفايل (Profile) اللي هنشتغل عليه</span>
            <select name="profile_id" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5">
              <option value="">كل البروفايلات</option>
              <?php foreach ($profiles as $p): ?>
                <option value="<?= e($p['_id']) ?>" <?= setting('profile_id') === $p['_id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= (int)($p['accountCount'] ?? 0) ?> حساب)<?= !empty($p['isDefault']) ? ' - الافتراضي' : '' ?></option>
              <?php endforeach; ?>
            </select></label>
        <?php else: ?><input type="hidden" name="profile_id" value="<?= e((string)setting('profile_id', '')) ?>"><?php endif; ?>
      </div>

      <?= card_open('p-6 space-y-4') ?>
        <h2 class="font-bold text-lg">الموقع</h2>
        <label class="block"><span class="text-sm font-semibold">اسم الموقع</span>
          <input name="site_name" value="<?= e(setting('site_name', '')) ?>" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5"></label>
        <label class="block"><span class="text-sm font-semibold">المنطقة الزمنية (للجدولة)</span>
          <select name="timezone" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5" dir="ltr">
            <?php foreach ($zones as $z): ?><option <?= $z === $cur ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
          </select></label>
      </div>
      <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3 rounded-lg">حفظ</button>
    </form>
    <?php
});
