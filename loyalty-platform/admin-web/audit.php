<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('audit', 'view');

$total = (int) scalar("select count(*) from admin.audit");
$page = page_num(); $off = ($page-1)*per_page();
$rows = all("select * from admin.audit order by created_at desc limit ".per_page()." offset $off");

$title = 'سجلّ التدقيق';
require __DIR__ . '/partials/header.php';
?>
<div class="bg-white rounded-xl border overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-gray-500 text-right"><tr>
      <th class="px-4 py-3 font-medium">الوقت</th><th class="px-4 py-3 font-medium">المسؤول</th>
      <th class="px-4 py-3 font-medium">الإجراء</th><th class="px-4 py-3 font-medium">الكيان</th>
      <th class="px-4 py-3 font-medium">تفاصيل</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="border-t">
        <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap"><?= dt($r['created_at']) ?></td>
        <td class="px-4 py-2.5 font-bold"><?= e($r['admin_name'] ?: '—') ?></td>
        <td class="px-4 py-2.5"><?= badge($r['action'],'blue') ?></td>
        <td class="px-4 py-2.5 text-gray-600"><?= e($r['entity'] ?: '—') ?><?php if ($r['entity_id']): ?><span class="text-xs text-gray-300"> #<?= e(substr($r['entity_id'],0,8)) ?></span><?php endif; ?></td>
        <td class="px-4 py-2.5 text-gray-500 text-xs font-mono ltr text-left"><?= e($r['meta'] ?: '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">لا سجلات.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= pager($total, $page, '') ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
