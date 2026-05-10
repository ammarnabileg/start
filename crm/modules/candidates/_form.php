<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_perm('candidates.manage');

$id = (int)($_GET['id'] ?? 0);
$cand = $id ? db_one('SELECT * FROM ' . tbl('candidates') . ' WHERE id = :id', ['id' => $id]) : null;
if ($id && !$cand) { flash('error', 'المرشح غير موجود.'); redirect('modules/candidates/'); }

$users = db_all('SELECT id, name FROM ' . tbl('users') . " WHERE status='active' ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && $cand) {
        db_delete(tbl('candidates'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'candidate', $id, null);
        flash('success', 'تم حذف المرشح.');
        redirect('modules/candidates/');
    }

    $skillsRaw = trim($_POST['skills'] ?? '');
    $skills = $skillsRaw ? array_values(array_filter(array_map('trim', explode(',', $skillsRaw)))) : [];

    $newStatus = $_POST['status'] ?? 'new';
    $data = [
        'name'               => trim($_POST['name'] ?? ''),
        'email'              => trim($_POST['email'] ?? '') ?: null,
        'phone'              => trim($_POST['phone'] ?? '') ?: null,
        'headline'           => trim($_POST['headline'] ?? '') ?: null,
        'level'              => $_POST['level'] ?: null,
        'skills'             => $skills ? json_encode($skills, JSON_UNESCAPED_UNICODE) : null,
        'current_role'       => trim($_POST['current_role'] ?? '') ?: null,
        'current_company'    => trim($_POST['current_company'] ?? '') ?: null,
        'salary_expectation' => $_POST['salary_expectation'] ? (float)$_POST['salary_expectation'] : null,
        'currency'           => trim($_POST['currency'] ?? CRM_DEFAULT_CURRENCY),
        'availability'       => trim($_POST['availability'] ?? '') ?: null,
        'source'             => trim($_POST['source'] ?? '') ?: null,
        'cv_url'             => trim($_POST['cv_url'] ?? '') ?: null,
        'linkedin_url'       => trim($_POST['linkedin_url'] ?? '') ?: null,
        'status'             => $newStatus,
        'owner_id'           => (int)($_POST['owner_id'] ?? auth_id()),
        'notes'              => trim($_POST['notes'] ?? '') ?: null,
    ];

    if ($data['name'] === '') $errors[] = 'الاسم مطلوب';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد غير صحيح';
    if (!$data['owner_id']) $errors[] = 'المسؤول مطلوب';

    if (!$errors) {
        if ($cand) {
            $oldStatus = $cand['status'];
            db_update(tbl('candidates'), $data, 'id = :id', ['id' => $id]);
            activity_log('update', 'candidate', $id, ['name' => $data['name']]);
            if ($oldStatus !== $newStatus) {
                event_fire('candidate.advanced', 'candidate', $id, ['from' => $oldStatus, 'to' => $newStatus], (int)$data['owner_id']);
            }
            flash('success', 'تم التحديث.');
            redirect('modules/candidates/edit.php?id=' . $id);
        } else {
            $newId = db_insert(tbl('candidates'), $data);
            activity_log('create', 'candidate', (int)$newId, ['name' => $data['name']]);
            event_fire('candidate.created', 'candidate', (int)$newId, [], (int)$data['owner_id']);
            flash('success', 'تم إنشاء المرشح.');
            redirect('modules/candidates/edit.php?id=' . $newId);
        }
    }
    $cand = array_merge((array)$cand, $data);
    if (!is_array($skills) || $skills === []) $cand['skills'] = $skillsRaw;
    else $cand['skills'] = $skillsRaw;
}

// for display
$displaySkills = '';
if ($cand && !empty($cand['skills'])) {
    $arr = is_array($cand['skills']) ? $cand['skills'] : (json_decode($cand['skills'], true) ?: []);
    $displaySkills = is_array($arr) ? implode(', ', $arr) : (string)$cand['skills'];
}

$pageTitle = $cand ? 'تعديل: ' . $cand['name'] : 'مرشح جديد';
$statuses = ['new'=>'جديد','screening'=>'فرز','interviewing'=>'مقابلات','shortlisted'=>'قائمة قصيرة','offered'=>'عُرض عليه','placed'=>'تم تعيينه','rejected'=>'مرفوض','onhold'=>'مؤجل'];
$levels = ['intern'=>'تدريب','junior'=>'مبتدئ','mid'=>'متوسط','senior'=>'كبير','lead'=>'قائد','manager'=>'مدير','director'=>'مدير عام'];
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
    <div>
      <label class="block text-sm mb-1">الاسم *</label>
      <input name="name" required value="<?= e($cand['name'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">العنوان المهني</label>
      <input name="headline" value="<?= e($cand['headline'] ?? '') ?>" placeholder="Senior Frontend Engineer" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">البريد</label>
      <input type="email" name="email" value="<?= e($cand['email'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الهاتف</label>
      <input name="phone" value="<?= e($cand['phone'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">المنصب الحالي</label>
      <input name="current_role" value="<?= e($cand['current_role'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الشركة الحالية</label>
      <input name="current_company" value="<?= e($cand['current_company'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">المستوى</label>
      <select name="level" class="w-full px-3 py-2 border rounded-lg">
        <option value="">—</option>
        <?php foreach ($levels as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($cand['level'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الحالة</label>
      <select name="status" class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($statuses as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($cand['status'] ?? 'new') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-span-2">
      <label class="block text-sm mb-1">المهارات (مفصولة بفاصلة)</label>
      <input name="skills" value="<?= e($displaySkills) ?>" placeholder="PHP, MySQL, JavaScript, Sales" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الراتب المتوقع</label>
      <input type="number" step="0.01" name="salary_expectation" value="<?= e($cand['salary_expectation'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">العملة</label>
      <input name="currency" value="<?= e($cand['currency'] ?? CRM_DEFAULT_CURRENCY) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">التوفر</label>
      <input name="availability" value="<?= e($cand['availability'] ?? '') ?>" placeholder="فورًا / إشعار شهر" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">المصدر</label>
      <input name="source" value="<?= e($cand['source'] ?? '') ?>" placeholder="LinkedIn / إحالة" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">رابط السيرة (CV)</label>
      <input name="cv_url" value="<?= e($cand['cv_url'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">LinkedIn</label>
      <input name="linkedin_url" value="<?= e($cand['linkedin_url'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">المسؤول *</label>
      <select name="owner_id" required class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= ((int)($cand['owner_id'] ?? auth_id()) === (int)$u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-span-2">
      <label class="block text-sm mb-1">ملاحظات</label>
      <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"><?= e($cand['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="flex justify-between mt-6">
    <div class="flex gap-2">
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
      <a href="<?= url('modules/candidates/') ?>" class="px-6 py-2 border rounded-lg">رجوع</a>
    </div>
    <?php if ($cand): ?>
      <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف</button>
    <?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
