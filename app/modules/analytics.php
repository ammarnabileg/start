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
        return [null, $msg];
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
