<?php
$err = null;
$z = zernio_or_null($err);

/**
 * كل طرق الربط المتاحة، متقسمة لمجموعات.
 * mode: oauth = صفحة المنصة نفسها، ads = حساب إعلانات، service = موقع/متجر/أداة، apikey = مفتاح
 */
function connect_catalog(): array
{
    $o = function (array $keys, string $mode, array $extra = []) {
        $out = [];
        foreach ($keys as $k => $api) {
            if (is_int($k)) { $k = $api; }
            $out[$k] = ['mode' => $mode, 'api' => $api] + ($extra[$k] ?? []);
        }
        return $out;
    };
    return [
        'social' => [t('السوشيال ميديا'), $o(['facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'youtube', 'threads', 'pinterest', 'reddit', 'bluesky', 'googlebusiness', 'snapchat'], 'oauth')],
        'messaging' => [t('المراسلة'), $o(['whatsapp', 'telegram', 'discord', 'slack'], 'oauth')],
        'ads' => [t('الإعلانات'), $o(['metaads' => 'facebook', 'googleads' => 'googleads', 'tiktokads' => 'tiktok', 'linkedinads' => 'linkedin', 'xads' => 'twitter', 'pinterestads' => 'pinterest', 'redditads' => 'redditads', 'microsoftads' => 'microsoftads'], 'ads')
            + ['openaiads' => ['mode' => 'apikey', 'api' => 'openai-ads']]],
        'sites' => [t('المواقع والمتاجر'), $o(['shopify', 'wordpress', 'wix', 'webflow'], 'service', ['shopify' => ['field' => 'shop']])],
        'crm' => [t('CRM والتحليلات'), $o(['hubspot', 'googleanalytics'], 'service')],
        'email' => [t('البريد والتقويم'), $o(['google', 'microsoft'], 'oauth')],
    ];
}

function connect_item(string $key): ?array
{
    foreach (connect_catalog() as $g) {
        if (isset($g[1][$key])) return $g[1][$key];
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'connect') {
            if (!can('accounts.manage')) {
                throw new ZernioException(t('مالكش صلاحية تربط حسابات. اطلبها من صاحب الشركة.'));
            }
            $key = (string)($_POST['platform'] ?? '');
            $item = connect_item($key);
            if (!$item) {
                throw new ZernioException(t('منصة غير مدعومة.'));
            }
            $back = base_url() . '/' . url('accounts', ['connected' => 1]);
            // مع أكتر من اتصال: الحساب الجديد بيتربط على الاتصال اللي اخترته
            $conn = (int)($_POST['conn'] ?? 0);
            $target = ($z instanceof ZernioMulti && $z->isMulti() && in_array($conn, $z->connectionIds(), true)) ? $z->client($conn) : $z;
            $profile = ($z instanceof ZernioMulti && $target !== $z) ? ($z->profileOf($conn) ?: current_profile_id($target)) : current_profile_id($z);
            $api = $item['api'];
            $auth = '';

            if ($item['mode'] === 'oauth') {
                $auth = $target->connectUrl($api, $profile, $back);
            } elseif ($item['mode'] === 'ads') {
                $q = ['profileId' => $profile, 'redirect_url' => $back];
                // إعلانات X محتاجة حساب X مربوط، وباقي منصات Meta/لينكدإن/بنترست بتستخدم الحساب الموجود لو فيه
                if (in_array($api, ['twitter', 'facebook', 'linkedin', 'pinterest', 'tiktok'], true)) {
                    $pick = null;
                    foreach ($target->accounts($profile) as $a) {
                        if (($a['platform'] ?? '') === $api) { $pick = $a['_id']; break; }
                    }
                    if ($api === 'twitter' && !$pick) {
                        throw new ZernioException(t('اربط حساب X الأول من تاب السوشيال ميديا، وبعدها اربط إعلاناته.'));
                    }
                    if ($pick) $q['accountId'] = $pick;
                }
                $r = $target->request('GET', '/v1/connect/' . rawurlencode($api) . '/ads', $q);
                if (!empty($r['alreadyConnected'])) {
                    // الإعلانات مربوطة بالفعل على نفس الحساب
                    flash('success', t('إعلانات %s مربوطة بالفعل (%s).', platform_label($key), trim((string)($r['displayName'] ?? $r['username'] ?? ''))));
                    redirect(url('accounts'));
                }
                $auth = (string)($r['authUrl'] ?? '');
            } elseif ($item['mode'] === 'service') {
                $q = ['profileId' => $profile, 'redirect_url' => $back];
                if (($item['field'] ?? '') === 'shop') {
                    $shop = strtolower(trim((string)($_POST['shop'] ?? '')));
                    $shop = preg_replace('#^https?://#', '', rtrim($shop, '/'));
                    if ($shop === '') throw new ZernioException(t('اكتب عنوان متجر Shopify (مثلاً: mystore.myshopify.com).'));
                    $q['shop'] = $shop;
                }
                $auth = (string)($target->request('GET', '/v1/connect/' . rawurlencode($api), $q)['authUrl'] ?? '');
            } elseif ($item['mode'] === 'apikey') {
                $key2 = trim((string)($_POST['api_key'] ?? ''));
                if ($key2 === '') throw new ZernioException(t('اكتب مفتاح الـ API.'));
                $target->request('POST', '/v1/connect/' . $api . '/credentials', [], ['apiKey' => $key2, 'profileId' => $profile]);
                log_activity('account.connect', $key);
                flash('success', t('اتربط %s.', platform_label($key)));
                redirect(url('accounts'));
            }
            if (!$auth) {
                throw new ZernioException(t('خدمة النشر لم ترجع رابط الربط.'));
            }
            log_activity('account.connect', $key);
            redirect($auth);
        }
        if ($action === 'disconnect' && can('accounts.manage')) {
            $z->disconnect((string)$_POST['id']);
            log_activity('account.disconnect', (string)$_POST['id']);
            flash('success', t('تم فصل الحساب.'));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('accounts'));
}

// إعادة الربط بتستخدم نفس طريقة الربط
$connectable = [];
foreach (connect_catalog() as $g) { $connectable = array_merge($connectable, array_keys($g[1])); }

if (isset($_GET['connected'])) {
    flash('success', t('لو كملت خطوات الربط، الحساب هيظهر في القائمة تحت.'));
}

$accounts = [];
$apiError = null;
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/accounts.json') ? json_decode((string)file_get_contents($fxd . '/accounts.json'), true) : null;
if ($fx) {
    $accounts = $fx;
} elseif ($z) {
    try {
        $accounts = $z->allAccounts(zernio_profile());
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('الحسابات المربوطة'), 'accounts', function () use ($z, $fx, $accounts, $apiError, $connectable) {
    if (!$z && !$fx) { no_api_box(); return; }
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
      <?php // الكروت هي الفلتر نفسه (بدل كروت + أزرار فلتر مكررة)
      foreach ([['', 'layers', t('كل الحسابات'), count($accounts), 'text-ink-900'], ['ok', 'check', t('شغالة'), count($accounts) - $bad, 'text-lime-700'], ['bad', 'alert', t('محتاجة إعادة ربط'), $bad, $bad ? 'text-red-600' : 'text-ink-900']] as [$k, $ic, $l, $v, $c]): ?>
        <button type="button" data-st-card="<?= $k ?>" class="acc-card text-start rounded-2xl border px-5 py-4 transition hover:-translate-y-0.5 hover:shadow-md <?= $k === '' ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-500/15' : ($k === 'bad' && $bad ? 'border-red-200 bg-red-50/60' : 'border-ink-200 bg-white') ?>">
          <div class="flex items-center gap-1.5 text-xs text-ink-500"><?= ui_icon($ic, 'w-4 h-4') ?><?= e($l) ?></div><div class="text-2xl font-semibold mt-1 <?= $c ?>"><?= (int)$v ?></div></button>
      <?php endforeach; ?>
        <div class="card px-5 py-4"><div class="flex items-center gap-1.5 text-xs text-ink-500"><?= ui_icon('grid', 'w-4 h-4') ?><?= e(t('المنصات')) ?></div><div class="text-2xl font-semibold mt-1 text-ink-900"><?= (int)count($byPlat) ?></div></div>
    </div>

    <?php if ($bad): $badList = array_values(array_filter($accounts, $isBad)); ?>
      <div class="rounded-2xl border border-red-200 bg-red-50/60 p-4 mb-5">
        <div class="font-bold text-red-800"><?= e(t('%s حساب وقف — المنشورات والرسايل عليهم مش شغالة', $bad)) ?></div>
        <p class="text-xs text-red-700 mt-1 mb-3"><?= e(t('ده بيحصل لما تغير كلمة السر أو الصلاحية تخلص. إعادة الربط بتاخد دقيقة ومش بتمسح أي حاجة.')) ?></p>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($badList as $a): $pl = (string)($a['platform'] ?? ''); ?>
            <form method="post" class="inline-flex items-center gap-2 rounded-full bg-white border border-red-200 ps-1.5 pe-1 py-1" data-no-ajax><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($pl) ?>"><?php if ($multi && !empty($a['_connectionId'])): ?><input type="hidden" name="conn" value="<?= (int)$a['_connectionId'] ?>"><?php endif; ?>
              <?= platform_icon($pl, 'w-5 h-5') ?><span class="text-sm text-ink-800"><?= e(trim($a['displayName'] ?? $a['username'] ?? '')) ?></span>
              <?php if (in_array($pl, $connectable, true)): ?><button class="rounded-full bg-red-600 text-white text-xs px-3 py-1 hover:bg-red-700"><?= e(t('إعادة ربط')) ?></button><?php endif; ?>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php
    $key = ['facebook', 'instagram', 'whatsapp', 'tiktok', 'googlebusiness', 'youtube', 'linkedin'];
    $missing = array_values(array_filter($key, function ($p) use ($byPlat) { return empty($byPlat[$p]); }));
    if ($accounts && $missing && can('accounts.manage')): ?>
      <div class="rounded-2xl border border-dashed border-ink-200 p-4 mb-5 flex flex-wrap items-center gap-2">
        <span class="text-sm text-ink-600 me-2"><?= e(t('منصات مهمة لسه مش مربوطة:')) ?></span>
        <?php foreach ($missing as $p): ?>
          <form method="post" class="contents" data-no-ajax><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($p) ?>"><?php if ($multi): ?><input type="hidden" name="conn" value="<?= (int)$z->connectionIds()[0] ?>"><?php endif; ?>
            <button class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-sm hover:border-brand-400 hover:shadow-sm transition"><?= platform_icon($p, 'w-4 h-4') ?><?= e(platform_label($p)) ?> <span class="text-brand-600">+</span></button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- أدوات البحث والفلترة -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <div class="relative flex-1 min-w-[14rem]">
        <svg class="w-4 h-4 text-ink-400 absolute top-1/2 -translate-y-1/2 start-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
        <input type="search" id="accSearch" class="input !mt-0 ps-9" placeholder="<?= e(t('دور على حساب بالاسم...')) ?>">
      </div>
      <div class="seg hidden" id="accStatus">
        <a href="#" data-st="" class="on"><?= e(t('الكل')) ?></a>
        <a href="#" data-st="ok"><?= e(t('شغالة')) ?></a>
        <a href="#" data-st="bad"><?= e(t('محتاجة إعادة ربط')) ?><?= $bad ? ' (' . $bad . ')' : '' ?></a>
      </div>
      <button type="button" class="btn-primary" onclick="document.getElementById('addAcc').showModal()"><?= e(t('+ ربط حساب')) ?></button>
    </div>
    <script>document.querySelectorAll('.acc-card').forEach(function (c) { c.addEventListener('click', function () { var a = document.querySelector('#accStatus a[data-st="' + c.dataset.stCard + '"]'); if (a) a.click();
      document.querySelectorAll('.acc-card').forEach(function (o) { var on = o === c; o.classList.toggle('ring-2', on); o.classList.toggle('ring-brand-500/15', on); o.classList.toggle('border-brand-400', on); o.classList.toggle('bg-brand-50', on); }); }); });</script>
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
              <?php if (!$b && !in_array($pl, ['whatsapp', 'telegram', 'discord', 'slack', 'google', 'microsoft', 'googleanalytics', 'hubspot'], true)): ?><a class="btn-ghost !py-1.5 text-xs" href="<?= e(url('compose', ['dup_text' => '', 'dup_acc' => $a['_id']])) ?>"><?= e(t('انشر عليه')) ?></a><?php endif; ?>
              <?php if (!$b && in_array($pl, ['facebook', 'instagram', 'whatsapp', 'telegram', 'twitter', 'linkedin', 'tiktok', 'googlebusiness'], true)): ?><a class="btn-ghost !py-1.5 text-xs" href="<?= e(url('inbox', ['acct' => $a['_id']])) ?>"><?= e(t('رسايله')) ?></a><?php endif; ?>
              <?php if (!empty($a['profileUrl'])): ?><a class="btn-ghost !py-1.5 text-xs" href="<?= e($a['profileUrl']) ?>" target="_blank" rel="noopener" title="<?= e(t('فتح الصفحة ↗')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M18 14v5H5V6h5"/></svg></a><?php endif; ?>
              <?php if ($b && in_array($pl, $connectable, true)): ?>
                <form method="post" data-no-ajax><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($pl) ?>"><?php if ($multi && !empty($a['_connectionId'])): ?><input type="hidden" name="conn" value="<?= (int)$a['_connectionId'] ?>"><?php endif; ?>
                  <button class="btn-primary !py-1.5 text-xs"><?= e(t('إعادة ربط')) ?></button></form>
              <?php endif; ?>
              <?php if (can('accounts.manage')): ?>
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
    <dialog id="addAcc" class="w-[min(820px,95vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-black/50">
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
        <?php $cat = connect_catalog(); ?>
        <input type="search" id="connQ" class="input !mt-0 mb-4" placeholder="<?= e(t('دوّر على منصة… (فيسبوك، Shopify، واتساب)')) ?>" autocomplete="off">
        <div class="flex gap-1 border-b border-ink-200 mb-5 overflow-x-auto" style="scrollbar-width:none" id="connTabs">
          <?php $first = true; foreach ($cat as $gk => [$gl, $items]): ?>
            <button type="button" data-tab="<?= e($gk) ?>" class="whitespace-nowrap px-3 py-2 text-sm border-b-2 -mb-px <?= $first ? 'border-brand-600 text-brand-700 font-medium' : 'border-transparent text-ink-500 hover:text-ink-900' ?>"><?= e($gl) ?> <span class="text-xs text-ink-400"><?= count($items) ?></span></button>
          <?php $first = false; endforeach; ?>
        </div>
        <?php $first = true; foreach ($cat as $gk => [$gl, $items]): ?>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 <?= $first ? '' : 'hidden' ?>" data-panel="<?= e($gk) ?>">
            <?php foreach ($items as $p => $it): $col = platform_icons()[$p][0] ?? '#64748B'; $needsInput = !empty($it['field']) || $it['mode'] === 'apikey'; $have = (int)($byPlat[$p] ?? 0); ?>
              <form method="post" data-no-ajax class="conn-it contents" data-q="<?= e(mb_strtolower($p . ' ' . platform_label($p))) ?>" data-have="<?= $have ?>"><?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="<?= e($p) ?>">
                <?php if ($multi): ?><input type="hidden" name="conn" class="connField" value="<?= (int)$z->connectionIds()[0] ?>"><?php endif; ?>
                <?php if ($needsInput): ?>
                  <details class="rounded-xl border border-ink-200 hover:shadow-md transition">
                    <summary class="flex flex-col items-center gap-2 py-4 text-sm text-ink-700 cursor-pointer"><?= platform_icon($p, 'w-8 h-8') ?><span><?= e(platform_label($p)) ?></span></summary>
                    <div class="px-3 pb-3 space-y-2">
                      <?php if (($it['field'] ?? '') === 'shop'): ?><input name="shop" class="input !mt-0 text-xs" dir="ltr" placeholder="mystore.myshopify.com" required>
                      <?php else: ?><input name="api_key" type="password" class="input !mt-0 text-xs" dir="ltr" placeholder="API Key" required><?php endif; ?>
                      <button class="btn-primary w-full !py-1.5 text-xs"><?= e(t('ربط')) ?></button>
                    </div>
                  </details>
                <?php else: ?>
                  <button class="w-full flex flex-col items-center gap-2 rounded-xl border border-ink-200 py-4 text-sm text-ink-700 hover:-translate-y-0.5 hover:shadow-md transition" style="--c:<?= e($col) ?>" onmouseover="this.style.borderColor=this.style.getPropertyValue('--c')" onmouseout="this.style.borderColor=''">
                    <?= platform_icon($p, 'w-8 h-8') ?><span class="text-center leading-tight px-1"><?= e(platform_label($p)) ?></span>
                    <?php if ($have): ?><span class="text-[10px] rounded-full bg-lime-50 text-lime-700 px-2 py-0.5"><?= e(t('مربوط %s', $have)) ?></span><?php endif; ?>
                  </button>
                <?php endif; ?>
              </form>
            <?php endforeach; ?>
          </div>
        <?php $first = false; endforeach; ?>
        <p id="connNone" class="hidden text-center text-sm text-ink-500 py-6"><?= e(t('مفيش منصة بالاسم ده.')) ?></p>
        <p class="text-xs text-ink-400 mt-5"><?= e(t('بعض المنصات بتطلب خطوة اختيار (زي اختيار الصفحة أو حساب الإعلانات) قبل ما ترجع هنا.')) ?></p>
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
      document.querySelectorAll('#connTabs [data-tab]').forEach(function (b) {
        b.addEventListener('click', function () {
          document.querySelectorAll('#connTabs [data-tab]').forEach(function (x) { var on = x === b; x.classList.toggle('border-brand-600', on); x.classList.toggle('text-brand-700', on); x.classList.toggle('font-medium', on); x.classList.toggle('border-transparent', !on); x.classList.toggle('text-ink-500', !on); });
          document.querySelectorAll('[data-panel]').forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== b.dataset.tab); });
        });
      });
      // بحث في كل المنصات (بيعرض كل التبويبات وقت البحث)
      var cq = document.getElementById('connQ');
      if (cq) cq.addEventListener('input', function () {
        var s = cq.value.trim().toLowerCase(), n = 0;
        document.getElementById('connTabs').style.display = s ? 'none' : '';
        document.querySelectorAll('[data-panel]').forEach(function (p) {
          var any = false;
          p.querySelectorAll('.conn-it').forEach(function (f) { var ok = !s || f.dataset.q.indexOf(s) > -1; Array.prototype.forEach.call(f.children, function (c) { if (c.tagName !== 'INPUT') c.style.display = ok ? '' : 'none'; }); if (ok) { any = true; n++; } });
          p.classList.toggle('hidden', s ? !any : p.dataset.panel !== (document.querySelector('#connTabs .border-brand-600') || {}).dataset.tab);
        });
        document.getElementById('connNone').classList.toggle('hidden', n > 0);
      });
      document.getElementById('addAcc').addEventListener('close', function () { if (cq && cq.value) { cq.value = ''; cq.dispatchEvent(new Event('input')); } });
      document.getElementById('addAcc').addEventListener('click', function (e) { if (e.target === this) this.close(); });
      document.addEventListener('keydown', function (e) { if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); q && q.focus(); } });
      var pick = document.getElementById('connPick');
      if (pick) pick.addEventListener('change', function () { document.querySelectorAll('.connField').forEach(function (i) { i.value = pick.value; }); });
      <?php if (isset($_GET['connected'])): ?>history.replaceState(null, '', location.href.replace(/&connected=1/, ''));<?php endif; ?>
    })();
    </script>
    <?php
});
