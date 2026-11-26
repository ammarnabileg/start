<?php
/** البريفنج اليومي للمبيعات: ما الذي يحتاج انتباهك اليوم (صفقات واقفة، متابعات متأخرة، قيم شاذة، ميتينجات) */
$wid = (int)ws('id');
$me = (int)(current_user()['id'] ?? 0);
// صاحب الشركة/المدير يشوف الكل افتراضيًا؛ المندوب يشوف عملاءه. ومتاح تبديل العرض.
$scopeAll = is_ws_owner() && (($_GET['scope'] ?? 'me') !== 'me');
$uid = $scopeAll ? 0 : $me;
$B = crm_brief_data($wid, $uid);

$cur = function ($v) { return number_format((float)$v) . ' ' . (lang() === 'en' ? '' : 'ر.س'); };
$leadUrl = function ($id) { return url('prospects', ['lead' => (int)$id]); };
$stageLabel = function ($s) { $st = function_exists('crm_stages') ? crm_stages() : []; return $st[$s] ?? $s; };
$fname = trim((string)(current_user()['name'] ?? ''));
$fname = preg_split('/\s+/u', $fname)[0] ?? '';

render(t('البريفنج اليومي'), 'crm_brief', function () use ($B, $cur, $leadUrl, $stageLabel, $fname, $scopeAll) {
    $card = function ($icon, $title, $color, $rows, $empty) {
        $h = '<section class="card p-4"><div class="flex items-center gap-2 mb-3"><span class="w-9 h-9 rounded-xl grid place-items-center text-lg" style="background:' . $color . '22">' . $icon . '</span>'
            . '<b class="text-ink-900">' . e($title) . '</b><span class="badge ms-auto">' . count($rows) . '</span></div>';
        if (!$rows) return $h . '<div class="text-sm text-ink-400 py-2">' . e($empty) . '</div></section>';
        return $h . '<div class="divide-y divide-ink-100">' . implode('', $rows) . '</div></section>';
    }; ?>
  <div class="mb-5 flex flex-wrap items-center gap-3">
    <div>
      <h1 class="text-2xl font-bold text-ink-900"><?= e(t('صباح الخير%s 👋', $fname ? '، ' . $fname : '')) ?></h1>
      <p class="text-ink-500 text-sm mt-1"><?= e($B['count'] ? t('عندك %s حاجة محتاجة انتباهك النهاردة.', $B['count']) : t('كل حاجة تمام — مفيش حاجة عاجلة النهاردة.')) ?></p>
    </div>
    <?php if (is_ws_owner()): ?>
    <div class="ms-auto flex rounded-xl border border-ink-200 overflow-hidden text-sm">
      <a href="<?= e(url('crm_brief', ['scope' => 'me'])) ?>" class="px-3 py-1.5 <?= !$scopeAll ? 'bg-brand-600 text-[#fff]' : 'text-ink-600' ?>"><?= e(t('عملائي')) ?></a>
      <a href="<?= e(url('crm_brief', ['scope' => 'all'])) ?>" class="px-3 py-1.5 <?= $scopeAll ? 'bg-brand-600 text-[#fff]' : 'text-ink-600' ?>"><?= e(t('الشركة كلها')) ?></a>
    </div>
    <?php endif; ?>
  </div>

  <div class="grid lg:grid-cols-2 gap-4 items-start">
    <?php
    // ميتينجات اليوم
    $rows = [];
    foreach ($B['meetings'] as $m) $rows[] = '<div class="py-2.5 flex items-center gap-3"><span class="text-xs font-bold text-brand-700 w-16" dir="ltr">' . e(fmt_date($m['start_at'], 'h:i A')) . '</span>'
        . '<a href="' . e($leadUrl($m['lead_id'])) . '" class="flex-1 min-w-0"><b class="text-ink-900 block truncate">' . e($m['lead'] ?: t('ميتينج')) . '</b>' . ($m['company'] ? '<span class="text-xs text-ink-500">' . e($m['company']) . '</span>' : '') . '</a>'
        . ($m['next_note'] ? '<span class="text-xs text-ink-400 hidden sm:block max-w-[40%] truncate">' . e($m['next_note']) . '</span>' : '') . '</div>';
    echo $card('📅', t('ميتينجات النهاردة'), '#2563eb', $rows, t('مفيش ميتينجات متسجّلة النهاردة.'));

    // متابعات متأخرة
    $rows = [];
    foreach ($B['overdue'] as $l) $rows[] = '<a href="' . e($leadUrl($l['id'])) . '" class="py-2.5 flex items-center gap-3 hover:bg-ink-50 -mx-1 px-1 rounded-lg"><span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>'
        . '<div class="flex-1 min-w-0"><b class="text-ink-900 block truncate">' . e($l['name']) . '</b>' . ($l['next_note'] ? '<span class="text-xs text-ink-500 truncate block">' . e($l['next_note']) . '</span>' : '') . '</div>'
        . '<span class="text-xs text-rose-600 shrink-0">' . e(fmt_date($l['next_at'], 'd/m')) . '</span></a>';
    foreach ($B['tasks'] as $tk) $rows[] = '<div class="py-2.5 flex items-center gap-3"><span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>'
        . '<div class="flex-1 min-w-0"><b class="text-ink-900 block truncate">' . e($tk['title']) . '</b>' . ($tk['lead'] ? '<span class="text-xs text-ink-500">' . e($tk['lead']) . '</span>' : '') . '</div>'
        . '<span class="text-xs text-amber-600 shrink-0">' . e($tk['due_at'] ? fmt_date($tk['due_at'], 'd/m') : '') . '</span></div>';
    echo $card('⏰', t('متابعات ومهام فات موعدها'), '#dc2626', $rows, t('مفيش متابعات متأخرة — عاش! 🎯'));

    // صفقات واقفة
    $rows = [];
    foreach ($B['stuck'] as $l) $rows[] = '<a href="' . e($leadUrl($l['id'])) . '" class="py-2.5 flex items-center gap-3 hover:bg-ink-50 -mx-1 px-1 rounded-lg">'
        . '<div class="flex-1 min-w-0"><b class="text-ink-900 block truncate">' . e($l['name']) . '</b><span class="text-xs text-ink-500">' . e($stageLabel($l['stage'])) . ($l['company'] ? ' · ' . e($l['company']) : '') . '</span></div>'
        . '<span class="text-xs font-semibold text-ink-400 shrink-0">' . e(t('واقفة %s يوم', (int)$l['idle_days'])) . '</span></a>';
    echo $card('🧊', t('صفقات واقفة محتاجة تحريك'), '#0891b2', $rows, t('مفيش صفقات واقفة — كله بيتحرّك.'));

    // قيم شاذة
    $rows = [];
    foreach ($B['anomaly'] as $l) $rows[] = '<a href="' . e($leadUrl($l['id'])) . '" class="py-2.5 flex items-center gap-3 hover:bg-ink-50 -mx-1 px-1 rounded-lg">'
        . '<span class="text-sm">' . ($l['kind'] === 'high' ? '🔺' : '🔻') . '</span>'
        . '<div class="flex-1 min-w-0"><b class="text-ink-900 block truncate">' . e($l['name']) . '</b><span class="text-xs text-ink-500">' . e($stageLabel($l['stage'])) . '</span></div>'
        . '<span class="text-xs font-semibold ' . ($l['kind'] === 'high' ? 'text-emerald-600' : 'text-amber-600') . ' shrink-0" dir="ltr">' . e($cur($l['deal_value'])) . '</span></a>';
    $avgTxt = $B['avg'] > 0 ? t('المتوسط ≈ %s', $cur($B['avg'])) : '';
    echo $card('⚖️', t('صفقات بقيمة غير معتادة') . ($avgTxt ? ' · ' . $avgTxt : ''), '#7c3aed', $rows, t('كل القيم في النطاق الطبيعي.'));
    ?>
  </div>
<?php });
