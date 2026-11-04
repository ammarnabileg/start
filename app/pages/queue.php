<?php
$err = null;
$z = zernio_or_null($err);
$apiError = null;
$queues = [];
$previews = [];
$profileId = null;

/** يحوّل الفورم (slot_day[] / slot_time[]) لقائمة مواعيد */
function queue_slots_from_post(): array
{
    $days = (array)($_POST['slot_day'] ?? []);
    $times = (array)($_POST['slot_time'] ?? []);
    $out = [];
    foreach ($days as $i => $d) {
        $tm = (string)($times[$i] ?? '');
        if ($tm === '' || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $tm)) continue;
        $d = (int)$d;
        if ($d < 0 || $d > 6) continue;
        $out[$d . $tm] = ['dayOfWeek' => $d, 'time' => $tm];
    }
    ksort($out);
    return array_values($out);
}

if ($z) {
    try {
        $profileId = current_profile_id($z);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

if ($z && $profileId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $name = trim((string)($_POST['name'] ?? ''));
            $slots = queue_slots_from_post();
            if ($name === '') throw new ZernioException(t('اكتب اسم للطابور.'));
            if (!$slots) throw new ZernioException(t('ضيف ميعاد واحد على الأقل.'));
            $body = ['profileId' => $profileId, 'name' => $name, 'timezone' => site_timezone(), 'slots' => $slots, 'active' => !empty($_POST['active'])];
            $qid = (string)($_POST['queueId'] ?? '');
            if ($qid !== '') {
                $body['queueId'] = $qid;
                $body['reshuffleExisting'] = !empty($_POST['reshuffle']);
                if (!empty($_POST['setDefault'])) $body['setAsDefault'] = true;
                pub_queue_update($z, $body);
                log_activity('queue.update', $qid);
            } else {
                pub_queue_create($z, $body);
                log_activity('queue.create', $name);
            }
            flash('success', t('تم حفظ الطابور.'));
        } elseif ($action === 'delete') {
            if (!can('posts.manage')) throw new ZernioException(t('الإجراء ده لصاحب الشركة بس.'));
            $qid = (string)($_POST['queueId'] ?? '');
            if ($qid === '') throw new ZernioException(t('مفيش طابور محدد.'));
            pub_queue_delete($z, $profileId, $qid);
            log_activity('queue.delete', $qid);
            flash('success', t('تم حذف الطابور.'));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('queue'));
}

if ($z && $profileId && !$apiError) {
    try {
        $queues = pub_queues($z, $profileId);
        foreach ($queues as $q) {
            $previews[$q['_id']] = !empty($q['active']) || !isset($q['active']) ? pub_queue_preview($z, $profileId, $q['_id'], 5) : [];
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('طوابير النشر'), 'queue', function () use ($z, $apiError, $queues, $previews) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $days = pub_weekdays();
    $editId = (string)($_GET['edit'] ?? '');
    $editing = null;
    foreach ($queues as $q) if ($q['_id'] === $editId) $editing = $q;
    $isNew = isset($_GET['new']);
    ?>
    <div data-ai-insights="queue_check,best_time" class="mb-6"></div>
    <div class="card p-5 mb-6 text-sm text-ink-700">
      <?= e(t('الطابور هو مواعيد ثابتة في الأسبوع (مثلاً الاتنين 10 الصبح)، ولما تختار "ضيف للطابور" وأنت بتكتب منشور، بيتحط تلقائي في أقرب ميعاد فاضي.')) ?>
    </div>

    <?php
      $suggested = (module_enabled('analytics') && function_exists('best_slots_local') && $z) ? best_slots_local($z, 5) : [];
      if ($suggested && !$editing): ?>
      <div class="card p-5 mb-6">
        <b class="text-ink-900 text-sm"><?= e(t('مواعيد مقترحة من أحسن أوقات التفاعل')) ?></b>
        <div class="flex flex-wrap gap-2 mt-3">
          <?php foreach ($suggested as $s): ?><span class="chip !cursor-default">⚡ <?= e(weekday_name($s['dow'])) ?> · <span dir="ltr"><?= sprintf('%02d:00', $s['hour']) ?></span></span><?php endforeach; ?>
        </div>
        <a href="<?= e(url('queue', ['new' => 1, 'suggest' => 1])) ?>" class="btn-ghost text-sm mt-4"><?= e(t('اعمل طابور بالمواعيد دي')) ?></a>
      </div>
    <?php endif; ?>

    <?php if ($editing || $isNew):
        $slots = $editing['slots'] ?? [['dayOfWeek' => 1, 'time' => '10:00']];
        if (!$editing && !empty($_GET['suggest']) && $suggested) {
            $slots = array_map(function ($s) { return ['dayOfWeek' => $s['dow'], 'time' => sprintf('%02d:00', $s['hour'])]; }, $suggested);
        }
        ?>
      <form method="post" class="card p-5 space-y-4 mb-6" id="queueForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <input type="hidden" name="queueId" value="<?= e($editing['_id'] ?? '') ?>">
        <h2 class="font-bold"><?= e($editing ? t('تعديل الطابور') : t('طابور جديد')) ?></h2>
        <label class="block"><span class="label"><?= e(t('اسم الطابور')) ?></span>
          <input name="name" class="input" value="<?= e($editing['name'] ?? '') ?>" placeholder="<?= e(t('مثلاً: منشورات الصبح')) ?>" required></label>
        <div>
          <span class="label"><?= e(t('المواعيد')) ?> (<?= e(site_timezone()) ?>)</span>
          <div id="slotRows" class="space-y-2 mt-2">
            <?php foreach ($slots as $s): ?>
              <div class="flex gap-2 items-center slot-row">
                <select name="slot_day[]" class="input !mt-0 w-40"><?php foreach ($days as $i => $dn): ?><option value="<?= $i ?>" <?= (int)$s['dayOfWeek'] === $i ? 'selected' : '' ?>><?= e($dn) ?></option><?php endforeach; ?></select>
                <input type="time" name="slot_time[]" value="<?= e($s['time']) ?>" class="input !mt-0 w-36" dir="ltr">
                <button type="button" class="text-red-600 text-sm px-2" onclick="this.parentNode.remove()"><?= e(t('شيل')) ?></button>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn-ghost text-sm mt-2" id="addSlot"><?= e(t('+ ضيف ميعاد')) ?></button>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" <?= ($editing['active'] ?? true) ? 'checked' : '' ?>> <?= e(t('الطابور شغال')) ?></label>
        <?php if ($editing): ?>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reshuffle" value="1"> <?= e(t('أعد ترتيب المنشورات اللي في الطابور على المواعيد الجديدة')) ?></label>
          <?php if (empty($editing['isDefault'])): ?><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="setDefault" value="1"> <?= e(t('خليه الطابور الأساسي')) ?></label><?php endif; ?>
        <?php endif; ?>
        <div class="flex gap-2">
          <button class="btn-primary"><?= e(t('احفظ')) ?></button>
          <a href="<?= e(url('queue')) ?>" class="btn-ghost"><?= e(t('إلغاء')) ?></a>
        </div>
      </form>
      <template id="slotTpl">
        <div class="flex gap-2 items-center slot-row">
          <select name="slot_day[]" class="input !mt-0 w-40"><?php foreach ($days as $i => $dn): ?><option value="<?= $i ?>"><?= e($dn) ?></option><?php endforeach; ?></select>
          <input type="time" name="slot_time[]" value="10:00" class="input !mt-0 w-36" dir="ltr">
          <button type="button" class="text-red-600 text-sm px-2" onclick="this.parentNode.remove()"><?= e(t('شيل')) ?></button>
        </div>
      </template>
      <script>
        document.getElementById('addSlot').addEventListener('click', function () {
          document.getElementById('slotRows').appendChild(document.getElementById('slotTpl').content.cloneNode(true));
        });
      </script>
    <?php else: ?>
      <a href="<?= e(url('queue', ['new' => 1])) ?>" class="btn-primary mb-6 inline-flex"><?= e(t('+ طابور جديد')) ?></a>
    <?php endif; ?>

    <?php if (!$queues): ?>
      <div class="card p-6 text-sm text-ink-500"><?= e(t('مفيش طوابير لسه. اعمل أول طابور.')) ?></div>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 gap-6">
      <?php foreach ($queues as $q): ?>
        <div class="card p-5 space-y-3">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="font-bold"><?= e($q['name'] ?? t('بدون اسم')) ?></h3>
            <?php if (!empty($q['isDefault'])): ?><span class="badge badge-green"><?= e(t('الأساسي')) ?></span><?php endif; ?>
            <?php if (isset($q['active']) && !$q['active']): ?><span class="badge"><?= e(t('متوقف')) ?></span><?php endif; ?>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <?php foreach ($q['slots'] ?? [] as $s): ?>
              <span class="badge"><?= e($days[(int)$s['dayOfWeek']] ?? '') ?> · <span dir="ltr"><?= e($s['time']) ?></span></span>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($previews[$q['_id']])): ?>
            <div>
              <p class="text-xs font-semibold text-ink-600 mb-1"><?= e(t('أقرب مواعيد فاضية:')) ?></p>
              <ul class="text-sm text-ink-700 space-y-0.5">
                <?php foreach ($previews[$q['_id']] as $slot): ?><li><?= e(($days[(int)fmt_date($slot, 'w')] ?? '') . ' ' . fmt_date($slot, 'd/m h:i A')) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <div class="flex gap-2 pt-2">
            <a class="btn-ghost text-sm" href="<?= e(url('queue', ['edit' => $q['_id']])) ?>"><?= e(t('تعديل')) ?></a>
            <?php if (can('posts.manage')): ?>
            <form method="post" onsubmit="return confirm('<?= tjs('متأكد إنك عاوز تحذف الطابور ده؟ المنشورات اللي متحطة فيه ممكن تتأثر.') ?>')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="queueId" value="<?= e($q['_id']) ?>">
              <button class="text-sm text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg"><?= e(t('حذف')) ?></button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
});
