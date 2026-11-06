<?php
$err = null;
$z = zernio_or_null($err);
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/compose_fake_z.php')) $z = require $fxd . '/compose_fake_z.php'; // اختبارات بس: مفيش نشر حقيقي
// #6: لينك → منشور. بنقرا عنوان ووصف وصورة الصفحة (og) من غير AI. محمي من الروابط الداخلية (SSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'from_link') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
    $u = trim((string)($_POST['url'] ?? ''));
    $host = (string)parse_url($u, PHP_URL_HOST);
    if (!preg_match('~^https?://~i', $u) || $host === '') $J(['ok' => false, 'error' => t('اكتب رابط يبدأ بـ https://')]);
    $ips = @gethostbynamel($host) ?: [];
    foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !getenv('UI_FIXTURES')) $J(['ok' => false, 'error' => t('الرابط ده مش متاح.')]);
    if (!$ips && !getenv('UI_FIXTURES')) $J(['ok' => false, 'error' => t('مش قادرين نوصل للرابط ده.')]);
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; LinkPreview)', CURLOPT_RANGE => '0-400000']);
    $html = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($html === '' || $code >= 400) $J(['ok' => false, 'error' => t('مش قادرين نوصل للرابط ده.')]);
    $html = mb_substr($html, 0, 400000);
    $meta = function ($names) use ($html) { foreach ($names as $n) if (preg_match('~<meta[^>]+(?:property|name)=["\']' . preg_quote($n, '~') . '["\'][^>]*content=["\']([^"\']*)~i', $html, $m) || preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . preg_quote($n, '~') . '["\']~i', $html, $m)) return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')); return ''; };
    $title = $meta(['og:title', 'twitter:title']) ?: (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m) ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')) : '');
    $desc = $meta(['og:description', 'description', 'twitter:description']);
    $img = $meta(['og:image', 'twitter:image']);
    if ($img !== '' && !preg_match('~^https?://~i', $img)) $img = '';
    if ($title === '' && $desc === '') $J(['ok' => false, 'error' => t('الصفحة دي مافيهاش عنوان أو وصف نقدر نستخدمه.')]);
    $text = trim('📌 ' . mb_substr($title, 0, 200) . "\n\n" . mb_substr($desc, 0, 600) . "\n\n👇 " . $u);
    // صورة الصفحة بتنزل مكتبة الوسائط وبتتحط في المنشور (لحد 5 ميجا، صور بس)
    $lib = null;
    if ($img !== '' && function_exists('media_store_path')) {
        try {
            $tmp = tempnam(sys_get_temp_dir(), 'og'); $fh = fopen($tmp, 'w');
            $ch = curl_init($img); curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 12, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; LinkPreview)',
                CURLOPT_NOPROGRESS => false, CURLOPT_PROGRESSFUNCTION => function ($c, $dt, $dn) { return $dn > 5 * 1024 * 1024 ? 1 : 0; }]);
            curl_exec($ch); curl_close($ch); fclose($fh);
            $mime = (string)(mime_content_type($tmp) ?: '');
            if (filesize($tmp) > 0 && preg_match('~^image/(jpeg|png|webp|gif)$~', $mime)) $lib = media_store_path((int)ws('id'), $tmp, basename((string)parse_url($img, PHP_URL_PATH)) ?: 'link.jpg', $mime, t('من لينكات'));
            @unlink($tmp);
        } catch (Throwable $e) { $lib = null; }
    }
    $J(['ok' => true, 'text' => $text, 'image' => $img, 'lib' => $lib]);
}
// #7: اكتب عن الصورة (AI بالرؤية، بيصرف كريديت بس لما تدوس)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'img_caption') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
    if (!can('ai.use') || !ai_connection()) $J(['ok' => false, 'error' => t('مالكش صلاحية استخدام الذكاء الاصطناعي.')]);
    $src = (string)($_POST['image'] ?? '');
    if (!preg_match('~^(https?://|data:image/(png|jpe?g|webp);base64,)~i', $src) || strlen($src) > 3000000) $J(['ok' => false, 'error' => t('الصورة مش مناسبة.')]);
    $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300);
    try {
        $r = ai_chat([['role' => 'system', 'content' => 'IMG_CAPTION. أنت كاتب محتوى سوشيال ميديا. اكتب منشور واحد جذاب عن الصورة دي بنفس لغة ولهجة المستخدم، 2-4 سطور، وفي آخره دعوة لاتخاذ إجراء و3 هاشتاجات. متخترعش أسعار أو معلومات مش ظاهرة.' . ai_country_note()],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => $note !== '' ? $note : 'اكتب منشور عن الصورة'], ['type' => 'image_url', 'image_url' => ['url' => $src]]]]], 500, 'compose');
        log_activity('ai.compose_vision', '1 image');
        $J(['ok' => true, 'text' => trim((string)$r['text'])]);
    } catch (Throwable $ex) { $J(['ok' => false, 'error' => $ex->getMessage()]); }
}
// #1 #5: قوالب وهاشتاجات الشركة المحفوظة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cpt_save') {
    header('Content-Type: application/json; charset=utf-8');
    $kind = ($_POST['kind'] ?? '') === 'tags' ? 'tags' : 'tpl';
    $d = json_decode((string)($_POST['data'] ?? '[]'), true); $clean = [];
    foreach (array_slice((array)$d, -30) as $x) { if (!is_array($x)) continue; $n = mb_substr(trim((string)($x['n'] ?? '')), 0, 40); $v = mb_substr(trim((string)($x[$kind === 'tags' ? 't' : 'b'] ?? '')), 0, $kind === 'tags' ? 600 : 3000); if ($n !== '' && $v !== '') $clean[] = ['n' => $n, $kind === 'tags' ? 't' : 'b' => $v]; }
    save_setting('cpt_' . $kind . '_' . (int)ws('id'), json_encode($clean, JSON_UNESCAPED_UNICODE));
    exit(json_encode(['ok' => true], JSON_UNESCAPED_UNICODE));
}
// #16: أحسن وقت لكل حساب مختار لوحده (من تفاعله هو، مش متوسط كل الحسابات)
if (($_GET['ajax'] ?? '') === 'best' && $z && function_exists('next_best_time')) {
    header('Content-Type: application/json; charset=utf-8');
    $out = [];
    foreach (array_slice(array_filter(array_map('strval', (array)($_GET['acc'] ?? []))), 0, 8) as $acc) {
        $nb = next_best_time($z, $acc);
        if ($nb) $out[] = ['acc' => $acc, 'at' => $nb->format('Y-m-d\TH:i'), 'label' => weekday_name((int)$nb->format('w')) . ' ' . $nb->format('h:i A')];
    }
    exit(json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE));
}
// #11: لينك متتبع للمنشور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'track_link') {
    header('Content-Type: application/json; charset=utf-8');
    $u = trim((string)($_POST['url'] ?? ''));
    if (!preg_match('~^https?://~i', $u)) exit(json_encode(['ok' => false, 'error' => t('اكتب رابط يبدأ بـ https://')], JSON_UNESCAPED_UNICODE));
    $lk = crm_link_make($u, mb_substr(trim((string)($_POST['label'] ?? '')), 0, 160));
    exit(json_encode(['ok' => true, 'short' => crm_link_short($lk['code'])], JSON_UNESCAPED_UNICODE));
}
// #36 + #37: رد أوتوماتيك على كلمة الدعوة (رد عام + رسالة خاصة فيها اللينك) لكل الحسابات المختارة، مرة واحدة لكل حساب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cta_auto') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
    if (!$z) $J(['ok' => false, 'error' => $err ?: t('اربط Zernio الأول.')]);
    if (!module_enabled('automations')) $J(['ok' => false, 'error' => t('القسم ده مقفول للشركة دي. صاحب الشركة يقدر يفتحه من الإعدادات.')]);
    $word = mb_substr(trim((string)($_POST['word'] ?? '')), 0, 40);
    $dm = mb_substr(trim((string)($_POST['dm'] ?? '')), 0, 900);
    $reply = mb_substr(trim((string)($_POST['reply'] ?? '')), 0, 300);
    $ids = array_slice(array_map('strval', (array)($_POST['accounts'] ?? [])), 0, 30);
    if ($word === '' || !$ids) $J(['ok' => false, 'error' => t('اختار الحسابات الأول.')]);
    try {
        $accs = []; foreach (ip_accounts($z, ip_automation_platforms()) as $a) $accs[$a['_id']] = $a;
        $existing = [];
        foreach (ip_automations($z) as $au) foreach ((array)($au['keywords'] ?? []) as $k) $existing[($au['accountId'] ?? '') . '|' . mb_strtolower((string)$k)] = 1;
        $made = 0; $skip = []; $had = 0;
        foreach ($ids as $id) {
            if (!isset($accs[$id])) { $skip[] = $id; continue; }
            if (isset($existing[$id . '|' . mb_strtolower($word)])) { $had++; continue; }
            $a = $accs[$id]; $canDm = ip_automation_dm_platform((string)$a['platform']);
            if (!$canDm && $reply === '') { $skip[] = $id; continue; }
            $pid = is_string($a['profileId'] ?? null) ? $a['profileId'] : (string)($a['profileId']['_id'] ?? '');
            $body = ['profileId' => $pid !== '' ? $pid : current_profile_id($z), 'accountId' => $id, 'trigger' => 'comment', 'name' => mb_substr(t('دعوة: %s', $word), 0, 100),
                'keywords' => [$word], 'matchMode' => 'word', 'isActive' => true];
            if ($canDm) $body['dmMessage'] = $dm !== '' ? $dm : $reply;
            if ($reply !== '') $body['commentReply'] = $reply;
            ip_create_automation($z, $body); $made++;
        }
        log_activity('automation.cta', $word . ' ' . $made);
        $J(['ok' => true, 'made' => $made, 'had' => $had, 'skip' => count($skip)]);
    } catch (ZernioException $ex) { $J(['ok' => false, 'error' => $ex->getMessage()]); }
}
$accounts = [];
$boards = [];
$apiError = null;
$profileId = null;
$validation = null;
$old = ['content' => '', 'accounts' => [], 'when' => '', 'mode' => 'schedule', 'opt' => [], 'rec' => [], 'extra' => []];
$done = $_SESSION['cp_done'] ?? null;
unset($_SESSION['cp_done']);
// تكرار منشور من صفحة المنشورات: النص والحسابات بس، والمستخدم يراجع قبل النشر
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['dup_text'])) {
    $old['content'] = mb_substr((string)$_GET['dup_text'], 0, 10000);
    $old['accounts'] = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['dup_acc'] ?? ''))), function ($x) { return preg_match('/^[\w-]{1,64}$/', $x); }));
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)($_GET['when'] ?? ''))) $old['when'] = (string)$_GET['when'];

if ($z) {
    try {
        $profileId = current_profile_id($z);
        $accounts = array_values(array_filter($z->accounts(zernio_profile()), function ($a) {
            return ($a['isActive'] ?? true) && empty($a['needsReconnection']);
        }));
        foreach ($accounts as $a) {
            if ($a['platform'] === 'pinterest') {
                try {
                    $boards[$a['_id']] = pub_pinterest_boards($z, $a['_id']);
                } catch (ZernioException $ex) {
                    $boards[$a['_id']] = [];
                }
            }
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

/** يبني إعدادات كل منصة من الفورم */
function cpro_platform_item(array $acc, array $o): array
{
    $p = $acc['platform'];
    $item = ['platform' => $p, 'accountId' => $acc['_id']];
    $custom = trim((string)($o['custom'] ?? ''));
    if ($custom !== '') $item['customContent'] = $custom;
    $d = [];
    $s = function ($k) use ($o) { return trim((string)($o[$k] ?? '')); };
    switch ($p) {
        case 'tiktok':
            $priv = in_array($s('privacy'), ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'], true) ? $s('privacy') : 'PUBLIC_TO_EVERYONE';
            $d = ['privacyLevel' => $priv, 'allowComment' => !empty($o['comment']), 'allowDuet' => !empty($o['duet']), 'allowStitch' => !empty($o['stitch']),
                  'contentPreviewConfirmed' => true, 'expressConsentGiven' => true];
            break;
        case 'youtube':
            if ($s('title') !== '') $d['title'] = mb_substr($s('title'), 0, 100);
            $d['visibility'] = in_array($s('visibility'), ['public', 'private', 'unlisted'], true) ? $s('visibility') : 'public';
            $d['madeForKids'] = !empty($o['kids']);
            if ($s('first_comment') !== '') $d['firstComment'] = $s('first_comment');
            break;
        case 'pinterest':
            if ($s('board') !== '') $d['boardId'] = $s('board');
            if ($s('title') !== '') $d['title'] = mb_substr($s('title'), 0, 100);
            if ($s('link') !== '') $d['link'] = $s('link');
            break;
        case 'instagram':
            if ($s('type') === 'story') $d['contentType'] = 'story';
            if ($s('type') === 'reel_only') $d['shareToFeed'] = false;
            if ($s('first_comment') !== '' && $s('type') !== 'story') $d['firstComment'] = $s('first_comment');
            break;
        case 'facebook':
            if (in_array($s('type'), ['story', 'reel'], true)) $d['contentType'] = $s('type');
            if ($s('first_comment') !== '' && $s('type') !== 'story') $d['firstComment'] = $s('first_comment');
            break;
        case 'linkedin':
            if ($s('first_comment') !== '') $d['firstComment'] = $s('first_comment');
            if (!empty($o['no_preview'])) $d['disableLinkPreview'] = true;
            break;
    }
    if ($d) $item['platformSpecificData'] = $d;
    return $item;
}

$allowedMime = [
    'image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'image', 'image/gif' => 'gif',
    'video/mp4' => 'video', 'video/quicktime' => 'video', 'video/webm' => 'video',
    'application/pdf' => 'document',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && !$apiError) {
    $old['content'] = trim((string)($_POST['content'] ?? ''));
    $old['accounts'] = array_map('strval', (array)($_POST['accounts'] ?? []));
    $old['when'] = (string)($_POST['when'] ?? '');
    $old['mode'] = in_array($_POST['mode'] ?? '', ['now', 'schedule', 'draft', 'queue'], true) ? $_POST['mode'] : 'schedule';
    $old['opt'] = (array)($_POST['opt'] ?? []);
    $old['rec'] = (array)($_POST['rec'] ?? []);
    $old['extra'] = array_slice(array_values(array_filter(array_map('strval', (array)($_POST['extra_when'] ?? [])), function ($x) { return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $x); })), 0, 60);
    $onlyCheck = ($_POST['action'] ?? '') === 'validate';

    $byId = [];
    foreach ($accounts as $a) $byId[$a['_id']] = $a;
    $platforms = [];
    foreach ($old['accounts'] as $id) {
        if (isset($byId[$id])) $platforms[] = cpro_platform_item($byId[$id], (array)($old['opt'][$id] ?? []));
    }

    $errors = [];
    if (!$platforms && $old['mode'] !== 'draft') $errors[] = t('اختار حساب واحد على الأقل.');

    // فحص المحتوى عند Zernio قبل أي حاجة
    if ($platforms) {
        try {
            $validation = pub_validate_post($z, ['content' => $old['content'], 'platforms' => $platforms]);
            if (empty($validation['valid'])) $errors[] = t('خدمة النشر لقت مشاكل في المنشور، راجعها تحت.');
        } catch (ZernioException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if ($onlyCheck) {
        if (!$errors) flash('success', t('المنشور سليم وجاهز.'));
        foreach ($errors as $m) flash('error', $m);
    } else {
        $media = [];
        if (!$errors && !empty($_FILES['media']['name'][0])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            foreach ($_FILES['media']['tmp_name'] as $i => $tmp) {
                $code = $_FILES['media']['error'][$i];
                if ($code === UPLOAD_ERR_NO_FILE) continue;
                if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                    $errors[] = t('فشل رفع ملف "%s".', $_FILES['media']['name'][$i]);
                    continue;
                }
                $mime = $finfo->file($tmp);
                if (!isset($allowedMime[$mime])) {
                    $errors[] = t('نوع الملف "%s" غير مدعوم.', $_FILES['media']['name'][$i]);
                    continue;
                }
                try {
                    $name = preg_replace('/[^\w.\-]+/u', '_', basename($_FILES['media']['name'][$i]));
                    $media[] = ['type' => $allowedMime[$mime], 'url' => $z->uploadFile($tmp, $name, $mime)];
                    media_capture($tmp, (string)$_FILES['media']['name'][$i], t('المنشورات'));
                } catch (ZernioException $ex) {
                    $errors[] = $ex->getMessage();
                }
            }
        }
        // صور Higgsfield: بننزلها ونرفعها لأن روابطها بتنتهي بعد 7 أيام
        // صور من مكتبة الذكاء الاصطناعي بتاعة الشركة
        foreach (array_slice(array_unique((array)($_POST['ai_media'] ?? [])), 0, 4) as $n) {
            $n = (string)$n;
            $path = hf_lib_dir((int)ws('id')) . '/' . $n;
            if ($errors || !hf_lib_valid($n) || !is_file($path)) continue;
            try {
                $mime = (string)mime_content_type($path);
                $media[] = ['type' => substr($n, -4) === '.mp4' ? 'video' : 'image', 'url' => $z->uploadFile($path, 'ai-' . $n, $mime)];
            } catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
        }
        // ملفات من مكتبة الوسائط (بتترفع لزيرنيو مرة واحدة ويتحفظ رابطها)
        foreach (array_slice(array_unique(array_map('intval', (array)($_POST['lib_media'] ?? []))), 0, 10) as $mid) {
            if ($errors || !$mid || !($mr = media_get((int)ws('id'), $mid))) continue;
            $kind = media_kind((string)$mr['mime']); if (!in_array($kind, ['image', 'video'], true) && $mr['mime'] !== 'application/pdf') continue;
            try { $media[] = ['type' => $kind === 'doc' ? 'document' : $kind, 'url' => media_public_url($z, (int)ws('id'), $mid, 'post', mb_substr((string)$old['content'], 0, 80))]; } catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
        }
        if ($old['content'] === '' && !$media) $errors[] = t('اكتب نص المنشور أو ارفع صورة/فيديو.');

        $payload = ['content' => $old['content'], 'platforms' => $platforms, 'timezone' => site_timezone()];
        if ($media) $payload['mediaItems'] = $media;
        if ($old['mode'] === 'now') {
            $payload['publishNow'] = true;
        } elseif ($old['mode'] === 'draft') {
            $payload['isDraft'] = true;
        } elseif ($old['mode'] === 'queue') {
            $payload['queuedFromProfile'] = $profileId;
        } else {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $old['when'], new DateTimeZone(site_timezone()));
            if (!$dt) {
                $errors[] = t('اختار تاريخ ووقت النشر.');
            } elseif ($dt < new DateTime('now', new DateTimeZone(site_timezone()))) {
                $errors[] = t('وقت النشر لازم يكون في المستقبل.');
            } else {
                $payload['scheduledFor'] = $dt->format('Y-m-d\TH:i:s');
            }
        }
        if (!empty($old['rec']['on'])) {
            $gap = max(1, (int)($old['rec']['gap'] ?? 1));
            $rec = ['enabled' => true, 'gap' => $gap, 'gapFreq' => ($old['rec']['freq'] ?? '') === 'week' ? 'week' : 'month'];
            if ((int)($old['rec']['count'] ?? 0) > 0) $rec['expireCount'] = (int)$old['rec']['count'];
            $payload['recycling'] = $rec;
        }

        // مواعيد إضافية لنفس المنشور (جدولة بس): لازم تبقى في المستقبل ومش مكررة
        $extraAt = [];
        if (!$errors && $old['mode'] === 'schedule') {
            $tz = new DateTimeZone(site_timezone());
            $main = $payload['scheduledFor'] ?? '';
            foreach ($old['extra'] as $x) {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $x, $tz);
                if (!$dt) continue;
                if ($dt < new DateTime('now', $tz)) { $errors[] = t('الميعاد الإضافي %s فات.', $dt->format('Y-m-d H:i')); continue; }
                $f = $dt->format('Y-m-d\TH:i:s');
                if ($f !== $main && !in_array($f, $extraAt, true)) $extraAt[] = $f;
            }
        }
        // #13: لو الموافقة مفعّلة والموظف مش مدير، المنشور بيتحفظ مسودة ومستني موافقة المدير
        if (!$errors && setting('post_approval_' . (int)ws('id'), '0') === '1' && !can('posts.manage') && $old['mode'] !== 'draft') {
            try {
                $want = $old['mode'] === 'now' ? 'now' : ($old['mode'] === 'queue' ? 'queue' : (string)($payload['scheduledFor'] ?? ''));
                $draft = $payload; unset($draft['publishNow'], $draft['scheduledFor'], $draft['queuedFromProfile'], $draft['recycling']); $draft['isDraft'] = true;
                $r = $z->createPost($draft);
                db()->prepare('INSERT INTO post_approvals (workspace_id, user_id, draft_id, payload, extra, want) VALUES (?, ?, ?, ?, ?, ?)')->execute([(int)ws('id'), (int)(current_user()['id'] ?? 0) ?: null,
                    (string)($r['post']['_id'] ?? ''), json_encode($payload, JSON_UNESCAPED_UNICODE), json_encode($extraAt), $want]);
                if (function_exists('crm_notify')) foreach (ws_approvers() as $u) crm_notify($u, null, 'approval', t('📝 منشور جديد مستني موافقتك من %s', (string)(current_user()['name'] ?? '')));
                log_activity('post.approval_request', mb_substr($old['content'], 0, 60));
                flash('success', t('اتبعت للمدير يوافق عليه. هيتنشر في الميعاد اللي اخترته بعد الموافقة.'));
                redirect(url('approvals'));
            } catch (ZernioException $ex) { $errors[] = $ex->getMessage(); }
        }
        if (!$errors) {
            if ($extraAt) @set_time_limit(60 + 5 * count($extraAt)); // كل تكرار = منشور منفصل
            try {
                $r = $z->createPost($payload);
                $newId = $r['post']['_id'] ?? '';
                $made = 1; $extraFail = [];
                // #27 A/B: نسخة B بنص تاني على نفس الحسابات بعد عدد ساعات، والنتيجة بتظهر في التحليلات
                $abText = trim((string)($_POST['ab_text'] ?? ''));
                if ($abText !== '' && in_array($old['mode'], ['schedule', 'now'], true)) {
                    try {
                        $gapH = max(1, min(168, (int)($_POST['ab_gap'] ?? 24)));
                        $baseT = $old['mode'] === 'now' ? new DateTime('now', new DateTimeZone(site_timezone())) : new DateTime((string)$payload['scheduledFor'], new DateTimeZone(site_timezone()));
                        $bp = $payload; unset($bp['publishNow'], $bp['recycling']); $bp['content'] = mb_substr($abText, 0, 10000);
                        $bp['scheduledFor'] = (clone $baseT)->modify("+$gapH hours")->format('Y-m-d\TH:i:s');
                        foreach ($bp['platforms'] as &$bpl) unset($bpl['customContent']); unset($bpl);
                        $rb = $z->createPost($bp); $made++;
                        db()->prepare('INSERT INTO post_ab (workspace_id, a_id, b_id, a_text, b_text, gap_hours) VALUES (?, ?, ?, ?, ?, ?)')->execute([(int)ws('id'), (string)$newId, (string)($rb['post']['_id'] ?? ''), mb_substr($old['content'], 0, 500), mb_substr($abText, 0, 500), $gapH]);
                    } catch (ZernioException $ex) { $extraFail[] = 'A/B: ' . $ex->getMessage(); }
                }
                foreach ($extraAt as $f) {
                    try { $z->createPost(['scheduledFor' => $f] + $payload); $made++; }
                    catch (ZernioException $ex) { $extraFail[] = substr($f, 0, 16) . ': ' . $ex->getMessage(); }
                }
                log_activity('post.create_pro', $old['mode'] . ' ' . count($platforms) . ' accounts' . ($made > 1 ? ' x' . $made : ''));
                foreach ($extraFail as $m) flash('error', t('الميعاد الإضافي ماتعملش: %s', $m));
                $_SESSION['cp_done'] = ['id' => $newId, 'mode' => $old['mode'], 'n' => $made, 'when' => $payload['scheduledFor'] ?? '', 'more' => $extraAt,
                    'text' => mb_substr($old['content'], 0, 4000), 'acc' => $old['accounts'], 'count' => count($platforms)];
                redirect(url('compose', ['done' => 1]));
            } catch (ZernioException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        foreach ($errors as $m) flash('error', $m);
    }
}

render(t('منشور جديد'), 'compose', function () use ($z, $accounts, $boards, $apiError, $old, $validation, $done) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    if (!$accounts) {
        echo '<div class="card p-6">' . e(t('مفيش حسابات مربوطة.')) . ' <a class="link" href="' . e(url('accounts')) . '">' . e(t('اربط حساب الأول')) . '</a></div>';
        return;
    }
    $defaultWhen = (new DateTime('+1 hour', new DateTimeZone(site_timezone())))->format('Y-m-d\TH:00');
    $o = function ($acc, $k, $def = '') use ($old) { return $old['opt'][$acc][$k] ?? $def; };
    $posted = $_SERVER['REQUEST_METHOD'] === 'POST';
    $chk = function ($acc, $k, $def = true) use ($old, $posted) { return ($posted ? !empty($old['opt'][$acc][$k]) : $def) ? 'checked' : ''; };
    ?>
    <?php if ($done):
        $dm = ['now' => t('اتنشر ✓'), 'draft' => t('اتحفظ كمسودة ✓'), 'schedule' => t('اتجدول ✓'), 'queue' => t('اتحط في الطابور ✓')][$done['mode']] ?? t('تم ✓');
        $times = array_filter(array_merge([$done['when']], $done['more'] ?? []));
        ?>
      <div id="cp-done" class="mb-5 rounded-2xl border border-lime-200 bg-gradient-to-br from-lime-50 to-white p-5" role="status">
        <div class="flex flex-wrap items-start gap-3">
          <span class="w-11 h-11 rounded-2xl bg-lime-500 text-white grid place-items-center text-xl shrink-0">✓</span>
          <div class="min-w-0 flex-1">
            <div class="font-bold text-ink-900 text-lg"><?= e($dm) ?><?php if ((int)$done['n'] > 1): ?> <span class="text-sm font-medium text-lime-700"><?= e(t('%s منشور', (int)$done['n'])) ?></span><?php endif; ?></div>
            <div class="text-sm text-ink-600 mt-0.5"><?= e(t('على %s حساب', (int)$done['count'])) ?><?php if ($times): ?> · <span dir="ltr"><?= e(implode('  ·  ', array_map(function ($x) { $d = DateTime::createFromFormat('Y-m-d\\TH:i:s', (string)$x); return $d ? $d->format('d/m h:i A') : (string)$x; }, array_slice($times, 0, 4)))) ?><?= count($times) > 4 ? ' …' : '' ?></span><?php endif; ?></div>
            <?php if ($done['text'] !== ''): ?><p class="text-xs text-ink-500 mt-1 line-clamp-1" dir="auto"><?= e(mb_substr($done['text'], 0, 140)) ?></p><?php endif; ?>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-4">
          <button type="button" id="cp-again" class="btn-primary !text-sm">＋ <?= e(t('منشور تاني بنفس الحسابات')) ?></button>
          <a class="btn-ghost !text-sm" href="<?= e(url('compose', ['dup_text' => $done['text'], 'dup_acc' => implode(',', $done['acc'])])) ?>"><?= e(t('كرّره بنفس النص')) ?></a>
          <?php if ($done['id']): ?><a class="btn-ghost !text-sm" href="<?= e(url('post_edit', ['id' => $done['id']])) ?>"><?= e(t('افتح المنشور')) ?></a><?php endif; ?>
          <a class="btn-ghost !text-sm" href="<?= e(url('calendar')) ?>"><?= e(t('التقويم')) ?></a>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($validation && (!empty($validation['errors']) || !empty($validation['warnings']))): ?>
      <div class="card p-4 mb-5 space-y-1 text-sm">
        <?php foreach ($validation['errors'] ?? [] as $v): ?><p class="text-red-700">✕ <b><?= e(platform_label((string)($v['platform'] ?? ''))) ?>:</b> <?= e($v['error'] ?? '') ?></p><?php endforeach; ?>
        <?php foreach ($validation['warnings'] ?? [] as $v): ?><p class="text-amber-700">! <b><?= e(platform_label((string)($v['platform'] ?? ''))) ?>:</b> <?= e($v['warning'] ?? '') ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-6">
      <?= csrf_field() ?>
      <div class="lg:col-span-2 space-y-6">
        <div class="card p-5">
          <label class="block"><span class="label"><?= e(t('نص المنشور')) ?></span>
            <textarea data-ai="from_idea,img_prompt,improve,hashtags,cta,hook,shorten,tone_formal,tone_friendly,tone_excited,tone_funny,translate_en,translate_ar,variants,three,thread,review,score" name="content" id="content" rows="7" class="input" placeholder="<?= e(t('اكتب هنا...')) ?>"><?= e($old['content']) ?></textarea></label>
          <?php if (module_enabled('crm') && can('crm.view')):
            $ctaW = crm_cta_words(); $tcfg = crm_track_cfg((int)ws('id')); $waN = preg_replace('/\D/', '', (string)$tcfg['wa']);
            $ctas = [];
            if ($ctaW) $ctas[] = ['k' => 'word', 'l' => '💬 ' . t('اكتب «%s» في الكومنت', $ctaW[0]), 'x' => t('👇 اكتب «%s» في الكومنتات وهنبعتلك التفاصيل', $ctaW[0])];
            if (setting('crm_book_on_' . (int)ws('id'), '0') === '1') $ctas[] = ['k' => 'book', 'l' => '📅 ' . t('لينك حجز ميعاد'), 'x' => t('📅 احجز مكالمة مجانية: %s', crm_book_url())];
            if (setting('crm_form_on_' . (int)ws('id'), '1') === '1') $ctas[] = ['k' => 'form', 'l' => '📝 ' . t('لينك الفورم'), 'x' => t('📝 سيب بياناتك وهنكلمك: %s', crm_form_url())];
            if ($waN !== '') $ctas[] = ['k' => 'wa', 'l' => '🟢 ' . t('لينك واتساب'), 'x' => t('🟢 كلّمنا واتساب: %s', 'https://wa.me/' . $waN)];
          ?>
          <div class="flex flex-wrap items-center gap-1.5 mt-2" id="cp-cta"><span class="text-[11px] text-ink-500">🧲 <?= e(t('دعوة للتواصل (عشان المنشور يجيب عملاء):')) ?></span>
            <?php foreach ($ctas as $ct): ?><button type="button" class="chip text-xs" data-k="<?= e($ct['k']) ?>" data-cta="<?= e($ct['x']) ?>" title="<?= e($ct['x']) ?>"><?= e($ct['l']) ?></button><?php endforeach; ?>
            <button type="button" class="chip text-xs" id="cp-track">🔗 <?= e(t('لينك متتبع')) ?></button>
            <?php if (count($ctas) < 2): ?><a class="link text-[11px]" href="<?= e(url('crm_leads')) ?>"><?= e(t('فعّل الحجز والفورم من الـ Leads ← أدوات')) ?></a><?php endif; ?>
          </div>
          <?php if ($ctaW && module_enabled('automations')):
            $link = setting('crm_book_on_' . (int)ws('id'), '0') === '1' ? crm_book_url() : crm_form_url(); ?>
          <div id="cp-auto" class="hidden mt-2 rounded-xl border border-violet-200 bg-violet-50/50 p-3 space-y-2 text-sm">
            <div class="font-medium text-ink-900">🤖 <?= e(t('رد أوتوماتيك على «%s»', $ctaW[0])) ?></div>
            <p class="text-[11px] text-ink-500"><?= e(t('أي حد يعلّق بالكلمة دي على أي منشور للحسابات المختارة: بيترد عليه رد عام، وبتوصله رسالة خاصة فيها اللينك (فيسبوك وإنستجرام)، وبيظهر في العملاء المقترحين.')) ?></p>
            <input id="cp-auto-reply" class="input !py-1.5 text-sm" maxlength="300" value="<?= e(t('بعتنالك رسالة خاصة فيها التفاصيل 📩')) ?>">
            <textarea id="cp-auto-dm" rows="2" class="input text-sm" maxlength="900" dir="auto"><?= e(t('أهلاً! دي التفاصيل اللي طلبتها 👇') . "\n" . $link) ?></textarea>
            <div class="flex items-center gap-2"><button type="button" id="cp-auto-go" class="btn-primary !py-1.5 !text-xs"><?= e(t('فعّل على الحسابات المختارة')) ?></button><span id="cp-auto-msg" class="text-xs"></span></div>
          </div>
          <?php endif; ?>
          <script>(function () { var b = document.getElementById('cp-cta'), ta = document.getElementById('content'); if (!b || !ta) return; b.addEventListener('click', function (e) { var x = e.target.closest('[data-cta]'); if (!x) return; var v = ta.value.replace(/\s+$/, ''); if (x.dataset.k === 'word') { var au = document.getElementById('cp-auto'); if (au) au.classList.remove('hidden'); } if (v.indexOf(x.dataset.cta) > -1) return; ta.value = (v ? v + '\n\n' : '') + x.dataset.cta; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); });
            var tr = document.getElementById('cp-track'); if (tr) tr.addEventListener('click', function () {
              var u = prompt(<?= json_encode(t('الرابط اللي عاوز تتابعه (موقعك، صفحة منتج، فورم…):'), JSON_UNESCAPED_UNICODE) ?>, <?= json_encode((string)setting('crm_site_url_' . (int)ws('id'), '') ?: 'https://', JSON_UNESCAPED_UNICODE) ?>); if (!u) return;
              var fd = new FormData(); fd.append('action', 'track_link'); fd.append('url', u.trim()); fd.append('label', ta.value.split('\n')[0].slice(0, 120)); fd.append('_csrf', <?= json_encode(csrf_token()) ?>);
              fetch(location.href, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (r) { if (!r.ok) return alert(r.error); var v = ta.value.replace(/\s+$/, ''); ta.value = (v ? v + '\n\n' : '') + '🔗 ' + r.short; ta.dispatchEvent(new Event('input', { bubbles: true })); });
            });
            var go = document.getElementById('cp-auto-go'); if (go) go.addEventListener('click', function () {
              var accs = Array.prototype.map.call(document.querySelectorAll('input[name="accounts[]"]:checked'), function (c) { return c.value; }), m = document.getElementById('cp-auto-msg');
              if (!accs.length) { m.className = 'text-xs text-red-600'; m.textContent = <?= json_encode(t('اختار الحسابات الأول.'), JSON_UNESCAPED_UNICODE) ?>; return; }
              var fd = new FormData(); fd.append('action', 'cta_auto'); fd.append('word', <?= json_encode($ctaW[0] ?? '', JSON_UNESCAPED_UNICODE) ?>); fd.append('reply', document.getElementById('cp-auto-reply').value); fd.append('dm', document.getElementById('cp-auto-dm').value); accs.forEach(function (a) { fd.append('accounts[]', a); }); fd.append('_csrf', <?= json_encode(csrf_token()) ?>);
              go.disabled = true;
              fetch(location.href, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (r) {
                go.disabled = false; if (!r.ok) { m.className = 'text-xs text-red-600'; m.textContent = r.error; return; }
                m.className = 'text-xs text-lime-700'; m.textContent = <?= json_encode(t('اتفعّل على %s حساب ✓'), JSON_UNESCAPED_UNICODE) ?>.replace('%s', r.made) + (r.had ? ' · ' + <?= json_encode(t('%s كان متفعّل قبل كده'), JSON_UNESCAPED_UNICODE) ?>.replace('%s', r.had) : '');
              }).catch(function () { go.disabled = false; });
            }); })();</script>
          <?php endif; ?>
          <div id="cpt-bar" class="flex flex-wrap items-center gap-1.5 mt-2">
            <button type="button" class="chip text-xs" data-tool="tpl">📋 <?= e(t('قوالب جاهزة')) ?></button>
            <button type="button" class="chip text-xs" data-tool="tags"># <?= e(t('بنك الهاشتاجات')) ?></button>
            <button type="button" class="chip text-xs" data-tool="link">🔗 <?= e(t('منشور من لينك')) ?></button>
            <button type="button" class="chip text-xs" data-tool="brand" title="<?= e(t('لوجو وعلامة مائية وإطار بلون الشركة على الصور اللي رفعتها')) ?>">🎨 <?= e(t('هوية الصورة')) ?></button>
            <button type="button" class="chip text-xs" data-tool="ab" title="<?= e(t('نسختين بنصين مختلفين، والتحليلات تقولك أنهي كسب')) ?>">🧪 A/B</button>
            <button type="button" class="chip text-xs" data-tool="ver" title="<?= e(t('بيعمل نسخة مظبوطة لكل حساب مختار: طول X، الهاشتاجات، واللينكات في إنستجرام')) ?>">✂️ <?= e(t('نسخة لكل منصة')) ?></button>
          </div>
          <div id="cpt-pane" class="hidden mt-2 rounded-2xl border border-ink-200 bg-ink-50/60 p-3"></div>
          <div id="cpt-ab" class="hidden mt-2 rounded-2xl border border-violet-200 bg-violet-50/40 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900">🧪 <?= e(t('النسخة B')) ?></div>
            <textarea name="ab_text" rows="4" class="input text-sm" dir="auto" placeholder="<?= e(t('نفس الفكرة بصياغة أو افتتاحية أو دعوة مختلفة')) ?>"></textarea>
            <label class="flex items-center gap-2 text-xs text-ink-600"><?= e(t('تتنشر بعد النسخة A بـ')) ?> <input type="number" name="ab_gap" min="1" max="168" value="24" class="input !mt-0 !py-1 !w-20 text-xs"> <?= e(t('ساعة')) ?></label>
            <p class="text-[11px] text-ink-500"><?= e(t('النتيجة (أنهي نسخة تفاعلها أعلى) بتظهر في التحليلات تحت "أفضل 10 منشورات". شغال مع الجدولة والنشر دلوقتي.')) ?></p>
          </div>
          <div id="cpt-check" class="flex flex-wrap gap-1.5 mt-2 text-[11px]"></div>
          <div class="flex flex-wrap items-center justify-between gap-2 mt-1"><div class="text-xs text-ink-500"><span id="charCount">0</span> <?= e(t('حرف · تويتر (X) بيقبل 280 حرف بس')) ?></div></div>
          <label class="block mt-4"><span class="label"><?= e(t('صور أو فيديو (اختياري)')) ?></span>
            <input type="file" name="media[]" id="mediaInput" multiple accept="image/*,video/*,application/pdf" class="input"></label>
          <div id="mediaPreview" class="flex flex-wrap gap-2 mt-3"></div>
          <div class="mt-2"><button type="button" id="libPick" class="inline-flex items-center gap-1.5 rounded-xl border border-ink-200 bg-white px-3 py-1.5 text-xs font-medium text-ink-700 hover:bg-ink-50">📁 <?= e(t('اختار من مكتبة الوسائط')) ?></button></div>
          <div id="libMedia" class="flex flex-wrap gap-2 mt-2"></div>
          <script>
          (function () {
            var box = document.getElementById('libMedia'), btn = document.getElementById('libPick'); if (!btn || !window.mediaPick) return;
            var form = btn.closest('form');
            function add(f) {
              if (form.querySelector('input[name="lib_media[]"][value="' + f.id + '"]')) return;
              var w = document.createElement('div'); w.className = 'relative w-20 h-20 rounded-xl overflow-hidden border border-ink-200 bg-ink-100';
              w.innerHTML = (f.kind === 'video' ? '<video muted preload="metadata" class="w-full h-full object-cover"></video>' : '<img alt="" class="w-full h-full object-cover">') + '<input type="hidden" name="lib_media[]"><button type="button" class="absolute top-1 end-1 w-5 h-5 rounded-full bg-ink-900/70 text-white text-xs leading-5" aria-label="✕">×</button>';
              w.querySelector('img, video').src = f.kind === 'video' ? f.url + '#t=0.5' : f.thumb || f.url; w.querySelector('input').value = f.id; w.title = f.name;
              w.querySelector('button').onclick = function () { w.remove(); }; box.appendChild(w);
            }
            btn.onclick = function () { window.mediaPick({ accept: 'image,video', multiple: true, public: false }).then(function (fs) { (fs || []).forEach(add); }); };
            window.cpAddLib = add;
          })();
          </script>
          <?php if (ai_connection() && can('ai.use') && ai_feature_on('vision')): ?>
          <div class="flex flex-wrap items-center gap-2 mt-2" id="vis-box">
            <?php foreach (['from_image', 'alt_text'] as $vk): if (!ai_feature_on($vk)) continue; ?>
              <button type="button" data-vis="<?= $vk ?>" class="inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 disabled:opacity-60"><?= ai_icon('spark', 'w-3.5 h-3.5') ?> <?= e(ai_presets()[$vk]['label']) ?></button>
            <?php endforeach; ?>
            <span class="text-[11px] text-ink-400"><?= e(t('يستخدم أول صورة مختارة. يحتاج موديلًا يدعم الصور.')) ?></span>
          </div>
          <script>
          (function () {
            var box = document.getElementById('vis-box'); if (!box) return;
            function shrink(file) {
              return new Promise(function (ok, bad) {
                var img = new Image(), url = URL.createObjectURL(file);
                img.onload = function () { var k = Math.min(1, 1024 / Math.max(img.width, img.height)), c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url); ok(c.toDataURL('image/jpeg', 0.85)); };
                img.onerror = bad; img.src = url;
              });
            }
            box.addEventListener('click', function (e) {
              var b = e.target.closest('[data-vis]'); if (!b) return;
              var f = [].slice.call(document.getElementById('mediaInput').files || []).filter(function (x) { return /^image\//.test(x.type); })[0];
              if (!f) { (window.aiToast || alert)(<?= json_encode(t('اختر صورة أولًا (أقل من 2 ميجا).'), JSON_UNESCAPED_UNICODE) ?>); return; }
              b.disabled = true; b.classList.add('animate-pulse');
              shrink(f).then(function (data) {
                var fd = new FormData(), c = document.getElementById('content'); fd.append('task', 'preset'); fd.append('preset', b.dataset.vis); fd.append('text', c.value); fd.append('image', data);
                return fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content } }).then(function (r) { return r.json(); }).then(function (d) {
                  if (d.error) { (window.aiToast || alert)(d.error); return; }
                  if (d.mode === 'show') { var p = box.querySelector('.vis-out') || document.createElement('div'); p.className = 'vis-out w-full rounded-xl border border-brand-200 bg-brand-50/60 p-3 text-sm'; p.textContent = d.text; box.appendChild(p); }
                  else { c.value = d.text; c.dispatchEvent(new Event('input', { bubbles: true })); c.focus(); }
                });
              }).catch(function () { (window.aiToast || alert)((window.T && T.liveFailed) || 'Error'); }).finally(function () { b.disabled = false; b.classList.remove('animate-pulse'); });
            });
          })();
          </script>
          <?php endif; ?>
          <?php if (connector('higgsfield') && ai_feature_on('image')): ?>
          <div class="mt-4 rounded-2xl border border-dashed border-ink-300 p-4" id="hf-box">
            <div class="flex items-center gap-2 mb-2"><span class="w-6 h-6 rounded-lg bg-[#0B0B0F] text-[#fff] text-xs font-semibold flex items-center justify-center">H</span><b class="text-sm text-ink-900"><?= e(t('توليد صورة بالذكاء الاصطناعي')) ?></b></div>
            <div class="flex flex-wrap items-center gap-2 mb-2 text-xs">
<?php if (ai_feature_on('video')): ?><select id="hf-kind" class="input !mt-0 !w-auto !py-1.5 text-xs"><option value="image"><?= e(t('صورة')) ?></option><option value="video"><?= e(t('فيديو (أغلى)')) ?></option></select><?php endif; ?>
              <select id="hf-aspect" class="input !mt-0 !w-auto !py-1.5 text-xs"><option value=""><?= e(t('المقاس الافتراضي')) ?></option><option value="1:1"><?= e(t('مربع 1:1')) ?></option><option value="4:5"><?= e(t('بوست 4:5')) ?></option><option value="9:16"><?= e(t('ستوري 9:16')) ?></option><option value="16:9"><?= e(t('عرضي 16:9')) ?></option></select>
              <label class="inline-flex items-center gap-1.5 text-ink-600 cursor-pointer"><input type="checkbox" id="hf-two" class="w-3.5 h-3.5"> <?= e(t('نسختين')) ?></label>
              <button type="button" id="hf-from-text" class="ms-auto link"><?= e(t('اكتب الوصف من نص المنشور')) ?></button>
            </div>
            <div class="flex gap-2 items-start">
              <textarea id="hf-prompt" rows="2" class="input !mt-0 flex-1 text-sm" placeholder="<?= e(t('صف الصورة: مثلاً كوب قهوة على طاولة خشب بإضاءة صباحية...')) ?>"></textarea>
              <button type="button" id="hf-go" class="btn-primary shrink-0"><?= e(t('توليد')) ?></button>
            </div>
            <p id="hf-status" class="text-xs text-ink-500 mt-2 hidden"></p>
            <div id="hf-results" class="grid grid-cols-3 sm:grid-cols-6 gap-2 mt-3">
              <?php foreach (hf_lib_list((int)ws('id'), 12) as $n): $isV = substr($n, -4) === '.mp4'; ?><div class="relative group"><button type="button" class="hf-img relative w-full rounded-xl overflow-hidden border-2 border-transparent hover:border-brand-400 transition aspect-square bg-ink-100" data-name="<?= e($n) ?>"><?php if ($isV): ?><video muted preload="metadata" class="w-full h-full object-cover" src="<?= e(url('ai_task', ['img' => $n])) ?>"></video><span class="absolute bottom-1 start-1 text-[10px] bg-black/70 text-[#fff] rounded px-1">▶</span><?php else: ?><img loading="lazy" class="w-full h-full object-cover" src="<?= e(url('ai_task', ['img' => $n])) ?>" alt=""><?php endif; ?><span class="absolute top-1.5 end-1.5 hidden w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs items-center justify-center">✓</span></button><?php if (!$isV && ai_feature_on('video') && filemtime(hf_lib_dir((int)ws('id')) . '/' . $n) > time() - 6.5 * 86400): ?><button type="button" class="hf-tovideo absolute bottom-1 end-1 hidden group-hover:flex rounded-lg bg-black/70 text-[#fff] text-[10px] px-1.5 py-0.5" data-src="<?= e($n) ?>" title="<?= e(t('تحويل لفيديو')) ?>"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 6h11v12H4zM15 10l5-3v10l-5-3"/></svg></button><?php endif; ?></div><?php endforeach; ?>
            </div>
            <p class="text-[11px] text-ink-400 mt-2"><?= e(t('اضغط على الصورة لإضافتها للمنشور. التوليد يستهلك من رصيد Higgsfield.')) ?></p>
          </div>
          <script>
          (function () {
            var go = document.getElementById('hf-go'), pr = document.getElementById('hf-prompt'), st = document.getElementById('hf-status'), res = document.getElementById('hf-results'), form = go.closest('form');
            var csrf = document.querySelector('meta[name=csrf-token]').content, label = go.textContent, pending = 0;
            function call(data) { var fd = new FormData(); for (var k in data) fd.append(k, data[k]); return fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } }).then(function (r) { return r.json(); }); }
            function msg(t, err) { st.textContent = t; st.classList.remove('hidden'); st.classList.toggle('text-red-600', !!err); }
            function done() { if (--pending <= 0) { pending = 0; go.disabled = false; go.textContent = label; } }
            function toggle(b) {
              var on = !b.dataset.on; b.dataset.on = on ? '1' : ''; var n = b.dataset.name;
              b.classList.toggle('border-brand-600', on); b.lastChild.classList.toggle('hidden', !on); b.lastChild.classList.toggle('flex', on);
              var inp = form.querySelector('input[name="ai_media[]"][value="' + CSS.escape(n) + '"]');
              if (on && !inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'ai_media[]'; inp.value = n; form.appendChild(inp); }
              if (!on && inp) inp.remove();
            }
            res.addEventListener('click', function (e) {
              var v = e.target.closest('.hf-tovideo');
              if (v) { if (!pr.value.trim()) { pr.placeholder = <?= json_encode(t('صف الحركة: مثلاً الكاميرا تقترب ببطء والبخار يتصاعد'), JSON_UNESCAPED_UNICODE) ?>; pr.focus(); return; } start({ task: 'image', kind: 'video', text: pr.value, src: v.dataset.src }); return; }
              var b = e.target.closest('.hf-img'); if (b) toggle(b);
            });
            function add(img) {
              var b = document.createElement('button'); b.type = 'button'; b.dataset.name = img.name;
              b.className = 'hf-img relative rounded-xl overflow-hidden border-2 border-transparent hover:border-brand-400 transition aspect-square bg-ink-100';
              b.innerHTML = (img.video ? '<video muted preload="metadata" class="w-full h-full object-cover"></video>' : '<img class="w-full h-full object-cover" alt="">') + '<span class="absolute top-1.5 end-1.5 hidden w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs items-center justify-center">✓</span>';
              b.firstChild.src = img.src; res.prepend(b); toggle(b);
            }
            function poll(id, tries) {
              call({ task: 'image_status', id: id }).then(function (s) {
                if (s.error) { msg(s.error, true); done(); return; }
                if (s.status === 'completed') { (s.images || []).forEach(add); if (pending <= 1) st.classList.add('hidden'); done(); return; }
                if (tries > 60) { msg(<?= json_encode(t('التوليد أخذ وقتًا أطول من المعتاد. جرّب مرة أخرى.'), JSON_UNESCAPED_UNICODE) ?>, true); done(); return; }
                setTimeout(function () { poll(id, tries + 1); }, 3000);
              }).catch(function () { setTimeout(function () { poll(id, tries + 1); }, 5000); });
            }
            go.addEventListener('click', function () {
              if (!pr.value.trim()) { pr.focus(); return; }
              go.disabled = true; go.textContent = (window.T && T.working) || '...'; msg(<?= json_encode(t('جاري التوليد... غالبًا يأخذ من 10 إلى 60 ثانية.'), JSON_UNESCAPED_UNICODE) ?>);
              var kind = document.getElementById('hf-kind');
              start({ task: 'image', kind: kind ? kind.value : 'image', text: pr.value, aspect: document.getElementById('hf-aspect').value, count: document.getElementById('hf-two').checked ? 2 : 1 });
            });
            function start(data) {
              go.disabled = true; go.textContent = (window.T && T.working) || '...'; msg(data.kind === 'video' ? <?= json_encode(t('جاري توليد الفيديو... قد يأخذ عدة دقائق.'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(t('جاري التوليد... غالبًا يأخذ من 10 إلى 60 ثانية.'), JSON_UNESCAPED_UNICODE) ?>);
              call(data).then(function (d) {
                if (d.error) { msg(d.error, true); go.disabled = false; go.textContent = label; return; }
                var ids = d.ids || [d.id]; pending = ids.length; ids.forEach(function (id) { poll(id, data.kind === 'video' ? -140 : 0); });
              }).catch(function () { msg((window.T && T.liveFailed) || 'Error', true); go.disabled = false; go.textContent = label; });
            }
            document.getElementById('hf-from-text').addEventListener('click', function () {
              var c = document.getElementById('content'); if (!c || !c.value.trim()) { c && c.focus(); return; }
              var b = this; b.classList.add('animate-pulse');
              call({ task: 'preset', preset: 'img_prompt', text: c.value }).then(function (d) { if (d.error) msg(d.error, true); else { pr.value = d.text; pr.focus(); } }).finally(function () { b.classList.remove('animate-pulse'); });
            });
          })();
          </script>
          <?php endif; ?>
          <p class="text-xs text-ink-500 mt-1"><?= e(t('أقصى حجم للملف على السيرفر بتاعك:')) ?> <?= e(ini_get('upload_max_filesize')) ?></p>
        </div>

        <div class="space-y-4" id="platformPanels">
          <p class="text-sm text-ink-500" id="noPanels"><?= e(t('اختار الحسابات اللي هتنشر عليها، وتقدر تخصص النص والإعدادات لكل حساب هنا.')) ?></p>
          <?php foreach ($accounts as $a): $id = $a['_id']; $p = $a['platform']; $n = 'opt[' . $id . ']'; ?>
            <details class="card hidden panel group" data-acc="<?= e($id) ?>" <?= ($o($id, 'custom') !== '' || $o($id, 'first_comment') !== '' || $o($id, 'title') !== '') ? 'open' : '' ?>>
              <summary class="flex items-center gap-2 p-4 cursor-pointer select-none">
                <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? '', 'w-7 h-7') ?>
                <b class="text-sm"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></b>
                <span class="badge"><?= e(platform_label($p)) ?></span>
                <span class="ms-auto text-xs text-ink-500"><?= e(t('تخصيص (اختياري)')) ?></span>
                <svg class="w-4 h-4 text-ink-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
              </summary>
              <div class="px-5 pb-5 space-y-3 border-t border-ink-100 pt-4">
              <label class="block"><span class="label"><?= e(t('نص خاص بالحساب ده (اختياري)')) ?></span>
                <textarea data-ai="improve,shorten,hashtags,translate_en,translate_ar" name="<?= e($n) ?>[custom]" rows="3" class="input" placeholder="<?= e(t('سيبه فاضي عشان يستخدم النص الأساسي')) ?>"><?= e($o($id, 'custom')) ?></textarea></label>

              <?php if ($p === 'tiktok'): ?>
                <label class="block"><span class="label"><?= e(t('مين يشوف الفيديو؟')) ?></span>
                  <select name="<?= e($n) ?>[privacy]" class="input">
                    <?php foreach (['PUBLIC_TO_EVERYONE' => t('الكل'), 'MUTUAL_FOLLOW_FRIENDS' => t('الأصحاب بس'), 'FOLLOWER_OF_CREATOR' => t('المتابعين بس'), 'SELF_ONLY' => t('أنا بس')] as $k => $l): ?>
                      <option value="<?= $k ?>" <?= $o($id, 'privacy') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <div class="flex flex-wrap gap-4 text-sm">
                  <label class="flex items-center gap-2"><input type="checkbox" name="<?= e($n) ?>[comment]" value="1" <?= $chk($id, 'comment') ?>> <?= e(t('اسمح بالتعليقات')) ?></label>
                  <label class="flex items-center gap-2"><input type="checkbox" name="<?= e($n) ?>[duet]" value="1" <?= $chk($id, 'duet') ?>> <?= e(t('اسمح بالـ Duet')) ?></label>
                  <label class="flex items-center gap-2"><input type="checkbox" name="<?= e($n) ?>[stitch]" value="1" <?= $chk($id, 'stitch') ?>> <?= e(t('اسمح بالـ Stitch')) ?></label>
                </div>
              <?php elseif ($p === 'youtube'): ?>
                <label class="block"><span class="label"><?= e(t('عنوان الفيديو')) ?></span>
                  <input name="<?= e($n) ?>[title]" maxlength="100" class="input" value="<?= e($o($id, 'title')) ?>"></label>
                <label class="block"><span class="label"><?= e(t('مين يشوف الفيديو؟')) ?></span>
                  <select name="<?= e($n) ?>[visibility]" class="input">
                    <?php foreach (['public' => t('عام للكل'), 'unlisted' => t('باللينك بس'), 'private' => t('خاص')] as $k => $l): ?>
                      <option value="<?= $k ?>" <?= $o($id, 'visibility') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="<?= e($n) ?>[kids]" value="1" <?= $chk($id, 'kids', false) ?>> <?= e(t('الفيديو معمول للأطفال')) ?></label>
                <label class="block"><span class="label"><?= e(t('أول تعليق (اختياري)')) ?></span>
                  <input name="<?= e($n) ?>[first_comment]" class="input" value="<?= e($o($id, 'first_comment')) ?>"></label>
              <?php elseif ($p === 'pinterest'): ?>
                <label class="block"><span class="label"><?= e(t('اللوحة (Board)')) ?></span>
                  <select name="<?= e($n) ?>[board]" class="input">
                    <option value=""><?= e(t('أول لوحة (تلقائي)')) ?></option>
                    <?php foreach ($boards[$id] ?? [] as $b): ?>
                      <option value="<?= e($b['id']) ?>" <?= $o($id, 'board') === $b['id'] ? 'selected' : '' ?>><?= e($b['name'] ?? $b['id']) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <label class="block"><span class="label"><?= e(t('عنوان الـ Pin')) ?></span>
                  <input name="<?= e($n) ?>[title]" maxlength="100" class="input" value="<?= e($o($id, 'title')) ?>"></label>
                <label class="block"><span class="label"><?= e(t('رابط الـ Pin (اختياري)')) ?></span>
                  <input name="<?= e($n) ?>[link]" type="url" class="input" dir="ltr" value="<?= e($o($id, 'link')) ?>"></label>
              <?php elseif ($p === 'instagram' || $p === 'facebook'): ?>
                <label class="block"><span class="label"><?= e(t('نوع المنشور')) ?></span>
                  <select name="<?= e($n) ?>[type]" class="input">
                    <?php $types = $p === 'instagram'
                        ? ['' => t('منشور عادي أو Reel (حسب الميديا)'), 'reel_only' => t('Reel في تبويب الريلز بس'), 'story' => t('ستوري (24 ساعة)')]
                        : ['' => t('منشور عادي'), 'reel' => t('Reel'), 'story' => t('ستوري (24 ساعة)')];
                    foreach ($types as $k => $l): ?>
                      <option value="<?= $k ?>" <?= $o($id, 'type') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <label class="block"><span class="label"><?= e(t('أول تعليق (اختياري، مش للستوري)')) ?></span>
                  <input name="<?= e($n) ?>[first_comment]" class="input" value="<?= e($o($id, 'first_comment')) ?>"></label>
              <?php elseif ($p === 'linkedin'): ?>
                <label class="block"><span class="label"><?= e(t('أول تعليق (اختياري)')) ?></span>
                  <input name="<?= e($n) ?>[first_comment]" class="input" value="<?= e($o($id, 'first_comment')) ?>"></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="<?= e($n) ?>[no_preview]" value="1" <?= $chk($id, 'no_preview', false) ?>> <?= e(t('متعرضش معاينة للروابط')) ?></label>
              <?php endif; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="space-y-6">
        <div class="card p-5">
          <div class="flex items-center justify-between mb-3"><span class="font-bold"><?= e(t('انشر على')) ?></span>
            <button type="button" id="selectAll" class="text-xs link"><?= e(t('اختار الكل')) ?></button></div>
          <div class="space-y-2 max-h-80 overflow-auto">
            <?php foreach ($accounts as $a): ?>
              <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-ink-50 cursor-pointer">
                <input type="checkbox" name="accounts[]" value="<?= e($a['_id']) ?>" <?= in_array($a['_id'], $old['accounts'], true) ? 'checked' : '' ?> class="acc w-4 h-4" data-plat="<?= e($a['platform'] ?? '') ?>" data-name="<?= e($a['displayName'] ?? $a['username'] ?? '') ?>" data-user="<?= e($a['username'] ?? '') ?>" data-pic="<?= e($a['profilePicture'] ?? '') ?>">
                <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? $a['username'] ?? '', 'w-8 h-8') ?>
                <span class="text-sm leading-tight"><span class="block font-semibold"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></span>
                  <span class="block text-xs text-ink-500"><?= e(platform_label($a['platform'])) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card p-5 space-y-3">
          <span class="font-bold block"><?= e(t('إمتى؟')) ?></span>
          <?php foreach (['schedule' => t('جدولة لوقت محدد'), 'queue' => t('ضيف للطابور (أقرب ميعاد فاضي)'), 'now' => t('انشر دلوقتي'), 'draft' => t('احفظ كمسودة')] as $k => $l): ?>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="<?= $k ?>" <?= $old['mode'] === $k ? 'checked' : '' ?> class="mode"> <?= e($l) ?></label>
          <?php endforeach; ?>
          <div id="whenBox">
            <input type="datetime-local" name="when" id="whenInput" value="<?= e($old['when'] ?: $defaultWhen) ?>" class="input" dir="ltr">
            <p class="text-xs text-ink-500 mt-1"><?= e(t('بتوقيت:')) ?> <?= e(site_timezone()) ?></p>
            <div id="cp-extra" class="mt-3 space-y-1.5">
              <?php foreach ($old['extra'] as $x): ?><div class="cp-x flex items-center gap-1.5"><input type="datetime-local" name="extra_when[]" value="<?= e($x) ?>" class="input !mt-0 !h-9 text-sm" dir="ltr"><button type="button" class="cp-x-del w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" aria-label="<?= e(t('حذف')) ?>">✕</button></div><?php endforeach; ?>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 mt-2 text-[11px]">
              <span class="text-ink-500"><?= e(t('انشره كمان:')) ?></span>
              <button type="button" class="cp-x-add rounded-full border border-dashed border-brand-300 px-2.5 py-1 text-brand-700 hover:bg-brand-50" data-add="custom">＋ <?= e(t('ميعاد تاني')) ?></button>
              <button type="button" class="cp-x-add rounded-full border border-ink-200 px-2.5 py-1 text-ink-700 hover:border-brand-300" data-add="d1"><?= e(t('بعد يوم')) ?></button>
              <button type="button" class="cp-x-add rounded-full border border-ink-200 px-2.5 py-1 text-ink-700 hover:border-brand-300" data-add="d7"><?= e(t('بعد أسبوع')) ?></button>
              <button type="button" class="cp-x-add rounded-full border border-ink-200 px-2.5 py-1 text-ink-700 hover:border-brand-300" data-add="w4"><?= e(t('كل أسبوع لمدة شهر')) ?></button>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-1.5 rounded-xl bg-ink-50 border border-ink-200 p-2 text-xs" id="cp-rep">
              <span class="font-medium text-ink-700">🔁 <?= e(t('كرّره')) ?></span>
              <select id="cp-rep-k" class="input !mt-0 !h-8 !py-0 text-xs !w-auto"><option value="d"><?= e(t('كل كام يوم')) ?></option><option value="w"><?= e(t('كل أسبوع في نفس اليوم')) ?></option><option value="m"><?= e(t('كل شهر في نفس التاريخ')) ?></option><option value="mf"><?= e(t('أول يوم في كل شهر')) ?></option></select>
              <input type="number" id="cp-rep-n" min="1" max="365" value="3" class="input !mt-0 !h-8 !py-0 text-xs !w-16">
              <span id="cp-rep-u"><?= e(t('يوم،')) ?></span>
              <input type="number" id="cp-rep-c" min="1" max="60" value="10" class="input !mt-0 !h-8 !py-0 text-xs !w-16">
              <span><?= e(t('مرة')) ?></span>
              <button type="button" id="cp-rep-go" class="btn-primary !py-1 !text-xs"><?= e(t('طبّق')) ?></button>
              <span class="w-full text-[11px] text-ink-500"><?= e(t('كل مرة بتتعمل منشور منفصل في نفس الساعة، لحد 60 مرة. مش مربوط بحد Zernio لإعادة النشر.')) ?></span>
            </div>
            <?php $nb = (module_enabled('analytics') && function_exists('next_best_time')) ? next_best_time($z) : null; if ($nb): ?>
              <button type="button" class="mt-2 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-lime-300 bg-lime-50 text-lime-700 px-3 py-2 text-sm hover:bg-lime-100 transition"
                onclick="document.getElementById('whenInput').value='<?= e($nb->format('Y-m-d\TH:i')) ?>'">
                <?= ui_icon('zap', 'w-4 h-4 text-amber-600 inline') ?> <?= e(t('انشر في أحسن وقت: %s', weekday_name((int)$nb->format('w')) . ' ' . $nb->format('h:i A'))) ?>
              </button>
              <p class="text-[11px] text-ink-400 mt-1"><?= e(t('محسوب من تفاعل منشوراتك اللي فاتت.')) ?></p>
            <?php endif; ?>
            <?php if (module_enabled('analytics') && function_exists('next_best_time')): ?>
            <div id="cp-best" class="hidden mt-2 rounded-xl border border-lime-200 bg-lime-50/50 p-2 space-y-1 text-xs"><div class="text-[11px] text-ink-500">⚡ <?= e(t('أحسن وقت لكل حساب لوحده:')) ?></div><div id="cp-best-l" class="flex flex-wrap gap-1.5"></div></div>
            <script>(function () {
              var box = document.getElementById('cp-best'), lst = document.getElementById('cp-best-l'), tmr = null, names = {};
              document.querySelectorAll('input[name="accounts[]"]').forEach(function (c) { var l = c.closest('label'); var ls = l ? l.innerText.split('\n').map(function (x) { return x.trim(); }).filter(function (x) { return x.length > 2; }) : []; names[c.value] = (c.dataset.plat ? c.dataset.plat.charAt(0).toUpperCase() + c.dataset.plat.slice(1) + ' · ' : '') + (ls[0] || '').slice(0, 24); });
              function load() {
                var acc = Array.prototype.map.call(document.querySelectorAll('input[name="accounts[]"]:checked'), function (c) { return c.value; });
                if (acc.length < 1) { box.classList.add('hidden'); return; }
                var q = acc.map(function (a) { return 'acc[]=' + encodeURIComponent(a); }).join('&');
                fetch(location.pathname + '?p=compose&ajax=best&' + q).then(function (r) { return r.json(); }).then(function (r) {
                  if (!r.ok || !r.items.length) { box.classList.add('hidden'); return; }
                  lst.innerHTML = r.items.map(function (it) { return '<button type="button" class="chip text-xs" data-at="' + it.at + '" title="' + it.at.replace('T', ' ') + '">' + (names[it.acc] || '') + ': <b>' + it.label + '</b></button>'; }).join('');
                  box.classList.remove('hidden');
                }).catch(function () {});
              }
              lst.addEventListener('click', function (e) { var b = e.target.closest('[data-at]'); if (!b) return; var w = document.getElementById('whenInput'); if (w) { w.value = b.dataset.at; w.dispatchEvent(new Event('change', { bubbles: true })); } });
              document.addEventListener('change', function (e) { if (e.target.name === 'accounts[]') { clearTimeout(tmr); tmr = setTimeout(load, 400); } });
              load();
            })();</script>
            <?php endif; ?>
          </div>
          <p class="text-xs text-ink-500"><?= e(t('الطابور: مواعيد ثابتة في الأسبوع، والمنشور بياخد أقرب ميعاد فاضي.')) ?>
            <a class="link" href="<?= e(url('queue')) ?>" data-modal="<?= e(url('queue')) ?>" data-modal-title="<?= e(t('مواعيد الطابور')) ?>"><?= e(t('إدارة المواعيد')) ?></a></p>
        </div>

        <details class="card group" <?= !empty($old['rec']['on']) ? 'open' : '' ?>>
          <summary class="flex items-center justify-between p-5 cursor-pointer select-none font-bold"><?= e(t('خيارات إضافية')) ?>
            <svg class="w-4 h-4 text-ink-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></summary>
          <div class="px-5 pb-5 space-y-3">
          <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="rec[on]" value="1" <?= !empty($old['rec']['on']) ? 'checked' : '' ?>> <?= e(t('إعادة النشر تلقائياً')) ?></label>
          <p class="text-xs text-ink-500"><?= e(t('بعد النشر، المنشور هيتنشر تاني كل فترة. مش متاح ليوتيوب وتيك توك.')) ?></p>
          <div class="flex gap-2 items-center text-sm">
            <span><?= e(t('كل')) ?></span>
            <input type="number" min="1" name="rec[gap]" value="<?= e($old['rec']['gap'] ?? '1') ?>" class="input !mt-0 w-20">
            <select name="rec[freq]" class="input !mt-0 w-28">
              <option value="week" <?= ($old['rec']['freq'] ?? '') === 'week' ? 'selected' : '' ?>><?= e(t('أسبوع')) ?></option>
              <option value="month" <?= ($old['rec']['freq'] ?? 'month') === 'month' ? 'selected' : '' ?>><?= e(t('شهر')) ?></option>
            </select>
          </div>
          <label class="block text-sm"><?= e(t('يقف بعد كام مرة؟ (فاضي = مفيش حد)')) ?>
            <input type="number" min="1" name="rec[count]" value="<?= e($old['rec']['count'] ?? '') ?>" class="input"></label>
          </div>
        </details>

        <p class="text-xs text-ink-500"><?= e(t('قبل التنفيذ بنفحص المنشور عند خدمة النشر، ولو فيه مشكلة مش هيتنشر.')) ?></p>
      </div>

      <div id="cp-bar" class="lg:col-span-3 sticky bottom-3 z-30">
        <div class="rounded-2xl border border-ink-200 bg-white/95 backdrop-blur shadow-xl px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
          <ol class="flex items-center gap-1.5 text-xs" id="cp-steps" aria-label="<?= e(t('الخطوات')) ?>">
            <li><button type="button" data-go="acc" class="cp-st inline-flex items-center gap-1 rounded-full border px-2.5 py-1"><span class="cp-ic">1</span><?= e(t('الحسابات')) ?></button></li>
            <li><button type="button" data-go="txt" class="cp-st inline-flex items-center gap-1 rounded-full border px-2.5 py-1"><span class="cp-ic">2</span><?= e(t('المحتوى')) ?></button></li>
            <li><button type="button" data-go="when" class="cp-st inline-flex items-center gap-1 rounded-full border px-2.5 py-1"><span class="cp-ic">3</span><?= e(t('الميعاد')) ?></button></li>
          </ol>
          <div id="cp-sum" class="min-w-0 flex-1 text-xs text-ink-600"></div>
          <span id="cp-draft" class="text-[11px] text-ink-400"></span>
          <div class="flex items-center gap-2 ms-auto">
            <button type="submit" name="action" value="validate" class="btn-ghost !text-sm"><?= e(t('افحص بس')) ?></button>
            <button type="submit" name="action" value="submit" id="submitBtn" class="btn-primary !text-sm !px-5"><span id="cp-go-t"><?= e(t('تنفيذ')) ?></span> <kbd class="hidden sm:inline text-[10px] opacity-70">Ctrl ↵</kbd></button>
          </div>
        </div>
      </div>
    </form>
    <?php $cpPlats = array_values(array_unique(array_map(function ($a) { return (string)($a['platform'] ?? ''); }, $accounts))); ?>
    <script>window.CPX = <?= json_encode(['ws' => (int)ws('id'),
      'icons' => array_combine($cpPlats, array_map(function ($p) { return platform_icon($p, 'w-3.5 h-3.5'); }, $cpPlats)),
      'plat' => array_combine($cpPlats, array_map('platform_label', $cpPlats)),
      'tooLong' => t('أطول من المسموح بـ %s حرف'), 'tags' => t('%s هاشتاج'), 'emoji' => t('إيموجي'), 'hashtag' => t('هاشتاج'), 'line' => t('سطر جديد'),
      'searchAcc' => t('ابحث في الحسابات...'), 'remove' => t('حذف'), 'drop' => t('اسحب الصور أو الفيديو هنا، أو اضغط للاختيار'), 'dropSub' => t('تقدر كمان تلصق صورة في خانة النص (Ctrl+V)'),
      'xMax' => t('X بيقبل 4 صور بس'), 'needVideo' => t('تيك توك ويوتيوب محتاجين فيديو'), 'igMedia' => t('إنستجرام محتاج صورة أو فيديو'),
      'preview' => t('معاينة'), 'pickAcc' => t('اختار حساب عشان تشوف المعاينة'), 'more' => t('المزيد'), 'emptyText' => t('اكتب نص المنشور أو ضيف صورة'), 'like' => t('إعجاب'), 'comment' => t('تعليق'), 'share' => t('مشاركة'), 'customText' => t('الحساب ده بيستخدم نص خاص بيه'),
      'modes' => ['now' => t('هيتنشر دلوقتي'), 'draft' => t('هيتحفظ كمسودة'), 'queue' => t('أقرب ميعاد فاضي في الطابور'), 'schedule' => t('مجدول')],
      'past' => t('الميعاد ده فات'), 'inMin' => t('بعد %s دقيقة'), 'inHr' => t('بعد %s ساعة'), 'inDay' => t('بعد %s يوم'),
      'quick' => [[t('بعد ساعة'), 'h1'], [t('بعد 3 ساعات'), 'h3'], [t('النهارده 8 م'), '0:20'], [t('بكرة 10 ص'), '1:10'], [t('بكرة 8 م'), '1:20']],
      'goT' => ['now' => t('انشر دلوقتي'), 'draft' => t('احفظ المسودة'), 'queue' => t('ضيف للطابور'), 'schedule' => t('جدوِل المنشور')], 'goN' => t('جدول %s منشور'),
      'confirmNow' => t('المنشور هيتنشر فوراً على كل الحسابات المختارة. تكمل؟'), 'lastAcc' => t('اخترنا الحسابات اللي استخدمتها آخر مرة.'), 'clearAcc' => t('شيلهم'), 'extraN' => t('+%s ميعاد'),
      'onN' => t('هيتنشر على %s حساب'), 'draftSaved' => t('المسودة محفوظة'), 'restore' => t('عندك مسودة لسه ماتنشرتش:'), 'restoreBtn' => t('استرجاع'), 'discard' => t('تجاهل')], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/compose-ux.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>window.CPT = <?= json_encode(['csrf' => csrf_token(), 'tpl' => cpt_templates(), 'mine' => json_decode((string)setting('cpt_tpl_' . (int)ws('id'), '[]'), true) ?: [], 'tags' => json_decode((string)setting('cpt_tags_' . (int)ws('id'), '[]'), true) ?: [],
        'tr' => ['tplT' => t('قوالب جاهزة'), 'mine' => t('قوالبي'), 'saveTpl' => t('احفظ النص ده كقالب'), 'tplHint' => t('الخانات اللي بين [ ] املاها بنفسك.'), 'use' => t('استخدم'), 'tplName' => t('اسم القالب؟'), 'replaceQ' => t('هيتشال النص الحالي. تكمل؟'),
            'tagsT' => t('بنك الهاشتاجات'), 'noTags' => t('لسه مفيش مجموعات. احفظ مجموعة هاشتاجات بتستخدمها كتير.'), 'tagName' => t('اسم المجموعة'), 'tagsHint' => t('احفظ كل مجموعة مرة واحدة واستخدمها بضغطة.'), 'tagNeed' => t('اكتب اسم وهاشتاج واحد على الأقل.'), 'save' => t('احفظ'), 'saved' => t('اتحفظ ✓'),
            'pickAcc' => t('اختار الحسابات الأول.'), 'empty' => t('اكتب النص الأول.'), 'verDone' => t('اتعملت نسخة لـ %s حساب. راجعها في إعدادات كل حساب تحت.'), 'verSame' => t('النص مناسب لكل الحسابات المختارة زي ما هو.'), 'linkBio' => t('🔗 اللينك في البايو'),
            'cLong' => t('%s: النص %s حرف والحد %s'), 'cMedia' => t('إنستجرام/تيك توك محتاجين صورة أو فيديو'), 'cTagsIg' => t('إنستجرام بيقبل 30 هاشتاج بس'), 'cTags' => t('%s هاشتاج كتير على فيسبوك/لينكدإن (الأحسن 3-5)'), 'cIgLink' => t('اللينكات في إنستجرام مش بتتضغط'),
            'cBadLink' => t('اللينك شكله غلط: %s'), 'cCaps' => t('حروف كابيتال كتير ورا بعض'), 'cRepeat' => t('فيه حرف متكرر كتير'), 'cShort' => t('النص قصير أوي'), 'cVar' => t('فيه متغير زي {city} لسه ماتبدلش'), 'cFill' => t('فيه خانة [ ] لسه ماتملتش'), 'cOk' => t('جاهز للنشر'), 'cBlack' => t('الميعاد ده من الأوقات الممنوعة للنشر'),
            'linkQ' => t('الرابط اللي عاوز تعمل منه منشور (مقال، منتج، صفحة):'), 'linking' => t('بنقرا الصفحة…'), 'imgAdded' => t('اتضافت صورة الصفحة للمنشور ✓'), 'noImg' => t('ارفع صورة أو اختار من المكتبة الأول.'), 'capping' => t('بنشوف الصورة ونكتب…'),
            'brandT' => t('هوية الصورة'), 'brandHint' => t('بيتطبق على الصور اللي رفعتها من جهازك قبل النشر. الإعدادات بتتحفظ للمرة الجاية.'), 'logo' => t('اللوجو'), 'pos' => t('المكان'), 'wm' => t('علامة مائية (نص)'), 'frame' => t('إطار بلون الشركة'), 'apply' => t('طبّق على الصور'), 'applied' => t('اتطبقت على %s صورة ✓'),
            'posBR' => t('تحت يمين'), 'posBL' => t('تحت شمال'), 'posTR' => t('فوق يمين'), 'posTL' => t('فوق شمال'), 'size' => t('الحجم')], 'canAi' => can('ai.use') && (bool)ai_connection(), 'black' => json_decode((string)setting('ps_blackout_' . (int)ws('id'), '[]'), true) ?: []], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/compose-tools.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>
      (function () {
        function sync() {
          var any = false;
          document.querySelectorAll('.acc').forEach(function (c) {
            var p = document.querySelector('.panel[data-acc="' + c.value + '"]');
            if (p) p.classList.toggle('hidden', !c.checked);
            if (c.checked) any = true;
          });
          document.getElementById('noPanels').classList.toggle('hidden', any);
          var m = document.querySelector('.mode:checked');
          document.getElementById('whenBox').classList.toggle('hidden', !m || m.value !== 'schedule');
        }
        document.querySelectorAll('.acc,.mode').forEach(function (el) { el.addEventListener('change', sync); });
        sync();
      })();
    </script>
    <?php
});
