<?php
$err = null;
$z = zernio_or_null($err);
$accounts = [];
$acc = null;
$apiError = null;
if ($z) {
    try {
        $accounts = wa_accounts($z);
        $acc = wa_pick_account($accounts);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/wa_profile.json') ? json_decode((string)file_get_contents($fxd . '/wa_profile.json'), true) : null;
if ($fx) { $accounts = $fx['accounts']; $acc = wa_pick_account($accounts); $apiError = null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $acc && wa_is_sandbox($accounts, $acc)) {
    flash('error', t('مينفعش تعدل على الرقم التجريبي.'));
    redirect(url('wa_profile', ['wa' => $acc]));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $acc) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $about = trim((string)($_POST['about'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $sites = array_values(array_filter([trim((string)($_POST['web1'] ?? '')), trim((string)($_POST['web2'] ?? ''))], 'strlen'));
            $errors = [];
            if (mb_strlen($about) > 139) $errors[] = t('النبذة أطول من 139 حرف.');
            if (mb_strlen($desc) > 512) $errors[] = t('الوصف أطول من 512 حرف.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('الإيميل مش صحيح.');
            foreach ($sites as $s) if (!preg_match('~^https?://~i', $s)) $errors[] = t('الموقع لازم يبدأ بـ http:// أو https://');
            if ($errors) {
                flash('error', implode(' ', array_unique($errors)));
            } else {
                $payload = ['accountId' => $acc, 'about' => $about, 'address' => trim((string)($_POST['address'] ?? '')), 'description' => $desc, 'websites' => $sites];
                if ($email !== '') $payload['email'] = $email;
                $v = (string)($_POST['vertical'] ?? '');
                if ($v !== '') $payload['vertical'] = $v;
                wa_update_business_profile($z, $payload);
                log_activity('wa_profile.update', $acc);
                flash('success', t('تم حفظ بروفايل الواتساب.'));
            }
        } elseif ($action === 'photo') {
            $u = trim((string)($_POST['photo_url'] ?? ''));
            if (!preg_match('~^https://~i', $u)) {
                flash('error', t('اكتب رابط https مباشر لصورة JPG أو PNG.'));
            } else {
                wa_profile_photo_url($z, $acc, $u);
                log_activity('wa_profile.photo', $acc);
                flash('success', t('تم تغيير صورة البروفايل.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('wa_profile', ['wa' => $acc]));
}

$profile = [];
$info = [];
$infoError = null;
if ($fx) {
    $profile = $fx['profile']; $info = $fx['info'];
} elseif ($z && $acc && !$apiError) {
    try {
        $profile = wa_business_profile($z, $acc);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
    try {
        $info = wa_number_info($z, $acc);
    } catch (ZernioException $ex) {
        $infoError = $ex->getMessage();
    }
}

render(t('بروفايل واتساب'), 'wa_profile', function () use ($z, $fx, $accounts, $acc, $apiError, $profile, $info, $infoError) {
    if (!$z && !$fx) { no_api_box(); return; }
    if (!$acc && !$apiError) { wa_no_account_box(); return; }
    wa_account_picker($accounts, $acc, 'wa_profile');
    $sandbox = wa_is_sandbox($accounts, $acc);
    if ($sandbox) { wa_sandbox_note(); }
    if ($apiError) { error_box($apiError); }
    $ph = $info['phone'] ?? [];
    $waba = $info['waba'] ?? [];
    $q = strtoupper((string)($ph['quality_rating'] ?? 'UNKNOWN'));
    $qMap = ['GREEN' => [t('عالية'), '#16a34a', 3], 'YELLOW' => [t('متوسطة'), '#d97706', 2], 'RED' => [t('ضعيفة'), '#dc2626', 1]];
    [$qLabel, $qCol, $qLvl] = $qMap[$q] ?? [t('غير معروفة'), '#9ca3af', 0];
    $tiers = ['TIER_250' => '250', 'TIER_1K' => '1,000', 'TIER_10K' => '10,000', 'TIER_100K' => '100,000', 'TIER_UNLIMITED' => '∞'];
    $tier = (string)($ph['messaging_limit_tier'] ?? '');
    $tierIdx = array_search($tier, array_keys($tiers), true);
    $canSend = (string)($ph['health_status']['can_send_message'] ?? '');
    $bv = strtolower((string)($waba['business_verification_status'] ?? ''));
    $verticals = ['' => t('— بدون تغيير —'), 'AUTO' => 'Automotive', 'BEAUTY' => 'Beauty, Spa & Salon', 'APPAREL' => 'Clothing & Apparel', 'EDU' => 'Education', 'ENTERTAIN' => 'Entertainment', 'EVENT_PLAN' => 'Event Planning', 'FINANCE' => 'Finance & Banking', 'GROCERY' => 'Food & Grocery', 'GOVT' => 'Public Service', 'HOTEL' => 'Hotel & Lodging', 'HEALTH' => 'Medical & Health', 'NONPROFIT' => 'Non-profit', 'PROF_SERVICES' => 'Professional Services', 'RETAIL' => 'Shopping & Retail', 'TRAVEL' => 'Travel & Transportation', 'RESTAURANT' => 'Restaurant', 'OTHER' => 'Other'];
    $name = (string)($ph['verified_name'] ?? ($profile['about'] ?? 'WhatsApp'));
    $w = (array)($profile['websites'] ?? []);
    $row = function (string $label, string $val, string $tone = '') {
        echo '<div class="flex items-center justify-between gap-3 py-2.5"><dt class="text-sm text-ink-500">' . e($label) . '</dt><dd class="text-sm font-medium ' . ($tone ?: 'text-ink-900') . ' text-end">' . e($val) . '</dd></div>';
    };
    ?>
    <div class="grid xl:grid-cols-[320px_minmax(0,1fr)_300px] lg:grid-cols-[minmax(0,1fr)_300px] gap-6 items-start">

      <?php /* ---------- صحة الرقم ---------- */ ?>
      <div class="space-y-4 lg:order-last xl:order-none">
        <div class="card p-5">
          <div class="text-xs text-ink-500"><?= e(t('حالة الرقم')) ?></div>
          <?php if ($infoError): ?><div class="mt-2"><?php error_box($infoError); ?></div><?php else: ?>
          <div class="text-xl font-bold text-ink-900 mt-1" dir="ltr" style="text-align:start"><?= e($ph['display_phone_number'] ?? '') ?></div>
          <div class="flex flex-wrap items-center gap-1.5 mt-1 text-sm"><span class="text-ink-700"><?= e($ph['verified_name'] ?? '') ?></span>
            <?php if (!empty($ph['is_official_business_account'])): ?><span class="rounded-full bg-lime-50 text-lime-700 text-[11px] px-2 py-0.5">✓ <?= e(t('حساب رسمي')) ?></span><?php endif; ?>
            <?php if ($canSend === 'AVAILABLE'): ?><span class="rounded-full bg-lime-50 text-lime-700 text-[11px] px-2 py-0.5"><?= e(t('بيبعت عادي')) ?></span><?php elseif ($canSend): ?><span class="rounded-full bg-red-50 text-red-700 text-[11px] px-2 py-0.5"><?= e(t('الإرسال متوقف: %s', $canSend)) ?></span><?php endif; ?></div>

          <div class="mt-5">
            <div class="flex items-center justify-between text-sm mb-2"><span class="text-ink-500"><?= e(t('جودة الرقم')) ?></span><b style="color:<?= $qCol ?>"><?= e($qLabel) ?></b></div>
            <div class="grid grid-cols-3 gap-1"><?php foreach ([1 => '#dc2626', 2 => '#d97706', 3 => '#16a34a'] as $k => $c): ?><span class="h-2 rounded-full" style="background:<?= $k <= $qLvl ? $c : '#eef0f3' ?>"></span><?php endforeach; ?></div>
            <p class="text-[11px] text-ink-500 mt-2"><?= e($q === 'GREEN' ? t('ممتاز. كمّل ابعت رسايل الناس طالباها.') : t('الجودة بتقل لما الناس تبلّك أو تبلغ عن رسايلك. ابعت للي طالبين بس، وخلّي فيه طريقة للإلغاء.')) ?></p>
          </div>

          <div class="mt-5">
            <div class="text-sm text-ink-500 mb-2"><?= e(t('حد المحادثات اليومي (عملاء جداد في 24 ساعة)')) ?></div>
            <div class="flex items-end gap-1" dir="ltr">
              <?php $i = 0; foreach ($tiers as $k => $l): $on = $tierIdx !== false && $i <= $tierIdx; $cur = $i === $tierIdx; ?>
                <div class="flex-1 text-center">
                  <div class="rounded-t-md mx-auto <?= $cur ? 'bg-brand-600' : ($on ? 'bg-brand-200' : 'bg-ink-100') ?>" style="height:<?= 10 + $i * 8 ?>px"></div>
                  <div class="text-[10px] mt-1 <?= $cur ? 'font-bold text-brand-700' : 'text-ink-400' ?>" dir="ltr"><?= e($l) ?></div>
                </div>
              <?php $i++; endforeach; ?>
            </div>
            <?php if ($tierIdx !== false && $tierIdx < 4): ?><p class="text-[11px] text-ink-500 mt-2"><?= e(t('Meta بترفع الحد لوحدها لما تستخدم نصه على الأقل خلال 7 أيام والجودة مش ضعيفة.')) ?></p><?php endif; ?>
          </div>

          <dl class="divide-y divide-ink-100 mt-4 border-t border-ink-100">
            <?php
            $row(t('حالة الاسم'), (string)($ph['name_status'] ?? '—'), ($ph['name_status'] ?? '') === 'APPROVED' ? 'text-lime-700' : '');
            $row(t('الحالة'), (string)($ph['status'] ?? '—'));
            $row(t('السرعة'), (string)($ph['throughput']['level'] ?? '—'));
            if ($waba) {
                $row(t('حساب البيزنس (WABA)'), (string)($waba['name'] ?? ''));
                $row(t('توثيق البيزنس'), $bv === 'verified' ? t('موثّق') : ($bv ? t('مش موثّق') : '—'), $bv === 'verified' ? 'text-lime-700' : 'text-amber-700');
            }
            ?>
          </dl>
          <?php if ($waba && $bv !== 'verified'): ?><p class="text-[11px] text-amber-700 mt-2"><?= e(t('توثيق البيزنس من Meta Business Manager بيفتح حدود أعلى وبيسمح تطلب العلامة الخضرا.')) ?></p><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$apiError && !$sandbox): ?>
      <?php /* ---------- التعديل ---------- */ ?>
      <div class="space-y-4 min-w-0">
        <div class="card p-5" id="wp-score"></div>
        <form method="post" class="card p-5 space-y-4" id="wp-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="wa" value="<?= e($acc) ?>">
          <div class="flex items-center gap-2"><h2 class="font-bold text-ink-900 me-auto"><?= e(t('بروفايل البيزنس')) ?></h2><span id="wp-dirty" class="rounded-full bg-amber-100 text-amber-800 text-xs px-2.5 py-0.5" style="display:none"><?= e(t('فيه تعديلات مش محفوظة')) ?></span></div>
          <label class="block"><span class="label flex justify-between"><span><?= e(t('نبذة (About)')) ?></span><span class="wp-c text-[11px] text-ink-400" dir="ltr" data-for="about" data-max="139"></span></span><input name="about" maxlength="139" class="input" dir="auto" value="<?= e($profile['about'] ?? '') ?>" placeholder="<?= e(t('سطر قصير بيظهر تحت اسمك')) ?>"></label>
          <label class="block"><span class="label flex justify-between"><span><?= e(t('الوصف')) ?></span><span class="wp-c text-[11px] text-ink-400" dir="ltr" data-for="description" data-max="512"></span></span><textarea data-ai="wa_about" name="description" maxlength="512" rows="4" class="input" dir="auto"><?= e($profile['description'] ?? '') ?></textarea></label>
          <label class="block"><span class="label"><?= e(t('العنوان')) ?></span><input name="address" class="input" dir="auto" value="<?= e($profile['address'] ?? '') ?>"></label>
          <div class="grid sm:grid-cols-2 gap-4">
            <label class="block"><span class="label"><?= e(t('الإيميل')) ?></span><input name="email" type="email" dir="ltr" class="input" value="<?= e($profile['email'] ?? '') ?>"></label>
            <label class="block"><span class="label"><?= e(t('المجال')) ?></span><select name="vertical" class="input">
              <?php $cur = (string)($profile['vertical'] ?? ''); foreach ($verticals as $k => $l): ?><option value="<?= e($k) ?>" <?= $k !== '' && $k === $cur ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select></label>
            <label class="block"><span class="label"><?= e(t('موقع 1')) ?></span><input name="web1" type="url" dir="ltr" class="input" value="<?= e($w[0] ?? '') ?>" placeholder="https://"></label>
            <label class="block"><span class="label"><?= e(t('موقع 2')) ?></span><input name="web2" type="url" dir="ltr" class="input" value="<?= e($w[1] ?? '') ?>" placeholder="https://"></label>
          </div>
          <div class="flex justify-end pt-2 border-t border-ink-100"><button class="btn-primary"><?= e(t('حفظ')) ?></button></div>
        </form>
        <form method="post" class="card p-5" id="wp-photo">
          <?= csrf_field() ?><input type="hidden" name="action" value="photo"><input type="hidden" name="wa" value="<?= e($acc) ?>">
          <h2 class="font-bold text-ink-900 mb-1"><?= e(t('تغيير صورة البروفايل')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('مربعة، JPG أو PNG، يفضل 640×640. اختار من المكتبة أو حط رابط https.')) ?></p>
          <div class="flex items-start gap-3">
            <span id="wp-ph-prev" class="w-14 h-14 rounded-full bg-ink-100 overflow-hidden shrink-0"></span>
            <div class="flex-1 min-w-0 space-y-2">
              <input name="photo_url" id="wp-ph" type="url" dir="ltr" required data-media="image" class="input !mt-0 w-full" placeholder="https://">
              <button class="btn-primary"><?= e(t('تغيير الصورة')) ?></button>
            </div>
          </div>
          <p id="wp-ph-warn" class="text-[11px] text-amber-700 mt-2" style="display:none"></p>
        </form>
      </div>

      <?php /* ---------- المعاينة ---------- */ ?>
      <aside class="lg:sticky lg:top-4">
        <div class="text-xs text-ink-500 mb-2 text-center"><?= e(t('هيظهر للعميل كده')) ?></div>
        <div class="mx-auto w-[280px] rounded-[2.2rem] border-[10px] border-ink-900 bg-white overflow-hidden shadow-xl">
          <div class="bg-[#f0f2f5] h-6"></div>
          <div class="px-4 pb-5 text-center bg-white">
            <div id="pv-photo" class="w-24 h-24 rounded-full mx-auto -mt-1 bg-[#dfe5e7] overflow-hidden flex items-center justify-center text-3xl font-bold text-white"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
            <div class="mt-3 text-lg font-medium text-ink-900"><?= e($name) ?></div>
            <div class="text-xs text-ink-500" dir="ltr"><?= e($ph['display_phone_number'] ?? '') ?></div>
            <div id="pv-cat" class="text-[11px] text-ink-400 mt-1"></div>
          </div>
          <div class="bg-[#f0f2f5] p-2 space-y-2 text-start">
            <div class="bg-white rounded-lg p-3"><div id="pv-about" class="text-sm text-ink-900 whitespace-pre-line" dir="auto"></div><div class="text-[10px] text-ink-400 mt-1"><?= e(t('نبذة')) ?></div></div>
            <div class="bg-white rounded-lg p-3 space-y-2 text-[13px]" id="pv-info"></div>
          </div>
        </div>
        <p class="text-[11px] text-ink-400 mt-3 text-center"><?= e(t('معاينة تقريبية — شكل واتساب الحقيقي ممكن يختلف شوية.')) ?></p>
      </aside>
      <?php endif; ?>
    </div>
    <script>window.WPX = <?= json_encode(['verticals' => $verticals, 'cur' => (string)($profile['vertical'] ?? ''), 'photo' => (string)($profile['profilePictureUrl'] ?? ''), 's' => [
      'score' => t('اكتمال البروفايل'), 'hint' => t('البروفايل الكامل بيخلي العميل يثق ويرد أسرع.'),
      'c_photo' => t('صورة بروفايل'), 'c_about' => t('نبذة'), 'c_desc' => t('وصف أطول من 80 حرف'), 'c_addr' => t('العنوان'), 'c_email' => t('الإيميل'), 'c_web' => t('موقع إلكتروني'), 'c_cat' => t('المجال'),
      'leave' => t('فيه تعديلات مش محفوظة. تخرج؟'), 'sq' => t('الصورة مش مربعة (%s×%s) — واتساب هيقصها دايرة من النص.'), 'small' => t('الصورة صغيرة (%s×%s) — هتبان مش واضحة.'), 'bad' => t('مش قادرين نفتح الصورة من الرابط ده.'),
    ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-wa_profile.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
