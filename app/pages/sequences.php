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
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/sequences.json') ? json_decode((string)file_get_contents($fxd . '/sequences.json'), true) : null;
if ($fx) { $accounts = $fx['accounts']; $acc = wa_pick_account($accounts); $apiError = null; }
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
if ($fx) {
    if ($id) { $seq = $fx['seq']; $enrollments = $fx['enrollments']; $contacts = $fx['contacts']; $templates = $fx['templates']; }
    elseif ($showNew) $templates = $fx['templates'];
    else $list = $fx['list'];
} elseif ($z && !$apiError) {
    try {
        if ($id) {
            $seq = wa_sequence($z, $id);
            $enrollments = wa_enrollments($z, $id, 100);
            $contacts = wa_contacts($z, ['limit' => 200, 'isSubscribed' => 'true', 'platform' => 'whatsapp'])['contacts'] ?? [];
            try { $templates = wa_templates($z, $acc, 'APPROVED'); } catch (ZernioException $ex) { $templates = []; }
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
    $m = ['draft' => [t('مسودة'), 'bg-ink-100 text-ink-700'], 'active' => [t('شغالة'), 'bg-lime-50 text-lime-700'], 'paused' => [t('متوقفة'), 'bg-amber-50 text-amber-700']];
    [$l, $c] = $m[$s] ?? [$s, 'bg-ink-100 text-ink-700'];
    return '<span class="inline-flex items-center gap-1 rounded-full text-xs px-2.5 py-0.5 ' . $c . '">' . ($s === 'active' ? '<span class="w-1.5 h-1.5 rounded-full bg-lime-500 animate-pulse"></span>' : '') . e($l) . '</span>';
}

render(t('السلاسل التلقائية'), 'sequences', function () use ($z, $fx, $accounts, $acc, $apiError, $id, $showNew, $list, $seq, $enrollments, $contacts, $templates) {
    if (!$z && !$fx) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    if (!$acc) { wa_no_account_box(); return; }
    $bodies = [];
    foreach ($templates as $tp) $bodies[($tp['name'] ?? '') . '|' . ($tp['language'] ?? '')] = (string)(wa_component($tp, 'body')['text'] ?? '');
    $pct = function ($a, $b) { return $b > 0 ? round($a / $b * 100) : 0; };

    /* ================= تفاصيل سلسلة ================= */
    if ($id && $seq) {
        $st = (string)($seq['status'] ?? '');
        $enrolledIds = array_column($enrollments, 'contactId');
        $steps = (array)($seq['steps'] ?? []);
        $tot = (int)($seq['totalEnrolled'] ?? 0); $done = (int)($seq['totalCompleted'] ?? 0); $exit = (int)($seq['totalExited'] ?? 0); $live = max(0, $tot - $done - $exit);
        // كام واحد وصل لكل خطوة (من المسجلين المعروضين)
        $reach = [];
        foreach ($steps as $i => $s) $reach[$i] = count(array_filter($enrollments, function ($e) use ($i) { return (int)($e['stepsSent'] ?? 0) > $i; }));
        $nE = max(1, count($enrollments));
        $stLabel = ['active' => t('شغال'), 'completed' => t('خلص'), 'exited' => t('خرج'), 'paused' => t('متوقف')];
        $stCls = ['active' => 'bg-lime-50 text-lime-700', 'completed' => 'bg-brand-50 text-brand-700', 'exited' => 'bg-ink-100 text-ink-600', 'paused' => 'bg-amber-50 text-amber-700'];
        ?>
        <a href="<?= e(url('sequences', ['wa' => $acc])) ?>" class="link text-sm"><?= e(t('→ كل السلاسل')) ?></a>
        <div class="card p-6 mt-3 mb-5">
          <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2"><h2 class="text-xl font-bold text-ink-900"><?= e($seq['name'] ?? '') ?></h2><?= wa_seq_badge($st) ?></div>
              <?php if (!empty($seq['description'])): ?><p class="text-sm text-ink-500 mt-1"><?= e($seq['description']) ?></p><?php endif; ?>
              <div class="flex flex-wrap gap-2 mt-2 text-xs">
                <?php if (!empty($seq['exitOnReply'])): ?><span class="rounded-full bg-ink-50 px-2.5 py-1"><?= e(t('بيخرج لو رد')) ?></span><?php endif; ?>
                <span class="rounded-full bg-ink-50 px-2.5 py-1"><?= e(t('بيخرج لو لغى الاشتراك')) ?></span>
              </div>
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

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
            <?php foreach ([[t('اتسجلوا'), $tot, ''], [t('لسه ماشيين'), $live, 'text-lime-700'], [t('خلصوا السلسلة'), $done, 'text-brand-700'], [t('خرجوا (رد أو لغى)'), $exit, 'text-ink-600']] as $k): ?>
              <div class="rounded-xl bg-ink-50 p-3"><div class="text-xs text-ink-500"><?= e($k[0]) ?></div><div class="text-2xl font-bold <?= $k[2] ?: 'text-ink-900' ?>"><?= (int)$k[1] ?></div><?php if ($k[0] !== t('اتسجلوا')): ?><div class="text-[11px] text-ink-400"><?= $pct($k[1], $tot) ?>%</div><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
          <?php if ($tot): ?>
          <div class="flex h-2 rounded-full overflow-hidden mt-3 bg-ink-100" title="<?= e(t('ماشيين / خلصوا / خرجوا')) ?>">
            <span class="bg-lime-500" style="width:<?= $pct($live, $tot) ?>%"></span><span class="bg-brand-600" style="width:<?= $pct($done, $tot) ?>%"></span><span class="bg-ink-400" style="width:<?= $pct($exit, $tot) ?>%"></span>
          </div>
          <?php endif; ?>

          <h3 class="font-semibold text-ink-900 mt-6 mb-3"><?= e(t('الخطوات')) ?></h3>
          <ol class="relative space-y-4 ps-2">
            <?php $cum = 0; foreach ($steps as $i => $s): $d = (int)($s['delayMinutes'] ?? 0); $cum += $d; $key = ($s['template']['name'] ?? '') . '|' . ($s['template']['language'] ?? ''); ?>
              <li class="relative flex gap-4">
                <?php if ($i < count($steps) - 1): ?><span class="absolute top-9 bottom-[-1rem] start-[1.05rem] w-0.5 bg-ink-200"></span><?php endif; ?>
                <span class="relative z-[1] w-9 h-9 rounded-full bg-brand-600 text-white flex items-center justify-center text-sm font-bold shrink-0"><?= $i + 1 ?></span>
                <div class="flex-1 min-w-0 rounded-xl border border-ink-200 p-3">
                  <div class="flex flex-wrap items-center gap-2 text-sm"><span class="font-medium text-ink-900"><?= e(wa_delay_label($d)) ?></span>
                    <span class="text-xs text-ink-400"><?= e(t('(من التسجيل: %s)', $cum ? wa_delay_label($cum) : t('فوراً'))) ?></span>
                    <span class="ms-auto text-xs text-ink-500"><?= e(t('وصلت لـ %s%%', $pct($reach[$i] ?? 0, $nE))) ?></span></div>
                  <div class="h-1 rounded-full bg-ink-100 mt-2 overflow-hidden"><div class="h-full bg-brand-500" style="width:<?= $pct($reach[$i] ?? 0, $nE) ?>%"></div></div>
                  <div class="text-xs font-mono text-ink-500 mt-2" dir="ltr" style="text-align:start"><?= e($s['template']['name'] ?? '') ?></div>
                  <?php if (!empty($bodies[$key]) || !empty($s['message']['text'])): ?><div class="mt-2 max-w-md rounded-2xl rounded-ss-sm bg-lime-50 border border-lime-200 px-3 py-2 text-sm text-ink-800 whitespace-pre-line" dir="auto"><?= e($bodies[$key] ?? $s['message']['text']) ?></div><?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
          <p class="text-[11px] text-ink-400 mt-3"><?= e(t('نسبة الوصول محسوبة من المسجلين اللي ظاهرين تحت (آخر 100).')) ?></p>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
          <div class="card overflow-hidden">
            <div class="p-4 border-b border-ink-100 flex flex-wrap items-center gap-2">
              <span class="font-semibold text-ink-900 me-auto"><?= e(t('المسجلين')) ?></span>
              <div id="en-chips" class="flex gap-1 text-xs">
                <?php foreach (['' => t('الكل'), 'active' => t('شغال'), 'completed' => t('خلص'), 'exited' => t('خرج')] as $k => $l): ?><button type="button" data-s="<?= $k ?>" class="rounded-full px-2.5 py-1 <?= $k === '' ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100' ?>"><?= e($l) ?></button><?php endforeach; ?>
              </div>
              <input type="search" id="en-q" class="input !mt-0 !py-1.5 text-sm w-full" placeholder="<?= e(t('دوّر بالاسم أو الرقم…')) ?>">
            </div>
            <div class="max-h-[28rem] overflow-y-auto">
            <?php if (!$enrollments): ?><p class="p-6 text-center text-sm text-ink-500"><?= e(t('مفيش حد مسجل.')) ?></p><?php endif; ?>
            <?php foreach ($enrollments as $en): $es = (string)($en['status'] ?? ''); $sent = (int)($en['stepsSent'] ?? 0); ?>
              <div class="en flex items-center gap-3 px-4 py-2.5 border-b border-ink-100 last:border-0" data-s="<?= e($es) ?>" data-q="<?= e(mb_strtolower(($en['contactName'] ?? '') . ' ' . preg_replace('/\D/', '', (string)($en['platformIdentifier'] ?? '')))) ?>">
                <span class="w-9 h-9 shrink-0 rounded-full bg-ink-100 text-ink-600 flex items-center justify-center font-bold text-sm"><?= e(mb_substr((string)($en['contactName'] ?? '?'), 0, 1)) ?></span>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2"><span class="font-medium text-sm text-ink-900 truncate"><?= e($en['contactName'] ?? $en['platformIdentifier'] ?? '') ?></span><span class="rounded-full text-[10px] px-2 py-0.5 <?= $stCls[$es] ?? 'bg-ink-100' ?>"><?= e($stLabel[$es] ?? $es) ?></span></div>
                  <div class="flex items-center gap-1 mt-1"><?php for ($k = 0; $k < count($steps); $k++): ?><span class="h-1.5 w-6 rounded-full <?= $k < $sent ? 'bg-brand-500' : 'bg-ink-100' ?>"></span><?php endfor; ?>
                    <span class="text-[11px] text-ink-500 ms-1"><?= e(!empty($en['nextStepAt']) && $es === 'active' ? t('الجاية: %s', fmt_date($en['nextStepAt'], 'd/m h:i A')) : t('اتبعت %s خطوة', $sent)) ?></span></div>
                </div>
                <?php if ($es === 'active' || $es === 'paused'): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('شيل جهة الاتصال دي من السلسلة؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="unenroll"><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="contact" value="<?= e($en['contactId'] ?? '') ?>"><button class="w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" title="<?= e(t('شيله')) ?>">✕</button></form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          </div>
          <form method="post" class="card overflow-hidden" id="enroll">
            <?= csrf_field() ?><input type="hidden" name="action" value="enroll"><input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="p-4 border-b border-ink-100 space-y-2">
              <div class="flex items-center gap-2"><span class="font-semibold text-ink-900 me-auto"><?= e(t('سجّل جهات اتصال')) ?></span><span id="enr-n" class="text-xs text-ink-500"></span></div>
              <div class="flex gap-2"><input type="search" id="enr-q" class="input !mt-0 !py-1.5 text-sm flex-1" placeholder="<?= e(t('دوّر…')) ?>"><button type="button" id="enr-all" class="btn-ghost !py-1.5 text-xs"><?= e(t('اختار الظاهرين')) ?></button></div>
            </div>
            <div class="max-h-[24rem] overflow-y-auto p-2">
              <?php $any = false; foreach ($contacts as $c): if (in_array($c['id'], $enrolledIds, true)) continue; $any = true; ?>
                <label class="enr flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-ink-50 cursor-pointer" data-q="<?= e(mb_strtolower(($c['name'] ?? '') . ' ' . preg_replace('/\D/', '', (string)($c['displayIdentifier'] ?? $c['platformIdentifier'] ?? '')))) ?>">
                  <input type="checkbox" name="contacts[]" value="<?= e($c['id']) ?>" class="accent-brand-600">
                  <span class="flex-1 min-w-0 text-sm"><span class="font-medium text-ink-900"><?= e($c['name'] ?? '') ?></span> <span class="text-xs text-ink-500" dir="ltr"><?= e($c['displayIdentifier'] ?? $c['platformIdentifier'] ?? '') ?></span></span>
                </label>
              <?php endforeach; ?>
              <?php if (!$any): ?><p class="p-4 text-center text-sm text-ink-500"><?= e(t('مفيش جهات اتصال مشتركة متاحة.')) ?></p><?php endif; ?>
            </div>
            <div class="p-4 border-t border-ink-100 space-y-2">
              <?php if ($st !== 'active'): ?><p class="text-xs text-amber-700"><?= e(t('السلسلة مش مفعلة، الرسايل مش هتتبعت لحد ما تفعلها.')) ?></p><?php endif; ?>
              <button class="btn-primary w-full disabled:opacity-50" id="enr-go" disabled><?= e(t('تسجيل')) ?></button>
            </div>
          </form>
        </div>
        <script>window.SQX = <?= json_encode(['n' => t('%s مختارين'), 'go' => t('سجّل %s')], JSON_UNESCAPED_UNICODE) ?>;</script>
        <script src="assets/ui-sequences.js?v=<?= e(APP_VERSION) ?>"></script>
        <?php
        return;
    }

    wa_account_picker($accounts, $acc, 'sequences');

    /* ================= سلسلة جديدة ================= */
    if ($showNew) {
        $opts = wa_template_options($templates);
        ?>
        <form method="post" id="sq-new" class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
          <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="wa" value="<?= e($acc) ?>">
          <div class="space-y-4 min-w-0">
            <div class="card p-5 space-y-4">
              <div class="flex items-center justify-between"><h2 class="font-bold text-lg text-ink-900"><?= e(t('سلسلة جديدة')) ?></h2><a class="text-sm text-ink-500" href="<?= e(url('sequences', ['wa' => $acc])) ?>"><?= e(t('إلغاء')) ?></a></div>
              <div class="grid sm:grid-cols-2 gap-4">
                <label class="block"><span class="label"><?= e(t('الاسم')) ?></span><input name="name" required class="input" placeholder="<?= e(t('مثلاً: ترحيب العملاء الجدد')) ?>"></label>
                <label class="block"><span class="label"><?= e(t('وصف (اختياري)')) ?></span><input name="description" class="input"></label>
              </div>
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="exitOnReply" value="1" checked class="peer sr-only">
                <span class="relative w-10 h-6 shrink-0 rounded-full bg-ink-200 peer-checked:bg-brand-600 transition-colors after:content-[''] after:absolute after:top-1 after:start-1 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:start-5"></span>
                <span class="text-sm"><?= e(t('وقف السلسلة للشخص لو رد على أي رسالة')) ?></span>
              </label>
              <?php if (!$templates): ?><p class="text-sm text-amber-700"><?= e(t('مفيش قوالب مقبولة لسه.')) ?> <a class="link" href="<?= e(url('wa_templates', ['wa' => $acc, 'new' => 1])) ?>"><?= e(t('اعمل قالب')) ?></a></p><?php endif; ?>
              <div class="flex flex-wrap items-center gap-1.5"><span class="text-xs text-ink-500"><?= e(t('مواعيد جاهزة:')) ?></span>
                <?php foreach (['0,1440,4320' => t('ترحيب: فوراً ← يوم ← 3 أيام'), '0,2880' => t('متابعة: فوراً ← يومين'), '60,1440,10080' => t('بعد الطلب: ساعة ← يوم ← أسبوع')] as $k => $l): ?><button type="button" data-preset="<?= $k ?>" class="rounded-full border border-ink-200 px-3 py-1 text-xs hover:border-brand-400 hover:bg-brand-50"><?= e($l) ?></button><?php endforeach; ?>
              </div>
            </div>
            <div id="sq-steps" class="space-y-3">
            <?php for ($i = 0; $i < WA_SEQ_MAX_STEPS; $i++): ?>
              <div class="sq-step card p-4" data-wa-step data-i="<?= $i ?>" <?= $i > 0 ? 'style="display:none"' : '' ?>>
                <div class="flex items-center gap-3 mb-3">
                  <span class="w-8 h-8 rounded-full bg-brand-600 text-white text-sm font-bold flex items-center justify-center"><?= $i + 1 ?></span>
                  <span class="font-semibold text-ink-900 flex-1"><?= e(t('خطوة %s', $i + 1)) ?></span>
                  <?php if ($i > 0): ?><button type="button" class="sq-rm w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" title="<?= e(t('شيل الخطوة')) ?>">✕</button><?php endif; ?>
                </div>
                <div class="grid sm:grid-cols-[minmax(0,1fr)_auto] gap-2 items-center">
                  <select name="steps[<?= $i ?>][template]" class="input !mt-0 wa-tpl" <?= $i === 0 ? 'required' : '' ?>><?= $opts ?></select>
                  <div class="flex items-center gap-2"><span class="text-xs text-ink-500 whitespace-nowrap"><?= e($i === 0 ? t('بعد التسجيل بـ') : t('بعد اللي قبلها بـ')) ?></span>
                    <input type="number" min="0" name="steps[<?= $i ?>][delay]" value="<?= $i === 0 ? 0 : 1 ?>" class="sq-d input !mt-0 !w-20">
                    <select name="steps[<?= $i ?>][unit]" class="sq-u input !mt-0 !w-auto"><option value="m"><?= e(t('دقيقة')) ?></option><option value="h"><?= e(t('ساعة')) ?></option><option value="d" selected><?= e(t('يوم')) ?></option></select></div>
                </div>
                <?= wa_mapping_fields('steps[' . $i . '][map]') ?>
              </div>
            <?php endfor; ?>
            </div>
            <button type="button" id="sq-add" class="w-full rounded-2xl border-2 border-dashed border-ink-200 hover:border-brand-400 hover:bg-brand-50 text-brand-700 py-3 text-sm font-medium transition"><?= e(t('+ ضيف خطوة')) ?> <span class="text-ink-400 font-normal">(<?= e(t('لحد %s', WA_SEQ_MAX_STEPS)) ?>)</span></button>
          </div>
          <aside class="lg:sticky lg:top-4 card p-5">
            <div class="font-bold text-ink-900 mb-1"><?= e(t('هتوصل للعميل كده')) ?></div>
            <p class="text-[11px] text-ink-500 mb-4"><?= e(t('من لحظة ما يتسجل في السلسلة')) ?></p>
            <div id="sq-tl" class="rounded-2xl bg-[#e7ddd3] p-3 space-y-2 min-h-[8rem]"></div>
            <div id="sq-total" class="text-xs text-ink-500 mt-3"></div>
            <button class="btn-primary w-full mt-4"><?= e(t('اعمل السلسلة (مسودة)')) ?></button>
            <p class="text-[11px] text-ink-400 mt-2"><?= e(t('هتتعمل مسودة، ومش هيتبعت حاجة لحد ما تفعلها.')) ?></p>
          </aside>
        </form>
        <?= wa_mapping_script() ?>
        <script>window.SQX = <?= json_encode(['new' => true, 'and' => t(' و'), 'max' => WA_SEQ_MAX_STEPS, 'u' => ['m' => t('دقيقة'), 'h' => t('ساعة'), 'd' => t('يوم')], 's' => ['now' => t('فوراً'), 'after' => t('بعد %s'), 'day' => t('يوم %s'), 'pick' => t('اختار قالب'), 'total' => t('السلسلة كلها: %s خطوات على مدى %s'), 'empty' => t('اختار قالب للخطوة الأولى عشان تشوف المعاينة.')]], JSON_UNESCAPED_UNICODE) ?>;</script>
        <script src="assets/ui-sequences.js?v=<?= e(APP_VERSION) ?>"></script>
        <?php
        return;
    }

    /* ================= القايمة ================= */
    $act = count(array_filter($list, function ($s) { return ($s['status'] ?? '') === 'active'; }));
    $totE = array_sum(array_map(function ($s) { return (int)($s['totalEnrolled'] ?? 0); }, $list));
    $totC = array_sum(array_map(function ($s) { return (int)($s['totalCompleted'] ?? 0); }, $list));
    ?>
    <div class="flex flex-wrap items-center gap-3 mb-5">
      <p class="text-sm text-ink-500 me-auto"><?= e(t('رسايل واتساب بتتبعت لوحدها على مراحل لكل عميل بتسجله.')) ?></p>
      <a href="<?= e(url('sequences', ['new' => 1, 'wa' => $acc])) ?>" class="btn-primary"><?= e(t('+ سلسلة جديدة')) ?></a>
    </div>
    <?php if ($list): ?>
    <div class="grid grid-cols-3 gap-3 mb-5">
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('سلاسل شغالة')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= $act ?> / <?= count($list) ?></div></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('اتسجلوا إجمالاً')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= $totE ?></div></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('نسبة اللي خلصوا')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= $pct($totC, $totE) ?>%</div></div>
    </div>
    <?php endif; ?>
    <?php if (!$list): ?>
      <div class="card p-10 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-lime-50 text-lime-700 flex items-center justify-center text-2xl mb-3">⟳</div>
        <p class="font-semibold text-ink-900"><?= e(t('مفيش سلاسل.')) ?></p>
        <p class="text-sm text-ink-500 mt-1 mb-4"><?= e(t('ابدأ بسلسلة ترحيب: رسالة فورية، وبعدها تذكير بعد يوم، وعرض بعد 3 أيام.')) ?></p>
        <a href="<?= e(url('sequences', ['new' => 1, 'wa' => $acc])) ?>" class="btn-primary"><?= e(t('+ سلسلة جديدة')) ?></a>
      </div>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
      <?php foreach ($list as $s): $tot = (int)($s['totalEnrolled'] ?? 0); $done = (int)($s['totalCompleted'] ?? 0); $exit = (int)($s['totalExited'] ?? 0); $live = max(0, $tot - $done - $exit); ?>
        <a href="<?= e(url('sequences', ['id' => $s['id'], 'wa' => $acc])) ?>" class="card p-5 hover:shadow-md hover:border-brand-300 transition block">
          <div class="flex items-start gap-2 mb-3"><span class="font-bold text-ink-900 flex-1"><?= e($s['name'] ?? '') ?></span><?= wa_seq_badge((string)($s['status'] ?? '')) ?></div>
          <div class="flex items-center gap-1 mb-3"><?php for ($k = 0; $k < (int)($s['stepsCount'] ?? 0); $k++): ?><span class="w-6 h-6 rounded-full bg-brand-50 text-brand-700 text-[10px] font-bold flex items-center justify-center"><?= $k + 1 ?></span><?php if ($k < (int)$s['stepsCount'] - 1): ?><span class="w-3 h-0.5 bg-brand-200"></span><?php endif; ?><?php endfor; ?>
            <span class="text-xs text-ink-500 ms-2 font-mono truncate" dir="ltr"><?= e($s['messagePreview'] ?? '') ?></span></div>
          <div class="flex h-1.5 rounded-full overflow-hidden bg-ink-100"><span class="bg-lime-500" style="width:<?= $pct($live, $tot) ?>%"></span><span class="bg-brand-600" style="width:<?= $pct($done, $tot) ?>%"></span><span class="bg-ink-400" style="width:<?= $pct($exit, $tot) ?>%"></span></div>
          <div class="flex justify-between text-xs text-ink-500 mt-2"><span><?= e(t('مسجلين %s', $tot)) ?></span><span><?= e(t('خلصوا %s%%', $pct($done, $tot))) ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
});
