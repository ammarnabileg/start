<?php
pi_require_perm('manage_edit_requests');
$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action'] ?? '';
    $er_id = (int)($_POST['er_id'] ?? 0);
    $note  = pi_escape(trim($_POST['admin_note'] ?? ''));

    if ($act === 'approve' && $er_id) {
        $r = $mysqli->query("SELECT * FROM pi_edit_requests WHERE er_id=$er_id");
        if ($r && $r->num_rows) {
            $req = $r->fetch_assoc();
            $edit_data = json_decode($req['er_edit_data'] ?? '{}', true) ?? [];
            if ($req['er_req_type'] === 'upgrade') {
                $upto = pi_escape($req['er_upgrade_to']);
                if ($req['er_entity_type'] === 'personality') {
                    $verified = $upto ? 1 : 0;
                    $mtype = $upto === 'executive' ? 'executive' : 'verified';
                    $mysqli->query("UPDATE pi_personalities SET p_verified=$verified, p_membership_type='$mtype' WHERE p_id={$req['er_entity_id']}");
                } else {
                    $verified = $upto ? 1 : 0;
                    $mtype = $upto === 'executive' ? 'executive' : 'verified';
                    $mysqli->query("UPDATE pi_institutions SET inst_verified=$verified, inst_membership_type='$mtype' WHERE inst_id={$req['er_entity_id']}");
                }
            } elseif ($req['er_req_type'] === 'edit' && !empty($edit_data)) {
                if ($req['er_entity_type'] === 'personality') {
                    $sets = [];
                    $map = ['name_ar'=>'p_name_ar','name_en'=>'p_name_en','title'=>'p_title','nationality'=>'p_nationality','bio'=>'p_bio','photo'=>'p_photo'];
                    foreach ($map as $key => $col) {
                        if (!empty($edit_data[$key])) $sets[] = "$col='" . pi_escape($edit_data[$key]) . "'";
                    }
                    if ($sets) $mysqli->query("UPDATE pi_personalities SET " . implode(',', $sets) . " WHERE p_id={$req['er_entity_id']}");
                } else {
                    $sets = [];
                    $map = ['name_ar'=>'inst_name_ar','name_en'=>'inst_name_en','description'=>'inst_description','photo'=>'inst_logo'];
                    foreach ($map as $key => $col) {
                        if (!empty($edit_data[$key])) $sets[] = "$col='" . pi_escape($edit_data[$key]) . "'";
                    }
                    if ($sets) $mysqli->query("UPDATE pi_institutions SET " . implode(',', $sets) . " WHERE inst_id={$req['er_entity_id']}");
                }
            }
            $mysqli->query("UPDATE pi_edit_requests SET er_status='approved', er_admin_note='$note' WHERE er_id=$er_id");
            $msg = 'تم قبول الطلب وتطبيق التعديلات';
        }
    }

    if ($act === 'reject' && $er_id) {
        $mysqli->query("UPDATE pi_edit_requests SET er_status='rejected', er_admin_note='$note' WHERE er_id=$er_id");
        $msg = 'تم رفض الطلب';
    }

    if ($act === 'apply_edit' && $er_id) {
        $r = $mysqli->query("SELECT * FROM pi_edit_requests WHERE er_id=$er_id");
        if ($r && $r->num_rows) {
            $req = $r->fetch_assoc();
            $is_p = $req['er_entity_type'] === 'personality';
            if ($is_p) {
                $map = ['name_ar'=>'p_name_ar','name_en'=>'p_name_en','title'=>'p_title','nationality'=>'p_nationality','residence'=>'p_residence','bio'=>'p_bio'];
                $sets = [];
                foreach ($map as $key => $col) {
                    if (isset($_POST[$key])) $sets[] = "$col='" . pi_escape(trim($_POST[$key])) . "'";
                }
                if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = dirname(__DIR__).'/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'er_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $udir.$fname))
                            $sets[] = "p_photo='uploads/$fname'";
                    }
                }
                if ($sets) $mysqli->query("UPDATE pi_personalities SET " . implode(',', $sets) . " WHERE p_id={$req['er_entity_id']}");
            } else {
                $map = ['name_ar'=>'inst_name_ar','name_en'=>'inst_name_en','description'=>'inst_description'];
                $sets = [];
                foreach ($map as $key => $col) {
                    if (isset($_POST[$key])) $sets[] = "$col='" . pi_escape(trim($_POST[$key])) . "'";
                }
                if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = dirname(__DIR__).'/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'er_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $udir.$fname))
                            $sets[] = "inst_logo='uploads/$fname'";
                    }
                }
                if ($sets) $mysqli->query("UPDATE pi_institutions SET " . implode(',', $sets) . " WHERE inst_id={$req['er_entity_id']}");
            }
            $mysqli->query("UPDATE pi_edit_requests SET er_status='approved', er_admin_note='$note' WHERE er_id=$er_id");
            $msg = 'تم تطبيق التعديلات بنجاح';
        }
    }
}

// Filters
$status_filter = $_GET['status'] ?? 'pending';
$type_filter   = $_GET['etype'] ?? '';
$allowed_statuses = ['pending','approved','rejected'];
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'pending';

$where = "WHERE er.er_status='" . pi_escape($status_filter) . "' AND er.er_req_type='edit'";
if ($type_filter === 'personality' || $type_filter === 'institution') $where .= " AND er.er_entity_type='" . pi_escape($type_filter) . "'";

$counts = [];
foreach ($allowed_statuses as $s) {
    $cr = $mysqli->query("SELECT COUNT(*) c FROM pi_edit_requests WHERE er_status='$s' AND er_req_type='edit'");
    $counts[$s] = $cr ? (int)$cr->fetch_assoc()['c'] : 0;
}

$requests = [];
$r = $mysqli->query("SELECT er.*,
    u.u_name, u.u_email, u.u_id AS uid,
    CASE WHEN er.er_entity_type='personality' THEN (SELECT p_name_ar FROM pi_personalities WHERE p_id=er.er_entity_id)
         ELSE (SELECT inst_name_ar FROM pi_institutions WHERE inst_id=er.er_entity_id) END AS entity_name,
    CASE WHEN er.er_entity_type='personality' THEN (SELECT p_photo FROM pi_personalities WHERE p_id=er.er_entity_id)
         ELSE (SELECT inst_logo FROM pi_institutions WHERE inst_id=er.er_entity_id) END AS entity_photo
    FROM pi_edit_requests er
    LEFT JOIN pi_users u ON er.er_user_id=u.u_id
    $where ORDER BY er.er_created DESC LIMIT 100");
if ($r) while ($row=$r->fetch_assoc()) $requests[] = $row;

$status_map = [
    'pending'  => ['text'=>'قيد المراجعة','class'=>'bg-yellow-100 text-yellow-700'],
    'approved' => ['text'=>'مقبول',       'class'=>'bg-green-100 text-green-700'],
    'rejected' => ['text'=>'مرفوض',       'class'=>'bg-red-100 text-red-700'],
];
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check ml-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <h2 class="text-xl font-black text-gray-800">طلبات التعديل</h2>
  <div class="flex gap-2 flex-wrap">
    <?php foreach (['pending'=>'قيد المراجعة','approved'=>'مقبولة','rejected'=>'مرفوضة'] as $sk=>$sl): ?>
    <a href="admin.php?p=edit_requests&status=<?= $sk ?>"
      class="px-4 py-1.5 text-sm font-bold rounded-full transition flex items-center gap-1.5 <?= $status_filter===$sk?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
      <?= $sl ?> <span class="<?= $status_filter===$sk?'bg-white/30':'bg-gray-100' ?> px-1.5 rounded-full text-xs"><?= $counts[$sk] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Filter bar -->
<form method="GET" action="admin.php" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex gap-3 items-center mb-5 flex-wrap">
  <input type="hidden" name="p" value="edit_requests">
  <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
  <select name="etype" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
    <option value="">كل الأنواع</option>
    <option value="personality" <?= $type_filter==='personality'?'selected':'' ?>>شخصيات فقط</option>
    <option value="institution" <?= $type_filter==='institution'?'selected':'' ?>>مؤسسات فقط</option>
  </select>
  <button type="submit" class="px-5 py-2 pi-primary-bg text-white font-bold rounded-xl text-sm hover:opacity-90 transition">فلتر</button>
</form>

<?php if (empty($requests)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-16 text-gray-300">
  <i class="fa-solid fa-inbox text-5xl mb-4 block"></i>
  <p class="font-semibold text-gray-400">لا توجد طلبات</p>
</div>
<?php else: ?>
<div class="space-y-4">
<?php foreach ($requests as $req):
  $edit_data = json_decode($req['er_edit_data'] ?? '{}', true) ?? [];
  $is_p = $req['er_entity_type'] === 'personality';
  // Load full current entity data for diff
  $current = [];
  if ($is_p) {
      $ecr = $mysqli->query("SELECT p_name_ar,p_name_en,p_title,p_nationality,p_residence,p_bio,p_photo FROM pi_personalities WHERE p_id={$req['er_entity_id']}");
      if ($ecr && $ecr->num_rows) {
          $row = $ecr->fetch_assoc();
          $current = ['name_ar'=>$row['p_name_ar'],'name_en'=>$row['p_name_en'],'title'=>$row['p_title'],'nationality'=>$row['p_nationality'],'residence'=>$row['p_residence'],'bio'=>$row['p_bio'],'photo'=>$row['p_photo']];
      }
  } else {
      $ecr = $mysqli->query("SELECT inst_name_ar,inst_name_en,inst_description,inst_logo FROM pi_institutions WHERE inst_id={$req['er_entity_id']}");
      if ($ecr && $ecr->num_rows) {
          $row = $ecr->fetch_assoc();
          $current = ['name_ar'=>$row['inst_name_ar'],'name_en'=>$row['inst_name_en'],'description'=>$row['inst_description'],'photo'=>$row['inst_logo']];
      }
  }
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
  <div class="flex items-start gap-4">

    <!-- Entity photo -->
    <div class="flex-shrink-0">
      <?php if ($req['entity_photo']): ?>
      <img src="<?= htmlspecialchars($req['entity_photo']) ?>" class="w-12 h-12 <?= $is_p?'rounded-full':'rounded-xl' ?> object-cover border border-gray-100">
      <?php else: ?>
      <div class="w-12 h-12 <?= $is_p?'rounded-full':'rounded-xl' ?> bg-gray-100 flex items-center justify-center text-gray-300 text-xl">
        <i class="fa-solid fa-<?= $is_p?'user':'building' ?>"></i>
      </div>
      <?php endif; ?>
    </div>

    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 flex-wrap mb-1">
        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $is_p?'bg-blue-100 text-blue-700':'bg-indigo-100 text-indigo-700' ?>">
          <?= $is_p?'شخصية':'مؤسسة' ?>
        </span>
        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $req['er_req_type']==='edit'?'bg-purple-100 text-purple-700':'bg-amber-100 text-amber-700' ?>">
          <?= $req['er_req_type']==='edit' ? 'تعديل' : 'ترقية' ?>
          <?php if ($req['er_req_type']==='upgrade' && $req['er_upgrade_to']): ?>
          — <?= $req['er_upgrade_to']==='executive'?'تنفيذي':'موثق' ?>
          <?php endif; ?>
        </span>
        <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($req['er_created'])) ?></span>
      </div>

      <h3 class="font-black text-gray-800 text-base mb-0.5"><?= htmlspecialchars($req['entity_name'] ?? 'محذوف') ?></h3>

      <?php if ($req['u_name']): ?>
      <a href="admin.php?p=users&view=<?= $req['uid'] ?>" class="text-xs text-purple-600 font-bold hover:underline flex items-center gap-1 w-fit">
        <i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($req['u_name']) ?> — <?= htmlspecialchars($req['u_email']) ?>
      </a>
      <?php endif; ?>

      <?php if ($req['er_req_type'] === 'edit'): ?>
<div class="mt-3 border border-gray-200 rounded-xl overflow-hidden">
  <div class="grid grid-cols-2 divide-x divide-x-reverse divide-gray-200 text-xs">
    <div class="px-3 py-2 bg-gray-100 font-black text-gray-500">البيانات الحالية</div>
    <div class="px-3 py-2 bg-purple-50 font-black text-purple-600">التعديلات المطلوبة</div>
  </div>
  <?php
  $all_fields = $is_p
    ? ['name_ar'=>'الاسم عربي','name_en'=>'الاسم إنجليزي','title'=>'المسمى','nationality'=>'الجنسية','residence'=>'الإقامة','bio'=>'السيرة','photo'=>'الصورة']
    : ['name_ar'=>'الاسم عربي','name_en'=>'الاسم إنجليزي','description'=>'الوصف','photo'=>'الصورة'];
  foreach ($all_fields as $fk => $fl):
    $cur_val = $current[$fk] ?? '';
    $new_val = array_key_exists($fk, $edit_data) ? ($edit_data[$fk] ?? '') : null;
    $submitted = ($new_val !== null);
    $changed = $submitted && ($new_val !== $cur_val);
    $added   = $submitted && ($new_val !== '' && $cur_val === '');
    if (!$cur_val && !$submitted) continue;
    $cur_txt = $fk === 'bio' || $fk === 'description' ? mb_substr(strip_tags($cur_val), 0, 120) : mb_substr($cur_val, 0, 80);
    $nv_str  = $new_val ?? '';
    $new_txt = $fk === 'bio' || $fk === 'description' ? mb_substr(strip_tags($nv_str), 0, 120) : mb_substr($nv_str, 0, 80);
  ?>
  <div class="grid grid-cols-2 divide-x divide-x-reverse divide-gray-100 border-t border-gray-100">
    <div class="px-3 py-2 <?= $changed ? 'bg-red-50' : 'bg-white' ?>">
      <span class="text-xs font-bold text-gray-400 block mb-0.5"><?= $fl ?></span>
      <?php if ($fk === 'photo' && $cur_val): ?>
        <img src="<?= htmlspecialchars($cur_val) ?>" class="w-10 h-10 rounded-lg object-cover <?= $changed ? 'ring-2 ring-red-300' : '' ?>">
      <?php else: ?>
        <span class="text-xs <?= $changed ? 'text-red-700 line-through' : 'text-gray-700' ?>"><?= htmlspecialchars($cur_txt ?: '—') ?></span>
      <?php endif; ?>
    </div>
    <div class="px-3 py-2 <?= $added ? 'bg-green-50' : ($changed ? 'bg-green-50' : 'bg-white') ?>">
      <span class="text-xs font-bold text-gray-400 block mb-0.5"><?= $fl ?></span>
      <?php if ($fk === 'photo' && $new_val): ?>
        <img src="<?= htmlspecialchars($new_val) ?>" class="w-10 h-10 rounded-lg object-cover ring-2 ring-green-400">
      <?php elseif ($submitted && $new_val !== ''): ?>
        <span class="text-xs <?= $changed ? 'font-bold text-green-700' : 'text-gray-600' ?>"><?= htmlspecialchars($new_txt) ?></span>
      <?php elseif ($submitted && $new_val === ''): ?>
        <span class="text-xs text-orange-500 italic">— (مسح)</span>
      <?php else: ?>
        <span class="text-xs text-gray-300">—</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

      <?php if ($req['er_notes']): ?>
      <p class="text-xs text-gray-500 mt-2 bg-yellow-50 rounded-lg px-3 py-1.5">
        <i class="fa-solid fa-note-sticky text-yellow-500 ml-1"></i><?= htmlspecialchars($req['er_notes']) ?>
      </p>
      <?php endif; ?>

      <?php if ($req['er_admin_note']): ?>
      <p class="text-xs text-gray-500 mt-1 bg-blue-50 rounded-lg px-3 py-1.5">
        <i class="fa-solid fa-comment text-blue-400 ml-1"></i><?= htmlspecialchars($req['er_admin_note']) ?>
      </p>
      <?php endif; ?>
    </div>

    <div class="flex-shrink-0 flex flex-col items-end gap-2">
      <span class="text-xs px-2.5 py-1 rounded-full font-bold <?= $status_map[$req['er_status']]['class'] ?>">
        <?= $status_map[$req['er_status']]['text'] ?>
      </span>
      <?php if ($status_filter === 'pending'): ?>
      <div class="flex flex-col gap-2">
        <?php if ($req['er_req_type'] === 'edit'): ?>
        <button onclick="openApplyModal(<?= htmlspecialchars(json_encode([
          'er_id'       => $req['er_id'],
          'entity_name' => $req['entity_name'] ?? '',
          'entity_type' => $req['er_entity_type'],
          'is_p'        => ($req['er_entity_type']==='personality'),
          'edit_data'   => $edit_data,
          'current'     => $current,
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
          class="px-3 py-1.5 text-xs font-black bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition flex items-center gap-1">
          <i class="fa-solid fa-pen-to-square"></i> تعديل وتطبيق
        </button>
        <?php endif; ?>
        <button onclick="openAction(<?= $req['er_id'] ?>,'approve')"
          class="px-3 py-1.5 text-xs font-black bg-green-500 text-white rounded-xl hover:bg-green-600 transition flex items-center gap-1">
          <i class="fa-solid fa-check"></i> قبول مباشر
        </button>
        <button onclick="openAction(<?= $req['er_id'] ?>,'reject')"
          class="px-3 py-1.5 text-xs font-black bg-red-100 text-red-600 rounded-xl hover:bg-red-200 transition flex items-center gap-1">
          <i class="fa-solid fa-xmark"></i> رفض
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Action modal -->
<div id="action-modal" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,.5)">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" dir="rtl">
      <div id="action-modal-header" class="px-6 py-4 rounded-t-2xl">
        <h3 class="font-black text-white text-base" id="action-modal-title">تأكيد</h3>
      </div>
      <form method="POST" class="p-6 space-y-4">
        <input type="hidden" name="er_id" id="action-er-id">
        <input type="hidden" name="action" id="action-type">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">ملاحظة للمستخدم <span class="text-gray-400 font-normal">(اختياري)</span></label>
          <textarea name="admin_note" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 resize-none" placeholder="أكتب ملاحظة..."></textarea>
        </div>
        <div class="flex gap-3">
          <button type="submit" id="action-submit-btn" class="flex-1 py-3 text-white font-black rounded-xl hover:opacity-90 transition">تأكيد</button>
          <button type="button" onclick="closeAction()" class="flex-1 py-3 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Apply Edit Modal -->
<div id="apply-modal" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,.5)">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" dir="rtl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100" style="background:linear-gradient(135deg,#7c3aed,#a855f7);border-radius:1rem 1rem 0 0">
        <h3 class="font-black text-white text-base">تعديل وتطبيق التغييرات</h3>
        <button type="button" onclick="closeApplyModal()" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
      </div>
      <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
        <input type="hidden" name="action" value="apply_edit">
        <input type="hidden" name="er_id" id="apply-er-id">
        <p class="text-xs text-gray-400">عدّل القيم حسب الحاجة ثم اضغط «تطبيق» — ستُحفظ مباشرة في الملف وسيُقبل الطلب.</p>
        <div id="apply-fields" class="space-y-3"></div>
        <div>
          <label class="block text-xs font-bold text-gray-600 mb-1">ملاحظة للمستخدم <span class="text-gray-400 font-normal">(اختياري)</span></label>
          <textarea name="admin_note" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 resize-none" placeholder="اكتب ملاحظة..."></textarea>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="submit" class="flex-1 py-3 text-white font-black rounded-xl hover:opacity-90 transition" style="background:linear-gradient(135deg,#7c3aed,#a855f7)">
            <i class="fa-solid fa-check ml-1"></i> تطبيق
          </button>
          <button type="button" onclick="closeApplyModal()" class="flex-1 py-3 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAction(id, type) {
  document.getElementById('action-er-id').value = id;
  document.getElementById('action-type').value  = type;
  var isApprove = type === 'approve';
  document.getElementById('action-modal-header').style.background = isApprove ? 'linear-gradient(135deg,#22c55e,#16a34a)' : 'linear-gradient(135deg,#ef4444,#dc2626)';
  document.getElementById('action-modal-title').textContent = isApprove ? 'تأكيد القبول وتطبيق التعديلات' : 'تأكيد الرفض';
  document.getElementById('action-submit-btn').style.background = isApprove ? 'linear-gradient(135deg,#22c55e,#16a34a)' : 'linear-gradient(135deg,#ef4444,#dc2626)';
  document.getElementById('action-modal').classList.remove('hidden');
}
function closeAction() {
  document.getElementById('action-modal').classList.add('hidden');
}
document.getElementById('action-modal').addEventListener('click', function(e){ if(e.target===this) closeAction(); });

function openApplyModal(dataStr) {
  var d = typeof dataStr === 'string' ? JSON.parse(dataStr) : dataStr;
  document.getElementById('apply-er-id').value = d.er_id;

  var isP = d.is_p;
  var fieldDefs = isP
    ? [{k:'name_ar',l:'الاسم بالعربي',type:'text'},{k:'name_en',l:'الاسم بالإنجليزي',type:'text',dir:'ltr'},{k:'title',l:'المسمى الوظيفي',type:'text'},{k:'nationality',l:'الجنسية',type:'text'},{k:'residence',l:'الإقامة',type:'text'},{k:'bio',l:'السيرة الذاتية',type:'textarea'}]
    : [{k:'name_ar',l:'الاسم بالعربي',type:'text'},{k:'name_en',l:'الاسم بالإنجليزي',type:'text',dir:'ltr'},{k:'description',l:'الوصف',type:'textarea'}];

  var html = '';
  fieldDefs.forEach(function(f) {
    var reqVal = (d.edit_data && d.edit_data[f.k] !== undefined) ? d.edit_data[f.k] : '';
    var curVal = (d.current && d.current[f.k]) ? d.current[f.k] : '';
    var changed = reqVal !== '' && reqVal !== curVal;
    var dir = f.dir ? ' dir="ltr"' : '';
    var highlight = changed ? ' border-purple-400 bg-purple-50' : ' border-gray-200';
    if (f.type === 'textarea') {
      html += '<div><label class="block text-xs font-bold text-gray-600 mb-1">' + f.l + (changed ? ' <span class="text-purple-500 text-xs">(مُعدَّل)</span>' : '') + '</label>' +
        '<textarea name="'+f.k+'" rows="3" class="w-full border'+highlight+' rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 resize-none"'+dir+'>'+esc(reqVal || curVal)+'</textarea></div>';
    } else {
      html += '<div><label class="block text-xs font-bold text-gray-600 mb-1">' + f.l + (changed ? ' <span class="text-purple-500 text-xs">(مُعدَّل)</span>' : '') + '</label>' +
        '<input type="text" name="'+f.k+'" class="w-full border'+highlight+' rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400"'+dir+' value="'+esc(reqVal || curVal)+'">' +
        (changed && curVal ? '<p class="text-xs text-gray-400 mt-0.5">الحالي: <span class="line-through">' + esc(curVal) + '</span></p>' : '') +
        '</div>';
    }
  });

  // Photo upload
  var photoLabel = isP ? 'الصورة الشخصية' : 'الشعار';
  var curPhoto = (d.current && d.current.photo) ? d.current.photo : '';
  html += '<div><label class="block text-xs font-bold text-gray-600 mb-1">'+photoLabel+' <span class="font-normal text-gray-400">(اختياري - اترك فارغاً للإبقاء على الحالية)</span></label>' +
    (curPhoto ? '<img src="'+curPhoto+'" class="w-12 h-12 rounded-xl object-cover border border-gray-200 mb-2">' : '') +
    '<input type="file" name="photo_file" accept="image/*" class="w-full text-sm text-gray-600"></div>';

  document.getElementById('apply-fields').innerHTML = html;
  document.getElementById('apply-modal').classList.remove('hidden');
}
function closeApplyModal() {
  document.getElementById('apply-modal').classList.add('hidden');
}
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
document.getElementById('apply-modal').addEventListener('click', function(e){ if(e.target===this) closeApplyModal(); });
</script>
