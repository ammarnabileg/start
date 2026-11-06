<?php
/** إدارة العملاء ← تقارير المبيعات: أداء كل موظف، الأهداف الشهرية، الصفقات، كريديت Apollo. حسابات بس، من غير AI */
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$canManage = can('crm.manage');
$period = in_array($_GET['period'] ?? '', ['month', 'last_month', '7d', '30d'], true) ? $_GET['period'] : 'month';
$ym = (new DateTime($period === 'last_month' ? 'first day of last month' : 'now', new DateTimeZone(site_timezone())))->format('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'goals' && $canManage) {
    $num = function ($v) { return max(0, (float)str_replace(',', '', (string)$v)); };
    $g = ['team' => ['won' => $num($_POST['team_won'] ?? 0), 'value' => $num($_POST['team_value'] ?? 0), 'contacts' => $num($_POST['team_contacts'] ?? 0)], 'users' => []];
    foreach ((array)($_POST['u'] ?? []) as $id => $x) $g['users'][(int)$id] = ['won' => $num($x['won'] ?? 0), 'value' => $num($x['value'] ?? 0), 'contacts' => $num($x['contacts'] ?? 0)];
    $gym = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['ym'] ?? '')) ? $_POST['ym'] : $ym;
    save_setting('crm_goals_' . $wid . '_' . $gym, json_encode($g));
    flash('success', t('اتحفظت الأهداف.'));
    redirect(url('crm_reports', ['period' => $period]));
}

[$from, $to] = crm_period($period);
$rows = crm_team_report($from, $to);
if (!$canManage) $rows = array_values(array_filter($rows, function ($r) use ($uid) { return $r['id'] === $uid; })); // الموظف بيشوف نفسه بس
$goals = crm_goals($ym);
$tot = ['leads' => 0, 'contacts' => 0, 'calls' => 0, 'answered' => 0, 'won' => 0, 'value' => 0, 'reveals' => 0, 'open' => 0, 'overdue' => 0];
foreach ($rows as $r) foreach ($tot as $k => $_) $tot[$k] += $r[$k];
$st = db()->prepare("SELECT COUNT(*), COALESCE(SUM(value), 0) FROM crm_deals WHERE workspace_id = ? AND status = 'open'"); $st->execute([$wid]); [$openDeals, $openVal] = $st->fetch(PDO::FETCH_NUM);
$st = db()->prepare("SELECT d.title, d.value, d.close_at, l.name, l.id FROM crm_deals d JOIN crm_leads l ON l.id = d.lead_id WHERE d.workspace_id = ? AND d.status = 'open' AND d.close_at IS NOT NULL AND d.close_at <= ? ORDER BY d.close_at LIMIT 8");
$st->execute([$wid, date('Y-m-d', strtotime('+14 days'))]); $closing = $st->fetchAll();
$limit = (int)(crm_prefs()['daily_limit'] ?? 0);
// مصادر العملاء: منين بييجوا، وأنهي مصدر بيتحول لعملاء فعلًا (كل الوقت)
$st = db()->prepare("SELECT source, COUNT(*) n, SUM(stage <> 'new') c, SUM(stage = 'won') w, COALESCE(SUM(CASE WHEN stage = 'won' THEN deal_value END), 0) v FROM crm_leads WHERE workspace_id = ? GROUP BY source ORDER BY n DESC");
$st->execute([$wid]); $srcRows = $st->fetchAll();
$links = crm_links_report();

render(t('تقارير المبيعات'), 'crm_reports', function () use ($rows, $tot, $goals, $period, $canManage, $ym, $openDeals, $openVal, $closing, $limit, $srcRows, $links) {
    $periods = ['month' => t('الشهر ده'), 'last_month' => t('الشهر اللي فات'), '7d' => t('آخر 7 أيام'), '30d' => t('آخر 30 يوم')];
    $isMonth = in_array($period, ['month', 'last_month'], true);
    $bar = function ($val, $goal) {
        if (!$goal) return '';
        $pc = min(100, round($val / $goal * 100));
        return '<div class="h-1.5 rounded-full bg-ink-100 mt-1 overflow-hidden"><div class="h-full rounded-full ' . ($pc >= 100 ? 'bg-lime-500' : ($pc >= 60 ? 'bg-brand-500' : 'bg-amber-400')) . '" style="width:' . $pc . '%"></div></div><div class="text-[10px] text-ink-400 mt-0.5">' . e(t('%s من هدف %s', $pc . '%', number_format($goal))) . '</div>';
    };
    $tg = $goals['team'] ?? [];
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="inline-flex rounded-xl border border-ink-200 bg-white p-0.5"><?php foreach ($periods as $k => $v): ?><a href="<?= e(url('crm_reports', ['period' => $k])) ?>" class="px-3 py-1.5 text-sm rounded-lg <?= $k === $period ? 'bg-brand-600 text-white' : 'text-ink-600 hover:bg-ink-50' ?>"><?= e($v) ?></a><?php endforeach; ?></div>
      <?php if ($canManage && $isMonth): ?><button type="button" class="btn-ghost ms-auto" onclick="document.getElementById('rp-goals').classList.toggle('hidden')">🎯 <?= e(t('الأهداف الشهرية')) ?></button><?php endif; ?>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('بقوا عملاء')) ?></div><div class="text-2xl font-bold text-lime-700"><?= (int)$tot['won'] ?></div><?= $isMonth ? $bar($tot['won'], $tg['won'] ?? 0) : '' ?></div>
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('قيمة المبيعات')) ?></div><div class="text-2xl font-bold text-ink-900"><?= e(number_format($tot['value'])) ?></div><?= $isMonth ? $bar($tot['value'], $tg['value'] ?? 0) : '' ?></div>
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('مرات التواصل')) ?></div><div class="text-2xl font-bold text-ink-900"><?= (int)$tot['contacts'] ?></div><?= $isMonth ? $bar($tot['contacts'], $tg['contacts'] ?? 0) : '' ?></div>
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('صفقات مفتوحة')) ?></div><div class="text-2xl font-bold text-brand-700"><?= e(number_format((float)$openVal)) ?></div><div class="text-[11px] text-ink-500"><?= e(t('%s صفقة', (int)$openDeals)) ?></div></div>
      <div class="card p-4 <?= $tot['overdue'] ? 'border-amber-300 bg-amber-50' : '' ?>"><div class="text-xs text-ink-500"><?= e(t('متابعات متأخرة')) ?></div><div class="text-2xl font-bold <?= $tot['overdue'] ? 'text-amber-700' : 'text-ink-900' ?>"><?= (int)$tot['overdue'] ?></div><div class="text-[11px] text-ink-500"><?= e(t('من %s عميل مفتوح', (int)$tot['open'])) ?></div></div>
    </div>

    <?php if ($canManage && $isMonth): ?>
    <form id="rp-goals" method="post" class="hidden card p-4 mb-5 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="goals"><input type="hidden" name="ym" value="<?= e($ym) ?>">
      <div class="font-semibold text-ink-900">🎯 <?= e(t('أهداف %s', $ym)) ?></div>
      <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-xs text-ink-500"><th class="text-start py-1"></th><th><?= e(t('عملاء جداد')) ?></th><th><?= e(t('قيمة المبيعات')) ?></th><th><?= e(t('مرات التواصل')) ?></th></tr></thead><tbody>
        <tr><td class="py-1 font-medium"><?= e(t('الفريق كله')) ?></td><?php foreach (['won', 'value', 'contacts'] as $k): ?><td class="px-1"><input class="input !py-1 text-sm" name="team_<?= $k ?>" inputmode="numeric" dir="ltr" value="<?= e((string)($tg[$k] ?? '')) ?>"></td><?php endforeach; ?></tr>
        <?php foreach ($rows as $r): $ug = $goals['users'][$r['id']] ?? []; ?><tr><td class="py-1"><?= e($r['name']) ?></td><?php foreach (['won', 'value', 'contacts'] as $k): ?><td class="px-1"><input class="input !py-1 text-sm" name="u[<?= (int)$r['id'] ?>][<?= $k ?>]" inputmode="numeric" dir="ltr" value="<?= e((string)($ug[$k] ?? '')) ?>"></td><?php endforeach; ?></tr><?php endforeach; ?>
      </tbody></table></div>
      <button class="btn-primary"><?= e(t('احفظ')) ?></button>
    </form>
    <?php endif; ?>

    <div class="card overflow-x-auto mb-5">
      <table class="w-full text-sm min-w-[860px]">
        <thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="text-start p-3"><?= e(t('الموظف')) ?></th><th><?= e(t('عملاء جداله')) ?></th><th><?= e(t('تواصل')) ?></th><th><?= e(t('مكالمات (ردّت)')) ?></th><th><?= e(t('بقوا عملاء')) ?></th><th><?= e(t('القيمة')) ?></th><th title="<?= e(t('الوسيط: نص العملاء اتكلّموا أسرع من كده')) ?>"><?= e(t('سرعة أول رد')) ?></th><th><?= e(t('متأخرة')) ?></th><th title="<?= e(t('كشف وتكميل من Apollo')) ?>"><?= e(t('كريديت Apollo')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $ug = $goals['users'][$r['id']] ?? []; $conv = $r['leads'] ? round($r['won'] / $r['leads'] * 100) : null; ?>
          <tr class="border-b border-ink-50 align-top">
            <td class="p-3 font-medium text-ink-900"><?= e($r['name']) ?><?php if ($conv !== null): ?><div class="text-[11px] text-ink-500 font-normal"><?= e(t('تحويل %s', $conv . '%')) ?></div><?php endif; ?></td>
            <td class="text-center p-2"><?= (int)$r['leads'] ?></td>
            <td class="text-center p-2"><?= (int)$r['contacts'] ?><?= $isMonth ? $bar($r['contacts'], $ug['contacts'] ?? 0) : '' ?></td>
            <td class="text-center p-2"><?= (int)$r['calls'] ?> <span class="text-ink-400">(<?= (int)$r['answered'] ?>)</span></td>
            <td class="text-center p-2 font-semibold text-lime-700"><?= (int)$r['won'] ?><?= $isMonth ? $bar($r['won'], $ug['won'] ?? 0) : '' ?></td>
            <td class="text-center p-2"><?= e(number_format($r['value'])) ?><?= $isMonth ? $bar($r['value'], $ug['value'] ?? 0) : '' ?></td>
            <td class="text-center p-2 <?= $r['resp'] !== null && $r['resp'] > 1440 ? 'text-red-600' : '' ?>"><?= e(fmt_minutes($r['resp'])) ?></td>
            <td class="text-center p-2 <?= $r['overdue'] ? 'text-amber-700 font-semibold' : 'text-ink-400' ?>"><?= (int)$r['overdue'] ?></td>
            <td class="text-center p-2"><?= (int)$r['reveals'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" class="p-8 text-center text-ink-500"><?= e(t('مفيش بيانات في الفترة دي.')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="text-[11px] text-ink-400 -mt-3 mb-5"><?= e(t('"تواصل" = مكالمات وواتساب وإيميلات واجتماعات اتسجلت. "سرعة أول رد" = من وقت ما العميل اتضاف لحد أول تواصل معاه. حد الكشف اليومي لكل موظف: %s.', $limit)) ?></p>

    <?php if ($srcRows): $srcL = crm_sources(); $best = 0; foreach ($srcRows as $r) if ($r['n'] >= 3) $best = max($best, $r['w'] / $r['n']); ?>
    <div class="card p-4 mb-5"><div class="font-semibold text-ink-900 mb-1">🧭 <?= e(t('مصادر العملاء')) ?></div>
      <p class="text-[11px] text-ink-500 mb-3"><?= e(t('منين بييجي العملاء، وأنهي مصدر بيتحول لعملاء فعلًا. ركّز مجهودك على اللي نسبته أعلى.')) ?></p>
      <div class="space-y-2"><?php $mx = max(array_column($srcRows, 'n')); foreach ($srcRows as $r): $conv = $r['n'] ? $r['w'] / $r['n'] : 0; ?>
        <div class="grid grid-cols-[120px_1fr_70px_70px] sm:grid-cols-[140px_1fr_90px_90px_110px] items-center gap-3 text-sm">
          <span class="truncate text-ink-800"><?= e($srcL[$r['source']] ?? $r['source']) ?></span>
          <div class="h-2.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-brand-500/80" style="width:<?= max(3, round($r['n'] / $mx * 100)) ?>%"></div></div>
          <span class="text-ink-600 text-xs"><?= e(t('%s عميل', (int)$r['n'])) ?></span>
          <span class="text-xs font-semibold <?= $r['n'] >= 3 && $conv === $best && $best > 0 ? 'text-lime-700' : 'text-ink-700' ?>"><?= round($conv * 100) ?>% <?= $r['n'] >= 3 && $conv === $best && $best > 0 ? '🏆' : '' ?></span>
          <span class="hidden sm:block text-xs text-ink-500 text-end"><?= (float)$r['v'] ? e(number_format((float)$r['v'])) : '' ?></span>
        </div>
      <?php endforeach; ?></div>
      <p class="text-[11px] text-ink-400 mt-2"><?= e(t('النسبة = كام في المية بقوا عملاء. 🏆 = أحسن مصدر (من 3 عملاء أو أكتر).')) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($links): ?>
    <div class="card p-4 mb-5"><div class="font-semibold text-ink-900 mb-1">🔗 <?= e(t('روابط المنشورات')) ?></div>
      <p class="text-[11px] text-ink-500 mb-3"><?= e(t('كل لينك متتبع حطيته في منشور: كام واحد ضغط عليه، وكام بقى عميل محتمل من الفورم أو الحجز.')) ?></p>
      <div class="overflow-x-auto"><table class="w-full text-sm min-w-[560px]"><thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="text-start py-1.5"><?= e(t('المنشور')) ?></th><th><?= e(t('ضغطات')) ?></th><th><?= e(t('عملاء')) ?></th><th><?= e(t('بقوا عملاء')) ?></th><th><?= e(t('تحويل')) ?></th></tr></thead><tbody>
      <?php foreach ($links as $lk): ?><tr class="border-b border-ink-50"><td class="py-2 max-w-[320px]"><div class="truncate text-ink-800" dir="auto"><?= e($lk['label'] ?: $lk['url']) ?></div><div class="text-[11px] text-ink-400 truncate" dir="ltr"><?= e($lk['url']) ?></div></td>
        <td class="text-center font-semibold"><?= (int)$lk['clicks'] ?></td><td class="text-center"><?= (int)$lk['leads'] ?></td><td class="text-center text-lime-700 font-semibold"><?= (int)$lk['won'] ?></td>
        <td class="text-center text-xs"><?= $lk['clicks'] ? round($lk['leads'] / $lk['clicks'] * 100) . '%' : '—' ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div>
    <?php endif; ?>

    <?php if ($closing): ?>
    <div class="card p-4"><div class="font-semibold text-ink-900 mb-2">⏳ <?= e(t('صفقات متوقع تتقفل خلال أسبوعين')) ?></div>
      <div class="divide-y divide-ink-100"><?php foreach ($closing as $d): ?><a href="<?= e(url('crm_leads') . '#lead-' . (int)$d['id']) ?>" class="flex items-center gap-3 py-2 text-sm hover:bg-ink-50 px-1 rounded"><span class="flex-1"><b><?= e($d['title']) ?></b> · <?= e($d['name']) ?></span><span><?= e(number_format((float)$d['value'])) ?></span><span class="text-xs <?= strtotime($d['close_at']) < strtotime('today') ? 'text-red-600' : 'text-ink-500' ?>"><?= e(fmt_date($d['close_at'], 'd/m')) ?></span></a><?php endforeach; ?></div></div>
    <?php endif; ?>
    <?php
}, ['full' => true]);
