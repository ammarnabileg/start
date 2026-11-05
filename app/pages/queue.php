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

$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/queue.json') ? json_decode((string)file_get_contents($fxd . '/queue.json'), true) : null;
if ($fx) {
    $queues = $fx['queues']; $previews = $fx['previews']; $apiError = null;
} elseif ($z && $profileId && !$apiError) {
    try {
        $queues = pub_queues($z, $profileId);
        foreach ($queues as $q) {
            $previews[$q['_id']] = !empty($q['active']) || !isset($q['active']) ? pub_queue_preview($z, $profileId, $q['_id'], 5) : [];
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('طوابير النشر'), 'queue', function () use ($z, $fx, $apiError, $queues, $previews) {
    if (!$z && !$fx) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $days = pub_weekdays();
    $editId = (string)($_GET['edit'] ?? '');
    $editing = null;
    foreach ($queues as $q) if ($q['_id'] === $editId) $editing = $q;
    $isNew = isset($_GET['new']);
    ?>
    <div class="card p-5 mb-6 text-sm text-ink-700">
      <?= e(t('الطابور هو مواعيد ثابتة في الأسبوع (مثلاً الاتنين 10 الصبح)، ولما تختار "ضيف للطابور" وأنت بتكتب منشور، بيتحط تلقائي في أقرب ميعاد فاضي.')) ?>
    </div>

    <?php
      $suggested = (module_enabled('analytics') && function_exists('best_slots_local') && $z && !$fx) ? best_slots_local($z, 5) : [];
      if ($suggested && !$editing): ?>
      <div class="card p-5 mb-6">
        <b class="text-ink-900 text-sm"><?= e(t('مواعيد مقترحة من أحسن أوقات التفاعل')) ?></b>
        <div class="flex flex-wrap gap-2 mt-3">
          <?php foreach ($suggested as $s): ?><span class="chip !cursor-default"><?= ui_icon('zap', 'w-3.5 h-3.5 text-amber-600') ?><?= e(weekday_name($s['dow'])) ?> · <span dir="ltr"><?= sprintf('%02d:00', $s['hour']) ?></span></span><?php endforeach; ?>
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
          <div class="flex flex-wrap items-center gap-2"><span class="label !mb-0 me-auto"><?= e(t('المواعيد')) ?> (<?= e(site_timezone()) ?>)</span>
            <span id="qs-n" class="text-xs rounded-full bg-brand-50 text-brand-700 px-2.5 py-0.5"></span></div>
          <div class="flex flex-wrap items-center gap-1.5 mt-2">
            <span class="text-xs text-ink-500"><?= e(t('جاهز:')) ?></span>
            <?php foreach (['3w' => t('3 مرات في الأسبوع'), 'daily' => t('كل يوم 7 بالليل'), 'work2' => t('أيام الشغل مرتين'), 'clear' => t('امسح الكل')] as $k => $l): ?><button type="button" data-p="<?= $k ?>" class="rounded-full border border-ink-200 px-3 py-1 text-xs hover:border-brand-400 hover:bg-brand-50 <?= $k === 'clear' ? 'text-red-600' : '' ?>"><?= e($l) ?></button><?php endforeach; ?>
          </div>
          <p class="text-xs text-ink-500 mt-2"><?= e(t('دوس على أي مربع عشان تضيف أو تشيل ميعاد. اسحب على كذا مربع مرة واحدة.')) ?></p>
          <div class="overflow-x-auto mt-2">
            <div id="qs-grid" class="min-w-[44rem] select-none" dir="ltr"></div>
          </div>
          <div id="slotRows" class="hidden">
            <?php foreach ($slots as $s): ?><span class="slot-init" data-d="<?= (int)$s['dayOfWeek'] ?>" data-t="<?= e($s['time']) ?>"></span><?php endforeach; ?>
          </div>
          <div id="qs-list" class="flex flex-wrap gap-1.5 mt-3"></div>
          <div class="flex flex-wrap items-center gap-2 mt-3">
            <span class="text-xs text-ink-500"><?= e(t('ميعاد بالدقيقة:')) ?></span>
            <select id="qs-day" class="input !mt-0 !py-1.5 !w-auto text-sm"><?php foreach ($days as $i => $dn): ?><option value="<?= $i ?>"><?= e($dn) ?></option><?php endforeach; ?></select>
            <input type="time" id="qs-time" value="10:30" class="input !mt-0 !py-1.5 !w-32 text-sm" dir="ltr">
            <button type="button" class="btn-ghost !py-1.5 text-xs" id="addSlot"><?= e(t('+ ضيف')) ?></button>
          </div>
          <div id="qs-inputs"></div>
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
      <script>
      (function () {
        var DAYS = <?= json_encode(array_values($days), JSON_UNESCAPED_UNICODE) ?>, N = <?= json_encode(t('%s ميعاد في الأسبوع'), JSON_UNESCAPED_UNICODE) ?>;
        var set = {}, grid = document.getElementById('qs-grid'), list = document.getElementById('qs-list'), inputs = document.getElementById('qs-inputs');
        document.querySelectorAll('.slot-init').forEach(function (e) { set[e.dataset.d + '|' + e.dataset.t] = 1; });
        var H = []; for (var h = 6; h < 24; h++) H.push(h); H.push(0); H.push(1);
        var pad = function (n) { return ('0' + n).slice(-2); };
        function lab(h) { return h === 0 ? '12a' : h < 12 ? h + 'a' : h === 12 ? '12p' : (h - 12) + 'p'; }
        function build() {
          var html = '<div class="flex gap-0.5 ps-14 mb-1 text-[10px] text-ink-500">' + H.map(function (h) { return '<div class="flex-1 text-center">' + lab(h) + '</div>'; }).join('') + '</div>';
          DAYS.forEach(function (dn, d) {
            html += '<div class="flex gap-0.5 mb-0.5 items-center"><div class="w-14 shrink-0 text-xs text-ink-600 pe-1 text-end" dir="auto">' + dn + '</div>'
              + H.map(function (h) { return '<button type="button" class="qs-c flex-1 h-7 rounded-md transition" data-d="' + d + '" data-h="' + h + '"></button>'; }).join('') + '</div>';
          });
          grid.innerHTML = html;
        }
        function hourHas(d, h) { return Object.keys(set).some(function (k) { var p = k.split('|'); return +p[0] === d && +p[1].split(':')[0] === h; }); }
        function paint() {
          grid.querySelectorAll('.qs-c').forEach(function (c) { var on = hourHas(+c.dataset.d, +c.dataset.h); c.className = 'qs-c flex-1 h-7 rounded-md transition ' + (on ? 'bg-brand-600 hover:bg-brand-700' : 'bg-ink-100 hover:bg-brand-200'); });
          var keys = Object.keys(set).sort(function (a, b) { var x = a.split('|'), y = b.split('|'); return x[0] - y[0] || (x[1] > y[1] ? 1 : -1); });
          list.innerHTML = keys.map(function (k) { var p = k.split('|'); return '<span class="inline-flex items-center gap-1 rounded-full bg-ink-50 ps-2.5 pe-1 py-0.5 text-xs">' + DAYS[p[0]] + ' · <b dir="ltr">' + p[1] + '</b><button type="button" data-k="' + k + '" class="w-5 h-5 rounded-full hover:bg-red-50 hover:text-red-600">×</button></span>'; }).join('');
          inputs.innerHTML = keys.map(function (k) { var p = k.split('|'); return '<input type="hidden" name="slot_day[]" value="' + p[0] + '"><input type="hidden" name="slot_time[]" value="' + p[1] + '">'; }).join('');
          document.getElementById('qs-n').textContent = N.replace('%s', keys.length);
        }
        function toggle(d, h, force) {
          var k = d + '|' + pad(h) + ':00', has = hourHas(d, h), want = force === undefined ? !has : force;
          Object.keys(set).forEach(function (x) { var p = x.split('|'); if (+p[0] === d && +p[1].split(':')[0] === h) delete set[x]; });
          if (want) set[k] = 1;
        }
        build();
        var drag = null;
        grid.addEventListener('mousedown', function (e) { var c = e.target.closest('.qs-c'); if (!c) return; e.preventDefault(); drag = !hourHas(+c.dataset.d, +c.dataset.h); toggle(+c.dataset.d, +c.dataset.h, drag); paint(); });
        grid.addEventListener('mouseover', function (e) { if (drag === null) return; var c = e.target.closest('.qs-c'); if (c) { toggle(+c.dataset.d, +c.dataset.h, drag); paint(); } });
        document.addEventListener('mouseup', function () { drag = null; });
        grid.addEventListener('keydown', function (e) { var c = e.target.closest('.qs-c'); if (c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggle(+c.dataset.d, +c.dataset.h); paint(); } });
        grid.addEventListener('touchend', function (e) { var c = e.target.closest('.qs-c'); if (c) { e.preventDefault(); toggle(+c.dataset.d, +c.dataset.h); paint(); } });
        list.addEventListener('click', function (e) { var b = e.target.closest('[data-k]'); if (b) { delete set[b.dataset.k]; paint(); } });
        document.getElementById('addSlot').addEventListener('click', function () { var t = document.getElementById('qs-time').value; if (/^\d\d:\d\d$/.test(t)) { set[document.getElementById('qs-day').value + '|' + t] = 1; paint(); } });
        document.querySelectorAll('[data-p]').forEach(function (b) {
          b.onclick = function () {
            set = {};
            var add = function (d, t) { set[d + '|' + t] = 1; };
            if (b.dataset.p === '3w') { add(0, '19:00'); add(2, '19:00'); add(4, '19:00'); }
            else if (b.dataset.p === 'daily') { for (var d = 0; d < 7; d++) add(d, '19:00'); }
            else if (b.dataset.p === 'work2') { <?= json_encode(array_values(array_filter(range(0, 6), function ($d) { return !in_array(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][$d], (array)cx('weekend'), true); }))) ?>.forEach(function (d) { add(d, '10:00'); add(d, '19:00'); }); }
            paint();
          };
        });
        document.getElementById('queueForm').addEventListener('submit', function (e) { if (!Object.keys(set).length) { e.preventDefault(); alert(<?= json_encode(t('ضيف ميعاد واحد على الأقل.'), JSON_UNESCAPED_UNICODE) ?>); } });
        paint();
      })();
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
          <?php $byD = array_fill(0, 7, []); foreach ($q['slots'] ?? [] as $s) $byD[(int)$s['dayOfWeek']][] = (string)$s['time']; ?>
          <div class="grid grid-cols-7 gap-1 text-center">
            <?php foreach ($byD as $d => $ts): sort($ts); ?>
              <div class="rounded-lg <?= $ts ? 'bg-brand-50' : 'bg-ink-50' ?> p-1.5 min-h-[3.5rem]">
                <div class="text-[10px] font-semibold <?= $ts ? 'text-brand-700' : 'text-ink-400' ?> truncate"><?= e(mb_substr(preg_replace('/^ال/u', '', $days[$d]), 0, 4)) ?></div>
                <?php foreach ($ts as $tm): ?><div class="text-[10px] text-ink-700 mt-0.5" dir="ltr"><?= e($tm) ?></div><?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="text-xs text-ink-500"><?= e(t('%s ميعاد في الأسبوع', count($q['slots'] ?? []))) ?></div>
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
