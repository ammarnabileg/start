<?php
/**
 * شاشة الإيجنت: تشغيل/إيقاف، الإعدادات بتتحفظ لوحدها، حالته دلوقتي، المحادثات الواقف فيها (مع استئناف)،
 * آخر الردود اللي عملها، وتجربة رد من قاعدة المعرفة من غير إرسال.
 */

/** سجل نشاط الإيجنت (آخر 60) */
function ai_agent_log(int $wid): array
{
    $l = json_decode((string)setting('ai_agent_log_' . $wid, ''), true);
    return is_array($l) ? $l : [];
}
function ai_agent_log_add(int $wid, array $row): void
{
    $l = ai_agent_log($wid); array_unshift($l, $row + ['t' => time()]);
    save_setting('ai_agent_log_' . $wid, json_encode(array_slice($l, 0, 60), JSON_UNESCAPED_UNICODE));
}
/** المحادثات/المنشورات اللي الإيجنت ساكت فيها عشان رد بشري */
function ai_agent_paused(int $wid): array
{
    $p = json_decode((string)setting('ai_paused_' . $wid, ''), true); $p = is_array($p) ? $p : [];
    return array_filter($p, function ($x) { return (int)($x['until'] ?? 0) > time(); });
}
function ai_agent_pause_set(int $wid, string $key, int $until, array $info): void
{
    $p = ai_agent_paused($wid); $p[$key] = $info + ['until' => $until];
    save_setting('ai_paused_' . $wid, json_encode(array_slice($p, -80, null, true), JSON_UNESCAPED_UNICODE));
}
/** استئناف: الإيجنت يتجاهل أي رد بشري قبل اللحظة دي على المحادثة دي */
function ai_agent_resumed(int $wid, string $key): int
{
    $r = json_decode((string)setting('ai_resume_' . $wid, ''), true) ?: [];
    return (int)($r[$key] ?? 0);
}

/** طلبات الشاشة (JSON) */
function ai_agent_ajax(string $a): void
{
    $wid = (int)ws('id'); $J = function ($d, int $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };
    if (!is_ws_owner() && $a !== 'agent_test' && $a !== 'agent_state') $J(['ok' => false, 'error' => t('هذا الإعداد متاح لمالك الشركة فقط.')], 403);
    if ($a === 'agent_save') {
        $c = ai_reply_cfg($wid); $in = $_POST;
        $c['mode'] = ($in['mode'] ?? $c['mode']) === 'send' ? 'send' : 'draft';
        $c['when'] = 'always';
        if (isset($in['comments'])) $c['comments'] = $in['comments'] === '1';
        if (isset($in['pause'])) $c['pause'] = max(5, min(240, (int)$in['pause']));
        if (isset($in['from']) && preg_match('/^\d\d:\d\d$/', $in['from'])) $c['from'] = $in['from'];
        if (isset($in['to']) && preg_match('/^\d\d:\d\d$/', $in['to'])) $c['to'] = $in['to'];
        if (isset($in['days'])) $c['days'] = array_values(array_intersect(array_map('intval', array_filter(explode(',', (string)$in['days']), 'strlen')), range(0, 6)));
        save_setting('ai_reply_cfg_' . $wid, json_encode(['when' => $c['when'], 'comments' => $c['comments'] ? 1 : 0, 'pause' => $c['pause'], 'mode' => $c['mode'], 'from' => $c['from'], 'to' => $c['to'], 'days' => $c['days']]));
        if (isset($in['on'])) save_setting('ai_auto_reply_' . $wid, $in['on'] === '1' ? '1' : '0');
        log_activity('ai.agent_cfg', json_encode($c));
        $J(['ok' => true] + ai_agent_state($wid));
    }
    if ($a === 'agent_resume') {
        $k = (string)($_POST['key'] ?? ''); $r = json_decode((string)setting('ai_resume_' . $wid, ''), true) ?: []; $r[$k] = time();
        save_setting('ai_resume_' . $wid, json_encode(array_slice($r, -200, null, true)));
        $p = ai_agent_paused($wid); unset($p[$k]); save_setting('ai_paused_' . $wid, json_encode($p, JSON_UNESCAPED_UNICODE));
        $h = json_decode((string)setting('ai_human_' . $wid, ''), true) ?: []; unset($h[$k]); save_setting('ai_human_' . $wid, json_encode($h));
        $J(['ok' => true] + ai_agent_state($wid));
    }
    if ($a === 'agent_run') {
        if (!ai_connection()) $J(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي أولًا.')]);
        try { $n = ai_auto_reply($wid, true); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
        $J(['ok' => true, 'n' => $n] + ai_agent_state($wid));
    }
    if ($a === 'agent_test') {
        $m = trim((string)($_POST['m'] ?? '')); if ($m === '') $J(['ok' => false, 'error' => t('اكتب رسالة للتجربة.')]);
        if (!ai_connection()) $J(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي أولًا.')]);
        if (ai_kb($wid) === '') $J(['ok' => false, 'error' => t('قاعدة المعرفة فارغة: لن يتمكن الإيجنت من الرد.')]);
        try { $d = kb_decide($wid, mb_substr($m, 0, 1500), ($_POST['ch'] ?? '') === 'comment' ? 'comment' : 'message'); }
        catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
        $J(['ok' => true, 'action' => $d['action'], 'reply' => trim((string)$d['reply'], " \n\"'«»"), 'reason' => (string)($d['reason'] ?? '')]);
    }
    if ($a === 'agent_state') $J(['ok' => true] + ai_agent_state($wid));
    $J(['ok' => false], 400);
}

function ai_agent_state(int $wid): array
{
    $c = ai_reply_cfg($wid); $log = ai_agent_log($wid); $today = strtotime('today');
    $tz = new DateTimeZone(site_timezone());
    $fmt = function ($t) use ($tz) { return (new DateTime('@' . (int)$t))->setTimezone($tz)->format('d/m H:i'); };
    $issues = [];
    if (!ai_connection()) $issues[] = t('لا يوجد مزود ذكاء اصطناعي مربوط.');
    if (ai_kb($wid) === '') $issues[] = t('قاعدة المعرفة فارغة.');
    if (!ai_feature_on('auto_reply', $wid)) $issues[] = t('ميزة الرد التلقائي متوقفة من تبويب الميزات.');
    if (!zernio_or_null()) $issues[] = t('لا توجد حسابات تواصل مربوطة.');
    $on = ai_auto_enabled($wid, 'reply');
    $active = $on && !$issues;
    return ['cfg' => $c, 'on' => $on, 'active' => $active, 'inHours' => ai_in_hours($wid), 'issues' => $issues,
        'last' => ($lr = (int)setting('ai_agent_last_' . $wid, '0')) ? $fmt($lr) : '',
        'today' => ['replies' => count(array_filter($log, function ($x) use ($today) { return $x['t'] >= $today && in_array($x['kind'], ['msg', 'comment'], true); })),
            'handoffs' => count(array_filter($log, function ($x) use ($today) { return $x['t'] >= $today && !empty($x['handoff']); })),
            'drafts' => count(ai_drafts($wid))],
        'paused' => array_map(function ($k, $x) use ($fmt) { return ['key' => $k, 'name' => (string)($x['name'] ?? ''), 'platform' => (string)($x['platform'] ?? ''), 'kind' => (string)($x['kind'] ?? ''), 'until' => $fmt($x['until']), 'mins' => max(1, (int)ceil(($x['until'] - time()) / 60))]; }, array_keys(ai_agent_paused($wid)), ai_agent_paused($wid)),
        'log' => array_map(function ($x) use ($fmt) { return $x + ['at' => $fmt($x['t'])]; }, array_slice($log, 0, 25))];
}

function ai_agent_render(): void
{
    $wid = (int)ws('id'); $owner = is_ws_owner(); $st = ai_agent_state($wid);
        ?>
    <div id="ag" class="ag">
      <section class="ag-hero card">
        <div class="ag-hd">
          <span class="ag-ic"><?= ui_icon('spark', 'w-6 h-6') ?></span>
          <div class="min-w-0 flex-1"><b class="ag-t"><?= e(t('إيجنت الرد من قاعدة المعرفة')) ?></b>
            <p class="ag-s" data-status></p></div>
          <button type="button" class="ag-sw" data-on role="switch" aria-label="<?= e(t('تشغيل الإيجنت')) ?>" <?= $owner ? '' : 'disabled' ?>><i></i></button>
        </div>
        <ul class="ag-issues" data-issues></ul>
        <div class="ag-kpis">
          <div><small><?= e(t('ردود اليوم')) ?></small><b data-k="replies">0</b></div>
          <div><small><?= e(t('تحويلات للفريق')) ?></small><b data-k="handoffs">0</b></div>
          <div><small><?= e(t('مسودات بانتظار المراجعة')) ?></small><b data-k="drafts">0</b></div>
          <div><small><?= e(t('متوقف فيها')) ?></small><b data-k="paused">0</b></div>
          <div><small><?= e(t('آخر تشغيل')) ?></small><b data-k="last">—</b></div>
        </div>
        <?php if ($owner): ?><div class="ag-run"><button type="button" class="btn-ghost !py-1.5 !text-xs" data-run><?= ui_icon('refresh', 'w-4 h-4') ?><?= e(t('تشغيل الآن')) ?></button><span class="text-[11px] text-ink-400"><?= e(t('يعمل تلقائيًا مع التشغيل الدوري، وهذا الزر يشغّله فورًا.')) ?></span></div><?php endif; ?>
      </section>

      <div class="ag-grid">
        <section class="card ag-card">
          <h3><?= ui_icon('sliders', 'w-4 h-4') ?><?= e(t('طريقة العمل')) ?><i class="ag-saved" data-saved><?= e(t('تم الحفظ ✓')) ?></i></h3>
          <div class="ag-seg" data-seg="mode"><button type="button" data-v="draft"><b><?= e(t('مسودة للمراجعة')) ?></b><small><?= e(t('يجهّز الرد وأنت ترسله')) ?></small></button><button type="button" data-v="send"><b><?= e(t('إرسال مباشر')) ?></b><small><?= e(t('يرد تلقائيًا دون مراجعة')) ?></small></button></div>
          <label class="ag-row"><span><b><?= e(t('الرد على التعليقات')) ?></b><small><?= e(t('رد علني قصير، مع الإرسال المباشر فقط، للتعليقات الأحدث من 3 أيام')) ?></small></span><button type="button" class="ag-sw sm" data-f="comments"><i></i></button></label>
          <div class="ag-row"><span><b><?= e(t('يتوقف بعد رد بشري')) ?></b><small><?= e(t('من آخر رد يدوي على نفس المحادثة أو المنشور')) ?></small></span>
            <div class="ag-pause"><?php foreach ([15, 30, 60, 120] as $m): ?><button type="button" data-pause="<?= $m ?>"><?= $m < 60 ? e(t('%s د', $m)) : e(t('%s س', $m / 60)) ?></button><?php endforeach; ?></div></div>
        </section>

        <section class="card ag-card">
          <h3><?= ui_icon('chat', 'w-4 h-4') ?><?= e(t('تجربة الإيجنت')) ?></h3>
          <p class="ag-hint"><?= e(t('اكتب رسالة كما سيكتبها العميل، وشاهد كيف سيرد الإيجنت من قاعدة المعرفة. لن يُرسل شيء.')) ?></p>
          <form class="ag-test" data-test data-no-ajax>
            <div class="ag-ch"><button type="button" data-ch="message" class="on"><?= e(t('رسالة')) ?></button><button type="button" data-ch="comment"><?= e(t('تعليق')) ?></button></div>
            <textarea name="m" rows="2" dir="auto" placeholder="<?= e(t('مثلًا: كم سعر الباقة؟ وأين توصلون؟')) ?>"></textarea>
            <div class="ag-sug"><?php foreach ([t('كم الأسعار؟'), t('ما مواعيد العمل؟'), t('أريد التحدث مع الإدارة'), t('وصل المنتج تالفًا')] as $x): ?><button type="button" data-sug><?= e($x) ?></button><?php endforeach; ?></div>
            <button class="btn-primary !py-1.5 !text-sm"><?= ui_icon('spark', 'w-4 h-4') ?><?= e(t('تجربة')) ?></button>
          </form>
          <div class="ag-out hidden" data-out></div>
          <a class="ag-kb" href="<?= e(url('knowledge')) ?>"><?= e(t('عدّل قاعدة المعرفة')) ?> ←</a>
        </section>
      </div>

      <section class="card ag-card">
        <h3><?= ui_icon('pause', 'w-4 h-4') ?><?= e(t('متوقف فيها لأن الفريق رد')) ?><span class="ag-n" data-pn></span></h3>
        <div class="ag-paused" data-paused></div>
      </section>

      <section class="card ag-card">
        <h3><?= ui_icon('clock', 'w-4 h-4') ?><?= e(t('آخر ما قام به')) ?></h3>
        <div class="ag-filter"><button type="button" class="on" data-lf=""><?= e(t('الكل')) ?></button><button type="button" data-lf="msg"><?= e(t('رسائل')) ?></button><button type="button" data-lf="comment"><?= e(t('تعليقات')) ?></button><button type="button" data-lf="handoff"><?= e(t('تحويلات')) ?></button><button type="button" data-lf="draft"><?= e(t('مسودات')) ?></button></div>
        <div class="ag-log" data-log></div>
      </section>
    </div>
    <script>
    (function () {
      var C = <?= json_encode(['csrf' => csrf_token(), 'url' => url('assistant', ['tab' => 'agent']), 'inbox' => url('inbox'), 'comments' => url('comments'), 'owner' => $owner, 'st' => $st,
        'T' => ['on' => t('يعمل 24 ساعة: يرد على الرسائل من قاعدة المعرفة.'), 'off' => t('متوقف: لا يرد على أحد.'), 'blocked' => t('مفعّل لكنه لن يعمل:'),
          'resume' => t('استئناف الآن'), 'left' => t('متبقٍ %s د'), 'noPaused' => t('لا توجد محادثات متوقف فيها حاليًا.'), 'noLog' => t('لم يقم بأي شيء بعد.'), 'reply' => t('رد'), 'handoff' => t('تحويل للفريق'), 'draft' => t('مسودة'),
          'msg' => t('رسالة'), 'comment' => t('تعليق'), 'testing' => t('جارٍ التفكير…'), 'willReply' => t('سيرد هكذا'), 'willHand' => t('سيحوّل للفريق'), 'why' => t('السبب'), 'err' => t('حدثت مشكلة.'), 'ran' => t('تم التشغيل: %s رد'),
          'sendWarn' => t('الإرسال المباشر سيرسل ردودًا للعملاء دون مراجعة. هل أنت متأكد؟'), 'running' => t('جارٍ التشغيل…'), 'open' => t('فتح')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      var root = document.getElementById('ag'), T = C.T, st = C.st, $ = function (s) { return root.querySelector(s); }, $$ = function (s) { return root.querySelectorAll(s); };
      function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
      function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }); }
      function toast(m, t) { if (window.fxToast) fxToast(m, t); }
      var lf = '';
      function paint() { var c = st.cfg;
        $('[data-on]').classList.toggle('on', st.on);
        var s = $('[data-status]'); s.className = 'ag-s ' + (st.active ? 'ok' : st.on ? 'warn' : '');
        s.textContent = !st.on ? T.off : st.issues.length ? T.blocked : T.on;
        $('[data-issues]').innerHTML = st.on ? st.issues.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') : '';
        $$('[data-seg]').forEach(function (g) { g.querySelectorAll('[data-v]').forEach(function (b) { b.classList.toggle('on', b.dataset.v === c[g.dataset.seg]); }); });
        $('[data-f=comments]').classList.toggle('on', !!c.comments); $('[data-f=comments]').disabled = c.mode !== 'send' || !C.owner;
        $$('[data-pause]').forEach(function (b) { b.classList.toggle('on', +b.dataset.pause === +c.pause); });
        ['replies', 'handoffs', 'drafts'].forEach(function (k) { $('[data-k=' + k + ']').textContent = st.today[k]; }); $('[data-k=paused]').textContent = st.paused.length; $('[data-k=last]').textContent = st.last || '—';
        $('[data-pn]').textContent = st.paused.length || '';
        $('[data-paused]').innerHTML = st.paused.length ? st.paused.map(function (p) { return '<div class="ag-pi"><span class="ag-pic">' + (p.kind === 'comment' ? '💬' : '✉️') + '</span><div class="min-w-0 flex-1"><b>' + esc(p.name || p.key) + '</b><small>' + esc([p.platform, T.left.replace('%s', p.mins)].filter(Boolean).join(' · ')) + '</small></div>' + (C.owner ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-resume="' + esc(p.key) + '">' + esc(T.resume) + '</button>' : '') + '</div>'; }).join('') : '<p class="ag-empty">' + esc(T.noPaused) + '</p>';
        var L = st.log.filter(function (x) { return !lf || (lf === 'handoff' ? x.handoff : lf === 'draft' ? x.draft : x.kind === lf); });
        $('[data-log]').innerHTML = L.length ? L.map(function (x) { var tag = x.handoff ? ['hand', T.handoff] : x.draft ? ['draft', T.draft] : ['ok', T.reply];
          return '<div class="ag-li"><div class="ag-lh"><span class="ag-tag ' + tag[0] + '">' + esc(tag[1]) + '</span><b>' + esc(x.who || '') + '</b><small>' + esc([x.kind === 'comment' ? T.comment : T.msg, x.platform, x.at].filter(Boolean).join(' · ')) + '</small>' + (x.link ? '<a href="' + esc(x.link) + '" class="ms-auto link text-xs">' + esc(T.open) + '</a>' : '') + '</div>' +
            '<p class="ag-in" dir="auto">' + esc(x.in || '') + '</p>' + (x.out ? '<p class="ag-o" dir="auto">' + esc(x.out) + '</p>' : '') + (x.reason ? '<small class="ag-why">' + esc(T.why) + ': ' + esc(x.reason) + '</small>' : '') + '</div>'; }).join('') : '<p class="ag-empty">' + esc(T.noLog) + '</p>'; }
      paint();
      var savedT; function save(d) { if (!C.owner) return; post(Object.assign({ action: 'agent_save' }, d)).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); st = j; paint(); var s = $('[data-saved]'); s.classList.add('show'); clearTimeout(savedT); savedT = setTimeout(function () { s.classList.remove('show'); }, 1400); }).catch(function () { toast(T.err, 'error'); }); }
      root.addEventListener('click', function (e) { var b;
        if ((b = e.target.closest('[data-on]'))) { save({ on: st.on ? '0' : '1' }); return; }
        if ((b = e.target.closest('[data-seg] [data-v]'))) { var g = b.closest('[data-seg]').dataset.seg; if (g === 'mode' && b.dataset.v === 'send' && st.cfg.mode !== 'send' && !confirm(T.sendWarn)) return; var d = {}; d[g] = b.dataset.v; save(d); return; }
        if ((b = e.target.closest('[data-f=comments]'))) { save({ comments: st.cfg.comments ? '0' : '1' }); return; }
        if ((b = e.target.closest('[data-pause]'))) { save({ pause: b.dataset.pause }); return; }
        if ((b = e.target.closest('[data-resume]'))) { b.disabled = true; post({ action: 'agent_resume', key: b.dataset.resume }).then(function (j) { if (j.ok) { st = j; paint(); } }); return; }
        if ((b = e.target.closest('[data-lf]'))) { lf = b.dataset.lf; $$('[data-lf]').forEach(function (x) { x.classList.toggle('on', x === b); }); paint(); return; }
        if ((b = e.target.closest('[data-run]'))) { b.disabled = true; var o = b.innerHTML; b.textContent = T.running; post({ action: 'agent_run' }).then(function (j) { b.disabled = false; b.innerHTML = o; if (!j.ok) return toast(j.error || T.err, 'error'); st = j; paint(); toast(T.ran.replace('%s', j.n)); }).catch(function () { b.disabled = false; b.innerHTML = o; toast(T.err, 'error'); }); return; }
        if ((b = e.target.closest('[data-ch]'))) { $$('[data-ch]').forEach(function (x) { x.classList.toggle('on', x === b); }); return; }
        if ((b = e.target.closest('[data-sug]'))) { var ta = $('[data-test] textarea'); ta.value = b.textContent; ta.focus(); return; }
      });
      $('[data-test]').addEventListener('submit', function (e) { e.preventDefault(); var f = this, m = f.m.value.trim(), out = $('[data-out]'), btn = f.querySelector('.btn-primary'); if (!m) return f.m.focus();
        btn.disabled = true; out.classList.remove('hidden'); out.className = 'ag-out'; out.innerHTML = '<span class="ag-dots">' + esc(T.testing) + '</span>';
        post({ action: 'agent_test', m: m, ch: (root.querySelector('[data-ch].on') || {}).dataset.ch || 'message' }).then(function (j) { btn.disabled = false;
          if (!j.ok) { out.className = 'ag-out err'; out.textContent = j.error || T.err; return; }
          out.className = 'ag-out ' + (j.action === 'handoff' ? 'hand' : 'ok'); out.innerHTML = '<b>' + esc(j.action === 'handoff' ? T.willHand : T.willReply) + '</b>' + (j.reply ? '<p dir="auto">' + esc(j.reply) + '</p>' : '') + (j.reason ? '<small>' + esc(T.why) + ': ' + esc(j.reason) + '</small>' : ''); })
          .catch(function () { btn.disabled = false; out.className = 'ag-out err'; out.textContent = T.err; }); });
      // تحديث الحالة كل دقيقة
      setInterval(function () { if (document.hidden) return; fetch(C.url + '&ajax=agent_state', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (j.ok) { st = j; paint(); } }).catch(function () {}); }, 60000);
    })();
    </script>
    <?php
}
