<?php
require_once __DIR__ . '/../../includes/auth.php';
if (!has_any_perm(['clients.view.own', 'clients.view.all', 'clients.manage'])) require_perm('clients.view.own');

$id = (int)($_GET['id'] ?? 0);
$client = db_one('
    SELECT c.*, u.name AS owner_name
    FROM ' . tbl('clients') . ' c
    LEFT JOIN ' . tbl('users') . ' u ON u.id = c.owner_id
    WHERE c.id = :id
', ['id' => $id]);
if (!$client) { flash('error', 'العميل غير موجود.'); redirect('modules/clients/'); }

// Scope check
if (!has_perm('clients.view.all') && !has_perm('clients.manage') && (int)$client['owner_id'] !== auth_id()) {
    require_perm('clients.view.all'); // forbidden page
}

$deals = db_all('SELECT * FROM ' . tbl('deals') . ' WHERE client_id = :id ORDER BY created_at DESC', ['id' => $id]);
$tasks = db_all('SELECT * FROM ' . tbl('tasks') . ' WHERE related_type = "client" AND related_id = :id ORDER BY (status="done"), due_at ASC', ['id' => $id]);
$contacts = db_all('SELECT * FROM ' . tbl('contacts') . ' WHERE client_id = :id ORDER BY is_primary DESC, name', ['id' => $id]);

$canManage = has_perm('clients.manage');
$pageTitle = $client['name'];
$stages = ['lead'=>'بداية','qualified'=>'مؤهل','active'=>'نشط','closed'=>'مغلق','lost'=>'ضائع'];
$dealStages = ['lead'=>'بداية','qualified'=>'مؤهل','proposal'=>'عرض','negotiation'=>'تفاوض','won'=>'فوز','lost'=>'خسارة'];
require __DIR__ . '/../../includes/header.php';

// Add contact handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['contact_action'] ?? '') === 'add' && $canManage) {
    csrf_check();
    db_insert(tbl('contacts'), [
        'client_id' => $id,
        'name'      => trim($_POST['c_name'] ?? ''),
        'role'      => trim($_POST['c_role'] ?? '') ?: null,
        'phone'     => trim($_POST['c_phone'] ?? '') ?: null,
        'email'     => trim($_POST['c_email'] ?? '') ?: null,
        'is_primary'=> empty($contacts) ? 1 : 0,
    ]);
    flash('success', 'تمت إضافة جهة الاتصال.');
    redirect('modules/clients/view.php?id=' . $id);
}
?>

<div class="flex justify-between items-start mb-6">
  <div>
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-bold"><?= e($client['name']) ?></h1>
      <span class="bg-emerald-100 text-emerald-700 text-xs px-3 py-1 rounded-full"><?= e($stages[$client['stage']] ?? '') ?></span>
    </div>
    <p class="text-gray-500 mt-1 text-sm"><?= e($client['industry'] ?? '') ?> · <?= e($client['city'] ?? '') ?> <?= e($client['country'] ?? '') ?></p>
  </div>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/clients/edit.php?id=' . $id) ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">تعديل</a>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <div class="bg-white p-6 rounded-xl shadow-sm border">
      <h2 class="font-bold mb-3">معلومات</h2>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div><span class="text-gray-500">الهاتف:</span> <?= e($client['phone'] ?? '—') ?></div>
        <div><span class="text-gray-500">البريد:</span> <?= e($client['email'] ?? '—') ?></div>
        <div><span class="text-gray-500">الموقع:</span> <?= $client['website'] ? '<a class="text-emerald-600 hover:underline" href="' . e($client['website']) . '" target="_blank">' . e($client['website']) . '</a>' : '—' ?></div>
        <div><span class="text-gray-500">المسؤول:</span> <?= e($client['owner_name']) ?></div>
        <div><span class="text-gray-500">القيمة:</span> <?= format_money($client['value']) ?></div>
        <div><span class="text-gray-500">الإنشاء:</span> <?= format_date($client['created_at'], 'Y-m-d') ?></div>
      </div>
      <?php if ($client['notes']): ?>
        <div class="mt-4 pt-4 border-t">
          <div class="text-sm text-gray-500 mb-1">ملاحظات:</div>
          <p class="text-sm whitespace-pre-line"><?= e($client['notes']) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border">
      <div class="flex justify-between items-center mb-3">
        <h2 class="font-bold">الصفقات (<?= count($deals) ?>)</h2>
        <?php if (has_perm('deals.manage')): ?>
          <a href="<?= url('modules/deals/create.php?client_id=' . $id) ?>" class="text-emerald-600 hover:underline text-sm">+ صفقة</a>
        <?php endif; ?>
      </div>
      <?php if (!$deals): ?>
        <p class="text-gray-500 text-sm">لا توجد صفقات.</p>
      <?php else: ?>
        <ul class="divide-y">
          <?php foreach ($deals as $d): ?>
            <li class="py-2 flex justify-between text-sm">
              <a href="<?= url('modules/deals/edit.php?id=' . $d['id']) ?>" class="text-emerald-600 hover:underline"><?= e($d['title']) ?></a>
              <span class="text-gray-500"><?= e($dealStages[$d['stage']] ?? '') ?> · <?= format_money($d['amount'], $d['currency']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border">
      <div class="flex justify-between items-center mb-3">
        <h2 class="font-bold">المهام (<?= count($tasks) ?>)</h2>
        <?php if (has_perm('tasks.manage')): ?>
          <a href="<?= url('modules/tasks/create.php?related_type=client&related_id=' . $id) ?>" class="text-emerald-600 hover:underline text-sm">+ مهمة</a>
        <?php endif; ?>
      </div>
      <?php if (!$tasks): ?>
        <p class="text-gray-500 text-sm">لا توجد مهام.</p>
      <?php else: ?>
        <ul class="divide-y">
          <?php foreach ($tasks as $t): ?>
            <li class="py-2 flex justify-between text-sm">
              <a href="<?= url('modules/tasks/edit.php?id=' . $t['id']) ?>" class="text-emerald-600 hover:underline <?= $t['status'] === 'done' ? 'line-through text-gray-400' : '' ?>"><?= e($t['title']) ?></a>
              <span class="text-gray-500"><?= e($t['status']) ?> <?= $t['due_at'] ? '· ' . format_date($t['due_at'], 'Y-m-d') : '' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="bg-white p-6 rounded-xl shadow-sm border">
      <h2 class="font-bold mb-3">جهات الاتصال (<?= count($contacts) ?>)</h2>
      <?php foreach ($contacts as $c): ?>
        <div class="border-b py-2 text-sm">
          <div class="font-medium"><?= e($c['name']) ?> <?= $c['is_primary'] ? '<span class="text-xs bg-emerald-100 text-emerald-700 px-1 rounded">رئيسي</span>' : '' ?></div>
          <div class="text-gray-500 text-xs"><?= e($c['role'] ?? '') ?></div>
          <div class="text-xs"><?= e($c['phone'] ?? '') ?> <?= e($c['email'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>

      <?php if ($canManage): ?>
        <details class="mt-4">
          <summary class="cursor-pointer text-emerald-600 text-sm">+ إضافة جهة اتصال</summary>
          <form method="post" class="mt-3 space-y-2">
            <?= csrf_field() ?>
            <input type="hidden" name="contact_action" value="add">
            <input name="c_name" required placeholder="الاسم" class="w-full px-3 py-2 border rounded text-sm">
            <input name="c_role" placeholder="المنصب" class="w-full px-3 py-2 border rounded text-sm">
            <input name="c_phone" placeholder="هاتف" class="w-full px-3 py-2 border rounded text-sm">
            <input name="c_email" type="email" placeholder="بريد" class="w-full px-3 py-2 border rounded text-sm">
            <button class="w-full bg-emerald-600 text-white py-2 rounded text-sm">إضافة</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
