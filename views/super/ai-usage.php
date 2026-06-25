<?php /** @var array[] $perTenant @var array[] $perFeature @var array[] $daily @var Request $req */ ?>
<meta name="csrf" content="<?= htmlspecialchars($req->csrf()) ?>">

<div class="space-y-6">
  <h1 class="text-2xl font-bold text-gray-900">AI Usage Analytics</h1>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Per Tenant -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <div class="p-6 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Usage by Company</h2></div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Company</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase">Tokens</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase">Calls</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($perTenant)): ?>
              <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No usage data.</td></tr>
            <?php else: ?>
              <?php foreach ($perTenant as $row): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['tenant_name']) ?></td>
                <td class="px-4 py-3 text-sm text-right text-gray-700"><?= number_format($row['total_tokens']) ?></td>
                <td class="px-4 py-3 text-sm text-right text-gray-500"><?= number_format($row['total_calls']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Per Feature -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
      <div class="p-6 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Usage by Feature</h2></div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Feature</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase">Tokens</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase">Calls</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($perFeature)): ?>
              <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No usage data.</td></tr>
            <?php else: ?>
              <?php foreach ($perFeature as $row): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['feature']) ?></td>
                <td class="px-4 py-3 text-sm text-right text-gray-700"><?= number_format($row['total_tokens']) ?></td>
                <td class="px-4 py-3 text-sm text-right text-gray-500"><?= number_format($row['total_calls']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Daily usage (last 30 days) -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="font-semibold text-gray-900 mb-4">Daily Usage (Last 30 Days)</h2>
    <?php if (empty($daily)): ?>
      <p class="text-gray-400 text-sm">No data yet.</p>
    <?php else: ?>
      <?php $maxTokens = max(array_column($daily, 'total_tokens') ?: [1]); ?>
      <div class="flex items-end gap-1 h-32 overflow-x-auto">
        <?php foreach (array_reverse($daily) as $d): ?>
          <?php $h = max(4, ($d['total_tokens'] / $maxTokens) * 100); ?>
          <div class="flex flex-col items-center gap-1 flex-shrink-0" style="min-width:24px" title="<?= $d['date'] ?>: <?= number_format($d['total_tokens']) ?> tokens">
            <div class="bg-blue-500 rounded-t w-5" style="height:<?= $h ?>%"></div>
            <span class="text-xs text-gray-400 rotate-45 origin-left" style="font-size:9px"><?= substr($d['date'], 5) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
