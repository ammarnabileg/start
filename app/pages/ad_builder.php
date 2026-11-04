<?php
/** منشئ الحملات المتقدم: مشاريع → حملة → مجموعات → إعلانات */
$err = null;
$z = zernio_or_null($err);
$wid = (int)ws('id');
$id = (int)($_GET['id'] ?? 0);

// ---- الإطلاق خطوة بخطوة + الفحص على المنصة (validateOnly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['launch_step', 'validate', 'reset_inflight'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_POST['id'] ?? 0));
    if (!$b || !$z) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    if (!can('ads.spend')) { echo json_encode(['ok' => false, 'error' => t('الإطلاق لصاحب صلاحية الصرف بس.')]); exit; }
    $d = $b['data']; $plan = ab_plan($d); $C = ab_caps((string)$d['platform']);
    $pr = $b['progress'] ?: ['ids' => ['campaign' => null, 'sets' => []], 'done' => [], 'inflight' => null, 'errors' => [], 'log' => []];
    if ($_POST['action'] === 'reset_inflight') { $pr['inflight'] = null; ab_set_progress($wid, $b['id'], 'failed', $pr); echo json_encode(['ok' => true]); exit; }
    if ($_POST['action'] === 'validate') {
        if (!$C['validate']) { echo json_encode(['ok' => false, 'error' => t('المنصة دي مفيهاش فحص من غير إنشاء.')]); exit; }
        $res = [];
        foreach ($plan as $i => $step) {
            if ($step['k'] === 'ad') continue;
            try { $z->request('POST', $step['k'] === 'campaign' ? '/v1/ads/campaigns' : '/v1/ads/create', [], ab_payload($d, $step, [], true)); $res[] = ['i' => $i, 'ok' => true]; }
            catch (ZernioException $ex) { $res[] = ['i' => $i, 'ok' => false, 'error' => $ex->getMessage()]; }
        }
        log_activity('ads.builder_validate', (string)$b['id']);
        echo json_encode(['ok' => true, 'results' => $res], JSON_UNESCAPED_UNICODE); exit;
    }
    // launch_step
    if (empty($_POST['confirm_spend'])) { echo json_encode(['ok' => false, 'error' => t('لازم تعلّم على خانة التأكيد الأول.')]); exit; }
    if ($pr['inflight'] !== null) { echo json_encode(['ok' => false, 'unknown' => true, 'step' => $pr['inflight'], 'error' => t('خطوة اتبعتت قبل كده وما وصلش ردها. افتح صفحة الحملات واتأكد هي اتعملت ولا لأ قبل ما تكمل، عشان ما تتكررش.')], JSON_UNESCAPED_UNICODE); exit; }
    $i = null; foreach ($plan as $k => $st) if (!in_array($k, $pr['done'], true)) { $i = $k; break; }
    if ($i === null) { ab_set_progress($wid, $b['id'], 'launched', $pr); echo json_encode(['ok' => true, 'finished' => true]); exit; }
    $pr['inflight'] = $i; ab_set_progress($wid, $b['id'], 'launching', $pr);
    try {
        [$ok, $warn, $pr['ids'], $raw] = ab_run_step($z, $d, $plan[$i], $pr['ids']);
        $pr['done'][] = $i; unset($pr['errors'][$i]); $pr['log'][] = ['i' => $i, 't' => date('H:i:s'), 'warn' => $warn];
        $pr['inflight'] = null;
        $fin = count($pr['done']) === count($plan);
        ab_set_progress($wid, $b['id'], $fin ? 'launched' : 'launching', $pr);
        log_activity('ads.builder_step', $b['id'] . ' #' . $i);
        echo json_encode(['ok' => true, 'step' => $i, 'warn' => $warn, 'finished' => $fin, 'progress' => $pr], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        $pr['inflight'] = null; $pr['errors'][$i] = $ex->getMessage();
        ab_set_progress($wid, $b['id'], 'failed', $pr);
        echo json_encode(['ok' => false, 'step' => $i, 'error' => $ex->getMessage(), 'progress' => $pr], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---- الجماهير المخصصة (قراءة بس)
if (($_GET['ajax'] ?? '') === 'audiences' && $z) {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_GET['id'] ?? 0)); $out = [];
    if ($b) { try { foreach (ads_audiences($z, $b['data']['connId'], $b['data']['adAccountId']) as $a) { $aid = (string)($a['id'] ?? $a['platformAudienceId'] ?? ''); if ($aid !== '') $out[] = ['id' => $aid, 'name' => (string)($a['name'] ?? $aid)]; } } catch (ZernioException $e) {} }
    echo json_encode(['audiences' => $out], JSON_UNESCAPED_UNICODE); exit;
}

// ---- AJAX (JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save', 'delete', 'duplicate'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['action'];
    $b = ab_get($wid, (int)($_POST['id'] ?? 0));
    if (!$b) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    if ($act === 'save') {
        if (!in_array($b['state'], ['draft', 'failed'], true) || !empty($b['progress']['done'])) { echo json_encode(['ok' => false, 'error' => t('الحملة اتطلقت، مينفعش تتعدل من هنا')]); exit; }
        $data = json_decode((string)($_POST['data'] ?? ''), true);
        if (!is_array($data) || strlen((string)$_POST['data']) > 2000000) { echo json_encode(['ok' => false, 'error' => t('بيانات غير صالحة')]); exit; }
        $data['platform'] = $b['data']['platform']; $data['connId'] = $b['data']['connId']; $data['adAccountId'] = $b['data']['adAccountId']; // الحساب ثابت
        ab_save($wid, $b['id'], $data);
        echo json_encode(['ok' => true, 'at' => date('H:i')]); exit;
    }
    if ($act === 'delete') {
        db()->prepare("DELETE FROM ad_builds WHERE workspace_id = ? AND id = ?")->execute([$wid, $b['id']]);
        echo json_encode(['ok' => true]); exit;
    }
    $d = $b['data']; $d['campaign']['name'] = t('نسخة من %s', $b['name']);
    echo json_encode(['ok' => true, 'id' => ab_create($wid, $d, $d['campaign']['name'])]); exit;
}

// ---- مشروع جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new' && $z) {
    [$connId, $accId, $cur, $cpf, $accName] = array_pad(explode('|', (string)($_POST['target'] ?? ''), 5), 5, '');
    if ($connId === '' || $accId === '') { flash('error', t('اختار حساب الإعلانات.')); redirect(url('ad_builder')); }
    $data = ab_default_data(ab_platform_of($cpf), $connId, $accId, $cur, $accName);
    $data['campaign']['name'] = trim((string)($_POST['name'] ?? '')) ?: t('حملة جديدة %s', date('d/m'));
    $nid = ab_create($wid, $data, $data['campaign']['name']);
    redirect(url('ad_builder', ['id' => $nid]));
}

$targets = [];
$apiError = null;
if ($z && !$id) {
    try { $targets = ads_usable_accounts($z, ads_connections($z)); } catch (ZernioException $ex) { $apiError = $ex->getMessage(); }
}
$build = $id ? ab_get($wid, $id) : null;
if ($id && !$build) { flash('error', t('المشروع مش موجود')); redirect(url('ad_builder')); }
$list = $id ? [] : ab_list($wid);

render(t('منشئ الحملات'), 'ad_builder', function () use ($z, $apiError, $targets, $build, $list) {
    if (!$z) { no_api_box(); return; }
    if (!$build): ?>
    <div class="space-y-5">
      <?php if ($apiError) error_box($apiError); ?>
      <section class="card p-5">
        <h2 class="font-bold text-ink-900"><?= e(t('حملة جديدة متقدمة')) ?></h2>
        <p class="text-sm text-ink-500 mt-1"><?= e(t('ابني حملة فيها أكتر من مجموعة إعلانية، وفي كل مجموعة أكتر من إعلان. بيتحفظ لوحده، ومش بيتطلق غير لما تراجع وتوافق.')) ?></p>
        <?php if (!$targets): ?><p class="text-sm text-ink-500 mt-4"><?= e(t('مفيش حسابات إعلانات شغالة.')) ?> <a class="link" href="<?= e(url('ads')) ?>"><?= e(t('اربط حساب')) ?></a></p>
        <?php else: ?>
        <form method="post" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] items-end">
          <?= csrf_field() ?><input type="hidden" name="action" value="new">
          <label class="block"><span class="label"><?= e(t('حساب الإعلانات')) ?></span>
            <select name="target" class="input" required>
              <?php foreach ($targets as $t): $pf = ab_platform_of((string)($t['conn']['platform'] ?? '')); ?>
                <option value="<?= e(implode('|', [$t['conn']['_id'], $t['acc']['id'], $t['acc']['currency'] ?? '', $t['conn']['platform'] ?? '', $t['acc']['name'] ?? $t['acc']['id']])) ?>"><?= e(ab_platform_label($pf) . ' · ' . ($t['acc']['name'] ?? $t['acc']['id']) . ' · ' . ($t['acc']['currency'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="block"><span class="label"><?= e(t('اسم الحملة')) ?></span><input name="name" class="input" maxlength="200" placeholder="<?= e(t('مثلاً: عرض نوفمبر')) ?>"></label>
          <button class="btn-primary h-[42px]"><?= e(t('ابدأ')) ?></button>
        </form>
        <?php endif; ?>
      </section>
      <section class="card overflow-hidden">
        <div class="p-4 border-b border-ink-200"><h2 class="font-bold text-ink-900"><?= e(t('مشاريعك')) ?> <span class="text-xs font-normal text-ink-500">(<?= count($list) ?>)</span></h2></div>
        <?php if (!$list): ?><p class="p-6 text-sm text-ink-500 text-center"><?= e(t('مفيش مشاريع لسه.')) ?></p><?php else: ?>
        <div class="divide-y divide-ink-100">
          <?php $stl = ['draft' => [t('مسودة'), 'bg-ink-100 text-ink-700'], 'launching' => [t('بيتطلق'), 'bg-amber-50 text-amber-800'], 'failed' => [t('اتطلق جزئيًا'), 'bg-red-50 text-red-700'], 'launched' => [t('اتطلق'), 'bg-lime-100 text-lime-800']];
          foreach ($list as $r): $s = $stl[$r['state']] ?? [$r['state'], 'bg-ink-100']; ?>
            <div class="ab-proj p-4 flex flex-wrap items-center gap-3" data-id="<?= (int)$r['id'] ?>">
              <a href="<?= e(url('ad_builder', ['id' => $r['id']])) ?>" class="font-semibold text-ink-900 hover:underline flex-1 min-w-0 truncate"><?= e($r['name']) ?></a>
              <span class="text-xs text-ink-500"><?= e(ab_platform_label($r['platform'])) ?></span>
              <span class="text-[11px] rounded px-1.5 py-0.5 <?= $s[1] ?>"><?= e($s[0]) ?></span>
              <span class="text-xs text-ink-400"><?= e(fmt_date($r['updated_at'], 'd/m h:i A')) ?></span>
              <button type="button" data-ab="duplicate" class="text-xs link"><?= e(t('نسخ')) ?></button>
              <button type="button" data-ab="delete" class="text-xs text-red-600 hover:underline"><?= e(t('امسح')) ?></button>
            </div>
          <?php endforeach; ?>
        </div><?php endif; ?>
      </section>
      <script>
      document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ab]'); if (!b) return; var row = b.closest('.ab-proj');
        if (b.dataset.ab === 'delete' && !confirm(<?= json_encode(t('تمسح المشروع ده؟ (الحملات اللي اتطلقت على المنصة مش هتتمسح)')) ?>)) return;
        var fd = new FormData(); fd.append('_csrf', <?= json_encode(csrf_token()) ?>); fd.append('action', b.dataset.ab); fd.append('id', row.dataset.id);
        fetch(<?= json_encode(url('ad_builder')) ?>, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) return alert(j.error || 'error'); if (j.id) location.href = <?= json_encode(url('ad_builder')) ?> + '&id=' + j.id; else row.remove();
        });
      });
      </script>
    </div>
    <?php return; endif;
    // ---- المنشئ
    $d = $build['data'];
    ?>
    <div id="ab-app" class="min-h-[60vh]" data-id="<?= (int)$build['id'] ?>"><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('بيحمّل...')) ?></div></div>
    <script>window.AB = <?= json_encode([
        'id' => (int)$build['id'], 'state' => $build['state'], 'progress' => $build['progress'], 'data' => $d,
        'caps' => ab_caps($d['platform']), 'plan' => ab_plan($d), 'campaignsUrl' => url('ads'), 'platformLabel' => ab_platform_label($d['platform']),
        'csrf' => csrf_token(), 'url' => url('ad_builder'), 'listUrl' => url('ad_builder'),
        'geoUrl' => url('ad_create', ['ajax' => 1]), 'myCountry' => ws_country(), 'lang' => lang(),
        'canSpend' => can('ads.spend'), 'kbProducts' => array_values(array_filter(array_map(function ($r) { return trim((string)($r['name'] ?? '')); }, (array)(kb_get((int)ws('id'))['services']['rows'] ?? [])))),
        's' => ab_strings(),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ad-builder.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-launch.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
