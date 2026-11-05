<?php
/**
 * إنشاء حملة بسيطة على Meta (فيسبوك/انستجرام):
 *  - ترويج منشور موجود (POST /v1/ads/boost)
 *  - أو إعلان جديد بصورة ورابط (POST /v1/ads/create)
 * خطوتين: الفورم ← مراجعة ← إنشاء. الحملة بتتعمل "متوقفة" افتراضياً.
 */
$err = null;
$z = zernio_or_null($err);

// الذكاء الاصطناعي: نسخ إعلان / اقتراح جمهور — JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['ai_copy', 'ai_audience'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['action'];
    $focus = mb_substr(trim((string)($_POST['focus'] ?? '')), 0, 300);
    $goal = mb_substr((string)($_POST['goal'] ?? ''), 0, 40);
    $ctx = 'الهدف: ' . $goal . ($focus !== '' ? "\nالمنتج/الخدمة أو العرض: " . $focus : '') . "\nدولة الشركة: " . ws_country();
    if ($act === 'ai_copy') {
        $sys = 'أنت كاتب إعلانات ممولة محترف لفيسبوك وانستجرام وخبير في علم نفس البيع. اعتمد على معلومات الشركة المتاحة لك فقط، ولا تخترع أسعارًا أو خصومات أو أرقامًا غير موجودة. '
            . 'اكتب 3 نسخ مختلفة الزاوية (1: الألم والحل، 2: الفائدة المباشرة، 3: الدليل الاجتماعي أو الندرة الحقيقية فقط إن وُجدت). '
            . 'تجنب العبارات التي ترفضها Meta: الادعاءات المطلقة (الأفضل، الأرخص، مضمون 100%)، ومخاطبة صفات شخصية حساسة (هل أنت سمين؟ هل أنت مديون؟). '
            . 'العنوان حتى 40 حرفًا، والنص حتى 300 حرف. اكتب بلغة ولهجة جمهور الشركة. '
            . 'أعد JSON فقط بهذا الشكل بدون أي شرح: {"variants":[{"angle":"...","headline":"...","body":"...","cta":"LEARN_MORE|SHOP_NOW|SIGN_UP|CONTACT_US|BOOK_TRAVEL|GET_QUOTE|SUBSCRIBE"}]}';
    } else {
        $sys = 'أنت خبير استهداف إعلانات Meta. اقترح جمهورًا واقعيًا للحملة اعتمادًا على معلومات الشركة وجمهورها. '
            . 'أعد JSON فقط بدون شرح: {"countries":["رموز ISO من حرفين"],"age_min":18,"age_max":65,"gender":"all|male|female","interests":["4 إلى 6 كلمات بحث عن اهتمامات بالإنجليزية كما تسميها Meta"],"why":"سطر أو سطرين بالعربية يشرحان الاختيار"}. '
            . 'لا تضيق الجمهور أكثر من اللازم، ولا تحدد الجنس إلا لو المنتج موجه لجنس واحد بوضوح.';
    }
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys . ai_country_note()], ['role' => 'user', 'content' => $ctx]], 900, 'ads_copy');
        $txt = (string)$r['text'];
        if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $j = json_decode($txt, true);
        if (!is_array($j)) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
        echo json_encode(['ok' => true] + $j, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// بحث الاستهداف (اهتمامات/أماكن) — بيرجع JSON للـ JS
if (isset($_GET['ajax']) && $z) {
    header('Content-Type: application/json; charset=utf-8');
    $dim = ($_GET['dim'] ?? '') === 'geo' ? 'geo' : 'interest';
    $q = trim((string)($_GET['q'] ?? ''));
    $out = [];
    if (mb_strlen($q) >= 2 && !empty($_GET['conn'])) {
        try {
            foreach (ads_targeting_search($z, (string)$_GET['conn'], $q, $dim, (string)($_GET['acc'] ?? '') ?: null) as $r) {
                $out[] = ['id' => (string)$r['id'], 'name' => (string)$r['name'], 'type' => (string)($r['type'] ?? ''),
                    'size' => $r['audienceSize'] ?? null, 'path' => implode(' › ', (array)($r['path'] ?? [])), 'cc' => (string)($r['countryCode'] ?? '')];
            }
        } catch (ZernioException $ex) {
            echo json_encode(['error' => $ex->getMessage()]);
            exit;
        }
    }
    echo json_encode(['results' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

$goals = [
    'engagement' => t('تفاعل (لايكات وتعليقات)'),
    'traffic' => t('زيارات للموقع'),
    'awareness' => t('انتشار ووصول'),
    'video_views' => t('مشاهدات فيديو'),
];
$ctas = ['LEARN_MORE' => t('اعرف أكتر'), 'SHOP_NOW' => t('اشتري دلوقتي'), 'SIGN_UP' => t('سجل'), 'CONTACT_US' => t('كلمنا'),
    'BOOK_TRAVEL' => t('احجز'), 'GET_QUOTE' => t('اطلب عرض سعر'), 'SUBSCRIBE' => t('اشترك')];

/** يقرا الفورم ويرجع [بيانات, أخطاء] */
function adc_read_form(array $in, array $goals, array $ctas): array
{
    $f = [
        'target' => (string)($in['target'] ?? ''),
        'mode' => ($in['mode'] ?? '') === 'new' ? 'new' : 'boost',
        'name' => trim((string)($in['name'] ?? '')),
        'goal' => (string)($in['goal'] ?? 'engagement'),
        'post' => trim((string)($in['post'] ?? '')),
        'post_manual' => trim((string)($in['post_manual'] ?? '')),
        'headline' => trim((string)($in['headline'] ?? '')),
        'body' => trim((string)($in['body'] ?? '')),
        'link' => trim((string)($in['link'] ?? '')),
        'cta' => (string)($in['cta'] ?? 'LEARN_MORE'),
        'image_url' => trim((string)($in['image_url'] ?? '')),
        'budget' => (float)str_replace(',', '.', (string)($in['budget'] ?? '0')),
        'budget_type' => ($in['budget_type'] ?? '') === 'lifetime' ? 'lifetime' : 'daily',
        'start' => (string)($in['start'] ?? ''),
        'end' => (string)($in['end'] ?? ''),
        'countries' => strtoupper(preg_replace('/[^A-Za-z,]/', '', (string)($in['countries'] ?? ''))),
        'age_min' => max(18, min(65, (int)($in['age_min'] ?? 18))),
        'age_max' => max(18, min(65, (int)($in['age_max'] ?? 65))),
        'gender' => in_array($in['gender'] ?? '', ['male', 'female'], true) ? $in['gender'] : 'all',
        'interests' => (string)($in['interests'] ?? '[]'),
        'launch' => ($in['launch'] ?? '') === 'active' ? 'active' : 'paused',
    ];
    $errors = [];
    $parts = explode('|', $f['target']);
    if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') $errors[] = t('اختار حساب الإعلانات.');
    if ($f['name'] === '') $errors[] = t('اكتب اسم للحملة.');
    if (!isset($goals[$f['goal']])) $errors[] = t('اختار هدف الحملة.');
    if ($f['mode'] === 'new' && $f['goal'] === 'video_views') $errors[] = t('هدف المشاهدات متاح بس مع ترويج منشور فيديو.');
    if ($f['budget'] < 1) $errors[] = t('الميزانية لازم تكون 1 على الأقل.');
    if ($f['mode'] === 'boost' && $f['post'] === '' && $f['post_manual'] === '') $errors[] = t('اختار المنشور اللي عايز تروّجه.');
    if ($f['mode'] === 'new') {
        if ($f['headline'] === '' || $f['body'] === '') $errors[] = t('اكتب العنوان ونص الإعلان.');
        if (!filter_var($f['link'], FILTER_VALIDATE_URL)) $errors[] = t('اكتب رابط صحيح يبدأ بـ https://');
        if (!isset($ctas[$f['cta']])) $f['cta'] = 'LEARN_MORE';
    }
    $countries = array_values(array_filter(explode(',', $f['countries']), function ($c) { return strlen($c) === 2; }));
    if (!$countries) $errors[] = t('اكتب دولة واحدة على الأقل (كود من حرفين، زي EG).');
    $f['country_list'] = $countries;
    if ($f['age_min'] > $f['age_max']) $errors[] = t('السن الأصغر لازم يكون أقل من الأكبر.');
    $today = (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d');
    if ($f['start'] !== '' && $f['start'] < $today) $errors[] = t('تاريخ البداية لازم يكون النهارده أو بعده.');
    if ($f['budget_type'] === 'lifetime' && $f['end'] === '') $errors[] = t('الميزانية الإجمالية محتاجة تاريخ نهاية.');
    if ($f['end'] !== '' && $f['end'] <= ($f['start'] ?: $today)) $errors[] = t('تاريخ النهاية لازم يكون بعد البداية.');
    $ints = json_decode($f['interests'], true);
    $f['interest_list'] = [];
    foreach (is_array($ints) ? $ints : [] as $i) {
        if (!empty($i['id']) && !empty($i['name'])) $f['interest_list'][] = ['id' => (string)$i['id'], 'name' => (string)$i['name']];
    }
    $f['interests'] = json_encode($f['interest_list'], JSON_UNESCAPED_UNICODE);
    if ($f['launch'] === 'active' && !can('ads.spend')) $errors[] = t('تشغيل الحملة فوراً لصاحب الشركة بس. اختار "متوقفة".');
    return [$f, $errors];
}

/** يحول التاريخ (يوم) لـ ISO بتوقيت الشركة */
function adc_iso(string $date, bool $end = false): ?string
{
    if ($date === '') return null;
    try {
        $d = new DateTime($date . ($end ? ' 23:59:00' : ' 00:00:00'), new DateTimeZone(site_timezone()));
        if (!$end && $d < new DateTime('now', new DateTimeZone(site_timezone()))) {
            return null; // النهارده: يبدأ فوراً
        }
        return $d->format(DATE_ATOM);
    } catch (Throwable $t) {
        return null;
    }
}

/** يبني الطلب اللي هيتبعت لـ Zernio */
function adc_payload(array $f): array
{
    [$connId, $adAccId, $cur] = array_pad(explode('|', $f['target']), 3, '');
    $targeting = ['countries' => $f['country_list'], 'ageMin' => $f['age_min'], 'ageMax' => $f['age_max'], 'gender' => $f['gender']];
    if ($f['interest_list']) $targeting['interests'] = $f['interest_list'];
    $p = [
        'accountId' => $connId, 'adAccountId' => $adAccId, 'name' => $f['name'], 'goal' => $f['goal'],
        'budgetAmount' => $f['budget'], 'budgetType' => $f['budget_type'],
        'status' => $f['launch'] === 'active' ? 'ACTIVE' : 'PAUSED',
    ];
    if ($cur !== '') $p['currency'] = $cur;
    if ($s = adc_iso($f['start'])) $p['startDate'] = $s;
    if ($e = adc_iso($f['end'], true)) $p['endDate'] = $e;
    if ($f['mode'] === 'boost') {
        $p['platformPostId'] = $f['post_manual'] !== '' ? $f['post_manual'] : $f['post'];
        $p['targeting'] = $targeting;
    } else {
        $p += ['headline' => $f['headline'], 'body' => $f['body'], 'linkUrl' => $f['link'], 'callToAction' => $f['cta'], 'imageUrl' => $f['image_url']];
        $p['targeting'] = $targeting;
    }
    return $p;
}

$form = $_SESSION['adc_form'] ?? null;
if (!empty($_GET['boost_post'])) { $form = ['mode' => 'boost', 'post' => (string)$_GET['boost_post'], 'boost_acc' => (string)($_GET['boost_acc'] ?? ''), 'goal' => 'engagement']; unset($_SESSION['adc_form']); }
if (!$form && !empty($_GET['dup'])) {
    $form = ['name' => mb_substr(t('نسخة من %s', (string)($_GET['dup_name'] ?? '')), 0, 200), 'dup_acc' => (string)($_GET['dup_acc'] ?? ''),
        'budget' => (float)($_GET['dup_budget'] ?? 0) ?: '', 'budget_type' => ($_GET['dup_bt'] ?? '') === 'lifetime' ? 'lifetime' : 'daily'];
}
$step = 'form';

// المسودات: محفوظة على مستوى الشركة (آخر 20)
$draftKey = 'adc_drafts_' . (int)ws('id');
$drafts = json_decode((string)setting($draftKey, '[]'), true) ?: [];
if (!$form && !empty($_GET['draft'])) {
    foreach ($drafts as $d) if ($d['id'] === $_GET['draft']) { $form = $d['form']; $form['_draft'] = $d['id']; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['draft_save', 'draft_delete'], true)) {
    if ($_POST['action'] === 'draft_save') {
        [$f] = adc_read_form($_POST, $goals, $ctas);
        $id = preg_replace('/[^a-f0-9]/', '', (string)($_POST['draft_id'] ?? '')) ?: bin2hex(random_bytes(6));
        $drafts = array_values(array_filter($drafts, function ($d) use ($id) { return $d['id'] !== $id; }));
        array_unshift($drafts, ['id' => $id, 'name' => $f['name'] !== '' ? $f['name'] : t('مسودة بدون اسم'), 'saved' => date('Y-m-d H:i'), 'by' => (string)(current_user()['name'] ?? ''), 'form' => $f]);
        save_setting($draftKey, json_encode(array_slice($drafts, 0, 20), JSON_UNESCAPED_UNICODE));
        unset($_SESSION['adc_form']);
        flash('success', t('اتحفظت المسودة. الصورة المرفوعة مش بتتحفظ، رابط الصورة بس.'));
        redirect(url('ad_create', ['draft' => $id]));
    }
    $id = (string)($_POST['draft_id'] ?? '');
    save_setting($draftKey, json_encode(array_values(array_filter($drafts, function ($d) use ($id) { return $d['id'] !== $id; })), JSON_UNESCAPED_UNICODE));
    flash('success', t('اتمسحت المسودة.'));
    redirect(url('ad_create'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'review') {
        [$f, $errors] = adc_read_form($_POST, $goals, $ctas);
        // رفع صورة الإعلان (لو فيه)
        if (!$errors && $f['mode'] === 'new' && !empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $mime = (string)(mime_content_type($_FILES['image']['tmp_name']) ?: '');
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                $errors[] = t('الصورة لازم تكون JPG أو PNG.');
            } else {
                try {
                    $f['image_url'] = $z->uploadFile($_FILES['image']['tmp_name'], basename((string)$_FILES['image']['name']), $mime);
                } catch (ZernioException $ex) {
                    $errors[] = $ex->getMessage();
                }
            }
        }
        if (!$errors && $f['mode'] === 'new' && !filter_var($f['image_url'], FILTER_VALIDATE_URL)) {
            $errors[] = t('ارفع صورة للإعلان أو حط رابط صورة.');
        }
        $_SESSION['adc_form'] = $f;
        if ($errors) {
            foreach ($errors as $m) flash('error', $m);
            redirect(url('ad_create'));
        }
        redirect(url('ad_create', ['step' => 'review']));
    } elseif ($action === 'submit') {
        if (!$form) {
            redirect(url('ad_create'));
        }
        [$f, $errors] = adc_read_form($form, $goals, $ctas);
        if ($f['launch'] === 'active' && empty($_POST['confirm_spend'])) {
            $errors[] = t('لازم تعلّم على خانة التأكيد عشان الحملة هتشتغل فوراً.');
        }
        if ($errors) {
            foreach ($errors as $m) flash('error', $m);
            redirect(url('ad_create', ['step' => 'review']));
        }
        try {
            $payload = adc_payload($f);
            $r = $f['mode'] === 'boost' ? ads_boost($z, $payload) : ads_create($z, $payload);
            log_activity('ads.create', $f['name'] . ' (' . $payload['status'] . ', ' . $f['budget'] . ' ' . $f['budget_type'] . ')');
            unset($_SESSION['adc_form']);
            flash('success', $f['launch'] === 'active' ? t('تم إنشاء الحملة وتشغيلها.') : t('تم إنشاء الحملة وهي متوقفة. راجعها وشغّلها لما تكون جاهز.'));
            foreach ($r['warnings'] ?? [] as $w) flash('error', (string)$w);
            $cid = (string)($r['ad']['platformCampaignId'] ?? '');
            $cpf = (string)($r['ad']['platform'] ?? '');
            redirect($cid !== '' ? url('ad_campaign', ['id' => $cid, 'pf' => $cpf]) : url('ads'));
        } catch (ZernioException $ex) {
            flash('error', $ex->getMessage());
            redirect(url('ad_create', ['step' => 'review']));
        }
    } elseif ($action === 'reset') {
        unset($_SESSION['adc_form']);
        redirect(url('ad_create'));
    }
    redirect(url('ad_create'));
}

if (($_GET['step'] ?? '') === 'review' && $form) {
    $step = 'review';
}

$targets = [];
$posts = [];
$apiError = null;
if ($z) {
    try {
        $connections = ads_connections($z);
        $targets = ads_usable_accounts($z, $connections, true);
        if ($step === 'form') {
            $connIds = array_map(function ($t) { return (string)$t['conn']['_id']; }, $targets);
            $r = $z->posts(['status' => 'published', 'limit' => 50, 'profileId' => zernio_profile()]);
            foreach ($r['posts'] ?? [] as $p) {
                foreach ($p['platforms'] ?? [] as $pl) {
                    $acc = $pl['accountId'] ?? '';
                    $accId = is_array($acc) ? (string)($acc['_id'] ?? '') : (string)$acc;
                    if (!empty($pl['platformPostId']) && in_array($pl['platform'] ?? '', ['facebook', 'instagram'], true)) {
                        $posts[] = ['conn' => $accId, 'id' => (string)$pl['platformPostId'], 'platform' => $pl['platform'],
                            'text' => mb_substr(trim((string)($p['content'] ?? '')), 0, 80), 'date' => $p['publishedAt'] ?? $p['scheduledFor'] ?? $p['createdAt'] ?? null];
                    }
                }
            }
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

$kbProducts = [];
$kbAll = kb_get((int)ws('id'));
$pos = [];
foreach ((array)($kbAll['pos_offers']['rows'] ?? []) as $r) if (trim((string)($r['offer'] ?? '')) !== '') $pos[mb_strtolower(trim($r['offer']))] = $r;
foreach ((array)($kbAll['services']['rows'] ?? []) as $r) {
    $n = trim((string)($r['name'] ?? '')); if ($n === '') continue;
    $pp = $pos[mb_strtolower($n)] ?? [];
    $kbProducts[] = ['name' => $n, 'price' => (string)($r['price'] ?? ''), 'details' => (string)($r['details'] ?? ''), 'audience' => (string)($pp['audience'] ?? ''), 'problem' => (string)($pp['problem'] ?? ''), 'pod' => (string)($pp['pod'] ?? '')];
}
$kbSite = (string)($kbAll['contact']['website'] ?? $kbAll['company']['website'] ?? '');
render(t('حملة جديدة'), 'ad_create', function () use ($z, $apiError, $targets, $posts, $form, $step, $goals, $ctas, $drafts, $kbProducts, $kbSite) {
    if (!$z) { no_api_box(); return; }
    $f = $form ?? [];
    if (!empty($f['post']) && !in_array($f['post'], array_column($posts, 'id'), true)) { $f['post_manual'] = $f['post']; $f['post'] = ''; }
    $v = function ($k, $d = '') use ($f) { return $f[$k] ?? $d; };
    ?>
    <div class="<?= $step === 'review' ? 'max-w-3xl' : '' ?> space-y-5">
      <a href="<?= e(url('ads')) ?>" class="link text-sm"><?= e(t('→ رجوع للحملات')) ?></a>
      <?php if ($apiError) error_box($apiError); ?>
      <?php if (!$targets && !$apiError): ?>
        <?php ads_empty_state(); ?>
        <p class="text-sm text-ink-500"><?= e(t('إنشاء الحملات من هنا متاح حالياً لحسابات Meta (فيسبوك وانستجرام) اللي حالتها شغالة.')) ?></p>
      <?php elseif ($step === 'review'):
        $payload = adc_payload($f);
        $tgt = null;
        foreach ($targets as $t) if ($t['conn']['_id'] . '|' . $t['acc']['id'] === implode('|', array_slice(explode('|', $f['target']), 0, 2))) $tgt = $t;
        $cur = $payload['currency'] ?? '';
        $money = ads_money($f['budget'], $cur ?: null);
        ?>
        <section class="card p-5 space-y-4">
          <h2 class="text-lg font-bold text-ink-900"><?= e(t('مراجعة قبل الإنشاء')) ?></h2>
          <div class="rounded-xl bg-brand-50 border border-brand-200 p-4">
            <div class="text-sm text-ink-600"><?= e(t('الميزانية')) ?></div>
            <div class="text-2xl font-bold text-ink-900"><?= e($f['budget_type'] === 'lifetime' ? t('%s إجمالي', $money) : t('%s يومياً', $money)) ?></div>
            <?php if ($f['budget_type'] === 'daily'): ?><div class="text-xs text-ink-600 mt-1"><?= e(t('يعني تقريباً %s في 30 يوم لو فضلت شغالة.', ads_money($f['budget'] * 30, $cur ?: null))) ?></div><?php endif; ?>
          </div>
          <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-ink-500"><?= e(t('اسم الحملة')) ?></dt><dd class="font-medium text-ink-900"><?= e($f['name']) ?></dd></div>
            <div><dt class="text-ink-500"><?= e(t('حساب الإعلانات')) ?></dt><dd class="font-medium text-ink-900"><?= e($tgt ? (($tgt['acc']['name'] ?? '') . ' (' . $tgt['acc']['id'] . ')') : $payload['adAccountId']) ?></dd></div>
            <div><dt class="text-ink-500"><?= e(t('النوع')) ?></dt><dd class="font-medium text-ink-900"><?= e($f['mode'] === 'boost' ? t('ترويج منشور موجود') : t('إعلان جديد بصورة')) ?></dd></div>
            <div><dt class="text-ink-500"><?= e(t('الهدف')) ?></dt><dd class="font-medium text-ink-900"><?= e($goals[$f['goal']] ?? $f['goal']) ?></dd></div>
            <div><dt class="text-ink-500"><?= e(t('المدة')) ?></dt><dd class="font-medium text-ink-900"><?= e(($f['start'] ?: t('من دلوقتي')) . ' → ' . ($f['end'] ?: t('لحد ما توقفها'))) ?></dd></div>
            <div><dt class="text-ink-500"><?= e(t('الجمهور')) ?></dt><dd class="font-medium text-ink-900"><?= e(t('%s · سن %s-%s · %s', implode(', ', $f['country_list']), $f['age_min'], $f['age_max'], ['all' => t('الكل'), 'male' => t('رجالة'), 'female' => t('ستات')][$f['gender']])) ?></dd></div>
            <?php if ($f['interest_list']): ?><div class="sm:col-span-2"><dt class="text-ink-500"><?= e(t('الاهتمامات')) ?></dt><dd class="font-medium text-ink-900"><?= e(implode('، ', array_column($f['interest_list'], 'name'))) ?></dd></div><?php endif; ?>
            <?php if ($f['mode'] === 'boost'): ?>
              <div class="sm:col-span-2"><dt class="text-ink-500"><?= e(t('المنشور')) ?></dt><dd class="font-medium text-ink-900" dir="ltr"><?= e($payload['platformPostId']) ?></dd></div>
            <?php else: ?>
              <div class="sm:col-span-2 flex gap-3">
                <img src="<?= e($f['image_url']) ?>" alt="" class="w-24 h-24 rounded-lg object-cover bg-ink-100" referrerpolicy="no-referrer">
                <div><div class="font-semibold text-ink-900"><?= e($f['headline']) ?></div><p class="text-ink-700 whitespace-pre-line"><?= e($f['body']) ?></p>
                  <div class="text-xs text-ink-500 mt-1"><?= e($ctas[$f['cta']] ?? '') ?> · <span dir="ltr"><?= e($f['link']) ?></span></div></div>
              </div>
            <?php endif; ?>
            <div class="sm:col-span-2"><dt class="text-ink-500"><?= e(t('بعد الإنشاء')) ?></dt>
              <dd class="font-medium <?= $f['launch'] === 'active' ? 'text-red-700' : 'text-ink-900' ?>"><?= e($f['launch'] === 'active' ? t('هتشتغل فوراً وتبدأ تصرف') : t('هتتعمل متوقفة — مفيش أي صرف لحد ما تشغلها')) ?></dd></div>
          </dl>
          <form method="post" class="space-y-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="submit">
            <?php if ($f['launch'] === 'active'): ?>
              <?= ads_spend_confirm(t('موافق إن الحملة دي هتشتغل فوراً وتصرف لحد %s.', $f['budget_type'] === 'lifetime' ? t('%s إجمالي', $money) : t('%s يومياً', $money))) ?>
            <?php endif; ?>
            <div class="flex flex-wrap gap-2">
              <button class="btn-primary"><?= e($f['launch'] === 'active' ? t('إنشاء وتشغيل') : t('إنشاء الحملة (متوقفة)')) ?></button>
              <a href="<?= e(url('ad_create')) ?>" class="btn-ghost"><?= e(t('رجوع للتعديل')) ?></a>
            </div>
          </form>
        </section>
      <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="space-y-5" id="adcForm">
          <?= csrf_field() ?><input type="hidden" name="action" value="review">
          <section class="card p-5 space-y-4">
            <h2 class="font-bold text-ink-900"><?= e(t('1. الحساب والهدف')) ?></h2>
            <div>
              <label class="label"><?= e(t('حساب الإعلانات')) ?></label>
              <select name="target" class="input" id="adcTarget" required>
                <?php foreach ($targets as $t): $val = $t['conn']['_id'] . '|' . $t['acc']['id'] . '|' . ($t['acc']['currency'] ?? ''); ?>
                  <option value="<?= e($val) ?>" data-conn="<?= e($t['conn']['_id']) ?>" data-acc="<?= e($t['acc']['id']) ?>" <?= $v('target') === $val || ($v('boost_acc') !== '' && $v('boost_acc') === (string)$t['conn']['_id']) || ($v('dup_acc') !== '' && ltrim((string)$v('dup_acc'), 'act_') === ltrim((string)$t['acc']['id'], 'act_')) ? 'selected' : '' ?>><?= e(($t['acc']['name'] ?? $t['acc']['id']) . ' · ' . ($t['acc']['currency'] ?? '') . ' — ' . ($t['conn']['displayName'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="label"><?= e(t('اسم الحملة')) ?></label>
              <input name="name" class="input" required maxlength="200" value="<?= e($v('name')) ?>" placeholder="<?= e(t('مثلاً: عرض نوفمبر')) ?>">
            </div>
            <div>
              <label class="label"><?= e(t('الهدف')) ?></label>
              <select name="goal" class="input">
                <?php foreach ($goals as $k => $g): ?><option value="<?= e($k) ?>" <?= $v('goal', 'engagement') === $k ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
              </select>
            </div>
          </section>

          <section class="card p-5 space-y-4">
            <h2 class="font-bold text-ink-900"><?= e(t('2. الإعلان')) ?></h2>
            <div class="flex flex-wrap gap-4 text-sm">
              <label class="flex items-center gap-2"><input type="radio" name="mode" value="boost" <?= $v('mode', 'boost') === 'boost' ? 'checked' : '' ?>> <?= e(t('ترويج منشور موجود')) ?></label>
              <label class="flex items-center gap-2"><input type="radio" name="mode" value="new" <?= $v('mode') === 'new' ? 'checked' : '' ?>> <?= e(t('إعلان جديد بصورة ورابط')) ?></label>
            </div>
            <div data-mode="boost" class="space-y-3">
              <div>
                <label class="label"><?= e(t('اختار منشور اتنشر من هنا')) ?></label>
                <select name="post" class="input" id="adcPost">
                  <option value=""><?= e($posts ? t('— اختار —') : t('مفيش منشورات منشورة على فيسبوك/انستجرام')) ?></option>
                  <?php foreach ($posts as $p): ?>
                    <option value="<?= e($p['id']) ?>" data-conn="<?= e($p['conn']) ?>" <?= $v('post') === $p['id'] ? 'selected' : '' ?>><?= e(fmt_date($p['date'], 'd/m') . ' · ' . platform_label($p['platform']) . ' · ' . ($p['text'] ?: t('(بدون نص)'))) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="label"><?= e(t('أو اكتب رقم المنشور على المنصة')) ?></label>
                <input name="post_manual" class="input" dir="ltr" value="<?= e($v('post_manual')) ?>" placeholder="1141994492327369_123456789">
                <p class="text-xs text-ink-500 mt-1"><?= e(t('لمنشور فيسبوك: رقم الصفحة_رقم المنشور. لانستجرام: رقم الميديا.')) ?></p>
              </div>
            </div>
            <div data-mode="new" class="space-y-3">
              <div><label class="label"><?= e(t('العنوان')) ?></label><input name="headline" class="input" maxlength="100" value="<?= e($v('headline')) ?>"></div>
              <div><label class="label"><?= e(t('نص الإعلان')) ?></label><textarea data-ai="ad_copy,improve,shorten" name="body" rows="3" class="input"><?= e($v('body')) ?></textarea></div>
              <div class="grid sm:grid-cols-2 gap-3">
                <div><label class="label"><?= e(t('الرابط')) ?></label><input name="link" type="url" class="input" dir="ltr" value="<?= e($v('link')) ?>" placeholder="https://"></div>
                <div><label class="label"><?= e(t('زرار الإعلان')) ?></label>
                  <select name="cta" class="input"><?php foreach ($ctas as $k => $c): ?><option value="<?= e($k) ?>" <?= $v('cta') === $k ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="grid sm:grid-cols-2 gap-3">
                <div><label class="label"><?= e(t('صورة الإعلان (JPG/PNG)')) ?></label><input type="file" name="image" accept="image/jpeg,image/png" class="input"></div>
                <div><label class="label"><?= e(t('أو رابط صورة')) ?></label><input name="image_url" type="url" data-media="image" class="input" dir="ltr" value="<?= e($v('image_url')) ?>" placeholder="https://"></div>
              </div>
            </div>
          </section>

          <section class="card p-5 space-y-4">
            <h2 class="font-bold text-ink-900"><?= e(t('3. الميزانية والمدة')) ?></h2>
            <div class="grid sm:grid-cols-2 gap-3">
              <div><label class="label"><?= e(t('المبلغ')) ?></label><input name="budget" type="number" step="0.01" min="1" required class="input" value="<?= e($v('budget', '5')) ?>"></div>
              <div><label class="label"><?= e(t('نوع الميزانية')) ?></label>
                <select name="budget_type" class="input">
                  <option value="daily" <?= $v('budget_type', 'daily') === 'daily' ? 'selected' : '' ?>><?= e(t('يومية')) ?></option>
                  <option value="lifetime" <?= $v('budget_type') === 'lifetime' ? 'selected' : '' ?>><?= e(t('إجمالية للحملة كلها')) ?></option>
                </select></div>
              <div><label class="label"><?= e(t('تبدأ (اختياري)')) ?></label><input name="start" type="date" class="input" value="<?= e($v('start')) ?>"></div>
              <div><label class="label"><?= e(t('تنتهي (اختياري للميزانية اليومية)')) ?></label><input name="end" type="date" class="input" value="<?= e($v('end')) ?>"></div>
            </div>
            <p class="text-xs text-ink-500"><?= e(t('العملة هي عملة حساب الإعلانات اللي اخترته.')) ?></p>
          </section>

          <section class="card p-5 space-y-4">
            <h2 class="font-bold text-ink-900"><?= e(t('4. الجمهور')) ?></h2>
            <div>
              <label class="label"><?= e(t('الدول (أكواد من حرفين مفصولة بفاصلة)')) ?></label>
              <input name="countries" id="adcCountries" class="input" dir="ltr" value="<?= e($v('countries', ws_country())) ?>" placeholder="EG, SA, AE">
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div><label class="label"><?= e(t('من سن')) ?></label><input name="age_min" type="number" min="18" max="65" class="input" value="<?= e($v('age_min', '18')) ?>"></div>
              <div><label class="label"><?= e(t('لحد سن')) ?></label><input name="age_max" type="number" min="18" max="65" class="input" value="<?= e($v('age_max', '65')) ?>"></div>
              <div><label class="label"><?= e(t('النوع')) ?></label>
                <select name="gender" class="input">
                  <?php foreach (['all' => t('الكل'), 'male' => t('رجالة'), 'female' => t('ستات')] as $k => $g): ?><option value="<?= e($k) ?>" <?= $v('gender', 'all') === $k ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
                </select></div>
            </div>
            <div>
              <label class="label"><?= e(t('الاهتمامات (اختياري)')) ?></label>
              <input type="hidden" name="interests" id="adcInterests" value="<?= e($v('interests', '[]')) ?>">
              <div id="adcChips" class="flex flex-wrap gap-2 mt-1.5"></div>
              <input id="adcSearch" class="input" autocomplete="off" placeholder="<?= e(t('اكتب اهتمام وابحث، مثلاً: كرة القدم')) ?>">
              <div id="adcResults" class="mt-1 rounded-xl border border-ink-200 divide-y divide-ink-100 hidden max-h-60 overflow-y-auto bg-white"></div>
            </div>
          </section>

          <section class="card p-5 space-y-3">
            <h2 class="font-bold text-ink-900"><?= e(t('5. بعد الإنشاء')) ?></h2>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="launch" value="paused" <?= $v('launch', 'paused') === 'paused' ? 'checked' : '' ?>> <?= e(t('اعملها متوقفة (مُستحسن) — أراجعها وأشغلها بعدين')) ?></label>
            <?php if (can('ads.spend')): ?>
              <label class="flex items-center gap-2 text-sm"><input type="radio" name="launch" value="active" <?= $v('launch') === 'active' ? 'checked' : '' ?>> <?= e(t('شغّلها فوراً (هتبدأ تصرف)')) ?></label>
            <?php endif; ?>
          </section>

          <div class="flex gap-2">
            <button class="btn-primary"><?= e(t('مراجعة ←')) ?></button>
            <?php if ($form): ?><button form="adcReset" class="btn-ghost"><?= e(t('ابدأ من الأول')) ?></button><?php endif; ?>
          </div>
        </form>
        <form method="post" id="adcReset"><?= csrf_field() ?><input type="hidden" name="action" value="reset"></form>
        <script>window.ADC = <?= json_encode(['preview' => t('معاينة الإعلان'), 'sponsored' => t('مُموَّل'), 'like' => t('إعجاب'), 'comment' => t('تعليق'), 'share' => t('مشاركة'),
          'ctas' => $ctas, 'noText' => t('نص الإعلان هيظهر هنا'), 'noHead' => t('العنوان'), 'boostHint' => t('هيتروّج المنشور ده زي ما هو'), 'pickPost' => t('اختار منشور عشان تشوف المعاينة'),
          'budget' => t('الميزانية'), 'perDay' => t('%s في اليوم'), 'total' => t('%s إجمالي'), 'days' => t('%s يوم'), 'estimate' => t('تقريبًا %s لو اشتغلت %s'), 'openEnd' => t('من غير تاريخ نهاية: بنحسب على 30 يوم'),
          'summary' => t('الملخص'), 'acc' => t('الحساب'), 'audience' => t('الجمهور'), 'age' => t('سن %s-%s'), 'launchPaused' => t('هتتعمل متوقفة، مفيش صرف'), 'launchActive' => t('هتشتغل فورًا وتبدأ تصرف'),
          'myCountry' => ws_country(), 'nearby' => ['EG' => ['SA', 'AE', 'KW'], 'SA' => ['AE', 'KW', 'QA', 'BH'], 'AE' => ['SA', 'QA', 'OM'], 'KW' => ['SA', 'AE', 'BH'], 'QA' => ['SA', 'AE', 'BH'], 'BH' => ['SA', 'KW', 'AE'], 'OM' => ['AE', 'SA'], 'JO' => ['SA', 'AE', 'PS'], 'MA' => ['DZ', 'TN', 'FR']][ws_country()] ?? ['SA', 'AE', 'EG'],
          'cNames' => array_map(function ($c) { return lang() === 'ar' ? $c['ar'] : $c['en']; }, countries()), 'flags' => array_map(function ($c) { return $c['flag']; }, countries()),
          'csrf' => csrf_token(), 'goalsMap' => $goals,
          'products' => $kbProducts, 'site' => $kbSite, 'kbUrl' => url('knowledge'), 'draftId' => (string)$v('_draft'),
          'drafts' => array_map(function ($d) { return ['id' => $d['id'], 'name' => $d['name'], 'saved' => $d['saved'], 'by' => $d['by'], 'url' => url('ad_create', ['draft' => $d['id']])]; }, $drafts),
          'cpm' => $_SESSION['ads_cpm_ref'] ?? new stdClass(),
          'p' => ['pick' => t('اختار منتج أو خدمة من قاعدة المعرفة'), 'none' => t('مفيش منتجات في قاعدة المعرفة لسه'), 'add' => t('ضيفها من هنا'),
            'fmt' => [t('فيد فيسبوك'), t('ستوري'), t('انستجرام')], 'tpl' => t('ابدأ من قالب'),
            'tpls' => [['engagement', 'boost', t('تفاعل على منشور'), t('روّج منشور نجح لوحده عشان يوصل لناس أكتر')], ['traffic', 'new', t('زيارات للموقع'), t('إعلان بصورة ورابط يودّي على صفحة المنتج')], ['awareness', 'new', t('انتشار في منطقتك'), t('أكبر وصول بأقل تكلفة لناس في دولتك')], ['video_views', 'boost', t('مشاهدات فيديو'), t('روّج فيديو منشور عندك')]],
            'reach' => t('الظهور المتوقع'), 'reachTxt' => t('حوالي %s - %s ظهور'), 'reachBase' => t('محسوب من تكلفة الألف ظهور في حملاتك (%s). تقدير تقريبي، مش وعد.'), 'reachNone' => t('مفيش أرقام من حملاتك بالعملة دي عشان نقدّر الوصول'),
            'save' => t('احفظ كمسودة'), 'drafts' => t('المسودات'), 'del' => t('امسح'), 'delQ' => t('تمسح المسودة دي؟'), 'by' => t('%s · %s')],
          'ai' => ['copyBtn' => t('اكتب 3 نسخ بالذكاء الاصطناعي'), 'audBtn' => t('اقترح الجمهور بالذكاء الاصطناعي'), 'focus' => t('بتعلن عن إيه؟ (اختياري: منتج أو خدمة أو عرض)'),
            'wait' => t('بيكتب...'), 'waitAud' => t('بيختار الجمهور...'), 'use' => t('استخدم النسخة دي'), 'err' => t('ما قدرناش نكمل'), 'applied' => t('اتطبق الجمهور المقترح'),
            'noInt' => t('ما لقيناش الاهتمام "%s" على المنصة'), 'needAcc' => t('اختار حساب الإعلانات الأول')],
          'chk' => ['title' => t('فحص قبل المراجعة'), 'ok' => t('كله تمام'), 'go' => t('كمّل للمراجعة'), 'fix' => t('ارجع أصلّح'),
            'words' => ['الأفضل', 'الأرخص', 'أرخص', 'مضمون', '100%', 'مجاني تمامًا', 'اضغط هنا', 'best', 'cheapest', 'guaranteed', 'click here', 'free money'],
            'wordHit' => t('فيه كلمة ممكن تخلي المنصة ترفض الإعلان: "%s"'), 'personal' => t('النص بيخاطب صفة شخصية ("هل أنت...")، وده ممنوع في سياسة Meta'),
            'longBody' => t('النص طويل (%s حرف)، اللي بيبان قبل "عرض المزيد" حوالي 125 حرف بس'), 'longHead' => t('العنوان طويل (%s حرف)، ممكن يتقص بعد 40'),
            'noImg' => t('مفيش صورة، الإعلان الجديد لازم يبقى له صورة'), 'noLink' => t('مفيش رابط، هدف الزيارات محتاج رابط'), 'badLink' => t('الرابط لازم يبدأ بـ https://'),
            'caps' => t('حروف كبيرة كتير، بتبان كأنها صراخ'), 'emoji' => t('إيموجي كتير (%s)، قللهم'), 'narrow' => t('الجمهور ضيق جدًا (سن %s-%s)، ممكن الإعلان ما يوصلش'),
            'noPost' => t('ما اخترتش منشور للترويج'), 'endBefore' => t('تاريخ النهاية قبل البداية'), 'warn' => t('تنبيه'), 'block' => t('لازم يتصلح')],
          'leave' => t('فيه تعديلات مش محفوظة. تخرج من غير مراجعة؟'), 'ref' => $_SESSION['ads_budget_ref'] ?? new stdClass(), 'overWarn' => t('تحذير: ده %s ضعف الميزانية اليومية المعتادة في حملاتك (%s). اتأكد من المبلغ.')], JSON_UNESCAPED_UNICODE) ?>;</script>
        <script src="assets/ui-ad_create.js?v=<?= e(APP_VERSION) ?>"></script>
        <script>
        (function () {
          var form = document.getElementById('adcForm');
          function mode() { var m = form.querySelector('input[name=mode]:checked'); return m ? m.value : 'boost'; }
          function syncMode() { form.querySelectorAll('[data-mode]').forEach(function (el) { el.style.display = el.dataset.mode === mode() ? '' : 'none'; }); }
          form.querySelectorAll('input[name=mode]').forEach(function (r) { r.addEventListener('change', syncMode); }); syncMode();
          var target = document.getElementById('adcTarget'), postSel = document.getElementById('adcPost');
          function syncPosts() {
            var conn = target.selectedOptions[0] ? target.selectedOptions[0].dataset.conn : '';
            Array.prototype.forEach.call(postSel.options, function (o) { if (o.dataset.conn) o.hidden = o.dataset.conn !== conn; });
          }
          target.addEventListener('change', syncPosts); syncPosts();
          var hidden = document.getElementById('adcInterests'), chips = document.getElementById('adcChips');
          var list = []; try { list = JSON.parse(hidden.value) || []; } catch (e) { list = []; }
          function draw() {
            hidden.value = JSON.stringify(list); chips.innerHTML = '';
            list.forEach(function (it, i) {
              var b = document.createElement('button'); b.type = 'button'; b.className = 'badge'; b.textContent = it.name + ' ×';
              b.onclick = function () { list.splice(i, 1); draw(); }; chips.appendChild(b);
            });
          }
          draw();
          window.adcAddInterest = function (it) { if (!list.some(function (x) { return x.id === it.id; })) { list.push({ id: it.id, name: it.name }); draw(); } };
          var box = document.getElementById('adcResults'), inp = document.getElementById('adcSearch'), timer;
          inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
          inp.addEventListener('input', function () {
            clearTimeout(timer);
            var q = inp.value.trim(); if (q.length < 2) { box.classList.add('hidden'); return; }
            timer = setTimeout(function () {
              var o = target.selectedOptions[0];
              fetch('index.php?p=ad_create&ajax=1&dim=interest&q=' + encodeURIComponent(q) + '&conn=' + encodeURIComponent(o.dataset.conn) + '&acc=' + encodeURIComponent(o.dataset.acc))
                .then(function (r) { return r.json(); }).then(function (d) {
                  box.innerHTML = ''; box.classList.remove('hidden');
                  if (d.error || !d.results.length) { var p = document.createElement('div'); p.className = 'p-2 text-sm text-ink-500'; p.textContent = d.error || <?= json_encode(t('مفيش نتايج')) ?>; box.appendChild(p); return; }
                  d.results.forEach(function (r) {
                    var b = document.createElement('button'); b.type = 'button'; b.className = 'block w-full text-start p-2 text-sm hover:bg-ink-50';
                    b.textContent = r.name + (r.size ? ' · ' + Number(r.size).toLocaleString() : '') + (r.path ? ' — ' + r.path : '');
                    b.onclick = function () { if (!list.some(function (x) { return x.id === r.id; })) list.push({ id: r.id, name: r.name }); draw(); box.classList.add('hidden'); inp.value = ''; };
                    box.appendChild(b);
                  });
                });
            }, 350);
          });
        })();
        </script>
      <?php endif; ?>
    </div>
    <?php
}, ['full' => true]);
