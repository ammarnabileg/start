<?php
/** مقارنة كل الشركات جنب بعض (لمدير النظام بس) */
if (!is_admin()) {
    redirect(url('dashboard'));
}

$maxApi = 30;     // أقصى عدد شركات بنكلم الـ API بتاعها في الفتحة الواحدة
$maxConn = 3;     // أقصى عدد اتصالات لكل شركة
$from = gmdate('Y-m-d', time() - 29 * 86400);
$to = gmdate('Y-m-d');

$ws = db()->query("SELECT w.id, w.name, COALESCE(w.status, 'active') AS status,
    (SELECT COUNT(*) FROM workspace_users wu WHERE wu.workspace_id = w.id AND wu.status = 'active') AS team,
    (SELECT MAX(created_at) FROM activity_log a WHERE a.workspace_id = w.id) AS last_activity,
    (SELECT COUNT(*) FROM connectors c WHERE c.workspace_id = w.id AND c.provider = 'zernio') AS conns
    FROM workspaces w ORDER BY w.name")->fetchAll();

$rows = [];
$apiUsed = 0;
foreach ($ws as $w) {
    $wid = (int)$w['id'];
    $r = $w + ['connected' => false, 'skipped' => false, 'accounts' => null, 'posts' => null, 'impressions' => null, 'engagement' => null, 'unread' => null, 'error' => null];
    $clients = (int)$w['conns'] > 0 ? array_slice(rp_clients($wid), 0, $maxConn) : [];
    if ($clients) {
        $r['connected'] = true;
        if ($apiUsed >= $maxApi) {
            $r['skipped'] = true;
        } else {
            $apiUsed++;
            foreach ($clients as $c) {
                $z = $c['z']; $pid = $c['pid'];
                [$acc, $e1] = an_try(function () use ($z, $pid) { return $z->request('GET', '/v1/accounts', array_filter(['profileId' => $pid]))['accounts'] ?? []; });
                if (!$e1) $r['accounts'] = (int)$r['accounts'] + count((array)$acc);
                [$d, $e2] = an_try(function () use ($z, $pid, $from, $to) { return an_daily_metrics($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to]); });
                if (!$e2) {
                    foreach ((array)$d as $row) {
                        $m = (array)($row['metrics'] ?? []);
                        $r['impressions'] = (float)$r['impressions'] + (float)($m['impressions'] ?? 0);
                        $r['engagement'] = (float)$r['engagement'] + an_engagement($m);
                        $r['posts'] = (int)$r['posts'] + (int)($row['postCount'] ?? 0);
                    }
                    if ($r['impressions'] === null) { $r['impressions'] = 0; $r['engagement'] = 0; $r['posts'] = 0; }
                }
                [$cv, $e3] = an_try(function () use ($z, $pid) { return $z->conversations(['limit' => 50, 'profileId' => $pid]); });
                if (!$e3) {
                    $r['unread'] = (int)$r['unread'];
                    foreach ((array)($cv['data'] ?? []) as $conv) $r['unread'] += (int)($conv['unreadCount'] ?? 0);
                }
                $r['error'] = $r['error'] ?: ($e1 ?: ($e2 ?: $e3));
            }
        }
    }
    $rows[] = $r;
}

render(t('مقارنة الشركات'), 'admin_compare', function () use ($rows, $from, $to, $maxApi) {
    $cols = [
        'accounts' => [t('حسابات مربوطة'), 'bg-cyan-500'],
        'posts' => [t('منشورات (30 يوم)'), 'bg-ink-500'],
        'impressions' => [t('مرات الظهور (30 يوم)'), 'bg-brand-600'],
        'engagement' => [t('التفاعل (30 يوم)'), 'bg-lime-500'],
        'unread' => [t('رسائل غير مقروءة'), 'bg-red-500'],
        'team' => [t('أعضاء الفريق'), 'bg-amber-500'],
    ];
    $skipped = count(array_filter($rows, function ($r) { return $r['skipped']; }));
    ?>
    <p class="text-sm text-ink-500 mb-4"><?= e(t('آخر 30 يوم:')) ?> <bdi dir="ltr"><?= e(date('d/m/Y', strtotime($from)) . ' – ' . date('d/m/Y', strtotime($to))) ?></bdi> · <?= e(t('%s شركة', count($rows))) ?></p>
    <?php if ($skipped): ?>
      <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-3 text-sm mb-4"><?= e(t('عشان الصفحة ما تتقلش، بنجيب بيانات أول %s شركة مربوطة بس. %s شركة اتسابت.', $maxApi, $skipped)) ?></div>
    <?php endif; ?>

    <div class="card overflow-x-auto mb-6">
      <table class="w-full text-sm">
        <thead class="text-xs text-ink-500 border-b border-ink-200">
          <tr>
            <th class="text-start py-3 px-4"><?= e(t('الشركة')) ?></th>
            <?php foreach ($cols as $k => $c): ?><th class="text-end py-3 px-3 whitespace-nowrap"><?= e($c[0]) ?></th><?php endforeach; ?>
            <th class="text-end py-3 px-4 whitespace-nowrap"><?= e(t('آخر نشاط')) ?></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink-100">
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="py-3 px-4">
                <p class="font-semibold text-ink-900"><?= e($r['name']) ?></p>
                <p class="text-[11px]">
                  <?php if (!$r['connected']): ?><span class="text-ink-400"><?= e(t('مش مربوط')) ?></span>
                  <?php elseif ($r['skipped']): ?><span class="text-amber-700"><?= e(t('ما اتجابتش البيانات')) ?></span>
                  <?php elseif ($r['error']): ?><span class="text-red-600" title="<?= e($r['error']) ?>"><?= e(t('بيانات ناقصة')) ?></span>
                  <?php else: ?><span class="text-lime-700"><?= e(t('مربوط')) ?></span><?php endif; ?>
                  <?php if ($r['status'] !== 'active'): ?> · <span class="text-red-600"><?= e(t('موقوفة')) ?></span><?php endif; ?>
                </p>
              </td>
              <?php foreach ($cols as $k => $c): $v = $r[$k]; ?>
                <td class="py-3 px-3 text-end tabular-nums text-ink-800"><?= $v === null ? '<span class="text-ink-400">—</span>' : e(an_num($v)) ?></td>
              <?php endforeach; ?>
              <td class="py-3 px-4 text-end text-xs text-ink-500 whitespace-nowrap"><?= $r['last_activity'] ? e(fmt_date($r['last_activity'] . 'Z', 'd/m/Y h:i A')) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
      <?php foreach ($cols as $k => $c):
          $list = array_filter($rows, function ($r) use ($k) { return $r[$k] !== null; });
          usort($list, function ($a, $b) use ($k) { return $b[$k] <=> $a[$k]; });
          $list = array_slice($list, 0, 10);
          $mx = 0; foreach ($list as $r) $mx = max($mx, (float)$r[$k]); ?>
        <div class="card p-5">
          <h3 class="font-semibold text-ink-900 mb-3"><?= e($c[0]) ?></h3>
          <?php if (!$list): ?>
            <p class="text-sm text-ink-500"><?= e(t('مفيش بيانات.')) ?></p>
          <?php else: ?>
            <div class="space-y-2.5">
              <?php foreach ($list as $r): ?>
                <div>
                  <div class="flex justify-between text-xs mb-1"><span class="text-ink-700 truncate"><?= e($r['name']) ?></span><span class="text-ink-500 tabular-nums"><?= e(an_num($r[$k])) ?></span></div>
                  <?= an_hbar((float)$r[$k], $mx, $c[1]) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
});
