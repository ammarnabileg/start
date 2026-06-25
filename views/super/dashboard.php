<?php /** @var array $stats @var array[] $recentTenants @var Request $req */ ?>
<meta name="csrf" content="<?= htmlspecialchars($req->csrf()) ?>">

<div class="space-y-6">
  <h1 class="text-2xl font-bold text-gray-900">Platform Dashboard</h1>

  <!-- Stats -->
  <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
    <?php
    $cards = [
      ['label'=>'Total Companies','value'=>$stats['total_tenants']??0,'color'=>'blue'],
      ['label'=>'Active','value'=>$stats['active_tenants']??0,'color'=>'green'],
      ['label'=>'Total Users','value'=>$stats['total_users']??0,'color'=>'purple'],
      ['label'=>'Applications','value'=>$stats['total_applications']??0,'color'=>'orange'],
      ['label'=>'AI Calls','value'=>$stats['total_ai_calls']??0,'color'=>'pink'],
      ['label'=>'Total Tokens','value'=>number_format($stats['total_tokens']??0),'color'=>'yellow'],
    ];
    foreach ($cards as $c): ?>
    <div class="bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
      <p class="text-xs font-medium text-gray-500 uppercase tracking-wide"><?= $c['label'] ?></p>
      <p class="text-2xl font-bold text-gray-900 mt-1"><?= $c['value'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent Companies -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-100">
      <h2 class="font-semibold text-gray-900">Recent Companies</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Company</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Slug</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Users</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Joined</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($recentTenants)): ?>
            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No companies yet.</td></tr>
          <?php else: ?>
            <?php foreach ($recentTenants as $t): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <a href="/super/companies" class="font-medium text-blue-600 hover:underline"><?= htmlspecialchars($t['name']) ?></a>
              </td>
              <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($t['slug']) ?></td>
              <td class="px-6 py-4 text-sm text-gray-700"><?= $t['user_count'] ?></td>
              <td class="px-6 py-4">
                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $t['status']==='active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                  <?= ucfirst($t['status']) ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-500"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
