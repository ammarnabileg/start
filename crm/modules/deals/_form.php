<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_perm('deals.manage');

$id = (int)($_GET['id'] ?? 0);
$deal = $id ? db_one('SELECT * FROM ' . tbl('deals') . ' WHERE id = :id', ['id' => $id]) : null;
if ($id && !$deal) { flash('error', 'الصفقة غير موجودة.'); redirect('modules/deals/'); }

$clients = db_all('SELECT id, name FROM ' . tbl('clients') . ' ORDER BY name LIMIT 500');
$users   = db_all('SELECT id, name FROM ' . tbl('users') . " WHERE status='active' ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && $deal) {
        db_delete(tbl('deals'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'deal', $id, null);
        flash('success', 'تم حذف الصفقة.');
        redirect('modules/deals/');
    }

    $stage = $_POST['stage'] ?? 'lead';
    $data = [
        'client_id'         => (int)($_POST['client_id'] ?? 0),
        'title'             => trim($_POST['title'] ?? ''),
        'stage'             => $stage,
        'amount'            => (float)($_POST['amount'] ?? 0),
        'currency'          => trim($_POST['currency'] ?? CRM_DEFAULT_CURRENCY),
        'probability'       => max(0, min(100, (int)($_POST['probability'] ?? 50))),
        'expected_close_at' => $_POST['expected_close_at'] ?: null,
        'actual_close_at'   => in_array($stage, ['won','lost'], true) ? ($deal['actual_close_at'] ?? date('Y-m-d')) : null,
        'owner_id'          => (int)($_POST['owner_id'] ?? auth_id()),
        'lost_reason'       => $stage === 'lost' ? trim($_POST['lost_reason'] ?? '') : null,
        'notes'             => trim($_POST['notes'] ?? '') ?: null,
    ];

    if (!$data['client_id']) $errors[] = 'العميل مطلوب';
    if ($data['title'] === '') $errors[] = 'العنوان مطلوب';

    if (!$errors) {
        if ($deal) {
            $oldStage = $deal['stage'];
            db_update(tbl('deals'), $data, 'id = :id', ['id' => $id]);
            activity_log('update', 'deal', $id, ['title' => $data['title'], 'stage' => $stage]);
            if ($oldStage !== $stage) {
                event_fire('deal.advanced', 'deal', $id, ['from' => $oldStage, 'to' => $stage], (int)$data['owner_id']);
            }
            if ($oldStage !== 'won' && $stage === 'won') {
                event_fire('deal.won', 'deal', $id, ['amount' => $data['amount']], (int)$data['owner_id']);
                notify((int)$data['owner_id'], 'deal_won', '🎉 صفقة مكسوبة: ' . $data['title'], format_money($data['amount'], $data['currency']), '/crm/modules/deals/edit.php?id=' . $id, '🎉');
            }
            if (in_array($oldStage, ['lost'], true) && $stage === 'won') {
                event_fire('deal.recovered', 'deal', $id, [], (int)$data['owner_id']);
            }
            flash('success', 'تم التحديث.');
            redirect('modules/deals/edit.php?id=' . $id);
        } else {
            $newId = db_insert(tbl('deals'), $data);
            activity_log('create', 'deal', (int)$newId, ['title' => $data['title']]);
            event_fire('deal.created', 'deal', (int)$newId, [], (int)$data['owner_id']);
            flash('success', 'تم إنشاء الصفقة.');
            redirect('modules/deals/edit.php?id=' . $newId);
        }
    }
    $deal = array_merge((array)$deal, $data);
}

$preClient = (int)($_GET['client_id'] ?? ($deal['client_id'] ?? 0));
$pageTitle = $deal ? 'تعديل صفقة' : 'صفقة جديدة';
$stages = ['lead'=>'بداية','qualified'=>'مؤهل','proposal'=>'عرض','negotiation'=>'تفاوض','won'=>'فوز','lost'=>'خسارة'];
require __DIR__ . '/../../includes/header.php';
?>

<form method="post" class="bg-white rounded-xl shadow-sm border p-6 max-w-3xl">
  <?= csrf_field() ?>
  <?php if ($errors): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm">
      <ul class="list-disc list-inside"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-2 gap-4">
    <div class="col-span-2">
      <label class="block text-sm mb-1">العنوان *</label>
      <input name="title" required value="<?= e($deal['title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">العميل *</label>
      <select name="client_id" required class="w-full px-3 py-2 border rounded-lg">
        <option value="">اختر...</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ((int)($deal['client_id'] ?? $preClient) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">المرحلة</label>
      <select name="stage" class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($stages as $k => $v): ?>
          <option value="<?= $k ?>" <?= (($deal['stage'] ?? 'lead') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">القيمة</label>
      <input type="number" step="0.01" name="amount" value="<?= e($deal['amount'] ?? '0') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">العملة</label>
      <input name="currency" value="<?= e($deal['currency'] ?? CRM_DEFAULT_CURRENCY) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الاحتمالية %</label>
      <input type="number" min="0" max="100" name="probability" value="<?= e($deal['probability'] ?? '50') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">إغلاق متوقع</label>
      <input type="date" name="expected_close_at" value="<?= e($deal['expected_close_at'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">المسؤول *</label>
      <select name="owner_id" required class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ((int)($deal['owner_id'] ?? auth_id()) === (int)$u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-span-2">
      <label class="block text-sm mb-1">سبب الخسارة (لو موجود)</label>
      <input name="lost_reason" value="<?= e($deal['lost_reason'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div class="col-span-2">
      <label class="block text-sm mb-1">ملاحظات</label>
      <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"><?= e($deal['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="flex justify-between mt-6">
    <div class="flex gap-2">
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
      <a href="<?= url('modules/deals/') ?>" class="px-6 py-2 border rounded-lg">إلغاء</a>
    </div>
    <?php if ($deal): ?>
      <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف</button>
    <?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
