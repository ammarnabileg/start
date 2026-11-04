<?php
/**
 * مركز التحليلات: نظرة عامة، المنشورات، الجمهور، التوقيت والمحتوى، المنصات.
 * كل تبويب بيجيب بياناته بس لما يتفتح (?tab=)، وكل جزء بيتحمّل لوحده.
 * كل النداءات قراءة بس (GET).
 */
$err = null;
$z = zernio_or_null($err);
$tabs = [
    'overview' => t('نظرة عامة'),
    'posts' => t('المنشورات'),
    'audience' => t('الجمهور'),
    'timing' => t('التوقيت والمحتوى'),
    'platforms' => t('المنصات'),
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? (string)$_GET['tab'] : 'overview';
$sec = in_array($_GET['sec'] ?? '', ['insights', 'facebook', 'youtube', 'ga'], true) ? (string)$_GET['sec'] : 'insights';
$per = an_period($_GET);
$acc = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['a'] ?? ''));
$pid = zernio_profile();

/* ---------- تفاصيل منشور (JSON) لما المستخدم يفتح صف في الجدول ---------- */
if (isset($_GET['detail'])) {
    header('Content-Type: application/json; charset=utf-8');
    $postId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_GET['detail']);
    $pAcc = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['acc'] ?? ''));
    $pPl = preg_replace('/[^a-z]/', '', (string)($_GET['pl'] ?? ''));
    $ppid = preg_replace('/[^a-zA-Z0-9_:\-\.]/', '', (string)($_GET['ppid'] ?? ''));
    $out = ['timeline' => [], 'timelineError' => null, 'reactions' => null, 'reactionsError' => null];
    if (!$z || $postId === '') {
        echo json_encode($out);
        exit;
    }
    [$tl, $out['timelineError']] = an_try(function () use ($z, $postId) { return an_post_timeline($z, $postId); });
    $byDate = [];
    foreach ((array)$tl as $r) {
        $d = substr((string)($r['date'] ?? ''), 0, 10);
        if ($d === '') continue;
        $byDate[$d] = $byDate[$d] ?? ['date' => $d, 'label' => date('d/m', strtotime($d)), 'impressions' => 0, 'reach' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'clicks' => 0];
        foreach (['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks'] as $k) $byDate[$d][$k] += (float)($r[$k] ?? 0);
    }
    ksort($byDate);
    $out['timeline'] = array_values($byDate);
    if ($pAcc !== '' && $ppid !== '' && in_array($pPl, ['facebook', 'linkedin'], true)) {
        [$re, $out['reactionsError']] = an_try(function () use ($z, $pPl, $pAcc, $ppid) { return an_post_reactions($z, $pPl, $pAcc, $ppid); });
        if (is_array($re)) {
            $labels = [
                'like' => t('إعجاب'), 'love' => t('حب'), 'haha' => t('ضحك'), 'wow' => t('اندهاش'), 'sad' => t('حزن'), 'angry' => t('غضب'), 'care' => t('اهتمام'),
                'LIKE' => t('إعجاب'), 'PRAISE' => t('احتفاء'), 'EMPATHY' => t('حب'), 'INTEREST' => t('اهتمام'), 'APPRECIATION' => t('دعم'), 'ENTERTAINMENT' => t('ضحك'), 'MAYBE' => t('فضول'),
            ];
            $bd = [];
            if ($pPl === 'facebook') {
                foreach ((array)($re['breakdown'] ?? []) as $k => $v) if ((int)$v > 0) $bd[$labels[$k] ?? $k] = (int)$v;
                $total = (int)($re['total'] ?? array_sum($bd));
            } else {
                foreach ((array)($re['reactions'] ?? []) as $r) {
                    $k = (string)($r['reactionType'] ?? '');
                    $l = $labels[$k] ?? ($r['reactionLabel'] ?? $k);
                    $bd[$l] = ($bd[$l] ?? 0) + 1;
                }
                $total = (int)($re['pagination']['total'] ?? array_sum($bd));
            }
            arsort($bd);
            $out['reactions'] = ['total' => $total, 'breakdown' => $bd];
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- حفظ وسوم منشور (محلي في قاعدة البيانات) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tags') {
    $postId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['post_id'] ?? ''));
    if ($postId !== '' && an_tags_available()) {
        an_set_tags($postId, explode(',', str_replace('،', ',', (string)($_POST['tags'] ?? ''))));
        log_activity('analytics.tags', $postId);
        flash('success', t('اتحفظت الوسوم.'));
    } else {
        flash('error', t('مش قادرين نحفظ الوسوم دلوقتي.'));
    }
    $back = (string)($_POST['back'] ?? '');
    redirect(strpos($back, 'index.php?p=analytics') === 0 ? $back : url('analytics', ['tab' => 'posts']));
}

/* ---------- تحميل البيانات حسب التبويب ---------- */
$D = [];   // البيانات
$E = [];   // رسائل الأخطاء لكل جزء
$accounts = [];
$accMap = [];
if ($z) {
    [$accounts, $E['accounts']] = an_try(function () use ($z) { return an_accounts($z); });
    $accounts = (array)$accounts;
    foreach ($accounts as $a) $accMap[(string)($a['_id'] ?? '')] = $a;
    if ($acc !== '' && !isset($accMap[$acc])) $acc = '';
}
$curAcc = $acc !== '' ? $accMap[$acc] : null;
$base = ['profileId' => $pid, 'accountId' => $acc ?: null, 'fromDate' => $per['from'], 'toDate' => $per['to']];
$prevBase = ['fromDate' => $per['pfrom'], 'toDate' => $per['pto']] + $base;
$folQ = ['profileId' => $pid, 'accountIds' => $acc ?: null, 'fromDate' => $per['from'], 'toDate' => $per['to'], 'granularity' => 'daily'];

/** كل منشورات الفترة (لحد 3 صفحات × 100) */
$collectPosts = function (int $maxPages = 3) use ($z, $base) {
    $all = [];
    $total = 0;
    for ($pg = 1; $pg <= $maxPages; $pg++) {
        $r = an_posts($z, $base + ['limit' => 100, 'page' => $pg, 'sortBy' => 'date', 'order' => 'desc']);
        $all = array_merge($all, $r['posts'] ?? []);
        $total = (int)($r['pagination']['total'] ?? count($all));
        if ($pg >= (int)($r['pagination']['pages'] ?? 1)) break;
    }
    return ['posts' => $all, 'total' => $total];
};

if ($z) {
    if ($tab === 'overview') {
        [$D['cur'], $E['cur']] = an_try(function () use ($z, $base) { return an_daily_full($z, $base); });
        [$D['prev'], $E['prev']] = an_try(function () use ($z, $prevBase) { return an_daily_full($z, $prevBase); });
        [$D['fol'], $E['fol']] = an_try(function () use ($z, $folQ) { return an_follower_stats($z, $folQ); });
        [$D['folPrev'], $E['folPrev']] = an_try(function () use ($z, $folQ, $per) { return an_follower_stats($z, ['fromDate' => $per['pfrom'], 'toDate' => $per['pto']] + $folQ); });
        [$D['top'], $E['top']] = an_try(function () use ($z, $base) { return an_posts($z, $base + ['limit' => 5, 'page' => 1, 'sortBy' => 'engagement', 'order' => 'desc']); });
        [$D['bottom'], $E['bottom']] = an_try(function () use ($z, $base) { return an_posts($z, $base + ['limit' => 5, 'page' => 1, 'sortBy' => 'engagement', 'order' => 'asc']); });
        [$D['freq'], $E['freq']] = an_try(function () use ($z, $pid, $acc) { return an_posting_frequency($z, ['profileId' => $pid, 'accountId' => $acc ?: null]); });
    } elseif ($tab === 'posts') {
        [$D['posts'], $E['posts']] = an_try(function () use ($collectPosts) { return $collectPosts(3); });
        // أنواع التفاعلات لأعلى منشورات فيسبوك/لينكدإن (في حدود الميزانية: 8 نداءات للصفحة)
        $D['reactions'] = [];
        $left = min(5, 8 - 1 - max(1, (int)ceil(count($D['posts']['posts'] ?? []) / 100)));
        $cand = array_values(array_filter((array)($D['posts']['posts'] ?? []), function ($p) { return in_array($p['platform'] ?? '', ['facebook', 'linkedin'], true) && !empty($p['platforms'][0]['platformPostId']); }));
        usort($cand, function ($a, $b) { return an_engagement((array)($b['analytics'] ?? [])) <=> an_engagement((array)($a['analytics'] ?? [])); });
        foreach (array_slice($cand, 0, max(0, $left)) as $p) {
            $pl0 = $p['platforms'][0];
            [$re, $rerr] = an_try(function () use ($z, $p, $pl0) { return an_post_reactions($z, (string)$p['platform'], (string)$pl0['accountId'], (string)$pl0['platformPostId']); });
            $D['reactions'][] = ['post' => $p, 'data' => $re, 'error' => $rerr];
        }
    } elseif ($tab === 'audience') {
        [$D['fol'], $E['fol']] = an_try(function () use ($z, $folQ) { return an_follower_stats($z, $folQ); });
        $D['demo'] = [];
        $budget = 6;
        $cw = (int)($_GET['cw'] ?? 0);
        $D['cw'] = null;
        if ($cw && $cw !== (int)ws('id')) {
            $oprof = null;
            $oz = an_other_workspace_client($cw, $oprof);
            $row = ['id' => $cw, 'error' => null];
            if (!$oz) {
                $row['error'] = t('الشركة دي مش مربوطة بخدمة النشر.');
            } else {
                $oq = ['profileId' => $oprof, 'fromDate' => $per['from'], 'toDate' => $per['to']];
                [$row['fol'], $row['folError']] = an_try(function () use ($oz, $oq) { return an_follower_stats($oz, $oq); });
                [$row['daily'], $row['dailyError']] = an_try(function () use ($oz, $oq) { return an_daily_full($oz, $oq); });
            }
            [$row['mine'], $row['mineError']] = an_try(function () use ($z, $base) { return an_daily_full($z, $base); });
            $D['cw'] = $row;
            $budget = 3;
        }
        foreach ($curAcc ? [$curAcc] : $accounts as $a) {
            $pl = (string)($a['platform'] ?? '');
            if (!in_array($pl, ['facebook', 'instagram', 'linkedin'], true)) continue;
            $id = (string)$a['_id'];
            if ($budget <= 0) { $D['demo'][$id] = ['skipped' => true]; continue; }
            $row = [];
            [$row['data'], $row['error']] = an_try(function () use ($z, $pl, $id) { return an_demographics($z, $pl, $id); });
            $budget--;
            if ($pl === 'instagram' && $budget > 0) {
                [$row['online'], $row['onlineError']] = an_try(function () use ($z, $id) { return an_ig_online($z, $id); });
                $budget--;
            }
            $D['demo'][$id] = $row;
        }
    } elseif ($tab === 'timing') {
        $tq = ['profileId' => $pid, 'accountId' => $acc ?: null];
        [$D['best'], $E['best']] = an_try(function () use ($z, $tq) { return an_best_time($z, $tq); });
        [$D['freq'], $E['freq']] = an_try(function () use ($z, $tq) { return an_posting_frequency($z, $tq); });
        [$D['decay'], $E['decay']] = an_try(function () use ($z, $tq) { return an_content_decay($z, $tq); });
        [$D['posts'], $E['posts']] = an_try(function () use ($collectPosts) { return $collectPosts(3); });
    } elseif ($tab === 'platforms' && in_array($sec, ['facebook', 'youtube', 'ga'], true)) {
        $want = ['facebook' => 'facebook', 'youtube' => 'youtube', 'ga' => 'googleanalytics'][$sec];
        $D['secAccounts'] = array_values(array_filter($curAcc ? [$curAcc] : $accounts, function ($a) use ($want) { return ($a['platform'] ?? '') === $want; }));
        $sa = $D['secAccounts'][0] ?? null;
        if ($sa && $sec === 'facebook') {
            $id = (string)$sa['_id'];
            [$D['tagged'], $E['tagged']] = an_try(function () use ($z, $id) { return an_fb_page_posts($z, $id, 'tagged'); });
            [$D['visitor'], $E['visitor']] = an_try(function () use ($z, $id) { return an_fb_page_posts($z, $id, 'visitor'); });
            [$fp, $E['fbPosts']] = an_try(function () use ($z, $base, $id) { return an_posts($z, ['accountId' => $id] + $base + ['limit' => 3, 'page' => 1, 'sortBy' => 'engagement', 'order' => 'desc']); });
            $D['earnings'] = [];
            foreach ((array)($fp['posts'] ?? []) as $p) {
                $ppid = (string)($p['platforms'][0]['platformPostId'] ?? '');
                if ($ppid === '') continue;
                [$er, $eerr] = an_try(function () use ($z, $id, $ppid) { return an_fb_earnings($z, $id, $ppid); });
                $D['earnings'][] = ['post' => $p, 'data' => $er, 'error' => $eerr];
            }
        } elseif ($sa && $sec === 'youtube') {
            $id = (string)$sa['_id'];
            [$yp, $E['ytPosts']] = an_try(function () use ($z, $base, $id) { return an_posts($z, ['accountId' => $id, 'fromDate' => gmdate('Y-m-d', time() - 365 * 86400)] + $base + ['limit' => 50, 'page' => 1, 'sortBy' => 'date', 'order' => 'desc']); });
            $D['videos'] = [];
            foreach ((array)($yp['posts'] ?? []) as $p) {
                $vid = (string)($p['platforms'][0]['platformPostId'] ?? '');
                if ($vid !== '') $D['videos'][$vid] = mb_substr(trim((string)($p['content'] ?? '')) ?: $vid, 0, 70);
            }
            $v = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['v'] ?? ''));
            $D['video'] = isset($D['videos'][$v]) ? $v : (string)(array_key_first($D['videos']) ?? '');
            if ($D['video'] !== '') {
                $vv = $D['video'];
                [$D['retention'], $E['retention']] = an_try(function () use ($z, $id, $vv) { return an_yt_retention($z, $id, $vv); });
                [$D['dviews'], $E['dviews']] = an_try(function () use ($z, $id, $vv, $per) { return an_yt_daily_views($z, $id, $vv, $per['from'], $per['to']); });
            }
        } elseif ($sa && $sec === 'ga') {
            $id = (string)$sa['_id'];
            [$D['ga'], $E['ga']] = an_try(function () use ($z, $id, $per) { return an_ga_social($z, $id, $per['from'], $per['to']); });
            [$D['gaPrev'], $E['gaPrev']] = an_try(function () use ($z, $id, $per) { return an_ga_social($z, $id, $per['pfrom'], $per['pto']); });
        }
    } elseif ($tab === 'platforms') {
        $D['native'] = [];
        $budget = 7;
        $paths = an_native_paths();
        foreach ($curAcc ? [$curAcc] : $accounts as $a) {
            $pl = (string)($a['platform'] ?? '');
            if (!isset($paths[$pl])) continue;
            $id = (string)$a['_id'];
            $need = $pl === 'googlebusiness' ? 3 : 2;
            if ($budget < $need) { $D['native'][$id] = ['skipped' => true]; continue; }
            $row = [];
            [$row['cur'], $row['error']] = an_try(function () use ($z, $pl, $id, $per) { return an_native_insights($z, $pl, $id, $per['from'], $per['to']); });
            [$row['prev'], $row['prevError']] = an_try(function () use ($z, $pl, $id, $per) { return an_native_insights($z, $pl, $id, $per['pfrom'], $per['pto']); });
            if ($pl === 'googlebusiness') {
                [$row['keywords'], $row['keywordsError']] = an_try(function () use ($z, $id, $per) { return an_gbp_keywords($z, $id, $per['from'], $per['to']); });
            }
            $budget -= $need;
            $D['native'][$id] = $row;
        }
    }
}

render(t('التحليلات'), 'analytics', function () use ($z, $tabs, $tab, $sec, $per, $acc, $curAcc, $accounts, $accMap, $D, $E) {
    if (!$z) { no_api_box(); return; }

    $q = function (array $over = []) use ($tab, $sec, $per, $acc) {
        $p = ['tab' => $tab, 'a' => $acc];
        if ($per['custom']) { $p['from'] = $per['from']; $p['to'] = $per['to']; } else { $p['d'] = $per['days']; }
        if ($tab === 'platforms' && $sec !== 'insights') $p['sec'] = $sec;
        if ($tab === 'audience' && !empty($_GET['cw'])) $p['cw'] = (int)$_GET['cw'];
        $p = array_merge($p, $over);
        return url('analytics', array_filter($p, function ($v) { return $v !== '' && $v !== null; }));
    };
    $dmy = function (string $ymd) { return date('d/m/Y', strtotime($ymd)); };
    $accName = function (?array $a) { return $a ? trim((string)($a['displayName'] ?? $a['username'] ?? '')) : ''; };
    $mediaLabels = ['image' => t('صورة'), 'video' => t('فيديو'), 'carousel' => t('ألبوم صور'), 'text' => t('نص')];
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $colors = [
        'impressions' => 'rgb(var(--brand-600))', 'reach' => 'rgb(var(--cyan-500))', 'likes' => 'rgb(var(--lime-500))',
        'comments' => 'rgb(var(--amber-500))', 'shares' => 'rgb(var(--red-500))', 'clicks' => 'rgb(var(--ink-500))',
    ];
    $metricNames = ['impressions' => t('مرات الظهور'), 'reach' => t('الوصول'), 'likes' => t('إعجابات'), 'comments' => t('تعليقات'), 'shares' => t('مشاركات'), 'clicks' => t('نقرات')];
    ?>
    <script src="assets/vendor/echarts.min.js?v=5.5.1"></script>
    <script src="assets/charts.js?v=<?= e(APP_VERSION) ?>"></script>
    <div class="space-y-5">
      <!-- التبويبات -->
      <nav class="flex gap-1 overflow-x-auto border-b border-ink-200" aria-label="<?= e(t('أقسام التحليلات')) ?>">
        <?php foreach ($tabs as $k => $label): ?>
          <a href="<?= e($q(['tab' => $k])) ?>" class="px-4 py-2.5 text-sm font-semibold whitespace-nowrap border-b-2 -mb-px <?= $k === $tab ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-800' ?>" <?= $k === $tab ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- الفلاتر: شريط واحد مدمج بيفضل ظاهر وانت بتنزل -->
      <div id="an-bar" class="sticky top-16 z-30 -mx-1 px-1 py-2 bg-ink-50/85 backdrop-blur supports-[backdrop-filter]:bg-ink-50/70">
        <div class="card px-3 py-2 flex flex-wrap items-center gap-2">
          <div class="tb p-0.5 gap-0.5" role="group" aria-label="<?= e(t('الفترة')) ?>">
            <?php foreach (an_ranges() as $i => $r): $on = !$per['custom'] && $per['days'] === $r; ?>
              <a href="<?= e($q(['d' => $r, 'from' => null, 'to' => null])) ?>" data-key="<?= $i + 1 ?>" class="h-8 px-3 rounded-lg text-xs flex items-center whitespace-nowrap <?= $on ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>"><?= e(t('آخر %s يوم', $r)) ?></a>
            <?php endforeach; ?>
          </div>
          <details class="relative" id="an-custom" <?= $per['custom'] ? '' : '' ?>>
            <summary class="tb h-9 px-3 gap-1.5 text-xs cursor-pointer list-none <?= $per['custom'] ? '!border-brand-500 !bg-brand-50 !text-brand-700 font-medium' : '' ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h16v14H4zM4 10h16M9 3v4M15 3v4"/></svg><bdi dir="ltr"><?= e($dmy($per['from']) . ' – ' . $dmy($per['to'])) ?></bdi></summary>
            <form method="get" class="absolute z-40 mt-2 w-[min(22rem,90vw)] rounded-2xl border border-ink-200 bg-white shadow-xl p-4 space-y-3">
              <input type="hidden" name="p" value="analytics">
              <input type="hidden" name="tab" value="<?= e($tab) ?>">
              <?php if ($acc !== ''): ?><input type="hidden" name="a" value="<?= e($acc) ?>"><?php endif; ?>
              <div class="grid grid-cols-2 gap-2">
                <label class="block"><span class="label"><?= e(t('تاريخ البداية')) ?></span><input id="an-from" type="date" name="from" class="input !mt-1 py-1.5" value="<?= e($per['from']) ?>" max="<?= e(gmdate('Y-m-d')) ?>"></label>
                <label class="block"><span class="label"><?= e(t('تاريخ النهاية')) ?></span><input id="an-to" type="date" name="to" class="input !mt-1 py-1.5" value="<?= e($per['to']) ?>" max="<?= e(gmdate('Y-m-d')) ?>"></label>
              </div>
              <div class="flex flex-wrap gap-1.5" id="an-presets"></div>
              <button class="btn-primary w-full !py-2"><?= e(t('تطبيق')) ?></button>
            </form>
          </details>
          <details class="relative" id="an-acc">
            <summary class="tb h-9 px-3 gap-2 text-xs cursor-pointer list-none max-w-[16rem]">
              <?php if ($curAcc): ?>
                <?= avatar_with_platform($curAcc['profilePicture'] ?? null, $accName($curAcc), (string)($curAcc['platform'] ?? ''), 'w-6 h-6') ?>
                <span class="truncate"><?= e($accName($curAcc)) ?></span>
              <?php else: ?>
                <span class="flex -space-x-1 rtl:space-x-reverse">
                  <?php foreach (array_slice(array_unique(array_map(function ($a) { return (string)($a['platform'] ?? ''); }, $accounts)), 0, 4) as $pl): ?><?= platform_icon($pl, 'w-4 h-4') ?><?php endforeach; ?>
                </span>
                <span><?= e(t('كل الحسابات')) ?></span>
              <?php endif; ?>
              <span class="ms-1 text-ink-400" aria-hidden="true">▾</span>
            </summary>
            <div class="absolute z-40 mt-2 w-72 max-h-80 overflow-auto rounded-xl border border-ink-200 bg-white shadow-xl p-1">
              <a href="<?= e($q(['a' => null])) ?>" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-ink-50 <?= $acc === '' ? 'font-semibold text-brand-700' : 'text-ink-800' ?>"><?= e(t('كل الحسابات')) ?></a>
              <?php foreach ($accounts as $a): $id = (string)($a['_id'] ?? ''); ?>
                <a href="<?= e($q(['a' => $id])) ?>" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm hover:bg-ink-50 <?= $acc === $id ? 'font-semibold text-brand-700' : 'text-ink-800' ?>">
                  <?= avatar_with_platform($a['profilePicture'] ?? null, $accName($a), (string)($a['platform'] ?? ''), 'w-7 h-7') ?>
                  <span class="min-w-0"><span class="block truncate"><?= e($accName($a)) ?></span><span class="block text-[11px] text-ink-500"><?= e(platform_label((string)($a['platform'] ?? ''))) ?></span></span>
                </a>
              <?php endforeach; ?>
            </div>
          </details>
          <span class="text-[11px] text-ink-500 ms-auto hidden md:inline"><?= e(t('بنقارن بالفترة اللي قبلها:')) ?> <bdi dir="ltr"><?= e($dmy($per['pfrom']) . ' – ' . $dmy($per['pto'])) ?></bdi></span>
          <button type="button" id="an-help" class="tb w-9 h-9 justify-center text-sm font-semibold text-ink-500" title="<?= e(t('اختصارات الكيبورد')) ?> (?)">?</button>
        </div>
      </div>
      <?php if (!empty($E['accounts'])): ?><?= an_notice($E['accounts']) ?><?php endif; ?>

      <?php
      /* =========================== نظرة عامة =========================== */
      if ($tab === 'overview'):
          $dates = an_date_list($per['from'], $per['to']);
          $pdates = an_date_list($per['pfrom'], $per['pto']);
          $sumDaily = function ($resp) {
              $t = ['impressions' => 0, 'reach' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'clicks' => 0, 'views' => 0, 'postCount' => 0];
              $by = [];
              foreach ((array)($resp['dailyData'] ?? []) as $d) {
                  $m = (array)($d['metrics'] ?? []);
                  foreach ($t as $k => $_) $t[$k] += $k === 'postCount' ? (int)($d['postCount'] ?? 0) : (float)($m[$k] ?? 0);
                  $by[substr((string)($d['date'] ?? ''), 0, 10)] = $m + ['postCount' => (int)($d['postCount'] ?? 0)];
              }
              $t['engagement'] = an_engagement($t);
              $t['rate'] = an_rate($t['engagement'], $t['impressions']);
              return [$t, $by];
          };
          [$cur, $curBy] = $sumDaily($D['cur']);
          [$prev, $prevBy] = $sumDaily($D['prev']);
          $series = function ($by, $dates, $fn) { return array_map(function ($d) use ($by, $fn) { return $fn($by[$d] ?? []); }, $dates); };
          // المتابعين: الصافي من growth، والمنحنى من فرق الأيام
          $folGain = function ($resp, $dates) {
              $total = 0; $daily = array_fill_keys($dates, 0);
              foreach ((array)($resp['accounts'] ?? []) as $a) $total += (float)($a['growth'] ?? 0);
              foreach ((array)($resp['stats'] ?? []) as $pts) {
                  $last = null;
                  foreach ((array)$pts as $p) {
                      $d = substr((string)($p['date'] ?? ''), 0, 10);
                      $v = (float)($p['followers'] ?? 0);
                      if ($last !== null && isset($daily[$d])) $daily[$d] += $v - $last;
                      $last = $v;
                  }
              }
              return [$total, array_values($daily)];
          };
          [$fg, $fgDaily] = $folGain($D['fol'], $dates);
          [$fgPrev] = $folGain($D['folPrev'], $pdates);
          $kpis = [
              ['m' => 'impressions', 'label' => t('مرات الظهور'), 'cur' => $cur['impressions'], 'prev' => $prev['impressions'], 'spark' => $series($curBy, $dates, function ($m) { return $m['impressions'] ?? 0; }), 'color' => 'text-brand-600', 'err' => $E['cur']],
              ['m' => 'reach', 'label' => t('الوصول'), 'cur' => $cur['reach'], 'prev' => $prev['reach'], 'spark' => $series($curBy, $dates, function ($m) { return $m['reach'] ?? 0; }), 'color' => 'text-cyan-500', 'err' => $E['cur']],
              ['m' => 'likes,comments,shares', 'label' => t('التفاعل'), 'cur' => $cur['engagement'], 'prev' => $prev['engagement'], 'spark' => $series($curBy, $dates, function ($m) { return an_engagement($m); }), 'color' => 'text-lime-500', 'err' => $E['cur'], 'hint' => t('إعجابات + تعليقات + مشاركات + حفظ')],
              ['label' => t('معدل التفاعل'), 'cur' => $cur['rate'], 'prev' => $prev['rate'], 'spark' => $series($curBy, $dates, function ($m) { return an_rate(an_engagement($m), (float)($m['impressions'] ?? 0)); }), 'color' => 'text-amber-500', 'err' => $E['cur'], 'pct' => true, 'hint' => t('التفاعل ÷ مرات الظهور')],
              ['label' => t('متابعين جداد'), 'cur' => $fg, 'prev' => $fgPrev, 'spark' => $fgDaily, 'color' => 'text-brand-500', 'err' => $E['fol'], 'signed' => true],
              ['label' => t('منشورات اتنشرت'), 'cur' => $cur['postCount'], 'prev' => $prev['postCount'], 'spark' => $series($curBy, $dates, function ($m) { return $m['postCount'] ?? 0; }), 'color' => 'text-ink-500', 'err' => $E['cur']],
          ];
          ?>
          <?php
          // ---- صحة الحساب والانخفاض المفاجئ ----
          $folNow = an_follower_totals($D['fol']);
          $folBefore = an_follower_totals($D['folPrev']);
          $scope = $curAcc ? [$curAcc] : $accounts;
          $bad = count(array_filter($scope, function ($a) { return !empty($a['needsReconnection']) || (isset($a['isActive']) && !$a['isActive']); }));
          $bestF = $E['freq'] ? null : an_best_frequency((array)$D['freq']);
          $ppw = $cur['postCount'] / max(1, $per['len'] / 7);
          $health = an_health_score(['followers' => $folNow['current'], 'growth' => $fg, 'rate' => $cur['rate'], 'ratePrev' => $prev['rate'],
              'postsPerWeek' => $ppw, 'bestFrequency' => $bestF, 'accounts' => max(1, count($scope)), 'needsReconnect' => $bad]);
          $drops = ($E['cur'] || $E['prev']) ? [] : an_drop_signals_from($cur, $prev, $E['fol'] ? [] : $folNow, $E['folPrev'] ? [] : $folBefore);
          $dropNames = ['impressions' => t('مرات الظهور'), 'followers' => t('المتابعين'), 'followers_gained' => t('المتابعين الجداد')];
          $hs = $health['score'];
          $hc = $hs >= 70 ? 'lime' : ($hs >= 40 ? 'amber' : 'red');
          $partNames = ['growth' => t('نمو المتابعين'), 'engagement' => t('معدل التفاعل مقارنة بالفترة السابقة'), 'consistency' => t('انتظام النشر'), 'connection' => t('حالة ربط الحسابات')];
          $partHint = function ($p) {
              switch ($p[0]) {
                  case 'growth': return t('نمو %s%% في الفترة (ثابت = نص الدرجة، +2%% أو أكتر = الدرجة كاملة).', number_format((float)$p[3], 2));
                  case 'engagement': return $p[3] === null ? t('مفيش فترة سابقة للمقارنة.') : t('الفرق عن الفترة السابقة %s نقطة (نفس المعدل = نص الدرجة، الضعف = الدرجة كاملة).', number_format((float)$p[3], 2));
                  case 'consistency': return t('%s منشور في الأسبوع من أفضل عدد %s.', number_format((float)$p[3][0], 1), number_format((float)$p[3][1], 0));
                  default: return $p[3] ? t('%s حساب محتاج إعادة ربط.', (int)$p[3]) : t('كل الحسابات مربوطة وشغالة.');
              }
          };
          ?>
          <?php if ($drops): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 p-4 text-sm flex gap-3" role="alert">
              <?= ui_icon('alert', 'w-5 h-5 text-amber-600') ?>
              <div>
                <p class="font-bold"><?= e(t('انخفاض مفاجئ مقارنة بالفترة السابقة')) ?></p>
                <ul class="mt-1 space-y-0.5">
                  <?php foreach ($drops as $d): ?>
                    <li><?= e(t('%s نزلت %s%% (من %s إلى %s).', $dropNames[$d['metric']] ?? $d['metric'], number_format(abs($d['change']), 1), an_num($d['previous']), an_num($d['current']))) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          <?php endif; ?>
          <section class="card p-5 flex flex-col sm:flex-row gap-5 sm:items-start">
            <div class="flex items-center gap-4 shrink-0">
              <div class="relative w-20 h-20" role="img" aria-label="<?= e(t('درجة صحة الحساب %s من 100', $hs)) ?>">
                <svg viewBox="0 0 36 36" class="w-20 h-20 -rotate-90"><circle cx="18" cy="18" r="15.9" fill="none" stroke="rgb(var(--ink-200))" stroke-width="3.5"/>
                  <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgb(var(--<?= $hc ?>-500))" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="<?= $hs ?> 100" pathLength="100"/></svg>
                <span class="absolute inset-0 flex items-center justify-center text-xl font-bold text-ink-900 tabular-nums"><?= $hs ?></span>
              </div>
              <div>
                <h2 class="font-bold text-ink-900"><?= e(t('صحة الحساب')) ?></h2>
                <p class="text-xs text-ink-500"><?= e($curAcc ? $accName($curAcc) : t('كل الحسابات')) ?></p>
                <p class="text-sm font-semibold text-<?= $hc ?>-700 mt-1"><?= e($hs >= 70 ? t('ممتازة') : ($hs >= 40 ? t('محتاجة اهتمام') : t('ضعيفة'))) ?></p>
              </div>
            </div>
            <details class="flex-1 min-w-0" open>
              <summary class="text-sm link cursor-pointer"><?= e(t('الدرجة محسوبة إزاي؟')) ?></summary>
              <ul class="mt-3 grid sm:grid-cols-2 gap-3">
                <?php foreach ($health['parts'] as $hp): ?>
                  <li class="rounded-lg bg-ink-50 p-3">
                    <div class="flex justify-between text-xs gap-2"><span class="font-semibold text-ink-800"><?= e($partNames[$hp[0]]) ?></span><span class="tabular-nums text-ink-600" dir="ltr"><?= e($hp[1] . ' / ' . $hp[2]) ?></span></div>
                    <?= an_hbar((float)$hp[1], (float)$hp[2], 'bg-' . $hc . '-500') ?>
                    <p class="text-[11px] text-ink-500 mt-1"><?= e($partHint($hp)) ?></p>
                  </li>
                <?php endforeach; ?>
              </ul>
            </details>
          </section>

          <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
            <?php foreach ($kpis as $k): ?>
              <div class="an-kpi card p-4 flex flex-col transition <?= !empty($k['m']) && !$k['err'] ? 'cursor-pointer hover:border-brand-300 hover:shadow-md hover:-translate-y-0.5' : '' ?>" <?= !empty($k['m']) && !$k['err'] ? 'data-metric="' . e($k['m']) . '" role="button" tabindex="0"' : '' ?> <?= !empty($k['hint']) ? 'title="' . e($k['hint']) . '"' : '' ?>>
                <p class="text-xs text-ink-500"><?= e($k['label']) ?></p>
                <?php if ($k['err']): ?>
                  <p class="text-sm text-ink-400 mt-2"><?= e(t('مش متاح')) ?></p>
                <?php else: ?>
                  <p class="text-2xl font-bold text-ink-900 tabular-nums mt-1 text-start"><bdi dir="ltr"><?= !empty($k['pct']) ? e(number_format($k['cur'], 2)) . '%' : e((!empty($k['signed']) && $k['cur'] > 0 ? '+' : '') . an_num($k['cur'])) ?></bdi></p>
                  <div class="flex items-center gap-1.5 whitespace-nowrap"><?= an_delta((float)$k['cur'], (float)$k['prev'], !empty($k['pct'])) ?><span class="sr-only"><?= e(t('عن الفترة السابقة')) ?></span></div>
                  <div class="mt-auto pt-2"><?= an_sparkline($k['spark'], $k['color']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <section class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
              <h2 class="font-bold text-ink-900 flex items-center gap-2"><?= e(t('الأداء يوم بيوم')) ?>
                <span class="flex gap-1 ms-2"><button type="button" data-export="csv" class="tb !h-7 px-2 text-[11px]" title="<?= e(t('تنزيل البيانات (CSV)')) ?>">CSV</button><button type="button" data-export="png" class="tb !h-7 px-2 text-[11px]" title="<?= e(t('تنزيل الرسم كصورة')) ?>">PNG</button></span></h2>
              <div class="flex flex-wrap gap-1.5" id="an-daily-toggles" role="group" aria-label="<?= e(t('المقاييس المعروضة')) ?>">
                <?php foreach ($metricNames as $k => $label): ?>
                  <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-ink-200 text-xs cursor-pointer has-[:checked]:bg-ink-50 select-none">
                    <input type="checkbox" class="sr-only peer" data-k="<?= e($k) ?>" <?= in_array($k, ['impressions', 'reach'], true) ? 'checked' : '' ?>>
                    <span class="w-2.5 h-2.5 rounded-full opacity-30 peer-checked:opacity-100" style="background:<?= e($colors[$k]) ?>"></span>
                    <span class="text-ink-500 peer-checked:text-ink-900"><?= e($label) ?></span>
                  </label>
                <?php endforeach; ?>
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-dashed border-ink-300 text-xs cursor-pointer select-none">
                  <input type="checkbox" class="sr-only peer" data-k="prev">
                  <span class="w-3 border-t-2 border-dashed border-ink-400 opacity-40 peer-checked:opacity-100"></span>
                  <span class="text-ink-500 peer-checked:text-ink-900"><?= e(t('الفترة السابقة (ظهور)')) ?></span>
                </label>
              </div>
            </div>
            <?php if ($E['cur']): ?>
              <?= an_notice($E['cur']) ?>
            <?php elseif (!$curBy): ?>
              <?= an_empty(t('مفيش بيانات في الفترة دي.'), t('جرب فترة أطول أو حساب تاني.')) ?>
            <?php else:
              $chart = ['labels' => array_map(function ($d) { return date('d/m', strtotime($d)); }, $dates), 'series' => []];
              foreach ($metricNames as $k => $label) {
                  $chart['series'][] = ['key' => $k, 'label' => $label, 'color' => $colors[$k], 'hidden' => !in_array($k, ['impressions', 'reach'], true), 'values' => $series($curBy, $dates, function ($m) use ($k) { return (float)($m[$k] ?? 0); })];
              }
              $chart['series'][] = ['key' => 'prev', 'label' => t('الفترة السابقة (ظهور)'), 'color' => 'rgb(var(--ink-400))', 'dash' => true, 'hidden' => true, 'values' => $series($prevBy, $pdates, function ($m) { return (float)($m['impressions'] ?? 0); })];
              $chart['extra'] = [['label' => t('منشورات'), 'values' => $series($curBy, $dates, function ($m) { return (int)($m['postCount'] ?? 0); })]];
              ?>
              <div id="an-daily" class="w-full"></div>
              <p class="text-[11px] text-ink-400 mt-1"><?= e(t('عدّي بالماوس على أي يوم عشان تشوف أرقامه، أو استخدم الأسهم بعد ما تختار الرسم.')) ?></p>
              <script type="application/json" id="an-daily-data"><?= json_encode($chart, $jsonFlags) ?></script>
              <script>
                (function () {
                  var cfg = JSON.parse(document.getElementById('an-daily-data').textContent);
                  cfg.aria = <?= json_encode(t('رسم الأداء اليومي'), $jsonFlags) ?>;
                  var c = AnCharts.line(document.getElementById('an-daily'), cfg); window.__anDaily = { chart: c, cfg: cfg };
                  document.querySelectorAll('#an-daily-toggles input').forEach(function (i) {
                    i.addEventListener('change', function () { c.toggle(i.dataset.k, i.checked); });
                  });
                })();
              </script>
            <?php endif; ?>
          </section>

          <div class="grid lg:grid-cols-3 gap-5 [&>*]:min-w-0">
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-3"><?= e(t('حسب المنصة')) ?></h2>
              <?php
              $pb = (array)($D['cur']['platformBreakdown'] ?? []);
              if ($E['cur']): ?>
                <?= an_notice($E['cur']) ?>
              <?php elseif (!$pb): ?>
                <?= an_empty(t('مفيش بيانات منصات في الفترة دي.')) ?>
              <?php else:
                $ti = 0; $te = 0;
                foreach ($pb as $r) { $ti += (float)($r['impressions'] ?? 0); $te += an_engagement($r); }
                usort($pb, function ($a, $b) { return ($b['impressions'] ?? 0) <=> ($a['impressions'] ?? 0); });
                $pcol = ['bg-brand-600', 'bg-cyan-500', 'bg-lime-500', 'bg-amber-500', 'bg-red-500', 'bg-ink-400'];
                ?>
                <?php foreach (['impressions' => [t('نصيب مرات الظهور'), $ti], 'engagement' => [t('نصيب التفاعل'), $te]] as $mk => [$ml, $mt]): ?>
                  <p class="text-xs text-ink-500 mb-1"><?= e($ml) ?></p>
                  <div class="flex h-3 rounded-full overflow-hidden bg-ink-100 mb-3" dir="ltr">
                    <?php foreach ($pb as $i => $r): $v = $mk === 'engagement' ? an_engagement($r) : (float)($r['impressions'] ?? 0); if ($mt <= 0 || $v <= 0) continue; ?>
                      <div class="<?= $pcol[$i % 6] ?>" style="width:<?= round($v / $mt * 100, 2) ?>%" title="<?= e(platform_label((string)$r['platform']) . ': ' . number_format($v / $mt * 100, 1) . '%') ?>"></div>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
                <ul class="space-y-2 text-sm">
                  <?php foreach ($pb as $i => $r): $imp = (float)($r['impressions'] ?? 0); $eng = an_engagement($r); ?>
                    <li class="flex items-center gap-2">
                      <span class="w-2.5 h-2.5 rounded-full <?= $pcol[$i % 6] ?>"></span>
                      <?= platform_icon((string)$r['platform'], 'w-4 h-4') ?>
                      <span class="font-medium text-ink-800"><?= e(platform_label((string)$r['platform'])) ?></span>
                      <span class="ms-auto text-xs text-ink-500 tabular-nums" dir="ltr"><?= e(an_num($imp)) ?> · <?= e($ti > 0 ? number_format($imp / $ti * 100, 1) : 0) ?>%</span>
                      <span class="text-xs text-ink-500 tabular-nums w-20 text-end" dir="ltr"><?= e(t('%s تفاعل', an_num($eng))) ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </section>

            <?php
            $topList = (array)($D['top']['posts'] ?? []);
            $topIds = array_map(function ($p) { return $p['_id'] ?? ''; }, $topList);
            $botList = array_values(array_filter((array)($D['bottom']['posts'] ?? []), function ($p) use ($topIds) { return !in_array($p['_id'] ?? '', $topIds, true); }));
            $postItem = function (array $p, int $i, bool $good) use ($accMap) {
                $an = (array)($p['analytics'] ?? []);
                $pl = (string)($p['platform'] ?? '');
                ?>
                <li class="flex gap-3">
                  <span class="w-6 h-6 shrink-0 rounded-full <?= $good ? 'bg-lime-100 text-lime-800' : 'bg-red-50 text-red-700' ?> text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span>
                  <?php if (!empty($p['thumbnailUrl'])): ?><img src="<?= e($p['thumbnailUrl']) ?>" alt="" class="w-11 h-11 rounded-lg object-cover bg-ink-100 shrink-0" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm text-ink-800 line-clamp-2"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 140)) ?: e(t('(منشور بدون نص)')) ?></p>
                    <p class="text-[11px] text-ink-500 mt-1 flex flex-wrap items-center gap-x-1.5">
                      <?= platform_icon($pl, 'w-3.5 h-3.5') ?>
                      <span><?= e(fmt_date($p['publishedAt'] ?? null, 'd/m/Y')) ?></span> ·
                      <span class="font-semibold text-ink-700"><?= e(t('تفاعل %s%%', number_format((float)($an['engagementRate'] ?? 0), 2))) ?></span> ·
                      <span><?= e(t('%s ظهور', an_num($an['impressions'] ?? 0))) ?></span>
                      <?php if (!empty($p['platformPostUrl'])): ?> · <a class="link" href="<?= e($p['platformPostUrl']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php endif; ?>
                    </p>
                  </div>
                </li>
                <?php
            };
            ?>
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-3"><?= e(t('أفضل 5 منشورات')) ?></h2>
              <?php if ($E['top']): ?><?= an_notice($E['top']) ?>
              <?php elseif (!$topList): ?><?= an_empty(t('مفيش منشورات في الفترة دي.')) ?>
              <?php else: ?><ol class="space-y-3"><?php foreach ($topList as $i => $p) $postItem($p, $i, true); ?></ol><?php endif; ?>
            </section>
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-3"><?= e(t('أضعف 5 منشورات')) ?></h2>
              <?php if ($E['bottom']): ?><?= an_notice($E['bottom']) ?>
              <?php elseif (!$botList): ?><?= an_empty(t('مفيش منشورات كفاية للمقارنة.'), t('القائمة دي بتظهر لما يكون عندك أكتر من 5 منشورات في الفترة.')) ?>
              <?php else: ?><ol class="space-y-3"><?php foreach ($botList as $i => $p) $postItem($p, $i, false); ?></ol><?php endif; ?>
            </section>
          </div>

      <?php
      /* =========================== المنشورات =========================== */
      elseif ($tab === 'posts'):
          $posts = (array)($D['posts']['posts'] ?? []);
          $tagsMap = an_tags_map();
          $rows = [];
          foreach ($posts as $p) {
              $an = (array)($p['analytics'] ?? []);
              $pl0 = (array)(($p['platforms'] ?? [])[0] ?? []);
              $aid = (string)($pl0['accountId'] ?? '');
              $extra = [];
              foreach (['saves' => t('حفظ'), 'views' => t('مشاهدات'), 'reposts' => t('إعادة نشر'), 'profileViews' => t('زيارات البروفايل'), 'websiteClicks' => t('نقرات الموقع'), 'follows' => t('متابعات'), 'replays' => t('إعادة تشغيل')] as $k => $l) {
                  if ((float)($an[$k] ?? 0) > 0) $extra[] = [$l, (float)$an[$k]];
              }
              $rows[] = [
                  'id' => (string)($p['_id'] ?? ''),
                  'text' => mb_substr(trim((string)($p['content'] ?? '')), 0, 280),
                  'platform' => (string)($p['platform'] ?? ''),
                  'platformLabel' => platform_label((string)($p['platform'] ?? '')),
                  'icon' => platform_icon((string)($p['platform'] ?? ''), 'w-4 h-4'),
                  'account' => (string)($pl0['accountUsername'] ?? ($accMap[$aid]['displayName'] ?? '')),
                  'accountId' => $aid,
                  'ppid' => (string)($pl0['platformPostId'] ?? ''),
                  'media' => (string)($p['mediaType'] ?? 'text') ?: 'text',
                  'thumb' => (string)($p['thumbnailUrl'] ?? ''),
                  'url' => (string)($p['platformPostUrl'] ?? ''),
                  'date' => substr(fmt_date($p['publishedAt'] ?? null, 'Y-m-d'), 0, 10),
                  'dateLabel' => fmt_date($p['publishedAt'] ?? null, 'd/m/Y h:i A'),
                  'impressions' => (float)($an['impressions'] ?? 0), 'reach' => (float)($an['reach'] ?? 0),
                  'likes' => (float)($an['likes'] ?? 0), 'comments' => (float)($an['comments'] ?? 0),
                  'shares' => (float)($an['shares'] ?? 0), 'clicks' => (float)($an['clicks'] ?? 0),
                  'engagement' => an_engagement($an), 'rate' => (float)($an['engagementRate'] ?? an_rate(an_engagement($an), (float)($an['impressions'] ?? 0))),
                  'extra' => $extra,
                  'sync' => (string)($p['syncStatus'] ?? ''),
                  'tags' => $tagsMap[(string)($p['_id'] ?? '')] ?? [],
              ];
          }
          $platforms = array_values(array_unique(array_column($rows, 'platform')));
          $medias = array_values(array_unique(array_column($rows, 'media')));
          $L = [
              'post' => t('المنشور'), 'date' => t('التاريخ'), 'impressions' => t('مرات الظهور'), 'reach' => t('الوصول'),
              'likes' => t('إعجابات'), 'comments' => t('تعليقات'), 'shares' => t('مشاركات'), 'clicks' => t('نقرات'),
              'engagement' => t('التفاعل'), 'rate' => t('معدل التفاعل'), 'platform' => t('المنصة'), 'account' => t('الحساب'), 'media' => t('النوع'),
              'noText' => t('(منشور بدون نص)'), 'open' => t('فتح ↗'), 'loading' => t('بنحمّل التفاصيل...'),
              'growth' => t('نمو المنشور مع الوقت'), 'noTimeline' => t('لسه مفيش تاريخ يومي للمنشور ده.'),
              'reactions' => t('التفاعلات حسب النوع'), 'noReactions' => t('مفيش تفاصيل تفاعلات للمنصة دي.'),
              'breakdown' => t('تفاصيل المقاييس'), 'shown' => t('معروض %s من %s منشور'), 'more' => t('عرض المزيد'),
              'none' => t('مفيش منشورات بتطابق الفلتر.'), 'url' => t('الرابط'), 'pending' => t('الأرقام لسه بتتحدث'),
              'medias' => $mediaLabels, 'loadError' => t('حصلت مشكلة وإحنا بنجيب البيانات. جرب تاني بعد شوية.'),
              'select' => t('اختار للمقارنة'), 'compare' => t('قارن (%s)'), 'compareTitle' => t('مقارنة المنشورات'), 'close' => t('اقفل'),
              'pickHint' => t('اختار من 2 لـ 4 منشورات.'), 'published' => t('اتنشر'), 'tags' => t('الوسوم'), 'tagsHint' => t('افصل بين الوسوم بفاصلة'),
              'save' => t('حفظ'), 'noTags' => t('مفيش وسوم'), 'best' => t('الأعلى'),
          ];
          $tagStats = [];
          foreach ($rows as $r) foreach ($r['tags'] as $tg) { $tagStats[$tg][] = $r; }
          $allTags = array_keys($tagStats);
          sort($allTags);
          ?>
          <section class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
              <h2 class="font-bold text-ink-900"><?= e(t('كل المنشورات')) ?></h2>
              <?php if ($rows): ?><div class="flex flex-wrap gap-2"><button type="button" class="btn-primary text-sm hidden" id="an-cmp-btn"></button><button type="button" class="btn-ghost text-sm" id="an-csv">⤓ <?= e(t('تصدير CSV')) ?></button></div><?php endif; ?>
            </div>
            <?php if ($E['posts']): ?>
              <?= an_notice($E['posts']) ?>
            <?php elseif (!$rows): ?>
              <?= an_empty(t('مفيش منشورات في الفترة دي.'), t('جرب فترة أطول أو حساب تاني.')) ?>
            <?php else: ?>
              <div class="grid sm:grid-cols-2 lg:grid-cols-6 xl:grid-cols-7 gap-3 mb-4" id="an-pf">
                <div class="lg:col-span-2">
                  <label class="label" for="an-q"><?= e(t('بحث في النص')) ?></label>
                  <input id="an-q" type="search" class="input" placeholder="<?= e(t('اكتب كلمة...')) ?>">
                </div>
                <div>
                  <label class="label" for="an-fpl"><?= e(t('المنصة')) ?></label>
                  <select id="an-fpl" class="input"><option value=""><?= e(t('الكل')) ?></option>
                    <?php foreach ($platforms as $pl): ?><option value="<?= e($pl) ?>"><?= e(platform_label($pl)) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="label" for="an-fm"><?= e(t('النوع')) ?></label>
                  <select id="an-fm" class="input"><option value=""><?= e(t('الكل')) ?></option>
                    <?php foreach ($medias as $m): ?><option value="<?= e($m) ?>"><?= e($mediaLabels[$m] ?? $m) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <?php if ($allTags): ?>
                <div>
                  <label class="label" for="an-ft"><?= e(t('الوسم')) ?></label>
                  <select id="an-ft" class="input"><option value=""><?= e(t('الكل')) ?></option>
                    <?php foreach ($allTags as $tg): ?><option value="<?= e($tg) ?>"><?= e($tg) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <?php endif; ?>
                <div class="flex gap-2 lg:col-span-2 min-w-0">
                  <div class="flex-1 min-w-0"><label class="label" for="an-df"><?= e(t('تاريخ البداية')) ?></label><input id="an-df" type="date" class="input px-2" min="<?= e($per['from']) ?>" max="<?= e($per['to']) ?>"></div>
                  <div class="flex-1 min-w-0"><label class="label" for="an-dt"><?= e(t('تاريخ النهاية')) ?></label><input id="an-dt" type="date" class="input px-2" min="<?= e($per['from']) ?>" max="<?= e($per['to']) ?>"></div>
                </div>
              </div>
              <?php if ((int)($D['posts']['total'] ?? 0) > count($rows)): ?>
                <p class="text-xs text-amber-700 mb-2"><?= e(t('معروض أحدث %s منشور من %s. ضيّق الفترة عشان تشوف الباقي.', count($rows), (int)$D['posts']['total'])) ?></p>
              <?php endif; ?>
              <div id="an-cmp" class="hidden mb-4 rounded-xl border border-brand-200 bg-brand-50/40 p-4"></div>
              <p class="text-xs text-ink-500 mb-2"><span id="an-count"></span> · <?= e(t('دوس على عنوان العمود عشان ترتب بيه، ودوس على أي منشور عشان تشوف تفاصيله.')) ?></p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm" id="an-table">
                  <thead><tr class="text-ink-500 text-xs border-b border-ink-200"></tr></thead>
                  <tbody></tbody>
                </table>
              </div>
              <div class="text-center mt-3"><button type="button" class="btn-ghost text-sm hidden" id="an-more"></button></div>
              <script type="application/json" id="an-posts-data"><?= json_encode(['rows' => $rows, 'L' => $L, 'detailUrl' => url('analytics', ['detail' => '__ID__']), 'csrf' => csrf_token(), 'back' => $q(), 'tagsOk' => an_tags_available()], $jsonFlags) ?></script>
              <script>
              (function () {
                var D = JSON.parse(document.getElementById('an-posts-data').textContent), L = D.L, rows = D.rows;
                var sel = {};
                var cols = [['sel', 0], ['post', 0], ['date', 0], ['impressions', 1], ['reach', 1], ['likes', 1], ['comments', 1], ['shares', 1], ['clicks', 1], ['engagement', 1], ['rate', 1]];
                var sort = 'date', dir = -1, limit = 25, open = {};
                var $ = function (id) { return document.getElementById(id); };
                var head = document.querySelector('#an-table thead tr'), body = document.querySelector('#an-table tbody');
                function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
                function num(v) { return AnCharts.fmt(v); }
                function filtered() {
                  var q = $('an-q').value.trim().toLowerCase(), pl = $('an-fpl').value, m = $('an-fm').value, df = $('an-df').value, dt = $('an-dt').value, tg = $('an-ft') ? $('an-ft').value : '';
                  var r = rows.filter(function (x) {
                    return (!tg || x.tags.indexOf(tg) >= 0) && (!q || (x.text + ' ' + x.account).toLowerCase().indexOf(q) >= 0) && (!pl || x.platform === pl) && (!m || x.media === m) && (!df || x.date >= df) && (!dt || x.date <= dt);
                  });
                  r.sort(function (a, b) {
                    var k = sort === 'post' ? 'text' : sort, va = a[k], vb = b[k];
                    return (va > vb ? 1 : va < vb ? -1 : 0) * dir;
                  });
                  return r;
                }
                function drawHead() {
                  head.innerHTML = cols.map(function (c) {
                    if (c[0] === 'sel') return '<th class="py-2 ps-2 w-6"><span class="sr-only">' + esc(L.select) + '</span></th>';
                    var on = sort === c[0];
                    return '<th class="py-2 px-2 font-semibold whitespace-nowrap text-start" aria-sort="' + (on ? (dir > 0 ? 'ascending' : 'descending') : 'none') + '"><button type="button" data-s="' + c[0] + '" class="' + (on ? 'text-brand-700' : 'hover:text-ink-800') + '">' + esc(L[c[0]]) + (on ? (dir > 0 ? ' ↑' : ' ↓') : '') + '</button></th>';
                  }).join('');
                }
                function draw() {
                  drawHead();
                  var r = filtered(), h = '';
                  r.slice(0, limit).forEach(function (x) {
                    h += '<tr class="border-b border-ink-100 align-top cursor-pointer hover:bg-ink-50" data-id="' + esc(x.id) + '" tabindex="0" aria-expanded="' + (open[x.id] ? 'true' : 'false') + '">' +
                      '<td class="py-2 ps-2 align-top"><input type="checkbox" class="an-sel mt-1" data-id="' + esc(x.id) + '"' + (sel[x.id] ? ' checked' : '') + (!sel[x.id] && Object.keys(sel).length >= 4 ? ' disabled' : '') + ' aria-label="' + esc(L.select) + '"></td>' +
                      '<td class="py-2 px-2 min-w-[16rem] max-w-md"><div class="flex gap-2">' +
                      (x.thumb ? '<img src="' + esc(x.thumb) + '" alt="" class="w-10 h-10 rounded-lg object-cover bg-ink-100 shrink-0" loading="lazy" referrerpolicy="no-referrer">' : '') +
                      '<div class="min-w-0"><p class="line-clamp-2 text-ink-800">' + (x.text ? esc(x.text) : '<span class="text-ink-400">' + esc(L.noText) + '</span>') + '</p>' +
                      '<p class="text-[11px] text-ink-500 flex flex-wrap items-center gap-1 mt-0.5">' + x.icon + '<span>' + esc(x.account || x.platformLabel) + '</span> · <span>' + esc(L.medias[x.media] || x.media) + '</span>' + x.tags.map(function (t) { return '<span class="badge !py-0 !text-[10px]">#' + esc(t) + '</span>'; }).join('') + '</p></div></div></td>' +
                      '<td class="py-2 px-2 whitespace-nowrap text-ink-600 text-xs">' + esc(x.dateLabel) + '</td>' +
                      ['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'engagement'].map(function (k) { return '<td class="py-2 px-2 tabular-nums">' + num(x[k]) + '</td>'; }).join('') +
                      '<td class="py-2 px-2 tabular-nums font-semibold" dir="ltr">' + x.rate.toFixed(2) + '%</td></tr>';
                    if (open[x.id]) h += '<tr class="bg-ink-50/60 border-b border-ink-200"><td colspan="11" class="p-4" id="an-d-' + esc(x.id) + '">' + detail(x) + '</td></tr>';
                  });
                  body.innerHTML = h || '<tr><td colspan="11" class="py-8 text-center text-ink-500">' + esc(L.none) + '</td></tr>';
                  $('an-count').textContent = L.shown.replace('%s', Math.min(limit, r.length)).replace('%s', r.length);
                  var more = $('an-more');
                  more.classList.toggle('hidden', r.length <= limit);
                  more.textContent = L.more;
                  Object.keys(open).forEach(function (id) { if (open[id] && open[id] !== 1) render(id); });
                  var n = Object.keys(sel).length, cb = $('an-cmp-btn');
                  cb.textContent = L.compare.replace('%s', n);
                  cb.classList.toggle('hidden', n < 1);
                  cb.disabled = n < 2; cb.title = n < 2 ? L.pickHint : '';
                  if (n < 2) $('an-cmp').classList.add('hidden'); else if (!$('an-cmp').classList.contains('hidden')) compare();
                }
                function compare() {
                  var list = rows.filter(function (r) { return sel[r.id]; }), box = $('an-cmp');
                  var ms = ['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'engagement', 'rate'];
                  var col = ['rgb(var(--brand-600))', 'rgb(var(--cyan-500))', 'rgb(var(--lime-500))', 'rgb(var(--amber-500))'];
                  var h = '<div class="flex items-center justify-between mb-3"><h3 class="font-bold text-ink-900">' + esc(L.compareTitle) + '</h3><button type="button" class="btn-ghost text-xs" id="an-cmp-x">' + esc(L.close) + '</button></div>';
                  h += '<div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(11rem,1fr))">';
                  list.forEach(function (x, i) {
                    h += '<div class="rounded-lg bg-white border border-ink-200 p-3 min-w-0"><div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:' + col[i] + '"></span>' + x.icon + '<span class="text-xs text-ink-500 truncate">' + esc(L.medias[x.media] || x.media) + '</span></div>' +
                      '<p class="text-xs text-ink-800 line-clamp-3 mt-1">' + esc(x.text || L.noText) + '</p><p class="text-[11px] text-ink-500 mt-1">' + esc(L.published) + ': ' + esc(x.dateLabel) + '</p>' +
                      '<p class="text-lg font-bold text-ink-900 mt-1" dir="ltr">' + x.rate.toFixed(2) + '%</p><p class="text-[11px] text-ink-500">' + esc(L.rate) + '</p></div>';
                  });
                  h += '</div><div class="mt-4 space-y-3">';
                  ms.forEach(function (k) {
                    var mx = Math.max.apply(null, list.map(function (x) { return x[k]; }).concat([0.0001]));
                    h += '<div><p class="text-xs font-semibold text-ink-700 mb-1">' + esc(L[k]) + '</p>';
                    list.forEach(function (x, i) {
                      var v = x[k];
                      h += '<div class="flex items-center gap-2 text-[11px]"><div class="flex-1 h-2.5 rounded-full bg-ink-100 overflow-hidden" dir="ltr"><div class="h-full rounded-full" style="width:' + (v / mx * 100) + '%;background:' + col[i] + '"></div></div><span class="w-14 text-end tabular-nums text-ink-700" dir="ltr">' + (k === 'rate' ? v.toFixed(2) + '%' : num(v)) + (v === mx && v > 0 && list.length > 1 ? ' ★' : '') + '</span></div>';
                    });
                    h += '</div>';
                  });
                  box.innerHTML = h + '</div>';
                  box.classList.remove('hidden');
                  $('an-cmp-x').addEventListener('click', function () { box.classList.add('hidden'); });
                }
                function detail(x) {
                  var m = [['impressions', x.impressions], ['reach', x.reach], ['likes', x.likes], ['comments', x.comments], ['shares', x.shares], ['clicks', x.clicks]].map(function (a) { return [L[a[0]], a[1]]; }).concat(x.extra);
                  var mx = Math.max.apply(null, m.map(function (a) { return a[1]; }).concat([1]));
                  var h = '<div class="grid lg:grid-cols-3 gap-5 [&>*]:min-w-0"><div><p class="text-xs font-semibold text-ink-700 mb-2">' + esc(L.breakdown) + '</p><ul class="space-y-1.5">';
                  m.forEach(function (a) { h += '<li><div class="flex justify-between text-xs"><span class="text-ink-600">' + esc(a[0]) + '</span><b class="tabular-nums text-ink-900">' + num(a[1]) + '</b></div><div class="h-1.5 rounded-full bg-ink-200 overflow-hidden"><div class="h-full bg-brand-500 rounded-full" style="width:' + (a[1] / mx * 100) + '%"></div></div></li>'; });
                  h += '</ul>' + (x.url ? '<a class="link text-xs mt-3 inline-block" href="' + esc(x.url) + '" target="_blank" rel="noopener">' + esc(L.open) + '</a>' : '') +
                    (x.sync === 'pending' ? '<p class="text-[11px] text-amber-700 mt-2">' + esc(L.pending) + '</p>' : '') +
                    (D.tagsOk ? '<form method="post" class="mt-4 an-tagform"><input type="hidden" name="_csrf" value="' + esc(D.csrf) + '"><input type="hidden" name="action" value="tags"><input type="hidden" name="back" value="' + esc(D.back) + '"><input type="hidden" name="post_id" value="' + esc(x.id) + '">' +
                      '<label class="label">' + esc(L.tags) + '</label><div class="flex gap-2"><input name="tags" class="input py-1.5 text-sm" maxlength="300" value="' + esc(x.tags.join(', ')) + '" placeholder="' + esc(L.tagsHint) + '"><button class="btn-ghost text-sm">' + esc(L.save) + '</button></div></form>' : '') + '</div>' +
                    '<div class="lg:col-span-2 space-y-4"><div><p class="text-xs font-semibold text-ink-700 mb-2">' + esc(L.growth) + '</p><div class="an-tl text-xs text-ink-500">' + esc(L.loading) + '</div></div>' +
                    '<div><p class="text-xs font-semibold text-ink-700 mb-2">' + esc(L.reactions) + '</p><div class="an-re text-xs text-ink-500">' + esc(L.loading) + '</div></div></div></div>';
                  return h;
                }
                var cache = {};
                function render(id) {
                  var box = $('an-d-' + id), d = cache[id];
                  if (!box || !d) return;
                  var tl = box.querySelector('.an-tl'), re = box.querySelector('.an-re');
                  if (d.error) { tl.textContent = L.loadError; re.textContent = ''; return; }
                  if (d.timeline && d.timeline.length > 1) {
                    tl.innerHTML = '';
                    AnCharts.line(tl, {height: 170, aria: L.growth, labels: d.timeline.map(function (t) { return t.label; }),
                      series: ['impressions', 'reach', 'likes', 'clicks'].map(function (k, i) { return {key: k, label: L[k], color: ['rgb(var(--brand-600))', 'rgb(var(--cyan-500))', 'rgb(var(--lime-500))', 'rgb(var(--ink-500))'][i], values: d.timeline.map(function (t) { return t[k]; })}; })});
                  } else tl.textContent = d.timelineError || L.noTimeline;
                  if (d.reactions && d.reactions.total > 0) {
                    var bd = d.reactions.breakdown, tot = 0, k; for (k in bd) tot += bd[k];
                    var h = '<div class="flex flex-wrap gap-2">';
                    for (k in bd) h += '<span class="badge">' + esc(k) + ' <b class="ms-1 tabular-nums">' + bd[k] + '</b> <span class="text-ink-400 ms-1" dir="ltr">' + Math.round(bd[k] / tot * 100) + '%</span></span>';
                    re.innerHTML = h + '</div>';
                  } else re.textContent = d.reactionsError || L.noReactions;
                }
                function toggle(id) {
                  open[id] = open[id] ? 0 : 2;
                  if (!open[id]) delete open[id];
                  draw();
                  if (open[id] && !cache[id]) {
                    var x = rows.filter(function (r) { return r.id === id; })[0];
                    var u = D.detailUrl.replace('__ID__', encodeURIComponent(id)) + '&acc=' + encodeURIComponent(x.accountId) + '&pl=' + encodeURIComponent(x.platform) + '&ppid=' + encodeURIComponent(x.ppid);
                    fetch(u, {credentials: 'same-origin'}).then(function (r) { return r.json(); })
                      .then(function (j) { cache[id] = j; render(id); })
                      .catch(function () { cache[id] = {error: 1}; render(id); });
                  } else if (open[id]) render(id);
                }
                head.addEventListener('click', function (ev) {
                  var b = ev.target.closest('button[data-s]'); if (!b) return;
                  if (sort === b.dataset.s) dir = -dir; else { sort = b.dataset.s; dir = sort === 'post' ? 1 : -1; }
                  draw();
                });
                body.addEventListener('click', function (ev) {
                  if (ev.target.closest('a, form, input, button')) {
                    var cb = ev.target.closest('input.an-sel');
                    if (cb) { if (cb.checked) sel[cb.dataset.id] = 1; else delete sel[cb.dataset.id]; draw(); }
                    return;
                  }
                  var tr = ev.target.closest('tr[data-id]'); if (tr) toggle(tr.dataset.id);
                });
                body.addEventListener('keydown', function (ev) {
                  var tr = ev.target.closest('tr[data-id]');
                  if (tr && ev.target === tr && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); toggle(tr.dataset.id); }
                });
                ['an-q', 'an-fpl', 'an-fm', 'an-df', 'an-dt', 'an-ft'].forEach(function (id) { if ($(id)) $(id).addEventListener('input', function () { limit = 25; draw(); }); });
                $('an-more').addEventListener('click', function () { limit += 25; draw(); });
                $('an-cmp-btn').addEventListener('click', function () { if (Object.keys(sel).length >= 2) { compare(); $('an-cmp').scrollIntoView({behavior: 'smooth', block: 'nearest'}); } });
                $('an-csv').addEventListener('click', function () {
                  var keys = ['dateLabel', 'platformLabel', 'account', 'media', 'text', 'impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'engagement', 'rate', 'url'];
                  var hd = [L.date, L.platform, L.account, L.media, L.post, L.impressions, L.reach, L.likes, L.comments, L.shares, L.clicks, L.engagement, L.rate, L.url];
                  var q = function (v) { v = String(v == null ? '' : v); return /[",\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
                  var lines = [hd.map(q).join(',')];
                  filtered().forEach(function (x) { lines.push(keys.map(function (k) { return q(k === 'media' ? (L.medias[x.media] || x.media) : k === 'rate' ? x.rate.toFixed(2) : x[k]); }).join(',')); });
                  var a = document.createElement('a');
                  a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], {type: 'text/csv;charset=utf-8'}));
                  a.download = 'posts-<?= e($per['from']) ?>-<?= e($per['to']) ?>.csv';
                  document.body.appendChild(a); a.click(); a.remove();
                });
                draw();
              })();
              </script>
            <?php endif; ?>
          </section>

          <?php if (!$E['posts'] && $rows): ?>
          <div class="grid lg:grid-cols-2 gap-5 [&>*]:min-w-0">
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-1"><?= e(t('الأداء حسب الوسم')) ?></h2>
              <p class="text-xs text-ink-500 mb-4"><?= e(t('الوسوم دي بتحطها أنت على المنشورات (من تفاصيل المنشور) وبتتحفظ هنا بس.')) ?></p>
              <?php if (!an_tags_available()): ?>
                <?= an_notice(t('الوسوم محتاجة تحديث قاعدة البيانات. شغّل صفحة التحديث الأول.')) ?>
              <?php elseif (!$tagStats): ?>
                <?= an_empty(t('لسه مفيش وسوم على منشورات الفترة دي.'), t('افتح أي منشور في الجدول وأضف له وسوم زي: عرض، توعية، منتج.'), '🏷️') ?>
              <?php else:
                $ts = [];
                foreach ($tagStats as $tg => $list) {
                    $n = count($list); $imp = array_sum(array_column($list, 'impressions')); $rt = array_sum(array_column($list, 'rate'));
                    $ts[] = [$tg, $n, $imp / $n, $rt / $n];
                }
                usort($ts, function ($a, $b) { return $b[3] <=> $a[3]; });
                $mxr = max(array_column($ts, 3)) ?: 1;
                ?>
                <div class="overflow-x-auto"><table class="w-full text-sm">
                  <thead><tr class="text-ink-500 text-xs border-b border-ink-200">
                    <th class="text-start py-2 pe-2 font-semibold"><?= e(t('الوسم')) ?></th><th class="text-start py-2 px-2 font-semibold"><?= e(t('منشورات')) ?></th>
                    <th class="text-start py-2 px-2 font-semibold"><?= e(t('متوسط الظهور')) ?></th><th class="text-start py-2 px-2 font-semibold w-2/5"><?= e(t('متوسط معدل التفاعل')) ?></th>
                  </tr></thead><tbody>
                  <?php foreach ($ts as $r): ?>
                    <tr class="border-b border-ink-100"><td class="py-2 pe-2 font-medium text-ink-800">#<?= e($r[0]) ?></td><td class="py-2 px-2 tabular-nums"><?= (int)$r[1] ?></td>
                      <td class="py-2 px-2 tabular-nums"><?= e(an_num($r[2])) ?></td>
                      <td class="py-2 px-2"><div class="flex items-center gap-2"><div class="flex-1"><?= an_hbar($r[3], $mxr, 'bg-brand-500') ?></div><span class="text-xs tabular-nums w-14 text-end" dir="ltr"><?= e(number_format($r[3], 2)) ?>%</span></div></td></tr>
                  <?php endforeach; ?>
                </tbody></table></div>
              <?php endif; ?>
            </section>

            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-1"><?= e(t('أنواع التفاعلات في أعلى المنشورات')) ?></h2>
              <p class="text-xs text-ink-500 mb-4"><?= e(t('لأعلى %s منشورات على فيسبوك ولينكدإن في الفترة.', count($D['reactions']))) ?></p>
              <?php
              $rcol = ['bg-brand-600', 'bg-red-500', 'bg-amber-500', 'bg-lime-500', 'bg-cyan-500', 'bg-ink-400', 'bg-brand-300'];
              $rl = ['like' => t('إعجاب'), 'love' => t('حب'), 'haha' => t('ضحك'), 'wow' => t('اندهاش'), 'sad' => t('حزن'), 'angry' => t('غضب'), 'care' => t('اهتمام'),
                  'LIKE' => t('إعجاب'), 'PRAISE' => t('احتفاء'), 'EMPATHY' => t('حب'), 'INTEREST' => t('اهتمام'), 'APPRECIATION' => t('دعم'), 'ENTERTAINMENT' => t('ضحك'), 'MAYBE' => t('فضول')];
              $bars = []; $types = [];
              foreach ($D['reactions'] as $r) {
                  $bd = [];
                  if (is_array($r['data'])) {
                      if (($r['post']['platform'] ?? '') === 'facebook') { foreach ((array)($r['data']['breakdown'] ?? []) as $k => $v) if ((int)$v > 0) $bd[$rl[$k] ?? $k] = (int)$v; }
                      else foreach ((array)($r['data']['reactions'] ?? []) as $x) { $k = (string)($x['reactionType'] ?? ''); $l = $rl[$k] ?? $k; $bd[$l] = ($bd[$l] ?? 0) + 1; }
                  }
                  foreach ($bd as $k => $_) $types[$k] = true;
                  $bars[] = [$r['post'], $bd, $r['error']];
              }
              $types = array_keys($types);
              if (!$bars): ?>
                <?= an_empty(t('مفيش منشورات فيسبوك أو لينكدإن في الفترة دي.'), '', '👍') ?>
              <?php elseif (!$types): ?>
                <?= an_empty(t('المنصة مارجعتش تفاصيل تفاعلات للمنشورات دي.'), '', '👍') ?>
              <?php else: ?>
                <div class="flex flex-wrap gap-3 text-[11px] text-ink-600 mb-3">
                  <?php foreach ($types as $i => $tp): ?><span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full <?= $rcol[$i % 7] ?>"></span><?= e($tp) ?></span><?php endforeach; ?>
                </div>
                <ul class="space-y-3">
                  <?php foreach ($bars as [$p, $bd, $er]): $tot = array_sum($bd); ?>
                    <li>
                      <div class="flex justify-between gap-2 text-xs mb-1"><span class="truncate text-ink-800 flex items-center gap-1"><?= platform_icon((string)$p['platform'], 'w-3.5 h-3.5') ?><?= e(mb_substr(an_plain_text((string)($p['content'] ?? '')), 0, 60) ?: t('(منشور بدون نص)')) ?></span><span class="tabular-nums text-ink-500 shrink-0"><?= (int)$tot ?></span></div>
                      <?php if ($tot > 0): ?>
                        <div class="flex h-3 rounded-full overflow-hidden bg-ink-100" dir="ltr">
                          <?php foreach ($types as $i => $tp): if (empty($bd[$tp])) continue; ?><div class="<?= $rcol[$i % 7] ?>" style="width:<?= round($bd[$tp] / $tot * 100, 2) ?>%" title="<?= e($tp . ': ' . $bd[$tp]) ?>"></div><?php endforeach; ?>
                        </div>
                      <?php else: ?><p class="text-[11px] text-ink-400"><?= e($er ?: t('مفيش تفاعلات')) ?></p><?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </section>
          </div>
          <?php endif; ?>

      <?php
      /* =========================== الجمهور =========================== */
      elseif ($tab === 'audience'):
          $fol = $D['fol'];
          ?>
          <section class="card p-5">
            <h2 class="font-bold text-ink-900 mb-3"><?= e(t('المتابعين')) ?></h2>
            <?php if ($E['fol']): ?>
              <?= an_notice($E['fol']) ?>
            <?php elseif (empty($fol['accounts'])): ?>
              <?= an_empty(t('مفيش حسابات متصلة لسه.')) ?>
            <?php else: ?>
              <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($fol['accounts'] as $i => $a):
                  $id = (string)($a['_id'] ?? '');
                  $pts = (array)($fol['stats'][$id] ?? []);
                  $gain = 0; $lost = 0; $last = null; $labels = []; $vals = [];
                  foreach ($pts as $p) {
                      $v = (float)($p['followers'] ?? 0);
                      if ($last !== null) { $dlt = $v - $last; if ($dlt > 0) $gain += $dlt; else $lost += -$dlt; }
                      $last = $v;
                      $labels[] = date('d/m', strtotime((string)($p['date'] ?? 'now')));
                      $vals[] = $v;
                  }
                  $net = (float)($a['growth'] ?? ($gain - $lost));
                  ?>
                  <div class="rounded-xl border border-ink-200 p-4">
                    <div class="flex items-center gap-3">
                      <?= avatar_with_platform($a['profilePicture'] ?? null, (string)($a['displayName'] ?? ''), (string)($a['platform'] ?? '')) ?>
                      <div class="min-w-0">
                        <p class="font-semibold text-ink-900 truncate"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></p>
                        <p class="text-xs text-ink-500"><?= e(platform_label((string)($a['platform'] ?? ''))) ?></p>
                      </div>
                      <div class="ms-auto text-end">
                        <p class="text-2xl font-bold text-ink-900 tabular-nums"><?= e(an_num($a['currentFollowers'] ?? 0)) ?></p>
                        <p class="text-[11px] text-ink-500"><?= e(t('متابع')) ?></p>
                      </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                      <div class="rounded-lg bg-ink-50 py-2"><p class="text-[11px] text-ink-500"><?= e(t('صافي')) ?></p><p class="font-bold tabular-nums <?= $net > 0 ? 'text-lime-700' : ($net < 0 ? 'text-red-600' : 'text-ink-700') ?>" dir="ltr"><?= $net > 0 ? '+' : '' ?><?= e(an_num($net)) ?></p></div>
                      <div class="rounded-lg bg-ink-50 py-2"><p class="text-[11px] text-ink-500"><?= e(t('زيادة')) ?></p><p class="font-bold tabular-nums text-lime-700" dir="ltr"><?= $gain > 0 ? "+" : "" ?><?= e(an_num($gain)) ?></p></div>
                      <div class="rounded-lg bg-ink-50 py-2"><p class="text-[11px] text-ink-500"><?= e(t('نقص')) ?></p><p class="font-bold tabular-nums text-red-600" dir="ltr"><?= $lost > 0 ? "−" : "" ?><?= e(an_num($lost)) ?></p></div>
                    </div>
                    <?php if (count($vals) > 1): ?>
                      <div class="mt-3" id="an-fol-<?= (int)$i ?>"></div>
                      <script>
                        AnCharts.line(document.getElementById('an-fol-<?= (int)$i ?>'), {height: 120, aria: <?= json_encode(t('نمو المتابعين'), $jsonFlags) ?>, labels: <?= json_encode($labels, $jsonFlags) ?>,
                          series: [{key: 'f', label: <?= json_encode(t('متابعين'), $jsonFlags) ?>, color: 'rgb(var(--brand-600))', values: <?= json_encode($vals) ?>}]});
                      </script>
                    <?php else: ?>
                      <p class="text-[11px] text-ink-400 mt-3"><?= e(t('لسه مفيش تاريخ كفاية لرسم النمو.')) ?></p>
                    <?php endif; ?>
                    <?php $fc = an_follower_forecast($pts, 30); ?>
                    <div class="mt-3 rounded-lg border border-dashed border-ink-200 p-3">
                      <p class="text-[11px] font-semibold text-ink-700"><?= e(t('توقع المتابعين بعد 30 يوم')) ?></p>
                      <?php if (!$fc): ?>
                        <p class="text-[11px] text-ink-400 mt-0.5"><?= e(t('مفيش تاريخ كفاية للتوقع (محتاجين 7 نقط على الأقل، عندنا %s).', count($pts))) ?></p>
                      <?php else: $dl = $fc['value'] - $fc['last']; ?>
                        <p class="text-lg font-bold text-ink-900 tabular-nums"><bdi dir="ltr"><?= e(an_num(round($fc['value']))) ?></bdi>
                          <span class="text-xs font-semibold <?= $dl >= 0 ? 'text-lime-700' : 'text-red-600' ?>" dir="ltr"><?= $dl >= 0 ? '+' : '' ?><?= e(an_num(round($dl))) ?></span></p>
                        <p class="text-[11px] text-ink-500"><?= e(t('بمعدل %s متابع في اليوم (انحدار خطي على آخر %s نقطة).', number_format($fc['perDay'], 1), count($pts))) ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <section class="card p-5">
            <h2 class="font-bold text-ink-900 mb-1"><?= e(t('بيانات الجمهور')) ?></h2>
            <p class="text-xs text-ink-500 mb-4"><?= e(t('السن والنوع والبلد والمدينة للمنصات اللي بتدعمها (فيسبوك، إنستجرام، لينكدإن).')) ?></p>
            <?php
            $dimLabels = ['age' => t('السن'), 'gender' => t('النوع'), 'country' => t('البلد'), 'city' => t('المدينة'), 'region' => t('المنطقة'),
                'function' => t('الوظيفة'), 'seniority' => t('المستوى الوظيفي'), 'industry' => t('المجال'), 'company_size' => t('حجم الشركة')];
            $genders = ['F' => t('ستات'), 'M' => t('رجالة'), 'U' => t('غير محدد'), 'female' => t('ستات'), 'male' => t('رجالة'), 'unknown' => t('غير محدد')];
            $dimColors = ['bg-brand-500', 'bg-cyan-500', 'bg-lime-500', 'bg-amber-500'];
            if (empty($D['demo'])): ?>
              <?= an_empty(t('مفيش حساب بيدعم بيانات الجمهور.'), t('بيانات الجمهور متاحة لصفحات فيسبوك وحسابات إنستجرام الاحترافية وصفحات لينكدإن.'), '👥') ?>
            <?php else: ?>
              <div class="space-y-6">
                <?php foreach ($D['demo'] as $id => $row): $a = $accMap[$id] ?? []; $pl = (string)($a['platform'] ?? ''); ?>
                  <div>
                    <div class="flex items-center gap-2 mb-3">
                      <?= avatar_with_platform($a['profilePicture'] ?? null, $accName($a), $pl, 'w-8 h-8') ?>
                      <p class="font-semibold text-ink-900"><?= e($accName($a)) ?></p>
                    </div>
                    <?php if (!empty($row['skipped'])): ?>
                      <?= an_empty(t('اختار الحساب ده من الفلتر عشان تشوف تفاصيله.'), '', '🔎') ?>
                    <?php elseif ($row['error']): ?>
                      <?= an_notice($row['error']) ?>
                    <?php else:
                      $dims = array_filter((array)($row['data']['demographics'] ?? []), function ($v) { return is_array($v) && $v; });
                      if (!$dims): ?>
                        <?= an_empty(t('المنصة لسه مارجعتش بيانات جمهور للحساب ده.'), $pl === 'facebook' ? t('ميتا بتعرض البلد والمدينة بس لصفحات فيسبوك، وبيظهروا لما الصفحة يبقى عندها متابعين كفاية.') : t('البيانات دي بتظهر لما الحساب يبقى عنده متابعين كفاية.'), '👥') ?>
                      <?php else: ?>
                        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-5">
                          <?php $ci = 0; foreach ($dims as $dk => $items):
                            usort($items, function ($x, $y) { return ($y['value'] ?? 0) <=> ($x['value'] ?? 0); });
                            $bars = array_map(function ($x) use ($dk, $genders) { $l = (string)($x['dimension'] ?? ''); return [$dk === 'gender' ? ($genders[$l] ?? $l) : $l, (float)($x['value'] ?? 0)]; }, $items);
                            ?>
                            <div>
                              <p class="text-xs font-semibold text-ink-700 mb-2"><?= e($dimLabels[$dk] ?? $dk) ?></p>
                              <?= an_hbars($bars, $dimColors[$ci++ % 4], 8) ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                      <?php if ($pl === 'instagram'): ?>
                        <div class="mt-5">
                          <p class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('المتابعين أونلاين إمتى (بتوقيت الشركة)')) ?></p>
                          <?php
                          $grid = [];
                          foreach ((array)($row['online']['days'] ?? []) as $day) {
                              $dow = ((int)gmdate('N', strtotime((string)($day['endTime'] ?? 'now'))) + 6) % 7;
                              foreach ((array)($day['hours'] ?? []) as $h => $v) {
                                  [$ld, $lh] = an_local_slot($dow, (int)$h);
                                  $grid[$ld][$lh] = ($grid[$ld][$lh] ?? 0) + (float)$v;
                              }
                          }
                          if (!empty($row['onlineError'])): ?><?= an_notice($row['onlineError']) ?>
                          <?php elseif (!$grid): ?><?= an_empty(t('إنستجرام لسه مارجعش بيانات المتابعين أونلاين.')) ?>
                          <?php else: $dn = an_day_names(); ?>
                            <?= an_heatmap($grid, function ($d, $h, $v) use ($dn) { return t('%s %s: %s متابع أونلاين', $dn[$d], an_hour_label($h), an_num($v)); }) ?>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <?php $others = array_values(array_filter(user_workspaces(), function ($w) { return (int)$w['id'] !== (int)ws('id'); })); ?>
          <section class="card p-5">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
              <div>
                <h2 class="font-bold text-ink-900"><?= e(t('مقارنة الجمهور بشركة تانية')) ?></h2>
                <p class="text-xs text-ink-500"><?= e(t('قارن المتابعين والنمو ومعدل التفاعل لنفس الفترة.')) ?></p>
              </div>
              <?php if ($others): ?>
                <form method="get" class="flex gap-2 items-end">
                  <input type="hidden" name="p" value="analytics"><input type="hidden" name="tab" value="audience">
                  <?php if ($per['custom']): ?><input type="hidden" name="from" value="<?= e($per['from']) ?>"><input type="hidden" name="to" value="<?= e($per['to']) ?>"><?php else: ?><input type="hidden" name="d" value="<?= (int)$per['days'] ?>"><?php endif; ?>
                  <select name="cw" class="input py-1.5" aria-label="<?= e(t('الشركة اللي هتقارن بيها')) ?>">
                    <option value=""><?= e(t('اختار شركة...')) ?></option>
                    <?php foreach ($others as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)($_GET['cw'] ?? 0) === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?>
                  </select>
                  <button class="btn-ghost"><?= e(t('قارن')) ?></button>
                </form>
              <?php endif; ?>
            </div>
            <?php if (!$others): ?>
              <?= an_empty(t('إنت عضو في شركة واحدة بس.'), t('المقارنة بتظهر لما تكون عضو في أكتر من شركة.'), '🏢') ?>
            <?php elseif (empty($D['cw'])): ?>
              <?= an_empty(t('اختار شركة من فوق عشان تقارن.'), '', '🏢') ?>
            <?php elseif ($D['cw']['error']): ?>
              <?= an_notice($D['cw']['error']) ?>
            <?php else:
              $cwName = '';
              foreach ($others as $w) if ((int)$w['id'] === (int)$D['cw']['id']) $cwName = (string)$w['name'];
              $sideA = ['name' => (string)ws('name'), 'fol' => $E['fol'] ? null : an_follower_totals($D['fol']), 'daily' => $D['cw']['mineError'] ? null : an_sum_daily($D['cw']['mine'])[0]];
              $sideB = ['name' => $cwName, 'fol' => $D['cw']['folError'] ? null : an_follower_totals($D['cw']['fol']), 'daily' => $D['cw']['dailyError'] ? null : an_sum_daily($D['cw']['daily'])[0]];
              $metrics = [
                  [t('المتابعين'), function ($x) { return $x['fol'] ? $x['fol']['current'] : null; }, false],
                  [t('صافي النمو'), function ($x) { return $x['fol'] ? $x['fol']['growth'] : null; }, false],
                  [t('مرات الظهور'), function ($x) { return $x['daily'] ? $x['daily']['impressions'] : null; }, false],
                  [t('معدل التفاعل'), function ($x) { return $x['daily'] ? $x['daily']['rate'] : null; }, true],
              ];
              ?>
              <?php if ($curAcc): ?><p class="text-[11px] text-amber-700 mb-3"><?= e(t('المقارنة على مستوى الشركة كلها (فلتر الحساب مش بيتطبق على الشركة التانية).')) ?></p><?php endif; ?>
              <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($metrics as [$ml, $fn, $pct]): $va = $fn($sideA); $vb = $fn($sideB); $mx = max((float)$va, (float)$vb) ?: 1; ?>
                  <div class="rounded-xl bg-ink-50 p-4">
                    <p class="text-xs font-semibold text-ink-700 mb-2"><?= e($ml) ?></p>
                    <?php foreach ([[$sideA['name'], $va, 'bg-brand-600'], [$sideB['name'], $vb, 'bg-cyan-500']] as [$nm, $v, $c]): ?>
                      <div class="mb-2"><div class="flex justify-between gap-2 text-[11px]"><span class="truncate text-ink-600"><?= e($nm) ?></span><b class="tabular-nums text-ink-900" dir="ltr"><?= $v === null ? '—' : ($pct ? e(number_format($v, 2)) . '%' : e(an_num($v))) ?></b></div>
                        <?= an_hbar(max(0, (float)$v), $mx, $c) ?></div>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

      <?php
      /* =========================== التوقيت والمحتوى =========================== */
      elseif ($tab === 'timing'):
          $dn = an_day_names();
          ?>
          <section class="card p-5">
            <h2 class="font-bold text-ink-900 mb-1"><?= e(t('أفضل وقت للنشر')) ?></h2>
            <p class="text-xs text-ink-500 mb-4"><?= e(t('متوسط التفاعل لكل يوم وساعة، محسوب من كل منشوراتك القديمة، بتوقيت الشركة (%s).', site_timezone())) ?></p>
            <?php if ($E['best']): ?>
              <?= an_notice($E['best']) ?>
            <?php elseif (!$D['best']): ?>
              <?= an_empty(t('لسه مفيش منشورات كفاية نحسب منها أفضل وقت.'), '', '🕒') ?>
            <?php else:
              $grid = []; $cnt = []; $slots = [];
              foreach ($D['best'] as $s) {
                  [$d, $h] = an_local_slot((int)($s['day_of_week'] ?? 0), (int)($s['hour'] ?? 0));
                  $grid[$d][$h] = (float)($s['avg_engagement'] ?? 0);
                  $cnt[$d][$h] = (int)($s['post_count'] ?? 0);
                  $slots[] = [$d, $h, (float)($s['avg_engagement'] ?? 0), (int)($s['post_count'] ?? 0)];
              }
              usort($slots, function ($a, $b) { return $b[2] <=> $a[2] ?: $b[3] <=> $a[3]; });
              ?>
              <div class="grid xl:grid-cols-4 gap-5 [&>*]:min-w-0">
                <div class="xl:col-span-3">
                  <?= an_heatmap($grid, function ($d, $h, $v) use ($dn, $cnt) { return t('%s %s: متوسط تفاعل %s من %s منشور', $dn[$d], an_hour_label($h), an_num($v), (int)($cnt[$d][$h] ?? 0)); }) ?>
                  <div class="flex items-center gap-2 text-[11px] text-ink-500 mt-2" dir="ltr">
                    <span><?= e(t('أقل')) ?></span>
                    <?php foreach ([0, 0.25, 0.5, 0.75, 1] as $x): ?><span class="w-4 h-3 rounded" style="<?= an_heat_style($x) ?>"></span><?php endforeach; ?>
                    <span><?= e(t('أعلى')) ?></span>
                  </div>
                </div>
                <div>
                  <p class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('أحسن 3 أوقات')) ?></p>
                  <ol class="space-y-2">
                    <?php foreach (array_slice($slots, 0, 3) as $i => $s): ?>
                      <li class="an-cell flex items-center gap-2 rounded-lg bg-ink-50 p-2.5" data-d="<?= (int)$s[0] ?>" data-h="<?= (int)$s[1] ?>">
                        <span class="w-6 h-6 rounded-full bg-brand-600 text-white text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span>
                        <div><p class="text-sm font-semibold text-ink-900"><?= e($dn[$s[0]]) ?> · <span dir="ltr"><?= e(an_hour_label($s[1])) ?></span></p>
                          <p class="text-[11px] text-ink-500"><?= e(t('متوسط تفاعل %s من %s منشور', an_num($s[2]), $s[3])) ?></p></div>
                      </li>
                    <?php endforeach; ?>
                  </ol>
                </div>
              </div>
            <?php endif; ?>
          </section>

          <div class="grid lg:grid-cols-2 gap-5 [&>*]:min-w-0">
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-1"><?= e(t('عدد المنشورات في الأسبوع مقابل التفاعل')) ?></h2>
              <p class="text-xs text-ink-500 mb-4"><?= e(t('متوسط معدل التفاعل في الأسابيع اللي نشرت فيها بالعدد ده.')) ?></p>
              <?php if ($E['freq']): ?>
                <?= an_notice($E['freq']) ?>
              <?php elseif (!$D['freq']): ?>
                <?= an_empty(t('لسه مفيش أسابيع كفاية للمقارنة.'), '', '📅') ?>
              <?php else:
                $agg = [];
                foreach ($D['freq'] as $f) {
                    $k = (int)($f['posts_per_week'] ?? 0);
                    $w = max(1, (int)($f['weeks_count'] ?? 1));
                    $agg[$k] = $agg[$k] ?? ['w' => 0, 'r' => 0, 'e' => 0];
                    $agg[$k]['w'] += $w;
                    $agg[$k]['r'] += (float)($f['avg_engagement_rate'] ?? 0) * $w;
                    $agg[$k]['e'] += (float)($f['avg_engagement'] ?? 0) * $w;
                }
                ksort($agg);
                $mxr = 0; $best = null;
                foreach ($agg as $k => $v) { $r = $v['r'] / $v['w']; if ($r > $mxr) { $mxr = $r; $best = $k; } }
                ?>
                <div class="flex items-end gap-2 h-44 border-b border-ink-200" dir="ltr" role="img" aria-label="<?= e(t('عدد المنشورات في الأسبوع مقابل التفاعل')) ?>">
                  <?php foreach ($agg as $k => $v): $r = $v['r'] / $v['w']; ?>
                    <div class="flex-1 h-full flex flex-col justify-end items-center gap-1" title="<?= e(t('%s منشور/أسبوع: معدل تفاعل %s%% · متوسط تفاعل %s · %s أسبوع', $k, number_format($r, 2), an_num($v['e'] / $v['w']), $v['w'])) ?>">
                      <span class="text-[10px] text-ink-600 tabular-nums"><?= e(number_format($r, 2)) ?>%</span>
                      <div class="w-full max-w-[3rem] rounded-t <?= $k === $best ? 'bg-lime-500' : 'bg-brand-500' ?>" style="height:<?= $mxr > 0 ? max(3, round($r / $mxr * 85)) : 3 ?>%"></div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="flex gap-2 mt-1 text-[11px] text-ink-500" dir="ltr">
                  <?php foreach ($agg as $k => $v): ?><span class="flex-1 text-center"><?= (int)$k ?></span><?php endforeach; ?>
                </div>
                <p class="text-[11px] text-ink-500 text-center mt-0.5"><?= e(t('منشورات في الأسبوع')) ?></p>
                <?php if ($best !== null): ?><p class="text-sm text-ink-700 mt-3 flex items-start gap-1.5"><?= ui_icon('bulb', 'w-4 h-4 text-amber-600 mt-0.5') ?><?= e(t('أعلى معدل تفاعل لما بتنشر %s مرة في الأسبوع.', $best)) ?></p><?php endif; ?>
              <?php endif; ?>
            </section>

            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-1"><?= e(t('عمر المحتوى')) ?></h2>
              <p class="text-xs text-ink-500 mb-4"><?= e(t('المنشور بيوصل لكام في المية من تفاعله النهائي مع مرور الوقت.')) ?></p>
              <?php if ($E['decay']): ?>
                <?= an_notice($E['decay']) ?>
              <?php elseif (!$D['decay']): ?>
                <?= an_empty(t('لسه مفيش بيانات كفاية.'), '', '⏳') ?>
              <?php else:
                $b = $D['decay'];
                usort($b, function ($x, $y) { return ($x['bucket_order'] ?? 0) <=> ($y['bucket_order'] ?? 0); });
                $bl = function ($s) {
                    $s = (string)$s;
                    if (preg_match('/^(\d+)-(\d+)([hd])$/', $s, $m)) return $m[3] === 'h' ? t('%s–%s ساعة', $m[1], $m[2]) : t('%s–%s يوم', $m[1], $m[2]);
                    if (preg_match('/^(\d+)([hd])\+$/', $s, $m)) return $m[2] === 'h' ? t('أكتر من %s ساعة', $m[1]) : t('أكتر من %s يوم', $m[1]);
                    if (preg_match('/^<?\s*(\d+)([hd])$/', $s, $m)) return $m[2] === 'h' ? t('أول %s ساعة', $m[1]) : t('أول %s يوم', $m[1]);
                    return $s;
                };
                $cfg = ['labels' => array_map(function ($x) use ($bl) { return $bl($x['bucket_label'] ?? ''); }, $b),
                    'series' => [['key' => 'p', 'label' => t('من التفاعل النهائي'), 'color' => 'rgb(var(--lime-500))', 'values' => array_map(function ($x) { return round((float)($x['avg_pct_of_final'] ?? 0), 1); }, $b)]],
                    'extra' => [['label' => t('منشورات'), 'values' => array_map(function ($x) { return (int)($x['post_count'] ?? 0); }, $b)]]];
                ?>
                <div id="an-decay"></div>
                <script>
                  (function () {
                    var cfg = <?= json_encode($cfg, $jsonFlags) ?>;
                    cfg.height = 200; cfg.aria = <?= json_encode(t('عمر المحتوى'), $jsonFlags) ?>;
                    cfg.fmt = function (v) { return (Math.round(v * 10) / 10) + '%'; };
                    cfg.extra[0].fmt = function (v) { return String(v); };
                    AnCharts.line(document.getElementById('an-decay'), cfg);
                  })();
                </script>
              <?php endif; ?>
            </section>
          </div>

          <section class="card p-5">
            <h2 class="font-bold text-ink-900 mb-1"><?= e(t('مقارنة أنواع المحتوى')) ?></h2>
            <p class="text-xs text-ink-500 mb-4"><?= e(t('محسوبة من منشورات الفترة المختارة.')) ?></p>
            <?php if ($E['posts']): ?>
              <?= an_notice($E['posts']) ?>
            <?php elseif (empty($D['posts']['posts'])): ?>
              <?= an_empty(t('مفيش منشورات في الفترة دي.'), t('جرب فترة أطول أو حساب تاني.')) ?>
            <?php else:
              $mt = [];
              foreach ($D['posts']['posts'] as $p) {
                  $k = (string)($p['mediaType'] ?? '') ?: 'text';
                  $an = (array)($p['analytics'] ?? []);
                  $mt[$k] = $mt[$k] ?? ['n' => 0, 'imp' => 0, 'eng' => 0, 'rate' => 0];
                  $mt[$k]['n']++;
                  $mt[$k]['imp'] += (float)($an['impressions'] ?? 0);
                  $mt[$k]['eng'] += an_engagement($an);
                  $mt[$k]['rate'] += (float)($an['engagementRate'] ?? 0);
              }
              uasort($mt, function ($a, $b) { return $b['rate'] / $b['n'] <=> $a['rate'] / $a['n']; });
              $mxr = max(array_map(function ($v) { return $v['rate'] / $v['n']; }, $mt)) ?: 1;
              ?>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead><tr class="text-ink-500 text-xs border-b border-ink-200">
                    <th class="text-start py-2 pe-3 font-semibold"><?= e(t('النوع')) ?></th>
                    <th class="text-start py-2 px-2 font-semibold"><?= e(t('منشورات')) ?></th>
                    <th class="text-start py-2 px-2 font-semibold"><?= e(t('متوسط الظهور')) ?></th>
                    <th class="text-start py-2 px-2 font-semibold"><?= e(t('متوسط التفاعل')) ?></th>
                    <th class="text-start py-2 px-2 font-semibold w-1/3"><?= e(t('متوسط معدل التفاعل')) ?></th>
                  </tr></thead>
                  <tbody>
                    <?php foreach ($mt as $k => $v): $r = $v['rate'] / $v['n']; ?>
                      <tr class="border-b border-ink-100">
                        <td class="py-2 pe-3 font-medium text-ink-800"><?= e($mediaLabels[$k] ?? $k) ?></td>
                        <td class="py-2 px-2 tabular-nums"><?= (int)$v['n'] ?></td>
                        <td class="py-2 px-2 tabular-nums"><?= e(an_num($v['imp'] / $v['n'])) ?></td>
                        <td class="py-2 px-2 tabular-nums"><?= e(an_num($v['eng'] / $v['n'])) ?></td>
                        <td class="py-2 px-2"><div class="flex items-center gap-2"><div class="flex-1"><?= an_hbar($r, $mxr, 'bg-lime-500') ?></div><span class="tabular-nums text-xs w-14 text-end" dir="ltr"><?= e(number_format($r, 2)) ?>%</span></div></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php if (count($mt) < 2): ?><p class="text-xs text-ink-500 mt-3"><?= e(t('كل منشورات الفترة دي من نفس النوع، جرب تنوّع (صور، فيديو، نص) عشان تقارن.')) ?></p><?php endif; ?>
            <?php endif; ?>
          </section>

          <?php
          // ---------- تحليلات المحتوى من قائمة المنشورات ----------
          $plist = (array)($D['posts']['posts'] ?? []);
          $cmpBars = function (array $groups) {
              // $groups = [[label, [posts]], ...]
              $stats = array_map(function ($g) { return [$g[0]] + [1 => an_group_stats($g[1])]; }, $groups);
              $mx = 0;
              foreach ($stats as $st) $mx = max($mx, $st[1][2]);
              $h = '<ul class="space-y-2.5">';
              foreach ($stats as $st) {
                  [$n, , $r] = $st[1];
                  $h .= '<li><div class="flex justify-between gap-2 text-xs mb-0.5"><span class="text-ink-800">' . e($st[0]) . ' <span class="text-ink-400">(' . e(t('%s منشور', $n)) . ')</span></span><span class="tabular-nums font-semibold text-ink-700" dir="ltr">' . ($n ? e(number_format($r, 2)) . '%' : '—') . '</span></div>'
                      . an_hbar($r, $mx ?: 1, $n ? 'bg-brand-500' : 'bg-ink-200') . '</li>';
              }
              return $h . '</ul>';
          };
          ?>
          <?php if (!$E['posts'] && $plist):
            $tags = [];
            $len = [[t('قصير (أقل من 100 حرف)'), []], [t('متوسط (100–500 حرف)'), []], [t('طويل (أكتر من 500 حرف)'), []]];
            $feat = ['link' => [[], []], 'emoji' => [[], []], 'question' => [[], []]];
            $byDay = []; $sched = ['immediate' => [], 'scheduled' => [], 'external' => []];
            foreach ($plist as $p) {
                $txt = an_plain_text((string)($p['content'] ?? ''));
                foreach (an_hashtags((string)($p['content'] ?? '')) as $h) $tags[$h][] = $p;
                $l = mb_strlen(trim($txt));
                $len[$l < 100 ? 0 : ($l <= 500 ? 1 : 2)][1][] = $p;
                $feat['link'][preg_match('~https?://|www\.~i', $txt) ? 0 : 1][] = $p;
                $feat['emoji'][an_has_emoji($txt) ? 0 : 1][] = $p;
                $feat['question'][preg_match('/[?؟]/u', $txt) ? 0 : 1][] = $p;
                if (!empty($p['publishedAt'])) {
                    $dow = ((int)fmt_date($p['publishedAt'], 'N') + 6) % 7;
                    $byDay[(string)($p['platform'] ?? '')][$dow][] = (float)($p['analytics']['engagementRate'] ?? 0);
                }
                $created = $p['createdAt'] ?? null;
                if ($created) {
                    $when = strtotime((string)($p['scheduledFor'] ?? $p['publishedAt'] ?? $created));
                    $sched[$when - strtotime((string)$created) < 300 ? 'immediate' : 'scheduled'][] = $p;
                } else {
                    $sched['external'][] = $p;
                }
            }
            $ranked = array_filter($tags, function ($l) { return count($l) >= 2; });
            uasort($ranked, function ($a, $b) { return an_group_stats($b)[2] <=> an_group_stats($a)[2]; });
            $dn = an_day_names();
            ?>
            <div class="grid lg:grid-cols-2 gap-5 [&>*]:min-w-0">
              <section class="card p-5">
                <h2 class="font-bold text-ink-900 mb-1"><?= e(t('تحليل الهاشتاجات')) ?></h2>
                <p class="text-xs text-ink-500 mb-4"><?= e(t('مترتبة حسب متوسط معدل التفاعل. الهاشتاج لازم يكون في منشورين على الأقل.')) ?></p>
                <?php if (!$tags): ?>
                  <?= an_empty(t('مفيش هاشتاجات في منشورات الفترة دي.'), '', '#') ?>
                <?php elseif (!$ranked): ?>
                  <?= an_empty(t('كل هاشتاج اتستخدم مرة واحدة بس، فمش كفاية للترتيب.'), t('اتستخدم %s هاشتاج مختلف.', count($tags)), '#') ?>
                <?php else: $mx = max(array_map(function ($l) { return an_group_stats($l)[2]; }, $ranked)) ?: 1; ?>
                  <ol class="space-y-2.5">
                    <?php foreach (array_slice($ranked, 0, 10, true) as $h => $l): [$n, $imp, $r] = an_group_stats($l); ?>
                      <li><div class="flex justify-between gap-2 text-xs mb-0.5"><span class="font-medium text-ink-800" dir="auto">#<?= e($h) ?> <span class="text-ink-400">(<?= e(t('%s منشور', $n)) ?> · <?= e(t('%s ظهور', an_num($imp))) ?>)</span></span><span class="tabular-nums font-semibold" dir="ltr"><?= e(number_format($r, 2)) ?>%</span></div>
                        <?= an_hbar($r, $mx, 'bg-cyan-500') ?></li>
                    <?php endforeach; ?>
                  </ol>
                <?php endif; ?>
              </section>

              <section class="card p-5">
                <h2 class="font-bold text-ink-900 mb-1"><?= e(t('طول النص والتفاعل')) ?></h2>
                <p class="text-xs text-ink-500 mb-4"><?= e(t('متوسط معدل التفاعل حسب عدد حروف المنشور.')) ?></p>
                <?= $cmpBars($len) ?>
                <h3 class="font-semibold text-ink-900 text-sm mt-6 mb-3"><?= e(t('روابط وإيموجي وأسئلة')) ?></h3>
                <div class="grid sm:grid-cols-3 gap-4">
                  <?php foreach (['link' => [t('فيه رابط'), t('من غير رابط')], 'emoji' => [t('فيه إيموجي'), t('من غير إيموجي')], 'question' => [t('فيه سؤال'), t('من غير سؤال')]] as $k => [$yes, $no]): ?>
                    <div><?= $cmpBars([[$yes, $feat[$k][0]], [$no, $feat[$k][1]]]) ?></div>
                  <?php endforeach; ?>
                </div>
              </section>

              <section class="card p-5">
                <h2 class="font-bold text-ink-900 mb-1"><?= e(t('أفضل يوم في الأسبوع لكل منصة')) ?></h2>
                <p class="text-xs text-ink-500 mb-4"><?= e(t('متوسط معدل التفاعل حسب يوم النشر بتوقيت الشركة.')) ?></p>
                <div class="space-y-5">
                  <?php foreach ($byDay as $pl => $days):
                    $avg = []; foreach ($days as $d => $vals) $avg[$d] = array_sum($vals) / count($vals);
                    $mx = max($avg) ?: 1; $bestD = array_search(max($avg), $avg, true);
                    ?>
                    <div>
                      <p class="text-xs font-semibold text-ink-700 mb-2 flex items-center gap-1.5"><?= platform_icon($pl, 'w-4 h-4') ?><?= e(platform_label($pl)) ?> · <span class="text-lime-700"><?= e(t('الأفضل: %s', $dn[$bestD])) ?></span></p>
                      <div class="grid grid-cols-7 gap-1 items-end h-24">
                        <?php for ($d = 0; $d < 7; $d++): $v = $avg[$d] ?? null; ?>
                          <div class="h-full flex flex-col justify-end items-center gap-0.5" title="<?= e($dn[$d] . ': ' . ($v === null ? t('مفيش منشورات') : number_format($v, 2) . '% · ' . t('%s منشور', count($days[$d])))) ?>">
                            <span class="text-[9px] text-ink-500 tabular-nums"><?= $v === null ? '' : e(number_format($v, 1)) ?></span>
                            <div class="w-full rounded-t <?= $d === $bestD ? 'bg-lime-500' : ($v === null ? 'bg-ink-100' : 'bg-brand-500') ?>" style="height:<?= $v === null ? 3 : max(4, round($v / $mx * 80)) ?>%"></div>
                          </div>
                        <?php endfor; ?>
                      </div>
                      <div class="grid grid-cols-7 gap-1 text-[10px] text-ink-500 text-center mt-1"><?php for ($d = 0; $d < 7; $d++): ?><span class="truncate text-[9px]" title="<?= e($dn[$d]) ?>"><?= e($dn[$d]) ?></span><?php endfor; ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </section>

              <section class="card p-5">
                <h2 class="font-bold text-ink-900 mb-1"><?= e(t('مجدول ولا اتنشر فوراً')) ?></h2>
                <p class="text-xs text-ink-500 mb-4"><?= e(t('فوراً = اتنشر في خلال 5 دقايق من إنشائه.')) ?></p>
                <?php if (!$sched['immediate'] && !$sched['scheduled']): ?>
                  <?= an_empty(t('المنشورات دي اتنشرت من برّه اللوحة، فمعرفناش وقت إنشائها.'), t('المقارنة بتظهر للمنشورات اللي بتتعمل من صفحة منشور جديد.'), '⏱️') ?>
                <?php else: ?>
                  <?= $cmpBars([[t('اتنشر فوراً'), $sched['immediate']], [t('مجدول'), $sched['scheduled']]]) ?>
                  <?php if ($sched['external']): ?><p class="text-[11px] text-ink-500 mt-3"><?= e(t('%s منشور اتنشر من برّه اللوحة ومش داخل في المقارنة.', count($sched['external']))) ?></p><?php endif; ?>
                <?php endif; ?>
              </section>
            </div>
          <?php endif; ?>

      <?php
      /* =========================== المنصات =========================== */
      elseif ($tab === 'platforms'):
          $secs = ['insights' => t('إحصائيات الحسابات'), 'facebook' => t('فيسبوك: إشارات وأرباح'), 'youtube' => t('يوتيوب: الفيديوهات'), 'ga' => t('جوجل أناليتكس')];
          ?>
          <div class="flex flex-wrap gap-2" role="tablist">
            <?php foreach ($secs as $k => $l): ?>
              <a href="<?= e($q(['sec' => $k === 'insights' ? null : $k, 'v' => null])) ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold border <?= $sec === $k ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700 hover:bg-ink-50' ?>" <?= $sec === $k ? 'aria-current="page"' : '' ?>><?= e($l) ?></a>
            <?php endforeach; ?>
          </div>
          <?php if ($sec === 'insights'):
          $mLabels = [
              'page_media_view' => t('مرات ظهور المحتوى'), 'page_total_media_view_unique' => t('المشاهدين الفريدين'), 'page_post_engagements' => t('التفاعل مع المنشورات'),
              'page_views_total' => t('زيارات الصفحة'), 'page_video_views' => t('مشاهدات الفيديو'), 'page_follows' => t('المتابعين'),
              'followers_gained' => t('متابعين جداد'), 'followers_lost' => t('متابعين خسرناهم'),
              'impressions' => t('مرات الظهور'), 'unique_impressions' => t('ظهور فريد'), 'clicks' => t('نقرات'), 'likes' => t('إعجابات'), 'comments' => t('تعليقات'),
              'shares' => t('مشاركات'), 'engagement_rate' => t('معدل التفاعل'), 'organic_followers_gained' => t('متابعين جداد (طبيعي)'), 'paid_followers_gained' => t('متابعين جداد (ممول)'),
              'reach' => t('الوصول'), 'views' => t('المشاهدات'), 'accounts_engaged' => t('حسابات اتفاعلت'), 'total_interactions' => t('إجمالي التفاعلات'),
              'profile_views' => t('زيارات البروفايل'), 'website_clicks' => t('نقرات الموقع'),
              'estimatedMinutesWatched' => t('دقائق المشاهدة'), 'averageViewDuration' => t('متوسط مدة المشاهدة (ثانية)'), 'subscribersGained' => t('مشتركين جداد'), 'subscribersLost' => t('مشتركين خسرناهم'),
              'follower_count' => t('المتابعين'), 'following_count' => t('بيتابع'), 'likes_count' => t('إجمالي الإعجابات'), 'video_count' => t('عدد الفيديوهات'),
              'BUSINESS_IMPRESSIONS_DESKTOP_MAPS' => t('ظهور على الخرائط (كمبيوتر)'), 'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH' => t('ظهور في البحث (كمبيوتر)'),
              'BUSINESS_IMPRESSIONS_MOBILE_MAPS' => t('ظهور على الخرائط (موبايل)'), 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH' => t('ظهور في البحث (موبايل)'),
              'BUSINESS_CONVERSATIONS' => t('محادثات'), 'BUSINESS_DIRECTION_REQUESTS' => t('طلبات الاتجاهات'), 'CALL_CLICKS' => t('مكالمات'),
              'WEBSITE_CLICKS' => t('نقرات الموقع'), 'BUSINESS_BOOKINGS' => t('حجوزات'), 'BUSINESS_FOOD_ORDERS' => t('طلبات أكل'), 'BUSINESS_FOOD_MENU_CLICKS' => t('نقرات المنيو'),
          ];
          $reasons = ['not_enrolled' => t('مش مفعّل للحساب'), 'permission_missing' => t('محتاج صلاحية إضافية'), 'unsupported_metric' => t('مش مدعوم'), 'no_data' => t('مفيش بيانات')];
          $titles = ['facebook' => t('إحصائيات صفحة فيسبوك'), 'instagram' => t('إحصائيات إنستجرام'), 'linkedin' => t('إحصائيات صفحة لينكدإن'), 'youtube' => t('إحصائيات قناة يوتيوب'), 'tiktok' => t('إحصائيات تيك توك'), 'googlebusiness' => t('أداء ملف النشاط التجاري على جوجل')];
          $paths = an_native_paths();
          $others = array_filter($curAcc ? [$curAcc] : $accounts, function ($a) use ($paths) { return !isset($paths[(string)($a['platform'] ?? '')]); });
          ?>
          <?php if (empty($D['native'])): ?>
            <div class="card p-5"><?= an_empty(t('مفيش حساب متصل بإحصائيات مباشرة.'), t('الإحصائيات المباشرة متاحة لفيسبوك وإنستجرام ولينكدإن ويوتيوب وتيك توك وملف النشاط التجاري على جوجل.'), '🧩') ?></div>
          <?php else: ?>
            <div class="grid lg:grid-cols-2 gap-5 [&>*]:min-w-0">
              <?php foreach ($D['native'] as $id => $row): $a = $accMap[$id] ?? []; $pl = (string)($a['platform'] ?? ''); ?>
                <section class="card p-5">
                  <div class="flex items-center gap-3 mb-4">
                    <?= avatar_with_platform($a['profilePicture'] ?? null, $accName($a), $pl, 'w-10 h-10') ?>
                    <div class="min-w-0">
                      <h2 class="font-bold text-ink-900 truncate"><?= e($titles[$pl] ?? platform_label($pl)) ?></h2>
                      <p class="text-xs text-ink-500 truncate"><?= e($accName($a)) ?></p>
                    </div>
                  </div>
                  <?php if (!empty($row['skipped'])): ?>
                    <?= an_empty(t('اختار الحساب ده من الفلتر عشان تشوف تفاصيله.'), '', '🔎') ?>
                  <?php elseif ($row['error']): ?>
                    <?= an_notice($row['error']) ?>
                  <?php else:
                    $ms = (array)($row['cur']['metrics'] ?? []);
                    $pm = (array)($row['prev']['metrics'] ?? []);
                    if (!$ms): ?>
                      <?= an_empty(t('المنصة مارجعتش أرقام للفترة دي.')) ?>
                    <?php else: ?>
                      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php foreach ($ms as $mk => $mv):
                          $isRate = $mk === 'engagement_rate';
                          $c = (float)($mv['total'] ?? 0); $p = (float)($pm[$mk]['total'] ?? 0);
                          if ($isRate) { $c *= 100; $p *= 100; }
                          ?>
                          <div class="rounded-xl bg-ink-50 p-3">
                            <p class="text-[11px] text-ink-500 leading-tight min-h-[1.75rem]"><?= e($mLabels[$mk] ?? ucwords(str_replace('_', ' ', strtolower((string)$mk)))) ?></p>
                            <p class="text-lg font-bold text-ink-900 tabular-nums" dir="ltr"><?= $isRate ? e(number_format($c, 2)) . '%' : e(an_num($c)) ?></p>
                            <?= $row['prevError'] ? '' : an_delta($c, $p, $isRate) ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($row['cur']['unavailableMetrics'])): ?>
                      <p class="text-[11px] text-ink-500 mt-3">
                        <?= e(t('مقاييس مش متاحة:')) ?>
                        <?php foreach ($row['cur']['unavailableMetrics'] as $u): ?>
                          <span class="badge ms-1"><?= e($mLabels[$u['metric'] ?? ''] ?? ($u['metric'] ?? '')) ?> — <?= e($reasons[$u['reason'] ?? ''] ?? t('مش متاح')) ?></span>
                        <?php endforeach; ?>
                      </p>
                    <?php endif; ?>
                    <?php if ($pl === 'googlebusiness'): ?>
                      <div class="mt-5">
                        <p class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('كلمات البحث اللي ظهرت فيها')) ?></p>
                        <?php if (!empty($row['keywordsError'])): ?><?= an_notice($row['keywordsError']) ?>
                        <?php elseif (empty($row['keywords']['keywords'])): ?><?= an_empty(t('لسه مفيش كلمات بحث للفترة دي.')) ?>
                        <?php else: ?>
                          <?= an_hbars(array_map(function ($k) { return [(string)($k['keyword'] ?? ''), (float)($k['impressions'] ?? $k['impressionsThreshold'] ?? 0)]; }, $row['keywords']['keywords']), 'bg-cyan-500', 10) ?>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </section>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($others): ?>
            <div class="card p-4">
              <p class="text-xs text-ink-500 mb-2"><?= e(t('الحسابات دي مالهاش إحصائيات مباشرة من المنصة، وأرقام منشوراتها موجودة في تبويب النظرة العامة والمنشورات:')) ?></p>
              <div class="flex flex-wrap gap-2">
                <?php foreach ($others as $a): ?>
                  <span class="badge inline-flex items-center gap-1.5"><?= platform_icon((string)($a['platform'] ?? ''), 'w-3.5 h-3.5') ?><?= e($accName($a)) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php else:
          $sa = $D['secAccounts'][0] ?? null;
          $connect = can('accounts.view') ? ' ' . t('تقدر تربطه من صفحة الحسابات المرتبطة.') : '';
          if (!$sa): ?>
            <div class="card p-5">
              <?= an_empty(t('مفيش حساب %s مربوط.', $sec === 'ga' ? t('جوجل أناليتكس') : platform_label($sec)), trim(t('الجزء ده بيظهر لما تربط الحساب.') . $connect), '🔌') ?>
              <?php if (can('accounts.view')): ?><p class="text-center mt-3"><a class="link text-sm" href="<?= e(url('accounts')) ?>"><?= e(t('الحسابات المرتبطة')) ?></a></p><?php endif; ?>
            </div>
          <?php elseif ($sec === 'facebook'): ?>
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-1"><?= e(t('أرباح المنشورات')) ?></h2>
              <p class="text-xs text-ink-500 mb-4"><?= e(t('أرباح أعلى منشورات الصفحة في الفترة (من بداية المنشور). بتظهر بس لو الصفحة مفعّل فيها تحقيق الدخل.')) ?></p>
              <?php
              $earn = array_filter($D['earnings'] ?? [], function ($r) { return !empty($r['data']['metrics']); });
              if (!empty($E['fbPosts'])): ?><?= an_notice($E['fbPosts']) ?>
              <?php elseif (!$earn): ?><?= an_empty(t('الصفحة مفيهاش أرباح للمنشورات دي.'), t('ده طبيعي لو الصفحة مش مشتركة في برنامج تحقيق الدخل من ميتا.'), '💰') ?>
              <?php else: ?>
                <ul class="space-y-3">
                  <?php foreach ($earn as $r): ?>
                    <li class="rounded-lg bg-ink-50 p-3">
                      <p class="text-sm text-ink-800 line-clamp-1"><?= e(mb_substr((string)($r['post']['content'] ?? ''), 0, 90) ?: t('(منشور بدون نص)')) ?></p>
                      <div class="flex flex-wrap gap-4 mt-1">
                        <?php foreach ($r['data']['metrics'] as $mk => $mv): $amt = (float)($mv['total'] ?? 0); if (($mv['unit'] ?? '') === 'micro_amount') $amt /= 1000000; ?>
                          <span class="text-xs text-ink-600"><?= e($mk === 'content_monetization_earnings' ? t('أرباح المحتوى') : t('أرباح تقريبية')) ?>: <b class="text-ink-900 tabular-nums" dir="ltr"><?= e(number_format($amt, 2) . (!empty($mv['currency']) ? ' ' . $mv['currency'] : '')) ?></b></span>
                        <?php endforeach; ?>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </section>
            <div class="grid lg:grid-cols-2 gap-5 [&>*]:min-w-0">
              <?php foreach (['tagged' => [t('منشورات الصفحة متاشة فيها'), t('مفيش حد عمل تاج للصفحة مؤخراً.')], 'visitor' => [t('منشورات الزوار على الصفحة'), t('مفيش زوار نشروا على الصفحة مؤخراً.')]] as $k => [$title, $none]): ?>
                <section class="card p-5">
                  <h2 class="font-bold text-ink-900 mb-3"><?= e($title) ?></h2>
                  <?php if (!empty($E[$k])): ?><?= an_notice($E[$k]) ?>
                  <?php elseif (empty($D[$k])): ?><?= an_empty($none, '', '💬') ?>
                  <?php else: ?>
                    <ul class="divide-y divide-ink-100">
                      <?php foreach ($D[$k] as $fp): ?>
                        <li class="py-2.5">
                          <p class="text-xs text-ink-500"><b class="text-ink-800"><?= e($fp['from']['name'] ?? t('مستخدم')) ?></b> · <?= e(fmt_date($fp['taggedTime'] ?? $fp['createdTime'] ?? null, 'd/m/Y h:i A')) ?></p>
                          <p class="text-sm text-ink-800 line-clamp-2 mt-0.5"><?= e((string)($fp['message'] ?? '')) ?: e(t('(منشور بدون نص)')) ?></p>
                          <?php if (!empty($fp['permalinkUrl'])): ?><a class="link text-xs" href="<?= e($fp['permalinkUrl']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </section>
              <?php endforeach; ?>
            </div>
          <?php elseif ($sec === 'youtube'): ?>
            <section class="card p-5">
              <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                <h2 class="font-bold text-ink-900"><?= e(t('أداء فيديو')) ?></h2>
                <?php if (!empty($D['videos'])): ?>
                  <form method="get" class="flex gap-2 items-end min-w-0">
                    <input type="hidden" name="p" value="analytics"><input type="hidden" name="tab" value="platforms"><input type="hidden" name="sec" value="youtube">
                    <?php if ($acc !== ''): ?><input type="hidden" name="a" value="<?= e($acc) ?>"><?php endif; ?>
                    <?php if ($per['custom']): ?><input type="hidden" name="from" value="<?= e($per['from']) ?>"><input type="hidden" name="to" value="<?= e($per['to']) ?>"><?php else: ?><input type="hidden" name="d" value="<?= (int)$per['days'] ?>"><?php endif; ?>
                    <select name="v" class="input py-1.5 max-w-xs" onchange="this.form.submit()" aria-label="<?= e(t('اختار فيديو')) ?>">
                      <?php foreach ($D['videos'] as $vid => $title): ?><option value="<?= e($vid) ?>" <?= $vid === $D['video'] ? 'selected' : '' ?>><?= e($title) ?></option><?php endforeach; ?>
                    </select>
                    <noscript><button class="btn-ghost"><?= e(t('عرض')) ?></button></noscript>
                  </form>
                <?php endif; ?>
              </div>
              <?php if (!empty($E['ytPosts'])): ?><?= an_notice($E['ytPosts']) ?>
              <?php elseif (empty($D['videos'])): ?><?= an_empty(t('مفيش فيديوهات يوتيوب في آخر سنة.'), '', '🎬') ?>
              <?php else: ?>
                <div class="grid lg:grid-cols-2 gap-6">
                  <div>
                    <p class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('منحنى الاحتفاظ بالمشاهدين')) ?></p>
                    <?php $rc = (array)($D['retention']['retentionCurve'] ?? []);
                    if (!empty($E['retention'])): ?><?= an_notice($E['retention']) ?>
                    <?php elseif (count($rc) < 2): ?><?= an_empty(t('يوتيوب لسه مارجعش منحنى الاحتفاظ للفيديو ده.')) ?>
                    <?php else: ?>
                      <div id="an-ret"></div>
                      <script>AnCharts.line(document.getElementById('an-ret'), {height: 200, aria: <?= json_encode(t('منحنى الاحتفاظ بالمشاهدين'), $jsonFlags) ?>,
                        labels: <?= json_encode(array_map(function ($r) { return round((float)($r['elapsedVideoTimeRatio'] ?? 0) * 100) . '%'; }, $rc)) ?>,
                        fmt: function (v) { return Math.round(v) + '%'; },
                        series: [{key: 'r', label: <?= json_encode(t('مكمّلين مشاهدة'), $jsonFlags) ?>, color: 'rgb(var(--red-500))', values: <?= json_encode(array_map(function ($r) { return round((float)($r['audienceWatchRatio'] ?? 0) * 100, 1); }, $rc)) ?>}]});</script>
                      <p class="text-[11px] text-ink-400 mt-1"><?= e(t('المحور الأفقي: نسبة مدة الفيديو.')) ?></p>
                    <?php endif; ?>
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('المشاهدات اليومية')) ?></p>
                    <?php $dv = (array)($D['dviews']['dailyViews'] ?? []);
                    if (!empty($E['dviews'])): ?><?= an_notice($E['dviews']) ?>
                    <?php elseif (!$dv): ?><?= an_empty(t('مفيش مشاهدات للفيديو ده في الفترة.')) ?>
                    <?php else: ?>
                      <p class="text-2xl font-bold text-ink-900 tabular-nums mb-2"><?= e(an_num($D['dviews']['totalViews'] ?? array_sum(array_column($dv, 'views')))) ?> <span class="text-xs font-normal text-ink-500"><?= e(t('مشاهدة')) ?></span></p>
                      <div id="an-dv"></div>
                      <script>AnCharts.line(document.getElementById('an-dv'), {height: 170, aria: <?= json_encode(t('المشاهدات اليومية'), $jsonFlags) ?>,
                        labels: <?= json_encode(array_map(function ($r) { return date('d/m', strtotime((string)$r['date'])); }, $dv)) ?>,
                        series: [{key: 'v', label: <?= json_encode(t('مشاهدات'), $jsonFlags) ?>, color: 'rgb(var(--brand-600))', values: <?= json_encode(array_map(function ($r) { return (int)($r['views'] ?? 0); }, $dv)) ?>},
                                 {key: 'm', label: <?= json_encode(t('دقائق المشاهدة'), $jsonFlags) ?>, color: 'rgb(var(--cyan-500))', values: <?= json_encode(array_map(function ($r) { return round((float)($r['estimatedMinutesWatched'] ?? 0), 1); }, $dv)) ?>}]});</script>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </section>
          <?php elseif ($sec === 'ga'):
            $gaSum = function ($rep) {
                $out = ['sessions' => 0, 'activeUsers' => 0, 'rows' => []];
                $names = array_map(function ($h) { return $h['name'] ?? ''; }, (array)($rep['metricHeaders'] ?? []));
                foreach ((array)($rep['rows'] ?? []) as $r) {
                    $vals = [];
                    foreach ((array)($r['metricValues'] ?? []) as $i => $mv) $vals[$names[$i] ?? $i] = (float)($mv['value'] ?? 0);
                    $out['sessions'] += $vals['sessions'] ?? 0; $out['activeUsers'] += $vals['activeUsers'] ?? 0;
                    $out['rows'][] = [(string)($r['dimensionValues'][0]['value'] ?? ''), $vals['sessions'] ?? 0];
                }
                return $out;
            };
            ?>
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 mb-1"><?= e(t('زيارات الموقع من السوشيال ميديا')) ?></h2>
              <p class="text-xs text-ink-500 mb-4"><?= e(t('من جوجل أناليتكس: الجلسات والمستخدمين اللي جم من مصادر السوشيال في الفترة.')) ?></p>
              <?php if (!empty($E['ga'])): ?><?= an_notice($E['ga']) ?>
              <?php else: $g = $gaSum($D['ga']); $gp = empty($E['gaPrev']) ? $gaSum($D['gaPrev']) : null; ?>
                <div class="grid grid-cols-2 gap-3 mb-5 max-w-md">
                  <?php foreach (['sessions' => t('الجلسات'), 'activeUsers' => t('المستخدمين')] as $k => $l): ?>
                    <div class="rounded-xl bg-ink-50 p-3"><p class="text-xs text-ink-500"><?= e($l) ?></p><p class="text-2xl font-bold text-ink-900 tabular-nums"><?= e(an_num($g[$k])) ?></p><?= $gp ? an_delta($g[$k], $gp[$k]) : '' ?></div>
                  <?php endforeach; ?>
                </div>
                <?php if ($g['rows']): ?><?= an_hbars($g['rows'], 'bg-lime-500', 10) ?>
                <?php else: ?><?= an_empty(t('مفيش زيارات من السوشيال في الفترة دي.')) ?><?php endif; ?>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <script>
      // يقفل قايمة الحسابات لما تدوس برّاها
      document.addEventListener('click', function (ev) { ['an-acc', 'an-custom'].forEach(function (id) { var d = document.getElementById(id); if (d && d.open && !d.contains(ev.target)) d.open = false; }); });
    </script>
    <script>window.ANX = <?= json_encode(['tabs' => array_map(function ($k) use ($q) { return $q(['tab' => $k]); }, array_keys($tabs)), 'tab' => array_search($tab, array_keys($tabs), true),
      'presets' => [[t('الشهر ده'), 'month'], [t('الشهر اللي فات'), 'lastmonth'], [t('السنة دي'), 'year']], 'date' => t('التاريخ'), 'keysTitle' => t('اختصارات الكيبورد'),
      'keys' => [['1 2 3', t('آخر 7 / 30 / 90 يوم')], ['[ ]', t('القسم السابق / التالي')], ['a', t('اختيار الحساب')], ['?', t('الاختصارات')]], 'chart' => t('الأداء يوم بيوم'), 'compose' => can('posts.create') ? url('compose') : null, 'schedHint' => t('اضغط عشان تجدول منشور في الوقت ده')], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/analytics-ux.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
