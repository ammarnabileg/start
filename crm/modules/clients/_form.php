<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_perm('clients.manage');

$id = (int)($_GET['id'] ?? 0);
$client = $id ? db_one('SELECT * FROM ' . tbl('clients') . ' WHERE id = :id', ['id' => $id]) : null;
if ($id && !$client) { flash('error', 'العميل غير موجود.'); redirect('modules/clients/'); }

$users = db_all('SELECT id, name FROM ' . tbl('users') . " WHERE status='active' ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'delete' && $client) {
        db_delete(tbl('clients'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'client', $id, null);
        flash('success', 'تم حذف العميل.');
        redirect('modules/clients/');
    }

    $data = [
        'name'     => trim($_POST['name'] ?? ''),
        'type'     => $_POST['type'] ?? 'company',
        'industry' => trim($_POST['industry'] ?? '') ?: null,
        'country'  => trim($_POST['country'] ?? '') ?: null,
        'city'     => trim($_POST['city'] ?? '') ?: null,
        'phone'    => trim($_POST['phone'] ?? '') ?: null,
        'email'    => trim($_POST['email'] ?? '') ?: null,
        'website'  => trim($_POST['website'] ?? '') ?: null,
        'owner_id' => (int)($_POST['owner_id'] ?? auth_id()),
        'stage'    => $_POST['stage'] ?? 'lead',
        'value'    => (float)($_POST['value'] ?? 0),
        'notes'    => trim($_POST['notes'] ?? '') ?: null,
    ];

    if ($data['name'] === '')  $errors[] = 'الاسم مطلوب';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد غير صحيح';
    if (!$data['owner_id']) $errors[] = 'المسؤول مطلوب';

    if (!$errors) {
        if ($client) {
            db_update(tbl('clients'), $data, 'id = :id', ['id' => $id]);
            activity_log('update', 'client', $id, ['name' => $data['name']]);
            flash('success', 'تم تحديث العميل.');
            redirect('modules/clients/view.php?id=' . $id);
        } else {
            $newId = db_insert(tbl('clients'), $data);
            activity_log('create', 'client', (int)$newId, ['name' => $data['name']]);
            event_fire('client.created', 'client', (int)$newId, [], (int)$data['owner_id']);
            flash('success', 'تم إنشاء العميل.');
            redirect('modules/clients/view.php?id=' . $newId);
        }
    }
    $client = array_merge((array)$client, $data);
}

$pageTitle = $client ? 'تعديل: ' . $client['name'] : 'عميل جديد';
$stages = ['lead'=>'بداية','qualified'=>'مؤهل','active'=>'نشط','closed'=>'مغلق','lost'=>'ضائع'];
require __DIR__ . '/../../includes/header.php';
?>

<form method="post" class="bg-white rounded-xl shadow-sm border p-6 max-w-4xl">
  <?= csrf_field() ?>
  <?php if ($errors): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm">
      <ul class="list-disc list-inside"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-2 gap-4">
    <div class="col-span-2">
      <label class="block text-sm mb-1">الاسم *</label>
      <input name="name" required value="<?= e($client['name'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">النوع</label>
      <select name="type" class="w-full px-3 py-2 border rounded-lg">
        <option value="company"    <?= ($client['type'] ?? '') === 'company' ? 'selected' : '' ?>>شركة</option>
        <option value="individual" <?= ($client['type'] ?? '') === 'individual' ? 'selected' : '' ?>>فرد</option>
        <option value="partner"    <?= ($client['type'] ?? '') === 'partner' ? 'selected' : '' ?>>شريك</option>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">المرحلة</label>
      <select name="stage" class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($stages as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($client['stage'] ?? 'lead') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">القطاع</label>
      <input name="industry" value="<?= e($client['industry'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">المسؤول *</label>
      <select name="owner_id" required class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ((int)($client['owner_id'] ?? auth_id()) === (int)$u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الدولة</label>
      <input name="country" value="<?= e($client['country'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">المدينة</label>
      <input name="city" value="<?= e($client['city'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الهاتف</label>
      <input name="phone" value="<?= e($client['phone'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">البريد</label>
      <input name="email" type="email" value="<?= e($client['email'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الموقع</label>
      <input name="website" value="<?= e($client['website'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">القيمة المتوقعة (<?= CRM_DEFAULT_CURRENCY ?>)</label>
      <input name="value" type="number" step="0.01" value="<?= e($client['value'] ?? '0') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div class="col-span-2">
      <label class="block text-sm mb-1">ملاحظات</label>
      <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"><?= e($client['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="flex justify-between mt-6">
    <div class="flex gap-2">
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
      <a href="<?= url('modules/clients/') ?>" class="px-6 py-2 border rounded-lg">إلغاء</a>
    </div>
    <?php if ($client): ?>
      <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف</button>
    <?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
