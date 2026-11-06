<?php
$err = null;
$z = zernio_or_null($err);
$apiError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $back = url('phone_numbers');
    try {
        if (!can('phone.spend')) {
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
                    flash('success', t('الدولة دي محتاجة بيانات هوية (KYC) قبل ما الرقم يتطلب. املاها هنا.'));
                    redirect(url('phone_kyc', array_filter(['country' => $country, 'numberType' => (string)($r['numberType'] ?? $type) ?: null, 'phoneNumber' => $number ?: null, 'sms' => $wantsSms ? 1 : null])));
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
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/phone_numbers.json') ? json_decode((string)file_get_contents($fxd . '/phone_numbers.json'), true) : null;
if ($fx) {
    $apiError = null;
    $data = $fx['data'] + $data;
    if ($showBuy) $countries = $fx['countries'];
    if ($showBuy && !empty($_GET['search'])) $results = $fx['available'];
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

render(t('أرقام التليفون'), 'phone_numbers', function () use ($z, $apiError, $data, $countries, $results, $showBuy, $country, $numberType, $areaCode, $smsOnly, $price, $typeInfo, $link, $searchError, $fx) {
    if (!$z && !$fx) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $owner = can('phone.spend');
    $numbers = array_values(array_filter($data['numbers'] ?? [], function ($n) { return ($n['status'] ?? '') !== 'released'; }));
    $flag = function (string $cc): string {
        $cc = strtoupper($cc);
        if (!preg_match('/^[A-Z]{2}$/', $cc)) return '🌐';
        return mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
    };
    $types = ['local' => t('محلي'), 'mobile' => t('موبايل'), 'national' => t('قومي'), 'toll_free' => t('مجاني للمتصل')];
    $cname = function (string $cc) { return function_exists('country_name') ? (country_name($cc) ?: $cc) : $cc; };
    // ملخص
    $active = 0; $monthly = 0; $voice = 0; $sms = 0; $todo = 0;
    foreach ($numbers as $n) {
        if (($n['status'] ?? '') === 'active') $active++;
        $monthly += (int)($n['monthlyCents'] ?? 0);
        if (!empty($n['pstnVoiceEnabled'])) $voice++;
        if (!empty($n['smsSendApproved'])) $sms++;
        if (ph_next_step($n) !== '') $todo++;
    }
    $tile = function (string $label, string $value, string $sub = '', string $tone = '') {
        echo '<div class="rounded-2xl border ' . ($tone === 'warn' ? 'border-amber-200 bg-amber-50/60' : 'border-ink-200 bg-white') . ' p-4">'
            . '<div class="text-xs text-ink-500">' . e($label) . '</div>'
            . '<div class="text-2xl font-bold mt-1 ' . ($tone === 'warn' ? 'text-amber-700' : 'text-ink-900') . '" dir="ltr" style="text-align:start">' . e($value) . '</div>'
            . ($sub !== '' ? '<div class="text-xs text-ink-500 mt-0.5">' . e($sub) . '</div>' : '') . '</div>';
    };
    ?>
    <?php if ($link): ?>
      <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 mb-5 flex flex-wrap items-center gap-3 text-sm">
        <span class="font-medium text-brand-900 flex-1"><?= e(t('فاضل خطوة: كملها من اللينك عشان الرقم يتفعل.')) ?></span>
        <a class="btn-primary" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(t('افتح الخطوة الجاية ←')) ?></a>
      </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center gap-2 mb-5">
      <p class="text-sm text-ink-500 me-auto"><?= e(t('الأرقام اللي الشركة بتستخدمها في المكالمات والـ SMS وواتساب.')) ?></p>
      <a class="btn-ghost" href="<?= e(url('calls')) ?>"><?= e(t('المكالمات')) ?></a>
      <a class="btn-ghost" href="<?= e(url('sms')) ?>"><?= e(t('رسايل SMS')) ?></a>
      <?php if (!$showBuy): ?><a class="btn-primary" href="<?= e(url('phone_numbers', ['buy' => 1])) ?>"><?= e(t('+ اشتري رقم')) ?></a><?php endif; ?>
    </div>

    <?php if ($numbers): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
      <?php
      $tile(t('أرقام شغالة'), $active . ' / ' . count($numbers));
      $tile(t('التكلفة الشهرية'), ph_money_cents($monthly), t('%s في السنة', ph_money_cents($monthly * 12)));
      $tile(t('جاهز للمكالمات / SMS'), $voice . ' / ' . $sms);
      $tile(t('محتاج منك إجراء'), (string)$todo, $todo ? t('شوف الكروت المعلّمة') : t('كله تمام'), $todo ? 'warn' : '');
      ?>
    </div>
    <?php endif; ?>

    <?php /* ================= شراء رقم ================= */ ?>
    <?php if ($showBuy):
      $ctyJs = [];
      foreach ($countries as $c) $ctyJs[$c['code']] = ['name' => $c['name'] ?? $c['code'], 'flag' => $flag((string)$c['code']), 'cents' => (int)($c['monthlyCents'] ?? 0), 'kyc' => !empty($c['needsKyc']), 'types' => array_map(function ($ty) { return ['t' => (string)($ty['numberType'] ?? ''), 'cents' => (int)($ty['monthlyCents'] ?? 0), 'sms' => !empty($ty['smsAvailable']), 'kyc' => !empty($ty['needsKyc'])]; }, $c['types'] ?? [])];
      $list = is_array($results) ? ($results['numbers'] ?? []) : null;
    ?>
      <div id="ph-buy" class="rounded-2xl border border-ink-200 bg-white mb-8 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-ink-100 bg-gradient-to-l from-brand-50 to-white">
          <span class="w-10 h-10 rounded-xl bg-brand-600 text-white flex items-center justify-center"><?= ui_icon('phone', 'w-5 h-5') ?></span>
          <div class="flex-1"><h2 class="font-bold text-ink-900"><?= e(t('دور على رقم جديد')) ?></h2><p class="text-xs text-ink-500"><?= e(t('اختار الدولة والنوع، دوّر، وبعدين اختار الرقم اللي يعجبك')) ?></p></div>
          <a class="w-9 h-9 rounded-full hover:bg-ink-100 flex items-center justify-center text-ink-500" href="<?= e(url('phone_numbers')) ?>" title="<?= e(t('اقفل')) ?>">✕</a>
        </div>
        <form method="get" id="ph-search" class="p-5 grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-6">
          <input type="hidden" name="p" value="phone_numbers"><input type="hidden" name="buy" value="1"><input type="hidden" name="search" value="1">
          <div>
            <div class="label flex items-center gap-2"><span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] flex items-center justify-center">1</span><?= e(t('الدولة')) ?></div>
            <input type="search" id="ph-cq" class="input !py-1.5 mb-2" placeholder="<?= e(t('دوّر على دولة...')) ?>" autocomplete="off">
            <div id="ph-clist" class="max-h-64 overflow-y-auto rounded-xl border border-ink-200 divide-y divide-ink-100">
              <?php foreach ($countries as $c): $cc = (string)$c['code']; ?>
                <label class="ph-c flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-ink-50 has-[:checked]:bg-brand-50" data-q="<?= e(mb_strtolower(($c['name'] ?? '') . ' ' . $cc . ' ' . $cname($cc))) ?>">
                  <input type="radio" name="country" value="<?= e($cc) ?>" class="accent-brand-600" <?= $cc === $country ? 'checked' : '' ?>>
                  <span class="text-xl leading-none"><?= $flag($cc) ?></span>
                  <span class="flex-1 min-w-0"><span class="block text-sm font-medium text-ink-900 truncate"><?= e($c['name'] ?? $cc) ?></span>
                    <?php if (!empty($c['needsKyc'])): ?><span class="text-[11px] text-amber-700"><?= e(t('محتاجة بيانات هوية (KYC)')) ?></span><?php endif; ?></span>
                  <span class="text-xs text-ink-600 whitespace-nowrap" dir="ltr"><?= e(t('من %s/شهر', ph_money_cents($c['monthlyCents'] ?? 0))) ?></span>
                </label>
              <?php endforeach; ?>
              <?php if (!$countries): ?><p class="p-4 text-sm text-ink-500"><?= e(t('مفيش دول متاحة دلوقتي.')) ?></p><?php endif; ?>
            </div>
          </div>
          <div class="space-y-5">
            <div>
              <div class="label flex items-center gap-2"><span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] flex items-center justify-center">2</span><?= e(t('النوع')) ?></div>
              <div id="ph-types" class="flex flex-wrap gap-2" data-cur="<?= e($numberType) ?>"></div>
              <p id="ph-tnote" class="text-xs mt-2"></p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="label"><?= e(t('كود المنطقة (اختياري)')) ?></span><input name="areaCode" value="<?= e($areaCode) ?>" class="input" inputmode="numeric" dir="ltr" placeholder="415"></label>
              <label class="flex items-center gap-3 cursor-pointer self-end pb-2">
                <input type="checkbox" name="sms" value="1" class="peer sr-only" <?= $smsOnly ? 'checked' : '' ?>>
                <span class="relative w-10 h-6 shrink-0 rounded-full bg-ink-200 peer-checked:bg-brand-600 transition-colors after:content-[''] after:absolute after:top-1 after:start-1 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:start-5"></span>
                <span class="text-sm"><?= e(t('لازم يبعت SMS')) ?></span>
              </label>
            </div>
            <button class="btn-primary w-full"><?= e(t('دور على أرقام')) ?></button>
          </div>
        </form>

        <?php if ($searchError): ?><div class="px-5 pb-5"><?php error_box($searchError); ?></div><?php endif; ?>

        <?php if (is_array($list)): ?>
          <div class="border-t border-ink-100 p-5">
          <?php if (!$list): ?>
            <div class="rounded-xl border border-dashed border-ink-200 p-8 text-center text-sm text-ink-500"><?= e(t('مفيش أرقام متاحة بالمواصفات دي. جرب من غير كود المنطقة أو نوع تاني.')) ?></div>
          <?php else: ?>
            <form method="post" id="ph-buyform" class="grid lg:grid-cols-[minmax(0,1fr)_300px] gap-6 items-start">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="buy"><input type="hidden" name="country" value="<?= e($country) ?>">
              <input type="hidden" name="numberType" value="<?= e($numberType) ?>"><input type="hidden" name="wantsSms" value="<?= $smsOnly ? 1 : '' ?>">
              <input type="hidden" name="intent" value="<?= e(bin2hex(random_bytes(8))) ?>">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                  <span class="label flex items-center gap-2 !mb-0 me-auto"><span class="w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] flex items-center justify-center">3</span><?= e(t('%s رقم متاح', count($list))) ?></span>
                  <input type="search" id="ph-nq" class="input !mt-0 !py-1.5 !w-44 text-sm" dir="ltr" inputmode="numeric" placeholder="<?= e(t('فيه أرقام زي 777')) ?>">
                  <label class="text-xs flex items-center gap-1.5 cursor-pointer"><input type="checkbox" id="ph-nice"> <?= e(t('المميزة بس')) ?></label>
                </div>
                <div id="ph-res" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2 max-h-[28rem] overflow-y-auto p-0.5">
                  <?php foreach ($list as $i => $n): $num = (string)($n['phoneNumber'] ?? '');
                    $feat = array_values(array_intersect(['voice', 'sms', 'mms'], array_map(function ($f) { return strtolower(is_array($f) ? (string)($f['name'] ?? '') : (string)$f); }, (array)($n['features'] ?? [])))); ?>
                    <label class="ph-n cursor-pointer" data-num="<?= e(preg_replace('/\D/', '', $num)) ?>">
                      <input type="radio" name="phoneNumber" value="<?= e($num) ?>" class="peer sr-only" <?= $i === 0 && $owner ? 'checked' : '' ?> <?= $owner ? '' : 'disabled' ?>>
                      <span class="block rounded-xl border border-ink-200 p-3 hover:border-brand-400 peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200 peer-checked:bg-brand-50 transition">
                        <span class="flex items-center gap-2"><span class="ph-dig font-bold text-ink-900 tracking-wide" dir="ltr" style="unicode-bidi:isolate"><?= e($num) ?></span><span class="ph-badge ms-auto"></span></span>
                        <span class="block text-xs text-ink-500 mt-1"><?= e($n['locality'] ?? '') ?></span>
                        <span class="flex gap-1 mt-2"><?php foreach (['voice' => t('مكالمات'), 'sms' => 'SMS', 'mms' => 'MMS'] as $k => $l): ?><span class="rounded-full px-2 py-0.5 text-[10px] <?= in_array($k, $feat, true) ? 'bg-lime-50 text-lime-700' : 'bg-ink-50 text-ink-400 line-through' ?>"><?= e($l) ?></span><?php endforeach; ?></span>
                        <?php if (!empty($n['bestEffort'])): ?><span class="block text-[11px] text-amber-700 mt-1"><?= e(t('قريب من طلبك بس مش مطابق')) ?></span><?php endif; ?>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <p id="ph-none" class="text-sm text-ink-500 text-center py-6" style="display:none"><?= e(t('مفيش رقم بالشكل ده في النتايج.')) ?></p>
              </div>
              <aside class="lg:sticky lg:top-4 rounded-2xl border border-ink-200 p-5 space-y-4">
                <?php if (!$owner): ?>
                  <p class="text-sm text-amber-700"><?= e(t('دي الأرقام المتاحة. الشراء لصاحب الشركة بس.')) ?></p>
                <?php else: ?>
                  <div><div class="text-xs text-ink-500"><?= e(t('الرقم المختار')) ?></div><div id="ph-sel" class="text-xl font-bold text-ink-900 mt-1" dir="ltr" style="text-align:start"></div>
                    <div class="text-xs text-ink-500 mt-0.5"><?= $flag($country) ?> <?= e(($ctyJs[$country]['name'] ?? $country) . ($numberType !== '' ? ' · ' . ($types[$numberType] ?? $numberType) : '')) ?></div></div>
                  <div class="rounded-xl bg-ink-50 p-3 text-sm space-y-1">
                    <div class="flex justify-between"><span class="text-ink-600"><?= e(t('في الشهر')) ?></span><b dir="ltr"><?= e(ph_money_cents($price)) ?></b></div>
                    <div class="flex justify-between"><span class="text-ink-600"><?= e(t('أول سنة')) ?></span><b dir="ltr"><?= e(ph_money_cents((int)$price * 12)) ?></b></div>
                  </div>
                  <?php if (!empty($typeInfo['needsKyc']) || !empty($ctyJs[$country]['kyc'])): ?><p class="text-xs text-amber-700"><?= e(t('الدولة دي محتاجة بيانات هوية (KYC) قبل ما الرقم يشتغل.')) ?></p><?php endif; ?>
                  <?php if ($typeInfo && empty($typeInfo['smsAvailable'])): ?><p class="text-xs text-ink-500"><?= e(t('النوع ده مش بيبعت SMS.')) ?></p><?php endif; ?>
                  <label class="flex items-start gap-2 text-sm rounded-xl bg-amber-50 border border-amber-200 p-3 text-amber-900 cursor-pointer"><input type="checkbox" name="confirm" value="1" id="ph-ok" required class="mt-1">
                    <span><?= e(t('فاهم إن الرقم هيتحسب عليه %s كل شهر على فاتورة الاشتراك لحد ما ألغيه.', ph_money_cents($price))) ?></span></label>
                  <?php if (!empty($typeInfo['needsKyc']) || !empty($ctyJs[$country]['kyc'])): $kb = url('phone_kyc', array_filter(['country' => $country, 'numberType' => $numberType ?: null, 'sms' => $smsOnly ? 1 : null])); ?>
                    <a id="ph-kyc-go" class="btn-primary w-full text-center block" data-base="<?= e($kb) ?>" href="<?= e($kb) ?>"><?= e(t('املا بيانات الهوية للرقم ده ←')) ?></a>
                    <p class="text-[11px] text-ink-500"><?= e(t('الأرقام في الدولة دي بتتطلب مع بيانات الهوية في خطوة واحدة.')) ?></p>
                  <?php else: ?>
                  <button id="ph-buybtn" class="btn-primary w-full disabled:opacity-50" disabled><?= e(t('اشتري الرقم المختار')) ?></button>
                  <?php endif; ?>
                <?php endif; ?>
              </aside>
            </form>
          <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <script>window.PHB = <?= json_encode(['countries' => $ctyJs, 'types' => $types, 'price' => ph_money_cents($price), 's' => [
        'perMonth' => t('%s/شهر'), 'noSms' => t('النوع ده مش بيبعت SMS'), 'kyc' => t('محتاج بيانات هوية (KYC)'), 'def' => t('الافتراضي'),
        'nice' => t('مميز'), 'confirm' => t('متأكد إنك عايز تشتري الرقم %s؟ هيتحسب عليه %s كل شهر.'), 'pick' => t('اختار رقم'),
      ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <?php endif; ?>

    <?php if (!$numbers && !$data['connected'] && !$showBuy): ?>
      <?php ph_empty_numbers(); ?>
    <?php endif; ?>

    <?php /* ================= الأرقام ================= */ ?>
    <?php if ($numbers): ?>
      <?php if (count($numbers) > 3): ?><input type="search" id="ph-filter" class="input !mt-0 mb-4 sm:!w-72" placeholder="<?= e(t('دوّر في أرقامك...')) ?>"><?php endif; ?>
      <div class="grid lg:grid-cols-2 gap-4 items-start">
      <?php foreach ($numbers as $n):
        $step = ph_next_step($n); $st = (string)($n['status'] ?? ''); $num = (string)($n['phoneNumber'] ?? '');
        $smsState = !empty($n['smsSendApproved']) ? 'on' : (!empty($n['smsCapable']) || !empty($n['smsRequested']) ? 'wait' : 'off');
        $stage = in_array($st, ['active'], true) ? 3 : (in_array($st, ['pending_regulatory', 'verifying', 'regulatory_declined'], true) ? 2 : 1);
      ?>
        <div class="ph-card card p-5 <?= $step ? 'ring-1 ring-amber-200' : '' ?>" data-q="<?= e(preg_replace('/\D/', '', $num) . ' ' . strtolower(($n['country'] ?? '') . ' ' . ($n['numberType'] ?? ''))) ?>">
          <div class="flex items-start gap-3">
            <span class="w-12 h-12 shrink-0 rounded-2xl bg-ink-50 flex items-center justify-center text-2xl"><?= $flag((string)($n['country'] ?? '')) ?></span>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-xl text-ink-900 truncate" dir="ltr" style="unicode-bidi:isolate"><?= e($num) ?></span>
                <button type="button" class="ph-copy w-7 h-7 rounded-lg text-ink-400 hover:bg-ink-100 hover:text-ink-700 shrink-0" data-v="<?= e($num) ?>" title="<?= e(t('انسخ')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h11v12H8zM5 16V4h11"/></svg></button>
              </div>
              <div class="text-xs text-ink-500 mt-0.5"><?= e(implode(' · ', array_filter([$types[$n['numberType'] ?? ''] ?? ($n['numberType'] ?? ''), isset($n['monthlyCents']) ? t('%s/شهر', ph_money_cents($n['monthlyCents'])) : '', !empty($n['createdAt']) ? t('مشترك من %s', fmt_date($n['createdAt'], 'd/m/Y')) : '']))) ?></div>
            </div>
            <?= ph_status_badge($st) ?>
          </div>

          <?php if ($st !== 'active'): ?>
          <div class="flex items-center gap-2 mt-4 text-[11px]">
            <?php foreach ([1 => t('اتطلب'), 2 => t('مراجعة'), 3 => t('شغال')] as $k => $l): $bad = $st === 'regulatory_declined' && $k === 2; ?>
              <span class="flex items-center gap-1.5 <?= $k <= $stage ? ($bad ? 'text-red-700' : 'text-brand-700 font-medium') : 'text-ink-400' ?>"><span class="w-5 h-5 rounded-full flex items-center justify-center <?= $k < $stage ? 'bg-brand-600 text-white' : ($k === $stage ? ($bad ? 'bg-red-600 text-white' : 'ring-2 ring-brand-600') : 'bg-ink-100') ?>"><?= $k < $stage ? '✓' : $k ?></span><?= e($l) ?></span>
              <?php if ($k < 3): ?><span class="flex-1 h-0.5 <?= $k < $stage ? 'bg-brand-600' : 'bg-ink-100' ?>"></span><?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="grid grid-cols-3 gap-2 mt-4">
            <?php
            $cap = function (string $label, string $state, string $txt, ?string $href) {
                $cls = ['on' => 'border-lime-200 bg-lime-50 text-lime-800', 'wait' => 'border-amber-200 bg-amber-50 text-amber-800', 'off' => 'border-ink-200 bg-ink-50 text-ink-500'][$state];
                $tag = $href ? 'a href="' . e($href) . '"' : 'div';
                echo '<' . $tag . ' class="rounded-xl border ' . $cls . ' px-3 py-2 ' . ($href ? 'hover:shadow-sm transition' : '') . '"><div class="text-[11px] opacity-80">' . e($label) . '</div><div class="text-sm font-medium">' . e($txt) . '</div></' . ($href ? 'a' : 'div') . '>';
            };
            $cap(t('مكالمات'), !empty($n['pstnVoiceEnabled']) ? 'on' : 'off', !empty($n['pstnVoiceEnabled']) ? t('شغالة') : t('مقفولة'), !empty($n['pstnVoiceEnabled']) ? url('calls') : null);
            $cap('SMS', $smsState, ['on' => t('شغال'), 'wait' => t('مستني تسجيل'), 'off' => t('مش متاح')][$smsState], $smsState === 'on' ? url('sms') : null);
            $cap(t('واتساب'), !empty($n['socialAccountId']) ? 'on' : 'off', !empty($n['socialAccountId']) ? t('مربوط') : t('مش مربوط'), null);
            ?>
          </div>

          <?php if (!empty($n['regulatoryDeclineReason'])): ?><p class="text-sm text-red-700 mt-3"><?= e(t('سبب الرفض: %s', $n['regulatoryDeclineReason'])) ?></p><?php endif; ?>
          <?php if (!empty($n['regulatoryReviewComment'])): ?><p class="text-sm text-ink-700 mt-3"><?= e(t('تعليق المراجع: %s', $n['regulatoryReviewComment'])) ?></p><?php endif; ?>
          <?php if ($step): ?>
            <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900 flex flex-wrap items-center gap-2">
              <span class="flex-1 min-w-[12rem]"><?= e($step) ?></span>
              <?php $vu = (string)($n['verifyUrl'] ?? $n['onfidoVerificationUrl'] ?? ''); if ($vu !== ''): ?><a class="btn-primary !py-1 text-xs" href="<?= e($vu) ?>" target="_blank" rel="noopener"><?= e(t('لينك تأكيد الهوية')) ?></a><?php endif; ?>
              <?php if ($owner && ($st === 'regulatory_declined' || ($n['regulatoryInfoStatus'] ?? '') === 'action_required')): ?><a class="btn-primary !py-1 text-xs" href="<?= e(url('phone_kyc', ['fix' => $n['_id'] ?? ''])) ?>"><?= e(t('صحح البيانات من هنا')) ?></a><?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($owner && !in_array($st, ['releasing', 'released'], true)): ?>
            <details class="mt-4 pt-3 border-t border-ink-100 text-sm group">
              <summary class="cursor-pointer list-none text-xs text-ink-400 hover:text-red-700 inline-flex items-center gap-1"><?= e(t('إلغاء الرقم')) ?> <span class="group-open:rotate-180 transition-transform">⌄</span></summary>
              <form method="post" class="mt-3 p-3 rounded-xl bg-red-50 border border-red-200" onsubmit="return confirm(<?= tjs('إلغاء الرقم نهائي ومش هتقدر ترجعه. متأكد؟') ?>)">
                <?= csrf_field() ?><input type="hidden" name="action" value="release"><input type="hidden" name="id" value="<?= e($n['_id'] ?? '') ?>">
                <label class="flex items-start gap-2 text-red-800"><input type="checkbox" name="confirm" value="1" required class="mt-1">
                  <span><?= e(t('فاهم إن الرقم هيروح نهائياً، وأي واتساب أو مكالمات أو رسايل عليه هتقف.')) ?></span></label>
                <button class="btn-primary mt-2 !bg-red-600"><?= e(t('ألغي الرقم نهائياً')) ?></button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($data['connected']) || !empty($data['imessage'])): ?>
    <div class="grid lg:grid-cols-2 gap-4 mt-8">
      <?php if (!empty($data['connected'])): ?>
        <div class="card p-5">
          <h2 class="font-bold text-ink-900 mb-1"><?= e(t('أرقام واتساب الخاصة بيك (مربوطة من بره)')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('مش بتتحاسب عليه هنا')) ?></p>
          <?php foreach ($data['connected'] as $c): $v = (string)($c['phoneNumber'] ?? $c['displayPhoneNumber'] ?? ''); ?>
            <div class="flex items-center gap-2 py-2 border-t border-ink-100 text-sm"><span class="text-lime-600">●</span><span dir="ltr" style="unicode-bidi:isolate" class="font-medium"><?= e($v) ?></span><button type="button" class="ph-copy ms-auto w-7 h-7 rounded-lg text-ink-400 hover:bg-ink-100" data-v="<?= e($v) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h11v12H8zM5 16V4h11"/></svg></button></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($data['imessage'])): ?>
        <div class="card p-5">
          <h2 class="font-bold text-ink-900 mb-3"><?= e(t('أرقام iMessage')) ?></h2>
          <?php foreach ($data['imessage'] as $c): ?>
            <div class="flex items-center gap-2 py-2 border-t border-ink-100 text-sm"><span dir="ltr" style="unicode-bidi:isolate"><?= e($c['phoneNumber'] ?? $c['handle'] ?? $c['_id'] ?? '') ?></span><span class="badge ms-auto"><?= e((string)($c['status'] ?? '')) ?></span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($data['sandbox']['phoneNumber'])): ?>
      <p class="text-xs text-ink-500 mt-6"><?= e(t('الرقم التجريبي لواتساب: %s (للتجربة بس، مش رقمك).', "\u{2066}" . $data['sandbox']['phoneNumber'] . "\u{2069}")) ?></p>
    <?php endif; ?>
    <script>window.PHX = <?= json_encode(['copied' => t('اتنسخ')], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-phone_numbers.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
