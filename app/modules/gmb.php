<?php
/**
 * جوجل بيزنس (Google Business Profile): التقييمات، بيانات الفرع، الصور، الخدمات، المنيو، روابط الحجز، والتحقق.
 * كل الدوال بتاخد Zernio $z وبتنادي $z->request مباشرة.
 */

/* ---------- الحسابات والفروع ---------- */

/** حسابات جوجل بيزنس المربوطة في الـ Profile الحالي */
function gmb_accounts(Zernio $z): array
{
    $r = $z->request('GET', '/v1/accounts', ['profileId' => zernio_profile(), 'platform' => 'googlebusiness']);
    return array_values(array_filter($r['accounts'] ?? [], function ($a) {
        return ($a['platform'] ?? '') === 'googlebusiness';
    }));
}

/** فروع (Locations) حساب جوجل بيزنس */
function gmb_locations(Zernio $z, string $accountId): array
{
    return $z->request('GET', '/v1/accounts/' . rawurlencode($accountId) . '/gmb-locations', ['limit' => 100]);
}

/**
 * كل الفروع المتاحة كخيارات: [['key' => 'acc|loc', 'account' => ..., 'location' => ..., 'label' => ...], ...]
 * لو جلب الفروع فشل لحساب، بنحط الحساب نفسه (الفرع المختار فيه).
 */
function gmb_location_options(Zernio $z, array $accounts): array
{
    $out = [];
    foreach ($accounts as $a) {
        $accId = (string)($a['_id'] ?? '');
        $accName = trim((string)($a['displayName'] ?? $a['username'] ?? $accId));
        $locs = [];
        try {
            $r = gmb_locations($z, $accId);
            $locs = $r['locations'] ?? [];
            $sel = (string)($r['selectedLocationId'] ?? '');
        } catch (ZernioException $ex) {
            $sel = '';
        }
        if (!$locs) {
            $out[] = ['key' => $accId . '|', 'account' => $accId, 'location' => $sel, 'label' => $accName];
            continue;
        }
        foreach ($locs as $l) {
            $name = trim((string)($l['name'] ?? ''));
            $label = $name ?: $accName;
            if (!empty($l['address'])) $label .= ' · ' . $l['address'];
            $out[] = ['key' => $accId . '|' . ($l['id'] ?? ''), 'account' => $accId, 'location' => (string)($l['id'] ?? ''), 'label' => $label];
        }
    }
    return $out;
}

/** يختار فرع: من ?loc= أو آخر اختيار في الجلسة أو أول فرع */
function gmb_pick_location(array $options, bool $allowAll = false): ?array
{
    if (!$options) return null;
    $want = (string)($_GET['loc'] ?? $_POST['loc'] ?? ($allowAll ? '' : ($_SESSION['gmb_loc'] ?? '')));
    foreach ($options as $o) {
        if ($o['key'] === $want) {
            if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['gmb_loc'] = $o['key'];
            return $o;
        }
    }
    return $allowAll ? null : $options[0];
}

/** قائمة اختيار الفرع */
function gmb_location_picker(array $options, ?array $current, string $page, bool $allowAll = false, array $keep = []): void
{
    if (!$options || (count($options) < 2 && !$allowAll)) return;
    ?>
    <form method="get" class="flex flex-wrap items-center gap-2 mb-4 text-sm">
      <input type="hidden" name="p" value="<?= e($page) ?>">
      <?php foreach ($keep as $k => $v): if ($v === '' || $v === null) continue; ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <span class="label !mb-0"><?= e(t('الفرع:')) ?></span>
      <select name="loc" class="input !mt-0 !w-auto max-w-full" onchange="this.form.submit()">
        <?php if ($allowAll): ?><option value=""><?= e(t('كل الحسابات والفروع')) ?></option><?php endif; ?>
        <?php foreach ($options as $o): ?>
          <option value="<?= e($o['key']) ?>" <?= $current && $o['key'] === $current['key'] ? 'selected' : '' ?>><?= e($o['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php
}

/** صندوق: مفيش حساب جوجل بيزنس مربوط (زرار الربط لصاحب الشركة بس) */
function gmb_no_account_box(): void
{
    ?>
    <div class="card p-8 text-center">
      <div class="w-14 h-14 mx-auto rounded-full bg-brand-50 text-brand-600 flex items-center justify-center mb-3">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1-5h16l1 5M3 9h18M3 9v11h18V9M9 20v-6h6v6"/></svg>
      </div>
      <p class="font-bold text-ink-900 mb-1"><?= e(t('مفيش حساب جوجل بيزنس مربوط')) ?></p>
      <p class="text-sm text-ink-500 mb-4"><?= e(t('اربط Google Business Profile عشان تدير التقييمات وبيانات الفرع والصور من هنا.')) ?></p>
      <?php if (is_ws_owner()): ?>
        <form method="post" action="<?= e(url('accounts')) ?>" class="inline">
          <?= csrf_field() ?><input type="hidden" name="action" value="connect"><input type="hidden" name="platform" value="googlebusiness">
          <button class="btn-primary"><?= e(t('ربط جوجل بيزنس')) ?></button>
        </form>
      <?php else: ?>
        <p class="text-xs text-ink-500"><?= e(t('اطلب من صاحب الشركة يربط حساب جوجل بيزنس.')) ?></p>
      <?php endif; ?>
    </div>
    <?php
}

function gmb_path(string $accountId, string $sub): string
{
    return '/v1/accounts/' . rawurlencode($accountId) . '/' . $sub;
}

/* ---------- التقييمات ---------- */

/** تقييمات كل المنصات (جوجل بيزنس + توصيات فيسبوك ...) */
function gmb_inbox_reviews(Zernio $z, array $filters): array
{
    return $z->request('GET', '/v1/inbox/reviews', $filters + ['profileId' => zernio_profile()]);
}

function gmb_inbox_reply(Zernio $z, string $reviewId, string $accountId, string $text): array
{
    return $z->request('POST', '/v1/inbox/reviews/' . rawurlencode($reviewId) . '/reply', [], ['accountId' => $accountId, 'message' => $text]);
}

function gmb_inbox_delete_reply(Zernio $z, string $reviewId, string $accountId): void
{
    $z->request('DELETE', '/v1/inbox/reviews/' . rawurlencode($reviewId) . '/reply', [], ['accountId' => $accountId]);
}

/** تقييمات فرع جوجل بيزنس معين */
function gmb_reviews(Zernio $z, string $accountId, string $locationId, ?string $pageToken = null): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-reviews'), ['locationId' => $locationId ?: null, 'pageSize' => 50, 'pageToken' => $pageToken]);
}

/** رد (أو تعديل الرد) على تقييم جوجل */
function gmb_reply_review(Zernio $z, string $accountId, string $reviewId, string $text): array
{
    return $z->request('POST', gmb_path($accountId, 'gmb-reviews/' . rawurlencode($reviewId) . '/reply'), [], ['comment' => $text]);
}

function gmb_delete_review_reply(Zernio $z, string $accountId, string $reviewId): void
{
    $z->request('DELETE', gmb_path($accountId, 'gmb-reviews/' . rawurlencode($reviewId) . '/reply'));
}

/** يحول تقييم من gmb-reviews لنفس شكل inbox/reviews */
function gmb_normalize_review(array $r, string $accountId, string $locationId): array
{
    $stars = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];
    $reply = $r['reviewReply'] ?? null;
    return [
        'id' => (string)($r['id'] ?? ''),
        'platform' => 'googlebusiness',
        'accountId' => $accountId,
        'locationId' => $locationId,
        'reviewer' => ['name' => $r['reviewer']['displayName'] ?? '', 'profileImage' => $r['reviewer']['profilePhotoUrl'] ?? null],
        'rating' => (int)($r['rating'] ?? ($stars[$r['starRating'] ?? ''] ?? 0)),
        'text' => (string)($r['comment'] ?? ''),
        'created' => $r['createTime'] ?? null,
        'hasReply' => !empty($reply['comment']),
        'reply' => $reply ? ['text' => $reply['comment'] ?? '', 'created' => $reply['updateTime'] ?? null] : null,
        'photos' => $r['photos'] ?? [],
        'reviewUrl' => null,
        '_gmb' => true,
    ];
}

/** نجوم */
function gmb_stars(int $n, string $cls = 'w-4 h-4'): string
{
    $o = '<span class="inline-flex" aria-label="' . e(t('%s من 5', $n)) . '">';
    for ($i = 1; $i <= 5; $i++) {
        $o .= '<svg class="' . $cls . ' ' . ($i <= $n ? 'text-amber-500' : 'text-ink-200') . '" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8L10 14.9l-5.3 2.7 1-5.8L1.5 7.7l5.9-.9z"/></svg>';
    }
    return $o . '</span>';
}

/* ---------- بيانات الفرع ---------- */

function gmb_location_details(Zernio $z, string $accountId, string $locationId): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-location-details'), [
        'locationId' => $locationId ?: null,
        'readMask' => 'name,title,phoneNumbers,websiteUri,regularHours,specialHours,profile,categories,serviceItems,metadata,openInfo,storefrontAddress',
    ]);
}

/** $body لازم يكون فيه updateMask */
function gmb_update_location(Zernio $z, string $accountId, string $locationId, array $body): array
{
    return $z->request('PUT', gmb_path($accountId, 'gmb-location-details'), ['locationId' => $locationId ?: null], $body);
}

function gmb_attributes(Zernio $z, string $accountId, string $locationId): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-attributes'), ['locationId' => $locationId ?: null])['attributes'] ?? [];
}

function gmb_attribute_metadata(Zernio $z, string $accountId, string $locationId): array
{
    $r = $z->request('GET', gmb_path($accountId, 'gmb-attribute-metadata'), ['locationId' => $locationId ?: null, 'languageCode' => lang(), 'pageSize' => 200]);
    $out = [];
    foreach ($r['attributeMetadata'] ?? [] as $m) {
        $key = preg_replace('#^attributes/#', '', (string)($m['parent'] ?? ''));
        if ($key !== '' && empty($m['deprecated'])) $out[$key] = $m;
    }
    return $out;
}

function gmb_update_attributes(Zernio $z, string $accountId, string $locationId, array $attributes, string $mask): array
{
    return $z->request('PUT', gmb_path($accountId, 'gmb-attributes'), ['locationId' => $locationId ?: null], ['attributes' => $attributes, 'attributeMask' => $mask]);
}

function gmb_services(Zernio $z, string $accountId, string $locationId): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-services'), ['locationId' => $locationId ?: null])['services'] ?? [];
}

function gmb_update_services(Zernio $z, string $accountId, string $locationId, array $items): array
{
    return $z->request('PUT', gmb_path($accountId, 'gmb-services'), ['locationId' => $locationId ?: null], ['serviceItems' => $items]);
}

function gmb_food_menus(Zernio $z, string $accountId, string $locationId): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-food-menus'), ['locationId' => $locationId ?: null])['menus'] ?? [];
}

function gmb_update_food_menus(Zernio $z, string $accountId, string $locationId, array $menus): array
{
    return $z->request('PUT', gmb_path($accountId, 'gmb-food-menus'), ['locationId' => $locationId ?: null], ['menus' => $menus, 'updateMask' => 'menus']);
}

function gmb_place_actions(Zernio $z, string $accountId, string $locationId): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-place-actions'), ['locationId' => $locationId ?: null, 'pageSize' => 100])['placeActionLinks'] ?? [];
}

function gmb_create_place_action(Zernio $z, string $accountId, string $locationId, string $type, string $uri): array
{
    return $z->request('POST', gmb_path($accountId, 'gmb-place-actions'), ['locationId' => $locationId ?: null], ['uri' => $uri, 'placeActionType' => $type]);
}

function gmb_update_place_action(Zernio $z, string $accountId, string $locationId, string $name, string $uri): array
{
    return $z->request('PATCH', gmb_path($accountId, 'gmb-place-actions'), ['locationId' => $locationId ?: null], ['name' => $name, 'uri' => $uri]);
}

function gmb_delete_place_action(Zernio $z, string $accountId, string $locationId, string $name): void
{
    $z->request('DELETE', gmb_path($accountId, 'gmb-place-actions'), ['locationId' => $locationId ?: null, 'name' => $name]);
}

function gmb_place_action_types(): array
{
    return [
        'APPOINTMENT' => t('حجز موعد'),
        'ONLINE_APPOINTMENT' => t('حجز موعد أونلاين'),
        'DINING_RESERVATION' => t('حجز ترابيزة'),
        'FOOD_ORDERING' => t('طلب أكل'),
        'FOOD_DELIVERY' => t('توصيل أكل'),
        'FOOD_TAKEOUT' => t('تيك أواي'),
        'SHOP_ONLINE' => t('تسوق أونلاين'),
    ];
}

function gmb_verifications(Zernio $z, string $accountId, string $locationId): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-verifications'), ['locationId' => $locationId ?: null]);
}

function gmb_days(): array
{
    return [
        'MONDAY' => t('الاتنين'), 'TUESDAY' => t('التلات'), 'WEDNESDAY' => t('الأربع'), 'THURSDAY' => t('الخميس'),
        'FRIDAY' => t('الجمعة'), 'SATURDAY' => t('السبت'), 'SUNDAY' => t('الحد'),
    ];
}

/** "09:00" من {hours, minutes} أو نص */
function gmb_time($t): string
{
    if (is_array($t)) {
        return sprintf('%02d:%02d', (int)($t['hours'] ?? 0), (int)($t['minutes'] ?? 0));
    }
    return (string)$t;
}

/** "09:00" -> {hours:9, minutes:0} */
function gmb_time_obj(string $s): array
{
    [$h, $m] = array_map('intval', explode(':', $s . ':0'));
    return ['hours' => $h, 'minutes' => $m];
}

function gmb_date_str($d): string
{
    if (!is_array($d)) return '';
    return sprintf('%04d-%02d-%02d', (int)($d['year'] ?? 0), (int)($d['month'] ?? 0), (int)($d['day'] ?? 0));
}

function gmb_date_obj(string $s): array
{
    [$y, $m, $d] = array_map('intval', explode('-', $s . '--'));
    return ['year' => $y, 'month' => $m, 'day' => $d];
}

/* ---------- الصور ---------- */

function gmb_media(Zernio $z, string $accountId, string $locationId, ?string $pageToken = null): array
{
    return $z->request('GET', gmb_path($accountId, 'gmb-media'), ['locationId' => $locationId ?: null, 'pageSize' => 60, 'pageToken' => $pageToken]);
}

function gmb_add_media(Zernio $z, string $accountId, string $locationId, string $sourceUrl, string $category, string $description = ''): array
{
    $body = ['sourceUrl' => $sourceUrl, 'mediaFormat' => 'PHOTO', 'category' => $category];
    if ($description !== '') $body['description'] = $description;
    return $z->request('POST', gmb_path($accountId, 'gmb-media'), ['locationId' => $locationId ?: null], $body);
}

function gmb_delete_media(Zernio $z, string $accountId, string $locationId, string $mediaId): void
{
    $z->request('DELETE', gmb_path($accountId, 'gmb-media'), ['locationId' => $locationId ?: null, 'mediaId' => $mediaId]);
}

function gmb_media_categories(): array
{
    return [
        'ADDITIONAL' => t('صور إضافية'), 'COVER' => t('صورة الغلاف'), 'PROFILE' => t('صورة البروفايل'), 'LOGO' => t('اللوجو'),
        'EXTERIOR' => t('من بره'), 'INTERIOR' => t('من جوه'), 'PRODUCT' => t('منتجات'), 'FOOD_AND_DRINK' => t('أكل ومشروبات'),
        'MENU' => t('المنيو'), 'COMMON_AREA' => t('أماكن عامة'), 'ROOMS' => t('غرف'), 'TEAMS' => t('الفريق'), 'AT_WORK' => t('في الشغل'),
    ];
}
