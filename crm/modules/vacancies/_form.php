<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_perm('vacancies.manage');

$id = (int)($_GET['id'] ?? 0);
$vac = $id ? db_one('SELECT * FROM ' . tbl('vacancies') . ' WHERE id = :id', ['id' => $id]) : null;
if ($id && !$vac) { flash('error', 'الشاغر غير موجود.'); redirect('modules/vacancies/'); }

$clients = db_all('SELECT id, name FROM ' . tbl('clients') . ' ORDER BY name LIMIT 500');
$users   = db_all('SELECT id, name FROM ' . tbl('users') . " WHERE status='active' ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && $vac) {
        db_delete(tbl('vacancies'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'vacancy', $id, null);
        flash('success', 'تم الحذف.');
        redirect('modules/vacancies/');
    }

    $newStatus = $_POST['status'] ?? 'open';
    $data = [
        'client_id'   => (int)($_POST['client_id'] ?? 0),
        'title'       => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? '') ?: null,
        'level'       => $_POST['level'] ?: null,
        'headcount'   => max(1, (int)($_POST['headcount'] ?? 1)),
        'salary_min'  => $_POST['salary_min'] ? (float)$_POST['salary_min'] : null,
        'salary_max'  => $_POST['salary_max'] ? (float)$_POST['salary_max'] : null,
        'currency'    => trim($_POST['currency'] ?? CRM_DEFAULT_CURRENCY),
        'status'      => $newStatus,
        'owner_id'    => (int)($_POST['owner_id'] ?? auth_id()),
        'opened_at'   => $_POST['opened_at'] ?: ($vac['opened_at'] ?? date('Y-m-d')),
        'closed_at'   => in_array($newStatus, ['closed','cancelled'], true) ? ($_POST['closed_at'] ?: date('Y-m-d')) : null,
    ];

    if (!$data['client_id']) $errors[] = 'العميل مطلوب';
    if ($data['title'] === '') $errors[] = 'العنوان مطلوب';

    if (!$errors) {
        if ($vac) {
            $oldStatus = $vac['status'];
            db_update(tbl('vacancies'), $data, 'id = :id', ['id' => $id]);
            activity_log('update', 'vacancy', $id, ['title' => $data['title']]);
            if ($oldStatus !== 'closed' && $newStatus === 'closed') {
                event_fire('vacancy.closed', 'vacancy', $id, [], (int)$data['owner_id']);
            }
            flash('success', 'تم التحديث.');
            redirect('modules/vacancies/edit.php?id=' . $id);
        } else {
            $newId = db_insert(tbl('vacancies'), $data);
            activity_log('create', 'vacancy', (int)$newId, ['title' => $data['title']]);
            event_fire('vacancy.opened', 'vacancy', (int)$newId, [], (int)$data['owner_id']);
            flash('success', 'تم إنشاء الشاغر.');
            redirect('modules/vacancies/edit.php?id=' . $newId);
        }
    }
    $vac = array_merge((array)$vac, $data);
}

// placements list
$placements = $id ? db_all("
    SELECT p.*, c.name AS candidate_name, c.headline, c.email
    FROM " . tbl('placements') . " p
    JOIN " . tbl('candidates') . " c ON c.id = p.candidate_id
    WHERE p.vacancy_id = :v
    ORDER BY p.created_at DESC
", ['v' => $id]) : [];

$pageTitle = $vac ? $vac['title'] : 'شاغر جديد';
$statuses = ['open'=>'مفتوح','onhold'=>'مؤجل','closed'=>'مغلق','cancelled'=>'ملغى'];
$levels = ['intern'=>'تدريب','junior'=>'مبتدئ','mid'=>'متوسط','senior'=>'كبير','lead'=>'قائد','manager'=>'مدير','director'=>'مدير عام'];
$plcStages = ['submitted'=>'مُرسل','interview'=>'مقابلة','offer'=>'عرض','placed'=>'تم تعيينه','probation_passed'=>'اجتاز التجربة','probation_failed'=>'فشل التجربة','rejected'=>'مرفوض'];

require __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <form method="post" class="lg:col-span-2 bg-white rounded-xl shadow-sm border p-6">
    <?= csrf_field() ?>
    <?php if ($errors): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm">
        <ul class="list-disc list-inside"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <label class="block text-sm mb-1">عنوان الشاغر *</label>
        <input name="title" required value="<?= e($vac['title'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
      </div>
      <div>
        <label class="block text-sm mb-1">العميل *</label>
        <select name="client_id" required class="w-full px-3 py-2 border rounded-lg">
          <option value="">اختر...</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ((int)($vac['client_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">المسؤول *</label>
        <select name="owner_id" required class="w-full px-3 py-2 border rounded-lg">
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= ((int)($vac['owner_id'] ?? auth_id()) === (int)$u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">المستوى</label>
        <select name="level" class="w-full px-3 py-2 border rounded-lg">
          <option value="">—</option>
          <?php foreach ($levels as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($vac['level'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">عدد الشواغر</label>
        <input type="number" min="1" name="headcount" value="<?= e($vac['headcount'] ?? 1) ?>" class="w-full px-3 py-2 border rounded-lg">
      </div>
      <div>
        <label class="block text-sm mb-1">الحد الأدنى للراتب</label>
        <input type="number" step="0.01" name="salary_min" value="<?= e($vac['salary_min'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
      </div>
      <div>
        <label class="block text-sm mb-1">الحد الأعلى</label>
        <input type="number" step="0.01" name="salary_max" value="<?= e($vac['salary_max'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
      </div>
      <div>
        <label class="block text-sm mb-1">العملة</label>
        <input name="currency" value="<?= e($vac['currency'] ?? CRM_DEFAULT_CURRENCY) ?>" class="w-full px-3 py-2 border rounded-lg">
      </div>
      <div>
        <label class="block text-sm mb-1">الحالة</label>
        <select name="status" class="w-full px-3 py-2 border rounded-lg">
          <?php foreach ($statuses as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($vac['status'] ?? 'open') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-span-2">
        <label class="block text-sm mb-1">الوصف</label>
        <textarea name="description" rows="5" class="w-full px-3 py-2 border rounded-lg"><?= e($vac['description'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="flex justify-between mt-6">
      <div class="flex gap-2">
        <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
        <a href="<?= url('modules/vacancies/') ?>" class="px-6 py-2 border rounded-lg">رجوع</a>
      </div>
      <?php if ($vac): ?>
        <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($vac): ?>
  <div class="bg-white rounded-xl shadow-sm border p-6">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-bold">المرشحون (<?= count($placements) ?>)</h2>
      <a href="<?= url('modules/placements/create.php?vacancy_id=' . $id) ?>" class="text-emerald-600 text-sm hover:underline">+ تقديم</a>
    </div>
    <?php if (!$placements): ?>
      <p class="text-gray-500 text-sm">لا يوجد مرشحون مقدمون.</p>
    <?php else: foreach ($placements as $p): ?>
      <a href="<?= url('modules/placements/edit.php?id=' . $p['id']) ?>" class="block border-b py-3 hover:bg-gray-50">
        <div class="flex justify-between items-start">
          <div>
            <div class="font-medium text-sm"><?= e($p['candidate_name']) ?></div>
            <div class="text-xs text-gray-500"><?= e($p['headline'] ?? '') ?></div>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><?= e($plcStages[$p['stage']] ?? '') ?></span>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
