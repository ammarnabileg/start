<?php
/** المنافسات الحكومية: اللي بيطابق مجال الشركة من اعتماد، ومتابعتها لحد ما تتحول لصفقة */
$wid = (int)ws('id');
$mgr = is_ws_owner() || can('crm.manage');
$J = function ($a, int $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'cfg') {
        if (!$mgr) { flash('error', t('مالكش صلاحية.')); redirect(url('tenders')); }
        $words = tnd_words_clean($_POST['words'] ?? '');
        foreach ((array)($_POST['preset'] ?? []) as $k) foreach (tnd_presets()[$k][1] ?? [] as $w) $words[] = $w;
        $words = array_values(array_unique($words));
        $c = ['words' => array_slice($words, 0, 80), 'not' => tnd_words_clean($_POST['not'] ?? '', 40), 'min_days' => max(0, min(30, (int)($_POST['min_days'] ?? 2)))];
        save_setting('tnd_cfg_' . $wid, json_encode($c, JSON_UNESCAPED_UNICODE)); log_activity('tenders.cfg', count($c['words']) . ' words');
        flash('success', t('اتحفظ ✓')); redirect(url('tenders'));
    }
    if ($a === 'apify_key' && is_admin()) {
        $k = trim((string)($_POST['key'] ?? ''));
        if ($k !== '' && !preg_match('/^apify_api_[A-Za-z0-9]{20,}$/', $k)) { flash('error', t('ده مش شكل مفتاح Apify (بيبدأ بـ apify_api_).')); redirect(url('tenders')); }
        $old = (string)setting('tenders_apify_token', ''); if ($k !== '') save_setting('tenders_apify_token', $k);
        if (isset(tnd_apify_actors()[$_POST['actor'] ?? ''])) save_setting('tenders_apify_actor', (string)$_POST['actor']);
        try { tnd_apify_req('GET', '/v2/users/me'); flash('success', t('المفتاح شغال ✓ دوس "اسحب دلوقتي".')); }
        catch (Throwable $e) { if ($k !== '') save_setting('tenders_apify_token', $old); flash('error', $e->getMessage() . ' ' . t('المفتاح ماتحفظش.')); }
        redirect(url('tenders'));
    }
    if ($a === 'apify_pull' && is_admin()) {
        try { if ((string)setting('tenders_apify_run', '') === '') { tnd_apify_start((string)setting('tenders_apify_full_done', '') === ''); save_setting('tenders_apify_day', gmdate('Y-m-d')); } $J(['ok' => true]); }
        catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 400); }
    }
    if ($a === 'apify_poll' && is_admin()) {
        try { $c = tnd_apify_collect(); $J(['ok' => true, 'done' => $c !== null, 'res' => $c]); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 400); }
    }
    if ($a === 'token' && is_admin()) {
        save_setting('tenders_ingest_token', bin2hex(random_bytes(24))); flash('success', t('اتعمل توكن جديد. حدّثه في السحّاب.')); redirect(url('tenders') . '#tnd-admin');
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
// اقتراحات من ملف الشركة (أسماء الخدمات والمجال)
$kb = function_exists('kb_get') ? kb_get($wid) : []; $sugg = [];
foreach ((array)($kb['services']['rows'] ?? []) as $r) { $n = trim((string)($r['name'] ?? '')); if ($n !== '' && mb_strlen($n) <= 30) $sugg[] = $n; }
if (!empty($kb['company']['industry'])) $sugg[] = (string)$kb['company']['industry'];
$sugg = array_slice(array_values(array_unique($sugg)), 0, 12);

render(t('المنافسات الحكومية'), 'tenders', function () use ($view, $cfg, $h, $rows, $counts, $mgr, $sugg) {
    $ago = function ($sec) { if ($sec === null) return t('لسه ماوصلش بيانات'); if ($sec < 3600) return t('من %s دقيقة', max(1, intdiv($sec, 60))); if ($sec < 86400) return t('من %s ساعة', intdiv($sec, 3600)); return t('من %s يوم', intdiv($sec, 86400)); };
    $mark = function (string $txt, array $hits) { $out = e($txt); foreach ($hits as $w) { $out = preg_replace('/(' . preg_quote(e($w), '/') . ')/u', '<mark class="bg-amber-100 text-ink-900 rounded px-0.5">$1</mark>', $out); } return $out; };
    ?>
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <div class="flex items-center gap-2 text-sm flex-1 min-w-[260px]">
        <span class="w-9 h-9 rounded-xl bg-lime-50 text-lime-700 grid place-items-center"><?= ui_icon('file', 'w-5 h-5') ?></span>
        <div><b class="text-ink-900"><?= e(t('منافسات اعتماد')) ?></b>
          <div class="text-xs <?= $h['stale'] ? 'text-amber-700' : 'text-ink-500' ?>"><?= e(t('%s منافسة نشطة', number_format($h['active']))) ?> · <?= e(t('آخر تحديث %s', $ago($h['age']))) ?><?= $h['stale'] && $h['age'] !== null ? ' · ' . e(t('البيانات متأخرة')) : '' ?></div></div>
      </div>
      <?php if ($mgr): ?><button type="button" popovertarget="tnd-cfg" class="btn-ghost"><?= ui_icon('sliders', 'w-4 h-4') ?><?= e(t('كلمات مجالك')) ?><?= $cfg['words'] ? ' (' . count($cfg['words']) . ')' : '' ?></button><?php endif; ?>
      <?php if (is_admin()): ?><button type="button" popovertarget="tnd-admin" class="btn-ghost"><?= ui_icon('key', 'w-4 h-4') ?><?= e(t('مصدر البيانات')) ?></button><?php endif; ?>
    </div>

    <?php if ($mgr): ?>
    <form id="tnd-cfg" popover method="post" class="m-0 w-[min(560px,94vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl space-y-3 text-sm">
      <?= csrf_field() ?><input type="hidden" name="action" value="cfg">
      <b class="text-ink-900"><?= e(t('كلمات مجالك')) ?></b>
      <p class="text-xs text-ink-500"><?= e(t('المنافسة بتظهر لك لو اسمها أو نشاطها فيه كلمة من دول. اكتب كلمة أو عبارة في كل سطر. الحروف بتتوحد لوحدها (أ إ آ، ة ه، ال).')) ?></p>
      <div class="flex flex-wrap gap-1.5"><?php foreach (tnd_presets() as $k => $p): ?><label class="text-xs rounded-full border border-ink-200 px-2.5 py-1 cursor-pointer has-[:checked]:bg-brand-50 has-[:checked]:border-brand-300"><input type="checkbox" name="preset[]" value="<?= e($k) ?>" class="hidden"> + <?= e($p[0]) ?></label><?php endforeach; ?></div>
      <?php if ($sugg): ?><div class="text-xs text-ink-500"><?= e(t('من ملف شركتك:')) ?> <?php foreach ($sugg as $s): ?><button type="button" class="rounded-full bg-ink-100 px-2 py-0.5 me-1 mb-1 hover:bg-ink-200" data-add-word="<?= e($s) ?>">+ <?= e($s) ?></button><?php endforeach; ?></div><?php endif; ?>
      <label class="block"><span class="label"><?= e(t('الكلمات')) ?></span><textarea name="words" rows="6" class="input" dir="auto" id="tnd-words"><?= e(implode("\n", (array)$cfg['words'])) ?></textarea></label>
      <label class="block"><span class="label"><?= e(t('استبعد لو فيها')) ?> <span class="text-ink-400 font-normal"><?= e(t('(اختياري)')) ?></span></span><textarea name="not" rows="2" class="input" dir="auto" placeholder="<?= e(t('مثلًا: صيانة، توريد، نظافة')) ?>"><?= e(implode("\n", (array)$cfg['not'])) ?></textarea></label>
      <label class="flex items-center justify-between gap-2"><span class="text-xs text-ink-600"><?= e(t('اخفي اللي فاضل على آخر موعد فيها أقل من (يوم)')) ?></span><input type="number" name="min_days" value="<?= (int)$cfg['min_days'] ?>" min="0" max="30" class="input !mt-0 !w-20 text-center"></label>
      <button class="btn-primary w-full"><?= e(t('حفظ')) ?></button>
    </form>
    <?php endif; ?>

    <?php if (is_admin()): $tok = (string)setting('tenders_ingest_token', ''); $st = $h['stats']; ?>
    <div id="tnd-admin" popover class="m-0 w-[min(560px,94vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl space-y-3 text-sm">
      <b class="text-ink-900"><?= e(t('مصدر البيانات (لمدير المنصة بس)')) ?></b>
      <?php $ak = (string)setting('tenders_apify_token', ''); $arun = json_decode((string)setting('tenders_apify_run', ''), true); ?>
      <div class="rounded-xl border border-ink-200 p-3 space-y-2">
        <b class="text-sm text-ink-900"><?= e(t('Apify (الأسهل، من غير سيرفر)')) ?></b>
        <p class="text-xs text-ink-500"><?= e(t('خدمة على Apify بتسحب اعتماد عندهم، والمنصة بتاخد منها الجديد مرة في اليوم.')) ?></p>
        <form method="post" class="m-0 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="apify_key">
          <?php foreach (tnd_apify_actors() as $ac => [$an, $ad]): ?><label class="flex items-start gap-2 text-xs cursor-pointer"><input type="radio" name="actor" value="<?= e($ac) ?>" <?= tnd_apify_actor() === $ac ? 'checked' : '' ?> class="mt-0.5"><span><b class="text-ink-800"><?= e($an) ?></b> <span class="text-ink-500"><?= e($ad) ?></span></span></label><?php endforeach; ?>
          <div class="flex gap-2"><input name="key" class="input !mt-0 font-mono text-xs flex-1" dir="ltr" autocomplete="off" placeholder="<?= e($ak !== '' ? '•••• ' . substr($ak, -4) : 'apify_api_…') ?>"><button class="btn-ghost !text-xs"><?= e($ak !== '' ? t('حفظ / اختبر') : t('حفظ')) ?></button></div>
          <p class="text-[11px] text-ink-400"><?= e(t('المفتاح من apify.com ← Settings ← API & Integrations. الحساب المجاني فيه 5 دولار رصيد كل شهر.')) ?></p></form>
        <?php if ($ak !== ''): ?><button type="button" id="tnd-pull" class="btn-primary !text-xs w-full" <?= $arun ? 'data-running="1"' : '' ?>><?= ui_icon('download', 'w-4 h-4') ?><span><?= e($arun ? t('بنسحب… (ممكن ياخد لحد 15 دقيقة)') : t('اسحب دلوقتي')) ?></span></button><?php endif; ?>
      </div>
      <details class="text-xs"><summary class="cursor-pointer text-ink-600"><?= e(t('أو سحّاب على سيرفرك')) ?></summary><div class="space-y-3 mt-2">
      <p class="text-xs text-ink-500"><?= e(t('اعتماد محمي ضد البوتات، فالبيانات بيجيبها سحّاب منفصل بيشتغل بمتصفح حقيقي على سيرفر (مجلد collector/etimad)، وبيبعتها هنا. شغّله كل ساعتين.')) ?></p>
      <label class="block"><span class="label"><?= e(t('رابط الاستقبال')) ?></span><input readonly class="input font-mono text-xs" dir="ltr" value="<?= e(base_url() . '/index.php?p=tenders_ingest') ?>" onclick="this.select()"></label>
      <label class="block"><span class="label"><?= e(t('التوكن')) ?></span><input readonly class="input font-mono text-xs" dir="ltr" value="<?= e($tok !== '' ? $tok : t('لسه مفيش')) ?>" onclick="this.select()"></label>
      <?php if ($st): ?><p class="text-xs text-ink-500" dir="auto"><?= e(t('آخر دفعة: %s جديدة، %s اتحدثت', (int)($st['new'] ?? 0), (int)($st['updated'] ?? 0))) ?> · <?= e(fmt_date($st['at'] ?? null, 'd/m h:i A')) ?></p><?php endif; ?>
      <?php if (!empty($st['note'])): ?><p class="text-xs rounded-lg bg-amber-50 text-amber-800 px-2 py-1" dir="auto"><?= e(t('آخر رسالة من السحّاب: %s', $st['note'])) ?></p><?php endif; ?>
      <form method="post" class="m-0" onsubmit="return confirm(<?= e(json_encode(t('التوكن القديم هيبطل. متأكد؟'), JSON_UNESCAPED_UNICODE)) ?>)"><?= csrf_field() ?><input type="hidden" name="action" value="token"><button class="btn-ghost !text-xs"><?= ui_icon('refresh', 'w-4 h-4') ?><?= e($tok !== '' ? t('توكن جديد') : t('اعمل توكن')) ?></button></form>
      </div></details>
    </div>
    <script>
    (function () { var b = document.getElementById('tnd-pull'); if (!b) return; var C = <?= json_encode(['csrf' => csrf_token(), 'url' => url('tenders'), 'busy' => t('بنسحب… (ممكن ياخد لحد 15 دقيقة)'), 'done' => t('وصلت %s منافسة جديدة ✓'), 'err' => t('حصلت مشكلة.')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      function post(a) { var d = new FormData(); d.append('_csrf', C.csrf); d.append('action', a); return fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
      function poll() { post('apify_poll').then(function (j) { if (!j.ok) { b.disabled = false; if (window.fxToast) fxToast(j.error || C.err, 'error'); return; } if (j.done) { if (window.fxToast) fxToast(C.done.replace('%s', (j.res && j.res.new) || 0), 'success'); setTimeout(function () { location.reload(); }, 900); } else setTimeout(poll, 15000); }).catch(function () { setTimeout(poll, 20000); }); }
      function busy() { b.disabled = true; b.querySelector('span').textContent = C.busy; }
      if (b.dataset.running) { busy(); poll(); }
      b.addEventListener('click', function () { busy(); post('apify_pull').then(function (j) { if (!j.ok) { b.disabled = false; if (window.fxToast) fxToast(j.error || C.err, 'error'); return; } setTimeout(poll, 10000); }); });
    })();
    </script>
    <?php endif; ?>

    <?php if (!$cfg['words'] && $view === 'match'): ?>
      <div class="card p-8 text-center max-w-xl mx-auto mb-5">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-lime-50 text-lime-700 grid place-items-center mb-4"><?= ui_icon('file', 'w-7 h-7') ?></div>
        <h2 class="text-lg font-semibold text-ink-900"><?= e(t('قولنا شغلك إيه ونجيبلك منافساته')) ?></h2>
        <p class="text-sm text-ink-500 mt-1 mb-5"><?= e(t('اختار مجالك أو اكتب كلماتك، وكل منافسة جديدة على اعتماد بتطابقها هتظهر هنا ويوصلك تنبيه.')) ?></p>
        <?php if ($mgr): ?><button type="button" popovertarget="tnd-cfg" class="btn-primary"><?= e(t('حدد مجالك')) ?></button><?php else: ?><p class="text-xs text-ink-400"><?= e(t('اطلب من مدير الشركة يحدد كلمات المجال.')) ?></p><?php endif; ?>
        <p class="mt-4"><a href="<?= e(url('tenders', ['v' => 'all'])) ?>" class="link text-sm"><?= e(t('أو اتفرج على كل المنافسات النشطة')) ?></a></p>
      </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center gap-2 mb-4">
      <?php foreach (['match' => [t('تناسبك'), $counts['match']], 'fav' => [t('المحفوظة'), $counts['fav']], 'applied' => [t('قدّمت عليها'), $counts['applied']], 'all' => [t('كل النشطة'), null], 'hidden' => [t('المخفية'), null]] as $k => [$l, $n]): ?>
        <a href="<?= e(url('tenders', $k === 'match' ? [] : ['v' => $k])) ?>" class="text-sm rounded-full border px-3 py-1 <?= $view === $k ? 'bg-ink-900 text-[#fff] border-ink-900' : 'border-ink-200 text-ink-600 hover:bg-ink-50' ?>"><?= e($l) ?><?= $n !== null ? ' <span class="opacity-70">' . (int)$n . '</span>' : '' ?></a>
      <?php endforeach; ?>
      <input type="search" id="tnd-q" class="input !mt-0 !w-64 ms-auto text-sm" placeholder="<?= e(t('دوّر في النتايج…')) ?>">
    </div>

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
            <?php foreach (array_slice($t['hits'], 0, 4) as $w): ?><span class="text-[11px] rounded-full bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5"><?= e($w) ?></span><?php endforeach; ?>
          </div>
          <a href="<?= e(tnd_url($t)) ?>" target="_blank" rel="noopener" class="block font-semibold text-ink-900 hover:text-brand-700 leading-snug" dir="auto"><?= $mark($t['name'], $t['hits']) ?></a>
          <div class="text-sm text-ink-600"><?= e($t['agency'] ?: '—') ?><?php if ($t['branch'] && $t['branch'] !== $t['agency']): ?> <span class="text-ink-400">·</span> <?= e($t['branch']) ?><?php endif; ?></div>
          <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500">
            <?php if ($t['activity']): ?><span><?= e(t('النشاط: %s', trim($t['activity']))) ?></span><?php endif; ?>
            <span><?= e(t('الرقم المرجعي: %s', $t['ref'] ?: '—')) ?></span>
            <span><?= e(t('الكراسة: %s', $t['booklet'] === null ? '—' : ((float)$t['booklet'] > 0 ? number_format((float)$t['booklet']) . ' ' . t('ريال') : t('مجانًا')))) ?></span>
            <?php if ($t['inquiry_at']): ?><span><?= e(t('آخر استفسار: %s', fmt_date($t['inquiry_at'], 'd/m'))) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="md:w-56 shrink-0 flex md:flex-col gap-2 md:items-stretch items-center justify-between">
          <div class="rounded-xl px-3 py-2 text-center <?= $d === null ? 'bg-ink-50 text-ink-500' : ($d < 3 ? 'bg-red-50 text-red-700' : ($d < 7 ? 'bg-amber-50 text-amber-800' : 'bg-ink-50 text-ink-700')) ?>">
            <b class="block text-lg leading-tight"><?= $d === null ? '—' : ($left < 0 ? e(t('انتهت')) : ($d === 0 ? e(t('النهارده')) : e(t('%s يوم', $d)))) ?></b>
            <span class="text-[11px]"><?= e(t('آخر موعد للعروض')) ?> <?= e($t['deadline'] ? fmt_date($t['deadline'], 'd/m h:i A') : '') ?></span>
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
