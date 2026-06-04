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

// Load submissions
$my_personalities = [];
$my_institutions  = [];
$r = $mysqli->query("SELECT p_id,p_name_ar,p_title,p_photo,p_active,p_verified,p_views FROM pi_personalities WHERE p_added_by_user=" . (int)$user['u_id'] . " ORDER BY p_id DESC");
if ($r) while ($row=$r->fetch_assoc()) $my_personalities[] = $row;
$r = $mysqli->query("SELECT inst_id,inst_name_ar,inst_logo,inst_active,inst_verified,inst_views FROM pi_institutions WHERE inst_added_by_user=" . (int)$user['u_id'] . " ORDER BY inst_id DESC");
if ($r) while ($row=$r->fetch_assoc()) $my_institutions[] = $row;

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
        <?php if ($user['u_plan'] !== 'free'): ?>
        <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 text-xs font-bold rounded-full <?= $user['u_plan'] === 'executive' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-700' ?>">
          <i class="fa-solid <?= $user['u_plan'] === 'executive' ? 'fa-crown' : 'fa-circle-check' ?> text-xs"></i>
          <?= $user['u_plan'] === 'executive' ? 'رئيس تنفيذي' : 'موثق' ?>
        </span>
        <?php else: ?>
        <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-500">
          الخطة المجانية
        </span>
        <?php endif; ?>
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
          <div class="flex items-center justify-between py-4 border-b border-gray-50 gap-4">
            <span class="text-sm font-bold text-gray-500 w-28 flex-shrink-0"><?= $flabel ?></span>
            <span class="flex-1 text-sm text-gray-800 font-semibold">
              <?= $fval ? htmlspecialchars($fval) : '<span class="text-gray-300 font-normal">لم يتم التحديد</span>' ?>
            </span>
            <?php if (!$isEmail): ?>
            <button type="button" onclick="toggleEdit('<?= $fname ?>')"
              class="text-xs font-bold text-purple-600 hover:text-purple-800 flex items-center gap-1 whitespace-nowrap">
              <i class="fa-solid fa-pen text-xs"></i> تعديل
            </button>
            <?php endif; ?>
          </div>
          <div id="edit_<?= $fname ?>" class="hidden py-3 border-b border-gray-50">
            <?php if ($fname === 'u_job'): ?>
            <!-- Gender field next -->
            <?php endif; ?>
            <input type="<?= $ftype ?>" name="<?= $fname ?>"
              value="<?= htmlspecialchars($fval) ?>"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 transition"
              <?= $ftype === 'tel' || $ftype === 'date' ? 'dir="ltr"' : '' ?>>
          </div>
          <?php endforeach; ?>

          <!-- Gender -->
          <div class="flex items-center justify-between py-4 border-b border-gray-50 gap-4">
            <span class="text-sm font-bold text-gray-500 w-28 flex-shrink-0">الجنس</span>
            <span class="flex-1 text-sm text-gray-800 font-semibold">
              <?= $user['u_gender'] === 'male' ? 'ذكر' : ($user['u_gender'] === 'female' ? 'أنثى' : '<span class="text-gray-300 font-normal">لم يتم التحديد</span>') ?>
            </span>
            <button type="button" onclick="toggleEdit('u_gender')"
              class="text-xs font-bold text-purple-600 hover:text-purple-800 flex items-center gap-1 whitespace-nowrap">
              <i class="fa-solid fa-pen text-xs"></i> تعديل
            </button>
          </div>
          <div id="edit_u_gender" class="hidden py-3 border-b border-gray-50">
            <select name="u_gender" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400">
              <option value="">اختر</option>
              <option value="male" <?= $user['u_gender']==='male'?'selected':'' ?>>ذكر</option>
              <option value="female" <?= $user['u_gender']==='female'?'selected':'' ?>>أنثى</option>
            </select>
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
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-black text-gray-800 text-lg mb-6">
          <i class="fa-solid fa-chart-bar text-purple-500 ml-2"></i> الإحصائيات
        </h2>
        <!-- Tabs within stats -->
        <div class="flex border-b border-gray-100 mb-6 gap-4">
          <button onclick="showStatsTab('personalities')" id="st-p"
            class="pb-3 text-sm font-bold border-b-2 border-purple-600 text-purple-600 transition">الشخصيات</button>
          <button onclick="showStatsTab('companies')" id="st-c"
            class="pb-3 text-sm font-bold border-b-2 border-transparent text-gray-400 transition">الشركات</button>
        </div>

        <div id="stats-personalities">
          <?php if (empty($my_personalities)): ?>
          <div class="text-center py-16 text-gray-300">
            <i class="fa-solid fa-pen-to-square text-5xl mb-4"></i>
            <p class="font-semibold text-gray-400">عفواً لا توجد شخصيات يتم إداراتها من قبلكم لعرضها.</p>
          </div>
          <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($my_personalities as $p): ?>
            <a href="profile.php?id=<?= $p['p_id'] ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition">
              <?php if (!empty($p['p_photo'])): ?>
                <img src="<?= htmlspecialchars($p['p_photo']) ?>" class="w-10 h-10 rounded-full object-cover border border-purple-100">
              <?php else: ?>
                <div class="w-10 h-10 rounded-full pi-gradient flex items-center justify-center flex-shrink-0">
                  <span class="text-white font-black text-sm"><?= mb_substr($p['p_name_ar'],0,1) ?></span>
                </div>
              <?php endif; ?>
              <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($p['p_name_ar']) ?>
                  <?php if ($p['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
                </p>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($p['p_title'] ?? '') ?></p>
              </div>
              <span class="text-xs text-gray-400"><i class="fa-solid fa-eye ml-1"></i><?= number_format($p['p_views']) ?></span>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <div id="stats-companies" class="hidden">
          <?php if (empty($my_institutions)): ?>
          <div class="text-center py-16 text-gray-300">
            <i class="fa-solid fa-pen-to-square text-5xl mb-4"></i>
            <p class="font-semibold text-gray-400">عفواً لا توجد شركات يتم إداراتها من قبلكم لعرضها.</p>
          </div>
          <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($my_institutions as $inst): ?>
            <a href="institution.php?id=<?= $inst['inst_id'] ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition">
              <?php if (!empty($inst['inst_logo'])): ?>
                <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" class="w-10 h-10 rounded-xl object-cover border border-gray-100">
              <?php else: ?>
                <div class="w-10 h-10 rounded-xl pi-gradient flex items-center justify-center flex-shrink-0">
                  <span class="text-white font-black text-sm"><?= mb_substr($inst['inst_name_ar'],0,1) ?></span>
                </div>
              <?php endif; ?>
              <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($inst['inst_name_ar']) ?>
                  <?php if ($inst['inst_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
                </p>
              </div>
              <span class="text-xs text-gray-400"><i class="fa-solid fa-eye ml-1"></i><?= number_format($inst['inst_views']) ?></span>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
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
        $sub_tabs = ['all'=>'الكل','verified'=>'موثقة','unverified'=>'غير موثقة','pending'=>'قيد التدقيق','rejected'=>'تم الرفض'];
        ?>
        <div class="flex gap-2 flex-wrap mb-4">
          <?php foreach ($sub_tabs as $sk => $sl): ?>
          <a href="account.php?tab=submissions&stype=<?= $sk ?>"
            class="px-3 py-1.5 text-xs font-bold rounded-lg transition <?= $sub_filter === $sk ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= $sl ?>
          </a>
          <?php endforeach; ?>
        </div>

        <?php
        $all_subs = array_merge(
            array_map(fn($p) => array_merge($p, ['_type'=>'personality']), $my_personalities),
            array_map(fn($i) => array_merge($i, ['_type'=>'institution']), $my_institutions)
        );
        if (empty($all_subs)):
        ?>
        <div class="text-center py-16 text-gray-300">
          <i class="fa-solid fa-pen-to-square text-5xl mb-4"></i>
          <p class="font-semibold text-gray-400">عفواً لا يوجد شخصيات أو شركات تم إضافتها</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($all_subs as $item):
            $is_p = $item['_type'] === 'personality';
            $name  = $is_p ? $item['p_name_ar']  : $item['inst_name_ar'];
            $photo = $is_p ? ($item['p_photo']??'') : ($item['inst_logo']??'');
            $link  = $is_p ? "profile.php?id={$item['p_id']}" : "institution.php?id={$item['inst_id']}";
            $active = $is_p ? $item['p_active'] : $item['inst_active'];
            $verified = $is_p ? $item['p_verified'] : $item['inst_verified'];
          ?>
          <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:bg-gray-50 transition">
            <?php if ($photo): ?>
              <img src="<?= htmlspecialchars($photo) ?>" class="w-10 h-10 rounded-xl object-cover border border-gray-100 flex-shrink-0">
            <?php else: ?>
              <div class="w-10 h-10 rounded-xl pi-gradient flex items-center justify-center flex-shrink-0">
                <span class="text-white font-black text-sm"><?= mb_substr($name,0,1) ?></span>
              </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($name) ?></p>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="text-xs px-2 py-0.5 rounded-full <?= $is_p ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' ?> font-bold">
                  <?= $is_p ? 'شخصية' : 'شركة' ?>
                </span>
                <?php if ($verified): ?>
                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-bold">موثقة</span>
                <?php elseif ($active): ?>
                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-bold">نشطة</span>
                <?php else: ?>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-bold">قيد المراجعة</span>
                <?php endif; ?>
              </div>
            </div>
            <a href="<?= $link ?>" class="text-xs font-bold text-purple-600 hover:text-purple-800 whitespace-nowrap">
              عرض <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ──────────── ACCOUNTS TAB ──────────── -->
      <?php elseif ($tab === 'accounts'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-black text-gray-800 text-lg mb-6">
          <i class="fa-solid fa-gear text-purple-500 ml-2"></i> إدارة الحسابات
        </h2>
        <div class="text-center py-16 text-gray-300">
          <i class="fa-solid fa-user-gear text-5xl mb-4"></i>
          <p class="font-semibold text-gray-400">عفواً لا يوجد حسابات مدارة</p>
        </div>
      </div>

      <!-- ──────────── MEMBERSHIP TAB ──────────── -->
      <?php elseif ($tab === 'membership'): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-black text-gray-800 text-lg mb-6">
          <i class="fa-solid fa-gem text-purple-500 ml-2"></i> اشتراكاتي
        </h2>
        <div class="rounded-2xl p-6 border-2 <?= $user['u_plan'] !== 'free' ? 'border-purple-200 bg-purple-50' : 'border-gray-100 bg-gray-50' ?>">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 rounded-xl pi-gradient flex items-center justify-center">
              <i class="fa-solid <?= $user['u_plan'] === 'executive' ? 'fa-crown' : ($user['u_plan'] === 'verified' ? 'fa-circle-check' : 'fa-star') ?> text-white text-lg"></i>
            </div>
            <div>
              <p class="font-black text-gray-800">
                <?= $user['u_plan'] === 'executive' ? 'باقة الرؤساء التنفيذيين' : ($user['u_plan'] === 'verified' ? 'العضوية الموثقة' : 'الخطة المجانية') ?>
              </p>
              <p class="text-gray-400 text-sm"><?= $user['u_plan'] === 'free' ? 'ترقية للاستفادة من مميزات التوثيق' : 'اشتراك فعّال' ?></p>
            </div>
          </div>
          <?php if ($user['u_plan'] === 'free'): ?>
          <div class="flex gap-3">
            <a href="membership.php?type=verified"
              class="px-5 py-2.5 pi-primary-bg text-white font-black rounded-xl hover:opacity-90 transition text-sm">
              ترقية للتوثيق
            </a>
            <a href="membership.php?type=executive"
              class="px-5 py-2.5 text-amber-900 font-black rounded-xl hover:brightness-110 transition text-sm"
              style="background:linear-gradient(135deg,#fde68a,#f59e0b)">
              <i class="fa-solid fa-crown ml-1"></i> رئيس تنفيذي
            </a>
          </div>
          <?php endif; ?>
        </div>
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

<script>
function toggleEdit(field) {
  var el = document.getElementById('edit_' + field);
  if (el) el.classList.toggle('hidden');
}
function showStatsTab(which) {
  document.getElementById('stats-personalities').classList.toggle('hidden', which !== 'personalities');
  document.getElementById('stats-companies').classList.toggle('hidden', which !== 'companies');
  document.getElementById('st-p').className = 'pb-3 text-sm font-bold border-b-2 ' + (which==='personalities' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-400') + ' transition';
  document.getElementById('st-c').className = 'pb-3 text-sm font-bold border-b-2 ' + (which==='companies' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-400') + ' transition';
}
<?php if (isset($_GET['sent'])): ?>
document.addEventListener('DOMContentLoaded', function(){
  document.getElementById('new-complaint-form').classList.remove('hidden');
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
