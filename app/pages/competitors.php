<?php
/** متابعة المنافسين: مواقعهم بتتقري كل يوم وأي تغيير في الأسعار أو الخدمات أو العروض بيتسجل */
$wid = (int)ws('id');
$back = function (string $h = '') { redirect(url('competitors') . ($h !== '' ? '#' . $h : '')); };
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'add') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $u = trim((string)($_POST['url'] ?? '')); if ($u !== '' && !preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
        if ($name === '' || !filter_var($u, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $u)) { flash('error', t('اكتب اسم المنافس ورابط موقعه.')); $back(); }
        if (count(comp_list()) >= 15) { flash('error', t('الحد الأقصى 15 منافس.')); $back(); }
        $links = implode("\n", array_slice(array_values(array_filter(array_map('trim', preg_split('/\s+/', (string)($_POST['links'] ?? ''))), function ($x) { return preg_match('~^https?://~i', $x); })), 0, 6));
        db()->prepare('INSERT INTO competitors (workspace_id, name, url, links, note) VALUES (?, ?, ?, ?, ?)')->execute([$wid, $name, mb_substr($u, 0, 500), $links ?: null, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500) ?: null]);
        $c = comp_get((int)db()->lastInsertId());
        comp_check($c); $c = comp_get((int)$c['id']);
        log_activity('competitor.add', $name);
        if ($c['last_ok']) flash('success', t('اتضاف %s واتقرا موقعه ✓', $name)); else flash('error', t('اتضاف %s، بس موقعه مش بيفتح دلوقتي. هنحاول تاني بكرة.', $name));
        $back('c' . (int)$c['id']);
    }
    $c = comp_get((int)($_POST['id'] ?? 0));
    if (!$c) { flash('error', t('المنافس ده مش موجود.')); $back(); }
    if ($a === 'check') {
        $n = comp_check($c); $c = comp_get((int)$c['id']);
        if (!$c['last_ok']) flash('error', t('موقع %s مش بيفتح دلوقتي.', $c['name']));
        else flash('success', $n ? t('اتسجل %s تغيير ✓', $n) : t('مفيش تغيير من آخر مرة.'));
        $back('c' . (int)$c['id']);
    }
    if ($a === 'del') {
        db()->prepare('DELETE FROM competitors WHERE id = ? AND workspace_id = ?')->execute([(int)$c['id'], $wid]);
        log_activity('competitor.del', (string)$c['name']); flash('success', t('اتشال ✓')); $back();
    }
    $back();
}

$comps = comp_list();
$events = comp_events(null, 60);
$prices = $comps ? comp_price_table($comps) : [];
$kinds = ['price_up' => [t('رفع سعر'), 'bg-red-50 text-red-700 border-red-200'], 'price_down' => [t('نزّل سعر'), 'bg-lime-50 text-lime-800 border-lime-200'], 'price' => [t('غيّر سعر'), 'bg-amber-50 text-amber-800 border-amber-200'],
    'offer_new' => [t('عرض جديد'), 'bg-brand-50 text-brand-700 border-brand-200'], 'offer_gone' => [t('عرض خلص'), 'bg-ink-50 text-ink-600 border-ink-200'], 'svc_new' => [t('خدمة جديدة'), 'bg-cyan-50 text-cyan-800 border-cyan-200'],
    'svc_gone' => [t('خدمة اتشالت'), 'bg-ink-50 text-ink-600 border-ink-200'], 'hours' => [t('المواعيد'), 'bg-ink-50 text-ink-600 border-ink-200'], 'down' => [t('الموقع وقع'), 'bg-red-50 text-red-700 border-red-200'],
    'up' => [t('الموقع رجع'), 'bg-lime-50 text-lime-800 border-lime-200'], 'first' => [t('أول قراءة'), 'bg-ink-50 text-ink-600 border-ink-200']];
$host = function ($u) { return preg_replace('/^www\./', '', (string)parse_url($u, PHP_URL_HOST)); };

render(t('المنافسين'), 'competitors', function () use ($comps, $events, $prices, $kinds, $host) {
    $pill = function ($k) use ($kinds) { $x = $kinds[$k] ?? [$k, 'bg-ink-50 text-ink-600 border-ink-200']; return '<span class="shrink-0 text-[11px] rounded-full border px-2 py-0.5 ' . $x[1] . '">' . e($x[0]) . '</span>'; };
    ?>
    <div class="flex flex-wrap items-center gap-3 mb-5">
      <p class="text-sm text-ink-500 flex-1 min-w-[260px]"><?= e(t('بنقرا موقع كل منافس مرة في اليوم ونقارنه باللي فات: الأسعار والخدمات والعروض والمواعيد. من غير ذكاء اصطناعي. صفحات السوشيال مش بتتقري لأن فيسبوك وإنستجرام مش بيسمحوا بده.')) ?></p>
      <button type="button" popovertarget="comp-add" class="btn-primary"><?= ui_icon('plus', 'w-4 h-4') ?><?= e(t('إضافة منافس')) ?></button>
    </div>
    <form id="comp-add" popover method="post" data-fx-reload class="m-0 w-[min(420px,92vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl space-y-3 text-sm">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <b class="text-ink-900"><?= e(t('إضافة منافس')) ?></b>
      <label class="block"><span class="label"><?= e(t('اسم المنافس')) ?></span><input name="name" required maxlength="120" class="input"></label>
      <label class="block"><span class="label"><?= e(t('رابط موقعه')) ?></span><input name="url" required class="input" dir="ltr" placeholder="https://"></label>
      <label class="block"><span class="label"><?= e(t('روابط السوشيال')) ?> <span class="text-ink-400 font-normal"><?= e(t('(اختياري، للوصول السريع بس)')) ?></span></span><textarea name="links" rows="2" class="input" dir="ltr"></textarea></label>
      <label class="block"><span class="label"><?= e(t('ملاحظة')) ?> <span class="text-ink-400 font-normal"><?= e(t('(اختياري)')) ?></span></span><input name="note" maxlength="500" class="input"></label>
      <button class="btn-primary w-full"><?= e(t('أضف واقرا الموقع')) ?></button>
      <p class="text-[11px] text-ink-400"><?= e(t('القراءة الأولى بتاخد لحد دقيقة.')) ?></p>
    </form>

    <?php if (!$comps): ?>
      <div class="card p-10 text-center max-w-xl mx-auto">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center mb-4"><?= ui_icon('eye', 'w-7 h-7') ?></div>
        <h2 class="text-lg font-semibold text-ink-900"><?= e(t('اعرف كل تغيير عند منافسينك')) ?></h2>
        <p class="text-sm text-ink-500 mt-1 mb-5"><?= e(t('ضيف موقع منافس، وكل يوم هتعرف لو غيّر أسعاره أو نزّل عرض أو ضاف خدمة، وتشوف أسعارك قدام أسعاره.')) ?></p>
        <button type="button" popovertarget="comp-add" class="btn-primary"><?= e(t('إضافة أول منافس')) ?></button>
      </div>
    <?php return; endif; ?>

    <section id="comp-cards" class="grid md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
      <?php foreach ($comps as $c): $snap = json_decode((string)$c['snap'], true) ?: []; $ev = array_slice(array_values(array_filter($events, function ($e) use ($c) { return (int)$e['competitor_id'] === (int)$c['id'] && $e['kind'] !== 'first'; })), 0, 3); ?>
        <div class="card p-4 flex flex-col gap-3" id="c<?= (int)$c['id'] ?>">
          <div class="flex items-start gap-3">
            <span class="w-10 h-10 rounded-xl bg-ink-100 text-ink-700 grid place-items-center font-bold"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
            <div class="flex-1 min-w-0">
              <b class="text-ink-900 block truncate"><?= e($c['name']) ?></b>
              <a href="<?= e($c['url']) ?>" target="_blank" rel="noopener" class="text-xs link" dir="ltr"><?= e($host($c['url'])) ?></a>
            </div>
            <?php if (!$c['last_check']): ?><span class="text-[11px] rounded-full bg-ink-100 text-ink-600 px-2 py-0.5"><?= e(t('لسه')) ?></span>
            <?php elseif ($c['last_ok']): ?><span class="text-[11px] rounded-full bg-lime-50 text-lime-800 px-2 py-0.5" title="<?= e(t('آخر قراءة')) ?>"><?= e(fmt_date($c['last_check'], 'd/m h:i A')) ?></span>
            <?php else: ?><span class="text-[11px] rounded-full bg-red-50 text-red-700 px-2 py-0.5"><?= e(t('الموقع مش بيفتح')) ?></span><?php endif; ?>
          </div>
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-ink-50 py-2"><b class="block text-lg text-ink-900"><?= count((array)($snap['services'] ?? [])) ?></b><span class="text-[11px] text-ink-500"><?= e(t('خدمة بسعر')) ?></span></div>
            <div class="rounded-xl bg-ink-50 py-2"><b class="block text-lg text-ink-900"><?= count((array)($snap['offers'] ?? [])) ?></b><span class="text-[11px] text-ink-500"><?= e(t('عرض')) ?></span></div>
            <div class="rounded-xl bg-ink-50 py-2"><b class="block text-lg text-ink-900"><?= (int)($snap['pages'] ?? 0) ?></b><span class="text-[11px] text-ink-500"><?= e(t('صفحة اتقرت')) ?></span></div>
          </div>
          <div class="space-y-1.5 text-sm flex-1">
            <?php if (!$ev): ?><p class="text-xs text-ink-400"><?= e(t('مفيش تغييرات لسه. أول تغيير هيظهر هنا.')) ?></p><?php endif; ?>
            <?php foreach ($ev as $e): ?>
              <div class="flex items-start gap-2"><?= $pill($e['kind']) ?><span class="min-w-0"><span class="text-ink-800"><?= e(comp_ev_title($e['kind'], $e['title'])) ?></span><?php if ($e['detail']): ?> <span class="text-xs text-ink-500" dir="auto"><?= e(comp_ev_detail($e['kind'], $e['detail'])) ?></span><?php endif; ?></span></div>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($snap["services"]) || !empty($snap["offers"])): ?>
          <details class="text-sm rounded-xl border border-ink-200">
            <summary class="cursor-pointer px-3 py-2 text-ink-600 select-none"><?= e(t('اللي على موقعه دلوقتي')) ?></summary>
            <div class="px-3 pb-3 space-y-2">
              <?php if (!empty($snap['services'])): ?><table class="w-full text-xs" data-no-search data-no-sort><?php foreach (array_slice((array)$snap['services'], 0, 25) as $s): ?><tr class="border-t border-ink-100"><td class="py-1"><?= e($s['name']) ?></td><td class="py-1 text-end font-medium whitespace-nowrap" dir="auto"><?= e($s['price']) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
              <?php foreach (array_slice((array)($snap['offers'] ?? []), 0, 6) as $o): ?><p class="text-xs rounded-lg bg-brand-50 text-brand-800 px-2 py-1" dir="auto"><?= e($o) ?></p><?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>
          <div class="flex flex-wrap items-center gap-2 border-t border-ink-100 pt-3">
            <form method="post" data-fx-reload class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="check"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn-ghost !py-1 !text-xs"><?= ui_icon('refresh', 'w-4 h-4') ?><?= e(t('اقرا دلوقتي')) ?></button></form>
            <?php foreach (array_filter(explode("\n", (string)$c['links'])) as $l): ?><a href="<?= e($l) ?>" target="_blank" rel="noopener" class="btn-ghost !py-1 !text-xs" title="<?= e($l) ?>"><?= ui_icon('link', 'w-4 h-4') ?><?= e($host($l)) ?></a><?php endforeach; ?>
            <form method="post" data-fx-reload class="m-0 ms-auto" onsubmit="return confirm(<?= e(json_encode(t('تشيل المنافس ده وكل تاريخه؟'), JSON_UNESCAPED_UNICODE)) ?>)"><?= csrf_field() ?><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="p-1.5 rounded-lg text-ink-400 hover:text-red-600 hover:bg-red-50" title="<?= e(t('شيل')) ?>"><?= ui_icon('trash', 'w-4 h-4') ?></button></form>
          </div>
          <?php if ($c['note']): ?><p class="text-xs text-ink-500 -mt-1"><?= e($c['note']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>

    <div class="grid xl:grid-cols-5 gap-5">
      <section id="comp-feed" class="card p-4 xl:col-span-2">
        <div class="flex items-center gap-2 mb-3"><b class="text-ink-900"><?= e(t('آخر التغييرات')) ?></b></div>
        <?php $feed = array_values(array_filter($events, function ($e) { return $e['kind'] !== 'first'; })); $used = array_unique(array_column($feed, 'kind')); ?>
        <?php if ($used): ?>
        <div class="flex flex-wrap gap-1.5 mb-3" data-cf>
          <button type="button" data-k="" class="text-[11px] rounded-full border px-2 py-0.5 bg-ink-900 text-[#fff] border-ink-900"><?= e(t('الكل')) ?></button>
          <?php foreach ($used as $k): ?><button type="button" data-k="<?= e($k) ?>" class="text-[11px] rounded-full border px-2 py-0.5 border-ink-200 text-ink-600"><?= e($kinds[$k][0] ?? $k) ?></button><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="space-y-2.5 max-h-[520px] overflow-y-auto" data-cf-list>
          <?php if (!$feed): ?><p class="text-sm text-ink-400"><?= e(t('لسه مفيش تغييرات. المواقع بتتقري كل يوم، وأول ما حاجة تتغير هتظهر هنا ويوصلك تنبيه.')) ?></p><?php endif; ?>
          <?php foreach ($feed as $e): ?>
            <div class="flex items-start gap-2 text-sm" data-kind="<?= e($e['kind']) ?>">
              <?= $pill($e['kind']) ?>
              <div class="min-w-0 flex-1"><b class="text-ink-900"><?= e($e['cname']) ?></b> <span class="text-ink-700"><?= e(comp_ev_title($e['kind'], $e['title'])) ?></span>
                <?php if ($e['detail']): ?><div class="text-xs text-ink-500" dir="auto"><?= e(comp_ev_detail($e['kind'], $e['detail'])) ?></div><?php endif; ?></div>
              <span class="text-[11px] text-ink-400 whitespace-nowrap"><?= e(fmt_date($e['created_at'], 'd/m')) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section id="comp-prices" class="card p-4 xl:col-span-3">
        <div class="flex items-center gap-2 mb-1"><b class="text-ink-900"><?= e(t('أسعارك قدام أسعارهم')) ?></b></div>
        <p class="text-xs text-ink-500 mb-3"><?= e(t('خدماتك من ملف الشركة، وقدامها الخدمة اللي بنفس الاسم أو اسم قريب عند كل منافس.')) ?></p>
        <?php if (!$prices): ?>
          <div class="rounded-xl border border-dashed border-ink-200 p-4 text-sm text-ink-500 text-center"><?= e(t('ضيف خدماتك وأسعارها في ملف الشركة عشان تظهر المقارنة.')) ?> <a href="<?= e(url('knowledge')) ?>" class="link"><?= e(t('ملف الشركة')) ?></a></div>
        <?php else: ?>
        <div class="overflow-x-auto"><table class="w-full text-sm" data-no-search>
          <thead><tr class="text-xs text-ink-500 text-start"><th class="py-2 text-start"><?= e(t('الخدمة')) ?></th><th class="py-2 text-start"><?= e(t('سعرك')) ?></th><?php foreach ($comps as $c): ?><th class="py-2 text-start"><?= e($c['name']) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($prices as $r): $mine = comp_price_num($r['mine']); ?>
            <tr class="border-t border-ink-100"><td class="py-2 font-medium text-ink-900"><?= e($r['name']) ?></td><td class="py-2 whitespace-nowrap" dir="auto"><?= e($r['mine'] ?: '—') ?></td>
              <?php foreach ($comps as $c): $h = $r['them'][(int)$c['id']] ?? null; ?>
                <td class="py-2 whitespace-nowrap">
                  <?php if (!$h): ?><span class="text-ink-300">—</span>
                  <?php else: $th = comp_price_num($h['price']); $d = ($mine && $th !== null) ? round(($th - $mine) / $mine * 100) : null; ?>
                    <span dir="auto"><?= e($h['price']) ?></span>
                    <?php if ($d !== null && $d !== 0.0): ?><span class="text-[11px] rounded-full px-1.5 <?= $d < 0 ? 'bg-red-50 text-red-700' : 'bg-lime-50 text-lime-800' ?>" title="<?= e($d < 0 ? t('أرخص منك') : t('أغلى منك')) ?>"><?= ($d > 0 ? '+' : '') . e((string)$d) ?>%</span><?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <p class="text-[11px] text-ink-400 mt-2"><?= e(t('الأحمر: المنافس أرخص منك. الأخضر: أغلى منك.')) ?></p>
        <?php endif; ?>
      </section>
    </div>
    <script>
    (function () { var bar = document.querySelector('[data-cf]'); if (!bar) return; var list = document.querySelector('[data-cf-list]');
      bar.addEventListener('click', function (e) { var b = e.target.closest('[data-k]'); if (!b) return;
        bar.querySelectorAll('[data-k]').forEach(function (x) { var on = x === b; x.classList.toggle('bg-ink-900', on); x.classList.toggle('text-[#fff]', on); x.classList.toggle('border-ink-900', on); x.classList.toggle('text-ink-600', !on); x.classList.toggle('border-ink-200', !on); });
        list.querySelectorAll('[data-kind]').forEach(function (r) { r.style.display = !b.dataset.k || r.dataset.kind === b.dataset.k ? '' : 'none'; }); }); })();
    </script>
    <?php
});
