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
                throw new ZernioException(t('منصة غير مدعومة.'));
            }
            $back = base_url() . '/' . url('accounts', ['connected' => 1]);
            // مع أكتر من اتصال: الحساب الجديد بيتربط على الاتصال اللي اخترته
            $conn = (int)($_POST['conn'] ?? 0);
            $target = ($z instanceof ZernioMulti && $z->isMulti() && in_array($conn, $z->connectionIds(), true)) ? $z->client($conn) : $z;
            $profile = ($z instanceof ZernioMulti && $target !== $z) ? ($z->profileOf($conn) ?: current_profile_id($target)) : current_profile_id($z);
            $auth = $target->connectUrl($platform, $profile, $back);
            if (!$auth) {
                throw new ZernioException(t('خدمة النشر لم ترجع رابط الربط.'));
            }
            log_activity('account.connect', $platform);
            redirect($auth);
        }
        if ($action === 'disconnect' && is_ws_owner()) {
            $z->disconnect((string)$_POST['id']);
            log_activity('account.disconnect', (string)$_POST['id']);
            flash('success', t('تم فصل الحساب.'));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('accounts'));
}

if (isset($_GET['connected'])) {
    flash('success', t('لو كملت خطوات الربط، الحساب هيظهر في القائمة تحت.'));
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

render(t('الحسابات المربوطة'), 'accounts', function () use ($z, $accounts, $apiError, $connectable) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $multi = $z instanceof ZernioMulti && $z->isMulti();
    $isBad = function ($a) { return !empty($a['needsReconnection']) || (isset($a['isActive']) && !$a['isActive']); };
    $byPlat = [];
    $bad = 0;
    foreach ($accounts as $a) {
        $byPlat[$a['platform'] ?? ''] = ($byPlat[$a['platform'] ?? ''] ?? 0) + 1;
        if ($isBad($a)) $bad++;
    }
    ?>
    <!-- ملخص -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
      <?php foreach ([[t('كل الحسابات'), count($accounts), 'text-ink-900'], [t('شغالة'), count($accounts) - $bad, 'text-lime-700'], [t('محتاجة إعادة ربط'), $bad, $bad ? 'text-red-600' : 'text-ink-900'], [t('المنصات'), count($byPlat), 'text-ink-900']] as [$l, $v, $c]): ?>
        <div class="card px-5 py-4"><div class="text-xs text-ink-500"><?= e($l) ?></div><div class="text-2xl font-semibold mt-1 <?= $c ?>"><?= (int)$v ?></div></div>
      <?php endforeach; ?>
    </div>

    <!-- أدوات البحث والفلترة -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <div class="relative flex-1 min-w-[14rem]">
        <svg class="w-4 h-4 text-ink-400 absolute top-1/2 -translate-y-1/2 start-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
        <input type="search" id="accSearch" class="input !mt-0 ps-9" placeholder="<?= e(t('دور على حساب بالاسم...')) ?>">
      </div>
      <div class="seg" id="accStatus">
        <a href="#" data-st="" class="on"><?= e(t('الكل')) ?></a>
        <a href="#" data-st="ok"><?= e(t('شغالة')) ?></a>
        <a href="#" data-st="bad"><?= e(t('محتاجة إعادة ربط')) ?><?= $bad ? ' (' . $bad . ')' : '' ?></a>
      </div>
      <button type="button" class="btn-primary" onclick="document.getElementById('addAcc').showModal()"><?= e(t('+ ربط حساب')) ?></button>
    </div>
    <?php if (count($byPlat) > 1): ?>
      <div class="flex gap-2 overflow-x-auto pb-1 mb-5" id="accPlat" style="scrollbar-width:none">
        <button type="button" data-pf="" class="chip on"><?= e(t('كل المنصات')) ?></button>
        <?php foreach ($byPlat as $pp => $n): ?>
          <button type="button" data-pf="<?= e($pp) ?>" class="chip"><?= platform_icon($pp, 'w-4 h-4') ?><?= e(platform_label($pp)) ?> <span class="text-xs text-ink-400"><?= (int)$n ?></span></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$accounts): ?>
      <div class="card p-10 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center mb-4">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1m1 3.7a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg>
        </div>
        <h2 class="text-lg font-semibold text-ink-900"><?= e(t('مفيش حسابات مربوطة لسه')) ?></h2>
        <p class="text-sm text-ink-500 mt-1 mb-5"><?= e(t('اربط صفحات وحسابات الشركة عشان تقدر تنشر وترد على الرسائل من هنا.')) ?></p>
        <button type="button" class="btn-primary" onclick="document.getElementById('addAcc').showModal()"><?= e(t('+ ربط حساب')) ?></button>
      </div>
    <?php endif; ?>

    <!-- كروت الحسابات -->
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4" id="accGrid">
      <?php foreach ($accounts as $a): $b = $isBad($a); $pl = (string)($a['platform'] ?? ''); $name = trim($a['displayName'] ?? $a['username'] ?? ''); $color = platform_icons()[$pl][0] ?? '#64748B'; ?>
        <div class="card overflow-hidden group transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-ink-900/5 acc-card"
             data-name="<?= e(mb_strtolower($name . ' ' . ($a['username'] ?? ''))) ?>" data-pf="<?= e($pl) ?>" data-st="<?= $b ? 'bad' : 'ok' ?>">
          <div class="h-1.5" style="background:<?= e($color === '#000000' ? '#475569' : $color) ?>"></div>
          <div class="p-5">
            <div class="flex items-start gap-3">
              <?= avatar_with_platform($a['profilePicture'] ?? null, $name, $pl, 'w-12 h-12') ?>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-ink-900 truncate"><?= e($name) ?></div>
                <div class="text-xs text-ink-500 truncate"><?= e(platform_label($pl)) ?><?php if (!empty($a['username']) && trim($a['username']) !== $name): ?> · <span dir="ltr">@<?= e(trim($a['username'])) ?></span><?php endif; ?></div>
              </div>
              <span class="inline-flex items-center gap-1.5 text-xs rounded-full px-2 py-1 whitespace-nowrap <?= $b ? 'bg-red-50 text-red-700' : 'bg-lime-50 text-lime-700' ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $b ? 'bg-red-500' : 'bg-lime-500' ?>"></span><?= e($b ? t('محتاج إعادة ربط') : t('شغال')) ?>
              </span>
            </div>
            <div class="flex flex-wrap gap-2 mt-4 text-xs text-ink-500">
              <?php if (isset($a['followersCount'])): ?><span class="badge"><?= e(t('%s متابع', number_format((int)$a['followersCount']))) ?></span><?php endif; ?>
              <?php if ($multi && !empty($a['_connection'])): ?><span class="badge"><?= e($a['_connection']) ?></span><?php endif; ?>
            </div>
            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-ink-100">
              <?php if (!empty($a['profileUrl'])): ?><a class="btn-ghost !py-1.5 text-xs" href="<?= e($a['profileUrl']) ?>" target="_blank" rel="noopener"><?= e(t('فتح الصفحة ↗')) ?></a><?php endif; ?>
              <?php if ($b && in_array($pl, $connectable, true)): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($pl) ?>"><?php if ($multi && !empty($a['_connectionId'])): ?><input type="hidden" name="conn" value="<?= (int)$a['_connectionId'] ?>"><?php endif; ?>
                  <button class="btn-primary !py-1.5 text-xs"><?= e(t('إعادة ربط')) ?></button></form>
              <?php endif; ?>
              <?php if (is_ws_owner()): ?>
                <form method="post" class="ms-auto" onsubmit="return confirm('<?= tjs('فصل الحساب ده؟ مش هتقدر تنشر عليه لحد ما تربطه تاني.') ?>')"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="disconnect"><input type="hidden" name="id" value="<?= e($a['_id']) ?>">
                  <button class="text-xs text-ink-400 hover:text-red-600 px-2 py-1.5 rounded-lg hover:bg-red-50 transition"><?= e(t('فصل')) ?></button></form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p id="accNone" class="hidden text-center text-sm text-ink-500 py-10"><?= e(t('مفيش حسابات بالبحث ده.')) ?></p>

    <!-- نافذة ربط حساب -->
    <dialog id="addAcc" class="w-[min(720px,95vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-black/50">
      <div class="flex items-center justify-between px-6 h-14 border-b border-ink-200">
        <b class="text-ink-900"><?= e(t('اربط حساب جديد')) ?></b>
        <button type="button" onclick="this.closest('dialog').close()" class="p-2 rounded-lg hover:bg-ink-100" aria-label="<?= e(t('إغلاق')) ?>">✕</button>
      </div>
      <div class="p-6">
        <p class="text-sm text-ink-500 mb-5"><?= e(t('اختار المنصة. هتتنقل لصفحتها، تسجل دخول وتوافق، وبعدها هترجع هنا تلقائياً.')) ?></p>
        <?php if ($multi): ?>
          <label class="block mb-5 max-w-sm"><span class="label"><?= e(t('اربطه على أنهي اتصال؟')) ?></span>
            <select id="connPick" class="input"><?php foreach ($z->connectionIds() as $cid): ?><option value="<?= (int)$cid ?>"><?= e($z->label($cid)) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
          <?php foreach ($connectable as $p): $col = platform_icons()[$p][0] ?? '#64748B'; ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($p) ?>">
              <?php if ($multi): ?><input type="hidden" name="conn" class="connField" value="<?= (int)$z->connectionIds()[0] ?>"><?php endif; ?>
              <button class="w-full flex flex-col items-center gap-2 rounded-xl border border-ink-200 py-4 text-sm text-ink-700 hover:-translate-y-0.5 hover:shadow-md transition" style="--c:<?= e($col) ?>" onmouseover="this.style.borderColor=this.style.getPropertyValue('--c')" onmouseout="this.style.borderColor=''">
                <?= platform_icon($p, 'w-8 h-8') ?><span><?= e(platform_label($p)) ?></span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
        <p class="text-xs text-ink-400 mt-5"><?= e(t('واتساب وتليجرام وباقي المنصات بتتربط من لوحة التكامل نفسها، وهتظهر هنا تلقائياً.')) ?></p>
      </div>
    </dialog>

    <script>
    (function () {
      var q = document.getElementById('accSearch'), st = '', pf = '';
      function apply() {
        var term = (q.value || '').trim().toLowerCase(), shown = 0;
        document.querySelectorAll('.acc-card').forEach(function (c) {
          var ok = (!term || c.dataset.name.indexOf(term) > -1) && (!st || c.dataset.st === st) && (!pf || c.dataset.pf === pf);
          c.classList.toggle('hidden', !ok); if (ok) shown++;
        });
        var none = document.getElementById('accNone'); if (none) none.classList.toggle('hidden', shown > 0 || !document.querySelector('.acc-card'));
      }
      if (q) q.addEventListener('input', apply);
      document.querySelectorAll('#accStatus a').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); st = a.dataset.st; document.querySelectorAll('#accStatus a').forEach(function (x) { x.classList.toggle('on', x === a); }); apply(); }); });
      document.querySelectorAll('#accPlat .chip').forEach(function (b) { b.addEventListener('click', function () { pf = b.dataset.pf; document.querySelectorAll('#accPlat .chip').forEach(function (x) { x.classList.toggle('on', x === b); }); apply(); }); });
      var pick = document.getElementById('connPick');
      if (pick) pick.addEventListener('change', function () { document.querySelectorAll('.connField').forEach(function (i) { i.value = pick.value; }); });
      <?php if (isset($_GET['connected'])): ?>history.replaceState(null, '', location.href.replace(/&connected=1/, ''));<?php endif; ?>
    })();
    </script>
    <?php
});
