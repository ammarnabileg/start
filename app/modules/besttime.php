<?php
/**
 * أحسن أوقات النشر بتوقيت الشركة (من تحليلات المنشورات السابقة).
 * بيستخدمها: زرار "أحسن وقت" في صفحة النشر، واقتراح مواعيد الطابور.
 */

/** أحسن المواعيد مترتبة حسب التفاعل. dow: 0 = الأحد (زي الطابور) */
function best_slots_local(Zernio $z, int $limit = 6): array
{
    if (!function_exists('an_best_time')) return [];
    try {
        $slots = an_best_time($z, ['profileId' => zernio_profile()]);
    } catch (Throwable $t) {
        return [];
    }
    $tz = new DateTimeZone(site_timezone());
    $out = [];
    foreach ($slots as $s) {
        if ((int)($s['post_count'] ?? 0) < 1) continue;
        // 2024-01-01 كان يوم اتنين، والـ API بيبدأ الأسبوع من الاتنين وبتوقيت UTC
        $d = new DateTime('2024-01-01 00:00:00', new DateTimeZone('UTC'));
        $d->modify('+' . (int)$s['day_of_week'] . ' day +' . (int)$s['hour'] . ' hour');
        $d->setTimezone($tz);
        $out[] = ['dow' => (int)$d->format('w'), 'hour' => (int)$d->format('G'), 'score' => (float)($s['avg_engagement'] ?? 0), 'posts' => (int)$s['post_count']];
    }
    usort($out, function ($a, $b) { return $b['score'] <=> $a['score']; });
    return array_slice($out, 0, $limit);
}

/** أقرب ميعاد جاي من أحسن 5 مواعيد (بعد نص ساعة على الأقل) */
function next_best_time(Zernio $z): ?DateTime
{
    $tz = new DateTimeZone(site_timezone());
    $now = new DateTime('+30 minutes', $tz);
    $best = null;
    foreach (best_slots_local($z, 5) as $s) {
        $c = clone $now;
        $c->setTime($s['hour'], 0);
        $diff = ($s['dow'] - (int)$c->format('w') + 7) % 7;
        if ($diff) $c->modify("+$diff day");
        if ($c <= $now) $c->modify('+7 day');
        if (!$best || $c < $best) $best = $c;
    }
    return $best;
}

function weekday_name(int $dow): string
{
    $n = [t('الأحد'), t('الاتنين'), t('التلات'), t('الأربع'), t('الخميس'), t('الجمعة'), t('السبت')];
    return $n[$dow] ?? '';
}
