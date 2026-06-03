<?php
$msg = '';

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
            $msg = 'تم قبول الاقتراح ونشره';
        }
    }

    if ($act === 'reject') {
        $mysqli->query("UPDATE pi_submissions SET sub_status='rejected',sub_note='$note' WHERE sub_id=$sub_id");
        $msg = 'تم رفض الاقتراح';
    }

    if ($act === 'edit_sub') {
        $r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_id=$sub_id");
        if ($r && $r->num_rows) {
            $sub  = $r->fetch_assoc();
            $data = json_decode($sub['sub_data'], true) ?? [];
            if ($sub['sub_type'] === 'personality') {
                $data['p_name_ar']    = trim($_POST['p_name_ar'] ?? '');
                $data['p_name_en']    = trim($_POST['p_name_en'] ?? '');
                $data['p_title']      = trim($_POST['p_title'] ?? '');
                $data['p_nationality']= trim($_POST['p_nationality'] ?? '');
                $data['p_residence']  = trim($_POST['p_residence'] ?? '');
                $data['p_bio']        = trim($_POST['p_bio'] ?? '');
            } else {
                $data['inst_name_ar']      = trim($_POST['inst_name_ar'] ?? '');
                $data['inst_name_en']      = trim($_POST['inst_name_en'] ?? '');
                $data['inst_description']  = trim($_POST['inst_description'] ?? '');
            }
            $new_json = pi_escape(json_encode($data, JSON_UNESCAPED_UNICODE));
            $mysqli->query("UPDATE pi_submissions SET sub_data='$new_json' WHERE sub_id=$sub_id");
            $msg = 'تم حفظ التعديلات';
        }
    }
}

$status_filter = $_GET['status'] ?? 'pending';
$submissions = [];
$r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_status='$status_filter' ORDER BY sub_created DESC LIMIT 50");
if ($r) while ($row=$r->fetch_assoc()) $submissions[] = $row;

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $counts[$s] = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_status='$s'")->fetch_assoc()['c'];
}

// Pre-load all categories for preview
$all_cats_map = [];
$rc = $mysqli->query("SELECT cat_id, cat_name, cat_icon FROM pi_categories WHERE cat_active=1");
if ($rc) while ($row=$rc->fetch_assoc()) $all_cats_map[$row['cat_id']] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">مقترحات المستخدمين</h2>
  <div class="flex gap-2">
    <a href="admin.php?p=submissions&status=pending"
      class="px-4 py-1.5 text-sm font-bold rounded-full transition flex items-center gap-1.5 <?= $status_filter==='pending'?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600' ?>">
      قيد المراجعة <span class="bg-white/30 px-1.5 rounded-full text-xs"><?= $counts['pending'] ?></span>
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
  $data   = json_decode($sub['sub_data'], true) ?? [];
  $is_p   = $sub['sub_type'] === 'personality';
  $name   = $data['p_name_ar'] ?? $data['inst_name_ar'] ?? 'بدون اسم';
  $sub_id = $sub['sub_id'];
  $photo  = $is_p ? ($data['p_photo'] ?? '') : ($data['inst_logo'] ?? '');
  $title  = $is_p ? ($data['p_title'] ?? '') : ($data['inst_name_en'] ?? '');
  $bio    = $is_p ? ($data['p_bio'] ?? '') : ($data['inst_description'] ?? '');
?>

<!-- CARD -->
<div class="bg-white rounded-2xl shadow-sm p-5">
  <div class="flex items-start gap-4">

    <!-- Avatar -->
    <div class="flex-shrink-0">
      <?php if ($photo): ?>
        <img src="<?= htmlspecialchars($photo) ?>" class="w-12 h-12 <?= $is_p?'rounded-full':'rounded-xl' ?> object-cover border-2 border-gray-100">
      <?php else: ?>
        <div class="w-12 h-12 <?= $is_p?'rounded-full':'rounded-xl' ?> pi-gradient flex items-center justify-center">
          <span class="text-white font-black text-lg"><?= mb_substr($name,0,1) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 mb-1">
        <span class="px-2.5 py-0.5 <?= $is_p?'bg-blue-100 text-blue-700':'bg-indigo-100 text-indigo-700' ?> rounded-full text-xs font-bold">
          <?= $is_p?'شخصية':'مؤسسة' ?>
        </span>
        <span class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($sub['sub_created'])) ?></span>
      </div>
      <h3 class="font-black text-gray-800 text-base leading-tight"><?= htmlspecialchars($name) ?></h3>
      <?php if ($title): ?>
        <p class="text-gray-500 text-sm mt-0.5 truncate"><?= htmlspecialchars($title) ?></p>
      <?php endif; ?>
    </div>

    <!-- Action buttons -->
    <div class="flex items-center gap-2 flex-shrink-0">
      <!-- Preview -->
      <button onclick="document.getElementById('prev-<?= $sub_id ?>').showModal()"
        title="معاينة"
        class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 hover:bg-purple-100 transition flex items-center justify-center">
        <i class="fa-solid fa-eye text-sm"></i>
      </button>
      <?php if ($status_filter === 'pending'): ?>
      <!-- Edit -->
      <button onclick="document.getElementById('edit-<?= $sub_id ?>').showModal()"
        title="تعديل"
        class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-100 transition flex items-center justify-center">
        <i class="fa-solid fa-pen text-sm"></i>
      </button>
      <!-- Approve -->
      <button onclick="document.getElementById('approve-<?= $sub_id ?>').showModal()"
        class="px-4 py-1.5 bg-green-500 text-white text-sm font-bold rounded-xl hover:bg-green-600 transition">
        قبول
      </button>
      <!-- Reject -->
      <button onclick="document.getElementById('reject-<?= $sub_id ?>').showModal()"
        class="px-4 py-1.5 bg-red-50 text-red-600 text-sm font-bold rounded-xl hover:bg-red-100 transition">
        رفض
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== PREVIEW DIALOG ===== -->
<dialog id="prev-<?= $sub_id ?>" class="rounded-2xl shadow-2xl w-full max-w-lg p-0 backdrop:bg-black/50" style="border:none;">
  <div class="bg-white rounded-2xl overflow-hidden">
    <!-- Header -->
    <div class="pi-gradient p-5 flex items-center gap-4">
      <?php if ($photo): ?>
        <img src="<?= htmlspecialchars($photo) ?>" class="w-16 h-16 <?= $is_p?'rounded-full':'rounded-2xl' ?> object-cover border-3 border-white/30 flex-shrink-0">
      <?php else: ?>
        <div class="w-16 h-16 <?= $is_p?'rounded-full':'rounded-2xl' ?> bg-white/20 flex items-center justify-center flex-shrink-0">
          <span class="text-white font-black text-2xl"><?= mb_substr($name,0,1) ?></span>
        </div>
      <?php endif; ?>
      <div>
        <h3 class="text-white font-black text-xl leading-tight"><?= htmlspecialchars($name) ?></h3>
        <?php if ($title): ?>
          <p class="text-purple-200 text-sm mt-0.5"><?= htmlspecialchars($title) ?></p>
        <?php endif; ?>
        <?php if ($is_p && ($data['p_nationality']??'')): ?>
          <p class="text-purple-300 text-xs mt-1"><i class="fa-solid fa-flag ml-1 text-xs"></i><?= htmlspecialchars($data['p_nationality']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Body -->
    <div class="p-5 space-y-4">
      <?php if ($is_p && ($data['p_name_en']??'')): ?>
      <div class="flex gap-3 text-sm"><span class="text-gray-400 w-28 flex-shrink-0">الاسم الإنجليزي</span><span class="font-semibold text-gray-700" dir="ltr"><?= htmlspecialchars($data['p_name_en']) ?></span></div>
      <?php endif; ?>
      <?php if ($is_p && ($data['p_residence']??'')): ?>
      <div class="flex gap-3 text-sm"><span class="text-gray-400 w-28 flex-shrink-0">بلد الإقامة</span><span class="font-semibold text-gray-700"><?= htmlspecialchars($data['p_residence']) ?></span></div>
      <?php endif; ?>
      <?php if (!$is_p && ($data['inst_name_en']??'')): ?>
      <div class="flex gap-3 text-sm"><span class="text-gray-400 w-28 flex-shrink-0">الاسم الإنجليزي</span><span class="font-semibold text-gray-700" dir="ltr"><?= htmlspecialchars($data['inst_name_en']) ?></span></div>
      <?php endif; ?>

      <?php if ($bio): ?>
      <div>
        <p class="text-gray-400 text-xs font-bold mb-1.5"><?= $is_p?'السيرة الذاتية':'الوصف' ?></p>
        <p class="text-gray-700 text-sm leading-7 bg-gray-50 rounded-xl p-3 max-h-32 overflow-y-auto">
          <?= nl2br(htmlspecialchars(mb_substr(strip_tags($bio), 0, 400))) ?><?= mb_strlen(strip_tags($bio))>400?'...':'' ?>
        </p>
      </div>
      <?php endif; ?>

      <?php if (!empty($data['categories'])): ?>
      <div>
        <p class="text-gray-400 text-xs font-bold mb-1.5">التصنيفات</p>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach ($data['categories'] as $cid):
            $cid = (int)$cid;
            $cn  = $all_cats_map[$cid] ?? null; ?>
          <?php if ($cn): ?>
          <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-50 text-purple-700 text-xs font-bold rounded-full">
            <i class="fa-solid <?= htmlspecialchars($cn['cat_icon']) ?> text-xs"></i>
            <?= htmlspecialchars($cn['cat_name']) ?>
          </span>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($sub['sub_submitter_name'] || $sub['sub_submitter_email']): ?>
      <div class="bg-gray-50 rounded-xl p-3 text-sm">
        <p class="text-gray-400 text-xs font-bold mb-1">مقدم الاقتراح</p>
        <?php if ($sub['sub_submitter_name']): ?><p class="font-semibold text-gray-700"><?= htmlspecialchars($sub['sub_submitter_name']) ?></p><?php endif; ?>
        <?php if ($sub['sub_submitter_email']): ?><p class="text-gray-500 text-xs" dir="ltr"><?= htmlspecialchars($sub['sub_submitter_email']) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="px-5 pb-5 flex gap-3">
      <?php if ($status_filter === 'pending'): ?>
      <button onclick="this.closest('dialog').close();document.getElementById('edit-<?= $sub_id ?>').showModal()"
        class="flex-1 py-2.5 bg-amber-50 text-amber-700 font-bold rounded-xl hover:bg-amber-100 transition text-sm">
        <i class="fa-solid fa-pen ml-1"></i> تعديل
      </button>
      <button onclick="this.closest('dialog').close();document.getElementById('approve-<?= $sub_id ?>').showModal()"
        class="flex-1 py-2.5 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition text-sm">
        <i class="fa-solid fa-check ml-1"></i> قبول ونشر
      </button>
      <?php endif; ?>
      <button onclick="this.closest('dialog').close()"
        class="py-2.5 px-5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition text-sm">
        إغلاق
      </button>
    </div>
  </div>
</dialog>

<!-- ===== EDIT DIALOG ===== -->
<?php if ($status_filter === 'pending'): ?>
<dialog id="edit-<?= $sub_id ?>" class="rounded-2xl shadow-2xl w-full max-w-lg p-0 backdrop:bg-black/50" style="border:none;">
  <div class="bg-white rounded-2xl overflow-hidden">
    <div class="bg-amber-50 border-b border-amber-100 px-5 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-amber-400 rounded-xl flex items-center justify-center">
          <i class="fa-solid fa-pen text-white text-sm"></i>
        </div>
        <h3 class="font-black text-amber-800">تعديل الاقتراح</h3>
      </div>
      <button onclick="this.closest('dialog').close()" class="text-amber-400 hover:text-amber-600 transition">
        <i class="fa-solid fa-xmark text-lg"></i>
      </button>
    </div>
    <form method="POST" class="p-5 space-y-4">
      <input type="hidden" name="action" value="edit_sub">
      <input type="hidden" name="sub_id" value="<?= $sub_id ?>">

      <?php if ($is_p): ?>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">الاسم بالعربي</label>
          <input type="text" name="p_name_ar" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition" value="<?= htmlspecialchars($data['p_name_ar']??'') ?>">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">الاسم بالإنجليزي</label>
          <input type="text" name="p_name_en" dir="ltr" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition" value="<?= htmlspecialchars($data['p_name_en']??'') ?>">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">المسمى الوظيفي</label>
          <input type="text" name="p_title" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition" value="<?= htmlspecialchars($data['p_title']??'') ?>">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">الجنسية</label>
          <input type="text" name="p_nationality" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition" value="<?= htmlspecialchars($data['p_nationality']??'') ?>">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-bold text-gray-500 mb-1">بلد الإقامة</label>
          <input type="text" name="p_residence" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition" value="<?= htmlspecialchars($data['p_residence']??'') ?>">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-bold text-gray-500 mb-1">السيرة الذاتية</label>
          <textarea name="p_bio" rows="4" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition resize-none leading-7"><?= htmlspecialchars($data['p_bio']??'') ?></textarea>
        </div>
      </div>

      <?php else: ?>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">اسم المؤسسة (عربي)</label>
          <input type="text" name="inst_name_ar" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition" value="<?= htmlspecialchars($data['inst_name_ar']??'') ?>">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">اسم المؤسسة (إنجليزي)</label>
          <input type="text" name="inst_name_en" dir="ltr" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition" value="<?= htmlspecialchars($data['inst_name_en']??'') ?>">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">الوصف</label>
          <textarea name="inst_description" rows="4" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-amber-400 transition resize-none leading-7"><?= htmlspecialchars($data['inst_description']??'') ?></textarea>
        </div>
      </div>
      <?php endif; ?>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 py-2.5 bg-amber-500 text-white font-bold rounded-xl hover:bg-amber-600 transition text-sm">
          <i class="fa-solid fa-floppy-disk ml-1"></i> حفظ التعديلات
        </button>
        <button type="button" onclick="this.closest('dialog').close()" class="py-2.5 px-5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition text-sm">
          إلغاء
        </button>
      </div>
    </form>
  </div>
</dialog>
<?php endif; ?>

<!-- Approve dialog -->
<?php if ($status_filter === 'pending'): ?>
<dialog id="approve-<?= $sub_id ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50" style="border:none;">
  <h3 class="font-black text-gray-800 mb-3">تأكيد القبول</h3>
  <p class="text-gray-500 text-sm mb-4">سيتم نشر هذا الاقتراح على الموقع فوراً</p>
  <form method="POST">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="sub_id" value="<?= $sub_id ?>">
    <textarea name="note" rows="2" placeholder="ملاحظة (اختياري)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-green-400"></textarea>
    <div class="flex gap-3">
      <button type="submit" class="flex-1 py-2.5 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition">قبول ونشر</button>
      <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
    </div>
  </form>
</dialog>

<dialog id="reject-<?= $sub_id ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50" style="border:none;">
  <h3 class="font-black text-gray-800 mb-3">رفض الاقتراح</h3>
  <form method="POST">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="sub_id" value="<?= $sub_id ?>">
    <textarea name="note" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-red-400"></textarea>
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
