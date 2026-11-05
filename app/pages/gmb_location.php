<?php
$err = null;
$z = zernio_or_null($err);

$accounts = [];
$options = [];
$apiError = null;
if ($z) {
    try {
        $accounts = gmb_accounts($z);
        $options = gmb_location_options($z, $accounts);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/gmb_location.json') ? json_decode((string)file_get_contents($fxd . '/gmb_location.json'), true) : null;
if ($fx && is_file($fxd . '/gmb_fake_z.php')) $z = require $fxd . '/gmb_fake_z.php'; // اختبارات بس: بيسجّل الكتابات بدل ما يبعتها
if ($fx) { $accounts = $accounts ?: [['id' => 'acc_gmb_demo']]; $options = $fx['locations'] ?? [$fx['location']]; $apiError = null; }
$loc = gmb_pick_location($options);

/** يحول سطور "اسم | سعر" لـ serviceItems */
function gmb_parse_services(string $text, string $currency): array
{
    $items = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (($parts[0] ?? '') === '') continue;
        $it = ['freeFormServiceItem' => ['category' => '', 'label' => ['displayName' => mb_substr($parts[0], 0, 140)]]];
        $price = str_replace(',', '.', (string)($parts[1] ?? ''));
        if ($price !== '' && is_numeric($price)) {
            $it['price'] = ['currencyCode' => $currency, 'units' => (string)(int)floor((float)$price), 'nanos' => (int)round(((float)$price - floor((float)$price)) * 1e9)];
        }
        $items[] = $it;
    }
    return $items;
}

function gmb_money(?array $p): string
{
    if (!$p || (!isset($p['units']) && !isset($p['nanos']))) return '';
    $v = (float)($p['units'] ?? 0) + ((int)($p['nanos'] ?? 0)) / 1e9;
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $loc) {
    $action = (string)($_POST['action'] ?? '');
    $acc = $loc['account'];
    $lid = $loc['location'];
    // تطبيق نفس التعديل على فروع تانية مختارة (بس من الفروع اللي المستخدم يقدر يشوفها)
    $applyAll = function (array $body, string $logKey, string $okMsg) use ($z, $acc, $lid, $loc, $options) {
        gmb_update_location($z, $acc, $lid, $body);
        log_activity($logKey, $lid);
        $want = array_map('strval', (array)($_POST['also'] ?? []));
        $ok = 1; $fail = [];
        foreach ($options as $o) {
            if (!in_array($o['key'], $want, true) || $o['key'] === $loc['key'] || $o['location'] === '') continue;
            try { gmb_update_location($z, $o['account'], $o['location'], $body); log_activity($logKey, $o['location']); $ok++; }
            catch (ZernioException $ex) { $fail[] = $o['label'] . ': ' . $ex->getMessage(); }
        }
        flash('success', $ok > 1 ? t('%s — اتحفظ على %s فروع.', $okMsg, $ok) : $okMsg);
        if ($fail) flash('error', t('ما اتحفظش على الفروع دي:') . ' ' . implode(' · ', $fail));
    };
    try {
        if ($action === 'basic') {
            $web = trim((string)($_POST['website'] ?? ''));
            if ($web !== '' && !filter_var($web, FILTER_VALIDATE_URL)) throw new ZernioException(t('رابط الموقع مش صحيح.'));
            $desc = trim((string)($_POST['description'] ?? ''));
            if (mb_strlen($desc) > 750) throw new ZernioException(t('الوصف لازم يكون 750 حرف أو أقل.'));
            $body = [
                'updateMask' => 'phoneNumbers.primaryPhone,websiteUri,profile.description',
                'phoneNumbers' => ['primaryPhone' => trim((string)($_POST['phone'] ?? ''))],
                'websiteUri' => $web,
                'profile' => ['description' => $desc],
            ];
            if (isset($_POST['title'])) {
                $title = trim((string)$_POST['title']);
                if ($title === '') throw new ZernioException(t('اسم النشاط مينفعش يبقى فاضي.'));
                $body['title'] = $title;
                $body['updateMask'] .= ',title';
            }
            gmb_update_location($z, $acc, $lid, $body);
            log_activity('gmb.location_update', $lid);
            flash('success', t('تم حفظ بيانات الفرع.'));
        } elseif ($action === 'hours') {
            $periods = [];
            foreach (array_keys(gmb_days()) as $day) {
                if (empty($_POST['open'][$day])) continue;
                $o = (string)($_POST['from'][$day] ?? '');
                $c = (string)($_POST['to'][$day] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $o) || !preg_match('/^\d{2}:\d{2}$/', $c)) throw new ZernioException(t('اكتب مواعيد الفتح والقفل لكل يوم شغال.'));
                // لو القفل بعد نص الليل يبقى اليوم اللي بعده
                $closeDay = $day;
                if ($c <= $o) {
                    $keys = array_keys(gmb_days());
                    $closeDay = $keys[(array_search($day, $keys, true) + 1) % 7];
                }
                $periods[] = ['openDay' => $day, 'openTime' => gmb_time_obj($o), 'closeDay' => $closeDay, 'closeTime' => gmb_time_obj($c === '00:00' ? '24:00' : $c)];
            }
            $applyAll(['updateMask' => 'regularHours', 'regularHours' => ['periods' => $periods]], 'gmb.hours_update', t('تم حفظ مواعيد العمل.'));
        } elseif ($action === 'special') {
            $list = [];
            foreach ((array)($_POST['sp_date'] ?? []) as $i => $date) {
                $date = (string)$date;
                if ($date === '') continue;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new ZernioException(t('تاريخ مش صحيح.'));
                $closed = !empty($_POST['sp_closed'][$i]);
                $p = ['startDate' => gmb_date_obj($date), 'endDate' => gmb_date_obj($date), 'closed' => $closed];
                if (!$closed) {
                    $o = (string)($_POST['sp_from'][$i] ?? '');
                    $c = (string)($_POST['sp_to'][$i] ?? '');
                    if ($o === '' || $c === '') throw new ZernioException(t('حدد مواعيد اليوم الخاص أو علّم إنه مقفول.'));
                    $p['openTime'] = gmb_time_obj($o);
                    $p['closeTime'] = gmb_time_obj($c);
                }
                $list[] = $p;
            }
            $applyAll(['updateMask' => 'specialHours', 'specialHours' => ['specialHourPeriods' => $list]], 'gmb.special_hours_update', t('تم حفظ المواعيد الخاصة.'));
        } elseif ($action === 'attributes') {
            $names = array_values(array_filter(array_map('strval', (array)($_POST['attr_names'] ?? [])), function ($n) { return preg_match('/^[a-z0-9_]+$/', $n); }));
            if (!$names) throw new ZernioException(t('مفيش خصائص تتحفظ.'));
            $attrs = [];
            foreach ($names as $n) {
                $attrs[] = ['name' => 'attributes/' . $n, 'valueType' => 'BOOL', 'values' => [!empty($_POST['attr'][$n])]];
            }
            gmb_update_attributes($z, $acc, $lid, $attrs, implode(',', $names));
            log_activity('gmb.attributes_update', $lid);
            flash('success', t('تم حفظ الخصائص.'));
        } elseif ($action === 'services') {
            $cur = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['currency'] ?? cx('cur')))) ?: cx('cur');
            $items = gmb_parse_services((string)($_POST['services'] ?? ''), $cur);
            // نحافظ على الخدمات المنظمة (structured) اللي اتعملت من جوجل
            foreach ((array)($_POST['keep_structured'] ?? []) as $sid) {
                $items[] = ['structuredServiceItem' => ['serviceTypeId' => (string)$sid]];
            }
            gmb_update_services($z, $acc, $lid, $items);
            log_activity('gmb.services_update', $lid);
            flash('success', t('تم حفظ الخدمات.'));
        } elseif ($action === 'menu') {
            $cur = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['currency'] ?? cx('cur')))) ?: cx('cur');
            $menuName = trim((string)($_POST['menu_name'] ?? '')) ?: 'Menu';
            $sections = [];
            foreach (preg_split('/\R/', (string)($_POST['menu'] ?? '')) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') continue;
                $item = ['labels' => [['displayName' => $parts[1]]]];
                $price = str_replace(',', '.', (string)($parts[2] ?? ''));
                if ($price !== '' && is_numeric($price)) {
                    $item['attributes'] = ['price' => ['currencyCode' => $cur, 'units' => (string)(int)floor((float)$price), 'nanos' => (int)round(((float)$price - floor((float)$price)) * 1e9)]];
                }
                $sections[$parts[0]][] = $item;
            }
            if (!$sections) throw new ZernioException(t('اكتب صنف واحد على الأقل بالشكل: القسم | الصنف | السعر'));
            $secs = [];
            foreach ($sections as $sn => $its) $secs[] = ['labels' => [['displayName' => $sn]], 'items' => $its];
            gmb_update_food_menus($z, $acc, $lid, [['labels' => [['displayName' => $menuName]], 'sections' => $secs]]);
            log_activity('gmb.menu_update', $lid);
            flash('success', t('تم حفظ المنيو.'));
        } elseif ($action === 'action_add') {
            $type = (string)($_POST['type'] ?? '');
            $uri = trim((string)($_POST['uri'] ?? ''));
            if (!isset(gmb_place_action_types()[$type])) throw new ZernioException(t('اختار نوع الرابط.'));
            if (!filter_var($uri, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $uri)) throw new ZernioException(t('الرابط لازم يبدأ بـ https://'));
            gmb_create_place_action($z, $acc, $lid, $type, $uri);
            log_activity('gmb.place_action_add', $type);
            flash('success', t('تم إضافة الرابط.'));
        } elseif ($action === 'action_edit') {
            $uri = trim((string)($_POST['uri'] ?? ''));
            if (!filter_var($uri, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $uri)) throw new ZernioException(t('الرابط لازم يبدأ بـ https://'));
            gmb_update_place_action($z, $acc, $lid, (string)($_POST['name'] ?? ''), $uri);
            log_activity('gmb.place_action_edit', (string)($_POST['name'] ?? ''));
            flash('success', t('تم تعديل الرابط.'));
        } elseif ($action === 'action_delete' && can('gmb.manage')) {
            gmb_delete_place_action($z, $acc, $lid, (string)($_POST['name'] ?? ''));
            log_activity('gmb.place_action_delete', (string)($_POST['name'] ?? ''));
            flash('success', t('تم حذف الرابط.'));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('gmb_location', ['loc' => $loc['key']]) . '#' . preg_replace('/[^a-z_]/', '', $action));
}

$d = null;
$attrs = [];
$meta = [];
$services = [];
$menus = [];
$actions = [];
$verif = null;
$errors = [];
if ($fx) {
    foreach (['d', 'attrs', 'meta', 'services', 'menus', 'actions', 'verif'] as $k) $$k = $fx[$k] ?? ($k === 'd' || $k === 'verif' ? null : []);
} elseif ($z && $loc) {
    $acc = $loc['account'];
    $lid = $loc['location'];
    try {
        $d = gmb_location_details($z, $acc, $lid);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
    // الأجزاء التانية: لو واحد فشل نكمل الباقي
    $try = function (string $k, callable $fn) use (&$errors) {
        try { return $fn(); } catch (ZernioException $ex) { $errors[$k] = $ex->getMessage(); return null; }
    };
    $verif = $try('verif', function () use ($z, $acc, $lid) { return gmb_verifications($z, $acc, $lid); });
    $attrs = $try('attrs', function () use ($z, $acc, $lid) { return gmb_attributes($z, $acc, $lid); }) ?: [];
    $meta = $try('meta', function () use ($z, $acc, $lid) { return gmb_attribute_metadata($z, $acc, $lid); }) ?: [];
    $services = $try('services', function () use ($z, $acc, $lid) { return gmb_services($z, $acc, $lid); }) ?: [];
    $menus = $try('menus', function () use ($z, $acc, $lid) { return gmb_food_menus($z, $acc, $lid); }) ?: [];
    $actions = $try('actions', function () use ($z, $acc, $lid) { return gmb_place_actions($z, $acc, $lid); }) ?: [];
}

render(t('بيانات الفرع على جوجل'), 'gmb_location', function () use ($z, $accounts, $options, $loc, $d, $attrs, $meta, $services, $menus, $actions, $verif, $errors, $apiError) {
    if (!$z && !getenv('UI_FIXTURES')) { no_api_box(); return; }
    if (!$accounts && !$apiError) { gmb_no_account_box(); return; }
    if ($apiError) { error_box($apiError); echo '<div class="h-4"></div>'; }

    $hidden = '<input type="hidden" name="loc" value="' . e($loc['key'] ?? '') . '">';
    $location = $d['location'] ?? [];
    $cats = $d['categories'] ?? [];
    $catText = strtolower(json_encode($cats));
    $isFood = $menus || preg_match('/restaurant|cafe|food|bakery|pizza|coffee|meal|diner|bar/', $catText);
    $byDay = [];
    foreach ($d['regularHours']['periods'] ?? [] as $p) $byDay[$p['openDay'] ?? ''] = $p;
    $err = function (string $k) use ($errors) {
        if (!empty($errors[$k])) echo '<p class="text-xs text-red-600 mb-3 rounded-lg bg-red-50 px-3 py-2">' . e($errors[$k]) . '</p>';
    };
    $ico = [
        'basic' => 'M3 9l1-5h16l1 5M3 9h18M3 9v11h18V9M9 20v-6h6v6',
        'hours' => 'M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z',
        'special' => 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
        'attributes' => 'M5 13l4 4L19 7',
        'services' => 'M4 6h16M4 12h16M4 18h10',
        'action_add' => 'M13.8 10.2a4 4 0 010 5.6l-3 3a4 4 0 01-5.6-5.6l1.5-1.5m3.5-1.5a4 4 0 010-5.6l3-3a4 4 0 015.6 5.6l-1.5 1.5',
        'menu' => 'M4 4v7a3 3 0 006 0V4M7 4v16m10-16c-2 0-3 2-3 5s1 4 3 4v7',
    ];
    $head = function (string $id, string $title, string $sub = '') use ($ico) {
        echo '<div class="flex items-start gap-3 mb-5"><span class="w-10 h-10 shrink-0 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center">'
            . '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="' . $ico[$id] . '"/></svg></span>'
            . '<div class="min-w-0 flex-1"><h2 id="' . e($id) . '" class="font-bold text-ink-900 scroll-mt-24">' . e($title) . '</h2>'
            . ($sub !== '' ? '<p class="text-xs text-ink-500 mt-0.5">' . e($sub) . '</p>' : '') . '</div>'
            . '<span class="gl-dot hidden w-2.5 h-2.5 mt-2 rounded-full bg-amber-500" title="' . e(t('فيه تعديلات مش محفوظة')) . '"></span></div>';
    };
    $saveBtn = function (string $label) {
        echo '<div class="flex justify-end mt-5 pt-4 border-t border-ink-100"><button class="btn-primary">' . e($label) . '</button></div>';
    };
    $vom = $verif['voiceOfMerchantState'] ?? [];
    $verified = !empty($vom['hasVoiceOfMerchant']) || (!$verif && !empty($location['isVerified']));
    $pending = !empty($vom['verify']['hasPendingVerification']);
    $title = $d['title'] ?? ($loc['label'] ?? '');
    $initial = mb_substr(trim($title) ?: '?', 0, 1);
    $splitLabel = function (string $l) { $p = explode(' · ', $l, 2); return [$p[0], $p[1] ?? '']; };
    $otherLocs = array_values(array_filter($options, function ($o) use ($loc) { return $o['key'] !== $loc['key'] && $o['location'] !== ''; }));
    $also = function (string $what) use ($otherLocs, $splitLabel) {
        if (!$otherLocs) return;
        ?>
        <div class="gl-also mt-5 rounded-xl border border-ink-200">
          <button type="button" class="gl-also-t w-full flex items-center gap-2 px-4 py-3 text-sm text-start">
            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01"/></svg>
            <span class="font-medium text-ink-800"><?= e(t('طبّق نفس المواعيد على فروع تانية')) ?></span>
            <span class="gl-also-n rounded-full bg-brand-600 text-white text-xs px-2 py-0.5" style="display:none"></span>
            <svg class="gl-also-c w-4 h-4 text-ink-400 ms-auto transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div class="gl-also-b px-4 pb-4" style="display:none">
            <div class="flex flex-wrap items-center gap-2 mb-3">
              <input type="search" class="gl-also-q input !mt-0 !py-1.5 flex-1 min-w-[10rem] text-sm" placeholder="<?= e(t('دوّر على فرع بالاسم أو العنوان...')) ?>">
              <button type="button" class="gl-also-all text-xs link"><?= e(t('اختار الكل')) ?></button>
              <button type="button" class="gl-also-none text-xs link"><?= e(t('شيل الكل')) ?></button>
            </div>
            <div class="flex flex-wrap gap-2 max-h-56 overflow-y-auto p-0.5">
              <?php foreach ($otherLocs as $o): [$n, $a] = $splitLabel($o['label']); ?>
                <label class="gl-also-i cursor-pointer select-none" data-q="<?= e(mb_strtolower($o['label'])) ?>"><input type="checkbox" name="also[]" value="<?= e($o['key']) ?>" class="peer sr-only">
                  <span class="inline-flex flex-col rounded-xl border border-ink-200 bg-white px-3 py-1.5 text-sm hover:border-brand-400 peer-checked:bg-brand-600 peer-checked:border-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 transition"><span class="font-medium"><?= e($n) ?></span><?php if ($a !== ''): ?><span class="text-[11px] opacity-75"><?= e($a) ?></span><?php endif; ?></span></label>
              <?php endforeach; ?>
            </div>
            <p class="text-xs text-amber-700 mt-3"><?= e($what === 'special' ? t('المواعيد الخاصة الحالية للفروع المختارة هتتمسح وتتبدل باللي هنا بالظبط.') : t('مواعيد العمل الحالية للفروع المختارة هتتمسح وتتبدل باللي هنا بالظبط.')) ?></p>
          </div>
        </div>
        <?php
    };
    ?>
    <?php /* ================= الهيدر + مبدّل الفروع ================= */ ?>
    <div class="relative rounded-2xl border border-ink-200 bg-white overflow-visible mb-6">
      <div class="h-20 rounded-t-2xl bg-gradient-to-l from-brand-600 via-brand-500 to-cyan-500"></div>
      <div class="px-5 pb-5 -mt-8 flex flex-wrap items-end gap-4">
        <div class="w-16 h-16 rounded-2xl bg-white shadow-md ring-4 ring-white flex items-center justify-center text-2xl font-bold text-brand-600"><?= e($initial) ?></div>
        <div class="min-w-0 flex-1 pt-9">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl font-bold text-ink-900 truncate"><?= e($title) ?></h2>
            <?php if ($d): ?>
              <?php if ($verified): ?><span class="inline-flex items-center gap-1 rounded-full bg-lime-50 text-lime-700 text-xs px-2.5 py-1 whitespace-nowrap"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg><?= e(t('متحقق ومنشور')) ?></span>
              <?php elseif ($pending): ?><a href="#verif" class="rounded-full bg-amber-50 text-amber-700 text-xs px-2.5 py-1"><?= e(t('في انتظار التحقق')) ?></a>
              <?php else: ?><a href="#verif" class="rounded-full bg-red-50 text-red-700 text-xs px-2.5 py-1"><?= e(t('مش متحقق')) ?></a><?php endif; ?>
              <span id="gl-open" class="rounded-full text-xs px-2.5 py-1 hidden"></span>
            <?php endif; ?>
          </div>
          <div class="text-sm text-ink-500 mt-0.5 truncate"><?= e(implode(' · ', array_filter([$cats['primaryCategory']['displayName'] ?? '', $splitLabel($loc['label'] ?? '')[1]]))) ?></div>
        </div>
        <?php gmb_switcher($options, $loc, 'gmb_location'); ?>
      </div>
      <?php if ($d): ?>
      <div class="px-5 pb-4 flex flex-wrap gap-2 text-sm">
        <?php if (!empty($location['mapsUri'])): ?><a class="btn-ghost !py-1.5" href="<?= e($location['mapsUri']) ?>" target="_blank" rel="noopener"><?= e(t('على الخريطة ↗')) ?></a><?php endif; ?>
        <?php if (!empty($location['reviewUrl'])): ?><button type="button" id="gl-copy-review" class="btn-ghost !py-1.5"><?= e(t('انسخ رابط التقييم')) ?></button><?php endif; ?>
        <a class="btn-ghost !py-1.5" href="<?= e(url('gmb_media', ['loc' => $loc['key']])) ?>"><?= e(t('الصور')) ?></a>
        <a class="btn-ghost !py-1.5" href="<?= e(url('gmb_reviews', ['loc' => $loc['key']])) ?>"><?= e(t('التقييمات')) ?></a>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!$d) return; ?>

    <div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
      <div id="gl-main" class="space-y-6 min-w-0">

      <?php if (!$verified): ?>
      <div id="verif" class="rounded-2xl border <?= $pending ? 'border-amber-200 bg-amber-50/60' : 'border-red-200 bg-red-50/60' ?> p-5">
        <div class="font-bold text-ink-900 mb-2"><?= e($pending ? t('في انتظار التحقق') : t('الفرع مش متحقق')) ?></div>
        <?php $err('verif'); ?>
        <?php if ($pending): ?>
          <p class="text-sm text-ink-700"><?= e(t('فيه طلب تحقق مفتوح. لو اخترت التحقق بالبريد، استنى الكارت اللي فيه الكود (ممكن ياخد لحد 14 يوم)، ولو بالتليفون أو الإيميل هيوصلك كود. دخّل الكود من حساب Google Business Profile.')) ?></p>
        <?php else: ?>
          <ol class="text-sm text-ink-700 list-decimal ps-5 space-y-1">
            <li><?= e(t('افتح business.google.com بنفس حساب جوجل المربوط.')) ?></li>
            <li><?= e(t('اختار الفرع ودوس "Verify" (تحقق).')) ?></li>
            <li><?= e(t('اختار طريقة التحقق المتاحة (فيديو، تليفون، إيميل، أو بريد) وكمل الخطوات.')) ?></li>
            <li><?= e(t('بعد ما التحقق يكتمل، الحالة هنا هتتحدث لوحدها.')) ?></li>
          </ol>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php /* ---------- البيانات الأساسية ---------- */ ?>
      <form method="post" class="card p-6" data-gl="basic">
        <?php $head('basic', t('البيانات الأساسية'), t('اللي الناس بتشوفه أول ما تلاقيك على جوجل')); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="basic">
        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block"><span class="label"><?= e(t('التليفون')) ?></span>
            <input name="phone" data-phone="any" data-phone-req class="input" dir="ltr" value="<?= e($d['phoneNumbers']['primaryPhone'] ?? '') ?>"></label>
          <label class="block"><span class="label"><?= e(t('الموقع الإلكتروني')) ?></span>
            <input name="website" type="url" class="input" dir="ltr" value="<?= e($d['websiteUri'] ?? '') ?>" placeholder="https://"></label>
        </div>
        <label class="block mt-4"><span class="label"><?= e(t('الوصف')) ?></span>
          <textarea data-ai="wa_about,improve" name="description" rows="5" maxlength="750" class="input"><?= e($d['profile']['description'] ?? '') ?></textarea></label>
        <div class="mt-4 rounded-xl bg-ink-50 p-3 text-sm flex flex-wrap items-center gap-x-6 gap-y-2">
          <div><span class="text-xs text-ink-500 block"><?= e(t('اسم النشاط')) ?></span><span class="font-medium text-ink-900"><?= e($d['title'] ?? '') ?></span></div>
          <div class="flex flex-wrap items-center gap-1">
            <span class="text-xs text-ink-500 w-full"><?= e(t('التصنيفات')) ?></span>
            <?php if (!empty($cats['primaryCategory'])): ?><span class="rounded-full bg-brand-600 text-white text-xs px-2.5 py-0.5"><?= e($cats['primaryCategory']['displayName'] ?? $cats['primaryCategory']['name'] ?? '') ?></span><?php endif; ?>
            <?php foreach ($cats['additionalCategories'] ?? [] as $c): ?><span class="rounded-full bg-white border border-ink-200 text-xs px-2.5 py-0.5"><?= e($c['displayName'] ?? $c['name'] ?? '') ?></span><?php endforeach; ?>
            <?php if (!$cats): ?><span class="text-ink-500">—</span><?php endif; ?>
          </div>
          <p class="text-[11px] text-ink-500 w-full"><?= e(t('الاسم والتصنيفات بيتعدلوا من Google Business Profile لأنهم محتاجين مراجعة جوجل.')) ?></p>
        </div>
        <?php $saveBtn(t('حفظ')); ?>
      </form>

      <?php /* ---------- مواعيد العمل ---------- */ ?>
      <form method="post" class="card p-6" data-gl="hours">
        <?php $head('hours', t('مواعيد العمل'), t('دوس على المفتاح عشان تفتح أو تقفل اليوم')); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="hours">
        <div id="gl-presets" class="flex flex-wrap items-center gap-1.5 mb-4"></div>
        <div class="divide-y divide-ink-100">
          <?php foreach (gmb_days() as $day => $label): $p = $byDay[$day] ?? null; ?>
            <div class="gl-day group flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5" data-day="<?= e($day) ?>">
              <label class="relative inline-flex items-center gap-3 w-32 cursor-pointer select-none">
                <input type="checkbox" name="open[<?= $day ?>]" value="1" class="peer sr-only" <?= $p ? 'checked' : '' ?>>
                <span class="relative w-10 h-6 shrink-0 rounded-full bg-ink-200 peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 transition-colors after:content-[''] after:absolute after:top-1 after:start-1 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:start-5"></span>
                <span class="text-sm font-medium text-ink-800"><?= e($label) ?></span>
              </label>
              <span class="gl-times flex items-center gap-1.5">
                <input type="time" name="from[<?= $day ?>]" class="input !mt-0 !w-[7.5rem] !py-1.5" value="<?= e($p ? gmb_time($p['openTime'] ?? '') : '09:00') ?>">
                <span class="text-ink-400">–</span>
                <input type="time" name="to[<?= $day ?>]" class="input !mt-0 !w-[7.5rem] !py-1.5" value="<?= e($p ? (gmb_time($p['closeTime'] ?? '') === '24:00' ? '00:00' : gmb_time($p['closeTime'] ?? '')) : '17:00') ?>">
              </span>
              <span class="gl-tag text-xs text-ink-500"></span>
              <button type="button" class="gl-copy ms-auto opacity-0 group-hover:opacity-100 focus:opacity-100 text-xs text-brand-700 hover:underline transition"><?= e(t('انسخ لكل الأيام')) ?></button>
              <div class="gl-bar w-full h-1.5 rounded-full bg-ink-100 relative overflow-hidden" dir="ltr"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php $also('hours'); ?>
        <div class="flex justify-end mt-5 pt-4 border-t border-ink-100 items-center gap-3"><span class="gl-week text-xs text-ink-500 me-auto"></span><button class="btn-primary"><?= e(t('حفظ المواعيد')) ?></button></div>
      </form>

      <?php /* ---------- المواعيد الخاصة ---------- */
      $sp = $d['specialHours']['specialHourPeriods'] ?? [];
      $sp[] = [];
      ?>
      <form method="post" class="card p-6" data-gl="special">
        <?php $head('special', t('مواعيد خاصة (أجازات ومناسبات)'), t('الأيام اللي مواعيدها مختلفة عن العادي — جوجل بيعرضها للناس بدل المواعيد العادية')); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="special">
        <div class="space-y-2" id="gl-sp">
          <?php foreach ($sp as $i => $p): ?>
            <div class="gl-sp flex flex-wrap items-center gap-2 rounded-xl border border-ink-200 p-2.5">
              <input type="date" name="sp_date[<?= $i ?>]" class="input !mt-0 !w-40 !py-1.5" value="<?= e(gmb_date_str($p['startDate'] ?? null)) ?>">
              <label class="cursor-pointer"><input type="checkbox" name="sp_closed[<?= $i ?>]" value="1" class="peer sr-only" <?= !empty($p['closed']) ? 'checked' : '' ?>><span class="inline-block rounded-full border border-ink-200 px-3 py-1 text-xs peer-checked:bg-red-600 peer-checked:border-red-600 peer-checked:text-white transition"><?= e(t('مقفول')) ?></span></label>
              <input type="time" name="sp_from[<?= $i ?>]" class="input !mt-0 !w-[7.5rem] !py-1.5" value="<?= e(isset($p['openTime']) ? gmb_time($p['openTime']) : '') ?>">
              <span class="text-ink-400">–</span>
              <input type="time" name="sp_to[<?= $i ?>]" class="input !mt-0 !w-[7.5rem] !py-1.5" value="<?= e(isset($p['closeTime']) ? gmb_time($p['closeTime']) : '') ?>">
              <button type="button" class="gl-sp-del ms-auto w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" title="<?= e(t('شيل')) ?>">✕</button>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="gl-hol" class="mt-3 flex flex-wrap gap-1.5"></div>
        <?php $also('special'); ?>
        <?php $saveBtn(t('حفظ المواعيد الخاصة')); ?>
      </form>

      <?php /* ---------- الخصائص ---------- */ ?>
      <form method="post" class="card p-6" data-gl="attributes">
        <?php $head('attributes', t('خصائص النشاط'), t('دوس على الخاصية عشان تفعّلها — بتظهر للناس كعلامات على ملفك')); ?>
        <?php $err('attrs'); $err('meta'); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="attributes">
        <?php
        $current = [];
        foreach ($attrs as $a) $current[preg_replace('#^attributes/#', '', (string)($a['name'] ?? ''))] = $a;
        $bools = [];
        foreach ($meta as $k => $m) if (($m['valueType'] ?? '') === 'BOOL') $bools[$k] = $m;
        foreach ($current as $k => $a) if (($a['valueType'] ?? '') === 'BOOL' && !isset($bools[$k])) $bools[$k] = ['displayName' => $k];
        $others = array_filter($current, function ($a) { return ($a['valueType'] ?? '') !== 'BOOL'; });
        ?>
        <?php if (!$bools && !$others): ?><p class="text-sm text-ink-500"><?= e(t('مفيش خصائص متاحة للتصنيف ده.')) ?></p><?php endif; ?>
        <div id="gl-attrs" class="flex flex-wrap gap-2 max-h-80 overflow-y-auto p-0.5">
          <?php foreach ($bools as $k => $m): $on = !empty($current[$k]['values'][0]); ?>
            <label class="gl-attr cursor-pointer select-none"><input type="hidden" name="attr_names[]" value="<?= e($k) ?>"><input type="checkbox" name="attr[<?= e($k) ?>]" value="1" class="peer sr-only" <?= $on ? 'checked' : '' ?>>
              <span class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3.5 py-1.5 text-sm text-ink-700 hover:border-brand-400 peer-checked:bg-brand-600 peer-checked:border-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 transition"><?= e($m['displayName'] ?? $k) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?php foreach ($others as $k => $a): ?>
          <p class="text-xs text-ink-500 mt-2"><?= e($meta[$k]['displayName'] ?? $k) ?>: <?= e(implode(', ', array_map(function ($v) { return is_scalar($v) ? (string)$v : ''; }, array_merge($a['values'] ?? [], $a['repeatedEnumValue']['setValues'] ?? [], array_column($a['uriValues'] ?? [], 'uri'))))) ?></p>
        <?php endforeach; ?>
        <?php if ($bools) $saveBtn(t('حفظ الخصائص')); ?>
      </form>

      <?php /* ---------- الخدمات ---------- */
      $lines = [];
      $structured = [];
      $currency = cx('cur');
      foreach ($services as $s) {
          if (!empty($s['structuredServiceItem']['serviceTypeId'])) { $structured[] = $s['structuredServiceItem']['serviceTypeId']; continue; }
          $name = $s['freeFormServiceItem']['label']['displayName'] ?? '';
          if ($name === '') continue;
          if (!empty($s['price']['currencyCode'])) $currency = $s['price']['currencyCode'];
          $pr = gmb_money($s['price'] ?? null);
          $lines[] = $name . ($pr !== '' ? ' | ' . $pr : '');
      }
      ?>
      <form method="post" class="card p-6" data-gl="services">
        <?php $head('services', t('الخدمات'), t('الخدمات وأسعارها بتظهر في تبويب الخدمات على ملفك')); ?>
        <?php $err('services'); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="services">
        <?php foreach ($structured as $sid): ?><input type="hidden" name="keep_structured[]" value="<?= e($sid) ?>"><?php endforeach; ?>
        <?php if ($structured): ?><p class="text-xs text-ink-500 mb-3"><?= e(t('%s خدمة من قايمة جوجل الجاهزة هتفضل زي ما هي.', count($structured))) ?></p><?php endif; ?>
        <textarea name="services" rows="7" class="input font-mono text-sm" data-rows="<?= e(t('اسم الخدمة')) ?>|<?= e(t('السعر')) ?>" placeholder="<?= e(t('خدمة واحدة في كل سطر: اسم الخدمة | السعر')) ?>"><?= e(implode("\n", $lines)) ?></textarea>
        <div class="flex justify-end items-center gap-3 mt-5 pt-4 border-t border-ink-100">
          <label class="text-sm me-auto flex items-center gap-2"><?= e(t('العملة')) ?> <input name="currency" class="input !mt-0 !w-20 !py-1.5" dir="ltr" maxlength="3" value="<?= e($currency) ?>"></label>
          <button class="btn-primary"><?= e(t('حفظ الخدمات')) ?></button>
        </div>
      </form>

      <?php /* ---------- روابط الحجز والطلب ---------- */ $types = gmb_place_action_types(); ?>
      <div class="card p-6" data-gl="actions" data-count="<?= count($actions) ?>">
        <?php $head('action_add', t('روابط الحجز والطلب'), t('زراير "احجز" و"اطلب" اللي بتظهر على ملفك وبتودّي لموقعك')); ?>
        <?php $err('actions'); ?>
        <div class="space-y-2 mb-4">
          <?php if (!$actions): ?><div class="rounded-xl border border-dashed border-ink-200 p-5 text-center text-sm text-ink-500"><?= e(t('مفيش روابط لسه. ضيف رابط حجز أو طلب عشان العميل يتحول لعميل فعلي من جوجل مباشرة.')) ?></div><?php endif; ?>
          <?php foreach ($actions as $a): ?>
            <div class="flex flex-wrap items-center gap-2 rounded-xl border border-ink-200 p-2.5">
              <span class="rounded-full bg-brand-50 text-brand-700 text-xs px-2.5 py-1"><?= e($types[$a['placeActionType'] ?? ''] ?? ($a['placeActionType'] ?? '')) ?></span>
              <form method="post" class="flex flex-1 gap-2 min-w-0">
                <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="action_edit"><input type="hidden" name="name" value="<?= e($a['name'] ?? '') ?>">
                <input name="uri" type="url" class="input !mt-0 !py-1.5 flex-1 min-w-0" dir="ltr" value="<?= e($a['uri'] ?? '') ?>" required>
                <button class="btn-ghost !py-1.5"><?= e(t('حفظ')) ?></button>
              </form>
              <?php if (can('gmb.manage')): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('حذف الرابط ده؟') ?>')">
                <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="action_delete"><input type="hidden" name="name" value="<?= e($a['name'] ?? '') ?>">
                <button class="w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" title="<?= e(t('حذف')) ?>">✕</button>
              </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="flex flex-wrap gap-2 items-center rounded-xl bg-ink-50 p-3">
          <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="action_add">
          <select name="type" class="input !mt-0 !w-auto !py-1.5" required>
            <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
          </select>
          <input name="uri" type="url" class="input !mt-0 !py-1.5 flex-1 min-w-[12rem]" dir="ltr" placeholder="https://" required>
          <button class="btn-primary"><?= e(t('إضافة')) ?></button>
        </form>
      </div>

      <?php /* ---------- المنيو ---------- */
      if ($isFood):
          $mlines = [];
          $menuName = '';
          $mcur = cx('cur');
          foreach (array_slice($menus, 0, 1) as $m) {
              $menuName = $m['labels'][0]['displayName'] ?? '';
              foreach ($m['sections'] ?? [] as $s) {
                  $sn = $s['labels'][0]['displayName'] ?? '';
                  foreach ($s['items'] ?? [] as $it) {
                      $pr = $it['attributes']['price'] ?? null;
                      if (!empty($pr['currencyCode'])) $mcur = $pr['currencyCode'];
                      $ps = gmb_money($pr);
                      $mlines[] = $sn . ' | ' . ($it['labels'][0]['displayName'] ?? '') . ($ps !== '' ? ' | ' . $ps : '');
                  }
              }
          }
      ?>
      <form method="post" class="card p-6" data-gl="menu">
        <?php $head('menu', t('منيو الأكل'), t('الأصناف بتظهر في تبويب المنيو على جوجل')); ?>
        <?php $err('menus'); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="menu">
        <?php if (count($menus) > 1): ?><p class="text-xs text-amber-700 mb-3"><?= e(t('فيه %s منيو، المعروض هنا أول واحد بس، والحفظ هيستبدلهم بالمنيو ده.', count($menus))) ?></p><?php endif; ?>
        <label class="block mb-4"><span class="label"><?= e(t('اسم المنيو')) ?></span><input name="menu_name" class="input sm:!w-64" value="<?= e($menuName) ?>"></label>
        <textarea name="menu" rows="8" class="input font-mono text-sm" data-rows="<?= e(t('القسم')) ?>|<?= e(t('الصنف')) ?>|<?= e(t('السعر')) ?>" placeholder="<?= e(t('القسم | الصنف | السعر')) ?>"><?= e(implode("\n", $mlines)) ?></textarea>
        <div class="flex justify-end items-center gap-3 mt-5 pt-4 border-t border-ink-100">
          <label class="text-sm me-auto flex items-center gap-2"><?= e(t('العملة')) ?> <input name="currency" class="input !mt-0 !w-20 !py-1.5" dir="ltr" maxlength="3" value="<?= e($mcur) ?>"></label>
          <button class="btn-primary"><?= e(t('حفظ المنيو')) ?></button>
        </div>
      </form>
      <?php endif; ?>
      </div>

      <?php /* ================= العمود الجانبي ================= */ ?>
      <aside class="space-y-4 lg:sticky lg:top-4 order-first lg:order-none">
        <div id="gl-score" class="card p-5"></div>
        <div id="gl-preview" class="card p-5 hidden lg:block"></div>
      </aside>
    </div>

    <div id="gl-dirty" class="fixed bottom-5 inset-x-0 z-50 flex justify-center pointer-events-none" style="display:none">
      <div class="pointer-events-auto flex items-center gap-3 rounded-full bg-ink-900 text-white shadow-2xl ps-5 pe-2 py-2 text-sm">
        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span><span id="gl-dirty-t"></span>
        <button type="button" id="gl-dirty-go" class="rounded-full bg-white/15 hover:bg-white/25 px-3 py-1"><?= e(t('روح للتعديل')) ?></button>
      </div>
    </div>

    <script>window.GLX = <?= json_encode(['title' => $d['title'] ?? '', 'cat' => $cats['primaryCategory']['displayName'] ?? '', 'review' => $location['reviewUrl'] ?? '', 'verified' => $verified, 'food' => (bool)$isFood, 'cur' => (string)$currency, 's' => [
      'score' => t('اكتمال الملف'), 'scoreHint' => t('الملفات الكاملة بتظهر أكتر في بحث جوجل والخرايط.'), 'left' => t('فاضل %s خطوات'), 'allDone' => t('الملف كامل 👌'),
      'c_phone' => t('رقم تليفون'), 'c_site' => t('موقع إلكتروني'), 'c_desc' => t('وصف 250 حرف على الأقل'), 'c_hours' => t('مواعيد العمل'), 'c_special' => t('مواعيد الأجازات الجاية'), 'c_attrs' => t('3 خصائص على الأقل'), 'c_services' => t('الخدمات'), 'c_actions' => t('رابط حجز أو طلب'), 'c_menu' => t('المنيو'), 'c_verif' => t('التحقق'),
      'openNow' => t('مفتوح دلوقتي'), 'closedNow' => t('مقفول دلوقتي'), 'closesAt' => t('بيقفل %s'), 'opensAt' => t('بيفتح %s'), 'closed' => t('مقفول'), 'h24' => t('24 ساعة'), 'overnight' => t('لحد تاني يوم'),
      'p_95' => t('9ص–5م أيام الشغل'), 'p_all' => t('كل يوم 12ظ–12ص'), 'p_247' => t('24/7'), 'p_fri' => t('الجمعة أجازة'), 'weekHours' => t('%s ساعة في الأسبوع'),
      'addRow' => t('+ يوم كمان'), 'past' => t('التاريخ ده عدّى'), 'dup' => t('التاريخ متكرر'), 'chars' => t('%s / 750 حرف'), 'descShort' => t('الوصف قصير — جوجل بيفضّل 250 حرف أو أكتر'), 'descPhone' => t('جوجل بيرفض الوصف اللي فيه أرقام تليفون أو روابط'),
      'attrSearch' => t('دوّر في الخصائص...'), 'attrOn' => t('%s من %s مفعّلة'), 'onlyOn' => t('المفعّل بس'),
      'preview' => t('معاينة الظهور على جوجل'), 'previewNote' => t('تقريبية — شكل جوجل الحقيقي ممكن يختلف.'), 'call' => t('اتصال'), 'site' => t('الموقع'), 'dir' => t('الاتجاهات'),
      'badPrice' => t('السعر لازم يكون رقم'), 'addItem' => t('+ ضيف سطر'), 'del' => t('شيل'), 'items' => t('%s عنصر'),
      'dirtyN' => t('تعديلات مش محفوظة في %s'), 'leave' => t('فيه تعديلات مش محفوظة. تخرج؟'), 'copied' => t('اتنسخ'), 'saveN' => t('حفظ على %s فروع'), 'confirmN' => t('هتستبدل المواعيد في %s فروع تانية غير الفرع ده:'), 'confirmEnd' => t('متأكد؟'),
      'hol' => ws_country() !== 'EG' ? [] : [['12-31', t('ليلة رأس السنة')], ['01-01', t('رأس السنة')], ['01-07', t('عيد الميلاد (شرقي)')], ['04-25', t('تحرير سينا')], ['05-01', t('عيد العمال')], ['07-23', t('ثورة يوليو')], ['10-06', t('6 أكتوبر')]],
    ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-gmb_location.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
