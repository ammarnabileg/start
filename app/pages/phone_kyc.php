<?php
/* بيانات الهوية (KYC) لأرقام الدول اللي محتاجاها — الفورم بيتبني من مواصفات زيرنيو لكل دولة،
   والملفات بتتبعت مع الطلب مباشرة. مفيش لينكات خارجية غير التحقق بالوش (Onfido) لو الدولة طالباه. */
$err = null;
$z = zernio_or_null($err);
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/phone_kyc.json') ? json_decode((string)file_get_contents($fxd . '/phone_kyc.json'), true) : null;
if ($fx && is_file($fxd . '/gmb_fake_z.php')) $z = require $fxd . '/gmb_fake_z.php'; // اختبارات بس: بيسجّل الطلبات بدل ما يبعتها

$q = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($q['country'] ?? ''))) ?: 'GB';
$numberType = (string)($q['numberType'] ?? '');
if (!in_array($numberType, ['', 'local', 'mobile', 'national', 'toll_free'], true)) $numberType = '';
$areaCode = preg_replace('/\D/', '', (string)($q['areaCode'] ?? ''));
$phoneNumber = ph_e164((string)($q['phoneNumber'] ?? ''));
$wantsSms = !empty($q['sms']);
$fix = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($q['fix'] ?? ''));
$preorder = !empty($q['preorder']);

$self = function (array $extra = []) use ($country, $numberType, $areaCode, $phoneNumber, $wantsSms, $fix) {
    return url('phone_kyc', array_filter(['country' => $fix ? null : $country, 'numberType' => $numberType ?: null, 'areaCode' => $areaCode ?: null, 'phoneNumber' => $phoneNumber ?: null, 'sms' => $wantsSms ? 1 : null, 'fix' => $fix ?: null] + $extra));
};

/* ---------- الإرسال ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $_SESSION['kyc_old'] = ['values' => (array)($_POST['values'] ?? []), 'address' => (array)($_POST['address'] ?? []), 'first' => (string)($_POST['first'] ?? ''), 'last' => (string)($_POST['last'] ?? ''), 'aud' => (string)($_POST['aud'] ?? '')];
    try {
        if (!can('phone.spend')) throw new ZernioException(t('شراء وإلغاء الأرقام بيكلف فلوس، فصاحب الشركة بس اللي يقدر يعمله.'));
        if (!$fix && empty($_POST['confirm'])) throw new ZernioException(t('لازم تعلّم على مربع التأكيد إنك موافق على السعر.'));
        $body = [];
        $reuse = (string)($_POST['reuse'] ?? '');
        if ($reuse !== '' && !$fix) {
            $body = ['reuse' => true, 'reuseOptionId' => mb_substr($reuse, 0, 100)];
        } else {
            $values = [];
            foreach ((array)($_POST['values'] ?? []) as $rid => $v) {
                $rid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$rid); $v = trim((string)$v);
                if ($rid !== '' && $v !== '') $values[$rid] = mb_substr($v, 0, 4000);
            }
            $address = [];
            foreach ((array)($_POST['address'] ?? []) as $rid => $a) {
                $rid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$rid);
                if ($rid === '' || !is_array($a)) continue;
                $a = array_map(function ($x) { return mb_substr(trim((string)$x), 0, 200); }, array_intersect_key($a, array_flip(['street_address', 'extended_address', 'locality', 'administrative_area', 'postal_code', 'country_code'])));
                $a['country_code'] = strtoupper(preg_replace('/[^A-Za-z]/', '', $a['country_code'] ?? ''));
                $a = array_filter($a, 'strlen');
                if (!$a) continue;
                $address[$rid] = $a;
            }
            $docs = [];
            foreach ((array)($_FILES['docs']['name'] ?? []) as $rid => $name) {
                $rid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$rid);
                $er = $_FILES['docs']['error'][$rid] ?? UPLOAD_ERR_NO_FILE;
                if ($er === UPLOAD_ERR_NO_FILE) continue;
                $tmp = (string)($_FILES['docs']['tmp_name'][$rid] ?? '');
                if ($er !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) throw new ZernioException(t('الملف "%s" ماترفعش. جرّب تاني.', (string)$name));
                if (filesize($tmp) > 10 * 1024 * 1024) throw new ZernioException(t('الملف "%s" أكبر من 10 ميجا.', (string)$name));
                $mime = function_exists('mime_content_type') ? (string)mime_content_type($tmp) : '';
                if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) throw new ZernioException(t('الملف "%s" لازم يكون PDF أو صورة (JPG/PNG/WebP).', (string)$name));
                $docs[] = ['requirementId' => $rid, 'filename' => preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$name)) ?: 'document.pdf', 'base64' => base64_encode((string)file_get_contents($tmp))];
            }
            if ($values) $body['values'] = $values;
            if ($address) $body['address'] = $address;
            if ($docs) $body['documents'] = $docs;
            $first = trim((string)($_POST['first'] ?? '')); $last = trim((string)($_POST['last'] ?? ''));
            if ($first !== '') $body['endUserFirstName'] = mb_substr($first, 0, 100);
            if ($last !== '') $body['endUserLastName'] = mb_substr($last, 0, 100);
            if (!$body) throw new ZernioException(t('املا البيانات المطلوبة الأول.'));
        }
        if ($fix) {
            $r = ph_remediate_submit($z, $fix, $body);
            log_activity('phone.kyc_fix', $fix);
            flash('success', t('التصحيحات اتبعتت للمراجعة. الرقم بيتفعل عادة خلال 1–3 أيام عمل بعد القبول.'));
        } else {
            $body += ['profileId' => current_profile_id($z), 'country' => $country, 'quantity' => 1, 'wantsSms' => $wantsSms,
                'submissionId' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['submission'] ?? '')), 0, 64)];
            if ($numberType !== '') $body['numberType'] = $numberType;
            if ($areaCode !== '') $body['areaCode'] = $areaCode;
            if ($phoneNumber !== '') $body['phoneNumber'] = $phoneNumber;
            if ($preorder) $body['preOrder'] = true;
            $r = ph_kyc_submit($z, $body);
            log_activity('phone.kyc_submit', $country);
            $st = (string)($r['status'] ?? '');
            flash('success', $st === 'kyc_already_submitted' ? t('البيانات دي اتبعتت قبل كده ولسه تحت المراجعة.') : t('بيانات الهوية اتبعتت والرقم اتطلب. المراجعة بتاخد عادة 1–3 أيام عمل، وهتلاقي حالته في صفحة الأرقام.'));
            if (!empty($r['preOrder'])) flash('success', t('الرقم اتطلب مسبقًا وهيتوفر خلال أسابيع.'));
        }
        unset($_SESSION['kyc_old']);
        redirect(url('phone_numbers'));
    } catch (ZernioException $ex) {
        $m = $ex->getMessage();
        if ($ex->status === 409 && stripos($m, 'pre_order') !== false) { flash('error', t('مفيش أرقام متاحة دلوقتي في المنطقة دي، بس ينفع تطلبه مسبقًا. علّم على "اطلبه مسبقًا" وابعت تاني.')); redirect($self(['preorder' => 1])); }
        flash('error', $m);
        redirect($self($preorder ? ['preorder' => 1] : []));
    }
}

/* ---------- المواصفات ---------- */
$spec = null; $apiError = null; $num = null;
if ($fx) {
    $spec = $fx;
} elseif ($z) {
    try {
        if ($fix) { $num = ph_number($z, $fix); $spec = ph_remediation($z, $fix); }
        else $spec = ph_kyc_spec($z, $country, $numberType);
    } catch (ZernioException $ex) { $apiError = $ex->getMessage(); }
}
$old = $_SESSION['kyc_old'] ?? []; unset($_SESSION['kyc_old']);
$countryPrice = null;
if ($z && !$fx && !$fix) { try { foreach (ph_countries($z) as $c) if (($c['code'] ?? '') === $country) { $countryPrice = (int)($c['monthlyCents'] ?? 0); foreach ($c['types'] ?? [] as $ty) if (($ty['numberType'] ?? '') === ($spec['numberType'] ?? $numberType)) $countryPrice = (int)($ty['monthlyCents'] ?? $countryPrice); } } catch (ZernioException $ex) {} }
if ($fx) $countryPrice = 400;

render($fix ? t('تصحيح بيانات الرقم') : t('بيانات الهوية للرقم'), 'phone_kyc', function () use ($z, $fx, $spec, $apiError, $country, $numberType, $areaCode, $phoneNumber, $wantsSms, $fix, $preorder, $old, $countryPrice, $num) {
    if (!$z && !$fx) { no_api_box(); return; }
    echo '<a class="link text-sm" href="' . e(url('phone_numbers')) . '">' . e(t('← رجوع للأرقام')) . '</a><div class="h-3"></div>';
    if ($apiError) { error_box($apiError); return; }
    if (!can('phone.spend')) { error_box(t('شراء وإلغاء الأرقام بيكلف فلوس، فصاحب الشركة بس اللي يقدر يعمله.')); return; }
    $fields = (array)($spec['fields'] ?? $spec['requirements'] ?? []);
    $fields = array_values(array_filter($fields, 'is_array'));
    $hasAction = (bool)array_filter($fields, function ($f) { return ($f['kind'] ?? '') === 'action'; });
    $hasBiz = (bool)array_filter($fields, function ($f) { return ($f['audience'] ?? '') === 'business'; });
    $reusable = (array)($spec['reusable']['options'] ?? []);
    $cc = strtoupper($country);
    $flag = preg_match('/^[A-Z]{2}$/', $cc) ? mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65) : '';
    $typeName = ['local' => t('محلي'), 'mobile' => t('موبايل'), 'national' => t('قومي'), 'toll_free' => t('مجاني للمتصل')];
    $kindLabel = ['text' => t('نص'), 'date' => t('تاريخ'), 'address' => t('عنوان'), 'file' => t('ملف'), 'action' => t('تحقق بالوش')];
    ?>
    <?php if (!empty($spec['pendingReview'])): ?>
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 mb-5 text-sm text-amber-900"><?= e(t('فيه بيانات للدولة دي لسه تحت المراجعة. لو بعت تاني هيتعمل طلب جديد.')) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="kyc" class="grid lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
      <?= csrf_field() ?>
      <input type="hidden" name="submission" value="<?= e(bin2hex(random_bytes(12))) ?>">
      <?php foreach (['country' => $fix ? '' : $country, 'numberType' => $numberType, 'areaCode' => $areaCode, 'phoneNumber' => $phoneNumber, 'sms' => $wantsSms ? '1' : '', 'fix' => $fix, 'preorder' => $preorder ? '1' : ''] as $k => $v): if ($v === '') continue; ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>

      <div class="min-w-0 space-y-4">
        <div class="card p-5 flex items-center gap-4">
          <span class="w-12 h-12 rounded-2xl bg-ink-50 flex items-center justify-center text-2xl"><?= $flag ?: '🌐' ?></span>
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-ink-900"><?= e($fix ? t('صحح البيانات اللي المراجعة رفضتها') : t('بيانات الهوية المطلوبة')) ?></h2>
            <p class="text-xs text-ink-500"><?= e($fix ? t('دي الحاجات اللي اتعلّم عليها بس. الطلب نفسه مش هيتحسب تاني.') : t('الدولة دي بتطلب تعرف مين صاحب الرقم قبل ما يتفعل. البيانات بتروح لمزود الأرقام مباشرة.')) ?></p>
          </div>
          <div id="kyc-prog" class="text-sm font-bold text-ink-900 whitespace-nowrap"></div>
        </div>

        <?php if ($reusable && !$fix): ?>
          <div class="card p-5">
            <div class="font-bold text-ink-900 mb-1"><?= e(t('استخدم بيانات اتقبلت قبل كده')) ?></div>
            <p class="text-xs text-ink-500 mb-3"><?= e(t('لو اخترت واحدة منهم مش محتاج تملا الفورم تاني.')) ?></p>
            <div class="space-y-2">
              <label class="flex items-center gap-2 text-sm"><input type="radio" name="reuse" value="" checked> <?= e(t('لا، هملا بيانات جديدة')) ?></label>
              <?php foreach ($reusable as $o): ?><label class="flex items-center gap-2 text-sm"><input type="radio" name="reuse" value="<?= e((string)($o['id'] ?? '')) ?>"> <?= e((string)($o['label'] ?? $o['name'] ?? $o['id'] ?? '')) ?></label><?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($hasBiz): ?>
          <div class="card p-5" id="kyc-aud">
            <div class="font-bold text-ink-900 mb-3"><?= e(t('الرقم هيتسجل باسم مين؟')) ?></div>
            <div class="grid grid-cols-2 gap-2">
              <?php foreach (['business' => [t('شركة'), t('سجل تجاري وموقع الشركة')], 'person' => [t('شخص'), t('بطاقة أو باسبور')]] as $k => $l): ?>
                <label class="cursor-pointer"><input type="radio" name="aud" value="<?= $k ?>" class="peer sr-only" <?= ($old['aud'] ?? 'business') === $k ? 'checked' : '' ?>>
                  <span class="block rounded-xl border border-ink-200 p-3 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-200 transition"><span class="block font-medium text-ink-900"><?= e($l[0]) ?></span><span class="block text-xs text-ink-500"><?= e($l[1]) ?></span></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div id="kyc-fields" class="space-y-4">
        <?php if (!$fields): ?><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('مفيش بيانات مطلوبة. تقدر تكمل على طول.')) ?></div><?php endif; ?>
        <?php foreach ($fields as $i => $f):
          $rid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($f['requirementId'] ?? $f['id'] ?? ''));
          $kind = (string)($f['kind'] ?? 'text');
          $status = (string)($f['status'] ?? '');
          if ($rid === '' && $kind !== 'action') continue;
        ?>
          <div class="kyc-f card p-5" data-kind="<?= e($kind) ?>" data-aud="<?= e((string)($f['audience'] ?? '')) ?>">
            <div class="flex items-start gap-3 mb-3">
              <span class="kyc-n w-7 h-7 shrink-0 rounded-full bg-ink-100 text-ink-600 text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2"><span class="font-medium text-ink-900" dir="auto"><?= e((string)($f['label'] ?? $rid)) ?></span>
                  <span class="rounded-full bg-ink-50 text-ink-500 text-[10px] px-2 py-0.5"><?= e($kindLabel[$kind] ?? $kind) ?></span>
                  <?php if (($f['audience'] ?? '') === 'business'): ?><span class="rounded-full bg-brand-50 text-brand-700 text-[10px] px-2 py-0.5"><?= e(t('للشركات بس')) ?></span><?php endif; ?>
                  <?php if ($status === 'declined'): ?><span class="rounded-full bg-red-50 text-red-700 text-[10px] px-2 py-0.5"><?= e(t('مرفوض')) ?></span><?php endif; ?>
                  <?php if (!empty($f['recencyMonths'])): ?><span class="rounded-full bg-amber-50 text-amber-700 text-[10px] px-2 py-0.5"><?= e(t('مش أقدم من %s شهور', (int)$f['recencyMonths'])) ?></span><?php endif; ?>
                </div>
                <?php if (!empty($f['reason'])): ?><p class="text-xs text-red-700 mt-1" dir="auto"><?= e((string)$f['reason']) ?></p><?php endif; ?>
                <?php if (!empty($f['description']) || !empty($f['example'])): ?>
                  <details class="mt-1 text-xs text-ink-600">
                    <summary class="cursor-pointer text-brand-700"><?= e(t('المطلوب بالظبط ومثال')) ?></summary>
                    <?php if (!empty($f['description'])): ?><p class="mt-2 leading-relaxed" dir="auto"><?= e((string)$f['description']) ?></p><?php endif; ?>
                    <?php if (!empty($f['example'])): ?><p class="mt-2 rounded-lg bg-ink-50 p-2 leading-relaxed" dir="auto"><b><?= e(t('مثال:')) ?></b> <?= e((string)$f['example']) ?></p><?php endif; ?>
                  </details>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($kind === 'text'): $v = (string)($old['values'][$rid] ?? ''); $long = mb_strlen((string)($f['description'] ?? '')) > 400 || stripos((string)($f['label'] ?? ''), 'description') !== false; ?>
              <?php if ($long): ?><textarea name="values[<?= e($rid) ?>]" rows="3" class="kyc-in input" dir="auto"><?= e($v) ?></textarea>
              <?php else: ?><input name="values[<?= e($rid) ?>]" class="kyc-in input" dir="auto" value="<?= e($v) ?>" <?= stripos((string)($f['label'] ?? ''), 'url') !== false ? 'type="url" placeholder="https://"' : '' ?>><?php endif; ?>
            <?php elseif ($kind === 'date'): ?>
              <input type="date" name="values[<?= e($rid) ?>]" class="kyc-in input sm:!w-56" value="<?= e((string)($old['values'][$rid] ?? '')) ?>">
            <?php elseif ($kind === 'address'): $a = (array)($old['address'][$rid] ?? []); $local = ($f['addressMatch'] ?? '') !== 'worldwide' ? (string)($f['localTo'] ?? $cc) : ''; ?>
              <div class="grid sm:grid-cols-2 gap-3">
                <label class="block sm:col-span-2"><span class="label"><?= e(t('الشارع ورقم المبنى')) ?></span><input name="address[<?= e($rid) ?>][street_address]" class="kyc-in input" dir="auto" value="<?= e((string)($a['street_address'] ?? '')) ?>" autocomplete="street-address"></label>
                <label class="block"><span class="label"><?= e(t('المدينة')) ?></span><input name="address[<?= e($rid) ?>][locality]" class="kyc-in input" dir="auto" value="<?= e((string)($a['locality'] ?? '')) ?>" autocomplete="address-level2"></label>
                <label class="block"><span class="label"><?= e(t('المحافظة / الولاية (لو موجودة)')) ?></span><input name="address[<?= e($rid) ?>][administrative_area]" class="input" dir="auto" value="<?= e((string)($a['administrative_area'] ?? '')) ?>" autocomplete="address-level1"></label>
                <label class="block"><span class="label"><?= e(t('الرقم البريدي')) ?></span><input name="address[<?= e($rid) ?>][postal_code]" class="kyc-in input" dir="ltr" value="<?= e((string)($a['postal_code'] ?? '')) ?>" autocomplete="postal-code"></label>
                <label class="block"><span class="label"><?= e(t('الدولة (كود من حرفين)')) ?></span><input name="address[<?= e($rid) ?>][country_code]" class="kyc-in input uppercase" dir="ltr" maxlength="2" value="<?= e((string)($a['country_code'] ?? $local)) ?>" <?= $local ? 'readonly' : '' ?> placeholder="EG"></label>
              </div>
              <?php if ($local): ?><p class="text-[11px] text-amber-700 mt-2"><?= e(t('العنوان لازم يكون جوه %s.', $local)) ?></p><?php endif; ?>
              <p class="text-[11px] text-ink-500 mt-1"><?= e(t('اكتب العنوان بالإنجليزي زي ما هو مكتوب في المستندات.')) ?></p>
            <?php elseif ($kind === 'file'): ?>
              <label class="kyc-drop flex items-center gap-3 rounded-xl border-2 border-dashed border-ink-200 hover:border-brand-400 p-4 cursor-pointer transition">
                <span class="w-10 h-10 shrink-0 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center">⇪</span>
                <span class="min-w-0 flex-1"><span class="kyc-fn block text-sm font-medium text-ink-800 truncate"><?= e(t('اختار ملف أو اسحبه هنا')) ?></span><span class="kyc-fs block text-xs text-ink-500"><?= e(t('PDF أو صورة · لحد 10 ميجا · البطاقة: الوشين في ملف واحد')) ?></span></span>
                <input type="file" name="docs[<?= e($rid) ?>]" accept="application/pdf,image/jpeg,image/png,image/webp" class="kyc-in sr-only">
              </label>
            <?php elseif ($kind === 'action'): ?>
              <div class="grid sm:grid-cols-2 gap-3">
                <label class="block"><span class="label"><?= e(t('الاسم الأول (زي البطاقة)')) ?></span><input name="first" class="kyc-in input" dir="auto" value="<?= e((string)($old['first'] ?? '')) ?>"></label>
                <label class="block"><span class="label"><?= e(t('اسم العيلة')) ?></span><input name="last" class="kyc-in input" dir="auto" value="<?= e((string)($old['last'] ?? '')) ?>"></label>
              </div>
              <p class="text-xs text-ink-500 mt-2"><?= e(t('بعد ما الطلب يتبعت، هيظهر لك في صفحة الأرقام لينك تحقق بالوش والبطاقة من الموبايل (دقيقتين).')) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        </div>
      </div>

      <aside class="lg:sticky lg:top-4 card p-5 space-y-4">
        <div>
          <div class="text-xs text-ink-500"><?= e($fix ? t('الرقم') : t('الطلب')) ?></div>
          <div class="text-lg font-bold text-ink-900 mt-1" dir="ltr" style="text-align:start"><?= e($fix ? (string)($num['phoneNumber'] ?? $fix) : ($phoneNumber ?: ($flag . ' ' . $cc))) ?></div>
          <div class="text-xs text-ink-500"><?= e(implode(' · ', array_filter([$typeName[$spec['numberType'] ?? $numberType] ?? '', $areaCode ? t('كود %s', $areaCode) : '', $wantsSms ? 'SMS' : '']))) ?></div>
        </div>
        <div>
          <div class="flex justify-between text-xs text-ink-500 mb-1"><span><?= e(t('اكتمال البيانات')) ?></span><span id="kyc-pct"></span></div>
          <div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div id="kyc-bar" class="h-full bg-brand-600 transition-all" style="width:0"></div></div>
          <ul id="kyc-miss" class="mt-2 text-xs text-ink-600 space-y-0.5"></ul>
        </div>
        <?php if (!$fix && $countryPrice !== null): ?>
          <div class="rounded-xl bg-ink-50 p-3 text-sm flex justify-between"><span class="text-ink-600"><?= e(t('في الشهر')) ?></span><b dir="ltr"><?= e(ph_money_cents($countryPrice)) ?></b></div>
        <?php endif; ?>
        <?php if ($preorder): ?><label class="flex items-center gap-2 text-sm"><input type="checkbox" checked disabled> <?= e(t('اطلبه مسبقًا (هيتوفر خلال 2–4 أسابيع)')) ?></label><?php endif; ?>
        <?php if (!$fix): ?>
          <label class="flex items-start gap-2 text-sm rounded-xl bg-amber-50 border border-amber-200 p-3 text-amber-900 cursor-pointer"><input type="checkbox" name="confirm" value="1" id="kyc-ok" class="mt-1">
            <span><?= e($countryPrice !== null ? t('فاهم إن الرقم هيتحسب عليه %s كل شهر بعد ما يتفعل، والبيانات صحيحة ومطابقة للمستندات.', ph_money_cents($countryPrice)) : t('البيانات صحيحة ومطابقة للمستندات، وفاهم إن الرقم بيتحسب بعد ما يتفعل.')) ?></span></label>
        <?php endif; ?>
        <button id="kyc-go" class="btn-primary w-full disabled:opacity-50"><?= e($fix ? t('ابعت التصحيحات') : t('ابعت البيانات واطلب الرقم')) ?></button>
        <p class="text-[11px] text-ink-400"><?= e(t('الأسماء والشروح جاية من مزود الأرقام بالإنجليزي زي ما هي عشان مايتغيرش معناها.')) ?></p>
      </aside>
    </form>
    <script>window.KYX = <?= json_encode(['fix' => (bool)$fix, 's' => ['step' => t('%s من %s'), 'pick' => t('اختار ملف أو اسحبه هنا'), 'big' => t('أكبر من 10 ميجا'), 'type' => t('لازم PDF أو صورة'), 'left' => t('ناقص:'), 'confirm' => t('هتبعت البيانات وتطلب الرقم دلوقتي. متأكد؟'), 'confirmFix' => t('هتبعت التصحيحات للمراجعة. متأكد؟'), 'sending' => t('بيتبعت...')]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-phone_kyc.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
