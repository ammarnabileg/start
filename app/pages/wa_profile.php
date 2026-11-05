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
if ($z && $acc && !$apiError) {
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

render(t('بروفايل واتساب'), 'wa_profile', function () use ($z, $accounts, $acc, $apiError, $profile, $info, $infoError) {
    if (!$z) { no_api_box(); return; }
    if (!$acc && !$apiError) { wa_no_account_box(); return; }
    wa_account_picker($accounts, $acc, 'wa_profile');
    if (wa_is_sandbox($accounts, $acc)) { wa_sandbox_note(); }
    if ($apiError) { error_box($apiError); }
    $ph = $info['phone'] ?? [];
    $waba = $info['waba'] ?? [];
    $q = strtoupper((string)($ph['quality_rating'] ?? 'UNKNOWN'));
    $qMap = ['GREEN' => [t('عالية'), 'badge badge-green'], 'YELLOW' => [t('متوسطة'), 'badge bg-amber-100 text-amber-800'], 'RED' => [t('ضعيفة'), 'badge bg-red-100 text-red-700']];
    [$qLabel, $qCls] = $qMap[$q] ?? [t('غير معروفة'), 'badge'];
    $tierMap = ['TIER_250' => '250', 'TIER_1K' => '1,000', 'TIER_10K' => '10,000', 'TIER_100K' => '100,000', 'TIER_UNLIMITED' => t('بلا حدود')];
    $tier = (string)($ph['messaging_limit_tier'] ?? '');
    $canSend = (string)($ph['health_status']['can_send_message'] ?? '');
    $verticals = ['' => t('— بدون تغيير —'), 'AUTO' => 'Automotive', 'BEAUTY' => 'Beauty, Spa & Salon', 'APPAREL' => 'Clothing & Apparel', 'EDU' => 'Education', 'ENTERTAIN' => 'Entertainment', 'EVENT_PLAN' => 'Event Planning', 'FINANCE' => 'Finance & Banking', 'GROCERY' => 'Food & Grocery', 'GOVT' => 'Public Service', 'HOTEL' => 'Hotel & Lodging', 'HEALTH' => 'Medical & Health', 'NONPROFIT' => 'Non-profit', 'PROF_SERVICES' => 'Professional Services', 'RETAIL' => 'Shopping & Retail', 'TRAVEL' => 'Travel & Transportation', 'RESTAURANT' => 'Restaurant', 'OTHER' => 'Other'];
    ?>
    <div class="grid lg:grid-cols-3 gap-4">
      <div class="card p-5 space-y-3">
        <h2 class="font-semibold"><?= e(t('حالة الرقم')) ?></h2>
        <?php if ($infoError): ?><?php error_box($infoError); ?><?php else: ?>
        <div class="text-2xl font-semibold" dir="ltr"><?= e($ph['display_phone_number'] ?? '') ?></div>
        <div class="text-sm"><?= e($ph['verified_name'] ?? '') ?> <?php if (!empty($ph['is_official_business_account'])): ?><span class="badge badge-green"><?= e(t('حساب رسمي')) ?></span><?php endif; ?></div>
        <dl class="text-sm divide-y divide-ink-100">
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('جودة الرقم')) ?></dt><dd><span class="<?= $qCls ?>"><?= e($qLabel) ?></span></dd></div>
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('حد الرسايل اليومي')) ?></dt><dd><?= e($tierMap[$tier] ?? ($tier ?: '—')) ?></dd></div>
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('يقدر يبعت؟')) ?></dt><dd><?php if ($canSend === 'AVAILABLE'): ?><span class="badge badge-green"><?= e(t('أيوه')) ?></span><?php elseif ($canSend): ?><span class="badge bg-red-100 text-red-700"><?= e($canSend) ?></span><?php else: ?>—<?php endif; ?></dd></div>
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('حالة الاسم')) ?></dt><dd><?= e($ph['name_status'] ?? '—') ?></dd></div>
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('الحالة')) ?></dt><dd><?= e($ph['status'] ?? '—') ?></dd></div>
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('السرعة')) ?></dt><dd><?= e($ph['throughput']['level'] ?? '—') ?></dd></div>
          <?php if ($waba): ?>
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('حساب البيزنس (WABA)')) ?></dt><dd><?= e($waba['name'] ?? '') ?></dd></div>
          <div class="flex justify-between py-2"><dt class="text-ink-500"><?= e(t('توثيق البيزنس')) ?></dt><dd><?= e($waba['business_verification_status'] ?? '—') ?></dd></div>
          <?php endif; ?>
        </dl>
        <p class="text-xs text-ink-500"><?= e(t('الجودة بتقل لما الناس تبلّك أو تبلغ عن رسايلك. لو بقت ضعيفة، Meta ممكن تقلل حد الرسايل.')) ?></p>
        <?php endif; ?>
      </div>

      <?php if (!$apiError && !wa_is_sandbox($accounts, $acc)): ?>
      <div class="lg:col-span-2 space-y-4">
        <form method="post" class="card p-5 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="wa" value="<?= e($acc) ?>">
          <div class="flex items-center gap-3">
            <?= avatar($profile['profilePictureUrl'] ?? null, (string)($ph['verified_name'] ?? 'W'), 'w-16 h-16') ?>
            <h2 class="font-semibold"><?= e(t('بروفايل البيزنس')) ?></h2>
          </div>
          <label class="block"><span class="label"><?= e(t('نبذة (About)')) ?></span><input name="about" maxlength="139" class="input" value="<?= e($profile['about'] ?? '') ?>"></label>
          <label class="block"><span class="label"><?= e(t('الوصف')) ?></span><textarea data-ai="wa_about" name="description" maxlength="512" rows="3" class="input"><?= e($profile['description'] ?? '') ?></textarea></label>
          <label class="block"><span class="label"><?= e(t('العنوان')) ?></span><input name="address" class="input" value="<?= e($profile['address'] ?? '') ?>"></label>
          <div class="grid sm:grid-cols-2 gap-3">
            <label class="block"><span class="label"><?= e(t('الإيميل')) ?></span><input name="email" type="email" dir="ltr" class="input" value="<?= e($profile['email'] ?? '') ?>"></label>
            <label class="block"><span class="label"><?= e(t('المجال')) ?></span><select name="vertical" class="input">
              <?php $cur = (string)($profile['vertical'] ?? ''); foreach ($verticals as $k => $l): ?><option value="<?= e($k) ?>" <?= $k !== '' && $k === $cur ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select></label>
            <?php $w = (array)($profile['websites'] ?? []); ?>
            <label class="block"><span class="label"><?= e(t('موقع 1')) ?></span><input name="web1" dir="ltr" class="input" value="<?= e($w[0] ?? '') ?>"></label>
            <label class="block"><span class="label"><?= e(t('موقع 2')) ?></span><input name="web2" dir="ltr" class="input" value="<?= e($w[1] ?? '') ?>"></label>
          </div>
          <button class="btn-primary"><?= e(t('حفظ')) ?></button>
        </form>
        <form method="post" class="card p-5 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="photo"><input type="hidden" name="wa" value="<?= e($acc) ?>">
          <h2 class="font-semibold"><?= e(t('تغيير صورة البروفايل')) ?></h2>
          <label class="block"><span class="label"><?= e(t('رابط الصورة (https، JPG أو PNG، يفضل 640×640)')) ?></span><input name="photo_url" type="url" dir="ltr" required data-media="image" class="input" placeholder="https://"></label>
          <button class="btn-ghost"><?= e(t('تغيير الصورة')) ?></button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php
});
