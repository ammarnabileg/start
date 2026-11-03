<?php
$err = null;
$z = zernio_or_null($err);
$apiError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $back = url('phone_numbers');
    try {
        if (!is_ws_owner()) {
            flash('error', t('شراء وإلغاء الأرقام بيكلف فلوس، فصاحب الشركة بس اللي يقدر يعمله.'));
        } elseif ($action === 'buy') {
            $country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['country'] ?? 'US')));
            $type = (string)($_POST['numberType'] ?? '');
            $number = ph_e164((string)($_POST['phoneNumber'] ?? ''));
            $wantsSms = !empty($_POST['wantsSms']);
            $back = url('phone_numbers', ['buy' => 1, 'country' => $country, 'numberType' => $type, 'sms' => $wantsSms ? 1 : null]);
            if (empty($_POST['confirm'])) {
                flash('error', t('لازم تعلّم على مربع التأكيد إنك موافق على السعر.'));
            } elseif ($number === '') {
                flash('error', t('اختار رقم من النتايج.'));
            } else {
                $body = [
                    'profileId' => current_profile_id($z), 'country' => $country, 'phoneNumber' => $number,
                    'connectWhatsapp' => false, 'wantsSms' => $wantsSms,
                    'purchaseIntentId' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['intent'] ?? '')), 0, 100),
                ];
                if (in_array($type, ['local', 'mobile', 'national', 'toll_free'], true)) $body['numberType'] = $type;
                if ($body['purchaseIntentId'] === '') unset($body['purchaseIntentId']);
                $r = ph_purchase($z, $body);
                log_activity('phone.buy', $number);
                if (($r['status'] ?? '') === 'kyc_required') {
                    $_SESSION['ph_link'] = (string)($r['kycUrl'] ?? '');
                    flash('success', t('الدولة دي محتاجة بيانات هوية (KYC) قبل ما الرقم يتطلب. كمل البيانات من اللينك اللي تحت.'));
                } elseif (!empty($r['checkoutUrl'])) {
                    $_SESSION['ph_link'] = (string)$r['checkoutUrl'];
                    flash('success', t('كمل الدفع من اللينك اللي تحت عشان الرقم يتفعل.'));
                } elseif (($r['status'] ?? '') === 'already_purchased') {
                    flash('success', t('الرقم ده اتشرى قبل كده ومش هيتحسب تاني.'));
                } else {
                    flash('success', t('الرقم %s اتشرى.', (string)($r['phoneNumber']['phoneNumber'] ?? $number)));
                }
                $back = url('phone_numbers');
            }
        } elseif ($action === 'release') {
            $id = (string)($_POST['id'] ?? '');
            if (empty($_POST['confirm'])) {
                flash('error', t('لازم تعلّم على مربع التأكيد قبل إلغاء الرقم.'));
            } else {
                $r = ph_release($z, $id);
                log_activity('phone.release', (string)($r['phoneNumber']['phoneNumber'] ?? $id));
                flash('success', t('الرقم اتلغى.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

$data = ['numbers' => [], 'connected' => [], 'imessage' => [], 'sandbox' => null];
$countries = [];
$results = null;
$showBuy = !empty($_GET['buy']);
$country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_GET['country'] ?? 'US'))) ?: 'US';
$numberType = (string)($_GET['numberType'] ?? '');
if (!in_array($numberType, ['', 'local', 'mobile', 'national', 'toll_free'], true)) $numberType = '';
$areaCode = preg_replace('/\D/', '', (string)($_GET['areaCode'] ?? ''));
$smsOnly = !empty($_GET['sms']);
$link = (string)($_SESSION['ph_link'] ?? '');
unset($_SESSION['ph_link']);
$searchError = null;
if ($z) {
    try {
        $data = ph_numbers($z) + $data;
        if ($showBuy) {
            $countries = ph_countries($z);
            if (!empty($_GET['search'])) {
                try {
                    $results = ph_available($z, ['country' => $country, 'numberType' => $numberType, 'areaCode' => $areaCode, 'sms' => $smsOnly ? 'true' : null, 'limit' => 20]);
                } catch (ZernioException $ex) {
                    $searchError = $ex->getMessage();
                }
            }
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}
// السعر الشهري للدولة/النوع المختار
$price = null;
$typeInfo = null;
foreach ($countries as $c) {
    if (($c['code'] ?? '') !== $country) continue;
    $price = $c['monthlyCents'] ?? null;
    foreach ($c['types'] ?? [] as $ty) {
        if ($numberType === '' ? $typeInfo === null : ($ty['numberType'] ?? '') === $numberType) $typeInfo = $ty;
    }
    if ($typeInfo) $price = $typeInfo['monthlyCents'] ?? $price;
}

render(t('أرقام التليفون'), 'phone_numbers', function () use ($z, $apiError, $data, $countries, $results, $showBuy, $country, $numberType, $areaCode, $smsOnly, $price, $typeInfo, $link, $searchError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $owner = is_ws_owner();
    $numbers = array_values(array_filter($data['numbers'] ?? [], function ($n) { return ($n['status'] ?? '') !== 'released'; }));
    ?>
    <?php if ($link): ?>
      <div class="card p-4 mb-4 text-sm"><a class="link break-all" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(t('افتح الخطوة الجاية ←')) ?></a></div>
    <?php endif; ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <p class="text-sm text-ink-500"><?= e(t('الأرقام اللي الشركة بتستخدمها في المكالمات والـ SMS وواتساب.')) ?></p>
      <div class="flex gap-2">
        <a class="btn-ghost" href="<?= e(url('calls')) ?>"><?= e(t('المكالمات')) ?></a>
        <a class="btn-ghost" href="<?= e(url('sms')) ?>"><?= e(t('رسايل SMS')) ?></a>
        <?php if (!$showBuy): ?><a class="btn-primary" href="<?= e(url('phone_numbers', ['buy' => 1])) ?>"><?= e(t('+ اشتري رقم')) ?></a><?php endif; ?>
      </div>
    </div>

    <?php if ($showBuy): ?>
      <div class="card p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-bold"><?= e(t('دور على رقم جديد')) ?></h2>
          <a class="link text-sm" href="<?= e(url('phone_numbers')) ?>"><?= e(t('اقفل')) ?></a>
        </div>
        <form method="get" class="grid sm:grid-cols-5 gap-3 items-end">
          <input type="hidden" name="p" value="phone_numbers"><input type="hidden" name="buy" value="1"><input type="hidden" name="search" value="1">
          <label class="block sm:col-span-2"><span class="label"><?= e(t('الدولة')) ?></span>
            <select name="country" class="input">
              <?php foreach ($countries as $c): ?>
                <option value="<?= e($c['code']) ?>" <?= ($c['code'] ?? '') === $country ? 'selected' : '' ?>><?= e(($c['name'] ?? $c['code']) . ' · ' . t('من %s/شهر', ph_money_cents($c['monthlyCents'] ?? 0)) . (!empty($c['needsKyc']) ? ' · KYC' : '')) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="block"><span class="label"><?= e(t('النوع')) ?></span>
            <select name="numberType" class="input">
              <option value=""><?= e(t('الافتراضي')) ?></option>
              <?php foreach (['local' => t('محلي'), 'mobile' => t('موبايل'), 'national' => t('قومي'), 'toll_free' => t('مجاني للمتصل')] as $k => $l): ?>
                <option value="<?= e($k) ?>" <?= $numberType === $k ? 'selected' : '' ?>><?= e($l) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="block"><span class="label"><?= e(t('كود المنطقة (اختياري)')) ?></span><input name="areaCode" value="<?= e($areaCode) ?>" class="input" inputmode="numeric" dir="ltr" placeholder="415"></label>
          <div>
            <label class="flex items-center gap-2 text-sm mb-2"><input type="checkbox" name="sms" value="1" <?= $smsOnly ? 'checked' : '' ?>> <?= e(t('لازم يبعت SMS')) ?></label>
            <button class="btn-primary w-full"><?= e(t('دور')) ?></button>
          </div>
        </form>
        <?php if ($typeInfo): ?>
          <p class="text-sm mt-3">
            <?= e(t('السعر: %s في الشهر لكل رقم.', ph_money_cents($price))) ?>
            <?php if (!empty($typeInfo['needsKyc'])): ?><span class="text-amber-700"><?= e(t('الدولة دي محتاجة بيانات هوية (KYC) قبل ما الرقم يشتغل.')) ?></span><?php endif; ?>
            <?php if (empty($typeInfo['smsAvailable'])): ?><span class="text-ink-500"><?= e(t('النوع ده مش بيبعت SMS.')) ?></span><?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if ($searchError): ?><div class="mt-3"><?php error_box($searchError); ?></div><?php endif; ?>
        <?php if (is_array($results)): ?>
          <?php $list = $results['numbers'] ?? []; ?>
          <?php if (!$list): ?>
            <p class="text-sm text-ink-500 mt-4"><?= e(t('مفيش أرقام متاحة بالمواصفات دي. جرب من غير كود المنطقة أو نوع تاني.')) ?></p>
          <?php elseif (!$owner): ?>
            <p class="text-sm text-amber-700 mt-4"><?= e(t('دي الأرقام المتاحة. الشراء لصاحب الشركة بس.')) ?></p>
            <ul class="mt-2 text-sm grid sm:grid-cols-3 gap-2"><?php foreach ($list as $n): ?><li dir="ltr" class="text-start"><?= e($n['phoneNumber'] ?? '') ?></li><?php endforeach; ?></ul>
          <?php else: ?>
            <form method="post" class="mt-4" onsubmit="return confirm(<?= tjs('متأكد إنك عايز تشتري الرقم ده؟ هيتحسب عليه %s كل شهر.', ph_money_cents($price)) ?>)">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="buy"><input type="hidden" name="country" value="<?= e($country) ?>">
              <input type="hidden" name="numberType" value="<?= e($numberType) ?>"><input type="hidden" name="wantsSms" value="<?= $smsOnly ? 1 : '' ?>">
              <input type="hidden" name="intent" value="<?= e(bin2hex(random_bytes(8))) ?>">
              <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-80 overflow-y-auto">
                <?php foreach ($list as $i => $n): ?>
                  <label class="flex items-start gap-2 border border-ink-200 rounded-xl p-3 text-sm cursor-pointer hover:bg-ink-50">
                    <input type="radio" name="phoneNumber" value="<?= e($n['phoneNumber'] ?? '') ?>" <?= $i === 0 ? 'checked' : '' ?> class="mt-1">
                    <span><span dir="ltr" class="font-semibold" style="unicode-bidi:isolate"><?= e($n['phoneNumber'] ?? '') ?></span>
                      <span class="block text-xs text-ink-500"><?= e(trim(($n['locality'] ?? '') . ' · ' . implode(', ', array_intersect(['voice', 'sms', 'mms'], array_map(function ($f) { return strtolower(is_array($f) ? (string)($f['name'] ?? '') : (string)$f); }, (array)($n['features'] ?? [])))), ' ·')) ?></span>
                      <?php if (!empty($n['bestEffort'])): ?><span class="block text-xs text-amber-700"><?= e(t('قريب من طلبك بس مش مطابق')) ?></span><?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900">
                <label class="flex items-start gap-2"><input type="checkbox" name="confirm" value="1" required class="mt-1">
                  <span><?= e(t('فاهم إن الرقم هيتحسب عليه %s كل شهر على فاتورة Zernio لحد ما ألغيه.', ph_money_cents($price))) ?></span></label>
              </div>
              <button class="btn-primary mt-3"><?= e(t('اشتري الرقم المختار')) ?></button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!$numbers && !$data['connected'] && !$showBuy): ?>
      <?php ph_empty_numbers(); ?>
    <?php endif; ?>

    <?php if ($numbers): ?>
      <div class="space-y-3">
      <?php foreach ($numbers as $n): $step = ph_next_step($n); ?>
        <div class="card p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="font-bold text-lg text-start" dir="ltr" style="unicode-bidi:isolate"><?= e($n['phoneNumber'] ?? '') ?></div>
              <div class="text-xs text-ink-500 mt-1"><?= e(($n['country'] ?? '') . ' · ' . ($n['numberType'] ?? '')) ?><?php if (isset($n['monthlyCents'])): ?> · <?= e(t('%s/شهر', ph_money_cents($n['monthlyCents']))) ?><?php endif; ?></div>
              <div class="flex flex-wrap gap-1 mt-2">
                <?= ph_status_badge((string)($n['status'] ?? '')) ?>
                <span class="badge <?= !empty($n['pstnVoiceEnabled']) ? 'badge-green' : '' ?>"><?= e(!empty($n['pstnVoiceEnabled']) ? t('مكالمات: شغالة') : t('مكالمات: مقفولة')) ?></span>
                <span class="badge <?= !empty($n['smsSendApproved']) ? 'badge-green' : '' ?>"><?= e(!empty($n['smsSendApproved']) ? t('SMS: شغال') : (!empty($n['smsCapable']) || !empty($n['smsRequested']) ? t('SMS: مستني تسجيل') : t('SMS: مش متاح'))) ?></span>
                <span class="badge <?= !empty($n['socialAccountId']) ? 'badge-green' : '' ?>"><?= e(!empty($n['socialAccountId']) ? t('واتساب: مربوط') : t('واتساب: مش مربوط')) ?></span>
              </div>
              <?php if (!empty($n['regulatoryDeclineReason'])): ?><p class="text-sm text-red-700 mt-2"><?= e(t('سبب الرفض: %s', $n['regulatoryDeclineReason'])) ?></p><?php endif; ?>
              <?php if (!empty($n['regulatoryReviewComment'])): ?><p class="text-sm text-ink-700 mt-2"><?= e(t('تعليق المراجع: %s', $n['regulatoryReviewComment'])) ?></p><?php endif; ?>
              <?php if ($step): ?><p class="text-sm text-amber-700 mt-2"><?= e($step) ?></p><?php endif; ?>
              <?php if (!empty($n['verifyUrl'])): ?><a class="link text-sm" href="<?= e($n['verifyUrl']) ?>" target="_blank" rel="noopener"><?= e(t('لينك تأكيد الهوية')) ?></a><?php endif; ?>
            </div>
            <?php if ($owner && !in_array($n['status'] ?? '', ['releasing', 'released'], true)): ?>
              <details class="text-sm">
                <summary class="cursor-pointer text-red-700"><?= e(t('إلغاء الرقم')) ?></summary>
                <form method="post" class="mt-2 p-3 rounded-xl bg-red-50 border border-red-200 max-w-xs" onsubmit="return confirm(<?= tjs('إلغاء الرقم نهائي ومش هتقدر ترجعه. متأكد؟') ?>)">
                  <?= csrf_field() ?><input type="hidden" name="action" value="release"><input type="hidden" name="id" value="<?= e($n['_id'] ?? '') ?>">
                  <label class="flex items-start gap-2 text-red-800"><input type="checkbox" name="confirm" value="1" required class="mt-1">
                    <span><?= e(t('فاهم إن الرقم هيروح نهائياً، وأي واتساب أو مكالمات أو رسايل عليه هتقف.')) ?></span></label>
                  <button class="btn-primary mt-2 !bg-red-600"><?= e(t('ألغي الرقم نهائياً')) ?></button>
                </form>
              </details>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($data['connected'])): ?>
      <h2 class="font-bold mt-6 mb-2"><?= e(t('أرقام واتساب الخاصة بيك (مربوطة من بره)')) ?></h2>
      <div class="card">
        <?php foreach ($data['connected'] as $c): ?>
          <div class="p-3 border-b border-ink-100 last:border-0 text-sm flex justify-between gap-2">
            <span dir="ltr" style="unicode-bidi:isolate"><?= e($c['phoneNumber'] ?? $c['displayPhoneNumber'] ?? '') ?></span>
            <span class="text-ink-500"><?= e(t('مش بتتحاسب عليه هنا')) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($data['imessage'])): ?>
      <h2 class="font-bold mt-6 mb-2"><?= e(t('أرقام iMessage')) ?></h2>
      <div class="card">
        <?php foreach ($data['imessage'] as $c): ?>
          <div class="p-3 border-b border-ink-100 last:border-0 text-sm flex justify-between gap-2">
            <span dir="ltr" style="unicode-bidi:isolate"><?= e($c['phoneNumber'] ?? $c['handle'] ?? $c['_id'] ?? '') ?></span>
            <span class="badge"><?= e((string)($c['status'] ?? '')) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($data['sandbox']['phoneNumber'])): ?>
      <p class="text-xs text-ink-500 mt-6"><?= e(t('رقم Zernio التجريبي لواتساب: %s (للتجربة بس، مش رقمك).', "\u{2066}" . $data['sandbox']['phoneNumber'] . "\u{2069}")) ?></p>
    <?php endif; ?>
    <?php
});
