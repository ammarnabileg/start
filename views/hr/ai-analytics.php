<?php
$pageTitle = 'AI Analytics';
$db   = Database::getInstance();
$tid  = Auth::user()['tenant_id'];
$days = (int)($_GET['days'] ?? 30);
$since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

$summary = Cache::remember("ai_summary_{$tid}_{$days}", 300, function() use ($db, $tid, $since) {
    return [
        'total_requests' => (int)$db->fetchColumn("SELECT COUNT(*) FROM ai_usage_logs WHERE tenant_id=? AND created_at>=?", [$tid, $since]) ?: 0,
        'total_tokens'   => (int)$db->fetchColumn("SELECT COALESCE(SUM(tokens_used),0) FROM ai_usage_logs WHERE tenant_id=? AND created_at>=?", [$tid, $since]) ?: 0,
        'total_cost'     => round(((int)$db->fetchColumn("SELECT COALESCE(SUM(tokens_used),0) FROM ai_usage_logs WHERE tenant_id=? AND created_at>=?", [$tid, $since]) ?: 0) / 1000 * 0.002, 4),
        'by_feature'     => $db->fetchAll("SELECT action_type, COUNT(*) as cnt, SUM(tokens_used) as tokens FROM ai_usage_logs WHERE tenant_id=? AND created_at>=? GROUP BY action_type ORDER BY cnt DESC LIMIT 10", [$tid, $since]) ?: [],
        'recent'         => $db->fetchAll("SELECT l.*, u.full_name as user_name FROM ai_usage_logs l LEFT JOIN users u ON u.id=l.user_id WHERE l.tenant_id=? ORDER BY l.created_at DESC LIMIT 20", [$tid]) ?: []
    ];
});
?>

<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">AI Analytics</h1>
      <p class="text-gray-500 text-sm mt-1">Monitor AI usage and costs for your team</p>
    </div>
    <div class="flex gap-2">
      <?php foreach ([7,30,90] as $d): ?>
      <a href="?days=<?= $d ?>"
        class="px-4 py-2 rounded-full text-sm font-medium <?= $days===$d ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        <?= $d ?> days
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['label'=>'Total Requests',  'value'=>number_format($summary['total_requests']),   'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color'=>'violet'],
        ['label'=>'Tokens Used',     'value'=>number_format($summary['total_tokens']),     'icon'=>'M13 10V3L4 14h7v7l9-11h-7z',                                                                                                        'color'=>'blue'],
        ['label'=>'Estimated Cost',  'value'=>'$'.number_format($summary['total_cost'],4), 'icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color'=>'emerald'],
        ['label'=>'Most Used',       'value'=>$summary['by_feature'][0]['action_type'] ?? '—', 'icon'=>'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', 'color'=>'amber']
    ];
    foreach ($cards as $c):
    $bg = "bg-{$c['color']}-100"; $ic = "text-{$c['color']}-600";
    ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-xl <?= $bg ?> flex items-center justify-center">
          <svg class="w-5 h-5 <?= $ic ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $c['icon'] ?>"/></svg>
        </div>
        <span class="text-sm text-gray-500"><?= $c['label'] ?></span>
      </div>
      <p class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($c['value']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Usage by feature -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Usage by Feature</h3>
      <?php if (empty($summary['by_feature'])): ?>
      <p class="text-gray-500 text-sm">No AI usage in this period.</p>
      <?php else: ?>
      <div class="space-y-3">
        <?php
        $max = max(array_column($summary['by_feature'], 'cnt'));
        foreach ($summary['by_feature'] as $f):
        $pct = $max > 0 ? round($f['cnt'] / $max * 100) : 0;
        $label = str_replace('_', ' ', $f['action_type']);
        $cost  = round(($f['tokens'] ?? 0) / 1000 * 0.002, 4);
        ?>
        <div>
          <div class="flex items-center justify-between text-sm mb-1">
            <span class="font-medium text-gray-700 capitalize"><?= htmlspecialchars($label) ?></span>
            <span class="text-gray-500"><?= number_format($f['cnt']) ?> reqs · $<?= $cost ?></span>
          </div>
          <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-violet-500 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Cost breakdown -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Cost Summary</h3>
      <div class="space-y-3">
        <div class="flex justify-between items-center py-2 border-b border-gray-50">
          <span class="text-sm text-gray-600">Total Tokens</span>
          <span class="font-semibold"><?= number_format($summary['total_tokens']) ?></span>
        </div>
        <div class="flex justify-between items-center py-2 border-b border-gray-50">
          <span class="text-sm text-gray-600">Rate (GPT-4o-mini)</span>
          <span class="font-semibold">$0.002 / 1K</span>
        </div>
        <div class="flex justify-between items-center py-2">
          <span class="text-sm text-gray-600 font-medium">Estimated Total</span>
          <span class="text-lg font-bold text-violet-600">$<?= number_format($summary['total_cost'], 4) ?></span>
        </div>
      </div>
      <div class="mt-4 bg-violet-50 rounded-xl p-3">
        <p class="text-xs text-violet-700">Cost estimates are based on token counts and may vary by model. Actual billing is through your OpenAI account.</p>
      </div>
    </div>
  </div>

  <!-- Recent logs -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-4 border-b border-gray-100">
      <h3 class="font-semibold text-gray-900">Recent AI Activity</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tokens</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($summary['recent'])): ?>
          <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">No AI activity yet</td></tr>
          <?php else: foreach ($summary['recent'] as $log): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-xs text-gray-500"><?= date('M j, H:i', strtotime($log['created_at'])) ?></td>
            <td class="px-4 py-3 text-sm text-gray-800 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $log['action_type'])) ?></td>
            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
            <td class="px-4 py-3"><span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"><?= htmlspecialchars($log['model_used'] ?? 'gpt-4o-mini') ?></span></td>
            <td class="px-4 py-3 text-sm text-right font-mono"><?= number_format((int)$log['tokens_used']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
