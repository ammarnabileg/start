<?php
$err = null;
$z = zernio_or_null($err);

$connectable = ['facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'youtube', 'threads', 'pinterest', 'reddit', 'googlebusiness'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'connect') {
            $platform = (string)($_POST['platform'] ?? '');
            if (!in_array($platform, $connectable, true)) {
                throw new ZernioException('منصة غير مدعومة.');
            }
            $back = base_url() . '/' . url('accounts', ['connected' => 1]);
            $auth = $z->connectUrl($platform, current_profile_id($z), $back);
            if (!$auth) {
                throw new ZernioException('Zernio لم يرجع رابط الربط.');
            }
            log_activity('account.connect', $platform);
            redirect($auth);
        }
        if ($action === 'disconnect' && is_ws_owner()) {
            $z->disconnect((string)$_POST['id']);
            log_activity('account.disconnect', (string)$_POST['id']);
            flash('success', 'تم فصل الحساب.');
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('accounts'));
}

if (isset($_GET['connected'])) {
    flash('success', 'لو كملت خطوات الربط، الحساب هيظهر في القائمة تحت.');
}

$accounts = [];
$apiError = null;
if ($z) {
    try {
        $accounts = $z->allAccounts(zernio_profile());
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render('الحسابات المربوطة', 'accounts', function () use ($z, $accounts, $apiError, $connectable) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    ?>
    <?= card_open('mb-6') ?>
      <div class="px-5 py-4 border-b border-slate-200 font-bold">الحسابات (<?= count($accounts) ?>)</div>
      <?php if (!$accounts): ?><p class="p-5 text-sm text-slate-500">مفيش حسابات مربوطة لسه. اختار منصة من تحت.</p><?php endif; ?>
      <?php foreach ($accounts as $a): $bad = !empty($a['needsReconnection']) || (isset($a['isActive']) && !$a['isActive']); ?>
        <div class="flex items-center gap-4 px-5 py-4 border-b last:border-0 border-slate-100">
          <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? $a['username'] ?? '') ?>
          <div class="flex-1 min-w-0">
            <div class="font-semibold truncate"><?= e(trim($a['displayName'] ?? $a['username'] ?? '')) ?></div>
            <div class="text-xs text-slate-500"><?= e(platform_label($a['platform'] ?? '')) ?>
              <?php if (!empty($a['profileUrl'])): ?> · <a class="underline" href="<?= e($a['profileUrl']) ?>" target="_blank" rel="noopener">فتح</a><?php endif; ?>
            </div>
          </div>
          <?php if ($bad): ?>
            <span class="text-xs bg-red-100 text-red-800 rounded-full px-2 py-1">محتاج إعادة ربط</span>
            <?php if (in_array($a['platform'], $connectable, true)): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($a['platform']) ?>"><button class="text-sm bg-indigo-600 text-white rounded-lg px-3 py-1.5">إعادة ربط</button></form>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-xs bg-green-100 text-green-800 rounded-full px-2 py-1">شغال</span>
          <?php endif; ?>
          <?php if (is_ws_owner()): ?>
            <form method="post" onsubmit="return confirm('فصل الحساب ده؟ مش هتقدر تنشر عليه لحد ما تربطه تاني.')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="disconnect"><input type="hidden" name="id" value="<?= e($a['_id']) ?>">
              <button class="text-sm text-red-600 hover:bg-red-50 rounded-lg px-3 py-1.5">فصل</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?= card_open('p-5') ?>
      <h2 class="font-bold mb-1">اربط حساب جديد</h2>
      <p class="text-sm text-slate-500 mb-4">هتتنقل لصفحة المنصة، تسجل دخول وتوافق، وبعدها هترجع هنا تلقائياً.</p>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <?php foreach ($connectable as $p): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($p) ?>">
            <button class="w-full border border-slate-200 rounded-lg py-3 font-semibold text-sm hover:bg-indigo-50 hover:border-indigo-300"><?= e(platform_label($p)) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
      <p class="text-xs text-slate-500 mt-4">واتساب وتليجرام وباقي المنصات بتتربط من لوحة Zernio نفسها، وهتظهر هنا تلقائياً.</p>
    </div>
    <?php
});
