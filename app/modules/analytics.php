<?php
/**
 * التحليلات: أداء المنشورات والمتابعين وأفضل وقت للنشر، وتحليلات صندوق الرسائل.
 * كل الدوال قراءة بس (GET).
 */

/** فترات التاريخ المتاحة بالأيام */
function an_ranges(): array
{
    return [7, 30, 90];
}

function an_range_days($v): int
{
    $v = (int)$v;
    return in_array($v, an_ranges(), true) ? $v : 30;
}

/** [fromDate, toDate] بصيغة Y-m-d */
function an_dates(int $days): array
{
    return [gmdate('Y-m-d', time() - $days * 86400), gmdate('Y-m-d')];
}

/**
 * ينفذ نداء ويرجع [data, error]. الخطأ رسالة لطيفة بدل ما الصفحة تقع.
 */
function an_try(callable $fn): array
{
    try {
        return [$fn(), null];
    } catch (ZernioException $ex) {
        $msg = $ex->getMessage();
        $st = (int)($ex->status ?? 0);
        if ($st === 402 || $st === 403 || stripos($msg, 'add-on') !== false || stripos($msg, 'addon') !== false || stripos($msg, 'upgrade') !== false || stripos($msg, 'plan') !== false) {
            return [null, t('الجزء ده محتاج إضافة التحليلات (Analytics add-on) في الاشتراك. صاحب الشركة يقدر يفعّلها من حساب التكامل.')];
        }
        if ($st === 400 || $st === 404 || $st === 422) {
            return [null, t('البيانات دي مش متاحة للحساب ده دلوقتي.')];
        }
        return [null, trim(str_ireplace('Zernio', t('خدمة النشر'), $msg))];
    } catch (Throwable $t) {
        return [null, t('حصلت مشكلة وإحنا بنجيب البيانات. جرب تاني بعد شوية.')];
    }
}

function an_posts(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/analytics', $q);
}

function an_daily_metrics(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/analytics/daily-metrics', $q)['dailyData'] ?? [];
}

function an_follower_stats(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/accounts/follower-stats', $q);
}

function an_best_time(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/analytics/best-time', $q)['slots'] ?? [];
}

function an_inbox(Zernio $z, string $what, array $q): array
{
    $allowed = ['volume', 'heatmap', 'source-breakdown', 'response-time', 'top-accounts'];
    if (!in_array($what, $allowed, true)) {
        throw new ZernioException('bad endpoint');
    }
    return $z->request('GET', '/v1/analytics/inbox/' . $what, $q);
}

/** فرق توقيت الشركة عن UTC بالساعات (لتحويل ساعات Zernio) */
function an_tz_offset_hours(): int
{
    try {
        $tz = new DateTimeZone(site_timezone());
        return (int)round($tz->getOffset(new DateTime('now', new DateTimeZone('UTC'))) / 3600);
    } catch (Throwable $t) {
        return 0;
    }
}

/** أسماء الأيام، المفتاح 0 = الاتنين ... 6 = الحد */
function an_day_names(): array
{
    return [t('الاتنين'), t('التلات'), t('الأربع'), t('الخميس'), t('الجمعة'), t('السبت'), t('الحد')];
}

/** يحول (يوم 0=الاتنين، ساعة UTC) لتوقيت الشركة */
function an_local_slot(int $dow0, int $hour): array
{
    $h = $hour + an_tz_offset_hours();
    $d = $dow0;
    while ($h < 0) { $h += 24; $d--; }
    while ($h > 23) { $h -= 24; $d++; }
    return [(($d % 7) + 7) % 7, $h];
}

function an_hour_label(int $h): string
{
    return date('g A', mktime($h, 0, 0, 1, 1, 2000));
}

function an_num($n): string
{
    $n = (float)$n;
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 10000) return round($n / 1000, 1) . 'K';
    return number_format($n, ($n == (int)$n) ? 0 : 1);
}

function an_duration(?float $s): string
{
    if ($s === null) return '—';
    if ($s < 60) return t('%s ثانية', (int)round($s));
    if ($s < 3600) return t('%s دقيقة', (int)round($s / 60));
    if ($s < 86400) return t('%s ساعة', round($s / 3600, 1));
    return t('%s يوم', round($s / 86400, 1));
}

/** رسم أعمدة بسيط بالـ CSS. $items = [[label, value, title], ...] */
function an_bars(array $items, string $color = 'bg-brand-500', string $height = 'h-40'): string
{
    $max = 0;
    foreach ($items as $it) $max = max($max, (float)$it[1]);
    $out = '<div class="flex items-end gap-0.5 ' . $height . '" dir="ltr">';
    foreach ($items as $it) {
        $pct = $max > 0 ? max(2, round((float)$it[1] / $max * 100)) : 2;
        $out .= '<div class="flex-1 h-full flex items-end" title="' . e($it[2] ?? ($it[0] . ': ' . $it[1])) . '"><div class="w-full rounded-t ' . ($it[1] > 0 ? $color : 'bg-ink-200') . '" style="height:' . $pct . '%"></div></div>';
    }
    $out .= '</div>';
    if ($items) {
        $first = $items[0][0];
        $last = $items[count($items) - 1][0];
        $out .= '<div class="flex justify-between text-[11px] text-ink-500 mt-1" dir="ltr"><span>' . e($first) . '</span><span>' . e($last) . '</span></div>';
    }
    return $out;
}

/** شريط أفقي بنسبة */
function an_hbar(float $value, float $max, string $color = 'bg-brand-500'): string
{
    $pct = $max > 0 ? round($value / $max * 100) : 0;
    return '<div class="h-2 rounded-full bg-ink-200 overflow-hidden"><div class="h-full ' . $color . ' rounded-full" style="width:' . $pct . '%"></div></div>';
}

/** صندوق رسالة لطيف (مش خطأ أحمر) */
function an_notice(string $msg): string
{
    return '<div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 p-4 text-sm">' . e($msg) . '</div>';
}

/* ======================================================================
 * مركز التحليلات (الإصدار الجديد): فترات ومقارنة ورسوم وواجهات المنصات.
 * ====================================================================== */

/**
 * الفترة المختارة من الرابط: d=7/30/90 أو from/to مخصص.
 * بترجع: from, to, pfrom, pto (الفترة اللي قبلها بنفس الطول), len, days, custom
 */
function an_period(array $g): array
{
    $today = gmdate('Y-m-d');
    $ok = function ($s) { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) !== false; };
    $from = $g['from'] ?? '';
    $to = $g['to'] ?? '';
    if ($ok($from) && $ok($to)) {
        if ($to > $today) $to = $today;
        if ($from > $to) [$from, $to] = [$to, $from];
        $len = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($len > 366) { $from = gmdate('Y-m-d', strtotime($to) - 365 * 86400); $len = 366; }
        $custom = true;
        $days = $len;
    } else {
        $days = an_range_days($g['d'] ?? 30);
        $to = $today;
        $from = gmdate('Y-m-d', strtotime($to) - ($days - 1) * 86400);
        $len = $days;
        $custom = false;
    }
    $pto = gmdate('Y-m-d', strtotime($from) - 86400);
    $pfrom = gmdate('Y-m-d', strtotime($pto) - ($len - 1) * 86400);
    return compact('from', 'to', 'pfrom', 'pto', 'len', 'days', 'custom');
}

/** كل الأيام بين تاريخين (Y-m-d) */
function an_date_list(string $from, string $to): array
{
    $out = [];
    for ($t = strtotime($from); $t <= strtotime($to) && count($out) < 400; $t += 86400) $out[] = gmdate('Y-m-d', $t);
    return $out;
}

/** نسبة التغيير، null لو مفيش فترة سابقة نقارن بيها */
function an_change(float $cur, float $prev): ?float
{
    if ($prev == 0.0) return null;
    return ($cur - $prev) / abs($prev) * 100;
}

/** شارة ▲/▼ بنسبة التغيير عن الفترة السابقة */
function an_delta(float $cur, float $prev, bool $points = false): string
{
    $title = e(t('مقارنة بالفترة السابقة: %s', an_num($prev)));
    if ($points) {
        $d = $cur - $prev;
        if (abs($d) < 0.005) return '<span class="text-xs text-ink-500" title="' . $title . '">— 0</span>';
        $up = $d > 0;
        return '<span class="text-xs font-semibold ' . ($up ? 'text-lime-700' : 'text-red-600') . '" dir="ltr" title="' . $title . '">' . ($up ? '▲ +' : '▼ ') . e(number_format($d, 2)) . ' pp</span>';
    }
    $c = an_change($cur, $prev);
    if ($c === null) {
        return $cur > 0
            ? '<span class="text-xs font-semibold text-brand-600" title="' . $title . '">' . e(t('جديد')) . '</span>'
            : '<span class="text-xs text-ink-500" title="' . $title . '">—</span>';
    }
    if (abs($c) < 0.05) return '<span class="text-xs text-ink-500" dir="ltr" title="' . $title . '">— 0%</span>';
    $up = $c > 0;
    return '<span class="text-xs font-semibold ' . ($up ? 'text-lime-700' : 'text-red-600') . '" dir="ltr" title="' . $title . '"><span aria-hidden="true">' . ($up ? '▲' : '▼') . '</span> ' . e(($up ? '+' : '') . number_format($c, abs($c) >= 100 ? 0 : 1)) . '%</span>';
}

/** التفاعل = إعجابات + تعليقات + مشاركات + حفظ */
function an_engagement(array $m): float
{
    return (float)($m['likes'] ?? 0) + (float)($m['comments'] ?? 0) + (float)($m['shares'] ?? 0) + (float)($m['saves'] ?? 0);
}

function an_rate(float $eng, float $imp): float
{
    return $imp > 0 ? $eng / $imp * 100 : 0.0;
}

/** رسم خطي صغير (sparkline) */
function an_sparkline(array $vals, string $class = 'text-brand-600', string $h = 'h-9'): string
{
    $vals = array_values(array_map('floatval', $vals));
    $n = count($vals);
    if ($n < 2) return '<div class="' . $h . '"></div>';
    $mn = min($vals); $mx = max($vals);
    $pts = [];
    foreach ($vals as $i => $v) {
        $pts[] = round($i / ($n - 1) * 100, 2) . ',' . round(29 - ($mx > $mn ? ($v - $mn) / ($mx - $mn) * 27 : 14), 2);
    }
    $line = implode(' ', $pts);
    return '<svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full ' . $h . ' ' . $class . '" dir="ltr" aria-hidden="true">'
        . '<polygon points="0,30 ' . $line . ' 100,30" fill="currentColor" opacity="0.12"/>'
        . '<polyline fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" vector-effect="non-scaling-stroke" points="' . $line . '"/></svg>';
}

/** حالة فاضية لطيفة */
function an_empty(string $title, string $hint = '', string $icon = '📊'): string
{
    return '<div class="rounded-xl border border-dashed border-ink-200 p-6 text-center">'
        . '<div class="text-2xl mb-1" aria-hidden="true">' . $icon . '</div>'
        . '<p class="text-sm font-semibold text-ink-700">' . e($title) . '</p>'
        . ($hint !== '' ? '<p class="text-xs text-ink-500 mt-1 max-w-md mx-auto">' . e($hint) . '</p>' : '')
        . '</div>';
}

/** أعمدة أفقية: $rows = [[label, value], ...] مترتبة. بتعرض النسبة من المجموع */
function an_hbars(array $rows, string $color = 'bg-brand-500', int $limit = 8): string
{
    $total = 0; $max = 0;
    foreach ($rows as $r) { $total += (float)$r[1]; $max = max($max, (float)$r[1]); }
    $out = '<ul class="space-y-2">';
    foreach (array_slice($rows, 0, $limit) as $r) {
        $v = (float)$r[1];
        $pct = $total > 0 ? $v / $total * 100 : 0;
        $w = $max > 0 ? max(1.5, $v / $max * 100) : 0;
        $out .= '<li><div class="flex justify-between gap-2 text-xs mb-0.5"><span class="text-ink-800 truncate">' . e($r[0]) . '</span>'
            . '<span class="text-ink-500 tabular-nums shrink-0" dir="ltr">' . e(an_num($v)) . ' · ' . e(number_format($pct, 1)) . '%</span></div>'
            . '<div class="h-2 rounded-full bg-ink-100 overflow-hidden" role="presentation"><div class="h-full rounded-full ' . $color . '" style="width:' . round($w, 1) . '%"></div></div></li>';
    }
    return $out . '</ul>';
}

/** لون خلية الخريطة الحرارية (0..1) */
function an_heat_style(float $x): string
{
    if ($x <= 0) return 'background:rgb(var(--ink-200))';
    return 'background:rgb(var(--brand-600) / ' . round(0.15 + 0.85 * min(1, $x), 3) . ')';
}

/** خريطة حرارية أيام × ساعات. $grid[day0..6][hour0..23] = قيمة. $tip(d,h,v) بيرجع نص التلميح */
function an_heatmap(array $grid, callable $tip): string
{
    $mx = 0;
    foreach ($grid as $row) foreach ($row as $v) $mx = max($mx, (float)$v);
    $dn = an_day_names();
    $out = '<div class="overflow-x-auto"><table class="border-separate text-[10px] text-ink-500" style="border-spacing:2px" dir="ltr" role="grid">';
    $out .= '<thead><tr><th></th>';
    for ($h = 0; $h < 24; $h++) $out .= '<th class="font-normal w-6">' . ($h % 3 === 0 ? e(date('ga', mktime($h, 0, 0, 1, 1, 2000))) : '') . '</th>';
    $out .= '</tr></thead><tbody>';
    for ($d = 0; $d < 7; $d++) {
        $out .= '<tr><th class="font-normal text-end pe-1 whitespace-nowrap text-ink-600">' . e($dn[$d]) . '</th>';
        for ($h = 0; $h < 24; $h++) {
            $v = (float)($grid[$d][$h] ?? 0);
            $tt = e($tip($d, $h, $v));
            $out .= '<td class="w-6 h-6 min-w-[1.25rem] rounded" style="' . an_heat_style($mx > 0 ? $v / $mx : 0) . '" title="' . $tt . '" aria-label="' . $tt . '"></td>';
        }
        $out .= '</tr>';
    }
    return $out . '</tbody></table></div>';
}

/* ---------- نداءات القراءة الجديدة ---------- */

function an_accounts(Zernio $z): array
{
    return $z->request('GET', '/v1/accounts', array_filter(['profileId' => zernio_profile()]))['accounts'] ?? [];
}

function an_daily_full(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/analytics/daily-metrics', $q);
}

function an_dashboard(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/analytics/dashboard', $q);
}

function an_content_decay(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/analytics/content-decay', $q)['buckets'] ?? [];
}

function an_posting_frequency(Zernio $z, array $q): array
{
    return $z->request('GET', '/v1/analytics/posting-frequency', $q)['frequency'] ?? [];
}

function an_post_timeline(Zernio $z, string $postId): array
{
    return $z->request('GET', '/v1/analytics/post-timeline', ['postId' => $postId])['timeline'] ?? [];
}

/** واجهات الإحصائيات الأصلية لكل منصة */
function an_native_paths(): array
{
    return [
        'facebook' => '/v1/analytics/facebook/page-insights',
        'instagram' => '/v1/analytics/instagram/account-insights',
        'linkedin' => '/v1/analytics/linkedin/org-aggregate-analytics',
        'youtube' => '/v1/analytics/youtube/channel-insights',
        'tiktok' => '/v1/analytics/tiktok/account-insights',
        'googlebusiness' => '/v1/analytics/googlebusiness/performance',
    ];
}

function an_native_insights(Zernio $z, string $platform, string $accountId, string $from, string $to): array
{
    $paths = an_native_paths();
    if (!isset($paths[$platform])) throw new ZernioException(t('المنصة دي مالهاش إحصائيات مباشرة.'));
    $q = ['accountId' => $accountId, 'fromDate' => $from, 'toDate' => $to];
    if ($platform === 'facebook') $q['metrics'] = 'page_media_view,page_total_media_view_unique,page_post_engagements,page_views_total,page_video_views,followers_gained,followers_lost';
    if ($platform === 'linkedin') $q['metrics'] = 'impressions,unique_impressions,clicks,likes,comments,shares,engagement_rate,organic_followers_gained';
    if ($platform === 'instagram') $q['metrics'] = 'reach,views,accounts_engaged,total_interactions,profile_views,website_clicks';
    return $z->request('GET', $paths[$platform], $q);
}

function an_demographics(Zernio $z, string $platform, string $accountId): array
{
    if ($platform === 'facebook') return $z->request('GET', '/v1/analytics/facebook/demographics', ['accountId' => $accountId]);
    if ($platform === 'instagram') return $z->request('GET', '/v1/analytics/instagram/demographics', ['accountId' => $accountId]);
    if ($platform === 'linkedin') return $z->request('GET', '/v1/analytics/linkedin/demographics', ['accountId' => $accountId, 'breakdown' => 'function,seniority,industry,company_size,country']);
    throw new ZernioException(t('المنصة دي مالهاش بيانات جمهور.'));
}

function an_ig_online(Zernio $z, string $accountId): array
{
    return $z->request('GET', '/v1/analytics/instagram/online-followers', ['accountId' => $accountId]);
}

function an_gbp_keywords(Zernio $z, string $accountId, string $from, string $to): array
{
    return $z->request('GET', '/v1/analytics/googlebusiness/search-keywords', ['accountId' => $accountId, 'startMonth' => substr($from, 0, 7), 'endMonth' => substr($to, 0, 7)]);
}

function an_post_reactions(Zernio $z, string $platform, string $accountId, string $platformPostId): ?array
{
    if ($platform === 'facebook') return $z->request('GET', '/v1/accounts/' . rawurlencode($accountId) . '/facebook-post-reactions', ['postId' => $platformPostId]);
    if ($platform === 'linkedin') return $z->request('GET', '/v1/accounts/' . rawurlencode($accountId) . '/linkedin-post-reactions', ['urn' => $platformPostId, 'limit' => 100]);
    return null;
}
