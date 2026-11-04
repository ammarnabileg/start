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
$id = (string)($_GET['id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $bid = (string)($_POST['id'] ?? '');
    try {
        if ($action === 'create' && $acc) {
            $name = trim((string)($_POST['name'] ?? ''));
            [$tplName, $tplLang] = array_pad(explode('|', (string)($_POST['template'] ?? ''), 2), 2, '');
            $mode = (string)($_POST['mode'] ?? 'tags');
            $contactIds = array_values(array_filter(array_map('strval', (array)($_POST['contacts'] ?? [])), function ($v) { return preg_match('/^[a-f0-9]{24}$/i', $v); }));
            if ($name === '' || $tplName === '') {
                flash('error', t('اكتب اسم للحملة واختار قالب.'));
                redirect(url('broadcasts', ['new' => 1, 'wa' => $acc]));
            }
            if ($mode === 'contacts' && !$contactIds) {
                flash('error', t('اختار جهة اتصال واحدة على الأقل.'));
                redirect(url('broadcasts', ['new' => 1, 'wa' => $acc]));
            }
            $template = ['name' => $tplName, 'language' => $tplLang];
            $map = wa_mapping_from_post($_POST['map'] ?? []);
            if ($map) $template['variableMapping'] = $map;
            $payload = [
                'profileId' => current_profile_id($z), 'accountId' => $acc, 'platform' => 'whatsapp',
                'name' => $name, 'description' => trim((string)($_POST['description'] ?? '')), 'template' => $template,
            ];
            if ($mode === 'tags') {
                $seg = ['isSubscribed' => true];
                $tags = array_values(array_filter(array_map('strval', (array)($_POST['tags'] ?? []))));
                if ($tags) $seg['tags'] = $tags;
                $payload['segmentFilters'] = $seg;
            }
            $b = wa_create_broadcast($z, $payload);
            $newId = (string)($b['id'] ?? '');
            $r = wa_add_broadcast_recipients($z, $newId, $mode === 'tags' ? ['useSegment' => true] : ['contactIds' => $contactIds]);
            log_activity('broadcast.create', $name);
            flash('success', t('المسودة اتعملت واتضاف %s مستلم (اتساب %s). راجعها قبل الإرسال.', (int)($r['added'] ?? 0), (int)($r['skipped'] ?? 0)));
            redirect(url('broadcasts', ['id' => $newId, 'wa' => $acc]));
        } elseif ($action === 'send' || $action === 'schedule') {
            $b = wa_broadcast($z, $bid);
            $count = (int)($b['recipientCount'] ?? 0);
            if (!can('whatsapp.manage')) {
                flash('error', t('الإرسال بيكلف فلوس، فصاحب الشركة بس اللي يقدر يبعت أو يجدول.'));
            } elseif (($b['status'] ?? '') !== 'draft') {
                flash('error', t('الحملة دي مش مسودة.'));
            } elseif (empty($_POST['confirm']) || (int)($_POST['expected'] ?? -1) !== $count) {
                flash('error', t('لازم تعلّم على مربع التأكيد. (لو عدد المستلمين اتغير، راجعه تاني.)'));
            } elseif ($count < 1) {
                flash('error', t('مفيش مستلمين في الحملة دي.'));
            } elseif ($action === 'send') {
                $r = wa_broadcast_action($z, $bid, 'send');
                log_activity('broadcast.send', $bid . ' (' . $count . ')');
                flash('success', t('بدأ الإرسال: اتبعت %s وفشل %s من %s.', (int)($r['sent'] ?? 0), (int)($r['failed'] ?? 0), (int)($r['recipientCount'] ?? $count)));
            } else {
                $when = (string)($_POST['scheduledAt'] ?? '');
                try {
                    $dt = new DateTime($when, new DateTimeZone(site_timezone()));
                } catch (Exception $ex) {
                    $dt = null;
                }
                if (!$dt || $when === '' || $dt->getTimestamp() < time() + 60) {
                    flash('error', t('اختار وقت في المستقبل.'));
                } else {
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    wa_broadcast_action($z, $bid, 'schedule', ['scheduledAt' => $dt->format('Y-m-d\TH:i:s\Z')]);
                    log_activity('broadcast.schedule', $bid . ' ' . $when);
                    flash('success', t('الحملة اتجدولت.'));
                }
            }
        } elseif ($action === 'cancel') {
            wa_broadcast_action($z, $bid, 'cancel');
            log_activity('broadcast.cancel', $bid);
            flash('success', t('الحملة اتلغت.'));
        } elseif ($action === 'delete') {
            if (!can('whatsapp.manage')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } else {
                wa_delete_broadcast($z, $bid);
                log_activity('broadcast.delete', $bid);
                flash('success', t('الحملة اتمسحت.'));
                redirect(url('broadcasts', ['wa' => $acc]));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
        if ($action === 'create') redirect(url('broadcasts', ['new' => 1, 'wa' => $acc]));
    }
    redirect(url('broadcasts', ['id' => $bid ?: null, 'wa' => $acc]));
}

$status = (string)($_GET['status'] ?? '');
if (!in_array($status, ['', 'draft', 'scheduled', 'sending', 'completed', 'failed', 'cancelled'], true)) $status = '';
$showNew = !empty($_GET['new']);
$list = [];
$detail = null;
$recips = [];
$summary = [];
$templates = [];
$contacts = [];
$allTags = [];
if ($z && !$apiError) {
    try {
        if ($id) {
            $detail = wa_broadcast($z, $id);
            $rr = wa_broadcast_recipients($z, $id, 100);
            $recips = $rr['recipients'] ?? [];
            $summary = $rr['summary'] ?? [];
        } elseif ($showNew && $acc) {
            $templates = wa_templates($z, $acc, 'APPROVED');
            $cr = wa_contacts($z, ['limit' => 200, 'isSubscribed' => 'true', 'platform' => 'whatsapp']);
            $contacts = $cr['contacts'] ?? [];
            $allTags = $cr['filters']['tags'] ?? [];
        } else {
            $list = wa_broadcasts($z, ['status' => $status ?: null, 'limit' => 50])['broadcasts'] ?? [];
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

function wa_stat_bar(array $b): string
{
    $total = max(1, (int)($b['recipientCount'] ?? 0));
    $parts = [['readCount', 'bg-lime-500'], ['deliveredCount', 'bg-cyan-500'], ['sentCount', 'bg-brand-500'], ['failedCount', 'bg-red-500']];
    $h = '<div class="flex h-2 rounded-full overflow-hidden bg-ink-100 w-full">';
    // read ⊂ delivered ⊂ sent: نرسم الفرق بس
    $read = (int)($b['readCount'] ?? 0);
    $del = max(0, (int)($b['deliveredCount'] ?? 0) - $read);
    $sent = max(0, (int)($b['sentCount'] ?? 0) - $read - $del);
    $vals = [$read, $del, $sent, (int)($b['failedCount'] ?? 0)];
    foreach ($parts as $i => $p) {
        if ($vals[$i] > 0) $h .= '<div class="' . $p[1] . '" style="width:' . round(100 * $vals[$i] / $total, 2) . '%"></div>';
    }
    return $h . '</div>';
}

render(t('الرسائل الجماعية'), 'broadcasts', function () use ($z, $accounts, $acc, $apiError, $status, $showNew, $list, $detail, $recips, $summary, $templates, $contacts, $allTags, $id) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    if (!$acc) { wa_no_account_box(); return; }

    /* ---------- تفاصيل حملة ---------- */
    if ($id && $detail) {
        $b = $detail;
        $st = (string)($b['status'] ?? '');
        $count = (int)($b['recipientCount'] ?? 0);
        ?>
        <a href="<?= e(url('broadcasts', ['wa' => $acc])) ?>" class="link text-sm"><?= e(t('→ كل الحملات')) ?></a>
        <div class="card p-5 mt-3 mb-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold"><?= e($b['name'] ?? '') ?> <?= wa_broadcast_status_badge($st) ?></h2>
              <p class="text-sm text-ink-500"><?= e(t('القالب:')) ?> <span class="font-mono" dir="ltr"><?= e(($b['template']['name'] ?? '') . ' (' . ($b['template']['language'] ?? '') . ')') ?></span></p>
              <?php if (!empty($b['segmentFilters']['tags'])): ?><p class="text-sm text-ink-500"><?= e(t('التاجات:')) ?> <?= e(implode(', ', $b['segmentFilters']['tags'])) ?></p><?php endif; ?>
              <?php if (!empty($b['scheduledAt'])): ?><p class="text-sm text-ink-500"><?= e(t('ميعاد الإرسال: %s', fmt_date($b['scheduledAt'], 'd/m/Y h:i A'))) ?></p><?php endif; ?>
            </div>
            <div class="flex gap-2">
              <?php if (in_array($st, ['scheduled', 'sending'], true)): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('إلغاء الحملة؟ الرسايل اللي اتبعتت خلاص مش هترجع.') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn-ghost"><?= e(t('إلغاء الحملة')) ?></button></form>
              <?php endif; ?>
              <?php if (can('whatsapp.manage') && in_array($st, ['draft', 'cancelled', 'completed', 'failed'], true)): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('حذف الحملة دي؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn-ghost text-red-600"><?= e(t('حذف')) ?></button></form>
              <?php endif; ?>
            </div>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-4 text-center">
            <?php foreach (['recipientCount' => t('المستلمين'), 'sentCount' => t('اتبعت'), 'deliveredCount' => t('وصل'), 'readCount' => t('اتقرا'), 'failedCount' => t('فشل')] as $k => $l): ?>
              <div class="rounded-xl bg-ink-50 border border-ink-200 p-3"><div class="text-xl font-semibold"><?= (int)($b[$k] ?? 0) ?></div><div class="text-xs text-ink-500"><?= e($l) ?></div></div>
            <?php endforeach; ?>
          </div>
          <div class="mt-3"><?= wa_stat_bar($b) ?></div>
        </div>

        <?php if ($st === 'draft'): ?>
        <form method="post" class="card p-5 mb-4 border-2 !border-amber-300 space-y-4">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="expected" value="<?= $count ?>">
          <h3 class="font-semibold text-lg"><?= e(t('الإرسال')) ?></h3>
          <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 p-4">
            <p class="text-2xl font-bold"><?= e(t('%s شخص هيستلموا الرسالة دي', $count)) ?></p>
            <p class="text-sm mt-1"><?= e(t('كل رسالة قالب على واتساب بتتحاسب عليها من Meta حسب نوع القالب وبلد المستلم. بعد الإرسال مفيش رجوع: الرسايل مش هينفع تتسحب.')) ?></p>
          </div>
          <?php if ($count < 1): ?>
            <p class="text-sm text-red-600"><?= e(t('مفيش مستلمين. احذف المسودة واعمل واحدة جديدة باختيار تاني.')) ?></p>
          <?php else: ?>
          <label class="flex items-start gap-2 text-sm font-medium"><input type="checkbox" name="confirm" value="1" required class="mt-1">
            <span><?= e(t('أنا متأكد إني عايز أبعت الرسالة دي لـ %s شخص، وفاهم إن ده بيتكلف فلوس ومش هينفع يتلغي بعد الإرسال.', $count)) ?></span></label>
          <div class="flex flex-wrap items-end gap-3">
            <button name="action" value="send" class="bg-red-600 text-white font-semibold rounded-xl px-5 py-2.5 text-sm hover:brightness-110" onclick="return confirm('<?= tjs('آخر تأكيد: إرسال لـ %s شخص دلوقتي؟', $count) ?>')"><?= e(t('ابعت دلوقتي لـ %s شخص', $count)) ?></button>
            <span class="text-ink-400 text-sm"><?= e(t('أو')) ?></span>
            <label class="block"><span class="label"><?= e(t('جدولة (بتوقيت الشركة)')) ?></span><input type="datetime-local" name="scheduledAt" class="input"></label>
            <button name="action" value="schedule" class="btn-ghost"><?= e(t('جدولة')) ?></button>
          </div>
          <?php endif; ?>
        </form>
        <?php endif; ?>

        <div class="card overflow-x-auto">
          <div class="p-4 border-b border-ink-200 flex flex-wrap gap-3 text-sm">
            <span class="font-semibold"><?= e(t('المستلمين')) ?></span>
            <?php foreach (['pending' => t('مستني'), 'sent' => t('اتبعت'), 'delivered' => t('وصل'), 'read' => t('اتقرا'), 'failed' => t('فشل')] as $k => $l): ?>
              <span class="text-ink-500"><?= e($l) ?>: <?= (int)($summary[$k] ?? 0) ?></span>
            <?php endforeach; ?>
          </div>
          <?php if (!$recips): ?><p class="p-5 text-sm text-ink-500"><?= e(t('مفيش مستلمين.')) ?></p><?php else: ?>
          <table class="w-full text-sm"><tbody>
            <?php foreach ($recips as $r): ?>
              <tr class="border-b border-ink-100 last:border-0">
                <td class="p-3"><?= e($r['contactName'] ?? '') ?></td>
                <td class="p-3 text-xs" dir="ltr"><?= e($r['platformIdentifier'] ?? '') ?></td>
                <td class="p-3"><span class="badge <?= ($r['status'] ?? '') === 'failed' ? 'bg-red-100 text-red-700' : (in_array($r['status'] ?? '', ['delivered', 'read'], true) ? 'badge-green' : '') ?>"><?= e($r['status'] ?? '') ?></span></td>
                <td class="p-3 text-xs text-red-600"><?= e($r['errorExplanation'] ?? $r['error'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody></table>
          <?php endif; ?>
        </div>
        <?php
        return;
    }

    wa_account_picker($accounts, $acc, 'broadcasts');

    /* ---------- حملة جديدة ---------- */
    if ($showNew) {
        ?>
        <form method="post" class="card p-5 space-y-5">
          <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="wa" value="<?= e($acc) ?>">
          <div class="flex justify-between"><h2 class="font-semibold text-lg"><?= e(t('حملة جديدة (خطوة 1 من 2: مسودة)')) ?></h2><a class="text-sm text-ink-500" href="<?= e(url('broadcasts', ['wa' => $acc])) ?>"><?= e(t('إلغاء')) ?></a></div>
          <p class="text-sm text-ink-500"><?= e(t('هنعمل مسودة الأول ومش هيتبعت أي حاجة. في الخطوة الجاية هتشوف عدد المستلمين بالظبط وتأكد الإرسال أو تجدوله.')) ?></p>
          <div class="grid sm:grid-cols-2 gap-4">
            <label class="block"><span class="label"><?= e(t('اسم الحملة')) ?></span><input name="name" required class="input"></label>
            <label class="block"><span class="label"><?= e(t('وصف (اختياري)')) ?></span><input name="description" class="input"></label>
          </div>
          <div data-wa-step>
            <label class="block"><span class="label"><?= e(t('القالب (المقبولة بس)')) ?></span>
              <select name="template" required class="input wa-tpl"><?= wa_template_options($templates) ?></select></label>
            <?php if (!$templates): ?><p class="text-sm text-amber-700 mt-1"><?= e(t('مفيش قوالب مقبولة لسه.')) ?> <a class="link" href="<?= e(url('wa_templates', ['wa' => $acc, 'new' => 1])) ?>"><?= e(t('اعمل قالب')) ?></a></p><?php endif; ?>
            <p class="text-xs text-ink-500 mt-2"><?= e(t('املى كل متغير في القالب من بيانات جهة الاتصال أو بقيمة ثابتة:')) ?></p>
            <?= wa_mapping_fields('map') ?>
          </div>
          <fieldset class="space-y-3">
            <legend class="label mb-2"><?= e(t('المستلمين')) ?></legend>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="tags" checked onchange="waMode()"> <?= e(t('حسب التاجات (المشتركين بس)')) ?></label>
            <div id="modeTags" class="ps-6 flex flex-wrap gap-2">
              <?php foreach ($allTags as $t): ?><label class="badge gap-1"><input type="checkbox" name="tags[]" value="<?= e($t) ?>"> <?= e($t) ?></label><?php endforeach; ?>
              <p class="text-xs text-ink-500 w-full"><?= e(t('لو مختارتش تاج، الرسالة هتروح لكل جهات الاتصال المشتركة.')) ?></p>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="contacts" onchange="waMode()"> <?= e(t('اختار جهات اتصال بعينها')) ?></label>
            <div id="modeContacts" class="ps-6 hidden max-h-72 overflow-y-auto border border-ink-200 rounded-xl p-3 space-y-1">
              <?php if (!$contacts): ?><p class="text-sm text-ink-500"><?= e(t('مفيش جهات اتصال مشتركة على واتساب.')) ?></p><?php endif; ?>
              <?php foreach ($contacts as $c): ?>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="contacts[]" value="<?= e($c['id']) ?>"> <?= e($c['name'] ?? '') ?> <span class="text-xs text-ink-500" dir="ltr"><?= e($c['displayIdentifier'] ?? $c['platformIdentifier'] ?? '') ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <button class="btn-primary"><?= e(t('اعمل المسودة وراجع المستلمين')) ?></button>
        </form>
        <script>function waMode(){var c=document.querySelector('input[name=mode]:checked').value;document.getElementById('modeTags').classList.toggle('hidden',c!=='tags');document.getElementById('modeContacts').classList.toggle('hidden',c!=='contacts');}</script>
        <?= wa_mapping_script() ?>
        <?php
        return;
    }

    /* ---------- القائمة ---------- */
    ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div class="seg">
        <?php foreach (['' => t('الكل'), 'draft' => t('مسودات'), 'scheduled' => t('مجدول'), 'completed' => t('اتبعت'), 'cancelled' => t('اتلغى')] as $k => $l): ?>
          <a class="<?= $status === $k ? 'on' : '' ?>" href="<?= e(url('broadcasts', ['status' => $k, 'wa' => $acc])) ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
      </div>
      <a href="<?= e(url('broadcasts', ['new' => 1, 'wa' => $acc])) ?>" class="btn-primary"><?= e(t('+ حملة جديدة')) ?></a>
    </div>
    <div class="card">
      <?php if (!$list): ?><p class="p-6 text-sm text-ink-500"><?= e(t('مفيش حملات.')) ?></p><?php endif; ?>
      <?php foreach ($list as $b): ?>
        <a href="<?= e(url('broadcasts', ['id' => $b['id'], 'wa' => $acc])) ?>" class="block p-4 border-b border-ink-100 last:border-0 hover:bg-ink-50">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
              <div class="font-medium"><?= e($b['name'] ?? '') ?> <?= wa_broadcast_status_badge((string)($b['status'] ?? '')) ?></div>
              <div class="text-xs text-ink-500"><?= e($b['accountName'] ?? '') ?> · <span dir="ltr"><?= e($b['messagePreview'] ?? '') ?></span> · <?= e(fmt_date($b['scheduledAt'] ?? $b['createdAt'] ?? null, 'd/m/Y h:i A')) ?></div>
            </div>
            <div class="text-xs text-ink-500 text-end"><?= e(t('%s مستلم · وصل %s · اتقرا %s · فشل %s', (int)($b['recipientCount'] ?? 0), (int)($b['deliveredCount'] ?? 0), (int)($b['readCount'] ?? 0), (int)($b['failedCount'] ?? 0))) ?></div>
          </div>
          <div class="mt-2"><?= wa_stat_bar($b) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
});
