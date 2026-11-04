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
            gmb_update_location($z, $acc, $lid, ['updateMask' => 'regularHours', 'regularHours' => ['periods' => $periods]]);
            log_activity('gmb.hours_update', $lid);
            flash('success', t('تم حفظ مواعيد العمل.'));
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
            gmb_update_location($z, $acc, $lid, ['updateMask' => 'specialHours', 'specialHours' => ['specialHourPeriods' => $list]]);
            log_activity('gmb.special_hours_update', $lid);
            flash('success', t('تم حفظ المواعيد الخاصة.'));
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
            $cur = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['currency'] ?? 'EGP'))) ?: 'EGP';
            $items = gmb_parse_services((string)($_POST['services'] ?? ''), $cur);
            // نحافظ على الخدمات المنظمة (structured) اللي اتعملت من جوجل
            foreach ((array)($_POST['keep_structured'] ?? []) as $sid) {
                $items[] = ['structuredServiceItem' => ['serviceTypeId' => (string)$sid]];
            }
            gmb_update_services($z, $acc, $lid, $items);
            log_activity('gmb.services_update', $lid);
            flash('success', t('تم حفظ الخدمات.'));
        } elseif ($action === 'menu') {
            $cur = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['currency'] ?? 'EGP'))) ?: 'EGP';
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
if ($z && $loc) {
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
    if (!$z) { no_api_box(); return; }
    if (!$accounts && !$apiError) { gmb_no_account_box(); return; }
    if ($apiError) { error_box($apiError); echo '<div class="h-4"></div>'; }
    gmb_location_picker($options, $loc, 'gmb_location');
    if (!$d) return;

    $hidden = '<input type="hidden" name="loc" value="' . e($loc['key']) . '">';
    $location = $d['location'] ?? [];
    $cats = $d['categories'] ?? [];
    $catText = strtolower(json_encode($cats));
    $isFood = $menus || preg_match('/restaurant|cafe|food|bakery|pizza|coffee|meal|diner|bar/', $catText);
    $byDay = [];
    foreach ($d['regularHours']['periods'] ?? [] as $p) {
        $byDay[$p['openDay'] ?? ''] = $p;
    }
    $sec = function (string $id, string $title) {
        echo '<h2 id="' . e($id) . '" class="font-bold text-ink-900 mb-3 scroll-mt-20">' . e($title) . '</h2>';
    };
    $err = function (string $k) use ($errors) {
        if (!empty($errors[$k])) echo '<p class="text-xs text-red-600 mb-2">' . e($errors[$k]) . '</p>';
    };
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
      <h2 class="text-lg font-bold text-ink-900 me-2"><?= e($d['title'] ?? $loc['label']) ?></h2>
      <?php if (!empty($location['mapsUri'])): ?><a class="link" href="<?= e($location['mapsUri']) ?>" target="_blank" rel="noopener"><?= e(t('على الخريطة ↗')) ?></a><?php endif; ?>
      <?php if (!empty($location['reviewUrl'])): ?><a class="link" href="<?= e($location['reviewUrl']) ?>" target="_blank" rel="noopener"><?= e(t('رابط طلب تقييم ↗')) ?></a><?php endif; ?>
      <a class="link" href="<?= e(url('gmb_media', ['loc' => $loc['key']])) ?>"><?= e(t('الصور')) ?></a>
      <a class="link" href="<?= e(url('gmb_reviews', ['loc' => $loc['key']])) ?>"><?= e(t('التقييمات')) ?></a>
    </div>

    <?php /* ---------- التحقق ---------- */
    $vom = $verif['voiceOfMerchantState'] ?? [];
    $verified = !empty($vom['hasVoiceOfMerchant']) || (!$verif && !empty($location['isVerified']));
    $pending = !empty($vom['verify']['hasPendingVerification']);
    ?>
    <div id="verif" class="card p-5 mb-4 <?= $verified ? '' : 'border-amber-300' ?>">
      <div class="flex flex-wrap items-center gap-2 mb-1">
        <span class="font-bold text-ink-900"><?= e(t('حالة التحقق')) ?></span>
        <?php if ($verified): ?><span class="badge badge-green"><?= e(t('متحقق ومنشور')) ?></span>
        <?php elseif ($pending): ?><span class="badge bg-amber-100 text-amber-800"><?= e(t('في انتظار التحقق')) ?></span>
        <?php else: ?><span class="badge bg-red-100 text-red-700"><?= e(t('مش متحقق')) ?></span><?php endif; ?>
      </div>
      <?php $err('verif'); ?>
      <?php if ($verified): ?>
        <p class="text-sm text-ink-600"><?= e(t('الفرع متحقق، وأي تعديل بتعمله هنا بيظهر للناس على جوجل بعد مراجعة جوجل.')) ?></p>
      <?php elseif ($pending): ?>
        <p class="text-sm text-ink-600"><?= e(t('فيه طلب تحقق مفتوح. لو اخترت التحقق بالبريد، استنى الكارت اللي فيه الكود (ممكن ياخد لحد 14 يوم)، ولو بالتليفون أو الإيميل هيوصلك كود. دخّل الكود من حساب Google Business Profile.')) ?></p>
      <?php else: ?>
        <ol class="text-sm text-ink-600 list-decimal ps-5 space-y-1">
          <li><?= e(t('افتح business.google.com بنفس حساب جوجل المربوط.')) ?></li>
          <li><?= e(t('اختار الفرع ودوس "Verify" (تحقق).')) ?></li>
          <li><?= e(t('اختار طريقة التحقق المتاحة (فيديو، تليفون، إيميل، أو بريد) وكمل الخطوات.')) ?></li>
          <li><?= e(t('بعد ما التحقق يكتمل، الحالة هنا هتتحدث لوحدها.')) ?></li>
        </ol>
      <?php endif; ?>
      <?php if (!empty($verif['verifications'])): ?>
        <div class="mt-3 text-xs text-ink-500 space-y-0.5">
          <?php foreach (array_slice($verif['verifications'], 0, 5) as $v): ?>
            <div><?= e(fmt_date($v['createTime'] ?? null, 'd/m/Y')) ?> · <?= e((string)($v['method'] ?? '')) ?> · <?= e((string)($v['state'] ?? '')) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
      <?php /* ---------- البيانات الأساسية ---------- */ ?>
      <form method="post" class="card p-5 space-y-3">
        <?php $sec('basic', t('البيانات الأساسية')); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="basic">
        <label class="block"><span class="label"><?= e(t('اسم النشاط')) ?></span>
          <input class="input bg-ink-50" value="<?= e($d['title'] ?? '') ?>" readonly>
          <span class="text-[11px] text-ink-500"><?= e(t('تغيير الاسم بيحتاج مراجعة جوجل، فبيتعمل من Google Business Profile نفسه.')) ?></span>
        </label>
        <label class="block"><span class="label"><?= e(t('التليفون')) ?></span>
          <input name="phone" class="input" dir="ltr" value="<?= e($d['phoneNumbers']['primaryPhone'] ?? '') ?>"></label>
        <label class="block"><span class="label"><?= e(t('الموقع الإلكتروني')) ?></span>
          <input name="website" type="url" class="input" dir="ltr" value="<?= e($d['websiteUri'] ?? '') ?>" placeholder="https://"></label>
        <label class="block"><span class="label"><?= e(t('الوصف')) ?></span>
          <textarea data-ai="wa_about,improve" name="description" rows="4" maxlength="750" class="input"><?= e($d['profile']['description'] ?? '') ?></textarea></label>
        <div>
          <span class="label"><?= e(t('التصنيفات')) ?></span>
          <div class="flex flex-wrap gap-1">
            <?php if (!empty($cats['primaryCategory'])): ?><span class="badge badge-green"><?= e($cats['primaryCategory']['displayName'] ?? $cats['primaryCategory']['name'] ?? '') ?> · <?= e(t('أساسي')) ?></span><?php endif; ?>
            <?php foreach ($cats['additionalCategories'] ?? [] as $c): ?><span class="badge"><?= e($c['displayName'] ?? $c['name'] ?? '') ?></span><?php endforeach; ?>
            <?php if (!$cats): ?><span class="text-sm text-ink-500">—</span><?php endif; ?>
          </div>
          <p class="text-[11px] text-ink-500 mt-1"><?= e(t('التصنيفات بتتعدل من Google Business Profile.')) ?></p>
        </div>
        <button class="btn-primary"><?= e(t('حفظ')) ?></button>
      </form>

      <?php /* ---------- مواعيد العمل ---------- */ ?>
      <form method="post" class="card p-5">
        <?php $sec('hours', t('مواعيد العمل')); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="hours">
        <div class="space-y-2">
          <?php foreach (gmb_days() as $day => $label): $p = $byDay[$day] ?? null; ?>
            <div class="flex flex-wrap items-center gap-2 text-sm">
              <label class="w-28 flex items-center gap-2"><input type="checkbox" name="open[<?= $day ?>]" value="1" <?= $p ? 'checked' : '' ?>> <?= e($label) ?></label>
              <input type="time" name="from[<?= $day ?>]" class="input !mt-0 !w-auto" value="<?= e($p ? gmb_time($p['openTime'] ?? '') : '09:00') ?>">
              <span class="text-ink-400">–</span>
              <input type="time" name="to[<?= $day ?>]" class="input !mt-0 !w-auto" value="<?= e($p ? (gmb_time($p['closeTime'] ?? '') === '24:00' ? '00:00' : gmb_time($p['closeTime'] ?? '')) : '17:00') ?>">
            </div>
          <?php endforeach; ?>
        </div>
        <p class="text-[11px] text-ink-500 mt-2"><?= e(t('اليوم اللي مش متعلم عليه يبقى مقفول. فترة واحدة لكل يوم.')) ?></p>
        <button class="btn-primary mt-3"><?= e(t('حفظ المواعيد')) ?></button>
      </form>

      <?php /* ---------- المواعيد الخاصة ---------- */
      $sp = $d['specialHours']['specialHourPeriods'] ?? [];
      $sp[] = [];
      $sp[] = [];
      ?>
      <form method="post" class="card p-5">
        <?php $sec('special', t('مواعيد خاصة (أجازات ومناسبات)')); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="special">
        <div class="space-y-2">
          <?php foreach ($sp as $i => $p): ?>
            <div class="flex flex-wrap items-center gap-2 text-sm">
              <input type="date" name="sp_date[<?= $i ?>]" class="input !mt-0 !w-36" value="<?= e(gmb_date_str($p['startDate'] ?? null)) ?>">
              <label class="flex items-center gap-1"><input type="checkbox" name="sp_closed[<?= $i ?>]" value="1" <?= !empty($p['closed']) ? 'checked' : '' ?>> <?= e(t('مقفول')) ?></label>
              <input type="time" name="sp_from[<?= $i ?>]" class="input !mt-0 !w-28" value="<?= e(isset($p['openTime']) ? gmb_time($p['openTime']) : '') ?>">
              <span class="text-ink-400">–</span>
              <input type="time" name="sp_to[<?= $i ?>]" class="input !mt-0 !w-28" value="<?= e(isset($p['closeTime']) ? gmb_time($p['closeTime']) : '') ?>">
            </div>
          <?php endforeach; ?>
        </div>
        <p class="text-[11px] text-ink-500 mt-2"><?= e(t('امسح التاريخ عشان تشيل اليوم. فيه سطرين فاضيين لإضافة أيام جديدة.')) ?></p>
        <button class="btn-primary mt-3"><?= e(t('حفظ المواعيد الخاصة')) ?></button>
      </form>

      <?php /* ---------- الخصائص ---------- */ ?>
      <form method="post" class="card p-5">
        <?php $sec('attributes', t('خصائص النشاط')); ?>
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
        <div class="grid sm:grid-cols-2 gap-1 max-h-72 overflow-y-auto">
          <?php foreach ($bools as $k => $m): $on = !empty($current[$k]['values'][0]); ?>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="attr_names[]" value="<?= e($k) ?>"><input type="checkbox" name="attr[<?= e($k) ?>]" value="1" <?= $on ? 'checked' : '' ?>> <?= e($m['displayName'] ?? $k) ?></label>
          <?php endforeach; ?>
        </div>
        <?php foreach ($others as $k => $a): ?>
          <p class="text-xs text-ink-500 mt-1"><?= e($meta[$k]['displayName'] ?? $k) ?>: <?= e(implode(', ', array_map(function ($v) { return is_scalar($v) ? (string)$v : ''; }, array_merge($a['values'] ?? [], $a['repeatedEnumValue']['setValues'] ?? [], array_column($a['uriValues'] ?? [], 'uri'))))) ?></p>
        <?php endforeach; ?>
        <?php if ($bools): ?><button class="btn-primary mt-3"><?= e(t('حفظ الخصائص')) ?></button><?php endif; ?>
      </form>

      <?php /* ---------- الخدمات ---------- */
      $lines = [];
      $structured = [];
      $currency = 'EGP';
      foreach ($services as $s) {
          if (!empty($s['structuredServiceItem']['serviceTypeId'])) { $structured[] = $s['structuredServiceItem']['serviceTypeId']; continue; }
          $name = $s['freeFormServiceItem']['label']['displayName'] ?? '';
          if ($name === '') continue;
          if (!empty($s['price']['currencyCode'])) $currency = $s['price']['currencyCode'];
          $pr = gmb_money($s['price'] ?? null);
          $lines[] = $name . ($pr !== '' ? ' | ' . $pr : '');
      }
      ?>
      <form method="post" class="card p-5">
        <?php $sec('services', t('الخدمات')); ?>
        <?php $err('services'); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="services">
        <?php foreach ($structured as $sid): ?><input type="hidden" name="keep_structured[]" value="<?= e($sid) ?>"><?php endforeach; ?>
        <?php if ($structured): ?><p class="text-xs text-ink-500 mb-2"><?= e(t('%s خدمة من قايمة جوجل الجاهزة هتفضل زي ما هي.', count($structured))) ?></p><?php endif; ?>
        <textarea name="services" rows="7" class="input font-mono text-sm" placeholder="<?= e(t('خدمة واحدة في كل سطر: اسم الخدمة | السعر')) ?>"><?= e(implode("\n", $lines)) ?></textarea>
        <div class="flex items-center gap-2 mt-2">
          <label class="text-sm"><?= e(t('العملة')) ?> <input name="currency" class="input !mt-0 !w-20 inline-block" dir="ltr" maxlength="3" value="<?= e($currency) ?>"></label>
          <button class="btn-primary ms-auto"><?= e(t('حفظ الخدمات')) ?></button>
        </div>
        <p class="text-[11px] text-ink-500 mt-2"><?= e(t('خدمة واحدة في كل سطر: اسم الخدمة | السعر (السعر اختياري).')) ?></p>
      </form>

      <?php /* ---------- روابط الحجز والطلب ---------- */ ?>
      <div class="card p-5">
        <?php $sec('action_add', t('روابط الحجز والطلب')); ?>
        <?php $err('actions'); ?>
        <div class="space-y-2 mb-4">
          <?php if (!$actions): ?><p class="text-sm text-ink-500"><?= e(t('مفيش روابط لسه.')) ?></p><?php endif; ?>
          <?php foreach ($actions as $a): ?>
            <div class="flex flex-wrap items-center gap-2 text-sm">
              <span class="badge"><?= e(gmb_place_action_types()[$a['placeActionType'] ?? ''] ?? ($a['placeActionType'] ?? '')) ?></span>
              <form method="post" class="flex flex-1 gap-2 min-w-0">
                <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="action_edit"><input type="hidden" name="name" value="<?= e($a['name'] ?? '') ?>">
                <input name="uri" type="url" class="input !mt-0 flex-1 min-w-0" dir="ltr" value="<?= e($a['uri'] ?? '') ?>" required>
                <button class="btn-ghost"><?= e(t('حفظ')) ?></button>
              </form>
              <?php if (can('gmb.manage')): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('حذف الرابط ده؟') ?>')">
                <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="action_delete"><input type="hidden" name="name" value="<?= e($a['name'] ?? '') ?>">
                <button class="text-red-600 text-xs"><?= e(t('حذف')) ?></button>
              </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="flex flex-wrap gap-2 items-end border-t border-ink-100 pt-3">
          <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="action_add">
          <select name="type" class="input !mt-0 !w-auto" required>
            <?php foreach (gmb_place_action_types() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
          </select>
          <input name="uri" type="url" class="input !mt-0 flex-1 min-w-[12rem]" dir="ltr" placeholder="https://" required>
          <button class="btn-primary"><?= e(t('إضافة')) ?></button>
        </form>
      </div>

      <?php /* ---------- المنيو ---------- */
      if ($isFood):
          $mlines = [];
          $menuName = '';
          $mcur = 'EGP';
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
      <form method="post" class="card p-5 lg:col-span-2">
        <?php $sec('menu', t('منيو الأكل')); ?>
        <?php $err('menus'); ?>
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="menu">
        <?php if (count($menus) > 1): ?><p class="text-xs text-amber-700 mb-2"><?= e(t('فيه %s منيو، المعروض هنا أول واحد بس، والحفظ هيستبدلهم بالمنيو ده.', count($menus))) ?></p><?php endif; ?>
        <div class="flex flex-wrap gap-2 mb-2">
          <label class="text-sm"><?= e(t('اسم المنيو')) ?> <input name="menu_name" class="input !mt-0 !w-48 inline-block" value="<?= e($menuName) ?>"></label>
          <label class="text-sm"><?= e(t('العملة')) ?> <input name="currency" class="input !mt-0 !w-20 inline-block" dir="ltr" maxlength="3" value="<?= e($mcur) ?>"></label>
        </div>
        <textarea name="menu" rows="8" class="input font-mono text-sm" placeholder="<?= e(t('القسم | الصنف | السعر')) ?>"><?= e(implode("\n", $mlines)) ?></textarea>
        <p class="text-[11px] text-ink-500 mt-1"><?= e(t('صنف واحد في كل سطر: القسم | الصنف | السعر')) ?></p>
        <button class="btn-primary mt-3"><?= e(t('حفظ المنيو')) ?></button>
      </form>
      <?php endif; ?>
    </div>
    <?php
});
