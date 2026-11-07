<?php
/** منشئ الحملات المتقدم: مشاريع → حملة → مجموعات → إعلانات */
$err = null;
$z = zernio_or_null($err);
$wid = (int)ws('id');
$id = (int)($_GET['id'] ?? 0);

// ---- الذكاء الاصطناعي: بناء الحملة، جمهور مجموعة، نسخ إعلان، مراجعة الهيكل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['ai_build', 'ai_audience', 'ai_copy', 'ai_review'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_POST['id'] ?? 0));
    if (!$b) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    $act = $_POST['action']; $d = $b['data']; $p = (string)$d['platform']; $C = ab_caps($p);
    $in = json_decode((string)($_POST['input'] ?? '{}'), true) ?: [];
    $clip = function ($v, $n = 400) { return mb_substr(is_scalar($v) ? trim((string)$v) : json_encode($v, JSON_UNESCAPED_UNICODE), 0, $n); };
    $capTxt = 'المنصة: ' . ab_platform_label($p) . '. الأهداف المسموحة: ' . implode(', ', $C['goals']) . '. أزرار الإجراء المسموحة: ' . implode(', ', array_slice($C['ctas'], 0, 30))
        . '. أقصى طول للعنوان: ' . $C['maxHeadline'] . ($C['maxBody'] ? '. أقصى طول للنص: ' . $C['maxBody'] : '') . '. ميزانية على مستوى الحملة متاحة: ' . ($C['cbo'] ? 'نعم' : 'لا')
        . '. إعلانات أكثر من واحد في المجموعة: ' . ($C['maxAdsPerSet'] === 1 ? 'لا (إعلان واحد فقط)' : 'نعم') . '. استهداف الاهتمامات متاح: ' . ($C['targeting']['interests'] ? 'نعم' : 'لا')
        . '. العملة: ' . $d['currency'] . '. دولة الشركة: ' . ws_country() . '.';
    $rules = ' قواعد صارمة: استخدم معلومات الشركة المتاحة لك فقط ولا تخترع أسعارًا أو خصومات أو أرقامًا أو شهادات. تجنب العبارات التي ترفضها المنصات (الأفضل، الأرخص، مضمون 100%، مخاطبة صفات شخصية حساسة مثل "هل أنت...؟"). اكتب الإعلانات بلغة ولهجة جمهور الشركة. أعد JSON فقط بدون أي شرح خارجه.';
    $tasks = [
        'ai_build' => ['أنت ميديا باير خبير يدير ميزانيات كبيرة. صمّم هيكل حملة كامل جاهز للاختبار المنظم: مجموعات إعلانية بجماهير مختلفة فعلًا (مثل: جمهور واسع، اهتمامات محددة، جمهور مختلف بالسن أو المنطقة) وليست متداخلة، وفي كل مجموعة إعلانات بزوايا إقناع مختلفة (الألم والحل، الفائدة المباشرة، الدليل الاجتماعي الحقيقي، العرض/الندرة الحقيقية فقط إن وُجدت). وزّع الميزانية بنسب منطقية، واختر CBO عندما يكون الهدف التوسع وABO عندما يكون الهدف الاختبار، واشرح السبب. '
            . 'الشكل: {"campaign":{"name":"","goal":"","budgetLevel":"adset|campaign","dailyBudget":رقم,"why":"سطر"},"adSets":[{"name":"","why":"لماذا هذا الجمهور","budgetShare":نسبة من 100,"targeting":{"countries":["ISO2"],"ageMin":18,"ageMax":65,"gender":"all|male|female","interestQueries":["كلمات بحث إنجليزية عن اهتمامات كما تسميها المنصة"]},"ads":[{"name":"","angle":"","headline":"","body":"","headlines":["بدائل"],"bodies":["بدائل"],"cta":""}]}],"tips":["نصيحة تنفيذية بالأرقام"]}. '
            . 'من 2 إلى 4 مجموعات، ومن 2 إلى 3 إعلانات في كل مجموعة (أو إعلان واحد إن كانت المنصة لا تسمح بأكثر).', 2600],
        'ai_audience' => ['أنت خبير استهداف. اقترح جمهورًا لهذه المجموعة الإعلانية يكون مختلفًا عن جماهير المجموعات الأخرى المذكورة حتى لا تتنافس. الشكل: {"name":"اسم قصير للجمهور","why":"سطرين","targeting":{"countries":["ISO2"],"ageMin":18,"ageMax":65,"gender":"all|male|female","interestQueries":["إنجليزي"]}}', 700],
        'ai_copy' => ['أنت كاتب إعلانات ممولة وخبير علم نفس البيع. اكتب 3 نسخ مختلفة الزاوية لهذا الإعلان تناسب جمهور مجموعته. الشكل: {"variants":[{"angle":"","headline":"","body":"","headlines":["بديلان"],"bodies":["بديل"],"cta":""}]}', 1400],
        'ai_review' => ['أنت ميديا باير كبير تراجع حملة قبل إطلاقها لعميل. انتقدها بصراحة: تداخل الجماهير، ميزانية غير كافية لعدد المجموعات (القاعدة: كل مجموعة تحتاج ميزانية تكفي 50 نتيجة في الأسبوع تقريبًا لتخرج من مرحلة التعلم)، جماهير ضيقة أو واسعة جدًا، تشابه الإعلانات داخل المجموعة، ضعف الهوك في أول سطر، زر إجراء لا يناسب الهدف، الهدف لا يناسب الغاية. '
            . 'الشكل: {"score":رقم من 100,"verdict":"سطر","findings":[{"level":"high|medium|low","where":"campaign|set:رقم|ad:رقم المجموعة.رقم الإعلان","msg":"المشكلة بالأرقام","fix":"التصحيح المقترح"}]}. رتب حسب الخطورة، ولا تتجاوز 10 ملاحظات.', 1500],
    ];
    [$sys, $max] = $tasks[$act];
    // ملخص الحملة الحالية للسياق
    $sum = ['campaign' => ['name' => $d['campaign']['name'], 'goal' => $d['campaign']['goal'], 'budgetLevel' => $d['campaign']['budgetLevel'], 'budget' => $d['campaign']['budget']]];
    foreach ($d['adSets'] as $si => $st) {
        $sum['adSets'][] = ['n' => $si + 1, 'name' => $st['name'], 'budget' => $st['budget'], 'countries' => $st['targeting']['countries'], 'age' => [$st['targeting']['ageMin'], $st['targeting']['ageMax']], 'gender' => $st['targeting']['gender'],
            'interests' => array_map(function ($x) { return $x['name']; }, $st['targeting']['interests'] ?? []), 'cities' => array_map(function ($x) { return $x['name']; }, $st['targeting']['cities'] ?? []),
            'ads' => array_map(function ($a) { return ['headline' => mb_substr($a['headline'], 0, 120), 'body' => mb_substr($a['body'], 0, 300), 'cta' => $a['cta'], 'format' => $a['format'], 'hasMedia' => (bool)($a['imageUrl'] || $a['videoUrl'] || $a['cards'])]; }, $st['ads'])];
    }
    $user = "طلب المستخدم: " . $clip($in['brief'] ?? '', 1200) . "\nالمنتج/الخدمة: " . $clip($in['product'] ?? '', 300) . "\nالميزانية اليومية المتاحة: " . $clip($in['budget'] ?? '', 30) . ' ' . $d['currency']
        . "\nالمجموعة المقصودة: " . $clip($in['set'] ?? '', 10) . "\nالإعلان المقصود: " . $clip($in['ad'] ?? '', 10) . "\nالحملة الحالية: " . json_encode($sum, JSON_UNESCAPED_UNICODE);
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys . ' ' . $capTxt . $rules . ai_country_note()], ['role' => 'user', 'content' => $user]], $max, 'ads_builder');
        $txt = (string)$r['text']; if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $j = json_decode($txt, true);
        if (!is_array($j)) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
        log_activity('ads.builder_ai', $act);
        echo json_encode(['ok' => true, 'r' => $j], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---- تقدير الوصول من المنصة (تقدير بس، مفيش إنشاء)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reach') {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_POST['id'] ?? 0)); $si = (int)($_POST['si'] ?? 0);
    if (!$b || !$z || empty($b['data']['adSets'][$si]) || !ab_caps((string)$b['data']['platform'])['reach']) { echo json_encode(['ok' => false]); exit; }
    $tg = json_decode((string)($_POST['targeting'] ?? ''), true) ?: $b['data']['adSets'][$si]['targeting'];
    try {
        $r = $z->request('POST', '/v1/ads/targeting/reach-estimate', [], ['accountId' => $b['data']['connId'], 'adAccountId' => $b['data']['adAccountId'], 'spec' => ab_targeting($tg, (string)$b['data']['platform'])]);
        echo json_encode(['ok' => true, 'available' => (bool)($r['available'] ?? false), 'lower' => $r['lower'] ?? null, 'upper' => $r['upper'] ?? null, 'daily' => $r['daily'] ?? null, 'ready' => $r['estimateReady'] ?? true]);
    } catch (ZernioException $ex) { echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE); }
    exit;
}

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
    $goal = (string)($_POST['goal'] ?? ''); if (in_array($goal, ab_caps($data['platform'])['goals'], true)) $data['campaign']['goal'] = $goal;
    $nid = ab_create($wid, $data, $data['campaign']['name']);
    redirect(url('ad_builder', array_filter(['id' => $nid, 'ai' => !empty($_POST['ai']) ? 1 : null])));
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
      <?php $gl = ab_strings()['goals'] ?? []; $goalIc = ['traffic' => 'pointer', 'engagement' => 'heart', 'awareness' => 'eye', 'video_views' => 'film', 'lead_generation' => 'users', 'conversions' => 'money', 'app_promotion' => 'zap', 'page_likes' => 'star']; ?>
      <section class="card p-0 overflow-hidden">
        <div class="p-5 bg-gradient-to-l from-brand-50 to-white border-b border-ink-100"><h2 class="text-lg font-bold text-ink-900"><?= e(t('حملة جديدة')) ?></h2>
          <p class="text-sm text-ink-500 mt-1"><?= e(t('ابني حملة فيها أكتر من مجموعة إعلانية، وفي كل مجموعة أكتر من إعلان. بيتحفظ لوحده، ومش بيتطلق غير بعد مراجعتك وموافقتك.')) ?></p></div>
        <?php if (!$targets): ?><p class="text-sm text-ink-500 p-5"><?= e(t('مفيش حسابات إعلانات شغالة.')) ?> <a class="link" href="<?= e(url('ads')) ?>"><?= e(t('اربط حساب')) ?></a></p>
        <?php else: ?>
        <form method="post" id="ab-new" class="p-5 space-y-5">
          <?= csrf_field() ?><input type="hidden" name="action" value="new">
          <div><b class="flex items-center gap-2 text-sm text-ink-900 mb-2"><span class="ab-n">1</span><?= e(t('حساب الإعلانات')) ?></b>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <?php foreach ($targets as $i => $t): $pf = ab_platform_of((string)($t['conn']['platform'] ?? '')); ?>
              <label class="cp-acc !flex-row !items-center !text-start gap-3"><input type="radio" name="target" class="sr-only" data-pf="<?= e($pf) ?>" <?= $i ? '' : 'checked' ?> required value="<?= e(implode('|', [$t['conn']['_id'], $t['acc']['id'], $t['acc']['currency'] ?? '', $t['conn']['platform'] ?? '', $t['acc']['name'] ?? $t['acc']['id']])) ?>">
                <span class="w-10 h-10 rounded-xl bg-ink-50 grid place-items-center shrink-0"><?= platform_icon((string)($t['conn']['platform'] ?? ''), 'w-6 h-6') ?></span>
                <span class="min-w-0 flex-1"><b class="block text-sm text-ink-900 truncate"><?= e($t['acc']['name'] ?? $t['acc']['id']) ?></b><span class="text-[11px] text-ink-500"><?= e(ab_platform_label($pf)) ?> · <span dir="ltr"><?= e($t['acc']['currency'] ?? '') ?></span></span></span>
                <span class="cp-ok !static" aria-hidden="true">✓</span></label>
            <?php endforeach; ?></div></div>
          <div><b class="flex items-center gap-2 text-sm text-ink-900 mb-2"><span class="ab-n">2</span><?= e(t('هدف الحملة')) ?></b>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2" id="ab-goals">
            <?php foreach ($gl as $k => [$l, $sub]): ?>
              <label class="cp-mode !items-start !text-start !px-3 !py-3" data-goal="<?= e($k) ?>"><input type="radio" name="goal" value="<?= e($k) ?>" class="sr-only" <?= $k === 'traffic' ? 'checked' : '' ?>>
                <span class="flex items-center gap-2 text-sm font-semibold"><?= ui_icon($goalIc[$k] ?? 'target', 'w-4 h-4') ?><?= e($l) ?></span><span class="text-[11px] font-normal text-ink-500"><?= e($sub) ?></span></label>
            <?php endforeach; ?></div></div>
          <div><b class="flex items-center gap-2 text-sm text-ink-900 mb-2"><span class="ab-n">3</span><?= e(t('اسم الحملة')) ?></b>
            <div class="flex flex-wrap items-center gap-3"><input name="name" class="input !mt-0 flex-1 min-w-[240px]" maxlength="200" placeholder="<?= e(t('مثلاً: عرض نوفمبر')) ?>">
              <label class="inline-flex items-center gap-2 text-sm text-ink-700 rounded-xl border border-brand-200 bg-brand-50 px-3 py-2.5"><input type="checkbox" name="ai" value="1" class="rounded"> <?= ui_icon('spark', 'w-4 h-4 text-brand-600') ?><?= e(t('ابنيها بالذكاء الاصطناعي من ملف الشركة')) ?></label>
              <button class="btn-primary !px-6"><?= e(t('ابدأ')) ?> ←</button></div></div>
        </form>
        <script>window.AB_GOALS = <?= json_encode(array_combine(array_map(function ($t) { return ab_platform_of((string)($t['conn']['platform'] ?? '')); }, $targets), array_map(function ($t) { return ab_caps(ab_platform_of((string)($t['conn']['platform'] ?? '')))['goals']; }, $targets))) ?>;
          (function () { var f = document.getElementById('ab-new'); if (!f) return; function sync() { var pf = (f.querySelector('[name=target]:checked') || {}).dataset; var ok = (window.AB_GOALS[pf && pf.pf] || []);
              f.querySelectorAll('[data-goal]').forEach(function (l) { var on = ok.indexOf(l.dataset.goal) > -1; l.hidden = !on; if (!on) l.querySelector('input').checked = false; });
              if (!f.querySelector('[name=goal]:checked')) { var first = f.querySelector('[data-goal]:not([hidden]) input'); if (first) first.checked = true; } }
            f.addEventListener('change', function (e) { if (e.target.name === 'target') sync(); }); sync(); })();</script>
        <?php endif; ?>
      </section>
      <section>
        <div class="flex items-center gap-2 mb-3"><h2 class="text-lg font-bold text-ink-900"><?= e(t('مشاريعك')) ?></h2><span class="text-xs text-ink-400"><?= count($list) ?></span></div>
        <?php if (!$list): ?><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('مفيش مشاريع لسه.')) ?></div><?php else: ?>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
          <?php $stl = ['draft' => [t('مسودة'), 'bg-ink-100 text-ink-700'], 'launching' => [t('بيتطلق'), 'bg-amber-50 text-amber-800'], 'failed' => [t('اتطلق جزئيًا'), 'bg-red-50 text-red-700'], 'launched' => [t('اتطلقت'), 'bg-lime-100 text-lime-800']];
          foreach ($list as $r): $s = $stl[$r['state']] ?? [$r['state'], 'bg-ink-100']; ?>
            <div class="ab-proj card p-4 flex flex-col gap-3 hover:shadow-md hover:border-brand-300 transition" data-id="<?= (int)$r['id'] ?>">
              <div class="flex items-start gap-3"><span class="w-10 h-10 rounded-xl bg-ink-50 grid place-items-center shrink-0"><?= platform_icon($r['platform'] === 'meta' ? 'facebook' : (string)$r['platform'], 'w-5 h-5') ?></span>
                <a href="<?= e(url('ad_builder', ['id' => $r['id']])) ?>" class="min-w-0 flex-1"><b class="block text-ink-900 truncate hover:underline"><?= e($r['name']) ?></b><span class="text-[11px] text-ink-500"><?= e(ab_platform_label($r['platform'])) ?> · <?= e(fmt_date($r['updated_at'], 'd/m h:i A')) ?></span></a>
                <span class="mk-pill <?= $s[1] ?>"><?= e($s[0]) ?></span></div>
              <div class="flex items-center gap-2 pt-2 border-t border-ink-100"><a href="<?= e(url('ad_builder', ['id' => $r['id']])) ?>" class="btn-primary !py-1 !text-xs"><?= e(t('افتح')) ?></a>
                <button type="button" data-ab="duplicate" class="btn-ghost !py-1 !text-xs"><?= ui_icon('copy', 'w-3.5 h-3.5') ?><?= e(t('نسخ')) ?></button>
                <button type="button" data-ab="delete" class="ms-auto p-1.5 rounded-lg text-ink-400 hover:text-red-600 hover:bg-red-50" title="<?= e(t('امسح')) ?>"><?= ui_icon('trash', 'w-4 h-4') ?></button></div>
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
        'ux' => ['dCampaign' => t('الاسم بيظهر ليك بس، مش للعملاء.'), 'dGoal' => t('المنصة بتحسّن العرض على حسب الهدف ده.'), 'dBudget' => t('هتدفع كام وإزاي المنصة تصرفها.'), 'dSchedule' => t('إمتى تبدأ وتخلص، والفئات اللي ليها قواعد خاصة.'),
          'dAdset' => t('كل مجموعة = جمهور وميزانية. اعمل أكتر من واحدة عشان تقارن.'), 'dLoc' => t('الدول والمدن اللي الإعلان هيظهر فيها.'), 'dPeople' => t('السن والنوع والاهتمامات.'), 'dPlace' => t('الإعلان هيظهر فين جوه المنصة.'),
          'dAd' => t('اسم الإعلان وشكله.'), 'dMedia' => t('الصورة أو الفيديو أول حاجة الناس بتشوفها.'), 'dCopy' => t('أول سطر هو اللي بيوقف الناس. خليه قصير وواضح.'), 'dCta' => t('الزرار ورايح على فين.'), 'age' => t('السن'), 'month' => t('≈ %s في الشهر')],
        'px' => ['s1' => t('الحملة'), 's2' => t('الجمهور والميزانية'), 's3' => t('الإعلانات'), 's4' => t('المراجعة والإطلاق'), 'stepN' => t('خطوة %s'), 'nSets' => t('%s مجموعة'), 'nAds' => t('%s إعلان'), 'nErr' => t('%s محتاج تصحيح'), 'review' => t('جاهز للإطلاق؟'),
          'next' => t('التالي: %s'), 'live' => t('معاينة حية'), 'story' => t('ستوري'), 'feed' => t('الفيد'), 'sponsored' => t('ممول'), 'promoted' => t('مُروَّج'), 'seeMore' => t('عرض المزيد'), 'noHead' => t('العنوان هيظهر هنا'),
          'noImg' => t('ضيف صورة'), 'noVideo' => t('ضيف فيديو'), 'like' => t('أعجبني'), 'comment' => t('تعليق'), 'share' => t('مشاركة'), 'variant' => t('نسخة %s من %s'), 'looksGood' => t('الإعلان شكله كامل'),
          'cutBody' => t('النص أطول من %s حرف، هيتقص ويظهر "عرض المزيد".'), 'cutHead' => t('العنوان أطول من %s حرف وممكن يتقص.'), 'noLink' => t('مفيش رابط للزرار.')],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ad-builder.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-launch.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-ai.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-pro.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-ui.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php if (!empty($_GET['ai'])): ?><script>(function (n) { var t = setInterval(function () { var b = document.getElementById('ab-ai-build'); if (b) { clearInterval(t); b.click(); } else if (++n > 40) clearInterval(t); }, 150); })(0);</script><?php endif; ?>
    <?php
}, ['full' => true]);
