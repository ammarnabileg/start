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
        if (!is_ws_owner()) {
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
                flash('error', t('رقم التحويل لازم يكون بالصيغة الدولية، مثلاً +201001234567.'));
            } else {
                ph_voice_settings($z, $id, [
                    'forwardTo' => $fwd !== '' ? 'tel:' . $fwd : '',
                    'voicemailEnabled' => !empty($_POST['voicemail']),
                    'voicemailGreeting' => mb_substr(trim((string)($_POST['greeting'] ?? '')), 0, 1000),
                ]);
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

render(t('المكالمات'), 'calls', function () use ($z, $apiError, $numbers, $calls, $next, $detail, $direction, $channel, $showDial, $showSettings, $estimate, $estimateErr, $dialTo, $dialFrom, $branded) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $owner = is_ws_owner();
    $voice = ph_voice_numbers($numbers);

    if ($detail) {
        $c = $detail;
        ?>
        <a class="link text-sm" href="<?= e(url('calls')) ?>"><?= e(t('← رجوع للسجل')) ?></a>
        <div class="card p-5 mt-3">
          <div class="flex flex-wrap justify-between gap-2">
            <div class="font-bold" dir="ltr"><?= e(($c['from'] ?? '') . ' → ' . ($c['to'] ?? '')) ?></div>
            <span class="badge"><?= e(($c['direction'] ?? '') === 'inbound' ? t('واردة') : t('صادرة')) ?> · <?= e(($c['channel'] ?? '') === 'whatsapp' ? t('واتساب') : t('تليفون')) ?></span>
          </div>
          <p class="text-sm text-ink-500 mt-1"><?= e(t('البداية: %s · المدة: %s', fmt_date($c['startedAt'] ?? null, 'd/m/Y h:i A'), ph_duration((int)($c['durationSeconds'] ?? 0)))) ?></p>
          <?php if (!empty($c['contactName'])): ?><p class="text-sm mt-1"><?= e(t('جهة الاتصال: %s', $c['contactName'])) ?></p><?php endif; ?>
          <?php if (!empty($c['isVoicemail'])): ?><p class="text-sm text-amber-700 mt-1"><?= e(t('المكالمة راحت للبريد الصوتي.')) ?></p><?php endif; ?>
          <?php if (!empty($c['recordingUrl']) || !empty($c['recordingEnabled'])): ?>
            <a class="btn-ghost mt-3 inline-block" href="<?= e(url('calls', ['rec' => $c['_id'] ?? ''])) ?>" target="_blank" rel="noopener"><?= e(t('اسمع التسجيل')) ?></a>
          <?php endif; ?>
          <?php if (!empty($c['transcript'])): ?>
            <h3 class="font-semibold mt-4 mb-2"><?= e(t('نص المكالمة')) ?></h3>
            <div class="space-y-1 text-sm">
              <?php foreach ((array)$c['transcript'] as $seg): ?>
                <p dir="auto"><?php $sp = $seg['speaker'] ?? $seg['role'] ?? $seg['track'] ?? ''; if ($sp !== ''): ?><span class="text-ink-500"><?= e($sp) ?>:</span> <?php endif; ?><?= e($seg['text'] ?? '') ?></p>
              <?php endforeach; ?>
            </div>
          <?php elseif (!empty($c['lastTranscriptSnippet'])): ?>
            <p class="text-sm mt-3" dir="auto"><?= e($c['lastTranscriptSnippet']) ?></p>
          <?php endif; ?>
        </div>
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
          <label class="block"><span class="label"><?= e(t('رقم العميل')) ?></span><input name="to" required class="input" dir="ltr" placeholder="+14155550100" value="<?= e($dialTo) ?>"></label>
          <button class="btn-ghost"><?= e(t('احسب التكلفة')) ?></button>
        </form>
        <?php if ($estimateErr): ?><div class="mt-3"><?php error_box($estimateErr); ?></div><?php endif; ?>
        <?php if ($estimate): $cost = (float)($estimate['breakdown']['billableCostUSD'] ?? $estimate['perMinuteUsd'] ?? 0); $sel = $dialFrom ?: ph_e164($voice[0]['phoneNumber']); $fwdDefault = ''; foreach ($voice as $n) { if (ph_e164($n['phoneNumber']) === $sel) $fwdDefault = preg_replace('/^tel:/', '', (string)($n['pstnForwardTo'] ?? '')); } ?>
          <form method="post" class="mt-4 space-y-3" onsubmit="return confirm(<?= tjs('المكالمة هتتطلب فعلاً وهتتحسب. نكمل؟') ?>)">
            <?= csrf_field() ?><input type="hidden" name="action" value="call"><input type="hidden" name="to" value="<?= e($dialTo) ?>"><input type="hidden" name="from" value="<?= e($sel) ?>">
            <p class="text-sm font-semibold"><?= e(t('التكلفة التقريبية: $%s للدقيقة (%s).', number_format($cost, 3), (string)($estimate['destinationCountry'] ?? ''))) ?></p>
            <label class="block"><span class="label"><?= e(t('وصّلها على (رقمك)')) ?></span><input name="forward" class="input" dir="ltr" placeholder="+201001234567" value="<?= e($fwdDefault) ?>"></label>
            <label class="block"><span class="label"><?= e(t('رسالة تتقال للعميل لما يرد (اختياري)')) ?></span><input name="greeting" maxlength="1000" class="input"></label>
            <label class="flex items-start gap-2 text-sm p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900"><input type="checkbox" name="confirm" value="1" required class="mt-1">
              <span><?= e(t('فاهم إن المكالمة بتتحسب بالدقيقة على فاتورة Zernio.')) ?></span></label>
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
            <div class="sm:col-span-2 font-semibold" dir="ltr" style="unicode-bidi:plaintext"><?= e($n['phoneNumber'] ?? '') ?> <?php if (empty($n['pstnVoiceEnabled'])): ?><span class="badge"><?= e(t('المكالمات مقفولة')) ?></span><?php endif; ?></div>
            <label class="block"><span class="label"><?= e(t('حوّل المكالمات على')) ?></span><input name="forward" class="input" dir="ltr" placeholder="+201001234567" value="<?= e(preg_replace('/^tel:/', '', (string)($n['pstnForwardTo'] ?? ''))) ?>"></label>
            <label class="flex items-center gap-2 text-sm mt-6"><input type="checkbox" name="voicemail" value="1" <?= !isset($n['voicemailEnabled']) || $n['voicemailEnabled'] ? 'checked' : '' ?>> <?= e(t('البريد الصوتي شغال')) ?></label>
            <label class="block sm:col-span-2"><span class="label"><?= e(t('رسالة البريد الصوتي (فاضية = الرسالة الافتراضية)')) ?></span><textarea name="greeting" maxlength="1000" rows="2" class="input"><?= e($n['voicemailGreeting'] ?? '') ?></textarea></label>
            <?php if (!empty($n['ivrEnabled']) || (!empty($n['pstnForwardTo']) && !preg_match('/^tel:/', (string)$n['pstnForwardTo']))): ?>
              <p class="sm:col-span-2 text-xs text-amber-700"><?= e(t('الرقم ده عليه إعدادات متقدمة (قايمة اختيارات أو تحويل لنظام آلي). عدلها من لوحة Zernio عشان ماتتمسحش.')) ?></p>
            <?php endif; ?>
            <div><button class="btn-primary" onclick="return confirm(<?= tjs('حفظ الإعدادات هيفعّل المكالمات على الرقم ده. نكمل؟') ?>)"><?= e(t('احفظ')) ?></button></div>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <?php if (!$calls): ?><p class="p-6 text-sm text-ink-500"><?= e(t('مفيش مكالمات لسه.')) ?></p><?php endif; ?>
      <?php foreach ($calls as $c): $in = ($c['direction'] ?? '') === 'inbound'; ?>
        <a href="<?= e(url('calls', ['id' => $c['_id'] ?? ''])) ?>" class="flex flex-wrap items-center justify-between gap-2 p-4 border-b border-ink-100 last:border-0 hover:bg-ink-50">
          <div class="min-w-0">
            <div class="font-medium"><span class="<?= $in ? 'text-lime-700' : 'text-brand-700' ?>"><?= $in ? '↙' : '↗' ?></span>
              <span dir="ltr" style="unicode-bidi:isolate"><?= e($in ? ($c['from'] ?? '') : ($c['to'] ?? '')) ?></span>
              <?php if (!empty($c['contactName'])): ?><span class="text-ink-500 text-sm">· <?= e($c['contactName']) ?></span><?php endif; ?></div>
            <div class="text-xs text-ink-500"><?= e(($in ? t('واردة') : t('صادرة')) . ' · ' . (($c['channel'] ?? '') === 'whatsapp' ? t('واتساب') : t('تليفون')) . ' · ' . fmt_date($c['startedAt'] ?? null, 'd/m h:i A')) ?></div>
            <?php if (!empty($c['lastTranscriptSnippet'])): ?><p class="text-xs text-ink-500 truncate max-w-md" dir="auto"><?= e($c['lastTranscriptSnippet']) ?></p><?php endif; ?>
          </div>
          <div class="text-sm text-end">
            <?= e(ph_duration((int)($c['durationSeconds'] ?? 0))) ?>
            <?php if (!empty($c['isVoicemail'])): ?><span class="badge"><?= e(t('بريد صوتي')) ?></span><?php endif; ?>
            <?php if (($c['status'] ?? '') === 'failed' || in_array($c['endReason'] ?? '', ['no_answer', 'rejected'], true)): ?><span class="badge bg-red-100 text-red-800"><?= e(t('مااتردش')) ?></span><?php endif; ?>
            <?php if (!empty($c['recordingUrl'])): ?><span class="badge"><?= e(t('متسجلة')) ?></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if ($next): ?><a class="block text-center p-3 text-sm link" href="<?= e(url('calls', ['direction' => $direction, 'channel' => $channel, 'before' => $next])) ?>"><?= e(t('أقدم ←')) ?></a><?php endif; ?>
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
    <?php
});
