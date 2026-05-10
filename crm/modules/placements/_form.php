<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_perm('placements.manage');

$id = (int)($_GET['id'] ?? 0);
$plc = $id ? db_one('SELECT * FROM ' . tbl('placements') . ' WHERE id = :id', ['id' => $id]) : null;
if ($id && !$plc) { flash('error', 'غير موجود.'); redirect('modules/placements/'); }

$candidates = db_all('SELECT id, name FROM ' . tbl('candidates') . ' ORDER BY name LIMIT 1000');
$vacancies  = db_all('SELECT id, title FROM ' . tbl('vacancies') . ' ORDER BY created_at DESC LIMIT 500');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && $plc) {
        db_delete(tbl('placements'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'placement', $id, null);
        flash('success', 'تم الحذف.');
        redirect('modules/placements/');
    }

    $stage = $_POST['stage'] ?? 'submitted';
    $data = [
        'candidate_id'      => (int)($_POST['candidate_id'] ?? 0),
        'vacancy_id'        => (int)($_POST['vacancy_id'] ?? 0),
        'stage'             => $stage,
        'offered_salary'    => $_POST['offered_salary'] ? (float)$_POST['offered_salary'] : null,
        'placed_at'         => $_POST['placed_at'] ?: ($stage === 'placed' ? date('Y-m-d') : null),
        'probation_end_at'  => $_POST['probation_end_at'] ?: null,
        'notes'             => trim($_POST['notes'] ?? '') ?: null,
    ];

    if (!$data['candidate_id'] || !$data['vacancy_id']) $errors[] = 'المرشح والشاغر مطلوبين';

    if (!$errors) {
        if ($plc) {
            $oldStage = $plc['stage'];
            db_update(tbl('placements'), $data, 'id = :id', ['id' => $id]);
            activity_log('update', 'placement', $id, ['stage' => $stage]);
            if ($oldStage !== 'placed' && $stage === 'placed') {
                $vac = db_one('SELECT * FROM ' . tbl('vacancies') . ' WHERE id = :v', ['v' => $data['vacancy_id']]);
                $cand = db_one('SELECT owner_id FROM ' . tbl('candidates') . ' WHERE id = :c', ['c' => $data['candidate_id']]);
                if ($vac) {
                    db_update(tbl('vacancies'), ['placed_count' => (int)$vac['placed_count'] + 1], 'id = :v', ['v' => $data['vacancy_id']]);
                }
                if ($cand) {
                    event_fire('placement.placed', 'placement', $id, ['vacancy_id' => $data['vacancy_id']], (int)$cand['owner_id']);
                    db_update(tbl('candidates'), ['status' => 'placed'], 'id = :c', ['c' => $data['candidate_id']]);
                }
            }
            flash('success', 'تم التحديث.');
            redirect('modules/placements/edit.php?id=' . $id);
        } else {
            try {
                $newId = db_insert(tbl('placements'), $data);
                activity_log('create', 'placement', (int)$newId, null);
                flash('success', 'تم التقديم.');
                redirect('modules/placements/edit.php?id=' . $newId);
            } catch (Throwable $e) {
                $errors[] = 'هذا المرشح مقدّم على الشاغر بالفعل.';
            }
        }
    }
    $plc = array_merge((array)$plc, $data);
}

$preCand = (int)($_GET['candidate_id'] ?? ($plc['candidate_id'] ?? 0));
$preVac  = (int)($_GET['vacancy_id'] ?? ($plc['vacancy_id'] ?? 0));
$pageTitle = $plc ? 'تعديل تقديم' : 'تقديم مرشح جديد';
$stages = ['submitted'=>'مُرسل','interview'=>'مقابلة','offer'=>'عرض','placed'=>'تم تعيينه','probation_passed'=>'اجتاز التجربة','probation_failed'=>'فشل التجربة','rejected'=>'مرفوض'];
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
    <div>
      <label class="block text-sm mb-1">المرشح *</label>
      <select name="candidate_id" required class="w-full px-3 py-2 border rounded-lg">
        <option value="">اختر...</option>
        <?php foreach ($candidates as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ((int)($plc['candidate_id'] ?? $preCand) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الشاغر *</label>
      <select name="vacancy_id" required class="w-full px-3 py-2 border rounded-lg">
        <option value="">اختر...</option>
        <?php foreach ($vacancies as $v): ?>
          <option value="<?= $v['id'] ?>" <?= ((int)($plc['vacancy_id'] ?? $preVac) === (int)$v['id']) ? 'selected' : '' ?>><?= e($v['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">المرحلة</label>
      <select name="stage" class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($stages as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($plc['stage'] ?? 'submitted') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الراتب المعروض</label>
      <input type="number" step="0.01" name="offered_salary" value="<?= e($plc['offered_salary'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">تاريخ التعيين</label>
      <input type="date" name="placed_at" value="<?= e($plc['placed_at'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">نهاية التجربة</label>
      <input type="date" name="probation_end_at" value="<?= e($plc['probation_end_at'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div class="col-span-2">
      <label class="block text-sm mb-1">ملاحظات</label>
      <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"><?= e($plc['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="flex justify-between mt-6">
    <div class="flex gap-2">
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
      <a href="<?= url('modules/placements/') ?>" class="px-6 py-2 border rounded-lg">رجوع</a>
    </div>
    <?php if ($plc): ?>
      <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف</button>
    <?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
