<?php
/**
 * سوشيال المنافسين (من غير ذكاء اصطناعي): المتابعين يوميًا، كل المنشورات بأرقامها، والحسابات منها.
 * المصدر: Apify (نفس اتصال التكاملات). التشغيلات بتبدأ وتتجمع على مراحل عشان الطلب مايعلقش دقايق.
 */

/** المنصات المدعومة والـ Actor لكل واحدة (عامة على Apify) */
function cs_platforms(): array
{
    return [
        'instagram' => ['name' => 'Instagram', 'actors' => ['apify~instagram-profile-scraper']],
        'tiktok' => ['name' => 'TikTok', 'actors' => ['clockworks~tiktok-scraper']],
        'facebook' => ['name' => 'Facebook', 'actors' => ['apify~facebook-pages-scraper', 'apify~facebook-posts-scraper']],
        'youtube' => ['name' => 'YouTube', 'actors' => ['streamers~youtube-scraper']],
    ];
}

/** المنصة والحساب من الرابط. null = مش سوشيال أو مش مدعوم */
function cs_detect(string $url): ?array
{
    $u = trim($url); if ($u === '') return null;
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    $h = strtolower(preg_replace('/^(www\.|m\.|mobile\.|web\.)/', '', (string)parse_url($u, PHP_URL_HOST)));
    $p = trim((string)parse_url($u, PHP_URL_PATH), '/'); $seg = $p === '' ? [] : explode('/', $p);
    if ($h === 'instagram.com' && !empty($seg[0]) && !in_array($seg[0], ['p', 'reel', 'explore', 'stories'], true)) return ['instagram', strtolower($seg[0]), 'https://www.instagram.com/' . strtolower($seg[0]) . '/'];
    if ($h === 'tiktok.com' && !empty($seg[0]) && $seg[0][0] === '@') return ['tiktok', strtolower(substr($seg[0], 1)), 'https://www.tiktok.com/' . strtolower($seg[0])];
    if (in_array($h, ['facebook.com', 'fb.com'], true) && !empty($seg[0])) {
        if ($seg[0] === 'profile.php') { parse_str((string)parse_url($u, PHP_URL_QUERY), $q); if (!empty($q['id'])) return ['facebook', (string)$q['id'], 'https://www.facebook.com/profile.php?id=' . $q['id']]; return null; }
        $pg = $seg[0] === 'pages' ? ($seg[1] ?? '') : $seg[0]; if ($pg === '' || in_array($pg, ['groups', 'events', 'watch', 'share'], true)) return null;
        return ['facebook', $pg, 'https://www.facebook.com/' . $pg];
    }
    if (in_array($h, ['youtube.com', 'youtu.be'], true) && !empty($seg[0])) {
        if ($seg[0][0] === '@') return ['youtube', $seg[0], 'https://www.youtube.com/' . $seg[0]];
        if (in_array($seg[0], ['channel', 'c', 'user'], true) && !empty($seg[1])) return ['youtube', $seg[1], 'https://www.youtube.com/' . $seg[0] . '/' . $seg[1]];
    }
    return null;
}

/** روابط سوشيال مش مدعومة (للعرض بس): X ولينكدإن */
function cs_unsupported(string $url): ?string
{
    $h = strtolower(preg_replace('/^www\./', '', (string)parse_url(preg_match('~^https?://~i', $url) ? $url : 'https://' . $url, PHP_URL_HOST)));
    return in_array($h, ['x.com', 'twitter.com'], true) ? 'X' : (in_array($h, ['linkedin.com'], true) ? 'LinkedIn' : null);
}

/** مسح تشغيلة حساب اتشال */
function cs_forget(int $sid): void { try { db()->prepare('DELETE FROM settings WHERE name = ?')->execute(['cs_run_' . $sid]); unset($GLOBALS['__settings']['cs_run_' . $sid]); } catch (Throwable $e) {} }
/** قبل مسح منافس: نمسح تشغيلات حساباته */
function cs_forget_comp(int $cid): void { try { $st = db()->prepare('SELECT id FROM competitor_social WHERE competitor_id = ?'); $st->execute([$cid]); foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $i) cs_forget((int)$i); } catch (Throwable $e) {} }

function cs_on(): bool { return function_exists('tnd_apify_on') && tnd_apify_on(); }

/** حسابات المنافس (بتتحدّث من روابطه: اللي اتشال يتمسح، والجديد يتضاف) */
function cs_sync(array $c): array
{
    $want = [];
    foreach (array_filter(explode("\n", (string)$c['links'])) as $l) { $d = cs_detect($l); if ($d && !isset($want[$d[0]])) $want[$d[0]] = $d; }
    $pdo = db(); $have = [];
    $st = $pdo->prepare('SELECT * FROM competitor_social WHERE competitor_id = ?'); $st->execute([(int)$c['id']]);
    foreach ($st->fetchAll() as $r) $have[$r['platform']] = $r;
    foreach ($have as $pl => $r) if (!isset($want[$pl]) || $want[$pl][1] !== $r['handle']) { $pdo->prepare('DELETE FROM competitor_social WHERE id = ?')->execute([(int)$r['id']]); cs_forget((int)$r['id']); unset($have[$pl]); }
    foreach ($want as $pl => $d) if (!isset($have[$pl])) $pdo->prepare('INSERT INTO competitor_social (workspace_id, competitor_id, platform, handle, url) VALUES (?, ?, ?, ?, ?)')->execute([(int)$c['workspace_id'], (int)$c['id'], $pl, mb_substr($d[1], 0, 190), mb_substr($d[2], 0, 500)]);
    $st->execute([(int)$c['id']]); return $st->fetchAll();
}

/** مدخلات كل Actor */
/** Actor التعليقات لكل منصة */
function cs_comment_actor(string $pl): ?string
{
    return ['instagram' => 'apify~instagram-comment-scraper', 'tiktok' => 'clockworks~tiktok-comments-scraper', 'facebook' => 'apify~facebook-comments-scraper', 'youtube' => 'streamers~youtube-comments-scraper'][$pl] ?? null;
}
/** روابط آخر منشورات الحساب (للتعليقات) */
function cs_recent_urls(int $sid, int $n = 5): array
{
    $st = db()->prepare('SELECT url FROM competitor_posts WHERE social_id = ? AND url IS NOT NULL AND url <> "" ORDER BY COALESCE(posted_at, first_seen) DESC LIMIT ' . max(1, $n)); $st->execute([$sid]); return $st->fetchAll(PDO::FETCH_COLUMN);
}
function cs_comment_input(string $pl, array $urls): array
{
    switch ($pl) {
        case 'instagram': return ['directUrls' => $urls, 'resultsLimit' => 30, 'includeNestedComments' => true];
        case 'tiktok': return ['postURLs' => $urls, 'commentsPerPost' => 30, 'maxRepliesPerComment' => 5];
        case 'facebook': return ['startUrls' => array_map(function ($u) { return ['url' => $u]; }, $urls), 'resultsLimit' => 30, 'includeNestedComments' => true];
        case 'youtube': return ['startUrls' => array_map(function ($u) { return ['url' => $u]; }, $urls), 'maxComments' => 30];
    }
    return [];
}

function cs_input(string $actor, array $s): array
{
    switch ($actor) {
        case 'apify~instagram-profile-scraper': return ['usernames' => [$s['handle']], 'resultsLimit' => 30];
        case 'clockworks~tiktok-scraper': return ['profiles' => [$s['handle']], 'resultsPerPage' => 30, 'shouldDownloadVideos' => false, 'shouldDownloadCovers' => false, 'shouldDownloadSubtitles' => false];
        case 'apify~facebook-pages-scraper': return ['startUrls' => [['url' => $s['url']]]];
        case 'apify~facebook-posts-scraper': return ['startUrls' => [['url' => $s['url']]], 'resultsLimit' => 30];
        case 'streamers~youtube-scraper': return ['startUrls' => [['url' => $s['url']]], 'maxResults' => 30, 'maxResultsShorts' => 15, 'maxResultStreams' => 0];
    }
    return [];
}

/** يبدأ تشغيلات Apify لحساب (من غير ما يستنى) */
function cs_start(array $s): bool
{
    $pl = cs_platforms()[$s['platform']] ?? null; if (!$pl || !cs_on()) return false;
    $runs = [];
    foreach ($pl['actors'] as $a) { $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode($a) . '/runs?memory=1024', cs_input($a, $s)); if (!empty($r['data']['id'])) $runs[$a] = (string)$r['data']['id']; }
    // التعليقات على آخر 5 منشورات: كل 3 أيام بس (الأغلى في Apify)
    $ca = cs_comment_actor($s['platform']); $urls = cs_recent_urls((int)$s['id']);
    if ($ca && $urls && (empty($s['comments_at']) || strtotime((string)$s['comments_at']) < time() - 3 * 86400)) {
        try { $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode($ca) . '/runs?memory=1024', cs_comment_input($s['platform'], $urls)); if (!empty($r['data']['id'])) $runs['c:' . $ca] = (string)$r['data']['id']; } catch (Throwable $e) {}
    }
    save_setting('cs_run_' . (int)$s['id'], json_encode(['runs' => $runs, 'at' => time()]));
    return (bool)$runs;
}

/** فيه تشغيلة شغالة للحساب ده؟ */
function cs_pending(int $sid): bool { $r = json_decode((string)setting('cs_run_' . $sid, ''), true); return !empty($r['runs']) && (int)($r['at'] ?? 0) > time() - 1800; }

/** يجمع نتايج التشغيلات اللي خلصت. true = اتجمع (أو فشل وخلص)، false = لسه */
function cs_collect(array $s): bool
{
    $key = 'cs_run_' . (int)$s['id']; $run = json_decode((string)setting($key, ''), true); if (empty($run['runs'])) return true;
    $items = []; $fail = '';
    foreach ($run['runs'] as $a => $id) {
        $r = tnd_apify_req('GET', '/v2/actor-runs/' . rawurlencode($id)); $st = (string)($r['data']['status'] ?? '');
        if (in_array($st, ['READY', 'RUNNING'], true) && (int)$run['at'] > time() - 1800) return false;
        if ($st !== 'SUCCEEDED') { if (strpos($a, 'c:') !== 0) $fail = t('تعذّرت قراءة الحساب (%s)', $st ?: '?'); continue; }
        $items[$a] = tnd_apify_req('GET', '/v2/datasets/' . rawurlencode((string)$r['data']['defaultDatasetId']) . '/items?clean=1&limit=100');
    }
    save_setting($key, '');
    $cm = []; foreach ($items as $a => $v) if (strpos($a, 'c:') === 0) { $cm = $v; unset($items[$a]); }
    $p = cs_parse($s['platform'], $items);
    cs_store($s, $p, $fail);
    foreach ($run['runs'] as $a => $_) if (strpos($a, 'c:') === 0) { cs_store_comments($s, $cm); break; }
    return true;
}

function cs_n($v): ?int { if ($v === null || $v === '' || !is_numeric($v)) return null; return max(0, (int)$v); }
function cs_time($v): ?string { if ($v === null || $v === '') return null; $t = is_numeric($v) ? (int)$v : strtotime((string)$v); if ($t > 2000000000000) $t = (int)($t / 1000); return $t > 0 ? date('Y-m-d H:i:s', $t) : null; }
function cs_tags(string $text): array { preg_match_all('/#([\p{L}\p{N}_]+)/u', $text, $m); return array_values(array_unique(array_map('mb_strtolower', $m[1]))); }
function cs_ments(string $text): array { preg_match_all('/@([\p{L}\p{N}_.]+)/u', $text, $m); return array_values(array_unique(array_map(function ($x) { return mb_strtolower(rtrim($x, '.')); }, $m[1]))); }

/** تحويل بيانات كل منصة لشكل واحد: profile + posts */
function cs_parse(string $pl, array $items): array
{
    $prof = []; $posts = [];
    $post = function (array $x) { $cap = (string)$x['caption']; $x['hashtags'] = array_values(array_unique(array_merge(array_map('mb_strtolower', (array)($x['hashtags'] ?? [])), cs_tags($cap)))); $x['mentions'] = array_values(array_unique(array_merge(array_map('mb_strtolower', (array)($x['mentions'] ?? [])), cs_ments($cap)))); return $x; };
    if ($pl === 'instagram') {
        $p = (array)(array_values($items)[0][0] ?? []);
        $prof = ['name' => $p['fullName'] ?? $p['username'] ?? null, 'followers' => cs_n($p['followersCount'] ?? null), 'following' => cs_n($p['followsCount'] ?? null), 'posts' => cs_n($p['postsCount'] ?? null), 'bio' => $p['biography'] ?? null, 'avatar' => $p['profilePicUrlHD'] ?? $p['profilePicUrl'] ?? null, 'link' => $p['externalUrl'] ?? null];
        foreach ((array)($p['latestPosts'] ?? []) as $x) {
            $t = strtolower((string)($x['type'] ?? '')); $type = ($x['productType'] ?? '') === 'clips' || $t === 'video' ? 'reel' : ($t === 'sidecar' ? 'carousel' : 'image');
            $posts[] = $post(['id' => (string)($x['id'] ?? $x['shortCode'] ?? ''), 'url' => $x['url'] ?? (isset($x['shortCode']) ? 'https://www.instagram.com/p/' . $x['shortCode'] . '/' : null), 'type' => $type, 'caption' => (string)($x['caption'] ?? ''), 'thumb' => $x['displayUrl'] ?? null,
                'hashtags' => (array)($x['hashtags'] ?? []), 'mentions' => (array)($x['mentions'] ?? []), 'likes' => cs_n($x['likesCount'] ?? null), 'comments' => cs_n($x['commentsCount'] ?? null), 'views' => cs_n($x['videoPlayCount'] ?? $x['videoViewCount'] ?? null), 'shares' => null,
                'paid' => !empty($x['isSponsored']) || !empty($x['paidPartnership']), 'at' => cs_time($x['timestamp'] ?? null)]);
        }
    } elseif ($pl === 'tiktok') {
        foreach ((array)(array_values($items)[0] ?? []) as $x) {
            $a = (array)($x['authorMeta'] ?? []); if (!$prof && $a) $prof = ['name' => $a['nickName'] ?? $a['name'] ?? null, 'followers' => cs_n($a['fans'] ?? null), 'following' => cs_n($a['following'] ?? null), 'posts' => cs_n($a['video'] ?? null), 'bio' => $a['signature'] ?? null, 'avatar' => $a['avatar'] ?? null, 'link' => $a['bioLink']['link'] ?? null];
            if (empty($x['id'])) continue;
            $posts[] = $post(['id' => (string)$x['id'], 'url' => $x['webVideoUrl'] ?? null, 'type' => 'video', 'caption' => (string)($x['text'] ?? ''), 'thumb' => $x['videoMeta']['coverUrl'] ?? null,
                'hashtags' => array_map(function ($h) { return is_array($h) ? (string)($h['name'] ?? '') : (string)$h; }, (array)($x['hashtags'] ?? [])), 'mentions' => array_map(function ($h) { return ltrim(is_array($h) ? (string)($h['name'] ?? $h['username'] ?? '') : (string)$h, '@'); }, (array)($x['mentions'] ?? [])),
                'likes' => cs_n($x['diggCount'] ?? null), 'comments' => cs_n($x['commentCount'] ?? null), 'views' => cs_n($x['playCount'] ?? null), 'shares' => cs_n($x['shareCount'] ?? null), 'paid' => !empty($x['isAd']) || !empty($x['isSponsored']), 'at' => cs_time($x['createTimeISO'] ?? $x['createTime'] ?? null),
                'music' => trim((string)($x['musicMeta']['musicName'] ?? '') . (empty($x['musicMeta']['musicOriginal']) && !empty($x['musicMeta']['musicAuthor']) ? ' — ' . $x['musicMeta']['musicAuthor'] : '')) ?: null, 'original' => !empty($x['musicMeta']['musicOriginal'])]);
        }
    } elseif ($pl === 'facebook') {
        $p = (array)($items['apify~facebook-pages-scraper'][0] ?? []);
        $prof = ['name' => $p['title'] ?? $p['pageName'] ?? null, 'followers' => cs_n($p['followers'] ?? $p['likes'] ?? null), 'following' => null, 'posts' => null, 'bio' => $p['intro'] ?? $p['info'][0] ?? null, 'avatar' => $p['profilePictureUrl'] ?? null];
        foreach ((array)($items['apify~facebook-posts-scraper'] ?? []) as $x) {
            if (empty($x['postId']) && empty($x['url'])) continue; $m = (array)($x['media'][0] ?? []);
            $type = !empty($x['isVideo']) || stripos((string)($m['__typename'] ?? ''), 'video') !== false ? 'video' : (count((array)($x['media'] ?? [])) > 1 ? 'carousel' : ($m ? 'image' : 'text'));
            $posts[] = $post(['id' => (string)($x['postId'] ?? md5((string)$x['url'])), 'url' => $x['url'] ?? null, 'type' => $type, 'caption' => (string)($x['text'] ?? ''), 'thumb' => $m['thumbnail'] ?? $m['photo_image']['uri'] ?? null, 'hashtags' => [], 'mentions' => [],
                'likes' => cs_n($x['likes'] ?? null), 'comments' => cs_n($x['comments'] ?? null), 'views' => cs_n($x['viewsCount'] ?? null), 'shares' => cs_n($x['shares'] ?? null), 'paid' => false, 'at' => cs_time($x['time'] ?? $x['timestamp'] ?? null)]);
        }
    } elseif ($pl === 'youtube') {
        foreach ((array)(array_values($items)[0] ?? []) as $x) {
            if (!$prof) $prof = ['name' => $x['channelName'] ?? null, 'followers' => cs_n($x['numberOfSubscribers'] ?? null), 'following' => null, 'posts' => cs_n($x['channelTotalVideos'] ?? null), 'bio' => $x['channelDescription'] ?? null, 'avatar' => $x['channelAvatarUrl'] ?? null];
            if (empty($x['id'])) continue;
            $posts[] = $post(['id' => (string)$x['id'], 'url' => $x['url'] ?? null, 'type' => ($x['type'] ?? '') === 'shorts' ? 'short' : 'video', 'caption' => trim((string)($x['title'] ?? '') . "\n" . mb_substr((string)($x['text'] ?? ''), 0, 400)), 'thumb' => $x['thumbnailUrl'] ?? null,
                'hashtags' => (array)($x['hashtags'] ?? []), 'mentions' => [], 'likes' => cs_n($x['likes'] ?? null), 'comments' => cs_n($x['commentsCount'] ?? null), 'views' => cs_n($x['viewCount'] ?? null), 'shares' => null, 'paid' => false, 'at' => cs_time($x['date'] ?? null)]);
        }
    }
    return ['profile' => $prof, 'posts' => array_values(array_filter($posts, function ($x) { return $x['id'] !== ''; }))];
}

/** تخزين: البروفايل، صف اليوم (للرسم)، والمنشورات (جديدة أو تحديث أرقامها) */
function cs_store(array $s, array $p, string $fail = ''): void
{
    $pdo = db(); $pr = $p['profile'];
    $ok = $pr && ($pr['followers'] !== null || $p['posts']);
    if (!$ok) { $pdo->prepare('UPDATE competitor_social SET last_check = NOW(), last_ok = 0, err = ? WHERE id = ?')->execute([mb_substr($fail ?: t('لم نجد بيانات لهذا الحساب. تأكد من الرابط وأن الحساب عام.'), 0, 300), (int)$s['id']]); return; }
    // تغييرات البروفايل (الاسم، البايو، اللينك) تتسجل في سجل التغييرات (مش أول قراءة)
    if (!empty($s['last_ok'])) {
        $ev = []; $pn = cs_platforms()[$s['platform']]['name'] ?? $s['platform'];
        $norm = function ($v) { return trim(preg_replace('/\s+/u', ' ', (string)$v)); };
        if ($pr['name'] !== null && $norm($s['name']) !== '' && $norm($pr['name']) !== $norm($s['name'])) $ev[] = ['soc_name', $pn, $norm($s['name']) . ' → ' . $norm($pr['name'])];
        if ($pr['bio'] !== null && $norm($s['bio']) !== '' && $norm($pr['bio']) !== $norm($s['bio'])) $ev[] = ['soc_bio', $pn, mb_substr($norm($pr['bio']), 0, 400)];
        if (array_key_exists('link', $pr) && $pr['link'] !== null && $norm($pr['link']) !== $norm($s['ext_url'] ?? '') && $norm($s['ext_url'] ?? '') !== '') $ev[] = ['soc_link', $pn, $norm($pr['link'])];
        foreach ($ev as $e) $pdo->prepare('INSERT INTO competitor_events (workspace_id, competitor_id, kind, title, detail) VALUES (?, ?, ?, ?, ?)')->execute([(int)$s['workspace_id'], (int)$s['competitor_id'], $e[0], $e[1], mb_substr($e[2], 0, 500)]);
    }
    if (array_key_exists('link', $pr) && $pr['link'] !== null) $pdo->prepare('UPDATE competitor_social SET ext_url = ? WHERE id = ?')->execute([mb_substr((string)$pr['link'], 0, 500), (int)$s['id']]);
    $pdo->prepare('UPDATE competitor_social SET name = ?, followers = COALESCE(?, followers), following = COALESCE(?, following), posts_count = COALESCE(?, posts_count), bio = ?, avatar = ?, last_check = NOW(), last_ok = 1, err = NULL WHERE id = ?')
        ->execute([$pr['name'] !== null ? mb_substr((string)$pr['name'], 0, 190) : null, $pr['followers'], $pr['following'], $pr['posts'], $pr['bio'] !== null ? mb_substr((string)$pr['bio'], 0, 2000) : null, $pr['avatar'] !== null ? mb_substr((string)$pr['avatar'], 0, 500) : null, (int)$s['id']]);
    if ($pr['followers'] !== null) $pdo->prepare('REPLACE INTO competitor_social_days (social_id, day, followers, posts_count) VALUES (?, ?, ?, ?)')->execute([(int)$s['id'], date('Y-m-d'), $pr['followers'], $pr['posts']]);
    // views7: مشاهدات الفيديو لما يكون عمره 6-9 أيام (أقرب قراءة لأسبوع)، بتتسجل مرة واحدة
    $up = $pdo->prepare('INSERT INTO competitor_posts (social_id, ext_id, url, type, caption, thumb, hashtags, mentions, likes, comments, views, shares, paid, posted_at, music, views7, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE likes = VALUES(likes), comments = VALUES(comments), views = VALUES(views), shares = VALUES(shares), thumb = VALUES(thumb), music = COALESCE(VALUES(music), music), views7 = COALESCE(views7, VALUES(views7)), updated_at = NOW()');
    foreach ($p['posts'] as $x) { $age = $x['at'] ? (time() - strtotime($x['at'])) / 86400 : null; $v7 = $x['views'] !== null && $age !== null && $age >= 6 && $age <= 9 ? $x['views'] : null;
        $up->execute([(int)$s['id'], mb_substr($x['id'], 0, 120), $x['url'] ? mb_substr((string)$x['url'], 0, 500) : null, $x['type'], mb_substr($x['caption'], 0, 3000), $x['thumb'] ? mb_substr((string)$x['thumb'], 0, 700) : null,
        json_encode(array_slice(array_filter($x['hashtags']), 0, 40), JSON_UNESCAPED_UNICODE), json_encode(array_slice(array_filter($x['mentions']), 0, 30), JSON_UNESCAPED_UNICODE), $x['likes'], $x['comments'], $x['views'], $x['shares'], $x['paid'] ? 1 : 0, $x['at'], isset($x['music']) && $x['music'] !== null ? mb_substr((string)$x['music'], 0, 190) : null, $v7]); }
}

/** تخزين التعليقات (بشكل موحد) + هل صاحبها هو الحساب نفسه */
function cs_store_comments(array $s, array $items): void
{
    $pdo = db(); $h = mb_strtolower(ltrim((string)$s['handle'], '@')); $nm = mb_strtolower(trim((string)$s['name']));
    $pid = function ($url) { $u = (string)$url; if (preg_match('~/(?:p|reel|tv)/([^/?#]+)~', $u, $m) || preg_match('~/video/(\d+)~', $u, $m) || preg_match('~[?&]v=([\w-]+)~', $u, $m) || preg_match('~/posts/([^/?#]+)~', $u, $m)) return $m[1]; return md5($u); };
    $rows = [];
    $add = function (array $c, ?string $parent, string $post) use (&$rows, &$add, $h, $nm, $s) {
        $auth = (string)($c['ownerUsername'] ?? $c['uniqueId'] ?? $c['profileName'] ?? $c['author'] ?? $c['user']['username'] ?? '');
        $own = !empty($c['authorIsChannelOwner']) || ($auth !== '' && (mb_strtolower(ltrim($auth, '@')) === $h || ($nm !== '' && mb_strtolower($auth) === $nm)));
        $id = (string)($c['id'] ?? $c['cid'] ?? $c['commentId'] ?? ''); if ($id === '') return;
        $rows[] = [$post, $id, $parent ?? (isset($c['repliesToId']) && $c['repliesToId'] ? (string)$c['repliesToId'] : (isset($c['replyToCid']) && $c['replyToCid'] ? (string)$c['replyToCid'] : (isset($c['replyToCommentId']) && $c['replyToCommentId'] ? (string)$c['replyToCommentId'] : null))),
            mb_substr($auth, 0, 190), mb_substr((string)($c['text'] ?? $c['comment'] ?? ''), 0, 1500), $own ? 1 : 0, cs_time($c['timestamp'] ?? $c['createTimeISO'] ?? $c['date'] ?? null)];
        foreach ((array)($c['replies'] ?? []) as $r) if (is_array($r)) $add($r, $id, $post);
    };
    foreach ($items as $c) if (is_array($c)) $add($c, null, $pid($c['postUrl'] ?? $c['videoWebUrl'] ?? $c['facebookUrl'] ?? $c['pageUrl'] ?? $c['videoUrl'] ?? (isset($c['videoId']) ? '?v=' . $c['videoId'] : '')));
    $ins = $pdo->prepare('INSERT IGNORE INTO competitor_comments (social_id, post_ext, ext_id, parent_ext, author, text, is_owner, at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($rows as $r) $ins->execute(array_merge([(int)$s['id']], $r));
    $pdo->prepare('UPDATE competitor_social SET comments_at = NOW() WHERE id = ?')->execute([(int)$s['id']]);
}

/** تحديث كل حسابات منافس (يبدأ التشغيلات). بيرجع عدد الحسابات اللي بدأت */
function cs_refresh(array $c): int
{
    $n = 0; foreach (cs_sync($c) as $s) { if (cs_pending((int)$s['id'])) { $n++; continue; } try { if (cs_start($s)) $n++; } catch (Throwable $e) { db()->prepare('UPDATE competitor_social SET last_check = NOW(), last_ok = 0, err = ? WHERE id = ?')->execute([mb_substr($e->getMessage(), 0, 300), (int)$s['id']]); } }
    return $n;
}

/** يجمع اللي خلص لمنافس. بيرجع عدد اللي لسه شغال */
function cs_poll(int $cid): int
{
    $st = db()->prepare('SELECT * FROM competitor_social WHERE competitor_id = ?'); $st->execute([$cid]); $left = 0;
    foreach ($st->fetchAll() as $s) { if (!cs_pending((int)$s['id'])) continue; try { if (!cs_collect($s)) $left++; } catch (Throwable $e) { save_setting('cs_run_' . (int)$s['id'], ''); } }
    return $left;
}

/** التشغيل الدوري: يجمع اللي خلص، ويبدأ الحسابات اللي عدّى عليها يوم (لحد $max في المرة عشان رصيد Apify) */
function cs_tick(int $max = 4): int
{
    if (!cs_on()) return 0;
    $n = 0;
    try { foreach (comp_list() as $c) cs_sync($c); } catch (Throwable $e) {}
    $st = db()->prepare('SELECT * FROM competitor_social WHERE workspace_id = ?'); $st->execute([(int)ws('id')]); $all = $st->fetchAll();
    $touched = [];
    foreach ($all as $s) if (cs_pending((int)$s['id'])) { $touched[(int)$s['id']] = 1; try { if (cs_collect($s)) $n++; } catch (Throwable $e) {} }
    // اللي اتجمع أو لسه شغال في الدورة دي مايتبدأش تاني (كان بيتقري مرتين ويتحاسب مرتين)
    $due = array_filter($all, function ($s) use ($touched) { return !isset($touched[(int)$s['id']]) && !cs_pending((int)$s['id']) && (!$s['last_check'] || strtotime($s['last_check']) < time() - 20 * 3600); });
    foreach (array_slice($due, 0, $max) as $s) { try { cs_start($s); } catch (Throwable $e) {} }
    return $n;
}

/** كل أرقام السوشيال لمنافس (الأفكار 1-10) */
function cs_report(int $cid): array
{
    $pdo = db(); $tz = new DateTimeZone(site_timezone());
    $st = $pdo->prepare('SELECT * FROM competitor_social WHERE competitor_id = ? AND workspace_id = ? ORDER BY FIELD(platform, "instagram", "tiktok", "facebook", "youtube")'); $st->execute([$cid, (int)ws('id')]); $acc = $st->fetchAll();
    $P = []; $posts = []; $since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
    foreach ($acc as $s) {
        $d = $pdo->prepare('SELECT day, followers FROM competitor_social_days WHERE social_id = ? AND day >= ? ORDER BY day'); $d->execute([(int)$s['id'], date('Y-m-d', strtotime('-90 days'))]); $series = $d->fetchAll();
        $at = function ($days) use ($series) { $lim = date('Y-m-d', strtotime('-' . $days . ' days')); $v = null; foreach ($series as $r) { if ($r['day'] <= $lim) $v = (int)$r['followers']; } return $v ?? ($series ? (int)$series[0]['followers'] : null); };
        $f = $s['followers'] !== null ? (int)$s['followers'] : null;
        $g = function ($days) use ($at, $f, $series) { $b = $at($days); if ($f === null || $b === null || count($series) < 2) return null; return ['abs' => $f - $b, 'pct' => $b ? round(($f - $b) / $b * 100, 2) : null]; };
        $q = $pdo->prepare('SELECT * FROM competitor_posts WHERE social_id = ? ORDER BY COALESCE(posted_at, first_seen) DESC LIMIT 300'); $q->execute([(int)$s['id']]); $ps = $q->fetchAll();
        $recent = array_filter($ps, function ($x) { return $x['posted_at'] && strtotime($x['posted_at']) >= strtotime('-28 days'); });
        $er = []; foreach ($ps as $x) { if (!$f || !$x['posted_at'] || $x['posted_at'] < $since30) continue; $er[] = ((int)$x['likes'] + (int)$x['comments']) / $f * 100; }
        $first = $series ? $series[0]['day'] : null;
        $P[] = ['id' => (int)$s['id'], 'platform' => $s['platform'], 'pname' => cs_platforms()[$s['platform']]['name'] ?? $s['platform'], 'handle' => $s['handle'], 'url' => $s['url'], 'name' => (string)$s['name'], 'avatar' => (string)$s['avatar'], 'bio' => (string)$s['bio'],
            'followers' => $f, 'following' => $s['following'] !== null ? (int)$s['following'] : null, 'posts' => $s['posts_count'] !== null ? (int)$s['posts_count'] : null,
            'ok' => (int)$s['last_ok'], 'err' => (string)$s['err'], 'checked' => $s['last_check'] ? fmt_date($s['last_check'], 'd/m h:i A') : '', 'pending' => cs_pending((int)$s['id']),
            'series' => array_map(function ($r) { return [$r['day'], (int)$r['followers']]; }, $series), 'g7' => $g(7), 'g30' => $g(30), 'tracked' => $first,
            'perWeek' => round(count($recent) / 4, 1), 'er' => $er ? round(array_sum($er) / count($er), 2) : null, 'erN' => count($er),
            'reach' => (function () use ($ps, $f, $since30) { $r = []; foreach ($ps as $x) if ($f && $x['views'] !== null && in_array($x['type'], ['reel', 'video', 'short'], true) && $x['posted_at'] && $x['posted_at'] >= $since30) $r[] = (int)$x['views'] / $f * 100; return $r ? round(array_sum($r) / count($r)) : null; })(),
            'v7' => (function () use ($ps) { $r = array_values(array_filter(array_map(function ($x) { return $x['views7'] !== null ? (int)$x['views7'] : null; }, $ps), function ($v) { return $v !== null; })); return $r ? ['avg' => (int)round(array_sum($r) / count($r)), 'n' => count($r)] : null; })(),
            'commentsAt' => $s['comments_at'] ? fmt_date($s['comments_at'], 'd/m') : ''];
        foreach ($ps as $x) { $x['platform'] = $s['platform']; $x['followers'] = $f; $posts[] = $x; }
    }
    // أسرع منصة بتكبر (نسبة الأسبوع)
    $fast = null; foreach ($P as $p) if ($p['g7'] && $p['g7']['pct'] !== null && (!$fast || $p['g7']['pct'] > $fast['pct'])) $fast = ['platform' => $p['pname'], 'pct' => $p['g7']['pct'], 'abs' => $p['g7']['abs']];
    $eng = function ($x) { return (int)$x['likes'] + (int)$x['comments'] + (int)$x['shares']; };
    $map = function ($x) use ($eng, $tz) { $t = $x['posted_at'] ? (new DateTime($x['posted_at']))->setTimezone($tz) : null; return ['id' => (int)$x['id'], 'platform' => $x['platform'], 'url' => (string)$x['url'], 'type' => $x['type'], 'caption' => mb_substr((string)$x['caption'], 0, 280), 'thumb' => (string)$x['thumb'],
        'likes' => $x['likes'] !== null ? (int)$x['likes'] : null, 'comments' => $x['comments'] !== null ? (int)$x['comments'] : null, 'views' => $x['views'] !== null ? (int)$x['views'] : null, 'shares' => $x['shares'] !== null ? (int)$x['shares'] : null,
        'eng' => $eng($x), 'er' => $x['followers'] ? round($eng($x) / $x['followers'] * 100, 2) : null, 'paid' => (int)$x['paid'], 'at' => $t ? $t->format('d/m/Y h:i A') : '', 'ts' => $t ? $t->getTimestamp() : 0,
        'tags' => json_decode((string)$x['hashtags'], true) ?: [], 'ments' => json_decode((string)$x['mentions'], true) ?: []]; };
    $all = array_map($map, $posts); usort($all, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
    $last30 = array_values(array_filter($all, function ($x) { return $x['ts'] >= strtotime('-30 days'); }));
    $top = $last30; usort($top, function ($a, $b) { return ($b['er'] ?? -1) <=> ($a['er'] ?? -1) ?: $b['eng'] <=> $a['eng']; }); $top = array_slice($top, 0, 10);
    // خريطة أوقات النشر (أيام × ساعات) بتوقيت الشركة، آخر 90 يوم
    $heat = array_fill(0, 7, array_fill(0, 24, 0));
    foreach ($all as $x) if ($x['ts'] && $x['ts'] >= strtotime('-90 days')) { $d = (new DateTime('@' . $x['ts']))->setTimezone($tz); $heat[(int)$d->format('w')][(int)$d->format('G')]++; }
    // نوع المحتوى، الهاشتاجات، المنشن (آخر 90 يوم)
    $mix = []; $tags = []; $ments = [];
    foreach ($all as $x) { if ($x['ts'] && $x['ts'] < strtotime('-90 days')) continue; $mix[$x['type']] = ($mix[$x['type']] ?? 0) + 1;
        foreach ($x['tags'] as $h) $tags[$h] = ($tags[$h] ?? 0) + 1; foreach ($x['ments'] as $h) $ments[$h] = ($ments[$h] ?? 0) + 1; }
    arsort($mix); arsort($tags); arsort($ments);
    $tot = array_sum(array_column(array_filter($P, function ($p) { return $p['followers'] !== null; }), 'followers'));
    // 11: الشراكات المدفوعة (آخر 90 يوم) والحسابات اللي اتعامل معاها فيها
    $paid = array_values(array_filter($all, function ($x) { return $x['paid'] && $x['ts'] >= strtotime('-90 days'); })); $partners = [];
    foreach ($paid as $x) foreach ($x['ments'] as $m) $partners[$m] = ($partners[$m] ?? 0) + 1; arsort($partners);
    // 18: الأصوات (تيك توك)
    $music = []; foreach ($posts as $x) if (!empty($x['music']) && $x['posted_at'] && strtotime($x['posted_at']) >= strtotime('-90 days')) $music[$x['music']] = ($music[$x['music']] ?? 0) + 1; arsort($music);
    // 13-15: التعليقات
    $cmt = cs_comment_stats(array_column($acc, 'id'), array_column($acc, 'platform', 'id'));
    return ['accounts' => $P, 'fast' => $fast, 'total' => $tot, 'top' => $top, 'heat' => $heat, 'mix' => $mix, 'tags' => array_slice($tags, 0, 25, true), 'ments' => array_slice($ments, 0, 20, true),
        'posts' => array_slice($all, 0, 150), 'n90' => array_sum($mix), 'on' => cs_on(),
        'paid' => ['n' => count($paid), 'list' => array_slice($paid, 0, 10), 'partners' => array_slice($partners, 0, 10, true)], 'music' => array_slice($music, 0, 10, true), 'cmt' => $cmt];
}

/** كلمات الشكاوى (عربي/إنجليزي) — مطابقة بسيطة من غير ذكاء */
function cs_neg_words(): array
{
    return ['غالي', 'غالية', 'سيء', 'سيئ', 'سيئة', 'زفت', 'زبالة', 'نصب', 'نصابين', 'حرامي', 'حراميه', 'احتيال', 'متأخر', 'متاخر', 'اتأخر', 'اتاخر', 'تأخير', 'تاخير', 'ما وصل', 'موصلش', 'لم يصل', 'ماوصل', 'وحش', 'رديء', 'ردئ', 'خايس', 'قذر', 'أسوأ', 'اسوأ', 'فاشل', 'ندمان', 'ندمت', 'مقرف', 'محدش رد', 'ما ردو', 'ماحد رد', 'لا يردون', 'مش بيردوا', 'خدمة سيئة', 'تعامل سيء',
        'bad', 'worst', 'scam', 'fraud', 'late', 'expensive', 'overpriced', 'terrible', 'awful', 'refund', 'disappointed', 'never again', 'rude', 'dirty', 'fake', 'horrible', 'no reply'];
}
/** مواضيع أسئلة العملاء */
function cs_q_topics(): array
{
    // كلمة كاملة (مع و/ف/ب/ال قبلها)، عشان «الموظفين» ماتتحسبش «فين»
    $w = function (string $alts) { return '/(^|[^\p{L}])(?:و|ف|ب|ل|ال|وال|بال|فال)?(?:' . $alts . ')($|[^\p{L}])/iu'; };
    return ['price' => $w('سعر|سعره|سعرها|اسعار|أسعار|اسعاركم|أسعاركم|بكام|بكم|كام|كم|تكلفه|تكلفة|price|prices|cost|how much'), 'place' => $w('وين|فين|عنوان|عنوانكم|موقع|موقعكم|مكان|مكانكم|فرع|فروع|فرعكم|لوكيشن|where|location|branch|address'),
        'time' => $w('متى|امتى|إمتى|مواعيد|مواعيدكم|ساعات|دوام|تفتحون|تفتحوا|بتفتحوا|تقفلون|when|open|hours'), 'delivery' => $w('توصيل|توصلون|توصلوا|بتوصلوا|شحن|يوصل|delivery|deliver|shipping|ship'), 'stock' => $w('متوفر|متوفره|متوفرة|متاح|متاحه|موجود|available|stock')];
}

/** 13-15: نسبة وسرعة الرد، الشكاوى، أسئلة العملاء (آخر 30 يوم) */
function cs_comment_stats(array $sids, array $plOf): array
{
    $out = ['n' => 0, 'replied' => 0, 'rate' => null, 'median' => null, 'neg' => [], 'negWords' => [], 'qs' => [], 'qTopics' => [], 'byPl' => []];
    if (!$sids) return $out;
    $in = implode(',', array_map('intval', $sids));
    try { $rows = db()->query("SELECT * FROM competitor_comments WHERE social_id IN ($in) AND (at IS NULL OR at >= NOW() - INTERVAL 30 DAY) ORDER BY at DESC LIMIT 3000")->fetchAll(); } catch (Throwable $e) { return $out; }
    if (!$rows) return $out;
    $replyAt = []; foreach ($rows as $r) if ($r['is_owner'] && $r['parent_ext']) { $k = $r['social_id'] . '|' . $r['parent_ext']; $t = $r['at'] ? strtotime($r['at']) : 0; if (!isset($replyAt[$k]) || ($t && $t < $replyAt[$k])) $replyAt[$k] = $t; }
    $mins = []; $neg = []; $qs = []; $nw = []; $qt = []; $norm = function ($t) { return mb_strtolower(strtr($t, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي'])); };
    $words = []; foreach (cs_neg_words() as $ow) $words[$norm($ow)] = $ow; // المطابقة بالمعدّل والعرض بالأصل
    foreach ($rows as $r) {
        if ($r['is_owner'] || $r['parent_ext']) continue; // عملاء بس، تعليقات أساسية
        $out['n']++; $pl = $plOf[$r['social_id']] ?? ''; $out['byPl'][$pl] = $out['byPl'][$pl] ?? ['n' => 0, 'r' => 0]; $out['byPl'][$pl]['n']++;
        $k = $r['social_id'] . '|' . $r['ext_id'];
        if (isset($replyAt[$k])) { $out['replied']++; $out['byPl'][$pl]['r']++; if ($replyAt[$k] && $r['at']) $mins[] = max(0, ($replyAt[$k] - strtotime($r['at'])) / 60); }
        $tx = (string)$r['text']; $nt = $norm($tx); $item = ['pl' => $pl, 'who' => (string)$r['author'], 'text' => mb_substr($tx, 0, 300), 'at' => $r['at'] ? fmt_date($r['at'], 'd/m') : '', 'replied' => isset($replyAt[$k])];
        foreach ($words as $w => $ow) if ($w !== '' && preg_match('/(^|[^\p{L}])' . preg_quote($w, '/') . '($|[^\p{L}])/u', $nt)) { $nw[$ow] = ($nw[$ow] ?? 0) + 1; if (count($neg) < 15) $neg[] = $item + ['w' => $ow]; break; }
        $isQ = preg_match('/[?؟]/u', $tx); $tp = []; foreach (cs_q_topics() as $tk => $re) if (preg_match($re, $tx)) $tp[] = $tk;
        if ($isQ || $tp) { foreach ($tp ?: ['other'] as $tk) $qt[$tk] = ($qt[$tk] ?? 0) + 1; if (count($qs) < 15) $qs[] = $item + ['topics' => $tp]; }
    }
    sort($mins); arsort($nw); arsort($qt);
    $out['rate'] = $out['n'] ? (int)round($out['replied'] / $out['n'] * 100) : null;
    $out['median'] = $mins ? (int)round($mins[(int)floor((count($mins) - 1) / 2)]) : null;
    $out['neg'] = $neg; $out['negWords'] = array_slice($nw, 0, 10, true); $out['qs'] = $qs; $out['qTopics'] = $qt;
    return $out;
}

/** ملخص للكارت: إجمالي المتابعين ونمو الأسبوع */
function cs_card(int $cid): ?array
{
    try {
        $st = db()->prepare('SELECT s.id, s.followers, s.platform, (SELECT d.followers FROM competitor_social_days d WHERE d.social_id = s.id AND d.day <= ? ORDER BY d.day DESC LIMIT 1) w FROM competitor_social s WHERE s.competitor_id = ?');
        $st->execute([date('Y-m-d', strtotime('-7 days')), $cid]); $r = $st->fetchAll();
    } catch (Throwable $e) { return null; }
    if (!$r) return null;
    $f = 0; $w = 0; $has = false; foreach ($r as $x) { if ($x['followers'] === null) continue; $f += (int)$x['followers']; if ($x['w'] !== null) { $w += (int)$x['w']; $has = true; } else $w += (int)$x['followers']; }
    return ['n' => count($r), 'followers' => $f, 'g7' => $has ? $f - $w : null, 'plats' => array_column($r, 'platform')];
}
