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

// #97 نسب العمولات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'comm' && $canManage) {
    $pc = function ($v) { return max(0, min(100, (float)str_replace(',', '.', (string)$v))); };
    $u = []; foreach ((array)($_POST['rate'] ?? []) as $id => $v) if (trim((string)$v) !== '') $u[(int)$id] = $pc($v);
    save_setting('crm_comm_' . $wid, json_encode(['def' => $pc($_POST['def'] ?? 0), 'users' => $u]));
    flash('success', t('اتحفظت نسب العمولات.'));
    redirect(url('crm_reports', ['period' => $period]) . '#comm');
}
[$from, $to] = crm_period($period);
// نقل الميزانية للحملات الكسبانة: إعدادات + تنفيذ + تراجع (السيرفر بيحسب الخطة من جديد، مابيثقش في أرقام جاية من المتصفح)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['bshift_cfg', 'bshift', 'bshift_undo'], true)) {
    $back = url('crm_reports', ['period' => $period]) . '#bshift';
    if (!$canManage || !can('ads.spend')) { flash('error', t('تعديل الميزانية لصاحب الشركة أو اللي عنده صلاحية الصرف بس.')); redirect($back); }
    $a = (string)$_POST['action'];
    if ($a === 'bshift_cfg') {
        $n = function ($k, $lo, $hi, $d) { $v = (float)str_replace(',', '.', (string)($_POST[$k] ?? $d)); return max($lo, min($hi, $v)); };
        save_setting('crm_bshift_' . $wid, json_encode(['step' => $n('step', 5, 50, 20), 'min_spend' => $n('min_spend', 0, 100000, 50), 'win_roi' => $n('win_roi', 0, 10000, 30), 'lose_roi' => $n('lose_roi', -100, 0, -50), 'cap' => $n('cap', 0, 10000000, 0)]));
        flash('success', t('اتحفظت قواعد نقل الميزانية.')); redirect($back);
    }
    if (empty($_POST['confirm_spend'])) { flash('error', t('لازم تعلّم على خانة التأكيد الأول.')); redirect($back); }
    if ($a === 'bshift_undo') {
        $last = crm_bshift_log()[0] ?? null;
        if (!$last || $last['kind'] !== 'apply') { flash('error', t('مفيش تغيير نقدر نرجعه.')); redirect($back); }
        $rev = array_map(function ($ch) { return ['from' => $ch['to'], 'to' => $ch['from']] + $ch; }, $last['items']);
        [$ok, $fail] = crm_bshift_apply($rev, 'undo');
        if ($ok) flash('success', t('رجعنا ميزانية %s حملة زي ما كانت.', count($ok))); if ($fail) flash('error', t('%s حملة ما اتعدلتش: %s', count($fail), $fail[0]));
        redirect($back);
    }
    $sel = array_map('strval', (array)($_POST['pick'] ?? []));
    [$plan] = crm_bshift_plan(crm_report_campaigns($from, $to)['rows']);
    $plan = array_values(array_filter($plan, function ($ch) use ($sel) { return in_array($ch['id'], $sel, true); }));
    if (!$plan) { flash('error', t('اختار حملة واحدة على الأقل.')); redirect($back); }
    [$ok, $fail] = crm_bshift_apply($plan);
    if ($ok) flash('success', t('اتعدلت ميزانية %s حملة. تقدر ترجع التغيير من سجل التغييرات.', count($ok))); if ($fail) flash('error', t('%s حملة ما اتعدلتش: %s', count($fail), $fail[0]));
    redirect($back);
}
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
$coupons = function_exists('coupon_list') ? coupon_list() : [];
$fc = crm_forecast();
$camp = function_exists('crm_report_campaigns') && $canManage ? crm_report_campaigns($from, $to) : ['rows' => [], 'err' => ''];
[$bPlan, $bNotes] = $camp['rows'] && function_exists('crm_bshift_plan') ? crm_bshift_plan($camp['rows']) : [[], []];
$cycle = function_exists('crm_cycle') ? crm_cycle($from, $to) : ['n' => 0];
$comm = function_exists('crm_comm_cfg') ? crm_comm_cfg() : ['def' => 0, 'users' => []];
// #99 تصدير Excel (CSV بيفتح في Excel مباشرة)
if (($_GET['export'] ?? '') === 'csv' && $canManage) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="sales-report-' . $period . '-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, [t('الفريق')]); fputcsv($o, [t('الموظف'), t('عملاء جداد'), t('تواصل'), t('مكالمات'), t('بقوا عملاء'), t('القيمة'), t('العمولة')]);
    foreach ($rows as $r) fputcsv($o, [$r['name'], $r['leads'], $r['contacts'], $r['calls'], $r['won'], $r['value'], round($r['value'] * crm_comm_rate((int)$r['id']) / 100, 2)]);
    fputcsv($o, []); fputcsv($o, [t('المصادر')]); fputcsv($o, [t('المصدر'), t('عملاء'), t('اتكلمنا معاهم'), t('بقوا عملاء'), t('القيمة')]);
    foreach ($srcRows as $r) fputcsv($o, [crm_sources()[$r['source']] ?? $r['source'], $r['n'], $r['c'], $r['w'], $r['v']]);
    if ($camp['rows']) { fputcsv($o, []); fputcsv($o, [t('الحملات الإعلانية')]); fputcsv($o, [t('الحملة'), t('الصرف'), t('عملاء'), t('بقوا عملاء'), t('المبيعات'), t('تكلفة العميل'), t('العائد %')]);
        foreach ($camp['rows'] as $c) fputcsv($o, [$c['name'], round($c['spend'], 2), $c['leads'], $c['won'], $c['value'], $c['cpl'] !== null ? round($c['cpl'], 2) : '', $c['roi'] !== null ? round($c['roi']) : '']); }
    fclose($o); exit;
}
$st = db()->prepare("SELECT lost_reason, COUNT(*) n FROM crm_leads WHERE workspace_id = ? AND stage = 'lost' AND lost_reason IS NOT NULL GROUP BY lost_reason ORDER BY n DESC"); $st->execute([$wid]); $lostRows = $st->fetchAll();

render(t('تقارير المبيعات'), 'crm_reports', function () use ($rows, $tot, $goals, $period, $canManage, $ym, $openDeals, $openVal, $closing, $limit, $srcRows, $links, $fc, $lostRows, $coupons, $camp, $cycle, $comm, $bPlan, $bNotes) {
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
      <span class="ms-auto"></span>
      <?php if ($canManage): ?><a href="<?= e(url('crm_reports', ['period' => $period, 'export' => 'csv'])) ?>" class="btn-ghost no-print" download><?= ui_icon('download', 'w-4 h-4') ?>Excel</a><?php endif; ?>
      <button type="button" class="btn-ghost no-print" onclick="window.print()"><?= ui_icon('file', 'w-4 h-4') ?>PDF</button>
      <?php if ($canManage && $isMonth): ?><button type="button" class="btn-ghost no-print" onclick="document.getElementById('rp-goals').classList.toggle('hidden')"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z"/></svg> <?= e(t('الأهداف الشهرية')) ?></button><?php endif; ?>
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
      <div class="font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z"/></svg> <?= e(t('أهداف %s', $ym)) ?></div>
      <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-xs text-ink-500"><th class="text-start py-1"></th><th><?= e(t('عملاء جداد')) ?></th><th><?= e(t('قيمة المبيعات')) ?></th><th><?= e(t('مرات التواصل')) ?></th></tr></thead><tbody>
        <tr><td class="py-1 font-medium"><?= e(t('الفريق كله')) ?></td><?php foreach (['won', 'value', 'contacts'] as $k): ?><td class="px-1"><input class="input !py-1 text-sm" name="team_<?= $k ?>" inputmode="numeric" dir="ltr" value="<?= e((string)($tg[$k] ?? '')) ?>"></td><?php endforeach; ?></tr>
        <?php foreach ($rows as $r): $ug = $goals['users'][$r['id']] ?? []; ?><tr><td class="py-1"><?= e($r['name']) ?></td><?php foreach (['won', 'value', 'contacts'] as $k): ?><td class="px-1"><input class="input !py-1 text-sm" name="u[<?= (int)$r['id'] ?>][<?= $k ?>]" inputmode="numeric" dir="ltr" value="<?= e((string)($ug[$k] ?? '')) ?>"></td><?php endforeach; ?></tr><?php endforeach; ?>
      </tbody></table></div>
      <button class="btn-primary"><?= e(t('احفظ')) ?></button>
    </form>
    <?php endif; ?>

    <?php // #96 لوحة المتصدرين + #94 سرعة الدورة
      $lb = $rows; usort($lb, function ($a, $b) { return $b['value'] <=> $a['value'] ?: $b['won'] <=> $a['won'] ?: $b['contacts'] <=> $a['contacts']; });
      $medal = ['text-amber-500 bg-amber-50 border-amber-200', 'text-ink-400 bg-ink-50 border-ink-200', 'text-orange-600 bg-orange-50 border-orange-200']; ?>
    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-4 mb-5">
      <section class="card p-4" id="lb"><div class="flex items-center gap-2 mb-3"><b class="text-ink-900 inline-flex items-center gap-1.5"><?= ui_icon('trophy', 'w-4 h-4 text-amber-500') ?><?= e(t('لوحة المتصدرين')) ?></b><span class="text-xs text-ink-500"><?= e($periods[$period]) ?></span></div>
        <?php if (!$lb): ?><p class="text-sm text-ink-400"><?= e(t('لسه مفيش نشاط في الفترة دي.')) ?></p><?php endif; ?>
        <div class="space-y-2"><?php $mx = max(1, (float)($lb[0]['value'] ?? 0), 1); foreach (array_slice($lb, 0, 8) as $i => $r): ?>
          <div class="flex items-center gap-3 rounded-xl border px-3 py-2 <?= $i < 3 ? $medal[$i] : 'border-ink-100' ?>">
            <span class="w-7 h-7 rounded-full grid place-items-center text-sm font-bold <?= $i < 3 ? 'bg-white' : 'bg-ink-100 text-ink-500' ?>"><?= $i < 3 ? ui_icon('trophy', 'w-4 h-4') : $i + 1 ?></span>
            <span class="flex-1 min-w-0"><span class="flex items-center gap-2"><b class="text-sm text-ink-900 truncate"><?= e($r['name']) ?></b><span class="text-[11px] text-ink-500"><?= e(t('%s بقوا عملاء · %s تواصل', (int)$r['won'], (int)$r['contacts'])) ?></span></span>
              <span class="block h-1.5 mt-1 rounded-full bg-white/70 overflow-hidden"><span class="block h-full rounded-full bg-brand-500" style="width:<?= max(3, round((float)$r['value'] / $mx * 100)) ?>%"></span></span></span>
            <b class="text-sm tabular-nums text-ink-900"><?= e(number_format((float)$r['value'])) ?></b>
          </div><?php endforeach; ?></div></section>
      <section class="card p-4" id="cycle"><b class="text-ink-900 inline-flex items-center gap-1.5 mb-1"><?= ui_icon('timer', 'w-4 h-4 text-brand-600') ?><?= e(t('سرعة الدورة')) ?></b>
        <p class="text-[11px] text-ink-500 mb-3"><?= e(t('كام يوم من أول ما العميل اتسجل لحد ما بقى عميل (للي اتقفلوا في الفترة دي).')) ?></p>
        <?php if (!$cycle['n']): ?><p class="text-sm text-ink-400"><?= e(t('مفيش صفقات اتقفلت في الفترة دي.')) ?></p><?php else: ?>
          <div class="grid grid-cols-3 gap-2 mb-3 text-center"><?php foreach ([[t('المتوسط'), round($cycle['avg'], 1)], [t('الوسيط'), $cycle['med']], [t('أسرع / أبطأ'), $cycle['min'] . ' / ' . $cycle['max']]] as [$k, $v]): ?><div class="rounded-xl bg-ink-50 p-2"><div class="text-[11px] text-ink-500"><?= e($k) ?></div><div class="text-xl font-bold text-ink-900"><?= e((string)$v) ?></div><div class="text-[10px] text-ink-400"><?= e(t('يوم')) ?></div></div><?php endforeach; ?></div>
          <div class="space-y-1 text-xs"><?php foreach ($cycle['src'] as $c): ?><div class="flex items-center gap-2"><span class="flex-1 truncate"><?= e(crm_sources()[$c['src']] ?? $c['src']) ?></span><span class="text-ink-500"><?= e(t('%s صفقة', $c['n'])) ?></span><b><?= e(t('%s يوم', round($c['avg'], 1))) ?></b></div><?php endforeach; ?></div>
        <?php endif; ?></section>
    </div>

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
    <div class="card p-4 mb-5"><div class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z"/></svg> <?= e(t('مصادر العملاء')) ?></div>
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

    <div class="grid md:grid-cols-2 gap-4 mb-5">
      <div class="card p-4"><div class="font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg> <?= e(t('الإيراد المتوقع')) ?> <b class="text-brand-700 ms-1"><?= e(number_format($fc['total'])) ?></b></div>
        <p class="text-[11px] text-ink-500 mb-2"><?= e(t('قيمة كل عميل مفتوح × احتمال مرحلته. الاحتمالات بتتغير من العملاء ← أدوات ← سرعة الرد والتوزيع.')) ?></p>
        <?php foreach (['new', 'contacted', 'qualified', 'proposal'] as $k): $r = $fc['rows'][$k] ?? ['n' => 0, 'v' => 0, 'w' => 0, 'p' => crm_stage_probs()[$k]]; ?>
          <div class="flex items-center gap-2 text-sm py-1"><span class="w-24 text-ink-600"><?= e(crm_stages()[$k]) ?></span><span class="text-[11px] text-ink-400 w-24"><?= (int)$r['n'] ?> · <?= (int)$r['p'] ?>%</span><span class="flex-1 text-ink-500 text-xs"><?= e(number_format($r['v'])) ?></span><b class="text-ink-900"><?= e(number_format($r['w'])) ?></b></div>
        <?php endforeach; ?></div>
      <div class="card p-4"><div class="font-semibold text-ink-900 mb-2">✕ <?= e(t('ليه بنخسر العملاء')) ?></div>
        <?php if (!$lostRows): ?><p class="text-xs text-ink-400"><?= e(t('لسه مفيش أسباب متسجلة. كل ما عميل يتعلّم "مش مناسب" هيتسأل السبب.')) ?></p><?php endif; ?>
        <?php $lm = $lostRows ? max(array_column($lostRows, 'n')) : 1; $lr = crm_lost_reasons(); foreach ($lostRows as $r): ?>
          <div class="grid grid-cols-[130px_1fr_40px] items-center gap-2 text-sm py-1"><span class="truncate text-ink-700"><?= e($lr[$r['lost_reason']] ?? $r['lost_reason']) ?></span><div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div class="h-full bg-red-400 rounded-full" style="width:<?= round($r['n'] / $lm * 100) ?>%"></div></div><b class="text-end"><?= (int)$r['n'] ?></b></div>
        <?php endforeach; ?></div>
    </div>

    <?php if ($links): ?>
    <div class="card p-4 mb-5"><div class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg> <?= e(t('روابط المنشورات')) ?></div>
      <p class="text-[11px] text-ink-500 mb-3"><?= e(t('كل لينك متتبع حطيته في منشور: كام واحد ضغط عليه، وكام بقى عميل محتمل من الفورم أو الحجز.')) ?></p>
      <div class="overflow-x-auto"><table class="w-full text-sm min-w-[560px]"><thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="text-start py-1.5"><?= e(t('المنشور')) ?></th><th><?= e(t('ضغطات')) ?></th><th><?= e(t('عملاء')) ?></th><th><?= e(t('بقوا عملاء')) ?></th><th><?= e(t('تحويل')) ?></th></tr></thead><tbody>
      <?php foreach ($links as $lk): ?><tr class="border-b border-ink-50"><td class="py-2 max-w-[320px]"><div class="truncate text-ink-800" dir="auto"><?= e($lk['label'] ?: $lk['url']) ?></div><div class="text-[11px] text-ink-400 truncate" dir="ltr"><?= e($lk['url']) ?></div></td>
        <td class="text-center font-semibold"><?= (int)$lk['clicks'] ?></td><td class="text-center"><?= (int)$lk['leads'] ?></td><td class="text-center text-lime-700 font-semibold"><?= (int)$lk['won'] ?></td>
        <td class="text-center text-xs"><?= $lk['clicks'] ? round($lk['leads'] / $lk['clicks'] * 100) . '%' : '—' ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div>
    <?php endif; ?>

    <?php if ($coupons): ?>
    <div class="card p-4 mb-5"><div class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12V4h8l10 10-8 8zM7.5 8.5h.01"/></svg> <?= e(t('كوبونات المنشورات')) ?></div>
      <p class="text-[11px] text-ink-500 mb-3"><?= e(t('العميل اللي يكتب الكود في كومنت أو رسالة واتضاف Lead، بيتسجل عليه الكوبون.')) ?></p>
      <div class="overflow-x-auto"><table class="w-full text-sm min-w-[460px]"><thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="text-start py-1.5"><?= e(t('الكود')) ?></th><th class="text-start"><?= e(t('المنشور')) ?></th><th><?= e(t('عملاء')) ?></th><th><?= e(t('بقوا عملاء')) ?></th></tr></thead><tbody>
      <?php foreach ($coupons as $cp): ?><tr class="border-b border-ink-50"><td class="py-2 font-mono font-semibold" dir="ltr"><?= e($cp['code']) ?></td><td class="max-w-[280px] truncate text-ink-600" dir="auto"><?= e($cp['label']) ?></td><td class="text-center"><?= (int)$cp['leads'] ?></td><td class="text-center text-lime-700 font-semibold"><?= (int)$cp['won'] ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div>
    <?php endif; ?>

    <?php if ($canManage && ($camp['rows'] || $camp['err'])): ?>
    <section class="card p-4 mb-5" id="camps"><div class="font-semibold text-ink-900 mb-1 inline-flex items-center gap-1.5"><?= ui_icon('megaphone', 'w-4 h-4 text-brand-600') ?><?= e(t('الإعلانات ← العملاء والعائد')) ?></div>
      <p class="text-[11px] text-ink-500 mb-3"><?= e(t('الصرف من الإعلانات، والعملاء اللي سجّلوا من كل حملة وكام واحد فيهم اشترى. تكلفة العميل = الصرف ÷ العملاء. العائد = (المبيعات − الصرف) ÷ الصرف.')) ?></p>
      <?php if ($camp['err']): ?><p class="text-xs text-red-600"><?= e($camp['err']) ?></p><?php endif; ?>
      <div class="overflow-x-auto"><table class="w-full text-sm min-w-[720px]"><thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="text-start py-1.5"><?= e(t('الحملة')) ?></th><th><?= e(t('الصرف')) ?></th><th><?= e(t('عملاء')) ?></th><th><?= e(t('تكلفة العميل')) ?></th><th><?= e(t('بقوا عملاء')) ?></th><th><?= e(t('تكلفة البيعة')) ?></th><th><?= e(t('المبيعات')) ?></th><th><?= e(t('العائد')) ?></th></tr></thead><tbody>
      <?php foreach ($camp['rows'] as $c): ?><tr class="border-b border-ink-50"><td class="py-2 max-w-[260px]"><span class="inline-flex items-center gap-1.5 min-w-0"><?= platform_icon($c['pf'], 'w-4 h-4') ?><span class="truncate" dir="auto"><?= e($c['name']) ?></span></span></td>
        <td class="text-center tabular-nums"><?= e(number_format($c['spend'], 2)) ?> <span class="text-[10px] text-ink-400"><?= e($c['cur']) ?></span></td><td class="text-center"><?= $c['leads'] ?></td>
        <td class="text-center tabular-nums"><?= $c['cpl'] !== null ? e(number_format($c['cpl'], 2)) : '—' ?></td><td class="text-center text-lime-700 font-semibold"><?= $c['won'] ?></td>
        <td class="text-center tabular-nums"><?= $c['cpw'] !== null ? e(number_format($c['cpw'], 2)) : '—' ?></td><td class="text-center tabular-nums"><?= e(number_format($c['value'])) ?></td>
        <td class="text-center font-bold <?= $c['roi'] === null ? 'text-ink-400' : ($c['roi'] >= 0 ? 'text-lime-700' : 'text-red-600') ?>"><?= $c['roi'] === null ? '—' : (($c['roi'] > 0 ? '+' : '') . round($c['roi']) . '%') ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <p class="text-[11px] text-ink-400 mt-2"><?= e(t('العملاء بيتربطوا بالحملة لما يتسحبوا من فورمز الإعلانات (زرار "اسحب من الإعلانات" في أدوات العملاء). المبيعات من قيمة الصفقة للي بقوا عملاء.')) ?></p></section>
    <?php endif; ?>

    <?php if ($canManage && $camp['rows']): $bc = crm_bshift_cfg(); $blog = crm_bshift_log(); $canSpend = can('ads.spend'); ?>
    <section class="card p-4 mb-5" id="bshift">
      <div class="flex flex-wrap items-center gap-2 mb-1"><b class="text-ink-900 inline-flex items-center gap-1.5"><?= ui_icon('route', 'w-4 h-4 text-lime-600') ?><?= e(t('انقل الميزانية للحملات الكسبانة')) ?></b>
        <button type="button" popovertarget="bsh-cfg" class="ms-auto btn-ghost !py-1 !text-xs no-print"><?= ui_icon('sliders', 'w-4 h-4') ?><?= e(t('القواعد')) ?></button></div>
      <p class="text-[11px] text-ink-500 mb-3"><?= e(t('حسابات ثابتة من غير ذكاء اصطناعي: الحملة اللي صرفت ومجابتش عملاء أو عائدها سالب بتقل ميزانيتها %s%%، والفلوس دي بتروح للحملات اللي بتبيع بعائد عالي (على قد عائدها، ومن غير ما تزيد أكتر من %s%% في المرة). إجمالي الميزانية اليومية مابيزيدش. مفيش حاجة بتتنفذ غير لما توافق.', (int)$bc['step'], (int)$bc['step'])) ?></p>
      <form id="bsh-cfg" popover method="post" class="m-0 w-[min(360px,92vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl space-y-2 text-sm"><?= csrf_field() ?><input type="hidden" name="action" value="bshift_cfg">
        <?php foreach ([['step', t('نسبة النقل في المرة %'), 5, 50], ['min_spend', t('أقل صرف قبل ما نحكم على الحملة'), 0, 100000], ['win_roi', t('تعتبر كسبانة لو العائد أكتر من %'), 0, 10000], ['lose_roi', t('تعتبر خسرانة لو العائد أقل من %'), -100, 0], ['cap', t('سقف إجمالي الميزانية اليومية (0 = من غير سقف)'), 0, 10000000]] as [$k, $lbl, $lo, $hi]): ?>
          <label class="flex items-center justify-between gap-2"><span class="text-xs text-ink-600"><?= e($lbl) ?></span><input name="<?= $k ?>" value="<?= e((string)$bc[$k]) ?>" min="<?= $lo ?>" max="<?= $hi ?>" type="number" step="any" class="input !mt-0 !py-1 !w-24 text-center text-sm" dir="ltr"></label>
        <?php endforeach; ?><button class="btn-primary !py-1.5 !text-xs w-full"><?= e(t('حفظ')) ?></button></form>
      <?php if (!$bPlan): ?>
        <div class="rounded-xl border border-dashed border-ink-200 p-4 text-sm text-ink-500 text-center"><?= e(t('مفيش اقتراحات دلوقتي: يا إما كل الحملات ماشية كويس، يا إما لسه البيانات مش كفاية.')) ?></div>
      <?php else: ?>
      <form method="post" class="space-y-3" id="bsh-form"><?= csrf_field() ?><input type="hidden" name="action" value="bshift">
        <div class="overflow-x-auto"><table class="w-full text-sm min-w-[640px]" data-no-sort><thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="w-8"></th><th class="text-start py-1.5"><?= e(t('الحملة')) ?></th><th><?= e(t('الميزانية اليومية')) ?></th><th><?= e(t('السبب')) ?></th></tr></thead><tbody>
        <?php $dn = 0; $up = 0; foreach ($bPlan as $ch): $isUp = $ch['to'] > $ch['from']; if ($isUp) $up += $ch['to'] - $ch['from']; else $dn += $ch['from'] - $ch['to']; ?>
          <tr class="border-b border-ink-50"><td><input type="checkbox" name="pick[]" value="<?= e($ch['id']) ?>" checked class="rounded" <?= $canSpend ? '' : 'disabled' ?>></td>
            <td class="py-2 max-w-[240px]"><span class="inline-flex items-center gap-1.5 min-w-0"><?= platform_icon($ch['pf'], 'w-4 h-4') ?><span class="truncate" dir="auto"><?= e($ch['name']) ?></span></span></td>
            <td class="text-center whitespace-nowrap tabular-nums"><span class="text-ink-400 line-through"><?= e(number_format($ch['from'], 2)) ?></span> <span class="mx-1 text-ink-300"><?= is_rtl() ? '←' : '→' ?></span> <b class="<?= $isUp ? 'text-lime-700' : 'text-red-600' ?>"><?= e(number_format($ch['to'], 2)) ?></b> <span class="text-[10px] text-ink-400"><?= e($ch['cur']) ?></span>
              <span class="ms-1 rounded-full px-1.5 text-[10px] font-semibold <?= $isUp ? 'bg-lime-100 text-lime-800' : 'bg-red-100 text-red-700' ?>"><?= $isUp ? '+' : '−' ?><?= e(number_format(abs($ch['to'] - $ch['from']), 2)) ?></span></td>
            <td class="text-xs text-ink-600" dir="auto"><?= e($ch['why']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
        <p class="text-xs text-ink-500"><?= e(t('هنقلل %s وهنزوّد %s في اليوم. الفرق (%s) بيتوفر.', number_format($dn, 2), number_format($up, 2), number_format(max(0, $dn - $up), 2))) ?></p>
        <?php if ($canSpend): ?><div class="flex flex-wrap items-center gap-3 no-print"><?= ads_spend_confirm(t('موافق على تغيير الميزانية اليومية للحملات المحددة. ده بيأثر على الصرف الفعلي.')) ?><button class="btn-primary"><?= ui_icon('route', 'w-4 h-4') ?><?= e(t('نفّذ النقل')) ?></button></div>
        <?php else: ?><p class="text-xs text-amber-700"><?= e(t('التنفيذ محتاج صلاحية الصرف على الإعلانات (صاحب الشركة).')) ?></p><?php endif; ?>
      </form>
      <?php endif; ?>
      <?php if ($bNotes): ?><details class="mt-3 text-xs"><summary class="cursor-pointer text-ink-500"><?= e(t('حملات مش داخلة في الاقتراح (%s)', count($bNotes))) ?></summary><ul class="mt-2 space-y-1"><?php foreach ($bNotes as $nt): ?><li><b><?= e($nt['name']) ?>:</b> <span class="text-ink-500"><?= e($nt['why']) ?></span></li><?php endforeach; ?></ul></details><?php endif; ?>
      <?php if ($blog): ?><details class="mt-3 text-xs"><summary class="cursor-pointer text-ink-500"><?= e(t('سجل التغييرات')) ?></summary><div class="mt-2 space-y-2">
        <?php foreach (array_slice($blog, 0, 6) as $i => $lg): ?><div class="rounded-lg bg-ink-50 p-2"><div class="flex items-center gap-2"><b><?= e($lg['kind'] === 'undo' ? t('تراجع') : t('نقل')) ?></b><span class="text-ink-500"><?= e(fmt_date($lg['at'], 'd/m h:i A')) ?> · <?= e($lg['by']) ?></span>
          <?php if ($i === 0 && $lg['kind'] === 'apply' && $canSpend): ?><form method="post" class="ms-auto no-print" onsubmit="return confirm(this.dataset.q)" data-q="<?= e(t('ترجع ميزانية الحملات دي زي ما كانت؟')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="bshift_undo"><input type="hidden" name="confirm_spend" value="1"><button class="link"><?= e(t('تراجع')) ?></button></form><?php endif; ?></div>
          <?php foreach ($lg['items'] as $it): ?><div class="text-ink-600"><?= e($it['name']) ?>: <?= e(number_format($it['from'], 2)) ?> <?= is_rtl() ? '←' : '→' ?> <?= e(number_format($it['to'], 2)) ?></div><?php endforeach; ?></div><?php endforeach; ?>
      </div></details><?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($rows): ?>
    <section class="card p-4 mb-5" id="comm"><div class="flex items-center gap-2 mb-1"><b class="text-ink-900 inline-flex items-center gap-1.5"><?= ui_icon('wallet', 'w-4 h-4 text-lime-600') ?><?= e(t('العمولات')) ?></b><span class="text-xs text-ink-500"><?= e($periods[$period]) ?></span></div>
      <p class="text-[11px] text-ink-500 mb-3"><?= e(t('نسبة من قيمة المبيعات اللي اتقفلت. حط نسبة عامة، وتقدر تغيّرها لأي موظف.')) ?></p>
      <form method="post" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="comm">
        <div class="overflow-x-auto"><table class="w-full text-sm" data-no-sort><thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="text-start py-1.5"><?= e(t('الموظف')) ?></th><th><?= e(t('المبيعات')) ?></th><th><?= e(t('النسبة %')) ?></th><th><?= e(t('العمولة')) ?></th></tr></thead><tbody>
        <?php $ctot = 0; foreach ($rows as $r): $rate = isset($comm['users'][$r['id']]) ? (float)$comm['users'][$r['id']] : (float)$comm['def']; $cv = (float)$r['value'] * $rate / 100; $ctot += $cv; ?>
          <tr class="border-b border-ink-50"><td class="py-1.5"><?= e($r['name']) ?></td><td class="text-center tabular-nums"><?= e(number_format((float)$r['value'])) ?></td>
            <td class="text-center"><?php if ($canManage): ?><input name="rate[<?= (int)$r['id'] ?>]" value="<?= isset($comm['users'][$r['id']]) ? e((string)$comm['users'][$r['id']]) : '' ?>" placeholder="<?= e((string)$comm['def']) ?>" class="input !mt-0 !py-1 !w-20 text-center text-sm" inputmode="decimal" dir="ltr"><?php else: ?><?= e((string)$rate) ?>%<?php endif; ?></td>
            <td class="text-center font-semibold tabular-nums text-lime-700"><?= e(number_format($cv, 2)) ?></td></tr>
        <?php endforeach; ?>
          <tr><td class="py-2 font-semibold"><?= e(t('الإجمالي')) ?></td><td></td><td></td><td class="text-center font-bold tabular-nums"><?= e(number_format($ctot, 2)) ?></td></tr></tbody></table></div>
        <?php if ($canManage): ?><div class="flex flex-wrap items-center gap-2 text-sm no-print"><label class="flex items-center gap-2"><?= e(t('النسبة العامة')) ?> <input name="def" value="<?= e((string)$comm['def']) ?>" class="input !mt-0 !py-1 !w-20 text-center" inputmode="decimal" dir="ltr">%</label><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button></div><?php endif; ?>
      </form></section>
    <?php endif; ?>

    <?php if ($closing): ?>
    <div class="card p-4"><div class="font-semibold text-ink-900 mb-2">⏳ <?= e(t('صفقات متوقع تتقفل خلال أسبوعين')) ?></div>
      <div class="divide-y divide-ink-100"><?php foreach ($closing as $d): ?><a href="<?= e(url('crm_leads') . '#lead-' . (int)$d['id']) ?>" class="flex items-center gap-3 py-2 text-sm hover:bg-ink-50 px-1 rounded"><span class="flex-1"><b><?= e($d['title']) ?></b> · <?= e($d['name']) ?></span><span><?= e(number_format((float)$d['value'])) ?></span><span class="text-xs <?= strtotime($d['close_at']) < strtotime('today') ? 'text-red-600' : 'text-ink-500' ?>"><?= e(fmt_date($d['close_at'], 'd/m')) ?></span></a><?php endforeach; ?></div></div>
    <?php endif; ?>
    <?php
}, ['full' => true]);
