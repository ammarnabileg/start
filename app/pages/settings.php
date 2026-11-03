<?php
/** إعدادات الشركة المفتوحة حالياً (لصاحب الشركة) */
$w = current_workspace();
$profiles = [];
$profileError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wsName = trim((string)($_POST['ws_name'] ?? ''));
    $tz = (string)($_POST['timezone'] ?? '');
    $key = trim((string)($_POST['api_key'] ?? ''));
    $profile = trim((string)($_POST['profile_id'] ?? ''));

    if ($key !== '') {
        try {
            (new Zernio($key))->profiles(); // تجربة المفتاح قبل الحفظ
        } catch (ZernioException $ex) {
            flash('error', 'ما اتحفظش أي حاجة. المفتاح فيه مشكلة: ' . $ex->getMessage());
            redirect(url('settings'));
        }
        // مفتاح جديد = حساب Zernio ممكن يكون مختلف، فالبروفايل القديم مش صالح
        if ($key !== (string)$w['api_key']) {
            $profile = '';
        }
    }

    db()->prepare("UPDATE workspaces SET name = ?, timezone = ?, profile_id = ?, api_key = ? WHERE id = ?")->execute([
        $wsName !== '' ? mb_substr($wsName, 0, 120) : $w['name'],
        in_array($tz, timezone_identifiers_list(), true) ? $tz : $w['timezone'],
        $profile !== '' ? $profile : null,
        $key !== '' ? $key : $w['api_key'],
        $w['id'],
    ]);
    log_activity('settings.update');
    flash('success', 'تم حفظ إعدادات "' . ($wsName ?: $w['name']) . '".');
    redirect(url('settings'));
}

if (Zernio::configured()) {
    try {
        $profiles = (new Zernio())->profiles();
    } catch (ZernioException $ex) {
        $profileError = $ex->getMessage();
    }
}

render('إعدادات الشركة', 'settings', function () use ($w, $profiles, $profileError) {
    $key = (string)$w['api_key'];
    $masked = $key ? substr($key, 0, 6) . str_repeat('•', 10) . substr($key, -4) : '';
    $zones = ['Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Amman', 'Europe/London', 'UTC'];
    $cur = site_timezone();
    if (!in_array($cur, $zones, true)) $zones[] = $cur;
    ?>
    <form method="post" class="max-w-2xl space-y-6">
      <?= csrf_field() ?>
      <div class="bg-indigo-50 border border-indigo-200 text-indigo-900 rounded-xl p-4 text-sm">
        بتعدّل إعدادات شركة: <b><?= e($w['name']) ?></b>. لتعديل شركة تانية، اختارها من القائمة اللي فوق.
      </div>
      <?= card_open('p-6 space-y-4') ?>
        <h2 class="font-bold text-lg">الشركة</h2>
        <label class="block"><span class="text-sm font-semibold">اسم الشركة</span>
          <input name="ws_name" value="<?= e($w['name']) ?>" required class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5"></label>
        <label class="block"><span class="text-sm font-semibold">مفتاح Zernio API الخاص بالشركة دي</span>
          <input type="password" name="api_key" autocomplete="off" placeholder="<?= $masked ? e($masked) . ' (سيبه فاضي لو مش عاوز تغيره)' : 'sk_...' ?>" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5" dir="ltr"></label>
        <p class="text-xs text-slate-500">بتجيبه من: zernio.com ← API Keys. البرنامج بيجربه قبل ما يحفظه.</p>
        <?php if ($profileError) error_box($profileError); ?>
        <?php if ($profiles): ?>
          <label class="block"><span class="text-sm font-semibold">البروفايل (Profile) في Zernio</span>
            <select name="profile_id" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5">
              <option value="">كل البروفايلات</option>
              <?php foreach ($profiles as $p): ?>
                <option value="<?= e($p['_id']) ?>" <?= $w['profile_id'] === $p['_id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= (int)($p['accountCount'] ?? 0) ?> حساب)<?= !empty($p['isDefault']) ? ' - الافتراضي' : '' ?></option>
              <?php endforeach; ?>
            </select></label>
          <p class="text-xs text-slate-500">لو المفتاح بتاع حساب Zernio فيه أكتر من شركة، اختار البروفايل بتاع الشركة دي عشان حساباتها بس هي اللي تظهر.</p>
        <?php else: ?><input type="hidden" name="profile_id" value="<?= e((string)$w['profile_id']) ?>"><?php endif; ?>
        <label class="block"><span class="text-sm font-semibold">المنطقة الزمنية (للجدولة)</span>
          <select name="timezone" class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5" dir="ltr">
            <?php foreach ($zones as $z): ?><option <?= $z === $cur ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
          </select></label>
      </div>

      <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3 rounded-lg">حفظ</button>
    </form>
    <?php
});
