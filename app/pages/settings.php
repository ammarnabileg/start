<?php
/** إعدادات الشركة المفتوحة حالياً (لصاحب الشركة). المفاتيح في صفحة Connectors. */
$w = current_workspace();
$zones = ['Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Bahrain', 'Asia/Muscat', 'Asia/Amman', 'Asia/Beirut', 'Europe/London', 'Europe/Istanbul', 'UTC'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['ws_name'] ?? ''));
    $tz = (string)($_POST['timezone'] ?? '');
    db()->prepare("UPDATE workspaces SET name = ?, timezone = ? WHERE id = ?")->execute([
        $name !== '' ? mb_substr($name, 0, 120) : $w['name'],
        in_array($tz, timezone_identifiers_list(), true) ? $tz : $w['timezone'],
        $w['id'],
    ]);
    log_activity('settings.update');
    flash('success', 'اتحفظ.');
    redirect(url('settings'));
}

render('إعدادات الشركة', 'settings', function () use ($w, $zones) {
    $cur = site_timezone();
    if (!in_array($cur, $zones, true)) $zones[] = $cur;
    ?>
    <form method="post" class="card p-6 max-w-xl space-y-5">
      <?= csrf_field() ?>
      <label class="block"><span class="label">اسم الشركة</span>
        <input name="ws_name" value="<?= e($w['name']) ?>" required class="input"></label>
      <label class="block"><span class="label">المنطقة الزمنية</span>
        <select name="timezone" class="input" dir="ltr">
          <?php foreach ($zones as $z): ?><option <?= $z === $cur ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
        </select>
        <span class="text-xs text-ink-400">مواعيد الجدولة والتواريخ بتظهر بالتوقيت ده.</span></label>
      <div class="flex items-center gap-3">
        <button class="btn-primary">حفظ</button>
        <a href="<?= e(url('connectors')) ?>" class="link text-sm">مفاتيح الربط (Connectors) ←</a>
      </div>
    </form>
    <?php
});
