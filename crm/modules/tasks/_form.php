<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_perm('tasks.manage');

$id = (int)($_GET['id'] ?? 0);
$task = $id ? db_one('SELECT * FROM ' . tbl('tasks') . ' WHERE id = :id', ['id' => $id]) : null;
if ($id && !$task) { flash('error', 'المهمة غير موجودة.'); redirect('modules/tasks/'); }

$users   = db_all('SELECT id, name FROM ' . tbl('users') . " WHERE status='active' ORDER BY name");
$clients = db_all('SELECT id, name FROM ' . tbl('clients') . ' ORDER BY name LIMIT 500');
$deals   = db_all('SELECT id, title FROM ' . tbl('deals') . ' ORDER BY created_at DESC LIMIT 500');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && $task) {
        db_delete(tbl('tasks'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'task', $id, null);
        flash('success', 'تم حذف المهمة.');
        redirect('modules/tasks/');
    }

    $status = $_POST['status'] ?? 'open';
    $data = [
        'title'        => trim($_POST['title'] ?? ''),
        'description'  => trim($_POST['description'] ?? '') ?: null,
        'assignee_id'  => (int)($_POST['assignee_id'] ?? auth_id()),
        'related_type' => $_POST['related_type'] ?? 'none',
        'related_id'   => ($_POST['related_id'] ?? '') ? (int)$_POST['related_id'] : null,
        'priority'     => $_POST['priority'] ?? 'medium',
        'status'       => $status,
        'due_at'       => $_POST['due_at'] ?: null,
        'completed_at' => $status === 'done' ? ($task['completed_at'] ?? date('Y-m-d H:i:s')) : null,
        'created_by'   => $task['created_by'] ?? auth_id(),
    ];
    if ($data['related_type'] === 'none') $data['related_id'] = null;
    if ($data['title'] === '') $errors[] = 'العنوان مطلوب';
    if (!$data['assignee_id']) $errors[] = 'المسؤول مطلوب';

    if (!$errors) {
        if ($task) {
            $wasDone = $task['status'] === 'done';
            unset($data['created_by']);
            db_update(tbl('tasks'), $data, 'id = :id', ['id' => $id]);
            activity_log('update', 'task', $id, ['title' => $data['title'], 'status' => $status]);
            if (!$wasDone && $status === 'done') {
                event_fire('task.completed', 'task', $id, ['priority' => $data['priority']], (int)$data['assignee_id']);
            }
            // notify assignee on assign change
            if ((int)$task['assignee_id'] !== (int)$data['assignee_id']) {
                notify((int)$data['assignee_id'], 'task_assigned', '📌 مهمة جديدة موكلة إليك: ' . $data['title'], null, '/crm/modules/tasks/edit.php?id=' . $id, '📌');
            }
            flash('success', 'تم التحديث.');
            redirect('modules/tasks/edit.php?id=' . $id);
        } else {
            $newId = db_insert(tbl('tasks'), $data);
            activity_log('create', 'task', (int)$newId, ['title' => $data['title']]);
            if ((int)$data['assignee_id'] !== auth_id()) {
                notify((int)$data['assignee_id'], 'task_assigned', '📌 مهمة جديدة موكلة إليك: ' . $data['title'], null, '/crm/modules/tasks/edit.php?id=' . $newId, '📌');
            }
            flash('success', 'تم إنشاء المهمة.');
            redirect('modules/tasks/edit.php?id=' . $newId);
        }
    }
    $task = array_merge((array)$task, $data);
}

$preType = $_GET['related_type'] ?? ($task['related_type'] ?? 'none');
$preId   = (int)($_GET['related_id'] ?? ($task['related_id'] ?? 0));
$pageTitle = $task ? 'تعديل مهمة' : 'مهمة جديدة';
$priorities = ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'];
$statuses   = ['open'=>'مفتوحة','in_progress'=>'قيد التنفيذ','done'=>'مكتملة','cancelled'=>'ملغاة'];
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
      <input name="title" required value="<?= e($task['title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div class="col-span-2">
      <label class="block text-sm mb-1">الوصف</label>
      <textarea name="description" rows="4" class="w-full px-3 py-2 border rounded-lg"><?= e($task['description'] ?? '') ?></textarea>
    </div>
    <div>
      <label class="block text-sm mb-1">المسؤول *</label>
      <select name="assignee_id" required class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ((int)($task['assignee_id'] ?? auth_id()) === (int)$u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الأولوية</label>
      <select name="priority" class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($priorities as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($task['priority'] ?? 'medium') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الحالة</label>
      <select name="status" class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($statuses as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($task['status'] ?? 'open') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الاستحقاق</label>
      <input type="datetime-local" name="due_at" value="<?= $task['due_at'] ? date('Y-m-d\TH:i', strtotime($task['due_at'])) : '' ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">مرتبطة بـ</label>
      <select name="related_type" id="related_type" onchange="syncRelatedOptions()" class="w-full px-3 py-2 border rounded-lg">
        <option value="none"   <?= $preType === 'none' ? 'selected' : '' ?>>—</option>
        <option value="client" <?= $preType === 'client' ? 'selected' : '' ?>>عميل</option>
        <option value="deal"   <?= $preType === 'deal' ? 'selected' : '' ?>>صفقة</option>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">السجل المرتبط</label>
      <select name="related_id" id="related_id" class="w-full px-3 py-2 border rounded-lg">
        <option value="">—</option>
        <optgroup label="العملاء" data-type="client">
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" data-type="client" <?= ($preType === 'client' && (int)$preId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="الصفقات" data-type="deal">
          <?php foreach ($deals as $d): ?>
            <option value="<?= $d['id'] ?>" data-type="deal" <?= ($preType === 'deal' && (int)$preId === (int)$d['id']) ? 'selected' : '' ?>><?= e($d['title']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
    </div>
  </div>

  <div class="flex justify-between mt-6">
    <div class="flex gap-2">
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
      <a href="<?= url('modules/tasks/') ?>" class="px-6 py-2 border rounded-lg">إلغاء</a>
    </div>
    <?php if ($task): ?>
      <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف</button>
    <?php endif; ?>
  </div>
</form>

<script>
function syncRelatedOptions() {
  const t = document.getElementById('related_type').value;
  const sel = document.getElementById('related_id');
  Array.from(sel.querySelectorAll('option')).forEach(o => {
    if (!o.dataset.type) { o.hidden = false; return; }
    o.hidden = (t === 'none' || o.dataset.type !== t);
  });
  Array.from(sel.querySelectorAll('optgroup')).forEach(g => {
    g.hidden = (t === 'none' || g.dataset.type !== t);
  });
  if (sel.selectedOptions[0] && sel.selectedOptions[0].hidden) sel.value = '';
}
syncRelatedOptions();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
