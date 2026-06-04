<?php
pi_require_perm('view_personalities');

$action = $_GET['action'] ?? 'list';
$msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_personality') {
        $id        = (int)($_POST['p_id'] ?? 0);
        $name_ar   = pi_escape($_POST['p_name_ar'] ?? '');
        $name_en   = pi_escape($_POST['p_name_en'] ?? '');
        $title     = pi_escape($_POST['p_title'] ?? '');
        $national  = pi_escape($_POST['p_nationality'] ?? '');
        $residence = pi_escape($_POST['p_residence'] ?? '');
        $bio       = pi_escape($_POST['p_bio'] ?? '');
        $bio_plat  = pi_escape($_POST['p_bio_platform'] ?? '');
        $photo     = pi_escape($_POST['p_photo'] ?? '');
        if (!empty($_FILES['p_photo_file']['name']) && $_FILES['p_photo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['p_photo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $udir = dirname(__DIR__).'/uploads/';
                if (!is_dir($udir)) mkdir($udir,0755,true);
                $fname = 'p_'.time().'_'.rand(100,999).'.'.$ext;
                if (move_uploaded_file($_FILES['p_photo_file']['tmp_name'], $udir.$fname))
                    $photo = pi_escape('uploads/'.$fname);
            }
        }
        $verified   = (int)($_POST['p_verified'] ?? 0);
        $mtype      = ($_POST['p_executive'] ?? '') ? 'executive' : 'standard';
        $country_id = (int)($_POST['p_country_id'] ?? 0);
        $cats       = $_POST['categories'] ?? [];

        if ($id) {
            pi_require_perm('edit_personality');
            $mysqli->query("UPDATE pi_personalities SET p_name_ar='$name_ar',p_name_en='$name_en',p_title='$title',p_nationality='$national',p_residence='$residence',p_bio='$bio',p_bio_platform='$bio_plat',p_photo='$photo',p_verified=$verified,p_membership_type='$mtype',p_country_id=$country_id WHERE p_id=$id");
            $mysqli->query("DELETE FROM pi_personality_categories WHERE p_id=$id");
        } else {
            pi_require_perm('add_personality');
            $mysqli->query("INSERT INTO pi_personalities (p_name_ar,p_name_en,p_title,p_nationality,p_residence,p_bio,p_bio_platform,p_photo,p_verified,p_membership_type,p_country_id) VALUES ('$name_ar','$name_en','$title','$national','$residence','$bio','$bio_plat','$photo',$verified,'$mtype',$country_id)");
            $id = $mysqli->insert_id;
        }
        foreach ($cats as $cat_id) {
            $cat_id = (int)$cat_id;
            $mysqli->query("INSERT INTO pi_personality_categories (p_id,cat_id) VALUES ($id,$cat_id)");
        }
        $msg = 'تم الحفظ بنجاح';
        $action = 'list';
    }

    if ($act === 'delete_personality') {
        pi_require_perm('delete_personality');
        $id = (int)($_POST['p_id'] ?? 0);
        $mysqli->query("UPDATE pi_personalities SET p_active=0 WHERE p_id=$id");
        $msg = 'تم الحذف';
    }

    if ($act === 'toggle_verify') {
        pi_require_perm('edit_personality');
        $id = (int)($_POST['p_id'] ?? 0);
        $mysqli->query("UPDATE pi_personalities SET p_verified=!p_verified WHERE p_id=$id");
    }
}

$all_cats = pi_get_categories();
$all_countries = pi_get_countries();
$filter = $_GET['filter'] ?? '';
$search = pi_escape($_GET['q'] ?? '');

if ($action === 'add' || $action === 'edit') {
    $edit_p = null;
    $edit_cats = [];
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_id=$eid");
        if ($r && $r->num_rows) $edit_p = $r->fetch_assoc();
        $r = $mysqli->query("SELECT cat_id FROM pi_personality_categories WHERE p_id=$eid");
        if ($r) while ($row=$r->fetch_assoc()) $edit_cats[] = $row['cat_id'];
    }
?>
<!-- Add/Edit form -->
<div class="max-w-3xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=personalities" class="text-gray-400 hover:text-gray-600 transition">
      <i class="fa-solid fa-arrow-right text-lg"></i>
    </a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة شخصية جديدة':'تعديل الشخصية' ?></h2>
  </div>

  <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_personality">
    <?php if ($edit_p): ?><input type="hidden" name="p_id" value="<?= $edit_p['p_id'] ?>"><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="form-label">الاسم بالعربي <span class="text-red-500">*</span></label>
        <input type="text" name="p_name_ar" required class="form-input"
          value="<?= htmlspecialchars($edit_p['p_name_ar'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">الاسم بالإنجليزي</label>
        <input type="text" name="p_name_en" class="form-input" dir="ltr"
          value="<?= htmlspecialchars($edit_p['p_name_en'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">المسمى الوظيفي</label>
        <input type="text" name="p_title" class="form-input"
          value="<?= htmlspecialchars($edit_p['p_title'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">الجنسية</label>
        <select name="p_nationality" class="form-input">
          <option value="">— اختر —</option>
          <?php foreach ($all_countries as $cn): ?>
          <option value="<?= htmlspecialchars($cn['c_name']) ?>" <?= ($edit_p['p_nationality']??'')==$cn['c_name']?'selected':'' ?>>
            <?= htmlspecialchars($cn['c_flag'].' '.$cn['c_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">بلد الإقامة</label>
        <select name="p_residence" class="form-input">
          <option value="">— اختر —</option>
          <?php foreach ($all_countries as $cn): ?>
          <option value="<?= htmlspecialchars($cn['c_name']) ?>" <?= ($edit_p['p_residence']??'')==$cn['c_name']?'selected':'' ?>>
            <?= htmlspecialchars($cn['c_flag'].' '.$cn['c_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      </div>
      <div>
        <label class="form-label">الصورة الشخصية</label>
        <div class="pi-upload-zone" onclick="document.getElementById('p_photo_file').click()">
          <input type="file" id="p_photo_file" name="p_photo_file" accept="image/*" class="hidden" data-preview="p_photo_prev" data-placeholder="p_photo_ph">
          <img id="p_photo_prev" src="<?= htmlspecialchars($edit_p['p_photo']??'') ?>"
            class="<?= ($edit_p['p_photo']??'')?'':'hidden' ?> w-20 h-20 rounded-full object-cover mx-auto mb-2">
          <div id="p_photo_ph" <?= ($edit_p['p_photo']??'')?'style="display:none"':'' ?>>
            <i class="fa-solid fa-camera" style="font-size:24px;color:#9ca3af;display:block;margin-bottom:6px;"></i>
            <p style="font-size:13px;font-weight:700;color:#6b7280;">اضغط لرفع صورة</p>
            <p style="font-size:11px;color:#9ca3af;margin-top:3px;">JPG, PNG, WebP</p>
          </div>
        </div>
        <input type="hidden" name="p_photo" value="<?= htmlspecialchars($edit_p['p_photo']??'') ?>">
      </div>
      <div>
        <label class="form-label">نوع العضوية</label>
        <div style="display:flex;flex-direction:column;gap:10px;padding:12px;border:1.5px solid #e5e7eb;border-radius:12px;background:#fafafa;">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox" name="p_verified" value="1" id="cb_verified"
              <?= ($edit_p['p_verified']??0)?'checked':'' ?> style="width:18px;height:18px;accent-color:#3b82f6;cursor:pointer;">
            <span style="font-size:14px;font-weight:700;color:#374151;display:flex;align-items:center;gap:6px;">
              <i class="fa-solid fa-circle-check" style="color:#3b82f6;"></i> موثقة
            </span>
          </label>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox" name="p_executive" value="1" id="cb_executive"
              <?= ($edit_p['p_membership_type']??'')==='executive'?'checked':'' ?> style="width:18px;height:18px;accent-color:#f59e0b;cursor:pointer;">
            <span style="font-size:14px;font-weight:700;color:#374151;display:flex;align-items:center;gap:6px;">
              <i class="fa-solid fa-crown" style="color:#f59e0b;"></i> رئيس تنفيذي
            </span>
          </label>
        </div>
      </div>
      <div>
        <label class="form-label">الدولة</label>
        <select name="p_country_id" class="form-input">
          <option value="0">— اختر الدولة —</option>
          <?php foreach ($all_countries as $cn): ?>
          <option value="<?= $cn['c_id'] ?>" <?= ($edit_p['p_country_id']??0)==$cn['c_id']?'selected':'' ?>>
            <?= htmlspecialchars($cn['c_flag'].' '.$cn['c_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

    <div class="bg-gray-50 rounded-2xl border border-gray-200 p-5 space-y-1">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
          <i class="fa-solid fa-align-right text-white text-xs"></i>
        </div>
        <label class="form-label mb-0 text-base">نبذة أو سيرة ذاتية</label>
      </div>
      <div id="bio_plat_editor" style="background:#fff;font-family:'Cairo',sans-serif;"></div>
      <textarea name="p_bio_platform" id="p_bio_plat_hidden" class="hidden"></textarea>
    </div>
    <div class="bg-gray-50 rounded-2xl border border-gray-200 p-5 space-y-1">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
          <i class="fa-solid fa-file-lines text-white text-xs"></i>
        </div>
        <label class="form-label mb-0 text-base">السيرة الذاتية الكاملة</label>
      </div>
      <div id="bio_full_editor" style="background:#fff;font-family:'Cairo',sans-serif;"></div>
      <textarea name="p_bio" id="p_bio_hidden" class="hidden"></textarea>
    </div>

    <div>
      <label class="form-label">التصنيفات</label>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-4 border border-gray-200 rounded-xl">
        <?php foreach ($all_cats as $cat): ?>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="categories[]" value="<?= $cat['cat_id'] ?>"
            <?= in_array($cat['cat_id'], $edit_cats)?'checked':'' ?> class="accent-purple-500">
          <span class="text-sm text-gray-700"><?= htmlspecialchars($cat['cat_name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="btn-primary flex items-center gap-2">
        <i class="fa-solid fa-floppy-disk"></i> حفظ الشخصية
      </button>
      <a href="admin.php?p=personalities" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<?php if ($edit_p): ?>
<!-- ====== TIMELINE SECTION ====== -->
<?php
// Handle timeline POST actions here
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tact = $_POST['tl_action'] ?? '';
    if ($tact === 'save_tl' && pi_has_perm('manage_timeline')) {
        $tl_id     = (int)($_POST['tl_id'] ?? 0);
        $tl_pid    = (int)$edit_p['p_id'];
        $tl_type   = pi_escape($_POST['tl_type'] ?? 'work');
        $tl_title  = pi_escape($_POST['tl_title'] ?? '');
        $tl_inst   = pi_escape($_POST['tl_institution'] ?? '');
        $tl_ys     = pi_escape($_POST['tl_year_start'] ?? '');
        $tl_ye     = pi_escape($_POST['tl_year_end'] ?? '');
        $tl_order  = (int)($_POST['tl_order'] ?? 0);
        if ($tl_id) {
            $mysqli->query("UPDATE pi_timeline SET tl_type='$tl_type',tl_title='$tl_title',tl_institution='$tl_inst',tl_year_start='$tl_ys',tl_year_end='$tl_ye',tl_order=$tl_order WHERE tl_id=$tl_id AND tl_p_id=$tl_pid");
        } else {
            $mysqli->query("INSERT INTO pi_timeline (tl_p_id,tl_type,tl_title,tl_institution,tl_year_start,tl_year_end,tl_order) VALUES ($tl_pid,'$tl_type','$tl_title','$tl_inst','$tl_ys','$tl_ye',$tl_order)");
        }
    }
    if ($tact === 'delete_tl' && pi_has_perm('manage_timeline')) {
        $tl_id = (int)($_POST['tl_id'] ?? 0);
        $tl_pid = (int)$edit_p['p_id'];
        $mysqli->query("DELETE FROM pi_timeline WHERE tl_id=$tl_id AND tl_p_id=$tl_pid");
    }
}

// Fetch timeline items for this personality
$tl_items = [];
$r = $mysqli->query("SELECT * FROM pi_timeline WHERE tl_p_id={$edit_p['p_id']} ORDER BY tl_type,tl_order,tl_year_start DESC");
if ($r) while ($row = $r->fetch_assoc()) $tl_items[] = $row;

// Editing a timeline item?
$tl_editing = null;
if (($_GET['tl_edit'] ?? '') && is_numeric($_GET['tl_edit'])) {
    $tl_eid = (int)$_GET['tl_edit'];
    foreach ($tl_items as $ti) { if ($ti['tl_id'] == $tl_eid) { $tl_editing = $ti; break; } }
}
?>

<div style="max-width:700px;margin-top:32px;" x-data="{showForm:<?= $tl_editing ? 'true' : 'false' ?>}">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:900;color:#111827;display:flex;align-items:center;gap:8px;">
      <span style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#8829C8,#5B1494);display:inline-flex;align-items:center;justify-content:center;">
        <i class="fa-solid fa-timeline" style="color:#fff;font-size:12px;"></i>
      </span>
      المحطات الزمنية
      <span style="font-size:12px;color:#9ca3af;font-weight:600;">(<?= count($tl_items) ?>)</span>
    </h3>
    <?php if (pi_has_perm('manage_timeline')): ?>
    <button @click="showForm=!showForm" class="btn-primary" style="padding:7px 14px;font-size:13px;">
      <i class="fa-solid fa-plus"></i>
      <span x-text="showForm ? 'إخفاء النموذج' : 'إضافة محطة'"></span>
    </button>
    <?php endif; ?>
  </div>

  <!-- Add/Edit form -->
  <?php if (pi_has_perm('manage_timeline')): ?>
  <div x-show="showForm" x-cloak x-transition style="background:#f8f5ff;border:1px solid #e9d5ff;border-radius:16px;padding:20px;margin-bottom:20px;">
    <h4 style="font-size:14px;font-weight:800;color:#6b21a8;margin-bottom:14px;">
      <?= $tl_editing ? 'تعديل المحطة' : 'إضافة محطة جديدة' ?>
    </h4>
    <form method="POST" action="admin.php?p=personalities&action=edit&id=<?= $edit_p['p_id'] ?>">
      <input type="hidden" name="tl_action" value="save_tl">
      <?php if ($tl_editing): ?><input type="hidden" name="tl_id" value="<?= $tl_editing['tl_id'] ?>"><?php endif; ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="form-label">النوع</label>
          <select name="tl_type" class="form-input">
            <option value="work"      <?= ($tl_editing['tl_type']??'work')==='work'?'selected':'' ?>>💼 عمل</option>
            <option value="education" <?= ($tl_editing['tl_type']??'')==='education'?'selected':'' ?>>🎓 تعليم</option>
          </select>
        </div>
        <div>
          <label class="form-label">الترتيب</label>
          <input type="number" name="tl_order" class="form-input" value="<?= $tl_editing['tl_order']??0 ?>" placeholder="0">
        </div>
        <div style="grid-column:span 2;">
          <label class="form-label">العنوان (المنصب / الشهادة) <span style="color:#ef4444;">*</span></label>
          <input type="text" name="tl_title" required class="form-input" value="<?= htmlspecialchars($tl_editing['tl_title']??'') ?>" placeholder="مثال: مدير تنفيذي، بكالوريوس هندسة...">
        </div>
        <div style="grid-column:span 2;">
          <label class="form-label">الجهة / المؤسسة</label>
          <input type="text" name="tl_institution" class="form-input" value="<?= htmlspecialchars($tl_editing['tl_institution']??'') ?>" placeholder="اسم الشركة أو الجامعة">
        </div>
        <div>
          <label class="form-label">سنة البداية</label>
          <input type="text" name="tl_year_start" class="form-input" value="<?= htmlspecialchars($tl_editing['tl_year_start']??'') ?>" placeholder="2010">
        </div>
        <div>
          <label class="form-label">سنة الانتهاء</label>
          <input type="text" name="tl_year_end" class="form-input" value="<?= htmlspecialchars($tl_editing['tl_year_end']??'') ?>" placeholder="الآن">
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn-primary" style="font-size:13px;">
          <i class="fa-solid fa-floppy-disk"></i> <?= $tl_editing ? 'حفظ التعديل' : 'إضافة' ?>
        </button>
        <?php if ($tl_editing): ?>
        <a href="admin.php?p=personalities&action=edit&id=<?= $edit_p['p_id'] ?>" class="btn-secondary" style="font-size:13px;">إلغاء</a>
        <?php else: ?>
        <button type="button" @click="showForm=false" class="btn-secondary" style="font-size:13px;">إغلاق</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Timeline list -->
  <?php if (empty($tl_items)): ?>
  <div style="background:#fff;border-radius:14px;border:1px dashed #e5e7eb;padding:32px;text-align:center;color:#9ca3af;">
    <i class="fa-solid fa-timeline" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4;"></i>
    <p style="font-size:14px;font-weight:600;">لا توجد محطات زمنية بعد</p>
    <p style="font-size:12px;margin-top:4px;">اضغط "إضافة محطة" للبدء</p>
  </div>
  <?php else: ?>

  <?php
  $work_items = array_filter($tl_items, function($i){ return $i['tl_type'] === 'work'; });
  $edu_items  = array_filter($tl_items, function($i){ return $i['tl_type'] === 'education'; });
  $sections   = [];
  if ($work_items) $sections['work']      = ['label'=>'💼 مسيرة العمل',   'items'=>$work_items, 'color'=>'#8829C8'];
  if ($edu_items)  $sections['education'] = ['label'=>'🎓 التعليم',        'items'=>$edu_items,  'color'=>'#2563eb'];
  foreach ($sections as $sec):
  ?>
  <div style="margin-bottom:20px;">
    <h4 style="font-size:13px;font-weight:800;color:<?= $sec['color'] ?>;margin-bottom:10px;"><?= $sec['label'] ?></h4>
    <div style="background:#fff;border-radius:14px;border:1px solid #f3f4f6;overflow:hidden;">
      <?php foreach ($sec['items'] as $idx => $tl): ?>
      <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 16px;<?= $idx > 0 ? 'border-top:1px solid #f9fafb;' : '' ?>">
        <div style="width:36px;height:36px;border-radius:10px;background:<?= $sec['color'] ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
          <i class="fa-solid <?= $tl['tl_type']==='education'?'fa-graduation-cap':'fa-briefcase' ?>" style="color:<?= $sec['color'] ?>;font-size:14px;"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <p style="font-size:14px;font-weight:800;color:#111827;margin:0 0 2px;"><?= htmlspecialchars($tl['tl_title']) ?></p>
          <?php if ($tl['tl_institution']): ?>
          <p style="font-size:12px;color:#6b7280;margin:0 0 4px;"><?= htmlspecialchars($tl['tl_institution']) ?></p>
          <?php endif; ?>
          <?php if ($tl['tl_year_start']): ?>
          <span style="font-size:11px;color:#9ca3af;background:#f9fafb;border:1px solid #f3f4f6;border-radius:999px;padding:1px 8px;">
            <?= htmlspecialchars($tl['tl_year_start']) ?><?= $tl['tl_year_end'] ? ' — '.htmlspecialchars($tl['tl_year_end']) : '' ?>
          </span>
          <?php endif; ?>
        </div>
        <?php if (pi_has_perm('manage_timeline')): ?>
        <div style="display:flex;gap:4px;flex-shrink:0;">
          <a href="admin.php?p=personalities&action=edit&id=<?= $edit_p['p_id'] ?>&tl_edit=<?= $tl['tl_id'] ?>#tl-section"
            onclick="document.querySelector('[x-data]').dispatchEvent(new CustomEvent('set-show-form'))"
            style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#f3f4f6;color:#6b7280;text-decoration:none;transition:all .15s;"
            onmouseover="this.style.background='#ede9fe';this.style.color='#7c3aed'" onmouseout="this.style.background='#f3f4f6';this.style.color='#6b7280'">
            <i class="fa-solid fa-pen" style="font-size:11px;"></i>
          </a>
          <form method="POST" action="admin.php?p=personalities&action=edit&id=<?= $edit_p['p_id'] ?>" onsubmit="return confirm('حذف هذه المحطة؟')">
            <input type="hidden" name="tl_action" value="delete_tl">
            <input type="hidden" name="tl_id" value="<?= $tl['tl_id'] ?>">
            <button type="submit" style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#f3f4f6;color:#6b7280;border:none;cursor:pointer;transition:all .15s;"
              onmouseover="this.style.background='#fee2e2';this.style.color='#dc2626'" onmouseout="this.style.background='#f3f4f6';this.style.color='#6b7280'">
              <i class="fa-solid fa-trash" style="font-size:11px;"></i>
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
<a id="tl-section"></a>
<?php endif; ?>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var quillToolbar = [
  [{ header: [2, 3, false] }],
  ['bold', 'italic', 'underline', 'strike'],
  [{ list: 'ordered' }, { list: 'bullet' }],
  ['blockquote'],
  ['clean']
];
var bioPlat = new Quill('#bio_plat_editor', { theme: 'snow', modules: { toolbar: quillToolbar }, placeholder: 'اكتب نبذة مختصرة عن الشخصية...' });
var bioFull = new Quill('#bio_full_editor', { theme: 'snow', modules: { toolbar: quillToolbar }, placeholder: 'اكتب السيرة الذاتية الكاملة...' });
bioPlat.root.setAttribute('dir','rtl');
bioFull.root.setAttribute('dir','rtl');

var epRaw = <?= json_encode($edit_p['p_bio_platform'] ?? '') ?>;
var efRaw = <?= json_encode($edit_p['p_bio'] ?? '') ?>;
if (epRaw) bioPlat.root.innerHTML = epRaw;
if (efRaw) bioFull.root.innerHTML = efRaw;

document.querySelector('form').addEventListener('submit', function() {
  document.getElementById('p_bio_plat_hidden').value = bioPlat.root.innerHTML;
  document.getElementById('p_bio_hidden').value = bioFull.root.innerHTML;
});
</script>

<?php } else { // LIST view
$where = "p_active=1";
if ($search) $where .= " AND p_name_ar LIKE '%$search%'";
if ($filter === 'verified') $where .= " AND p_verified=1";
if ($filter === 'executive') $where .= " AND p_membership_type='executive'";

$page_num = max(1,(int)($_GET['page']??1));
$per_page = 20;
$offset   = ($page_num-1)*$per_page;
$total    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE $where")->fetch_assoc()['c'];
$total_pages = max(1,ceil($total/$per_page));

$list = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE $where ORDER BY p_created DESC LIMIT $offset,$per_page");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm flex items-center gap-2">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="flex flex-wrap items-center gap-3 mb-6">
  <?php if (pi_has_perm('add_personality')): ?>
  <a href="admin.php?p=personalities&action=add" class="btn-primary flex items-center gap-2">
    <i class="fa-solid fa-user-plus"></i> إضافة شخصية
  </a>
  <?php endif; ?>
  <form method="GET" class="flex gap-2 flex-1 max-w-sm">
    <input type="hidden" name="p" value="personalities">
    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>"
      placeholder="بحث عن شخصية..."
      class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 transition">
    <button type="submit" class="btn-primary py-2.5">بحث</button>
  </form>
  <div class="flex gap-2">
    <a href="admin.php?p=personalities" class="px-4 py-2 text-sm font-bold rounded-xl transition <?= !$filter?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">الكل</a>
    <a href="admin.php?p=personalities&filter=verified" class="px-4 py-2 text-sm font-bold rounded-xl transition <?= $filter==='verified'?'bg-blue-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">الموثقون</a>
    <a href="admin.php?p=personalities&filter=executive" class="px-4 py-2 text-sm font-bold rounded-xl transition <?= $filter==='executive'?'bg-yellow-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">الرؤساء التنفيذيون</a>
  </div>
  <span class="text-gray-400 text-sm mr-auto"><?= number_format($total) ?> شخصية</span>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead>
      <tr>
        <th>الشخصية</th>
        <th>المسمى</th>
        <th>العضوية</th>
        <th>التوثيق</th>
        <th>الزيارات</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $row): ?>
      <tr class="hover:bg-gray-50 transition">
        <td>
          <div class="flex items-center gap-3">
            <?php if ($row['p_photo']): ?>
              <img src="<?= htmlspecialchars($row['p_photo']) ?>" class="w-9 h-9 rounded-full object-cover">
            <?php else: ?>
              <div class="w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xs">
                <?= mb_substr($row['p_name_ar'],0,1) ?>
              </div>
            <?php endif; ?>
            <div>
              <p class="font-bold text-gray-800"><?= htmlspecialchars($row['p_name_ar']) ?></p>
              <?php if ($row['p_name_en']): ?><p class="text-gray-400 text-xs" dir="ltr"><?= htmlspecialchars($row['p_name_en']) ?></p><?php endif; ?>
            </div>
          </div>
        </td>
        <td class="text-gray-500"><?= htmlspecialchars($row['p_title'] ?? '—') ?></td>
        <td>
          <?php
          $mt_labels = ['standard'=>['bg-gray-100 text-gray-600','عادية'],'verified'=>['bg-blue-100 text-blue-700','موثقة'],'executive'=>['bg-yellow-100 text-yellow-700','رئيس تنفيذي']];
          $mt = $mt_labels[$row['p_membership_type']] ?? $mt_labels['standard'];
          ?>
          <span class="px-2.5 py-1 <?= $mt[0] ?> rounded-full text-xs font-bold"><?= $mt[1] ?></span>
        </td>
        <td>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="toggle_verify">
            <input type="hidden" name="p_id" value="<?= $row['p_id'] ?>">
            <button type="submit" class="<?= $row['p_verified']?'text-blue-500 hover:text-blue-700':'text-gray-300 hover:text-blue-400' ?> transition text-xl">
              <i class="fa-solid fa-circle-check"></i>
            </button>
          </form>
        </td>
        <td class="font-semibold text-gray-600"><?= number_format($row['p_views']) ?></td>
        <td>
          <div class="flex items-center gap-2">
            <a href="../profile.php?id=<?= $row['p_id'] ?>" target="_blank"
              class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-blue-50 hover:text-blue-500 transition" title="عرض">
              <i class="fa-solid fa-eye text-xs"></i>
            </a>
            <?php if (pi_has_perm('edit_personality')): ?>
            <a href="admin.php?p=personalities&action=edit&id=<?= $row['p_id'] ?>"
              class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition" title="تعديل">
              <i class="fa-solid fa-pen text-xs"></i>
            </a>
            <?php endif; ?>
            <?php if (pi_has_perm('delete_personality')): ?>
            <form method="POST" onsubmit="return confirm('تأكيد حذف الشخصية؟')">
              <input type="hidden" name="action" value="delete_personality">
              <input type="hidden" name="p_id" value="<?= $row['p_id'] ?>">
              <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition" title="حذف">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
      <tr><td colspan="6" class="text-center py-12 text-gray-400">
        <i class="fa-solid fa-users text-4xl mb-3 block"></i> لا توجد شخصيات
      </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-6">
  <?php for ($i=1; $i<=$total_pages; $i++): ?>
  <a href="admin.php?p=personalities&page=<?=$i?>&q=<?=urlencode($_GET['q']??'')?>&filter=<?=urlencode($filter)?>"
    class="w-9 h-9 flex items-center justify-center rounded-xl font-bold text-sm transition
      <?= $i==$page_num?'pi-gradient text-white':'bg-white text-gray-600 border border-gray-200 hover:bg-purple-50' ?>">
    <?=$i?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php } ?>
