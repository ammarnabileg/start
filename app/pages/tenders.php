<?php
/** المنافسات الحكومية: اللي بيطابق مجال الشركة من اعتماد، ومتابعتها لحد ما تتحول لصفقة */
$wid = (int)ws('id');
$mgr = is_ws_owner() || can('crm.manage');
$J = function ($a, int $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    // كلمات البحث: بتيجي من معرفة الشركة، والمستخدم بيقفل أي كلمة أو يضيف كلماته
    if ($a === 'kw_ai') { // كلمات بلغة اعتماد من الذكاء الاصطناعي
        if (!$mgr) $J(['ok' => false, 'error' => t('مالكش صلاحية.')], 403);
        try { $r = tnd_ai_words(true); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 502); }
        if (!$r || (!$r['activities'] && !$r['keywords'])) $J(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي من التكاملات، وكمّل معرفة الشركة.')], 422);
        $J(['ok' => true, 'n' => count($r['activities']) + count($r['keywords']), 'why' => $r['why']]);
    }
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
        $J(['ok' => true, 'status' => 'applied', 'lead' => (int)$l['id'], 'url' => url('prospects', ['lead' => (int)$l['id']])]);
    }
    $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
}

$cfg = tnd_cfg();
$h = tnd_health();
$rows = tnd_list(true, 3000);
$seen = array_flip(array_column($rows, 'id'));
foreach (['fav', 'applied'] as $m) foreach (tnd_marked($m) as $t) if (!isset($seen[$t['id']])) { $t['hits'] = []; $rows[] = $t; $seen[$t['id']] = 1; }
$counts = ['match' => 0, 'soon' => 0, 'fav' => 0, 'applied' => 0, 'all' => 0];
foreach ($rows as $t) { $hid = $t['mark'] === 'hidden'; $act = $t['deadline'] && strtotime($t['deadline']) > time();
    if ($t['hits'] && !$hid && $act) $counts['match']++; if ($t['hits'] && !$hid && $act && strtotime($t['deadline']) < time() + 3 * 86400) $counts['soon']++;
    if ($t['mark'] === 'fav') $counts['fav']++; if ($t['mark'] === 'applied') $counts['applied']++; if (!$hid && $act) $counts['all']++; }
render(t('آخر المنافسات'), 'tenders', function () use ($cfg, $h, $rows, $counts, $mgr) {
    $ago = function ($sec) { if ($sec === null) return t('لسه ماوصلش بيانات'); if ($sec < 3600) return t('من %s دقيقة', max(1, intdiv($sec, 60))); if ($sec < 86400) return t('من %s ساعة', intdiv($sec, 3600)); return t('من %s يوم', intdiv($sec, 86400)); };
    $mark = function (string $txt, array $hits) { $out = e($txt); foreach ($hits as $w) { $out = preg_replace('/(' . preg_quote(e($w), '/') . ')/u', '<mark class="bg-amber-100 text-ink-900 rounded px-0.5">$1</mark>', $out); } return $out; };
    ?>

    <?php if ($mgr): ?>
    <?php $ai = json_decode((string)setting('tnd_ai_' . (int)ws('id'), ''), true); $ai = is_array($ai) ? $ai : ['activities' => [], 'keywords' => [], 'why' => ''];
      $offK = array_flip(array_map('tnd_norm', $cfg['off'])); $actK = array_flip(array_map('tnd_norm', (array)$ai['activities']));
      $cnt = tnd_word_counts(array_values(array_unique(array_merge($cfg['auto'], $cfg['add']))));
      $grp = ['act' => [], 'kw' => [], 'mine' => []];
      foreach ($cfg['auto'] as $w) $grp[isset($actK[tnd_norm($w)]) ? 'act' : 'kw'][] = $w; $grp['mine'] = $cfg['add'];
      foreach ($grp as &$g) usort($g, function ($a, $b) use ($cnt, $offK) { return [isset($offK[tnd_norm($a)]), -($cnt[$a] ?? 0)] <=> [isset($offK[tnd_norm($b)]), -($cnt[$b] ?? 0)]; }); unset($g);
      $chip = function ($w, $kind) use ($cnt, $offK) { $off = $kind !== 'mine' && isset($offK[tnd_norm($w)]); $n = (int)($cnt[$w] ?? 0);
        return '<button type="button" data-kw="' . e($w) . '" data-a="' . ($kind === 'mine' ? 'kw_del' : ($off ? 'kw_on' : 'kw_off')) . '" class="kw ' . ($kind === 'mine' ? 'mine' : '') . ($off ? ' off' : '') . (!$off && !$n ? ' zero' : '') . '" title="'
          . e($kind === 'mine' ? t('شيلها') : ($off ? t('رجّعها') : t('وقّفها'))) . ($n ? '' : ' · ' . t('مفيش منافسات نشطة بتطابقها دلوقتي')) . '">' . e($w) . '<span class="kw-n">' . $n . '</span>' . ($kind === 'mine' ? ' ×' : '') . '</button>'; }; ?>
    <div id="tnd-cfg" popover class="m-0 kwp">
      <div class="kwp-h"><b><?= e(t('كلمات البحث')) ?></b><span class="kwp-n"><?= count($cfg['words']) ?></span>
        <?php if (function_exists('ai_connection') && ai_connection()): ?><button type="button" id="kw-ai" class="kwp-ai" title="<?= e(t('حدّث بلغة اعتماد')) ?>"><?= ui_icon('spark', 'w-3.5 h-3.5') ?><span><?= e(t('اقتراح')) ?></span></button><?php endif; ?>
        <button type="button" class="kwp-x" popovertarget="tnd-cfg" popovertargetaction="hide"><?= ui_icon('x', 'w-4 h-4') ?></button></div>
      <p class="kwp-note"><?= ui_icon('bulb', 'w-3.5 h-3.5') ?><?= e(t('يتم السحب على الكلمات المفعّلة فقط لتوفير الرصيد. الرقم = عدد المنافسات النشطة المطابقة.')) ?></p>
      <form id="kw-add" class="kwp-add" data-no-ajax><input name="w" maxlength="40" dir="auto" placeholder="<?= e(t('أضف كلمة… مثلًا: صيانة شبكات')) ?>"><button><?= ui_icon('plus', 'w-4 h-4') ?></button></form>
      <div class="kwp-tabs" role="tablist"><?php $first = true; foreach (['act' => t('نشاطات'), 'kw' => t('كلمات'), 'mine' => t('كلماتك')] as $k => $l): if (!$grp[$k] && $k !== 'mine') continue; ?><button type="button" data-kt="<?= $k ?>" class="<?= $first ? 'on' : '' ?>"><?= e($l) ?><i><?= count($grp[$k]) ?></i></button><?php $first = false; endforeach; ?></div>
      <?php $first = true; foreach (['act', 'kw', 'mine'] as $k): if (!$grp[$k] && $k !== 'mine') continue;
        $live = []; $zero = []; foreach ($grp[$k] as $w) { if ($k !== 'mine' && !(int)($cnt[$w] ?? 0) && !isset($offK[tnd_norm($w)])) $zero[] = $w; else $live[] = $w; } ?>
        <div class="kwp-pane<?= $first ? '' : ' hidden' ?>" data-kp="<?= $k ?>"><div class="kwp-list" data-kw-list><?php foreach ($live as $w) echo $chip($w, $k); ?><?php if (!$grp[$k]): ?><span class="kwp-empty"><?= e(t('لم تُضف كلمات بعد.')) ?></span><?php endif; ?></div>
          <?php if ($zero): ?><details class="kwp-zero"><summary><?= e(t('بلا نتائج حاليًا (%s)', count($zero))) ?></summary><div class="kwp-list" data-kw-list><?php foreach ($zero as $w) echo $chip($w, $k); ?></div></details><?php endif; ?></div>
      <?php $first = false; endforeach; ?>
      <details class="kwp-more"><summary><?= ui_icon('sliders', 'w-3.5 h-3.5') ?><?= e(t('استبعاد وأيام')) ?></summary>
        <form id="kw-cfg" class="kwp-cfg" data-no-ajax>
          <textarea name="not" rows="2" dir="auto" placeholder="<?= e(t('استبعد لو فيها (كلمة في كل سطر)')) ?>"><?= e(implode("\n", (array)$cfg['not'])) ?></textarea>
          <label><span><?= e(t('اخفي اللي فاضل على آخر موعد فيها أقل من (يوم)')) ?></span><input type="number" name="min_days" value="<?= (int)$cfg['min_days'] ?>" min="0" max="30"></label>
          <button class="btn-ghost !text-xs"><?= e(t('حفظ')) ?></button></form></details>
      <a href="<?= e(url('knowledge')) ?>" class="kwp-kb"><?= e(t('الكلمات التلقائية مأخوذة من معرفة الشركة')) ?> ←</a>
    </div>
    <style>
      .kwp{position:fixed;inset:auto;margin:0;width:min(460px,94vw);max-height:72vh;overflow-y:auto;border-radius:16px;border:1px solid #e2e8f0;background:#fff;padding:.75rem;box-shadow:0 24px 50px -16px rgb(15 23 42/.35);font-size:.8rem}
      .kwp-h{display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem}.kwp-h b{font-size:.92rem;color:#0f172a}
      .kwp-n{font-size:.68rem;font-weight:700;background:#eff6ff;color:#1d4ed8;border-radius:99px;padding:0 .45rem}
      .kwp-ai{margin-inline-start:auto;display:inline-flex;align-items:center;gap:.2rem;font-size:.72rem;font-weight:600;padding:.2rem .5rem;border-radius:99px;background:#f5f3ff;color:#6d28d9}
      .kwp-x{width:1.7rem;height:1.7rem;border-radius:8px;display:grid;place-items:center;color:#64748b}.kwp-x:hover{background:#f1f5f9}
      .kwp-note{display:flex;gap:.3rem;align-items:flex-start;font-size:.7rem;color:#64748b;line-height:1.5;margin-bottom:.5rem}
      .kwp-add{display:flex;gap:.3rem;margin:0 0 .5rem}.kwp-add input{flex:1;min-width:0;height:2rem;border:1px solid #e2e8f0;border-radius:10px;padding:0 .6rem;font-size:.8rem;outline:none}.kwp-add input:focus{border-color:#93c5fd}
      .kwp-add button{width:2rem;height:2rem;border-radius:10px;display:grid;place-items:center;background:#2563eb;color:#fff}
      .kwp-tabs{display:flex;gap:.2rem;padding:.2rem;background:#f1f5f9;border-radius:10px;margin-bottom:.5rem}
      .kwp-tabs button{flex:1;display:flex;align-items:center;justify-content:center;gap:.3rem;font-size:.74rem;font-weight:600;color:#64748b;padding:.3rem;border-radius:8px}
      .kwp-tabs button.on{background:#fff;color:#1d4ed8;box-shadow:0 1px 3px rgb(15 23 42/.08)}.kwp-tabs i{font-style:normal;font-size:.65rem;opacity:.7}
      .kwp-list{display:flex;flex-wrap:wrap;gap:.3rem}.kwp-empty{font-size:.72rem;color:#94a3b8}
      .kwp-zero,.kwp-more{margin-top:.5rem}.kwp-zero summary,.kwp-more summary{cursor:pointer;font-size:.72rem;color:#64748b;display:flex;align-items:center;gap:.25rem}
      .kwp-zero .kwp-list{margin-top:.4rem}
      .kwp-cfg{display:flex;flex-direction:column;gap:.4rem;margin-top:.4rem}.kwp-cfg textarea{border:1px solid #e2e8f0;border-radius:10px;padding:.4rem .6rem;font-size:.78rem}
      .kwp-cfg label{display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:.72rem;color:#475569}.kwp-cfg input{width:4rem;height:1.8rem;border:1px solid #e2e8f0;border-radius:8px;text-align:center}
      .kwp-kb{display:block;margin-top:.6rem;font-size:.7rem;color:#2563eb}
      .dark .kwp{background:#0f172a;border-color:rgb(255 255 255/.1)}.dark .kwp-h b{color:#f1f5f9}.dark .kwp-tabs{background:#1e293b}.dark .kwp-tabs button.on{background:#0f172a;color:#93c5fd}
      .dark .kwp-add input,.dark .kwp-cfg textarea,.dark .kwp-cfg input{background:#0b1220;border-color:rgb(255 255 255/.12);color:#e2e8f0}
    .kw{display:inline-flex;align-items:center;gap:.2rem;font-size:.7rem;border-radius:9999px;padding:.15rem .5rem;border:1px solid rgb(252 211 77);background:rgb(255 251 235);color:rgb(146 64 14)}.kw.off{border-color:rgb(226 232 240);border-style:dashed;background:#f8fafc;color:rgb(148 163 184)}.kw.off::before{content:'+';font-weight:700;margin-inline-end:.15rem}.kw:not(.off):not(.mine)::before{content:'✓';font-weight:700;margin-inline-end:.1rem;font-size:.65rem}.kw.mine{border-color:rgb(147 197 253);background:rgb(239 246 255);color:rgb(30 64 175)}.kw .kw-n{margin-inline-start:.35rem;border-radius:9999px;background:rgba(255,255,255,.8);padding:0 .35rem;font-size:10px;font-weight:700}.kw.zero{opacity:.55;border-style:dashed}</style>
    <script>
    (function () { var C = <?= json_encode(['csrf' => csrf_token(), 'url' => url('tenders'), 'err' => t('حصلت مشكلة.'), 'aiBusy' => t('بنختار الكلمات…'),
        'needAi' => $mgr && function_exists('ai_connection') && ai_connection() && tnd_activities(1) && ((!$ai['keywords'] && !$ai['activities']) || ($ai['sig'] ?? '') !== md5(tg_kb_brief()))], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) { if (window.fxToast) fxToast(j.error || C.err, 'error'); return; } location.reload(); }); }
      document.querySelectorAll('[data-kt]').forEach(function (t) { t.addEventListener('click', function () { document.querySelectorAll('[data-kt]').forEach(function (x) { x.classList.toggle('on', x === t); }); document.querySelectorAll('[data-kp]').forEach(function (p) { p.classList.toggle('hidden', p.dataset.kp !== t.dataset.kt); }); }); });
      function quiet(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
      document.querySelectorAll('[data-kw-list]').forEach(function (l) { l.addEventListener('click', function (e) { var b = e.target.closest('[data-kw]'); if (!b) return;
        if (b.dataset.a === 'kw_del') return post({ action: b.dataset.a, w: b.dataset.kw });
        var on = b.dataset.a === 'kw_on'; b.disabled = true; quiet({ action: b.dataset.a, w: b.dataset.kw }).then(function (j) { b.disabled = false; if (!j.ok) return window.fxToast && fxToast(j.error || C.err, 'error'); b.classList.toggle('off', !on); b.dataset.a = on ? 'kw_off' : 'kw_on'; keepOpen(); location.reload(); }); }); });
      // بعد أي تعديل: الصفحة تتحدث فورًا (القائمة والعدادات) واللوحة ترجع مفتوحة على نفس التبويب
      var pop = document.getElementById('tnd-cfg'), trig = [].filter.call(document.querySelectorAll('button[popovertarget="tnd-cfg"]:not(.kwp-x)'), function (x) { return x.offsetParent; })[0];
      document.addEventListener('click', function (e) { var t = e.target.closest('button[popovertarget="tnd-cfg"]:not(.kwp-x)'); if (t) trig = t; }, true);
      function keepOpen() { try { var on = document.querySelector('[data-kt].on'); sessionStorage.setItem('kwOpen', JSON.stringify({ t: on ? on.dataset.kt : '', y: window.scrollY })); } catch (e) {} }
      function place() { if (!pop || !trig) return; var r = trig.getBoundingClientRect(), w = pop.offsetWidth, rtl = document.documentElement.dir === 'rtl';
        var x = rtl ? r.left : r.right - w; x = Math.max(12, Math.min(x, innerWidth - w - 12)); pop.style.top = (r.bottom + 8) + 'px'; pop.style.left = x + 'px'; pop.style.maxHeight = (innerHeight - r.bottom - 24) + 'px'; }
      if (pop) { pop.addEventListener('toggle', function (e) { if (e.newState === 'open') place(); }); addEventListener('resize', function () { if (pop.matches(':popover-open')) place(); }); addEventListener('scroll', function () { if (pop.matches(':popover-open')) place(); }, { passive: true }); }
      try { var ko = JSON.parse(sessionStorage.getItem('kwOpen') || 'null'); if (ko && pop) { sessionStorage.removeItem('kwOpen'); window.scrollTo(0, ko.y || 0); pop.showPopover(); if (ko.t) { var tb = document.querySelector('[data-kt="' + ko.t + '"]'); if (tb) tb.click(); } } } catch (e) {}
      // الإضافة والحذف كمان يرجعوا اللوحة مفتوحة
      var _post = post; post = function (d) { keepOpen(); return _post(d); };
      var ai = document.getElementById('kw-ai'); if (ai) ai.addEventListener('click', function () { ai.disabled = true; ai.querySelector('span').textContent = C.aiBusy; post({ action: 'kw_ai' }); });
      if (C.needAi) { var f = new FormData(); f.append('_csrf', C.csrf); f.append('action', 'kw_ai'); fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (j.ok) location.reload(); }); }
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

    <?php if (!$cfg['words']): ?>
      <div class="card p-4 mb-4 flex flex-wrap items-center gap-3 text-sm"><?= ui_icon('id', 'w-5 h-5 text-ink-400') ?><span class="flex-1 text-ink-600"><?= e(t('معرفة الشركة مفيهاش مجال ولا خدمات لسه، فمش عارفين ندوّر على إيه. كمّل "عن الشركة" و"الخدمات".')) ?></span>
        <a href="<?= e(url('knowledge')) ?>" class="btn-primary !py-1.5 !text-xs"><?= e(t('معرفة الشركة')) ?></a></div>
    <?php endif; ?>

    <?php $cols = 'md:grid-cols-[22px_minmax(0,2.6fr)_minmax(0,1.3fr)_128px_92px_minmax(0,0.9fr)_150px]'; $L = ['fav' => t('محفوظة'), 'applied' => t('قدّمت'), 'hidden' => t('مخفية')]; ?>
    <div id="tnd" data-mk>
    <div class="mk-stats">
      <?php foreach ([['match', 'match=1', t('تناسبك'), $counts['match'], 'spark', 'bg-brand-50 text-brand-700'], ['soon', 'soon=1', t('تنتهي خلال 3 أيام'), $counts['soon'], 'timer', 'bg-red-50 text-red-700'],
          ['fav', 'mark=fav', t('المحفوظة'), $counts['fav'], 'star', 'bg-amber-50 text-amber-700'], ['applied', 'mark=applied', t('قدّمت عليها'), $counts['applied'], 'check', 'bg-lime-50 text-lime-700'],
          ['all', 'act=1', t('كل النشطة'), $counts['all'], 'file', 'bg-ink-100 text-ink-700']] as $i => [$k, $test, $l, $n, $ic, $cl]): ?>
        <button type="button" class="mk-stat<?= $i === 0 ? ' mk-on' : '' ?>" data-mk-stat="<?= $k ?>" data-mk-test="<?= e($test) ?>"><span class="mk-ic <?= $cl ?>"><?= ui_icon($ic, 'w-5 h-5') ?></span><span><b data-cnt="<?= $k ?>"><?= number_format($n) ?></b><small><?= e($l) ?></small></span></button>
      <?php endforeach; ?>
    </div>
    <div class="mk-tools">
      <label class="mk-search"><?= ui_icon('search') ?><input type="search" data-mk-q placeholder="<?= e(t('اسم المنافسة، الجهة، النشاط، الرقم المرجعي…')) ?>"><kbd>/</kbd></label>
      <span class="text-xs text-ink-500 tabular-nums"><span data-mk-shown></span></span>
      <?php if ($h['stale'] && $h['age'] !== null): ?><span class="text-xs text-amber-700"><?= e(t('البيانات متأخرة: آخر تحديث %s.', $ago($h['age']))) ?></span><?php endif; ?>
      <span class="ms-auto"></span>
      <?php if ($mgr): ?><button type="button" popovertarget="tnd-cfg" class="btn-ghost"><?= ui_icon('sliders', 'w-4 h-4') ?><?= e(t('كلمات البحث')) ?><?= $cfg['words'] ? ' <span class="rounded-full bg-brand-600 text-[#fff] text-[11px] px-1.5">' . count($cfg['words']) . '</span>' : '' ?></button><?php endif; ?>
      <?php if (is_ws_owner() && tnd_apify_on()): $arun = (string)setting('tenders_apify_run', '') !== ''; ?><button type="button" id="tnd-pull" class="btn-ghost" <?= $arun ? 'data-running="1"' : '' ?>><?= ui_icon('refresh', 'w-4 h-4') ?><span><?= e($arun ? t('بنسحب…') : t('اسحب الآن')) ?></span></button><?php endif; ?>
    </div>

    <div class="mk-table">
      <div class="mk-head <?= $cols ?>"><input type="checkbox" data-mk-all aria-label="<?= e(t('تحديد الكل')) ?>">
        <span data-mk-sort="name"><?= e(t('المنافسة')) ?></span><span data-mk-sort="act"><?= e(t('النشاط')) ?></span><span data-mk-sort="dl" data-dir="asc"><?= e(t('آخر موعد')) ?></span>
        <span data-mk-sort="bk"><?= e(t('الكراسة')) ?></span><span data-mk-sort="type"><?= e(t('النوع')) ?></span><span></span></div>
      <div data-mk-body>
      <?php foreach ($rows as $t): $left = $t['deadline'] ? (strtotime($t['deadline']) - time()) : null; $d = $left !== null ? (int)floor($left / 86400) : null; $act = $left !== null && $left > 0;
          $isNew = strtotime($t['first_seen']) > time() - 86400; $dlTxt = $t['deadline'] ? fmt_date($t['deadline'], fmt_date($t['deadline'], 'H:i') === '00:00' ? 'd/m' : 'd/m h:i A') : '';
          $bk = $t['booklet'] === null ? '' : ((float)$t['booklet'] > 0 ? number_format((float)$t['booklet']) . ' ' . t('ريال') : t('مجانًا'));
          $j = ['id' => (int)$t['id'], 'name' => $t['name'], 'agency' => (string)$t['agency'], 'branch' => (string)$t['branch'], 'activity' => trim((string)$t['activity']), 'type' => (string)$t['type'], 'ref' => (string)$t['ref'],
            'booklet' => $bk, 'deadline' => $dlTxt, 'inquiry' => $t['inquiry_at'] ? fmt_date($t['inquiry_at'], 'd/m/Y') : '', 'published' => $t['published_at'] ? fmt_date($t['published_at'], 'd/m/Y') : '',
            'url' => tnd_url($t), 'hits' => array_values($t['hits']), 'lead' => !empty($t['lead_id']) ? url('prospects', ['lead' => (int)$t['lead_id']]) : '', 'days' => $d]; ?>
        <div class="mk-row <?= $cols ?>" data-mk-row data-id="<?= (int)$t['id'] ?>" data-match="<?= $t['hits'] && $t['mark'] !== 'hidden' && $act ? 1 : 0 ?>" data-soon="<?= $t['hits'] && $t['mark'] !== 'hidden' && $act && $left < 3 * 86400 ? 1 : 0 ?>"
          data-act="<?= $act && $t['mark'] !== 'hidden' ? 1 : 0 ?>" data-mark="<?= e((string)$t['mark']) ?>" data-q="<?= e(mb_strtolower($t['name'] . ' ' . $t['agency'] . ' ' . $t['branch'] . ' ' . $t['activity'] . ' ' . $t['ref'])) ?>"
          data-s-name="<?= e(mb_strtolower($t['name'])) ?>" data-s-act="<?= e(mb_strtolower((string)$t['activity'])) ?>" data-s-dl="<?= e((string)$t['deadline']) ?>" data-s-bk="<?= (float)$t['booklet'] ?>" data-s-type="<?= e((string)$t['type']) ?>"
          data-j="<?= e(json_encode($j, JSON_UNESCAPED_UNICODE)) ?>">
          <input type="checkbox" data-mk-check aria-label="<?= e(t('تحديد')) ?>">
          <span class="min-w-0"><span class="flex items-center gap-1.5"><?php if ($isNew): ?><span class="mk-pill bg-brand-600 text-[#fff]"><?= e(t('جديدة')) ?></span><?php endif; ?><span class="mk-pill bg-amber-50 text-amber-800 <?= in_array($t['mark'], ['fav', 'applied'], true) ? '' : 'hidden' ?>" data-mark-pill><?= e($L[$t['mark']] ?? '') ?></span>
              <b class="text-ink-900 truncate" dir="auto"><?= $mark($t['name'], $t['hits']) ?></b></span>
            <span class="block text-xs text-ink-500 truncate"><?= e($t['agency'] ?: '—') ?><?= $t['branch'] && $t['branch'] !== $t['agency'] ? ' · ' . e($t['branch']) : '' ?></span></span>
          <span class="text-sm text-ink-600 truncate"><?= e(trim((string)$t['activity']) ?: '—') ?></span>
          <span class="min-w-0"><span class="mk-pill <?= $d === null ? 'bg-ink-100 text-ink-500' : (!$act ? 'bg-ink-100 text-ink-500' : ($d < 3 ? 'bg-red-50 text-red-700' : ($d < 7 ? 'bg-amber-50 text-amber-800' : 'bg-lime-50 text-lime-800'))) ?>"><?= $d === null ? '—' : (!$act ? e(t('انتهت')) : ($d === 0 ? e(t('النهارده')) : e(t('%s يوم', $d)))) ?></span>
            <span class="block text-[11px] text-ink-400 mt-0.5 tabular-nums"><?= e($dlTxt) ?></span></span>
          <span class="text-xs text-ink-600"><?= e($bk ?: '—') ?></span>
          <span class="text-xs text-ink-600 truncate"><?= e($t['type'] ?: '—') ?></span>
          <span class="mk-acts">
            <button type="button" class="mk-act<?= $t['mark'] === 'fav' ? ' on' : '' ?>" data-a="fav" title="<?= e(t('احفظ')) ?>"><?= ui_icon('star', 'w-4 h-4') ?></button>
            <button type="button" class="mk-act<?= $t['mark'] === 'applied' ? ' on' : '' ?>" data-a="applied" title="<?= e(t('قدّمت عليها')) ?>"><?= ui_icon('check', 'w-4 h-4') ?></button>
            <?php if (module_enabled('crm') && can('crm.view')): ?><button type="button" class="mk-act" data-a="deal" title="<?= e(t('حوّلها لصفقة في العملاء')) ?>"><?= ui_icon('user_plus', 'w-4 h-4') ?></button><?php endif; ?>
            <button type="button" class="mk-act" data-a="hidden" title="<?= e(t('مش مناسبة')) ?>"><?= ui_icon('x', 'w-4 h-4') ?></button>
            <a class="mk-act" href="<?= e(tnd_url($t)) ?>" target="_blank" rel="noopener" title="<?= e(t('افتحها في اعتماد')) ?>"><?= ui_icon('link', 'w-4 h-4') ?></a>
          </span>
        </div>
      <?php endforeach; ?>
      </div>
      <p data-mk-empty class="hidden p-8 text-center text-sm text-ink-500"><?= e($cfg['words'] ? t('مفيش منافسات هنا. جرّب كرت تاني فوق أو كلمات بحث أكتر.') : t('مفيش حاجة هنا.')) ?></p>
    </div>

    <div class="mk-bar" data-mk-bar>
      <span class="text-sm"><b data-mk-nsel>0</b> <?= e(t('محدد')) ?></span>
      <button type="button" data-bulk="fav"><?= ui_icon('star', 'w-4 h-4 inline') ?> <?= e(t('احفظ')) ?></button>
      <button type="button" data-bulk="applied"><?= ui_icon('check', 'w-4 h-4 inline') ?> <?= e(t('قدّمت عليها')) ?></button>
      <button type="button" data-bulk="hidden"><?= ui_icon('x', 'w-4 h-4 inline') ?> <?= e(t('مش مناسبة')) ?></button>
      <button type="button" data-mk-clear class="!px-2" title="<?= e(t('إلغاء التحديد')) ?>"><?= ui_icon('x', 'w-4 h-4') ?></button>
    </div>
    </div>

    <dialog id="tnd-dlg" class="mk-dlg">
      <div class="mk-dlg-head"><span class="mk-av !w-12 !h-12 !bg-lime-50 !text-lime-700"><?= ui_icon('file', 'w-6 h-6') ?></span><div class="flex-1 min-w-0"><b id="td-name" class="block text-lg text-ink-900 leading-snug" dir="auto"></b><span id="td-sub" class="text-sm text-ink-500"></span></div>
        <button type="button" data-close class="p-2 -m-1 rounded-xl text-ink-400 hover:text-ink-900 hover:bg-ink-100" aria-label="<?= e(t('إغلاق')) ?>"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div class="p-5 space-y-4">
        <div id="td-hits" class="flex flex-wrap gap-1.5"></div>
        <dl id="td-meta" class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm"></dl>
        <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-ink-100" id="td-acts">
          <button type="button" class="btn-ghost !py-1.5 !text-sm" data-a="fav"><?= ui_icon('star', 'w-4 h-4') ?><?= e(t('احفظ')) ?></button>
          <button type="button" class="btn-ghost !py-1.5 !text-sm" data-a="applied"><?= ui_icon('check', 'w-4 h-4') ?><?= e(t('قدّمت عليها')) ?></button>
          <?php if (module_enabled('crm') && can('crm.view')): ?><button type="button" class="btn-ghost !py-1.5 !text-sm" data-a="deal"><?= ui_icon('user_plus', 'w-4 h-4') ?><?= e(t('حوّلها لصفقة في العملاء')) ?></button><?php endif; ?>
          <a id="td-lead" href="#" class="link text-sm hidden"><?= e(t('الصفقة في العملاء')) ?></a>
          <span class="ms-auto"></span><a id="td-url" href="#" target="_blank" rel="noopener" class="btn-primary !py-1.5 !text-sm"><?= e(t('افتحها في اعتماد')) ?> ↗</a>
        </div>
      </div>
    </dialog>
    <script>window.TND = <?= json_encode(['csrf' => csrf_token(), 'url' => url('tenders'), 'marks' => $L,
        'L' => ['err' => t('حصلت مشكلة.'), 'deal' => t('اتعملت صفقة في العملاء ✓'), 'saved' => t('اتحفظ ✓'), 'done' => t('اتعمل على %s'), 'ref' => t('الرقم المرجعي'), 'act' => t('النشاط'), 'type' => t('النوع'),
        'bk' => t('الكراسة'), 'dl' => t('آخر موعد للعروض'), 'inq' => t('آخر استفسار'), 'pub' => t('تاريخ النشر'), 'matched' => t('طابقت كلماتك:')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/mk-list.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/tenders.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
