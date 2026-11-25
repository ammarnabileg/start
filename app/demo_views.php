<?php
/**
 * عروض الديمو المستقلة (وضع التعلّم). تُستدعى فقط داخل وورك سبيس الديمو عبر short-circuit
 * في أعلى الصفحة، فلا تمسّ منطق الصفحة الحقيقي للمستخدمين. أرقام ثابتة للعرض فقط.
 */

function dm_t($ar, $en) { return (function_exists('lang') && lang() === 'en') ? $en : $ar; }
function dm_n($n) { return number_format((float)$n); }
function dm_money($n) { return (function_exists('lang') && lang() === 'en' ? '$' : '') . number_format((float)$n) . (function_exists('lang') && lang() === 'en' ? '' : ' ر.س'); }

function dm_kpi($ic, $v, $l)
{
    return '<div class="card p-4"><div class="text-2xl">' . $ic . '</div><div class="text-2xl font-bold text-ink-900 mt-1">' . e($v) . '</div><div class="text-xs text-ink-500 mt-0.5">' . e($l) . '</div></div>';
}

function dm_bars($arr, $labels = [])
{
    $max = max($arr) ?: 1; $w = 100 / max(1, count($arr));
    $h = '<div class="flex items-end gap-1" style="height:160px">';
    foreach ($arr as $i => $v) {
        $h .= '<div class="flex-1 flex flex-col items-center justify-end gap-1" title="' . e(dm_n($v)) . '">'
            . '<div class="w-full rounded-t" style="height:' . ($v / $max * 100) . '%;background:linear-gradient(180deg,#2563eb,#06b6d4)"></div>'
            . (isset($labels[$i]) ? '<span class="text-[10px] text-ink-400">' . e($labels[$i]) . '</span>' : '') . '</div>';
    }
    return $h . '</div>';
}

/** بانر صغير يوضّح أن الأرقام تجريبية */
function dm_note()
{
    return '<div class="rounded-xl bg-brand-50 border border-brand-200 text-brand-800 text-sm px-4 py-2 mb-4">'
        . '🎓 ' . e(dm_t('أرقام تجريبية للعرض — في حسابك الحقيقي تظهر بياناتك الفعلية من حساباتك المتصلة.',
            'Sample demo figures — in your real account these show your actual data from your connected accounts.')) . '</div>';
}

/* ============================ التحليلات ============================ */
function demo_render_analytics()
{
    render(t('التحليلات'), 'analytics', function () {
        $months = dm_t(['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
            ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
        $reach = [420, 510, 480, 630, 710, 690, 840, 910, 880, 970, 1120, 1280];
        echo dm_note();
        echo '<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">'
            . dm_kpi('📡', dm_n(3820000), dm_t('إجمالي الوصول (٣٠ يوم)', 'Total reach (30d)'))
            . dm_kpi('❤️', '4.6%', dm_t('معدّل التفاعل', 'Engagement rate'))
            . dm_kpi('👥', dm_n(85500), dm_t('إجمالي المتابعين', 'Total followers'))
            . dm_kpi('🎯', dm_n(548), dm_t('عملاء من المحتوى', 'Content-sourced leads'))
            . '</div>';
        echo '<div class="card p-4 mb-4"><div class="font-bold text-ink-900 mb-3">' . e(dm_t('نمو الوصول (١٢ شهر)', 'Reach growth (12 months)')) . '</div>'
            . dm_bars($reach, $months) . '</div>';
        echo '<div class="grid lg:grid-cols-2 gap-3 mb-4">';
        // أفضل المنشورات
        $posts = [
            [dm_t('من يراقب شبكة الـ SCADA ليلاً؟', 'Who watches your SCADA at night?'), 128000, 5.2, 42],
            [dm_t('٧٠٪ من الاختراقات تبدأ من الطرف', '70% of breaches start at the edge'), 96500, 4.8, 31],
            [dm_t('النسخ الاحتياطي ليس خطة', 'Backup isn\'t a plan'), 74200, 4.1, 23],
        ];
        $rows = '';
        foreach ($posts as $p) $rows .= '<tr class="border-b border-ink-100 last:border-0"><td class="py-2 pe-2">' . e($p[0]) . '</td><td class="py-2 text-ink-600">' . dm_n($p[1]) . '</td><td class="py-2 text-ink-600">' . $p[2] . '%</td><td class="py-2 font-semibold text-emerald-600">' . $p[3] . '</td></tr>';
        echo '<div class="card p-4"><div class="font-bold text-ink-900 mb-3">' . e(dm_t('أفضل المنشورات', 'Top posts')) . '</div>'
            . '<table class="w-full text-sm"><thead><tr class="text-xs text-ink-400"><th class="text-start pb-2">' . e(dm_t('المنشور', 'Post')) . '</th><th class="text-start pb-2">' . e(dm_t('وصول', 'Reach')) . '</th><th class="text-start pb-2">' . e(dm_t('تفاعل', 'Eng.')) . '</th><th class="text-start pb-2">' . e(dm_t('عملاء', 'Leads')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
        // المنصات
        $plats = [['LinkedIn', 48200, 6.4], ['Instagram', 15800, 8.9], ['X', 21500, 3.1]];
        $pr = '';
        foreach ($plats as $pl) $pr .= '<div class="flex items-center justify-between py-2 border-b border-ink-100 last:border-0"><span class="font-medium">' . e($pl[0]) . '</span><span class="text-ink-600 text-sm">👥 ' . dm_n($pl[1]) . '</span><span class="text-emerald-600 text-sm font-semibold">▲ ' . $pl[2] . '%</span></div>';
        echo '<div class="card p-4"><div class="font-bold text-ink-900 mb-3">' . e(dm_t('المنصات', 'Platforms')) . '</div>' . $pr . '</div>';
        echo '</div>';
        echo '<div class="grid grid-cols-2 lg:grid-cols-3 gap-3">'
            . dm_kpi('🧲', dm_n(196), dm_t('عملاء مؤهّلون (MQL)', 'Qualified leads (MQL)'))
            . dm_kpi('💼', dm_money(14200000), dm_t('قيمة الفرص', 'Pipeline value'))
            . dm_kpi('🏆', '27%', dm_t('نسبة الإغلاق', 'Win rate'))
            . '</div>';
    });
}

/* ============================ الإعلانات ============================ */
function demo_render_ads()
{
    render(t('الحملات الإعلانية'), 'ads', function () {
        echo dm_note();
        $ads = [
            [dm_t('حملة OT Security — الربع الرابع', 'OT Security — Q4'), 42000, 1240000, 18400, 310, 4.2],
            [dm_t('إعادة استهداف SOC', 'SOC Retargeting'), 18500, 540000, 9200, 142, 5.8],
            [dm_t('توعية NGFW', 'NGFW Awareness'), 27000, 980000, 12600, 96, 2.4],
        ];
        $spend = array_sum(array_column($ads, 1)); $leads = array_sum(array_column($ads, 4));
        echo '<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">'
            . dm_kpi('💰', dm_money($spend), dm_t('إجمالي الإنفاق', 'Total spend'))
            . dm_kpi('🎯', dm_n($leads), dm_t('عملاء محتملون', 'Leads'))
            . dm_kpi('📈', dm_money($spend / max(1, $leads)), dm_t('تكلفة العميل', 'Cost / lead'))
            . dm_kpi('📣', dm_n(array_sum(array_column($ads, 2))), dm_t('مرّات الظهور', 'Impressions'))
            . '</div>';
        $rows = '';
        foreach ($ads as $a) {
            $roasCls = $a[5] >= 4 ? 'text-emerald-600' : ($a[5] < 3 ? 'text-rose-600' : 'text-ink-700');
            $rows .= '<tr class="border-b border-ink-100 last:border-0">'
                . '<td class="py-2.5 pe-2 font-medium">' . e($a[0]) . ' <span class="badge badge-green ms-1">' . e(dm_t('نشطة', 'Active')) . '</span></td>'
                . '<td class="py-2.5 text-ink-600">' . dm_money($a[1]) . '</td>'
                . '<td class="py-2.5 text-ink-600">' . dm_n($a[2]) . '</td>'
                . '<td class="py-2.5 text-ink-600">' . dm_n($a[3]) . '</td>'
                . '<td class="py-2.5 text-ink-600">' . dm_n($a[4]) . '</td>'
                . '<td class="py-2.5 font-bold ' . $roasCls . '">' . $a[5] . 'x</td></tr>';
        }
        echo '<div class="card p-4 overflow-x-auto"><div class="font-bold text-ink-900 mb-3">' . e(dm_t('الحملات', 'Campaigns')) . '</div>'
            . '<table class="w-full text-sm min-w-[560px]"><thead><tr class="text-xs text-ink-400">'
            . '<th class="text-start pb-2">' . e(dm_t('الحملة', 'Campaign')) . '</th><th class="text-start pb-2">' . e(dm_t('إنفاق', 'Spend')) . '</th><th class="text-start pb-2">' . e(dm_t('ظهور', 'Impr.')) . '</th><th class="text-start pb-2">' . e(dm_t('نقرات', 'Clicks')) . '</th><th class="text-start pb-2">' . e(dm_t('عملاء', 'Leads')) . '</th><th class="text-start pb-2">ROAS</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
    });
}
