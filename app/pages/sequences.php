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
const WA_SEQ_MAX_STEPS = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $sid = (string)($_POST['id'] ?? '');
    try {
        if ($action === 'create' && $acc) {
            $name = trim((string)($_POST['name'] ?? ''));
            $steps = [];
            foreach ((array)($_POST['steps'] ?? []) as $s) {
                [$tn, $tl] = array_pad(explode('|', (string)($s['template'] ?? ''), 2), 2, '');
                if ($tn === '') continue;
                $unit = ['m' => 1, 'h' => 60, 'd' => 1440][(string)($s['unit'] ?? 'd')] ?? 1440;
                $tpl = ['name' => $tn, 'language' => $tl];
                $map = wa_mapping_from_post($s['map'] ?? []);
                if ($map) $tpl['variableMapping'] = $map;
                $steps[] = ['order' => count($steps) + 1, 'delayMinutes' => max(0, (int)($s['delay'] ?? 0)) * $unit, 'template' => $tpl];
            }
            if ($name === '' || !$steps) {
                flash('error', t('اكتب اسم للسلسلة وضيف خطوة واحدة على الأقل.'));
                redirect(url('sequences', ['new' => 1, 'wa' => $acc]));
            }
            $seq = wa_create_sequence($z, [
                'profileId' => current_profile_id($z), 'accountId' => $acc, 'platform' => 'whatsapp', 'name' => $name,
                'description' => trim((string)($_POST['description'] ?? '')), 'steps' => $steps,
                'exitOnReply' => !empty($_POST['exitOnReply']), 'exitOnUnsubscribe' => true,
            ]);
            log_activity('sequence.create', $name);
            flash('success', t('السلسلة اتعملت كمسودة. فعّلها لما تكون جاهز.'));
            redirect(url('sequences', ['id' => $seq['id'] ?? null, 'wa' => $acc]));
        } elseif ($action === 'activate' || $action === 'pause') {
            wa_sequence_action($z, $sid, $action);
            log_activity('sequence.' . $action, $sid);
            flash('success', $action === 'activate' ? t('السلسلة اتفعلت.') : t('السلسلة اتوقفت مؤقتاً.'));
        } elseif ($action === 'enroll') {
            $ids = array_values(array_filter(array_map('strval', (array)($_POST['contacts'] ?? []))));
            if (!$ids) {
                flash('error', t('اختار جهة اتصال واحدة على الأقل.'));
            } else {
                $r = wa_enroll($z, $sid, $ids);
                log_activity('sequence.enroll', $sid . ' (' . count($ids) . ')');
                flash('success', t('اتسجل %s، وفشل %s (مسجلين قبل كده أو مش مشتركين).', (int)($r['enrolled'] ?? 0), (int)($r['failed'] ?? 0)));
            }
        } elseif ($action === 'unenroll') {
            wa_unenroll($z, $sid, (string)($_POST['contact'] ?? ''));
            log_activity('sequence.unenroll', $sid);
            flash('success', t('اتشال من السلسلة.'));
        } elseif ($action === 'delete') {
            if (!can('whatsapp.manage')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } else {
                wa_delete_sequence($z, $sid);
                log_activity('sequence.delete', $sid);
                flash('success', t('السلسلة اتمسحت.'));
                redirect(url('sequences', ['wa' => $acc]));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
        if ($action === 'create') redirect(url('sequences', ['new' => 1, 'wa' => $acc]));
    }
    redirect(url('sequences', ['id' => $sid ?: null, 'wa' => $acc]));
}

$showNew = !empty($_GET['new']);
$list = [];
$seq = null;
$enrollments = [];
$contacts = [];
$templates = [];
if ($z && !$apiError) {
    try {
        if ($id) {
            $seq = wa_sequence($z, $id);
            $enrollments = wa_enrollments($z, $id, 100);
            $contacts = wa_contacts($z, ['limit' => 200, 'isSubscribed' => 'true', 'platform' => 'whatsapp'])['contacts'] ?? [];
        } elseif ($showNew && $acc) {
            $templates = wa_templates($z, $acc, 'APPROVED');
        } else {
            $list = wa_sequences($z, ['limit' => 50])['sequences'] ?? [];
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

function wa_seq_badge(string $s): string
{
    $m = ['draft' => [t('مسودة'), 'badge'], 'active' => [t('شغالة'), 'badge badge-green'], 'paused' => [t('متوقفة'), 'badge bg-amber-100 text-amber-800']];
    [$l, $c] = $m[$s] ?? [$s, 'badge'];
    return '<span class="' . $c . '">' . e($l) . '</span>';
}

render(t('السلاسل التلقائية'), 'sequences', function () use ($z, $accounts, $acc, $apiError, $id, $showNew, $list, $seq, $enrollments, $contacts, $templates) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    if (!$acc) { wa_no_account_box(); return; }

    if ($id && $seq) {
        $st = (string)($seq['status'] ?? '');
        $enrolledIds = array_column($enrollments, 'contactId');
        ?>
        <a href="<?= e(url('sequences', ['wa' => $acc])) ?>" class="link text-sm"><?= e(t('→ كل السلاسل')) ?></a>
        <div class="card p-5 mt-3 mb-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold"><?= e($seq['name'] ?? '') ?> <?= wa_seq_badge($st) ?></h2>
              <?php if (!empty($seq['description'])): ?><p class="text-sm text-ink-500"><?= e($seq['description']) ?></p><?php endif; ?>
              <p class="text-xs text-ink-500 mt-1"><?= e(t('مسجلين %s · خلصوا %s · خرجوا %s', (int)($seq['totalEnrolled'] ?? 0), (int)($seq['totalCompleted'] ?? 0), (int)($seq['totalExited'] ?? 0))) ?>
                <?php if (!empty($seq['exitOnReply'])): ?> · <?= e(t('بيخرج لو رد')) ?><?php endif; ?></p>
            </div>
            <div class="flex gap-2">
              <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>">
                <?php if ($st === 'active'): ?><button name="action" value="pause" class="btn-ghost"><?= e(t('إيقاف مؤقت')) ?></button>
                <?php else: ?><button name="action" value="activate" class="btn-primary" onclick="return confirm('<?= tjs('تفعيل السلسلة؟ الرسايل هتبدأ تتبعت للمسجلين حسب المواعيد، وكل رسالة بتتحاسب.') ?>')"><?= e(t('تفعيل')) ?></button><?php endif; ?>
              </form>
              <?php if (can('whatsapp.manage')): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('حذف السلسلة دي نهائياً؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn-ghost text-red-600"><?= e(t('حذف')) ?></button></form>
              <?php endif; ?>
            </div>
          </div>
          <ol class="mt-4 space-y-2">
            <?php foreach ($seq['steps'] ?? [] as $i => $s): ?>
              <li class="flex gap-3 items-start">
                <span class="w-7 h-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-semibold shrink-0"><?= $i + 1 ?></span>
                <div class="text-sm">
                  <div class="font-medium"><?= e(wa_delay_label((int)($s['delayMinutes'] ?? 0))) ?></div>
                  <div class="text-ink-500"><span class="font-mono" dir="ltr"><?= e($s['template']['name'] ?? '') ?></span><?= e($s['message']['text'] ?? '') ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
          <div class="card">
            <div class="p-4 border-b border-ink-200 font-semibold"><?= e(t('المسجلين')) ?></div>
            <?php if (!$enrollments): ?><p class="p-4 text-sm text-ink-500"><?= e(t('مفيش حد مسجل.')) ?></p><?php endif; ?>
            <?php foreach ($enrollments as $en): ?>
              <div class="p-3 border-b border-ink-100 last:border-0 flex items-center justify-between gap-2 text-sm">
                <div><div class="font-medium"><?= e($en['contactName'] ?? $en['platformIdentifier'] ?? '') ?></div>
                  <div class="text-xs text-ink-500"><?= e($en['status'] ?? '') ?> · <?= e(t('اتبعت %s خطوة', (int)($en['stepsSent'] ?? 0))) ?><?php if (!empty($en['nextStepAt'])): ?> · <?= e(t('الجاية: %s', fmt_date($en['nextStepAt'], 'd/m h:i A'))) ?><?php endif; ?></div></div>
                <?php if (($en['status'] ?? '') === 'active' || ($en['status'] ?? '') === 'paused'): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('شيل جهة الاتصال دي من السلسلة؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="unenroll"><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="contact" value="<?= e($en['contactId'] ?? '') ?>"><button class="text-xs text-red-600"><?= e(t('شيله')) ?></button></form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <form method="post" class="card p-4 space-y-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="enroll"><input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="font-semibold"><?= e(t('سجّل جهات اتصال')) ?></div>
            <div class="max-h-72 overflow-y-auto space-y-1">
              <?php $any = false; foreach ($contacts as $c): if (in_array($c['id'], $enrolledIds, true)) continue; $any = true; ?>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="contacts[]" value="<?= e($c['id']) ?>"> <?= e($c['name'] ?? '') ?> <span class="text-xs text-ink-500" dir="ltr"><?= e($c['displayIdentifier'] ?? $c['platformIdentifier'] ?? '') ?></span></label>
              <?php endforeach; ?>
              <?php if (!$any): ?><p class="text-sm text-ink-500"><?= e(t('مفيش جهات اتصال مشتركة متاحة.')) ?></p><?php endif; ?>
            </div>
            <?php if ($st !== 'active'): ?><p class="text-xs text-amber-700"><?= e(t('السلسلة مش مفعلة، الرسايل مش هتتبعت لحد ما تفعلها.')) ?></p><?php endif; ?>
            <button class="btn-primary"><?= e(t('تسجيل')) ?></button>
          </form>
        </div>
        <?php
        return;
    }

    wa_account_picker($accounts, $acc, 'sequences');

    if ($showNew) {
        $opts = wa_template_options($templates);
        ?>
        <form method="post" class="card p-5 space-y-4">
          <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="wa" value="<?= e($acc) ?>">
          <div class="flex justify-between"><h2 class="font-semibold text-lg"><?= e(t('سلسلة جديدة')) ?></h2><a class="text-sm text-ink-500" href="<?= e(url('sequences', ['wa' => $acc])) ?>"><?= e(t('إلغاء')) ?></a></div>
          <div class="grid sm:grid-cols-2 gap-4">
            <label class="block"><span class="label"><?= e(t('الاسم')) ?></span><input name="name" required class="input"></label>
            <label class="block"><span class="label"><?= e(t('وصف (اختياري)')) ?></span><input name="description" class="input"></label>
          </div>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="exitOnReply" value="1" checked> <?= e(t('وقف السلسلة للشخص لو رد على أي رسالة')) ?></label>
          <?php if (!$templates): ?><p class="text-sm text-amber-700"><?= e(t('مفيش قوالب مقبولة لسه.')) ?> <a class="link" href="<?= e(url('wa_templates', ['wa' => $acc, 'new' => 1])) ?>"><?= e(t('اعمل قالب')) ?></a></p><?php endif; ?>
          <p class="text-xs text-ink-500"><?= e(t('كل خطوة رسالة قالب. التأخير بيتحسب من الخطوة اللي قبلها (الخطوة الأولى من وقت التسجيل). سيب القالب فاضي لو مش محتاج الخطوة.')) ?></p>
          <?php for ($i = 0; $i < WA_SEQ_MAX_STEPS; $i++): ?>
            <div class="rounded-xl border border-ink-200 p-3" data-wa-step>
              <div class="text-sm font-semibold mb-2"><?= e(t('خطوة %s', $i + 1)) ?></div>
              <div class="grid sm:grid-cols-3 gap-2">
                <select name="steps[<?= $i ?>][template]" class="input !mt-0 wa-tpl sm:col-span-2" <?= $i === 0 ? 'required' : '' ?>><?= $opts ?></select>
                <div class="flex gap-2">
                  <input type="number" min="0" name="steps[<?= $i ?>][delay]" value="<?= $i === 0 ? 0 : 1 ?>" class="input !mt-0 !w-24">
                  <select name="steps[<?= $i ?>][unit]" class="input !mt-0"><option value="m"><?= e(t('دقيقة')) ?></option><option value="h"><?= e(t('ساعة')) ?></option><option value="d" selected><?= e(t('يوم')) ?></option></select>
                </div>
              </div>
              <?= wa_mapping_fields('steps[' . $i . '][map]') ?>
            </div>
          <?php endfor; ?>
          <button class="btn-primary"><?= e(t('اعمل السلسلة (مسودة)')) ?></button>
        </form>
        <?= wa_mapping_script() ?>
        <?php
        return;
    }
    ?>
    <div class="flex justify-end mb-4"><a href="<?= e(url('sequences', ['new' => 1, 'wa' => $acc])) ?>" class="btn-primary"><?= e(t('+ سلسلة جديدة')) ?></a></div>
    <div class="card">
      <?php if (!$list): ?><p class="p-6 text-sm text-ink-500"><?= e(t('مفيش سلاسل.')) ?></p><?php endif; ?>
      <?php foreach ($list as $s): ?>
        <a href="<?= e(url('sequences', ['id' => $s['id'], 'wa' => $acc])) ?>" class="flex flex-wrap items-center justify-between gap-2 p-4 border-b border-ink-100 last:border-0 hover:bg-ink-50">
          <div class="min-w-0">
            <div class="font-medium"><?= e($s['name'] ?? '') ?> <?= wa_seq_badge((string)($s['status'] ?? '')) ?></div>
            <div class="text-xs text-ink-500"><?= e(platform_label((string)($s['platform'] ?? ''))) ?> · <?= e($s['accountName'] ?? '') ?> · <span dir="ltr"><?= e($s['messagePreview'] ?? '') ?></span></div>
          </div>
          <div class="text-xs text-ink-500"><?= e(t('%s خطوات · مسجلين %s · خلصوا %s', (int)($s['stepsCount'] ?? 0), (int)($s['totalEnrolled'] ?? 0), (int)($s['totalCompleted'] ?? 0))) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
});
