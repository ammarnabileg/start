<?php
/** إدارة المنصة: نظرة عامة (لمدير النظام) */
$pdo = db();

$n = function (string $sql, array $p = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int)$st->fetchColumn();
};
$stats = [
    'users' => $n("SELECT COUNT(*) FROM users"),
    'users_suspended' => $n("SELECT COUNT(*) FROM users WHERE status <> 'active'"),
    'active7' => $n("SELECT COUNT(*) FROM users WHERE last_login_at > NOW() - INTERVAL 7 DAY"),
    'new30' => $n("SELECT COUNT(*) FROM users WHERE created_at > NOW() - INTERVAL 30 DAY"),
    'workspaces' => $n("SELECT COUNT(*) FROM workspaces"),
    'ws_suspended' => $n("SELECT COUNT(*) FROM workspaces WHERE status <> 'active'"),
    'ws_unconnected' => $n("SELECT COUNT(*) FROM workspaces w WHERE NOT EXISTS (SELECT 1 FROM connectors c WHERE c.workspace_id = w.id)"),
    'connections' => $n("SELECT COUNT(*) FROM connectors"),
    'actions24' => $n("SELECT COUNT(*) FROM activity_log WHERE created_at > NOW() - INTERVAL 1 DAY"),
    'invites' => $n("SELECT COUNT(*) FROM workspace_users WHERE status = 'pending'") + $n("SELECT COUNT(*) FROM workspace_invites WHERE expires_at > NOW()"),
];

// نشاط آخر 30 يوم: عمليات في اليوم + تسجيلات دخول
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = ['actions' => 0, 'logins' => 0];
}
$st = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c, SUM(action = 'login') l FROM activity_log WHERE created_at > NOW() - INTERVAL 30 DAY GROUP BY DATE(created_at)");
foreach ($st as $r) {
    if (isset($days[$r['d']])) $days[$r['d']] = ['actions' => (int)$r['c'], 'logins' => (int)$r['l']];
}

// أكتر الشركات نشاطاً
$topWs = $pdo->query("SELECT w.id, w.name, COUNT(a.id) c FROM workspaces w LEFT JOIN activity_log a ON a.workspace_id = w.id AND a.created_at > NOW() - INTERVAL 30 DAY GROUP BY w.id ORDER BY c DESC, w.name LIMIT 6")->fetchAll();
$recent = $pdo->query("SELECT a.*, u.name uname, w.name wname FROM activity_log a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN workspaces w ON w.id = a.workspace_id ORDER BY a.id DESC LIMIT 12")->fetchAll();

// صحة السيرفر
$dbSize = 0;
try {
    $dbSize = (int)$pdo->query("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
} catch (Throwable $t) {
}
$mysql = (string)$pdo->query("SELECT VERSION()")->fetchColumn();
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$health = [
    [t('إصدار البرنامج'), APP_VERSION, true],
    [t('قاعدة البيانات محدثة'), setting('app_version') === APP_VERSION ? t('أيوه') : t('لأ، شغّل التحديث'), setting('app_version') === APP_VERSION],
    ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>=')],
    ['MySQL / MariaDB', $mysql, true],
    [t('حجم قاعدة البيانات'), number_format($dbSize / 1048576, 1) . ' MB', true],
    [t('أقصى حجم رفع ملفات'), ini_get('upload_max_filesize'), (int)ini_get('upload_max_filesize') >= 20],
    [t('اتصال آمن (HTTPS)'), $https ? t('شغال') : t('مش شغال'), $https],
    [t('مفتاح التشفير'), !empty(app_config()['app_key']) ? t('موجود') : t('ناقص'), !empty(app_config()['app_key'])],
    [t('ملف config.php محمي من الكتابة'), is_writable(CONFIG_FILE) ? t('قابل للكتابة') : t('محمي'), !is_writable(CONFIG_FILE)],
];

render(t('نظرة عامة'), 'admin', function () use ($stats, $days, $topWs, $recent, $health) {
    $cards = [
        [t('المستخدمين'), $stats['users'], t('%s نشطين آخر 7 أيام · %s موقوفين', $stats['active7'], $stats['users_suspended']), url('admin_users')],
        [t('الشركات'), $stats['workspaces'], t('%s مش مربوطة · %s موقوفة', $stats['ws_unconnected'], $stats['ws_suspended']), url('admin_workspaces')],
        [t('الاتصالات'), $stats['connections'], t('مفاتيح API متخزنة مشفرة'), url('admin_workspaces')],
        [t('العمليات آخر 24 ساعة'), $stats['actions24'], t('%s مستخدم جديد آخر 30 يوم · %s دعوة مفتوحة', $stats['new30'], $stats['invites']), url('admin_activity')],
    ];
    $max = max(1, max(array_column($days, 'actions')));
    ?>
    <div data-ai-insights="admin_ask" class="mb-6"></div>
    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <?php foreach ($cards as [$l, $v, $sub, $href]): ?>
        <a href="<?= e($href) ?>" class="card p-5 hover:border-brand-300 transition">
          <div class="text-sm text-ink-500"><?= e($l) ?></div>
          <div class="text-3xl font-semibold text-ink-900 mt-3"><?= number_format((int)$v) ?></div>
          <div class="text-xs text-ink-500 mt-1"><?= e($sub) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-3 gap-4 mb-6">
      <div class="card p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <b class="text-ink-900"><?= e(t('النشاط آخر 30 يوم')) ?></b>
          <span class="text-xs text-ink-500 flex items-center gap-3">
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-brand-500"></span><?= e(t('عمليات')) ?></span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-lime-500"></span><?= e(t('تسجيلات دخول')) ?></span>
          </span>
        </div>
        <div class="flex items-end gap-1 h-40" dir="ltr">
          <?php foreach ($days as $d => $v): $h = (int)round($v['actions'] / $max * 100); $hl = (int)round($v['logins'] / $max * 100); ?>
            <div class="flex-1 h-full flex flex-col justify-end relative group" title="<?= e($d . ': ' . $v['actions'] . ' / ' . $v['logins']) ?>">
              <div class="w-full rounded-t bg-brand-500/80 group-hover:bg-brand-600" style="height:<?= max($h, $v['actions'] ? 2 : 0) ?>%"></div>
              <div class="absolute bottom-0 inset-x-1 rounded-t bg-lime-500" style="height:<?= max($hl, $v['logins'] ? 2 : 0) ?>%"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="flex justify-between text-[11px] text-ink-400 mt-2" dir="ltr"><span><?= e(array_key_first($days)) ?></span><span><?= e(array_key_last($days)) ?></span></div>
      </div>

      <div class="card p-5">
        <b class="text-ink-900 block mb-4"><?= e(t('أنشط الشركات (30 يوم)')) ?></b>
        <?php $tmax = max(1, (int)($topWs[0]['c'] ?? 1)); foreach ($topWs as $w): ?>
          <div class="mb-3">
            <div class="flex justify-between text-sm"><span class="truncate"><?= e($w['name']) ?></span><span class="text-ink-500"><?= (int)$w['c'] ?></span></div>
            <div class="h-1.5 rounded-full bg-ink-100 mt-1"><div class="h-1.5 rounded-full bg-gradient-to-l from-brand-600 to-cyan-500" style="width:<?= (int)round($w['c'] / $tmax * 100) ?>%"></div></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$topWs): ?><p class="text-sm text-ink-500"><?= e(t('مفيش شركات لسه.')) ?></p><?php endif; ?>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
      <div class="card lg:col-span-2">
        <div class="px-5 py-4 flex items-center justify-between"><b class="text-ink-900"><?= e(t('آخر نشاط')) ?></b><a class="link text-sm" href="<?= e(url('admin_activity')) ?>"><?= e(t('عرض الكل')) ?></a></div>
        <?php foreach ($recent as $a): ?>
          <div class="px-5 py-3 border-t border-ink-100 flex items-center gap-3 text-sm">
            <span class="badge font-mono" dir="ltr"><?= e($a['action']) ?></span>
            <span class="flex-1 min-w-0 truncate text-ink-700"><?= e($a['uname'] ?? t('محذوف')) ?><?php if ($a['wname']): ?> · <span class="text-ink-500"><?= e($a['wname']) ?></span><?php endif; ?><?php if ($a['details']): ?> · <span class="text-ink-400"><?= e(mb_substr($a['details'], 0, 60)) ?></span><?php endif; ?></span>
            <span class="text-xs text-ink-400 whitespace-nowrap"><?= e(substr((string)$a['created_at'], 0, 16)) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$recent): ?><p class="px-5 pb-5 text-sm text-ink-500"><?= e(t('مفيش نشاط لسه.')) ?></p><?php endif; ?>
      </div>

      <div class="card p-5">
        <b class="text-ink-900 block mb-4"><?= e(t('صحة السيرفر')) ?></b>
        <?php foreach ($health as [$l, $v, $ok]): ?>
          <div class="flex items-center gap-2 py-1.5 text-sm">
            <span class="w-2 h-2 rounded-full <?= $ok ? 'bg-lime-500' : 'bg-amber-500' ?>"></span>
            <span class="flex-1 text-ink-600"><?= e($l) ?></span>
            <span class="text-ink-900 font-medium" dir="ltr"><?= e($v) ?></span>
          </div>
        <?php endforeach; ?>
        <a href="setup.php" class="btn-ghost w-full mt-4"><?= e(t('صفحة التحديث')) ?></a>
      </div>
    </div>
    <?php
});
