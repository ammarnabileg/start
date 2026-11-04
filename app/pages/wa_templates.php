<?php
$err = null;
$z = zernio_or_null($err);
$accounts = [];
$acc = null;
$apiError = null;
if ($z) {
    try {
        $accounts = wa_accounts($z);
        $acc = wa_pick_account($accounts);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $acc && wa_is_sandbox($accounts, $acc)) {
    flash('error', t('مينفعش تعدل على الرقم التجريبي.'));
    redirect(url('wa_templates', ['wa' => $acc]));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $acc) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            if (!can('whatsapp.manage')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } else {
                $name = (string)($_POST['name'] ?? '');
                $lang = (string)($_POST['language'] ?? '');
                wa_delete_template($z, $acc, $name, $lang ?: null);
                log_activity('wa_template.delete', $name . ' ' . $lang);
                flash('success', t('تم حذف القالب.'));
            }
        } elseif ($action === 'create') {
            $name = strtolower(trim((string)($_POST['name'] ?? '')));
            $category = strtoupper((string)($_POST['category'] ?? ''));
            $language = trim((string)($_POST['language'] ?? ''));
            $header = trim((string)($_POST['header'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            $footer = trim((string)($_POST['footer'] ?? ''));
            $_SESSION['wa_tpl_form'] = $_POST;
            $errors = [];
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) $errors[] = t('اسم القالب لازم يبدأ بحرف إنجليزي صغير وفيه حروف صغيرة وأرقام و _ بس.');
            if (!in_array($category, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)) $errors[] = t('اختار نوع القالب.');
            if (!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $language)) $errors[] = t('كود اللغة مش صحيح (مثال: ar أو en_US).');
            if ($body === '') $errors[] = t('نص الرسالة مطلوب.');
            if (mb_strlen($body) > 1024) $errors[] = t('نص الرسالة أطول من 1024 حرف.');
            if (mb_strlen($header) > 60) $errors[] = t('العنوان أطول من 60 حرف.');
            if (mb_strlen($footer) > 60) $errors[] = t('التذييل أطول من 60 حرف.');

            $components = [];
            if ($header !== '') {
                $h = ['type' => 'header', 'format' => 'text', 'text' => $header];
                $hv = wa_vars_in($header);
                if (count($hv) > 1) $errors[] = t('العنوان يقبل متغير واحد بس.');
                if ($hv) {
                    $hs = trim((string)($_POST['header_sample'] ?? ''));
                    if ($hs === '') $errors[] = t('اكتب مثال لمتغير العنوان.');
                    $h['example'] = ['header_text' => [$hs]];
                }
                $components[] = $h;
            }
            $b = ['type' => 'body', 'text' => $body];
            $bv = wa_vars_in($body);
            if ($bv) {
                foreach ($bv as $k) {
                    if (!ctype_digit($k)) { $errors[] = t('استخدم متغيرات بأرقام بس زي {{1}} و {{2}}.'); break; }
                }
                $samples = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['body_samples'] ?? ''))), 'strlen'));
                if (count($samples) < count($bv)) {
                    $errors[] = t('الرسالة فيها %s متغير، اكتب مثال لكل واحد (سطر لكل متغير).', count($bv));
                }
                $b['example'] = ['body_text' => [array_slice($samples, 0, count($bv))]];
            }
            $components[] = $b;
            if ($footer !== '') $components[] = ['type' => 'footer', 'text' => $footer];

            $buttons = [];
            foreach ((array)($_POST['btn'] ?? []) as $btn) {
                $type = (string)($btn['type'] ?? '');
                $text = trim((string)($btn['text'] ?? ''));
                $val = trim((string)($btn['value'] ?? ''));
                if ($type === '' || $text === '') continue;
                if ($type === 'quick_reply') {
                    $buttons[] = ['type' => 'quick_reply', 'text' => $text];
                } elseif ($type === 'url') {
                    if (!preg_match('~^https://~i', $val)) { $errors[] = t('رابط الزرار لازم يبدأ بـ https://'); continue; }
                    $buttons[] = ['type' => 'url', 'text' => $text, 'url' => $val];
                } elseif ($type === 'phone_number') {
                    if (!preg_match('/^\+?[0-9]{8,15}$/', $val)) { $errors[] = t('رقم زرار الاتصال مش صحيح (مثال: %s).', cx_phone()); continue; }
                    $buttons[] = ['type' => 'phone_number', 'text' => $text, 'phone_number' => $val];
                }
            }
            if ($buttons) $components[] = ['type' => 'buttons', 'buttons' => $buttons];

            if ($errors) {
                flash('error', implode(' ', $errors));
                redirect(url('wa_templates', ['wa' => $acc, 'new' => 1]));
            }
            $payload = ['accountId' => $acc, 'name' => $name, 'category' => $category, 'language' => $language, 'components' => $components];
            $r = wa_create_template($z, $payload);
            unset($_SESSION['wa_tpl_form']);
            log_activity('wa_template.create', $name);
            flash('success', t('القالب اتبعت لـ Meta للمراجعة. حالته دلوقتي: %s', (string)($r['template']['status'] ?? 'PENDING')));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
        if ($action === 'create') redirect(url('wa_templates', ['wa' => $acc, 'new' => 1]));
    }
    redirect(url('wa_templates', ['wa' => $acc]));
}

$status = strtoupper((string)($_GET['status'] ?? ''));
if (!in_array($status, ['', 'APPROVED', 'PENDING', 'REJECTED'], true)) $status = '';
$templates = [];
if ($z && $acc && !$apiError) {
    try {
        $templates = wa_templates($z, $acc, $status ?: null);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}
$showNew = !empty($_GET['new']);
$old = $showNew ? (array)($_SESSION['wa_tpl_form'] ?? []) : [];

render(t('قوالب واتساب'), 'wa_templates', function () use ($z, $accounts, $acc, $templates, $apiError, $status, $showNew, $old) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    if (!$acc) { wa_no_account_box(); return; }
    wa_account_picker($accounts, $acc, 'wa_templates');
    $sb = wa_is_sandbox($accounts, $acc);
    if ($sb) { wa_sandbox_note(); $showNew = false; }
    $o = function ($k, $d = '') use ($old) { return (string)($old[$k] ?? $d); };
    ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div class="seg">
        <?php foreach (['' => t('الكل'), 'APPROVED' => t('مقبول'), 'PENDING' => t('مستني موافقة'), 'REJECTED' => t('مرفوض')] as $k => $l): ?>
          <a class="<?= $status === $k ? 'on' : '' ?>" href="<?= e(url('wa_templates', ['wa' => $acc, 'status' => $k])) ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
      </div>
      <?php if (!$sb): ?><a href="<?= e(url('wa_templates', ['wa' => $acc, 'new' => 1])) ?>" class="btn-primary"><?= e(t('+ قالب جديد')) ?></a><?php endif; ?>
    </div>

    <?php if ($showNew): ?>
    <form method="post" class="card p-5 mb-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create"><input type="hidden" name="wa" value="<?= e($acc) ?>">
      <div class="flex items-start justify-between gap-3">
        <h2 class="font-semibold text-lg"><?= e(t('قالب جديد')) ?></h2>
        <a href="<?= e(url('wa_templates', ['wa' => $acc])) ?>" class="text-sm text-ink-500"><?= e(t('إلغاء')) ?></a>
      </div>
      <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-3 text-sm">
        <?= e(t('القالب لازم Meta توافق عليه قبل ما تقدر تستخدمه. المراجعة بتاخد من دقايق لحد 24 ساعة، وممكن يترفض لو المحتوى مخالف لسياسات واتساب.')) ?>
      </div>
      <div class="grid sm:grid-cols-3 gap-4">
        <label class="block"><span class="label"><?= e(t('اسم القالب')) ?></span>
          <input name="name" required pattern="[a-z][a-z0-9_]*" dir="ltr" class="input font-mono" value="<?= e($o('name')) ?>" placeholder="order_update"></label>
        <label class="block"><span class="label"><?= e(t('النوع')) ?></span>
          <select name="category" class="input">
            <?php foreach (['MARKETING', 'UTILITY', 'AUTHENTICATION'] as $c): ?><option value="<?= $c ?>" <?= $o('category', 'MARKETING') === $c ? 'selected' : '' ?>><?= e(wa_category_label($c)) ?></option><?php endforeach; ?>
          </select></label>
        <label class="block"><span class="label"><?= e(t('اللغة')) ?></span>
          <input name="language" required dir="ltr" list="wa-langs" class="input font-mono" value="<?= e($o('language', 'ar')) ?>">
          <datalist id="wa-langs"><option value="ar"><option value="en"><option value="en_US"><option value="en_GB"><option value="fr"><option value="de"><option value="es"></datalist></label>
      </div>
      <p class="text-xs text-ink-500"><?= e(t('اكتب المتغيرات بالشكل {{1}} و {{2}}... وهتتملى لكل مستلم وقت الإرسال.')) ?></p>
      <div class="grid sm:grid-cols-2 gap-4">
        <label class="block"><span class="label"><?= e(t('العنوان (اختياري، نص)')) ?></span>
          <input name="header" maxlength="60" class="input" value="<?= e($o('header')) ?>"></label>
        <label class="block"><span class="label"><?= e(t('مثال لمتغير العنوان (لو فيه)')) ?></span>
          <input name="header_sample" class="input" value="<?= e($o('header_sample')) ?>"></label>
      </div>
      <label class="block"><span class="label"><?= e(t('نص الرسالة')) ?></span>
        <textarea data-ai="wa_template,improve" name="body" required maxlength="1024" rows="5" class="input" placeholder="<?= e(t('أهلاً {{1}}، طلبك رقم {{2}} اتشحن.')) ?>"><?= e($o('body')) ?></textarea></label>
      <label class="block"><span class="label"><?= e(t('أمثلة للمتغيرات (سطر لكل متغير بالترتيب)')) ?></span>
        <textarea name="body_samples" rows="2" class="input" placeholder="<?= e(t("أحمد\n12345")) ?>"><?= e($o('body_samples')) ?></textarea></label>
      <label class="block"><span class="label"><?= e(t('التذييل (اختياري)')) ?></span>
        <input name="footer" maxlength="60" class="input" value="<?= e($o('footer')) ?>"></label>
      <div>
        <span class="label"><?= e(t('الأزرار (اختياري، لحد 3)')) ?></span>
        <?php for ($i = 0; $i < 3; $i++): $ob = (array)($old['btn'][$i] ?? []); ?>
          <div class="grid sm:grid-cols-3 gap-2 mt-2">
            <select name="btn[<?= $i ?>][type]" class="input !mt-0">
              <option value=""><?= e(t('— بدون —')) ?></option>
              <?php foreach (['quick_reply' => t('رد سريع'), 'url' => t('رابط'), 'phone_number' => t('اتصال')] as $k => $l): ?>
                <option value="<?= $k ?>" <?= ($ob['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
              <?php endforeach; ?>
            </select>
            <input name="btn[<?= $i ?>][text]" maxlength="25" class="input !mt-0" placeholder="<?= e(t('نص الزرار')) ?>" value="<?= e($ob['text'] ?? '') ?>">
            <input name="btn[<?= $i ?>][value]" dir="ltr" class="input !mt-0" placeholder="<?= e(t('الرابط أو الرقم')) ?>" value="<?= e($ob['value'] ?? '') ?>">
          </div>
        <?php endfor; ?>
      </div>
      <button class="btn-primary"><?= e(t('ابعت القالب للمراجعة')) ?></button>
    </form>
    <?php endif; ?>

    <?php if (!$templates): ?>
      <div class="card p-6 text-sm text-ink-500"><?= e(t('مفيش قوالب هنا.')) ?></div>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
      <?php foreach ($templates as $tpl):
        $h = wa_component($tpl, 'header'); $b = wa_component($tpl, 'body'); $f = wa_component($tpl, 'footer'); $bt = wa_component($tpl, 'buttons'); ?>
        <div class="card p-4 flex flex-col">
          <div class="flex items-start justify-between gap-2 mb-2">
            <div class="min-w-0">
              <div class="font-mono font-semibold truncate" dir="ltr"><?= e($tpl['name'] ?? '') ?></div>
              <div class="text-xs text-ink-500"><?= e(wa_category_label((string)($tpl['category'] ?? ''))) ?> · <span dir="ltr"><?= e($tpl['language'] ?? '') ?></span></div>
            </div>
            <?= wa_template_status_badge((string)($tpl['status'] ?? '')) ?>
          </div>
          <div class="rounded-xl bg-lime-50 border border-lime-200 p-3 text-sm flex-1">
            <?php if ($h && !empty($h['text'])): ?><p class="font-semibold mb-1"><?= e($h['text']) ?></p>
            <?php elseif ($h): ?><p class="text-xs text-ink-500 mb-1">[<?= e(strtolower((string)($h['format'] ?? ''))) ?>]</p><?php endif; ?>
            <p class="whitespace-pre-line break-words"><?= e($b['text'] ?? '') ?></p>
            <?php if ($f && !empty($f['text'])): ?><p class="text-xs text-ink-500 mt-2"><?= e($f['text']) ?></p><?php endif; ?>
            <?php if ($bt): ?><div class="flex flex-wrap gap-1 mt-2"><?php foreach ($bt['buttons'] ?? [] as $x): ?><span class="badge bg-white border border-ink-200"><?= e($x['text'] ?? ($x['type'] ?? '')) ?></span><?php endforeach; ?></div><?php endif; ?>
          </div>
          <?php if (can('whatsapp.manage') && !$sb): ?>
          <form method="post" class="mt-3 text-end" onsubmit="return confirm('<?= tjs('حذف القالب ده نهائياً من واتساب؟ مش هينفع ترجعه، ولازم تستنى قبل ما تستخدم نفس الاسم تاني.') ?>')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="wa" value="<?= e($acc) ?>">
            <input type="hidden" name="name" value="<?= e($tpl['name'] ?? '') ?>"><input type="hidden" name="language" value="<?= e($tpl['language'] ?? '') ?>">
            <button class="text-sm text-red-600"><?= e(t('حذف')) ?></button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
});
