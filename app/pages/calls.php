<?php
$err = null;
$z = zernio_or_null($err);
$apiError = null;

// تسجيل مكالمة: نجيب لينك جديد ونحوّل عليه (اللينكات بتنتهي بعد دقايق)
if ($z && !empty($_GET['rec'])) {
    try {
        $u = ph_call_recording($z, (string)$_GET['rec']);
        if (preg_match('#^https://#', $u)) redirect($u);
        flash('error', t('مفيش تسجيل للمكالمة دي.'));
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('calls', ['id' => (string)$_GET['rec']]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $back = url('calls');
    try {
        if (!can('phone.spend')) {
            flash('error', t('المكالمات وإعداداتها لصاحب الشركة بس لأنها بتكلف فلوس.'));
        } elseif ($action === 'call') {
            $to = ph_e164((string)($_POST['to'] ?? ''));
            $from = ph_e164((string)($_POST['from'] ?? ''));
            $fwd = ph_e164((string)($_POST['forward'] ?? ''));
            $mine = array_map(function ($n) { return ph_e164((string)($n['phoneNumber'] ?? '')); }, ph_voice_numbers(ph_numbers($z)['numbers'] ?? []));
            $back = url('calls', ['dial' => 1]);
            if (!in_array($from, $mine, true) || $to === '') {
                flash('error', t('اختار رقم من أرقامك واكتب رقم صحيح بالصيغة الدولية.'));
            } elseif (empty($_POST['confirm'])) {
                flash('error', t('لازم تعلّم على مربع التأكيد إن المكالمة بتتحسب.'));
            } else {
                $body = ['to' => $to, 'fromNumber' => $from];
                if ($fwd !== '') $body['forwardTo'] = 'tel:' . $fwd;
                $g = trim((string)($_POST['greeting'] ?? ''));
                if ($g !== '') $body['greeting'] = mb_substr($g, 0, 1000);
                $r = ph_place_call($z, $body);
                log_activity('call.place', $from . ' -> ' . $to);
                flash('success', t('المكالمة بتتطلب دلوقتي. هتظهر في السجل.'));
                $back = url('calls');
            }
        } elseif ($action === 'settings') {
            $id = (string)($_POST['id'] ?? '');
            $fwdRaw = trim((string)($_POST['forward'] ?? ''));
            $fwd = ph_e164($fwdRaw);
            $back = url('calls', ['settings' => 1]);
            if ($fwdRaw !== '' && $fwd === '') {
                flash('error', t('رقم التحويل لازم يكون بالصيغة الدولية، مثلاً %s.', cx_phone()));
            } else {
                $body = [
                    'forwardTo' => $fwd !== '' ? 'tel:' . $fwd : '',
                    'voicemailEnabled' => !empty($_POST['voicemail']),
                    'voicemailGreeting' => mb_substr(trim((string)($_POST['greeting'] ?? '')), 0, 1000),
                ];
                // تحويل متقدم (sip/wss) موجود والخانة فاضية: سيبه زي ما هو
                if ($fwd === '' && !empty($_POST['keep_fwd'])) unset($body['forwardTo']);
                ph_voice_settings($z, $id, $body);
                log_activity('call.settings', $id);
                flash('success', t('إعدادات المكالمات اتحفظت.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

$direction = in_array($_GET['direction'] ?? '', ['inbound', 'outbound'], true) ? $_GET['direction'] : '';
$channel = in_array($_GET['channel'] ?? '', ['whatsapp', 'pstn'], true) ? $_GET['channel'] : '';
$before = (string)($_GET['before'] ?? '');
$id = (string)($_GET['id'] ?? '');
$showDial = !empty($_GET['dial']);
$showSettings = !empty($_GET['settings']);
$numbers = [];
$calls = [];
$next = null;
$detail = null;
$estimate = null;
$estimateErr = null;
$branded = [];
$dialTo = ph_e164((string)($_GET['to'] ?? ''));
$dialFrom = ph_e164((string)($_GET['from'] ?? ''));
if ($z) {
    try {
        $numbers = array_values(array_filter(ph_numbers($z)['numbers'] ?? [], function ($n) { return ($n['status'] ?? '') !== 'released'; }));
        if ($id) {
            $detail = ph_call($z, $id);
        } else {
            $r = ph_calls($z, ['direction' => $direction, 'channel' => $channel, 'before' => $before, 'limit' => 50]);
            $calls = $r['calls'] ?? [];
            $next = $r['nextCursor'] ?? null;
        }
        if ($showDial && $dialTo) {
            try {
                $estimate = ph_call_estimate($z, $dialTo, $dialFrom);
            } catch (ZernioException $ex) {
                $estimateErr = $ex->getMessage();
            }
        }
        try { $branded = ph_branded_identities($z); } catch (ZernioException $ex) { $branded = []; }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/calls.json') ? json_decode((string)file_get_contents($fxd . '/calls.json'), true) : null;
if ($fx) {
    $apiError = null;
    $numbers = $fx['numbers'];
    if ($id) $detail = ($fx['detail']['_id'] ?? '') === $id ? $fx['detail'] : (array_values(array_filter($fx['calls'], function ($c) use ($id) { return $c['_id'] === $id; }))[0] ?? null);
    else $calls = array_values(array_filter($fx['calls'], function ($c) use ($direction, $channel) { return (!$direction || $c['direction'] === $direction) && (!$channel || $c['channel'] === $channel); }));
    if ($showDial && $dialTo) $estimate = $fx['estimate'];
    $branded = $fx['branded'] ?? [];
}

render(t('المكالمات'), 'calls', function () use ($fx, $z, $apiError, $numbers, $calls, $next, $detail, $direction, $channel, $showDial, $showSettings, $estimate, $estimateErr, $dialTo, $dialFrom, $branded) {
    if (!$z && !$fx) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $owner = can('phone.spend');
    $voice = ph_voice_numbers($numbers);
    $missed = function (array $c): bool { return ($c['status'] ?? '') === 'failed' || in_array($c['endReason'] ?? '', ['no_answer', 'rejected', 'busy', 'canceled'], true); };

    if ($detail) {
        $c = $detail;
        ?>
        <?php $in = ($c['direction'] ?? '') === 'inbound'; $other = (string)($in ? ($c['from'] ?? '') : ($c['to'] ?? '')); $miss = $missed($c); ?>
        <a class="link text-sm" href="<?= e(url('calls')) ?>"><?= e(t('← رجوع للسجل')) ?></a>
        <div class="card p-6 mt-3">
          <div class="flex flex-wrap items-center gap-4">
            <span class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl <?= $miss ? 'bg-red-50 text-red-600' : ($in ? 'bg-lime-50 text-lime-700' : 'bg-brand-50 text-brand-700') ?>"><?= $miss ? '✕' : ($in ? '↙' : '↗') ?></span>
            <div class="min-w-0 flex-1">
              <div class="text-xl font-bold text-ink-900"><?= e(($c['contactName'] ?? '') ?: '') ?> <span dir="ltr" class="<?= !empty($c['contactName']) ? 'text-base font-medium text-ink-500' : '' ?>" style="unicode-bidi:isolate"><?= e($other) ?></span></div>
              <div class="text-sm text-ink-500 mt-0.5"><?= e(implode(' · ', [$in ? t('واردة') : t('صادرة'), ($c['channel'] ?? '') === 'whatsapp' ? t('واتساب') : t('تليفون'), fmt_date($c['startedAt'] ?? null, 'd/m/Y h:i A')])) ?></div>
            </div>
            <div class="flex gap-2">
              <button type="button" class="ph-copy btn-ghost !py-1.5" data-v="<?= e($other) ?>"><?= e(t('انسخ الرقم')) ?></button>
              <?php if ($owner && $voice && ($c['channel'] ?? '') !== 'whatsapp'): ?><a class="btn-primary !py-1.5" href="<?= e(url('calls', ['dial' => 1, 'to' => $other])) ?>"><?= e(t('اتصل تاني')) ?></a><?php endif; ?>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-3 mt-5">
            <div class="rounded-xl bg-ink-50 p-3"><div class="text-xs text-ink-500"><?= e(t('المدة')) ?></div><div class="font-bold text-ink-900" dir="ltr" style="text-align:start"><?= e(ph_duration((int)($c['durationSeconds'] ?? 0))) ?></div></div>
            <div class="rounded-xl bg-ink-50 p-3"><div class="text-xs text-ink-500"><?= e(t('النتيجة')) ?></div><div class="font-bold <?= $miss ? 'text-red-600' : 'text-ink-900' ?>"><?= e(!empty($c['isVoicemail']) ? t('بريد صوتي') : ($miss ? t('مااتردش') : t('اترد عليها'))) ?></div></div>
            <div class="rounded-xl bg-ink-50 p-3"><div class="text-xs text-ink-500"><?= e(t('من رقم')) ?></div><div class="font-bold text-ink-900 truncate" dir="ltr" style="text-align:start"><?= e((string)($in ? ($c['to'] ?? '') : ($c['from'] ?? ''))) ?></div></div>
          </div>
          <?php if (!empty($c['isVoicemail'])): ?><p class="text-sm text-amber-700 mt-3"><?= e(t('المكالمة راحت للبريد الصوتي.')) ?></p><?php endif; ?>
          <?php if (!empty($c['recordingUrl']) || !empty($c['recordingEnabled'])): ?>
            <a class="btn-ghost mt-3 inline-block" href="<?= e(url('calls', ['rec' => $c['_id'] ?? ''])) ?>" target="_blank" rel="noopener"><?= e(t('اسمع التسجيل')) ?></a>
          <?php endif; ?>
          <?php if (!empty($c['transcript'])): ?>
            <div class="flex items-center gap-2 mt-6 mb-3"><h3 class="font-semibold text-ink-900"><?= e(t('نص المكالمة')) ?></h3>
              <input type="search" id="tr-q" class="input !mt-0 !py-1 !w-48 text-sm ms-auto" placeholder="<?= e(t('دوّر في الكلام...')) ?>"></div>
            <div id="tr" class="space-y-2 text-sm rounded-2xl bg-ink-50 p-4 max-h-[32rem] overflow-y-auto">
              <?php $first = null; foreach ((array)$c['transcript'] as $seg): $sp = (string)($seg['speaker'] ?? $seg['role'] ?? $seg['track'] ?? ''); if ($first === null) $first = $sp; $mine = $sp === $first; ?>
                <div class="tr-seg flex <?= $mine ? 'justify-start' : 'justify-end' ?>">
                  <div class="max-w-[80%] rounded-2xl px-3.5 py-2 <?= $mine ? 'bg-white border border-ink-200 rounded-ss-sm' : 'bg-brand-600 text-white rounded-se-sm' ?>">
                    <?php if ($sp !== ''): ?><div class="text-[10px] opacity-60 mb-0.5"><?= e($sp) ?></div><?php endif; ?><div class="tr-t" dir="auto"><?= e($seg['text'] ?? '') ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php elseif (!empty($c['lastTranscriptSnippet'])): ?>
            <p class="text-sm mt-3" dir="auto"><?= e($c['lastTranscriptSnippet']) ?></p>
          <?php endif; ?>
          <?php if (ai_connection() && can('ai.use') && ai_feature_on('calls') && (!empty($c['transcript']) || !empty($c['recordingUrl']) || !empty($c['recordingEnabled']))): ?>
          <div class="mt-5 rounded-2xl border border-brand-200 bg-brand-50/40 p-4" id="call-ai" data-id="<?= e($c['_id'] ?? '') ?>">
            <div class="flex flex-wrap gap-2">
              <button type="button" data-mode="summary" class="btn-primary !py-1.5 !text-xs"><?= ai_icon('spark', 'w-3.5 h-3.5') ?> <?= e(t('لخّص واستخرج المهام')) ?></button>
              <?php if (setting('ai_call_consent_' . (int)ws('id'), '0') === '1'): ?><button type="button" data-mode="score" class="btn-ghost !py-1.5 !text-xs"><?= ai_icon('competitors', 'w-3.5 h-3.5') ?> <?= e(t('تقييم جودة المكالمة')) ?></button><?php endif; ?>
            </div>
            <div class="call-out mt-3 text-sm leading-relaxed hidden"></div>
          </div>
          <script>
          (function () {
            var box = document.getElementById('call-ai'), out = box.querySelector('.call-out');
            box.addEventListener('click', function (e) {
              var b = e.target.closest('[data-mode]'); if (!b) return; b.disabled = true; b.classList.add('animate-pulse');
              out.classList.remove('hidden'); out.textContent = (window.T && T.working) || '...';
              var fd = new FormData(); fd.append('task', 'call'); fd.append('id', box.dataset.id); fd.append('mode', b.dataset.mode);
              fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content } }).then(function (r) { return r.json(); })
                .then(function (d) { if (d.error) { out.innerHTML = '<p class="text-red-600"></p>'; out.firstChild.textContent = d.error; } else out.innerHTML = d.html; })
                .catch(function () { out.textContent = (window.T && T.liveFailed) || 'Error'; })
                .finally(function () { b.disabled = false; b.classList.remove('animate-pulse'); });
            });
          })();
          </script>
          <?php endif; ?>
        </div>
        <script>window.CLX = <?= json_encode(['copied' => t('اتنسخ')], JSON_UNESCAPED_UNICODE) ?>;</script>
        <script src="assets/ui-calls.js?v=<?= e(APP_VERSION) ?>"></script>
        <?php
        return;
    }
    ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div class="seg">
        <?php foreach (['' => t('الكل'), 'inbound' => t('واردة'), 'outbound' => t('صادرة')] as $k => $l): ?>
          <a class="<?= $direction === $k ? 'on' : '' ?>" href="<?= e(url('calls', ['direction' => $k, 'channel' => $channel])) ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="seg">
        <?php foreach (['' => t('كل القنوات'), 'pstn' => t('تليفون'), 'whatsapp' => t('واتساب')] as $k => $l): ?>
          <a class="<?= $channel === $k ? 'on' : '' ?>" href="<?= e(url('calls', ['direction' => $direction, 'channel' => $k])) ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
      </div>
      <?php if ($owner && $voice): ?>
        <div class="flex gap-2">
          <a class="btn-ghost" href="<?= e(url('calls', ['settings' => 1])) ?>"><?= e(t('إعدادات الأرقام')) ?></a>
          <a class="btn-primary" href="<?= e(url('calls', ['dial' => 1])) ?>"><?= e(t('+ اعمل مكالمة')) ?></a>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$numbers): ?><div class="mb-6"><?php ph_empty_numbers(); ?></div><?php endif; ?>
    <?php if ($numbers && !$voice): ?>
      <p class="text-sm text-amber-700 mb-4"><?= e(t('المكالمات العادية مش مفعلة على أي رقم. صاحب الشركة يقدر يفعلها من "إعدادات الأرقام".')) ?>
        <?php if ($owner): ?><a class="link" href="<?= e(url('calls', ['settings' => 1])) ?>"><?= e(t('افتح الإعدادات')) ?></a><?php endif; ?></p>
    <?php endif; ?>

    <?php if ($showDial && $owner && $voice): ?>
      <div class="card p-5 mb-6">
        <h2 class="font-bold mb-1"><?= e(t('مكالمة صادرة')) ?></h2>
        <p class="text-xs text-ink-500 mb-3"><?= e(t('النظام هيتصل بالعميل من رقم الشركة، ولما يرد هيوصله بالرقم اللي تكتبه في "وصّلها على" (موبايلك مثلاً). المكالمة بتتحسب بالدقيقة.')) ?></p>
        <form method="get" class="grid sm:grid-cols-3 gap-3 items-end">
          <input type="hidden" name="p" value="calls"><input type="hidden" name="dial" value="1">
          <label class="block"><span class="label"><?= e(t('من رقم')) ?></span>
            <select name="from" class="input" dir="ltr"><?php foreach ($voice as $n): ?><option <?= ph_e164($n['phoneNumber']) === $dialFrom ? 'selected' : '' ?>><?= e($n['phoneNumber']) ?></option><?php endforeach; ?></select></label>
          <label class="block"><span class="label"><?= e(t('رقم العميل')) ?></span><input name="to" required data-phone="any" data-phone-req class="input" dir="ltr" value="<?= e($dialTo) ?>"></label>
          <button class="btn-ghost"><?= e(t('احسب التكلفة')) ?></button>
        </form>
        <?php if ($estimateErr): ?><div class="mt-3"><?php error_box($estimateErr); ?></div><?php endif; ?>
        <?php if ($estimate): $cost = (float)($estimate['breakdown']['billableCostUSD'] ?? $estimate['perMinuteUsd'] ?? 0); $sel = $dialFrom ?: ph_e164($voice[0]['phoneNumber']); $fwdDefault = ''; foreach ($voice as $n) { if (ph_e164($n['phoneNumber']) === $sel) $fwdDefault = preg_replace('/^tel:/', '', (string)($n['pstnForwardTo'] ?? '')); } ?>
          <form method="post" class="mt-4 space-y-3" onsubmit="return confirm(<?= tjs('المكالمة هتتطلب فعلاً وهتتحسب. نكمل؟') ?>)">
            <?= csrf_field() ?><input type="hidden" name="action" value="call"><input type="hidden" name="to" value="<?= e($dialTo) ?>"><input type="hidden" name="from" value="<?= e($sel) ?>">
            <p class="text-sm font-semibold"><?= e(t('التكلفة التقريبية: $%s للدقيقة (%s).', number_format($cost, 3), (string)($estimate['destinationCountry'] ?? ''))) ?></p>
            <label class="block"><span class="label"><?= e(t('وصّلها على (رقمك)')) ?></span><input name="forward" class="input" dir="ltr" placeholder="<?= e(cx_phone()) ?>" value="<?= e($fwdDefault) ?>"></label>
            <label class="block"><span class="label"><?= e(t('رسالة تتقال للعميل لما يرد (اختياري)')) ?></span><input name="greeting" maxlength="1000" class="input"></label>
            <label class="flex items-start gap-2 text-sm p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900"><input type="checkbox" name="confirm" value="1" required class="mt-1">
              <span><?= e(t('فاهم إن المكالمة بتتحسب بالدقيقة على فاتورة الاشتراك.')) ?></span></label>
            <button class="btn-primary"><?= e(t('اتصل دلوقتي')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($showSettings && $owner && $numbers): ?>
      <div class="card p-5 mb-6">
        <h2 class="font-bold mb-1"><?= e(t('إعدادات المكالمات لكل رقم')) ?></h2>
        <p class="text-xs text-ink-500 mb-3"><?= e(t('الحفظ بيفعّل المكالمات العادية على الرقم لو مش مفعلة. المكالمات الواردة بتتحول على رقم التحويل، ولو مفيش حد رد بتروح للبريد الصوتي.')) ?></p>
        <?php foreach ($numbers as $n): if (($n['status'] ?? '') !== 'active' || (isset($n['hostedByZernio']) && !$n['hostedByZernio'])) continue; ?>
          <form method="post" class="border-t border-ink-100 pt-4 mt-4 grid sm:grid-cols-2 gap-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="settings"><input type="hidden" name="id" value="<?= e($n['_id'] ?? '') ?>">
            <?php $customFwd = !empty($n['pstnForwardTo']) && !preg_match('/^tel:/', (string)$n['pstnForwardTo']); ?><?php if ($customFwd): ?><input type="hidden" name="keep_fwd" value="1"><?php endif; ?>
            <div class="sm:col-span-2 font-semibold" dir="ltr" style="unicode-bidi:plaintext"><?= e($n['phoneNumber'] ?? '') ?> <?php if (empty($n['pstnVoiceEnabled'])): ?><span class="badge"><?= e(t('المكالمات مقفولة')) ?></span><?php endif; ?></div>
            <label class="block"><span class="label"><?= e(t('حوّل المكالمات على')) ?></span><input name="forward" class="input" dir="ltr" placeholder="<?= e(cx_phone()) ?>" value="<?= e($customFwd ? '' : preg_replace('/^tel:/', '', (string)($n['pstnForwardTo'] ?? ''))) ?>"></label>
            <label class="flex items-center gap-2 text-sm mt-6"><input type="checkbox" name="voicemail" value="1" <?= !isset($n['voicemailEnabled']) || $n['voicemailEnabled'] ? 'checked' : '' ?>> <?= e(t('البريد الصوتي شغال')) ?></label>
            <label class="block sm:col-span-2"><span class="label"><?= e(t('رسالة البريد الصوتي (فاضية = الرسالة الافتراضية)')) ?></span><textarea name="greeting" maxlength="1000" rows="2" class="input"><?= e($n['voicemailGreeting'] ?? '') ?></textarea></label>
            <?php if (!empty($n['ivrEnabled']) || (!empty($n['pstnForwardTo']) && !preg_match('/^tel:/', (string)$n['pstnForwardTo']))): ?>
              <p class="sm:col-span-2 text-xs text-amber-700"><?= e(t('الرقم ده عليه إعدادات متقدمة (قايمة اختيارات أو تحويل لنظام آلي). عدلها من لوحة التكامل عشان ماتتمسحش.')) ?></p>
            <?php endif; ?>
            <div><button class="btn-primary" onclick="return confirm(<?= tjs('حفظ الإعدادات هيفعّل المكالمات على الرقم ده. نكمل؟') ?>)"><?= e(t('احفظ')) ?></button></div>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($calls):
      $tot = count($calls); $inN = 0; $missN = 0; $talk = 0; $ans = 0; $missIn = [];
      foreach ($calls as $c) {
          $in = ($c['direction'] ?? '') === 'inbound'; if ($in) $inN++;
          if ($missed($c)) { $missN++; if ($in) { $k = (string)($c['from'] ?? ''); if (!isset($missIn[$k])) $missIn[$k] = $c; } }
          else { $ans++; $talk += (int)($c['durationSeconds'] ?? 0); }
      }
      // اللي فاتتنا ومحدش رجع كلمهم بعدها
      foreach ($calls as $c) { if (($c['direction'] ?? '') === 'outbound' && !$missed($c)) { $k = (string)($c['to'] ?? ''); if (isset($missIn[$k]) && ($c['startedAt'] ?? '') > ($missIn[$k]['startedAt'] ?? '')) unset($missIn[$k]); } }
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('المكالمات')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= $tot ?></div><div class="text-xs text-ink-500"><?= e(t('%s واردة · %s صادرة', $inN, $tot - $inN)) ?></div></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('نسبة الرد')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= round($ans / max(1, $tot) * 100) ?>%</div><div class="h-1.5 rounded-full bg-ink-100 mt-2 overflow-hidden"><div class="h-full bg-lime-500" style="width:<?= round($ans / max(1, $tot) * 100) ?>%"></div></div></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('متوسط المكالمة')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1" dir="ltr" style="text-align:start"><?= e(ph_duration(intdiv($talk, max(1, $ans)))) ?></div><div class="text-xs text-ink-500"><?= e(t('%s دقيقة كلام', round($talk / 60))) ?></div></div>
      <div class="rounded-2xl border <?= $missIn ? 'border-red-200 bg-red-50/60' : 'border-ink-200 bg-white' ?> p-4"><div class="text-xs text-ink-500"><?= e(t('فايتة ومحدش رجعلها')) ?></div><div class="text-2xl font-bold mt-1 <?= $missIn ? 'text-red-600' : 'text-ink-900' ?>"><?= count($missIn) ?></div><div class="text-xs text-ink-500"><?= e(t('%s مكالمة مااتردش عليها إجمالاً', $missN)) ?></div></div>
    </div>
    <?php if ($missIn): ?>
      <div class="rounded-2xl border border-red-200 bg-white p-4 mb-5">
        <div class="font-bold text-ink-900 mb-1"><?= e(t('ناس اتصلت ومحدش رد عليها')) ?></div>
        <p class="text-xs text-ink-500 mb-3"><?= e(t('مفيش مكالمة صادرة ليهم بعد ما فاتتهم. كل دقيقة بتتأخر فيها العميل ممكن يروح لحد تاني.')) ?></p>
        <div class="flex flex-wrap gap-2">
          <?php foreach (array_slice($missIn, 0, 8) as $k => $c): ?>
            <span class="inline-flex items-center gap-2 rounded-full border border-ink-200 ps-3 pe-1 py-1 text-sm">
              <span><?= e(($c['contactName'] ?? '') ?: '') ?> <span dir="ltr" class="text-ink-600" style="unicode-bidi:isolate"><?= e($k) ?></span></span>
              <span class="text-[11px] text-ink-400" data-ago="<?= e((string)($c['startedAt'] ?? '')) ?>"></span>
              <?php if ($owner && $voice && ($c['channel'] ?? '') !== 'whatsapp'): ?><a class="rounded-full bg-brand-600 text-white text-xs px-2.5 py-1 hover:bg-brand-700" href="<?= e(url('calls', ['dial' => 1, 'to' => $k])) ?>"><?= e(t('اتصل')) ?></a><?php endif; ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <div id="cl-chart" class="card p-5 mb-5"></div>
    <?php endif; ?>

    <div class="card overflow-hidden">
      <?php if ($calls): ?>
      <div class="flex flex-wrap items-center gap-2 p-3 border-b border-ink-100">
        <input type="search" id="cl-q" class="input !mt-0 !py-1.5 flex-1 min-w-[12rem] text-sm" placeholder="<?= e(t('دوّر برقم أو اسم أو كلام من المكالمة...')) ?>">
        <div id="cl-chips" class="flex flex-wrap gap-1.5 text-xs">
          <?php foreach (['' => t('الكل'), 'missed' => t('فايتة'), 'vm' => t('بريد صوتي'), 'rec' => t('متسجلة'), 'long' => t('أطول من 5 دقايق')] as $k => $l): ?>
            <button type="button" data-f="<?= $k ?>" class="rounded-full px-3 py-1 <?= $k === '' ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100' ?>"><?= e($l) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!$calls): ?><div class="p-10 text-center"><div class="w-12 h-12 mx-auto rounded-full bg-ink-100 text-ink-500 flex items-center justify-center mb-3"><?= ui_icon('phone', 'w-6 h-6') ?></div><p class="text-sm text-ink-500"><?= e(t('مفيش مكالمات لسه.')) ?></p></div><?php endif; ?>
      <div id="cl-list">
      <?php foreach ($calls as $c): $in = ($c['direction'] ?? '') === 'inbound'; $miss = $missed($c); $other = (string)($in ? ($c['from'] ?? '') : ($c['to'] ?? '')); $dur = (int)($c['durationSeconds'] ?? 0); ?>
        <div class="cl-row group flex items-center gap-3 px-4 py-3 border-b border-ink-100 last:border-0 hover:bg-ink-50"
             data-at="<?= e((string)($c['startedAt'] ?? '')) ?>" data-in="<?= $in ? 1 : 0 ?>" data-miss="<?= $miss ? 1 : 0 ?>" data-vm="<?= empty($c['isVoicemail']) ? 0 : 1 ?>" data-rec="<?= empty($c['recordingUrl']) ? 0 : 1 ?>" data-dur="<?= $dur ?>"
             data-q="<?= e(mb_strtolower(preg_replace('/\D/', '', $other) . ' ' . ($c['contactName'] ?? '') . ' ' . ($c['lastTranscriptSnippet'] ?? ''))) ?>">
          <span class="w-9 h-9 shrink-0 rounded-full flex items-center justify-center <?= $miss ? 'bg-red-50 text-red-600' : ($in ? 'bg-lime-50 text-lime-700' : 'bg-brand-50 text-brand-700') ?>" title="<?= e($in ? t('واردة') : t('صادرة')) ?>"><?= $miss ? '✕' : ($in ? '↙' : '↗') ?></span>
          <a href="<?= e(url('calls', ['id' => $c['_id'] ?? ''])) ?>" class="min-w-0 flex-1">
            <div class="font-medium text-ink-900 truncate <?= $miss ? '!text-red-700' : '' ?>"><?php if (!empty($c['contactName'])): ?><?= e($c['contactName']) ?> <span class="text-ink-500 font-normal text-sm" dir="ltr" style="unicode-bidi:isolate"><?= e($other) ?></span><?php else: ?><span dir="ltr" style="unicode-bidi:isolate"><?= e($other) ?></span><?php endif; ?></div>
            <div class="text-xs text-ink-500 truncate"><?= e(implode(' · ', array_filter([($c['channel'] ?? '') === 'whatsapp' ? t('واتساب') : '', fmt_date($c['startedAt'] ?? null, 'h:i A'), $c['lastTranscriptSnippet'] ?? '']))) ?></div>
          </a>
          <div class="hidden sm:flex items-center gap-1">
            <?php if (!empty($c['isVoicemail'])): ?><span class="rounded-full bg-amber-50 text-amber-700 text-[11px] px-2 py-0.5"><?= e(t('بريد صوتي')) ?></span><?php endif; ?>
            <?php if (!empty($c['recordingUrl'])): ?><a href="<?= e(url('calls', ['rec' => $c['_id'] ?? ''])) ?>" target="_blank" rel="noopener" class="rounded-full bg-ink-100 text-ink-700 text-[11px] px-2 py-0.5 hover:bg-ink-200"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg> <?= e(t('متسجلة')) ?></a><?php endif; ?>
          </div>
          <span class="w-14 text-end text-sm tabular-nums <?= $miss ? 'text-red-600' : 'text-ink-700' ?>" dir="ltr"><?= $miss ? '—' : e(ph_duration($dur)) ?></span>
          <span class="flex items-center gap-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition">
            <button type="button" class="ph-copy w-8 h-8 rounded-lg text-ink-400 hover:bg-ink-100" data-v="<?= e($other) ?>" title="<?= e(t('انسخ الرقم')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h11v12H8zM5 16V4h11"/></svg></button>
            <?php if ($owner && $voice && ($c['channel'] ?? '') !== 'whatsapp'): ?><a class="w-8 h-8 rounded-lg text-brand-600 hover:bg-brand-50 flex items-center justify-center" href="<?= e(url('calls', ['dial' => 1, 'to' => $other])) ?>" title="<?= e(t('اتصل تاني')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z"/></svg></a><?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
      </div>
      <p id="cl-none" class="p-6 text-center text-sm text-ink-500" style="display:none"><?= e(t('مفيش مكالمات مطابقة.')) ?></p>
      <?php if ($next): ?><a class="block text-center p-3 text-sm link border-t border-ink-100" href="<?= e(url('calls', ['direction' => $direction, 'channel' => $channel, 'before' => $next])) ?>"><?= e(t('أقدم ←')) ?></a><?php endif; ?>
    </div>

    <?php if ($branded): ?>
      <h2 class="font-bold mt-6 mb-2"><?= e(t('اسم الشركة على شاشة المتصل (Branded Calling)')) ?></h2>
      <div class="card">
        <?php foreach ($branded as $b): ?>
          <div class="p-3 border-b border-ink-100 last:border-0 text-sm flex justify-between gap-2">
            <span><?= e($b['displayName'] ?? $b['name'] ?? $b['_id'] ?? '') ?></span><span class="badge"><?= e((string)($b['status'] ?? '')) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <script>window.CLX = <?= json_encode(['copied' => t('اتنسخ'), 'today' => t('النهارده'), 'yday' => t('امبارح'), 'chart' => t('المكالمات في آخر 14 يوم'), 'in' => t('واردة'), 'out' => t('صادرة'), 'miss' => t('فايتة'),
      'ago' => [t('دلوقتي'), t('من %s دقيقة'), t('من %s ساعة'), t('من %s يوم')], 'lang' => lang()], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-calls.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
