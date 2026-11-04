<?php
/** الإعلانات: حسابات الإعلانات + قائمة الحملات بالأرقام، وإيقاف/تشغيل حملة */
$err = null;
$z = zernio_or_null($err);

$platform = (string)($_GET['pf'] ?? '');
if ($platform !== '' && !in_array($platform, ads_campaign_platforms(), true)) $platform = '';
$adAcc = (string)($_GET['acc'] ?? '');
$status = (string)($_GET['st'] ?? '');
if ($status !== '' && !in_array($status, ['active', 'paused', 'pending_review', 'rejected', 'completed', 'error'], true)) $status = '';
$range = (string)($_GET['r'] ?? '30');
if (!isset(ads_date_presets()[$range])) $range = '30';
$pg = max(1, (int)($_GET['pg'] ?? 1));
$back = url('ads', array_filter(['pf' => $platform, 'acc' => $adAcc, 'st' => $status, 'r' => $range, 'pg' => $pg > 1 ? $pg : null]));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_advisor') {
    header('Content-Type: application/json; charset=utf-8');
    $mode = ($_POST['mode'] ?? '') === 'realloc' ? 'realloc' : 'summary';
    $rows = array_slice((array)(json_decode((string)($_POST['data'] ?? '[]'), true) ?: []), 0, 40);
    $lines = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $lines[] = implode(' | ', array_map(function ($k) use ($r) { return $k . '=' . mb_substr(is_scalar($r[$k] ?? '') ? (string)($r[$k] ?? '') : '', 0, 80); }, ['id', 'name', 'status', 'currency', 'budget', 'budget_type', 'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'results', 'prev_spend', 'prev_results', 'can_edit_budget']));
    }
    $period = mb_substr((string)($_POST['period'] ?? ''), 0, 40);
    if ($mode === 'summary') {
        $sys = 'أنت مستشار إعلانات ممولة يكتب ملخصًا لصاحب شركة غير متخصص. استخدم الأرقام المعطاة فقط ولا تخترع أرقامًا. اكتب بالعربية الفصحى المبسطة، بهذا الترتيب وباختصار شديد: '
            . '1) سطر واحد: كم صرف وكم نتيجة جاء بها في الفترة، ومقارنة بالفترة السابقة إن وُجدت. 2) أفضل حملة ولماذا (بالأرقام). 3) أسوأ حملة ولماذا. 4) قرار واحد مقترح هذا الأسبوع. '
            . 'لا تجمع مبالغ بعملات مختلفة معًا. إذا كانت البيانات قليلة جدًا للحكم فقل ذلك.';
        $max = 600;
    } else {
        $sys = 'أنت خبير توزيع ميزانيات إعلانات. اقترح نقل ميزانية يومية من الحملات الأضعف إلى الأقوى، اعتمادًا على تكلفة النتيجة ونسبة الضغط. قواعد صارمة: '
            . 'انقل فقط بين حملتين بنفس العملة، وفقط الحملات التي can_edit_budget=1 والحالة active أو paused للمستقبِل. لا تنقل أكثر من 30% من ميزانية الحملة المصدر. '
            . 'لا تقترح نقلًا إذا كانت البيانات غير كافية (أقل من 1000 ظهور). المبالغ بنفس وحدة budget. '
            . 'أعد JSON فقط بدون شرح: {"moves":[{"from":"id","to":"id","amount":رقم,"why":"سطر بالعربية بالأرقام"}],"note":"سطر بالعربية"}. إذا لم يكن هناك نقل منطقي أعد moves فارغة واشرح السبب في note.';
        $max = 700;
    }
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys . ai_country_note()], ['role' => 'user', 'content' => "الفترة: $period\n" . implode("\n", $lines)]], $max, 'ads_advisor');
        if ($mode === 'summary') { echo json_encode(['ok' => true, 'text' => $r['text']], JSON_UNESCAPED_UNICODE); exit; }
        $txt = (string)$r['text']; if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $j = json_decode($txt, true);
        if (!is_array($j)) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
        echo json_encode(['ok' => true, 'moves' => array_values(array_filter((array)($j['moves'] ?? []), 'is_array')), 'note' => (string)($j['note'] ?? '')], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_analyze') {
    header('Content-Type: application/json; charset=utf-8');
    $d = json_decode((string)($_POST['data'] ?? '{}'), true) ?: [];
    $lines = [];
    foreach (['name', 'platform', 'status', 'currency', 'budget', 'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'results', 'prev_spend', 'prev_clicks', 'prev_results', 'account_avg_ctr', 'account_avg_cpc', 'period'] as $k) {
        if (isset($d[$k]) && $d[$k] !== '') $lines[] = $k . ': ' . mb_substr(is_scalar($d[$k]) ? (string)$d[$k] : json_encode($d[$k], JSON_UNESCAPED_UNICODE), 0, 200);
    }
    try {
        $r = ai_chat([
            ['role' => 'system', 'content' => 'أنت خبير إعلانات ممولة (Meta وLinkedIn) تعمل لصالح صاحب شركة. حلّل أداء الحملة بالأرقام المعطاة فقط، ولا تخترع أرقامًا. اكتب بالعربية الفصحى المبسطة، باختصار، بهذا الشكل: '
                . '1) الحكم في سطر واحد (جيدة / متوسطة / ضعيفة) مع السبب الرئيسي. 2) أهم 3 ملاحظات مبنية على الأرقام ومقارنتها بمتوسط الحساب والفترة السابقة. 3) أهم 3 خطوات عملية مرتبة بالأولوية. '
                . 'لو البيانات غير كافية للحكم (ظهور قليل جدًا مثلًا) قل ذلك صراحة. لا تقترح زيادة الميزانية إلا لو الأرقام تبرر ذلك بوضوح.' . ai_country_note()],
            ['role' => 'user', 'content' => implode("
", $lines)],
        ], 700, 'ads_analyze');
        echo json_encode(['ok' => true, 'text' => $r['text']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'bulk_budget') {
            // تعديل ميزانية كذا حملة بنسبة: items[] = "id|platform|amount|type"
            $items = array_slice(array_filter((array)($_POST['items'] ?? []), 'is_string'), 0, 50);
            if (!can('ads.spend')) {
                flash('error', t('التشغيل وتعديل الميزانية لصاحب الشركة بس.'));
            } elseif (empty($_POST['confirm_spend'])) {
                flash('error', t('لازم تعلّم على خانة التأكيد الأول.'));
            } else {
                $ok = 0; $fail = [];
                foreach ($items as $it) {
                    [$cid, $cpf, $amt, $bt] = array_pad(explode('|', $it, 4), 4, '');
                    $amt = (float)$amt;
                    if ($cid === '' || $amt <= 0 || !in_array($cpf, ads_campaign_platforms(), true)) continue;
                    try { ads_update_campaign_budget($z, $cid, $cpf, $amt, $bt === 'lifetime' ? 'lifetime' : 'daily'); log_activity('ads.budget', $cid . ' ' . $bt . ' ' . $amt); $ok++; }
                    catch (ZernioException $ex) { $fail[] = $ex->getMessage(); }
                }
                if ($ok) flash('success', t('اتعدلت ميزانية %s حملة.', $ok));
                if ($fail) flash('error', t('%s حملة ما اتعدلتش: %s', count($fail), $fail[0]));
            }
        } elseif ($action === 'stop_all') {
            // طوارئ: وقف كل الحملات الشغالة في كل الحسابات (مش بس الصفحة الحالية)
            if (($_POST['confirm_text'] ?? '') !== 'STOP') {
                flash('error', t('اكتب STOP بالظبط عشان نوقف كل الحملات.'));
            } else {
                $ok = 0; $fail = [];
                for ($page = 1; $page <= 10; $page++) {
                    $r = ads_campaigns($z, ['status' => 'active', 'page' => $page, 'limit' => 100]);
                    foreach ($r['campaigns'] ?? [] as $c) {
                        try { ads_set_campaign_status($z, (string)$c['platformCampaignId'], (string)$c['platform'], 'paused'); $ok++; }
                        catch (ZernioException $ex) { $fail[] = $ex->getMessage(); }
                    }
                    if (count($r['campaigns'] ?? []) < 100) break;
                }
                log_activity('ads.stop_all', (string)$ok);
                flash($fail ? 'error' : 'success', t('اتوقفت %s حملة.', $ok) . ($fail ? ' ' . t('%s حملة ما اتعدلتش: %s', count($fail), $fail[0]) : ''));
            }
        } elseif ($action === 'bulk_status') {
            $to = ($_POST['to'] ?? '') === 'active' ? 'active' : 'paused';
            $ids = array_slice(array_filter((array)($_POST['ids'] ?? []), 'is_string'), 0, 50);
            if (!$ids) {
                flash('error', t('اختار حملة واحدة على الأقل.'));
            } elseif ($to === 'active' && !can('ads.spend')) {
                flash('error', t('تشغيل الحملات (صرف فلوس) لصاحب الشركة بس.'));
            } elseif ($to === 'active' && empty($_POST['confirm_spend'])) {
                flash('error', t('لازم تعلّم على خانة التأكيد قبل التشغيل.'));
            } else {
                $ok = 0; $fail = [];
                foreach ($ids as $pair) {
                    [$cid, $cpf] = array_pad(explode('|', $pair, 2), 2, '');
                    if ($cid === '' || !in_array($cpf, ads_campaign_platforms(), true)) continue;
                    try { ads_set_campaign_status($z, $cid, $cpf, $to); log_activity('ads.campaign_' . $to, $cid); $ok++; }
                    catch (ZernioException $ex) { $fail[] = $ex->getMessage(); }
                }
                if ($ok) flash('success', $to === 'active' ? t('اتشغّلت %s حملة.', $ok) : t('اتوقفت %s حملة.', $ok));
                if ($fail) flash('error', t('%s حملة ما اتعدلتش: %s', count($fail), $fail[0]));
            }
        } elseif ($action === 'connect') {
            if (!can('ads.spend')) {
                flash('error', t('ربط حساب الإعلانات لصاحب الشركة بس.'));
            } else {
                $pfm = (string)($_POST['platform'] ?? '');
                if (!isset(ads_connect_platforms()[$pfm])) {
                    flash('error', t('اختار منصة صحيحة.'));
                } else {
                    $r = ads_connect_url($z, $pfm, base_url() . '/' . url('ads'));
                    if (!empty($r['authUrl'])) {
                        log_activity('ads.connect', $pfm);
                        header('Location: ' . $r['authUrl']);
                        exit;
                    }
                    flash('success', t('حساب الإعلانات ده مربوط بالفعل.'));
                }
            }
        } elseif ($action === 'status') {
            $cid = (string)($_POST['campaign_id'] ?? '');
            $cpf = (string)($_POST['platform'] ?? '');
            $to = ($_POST['to'] ?? '') === 'active' ? 'active' : 'paused';
            if ($cid === '' || !in_array($cpf, ads_campaign_platforms(), true)) {
                flash('error', t('بيانات الحملة ناقصة.'));
            } elseif ($to === 'active' && !can('ads.spend')) {
                flash('error', t('تشغيل الحملات (صرف فلوس) لصاحب الشركة بس.'));
            } elseif ($to === 'active' && empty($_POST['confirm_spend'])) {
                flash('error', t('لازم تعلّم على خانة التأكيد قبل التشغيل.'));
            } else {
                $r = ads_set_campaign_status($z, $cid, $cpf, $to);
                log_activity('ads.campaign_' . $to, $cid);
                if (!empty($r['skipped'])) {
                    flash('success', t('الحملة كانت أصلاً في الحالة دي.'));
                } else {
                    flash('success', $to === 'active' ? t('تم تشغيل الحملة.') : t('تم إيقاف الحملة.'));
                }
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

$connections = [];
$adAccounts = [];   // [ ['conn'=>..., 'accounts'=>[...], 'error'=>?] ]
$campaigns = [];
$prev = [];
$pagination = [];
$apiError = null;
[$from, $to] = ads_date_range($range);
if ($z) {
    try {
        $connections = ads_connections($z);
        foreach ($connections as $c) {
            $row = ['conn' => $c, 'accounts' => [], 'error' => null];
            try {
                $row['accounts'] = ads_ad_accounts($z, $c['_id'], isset($_GET['refresh']));
            } catch (ZernioException $ex) {
                $row['error'] = $ex->getMessage();
            }
            $adAccounts[] = $row;
        }
        $uiFx = null; if (($fxd = getenv('UI_FIXTURES')) && is_file($fxd . '/ads.json')) $uiFx = json_decode((string)file_get_contents($fxd . '/ads.json'), true) ?: null;
        $r = $uiFx ? ['campaigns' => array_values(array_filter($uiFx['campaigns'] ?? [], function ($c) use ($platform, $adAcc, $status) { return (!$platform || $c['platform'] === $platform) && (!$adAcc || $c['platformAdAccountId'] === $adAcc) && (!$status || $c['status'] === $status); }))] : ads_campaigns($z, [
            'platform' => $platform ?: null, 'adAccountId' => $adAcc ?: null, 'status' => $status ?: null,
            'fromDate' => $from, 'toDate' => $to, 'page' => $pg, 'limit' => 25,
        ]);
        if ($uiFx) $r['pagination'] = ['total' => count($r['campaigns'])];
        $campaigns = $r['campaigns'] ?? [];
        $pagination = $r['pagination'] ?? [];
        // الفترة اللي قبلها بنفس الطول، للمقارنة
        if ($campaigns) {
            $len = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;
            $pTo = date('Y-m-d', strtotime($from . ' -1 day'));
            $pFrom = date('Y-m-d', strtotime($pTo . ' -' . ($len - 1) . ' days'));
            try {
                $pfx = ($fxd ?? '') && is_file($fxd . '/ads_prev.json') ? json_decode((string)file_get_contents($fxd . '/ads_prev.json'), true) : null;
                $pr = $uiFx ? ($pfx ?: ['campaigns' => []]) : ads_campaigns($z, array_filter(['platform' => $platform ?: null, 'adAccountId' => $adAcc ?: null, 'fromDate' => $pFrom, 'toDate' => $pTo, 'limit' => 100]));
                foreach ($pr['campaigns'] ?? [] as $pc) $prev[(string)($pc['platformCampaignId'] ?? '')] = $pc['metrics'] ?? [];
            } catch (ZernioException $ex) { /* المقارنة اختيارية */ }
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

$ref = [];
foreach ($campaigns as $c) { $b = $c['campaignBudget'] ?? $c['budget'] ?? null; if ($b && ($b['type'] ?? '') === 'daily' && (float)($b['amount'] ?? 0) > 0) $ref[(string)($c['currency'] ?? '')][] = (float)$b['amount']; }
foreach ($ref as $k => $arr) { sort($arr); $_SESSION['ads_budget_ref'][$k] = $arr[intdiv(count($arr), 2)]; }
$cpmAgg = [];
foreach ($campaigns as $c) { $m = $c['metrics'] ?? []; if ((int)($m['impressions'] ?? 0) > 0) { $k = (string)($c['currency'] ?? ''); $cpmAgg[$k][0] = ($cpmAgg[$k][0] ?? 0) + (float)($m['spend'] ?? 0); $cpmAgg[$k][1] = ($cpmAgg[$k][1] ?? 0) + (int)$m['impressions']; } }
foreach ($cpmAgg as $k => $x) if ($x[1] >= 1000 && $x[0] > 0) $_SESSION['ads_cpm_ref'][$k] = round($x[0] / $x[1] * 1000, 2);

// الصرف لكل حساب إعلانات (من الحملات المعروضة)
$spendByAcc = [];
$totals = [];
foreach ($campaigns as $c) {
    $m = $c['metrics'] ?? [];
    $a = (string)($c['platformAdAccountId'] ?? '');
    $spendByAcc[$a] = ($spendByAcc[$a] ?? 0) + (float)($m['spend'] ?? 0);
    $cur = (string)($c['currency'] ?? '');
    $t = $totals[$cur] ?? ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'results' => 0];
    $t['spend'] += (float)($m['spend'] ?? 0);
    $t['impressions'] += (int)($m['impressions'] ?? 0);
    $t['clicks'] += (int)($m['clicks'] ?? 0);
    $t['results'] += ads_results($m);
    $totals[$cur] = $t;
}
$usableCount = 0;
foreach ($adAccounts as $row) {
    foreach ($row['accounts'] as $a) if (ads_account_usable($a)) $usableCount++;
}

render(t('الإعلانات'), 'ads', function () use ($z, $apiError, $adAccounts, $campaigns, $pagination, $platform, $adAcc, $status, $range, $pg, $spendByAcc, $totals, $usableCount, $from, $to, $prev) {
    if (!$z) { no_api_box(); return; }
    $q = function (array $over = []) use ($platform, $adAcc, $status, $range) {
        return url('ads', array_filter($over + ['pf' => $platform, 'acc' => $adAcc, 'st' => $status, 'r' => $range], function ($v) { return $v !== '' && $v !== null; }));
    };
    ?>
    <div class="space-y-5">
      <?php if ($apiError) error_box($apiError); ?>

      <div class="flex flex-wrap items-center gap-2">
        <a href="<?= e(url('ad_create')) ?>" class="btn-primary"><?= e(t('+ حملة جديدة')) ?></a>
        <a href="<?= e(url('ad_audiences')) ?>" class="btn-ghost"><?= e(t('الجماهير')) ?></a>
        <a href="<?= e(url('leads')) ?>" class="btn-ghost"><?= e(t('العملاء المحتملين (Leads)')) ?></a>
      </div>

      <?php if (!$adAccounts || $usableCount === 0 && !array_filter($adAccounts, function ($r) { return $r['accounts']; })): ?>
        <?php ads_empty_state(); ?>
      <?php else: ?>
      <!-- حسابات الإعلانات -->
      <details class="card p-5 group" id="ads-accs" <?= $adAcc === '' && !$campaigns ? 'open' : '' ?>>
        <summary class="flex flex-wrap items-center justify-between gap-2 cursor-pointer list-none">
          <h2 class="font-bold text-ink-900 flex items-center gap-2"><?= e(t('حسابات الإعلانات')) ?> <span class="rounded-full bg-lime-100 text-lime-700 text-[11px] px-2 py-0.5 tabular-nums"><?= e(t('%s شغال', $usableCount)) ?></span>
            <svg class="w-4 h-4 text-ink-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></h2>
          <a href="<?= e($q(['refresh' => 1])) ?>" class="link text-sm" onclick="event.stopPropagation()"><?= e(t('تحديث القائمة')) ?></a>
        </summary>
        <div class="mt-3"></div>
        <div class="space-y-4">
          <?php foreach ($adAccounts as $row): $c = $row['conn'];
            $usable = array_values(array_filter($row['accounts'], 'ads_account_usable'));
            $off = array_values(array_filter($row['accounts'], function ($a) { return !ads_account_usable($a); }));
            ?>
            <div class="rounded-xl border border-ink-200 p-4">
              <div class="flex items-center gap-3">
                <?= avatar($c['profilePicture'] ?? null, $c['displayName'] ?? '', 'w-9 h-9') ?>
                <div class="min-w-0">
                  <div class="font-semibold text-ink-900 truncate"><?= e($c['displayName'] ?? $c['username'] ?? '') ?></div>
                  <div class="text-xs text-ink-500"><?= e(ads_platform_label($c['platform'] ?? '')) ?></div>
                </div>
              </div>
              <?php if ($row['error']): ?>
                <p class="text-sm text-ink-500 mt-2"><?= e(t('مش قادرين نقرا حسابات الإعلانات هنا: %s', $row['error'])) ?></p>
              <?php elseif (!$row['accounts']): ?>
                <p class="text-sm text-ink-500 mt-2"><?= e(t('مفيش حسابات إعلانات على الحساب ده.')) ?></p>
              <?php else: ?>
                <div class="mt-3 grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                  <?php foreach ($usable as $a): $id = (string)$a['id']; ?>
                    <a href="<?= e($q(['acc' => $id, 'pg' => null])) ?>" class="rounded-lg border p-3 hover:bg-ink-50 <?= $adAcc === $id ? 'border-brand-500 bg-brand-50' : 'border-ink-200' ?>">
                      <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-sm text-ink-900 truncate"><?= e($a['name'] ?? $id) ?></span>
                        <span class="badge badge-green"><?= e(t('شغال')) ?></span>
                      </div>
                      <div class="text-xs text-ink-500 mt-1" dir="ltr"><?= e($id) ?> · <?= e($a['currency'] ?? '') ?></div>
                      <?php if (isset($spendByAcc[$id])): ?><div class="text-xs text-ink-700 mt-1"><?= e(t('الصرف: %s', ads_money($spendByAcc[$id], $a['currency'] ?? null))) ?></div><?php endif; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
                <?php if ($off): ?>
                  <details class="mt-2">
                    <summary class="text-xs text-ink-500 cursor-pointer"><?= e(t('حسابات موقوفة أو مش متاحة (%s)', count($off))) ?></summary>
                    <ul class="mt-2 text-xs text-ink-600 space-y-1">
                      <?php foreach ($off as $a): ?>
                        <li><span class="font-medium"><?= e($a['name'] ?? $a['id']) ?></span> <span dir="ltr">(<?= e($a['id']) ?>)</span> — <?= e($a['unusableReason'] ?? t('غير متاح')) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </details>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <details class="mt-4">
          <summary class="text-sm link cursor-pointer"><?= e(t('ربط حساب إعلانات تاني')) ?></summary>
          <?php ads_connect_buttons(); ?>
        </details>
      </details>
      <?php endif; ?>

      <!-- الفلاتر -->
      <div class="sticky top-16 z-30 -mx-1 px-1 py-2 bg-ink-50/85 backdrop-blur" id="ads-bar">
        <div class="card px-3 py-2 flex flex-wrap items-center gap-2">
          <div class="tb p-0.5 gap-0.5 overflow-x-auto" style="scrollbar-width:none">
            <?php foreach (ads_date_presets() as $k => $v): ?><a href="<?= e($q(['r' => $k, 'pg' => null])) ?>" class="h-8 px-3 rounded-lg text-xs flex items-center whitespace-nowrap <?= (string)$k === $range ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>"><?= e($v) ?></a><?php endforeach; ?>
          </div>
          <div class="tb p-0.5 gap-0.5">
            <a href="<?= e($q(['pf' => null, 'pg' => null])) ?>" class="h-8 px-3 rounded-lg text-xs flex items-center <?= $platform === '' ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>"><?= e(t('كل المنصات')) ?></a>
            <?php $connPf = array_values(array_unique(array_filter(array_map(function ($r) { return (string)($r['conn']['platform'] ?? ''); }, $adAccounts)))); foreach (ads_campaign_platforms() as $p): if (!in_array($p, $connPf, true) && $p !== $platform) continue; ?><a href="<?= e($q(['pf' => $p, 'pg' => null])) ?>" class="h-8 px-2.5 rounded-lg text-xs flex items-center gap-1.5 <?= $p === $platform ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>"><?= platform_icon($p, 'w-4 h-4') ?><?= e(ads_platform_label($p)) ?></a><?php endforeach; ?>
          </div>
          <form method="get" class="contents">
            <input type="hidden" name="p" value="ads"><input type="hidden" name="r" value="<?= e($range) ?>"><?php if ($platform): ?><input type="hidden" name="pf" value="<?= e($platform) ?>"><?php endif; ?><?php if ($status): ?><input type="hidden" name="st" value="<?= e($status) ?>"><?php endif; ?>
            <select name="acc" class="input !mt-0 !h-9 !py-0 !w-auto max-w-[14rem] text-xs" onchange="this.form.submit()">
              <option value=""><?= e(t('كل الحسابات')) ?></option>
              <?php foreach ($adAccounts as $row) foreach ($row['accounts'] as $a): if (!ads_account_usable($a) && $adAcc !== (string)$a['id']) continue; ?>
                <option value="<?= e($a['id']) ?>" <?= $adAcc === (string)$a['id'] ? 'selected' : '' ?>><?= e(($a['name'] ?? $a['id']) . ' — ' . ads_platform_label($row['conn']['platform'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <div class="tb p-0.5 gap-0.5 overflow-x-auto" style="scrollbar-width:none">
            <?php foreach (['' => t('كل الحالات'), 'active' => t('شغالة'), 'paused' => t('متوقفة'), 'pending_review' => t('تحت المراجعة'), 'rejected' => t('مرفوضة'), 'completed' => t('خلصت'), 'error' => t('فيها مشكلة')] as $k => $v): ?>
              <a href="<?= e($q(['st' => $k ?: null, 'pg' => null])) ?>" class="h-8 px-3 rounded-lg text-xs flex items-center whitespace-nowrap <?= $k === $status ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>"><?= e($v) ?></a>
            <?php endforeach; ?>
          </div>
          <span class="text-[11px] text-ink-500 ms-auto" dir="ltr"><?= e($from . ' → ' . $to) ?></span>
          <button type="button" id="ads-help" class="tb w-9 h-9 justify-center text-sm font-semibold text-ink-500" title="<?= e(t('اختصارات الكيبورد')) ?> (?)">?</button>
        </div>
      </div>

      <!-- الإجماليات: كارت واحد، سطر لكل عملة -->
      <?php if ($totals): ?>
      <div class="card p-0 overflow-hidden">
        <div class="grid grid-cols-[auto_repeat(4,minmax(0,1fr))] text-[11px] text-ink-500 bg-ink-50 border-b border-ink-100">
          <span class="px-4 py-2"><?= e(t('العملة')) ?></span><span class="px-4 py-2"><?= e(t('الصرف')) ?></span><span class="px-4 py-2"><?= e(t('مرات الظهور')) ?></span><span class="px-4 py-2"><?= e(t('الضغطات')) ?></span><span class="px-4 py-2"><?= e(t('النتايج')) ?></span>
        </div>
        <?php foreach ($totals as $cur => $tt): ?>
          <div class="grid grid-cols-[auto_repeat(4,minmax(0,1fr))] items-center border-b border-ink-100 last:border-0">
            <span class="px-4 py-3"><span class="rounded-md bg-brand-50 text-brand-700 text-xs font-semibold px-2 py-1" dir="ltr"><?= e($cur ?: '—') ?></span></span>
            <?php foreach ([ads_money($tt['spend'], $cur ?: null), ads_num($tt['impressions']), ads_num($tt['clicks']), ads_num($tt['results'])] as $i => $v): ?>
              <span class="ads-num px-4 py-3 tabular-nums <?= $i === 0 ? 'text-xl font-bold text-ink-900' : 'text-lg font-semibold text-ink-800' ?>"><?= e($v) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- محتاج انتباهك -->
      <section id="ads-attn" class="card p-4 hidden"></section>

      <!-- الحملات -->
      <section class="card overflow-hidden">
        <div id="ads-bulk" class="hidden sticky top-0 z-20 p-3 bg-brand-600 text-white flex flex-wrap items-center gap-2 text-sm"></div>
        <div class="p-4 border-b border-ink-200 flex items-center justify-between">
          <h2 class="font-bold text-ink-900"><?= e(t('الحملات')) ?> <span class="text-xs font-normal text-ink-500"><?= e(t('%s حملة', (int)($pagination['total'] ?? count($campaigns)))) ?></span></h2>
          <?php if ($campaigns): ?><div class="relative w-64 max-w-full"><svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-3 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/></svg><input type="search" id="ads-q" class="input !mt-0 !h-9 !py-0 ps-9 text-sm" dir="auto" placeholder="<?= e(t('ابحث في الحملات...')) ?> (/)"></div><?php endif; ?>
        </div>
        <?php if (!$campaigns): ?>
          <div class="p-8 text-center text-sm text-ink-500">
            <?= e(t('مفيش حملات بالفلاتر دي.')) ?>
            <div class="mt-3"><a href="<?= e(url('ad_create')) ?>" class="link"><?= e(t('اعمل أول حملة')) ?></a></div>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm" id="ads-table">
              <thead class="bg-ink-50 text-ink-600 text-xs">
                <tr>
                  <th class="p-3 w-8"><input type="checkbox" id="ads-all" aria-label="<?= e(t('اختار الكل')) ?>"></th>
                  <th class="text-start p-3"><?= e(t('الحملة')) ?></th>
                  <th class="text-start p-3"><?= e(t('الحالة')) ?></th>
                  <th class="text-start p-3"><?= e(t('الميزانية')) ?></th>
                  <th class="text-end p-3 cursor-pointer select-none hover:text-ink-900" data-sort="4"><?= e(t('الصرف')) ?></th>
                  <th class="text-end p-3 cursor-pointer select-none hover:text-ink-900" data-sort="5"><?= e(t('الظهور')) ?></th>
                  <th class="text-end p-3 cursor-pointer select-none hover:text-ink-900" data-sort="6"><?= e(t('الضغطات')) ?></th>
                  <th class="text-end p-3 cursor-pointer select-none hover:text-ink-900" data-sort="7">CTR</th>
                  <th class="text-end p-3 cursor-pointer select-none hover:text-ink-900" data-sort="8">CPC</th>
                  <th class="text-end p-3 cursor-pointer select-none hover:text-ink-900" data-sort="9"><?= e(t('النتايج')) ?></th>
                  <th class="p-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-ink-100">
              <?php foreach ($campaigns as $c):
                $m = $c['metrics'] ?? [];
                $cur = $c['currency'] ?? null;
                $cid = (string)($c['platformCampaignId'] ?? '');
                $cpf = (string)($c['platform'] ?? '');
                $budget = $c['campaignBudget'] ?? $c['budget'] ?? null;
                $st = (string)($c['status'] ?? '');
                $detail = url('ad_campaign', ['id' => $cid, 'pf' => $cpf, 'r' => $range]);
                ?>
                <?php $pm = $prev[$cid] ?? null; $bAmt = (float)($budget['amount'] ?? 0); ?>
                <tr class="ads-row align-top hover:bg-ink-50 transition cursor-pointer" data-id="<?= e($cid) ?>" data-pf="<?= e($cpf) ?>" data-st="<?= e($st) ?>" data-cur="<?= e((string)$cur) ?>" data-name="<?= e($c['campaignName'] ?? $cid) ?>" data-acc="<?= e((string)($c['platformAdAccountId'] ?? '')) ?>" data-accn="<?= e((string)($c['platformAdAccountName'] ?? '')) ?>"
                  data-b="<?= e(json_encode($budget && $bAmt > 0 ? [$bAmt, ($budget['type'] ?? 'daily') === 'lifetime' ? 'lifetime' : 'daily', !empty($c['campaignBudget'])] : null)) ?>" data-p="<?= e(json_encode($pm ? [(float)($pm['spend'] ?? 0), (int)($pm['impressions'] ?? 0), (int)($pm['clicks'] ?? 0), (float)($pm['ctr'] ?? 0), (float)($pm['cpc'] ?? 0), ads_results($pm)] : null)) ?>" data-href="<?= e($detail) ?>" data-q="<?= e(mb_strtolower(($c['campaignName'] ?? '') . ' ' . ($c['platformAdAccountName'] ?? ''))) ?>" data-v="<?= e(json_encode([(float)($m['spend'] ?? 0), (int)($m['impressions'] ?? 0), (int)($m['clicks'] ?? 0), (float)($m['ctr'] ?? 0), (float)($m['cpc'] ?? 0), ads_results($m)])) ?>">
                  <td class="p-3"><input type="checkbox" class="ads-sel" value="<?= e($cid . '|' . $cpf) ?>" aria-label="<?= e(t('اختار %s', $c['campaignName'] ?? $cid)) ?>"></td>
                  <td class="p-3 min-w-[14rem]">
                    <a href="<?= e($detail) ?>" class="font-semibold text-ink-900 hover:underline"><?= e($c['campaignName'] ?? $cid) ?></a>
                    <div class="text-xs text-ink-500 mt-0.5"><?= e(ads_platform_label($cpf)) ?> · <?= e($c['platformAdAccountName'] ?? $c['platformAdAccountId'] ?? '') ?> · <?= e(t('%s إعلان', (int)($c['adCount'] ?? 0))) ?></div>
                  </td>
                  <td class="p-3"><?= ads_status_badge($st) ?></td>
                  <td class="p-3 whitespace-nowrap"><span class="ads-budget"><?= e(ads_budget_text($budget, $cur)) ?></span></td>
                  <td class="p-3 text-end whitespace-nowrap font-medium"><?= e(ads_money($m['spend'] ?? 0, $cur)) ?></td>
                  <td class="p-3 text-end"><?= e(ads_num($m['impressions'] ?? 0)) ?></td>
                  <td class="p-3 text-end"><?= e(ads_num($m['clicks'] ?? 0)) ?></td>
                  <td class="p-3 text-end"><span class="ads-ctr rounded-md px-1.5 py-0.5" data-ctr="<?= (float)($m['ctr'] ?? 0) ?>"><?= e(number_format((float)($m['ctr'] ?? 0), 2)) ?>%</span></td>
                  <td class="p-3 text-end"><?= e(ads_money($m['cpc'] ?? 0)) ?></td>
                  <td class="p-3 text-end"><?= e(ads_num(ads_results($m))) ?></td>
                  <td class="p-3 whitespace-nowrap">
                    <?php if ($st === 'active' || $st === 'pending_review'): ?>
                      <form method="post" onsubmit="return confirm('<?= tjs('إيقاف الحملة دي؟') ?>')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="to" value="paused">
                        <input type="hidden" name="campaign_id" value="<?= e($cid) ?>"><input type="hidden" name="platform" value="<?= e($cpf) ?>">
                        <button class="btn-ghost !py-1 !px-3"><?= e(t('إيقاف')) ?></button>
                      </form>
                    <?php elseif ($st === 'paused'): ?>
                      <?php if (can('ads.spend')): ?>
                        <details class="relative">
                          <summary class="btn-ghost !py-1 !px-3 cursor-pointer list-none"><?= e(t('تشغيل…')) ?></summary>
                          <form method="post" class="absolute end-0 z-10 mt-1 w-72 card p-3 space-y-2 text-start">
                            <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="to" value="active">
                            <input type="hidden" name="campaign_id" value="<?= e($cid) ?>"><input type="hidden" name="platform" value="<?= e($cpf) ?>">
                            <?= ads_spend_confirm(t('موافق إن الحملة دي هتبدأ تصرف فلوس فعلاً. الميزانية: %s', ads_budget_text($budget, $cur))) ?>
                            <button class="btn-primary w-full"><?= e(t('تشغيل الحملة')) ?></button>
                          </form>
                        </details>
                      <?php else: ?>
                        <span class="text-xs text-ink-400"><?= e(t('التشغيل لصاحب الشركة')) ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?= e($detail) ?>" class="link text-xs block mt-1"><?= e(t('التفاصيل')) ?></a>
                    <a href="<?= e(url('ad_create', array_filter(['dup' => 1, 'dup_name' => $c['campaignName'] ?? '', 'dup_acc' => (string)($c['platformAdAccountId'] ?? ''), 'dup_budget' => $bAmt ?: null, 'dup_bt' => $budget['type'] ?? null]))) ?>" class="link text-xs block mt-1"><?= e(t('كرر')) ?></a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div id="ads-empty" class="hidden p-8 text-center text-sm text-ink-500"><?= e(t('مفيش نتائج.')) ?></div>
          <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <div class="p-3 flex justify-between text-sm border-t border-ink-200">
              <?php if ($pg > 1): ?><a class="link" href="<?= e($q(['pg' => $pg - 1])) ?>"><?= e(t('السابق')) ?></a><?php else: ?><span></span><?php endif; ?>
              <span class="text-ink-500"><?= e(t('صفحة %s من %s', $pg, (int)$pagination['pages'])) ?></span>
              <?php if ($pg < (int)$pagination['pages']): ?><a class="link" href="<?= e($q(['pg' => $pg + 1])) ?>"><?= e(t('التالي')) ?></a><?php else: ?><span></span><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
    <script>window.ADX2 = <?= json_encode(['csrf' => csrf_token(), 'canSpend' => can('ads.spend'), 'range' => $range, 'period' => ads_date_presets()[$range] ?? '',
      'myCur' => countries()[ws_country()]['cur'] ?? 'USD', 'aiUrl' => url('ads'), 'bulkUrl' => url('ads', array_filter(['pf' => $platform, 'acc' => $adAcc, 'st' => $status, 'r' => $range])), 'campUrl' => url('ad_campaign'),
      's' => ['attn' => t('محتاج انتباهك'), 'attnNone' => t('كل الحملات تمام في الفترة دي'), 'rejected' => t('مرفوضة من المنصة'), 'review' => t('لسه تحت المراجعة'),
        'lowCtr' => t('نسبة الضغط %s أقل من متوسط حساباتك %s'), 'highCpc' => t('تكلفة الضغطة %s أعلى من المتوسط بـ %s'), 'noRes' => t('صرفت %s من غير أي نتيجة'),
        'selected' => t('%s مختارة'), 'pause' => t('إيقاف'), 'activate' => t('تشغيل…'), 'compare' => t('قارن'), 'clear' => t('إلغاء الاختيار'),
        'pauseQ' => t('إيقاف %s حملة؟'), 'actTitle' => t('تشغيل الحملات المختارة'), 'actConfirm' => t('موافق إن %s حملة هتبدأ تصرف فلوس فعلاً.'), 'run' => t('تشغيل'), 'cancel' => t('إلغاء'),
        'cmpTitle' => t('مقارنة الحملات'), 'cmpMax' => t('اختار من 2 لـ 4 حملات للمقارنة'), 'metric' => t('المؤشر'),
        'cols' => [t('الصرف'), t('الظهور'), t('الضغطات'), 'CTR', 'CPC', t('النتايج')], 'costRes' => t('تكلفة النتيجة'),
        'vsPrev' => t('مقارنة بالفترة اللي قبلها'), 'share' => t('%s من صرف حملات %s'),
        'quick' => t('نظرة سريعة'), 'open' => t('افتح التفاصيل كاملة'), 'loading' => t('بيحمّل...'), 'loadErr' => t('ما قدرناش نحمّل التفاصيل'),
        'ai' => t('حلّل الحملة دي بالذكاء الاصطناعي'), 'aiWait' => t('بيحلل الأرقام...'), 'aiErr' => t('التحليل فشل'),
        'budget' => t('تعديل الميزانية'), 'amount' => t('المبلغ'), 'daily' => t('يومي'), 'lifetime' => t('إجمالي'), 'save' => t('حفظ'),
        'budConfirm' => t('موافق على تعديل ميزانية الحملة دي لـ %s.'), 'budWarn' => t('تحذير: ده %s ضعف الميزانية الحالية. اتأكد إنك مش كاتب صفر زيادة.'), 'budOwner' => t('تعديل الميزانية لصاحب الشركة بس'), 'noCbo' => t('ميزانية الحملة دي على مستوى المجموعات، عدّلها من التفاصيل'),
        'typeAmt' => t('مبلغ كبير: اكتب المبلغ تاني للتأكيد'),
        'score' => t('درجة الصحة'), 'scoreTip' => t('محسوبة من نسبة الضغط وتكلفة الضغطة مقارنة بمتوسط حساباتك، ووجود نتايج، والحالة'),
        'group' => t('تجميع'), 'gNone' => t('من غير تجميع'), 'gAcc' => t('بالحساب'), 'gPf' => t('بالمنصة'), 'gSt' => t('بالحالة'),
        'view' => t('العرض'), 'vTable' => t('جدول'), 'vCards' => t('كروت'), 'colsBtn' => t('الأعمدة'), 'csv' => t('تصدير CSV'),
        'cpm' => t('تكلفة الألف ظهور'), 'cpr' => t('تكلفة النتيجة'), 'use' => t('المصروف من الميزانية'),
        'bBudget' => t('الميزانية ±%'), 'bBudTitle' => t('تعديل ميزانية الحملات المختارة'), 'bBudSkip' => t('%s حملة ميزانيتها على مستوى المجموعات واتشالت'), 'bBudConfirm' => t('موافق على تعديل ميزانية %s حملة.'),
        'stopAll' => t('وقّف كل الحملات'), 'stopTitle' => t('إيقاف طوارئ'), 'stopText' => t('ده هيوقف كل الحملات الشغالة في كل حسابات الإعلانات، مش بس اللي ظاهرة هنا. اكتب STOP للتأكيد.'),
        'fxTitle' => t('الإجمالي بعملة واحدة'), 'fxRate' => t('1 %s = كام %s؟'), 'fxTotal' => t('إجمالي الصرف تقريبًا'), 'fxNote' => t('بسعر الصرف اللي كتبته، مش سعر السوق'),
        'adv' => t('مستشار الإعلانات'), 'advSum' => t('لخّص أداء الفترة دي'), 'advRe' => t('أعيد توزيع الميزانية إزاي؟'), 'advWait' => t('بيحلل حملاتك...'),
        'advNone' => t('مفيش نقل منطقي دلوقتي'), 'advData' => t('بيانات قليلة (أقل من 1000 ظهور)'), 'advApply' => t('طبّق النقلة دي'), 'advMove' => t('انقل %s من "%s" لـ "%s"'), 'advBad' => t('الاقتراح ده مخالف للقواعد واتشال: %s'),
        'advTitle' => t('نقل ميزانية'), 'advConfirm' => t('موافق على تعديل ميزانية الحملتين: %s ← %s، و %s ← %s.'), 'advHint' => t('الاقتراحات من الذكاء الاصطناعي ولازم توافق على كل نقلة. مفيش حاجة بتتنفذ لوحدها.'),
        'gl' => ['ctr' => t('نسبة الضغط (CTR): من كل 100 مرة إعلانك ظهر، كام مرة حد ضغط عليه. متوسط حملاتك: %s. أعلى = إعلانك بيشد أكتر.'),
          'cpc' => t('تكلفة الضغطة (CPC): بتدفع كام في المتوسط عشان حد يضغط. متوسط حملاتك بعملة %s: %s. أقل = أحسن.'),
          'cpm' => t('تكلفة الألف ظهور (CPM): بتدفع كام عشان إعلانك يظهر 1000 مرة. بتقيس غلاء الجمهور، مش جودة الإعلان.'),
          'cpr' => t('تكلفة النتيجة: الصرف ÷ النتايج (شراء، رسالة، تفاعل حسب هدف الحملة). ده أهم رقم لو هدفك مبيعات.'),
          'res' => t('النتايج: اللي الحملة اتعملت عشانه (تحويل أو تفاعل). إجمالي حملاتك: %s.'),
          'imp' => t('الظهور: عدد المرات اللي الإعلان ظهر فيها، حتى لو لنفس الشخص أكتر من مرة.')],
        'kSel' => t('اختيار الصف'), 'kQuick' => t('نظرة سريعة على الحملة')]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script>window.ADX = <?= json_encode(['keysTitle' => t('اختصارات الكيبورد'), 'keys' => [['/', t('البحث في الحملات')], ['n', t('حملة جديدة')], ['a', t('حسابات الإعلانات')], ['?', t('الاختصارات')]], 'create' => url('ad_create'), 'best' => t('أعلى نسبة ضغط'), 'low' => t('نسبة ضغط ضعيفة')], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-ads.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ui-ads2.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ui-ads3.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ui-ads4.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
