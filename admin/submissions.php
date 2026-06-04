<?php
pi_require_perm('manage_submissions');
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
            } else {
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
                $data['p_name_ar']     = trim($_POST['p_name_ar']     ?? '');
                $data['p_name_en']     = trim($_POST['p_name_en']     ?? '');
                $data['p_title']       = trim($_POST['p_title']       ?? '');
                $data['p_nationality'] = trim($_POST['p_nationality'] ?? '');
                $data['p_residence']   = trim($_POST['p_residence']   ?? '');
                $data['p_bio']         = trim($_POST['p_bio']         ?? '');
                if (!empty($_FILES['p_photo_file']['name']) && $_FILES['p_photo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['p_photo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = dirname(__DIR__).'/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'sub_'.time().'_'.rand(100,999).'.'.$ext;
                        if (move_uploaded_file($_FILES['p_photo_file']['tmp_name'], $udir.$fname))
                            $data['p_photo'] = 'uploads/'.$fname;
                    }
                }
            } else {
                $data['inst_name_ar']     = trim($_POST['inst_name_ar']     ?? '');
                $data['inst_name_en']     = trim($_POST['inst_name_en']     ?? '');
                $data['inst_description'] = trim($_POST['inst_description'] ?? '');
                if (!empty($_FILES['inst_logo_file']['name']) && $_FILES['inst_logo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['inst_logo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = dirname(__DIR__).'/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'sub_'.time().'_'.rand(100,999).'.'.$ext;
                        if (move_uploaded_file($_FILES['inst_logo_file']['tmp_name'], $udir.$fname))
                            $data['inst_logo'] = 'uploads/'.$fname;
                    }
                }
            }
            $data['categories'] = array_map('intval', (array)($_POST['categories'] ?? []));
            $json = pi_escape(json_encode($data, JSON_UNESCAPED_UNICODE));
            $mysqli->query("UPDATE pi_submissions SET sub_data='$json' WHERE sub_id=$sub_id");
            $msg = 'تم حفظ التعديلات بنجاح';
        }
    }
}

$status_filter = in_array($_GET['status'] ?? '', ['pending','approved','rejected']) ? $_GET['status'] : 'pending';
$submissions   = [];
$r = $mysqli->query("SELECT s.*, u.u_name, u.u_email FROM pi_submissions s LEFT JOIN pi_users u ON s.sub_user_id=u.u_id WHERE s.sub_status='$status_filter' ORDER BY s.sub_created DESC LIMIT 50");
if ($r) while ($row = $r->fetch_assoc()) $submissions[] = $row;

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $cnt_r = $mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_status='$s'");
    $counts[$s] = $cnt_r ? (int)$cnt_r->fetch_assoc()['c'] : 0;
}

$all_cats = [];
$rc = $mysqli->query("SELECT cat_id, cat_name FROM pi_categories WHERE cat_active=1 ORDER BY cat_order,cat_name");
if ($rc) while ($c = $rc->fetch_assoc()) $all_cats[] = $c;
?>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.ql-toolbar.ql-snow { direction:ltr; text-align:left; border-radius:10px 10px 0 0 !important; border-color:#e5e7eb !important; }
.ql-container.ql-snow { border-radius:0 0 10px 10px !important; border-color:#e5e7eb !important; font-family:'Cairo',sans-serif !important; font-size:14px !important; }
.ql-editor { direction:rtl; text-align:right; min-height:130px; }
.ql-editor.ql-blank::before { right:15px; left:auto; }
</style>

<?php if ($msg): ?>
<div class="bg-<?= $msg_type ?>-50 border border-<?= $msg_type ?>-200 text-<?= $msg_type ?>-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <h2 class="text-xl font-black text-gray-800">مقترحات المستخدمين</h2>
  <div class="flex gap-2 flex-wrap">
    <a href="admin.php?p=submissions&status=pending"
      class="px-4 py-1.5 text-sm font-bold rounded-full transition flex items-center gap-1.5 <?= $status_filter==='pending'?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600' ?>">
      قيد المراجعة
      <span class="<?= $status_filter==='pending'?'bg-white/30':'bg-gray-100' ?> px-1.5 rounded-full text-xs"><?= $counts['pending'] ?></span>
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

<script>var _subs = {};</script>

<div class="space-y-4">
<?php foreach ($submissions as $sub):
    $data      = json_decode($sub['sub_data'], true) ?? [];
    $is_person = $sub['sub_type'] === 'personality';
    $name      = $data['p_name_ar']    ?? $data['inst_name_ar']    ?? 'بدون اسم';
    $photo     = $data['p_photo']      ?? $data['inst_logo']        ?? '';
    $title     = $data['p_title']      ?? '';
    $bio       = $data['p_bio']        ?? $data['inst_description'] ?? '';
    $sub_cats  = array_map('intval', $data['categories'] ?? []);
    $sid       = $sub['sub_id'];
?>

<div class="bg-white rounded-2xl shadow-sm p-5" x-data="{open:false}">
  <div class="flex items-start gap-4">

    <!-- Thumbnail -->
    <div class="flex-shrink-0">
      <?php if ($photo): ?>
      <img src="../<?= htmlspecialchars($photo) ?>" class="w-14 h-14 rounded-xl object-cover border border-gray-100">
      <?php else: ?>
      <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center text-gray-300 text-2xl">
        <i class="fa-solid fa-<?= $is_person?'user':'building' ?>"></i>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 mb-1 flex-wrap">
        <span class="px-2.5 py-0.5 <?= $is_person?'bg-blue-100 text-blue-700':'bg-indigo-100 text-indigo-700' ?> rounded-full text-xs font-bold">
          <?= $is_person?'شخصية':'مؤسسة' ?>
        </span>
        <span class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($sub['sub_created'])) ?></span>
        <?php if ($sub['u_name']): ?>
        <a href="admin.php?p=users&view=<?= $sub['sub_user_id'] ?>" class="text-purple-600 text-xs font-bold flex items-center gap-1 hover:underline">
          <i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($sub['u_name']) ?>
        </a>
        <?php elseif ($sub['sub_submitter_name']): ?>
        <span class="text-gray-500 text-xs">بواسطة: <?= htmlspecialchars($sub['sub_submitter_name']) ?></span>
        <?php endif; ?>
      </div>
      <h3 class="font-black text-gray-800 text-base"><?= htmlspecialchars($name) ?></h3>
      <?php if ($title): ?><p class="text-gray-500 text-sm"><?= htmlspecialchars($title) ?></p><?php endif; ?>
      <!-- bio preview snippet -->
      <?php if ($bio): ?>
      <p class="text-gray-400 text-xs mt-1 line-clamp-1"><?= htmlspecialchars(mb_substr(strip_tags($bio), 0, 100)) ?>...</p>
      <?php else: ?>
      <p class="text-red-400 text-xs mt-1 font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>لا توجد نبذة</p>
      <?php endif; ?>
    </div>

    <!-- Buttons -->
    <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
      <button onclick="openPreview(<?= $sid ?>)"
        class="px-3 py-1.5 bg-purple-50 text-purple-700 text-sm font-bold rounded-lg hover:bg-purple-100 transition">
        <i class="fa-solid fa-eye text-xs"></i> معاينة
      </button>
      <button @click="open=!open"
        class="px-3 py-1.5 bg-gray-100 text-gray-600 text-sm font-bold rounded-lg hover:bg-gray-200 transition">
        <span x-text="open?'إخفاء':'تفاصيل'"></span>
      </button>
      <?php if ($status_filter === 'pending'): ?>
      <button onclick="openEdit(<?= $sid ?>)"
        class="px-3 py-1.5 bg-amber-50 text-amber-700 text-sm font-bold rounded-lg hover:bg-amber-100 transition">
        <i class="fa-solid fa-pen text-xs"></i> تعديل
      </button>
      <button onclick="document.getElementById('approve-<?= $sid ?>').showModal()"
        class="px-3 py-1.5 bg-green-500 text-white text-sm font-bold rounded-lg hover:bg-green-600 transition">
        <i class="fa-solid fa-check text-xs"></i> قبول
      </button>
      <button onclick="document.getElementById('reject-<?= $sid ?>').showModal()"
        class="px-3 py-1.5 bg-red-50 text-red-600 text-sm font-bold rounded-lg hover:bg-red-100 transition">
        <i class="fa-solid fa-xmark text-xs"></i> رفض
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Expanded details -->
  <div x-show="open" x-cloak x-transition class="mt-4 pt-4 border-t border-gray-100 space-y-3">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
      <?php if ($data['p_name_en'] ?? null): ?><div><span class="text-gray-400 text-xs block">الاسم الإنجليزي</span><p class="font-semibold" dir="ltr"><?= htmlspecialchars($data['p_name_en']) ?></p></div><?php endif; ?>
      <?php if ($data['p_nationality'] ?? null): ?><div><span class="text-gray-400 text-xs block">الجنسية</span><p class="font-semibold"><?= htmlspecialchars($data['p_nationality']) ?></p></div><?php endif; ?>
      <?php if ($data['p_residence'] ?? null): ?><div><span class="text-gray-400 text-xs block">بلد الإقامة</span><p class="font-semibold"><?= htmlspecialchars($data['p_residence']) ?></p></div><?php endif; ?>
      <?php $display_email = $sub['u_email'] ?: $sub['sub_submitter_email']; if ($display_email): ?>
      <div>
        <span class="text-gray-400 text-xs block">البريد<?= $sub['u_name'] ? ' (حساب مسجل)' : '' ?></span>
        <p class="font-semibold text-xs <?= $sub['u_name'] ? 'text-purple-600' : '' ?>" dir="ltr"><?= htmlspecialchars($display_email) ?></p>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($bio): ?>
    <div>
      <span class="text-gray-400 text-xs block mb-1"><?= $is_person?'السيرة الذاتية':'الوصف' ?></span>
      <div class="text-gray-700 text-sm leading-7 bg-gray-50 rounded-lg p-3"><?= $bio ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($sub_cats)): ?>
    <div class="flex flex-wrap gap-1">
      <?php foreach ($sub_cats as $cid):
        $rc2 = $mysqli->query("SELECT cat_name FROM pi_categories WHERE cat_id=$cid");
        if ($rc2 && $rc2->num_rows): $cn=$rc2->fetch_assoc()['cat_name']; ?>
        <span class="px-2 py-0.5 bg-purple-50 text-purple-800 text-xs font-semibold rounded-full"><?= htmlspecialchars($cn) ?></span>
        <?php endif; endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Approve dialog -->
<?php if ($status_filter === 'pending'): ?>
<dialog id="approve-<?= $sid ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50" dir="rtl">
  <h3 class="font-black text-gray-800 mb-1 flex items-center gap-2"><i class="fa-solid fa-circle-check text-green-500"></i> تأكيد القبول</h3>
  <p class="text-gray-500 text-sm mb-4">سيتم نشر «<?= htmlspecialchars($name) ?>» على الموقع فوراً</p>
  <form method="POST">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="sub_id" value="<?= $sid ?>">
    <textarea name="note" rows="2" placeholder="ملاحظة (اختياري)"
      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-green-400 resize-none"></textarea>
    <div class="flex gap-3">
      <button type="submit" class="flex-1 py-2.5 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition">قبول ونشر</button>
      <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl">إلغاء</button>
    </div>
  </form>
</dialog>

<!-- Reject dialog -->
<dialog id="reject-<?= $sid ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50" dir="rtl">
  <h3 class="font-black text-gray-800 mb-3 flex items-center gap-2"><i class="fa-solid fa-circle-xmark text-red-500"></i> رفض الاقتراح</h3>
  <form method="POST">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="sub_id" value="<?= $sid ?>">
    <textarea name="note" rows="2" placeholder="سبب الرفض (اختياري)"
      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-red-400 resize-none"></textarea>
    <div class="flex gap-3">
      <button type="submit" class="flex-1 py-2.5 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 transition">رفض</button>
      <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl">إلغاء</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<!-- Store submission data for JS -->
<script>
_subs[<?= $sid ?>] = {
  type: <?= json_encode($sub['sub_type']) ?>,
  name: <?= json_encode($name) ?>,
  photo: <?= json_encode($photo) ?>,
  title: <?= json_encode($title) ?>,
  bio: <?= json_encode($bio) ?>,
  p_name_ar: <?= json_encode($data['p_name_ar'] ?? '') ?>,
  p_name_en: <?= json_encode($data['p_name_en'] ?? '') ?>,
  p_title: <?= json_encode($data['p_title'] ?? '') ?>,
  p_nationality: <?= json_encode($data['p_nationality'] ?? '') ?>,
  p_residence: <?= json_encode($data['p_residence'] ?? '') ?>,
  p_bio: <?= json_encode($data['p_bio'] ?? '') ?>,
  inst_name_ar: <?= json_encode($data['inst_name_ar'] ?? '') ?>,
  inst_name_en: <?= json_encode($data['inst_name_en'] ?? '') ?>,
  inst_description: <?= json_encode($data['inst_description'] ?? '') ?>,
  cats: <?= json_encode($sub_cats) ?>,
  submitter_name: <?= json_encode($sub['sub_submitter_name'] ?? '') ?>,
  submitter_email: <?= json_encode($sub['sub_submitter_email'] ?? '') ?>
};
</script>

<?php endforeach; ?>

<?php if (empty($submissions)): ?>
<div class="text-center py-16 text-gray-400">
  <i class="fa-solid fa-inbox text-5xl mb-4 block opacity-30"></i>
  <p class="font-bold">لا توجد اقتراحات <?= $status_filter==='pending'?'قيد المراجعة':($status_filter==='approved'?'مقبولة':'مرفوضة') ?></p>
</div>
<?php endif; ?>
</div>

<!-- ═══════════════ PREVIEW DIALOG (shared) ═══════════════ -->
<dialog id="preview-dialog" class="rounded-2xl shadow-2xl w-full max-w-md p-0 overflow-hidden backdrop:bg-black/60" dir="rtl">
  <div class="h-2" style="background:linear-gradient(135deg,#8829C8,#5B1494)"></div>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <span class="text-xs font-bold text-purple-700 bg-purple-50 px-3 py-1 rounded-full">معاينة الاقتراح</span>
      <button onclick="this.closest('dialog').close()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
    </div>
    <div class="bg-gray-50 rounded-2xl p-5 flex flex-col items-center text-center gap-3" id="preview-body"></div>
    <div class="mt-4 flex gap-3" id="preview-actions"></div>
  </div>
</dialog>

<!-- ═══════════════ EDIT DIALOG (shared) ═══════════════ -->
<dialog id="edit-dialog" class="rounded-2xl shadow-2xl w-full max-w-2xl p-0 overflow-hidden backdrop:bg-black/60" dir="rtl">
  <!-- Header with platform gradient -->
  <div class="flex items-center justify-between px-6 py-4" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
    <h3 class="font-black text-white text-lg flex items-center gap-2">
      <i class="fa-solid fa-pen-to-square text-purple-200"></i> تعديل الاقتراح
    </h3>
    <button onclick="this.closest('dialog').close()" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
  </div>

  <form method="POST" id="edit-form" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto max-h-[80vh]">
    <input type="hidden" name="action" value="edit_sub">
    <input type="hidden" name="sub_id" id="edit-sub-id">

    <!-- Personality fields -->
    <div id="edit-personality-fields">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">الاسم بالعربية <span class="text-red-500">*</span></label>
          <input type="text" name="p_name_ar" id="edit-p-name-ar"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">الاسم بالإنجليزية</label>
          <input type="text" name="p_name_en" id="edit-p-name-en" dir="ltr"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">المسمى الوظيفي</label>
          <input type="text" name="p_title" id="edit-p-title"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">الجنسية</label>
          <input type="text" name="p_nationality" id="edit-p-nationality"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">بلد الإقامة</label>
          <input type="text" name="p_residence" id="edit-p-residence"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
        </div>
      </div>
      <div class="mt-4">
        <label class="block text-xs font-bold text-gray-500 mb-1">السيرة الذاتية / النبذة</label>
        <div id="edit-bio-editor" style="min-height:130px;"></div>
        <textarea name="p_bio" id="edit-bio-hidden" class="hidden"></textarea>
      </div>
      <div class="mt-4">
        <label class="block text-xs font-bold text-gray-500 mb-1">الصورة الشخصية</label>
        <div class="flex items-center gap-3">
          <img id="p-photo-preview" src="" class="w-10 h-10 rounded-full object-cover border border-gray-200 hidden flex-shrink-0">
          <label class="flex-1 flex items-center gap-2 border border-gray-200 rounded-xl px-3 py-2 cursor-pointer hover:border-purple-400 transition bg-white">
            <i class="fa-solid fa-image text-gray-400 text-sm"></i>
            <span id="p-photo-file-name" class="text-xs text-gray-400 truncate">اختر صورة...</span>
            <input type="file" name="p_photo_file" id="edit-p-photo-file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
              onchange="previewSubPhoto(this,'p-photo-preview','p-photo-file-name')">
          </label>
        </div>
      </div>
    </div>

    <!-- Institution fields -->
    <div id="edit-institution-fields" class="hidden">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">اسم المؤسسة بالعربية <span class="text-red-500">*</span></label>
          <input type="text" name="inst_name_ar" id="edit-inst-name-ar"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">اسم المؤسسة بالإنجليزية</label>
          <input type="text" name="inst_name_en" id="edit-inst-name-en" dir="ltr"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100">
        </div>
      </div>
      <div class="mt-4">
        <label class="block text-xs font-bold text-gray-500 mb-1">وصف المؤسسة</label>
        <div id="edit-desc-editor" style="min-height:130px;"></div>
        <textarea name="inst_description" id="edit-desc-hidden" class="hidden"></textarea>
      </div>
      <div class="mt-4">
        <label class="block text-xs font-bold text-gray-500 mb-1">شعار المؤسسة</label>
        <div class="flex items-center gap-3">
          <img id="inst-logo-preview" src="" class="w-10 h-10 rounded-xl object-cover border border-gray-200 hidden flex-shrink-0">
          <label class="flex-1 flex items-center gap-2 border border-gray-200 rounded-xl px-3 py-2 cursor-pointer hover:border-purple-400 transition bg-white">
            <i class="fa-solid fa-image text-gray-400 text-sm"></i>
            <span id="inst-logo-file-name" class="text-xs text-gray-400 truncate">اختر صورة...</span>
            <input type="file" name="inst_logo_file" id="edit-inst-logo-file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
              onchange="previewSubPhoto(this,'inst-logo-preview','inst-logo-file-name')">
          </label>
        </div>
      </div>
    </div>

    <!-- Categories -->
    <?php if (!empty($all_cats)): ?>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-2">التصنيفات</label>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-2 bg-gray-50 rounded-xl p-3 max-h-44 overflow-y-auto" id="edit-cats-grid">
        <?php foreach ($all_cats as $cat): ?>
        <label class="flex items-center gap-2 cursor-pointer text-sm py-0.5">
          <input type="checkbox" name="categories[]" value="<?= $cat['cat_id'] ?>"
            class="accent-purple-600 w-4 h-4 rounded edit-cat-check"
            data-catid="<?= $cat['cat_id'] ?>">
          <span class="text-gray-700"><?= htmlspecialchars($cat['cat_name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="flex gap-3 pt-2 border-t border-gray-100">
      <button type="submit"
        class="flex-1 py-3 text-white font-black rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2"
        style="background:linear-gradient(135deg,#8829C8,#5B1494)">
        <i class="fa-solid fa-floppy-disk"></i> حفظ التعديلات
      </button>
      <button type="button" onclick="this.closest('dialog').close()"
        class="flex-1 py-3 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">
        إلغاء
      </button>
    </div>
  </form>
</dialog>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
// Quill instances for edit dialog
var _bioQuill, _descQuill;

function _initQuills() {
  var opts = {
    theme: 'snow',
    modules: { toolbar: [['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['clean']] }
  };
  if (!_bioQuill) {
    _bioQuill  = new Quill('#edit-bio-editor',  opts);
    _bioQuill.root.setAttribute('dir','rtl');
    _bioQuill.root.style.minHeight = '130px';
  }
  if (!_descQuill) {
    _descQuill = new Quill('#edit-desc-editor', opts);
    _descQuill.root.setAttribute('dir','rtl');
    _descQuill.root.style.minHeight = '130px';
  }
}

// PREVIEW
function openPreview(sid) {
  var s = _subs[sid];
  if (!s) return;
  var d = document.getElementById('preview-body');
  var photoHtml = s.photo
    ? '<img src="../'+esc(s.photo)+'" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">'
    : '<div style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-'+(s.type==='personality'?'user':'building')+'" style="color:#fff;font-size:36px;"></i></div>';
  var bioText = s.bio ? '<div style="font-size:13px;color:#4b5563;line-height:1.8;background:#fff;border-radius:12px;padding:12px 16px;width:100%;text-align:right;">'+s.bio+'</div>' : '<p style="font-size:12px;color:#f87171;font-weight:700;"><i class="fa-solid fa-triangle-exclamation"></i> لا توجد نبذة</p>';
  d.innerHTML = photoHtml +
    '<div><h3 style="font-size:20px;font-weight:900;color:#111827;">'+esc(s.name)+'</h3>'+(s.title?'<p style="color:#8829C8;font-weight:700;font-size:14px;">'+esc(s.title)+'</p>':'')+'</div>' +
    bioText;
  <?php if ($status_filter === 'pending'): ?>
  document.getElementById('preview-actions').innerHTML =
    '<button onclick="document.getElementById(\'preview-dialog\').close();document.getElementById(\'approve-'+sid+'\').showModal()" style="flex:1;padding:10px;background:#22c55e;color:#fff;font-weight:700;border-radius:12px;border:none;cursor:pointer;font-family:inherit;font-size:14px;"><i class="fa-solid fa-check"></i> قبول</button>' +
    '<button onclick="document.getElementById(\'preview-dialog\').close();openEdit('+sid+')" style="flex:1;padding:10px;background:#fef3c7;color:#92400e;font-weight:700;border-radius:12px;border:none;cursor:pointer;font-family:inherit;font-size:14px;"><i class="fa-solid fa-pen"></i> تعديل</button>' +
    '<button onclick="document.getElementById(\'preview-dialog\').close()" style="flex:1;padding:10px;border:1px solid #e5e7eb;color:#6b7280;font-weight:700;border-radius:12px;background:#fff;cursor:pointer;font-family:inherit;font-size:14px;">إغلاق</button>';
  <?php else: ?>
  document.getElementById('preview-actions').innerHTML =
    '<button onclick="document.getElementById(\'preview-dialog\').close()" style="flex:1;padding:10px;border:1px solid #e5e7eb;color:#6b7280;font-weight:700;border-radius:12px;background:#fff;cursor:pointer;font-family:inherit;font-size:14px;">إغلاق</button>';
  <?php endif; ?>
  document.getElementById('preview-dialog').showModal();
}

// EDIT
function openEdit(sid) {
  var s = _subs[sid];
  if (!s) return;
  _initQuills();

  document.getElementById('edit-sub-id').value = sid;

  var isPerson = s.type === 'personality';
  document.getElementById('edit-personality-fields').classList.toggle('hidden', !isPerson);
  document.getElementById('edit-institution-fields').classList.toggle('hidden', isPerson);

  if (isPerson) {
    document.getElementById('edit-p-name-ar').value     = s.p_name_ar     || '';
    document.getElementById('edit-p-name-en').value     = s.p_name_en     || '';
    document.getElementById('edit-p-title').value       = s.p_title       || '';
    document.getElementById('edit-p-nationality').value = s.p_nationality  || '';
    document.getElementById('edit-p-residence').value   = s.p_residence    || '';
    var pprev = document.getElementById('p-photo-preview');
    if (pprev && s.photo) { pprev.src = s.photo.startsWith('http') ? s.photo : '../'+s.photo; pprev.classList.remove('hidden'); } else if (pprev) pprev.classList.add('hidden');
    var pf = document.getElementById('edit-p-photo-file'); if (pf) { pf.value=''; document.getElementById('p-photo-file-name').textContent='اختر صورة...'; }
    _bioQuill.root.innerHTML  = s.p_bio || '<p></p>';
  } else {
    document.getElementById('edit-inst-name-ar').value = s.inst_name_ar     || '';
    document.getElementById('edit-inst-name-en').value = s.inst_name_en     || '';
    var iprev = document.getElementById('inst-logo-preview');
    if (iprev && s.photo) { iprev.src = s.photo.startsWith('http') ? s.photo : '../'+s.photo; iprev.classList.remove('hidden'); } else if (iprev) iprev.classList.add('hidden');
    var ilf = document.getElementById('edit-inst-logo-file'); if (ilf) { ilf.value=''; document.getElementById('inst-logo-file-name').textContent='اختر صورة...'; }
    _descQuill.root.innerHTML = s.inst_description || '<p></p>';
  }

  // Categories checkboxes
  document.querySelectorAll('.edit-cat-check').forEach(function(cb) {
    cb.checked = s.cats.indexOf(parseInt(cb.dataset.catid)) !== -1;
  });

  document.getElementById('edit-dialog').showModal();
}

// Sync Quill → hidden textarea before submit
document.getElementById('edit-form').addEventListener('submit', function() {
  if (_bioQuill)  document.getElementById('edit-bio-hidden').value  = _bioQuill.root.innerHTML;
  if (_descQuill) document.getElementById('edit-desc-hidden').value = _descQuill.root.innerHTML;
});

function esc(s){ var d=document.createElement('div');d.textContent=s||'';return d.innerHTML; }
function previewSubPhoto(input, prevId, nameId) {
  var prev = document.getElementById(prevId);
  var nm   = document.getElementById(nameId);
  if (input.files && input.files[0]) {
    var r = new FileReader();
    r.onload = function(e) { if (prev) { prev.src = e.target.result; prev.classList.remove('hidden'); } };
    r.readAsDataURL(input.files[0]);
    if (nm) nm.textContent = input.files[0].name;
  }
}
</script>
