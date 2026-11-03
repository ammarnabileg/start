<?php
/** إعدادات الشركة المفتوحة حالياً (لصاحب الشركة). المفاتيح في صفحة Connectors. */
$w = current_workspace();
$zones = ['Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Bahrain', 'Asia/Muscat', 'Asia/Amman', 'Asia/Beirut', 'Europe/London', 'Europe/Istanbul', 'UTC'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['ws_name'] ?? ''));
    $tz = (string)($_POST['timezone'] ?? '');
    $on = (array)($_POST['modules'] ?? []);
    $off = array_values(array_diff(array_keys(module_catalog()), $on));
    db()->prepare("UPDATE workspaces SET name = ?, timezone = ?, disabled_modules = ? WHERE id = ?")->execute([
        $name !== '' ? mb_substr($name, 0, 120) : $w['name'],
        in_array($tz, timezone_identifiers_list(), true) ? $tz : $w['timezone'],
        json_encode($off),
        $w['id'],
    ]);
    log_activity('settings.update');
    flash('success', t('اتحفظ.'));
    redirect(url('settings'));
}

render(t('الإعدادات العامة'), 'settings', function () use ($w, $zones) {
    $cur = site_timezone();
    if (!in_array($cur, $zones, true)) $zones[] = $cur;
    ?>
    <form method="post" class="card p-6 max-w-xl space-y-5">
      <?= csrf_field() ?>
      <label class="block"><span class="label"><?= e(t('اسم الشركة')) ?></span>
        <input name="ws_name" value="<?= e($w['name']) ?>" required class="input"></label>
      <label class="block"><span class="label"><?= e(t('المنطقة الزمنية')) ?></span>
        <select name="timezone" class="input" dir="ltr">
          <?php foreach ($zones as $z): ?><option <?= $z === $cur ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
        </select>
        <span class="text-xs text-ink-400"><?= e(t('مواعيد الجدولة والتواريخ بتظهر بالتوقيت ده.')) ?></span></label>
      <fieldset class="pt-2">
        <legend class="label mb-1"><?= e(t('الأقسام')) ?></legend>
        <p class="text-xs text-ink-500 mb-3"><?= e(t('اقفل الأقسام اللي الشركة دي مش بتستخدمها، عشان القائمة تبقى أبسط للفريق. مفيش بيانات بتتمسح.')) ?></p>
        <div class="grid sm:grid-cols-2 gap-2">
          <?php foreach (module_catalog() as $mk => $ml): ?>
            <label class="flex items-center gap-3 rounded-xl border border-ink-200 px-3 py-2.5 cursor-pointer hover:bg-ink-50">
              <input type="checkbox" name="modules[]" value="<?= e($mk) ?>" <?= module_enabled($mk) ? 'checked' : '' ?> class="w-4 h-4 accent-brand-600">
              <span class="text-sm"><?= e($ml) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="flex items-center gap-3">
        <button class="btn-primary"><?= e(t('حفظ')) ?></button>
      </div>
    </form>
    <?php
});
