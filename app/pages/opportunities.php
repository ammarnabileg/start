<?php
/** تقويم الفرص: المناسبات والمواسم الجاية حسب دولة الشركة، ووقت التحضير لكل واحدة، وزرار يفتح حملة متكاملة جاهزة */
$wid = (int)ws('id');
$mgr = is_ws_owner() || can('settings.manage') || can('posts.create');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    if (!$mgr) { flash('error', t('مالكش صلاحية.')); redirect(url('opportunities')); }
    if ($a === 'add') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80); $d = (string)($_POST['date'] ?? '');
        if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) { flash('error', t('اكتب اسم المناسبة وتاريخها.')); redirect(url('opportunities')); }
        $c = opp_custom(); if (count($c) >= 40) { flash('error', t('الحد الأقصى 40 مناسبة.')); redirect(url('opportunities')); }
        $c[] = ['id' => substr(bin2hex(random_bytes(4)), 0, 8), 'name' => $name, 'date' => $d, 'yearly' => !empty($_POST['yearly']), 'lead' => max(1, min(60, (int)($_POST['lead'] ?? 10))),
            'offer' => mb_substr(trim((string)($_POST['offer'] ?? '')), 0, 120), 'note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300)];
        save_setting('opp_custom_' . $wid, json_encode(array_values($c), JSON_UNESCAPED_UNICODE)); log_activity('opp.add', $name);
        flash('success', t('اتضافت ✓')); redirect(url('opportunities'));
    }
    if ($a === 'del_custom') {
        $id = (string)($_POST['id'] ?? ''); $c = array_values(array_filter(opp_custom(), function ($x) use ($id) { return $x['id'] !== $id; }));
        save_setting('opp_custom_' . $wid, json_encode($c, JSON_UNESCAPED_UNICODE)); flash('success', t('اتشالت ✓')); redirect(url('opportunities'));
    }
    if ($a === 'hide' || $a === 'unhide') {
        $k = mb_substr((string)($_POST['k'] ?? ''), 0, 80); $h = opp_hidden();
        if ($a === 'hide') $h[$k] = 1; else unset($h[$k]);
        if ($a === 'unhide' && $k === '*') $h = [];
        save_setting('opp_hidden_' . $wid, json_encode($h)); flash('success', $a === 'hide' ? t('اتخفت ✓') : t('رجعت ✓')); redirect(url('opportunities'));
    }
    redirect(url('opportunities'));
}

$range = in_array((int)($_GET['range'] ?? 0), [60, 120, 365], true) ? (int)$_GET['range'] : 120;
$cc = ws_country();
$list = opp_list($range, $cc);
$camps = function_exists('mcp_list') ? mcp_list() : [];
$kinds = ['religious' => [t('دينية'), 'bg-lime-50 text-lime-800 border-lime-200'], 'national' => [t('وطنية'), 'bg-cyan-50 text-cyan-800 border-cyan-200'],
    'commercial' => [t('مواسم تسوق'), 'bg-brand-50 text-brand-700 border-brand-200'], 'payday' => [t('الرواتب'), 'bg-amber-50 text-amber-800 border-amber-200'], 'custom' => [t('مناسباتك'), 'bg-ink-100 text-ink-700 border-ink-200']];
$hiddenN = count(opp_hidden());
$canCamp = can('posts.create');

render(t('تقويم الفرص'), 'opportunities', function () use ($list, $camps, $kinds, $range, $cc, $hiddenN, $mgr, $canCamp) {
    $now = array_values(array_filter($list, function ($x) { return opp_state($x)['state'] !== 'later' && $x['kind'] !== 'payday'; }));
    $months = []; foreach ($list as $x) $months[substr($x['date'], 0, 7)][] = $x;
    $usedKinds = array_unique(array_column($list, 'kind'));
    $pill = function ($k) use ($kinds) { $x = $kinds[$k] ?? [$k, '']; return '<span class="text-[11px] rounded-full border px-2 py-0.5 ' . $x[1] . '">' . e($x[0]) . '</span>'; };
    $card = function (array $x, bool $big) use ($camps, $pill, $mgr, $canCamp) {
        $s = opp_state($x); $c = opp_campaign_for($x, $camps);
        $when = $s['state'] === 'live' ? t('شغالة دلوقتي') : ($s['days'] === 0 ? t('النهارده') : ($s['days'] === 1 ? t('بكرة') : t('بعد %s يوم', $s['days'])));
        ob_start(); ?>
        <div class="<?= $big ? 'card p-4' : 'rounded-xl border border-ink-200 bg-white p-3' ?> flex flex-col gap-2" data-opp data-kind="<?= e($x['kind']) ?>" id="o-<?= e($x['key'] . '-' . $x['date']) ?>">
          <div class="flex items-start gap-3">
            <div class="w-12 shrink-0 rounded-xl bg-ink-50 text-center py-1.5 leading-tight"><b class="block text-lg text-ink-900"><?= e(opp_fmt($x['date'], 'j')) ?></b><span class="text-[10px] text-ink-500"><?= e(opp_fmt($x['date'], 'M')) ?></span></div>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-1.5"><b class="text-ink-900"><?= e($x['name']) ?></b><?= $pill($x['kind']) ?></div>
              <div class="text-xs mt-0.5 <?= $s['state'] === 'later' ? 'text-ink-500' : 'text-brand-700 font-medium' ?>"><?= e($when) ?><?php if ($x['end'] !== $x['date']): ?> · <?= e(t('لحد %s', opp_fmt($x['end'], 'j M'))) ?><?php endif; ?><?php if ($x['approx']): ?> · <span class="text-ink-400 font-normal" title="<?= e(t('بتقويم أم القرى، وممكن يفرق يوم حسب رؤية الهلال في بلدك.')) ?>"><?= e(t('±يوم')) ?></span><?php endif; ?></div>
            </div>
            <?php if ($mgr): ?><form method="post" class="m-0" data-fx-reload><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="k" value="<?= e($x['key'] . '@' . $x['date']) ?>"><button class="p-1 rounded-lg text-ink-300 hover:text-ink-700 hover:bg-ink-100" title="<?= e(t('مش مناسبة لشغلي')) ?>"><?= ui_icon('x', 'w-4 h-4') ?></button></form><?php endif; ?>
          </div>
          <?php if ($big || $x['angle'] !== ''): ?><p class="text-sm text-ink-600 <?= $big ? '' : 'text-xs' ?>"><?= e($x['angle']) ?></p><?php endif; ?>
          <div class="flex flex-wrap items-center gap-2 mt-auto">
            <?php if ($c): ?>
              <a href="<?= e(url('campaign')) ?>#cmp-list" class="text-xs rounded-full bg-lime-50 text-lime-800 px-2 py-1 inline-flex items-center gap-1"><?= ui_icon('check', 'w-3.5 h-3.5') ?><?= e(t('عندك حملة: %s', $c['name'])) ?></a>
            <?php elseif ($x['kind'] !== 'payday' || $big): ?>
              <span class="text-xs text-ink-500"><?= e($s['state'] === 'later' ? t('ابدأ التحضير %s', opp_fmt($s['prep'], 'j M')) : t('لسه مفيش حملة')) ?></span>
              <?php if ($canCamp): ?><a href="<?= e(opp_campaign_url($x)) ?>" class="<?= $s['state'] === 'later' ? 'btn-ghost' : 'btn-primary' ?> !py-1 !text-xs ms-auto"><?= ui_icon('megaphone', 'w-4 h-4') ?><?= e(t('جهّز حملة')) ?></a><?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php return ob_get_clean();
    };
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-5">
      <p class="text-sm text-ink-500 flex-1 min-w-[260px]"><?= e(t('المواسم والمناسبات اللي الطلب بيزيد فيها في %s، ووقت التحضير لكل واحدة. لما فرصة تقرب ومفيش حملة ليها هيوصلك تنبيه.', cx_name($cc))) ?>
        <?php if (!ws_country_set() && is_ws_owner()): ?><a href="<?= e(url('settings')) ?>" class="link"><?= e(t('غيّر الدولة')) ?></a><?php endif; ?></p>
      <div class="flex rounded-xl border border-ink-200 overflow-hidden text-xs">
        <?php foreach ([60 => t('شهرين'), 120 => t('4 شهور'), 365 => t('سنة')] as $r => $l): ?><a href="<?= e(url('opportunities', $r === 120 ? [] : ['range' => $r])) ?>" class="px-3 py-1.5 <?= $range === $r ? 'bg-ink-900 text-[#fff]' : 'text-ink-600 hover:bg-ink-50' ?>"><?= e($l) ?></a><?php endforeach; ?>
      </div>
      <?php if ($mgr): ?><button type="button" popovertarget="opp-add" class="btn-primary"><?= ui_icon('plus', 'w-4 h-4') ?><?= e(t('مناسبة خاصة بيك')) ?></button><?php endif; ?>
    </div>

    <?php if ($mgr): ?>
    <form id="opp-add" popover method="post" data-fx-reload class="m-0 w-[min(420px,92vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl space-y-3 text-sm">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <b class="text-ink-900"><?= e(t('مناسبة خاصة بيك')) ?></b>
      <p class="text-xs text-ink-500"><?= e(t('عيد ميلاد الشركة، افتتاح فرع، موسم شغلك، معرض بتشارك فيه...')) ?></p>
      <label class="block"><span class="label"><?= e(t('اسم المناسبة')) ?></span><input name="name" required maxlength="80" class="input"></label>
      <div class="grid grid-cols-2 gap-2"><label class="block"><span class="label"><?= e(t('التاريخ')) ?></span><input type="date" name="date" required class="input"></label>
        <label class="block"><span class="label"><?= e(t('أيام التحضير')) ?></span><input type="number" name="lead" value="10" min="1" max="60" class="input"></label></div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="yearly" value="1" checked> <?= e(t('بتتكرر كل سنة')) ?></label>
      <label class="block"><span class="label"><?= e(t('العرض المقترح')) ?> <span class="text-ink-400 font-normal"><?= e(t('(اختياري)')) ?></span></span><input name="offer" maxlength="120" class="input"></label>
      <button class="btn-primary w-full"><?= e(t('أضف')) ?></button>
    </form>
    <?php endif; ?>

    <?php if ($now): ?>
    <section id="opp-now" class="mb-6">
      <h2 class="text-sm font-semibold text-ink-900 mb-2 inline-flex items-center gap-1.5"><?= ui_icon('fire', 'w-4 h-4 text-red-500') ?><?= e(t('وقت التحضير دلوقتي')) ?></h2>
      <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4"><?php foreach ($now as $x) echo $card($x, true); ?></div>
    </section>
    <?php endif; ?>

    <section id="opp-cal" class="card p-4">
      <div class="flex flex-wrap items-center gap-1.5 mb-4" data-of>
        <b class="text-ink-900 me-2"><?= e(t('كل الفرص')) ?></b>
        <button type="button" data-k="" class="text-[11px] rounded-full border px-2 py-0.5 bg-ink-900 text-[#fff] border-ink-900"><?= e(t('الكل')) ?></button>
        <?php foreach ($kinds as $k => $kv) if (in_array($k, $usedKinds, true)): ?><button type="button" data-k="<?= e($k) ?>" class="text-[11px] rounded-full border px-2 py-0.5 border-ink-200 text-ink-600"><?= e($kv[0]) ?></button><?php endif; ?>
        <?php if ($hiddenN && $mgr): ?><form method="post" class="m-0 ms-auto" data-fx-reload><?= csrf_field() ?><input type="hidden" name="action" value="unhide"><input type="hidden" name="k" value="*"><button class="text-xs link"><?= e(t('رجّع المخفي (%s)', $hiddenN)) ?></button></form><?php endif; ?>
      </div>
      <?php if (!$list): ?><p class="text-sm text-ink-500"><?= e(t('مفيش مناسبات في الفترة دي. جرّب فترة أطول أو ضيف مناسبة خاصة بيك.')) ?></p><?php endif; ?>
      <div class="space-y-5">
      <?php foreach ($months as $ym => $xs): ?>
        <div data-month>
          <h3 class="text-xs font-semibold text-ink-500 mb-2"><?= e(opp_fmt($ym . '-15', 'F Y')) ?></h3>
          <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3"><?php foreach ($xs as $x) echo $card($x, false); ?></div>
        </div>
      <?php endforeach; ?>
      </div>
    </section>

    <?php $custom = opp_custom(); if ($custom && $mgr): ?>
    <section id="opp-mine" class="card p-4 mt-5">
      <b class="text-ink-900 block mb-2"><?= e(t('مناسباتك')) ?></b>
      <div class="divide-y divide-ink-100">
      <?php foreach ($custom as $c): ?>
        <div class="flex items-center gap-3 py-2 text-sm"><span class="flex-1"><?= e($c['name']) ?> <span class="text-xs text-ink-500"><?= e(opp_fmt($c['date'], !empty($c['yearly']) ? 'j M' : 'j M Y')) ?><?= !empty($c['yearly']) ? ' · ' . e(t('كل سنة')) : '' ?></span></span>
          <form method="post" class="m-0" data-fx-reload><?= csrf_field() ?><input type="hidden" name="action" value="del_custom"><input type="hidden" name="id" value="<?= e($c['id']) ?>"><button class="p-1.5 rounded-lg text-ink-400 hover:text-red-600 hover:bg-red-50" title="<?= e(t('شيل')) ?>"><?= ui_icon('trash', 'w-4 h-4') ?></button></form></div>
      <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
    <script>
    (function () { var bar = document.querySelector('[data-of]'); if (!bar) return;
      bar.addEventListener('click', function (e) { var b = e.target.closest('[data-k]'); if (!b) return;
        bar.querySelectorAll('[data-k]').forEach(function (x) { var on = x === b; x.classList.toggle('bg-ink-900', on); x.classList.toggle('text-[#fff]', on); x.classList.toggle('border-ink-900', on); x.classList.toggle('text-ink-600', !on); x.classList.toggle('border-ink-200', !on); });
        document.querySelectorAll('#opp-cal [data-opp]').forEach(function (r) { r.style.display = !b.dataset.k || r.dataset.kind === b.dataset.k ? '' : 'none'; });
        document.querySelectorAll('#opp-cal [data-month]').forEach(function (m) { m.style.display = m.querySelector('[data-opp]:not([style*="none"])') ? '' : 'none'; }); }); })();
    </script>
    <?php
});
