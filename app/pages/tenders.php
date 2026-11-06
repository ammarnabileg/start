<?php
/** المنافسات الحكومية: اللي بيطابق مجال الشركة من اعتماد، ومتابعتها لحد ما تتحول لصفقة */
$wid = (int)ws('id');
$mgr = is_ws_owner() || can('crm.manage');
$J = function ($a, int $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    // كلمات البحث: بتيجي من معرفة الشركة، والمستخدم بيقفل أي كلمة أو يضيف كلماته
    if (in_array($a, ['kw_add', 'kw_off', 'kw_on', 'kw_del', 'cfg'], true)) {
        if (!$mgr) $J(['ok' => false, 'error' => t('مالكش صلاحية.')], 403);
        $c = tnd_cfg(); $w = mb_substr(trim((string)($_POST['w'] ?? '')), 0, 40); $k = tnd_norm($w);
        $drop = function (array $l) use ($k) { return array_values(array_filter($l, function ($x) use ($k) { return tnd_norm($x) !== $k; })); };
        if ($a === 'kw_add' && mb_strlen($k) >= 2) { $c['off'] = $drop($c['off']); if (!in_array($k, array_map('tnd_norm', $c['words']), true)) $c['add'][] = $w; }
        if ($a === 'kw_off' && $k !== '') $c['off'][] = $w;
        if ($a === 'kw_on') $c['off'] = $drop($c['off']);
        if ($a === 'kw_del') $c['add'] = $drop($c['add']);
        if ($a === 'cfg') { $c['not'] = tnd_words_clean($_POST['not'] ?? '', 40); $c['min_days'] = (int)($_POST['min_days'] ?? 2); }
        tnd_cfg_save($c); log_activity('tenders.' . $a, $w);
        $J(['ok' => true]);
    }
    if ($a === 'apify_pull' && is_ws_owner() && tnd_apify_on()) {
        try { if ((string)setting('tenders_apify_run', '') === '') { tnd_apify_start((string)setting('tenders_apify_full_done', '') === ''); save_setting('tenders_apify_day', gmdate('Y-m-d')); } $J(['ok' => true]); }
        catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 400); }
    }
    if ($a === 'apify_poll' && is_ws_owner() && tnd_apify_on()) {
        try { $c = tnd_apify_collect(); $J(['ok' => true, 'done' => $c !== null, 'res' => $c]); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 400); }
    }
    // حالة منافسة (حفظ، قدّمت، إخفاء، صفقة) — JSON
    $tid = (int)($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT * FROM tenders WHERE id = ?'); $st->execute([$tid]); $t = $st->fetch();
    if (!$t) $J(['ok' => false, 'error' => t('المنافسة دي مش موجودة.')], 404);
    if (in_array($a, ['fav', 'applied', 'hidden', 'clear'], true)) {
        tnd_mark($tid, $a === 'clear' ? '' : $a);
        log_activity('tenders.' . $a, (string)$t['ref']);
        $J(['ok' => true, 'status' => $a === 'clear' ? '' : $a]);
    }
    if ($a === 'deal') {
        if (!module_enabled('crm') || !can('crm.view')) $J(['ok' => false, 'error' => t('مالكش صلاحية على العملاء.')], 403);
        $agency = trim((string)$t['agency']) ?: t('جهة حكومية');
        $l = crm_lead_upsert(['source' => 'tender', 'ext_id' => 'tender:' . $t['src'] . ':' . $t['ext_id'], 'name' => $t['branch'] ?: $agency, 'company' => $agency, 'country' => 'SA']);
        if (!$l) $J(['ok' => false, 'error' => t('حصلت مشكلة.')], 500);
        if (empty($l['merged'])) {
            $due = $t['deadline'] ? date('Y-m-d H:i:s', max(time() + 3600, strtotime($t['deadline']) - 3 * 86400)) : null;
            db()->prepare("UPDATE crm_leads SET stage = 'qualified', note = ?, next_at = ?, next_note = ? WHERE id = ? AND workspace_id = ?")->execute([
                mb_substr(t('منافسة على اعتماد: %s', $t['name']) . "\n" . t('الرقم المرجعي: %s', $t['ref']) . "\n" . tnd_url($t), 0, 2000), $due,
                mb_substr(t('آخر موعد لتقديم العروض %s', $t['deadline'] ? fmt_date($t['deadline'], 'd/m h:i A') : '—'), 0, 255), (int)$l['id'], $wid]);
            if (function_exists('crm_act_add')) crm_act_add((int)$l['id'], 'note', '🏛️ ' . t('اتحولت من منافسة: %s', mb_substr($t['name'], 0, 200)));
        }
        tnd_mark($tid, 'applied', null, (int)$l['id']);
        log_activity('tenders.deal', (string)$t['ref']);
        $J(['ok' => true, 'status' => 'applied', 'lead' => (int)$l['id'], 'url' => url('crm_leads', ['lead' => (int)$l['id']])]);
    }
    $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
}

$view = in_array($_GET['v'] ?? '', ['fav', 'applied', 'all', 'hidden'], true) ? $_GET['v'] : 'match';
$cfg = tnd_cfg();
$h = tnd_health();
$rows = $view === 'match' ? tnd_list(false) : ($view === 'all' ? tnd_list(true, 300) : tnd_marked($view));
$counts = ['match' => $view === 'match' ? count($rows) : count(tnd_list(false)), 'fav' => count(tnd_marked('fav')), 'applied' => count(tnd_marked('applied'))];
render(t('المنافسات والمناقصات'), 'tenders', function () use ($view, $cfg, $h, $rows, $counts, $mgr) {
    $ago = function ($sec) { if ($sec === null) return t('لسه ماوصلش بيانات'); if ($sec < 3600) return t('من %s دقيقة', max(1, intdiv($sec, 60))); if ($sec < 86400) return t('من %s ساعة', intdiv($sec, 3600)); return t('من %s يوم', intdiv($sec, 86400)); };
    $mark = function (string $txt, array $hits) { $out = e($txt); foreach ($hits as $w) { $out = preg_replace('/(' . preg_quote(e($w), '/') . ')/u', '<mark class="bg-amber-100 text-ink-900 rounded px-0.5">$1</mark>', $out); } return $out; };
    ?>

    <?php if ($mgr): ?>
    <div id="tnd-cfg" popover class="m-0 w-[min(560px,94vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl space-y-3 text-sm">
      <div class="flex items-center gap-2"><b class="text-ink-900 flex-1"><?= e(t('كلمات البحث')) ?></b><a href="<?= e(url('knowledge')) ?>" class="text-xs link"><?= e(t('معرفة الشركة')) ?></a></div>
      <p class="text-xs text-ink-500"><?= e(t('الكلمات دي جاية من معرفة الشركة (المجال والفئة والخدمات). دوس على كلمة عشان توقفها، أو ضيف كلماتك.')) ?></p>
      <div class="flex flex-wrap gap-1.5" id="kw-list">
        <?php $offK = array_flip(array_map('tnd_norm', $cfg['off'])); foreach ($cfg['auto'] as $w): $isOff = isset($offK[tnd_norm($w)]); ?>
          <button type="button" data-kw="<?= e($w) ?>" data-a="<?= $isOff ? 'kw_on' : 'kw_off' ?>" class="kw <?= $isOff ? 'off' : '' ?>" title="<?= e($isOff ? t('رجّعها') : t('وقّفها')) ?>"><?= ui_icon('id', 'w-3 h-3 opacity-60') ?><?= e($w) ?></button>
        <?php endforeach; ?>
        <?php foreach ($cfg['add'] as $w): ?><button type="button" data-kw="<?= e($w) ?>" data-a="kw_del" class="kw mine" title="<?= e(t('شيلها')) ?>"><?= e($w) ?> ×</button><?php endforeach; ?>
      </div>
      <form id="kw-add" class="flex gap-2 m-0" data-no-ajax><input name="w" maxlength="40" class="input !mt-0 flex-1 text-sm" dir="auto" placeholder="<?= e(t('كلمة أو عبارة')) ?>"><button class="btn-ghost !text-xs"><?= e(t('أضف')) ?></button></form>
      <details class="text-xs"><summary class="cursor-pointer text-ink-600"><?= e(t('استبعاد وأيام')) ?></summary>
        <form id="kw-cfg" class="space-y-2 mt-2 m-0" data-no-ajax>
          <textarea name="not" rows="2" class="input text-sm" dir="auto" placeholder="<?= e(t('استبعد لو فيها (كلمة في كل سطر)')) ?>"><?= e(implode("\n", (array)$cfg['not'])) ?></textarea>
          <label class="flex items-center justify-between gap-2"><span class="text-ink-600"><?= e(t('اخفي اللي فاضل على آخر موعد فيها أقل من (يوم)')) ?></span><input type="number" name="min_days" value="<?= (int)$cfg['min_days'] ?>" min="0" max="30" class="input !mt-0 !w-20 text-center"></label>
          <button class="btn-ghost !text-xs w-full"><?= e(t('حفظ')) ?></button></form></details>
    </div>
    <style>.kw{display:inline-flex;align-items:center;gap:.25rem;font-size:.75rem;border-radius:9999px;padding:.25rem .65rem;border:1px solid rgb(252 211 77);background:rgb(255 251 235);color:rgb(146 64 14)}.kw.off{border-color:rgb(226 232 240);background:#fff;color:rgb(148 163 184);text-decoration:line-through}.kw.mine{border-color:rgb(147 197 253);background:rgb(239 246 255);color:rgb(30 64 175)}</style>
    <script>
    (function () { var C = <?= json_encode(['csrf' => csrf_token(), 'url' => url('tenders'), 'err' => t('حصلت مشكلة.')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) { if (window.fxToast) fxToast(j.error || C.err, 'error'); return; } location.reload(); }); }
      var l = document.getElementById('kw-list'); if (l) l.addEventListener('click', function (e) { var b = e.target.closest('[data-kw]'); if (b) post({ action: b.dataset.a, w: b.dataset.kw }); });
      var fa = document.getElementById('kw-add'); if (fa) fa.addEventListener('submit', function (e) { e.preventDefault(); var w = fa.w.value.trim(); if (w) post({ action: 'kw_add', w: w }); });
      var fc = document.getElementById('kw-cfg'); if (fc) fc.addEventListener('submit', function (e) { e.preventDefault(); post({ action: 'cfg', not: fc.not.value, min_days: fc.min_days.value }); });
    })();
    </script>
    <?php endif; ?>

    <?php if (!tnd_apify_on() && !$h['active']): ?>
      <div class="card p-4 mb-4 flex flex-wrap items-center gap-3 text-sm"><?= ui_icon('link', 'w-5 h-5 text-ink-400') ?><span class="flex-1 text-ink-600"><?= e(t('المنافسات بتيجي من اعتماد عن طريق تكامل "منافسات اعتماد (Apify)". لسه مش مربوط.')) ?></span>
        <?php if (can('integrations.manage')): ?><a href="<?= e(url('connectors')) ?>" class="btn-primary !py-1.5 !text-xs"><?= e(t('فتح التكاملات')) ?></a><?php else: ?><span class="text-xs text-ink-400"><?= e(t('اطلب من صاحب الشركة يربطه.')) ?></span><?php endif; ?></div>
    <?php endif; ?>
    <script>
    (function () { var go = function () { var b = document.getElementById('tnd-pull'); if (!b || b.dataset.bound) return; b.dataset.bound = '1'; var C = <?= json_encode(['csrf' => csrf_token(), 'url' => url('tenders'), 'busy' => t('بنسحب…'), 'done' => t('وصلت %s منافسة جديدة ✓'), 'err' => t('حصلت مشكلة.')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      function post(a) { var d = new FormData(); d.append('_csrf', C.csrf); d.append('action', a); return fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
      function poll() { post('apify_poll').then(function (j) { if (!j.ok) { b.disabled = false; if (window.fxToast) fxToast(j.error || C.err, 'error'); return; } if (j.done) { if (window.fxToast) fxToast(C.done.replace('%s', (j.res && j.res.new) || 0), 'success'); setTimeout(function () { location.reload(); }, 900); } else setTimeout(poll, 15000); }).catch(function () { setTimeout(poll, 20000); }); }
      function busy() { b.disabled = true; b.querySelector('span').textContent = C.busy; }
      if (b.dataset.running) { busy(); poll(); }
      b.addEventListener('click', function () { busy(); post('apify_pull').then(function (j) { if (!j.ok) { b.disabled = false; if (window.fxToast) fxToast(j.error || C.err, 'error'); return; } setTimeout(poll, 10000); }); });
    }; if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else setTimeout(go, 0); })();
    </script>

    <?php if (!$cfg['words'] && $view === 'match'): ?>
      <div class="card p-4 mb-4 flex flex-wrap items-center gap-3 text-sm"><?= ui_icon('id', 'w-5 h-5 text-ink-400') ?><span class="flex-1 text-ink-600"><?= e(t('معرفة الشركة مفيهاش مجال ولا خدمات لسه، فمش عارفين ندوّر على إيه. كمّل "عن الشركة" و"الخدمات".')) ?></span>
        <a href="<?= e(url('knowledge')) ?>" class="btn-primary !py-1.5 !text-xs"><?= e(t('معرفة الشركة')) ?></a></div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center gap-2 mb-4">
      <?php foreach (['match' => [t('تناسبك'), $counts['match']], 'fav' => [t('المحفوظة'), $counts['fav']], 'applied' => [t('قدّمت عليها'), $counts['applied']], 'all' => [t('كل النشطة'), null], 'hidden' => [t('المخفية'), null]] as $k => [$l, $n]): ?>
        <a href="<?= e(url('tenders', $k === 'match' ? [] : ['v' => $k])) ?>" class="text-sm rounded-full border px-3 py-1 <?= $view === $k ? 'bg-ink-900 text-[#fff] border-ink-900' : 'border-ink-200 text-ink-600 hover:bg-ink-50' ?>"><?= e($l) ?><?= $n !== null ? ' <span class="opacity-70">' . (int)$n . '</span>' : '' ?></a>
      <?php endforeach; ?>
      <span class="ms-auto"></span><input type="search" id="tnd-q" class="input !mt-0 !w-56 text-sm" placeholder="<?= e(t('دوّر في النتايج…')) ?>">
      <?php if ($mgr): ?><button type="button" popovertarget="tnd-cfg" class="btn-ghost"><?= ui_icon('sliders', 'w-4 h-4') ?><?= e(t('كلمات البحث')) ?><?= $cfg['words'] ? ' (' . count($cfg['words']) . ')' : '' ?></button><?php endif; ?>
      <?php if (is_ws_owner() && tnd_apify_on()): $arun = (string)setting('tenders_apify_run', '') !== ''; ?><button type="button" id="tnd-pull" class="btn-ghost" <?= $arun ? 'data-running="1"' : '' ?>><?= ui_icon('refresh', 'w-4 h-4') ?><span><?= e($arun ? t('بنسحب…') : t('اسحب الآن')) ?></span></button><?php endif; ?>
    </div>
    <?php if ($h['stale'] && $h['age'] !== null): ?><p class="text-xs text-amber-700 -mt-2 mb-4"><?= e(t('البيانات متأخرة: آخر تحديث %s.', $ago($h['age']))) ?></p><?php endif; ?>

    <?php if (!$rows && ($cfg['words'] || $view !== 'match')): ?>
      <div class="card p-6 text-center text-sm text-ink-500"><?= e($view === 'match' ? t('مفيش منافسات نشطة بتطابق كلماتك دلوقتي. جرّب كلمات أكتر، أو استنى الجديد.') : t('مفيش حاجة هنا.')) ?></div>
    <?php endif; ?>

    <div class="space-y-3" id="tnd-list">
    <?php foreach ($rows as $t): $left = $t['deadline'] ? (strtotime($t['deadline']) - time()) : null; $d = $left !== null ? (int)floor($left / 86400) : null; $isNew = strtotime($t['first_seen']) > time() - 86400; ?>
      <div class="card p-4 flex flex-col md:flex-row gap-4" data-tnd="<?= (int)$t['id'] ?>" data-q="<?= e(mb_strtolower($t['name'] . ' ' . $t['agency'] . ' ' . $t['branch'] . ' ' . $t['activity'] . ' ' . $t['ref'])) ?>">
        <div class="flex-1 min-w-0 space-y-1.5">
          <div class="flex flex-wrap items-center gap-1.5">
            <?php if ($isNew): ?><span class="text-[11px] rounded-full bg-brand-600 text-[#fff] px-2 py-0.5"><?= e(t('جديدة')) ?></span><?php endif; ?>
            <?php if ($t['type']): ?><span class="text-[11px] rounded-full bg-ink-100 text-ink-600 px-2 py-0.5"><?= e($t['type']) ?></span><?php endif; ?>
          </div>
          <a href="<?= e(tnd_url($t)) ?>" target="_blank" rel="noopener" class="block font-semibold text-ink-900 hover:text-brand-700 leading-snug" dir="auto"><?= $mark($t['name'], $t['hits']) ?></a>
          <div class="text-sm text-ink-600"><?= e($t['agency'] ?: '—') ?><?php if ($t['branch'] && $t['branch'] !== $t['agency']): ?> <span class="text-ink-400">·</span> <?= e($t['branch']) ?><?php endif; ?></div>
          <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500">
            <?php if ($t['activity']): ?><span><?= e(t('النشاط: %s', trim($t['activity']))) ?></span><?php endif; ?>
            <?php if ($t['ref']): ?><span><?= e(t('الرقم المرجعي: %s', $t['ref'])) ?></span><?php endif; ?>
            <?php if ($t['booklet'] !== null): ?><span><?= e(t('الكراسة: %s', (float)$t['booklet'] > 0 ? number_format((float)$t['booklet']) . ' ' . t('ريال') : t('مجانًا'))) ?></span><?php endif; ?>
            <?php if ($t['inquiry_at']): ?><span><?= e(t('آخر استفسار: %s', fmt_date($t['inquiry_at'], 'd/m'))) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="md:w-56 shrink-0 flex md:flex-col gap-2 md:items-stretch items-center justify-between">
          <div class="rounded-xl px-3 py-2 text-center <?= $d === null ? 'bg-ink-50 text-ink-500' : ($d < 3 ? 'bg-red-50 text-red-700' : ($d < 7 ? 'bg-amber-50 text-amber-800' : 'bg-ink-50 text-ink-700')) ?>">
            <b class="block text-lg leading-tight"><?= $d === null ? '—' : ($left < 0 ? e(t('انتهت')) : ($d === 0 ? e(t('النهارده')) : e(t('%s يوم', $d)))) ?></b>
            <span class="text-[11px]"><?= e(t('آخر موعد للعروض')) ?> <?= e($t['deadline'] ? fmt_date($t['deadline'], fmt_date($t['deadline'], 'H:i') === '00:00' ? 'd/m' : 'd/m h:i A') : '') ?></span>
          </div>
          <div class="flex flex-wrap gap-1.5 md:justify-end" data-acts data-mark="<?= e((string)$t['mark']) ?>">
            <button type="button" data-a="fav" class="tnd-b" title="<?= e(t('احفظ')) ?>"><?= ui_icon('star', 'w-4 h-4') ?></button>
            <button type="button" data-a="applied" class="tnd-b" title="<?= e(t('قدّمت عليها')) ?>"><?= ui_icon('check', 'w-4 h-4') ?></button>
            <?php if (module_enabled('crm') && can('crm.view')): ?><button type="button" data-a="deal" class="tnd-b" title="<?= e(t('حوّلها لصفقة في العملاء')) ?>"><?= ui_icon('user_plus', 'w-4 h-4') ?></button><?php endif; ?>
            <button type="button" data-a="hidden" class="tnd-b" title="<?= e(t('مش مناسبة')) ?>"><?= ui_icon('x', 'w-4 h-4') ?></button>
            <a href="<?= e(tnd_url($t)) ?>" target="_blank" rel="noopener" class="btn-primary !py-1 !text-xs" title="<?= e(t('افتحها في اعتماد')) ?>"><?= e(t('اعتماد')) ?> ↗</a>
          </div>
          <?php if (!empty($t['lead_id'])): ?><a href="<?= e(url('crm_leads', ['lead' => (int)$t['lead_id']])) ?>" class="text-xs link md:text-end"><?= e(t('الصفقة في العملاء')) ?></a><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <style>.tnd-b{display:inline-grid;place-items:center;width:2rem;height:2rem;border-radius:.6rem;border:1px solid rgb(var(--ink-200,226 232 240));color:rgb(100 116 139)}.tnd-b:hover{background:rgb(241 245 249)}.tnd-b.on{background:rgb(254 243 199);border-color:rgb(252 211 77);color:rgb(146 64 14)}</style>
    <script>
    (function () {
      var T = <?= json_encode(['csrf' => csrf_token(), 'url' => url('tenders'), 'err' => t('حصلت مشكلة.'), 'deal' => t('اتعملت صفقة في العملاء ✓'), 'saved' => t('اتحفظ ✓')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      var list = document.getElementById('tnd-list'); if (!list) return;
      function paint(box) { var m = box.dataset.mark; box.querySelectorAll('[data-a]').forEach(function (b) { b.classList.toggle('on', b.dataset.a === m || (b.dataset.a === 'deal' && false)); }); }
      list.querySelectorAll('[data-acts]').forEach(paint);
      list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-a]'); if (!b) return; var box = b.closest('[data-acts]'), card = b.closest('[data-tnd]');
        var a = b.dataset.a; if (a !== 'deal' && box.dataset.mark === a) a = 'clear';
        var d = new FormData(); d.append('_csrf', T.csrf); d.append('action', a); d.append('id', card.dataset.tnd); b.disabled = true;
        fetch(T.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          b.disabled = false; if (!j.ok) { if (window.fxToast) fxToast(j.error || T.err, 'error'); return; }
          box.dataset.mark = j.status; paint(box);
          if (j.status === 'hidden') { card.style.transition = 'opacity .2s'; card.style.opacity = '0'; setTimeout(function () { card.remove(); }, 200); }
          if (j.lead && window.fxToast) fxToast(T.deal, 'success'); else if (window.fxToast && j.status) fxToast(T.saved, 'success');
          if (j.url && !card.querySelector('a[href*="crm_leads"]')) { var l = document.createElement('a'); l.href = j.url; l.className = 'text-xs link md:text-end'; l.textContent = <?= json_encode(t('الصفقة في العملاء'), JSON_UNESCAPED_UNICODE) ?>; box.parentNode.appendChild(l); }
        }).catch(function () { b.disabled = false; if (window.fxToast) fxToast(T.err, 'error'); });
      });
      var q = document.getElementById('tnd-q'); if (q) q.addEventListener('input', function () { var v = q.value.trim().toLowerCase(); list.querySelectorAll('[data-tnd]').forEach(function (c) { c.style.display = !v || c.dataset.q.indexOf(v) >= 0 ? '' : 'none'; }); });
      document.querySelectorAll('[data-add-word]').forEach(function (b) { b.addEventListener('click', function () { var ta = document.getElementById('tnd-words'); ta.value = (ta.value.trim() ? ta.value.trim() + '\n' : '') + b.dataset.addWord; b.remove(); }); });
    })();
    </script>
    <?php
});
