<?php
/** التنبيهات: قواعد يظبطها صاحب الشركة، والتنبيهات اللي حصلت */
$wid = (int)ws('id');
$canManage = can('settings.manage');
$catalog = rp_alert_rules();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'seen_all') {
        db()->prepare("UPDATE alerts SET seen = 1 WHERE workspace_id = ? AND seen = 0")->execute([$wid]);
    } elseif ($action === 'seen') {
        db()->prepare("UPDATE alerts SET seen = 1 WHERE workspace_id = ? AND id = ?")->execute([$wid, (int)($_POST['id'] ?? 0)]);
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'unseen' => alerts_unseen_count($wid)]); exit; }
    } elseif ($action === 'check') {
        $n = rp_evaluate_alerts($wid, true);
        flash('success', $n ? t('فيه %s تنبيه جديد.', $n) : t('اتراجعت القواعد. مفيش تنبيهات جديدة.'));
    } elseif ($action === 'rules') {
        if (!$canManage) {
            flash('error', t('مالكش صلاحية تعدل الإعدادات دي.'));
            redirect(url('alerts'));
        }
        $st = db()->prepare("INSERT INTO alert_rules (workspace_id, rule, enabled, threshold) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), threshold = VALUES(threshold)");
        foreach ($catalog as $k => $r) {
            $th = (float)($_POST['threshold'][$k] ?? $r['default']);
            if ($th < 0) $th = 0;
            if ($th > 100000) $th = 100000;
            $st->execute([$wid, $k, empty($_POST['enabled'][$k]) ? 0 : 1, $th]);
        }
        save_setting('alerts_eval_' . $wid, '0');
        log_activity('alerts.rules');
        flash('success', t('اتحفظ.'));
    } elseif ($action === 'clear') {
        if ($canManage) {
            db()->prepare("DELETE FROM alerts WHERE workspace_id = ? AND seen = 1")->execute([$wid]);
            log_activity('alerts.clear');
        }
    }
    redirect(url('alerts'));
}

$hasApi = (bool)rp_clients($wid);
if ($hasApi) rp_evaluate_alerts($wid);
$rules = rp_rules_for($wid);
$st = db()->prepare("SELECT * FROM alerts WHERE workspace_id = ? ORDER BY created_at DESC LIMIT 100");
$st->execute([$wid]);
$alerts = $st->fetchAll();
$unseen = alerts_unseen_count($wid);
$lastEval = (int)setting('alerts_eval_' . $wid, 0);
// كل قاعدة اشتغلت كام مرة آخر 30 يوم وآخر مرة امتى — عشان تظبط الحد
$stats = [];
$st = db()->prepare("SELECT rule, COUNT(*) n, MAX(created_at) last_at, SUM(created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)) n7 FROM alerts WHERE workspace_id = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) GROUP BY rule");
$st->execute([$wid]);
foreach ($st->fetchAll() as $r) $stats[$r['rule']] = $r;

render(t('التنبيهات'), 'alerts', function () use ($alerts, $rules, $catalog, $canManage, $hasApi, $unseen, $lastEval, $stats) {
    $icons = ['ai_anomaly' => 'spark', 'post_above' => 'trend_up', 'impressions_drop' => 'trend_down', 'follower_drop' => 'users', 'reconnect' => 'plug', 'unread_old' => 'chat'];
    $links = ['post_above' => url('analytics'), 'impressions_drop' => url('analytics'), 'follower_drop' => url('analytics'), 'reconnect' => url('accounts'), 'unread_old' => url('inbox')];
    ?>
    <?php if (!$hasApi): ?><div class="mb-5"><?php no_api_box(); ?></div><?php endif; ?>
    <?php
    $n7 = array_sum(array_map(function ($r) { return (int)$r['n7']; }, $stats));
    $top = null; foreach ($stats as $k => $r) if (!$top || $r['n'] > $stats[$top]['n']) $top = $k;
    $on = count(array_filter($rules, function ($r) { return !empty($r['enabled']); }));
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl border <?= $unseen ? 'border-red-200 bg-red-50/60' : 'border-ink-200 bg-white' ?> p-4"><div class="text-xs text-ink-500"><?= e(t('مش مقروءة')) ?></div><div id="al-unseen" class="text-2xl font-bold mt-1 <?= $unseen ? 'text-red-600' : 'text-ink-900' ?>"><?= (int)$unseen ?></div></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('آخر 7 أيام')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= (int)$n7 ?></div></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('أكتر تنبيه بيتكرر')) ?></div><div class="text-sm font-bold text-ink-900 mt-2 truncate"><?= e($top ? ($catalog[$top]['label'] ?? $top) : '—') ?></div><?php if ($top): ?><div class="text-[11px] text-ink-500"><?= e(t('%s مرة في 30 يوم', (int)$stats[$top]['n'])) ?></div><?php endif; ?></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('قواعد شغالة')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= $on ?> / <?= count($catalog) ?></div></div>
    </div>
    <div class="grid lg:grid-cols-3 gap-5">
      <div class="lg:col-span-2 card p-5">
        <div class="flex flex-wrap items-center gap-2 mb-4">
          <h2 class="font-bold text-ink-900"><?= e(t('آخر التنبيهات')) ?></h2>
          <?php if ($unseen): ?><span class="bg-red-600 text-white text-[11px] rounded-full px-2 py-0.5"><?= (int)$unseen ?></span><?php endif; ?>
          <div class="ms-auto flex gap-2">
            <?php if ($hasApi): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="check"><button class="btn-ghost text-sm"><?= e(t('افحص دلوقتي')) ?></button></form>
            <?php endif; ?>
            <?php if ($unseen): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="seen_all"><button class="btn-ghost text-sm"><?= e(t('علّم الكل كمقروء')) ?></button></form>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($lastEval > 0): ?>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('آخر فحص: %s', rp_fmt(gmdate('c', $lastEval), site_timezone(), 'd/m h:i A'))) ?></p>
        <?php endif; ?>
        <?php if (!$alerts): ?>
          <?= an_empty(t('مفيش تنبيهات'), t('أول ما حاجة من القواعد تحصل هتلاقيها هنا.'), '🔔') ?>
        <?php else: $cnt = []; foreach ($alerts as $a) $cnt[$a['rule']] = ($cnt[$a['rule']] ?? 0) + 1; ?>
          <div id="al-chips" class="flex flex-wrap gap-1.5 mb-3 text-xs">
            <button type="button" data-r="" class="rounded-full px-3 py-1 bg-brand-600 text-white"><?= e(t('الكل')) ?> <span class="opacity-70"><?= count($alerts) ?></span></button>
            <button type="button" data-r="unseen" class="rounded-full px-3 py-1 bg-ink-50 text-ink-700 hover:bg-ink-100"><?= e(t('مش مقروءة')) ?></button>
            <?php foreach ($cnt as $rk => $n): ?><button type="button" data-r="<?= e($rk) ?>" class="rounded-full px-3 py-1 bg-ink-50 text-ink-700 hover:bg-ink-100"><?= e($catalog[$rk]['label'] ?? $rk) ?> <span class="opacity-70"><?= $n ?></span></button><?php endforeach; ?>
          </div>
          <ul class="divide-y divide-ink-100" id="al-list">
            <?php $lastDay = ''; foreach ($alerts as $a): $d = json_decode((string)$a['data'], true) ?: []; $day = fmt_date($a['created_at'] . 'Z', 'Y-m-d'); ?>
              <?php if ($day !== $lastDay): $lastDay = $day; ?><li class="al-day pt-3 pb-1 text-[11px] font-semibold text-ink-400" data-day="<?= e($day) ?>"><?= e($day === fmt_date(gmdate('c'), 'Y-m-d') ? t('النهارده') : ($day === fmt_date(gmdate('c', time() - 86400), 'Y-m-d') ? t('امبارح') : fmt_date($a['created_at'] . 'Z', 'd/m/Y'))) ?></li><?php endif; ?>
              <li class="al py-3 flex gap-3 <?= $a['seen'] ? 'opacity-70' : '' ?>" data-r="<?= e($a['rule']) ?>" data-seen="<?= $a['seen'] ? 1 : 0 ?>" data-day="<?= e($day) ?>">
                <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center <?= ['ai_anomaly' => 'bg-brand-50 text-brand-700', 'post_above' => 'bg-lime-100 text-lime-700', 'impressions_drop' => 'bg-red-100 text-red-700', 'follower_drop' => 'bg-amber-100 text-amber-700', 'reconnect' => 'bg-amber-100 text-amber-700', 'unread_old' => 'bg-cyan-100 text-cyan-700'][$a['rule']] ?? 'bg-ink-100 text-ink-600' ?>"><?= ui_icon($icons[$a['rule']] ?? 'bell', 'w-[18px] h-[18px]') ?></span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm text-ink-800 <?= $a['seen'] ? '' : 'font-semibold' ?> break-words"><?= e(rp_alert_text((string)$a['rule'], $d)) ?></p>
                  <p class="text-xs text-ink-500 mt-0.5">
                    <?= e(fmt_date($a['created_at'] . 'Z', 'd/m h:i A')) ?> ·
                    <?php if (!empty($d['url'])): ?><a class="link" href="<?= e($d['url']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php else: ?><a class="link" href="<?= e($links[$a['rule']] ?? url('dashboard')) ?>"><?= e(t('افتح')) ?></a><?php endif; ?>
                  </p>
                </div>
                <?php if (!$a['seen']): ?>
                  <form method="post" class="al-seen shrink-0"><?= csrf_field() ?><input type="hidden" name="action" value="seen"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="text-xs rounded-full border border-ink-200 px-2.5 py-1 hover:bg-ink-50" title="<?= e(t('اتشاف')) ?>">✓ <?= e(t('اتشاف')) ?></button></form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($canManage): ?>
            <form method="post" class="mt-3" onsubmit="return confirm('<?= tjs('نمسح التنبيهات المقروءة؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="clear">
              <button class="text-xs text-ink-500 hover:text-red-600"><?= e(t('امسح التنبيهات المقروءة')) ?></button></form>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="card p-5">
        <h2 class="font-bold text-ink-900 mb-1"><?= e(t('القواعد')) ?></h2>
        <p class="text-xs text-ink-500 mb-4"><?= e(t('القواعد بتتراجع لما تفتح الصفحة دي (كل 15 دقيقة بالكتير) ومع كل تشغيل للـ Cron.')) ?></p>
        <form method="post" class="space-y-4">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="rules">
          <?php foreach ($catalog as $k => $r): $cur = $rules[$k]; ?>
            <?php $sx = $stats[$k] ?? null; ?>
            <div class="al-rule rounded-xl border border-ink-200 p-3 has-[:checked]:border-brand-200 has-[:checked]:bg-brand-50/30 transition">
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="enabled[<?= e($k) ?>]" value="1" <?= $cur['enabled'] ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?> class="peer sr-only">
                <span class="relative w-10 h-6 shrink-0 rounded-full bg-ink-200 peer-checked:bg-brand-600 transition-colors after:content-[''] after:absolute after:top-1 after:start-1 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:start-5"></span>
                <span class="text-sm font-semibold text-ink-800 flex-1"><?= e($r['label']) ?></span>
              </label>
              <p class="text-xs text-ink-500 mt-1"><?= e($r['hint']) ?></p>
              <p class="text-[11px] mt-1 <?= $sx && $sx['n'] > 20 ? 'text-amber-700' : 'text-ink-400' ?>"><?= e($sx ? t('اشتغلت %s مرة في 30 يوم · آخر مرة %s', (int)$sx['n'], fmt_date($sx['last_at'] . 'Z', 'd/m')) . ($sx['n'] > 20 ? ' — ' . t('كتير؟ ارفع الحد') : '') : t('ما اشتغلتش في آخر 30 يوم')) ?></p>
              <?php if ($r['unit'] !== ''): ?>
                <div class="flex items-center gap-2 mt-2">
                  <input type="number" min="0" step="any" name="threshold[<?= e($k) ?>]" value="<?= e(rtrim(rtrim(number_format($cur['threshold'], 2, '.', ''), '0'), '.')) ?>" class="input py-1.5 w-24" dir="ltr" <?= $canManage ? '' : 'disabled' ?> aria-label="<?= e($r['label']) ?>">
                  <span class="text-xs text-ink-600"><?= e($r['unit']) ?></span>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if ($canManage): ?>
            <button class="btn-primary w-full"><?= e(t('حفظ القواعد')) ?></button>
          <?php else: ?>
            <p class="text-xs text-ink-500"><?= e(t('صاحب الشركة بس اللي يقدر يغير القواعد.')) ?></p>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <script>
    (function () {
      var list = document.getElementById('al-list'); if (!list) return;
      var mode = '', U = document.getElementById('al-unseen');
      function filt() {
        var days = {};
        list.querySelectorAll('.al').forEach(function (li) { var ok = !mode || (mode === 'unseen' ? li.dataset.seen === '0' : li.dataset.r === mode); li.style.display = ok ? '' : 'none'; if (ok) days[li.dataset.day] = 1; });
        list.querySelectorAll('.al-day').forEach(function (d) { d.style.display = days[d.dataset.day] ? '' : 'none'; });
      }
      document.getElementById('al-chips').addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return; mode = b.dataset.r;
        this.querySelectorAll('button').forEach(function (x) { x.className = 'rounded-full px-3 py-1 ' + (x === b ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100'); });
        filt();
      });
      // "اتشاف" من غير ما الصفحة تتحمل تاني
      list.addEventListener('submit', function (e) {
        var f = e.target.closest('.al-seen'); if (!f) return; e.preventDefault();
        var fd = new FormData(f); fd.append('ajax', '1');
        fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
          var li = f.closest('.al'); li.dataset.seen = '1'; li.classList.add('opacity-70'); li.querySelector('p').classList.remove('font-semibold'); f.remove();
          if (U) { U.textContent = d.unseen; }
          var badge = document.querySelector('[data-unseen-badge]'); if (badge) badge.textContent = d.unseen;
          filt();
        }).catch(function () { f.submit(); });
      });
    })();
    </script>
    <?php
});
