<?php
require_once 'includes/config.php';
pi_require_user();
$pageTitle = 'حسابي - PioneerIcons';
$user = pi_current_user();

$tab = $_GET['tab'] ?? 'profile';
$msg = '';
$msg_type = 'success';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'update_profile') {
        $name  = pi_escape(trim($_POST['u_name'] ?? ''));
        $phone = pi_escape(trim($_POST['u_phone'] ?? ''));
        $nat   = pi_escape(trim($_POST['u_nationality'] ?? ''));
        $comp  = pi_escape(trim($_POST['u_company'] ?? ''));
        $bd    = pi_escape(trim($_POST['u_birthdate'] ?? ''));
        $job   = pi_escape(trim($_POST['u_job'] ?? ''));
        $gen   = in_array($_POST['u_gender'] ?? '', ['male','female']) ? pi_escape($_POST['u_gender']) : '';
        $uid   = (int)$user['u_id'];
        if ($name) {
            $mysqli->query("UPDATE pi_users SET u_name='$name',u_phone='$phone',u_nationality='$nat',u_company='$comp',u_birthdate=" . ($bd ? "'$bd'" : 'NULL') . ",u_job='$job',u_gender='$gen' WHERE u_id=$uid");
            $msg = 'تم تحديث البيانات بنجاح';
            $user = pi_current_user();
            // Force reload
            header("Location: account.php?tab=profile&saved=1"); exit;
        }
    }

    if ($act === 'change_password') {
        $old  = $_POST['old_pass']  ?? '';
        $new  = $_POST['new_pass']  ?? '';
        $new2 = $_POST['new_pass2'] ?? '';
        $uid  = (int)$user['u_id'];
        if (!password_verify($old, $user['u_password'])) {
            $msg = 'كلمة المرور الحالية غير صحيحة'; $msg_type = 'error';
        } elseif (strlen($new) < 6) {
            $msg = 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل'; $msg_type = 'error';
        } elseif ($new !== $new2) {
            $msg = 'كلمتا المرور غير متطابقتين'; $msg_type = 'error';
        } else {
            $hash = pi_escape(password_hash($new, PASSWORD_DEFAULT));
            $mysqli->query("UPDATE pi_users SET u_password='$hash' WHERE u_id=$uid");
            $msg = 'تم تغيير كلمة المرور بنجاح';
        }
    }

    if ($act === 'send_edit_request') {
        $entity_type = in_array($_POST['entity_type'] ?? '', ['personality','institution']) ? pi_escape($_POST['entity_type']) : '';
        $entity_id   = (int)($_POST['entity_id'] ?? 0);
        $req_type    = in_array($_POST['req_type'] ?? '', ['edit','upgrade']) ? pi_escape($_POST['req_type']) : '';
        $upgrade_to  = in_array($_POST['upgrade_to'] ?? '', ['verified','executive','']) ? pi_escape($_POST['upgrade_to']) : '';
        $notes       = pi_escape(trim($_POST['req_notes'] ?? ''));
        $uid         = (int)$user['u_id'];

        // Verify entity belongs to this user
        $owner = false;
        if ($entity_type === 'personality') {
            $chk = $mysqli->query("SELECT p_id FROM pi_personalities WHERE p_id=$entity_id AND p_added_by_user=$uid");
            $owner = $chk && $chk->num_rows;
        } elseif ($entity_type === 'institution') {
            $chk = $mysqli->query("SELECT inst_id FROM pi_institutions WHERE inst_id=$entity_id AND inst_added_by_user=$uid");
            $owner = $chk && $chk->num_rows;
        }

        if ($owner && $entity_type && $req_type) {
            // Collect edit fields into JSON
            $edit_data = [];
            if ($req_type === 'edit') {
                foreach (['name_ar','name_en','title','bio','nationality','residence','website','description','country'] as $f) {
                    if (isset($_POST[$f])) $edit_data[$f] = trim($_POST[$f]);
                }
                // Handle photo upload
                if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = __DIR__ . '/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'er_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $udir . $fname)) {
                            $edit_data['photo'] = 'uploads/' . $fname;
                        }
                    }
                }
            }
            $edit_json = pi_escape(json_encode($edit_data, JSON_UNESCAPED_UNICODE));
            if ($req_type === 'upgrade') {
                // Upgrade requests go only to pi_memberships — not to edit_requests
                $mem_plan  = in_array($_POST['mem_plan'] ?? '', ['monthly','lifetime']) ? pi_escape($_POST['mem_plan']) : 'monthly';
                $mem_name  = pi_escape(trim($_POST['mem_name']  ?? $user['u_name']));
                $mem_phone = pi_escape(trim($_POST['mem_phone'] ?? $user['u_phone'] ?? ''));
                $mem_email = pi_escape($user['u_email']);
                $profile_url = pi_escape($entity_type === 'personality' ? 'profile.php?id='.$entity_id : 'institution.php?id='.$entity_id);
                $mem_utype = $upgrade_to ?: 'verified';
                $mysqli->query("INSERT INTO pi_memberships(mem_type,mem_plan,mem_name,mem_phone,mem_email,mem_profile_url) VALUES('$mem_utype','$mem_plan','$mem_name','$mem_phone','$mem_email','$profile_url')");
            } else {
                $mysqli->query("INSERT INTO pi_edit_requests (er_user_id,er_entity_type,er_entity_id,er_req_type,er_upgrade_to,er_edit_data,er_notes)
                    VALUES($uid,'$entity_type',$entity_id,'$req_type','$upgrade_to','$edit_json','$notes')");
            }
            header("Location: account.php?tab=accounts&req_sent=1"); exit;
        } else {
            $msg = 'حدث خطأ، يرجى المحاولة مجدداً'; $msg_type = 'error';
        }
    }

    if ($act === 'send_complaint') {
        $type    = in_array($_POST['cmp_type'] ?? '', ['complaint','suggestion','feedback','request']) ? pi_escape($_POST['cmp_type']) : 'complaint';
        $subject = pi_escape(trim($_POST['cmp_subject'] ?? ''));
        $message = pi_escape(trim($_POST['cmp_message'] ?? ''));
        $uid     = (int)$user['u_id'];
        $uname   = pi_escape($user['u_name']);
        $uemail  = pi_escape($user['u_email']);
        if ($subject && $message) {
            $mysqli->query("INSERT INTO pi_complaints(cmp_user_id,cmp_type,cmp_subject,cmp_message,cmp_name,cmp_email) VALUES($uid,'$type','$subject','$message','$uname','$uemail')");
            header("Location: account.php?tab=complaints&sent=1"); exit;
        } else {
            $msg = 'يرجى ملء جميع الحقول المطلوبة'; $msg_type = 'error';
        }
    }
}

if (isset($_GET['saved'])) $msg = 'تم تحديث البيانات بنجاح';

// Load approved personalities/institutions added by user
$my_personalities = [];
$my_institutions  = [];
$r = $mysqli->query("SELECT p_id,p_name_ar,p_name_en,p_title,p_nationality,p_residence,p_bio,p_photo,p_active,p_verified,p_views,p_membership_type FROM pi_personalities WHERE p_added_by_user=" . (int)$user['u_id'] . " ORDER BY p_id DESC");
if ($r) while ($row=$r->fetch_assoc()) $my_personalities[] = $row;
$r = $mysqli->query("SELECT inst_id,inst_name_ar,inst_name_en,inst_description,inst_logo,inst_active,inst_verified,inst_views,inst_membership_type FROM pi_institutions WHERE inst_added_by_user=" . (int)$user['u_id'] . " ORDER BY inst_id DESC");
if ($r) while ($row=$r->fetch_assoc()) $my_institutions[] = $row;
// Load all submissions by user (all statuses)
$my_all_subs = [];
$r = $mysqli->query("SELECT sub_id,sub_type,sub_data,sub_status,sub_created FROM pi_submissions WHERE sub_user_id=" . (int)$user['u_id'] . " ORDER BY sub_id DESC");
if ($r) while ($row=$r->fetch_assoc()) {
    $d = json_decode($row['sub_data'] ?? '{}', true);
    $row['_name']  = $row['sub_type'] === 'personality' ? ($d['p_name_ar'] ?? '') : ($d['inst_name_ar'] ?? '');
    $row['_photo'] = $row['sub_type'] === 'personality' ? ($d['p_photo']  ?? '') : ($d['inst_logo']   ?? '');
    // For approved ones, try to find the actual entity's verification status
    $row['_verified']  = false;
    $row['_mem_type']  = 'standard';
    if ($row['sub_status'] === 'approved' && $row['_name']) {
        $ename = pi_escape($row['_name']);
        if ($row['sub_type'] === 'personality') {
            $ev = $mysqli->query("SELECT p_verified,p_membership_type FROM pi_personalities WHERE p_name_ar='$ename' LIMIT 1");
        } else {
            $ev = $mysqli->query("SELECT inst_verified AS p_verified,inst_membership_type AS p_membership_type FROM pi_institutions WHERE inst_name_ar='$ename' LIMIT 1");
        }
        if ($ev && $ev->num_rows) {
            $evr = $ev->fetch_assoc();
            $row['_verified'] = (bool)$evr['p_verified'];
            $row['_mem_type'] = $evr['p_membership_type'] ?? 'standard';
        }
    }
    $my_all_subs[] = $row;
}

// Load sponsors linked to this user + their lists with views
$my_sponsors = [];
$sr = $mysqli->query("SELECT s.sp_id, s.sp_name, s.sp_logo, s.sp_url,
    (SELECT COUNT(*) FROM pi_lists WHERE list_sponsor_id=s.sp_id AND list_active=1) AS lists_count,
    COALESCE(s.sp_views,0) AS total_views
    FROM pi_sponsors s WHERE s.sp_user_id=" . (int)$user['u_id']);
if ($sr) while ($row=$sr->fetch_assoc()) {
    // Load linked lists for this sponsor
    $row['_lists'] = [];
    $lr = $mysqli->query("SELECT l.list_id,l.list_title,l.list_views
        FROM pi_lists l WHERE l.list_sponsor_id=".(int)$row['sp_id']." AND l.list_active=1 ORDER BY l.list_views DESC");
    if ($lr) while ($lr_row=$lr->fetch_assoc()) $row['_lists'][] = $lr_row;
    $my_sponsors[] = $row;
}

// Per-entity 30/7 day views from pi_visit_daily
$p_views_30d = []; $p_views_7d = [];
$i_views_30d = []; $i_views_7d = [];
if (!empty($my_personalities)) {
    $pids = implode(',', array_column($my_personalities,'p_id'));
    $rvp = $mysqli->query("SELECT vd_page, SUM(CASE WHEN vd_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) THEN vd_count ELSE 0 END) v30,
        SUM(CASE WHEN vd_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN vd_count ELSE 0 END) v7
        FROM pi_visit_daily WHERE vd_page IN (".implode(',',array_map(function($id){ return "'profile/$id'"; },array_column($my_personalities,'p_id'))).") GROUP BY vd_page");
    if ($rvp) while ($row=$rvp->fetch_assoc()) {
        $id = (int)str_replace('profile/','',$row['vd_page']);
        $p_views_30d[$id] = (int)$row['v30'];
        $p_views_7d[$id]  = (int)$row['v7'];
    }
}
if (!empty($my_institutions)) {
    $rvp = $mysqli->query("SELECT vd_page, SUM(CASE WHEN vd_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) THEN vd_count ELSE 0 END) v30,
        SUM(CASE WHEN vd_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN vd_count ELSE 0 END) v7
        FROM pi_visit_daily WHERE vd_page IN (".implode(',',array_map(function($id){ return "'institution/$id'"; },array_column($my_institutions,'inst_id'))).") GROUP BY vd_page");
    if ($rvp) while ($row=$rvp->fetch_assoc()) {
        $id = (int)str_replace('institution/','',$row['vd_page']);
        $i_views_30d[$id] = (int)$row['v30'];
        $i_views_7d[$id]  = (int)$row['v7'];
    }
}

// Summary totals for stats header
$total_p_views    = array_sum(array_column($my_personalities,'p_views'));
$total_i_views    = array_sum(array_column($my_institutions,'inst_views'));
$total_sp_views   = array_sum(array_column($my_sponsors,'total_views'));
$total_views_all  = $total_p_views + $total_i_views + $total_sp_views;
$total_pages      = count($my_personalities) + count($my_institutions);
$total_30d        = array_sum($p_views_30d) + array_sum($i_views_30d) + array_sum(array_column($my_sponsors,'views_30d'));

$all_countries = pi_get_countries();

// Load my edit requests (type='edit' only — upgrade requests are in memberships tab)
$my_requests = [];
$r = $mysqli->query("SELECT * FROM pi_edit_requests WHERE er_user_id=" . (int)$user['u_id'] . " AND er_req_type='edit' ORDER BY er_created DESC LIMIT 20");
if ($r) while ($row=$r->fetch_assoc()) $my_requests[] = $row;

// Load complaints
$my_complaints = [];
$cmp_filter = $_GET['ctype'] ?? '';
$cmp_where  = "WHERE cmp_user_id=" . (int)$user['u_id'];
if ($cmp_filter && in_array($cmp_filter, ['complaint','suggestion','feedback','request'])) {
    $cmp_where .= " AND cmp_type='" . pi_escape($cmp_filter) . "'";
}
$r = $mysqli->query("SELECT * FROM pi_complaints $cmp_where ORDER BY cmp_id DESC");
if ($r) while ($row=$r->fetch_assoc()) $my_complaints[] = $row;

// Profile completeness
$profile_fields = ['u_phone','u_nationality','u_company','u_job','u_gender'];
$filled = 0;
foreach ($profile_fields as $f) if (!empty($user[$f])) $filled++;
$completeness = (int)(($filled / count($profile_fields)) * 100);

$_menu = [
    'profile'     => ['fa-user',       'الملف الشخصي'],
    'stats'       => ['fa-chart-bar',  'الإحصائيات'],
    'submissions' => ['fa-plus-circle','إضافاتي'],
    'accounts'    => ['fa-gear',       'إدارة الحسابات'],
    'membership'  => ['fa-gem',        'اشتراكاتي'],
    'complaints'  => ['fa-pen-to-square','الشكاوي والملاحظات'],
];

include 'includes/header.php';
?>

<!-- Profile incomplete banner -->
<?php if ($completeness < 100): ?>
<div class="bg-red-50 border-b border-red-100">
  <div class="max-w-5xl mx-auto px-4 py-2 flex items-center gap-2 text-sm">
    <i class="fa-solid fa-circle-exclamation text-red-400"></i>
    <span class="text-red-600 font-semibold">معلومات الحساب غير مكتملة</span>
    <a href="account.php?tab=profile" class="text-red-700 font-bold hover:underline mr-2">اكمل ملفك ←</a>
  </div>
</div>
<?php endif; ?>

<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex flex-col md:flex-row gap-6">

    <!-- SIDEBAR -->
    <aside class="md:w-64 flex-shrink-0">
      <!-- User card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4 text-center">
        <?php if (!empty($user['u_photo'])): ?>
          <img src="<?= htmlspecialchars($user['u_photo']) ?>" class="w-16 h-16 rounded-full mx-auto mb-3 object-cover border-2 border-purple-100">
        <?php else: ?>
          <div class="w-16 h-16 rounded-full pi-gradient flex items-center justify-center mx-auto mb-3">
            <span class="text-white font-black text-2xl"><?= mb_substr($user['u_name'],0,1) ?></span>
          </div>
        <?php endif; ?>
        <p class="text-xs text-gray-400 font-semibold mb-0.5">مرحباً بك</p>
        <p class="font-black text-gray-800 text-sm leading-tight"><?= htmlspecialchars($user['u_name']) ?></p>
        <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-700">
          <i class="fa-solid fa-circle-check text-xs"></i> مستخدم نشط
        </span>
      </div>

      <!-- Nav menu -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <?php foreach ($_menu as $key => [$icon, $label]): ?>
        <a href="account.php?tab=<?= $key ?>"
          class="flex items-center gap-3 px-4 py-3 text-sm font-semibold transition border-b border-gray-50 last:border-0
            <?= $tab === $key ? 'bg-purple-50 text-purple-700 font-black' : 'text-gray-600 hover:bg-gray-50 hover:text-purple-600' ?>">
          <i class="fa-solid <?= $icon ?> w-4 text-center <?= $tab === $key ? 'text-purple-500' : 'text-gray-400' ?>"></i>
          <?= $label ?>
        </a>
        <?php endforeach; ?>
        <a href="user_logout.php"
          class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-red-500 hover:bg-red-50 transition">
          <i class="fa-solid fa-right-from-bracket w-4 text-center text-red-400"></i> تسجيل الخروج
        </a>
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 min-w-0">

      <?php if ($msg): ?>
      <div class="mb-4 rounded-xl px-4 py-3 text-sm font-semibold <?= $msg_type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
        <i class="fa-solid <?= $msg_type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?> ml-2"></i><?= htmlspecialchars($msg) ?>
      </div>
      <?php endif; ?>

      <!-- ──────────── PROFILE TAB ──────────── -->
      <?php if ($tab === 'profile'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-black text-gray-800 text-lg mb-1">بيانات الحساب</h2>
        <p class="text-gray-400 text-sm mb-6">معلومات الحساب الخاص بك يمكنك تحديثها بناء على رغبتك</p>

        <!-- Progress -->
        <?php if ($completeness < 100): ?>
        <div class="mb-6 bg-orange-50 border border-orange-200 rounded-xl p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-bold text-orange-700">اكتمال الملف</span>
            <span class="text-sm font-black text-orange-700"><?= $completeness ?>%</span>
          </div>
          <div class="w-full bg-orange-100 rounded-full h-2">
            <div class="bg-orange-500 h-2 rounded-full transition-all" style="width:<?= $completeness ?>%"></div>
          </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-0">
          <input type="hidden" name="_action" value="update_profile">
          <?php
          $fields = [
            ['u_name',        'الاسم',         'text',   $user['u_name']         ?? ''],
            ['u_email',       'البريد الإلكتروني', 'email', $user['u_email']     ?? ''],
            ['u_phone',       'رقم الهاتف',    'tel',    $user['u_phone']        ?? ''],
            ['u_nationality', 'الجنسية',        'text',   $user['u_nationality']  ?? ''],
            ['u_company',     'المؤسسة',        'text',   $user['u_company']      ?? ''],
            ['u_birthdate',   'تاريخ الميلاد', 'date',   $user['u_birthdate']    ?? ''],
            ['u_job',         'المهنة',         'text',   $user['u_job']          ?? ''],
          ];
          foreach ($fields as [$fname, $flabel, $ftype, $fval]):
            $isEmail = $fname === 'u_email';
          ?>
          <div class="border-b border-gray-50">
            <div class="flex items-center justify-between py-4 gap-4">
              <span class="text-sm font-bold text-gray-500 w-28 flex-shrink-0"><?= $flabel ?></span>
              <span class="flex-1 text-sm text-gray-800 font-semibold field-val-<?= $fname ?>">
                <?= $fval ? htmlspecialchars($fval) : '<span class="text-gray-300 font-normal">لم يتم التحديد</span>' ?>
              </span>
              <?php if (!$isEmail): ?>
              <button type="button" id="btn_<?= $fname ?>" onclick="toggleEdit('<?= $fname ?>')"
                class="text-xs font-bold text-purple-600 hover:text-purple-800 flex items-center gap-1 whitespace-nowrap px-2.5 py-1 rounded-lg hover:bg-purple-50 transition">
                <i class="fa-solid fa-pen text-xs"></i> <span>تعديل</span>
              </button>
              <?php endif; ?>
            </div>
            <div id="edit_<?= $fname ?>" class="hidden pb-4 edit-input-row">
              <input type="<?= $ftype ?>" name="<?= $fname ?>"
                value="<?= htmlspecialchars($fval) ?>"
                class="w-full border-2 border-purple-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-500 bg-purple-50 transition"
                <?= $ftype === 'tel' || $ftype === 'date' ? 'dir="ltr"' : '' ?>>
            </div>
          </div>
          <?php endforeach; ?>

          <!-- Gender -->
          <div class="border-b border-gray-50">
            <div class="flex items-center justify-between py-4 gap-4">
              <span class="text-sm font-bold text-gray-500 w-28 flex-shrink-0">الجنس</span>
              <span class="flex-1 text-sm text-gray-800 font-semibold">
                <?= $user['u_gender'] === 'male' ? 'ذكر' : ($user['u_gender'] === 'female' ? 'أنثى' : '<span class="text-gray-300 font-normal">لم يتم التحديد</span>') ?>
              </span>
              <button type="button" id="btn_u_gender" onclick="toggleEdit('u_gender')"
                class="text-xs font-bold text-purple-600 hover:text-purple-800 flex items-center gap-1 whitespace-nowrap px-2.5 py-1 rounded-lg hover:bg-purple-50 transition">
                <i class="fa-solid fa-pen text-xs"></i> <span>تعديل</span>
              </button>
            </div>
            <div id="edit_u_gender" class="hidden pb-4 edit-input-row">
              <select name="u_gender" class="w-full border-2 border-purple-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-500 bg-purple-50">
                <option value="">اختر</option>
                <option value="male" <?= $user['u_gender']==='male'?'selected':'' ?>>ذكر</option>
                <option value="female" <?= $user['u_gender']==='female'?'selected':'' ?>>أنثى</option>
              </select>
            </div>
          </div>

          <div class="pt-5">
            <button type="submit"
              class="px-8 py-3 pi-primary-bg text-white font-black rounded-xl hover:opacity-90 transition text-sm">
              حفظ التغييرات
            </button>
          </div>
        </form>

        <!-- Change password -->
        <div class="mt-8 pt-6 border-t border-gray-100">
          <h3 class="font-black text-gray-800 text-base mb-4">تغيير كلمة المرور</h3>
          <form method="POST" class="space-y-3 max-w-sm">
            <input type="hidden" name="_action" value="change_password">
            <input type="password" name="old_pass" dir="ltr" required placeholder="كلمة المرور الحالية"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 transition">
            <input type="password" name="new_pass" dir="ltr" required placeholder="كلمة المرور الجديدة"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 transition">
            <input type="password" name="new_pass2" dir="ltr" required placeholder="تأكيد كلمة المرور الجديدة"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 transition">
            <button type="submit" class="px-6 py-2.5 border-2 border-purple-500 text-purple-600 font-black rounded-xl hover:bg-purple-50 transition text-sm">
              تغيير كلمة المرور
            </button>
          </form>
        </div>
      </div>

      <!-- ──────────── STATS TAB ──────────── -->
      <?php elseif ($tab === 'stats'): ?>
      <div class="space-y-5">

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
          <?php
          $sum_cards = [
            ['icon'=>'fa-eye','label'=>'إجمالي المشاهدات','val'=>number_format($total_views_all),'color'=>'#7c3aed','bg'=>'#f5f3ff'],
            ['icon'=>'fa-id-badge','label'=>'الصفحات المُدارة','val'=>$total_pages,'color'=>'#059669','bg'=>'#f0fdf4'],
          ];
          foreach ($sum_cards as $sc):
          ?>
          <div style="background:#fff;border-radius:18px;padding:18px 20px;border:1px solid #f0f0f0;box-shadow:0 2px 8px rgba(0,0,0,.04);">
            <div style="width:40px;height:40px;border-radius:12px;background:<?= $sc['bg'] ?>;display:flex;align-items:center;justify-content:center;margin-bottom:12px;">
              <i class="fa-solid <?= $sc['icon'] ?>" style="color:<?= $sc['color'] ?>;font-size:16px;"></i>
            </div>
            <p style="font-size:22px;font-weight:900;color:#111827;line-height:1;margin-bottom:4px;"><?= $sc['val'] ?></p>
            <p style="font-size:11px;color:#6b7280;font-weight:600;"><?= $sc['label'] ?></p>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Tabs -->
        <div style="background:#fff;border-radius:18px;border:1px solid #f0f0f0;box-shadow:0 2px 8px rgba(0,0,0,.04);overflow:hidden;">
          <div style="display:flex;border-bottom:1px solid #f0f0f0;padding:0 20px;gap:4px;">
            <button onclick="showStatsTab('personalities')" id="st-p"
              style="padding:16px 18px;font-size:13px;font-weight:800;border:none;background:none;cursor:pointer;border-bottom:3px solid #7c3aed;color:#7c3aed;margin-bottom:-1px;font-family:inherit;">
              <i class="fa-solid fa-user ml-1"></i>الشخصيات
              <?php if (!empty($my_personalities)): ?><span style="background:#f3e8ff;color:#7c3aed;font-size:10px;font-weight:900;padding:2px 7px;border-radius:999px;margin-right:4px;"><?= count($my_personalities) ?></span><?php endif; ?>
            </button>
            <button onclick="showStatsTab('companies')" id="st-c"
              style="padding:16px 18px;font-size:13px;font-weight:800;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#9ca3af;margin-bottom:-1px;font-family:inherit;">
              <i class="fa-solid fa-building ml-1"></i>الشركات
              <?php if (!empty($my_institutions)): ?><span style="background:#f3f4f6;color:#6b7280;font-size:10px;font-weight:900;padding:2px 7px;border-radius:999px;margin-right:4px;"><?= count($my_institutions) ?></span><?php endif; ?>
            </button>
            <button onclick="showStatsTab('sponsors')" id="st-s"
              style="padding:16px 18px;font-size:13px;font-weight:800;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#9ca3af;margin-bottom:-1px;font-family:inherit;">
              <i class="fa-solid fa-handshake ml-1"></i>الرعايات
              <?php if (!empty($my_sponsors)): ?><span style="background:#f3f4f6;color:#6b7280;font-size:10px;font-weight:900;padding:2px 7px;border-radius:999px;margin-right:4px;"><?= count($my_sponsors) ?></span><?php endif; ?>
            </button>
          </div>

          <!-- ── Personalities ── -->
          <div id="stats-personalities" style="padding:20px;">
            <?php if (empty($my_personalities)): ?>
            <div style="text-align:center;padding:48px;color:#9ca3af;">
              <i class="fa-solid fa-user-slash" style="font-size:40px;opacity:.2;display:block;margin-bottom:12px;"></i>
              <p style="font-weight:700;">لا توجد شخصيات مرتبطة بحسابك</p>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:2px;">
              <?php foreach ($my_personalities as $p):
                $v30 = $p_views_30d[$p['p_id']] ?? 0;
                $v7  = $p_views_7d[$p['p_id']]  ?? 0;
              ?>
              <div style="display:flex;align-items:center;gap:14px;padding:14px 12px;border-radius:14px;transition:background .15s;"
                onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background=''">
                <!-- Avatar -->
                <a href="profile.php?id=<?= $p['p_id'] ?>" style="flex-shrink:0;">
                  <?php if (!empty($p['p_photo'])): ?>
                  <img src="<?= htmlspecialchars($p['p_photo']) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #ede9fe;">
                  <?php else: ?>
                  <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#4c1d95);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:18px;">
                    <?= mb_substr($p['p_name_ar'],0,1) ?>
                  </div>
                  <?php endif; ?>
                </a>
                <!-- Name -->
                <div style="flex:1;min-width:0;">
                  <a href="profile.php?id=<?= $p['p_id'] ?>" style="font-weight:800;font-size:14px;color:#111827;text-decoration:none;display:flex;align-items:center;gap:5px;">
                    <?= htmlspecialchars($p['p_name_ar']) ?>
                    <?php if ($p['p_verified']): ?><i class="fa-solid fa-circle-check" style="color:#3b82f6;font-size:11px;"></i><?php endif; ?>
                  </a>
                  <p style="font-size:11px;color:#9ca3af;font-weight:600;margin-top:2px;"><?= htmlspecialchars($p['p_title']??'') ?></p>
                </div>
                <!-- Stats -->
                <div style="display:flex;gap:20px;align-items:center;flex-shrink:0;">
                  <div style="text-align:center;">
                    <p style="font-size:16px;font-weight:900;color:#111827;line-height:1;"><?= number_format($p['p_views']) ?></p>
                    <p style="font-size:10px;color:#9ca3af;font-weight:600;margin-top:2px;">إجمالي</p>
                  </div>
                  <span style="background:<?= $p['p_verified']?'#eff6ff':'#f3f4f6' ?>;color:<?= $p['p_verified']?'#2563eb':'#9ca3af' ?>;font-size:10px;font-weight:800;padding:4px 10px;border-radius:999px;">
                    <?= $p['p_verified'] ? 'موثّق' : 'عادي' ?>
                  </span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- ── Companies ── -->
          <div id="stats-companies" style="padding:20px;display:none;">
            <?php if (empty($my_institutions)): ?>
            <div style="text-align:center;padding:48px;color:#9ca3af;">
              <i class="fa-solid fa-building" style="font-size:40px;opacity:.2;display:block;margin-bottom:12px;"></i>
              <p style="font-weight:700;">لا توجد شركات مرتبطة بحسابك</p>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:2px;">
              <?php foreach ($my_institutions as $inst):
                $v30 = $i_views_30d[$inst['inst_id']] ?? 0;
                $v7  = $i_views_7d[$inst['inst_id']]  ?? 0;
              ?>
              <div style="display:flex;align-items:center;gap:14px;padding:14px 12px;border-radius:14px;transition:background .15s;"
                onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background=''">
                <a href="institution.php?id=<?= $inst['inst_id'] ?>" style="flex-shrink:0;">
                  <?php if (!empty($inst['inst_logo'])): ?>
                  <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" style="width:48px;height:48px;border-radius:12px;object-fit:contain;border:2px solid #ede9fe;">
                  <?php else: ?>
                  <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#4c1d95);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:18px;">
                    <?= mb_substr($inst['inst_name_ar'],0,1) ?>
                  </div>
                  <?php endif; ?>
                </a>
                <div style="flex:1;min-width:0;">
                  <a href="institution.php?id=<?= $inst['inst_id'] ?>" style="font-weight:800;font-size:14px;color:#111827;text-decoration:none;display:flex;align-items:center;gap:5px;">
                    <?= htmlspecialchars($inst['inst_name_ar']) ?>
                    <?php if ($inst['inst_verified']): ?><i class="fa-solid fa-circle-check" style="color:#3b82f6;font-size:11px;"></i><?php endif; ?>
                  </a>
                </div>
                <div style="display:flex;gap:20px;align-items:center;flex-shrink:0;">
                  <div style="text-align:center;">
                    <p style="font-size:16px;font-weight:900;color:#111827;line-height:1;"><?= number_format($inst['inst_views']) ?></p>
                    <p style="font-size:10px;color:#9ca3af;font-weight:600;margin-top:2px;">إجمالي</p>
                  </div>
                  <span style="background:<?= $inst['inst_verified']?'#eff6ff':'#f3f4f6' ?>;color:<?= $inst['inst_verified']?'#2563eb':'#9ca3af' ?>;font-size:10px;font-weight:800;padding:4px 10px;border-radius:999px;">
                    <?= $inst['inst_verified'] ? 'موثّقة' : 'عادية' ?>
                  </span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- ── Sponsors ── -->
          <div id="stats-sponsors" style="padding:20px;display:none;">
            <?php if (empty($my_sponsors)): ?>
            <div style="text-align:center;padding:48px;color:#9ca3af;">
              <i class="fa-solid fa-handshake" style="font-size:40px;opacity:.2;display:block;margin-bottom:12px;"></i>
              <p style="font-weight:700;">لا توجد رعايات مرتبطة بحسابك</p>
              <p style="font-size:12px;margin-top:6px;">تواصل مع الإدارة لربط رعايتك بحسابك</p>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
              <?php foreach ($my_sponsors as $sp): ?>
              <div style="border:1px solid #f0f0f0;border-radius:16px;overflow:hidden;">
                <!-- Sponsor header -->
                <div style="display:flex;align-items:center;gap:14px;padding:16px 18px;background:#fafafa;">
                  <?php if (!empty($sp['sp_logo'])): ?>
                  <img src="<?= htmlspecialchars($sp['sp_logo']) ?>" style="width:52px;height:52px;border-radius:12px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;flex-shrink:0;">
                  <?php else: ?>
                  <div style="width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#4c1d95);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:20px;flex-shrink:0;">
                    <?= mb_substr($sp['sp_name'],0,1) ?>
                  </div>
                  <?php endif; ?>
                  <div style="flex:1;">
                    <p style="font-weight:900;font-size:15px;color:#111827;"><?= htmlspecialchars($sp['sp_name']) ?></p>
                    <p style="font-size:11px;color:#9ca3af;font-weight:600;margin-top:2px;">
                      <i class="fa-solid fa-list" style="margin-left:4px;"></i><?= (int)$sp['lists_count'] ?> قائمة
                    </p>
                  </div>
                  <!-- View stats -->
                  <div style="display:flex;gap:16px;">
                    <div style="text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px 16px;">
                      <p style="font-size:20px;font-weight:900;color:#7c3aed;line-height:1;"><?= number_format((int)$sp['total_views']) ?></p>
                      <p style="font-size:10px;color:#9ca3af;font-weight:600;margin-top:3px;">إجمالي المشاهدات</p>
                    </div>
                  </div>
                </div>
                <!-- Linked lists -->
                <?php if (!empty($sp['_lists'])): ?>
                <div style="padding:0 18px 12px;">
                  <p style="font-size:10px;font-weight:800;color:#9ca3af;letter-spacing:.5px;padding:12px 0 8px;">القوائم المرتبطة</p>
                  <div style="display:flex;flex-direction:column;gap:6px;">
                    <?php foreach ($sp['_lists'] as $lst): ?>
                    <a href="list.php?id=<?= $lst['list_id'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border:1px solid #f0f0f0;border-radius:10px;text-decoration:none;transition:border-color .15s;"
                      onmouseover="this.style.borderColor='#c4b5fd'" onmouseout="this.style.borderColor='#f0f0f0'">
                      <span style="font-size:13px;font-weight:700;color:#374151;"><?= htmlspecialchars($lst['list_title']) ?></span>
                      <span style="font-size:12px;color:#7c3aed;font-weight:800;"><i class="fa-solid fa-eye" style="margin-left:4px;"></i><?= number_format((int)$lst['list_views']) ?> مشاهدة</span>
                    </a>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ──────────── SUBMISSIONS TAB ──────────── -->
      <?php elseif ($tab === 'submissions'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="font-black text-gray-800 text-lg">
            <i class="fa-solid fa-plus-circle text-purple-500 ml-2"></i> إضافاتي
          </h2>
          <div class="flex gap-2">
            <a href="add_personality.php" class="px-4 py-2 pi-primary-bg text-white text-xs font-black rounded-xl hover:opacity-90 transition flex items-center gap-1">
              <i class="fa-solid fa-plus"></i> أضف شخصية
            </a>
            <a href="add_institution.php" class="px-4 py-2 bg-indigo-600 text-white text-xs font-black rounded-xl hover:opacity-90 transition flex items-center gap-1">
              <i class="fa-solid fa-plus"></i> أضف شركة
            </a>
          </div>
        </div>

        <p class="text-xs text-gray-400 mb-4 font-medium">لديك تحكم كامل في الشخصيات والشركات إذا قمت بالإضافة</p>

        <?php
        $sub_filter = $_GET['stype'] ?? 'all';
        $sub_tabs   = ['all'=>'الكل','pending'=>'قيد المراجعة','approved'=>'مقبول','rejected'=>'مرفوض'];
        // Count per status for badges
        $sub_counts = ['all'=>count($my_all_subs),'pending'=>0,'approved'=>0,'rejected'=>0];
        foreach ($my_all_subs as $s) { if (isset($sub_counts[$s['sub_status']])) $sub_counts[$s['sub_status']]++; }
        // Apply filter
        $filtered_subs = $sub_filter === 'all' ? $my_all_subs : array_filter($my_all_subs, function($s) use ($sub_filter){ return $s['sub_status'] === $sub_filter; });
        ?>
        <div class="flex gap-2 flex-wrap mb-5">
          <?php foreach ($sub_tabs as $sk => $sl): ?>
          <a href="account.php?tab=submissions&stype=<?= $sk ?>"
            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl transition <?= $sub_filter === $sk ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= $sl ?>
            <?php if ($sub_counts[$sk]): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-black <?= $sub_filter === $sk ? 'bg-white/30 text-white' : 'bg-gray-300 text-gray-600' ?>"><?= $sub_counts[$sk] ?></span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>

        <?php if (empty($my_all_subs)): ?>
        <div class="text-center py-16 text-gray-300">
          <i class="fa-solid fa-pen-to-square text-5xl mb-4"></i>
          <p class="font-semibold text-gray-400">لم تقترح أي شخصية أو مؤسسة بعد</p>
        </div>
        <?php elseif (empty($filtered_subs)): ?>
        <div class="text-center py-10 text-gray-300">
          <i class="fa-solid fa-filter text-3xl mb-3"></i>
          <p class="font-semibold text-gray-400 text-sm">لا توجد اقتراحات في هذه الحالة</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($filtered_subs as $sub):
            $sub_is_p = $sub['sub_type'] === 'personality';
            // Status styles
            $sstyles = [
              'pending'  => ['text'=>'قيد المراجعة', 'cls'=>'bg-yellow-100 text-yellow-700', 'icon'=>'fa-clock'],
              'approved' => ['text'=>'مقبول',         'cls'=>'bg-green-100 text-green-700',  'icon'=>'fa-circle-check'],
              'rejected' => ['text'=>'مرفوض',         'cls'=>'bg-red-100 text-red-700',      'icon'=>'fa-xmark'],
            ];
            $ss = $sstyles[$sub['sub_status']] ?? $sstyles['pending'];
            // Membership badge for approved
            $mem_badge = ''; $mem_cls = '';
            if ($sub['sub_status'] === 'approved') {
              if ($sub['_mem_type'] === 'executive') { $mem_badge='تنفيذي'; $mem_cls='bg-amber-100 text-amber-700'; }
              elseif ($sub['_mem_type'] === 'verified' || $sub['_verified']) { $mem_badge='موثق'; $mem_cls='bg-blue-100 text-blue-700'; }
              else { $mem_badge='عادي'; $mem_cls='bg-gray-100 text-gray-500'; }
            }
          ?>
          <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:bg-gray-50 transition">
            <?php if ($sub['_photo']): ?>
            <img src="<?= htmlspecialchars($sub['_photo']) ?>" class="w-10 h-10 <?= $sub_is_p ? 'rounded-full' : 'rounded-xl' ?> object-cover border border-gray-100 flex-shrink-0">
            <?php else: ?>
            <div class="w-10 h-10 <?= $sub_is_p ? 'rounded-full' : 'rounded-xl' ?> <?= $sub_is_p ? 'bg-purple-100' : 'bg-indigo-100' ?> flex items-center justify-center flex-shrink-0">
              <i class="fa-solid <?= $sub_is_p ? 'fa-user text-purple-500' : 'fa-building text-indigo-500' ?> text-sm"></i>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($sub['_name'] ?: '(بدون اسم)') ?></p>
              <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $sub_is_p ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' ?>">
                  <?= $sub_is_p ? 'شخصية' : 'مؤسسة' ?>
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $ss['cls'] ?>">
                  <i class="fa-solid <?= $ss['icon'] ?> ml-0.5"></i><?= $ss['text'] ?>
                </span>
                <?php if ($mem_badge): ?>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $mem_cls ?>"><?= $mem_badge ?></span>
                <?php endif; ?>
                <span class="text-[10px] text-gray-400"><?= date('d/m/Y', strtotime($sub['sub_created'])) ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ──────────── ACCOUNTS TAB ──────────── -->
      <?php elseif ($tab === 'accounts'): ?>

      <?php if (isset($_GET['req_sent'])): ?>
      <div class="bg-green-50 border border-green-200 rounded-2xl p-4 mb-4 text-green-700 text-sm font-semibold flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-lg"></i> تم إرسال طلبك بنجاح — سيراجعه فريقنا قريباً
      </div>
      <?php endif; ?>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
        <h2 class="font-black text-gray-800 text-lg mb-1">
          <i class="fa-solid fa-gear text-purple-500 ml-2"></i> الحسابات المرتبطة بك
        </h2>
        <p class="text-gray-400 text-sm mb-6">الشخصيات والمؤسسات التي أضفتها — يمكنك اقتراح تعديل أو طلب ترقية</p>

        <?php if (empty($my_personalities) && empty($my_institutions)): ?>
        <div class="text-center py-12 text-gray-300">
          <i class="fa-solid fa-user-gear text-5xl mb-4"></i>
          <p class="font-semibold text-gray-400 mb-3">لا توجد حسابات مرتبطة بعد</p>
          <div class="flex gap-3 justify-center">
            <a href="add_personality.php" class="px-5 py-2 pi-primary-bg text-white text-sm font-black rounded-xl hover:opacity-90 transition">
              <i class="fa-solid fa-user-plus ml-1"></i> اقتراح شخصية
            </a>
            <a href="add_institution.php" class="px-5 py-2 bg-indigo-500 text-white text-sm font-black rounded-xl hover:opacity-90 transition">
              <i class="fa-solid fa-building ml-1"></i> اقتراح مؤسسة
            </a>
          </div>
        </div>
        <?php else: ?>

        <!-- Personalities -->
        <?php if (!empty($my_personalities)): ?>
        <h3 class="font-bold text-gray-600 text-sm mb-3 flex items-center gap-2">
          <i class="fa-solid fa-users text-blue-500"></i> الشخصيات (<?= count($my_personalities) ?>)
        </h3>
        <div class="space-y-3 mb-6">
          <?php foreach ($my_personalities as $ep): ?>
          <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100">
            <?php if ($ep['p_photo']): ?>
            <img src="<?= htmlspecialchars($ep['p_photo']) ?>" class="w-12 h-12 rounded-full object-cover flex-shrink-0">
            <?php else: ?>
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-black flex-shrink-0">
              <?= mb_substr($ep['p_name_ar'],0,1) ?>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-black text-gray-800 text-sm"><?= htmlspecialchars($ep['p_name_ar']) ?></p>
                <?php if ($ep['p_verified']): ?>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold"><i class="fa-solid fa-circle-check ml-0.5"></i> موثق</span>
                <?php endif; ?>
                <?php if (!$ep['p_active']): ?>
                <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-bold">قيد المراجعة</span>
                <?php endif; ?>
              </div>
              <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($ep['p_title'] ?? '') ?></p>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button onclick="openReqModal('personality',<?= $ep['p_id'] ?>,'<?= htmlspecialchars(addslashes($ep['p_name_ar'])) ?>','edit')"
                class="px-3 py-1.5 text-xs font-black bg-purple-50 text-purple-700 border border-purple-200 rounded-xl hover:bg-purple-100 transition">
                <i class="fa-solid fa-pen ml-1"></i> تعديل
              </button>
              <?php if (!$ep['p_verified']): ?>
              <button onclick="openReqModal('personality',<?= $ep['p_id'] ?>,'<?= htmlspecialchars(addslashes($ep['p_name_ar'])) ?>','upgrade')"
                class="px-3 py-1.5 text-xs font-black bg-amber-50 text-amber-700 border border-amber-200 rounded-xl hover:bg-amber-100 transition">
                <i class="fa-solid fa-crown ml-1"></i> ترقية
              </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Institutions -->
        <?php if (!empty($my_institutions)): ?>
        <h3 class="font-bold text-gray-600 text-sm mb-3 flex items-center gap-2">
          <i class="fa-solid fa-building text-indigo-500"></i> المؤسسات (<?= count($my_institutions) ?>)
        </h3>
        <div class="space-y-3">
          <?php foreach ($my_institutions as $ei): ?>
          <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100">
            <?php if ($ei['inst_logo']): ?>
            <img src="<?= htmlspecialchars($ei['inst_logo']) ?>" class="w-12 h-12 rounded-xl object-contain flex-shrink-0">
            <?php else: ?>
            <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 font-black flex-shrink-0">
              <?= mb_substr($ei['inst_name_ar'],0,1) ?>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-black text-gray-800 text-sm"><?= htmlspecialchars($ei['inst_name_ar']) ?></p>
                <?php if ($ei['inst_verified']): ?>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold"><i class="fa-solid fa-circle-check ml-0.5"></i> موثقة</span>
                <?php endif; ?>
                <?php if (!$ei['inst_active']): ?>
                <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-bold">قيد المراجعة</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button onclick="openReqModal('institution',<?= $ei['inst_id'] ?>,'<?= htmlspecialchars(addslashes($ei['inst_name_ar'])) ?>','edit')"
                class="px-3 py-1.5 text-xs font-black bg-purple-50 text-purple-700 border border-purple-200 rounded-xl hover:bg-purple-100 transition">
                <i class="fa-solid fa-pen ml-1"></i> تعديل
              </button>
              <?php if (!$ei['inst_verified']): ?>
              <button onclick="openReqModal('institution',<?= $ei['inst_id'] ?>,'<?= htmlspecialchars(addslashes($ei['inst_name_ar'])) ?>','upgrade')"
                class="px-3 py-1.5 text-xs font-black bg-amber-50 text-amber-700 border border-amber-200 rounded-xl hover:bg-amber-100 transition">
                <i class="fa-solid fa-crown ml-1"></i> ترقية
              </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- My requests history -->
      <?php if (!empty($my_requests)): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-black text-gray-800 mb-4"><i class="fa-solid fa-clock-rotate-left text-gray-400 ml-2"></i> سجل طلباتي</h3>
        <div class="space-y-3">
          <?php
          $req_status_map = [
            'pending'  => ['text'=>'قيد المراجعة','class'=>'bg-yellow-100 text-yellow-700'],
            'approved' => ['text'=>'تم القبول',   'class'=>'bg-green-100 text-green-700'],
            'rejected' => ['text'=>'مرفوض',       'class'=>'bg-red-100 text-red-700'],
          ];
          foreach ($my_requests as $rq):
            // Get entity name
            if ($rq['er_entity_type'] === 'personality') {
              $er = $mysqli->query("SELECT p_name_ar FROM pi_personalities WHERE p_id={$rq['er_entity_id']}");
              $ename = $er && $er->num_rows ? $er->fetch_assoc()['p_name_ar'] : 'محذوف';
            } else {
              $er = $mysqli->query("SELECT inst_name_ar FROM pi_institutions WHERE inst_id={$rq['er_entity_id']}");
              $ename = $er && $er->num_rows ? $er->fetch_assoc()['inst_name_ar'] : 'محذوف';
            }
          ?>
          <?php
          $rq_data_b64 = base64_encode(json_encode([
            'entity_name' => $ename,
            'entity_type' => $rq['er_entity_type'],
            'req_type'    => $rq['er_req_type'],
            'status'      => $rq['er_status'],
            'created'     => $rq['er_created'],
            'admin_note'  => $rq['er_admin_note'] ?? '',
            'edit_data'   => json_decode($rq['er_edit_data'] ?? '{}', true) ?: [],
          ], JSON_UNESCAPED_UNICODE));
          ?>
          <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
            <div class="flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($ename) ?></p>
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">تعديل</span>
              </div>
              <?php if ($rq['er_admin_note']): ?>
              <p class="text-xs text-gray-500 mt-0.5"><i class="fa-solid fa-comment ml-1"></i><?= htmlspecialchars($rq['er_admin_note']) ?></p>
              <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button class="view-req-btn text-xs px-3 py-1.5 rounded-xl bg-purple-50 text-purple-700 hover:bg-purple-100 font-bold transition border border-purple-200" data-rq="<?= htmlspecialchars($rq_data_b64) ?>">
                <i class="fa-solid fa-eye ml-1"></i> عرض
              </button>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $req_status_map[$rq['er_status']]['class'] ?>">
                <?= $req_status_map[$rq['er_status']]['text'] ?>
              </span>
              <span class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($rq['er_created'])) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Request Modal -->
      <div id="req-modal" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,.5)">
        <div class="flex items-center justify-center min-h-screen p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" dir="rtl">
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
              <h3 class="font-black text-gray-800" id="modal-title">إرسال طلب</h3>
              <button onclick="closeReqModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
              <input type="hidden" name="_action" value="send_edit_request">
              <input type="hidden" name="entity_type" id="modal-entity-type">
              <input type="hidden" name="entity_id" id="modal-entity-id">
              <input type="hidden" name="req_type" id="modal-req-type">

              <!-- Edit fields (shown for edit type) -->
              <div id="edit-fields" class="space-y-3 hidden">
                <p class="text-sm text-gray-500 font-semibold">اذكر التعديلات التي تريد إجراؤها — اترك الحقول الفارغة إذا لم ترد تغييرها</p>
                <div id="personality-edit-fields">
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">الاسم بالعربي</label><input type="text" name="name_ar" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400"></div>
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">الاسم بالإنجليزي</label><input type="text" name="name_en" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400" dir="ltr"></div>
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">المسمى الوظيفي</label><input type="text" name="title" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400"></div>
                    <div>
                      <label class="block text-xs font-bold text-gray-600 mb-1">الجنسية</label>
                      <select name="nationality" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
                        <option value="">— اختر —</option>
                        <?php foreach ($all_countries as $cn): ?>
                        <option value="<?= htmlspecialchars($cn['c_name']) ?>"><?= htmlspecialchars($cn['c_flag'].' '.$cn['c_name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-gray-600 mb-1">بلد الإقامة</label>
                      <input type="text" name="residence" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400" placeholder="مثال: دبي، لندن...">
                    </div>
                  </div>
                  <div class="mt-3"><label class="block text-xs font-bold text-gray-600 mb-1">النبذة / السيرة الذاتية</label><textarea name="bio" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 resize-none" placeholder="أكتب التعديل المطلوب..."></textarea></div>
              <div class="mt-3">
                <label class="block text-xs font-bold text-gray-600 mb-1">الصورة الشخصية <span class="text-gray-400 font-normal">(اختياري)</span></label>
                <div class="pi-upload-zone" onclick="document.getElementById('edit-photo-file-p').click()">
                  <input type="file" name="photo_file" id="edit-photo-file-p" accept="image/jpeg,image/png,image/webp" class="hidden" data-preview="edit-photo-prev-p" data-placeholder="edit-photo-ph-p">
                  <div id="edit-photo-ph-p">
                    <div style="width:52px;height:52px;border-radius:14px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;"><i class="fa-solid fa-camera" style="font-size:20px;color:#9ca3af;"></i></div>
                    <p style="font-size:13px;font-weight:800;color:#374151;margin-bottom:3px;">اضغط لرفع صورة</p>
                    <p style="font-size:11px;color:#9ca3af;">JPG, PNG, WebP — حتى 5MB</p>
                  </div>
                  <img id="edit-photo-prev-p" src="" class="hidden" style="width:90px;height:90px;object-fit:cover;border-radius:50%;margin:0 auto;display:none;border:3px solid #e9d5ff;">
                </div>
              </div>
                </div>
                <div id="institution-edit-fields" class="hidden">
                  <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">الاسم بالعربي</label><input type="text" name="name_ar" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400"></div>
                    <div><label class="block text-xs font-bold text-gray-600 mb-1">الاسم بالإنجليزي</label><input type="text" name="name_en" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400" dir="ltr"></div>
                    <div>
                      <label class="block text-xs font-bold text-gray-600 mb-1">الدولة</label>
                      <select name="country" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
                        <option value="">— اختر الدولة —</option>
                        <?php foreach ($all_countries as $cn): ?>
                        <option value="<?= htmlspecialchars($cn['c_name']) ?>"><?= htmlspecialchars($cn['c_flag'].' '.$cn['c_name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="mt-3"><label class="block text-xs font-bold text-gray-600 mb-1">الوصف</label><textarea name="description" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 resize-none"></textarea></div>
                <div class="mt-3">
                  <label class="block text-xs font-bold text-gray-600 mb-1">شعار المؤسسة <span class="text-gray-400 font-normal">(اختياري)</span></label>
                  <div class="pi-upload-zone" onclick="document.getElementById('edit-photo-file-i').click()">
                    <input type="file" name="photo_file" id="edit-photo-file-i" accept="image/jpeg,image/png,image/webp" class="hidden" data-preview="edit-photo-prev-i" data-placeholder="edit-photo-ph-i">
                    <div id="edit-photo-ph-i">
                      <div style="width:52px;height:52px;border-radius:14px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;"><i class="fa-solid fa-camera" style="font-size:20px;color:#9ca3af;"></i></div>
                      <p style="font-size:13px;font-weight:800;color:#374151;margin-bottom:3px;">اضغط لرفع صورة</p>
                      <p style="font-size:11px;color:#9ca3af;">JPG, PNG, WebP — حتى 5MB</p>
                    </div>
                    <img id="edit-photo-prev-i" src="" class="hidden" style="width:90px;height:90px;object-fit:cover;border-radius:12px;margin:0 auto;display:none;border:2px solid #e9d5ff;">
                  </div>
                </div>
                </div>
              </div>

              <!-- Upgrade fields (shown for upgrade type) -->
              <div id="upgrade-fields" class="space-y-4 hidden">
                <p class="text-sm text-gray-600 font-bold">اختر نوع الترقية</p>
                <div class="grid grid-cols-2 gap-3">
                  <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-400 transition" id="upgrade-type-verified-label">
                    <input type="radio" name="upgrade_to" value="verified" class="accent-blue-500" onchange="onUpgradeTypeChange(this)">
                    <div><p class="font-black text-sm text-gray-800"><i class="fa-solid fa-circle-check text-blue-500 ml-1"></i>توثيق</p><p class="text-xs text-gray-400">شارة زرقاء</p></div>
                  </label>
                  <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-amber-400 transition" id="upgrade-type-executive-label">
                    <input type="radio" name="upgrade_to" value="executive" class="accent-amber-500" onchange="onUpgradeTypeChange(this)">
                    <div><p class="font-black text-sm text-gray-800"><i class="fa-solid fa-crown text-amber-500 ml-1"></i>تنفيذي</p><p class="text-xs text-gray-400">شارة ذهبية</p></div>
                  </label>
                </div>
                <!-- Plan boxes + contact: hidden until type selected -->
                <div id="upgrade-plan-section" class="hidden space-y-4">
                  <p class="text-sm text-gray-600 font-bold">اختر الباقة</p>
                  <div class="grid grid-cols-2 gap-3" id="upgrade-plan-boxes">
                    <label class="flex flex-col gap-1 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 transition" id="plan-verified-monthly">
                      <input type="radio" name="mem_plan" value="monthly" class="accent-purple-500 hidden">
                      <p class="font-black text-sm text-gray-800">شهري — توثيق</p>
                      <p class="text-purple-600 font-black text-lg">$90<span class="text-xs text-gray-400 font-normal">/شهر</span></p>
                    </label>
                    <label class="flex flex-col gap-1 p-3 border-2 border-purple-500 rounded-xl cursor-pointer hover:border-purple-600 transition bg-purple-50" id="plan-verified-lifetime">
                      <input type="radio" name="mem_plan" value="lifetime" class="accent-purple-500 hidden">
                      <p class="font-black text-sm text-gray-800">مدى الحياة — توثيق ⭐</p>
                      <p class="text-purple-600 font-black text-lg">$99</p>
                    </label>
                    <label class="flex flex-col gap-1 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-amber-400 transition hidden" id="plan-executive-monthly">
                      <input type="radio" name="mem_plan" value="monthly" class="accent-amber-500 hidden">
                      <p class="font-black text-sm text-gray-800">شهري — تنفيذي</p>
                      <p class="text-amber-600 font-black text-lg">$210<span class="text-xs text-gray-400 font-normal">/شهر</span></p>
                    </label>
                    <label class="flex flex-col gap-1 p-3 border-2 border-amber-500 rounded-xl cursor-pointer hover:border-amber-600 transition bg-amber-50 hidden" id="plan-executive-lifetime">
                      <input type="radio" name="mem_plan" value="lifetime" class="accent-amber-500 hidden">
                      <p class="font-black text-sm text-gray-800">مدى الحياة — تنفيذي 👑</p>
                      <p class="text-amber-600 font-black text-lg">$250</p>
                    </label>
                  </div>
                  <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                      <label class="block text-xs font-bold text-gray-600 mb-1">الاسم الكامل <span class="text-red-500">*</span></label>
                      <input type="text" name="mem_name" id="mem-name-field" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400" value="<?= htmlspecialchars($user['u_name'] ?? '') ?>">
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-gray-600 mb-1">رقم الجوال <span class="text-red-500">*</span></label>
                      <input type="tel" name="mem_phone" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400" dir="ltr" value="<?= htmlspecialchars($user['u_phone'] ?? '') ?>">
                    </div>
                  </div>
                </div>
              </div>
              <script>
              function onUpgradeTypeChange(radio) {
                var isExec = radio.value === 'executive';
                // Highlight selected type label
                var vl = document.getElementById('upgrade-type-verified-label');
                var el = document.getElementById('upgrade-type-executive-label');
                if (vl) vl.classList.toggle('border-blue-500', !isExec);
                if (el) el.classList.toggle('border-amber-500', isExec);
                // Show plan section
                var ps = document.getElementById('upgrade-plan-section');
                if (ps) ps.classList.remove('hidden');
                // Toggle plan boxes
                ['plan-verified-monthly','plan-verified-lifetime'].forEach(function(id){ var e=document.getElementById(id); if(e) e.classList.toggle('hidden',isExec); });
                ['plan-executive-monthly','plan-executive-lifetime'].forEach(function(id){ var e=document.getElementById(id); if(e) e.classList.toggle('hidden',!isExec); });
                // Auto-select lifetime plan and highlight it
                document.querySelectorAll('#upgrade-plan-boxes label').forEach(function(lb){
                  lb.classList.remove('border-purple-500','bg-purple-50','border-amber-500','bg-amber-50');
                  lb.classList.add('border-gray-200');
                });
                var lv = isExec ? document.getElementById('plan-executive-lifetime') : document.getElementById('plan-verified-lifetime');
                if (lv) {
                  var ri = lv.querySelector('input'); if(ri) ri.checked = true;
                  lv.classList.remove('border-gray-200');
                  lv.classList.add(isExec ? 'border-amber-500' : 'border-purple-500', isExec ? 'bg-amber-50' : 'bg-purple-50');
                }
              }
              document.querySelectorAll('#upgrade-plan-boxes label').forEach(function(lbl){
                lbl.addEventListener('click', function(){
                  var ri = this.querySelector('input[type=radio]');
                  if(ri) ri.checked = true;
                  // Update visual highlight: remove from all, add to clicked
                  var isExec = (document.querySelector('input[name="upgrade_to"]:checked') || {}).value === 'executive';
                  var color  = isExec ? ['amber-500','amber-50'] : ['purple-500','purple-50'];
                  document.querySelectorAll('#upgrade-plan-boxes label').forEach(function(lb){
                    lb.classList.remove('border-purple-500','bg-purple-50','border-amber-500','bg-amber-50');
                    lb.classList.add('border-gray-200');
                  });
                  this.classList.remove('border-gray-200');
                  this.classList.add('border-'+color[0], 'bg-'+color[1]);
                });
              });
              </script>

              <button type="submit" class="w-full py-3 pi-primary-bg text-white font-black rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-paper-plane"></i> إرسال الطلب
              </button>
            </form>
          </div>
        </div>
      </div>
      <script>
      var _entityData = {
        personality: <?= json_encode(array_combine(
          array_column($my_personalities, 'p_id'),
          array_map(function($p){ return ['name_ar'=>$p['p_name_ar']??'','name_en'=>$p['p_name_en']??'','title'=>$p['p_title']??'','nationality'=>$p['p_nationality']??'','residence'=>$p['p_residence']??'','bio'=>$p['p_bio']??'','photo'=>$p['p_photo']??'']; }, $my_personalities)
        ) ?: new stdClass(), JSON_UNESCAPED_UNICODE) ?>,
        institution: <?= json_encode(array_combine(
          array_column($my_institutions, 'inst_id'),
          array_map(function($i){ return ['name_ar'=>$i['inst_name_ar']??'','name_en'=>$i['inst_name_en']??'','description'=>$i['inst_description']??'','photo'=>$i['inst_logo']??'']; }, $my_institutions)
        ) ?: new stdClass(), JSON_UNESCAPED_UNICODE) ?>
      };
      function openReqModal(type, id, name, reqType) {
        document.getElementById('modal-entity-type').value = type;
        document.getElementById('modal-entity-id').value   = id;
        document.getElementById('modal-req-type').value    = reqType;
        document.getElementById('modal-title').textContent = (reqType === 'edit' ? 'اقتراح تعديل: ' : 'طلب ترقية: ') + name;
        document.getElementById('edit-fields').classList.toggle('hidden', reqType !== 'edit');
        document.getElementById('upgrade-fields').classList.toggle('hidden', reqType !== 'upgrade');
        document.getElementById('personality-edit-fields').classList.toggle('hidden', type !== 'personality');
        document.getElementById('institution-edit-fields').classList.toggle('hidden', type !== 'institution');
        // Pre-fill current values
        var d = (_entityData[type] || {})[id] || {};
        if (reqType === 'edit') {
          // Use scoped querySelector to avoid selecting wrong section's inputs
          var sec = document.getElementById(type === 'personality' ? 'personality-edit-fields' : 'institution-edit-fields');
          if (sec) {
            var q = function(n){ return sec.querySelector('[name='+n+']'); };
            if (type === 'personality') {
              if(q('name_ar'))     q('name_ar').value     = d.name_ar    || '';
              if(q('name_en'))     q('name_en').value     = d.name_en    || '';
              if(q('title'))       q('title').value       = d.title      || '';
              if(q('nationality')) q('nationality').value = d.nationality || '';
              if(q('residence'))   q('residence').value   = d.residence  || '';
              if(q('bio'))         q('bio').value         = d.bio ? d.bio.replace(/<[^>]+>/g,'') : '';
              var pp = document.getElementById('edit-photo-prev-p');
              if (d.photo && pp) { pp.src = d.photo; pp.classList.remove('hidden'); }
              else if (pp) pp.classList.add('hidden');
            } else {
              if(q('name_ar'))     q('name_ar').value     = d.name_ar     || '';
              if(q('name_en'))     q('name_en').value     = d.name_en     || '';
              if(q('description')) q('description').value = d.description ? d.description.replace(/<[^>]+>/g,'') : '';
              var pi = document.getElementById('edit-photo-prev-i');
              if (d.photo && pi) { pi.src = d.photo; pi.classList.remove('hidden'); }
              else if (pi) pi.classList.add('hidden');
            }
          }
        }
        // Reset file inputs
        var fp = document.getElementById('edit-photo-file-p');
        var fi = document.getElementById('edit-photo-file-i');
        if (fp) { fp.value = ''; document.getElementById('edit-photo-name-p').textContent = 'اختر صورة...'; }
        if (fi) { fi.value = ''; document.getElementById('edit-photo-name-i').textContent = 'اختر صورة...'; }
        // Reset upgrade type selection and hide plan section
        if (reqType === 'upgrade') {
          document.querySelectorAll('input[name="upgrade_to"]').forEach(function(r){ r.checked = false; });
          var ps = document.getElementById('upgrade-plan-section');
          if (ps) ps.classList.add('hidden');
          var vl = document.getElementById('upgrade-type-verified-label');
          var el = document.getElementById('upgrade-type-executive-label');
          if (vl) vl.classList.remove('border-blue-500');
          if (el) el.classList.remove('border-amber-500');
        }
        document.getElementById('req-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }
      function closeReqModal() {
        document.getElementById('req-modal').classList.add('hidden');
        document.body.style.overflow = '';
      }
      function previewEditPhoto(input, prevId, nameId) {
        var prev = document.getElementById(prevId);
        var nm   = document.getElementById(nameId);
        if (input.files && input.files[0]) {
          var r = new FileReader();
          r.onload = function(e) { if (prev) { prev.src = e.target.result; prev.classList.remove('hidden'); } };
          r.readAsDataURL(input.files[0]);
          if (nm) nm.textContent = input.files[0].name;
        }
      }
      document.getElementById('req-modal').addEventListener('click', function(e){ if(e.target===this) closeReqModal(); });

      // ── View edit request popup ──────────────────────────────────────────
      var fieldLabels = {
        name_ar:'الاسم بالعربي', name_en:'الاسم بالإنجليزي', title:'المسمى الوظيفي',
        nationality:'الجنسية', residence:'الإقامة', bio:'السيرة الذاتية',
        description:'الوصف', website:'الموقع الإلكتروني', photo:'الصورة'
      };
      document.addEventListener('click', function(e) {
        var btn = e.target.closest('.view-req-btn');
        if (btn) viewEditRequest(btn.getAttribute('data-rq'));
      });
      function viewEditRequest(b64) {
        var d = JSON.parse(atob(b64));
        var statusMap = {pending:'قيد المراجعة', approved:'تم القبول', rejected:'مرفوض'};
        var statusColor = {pending:'bg-yellow-100 text-yellow-700', approved:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700'};

        var rows = '';
        var data = d.edit_data || {};
        var hasData = false;
        for (var k in data) {
          if (!fieldLabels[k]) continue;
          var val = data[k] || '';
          hasData = true;
          if (k === 'photo' && val) {
            rows += '<div class="flex items-start gap-3 py-2.5 border-b border-gray-100 last:border-0"><span class="text-xs text-gray-500 w-32 flex-shrink-0 pt-1">'+fieldLabels[k]+'</span><img src="'+val+'" class="w-14 h-14 rounded-xl object-cover border border-gray-200"></div>';
          } else if (val) {
            rows += '<div class="flex items-start gap-3 py-2.5 border-b border-gray-100 last:border-0"><span class="text-xs text-gray-500 w-32 flex-shrink-0 pt-0.5">'+fieldLabels[k]+'</span><span class="text-sm text-gray-800 flex-1">'+val+'</span></div>';
          }
        }
        if (!hasData) rows = '<p class="text-sm text-gray-400 text-center py-4">لا توجد تفاصيل تعديل</p>';

        var adminNote = d.admin_note ? '<div class="mt-4 bg-blue-50 rounded-xl px-4 py-3 text-sm text-blue-800"><i class="fa-solid fa-comment-dots ml-2 text-blue-400"></i><strong>ملاحظة الإدارة:</strong> '+d.admin_note+'</div>' : '';

        document.getElementById('view-req-body').innerHTML =
          '<div class="flex items-center gap-3 mb-4 pb-4 border-b border-gray-100">' +
          '<div class="flex-1"><p class="font-black text-gray-800">'+d.entity_name+'</p>' +
          '<p class="text-xs text-gray-400 mt-0.5">'+date_ar(d.created)+'</p></div>' +
          '<span class="text-xs px-2.5 py-1 rounded-full font-bold '+(statusColor[d.status]||'')+'">'+statusMap[d.status]+'</span>' +
          '</div>' +
          '<div class="space-y-0">'+rows+'</div>' +
          adminNote;

        document.getElementById('view-req-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }
      function closeViewReqModal() {
        document.getElementById('view-req-modal').classList.add('hidden');
        document.body.style.overflow = '';
      }
      function date_ar(s) {
        if (!s) return '';
        var parts = s.split(' ')[0].split('-');
        return parts[2]+'/'+parts[1]+'/'+parts[0];
      }
      </script>

      <!-- View Edit Request Popup -->
      <div id="view-req-modal" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,.5)">
        <div class="flex items-center justify-center min-h-screen p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" dir="rtl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h3 class="font-black text-gray-800 text-sm">تفاصيل طلب التعديل</h3>
              <button onclick="closeViewReqModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div id="view-req-body" class="px-6 py-5 max-h-[70vh] overflow-y-auto"></div>
          </div>
        </div>
      </div>
      <script>
      document.getElementById('view-req-modal').addEventListener('click', function(e){ if(e.target===this) closeViewReqModal(); });
      </script>

      <!-- ──────────── MEMBERSHIP TAB ──────────── -->
      <?php elseif ($tab === 'membership'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-black text-gray-800 text-lg mb-2">
          <i class="fa-solid fa-gem text-purple-500 ml-2"></i> اشتراكاتي
        </h2>
        <p class="text-gray-400 text-sm mb-6">التوثيق يكون على الشخصيات والمؤسسات التي تديرها، وليس على حسابك الشخصي</p>

        <?php if (empty($my_personalities) && empty($my_institutions)): ?>
        <div class="text-center py-12 bg-gray-50 rounded-2xl">
          <i class="fa-solid fa-id-card text-4xl text-gray-300 mb-3 block"></i>
          <p class="text-gray-500 font-semibold mb-4">لا توجد صفحات مرتبطة بحسابك بعد</p>
          <div class="flex gap-3 justify-center">
            <a href="add_personality.php" class="px-5 py-2 pi-primary-bg text-white text-sm font-black rounded-xl hover:opacity-90 transition">اقتراح شخصية</a>
            <a href="add_institution.php" class="px-5 py-2 bg-indigo-600 text-white text-sm font-black rounded-xl hover:opacity-90 transition">اقتراح مؤسسة</a>
          </div>
        </div>
        <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($my_personalities as $mp):
            $mem = $mp['p_membership_type'] ?? 'standard';
            if ($mem === 'executive') { $badge = 'تنفيذي'; $bcls = 'bg-amber-100 text-amber-800 border-amber-200'; $icn = 'fa-crown'; }
            elseif ($mem === 'verified' || $mp['p_verified']) { $badge = 'موثق'; $bcls = 'bg-blue-100 text-blue-700 border-blue-200'; $icn = 'fa-circle-check'; }
            else { $badge = 'غير موثق'; $bcls = 'bg-gray-100 text-gray-500 border-gray-200'; $icn = 'fa-circle'; }
          ?>
          <div class="flex items-center gap-4 p-4 rounded-2xl border-2 <?= $bcls ?>">
            <?php if ($mp['p_photo']): ?><img src="<?= htmlspecialchars($mp['p_photo']) ?>" class="w-12 h-12 rounded-full object-cover flex-shrink-0">
            <?php else: ?><div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-user text-purple-500"></i></div><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-black text-gray-800"><?= htmlspecialchars($mp['p_name_ar']) ?></p>
              <p class="text-gray-400 text-xs"><?= htmlspecialchars($mp['p_title'] ?? '') ?></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs px-2.5 py-1 rounded-full font-bold <?= $bcls ?>"><i class="fa-solid <?= $icn ?> ml-1 text-xs"></i><?= $badge ?></span>
              <?php if ($mem === 'standard' && !$mp['p_verified']): ?>
              <span class="text-xs text-gray-400">— اذهب لإدارة الحسابات لطلب الترقية</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php foreach ($my_institutions as $mi):
            $mem = $mi['inst_membership_type'] ?? 'standard';
            if ($mem === 'executive') { $badge = 'تنفيذي'; $bcls = 'bg-amber-100 text-amber-800 border-amber-200'; $icn = 'fa-crown'; }
            elseif ($mem === 'verified' || $mi['inst_verified']) { $badge = 'موثقة'; $bcls = 'bg-blue-100 text-blue-700 border-blue-200'; $icn = 'fa-circle-check'; }
            else { $badge = 'غير موثقة'; $bcls = 'bg-gray-100 text-gray-500 border-gray-200'; $icn = 'fa-circle'; }
          ?>
          <div class="flex items-center gap-4 p-4 rounded-2xl border-2 <?= $bcls ?>">
            <?php if ($mi['inst_logo']): ?><img src="<?= htmlspecialchars($mi['inst_logo']) ?>" class="w-12 h-12 rounded-xl object-cover flex-shrink-0">
            <?php else: ?><div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-building text-indigo-500"></i></div><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-black text-gray-800"><?= htmlspecialchars($mi['inst_name_ar']) ?></p>
              <p class="text-xs text-indigo-500 font-bold">مؤسسة</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs px-2.5 py-1 rounded-full font-bold <?= $bcls ?>"><i class="fa-solid <?= $icn ?> ml-1 text-xs"></i><?= $badge ?></span>
              <?php if ($mem === 'standard' && !$mi['inst_verified']): ?>
              <span class="text-xs text-gray-400">— اذهب لإدارة الحسابات لطلب الترقية</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ──────────── COMPLAINTS TAB ──────────── -->
      <?php elseif ($tab === 'complaints'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="font-black text-gray-800 text-lg">
            <i class="fa-solid fa-pen-to-square text-purple-500 ml-2"></i> الشكاوي والملاحظات
          </h2>
          <button onclick="document.getElementById('new-complaint-form').classList.toggle('hidden')"
            class="px-4 py-2 pi-primary-bg text-white text-xs font-black rounded-xl hover:opacity-90 transition flex items-center gap-1">
            <i class="fa-solid fa-plus"></i> رسالة جديدة
          </button>
        </div>

        <!-- New complaint form -->
        <div id="new-complaint-form" class="hidden mb-6 p-5 bg-gray-50 rounded-2xl border border-gray-100">
          <?php if (isset($_GET['sent'])): ?>
          <div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4 text-sm text-green-700 font-semibold">
            <i class="fa-solid fa-circle-check ml-2"></i> تم إرسال رسالتك بنجاح
          </div>
          <?php endif; ?>
          <form method="POST" class="space-y-4">
            <input type="hidden" name="_action" value="send_complaint">
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1.5">نوع الرسالة <span class="text-red-500">*</span></label>
              <select name="cmp_type" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400">
                <option value="complaint">شكوى</option>
                <option value="suggestion">اقتراح</option>
                <option value="feedback">ملاحظة</option>
                <option value="request">طلب</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1.5">عنوان الرسالة <span class="text-red-500">*</span></label>
              <input type="text" name="cmp_subject" required
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 transition"
                placeholder="أدخل عنوان الرسالة">
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1.5">محتوى الرسالة <span class="text-red-500">*</span></label>
              <textarea name="cmp_message" required rows="4"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 transition resize-none"
                placeholder="أدخل محتوى الرسالة"></textarea>
            </div>
            <button type="submit"
              class="px-6 py-2.5 pi-primary-bg text-white font-black rounded-xl hover:opacity-90 transition text-sm">
              أرسل الآن
            </button>
          </form>
        </div>

        <!-- Filter tabs -->
        <?php
        $ctabs = [''=> 'الكل', 'complaint'=>'شكوى','suggestion'=>'اقتراح','feedback'=>'ملاحظة','request'=>'طلب'];
        ?>
        <div class="flex gap-2 flex-wrap border-b border-gray-100 pb-4 mb-5">
          <?php foreach ($ctabs as $ck => $cl): ?>
          <a href="account.php?tab=complaints&ctype=<?= $ck ?>"
            class="px-3 py-1.5 text-xs font-bold rounded-lg transition <?= $cmp_filter === $ck ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= $cl ?>
          </a>
          <?php endforeach; ?>
        </div>

        <?php if (empty($my_complaints)): ?>
        <div class="text-center py-16 text-gray-300">
          <i class="fa-solid fa-pen-to-square text-5xl mb-4"></i>
          <p class="font-semibold text-gray-400">عفواً لا توجد شكاوي أو ملاحظات تم إرسالها</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
          <?php
          $type_labels = ['complaint'=>'شكوى','suggestion'=>'اقتراح','feedback'=>'ملاحظة','request'=>'طلب'];
          $type_colors = ['complaint'=>'bg-red-100 text-red-700','suggestion'=>'bg-blue-100 text-blue-700','feedback'=>'bg-purple-100 text-purple-700','request'=>'bg-green-100 text-green-700'];
          $status_labels = ['new'=>'جديدة','read'=>'تمت القراءة','resolved'=>'تم الحل'];
          $status_colors = ['new'=>'bg-yellow-100 text-yellow-700','read'=>'bg-blue-100 text-blue-700','resolved'=>'bg-green-100 text-green-700'];
          foreach ($my_complaints as $c):
          ?>
          <div class="border border-gray-100 rounded-2xl p-4">
            <div class="flex items-start justify-between gap-3 mb-2">
              <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($c['cmp_subject']) ?></p>
              <div class="flex gap-1.5 flex-shrink-0">
                <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $type_colors[$c['cmp_type']] ?? '' ?>">
                  <?= $type_labels[$c['cmp_type']] ?? $c['cmp_type'] ?>
                </span>
                <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $status_colors[$c['cmp_status']] ?? '' ?>">
                  <?= $status_labels[$c['cmp_status']] ?? $c['cmp_status'] ?>
                </span>
              </div>
            </div>
            <p class="text-gray-500 text-xs leading-relaxed"><?= htmlspecialchars(mb_substr($c['cmp_message'],0,150)) ?>...</p>
            <p class="text-gray-300 text-xs mt-2"><?= date('Y/m/d', strtotime($c['cmp_created'])) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php endif; ?>
    </div><!-- /main -->
  </div><!-- /flex -->
</div>

<style>
.edit-input-row { animation: slideDown .18s ease; }
@keyframes slideDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
</style>
<script>
function toggleEdit(field) {
  var el  = document.getElementById('edit_' + field);
  var btn = document.getElementById('btn_' + field);
  if (!el) return;
  var opening = el.classList.contains('hidden');
  el.classList.toggle('hidden', !opening);
  if (btn) {
    var icon = btn.querySelector('i');
    var lbl  = btn.querySelector('span');
    if (opening) {
      btn.classList.remove('text-purple-600','hover:bg-purple-50');
      btn.classList.add('text-red-500','hover:bg-red-50','bg-red-50');
      if (icon) { icon.className = 'fa-solid fa-xmark text-xs'; }
      if (lbl)  lbl.textContent = 'إلغاء';
    } else {
      btn.classList.add('text-purple-600','hover:bg-purple-50');
      btn.classList.remove('text-red-500','hover:bg-red-50','bg-red-50');
      if (icon) { icon.className = 'fa-solid fa-pen text-xs'; }
      if (lbl)  lbl.textContent = 'تعديل';
    }
    if (opening) { var inp = el.querySelector('input,select'); if(inp) inp.focus(); }
  }
}
function showStatsTab(which) {
  var tabs = ['personalities','companies','sponsors'];
  tabs.forEach(function(t) {
    var el = document.getElementById('stats-' + t);
    if (el) el.style.display = (t === which) ? 'block' : 'none';
  });
  // Active tab button styles
  var btns = {'personalities':'st-p','companies':'st-c','sponsors':'st-s'};
  Object.keys(btns).forEach(function(t) {
    var btn = document.getElementById(btns[t]);
    if (!btn) return;
    if (t === which) {
      btn.style.borderBottom = '3px solid #7c3aed';
      btn.style.color = '#7c3aed';
    } else {
      btn.style.borderBottom = '3px solid transparent';
      btn.style.color = '#9ca3af';
    }
  });
}
<?php if (isset($_GET['sent'])): ?>
document.addEventListener('DOMContentLoaded', function(){
  document.getElementById('new-complaint-form').classList.remove('hidden');
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
