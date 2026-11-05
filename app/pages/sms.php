<?php
$err = null;
$z = zernio_or_null($err);
$apiError = null;
$fxd = getenv('UI_FIXTURES');
$fx = $fxd && is_file($fxd . '/sms.json') ? json_decode((string)file_get_contents($fxd . '/sms.json'), true) : null;
$gate = function_exists('smsgate_conf') ? smsgate_conf() : null;
$gateFake = $fxd && is_file($fxd . '/smsgate_fake.php') ? require $fxd . '/smsgate_fake.php' : null; // اختبارات بس

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($z || $gate)) {
    $from = (string)($_POST['from'] ?? '');
    $to = ph_e164((string)($_POST['to'] ?? ''));
    $text = trim((string)($_POST['text'] ?? ''));
    $_SESSION['sms_draft'] = ['to' => (string)($_POST['to'] ?? ''), 'text' => $text, 'from' => $from];
    try {
        if ($to === '') {
            flash('error', t('اكتب رقم المستلم بالصيغة الدولية، مثلاً +14155550100.'));
        } elseif ($text === '' || mb_strlen($text) > 1530) {
            flash('error', t('اكتب نص الرسالة (أقصى حد 1530 حرف).'));
        } elseif ($from === 'mine') {
            // من الخط بتاعك عن طريق موبايل أندرويد — مش بيتحسب على الاشتراك
            if (!$gate) throw new ZernioException(t('اربط رقمك الأول من تبويب "رقمي".'));
            $st = db()->prepare("SELECT COUNT(*) FROM sms_gate_messages WHERE workspace_id = ? AND direction = 'out' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $st->execute([(int)ws('id')]);
            if ((int)$st->fetchColumn() >= 20) throw new ZernioException(t('بعت رسايل كتير في دقيقة. استنى شوية عشان شركة الاتصالات ماتوقفش الخط.'));
            $r = $gateFake ? $gateFake('send', $to, $text) : smsgate_send($gate, $to, $text);
            smsgate_log((int)ws('id'), 'out', $to, $text, (string)($r['id'] ?? ''), strtolower((string)($r['state'] ?? 'pending')), null, (int)(current_user()['id'] ?? 0) ?: null);
            unset($_SESSION['sms_draft']);
            log_activity('sms_mine.send', $to);
            flash('success', t('الرسالة اتبعتت من رقمك. هتلاقيها في تبويب "رقمي".'));
        } else {
            $from = ph_e164($from);
            $mine = array_map(function ($n) { return ph_e164((string)($n['phoneNumber'] ?? '')); }, ph_sms_numbers(ph_numbers($z)['numbers'] ?? []));
            if (!in_array($from, $mine, true)) {
                flash('error', t('اختار رقم من أرقام الشركة اللي بتبعت SMS.'));
            } elseif (empty($_POST['confirm'])) {
                flash('error', t('لازم تعلّم على مربع التأكيد إن الرسالة بتتحسب.'));
            } else {
                ph_send_sms($z, $from, $to, $text);
                unset($_SESSION['sms_draft']);
                log_activity('sms.send', $from . ' -> ' . $to);
                flash('success', t('الرسالة اتبعتت. الرد هيظهر في صندوق الرسائل.'));
            }
        }
    } catch (Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('sms'));
}

$numbers = [];
$smsNumbers = [];
$convs = [];
$imsg = [];
$regs = [];
$optOuts = [];
$rcs = [];
$partial = [];
if ($fx) {
    foreach (['numbers', 'convs', 'imsg', 'regs', 'optOuts', 'rcs'] as $k) $$k = $fx[$k] ?? [];
    $smsNumbers = ph_sms_numbers($numbers);
} elseif ($z) {
    try {
        $numbers = ph_numbers($z)['numbers'] ?? [];
        $smsNumbers = ph_sms_numbers($numbers);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
    if (!$apiError) {
        // كل جزء لوحده: لو واحد فشل الباقي يظهر
        foreach ([
            'convs' => function () use ($z) { return ph_conversations($z, 'sms'); },
            'imsg' => function () use ($z) { return ph_conversations($z, 'imessage'); },
            'regs' => function () use ($z) { return ph_registrations($z); },
            'optOuts' => function () use ($z) { return ph_opt_outs($z, 500)['optOuts'] ?? []; },
            'rcs' => function () use ($z) { return ph_rcs_agents($z); },
        ] as $k => $fn) {
            try {
                $$k = $fn();
            } catch (ZernioException $ex) {
                $partial[] = $ex->getMessage();
            }
        }
    }
}

// تحميل قايمة الإلغاء CSV
if (($z || $fx) && !$apiError && !empty($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sms-opt-outs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['phone_number', 'opted_out_at', 'keyword', 'our_number']);
    foreach ($optOuts as $o) {
        fputcsv($out, [$o['phoneNumber'] ?? '', $o['optedOutAt'] ?? '', $o['keyword'] ?? '', $o['from'] ?? '']);
    }
    exit;
}

$mineThreads = $gate ? smsgate_threads((int)ws('id'), 50) : [];
$draft = (array)($_SESSION['sms_draft'] ?? []);
unset($_SESSION['sms_draft']);

render(t('رسايل SMS'), 'sms', function () use ($z, $fx, $gate, $apiError, $numbers, $smsNumbers, $convs, $imsg, $regs, $optOuts, $rcs, $partial, $draft, $mineThreads) {
    if (!$z && !$fx && !$gate) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    foreach (array_unique($partial) as $p) { echo '<div class="mb-3">'; error_box($p); echo '</div>'; }
    if (!$numbers && !$gate) { ph_empty_numbers(); return; }
    $owner = can('phone.spend');
    $approved = count(array_filter($smsNumbers, function ($n) { return !empty($n['smsSendApproved']); }));
    $unread = array_sum(array_map(function ($t) { return (int)$t['unread']; }, $mineThreads));
    // كل المحادثات في قايمة واحدة
    $all = [];
    foreach (array_merge($convs, $imsg) as $c) $all[] = ['ch' => ($c['platform'] ?? '') === 'imessage' ? 'imessage' : 'sms', 'name' => (string)($c['participantName'] ?? $c['participantUsername'] ?? ''), 'last' => (string)($c['lastMessage'] ?? ''), 'at' => (string)($c['updatedTime'] ?? ''), 'href' => url('inbox', ['c' => $c['id'] ?? '', 'a' => $c['accountId'] ?? '']), 'unread' => 0];
    foreach ($mineThreads as $t) $all[] = ['ch' => 'mine', 'name' => (string)$t['phone'], 'last' => ($t['direction'] === 'out' ? '↗ ' : '') . $t['body'], 'at' => date('c', strtotime((string)$t['created_at'])), 'href' => url('sms_mine', ['to' => $t['phone']]), 'unread' => (int)$t['unread']];
    usort($all, function ($a, $b) { return strcmp($b['at'], $a['at']); });
    $chLabel = ['sms' => 'SMS', 'imessage' => 'iMessage', 'mine' => t('رقمي')];
    $chCls = ['sms' => 'bg-brand-50 text-brand-700', 'imessage' => 'bg-cyan-50 text-cyan-700', 'mine' => 'bg-lime-50 text-lime-700'];
    $tile = function (string $label, string $value, string $sub = '', string $tone = '') {
        echo '<div class="rounded-2xl border ' . ($tone === 'warn' ? 'border-amber-200 bg-amber-50/60' : 'border-ink-200 bg-white') . ' p-4"><div class="text-xs text-ink-500">' . e($label) . '</div>'
            . '<div class="text-2xl font-bold mt-1 ' . ($tone === 'warn' ? 'text-amber-700' : 'text-ink-900') . '">' . e($value) . '</div>' . ($sub !== '' ? '<div class="text-xs text-ink-500 mt-0.5">' . e($sub) . '</div>' : '') . '</div>';
    };
    $regPending = count(array_filter($regs, function ($r) { return in_array($r['status'] ?? '', ['pending', 'requested', 'changes_requested', 'rejected'], true) || !empty($r['awaitingOtp']); }));
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
      <?php
      $tile(t('أرقام بتبعت SMS'), $approved . ' / ' . count($smsNumbers), $gate ? t('+ رقمك من الموبايل') : '');
      $tile(t('المحادثات'), (string)count($all), $unread ? t('%s رسالة جديدة على رقمك', $unread) : '');
      $tile(t('لغوا الاشتراك'), (string)count($optOuts), t('مش هيوصلهم رسايل'));
      $tile(t('تسجيلات محتاجة متابعة'), (string)$regPending, $regPending ? t('شوف قسم التسجيل') : t('كله تمام'), $regPending ? 'warn' : '');
      ?>
    </div>

    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] xl:grid-cols-[420px_minmax(0,1fr)] gap-6 items-start">
      <div class="space-y-6 min-w-0">
        <?php /* ---------- الإرسال ---------- */ ?>
        <div class="card p-5" id="sms-compose">
          <h2 class="font-bold text-ink-900 mb-4"><?= e(t('ابعت SMS جديدة')) ?></h2>
          <?php if (!$smsNumbers && !$gate): ?>
            <p class="text-sm text-amber-700"><?= e(t('مفيش رقم من أرقام الشركة شغال عليه SMS. فعّل الـ SMS على رقم من لوحة التكامل أو اشتري رقم بيبعت SMS.')) ?></p>
            <a class="link text-sm" href="<?= e(url('sms_mine')) ?>"><?= e(t('أو ابعت من رقمك نفسه ←')) ?></a>
          <?php else: ?>
            <form method="post" id="sms-form" class="space-y-4">
              <?= csrf_field() ?>
              <div>
                <span class="label"><?= e(t('من رقم')) ?></span>
                <div class="grid gap-2">
                  <?php $first = true; foreach ($smsNumbers as $n): $v = (string)$n['phoneNumber']; $chk = ($draft['from'] ?? '') !== '' ? ($draft['from'] === $v) : $first; $first = false; ?>
                    <label class="cursor-pointer"><input type="radio" name="from" value="<?= e($v) ?>" data-kind="paid" class="peer sr-only" <?= $chk ? 'checked' : '' ?>>
                      <span class="flex items-center gap-3 rounded-xl border border-ink-200 px-3 py-2 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-200 transition">
                        <span class="text-lg"><?= country_flag((string)($n['country'] ?? '')) ?></span>
                        <span class="flex-1 min-w-0"><span class="block font-medium text-ink-900 text-sm" dir="ltr" style="text-align:start"><?= e($v) ?></span>
                          <span class="block text-[11px] <?= empty($n['smsSendApproved']) ? 'text-amber-700' : 'text-ink-500' ?>"><?= e(empty($n['smsSendApproved']) ? t('مستني تسجيل') : t('رقم الشركة · بيتحسب على الاشتراك')) ?></span></span>
                      </span></label>
                  <?php endforeach; ?>
                  <?php if ($gate): ?>
                    <label class="cursor-pointer"><input type="radio" name="from" value="mine" data-kind="mine" class="peer sr-only" <?= ($draft['from'] ?? '') === 'mine' || !$smsNumbers ? 'checked' : '' ?>>
                      <span class="flex items-center gap-3 rounded-xl border border-ink-200 px-3 py-2 peer-checked:border-lime-600 peer-checked:bg-lime-50 peer-checked:ring-2 peer-checked:ring-lime-200 transition">
                        <span class="w-7 h-7 rounded-lg bg-lime-600 text-white text-[9px] font-bold flex items-center justify-center">SIM</span>
                        <span class="flex-1 min-w-0"><span class="block font-medium text-ink-900 text-sm"><?= e(t('رقمي (من الموبايل)')) ?></span><span class="block text-[11px] text-ink-500"><?= e(t('من باقة الخط نفسه · مش بيتحسب على الاشتراك')) ?></span></span>
                      </span></label>
                  <?php endif; ?>
                </div>
              </div>
              <label class="block"><span class="label"><?= e(t('إلى رقم')) ?></span>
                <input name="to" id="sms-to" required data-phone="mobile" data-phone-req class="input" dir="ltr" value="<?= e($draft['to'] ?? '') ?>"></label>
              <p id="sms-opt" class="text-xs text-red-600 -mt-2" style="display:none"><?= e(t('الرقم ده لغى الاشتراك (STOP)، والرسالة هتترفض.')) ?></p>
              <div>
                <div class="flex items-center gap-2 mb-1"><span class="label !mb-0 me-auto"><?= e(t('الرسالة')) ?></span><div id="sms-tpl" class="flex flex-wrap gap-1"></div></div>
                <textarea data-ai="sms_short,improve,translate_en,translate_ar" name="text" id="smsText" required maxlength="1530" rows="4" class="input" dir="auto"><?= e($draft['text'] ?? '') ?></textarea>
                <div class="flex flex-wrap items-center gap-2 mt-1.5">
                  <div class="flex gap-0.5" id="sms-segs"></div>
                  <span id="smsCount" class="text-xs text-ink-500"></span>
                  <button type="button" id="sms-stop" class="ms-auto text-xs link"><?= e(t('+ جملة الإلغاء')) ?></button>
                  <button type="button" id="sms-save" class="text-xs link"><?= e(t('احفظ كقالب')) ?></button>
                </div>
                <p id="sms-uni" class="text-[11px] text-amber-700 mt-1" style="display:none"></p>
              </div>
              <div class="rounded-2xl bg-ink-50 p-4">
                <div class="text-[11px] text-ink-500 mb-2"><?= e(t('معاينة على موبايل المستلم')) ?></div>
                <div class="max-w-[260px] rounded-2xl rounded-ss-sm bg-white border border-ink-200 px-3.5 py-2 text-sm text-ink-900 whitespace-pre-wrap break-words min-h-[2.5rem]" id="sms-prev" dir="auto"></div>
              </div>
              <label id="sms-paid" class="flex items-start gap-2 text-sm p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900"><input type="checkbox" name="confirm" value="1" class="mt-1">
                <span><?= e(t('فاهم إن الرسالة بتتحسب، وإن المستلم وافق يستقبل رسايل مننا.')) ?></span></label>
              <button class="btn-primary w-full" id="sms-go"><?= e(t('ابعت')) ?></button>
            </form>
          <?php endif; ?>
        </div>

        <?php /* ---------- التسجيل ---------- */ ?>
        <div class="card p-5">
          <h2 class="font-bold text-ink-900 mb-1"><?= e(t('تسجيل الـ SMS عند شركات المحمول (10DLC)')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('عشان تبعت لأرقام أمريكية لازم الشركة والحملة يتسجلوا الأول. التسجيل بيتعمل من لوحة التكامل لأنه محتاج ورق ومراجعة.')) ?></p>
          <?php if (!$regs): ?><p class="text-sm text-ink-500"><?= e(t('مفيش تسجيلات لسه. لو هتبعت لأمريكا، ابدأ التسجيل من لوحة التكامل.')) ?></p><?php endif; ?>
          <?php foreach ($regs as $r):
            $s = (string)($r['status'] ?? '');
            $stage = $s === 'approved' ? 3 : (in_array($s, ['pending', 'requested', 'changes_requested', 'rejected'], true) ? 2 : 1);
            $bad = in_array($s, ['rejected', 'changes_requested'], true);
            $msg = '';
            if (!empty($r['awaitingOtp'])) $msg = t('صاحب النشاط لازم يدخل كود التأكيد اللي وصله على الموبايل.');
            elseif ($s === 'changes_requested') $msg = t('المراجع طالب تعديلات: %s', (string)($r['adminReviewNote'] ?? ''));
            elseif ($s === 'rejected') $msg = t('اترفض: %s. تقدر تعمل طعن أو تعدل من لوحة التكامل.', (string)($r['declineReason'] ?? ''));
            elseif ($s === 'pending' || $s === 'requested') $msg = t('تحت المراجعة، بتاخد عادة من أيام لأسبوعين.');
          ?>
            <div class="rounded-xl border <?= $bad ? 'border-amber-200' : 'border-ink-200' ?> p-3 mt-3 text-sm">
              <div class="flex items-center justify-between gap-2"><span class="font-medium text-ink-900"><?= e($r['displayName'] ?? $r['registrationType'] ?? '') ?></span><?= ph_reg_status_badge($s) ?></div>
              <div class="text-xs text-ink-500 mt-1" dir="ltr" style="text-align:start"><?= e(implode(', ', (array)($r['phoneNumbers'] ?? []))) ?></div>
              <div class="flex items-center gap-2 mt-3 text-[11px]">
                <?php foreach ([1 => t('اتقدم'), 2 => t('مراجعة'), 3 => t('مقبول')] as $k => $l): ?>
                  <span class="flex items-center gap-1 <?= $k <= $stage ? ($bad && $k === 2 ? 'text-amber-700 font-medium' : 'text-brand-700 font-medium') : 'text-ink-400' ?>"><span class="w-4 h-4 rounded-full text-[9px] flex items-center justify-center <?= $k < $stage ? 'bg-brand-600 text-white' : ($k === $stage ? ($bad ? 'bg-amber-500 text-white' : 'ring-2 ring-brand-600') : 'bg-ink-100') ?>"><?= $k < $stage ? '✓' : $k ?></span><?= e($l) ?></span>
                  <?php if ($k < 3): ?><span class="flex-1 h-0.5 <?= $k < $stage ? 'bg-brand-600' : 'bg-ink-100' ?>"></span><?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php if ($msg): ?><p class="text-xs text-amber-700 mt-2"><?= e($msg) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="space-y-6 min-w-0">
        <?php /* ---------- المحادثات ---------- */ ?>
        <div class="card overflow-hidden">
          <div class="p-4 border-b border-ink-100 flex flex-wrap items-center gap-2">
            <h2 class="font-bold text-ink-900 me-auto"><?= e(t('المحادثات')) ?></h2>
            <div id="cv-chips" class="flex gap-1 text-xs">
              <?php foreach (['' => t('الكل'), 'sms' => 'SMS', 'imessage' => 'iMessage', 'mine' => t('رقمي')] as $k => $l): if ($k === 'mine' && !$gate) continue; ?>
                <button type="button" data-ch="<?= $k ?>" class="rounded-full px-2.5 py-1 <?= $k === '' ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100' ?>"><?= e($l) ?></button>
              <?php endforeach; ?>
            </div>
            <input type="search" id="cv-q" class="input !mt-0 !py-1.5 text-sm w-full" placeholder="<?= e(t('دوّر بالاسم أو الرقم أو الكلام…')) ?>">
          </div>
          <div id="cv-list" class="max-h-[34rem] overflow-y-auto">
            <?php if (!$all): ?><p class="p-6 text-sm text-ink-500 text-center"><?= e(t('مفيش محادثات لسه. الرسايل اللي بتبعتها والردود هتظهر هنا وفي صندوق الرسائل.')) ?></p><?php endif; ?>
            <?php foreach ($all as $c): ?>
              <a href="<?= e($c['href']) ?>" data-ch="<?= e($c['ch']) ?>" data-q="<?= e(mb_strtolower($c['name'] . ' ' . $c['last'])) ?>" class="cv flex items-center gap-3 px-4 py-3 border-b border-ink-100 last:border-0 hover:bg-ink-50">
                <span class="w-10 h-10 shrink-0 rounded-full bg-ink-100 text-ink-600 flex items-center justify-center font-bold"><?= e(mb_strtoupper(mb_substr(ltrim($c['name'], '+') ?: '?', 0, 1))) ?></span>
                <span class="min-w-0 flex-1">
                  <span class="flex items-center gap-2"><span class="font-medium text-ink-900 truncate" dir="auto"><?= e($c['name']) ?></span>
                    <span class="rounded-full text-[10px] px-1.5 py-0.5 <?= $chCls[$c['ch']] ?>"><?= e($chLabel[$c['ch']]) ?></span>
                    <span class="ms-auto text-[11px] text-ink-400 whitespace-nowrap"><?= e(fmt_date($c['at'], 'd/m H:i')) ?></span></span>
                  <span class="flex items-center gap-2"><span class="text-sm text-ink-500 truncate flex-1" dir="auto"><?= e($c['last']) ?></span>
                    <?php if ($c['unread']): ?><span class="rounded-full bg-lime-600 text-white text-[10px] px-1.5"><?= $c['unread'] ?></span><?php endif; ?></span>
                </span>
              </a>
            <?php endforeach; ?>
            <p id="cv-none" class="p-6 text-sm text-ink-500 text-center" style="display:none"><?= e(t('مفيش محادثات مطابقة.')) ?></p>
          </div>
          <div class="p-3 border-t border-ink-100 flex gap-3 text-sm"><a class="link" href="<?= e(url('inbox')) ?>"><?= e(t('افتح صندوق الرسائل')) ?></a><?php if ($gate): ?><a class="link" href="<?= e(url('sms_mine')) ?>"><?= e(t('محادثات رقمي')) ?></a><?php endif; ?></div>
        </div>

        <?php /* ---------- لغوا الاشتراك ---------- */ ?>
        <div class="card p-5">
          <div class="flex items-center gap-2 mb-1"><h2 class="font-bold text-ink-900 me-auto"><?= e(t('الناس اللي لغت الاشتراك')) ?></h2>
            <?php if ($optOuts): ?><a class="btn-ghost !py-1 text-xs" href="<?= e(url('sms', ['csv' => 1])) ?>"><?= e(t('نزّل القايمة (CSV)')) ?></a><?php endif; ?></div>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('أي حد يرد بكلمة STOP بيتشال من الإرسال تلقائياً، ولو حاولت تبعتله الرسالة هترفض. بيرجع بس لو رد بكلمة START.')) ?></p>
          <?php if (!$optOuts): ?><p class="text-sm text-ink-500"><?= e(t('محدش لغى الاشتراك.')) ?></p><?php else: ?>
            <?php if (count($optOuts) > 6): ?><input type="search" id="oo-q" class="input !mt-0 !py-1.5 text-sm mb-2" dir="ltr" placeholder="+1…"><?php endif; ?>
            <div class="max-h-60 overflow-y-auto divide-y divide-ink-100" id="oo-list">
              <?php foreach ($optOuts as $o): ?>
                <div class="oo text-sm flex justify-between gap-2 py-1.5" data-q="<?= e(preg_replace('/\D/', '', (string)($o['phoneNumber'] ?? ''))) ?>">
                  <span dir="ltr" style="unicode-bidi:isolate"><?= e($o['phoneNumber'] ?? '') ?></span>
                  <span class="text-xs text-ink-500"><span class="rounded bg-ink-100 px-1.5 py-0.5 me-1"><?= e($o['keyword'] ?? '') ?></span><?= e(fmt_date($o['optedOutAt'] ?? null, 'd/m/Y')) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card p-5">
          <h2 class="font-bold text-ink-900 mb-2"><?= e(t('رسايل RCS (رسايل غنية على أندرويد)')) ?></h2>
          <?php if (!$rcs): ?>
            <p class="text-sm text-ink-500"><?= e(t('مفيش حساب RCS. ده محتاج طلب وموافقة من جوجل وشركات المحمول، وبيتعمل من لوحة التكامل.')) ?></p>
          <?php else: foreach ($rcs as $a): ?>
            <div class="text-sm flex justify-between gap-2 py-1"><span><?= e($a['displayName'] ?? $a['name'] ?? $a['_id'] ?? '') ?></span><span class="badge"><?= e((string)($a['status'] ?? $a['launchStatus'] ?? '')) ?></span></div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
    <script>window.SMSX = <?= json_encode(['ws' => (int)ws('id'), 'opt' => array_values(array_filter(array_map(function ($o) { return preg_replace('/\D/', '', (string)($o['phoneNumber'] ?? '')); }, $optOuts))), 's' => [
      'count' => t('%s حرف · %s رسالة'), 'uni' => t('فيه حروف عربي أو رموز، فالرسالة الواحدة 70 حرف بس بدل 160.'), 'stop' => t('للإلغاء ابعت STOP'),
      'saved' => t('اتحفظ القالب'), 'tplName' => t('اسم القالب؟'), 'del' => t('امسح'), 'many' => t('الرسالة هتتقسم على %s رسايل وكل واحدة بتتحسب.'),
      'confirmPaid' => t('الرسالة هتتبعت فعلاً وهتتحسب. نكمل؟'), 'confirmMine' => t('الرسالة هتتبعت من خطك. نكمل؟'),
    ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-sms.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
