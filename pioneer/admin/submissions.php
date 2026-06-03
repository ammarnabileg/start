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

    // Edit submission data
    if ($act === 'edit_sub') {
        $r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_id=$sub_id");
        if ($r && $r->num_rows) {
            $sub  = $r->fetch_assoc();
            $data = json_decode($sub['sub_data'], true) ?? [];
            if ($sub['sub_type'] === 'personality') {
                $data['p_name_ar']    = $_POST['p_name_ar']    ?? $data['p_name_ar'];
                $data['p_name_en']    = $_POST['p_name_en']    ?? $data['p_name_en'];
                $data['p_title']      = $_POST['p_title']      ?? $data['p_title'];
                $data['p_nationality']= $_POST['p_nationality']?? $data['p_nationality'];
                $data['p_residence']  = $_POST['p_residence']  ?? $data['p_residence'];
                $data['p_bio']        = $_POST['p_bio']        ?? $data['p_bio'];
            } else {
                $data['inst_name_ar']    = $_POST['inst_name_ar']    ?? $data['inst_name_ar'];
                $data['inst_name_en']    = $_POST['inst_name_en']    ?? $data['inst_name_en'];
                $data['inst_description']= $_POST['inst_description']?? $data['inst_description'];
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

$all_cats = pi_get_categories();
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">مقترحات المستخدمين</h2>
  <div class="flex gap-2">
    <a href="admin.php?p=submissions&status=pending" class="px-4 py-1.5 text-sm font-bold rounded-full transition flex items-center gap-1.5 <?= $status_filter==='pending'?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600' ?>">
      قيد المراجعة <span class="bg-white/30 px-1.5 rounded-full text-xs"><?= $counts['pending'] ?></span>
    </a>
    <a href="admin.php?p=submissions&status=approved" class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $status_filter==='approved'?'bg-green-500 text-white':'bg-white border border-gray-200 text-gray-600' ?>">مقبولة (<?= $counts['approved'] ?>)</a>
    <a href="admin.php?p=submissions&status=rejected" class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $status_filter==='rejected'?'bg-red-500 text-white':'bg-white border border-gray-200 text-gray-600' ?>">مرفوضة (<?= $counts['rejected'] ?>)</a>
  </div>
</div>

<div class="space-y-4">
  <?php foreach ($submissions as $sub):
    $data = json_decode($sub['sub_data'], true) ?? [];
    $is_personality = $sub['sub_type'] === 'personality';
    $display_name = $data['p_name_ar'] ?? $data['inst_name_ar'] ?? 'بدون اسم';
    $display_sub  = $data['p_title'] ?? mb_substr(strip_tags($data['inst_description'] ?? ''), 0, 80);
    $display_photo= $data['p_photo'] ?? $data['inst_logo'] ?? '';
  ?>
  <div class="bg-white rounded-2xl shadow-sm p-5">
    <div class="flex items-start justify-between gap-4">

      <!-- Photo / Logo -->
      <div class="flex-shrink-0">
        <?php if ($display_photo): ?>
        <img src="../<?= htmlspecialchars($display_photo) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;">
        <?php else: ?>
        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;">
          <i class="fa-solid <?= $is_personality?'fa-user':'fa-building' ?>" style="color:#fff;font-size:20px;"></i>
        </div>
        <?php endif; ?>
      </div>

      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
          <span class="px-2.5 py-0.5 <?= $is_personality?'bg-blue-100 text-blue-700':'bg-indigo-100 text-indigo-700' ?> rounded-full text-xs font-bold">
            <?= $is_personality?'شخصية':'مؤسسة' ?>
          </span>
          <span class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($sub['sub_created'])) ?></span>
          <?php if ($sub['sub_submitter_name']): ?>
          <span class="text-gray-500 text-xs">بواسطة: <?= htmlspecialchars($sub['sub_submitter_name']) ?></span>
          <?php endif; ?>
        </div>
        <h3 class="font-black text-gray-800 text-base"><?= htmlspecialchars($display_name) ?></h3>
        <?php if ($display_sub): ?>
        <p class="text-gray-500 text-sm mt-0.5 line-clamp-1"><?= htmlspecialchars($display_sub) ?></p>
        <?php endif; ?>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
        <!-- Preview -->
        <button onclick="document.getElementById('prev-<?= $sub['sub_id'] ?>').showModal()"
          title="معاينة"
          class="w-9 h-9 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center hover:bg-purple-100 transition">
          <i class="fa-solid fa-eye text-sm"></i>
        </button>
        <!-- Edit -->
        <?php if ($status_filter === 'pending'): ?>
        <button onclick="document.getElementById('edit-<?= $sub['sub_id'] ?>').showModal()"
          title="تعديل"
          class="w-9 h-9 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center hover:bg-amber-100 transition">
          <i class="fa-solid fa-pen text-sm"></i>
        </button>
        <!-- Approve -->
        <button onclick="document.getElementById('approve-<?= $sub['sub_id'] ?>').showModal()"
          class="px-4 py-1.5 bg-green-500 text-white text-sm font-bold rounded-lg hover:bg-green-600 transition">
          قبول
        </button>
        <!-- Reject -->
        <button onclick="document.getElementById('reject-<?= $sub['sub_id'] ?>').showModal()"
          class="px-4 py-1.5 bg-red-50 text-red-600 text-sm font-bold rounded-lg hover:bg-red-100 transition">
          رفض
        </button>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($sub['sub_note']): ?>
    <div class="mt-3 bg-yellow-50 rounded-lg px-4 py-2.5 text-sm">
      <span class="text-yellow-700 font-bold">ملاحظة: </span>
      <span class="text-yellow-800"><?= htmlspecialchars($sub['sub_note']) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- ===== PREVIEW DIALOG ===== -->
  <dialog id="prev-<?= $sub['sub_id'] ?>" class="rounded-2xl p-0 shadow-2xl w-full max-w-lg backdrop:bg-black/50 overflow-hidden">
    <div style="background:linear-gradient(135deg,#8829C8,#5B1494);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
      <span style="color:#fff;font-weight:900;font-size:15px;">معاينة — <?= htmlspecialchars($display_name) ?></span>
      <button onclick="this.closest('dialog').close()" style="color:rgba(255,255,255,.7);background:none;border:none;cursor:pointer;font-size:18px;">✕</button>
    </div>
    <div style="padding:24px;font-family:'Cairo',sans-serif;">
      <!-- Card -->
      <div style="display:flex;gap:16px;align-items:flex-start;padding:16px;background:#f9fafb;border-radius:16px;border:1.5px solid #e5e7eb;margin-bottom:16px;">
        <?php if ($display_photo): ?>
        <img src="../<?= htmlspecialchars($display_photo) ?>" style="width:80px;height:80px;border-radius:<?= $is_personality?'50%':'14px' ?>;object-fit:cover;flex-shrink:0;border:2px solid #e5e7eb;">
        <?php else: ?>
        <div style="width:80px;height:80px;border-radius:<?= $is_personality?'50%':'14px' ?>;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa-solid <?= $is_personality?'fa-user':'fa-building' ?>" style="color:#fff;font-size:28px;"></i>
        </div>
        <?php endif; ?>
        <div style="flex:1;min-width:0;">
          <h3 style="font-size:18px;font-weight:900;color:#111827;margin:0 0 4px;"><?= htmlspecialchars($display_name) ?></h3>
          <?php if ($is_personality && ($data['p_name_en']??'')): ?>
          <p style="font-size:13px;color:#9ca3af;margin:0 0 6px;" dir="ltr"><?= htmlspecialchars($data['p_name_en']) ?></p>
          <?php endif; ?>
          <?php if ($is_personality && ($data['p_title']??'')): ?>
          <p style="font-size:13px;color:#374151;font-weight:600;margin:0 0 6px;"><?= htmlspecialchars($data['p_title']) ?></p>
          <?php endif; ?>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
            <?php if ($is_personality && ($data['p_nationality']??'')): ?>
            <span style="font-size:11px;background:#f3f4f6;color:#6b7280;border-radius:999px;padding:2px 10px;font-weight:700;">🌍 <?= htmlspecialchars($data['p_nationality']) ?></span>
            <?php endif; ?>
            <?php if ($is_personality && ($data['p_residence']??'')): ?>
            <span style="font-size:11px;background:#f3f4f6;color:#6b7280;border-radius:999px;padding:2px 10px;font-weight:700;"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($data['p_residence']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php $bio_text = $is_personality ? ($data['p_bio']??'') : ($data['inst_description']??''); ?>
      <?php if ($bio_text): ?>
      <div style="background:#faf5ff;border-right:4px solid #8829C8;border-radius:10px;padding:14px;margin-bottom:16px;font-size:13px;color:#374151;line-height:1.9;">
        <?= nl2br(htmlspecialchars(mb_substr(strip_tags($bio_text), 0, 300))) ?>
        <?php if (mb_strlen(strip_tags($bio_text)) > 300): ?><span style="color:#8829C8;font-weight:700;">... (عرض المزيد)</span><?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($data['categories'])): ?>
      <div>
        <p style="font-size:12px;color:#9ca3af;font-weight:700;margin-bottom:8px;">التصنيفات</p>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
          <?php foreach ($data['categories'] as $cid):
            $r2 = $mysqli->query("SELECT cat_name,cat_icon FROM pi_categories WHERE cat_id=".(int)$cid);
            if ($r2 && $r2->num_rows) { $cn=$r2->fetch_assoc(); ?>
            <span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;background:#f5f0ff;color:#6b21a8;border-radius:999px;font-size:12px;font-weight:700;">
              <i class="fa-solid <?= htmlspecialchars($cn['cat_icon']??'fa-tag') ?>" style="font-size:10px;"></i>
              <?= htmlspecialchars($cn['cat_name']) ?>
            </span>
          <?php } endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($sub['sub_submitter_name'] || $sub['sub_submitter_email']): ?>
      <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;">
        مقدّم من: <?= htmlspecialchars($sub['sub_submitter_name']??' ') ?>
        <?php if ($sub['sub_submitter_email']): ?>— <span dir="ltr"><?= htmlspecialchars($sub['sub_submitter_email']) ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <div style="padding:0 24px 20px;display:flex;gap:10px;">
      <?php if ($status_filter === 'pending'): ?>
      <button onclick="this.closest('dialog').close();document.getElementById('edit-<?= $sub['sub_id'] ?>').showModal()"
        style="flex:1;padding:10px;background:#fef3c7;color:#92400e;border:none;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;">
        <i class="fa-solid fa-pen mr-1"></i> تعديل
      </button>
      <button onclick="this.closest('dialog').close();document.getElementById('approve-<?= $sub['sub_id'] ?>').showModal()"
        style="flex:1;padding:10px;background:#22c55e;color:#fff;border:none;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;">
        <i class="fa-solid fa-check mr-1"></i> قبول ونشر
      </button>
      <?php else: ?>
      <button onclick="this.closest('dialog').close()" style="flex:1;padding:10px;background:#f3f4f6;color:#374151;border:none;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;">إغلاق</button>
      <?php endif; ?>
    </div>
  </dialog>

  <!-- ===== EDIT DIALOG ===== -->
  <?php if ($status_filter === 'pending'): ?>
  <dialog id="edit-<?= $sub['sub_id'] ?>" class="rounded-2xl p-0 shadow-2xl w-full max-w-xl backdrop:bg-black/50 overflow-hidden">
    <div style="background:#f59e0b;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;">
      <span style="color:#fff;font-weight:900;font-size:15px;"><i class="fa-solid fa-pen mr-2"></i>تعديل البيانات</span>
      <button onclick="this.closest('dialog').close()" style="color:rgba(255,255,255,.8);background:none;border:none;cursor:pointer;font-size:18px;">✕</button>
    </div>
    <form method="POST" style="padding:24px;font-family:'Cairo',sans-serif;max-height:70vh;overflow-y:auto;">
      <input type="hidden" name="action" value="edit_sub">
      <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">

      <?php if ($is_personality): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">الاسم بالعربي *</label>
          <input type="text" name="p_name_ar" value="<?= htmlspecialchars($data['p_name_ar']??'') ?>" required
            style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">الاسم بالإنجليزي</label>
          <input type="text" name="p_name_en" value="<?= htmlspecialchars($data['p_name_en']??'') ?>" dir="ltr"
            style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">المسمى الوظيفي</label>
          <input type="text" name="p_title" value="<?= htmlspecialchars($data['p_title']??'') ?>"
            style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">الجنسية</label>
          <input type="text" name="p_nationality" value="<?= htmlspecialchars($data['p_nationality']??'') ?>"
            style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div class="col-span-2" style="grid-column:span 2">
          <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">بلد الإقامة</label>
          <input type="text" name="p_residence" value="<?= htmlspecialchars($data['p_residence']??'') ?>"
            style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">السيرة الذاتية</label>
        <textarea name="p_bio" rows="5" style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:13px;font-family:inherit;outline:none;resize:vertical;box-sizing:border-box;line-height:1.7;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'"><?= htmlspecialchars($data['p_bio']??'') ?></textarea>
      </div>

      <?php else: ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">الاسم بالعربي *</label>
          <input type="text" name="inst_name_ar" value="<?= htmlspecialchars($data['inst_name_ar']??'') ?>" required
            style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">الاسم بالإنجليزي</label>
          <input type="text" name="inst_name_en" value="<?= htmlspecialchars($data['inst_name_en']??'') ?>" dir="ltr"
            style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;">الوصف</label>
        <textarea name="inst_description" rows="4" style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:13px;font-family:inherit;outline:none;resize:vertical;box-sizing:border-box;line-height:1.7;" onfocus="this.style.borderColor='#f59e0b'" onblur="this.style.borderColor='#e5e7eb'"><?= htmlspecialchars($data['inst_description']??'') ?></textarea>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:11px;background:#f59e0b;color:#fff;border:none;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;">
          <i class="fa-solid fa-floppy-disk mr-1"></i> حفظ التعديلات
        </button>
        <button type="button" onclick="this.closest('dialog').close()" style="flex:1;padding:11px;background:#f3f4f6;color:#374151;border:none;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;">إلغاء</button>
      </div>
    </form>
  </dialog>

  <!-- Approve dialog -->
  <dialog id="approve-<?= $sub['sub_id'] ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50">
    <h3 class="font-black text-gray-800 mb-3">تأكيد القبول</h3>
    <p class="text-gray-500 text-sm mb-4">سيتم نشر <?= $is_personality?'هذه الشخصية':'هذه المؤسسة' ?> على الموقع فوراً</p>
    <form method="POST">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">
      <textarea name="note" rows="2" placeholder="ملاحظة (اختياري)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-green-400"></textarea>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 py-2.5 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition">قبول ونشر</button>
        <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
      </div>
    </form>
  </dialog>

  <dialog id="reject-<?= $sub['sub_id'] ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50">
    <h3 class="font-black text-gray-800 mb-3">رفض الاقتراح</h3>
    <form method="POST">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">
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
