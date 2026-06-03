<?php
$msg = '';
$msg_type = 'green';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['action'] ?? '';
    $sub_id = (int)($_POST['sub_id'] ?? 0);
    $note   = pi_escape($_POST['note'] ?? '');

    if ($act === 'approve') {
        $r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_id=$sub_id");
        if ($r && $r->num_rows) {
            $sub  = $r->fetch_assoc();
            $data = json_decode($sub['sub_data'], true);

            if ($sub['sub_type'] === 'personality') {
                $name_ar = pi_escape($data['p_name_ar'] ?? '');
                $name_en = pi_escape($data['p_name_en'] ?? '');
                $title   = pi_escape($data['p_title'] ?? '');
                $nat     = pi_escape($data['p_nationality'] ?? '');
                $res     = pi_escape($data['p_residence'] ?? '');
                $bio     = pi_escape($data['p_bio'] ?? '');
                $photo   = pi_escape($data['p_photo'] ?? '');
                $mysqli->query("INSERT INTO pi_personalities (p_name_ar,p_name_en,p_title,p_nationality,p_residence,p_bio,p_photo) VALUES ('$name_ar','$name_en','$title','$nat','$res','$bio','$photo')");
                $new_id = $mysqli->insert_id;
                foreach (($data['categories'] ?? []) as $cat_id) {
                    $cat_id = (int)$cat_id;
                    $mysqli->query("INSERT INTO pi_personality_categories (p_id,cat_id) VALUES ($new_id,$cat_id)");
                }
            } elseif ($sub['sub_type'] === 'institution') {
                $name_ar = pi_escape($data['inst_name_ar'] ?? '');
                $name_en = pi_escape($data['inst_name_en'] ?? '');
                $desc    = pi_escape($data['inst_description'] ?? '');
                $logo    = pi_escape($data['inst_logo'] ?? '');
                $mysqli->query("INSERT INTO pi_institutions (inst_name_ar,inst_name_en,inst_description,inst_logo) VALUES ('$name_ar','$name_en','$desc','$logo')");
                $new_id = $mysqli->insert_id;
                foreach (($data['categories'] ?? []) as $cat_id) {
                    $cat_id = (int)$cat_id;
                    $mysqli->query("INSERT INTO pi_institution_categories (inst_id,cat_id) VALUES ($new_id,$cat_id)");
                }
            }
            $mysqli->query("UPDATE pi_submissions SET sub_status='approved',sub_note='$note' WHERE sub_id=$sub_id");
            $msg = 'تم قبول الاقتراح ونشره بنجاح';
        }
    }

    if ($act === 'reject') {
        $mysqli->query("UPDATE pi_submissions SET sub_status='rejected',sub_note='$note' WHERE sub_id=$sub_id");
        $msg = 'تم رفض الاقتراح';
        $msg_type = 'red';
    }

    if ($act === 'edit_sub') {
        $r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_id=$sub_id");
        if ($r && $r->num_rows) {
            $sub  = $r->fetch_assoc();
            $data = json_decode($sub['sub_data'], true) ?? [];

            if ($sub['sub_type'] === 'personality') {
                $data['p_name_ar']     = trim($_POST['p_name_ar'] ?? '');
                $data['p_name_en']     = trim($_POST['p_name_en'] ?? '');
                $data['p_title']       = trim($_POST['p_title'] ?? '');
                $data['p_nationality'] = trim($_POST['p_nationality'] ?? '');
                $data['p_residence']   = trim($_POST['p_residence'] ?? '');
                $data['p_bio']         = trim($_POST['p_bio'] ?? '');
                $data['categories']    = array_map('intval', (array)($_POST['categories'] ?? []));
            } else {
                $data['inst_name_ar']      = trim($_POST['inst_name_ar'] ?? '');
                $data['inst_name_en']      = trim($_POST['inst_name_en'] ?? '');
                $data['inst_description']  = trim($_POST['inst_description'] ?? '');
                $data['categories']        = array_map('intval', (array)($_POST['categories'] ?? []));
            }

            $json = pi_escape(json_encode($data, JSON_UNESCAPED_UNICODE));
            $mysqli->query("UPDATE pi_submissions SET sub_data='$json' WHERE sub_id=$sub_id");
            $msg = 'تم حفظ التعديلات بنجاح';
        }
    }
}

$status_filter = $_GET['status'] ?? 'pending';
$submissions   = [];
$r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_status='$status_filter' ORDER BY sub_created DESC LIMIT 50");
if ($r) while ($row = $r->fetch_assoc()) $submissions[] = $row;

$counts = [];
foreach (['pending', 'approved', 'rejected'] as $s) {
    $counts[$s] = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_status='$s'")->fetch_assoc()['c'];
}

// Load all categories once for edit forms
$all_cats = [];
$rc = $mysqli->query("SELECT cat_id, cat_name FROM pi_categories ORDER BY cat_name");
if ($rc) while ($c = $rc->fetch_assoc()) $all_cats[] = $c;
?>

<?php if ($msg): ?>
<div class="bg-<?= $msg_type ?>-50 border border-<?= $msg_type ?>-200 text-<?= $msg_type ?>-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
    <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">مقترحات المستخدمين</h2>
  <div class="flex gap-2">
    <a href="admin.php?p=submissions&status=pending"
       class="px-4 py-1.5 text-sm font-bold rounded-full transition flex items-center gap-1.5 <?= $status_filter==='pending'?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600' ?>">
      قيد المراجعة <span class="<?= $status_filter==='pending'?'bg-white/30':'bg-gray-100' ?> px-1.5 rounded-full text-xs"><?= $counts['pending'] ?></span>
    </a>
    <a href="admin.php?p=submissions&status=approved"
       class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $status_filter==='approved'?'bg-green-500 text-white':'bg-white border border-gray-200 text-gray-600' ?>">
      مقبولة (<?= $counts['approved'] ?>)
    </a>
    <a href="admin.php?p=submissions&status=rejected"
       class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $status_filter==='rejected'?'bg-red-500 text-white':'bg-white border border-gray-200 text-gray-600' ?>">
      مرفوضة (<?= $counts['rejected'] ?>)
    </a>
  </div>
</div>

<div class="space-y-4">
<?php foreach ($submissions as $sub):
    $data      = json_decode($sub['sub_data'], true) ?? [];
    $is_person = $sub['sub_type'] === 'personality';
    $name      = $data['p_name_ar'] ?? $data['inst_name_ar'] ?? 'بدون اسم';
    $photo     = $data['p_photo'] ?? $data['inst_logo'] ?? '';
    $title     = $data['p_title'] ?? '';
    $bio       = $data['p_bio'] ?? $data['inst_description'] ?? '';
    $sub_cats  = $data['categories'] ?? [];
?>

<div class="bg-white rounded-2xl shadow-sm p-5" x-data="{open:false}">
  <div class="flex items-start justify-between gap-4">
    <!-- Photo thumb -->
    <div class="flex-shrink-0">
      <?php if ($photo): ?>
      <img src="../<?= htmlspecialchars($photo) ?>" alt="" class="w-14 h-14 rounded-xl object-cover border border-gray-100">
      <?php else: ?>
      <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center text-gray-300 text-2xl">
        <i class="fa-solid fa-<?= $is_person ? 'user' : 'building' ?>"></i>
      </div>
      <?php endif; ?>
    </div>

    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 mb-1 flex-wrap">
        <span class="px-2.5 py-0.5 <?= $is_person?'bg-blue-100 text-blue-700':'bg-indigo-100 text-indigo-700' ?> rounded-full text-xs font-bold">
          <?= $is_person?'شخصية':'مؤسسة' ?>
        </span>
        <span class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($sub['sub_created'])) ?></span>
        <?php if ($sub['sub_submitter_name']): ?>
        <span class="text-gray-500 text-xs">بواسطة: <?= htmlspecialchars($sub['sub_submitter_name']) ?></span>
        <?php endif; ?>
      </div>
      <h3 class="font-black text-gray-800 text-base truncate"><?= htmlspecialchars($name) ?></h3>
      <?php if ($title): ?>
      <p class="text-gray-500 text-sm truncate"><?= htmlspecialchars($title) ?></p>
      <?php endif; ?>
    </div>

    <!-- Action buttons -->
    <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
      <!-- Preview -->
      <button onclick="document.getElementById('preview-<?= $sub['sub_id'] ?>').showModal()"
        class="px-3 py-1.5 bg-purple-50 text-purple-700 text-sm font-bold rounded-lg hover:bg-purple-100 transition flex items-center gap-1.5">
        <i class="fa-solid fa-eye text-xs"></i> معاينة
      </button>
      <!-- Details toggle -->
      <button @click="open=!open"
        class="px-3 py-1.5 bg-gray-100 text-gray-600 text-sm font-bold rounded-lg hover:bg-gray-200 transition">
        <span x-text="open?'إخفاء':'تفاصيل'"></span>
      </button>
      <?php if ($status_filter === 'pending'): ?>
      <!-- Edit -->
      <button onclick="document.getElementById('edit-<?= $sub['sub_id'] ?>').showModal()"
        class="px-3 py-1.5 bg-amber-50 text-amber-700 text-sm font-bold rounded-lg hover:bg-amber-100 transition flex items-center gap-1.5">
        <i class="fa-solid fa-pen text-xs"></i> تعديل
      </button>
      <!-- Approve -->
      <button onclick="document.getElementById('approve-<?= $sub['sub_id'] ?>').showModal()"
        class="px-3 py-1.5 bg-green-500 text-white text-sm font-bold rounded-lg hover:bg-green-600 transition flex items-center gap-1.5">
        <i class="fa-solid fa-check text-xs"></i> قبول
      </button>
      <!-- Reject -->
      <button onclick="document.getElementById('reject-<?= $sub['sub_id'] ?>').showModal()"
        class="px-3 py-1.5 bg-red-50 text-red-600 text-sm font-bold rounded-lg hover:bg-red-100 transition flex items-center gap-1.5">
        <i class="fa-solid fa-xmark text-xs"></i> رفض
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Collapsed details -->
  <div x-show="open" x-cloak x-transition class="mt-4 pt-4 border-t border-gray-100">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
      <?php if ($data['p_name_en'] ?? null): ?>
      <div><span class="text-gray-400 text-xs">الاسم الإنجليزي</span><p class="font-semibold" dir="ltr"><?= htmlspecialchars($data['p_name_en']) ?></p></div>
      <?php endif; ?>
      <?php if ($data['p_nationality'] ?? null): ?>
      <div><span class="text-gray-400 text-xs">الجنسية</span><p class="font-semibold"><?= htmlspecialchars($data['p_nationality']) ?></p></div>
      <?php endif; ?>
      <?php if ($data['p_residence'] ?? null): ?>
      <div><span class="text-gray-400 text-xs">بلد الإقامة</span><p class="font-semibold"><?= htmlspecialchars($data['p_residence']) ?></p></div>
      <?php endif; ?>
      <?php if ($sub['sub_submitter_email']): ?>
      <div><span class="text-gray-400 text-xs">البريد الإلكتروني</span><p class="font-semibold text-xs" dir="ltr"><?= htmlspecialchars($sub['sub_submitter_email']) ?></p></div>
      <?php endif; ?>
    </div>
    <?php if ($bio): ?>
    <div class="mt-3">
      <span class="text-gray-400 text-xs block mb-1"><?= $is_person ? 'السيرة الذاتية' : 'الوصف' ?></span>
      <p class="text-gray-700 text-sm leading-7 bg-gray-50 rounded-lg p-3"><?= nl2br(htmlspecialchars($bio)) ?></p>
    </div>
    <?php endif; ?>
    <?php if (!empty($sub_cats)): ?>
    <div class="mt-3">
      <span class="text-gray-400 text-xs block mb-1">التصنيفات</span>
      <div class="flex flex-wrap gap-1">
        <?php foreach ($sub_cats as $cid):
          $rc2 = $mysqli->query("SELECT cat_name FROM pi_categories WHERE cat_id=".(int)$cid);
          if ($rc2 && $rc2->num_rows): $cn = $rc2->fetch_assoc()['cat_name']; ?>
          <span class="px-2 py-0.5 bg-purple-50 text-purple-800 text-xs font-semibold rounded-full"><?= htmlspecialchars($cn) ?></span>
          <?php endif; endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($sub['sub_note']): ?>
    <div class="mt-3 bg-yellow-50 rounded-lg p-3">
      <span class="text-yellow-700 text-xs font-bold">ملاحظة المراجع: </span>
      <span class="text-yellow-800 text-sm"><?= htmlspecialchars($sub['sub_note']) ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════ PREVIEW DIALOG ═══════════════ -->
<dialog id="preview-<?= $sub['sub_id'] ?>" class="rounded-2xl shadow-2xl w-full max-w-md p-0 overflow-hidden backdrop:bg-black/60" dir="rtl">
  <div class="pi-gradient h-2"></div>
  <div class="p-6">
    <!-- Header close -->
    <div class="flex items-center justify-between mb-5">
      <span class="text-xs font-bold text-purple-700 bg-purple-50 px-3 py-1 rounded-full">معاينة الاقتراح</span>
      <button onclick="this.closest('dialog').close()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
    </div>

    <!-- Card preview -->
    <div class="bg-gray-50 rounded-2xl p-5 flex flex-col items-center text-center gap-3">
      <?php if ($photo): ?>
      <img src="../<?= htmlspecialchars($photo) ?>" alt="" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
      <?php else: ?>
      <div class="w-24 h-24 rounded-full bg-gradient-to-br from-purple-400 to-pink-400 flex items-center justify-center text-white text-4xl shadow-md">
        <i class="fa-solid fa-<?= $is_person ? 'user' : 'building' ?>"></i>
      </div>
      <?php endif; ?>

      <div>
        <h3 class="text-xl font-black text-gray-800"><?= htmlspecialchars($name) ?></h3>
        <?php if ($title): ?>
        <p class="text-purple-600 font-semibold text-sm mt-0.5"><?= htmlspecialchars($title) ?></p>
        <?php endif; ?>
        <?php if ($data['p_name_en'] ?? null): ?>
        <p class="text-gray-400 text-xs mt-0.5" dir="ltr"><?= htmlspecialchars($data['p_name_en']) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($data['p_nationality'] ?? $data['p_residence'] ?? null): ?>
      <div class="flex items-center gap-3 text-xs text-gray-500">
        <?php if ($data['p_nationality'] ?? null): ?>
        <span><i class="fa-solid fa-flag mr-1 text-purple-400"></i><?= htmlspecialchars($data['p_nationality']) ?></span>
        <?php endif; ?>
        <?php if ($data['p_residence'] ?? null): ?>
        <span><i class="fa-solid fa-location-dot mr-1 text-purple-400"></i><?= htmlspecialchars($data['p_residence']) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($sub_cats)): ?>
      <div class="flex flex-wrap gap-1 justify-center">
        <?php foreach ($sub_cats as $cid):
          $rc3 = $mysqli->query("SELECT cat_name FROM pi_categories WHERE cat_id=".(int)$cid);
          if ($rc3 && $rc3->num_rows): $cn = $rc3->fetch_assoc()['cat_name']; ?>
          <span class="px-2.5 py-0.5 bg-white border border-purple-200 text-purple-700 text-xs font-semibold rounded-full shadow-sm"><?= htmlspecialchars($cn) ?></span>
          <?php endif; endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($bio): ?>
      <p class="text-gray-600 text-sm leading-7 text-center line-clamp-4 bg-white rounded-xl px-4 py-3 w-full"><?= nl2br(htmlspecialchars($bio)) ?></p>
      <?php endif; ?>
    </div>

    <!-- Submitter info -->
    <?php if ($sub['sub_submitter_name'] || $sub['sub_submitter_email']): ?>
    <div class="mt-4 bg-blue-50 rounded-xl p-3 flex items-center gap-3 text-sm">
      <i class="fa-solid fa-user-pen text-blue-400 text-lg"></i>
      <div>
        <?php if ($sub['sub_submitter_name']): ?>
        <p class="font-bold text-blue-800"><?= htmlspecialchars($sub['sub_submitter_name']) ?></p>
        <?php endif; ?>
        <?php if ($sub['sub_submitter_email']): ?>
        <p class="text-blue-600 text-xs" dir="ltr"><?= htmlspecialchars($sub['sub_submitter_email']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="mt-4 flex gap-3">
      <?php if ($status_filter === 'pending'): ?>
      <button onclick="this.closest('dialog').close();document.getElementById('approve-<?= $sub['sub_id'] ?>').showModal()"
        class="flex-1 py-2.5 bg-green-500 text-white font-bold rounded-xl text-sm hover:bg-green-600 transition">
        <i class="fa-solid fa-check mr-1"></i> قبول
      </button>
      <button onclick="this.closest('dialog').close();document.getElementById('edit-<?= $sub['sub_id'] ?>').showModal()"
        class="flex-1 py-2.5 bg-amber-100 text-amber-700 font-bold rounded-xl text-sm hover:bg-amber-200 transition">
        <i class="fa-solid fa-pen mr-1"></i> تعديل
      </button>
      <?php endif; ?>
      <button onclick="this.closest('dialog').close()"
        class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-sm hover:bg-gray-50 transition">
        إغلاق
      </button>
    </div>
  </div>
</dialog>

<?php if ($status_filter === 'pending'): ?>

<!-- ═══════════════ EDIT DIALOG ═══════════════ -->
<dialog id="edit-<?= $sub['sub_id'] ?>" class="rounded-2xl shadow-2xl w-full max-w-2xl backdrop:bg-black/60" dir="rtl">
  <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
    <h3 class="font-black text-gray-800 text-lg flex items-center gap-2">
      <i class="fa-solid fa-pen text-amber-500"></i> تعديل الاقتراح
    </h3>
    <button onclick="this.closest('dialog').close()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
  </div>

  <form method="POST" class="p-6 space-y-4 overflow-y-auto max-h-[80vh]">
    <input type="hidden" name="action" value="edit_sub">
    <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">

    <?php if ($is_person): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">الاسم بالعربية <span class="text-red-500">*</span></label>
        <input type="text" name="p_name_ar" value="<?= htmlspecialchars($data['p_name_ar'] ?? '') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">الاسم بالإنجليزية</label>
        <input type="text" name="p_name_en" value="<?= htmlspecialchars($data['p_name_en'] ?? '') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100" dir="ltr">
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">المسمى الوظيفي</label>
        <input type="text" name="p_title" value="<?= htmlspecialchars($data['p_title'] ?? '') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">الجنسية</label>
        <input type="text" name="p_nationality" value="<?= htmlspecialchars($data['p_nationality'] ?? '') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">بلد الإقامة</label>
        <input type="text" name="p_residence" value="<?= htmlspecialchars($data['p_residence'] ?? '') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
      </div>
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1">السيرة الذاتية</label>
      <textarea name="p_bio" rows="5"
        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100 resize-none leading-7"><?= htmlspecialchars($data['p_bio'] ?? '') ?></textarea>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">اسم المؤسسة بالعربية <span class="text-red-500">*</span></label>
        <input type="text" name="inst_name_ar" value="<?= htmlspecialchars($data['inst_name_ar'] ?? '') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">اسم المؤسسة بالإنجليزية</label>
        <input type="text" name="inst_name_en" value="<?= htmlspecialchars($data['inst_name_en'] ?? '') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100" dir="ltr">
      </div>
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1">وصف المؤسسة</label>
      <textarea name="inst_description" rows="5"
        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100 resize-none leading-7"><?= htmlspecialchars($data['inst_description'] ?? '') ?></textarea>
    </div>
    <?php endif; ?>

    <!-- Categories -->
    <?php if (!empty($all_cats)): ?>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-2">التصنيفات</label>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-2 bg-gray-50 rounded-xl p-3 max-h-40 overflow-y-auto">
        <?php foreach ($all_cats as $cat): ?>
        <label class="flex items-center gap-2 cursor-pointer text-sm py-0.5">
          <input type="checkbox" name="categories[]" value="<?= $cat['cat_id'] ?>"
            <?= in_array((int)$cat['cat_id'], array_map('intval', $sub_cats)) ? 'checked' : '' ?>
            class="accent-purple-600 w-4 h-4 rounded">
          <span class="text-gray-700"><?= htmlspecialchars($cat['cat_name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="flex gap-3 pt-2 border-t border-gray-100">
      <button type="submit"
        class="flex-1 py-2.5 pi-gradient text-white font-bold rounded-xl text-sm hover:opacity-90 transition flex items-center justify-center gap-2">
        <i class="fa-solid fa-floppy-disk"></i> حفظ التعديلات
      </button>
      <button type="button" onclick="this.closest('dialog').close()"
        class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-sm hover:bg-gray-50 transition">
        إلغاء
      </button>
    </div>
  </form>
</dialog>

<!-- Approve dialog -->
<dialog id="approve-<?= $sub['sub_id'] ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50" dir="rtl">
  <h3 class="font-black text-gray-800 mb-1 flex items-center gap-2"><i class="fa-solid fa-circle-check text-green-500"></i> تأكيد القبول</h3>
  <p class="text-gray-500 text-sm mb-4">سيتم نشر هذه الشخصية على الموقع فوراً</p>
  <form method="POST">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">
    <textarea name="note" rows="2" placeholder="ملاحظة (اختياري)"
      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-green-400 resize-none"></textarea>
    <div class="flex gap-3">
      <button type="submit" class="flex-1 py-2.5 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition">قبول ونشر</button>
      <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
    </div>
  </form>
</dialog>

<!-- Reject dialog -->
<dialog id="reject-<?= $sub['sub_id'] ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50" dir="rtl">
  <h3 class="font-black text-gray-800 mb-3 flex items-center gap-2"><i class="fa-solid fa-circle-xmark text-red-500"></i> رفض الاقتراح</h3>
  <form method="POST">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">
    <textarea name="note" rows="2" placeholder="سبب الرفض (اختياري)"
      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-red-400 resize-none"></textarea>
    <div class="flex gap-3">
      <button type="submit" class="flex-1 py-2.5 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 transition">رفض</button>
      <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
    </div>
  </form>
</dialog>

<?php endif; ?>
<?php endforeach; ?>

<?php if (empty($submissions)): ?>
<div class="text-center py-16 text-gray-400">
  <i class="fa-solid fa-inbox text-5xl mb-4 block"></i>
  <p class="font-bold">لا توجد اقتراحات <?= $status_filter==='pending'?'قيد المراجعة':($status_filter==='approved'?'مقبولة':'مرفوضة') ?></p>
</div>
<?php endif; ?>
</div>
