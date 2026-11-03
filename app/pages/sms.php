<?php
$err = null;
$z = zernio_or_null($err);
$apiError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $from = ph_e164((string)($_POST['from'] ?? ''));
    $to = ph_e164((string)($_POST['to'] ?? ''));
    $text = trim((string)($_POST['text'] ?? ''));
    $_SESSION['sms_draft'] = ['to' => (string)($_POST['to'] ?? ''), 'text' => $text];
    try {
        $mine = array_map(function ($n) { return ph_e164((string)($n['phoneNumber'] ?? '')); }, ph_sms_numbers(ph_numbers($z)['numbers'] ?? []));
        if (!in_array($from, $mine, true)) {
            flash('error', t('اختار رقم من أرقام الشركة اللي بتبعت SMS.'));
        } elseif ($to === '') {
            flash('error', t('اكتب رقم المستلم بالصيغة الدولية، مثلاً +14155550100.'));
        } elseif ($text === '' || mb_strlen($text) > 1530) {
            flash('error', t('اكتب نص الرسالة (أقصى حد 1530 حرف).'));
        } elseif (empty($_POST['confirm'])) {
            flash('error', t('لازم تعلّم على مربع التأكيد إن الرسالة بتتحسب.'));
        } else {
            $r = ph_send_sms($z, $from, $to, $text);
            unset($_SESSION['sms_draft']);
            log_activity('sms.send', $from . ' -> ' . $to);
            flash('success', t('الرسالة اتبعتت. الرد هيظهر في صندوق الرسائل.'));
        }
    } catch (ZernioException $ex) {
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
if ($z) {
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
if ($z && !$apiError && !empty($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sms-opt-outs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['phone_number', 'opted_out_at', 'keyword', 'our_number']);
    foreach ($optOuts as $o) {
        fputcsv($out, [$o['phoneNumber'] ?? '', $o['optedOutAt'] ?? '', $o['keyword'] ?? '', $o['from'] ?? '']);
    }
    exit;
}

$draft = (array)($_SESSION['sms_draft'] ?? []);
unset($_SESSION['sms_draft']);

render(t('رسايل SMS'), 'sms', function () use ($z, $apiError, $numbers, $smsNumbers, $convs, $imsg, $regs, $optOuts, $rcs, $partial, $draft) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    foreach (array_unique($partial) as $p) { echo '<div class="mb-3">'; error_box($p); echo '</div>'; }
    if (!$numbers) { ph_empty_numbers(); return; }
    ?>
    <div class="grid lg:grid-cols-5 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="card p-5">
          <h2 class="font-bold mb-1"><?= e(t('ابعت SMS جديدة')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('كل رسالة بتتحسب على فاتورة Zernio حسب عدد الأجزاء (الجزء 160 حرف إنجليزي أو 70 حرف عربي) ودولة المستلم.')) ?></p>
          <?php if (!$smsNumbers): ?>
            <p class="text-sm text-amber-700"><?= e(t('مفيش رقم من أرقام الشركة شغال عليه SMS. فعّل الـ SMS على رقم من لوحة Zernio أو اشتري رقم بيبعت SMS.')) ?></p>
          <?php else: ?>
            <form method="post" class="space-y-3" onsubmit="return confirm(<?= tjs('الرسالة هتتبعت فعلاً وهتتحسب. نكمل؟') ?>)">
              <?= csrf_field() ?>
              <label class="block"><span class="label"><?= e(t('من رقم')) ?></span>
                <select name="from" class="input" dir="ltr">
                  <?php foreach ($smsNumbers as $n): ?>
                    <option value="<?= e($n['phoneNumber']) ?>"><?= e($n['phoneNumber'] . (empty($n['smsSendApproved']) ? ' (' . t('مستني تسجيل') . ')' : '')) ?></option>
                  <?php endforeach; ?>
                </select></label>
              <label class="block"><span class="label"><?= e(t('إلى رقم')) ?></span>
                <input name="to" required class="input" dir="ltr" placeholder="+14155550100" value="<?= e($draft['to'] ?? '') ?>"></label>
              <label class="block"><span class="label"><?= e(t('الرسالة')) ?></span>
                <textarea name="text" id="smsText" required maxlength="1530" rows="4" class="input"><?= e($draft['text'] ?? '') ?></textarea></label>
              <p class="text-xs text-ink-500" id="smsCount"></p>
              <label class="flex items-start gap-2 text-sm p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900"><input type="checkbox" name="confirm" value="1" required class="mt-1">
                <span><?= e(t('فاهم إن الرسالة بتتحسب، وإن المستلم وافق يستقبل رسايل مننا.')) ?></span></label>
              <button class="btn-primary"><?= e(t('ابعت')) ?></button>
            </form>
            <script>
            (function(){var t=document.getElementById('smsText'),o=document.getElementById('smsCount');
              var tpl=<?= json_encode(t('%s حرف · حوالي %s جزء'), JSON_UNESCAPED_UNICODE) ?>;
              function u(){var s=t.value,uni=/[^\x00-\x7F]/.test(s),n=s.length,seg=n===0?0:(uni?(n<=70?1:Math.ceil(n/67)):(n<=160?1:Math.ceil(n/153)));
                o.textContent=tpl.replace('%s',n).replace('%s',seg);}
              t.addEventListener('input',u);u();})();
            </script>
          <?php endif; ?>
        </div>

        <div class="card p-5">
          <h2 class="font-bold mb-2"><?= e(t('تسجيل الـ SMS عند شركات المحمول (10DLC)')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('عشان تبعت لأرقام أمريكية لازم الشركة والحملة يتسجلوا الأول. التسجيل بيتعمل من لوحة Zernio لأنه محتاج ورق ومراجعة.')) ?></p>
          <?php if (!$regs): ?><p class="text-sm text-ink-500"><?= e(t('مفيش تسجيلات لسه. لو هتبعت لأمريكا، ابدأ التسجيل من لوحة Zernio.')) ?></p><?php endif; ?>
          <?php foreach ($regs as $r): ?>
            <div class="border-t border-ink-100 pt-3 mt-3 text-sm">
              <div class="flex justify-between gap-2"><span class="font-medium"><?= e($r['displayName'] ?? $r['registrationType'] ?? '') ?></span><?= ph_reg_status_badge((string)($r['status'] ?? '')) ?></div>
              <div class="text-xs text-ink-500 mt-1" dir="ltr"><?= e(implode(', ', (array)($r['phoneNumbers'] ?? []))) ?></div>
              <?php
              $s = (string)($r['status'] ?? '');
              $msg = '';
              if (!empty($r['awaitingOtp'])) $msg = t('صاحب النشاط لازم يدخل كود التأكيد اللي وصله على الموبايل.');
              elseif ($s === 'changes_requested') $msg = t('المراجع طالب تعديلات: %s', (string)($r['adminReviewNote'] ?? ''));
              elseif ($s === 'rejected') $msg = t('اترفض: %s. تقدر تعمل طعن أو تعدل من لوحة Zernio.', (string)($r['declineReason'] ?? ''));
              elseif ($s === 'pending' || $s === 'requested') $msg = t('تحت المراجعة، بتاخد عادة من أيام لأسبوعين.');
              ?>
              <?php if ($msg): ?><p class="text-xs text-amber-700 mt-1"><?= e($msg) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="card p-5">
          <h2 class="font-bold mb-2"><?= e(t('الناس اللي لغت الاشتراك')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('أي حد يرد بكلمة STOP بيتشال من الإرسال تلقائياً، ولو حاولت تبعتله الرسالة هترفض. بيرجع بس لو رد بكلمة START.')) ?></p>
          <?php if (!$optOuts): ?><p class="text-sm text-ink-500"><?= e(t('محدش لغى الاشتراك.')) ?></p><?php else: ?>
            <a class="link text-sm" href="<?= e(url('sms', ['csv' => 1])) ?>"><?= e(t('نزّل القايمة (CSV)')) ?></a>
            <div class="max-h-60 overflow-y-auto mt-2">
              <?php foreach ($optOuts as $o): ?>
                <div class="text-sm flex justify-between gap-2 py-1 border-b border-ink-100 last:border-0">
                  <span dir="ltr" style="unicode-bidi:isolate"><?= e($o['phoneNumber'] ?? '') ?></span>
                  <span class="text-xs text-ink-500"><?= e(($o['keyword'] ?? '') . ' · ' . fmt_date($o['optedOutAt'] ?? null, 'd/m/Y')) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="lg:col-span-3 space-y-6">
        <div class="card">
          <div class="p-4 border-b border-ink-100 flex justify-between items-center">
            <h2 class="font-bold"><?= e(t('محادثات SMS و iMessage')) ?></h2>
            <a class="link text-sm" href="<?= e(url('inbox')) ?>"><?= e(t('افتح صندوق الرسائل')) ?></a>
          </div>
          <?php $all = array_merge($convs, $imsg); usort($all, function ($a, $b) { return strcmp((string)($b['updatedTime'] ?? ''), (string)($a['updatedTime'] ?? '')); }); ?>
          <?php if (!$all): ?><p class="p-6 text-sm text-ink-500"><?= e(t('مفيش محادثات لسه. الرسايل اللي بتبعتها والردود هتظهر هنا وفي صندوق الرسائل.')) ?></p><?php endif; ?>
          <?php foreach ($all as $c): ?>
            <a href="<?= e(url('inbox', ['c' => $c['id'] ?? '', 'a' => $c['accountId'] ?? ''])) ?>" class="block p-4 border-b border-ink-100 last:border-0 hover:bg-ink-50">
              <div class="flex justify-between gap-2">
                <span class="font-medium" dir="auto"><?= e($c['participantName'] ?? $c['participantUsername'] ?? '') ?></span>
                <span class="text-xs text-ink-500"><?= e(platform_label($c['platform'] ?? '') . ' · ' . fmt_date($c['updatedTime'] ?? null, 'd/m h:i A')) ?></span>
              </div>
              <p class="text-sm text-ink-500 truncate" dir="auto"><?= e($c['lastMessage'] ?? '') ?></p>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="card p-5">
          <h2 class="font-bold mb-2"><?= e(t('رسايل RCS (رسايل غنية على أندرويد)')) ?></h2>
          <?php if (!$rcs): ?>
            <p class="text-sm text-ink-500"><?= e(t('مفيش حساب RCS. ده محتاج طلب وموافقة من جوجل وشركات المحمول، وبيتعمل من لوحة Zernio.')) ?></p>
          <?php else: foreach ($rcs as $a): ?>
            <div class="text-sm flex justify-between gap-2 py-1"><span><?= e($a['displayName'] ?? $a['name'] ?? $a['_id'] ?? '') ?></span><span class="badge"><?= e((string)($a['status'] ?? $a['launchStatus'] ?? '')) ?></span></div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
    <?php
});
