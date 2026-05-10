<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('activities.view');

$pageTitle = 'سجل الأنشطة';
$user = $_GET['user'] ?? '';
$action = $_GET['action'] ?? '';

$where = '1';
$params = [];
if ($user)   { $where .= ' AND a.user_id = :uid'; $params['uid'] = (int)$user; }
if ($action) { $where .= ' AND a.action = :act'; $params['act'] = $action; }

$rows = db_all("
    SELECT a.*, u.name AS user_name
    FROM " . tbl('activities') . " a
    LEFT JOIN " . tbl('users') . " u ON u.id = a.user_id
    WHERE $where
    ORDER BY a.created_at DESC
    LIMIT 500
", $params);

$users = db_all('SELECT id, name FROM ' . tbl('users') . ' ORDER BY name');
require __DIR__ . '/../../includes/header.php';
?>

<form method="get" class="bg-white p-4 rounded-xl border mb-4 flex gap-2 flex-wrap">
  <select name="user" class="px-3 py-2 border rounded-lg">
    <option value="">كل المستخدمين</option>
    <?php foreach ($users as $u): ?>
      <option value="<?= $u['id'] ?>" <?= (string)$user === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="action" class="px-3 py-2 border rounded-lg">
    <option value="">كل الأحداث</option>
    <?php foreach (['login','logout','create','update','delete','complete'] as $a): ?>
      <option value="<?= $a ?>" <?= $action === $a ? 'selected' : '' ?>><?= $a ?></option>
    <?php endforeach; ?>
  </select>
  <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg">تصفية</button>
</form>

<div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b">
      <tr>
        <th class="text-right p-3">الوقت</th>
        <th class="text-right p-3">المستخدم</th>
        <th class="text-right p-3">الحدث</th>
        <th class="text-right p-3">الكيان</th>
        <th class="text-right p-3">التفاصيل</th>
        <th class="text-right p-3">IP</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($rows as $r): ?>
        <tr class="hover:bg-gray-50">
          <td class="p-2 text-gray-500"><?= format_date($r['created_at']) ?></td>
          <td class="p-2 font-medium"><?= e($r['user_name'] ?? 'النظام') ?></td>
          <td class="p-2"><?= e($r['action']) ?></td>
          <td class="p-2"><?= $r['entity_type'] ? e($r['entity_type']) . ' #' . (int)$r['entity_id'] : '—' ?></td>
          <td class="p-2 text-gray-500 max-w-md truncate"><?= e($r['details'] ?? '') ?></td>
          <td class="p-2 text-gray-400 text-xs"><?= e($r['ip'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center p-8 text-gray-500">لا توجد أنشطة.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
