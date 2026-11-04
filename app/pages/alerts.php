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
$st = db()->prepare("SELECT * FROM alerts WHERE workspace_id = ? ORDER BY seen ASC, created_at DESC LIMIT 100");
$st->execute([$wid]);
$alerts = $st->fetchAll();
$unseen = alerts_unseen_count($wid);
$lastEval = (int)setting('alerts_eval_' . $wid, 0);

render(t('التنبيهات'), 'alerts', function () use ($alerts, $rules, $catalog, $canManage, $hasApi, $unseen, $lastEval) {
    $icons = ['ai_anomaly' => '⚡', 'post_above' => '🚀', 'impressions_drop' => '📉', 'follower_drop' => '👥', 'reconnect' => '🔌', 'unread_old' => '💬'];
    $links = ['post_above' => url('analytics'), 'impressions_drop' => url('analytics'), 'follower_drop' => url('analytics'), 'reconnect' => url('accounts'), 'unread_old' => url('inbox')];
    ?>
    <?php if (!$hasApi): ?><div class="mb-5"><?php no_api_box(); ?></div><?php endif; ?>
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
        <?php else: ?>
          <ul class="divide-y divide-ink-100">
            <?php foreach ($alerts as $a): $d = json_decode((string)$a['data'], true) ?: []; ?>
              <li class="py-3 flex gap-3 <?= $a['seen'] ? 'opacity-70' : '' ?>">
                <span class="text-xl shrink-0" aria-hidden="true"><?= $icons[$a['rule']] ?? '🔔' ?></span>
                <div class="min-w-0 flex-1">
                  <p class="text-sm text-ink-800 <?= $a['seen'] ? '' : 'font-semibold' ?> break-words"><?= e(rp_alert_text((string)$a['rule'], $d)) ?></p>
                  <p class="text-xs text-ink-500 mt-0.5">
                    <?= e(fmt_date($a['created_at'] . 'Z', 'd/m h:i A')) ?> ·
                    <?php if (!empty($d['url'])): ?><a class="link" href="<?= e($d['url']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php else: ?><a class="link" href="<?= e($links[$a['rule']] ?? url('dashboard')) ?>"><?= e(t('افتح')) ?></a><?php endif; ?>
                  </p>
                </div>
                <?php if (!$a['seen']): ?>
                  <form method="post" class="shrink-0"><?= csrf_field() ?><input type="hidden" name="action" value="seen"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="text-xs link"><?= e(t('اتشاف')) ?></button></form>
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
            <div class="rounded-xl border border-ink-200 p-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="enabled[<?= e($k) ?>]" value="1" <?= $cur['enabled'] ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?> class="w-4 h-4 accent-brand-600">
                <span class="text-sm font-semibold text-ink-800"><?= e($r['label']) ?></span>
              </label>
              <p class="text-xs text-ink-500 mt-1"><?= e($r['hint']) ?></p>
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
    <?php
});
