<?php
pi_require_login();
pi_require_any_perm('manage_users','view_users');

$msg = '';
$msg_type = 'green';

// ── POST actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $uid = (int)($_POST['uid'] ?? 0);

    if ($act === 'block' && $uid) {
        $mysqli->query("UPDATE pi_users SET u_active=0 WHERE u_id=$uid");
        $msg = 'تم حظر المستخدم';
        $msg_type = 'red';
    }
    if ($act === 'unblock' && $uid) {
        $mysqli->query("UPDATE pi_users SET u_active=1 WHERE u_id=$uid");
        $msg = 'تم رفع الحظر عن المستخدم';
    }
    if ($act === 'delete' && $uid) {
        $mysqli->query("DELETE FROM pi_users WHERE u_id=$uid");
        $msg = 'تم حذف الحساب نهائياً';
        $msg_type = 'red';
    }
    if ($act === 'change_plan' && $uid) {
        $plan = in_array($_POST['plan'] ?? '', ['free','verified','executive']) ? pi_escape($_POST['plan']) : 'free';
        $mysqli->query("UPDATE pi_users SET u_plan='$plan' WHERE u_id=$uid");
        $msg = 'تم تحديث خطة الاشتراك';
    }
    if ($act === 'edit_user' && $uid) {
        $name  = pi_escape(trim($_POST['u_name']  ?? ''));
        $email = pi_escape(trim($_POST['u_email'] ?? ''));
        $phone = pi_escape(trim($_POST['u_phone'] ?? ''));
        $job   = pi_escape(trim($_POST['u_job']   ?? ''));
        $mysqli->query("UPDATE pi_users SET u_name='$name',u_email='$email',u_phone='$phone',u_job='$job' WHERE u_id=$uid");
        $msg = 'تم حفظ بيانات المستخدم';
    }
    if ($act === 'reset_password' && $uid) {
        $np = trim($_POST['new_password'] ?? '');
        if (strlen($np) >= 6) {
            $hash = pi_escape(password_hash($np, PASSWORD_DEFAULT));
            $mysqli->query("UPDATE pi_users SET u_password='$hash' WHERE u_id=$uid");
            $msg = 'تم تغيير كلمة المرور بنجاح';
        } else {
            $msg = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
            $msg_type = 'red';
        }
    }
    // Link a personality/institution to this user
    if ($act === 'link_page' && $uid) {
        $etype = ($_POST['etype'] ?? '') === 'institution' ? 'institution' : 'personality';
        $eid   = (int)($_POST['eid'] ?? 0);
        if ($eid) {
            if ($etype === 'personality') {
                $mysqli->query("UPDATE pi_personalities SET p_added_by_user=$uid WHERE p_id=$eid");
            } else {
                $mysqli->query("UPDATE pi_institutions SET inst_added_by_user=$uid WHERE inst_id=$eid");
            }
            $msg = 'تم ربط الصفحة بالمستخدم';
        }
    }
    // Unlink a personality/institution from this user
    if ($act === 'unlink_page' && $uid) {
        $etype = ($_POST['etype'] ?? '') === 'institution' ? 'institution' : 'personality';
        $eid   = (int)($_POST['eid'] ?? 0);
        if ($eid) {
            if ($etype === 'personality') {
                $mysqli->query("UPDATE pi_personalities SET p_added_by_user=NULL WHERE p_id=$eid AND p_added_by_user=$uid");
            } else {
                $mysqli->query("UPDATE pi_institutions SET inst_added_by_user=NULL WHERE inst_id=$eid AND inst_added_by_user=$uid");
            }
            $msg = 'تم إلغاء ربط الصفحة';
            $msg_type = 'red';
        }
    }
}

// ── Filters ─────────────────────────────────────────────────────────────────
$search      = pi_escape(trim($_GET['q']    ?? ''));
$filter_plan = $_GET['plan']   ?? '';
$filter_stat = $_GET['status'] ?? '';
$view_uid    = (int)($_GET['view'] ?? 0);

$where = "WHERE 1";
if ($search)      $where .= " AND (u_name LIKE '%$search%' OR u_email LIKE '%$search%' OR u_phone LIKE '%$search%')";
if ($filter_plan) $where .= " AND u_plan='" . pi_escape($filter_plan) . "'";
if ($filter_stat === 'active')  $where .= " AND u_active=1";
if ($filter_stat === 'blocked') $where .= " AND u_active=0";

$per_page = 20;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total_r = $mysqli->query("SELECT COUNT(*) c FROM pi_users $where");
$total   = $total_r ? (int)$total_r->fetch_assoc()['c'] : 0;
$total_pages = max(1, ceil($total / $per_page));

$users = [];
$r = $mysqli->query("SELECT * FROM pi_users $where ORDER BY u_id DESC LIMIT $per_page OFFSET $offset");
if ($r) while ($row = $r->fetch_assoc()) $users[] = $row;

// Stats
$stats = [];
foreach (['total'  => 'SELECT COUNT(*) c FROM pi_users',
          'active' => 'SELECT COUNT(*) c FROM pi_users WHERE u_active=1',
          'blocked'=> 'SELECT COUNT(*) c FROM pi_users WHERE u_active=0',
          'new7'   => "SELECT COUNT(*) c FROM pi_users WHERE u_created >= DATE_SUB(NOW(),INTERVAL 7 DAY)"] as $k=>$q) {
    $sr = $mysqli->query($q);
    $stats[$k] = $sr ? (int)$sr->fetch_assoc()['c'] : 0;
}

// Single view
$view_user = null;
$view_subs = [];
$view_cmps = [];
$view_personalities = [];
$view_institutions  = [];
$view_sponsors      = [];
if ($view_uid) {
    $vr = $mysqli->query("SELECT * FROM pi_users WHERE u_id=$view_uid");
    if ($vr && $vr->num_rows) {
        $view_user = $vr->fetch_assoc();
        // Personalities managed by this user
        $vpr = $mysqli->query("SELECT p_id,p_name_ar,p_title,p_photo,p_verified,p_membership_type,p_active FROM pi_personalities WHERE p_added_by_user=$view_uid ORDER BY p_id DESC");
        if ($vpr) while ($row=$vpr->fetch_assoc()) $view_personalities[] = $row;
        // Institutions managed by this user
        $vir = $mysqli->query("SELECT inst_id,inst_name_ar,inst_logo,inst_verified,inst_membership_type,inst_active FROM pi_institutions WHERE inst_added_by_user=$view_uid ORDER BY inst_id DESC");
        if ($vir) while ($row=$vir->fetch_assoc()) $view_institutions[] = $row;
        // All personalities (for link dropdown - exclude already linked to this user)
        $linked_p_ids = array_column($view_personalities, 'p_id');
        $all_personalities_flat = [];
        $apr = $mysqli->query("SELECT p_id,p_name_ar,p_title FROM pi_personalities WHERE p_active=1 ORDER BY p_name_ar ASC LIMIT 500");
        if ($apr) while ($row=$apr->fetch_assoc()) $all_personalities_flat[] = $row;
        // All institutions (for link dropdown)
        $all_institutions_flat = [];
        $air = $mysqli->query("SELECT inst_id,inst_name_ar FROM pi_institutions WHERE inst_active=1 ORDER BY inst_name_ar ASC LIMIT 500");
        if ($air) while ($row=$air->fetch_assoc()) $all_institutions_flat[] = $row;
        $sr = $mysqli->query("SELECT sub_id,sub_type,sub_status,sub_created,sub_data FROM pi_submissions WHERE sub_user_id=$view_uid ORDER BY sub_created DESC LIMIT 20");
        if ($sr) while ($row=$sr->fetch_assoc()) $view_subs[] = $row;
        $view_edit_reqs = [];
        $er = $mysqli->query("SELECT er.*,
          CASE WHEN er.er_entity_type='personality' THEN (SELECT p_name_ar FROM pi_personalities WHERE p_id=er.er_entity_id)
               ELSE (SELECT inst_name_ar FROM pi_institutions WHERE inst_id=er.er_entity_id) END AS entity_name
          FROM pi_edit_requests er WHERE er.er_user_id=$view_uid AND er.er_req_type='edit' ORDER BY er.er_created DESC LIMIT 20");
        if ($er) while ($row=$er->fetch_assoc()) $view_edit_reqs[] = $row;
        $cr = $mysqli->query("SELECT cmp_id,cmp_type,cmp_subject,cmp_status,cmp_created FROM pi_complaints WHERE cmp_user_id=$view_uid ORDER BY cmp_created DESC LIMIT 20");
        if ($cr) while ($row=$cr->fetch_assoc()) $view_cmps[] = $row;
        // Sponsors linked to this user
        $view_sponsors = [];
        $spqr = $mysqli->query("SELECT sp_id,sp_name,sp_logo,sp_url FROM pi_sponsors WHERE sp_user_id=$view_uid ORDER BY sp_order,sp_id");
        if ($spqr) while ($row=$spqr->fetch_assoc()) $view_sponsors[] = $row;
    }
}

$status_map = ['new'=>['text'=>'جديدة','class'=>'bg-yellow-100 text-yellow-800'],
               'read'=>['text'=>'مقروءة','class'=>'bg-blue-100 text-blue-800'],
               'resolved'=>['text'=>'محلولة','class'=>'bg-green-100 text-green-800']];
$sub_status = ['pending'=>['text'=>'قيد المراجعة','class'=>'bg-yellow-100 text-yellow-700'],
               'approved'=>['text'=>'مقبول','class'=>'bg-green-100 text-green-700'],
               'rejected'=>['text'=>'مرفوض','class'=>'bg-red-100 text-red-700']];
?>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-black text-gray-800">إدارة المستخدمين</h1>
    <span class="bg-purple-100 text-purple-700 text-sm font-black px-3 py-1 rounded-full"><?= number_format($stats['total']) ?> مستخدم</span>
  </div>

  <?php if ($msg): ?>
  <div class="bg-<?= $msg_type ?>-50 border border-<?= $msg_type ?>-200 rounded-xl p-3 text-<?= $msg_type ?>-700 text-sm font-semibold">
    <i class="fa-solid fa-circle-check ml-2"></i><?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <?php
    $stat_cards = [
      ['label'=>'إجمالي','val'=>$stats['total'],'color'=>'purple','icon'=>'users'],
      ['label'=>'نشطون','val'=>$stats['active'],'color'=>'green','icon'=>'circle-check'],
      ['label'=>'محظورون','val'=>$stats['blocked'],'color'=>'red','icon'=>'ban'],
      ['label'=>'جدد (7 أيام)','val'=>$stats['new7'],'color'=>'blue','icon'=>'user-plus'],
    ];
    foreach ($stat_cards as $sc): ?>
    <div class="bg-<?= $sc['color'] ?>-50 border border-<?= $sc['color'] ?>-200 rounded-2xl p-4 text-center">
      <p class="text-2xl font-black text-<?= $sc['color'] ?>-700"><?= $sc['val'] ?></p>
      <p class="text-<?= $sc['color'] ?>-600 text-xs font-semibold mt-1"><?= $sc['label'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($view_user): ?>
  <?php
  // Pre-compute counts for KPI cards
  $upgrade_reqs_count = 0;
  foreach ($view_edit_reqs as $er) { if ($er['er_req_type'] === 'upgrade') $upgrade_reqs_count++; }
  $edit_reqs_count = count($view_edit_reqs) - $upgrade_reqs_count;
  $linked_total = count($view_personalities) + count($view_institutions);
  $is_active = (bool)$view_user['u_active'];
  $pending_items = 0;
  foreach ($view_subs as $vs) { if ($vs['sub_status']==='pending') $pending_items++; }
  foreach ($view_edit_reqs as $er) { if ($er['er_status']==='pending') $pending_items++; }
  ?>

  <!-- ══════════════════════════════════════════════════ -->
  <!--  HERO SECTION                                      -->
  <!-- ══════════════════════════════════════════════════ -->
  <!-- User card header -->
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-4">
    <!-- Purple top accent bar -->
    <div class="h-1.5 w-full" style="background:linear-gradient(90deg,#7c3aed,#a855f7)"></div>

    <!-- Navigation row -->
    <div class="px-6 pt-4 pb-0 flex items-center justify-between">
      <a href="admin.php?p=users" class="flex items-center gap-2 text-gray-400 hover:text-purple-600 transition text-sm font-semibold">
        <i class="fa-solid fa-arrow-right text-xs"></i> العودة للقائمة
      </a>
      <span class="text-gray-400 text-xs font-mono bg-gray-100 px-2 py-0.5 rounded-full">ID #<?= $view_user['u_id'] ?></span>
    </div>

    <!-- Main content -->
    <div class="px-6 py-5 flex flex-col md:flex-row md:items-center gap-5">
      <!-- Avatar -->
      <div class="relative flex-shrink-0">
        <?php if ($view_user['u_photo']): ?>
        <img src="../<?= htmlspecialchars($view_user['u_photo']) ?>" class="w-20 h-20 rounded-2xl object-cover border border-gray-200">
        <?php else: ?>
        <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-3xl font-black text-white" style="background:linear-gradient(135deg,#7c3aed,#a855f7)">
          <?= mb_substr($view_user['u_name'], 0, 1) ?>
        </div>
        <?php endif; ?>
        <span class="absolute -bottom-1 -left-1 w-4 h-4 rounded-full border-2 border-white <?= $is_active ? 'bg-emerald-400' : 'bg-red-400' ?>"></span>
      </div>

      <!-- Identity -->
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-3 flex-wrap mb-1">
          <h2 class="text-gray-900 font-black text-2xl"><?= htmlspecialchars($view_user['u_name']) ?></h2>
          <?php if ($is_active): ?>
          <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
            <i class="fa-solid fa-circle text-[7px] ml-1"></i>نشط
          </span>
          <?php else: ?>
          <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-red-50 text-red-700 border border-red-200">
            <i class="fa-solid fa-ban text-xs ml-1"></i>محظور
          </span>
          <?php endif; ?>
          <?php if ($pending_items > 0): ?>
          <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200">
            <i class="fa-solid fa-clock text-xs ml-1"></i><?= $pending_items ?> بانتظار المراجعة
          </span>
          <?php endif; ?>
        </div>
        <p class="text-gray-500 text-sm mb-2" dir="ltr"><?= htmlspecialchars($view_user['u_email']) ?></p>
        <div class="flex flex-wrap gap-4 text-xs text-gray-400">
          <span><i class="fa-solid fa-calendar-plus ml-1"></i>سُجِّل <?= date('d/m/Y', strtotime($view_user['u_created'])) ?></span>
          <?php if ($view_user['u_phone']): ?><span><i class="fa-solid fa-phone ml-1"></i><?= htmlspecialchars($view_user['u_phone']) ?></span><?php endif; ?>
          <?php if ($view_user['u_job']): ?><span><i class="fa-solid fa-briefcase ml-1"></i><?= htmlspecialchars($view_user['u_job']) ?></span><?php endif; ?>
          <?php if ($view_user['u_nationality']): ?><span><i class="fa-solid fa-flag ml-1"></i><?= htmlspecialchars($view_user['u_nationality']) ?></span><?php endif; ?>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="flex flex-wrap gap-2 flex-shrink-0">
        <a href="admin.php?p=users&impersonate=<?= $view_user['u_id'] ?>"
           onclick="return confirm('ستدخل للمنصة كـ <?= htmlspecialchars(addslashes($view_user['u_name'])) ?>. تأكيد؟')"
           class="flex items-center gap-2 px-4 py-2.5 text-xs font-black rounded-xl bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200 transition">
          <i class="fa-solid fa-right-to-bracket"></i> دخول كـ المستخدم
        </a>
        <button onclick="document.getElementById('edit-user-panel').classList.toggle('hidden')"
          class="flex items-center gap-2 px-4 py-2.5 text-xs font-black rounded-xl bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 transition">
          <i class="fa-solid fa-pen"></i> تعديل
        </button>
        <?php if ($is_active): ?>
        <form method="POST" class="inline">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <button name="act" value="block"
            onclick="return confirm('هل تريد حظر هذا المستخدم؟')"
            class="flex items-center gap-2 px-4 py-2.5 text-xs font-black rounded-xl bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition">
            <i class="fa-solid fa-ban"></i> حظر
          </button>
        </form>
        <?php else: ?>
        <form method="POST" class="inline">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <button name="act" value="unblock"
            class="flex items-center gap-2 px-4 py-2.5 text-xs font-black rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition">
            <i class="fa-solid fa-circle-check"></i> رفع الحظر
          </button>
        </form>
        <?php endif; ?>
        <form method="POST" class="inline"
          onsubmit="return confirm('سيتم حذف حساب <?= htmlspecialchars(addslashes($view_user['u_name'])) ?> بشكل دائم. هل أنت متأكد؟')">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <button name="act" value="delete"
            class="flex items-center gap-2 px-4 py-2.5 text-xs font-black rounded-xl bg-red-600 text-white hover:bg-red-700 transition">
            <i class="fa-solid fa-trash"></i> حذف
          </button>
        </form>
      </div>
    </div>

    <!-- KPI Bar -->
    <div class="grid grid-cols-3 md:grid-cols-5 border-t border-gray-100">
      <?php
      $kpis = [
        ['label'=>'الاقتراحات',       'val'=>count($view_subs),  'icon'=>'fa-inbox',         'color'=>'text-violet-600', 'bg'=>'bg-violet-50'],
        ['label'=>'طلبات التعديل',    'val'=>$edit_reqs_count,   'icon'=>'fa-pen-to-square', 'color'=>'text-amber-600',  'bg'=>'bg-amber-50'],
        ['label'=>'الشكاوى',          'val'=>count($view_cmps),  'icon'=>'fa-message',       'color'=>'text-sky-600',    'bg'=>'bg-sky-50'],
        ['label'=>'الصفحات المرتبطة', 'val'=>$linked_total,      'icon'=>'fa-id-card',       'color'=>'text-emerald-600','bg'=>'bg-emerald-50'],
        ['label'=>'معلق للمراجعة',    'val'=>$pending_items,     'icon'=>'fa-clock',         'color'=>$pending_items?'text-red-600':'text-gray-400', 'bg'=>$pending_items?'bg-red-50':'bg-gray-50'],
      ];
      foreach ($kpis as $kpi): ?>
      <div class="flex flex-col items-center justify-center py-4 px-2 border-r border-gray-100 last:border-0 <?= $kpi['bg'] ?>">
        <i class="fa-solid <?= $kpi['icon'] ?> <?= $kpi['color'] ?> text-base mb-1.5"></i>
        <p class="text-gray-800 font-black text-xl leading-none mb-1"><?= $kpi['val'] ?></p>
        <p class="text-gray-400 text-[10px] font-semibold text-center"><?= $kpi['label'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Edit panel (hidden by default, toggled by button) -->
  <div id="edit-user-panel" class="hidden">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Edit info -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-purple-100 flex items-center justify-center">
            <i class="fa-solid fa-pen text-purple-600 text-sm"></i>
          </div>
          <p class="font-black text-gray-800 text-sm">تعديل البيانات</p>
        </div>
        <form method="POST" class="p-5 space-y-4">
          <input type="hidden" name="act" value="edit_user">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1.5">الاسم الكامل</label>
              <input type="text" name="u_name" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100 transition" value="<?= htmlspecialchars($view_user['u_name']) ?>">
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1.5">البريد الإلكتروني</label>
              <input type="email" name="u_email" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100 transition" dir="ltr" value="<?= htmlspecialchars($view_user['u_email']) ?>">
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1.5">رقم الهاتف</label>
              <input type="text" name="u_phone" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100 transition" value="<?= htmlspecialchars($view_user['u_phone'] ?? '') ?>">
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-500 mb-1.5">الوظيفة</label>
              <input type="text" name="u_job" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100 transition" value="<?= htmlspecialchars($view_user['u_job'] ?? '') ?>">
            </div>
          </div>
          <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-black text-white transition hover:opacity-90" style="background:linear-gradient(135deg,#7c3aed,#4f46e5)">
            <i class="fa-solid fa-floppy-disk ml-2"></i>حفظ التعديلات
          </button>
        </form>
      </div>
      <!-- Change password -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-amber-100 flex items-center justify-center">
            <i class="fa-solid fa-key text-amber-600 text-sm"></i>
          </div>
          <p class="font-black text-gray-800 text-sm">تغيير كلمة المرور</p>
        </div>
        <form method="POST" class="p-5 space-y-4">
          <input type="hidden" name="act" value="reset_password">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <div>
            <label class="block text-xs font-bold text-gray-500 mb-1.5">كلمة المرور الجديدة</label>
            <input type="text" name="new_password" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition font-mono" placeholder="6 أحرف على الأقل" dir="ltr">
          </div>
          <p class="text-xs text-gray-400"><i class="fa-solid fa-triangle-exclamation text-amber-500 ml-1"></i>هذه العملية لا يمكن التراجع عنها</p>
          <button type="submit" onclick="return confirm('تغيير كلمة المرور؟')" class="px-5 py-2.5 rounded-xl text-sm font-black text-white transition hover:opacity-90" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <i class="fa-solid fa-key ml-2"></i>تغيير كلمة المرور
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════ -->
  <!--  MAIN CONTENT GRID: Account Mgmt + Activity        -->
  <!-- ══════════════════════════════════════════════════ -->
  <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">

    <!-- LEFT: Account Management (2/5) -->
    <div class="xl:col-span-2 space-y-4">

      <!-- Linked Pages -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-indigo-100 flex items-center justify-center">
              <i class="fa-solid fa-id-card text-indigo-600 text-sm"></i>
            </div>
            <div>
              <p class="font-black text-gray-800 text-sm">الصفحات المرتبطة</p>
              <p class="text-gray-400 text-xs"><?= $linked_total ?> صفحة مرتبطة</p>
            </div>
          </div>
          <button onclick="document.getElementById('link-page-form-<?= $view_uid ?>').classList.toggle('hidden')"
            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-black text-white rounded-xl transition hover:opacity-90" style="background:linear-gradient(135deg,#7c3aed,#4f46e5)">
            <i class="fa-solid fa-plus text-[10px]"></i>ربط صفحة
          </button>
        </div>

        <!-- Link form -->
        <div id="link-page-form-<?= $view_uid ?>" class="hidden border-b border-gray-100 p-4 bg-violet-50/60">
          <form method="POST" class="space-y-3">
            <input type="hidden" name="uid" value="<?= $view_uid ?>">
            <input type="hidden" name="act" value="link_page">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">النوع</label>
                <select name="etype" id="link-etype-<?= $view_uid ?>" onchange="updateLinkSelect(<?= $view_uid ?>)"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white">
                  <option value="personality">شخصية</option>
                  <option value="institution">مؤسسة</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">بحث</label>
                <input type="text" id="link-search-<?= $view_uid ?>" placeholder="فلترة..."
                  oninput="filterLinkOptions(<?= $view_uid ?>)"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white">
              </div>
            </div>
            <div>
              <?php
              $suggested_p_ids = [];
              $suggested_i_ids = [];
              $sr2 = $mysqli->query("SELECT sub_type,sub_data FROM pi_submissions WHERE sub_user_id=$view_uid AND sub_status='approved' ORDER BY sub_id DESC LIMIT 50");
              if ($sr2) while ($srow=$sr2->fetch_assoc()) {
                  $sd = json_decode($srow['sub_data'] ?? '{}', true);
                  $sname = $srow['sub_type']==='personality' ? ($sd['p_name_ar'] ?? '') : ($sd['inst_name_ar'] ?? '');
                  if ($srow['sub_type']==='personality' && $sname) {
                      $sp = $mysqli->query("SELECT p_id FROM pi_personalities WHERE p_name_ar='".pi_escape($sname)."' AND p_active=1 LIMIT 1");
                      if ($sp && $sp->num_rows) $suggested_p_ids[] = (int)$sp->fetch_assoc()['p_id'];
                  } else if ($sname) {
                      $si = $mysqli->query("SELECT inst_id FROM pi_institutions WHERE inst_name_ar='".pi_escape($sname)."' AND inst_active=1 LIMIT 1");
                      if ($si && $si->num_rows) $suggested_i_ids[] = (int)$si->fetch_assoc()['inst_id'];
                  }
              }
              ?>
              <select name="eid" id="link-eid-p-<?= $view_uid ?>"
                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white">
                <?php if (!empty($suggested_p_ids)): ?>
                <optgroup label="── اقترحها هذا المستخدم ──">
                  <?php foreach ($all_personalities_flat as $ap): if (!in_array($ap['p_id'], $suggested_p_ids)) continue; ?>
                  <option value="<?= $ap['p_id'] ?>">★ <?= htmlspecialchars($ap['p_name_ar']) ?><?= $ap['p_title'] ? ' — '.htmlspecialchars($ap['p_title']) : '' ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="── باقي الشخصيات ──">
                <?php else: ?>
                <optgroup label="── الشخصيات ──">
                <?php endif; ?>
                  <?php foreach ($all_personalities_flat as $ap): if (in_array($ap['p_id'], $suggested_p_ids)) continue; ?>
                  <option value="<?= $ap['p_id'] ?>"><?= htmlspecialchars($ap['p_name_ar']) ?><?= $ap['p_title'] ? ' — '.htmlspecialchars($ap['p_title']) : '' ?></option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
              <select name="eid" id="link-eid-i-<?= $view_uid ?>" class="hidden w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white">
                <?php if (!empty($suggested_i_ids)): ?>
                <optgroup label="── اقترحها هذا المستخدم ──">
                  <?php foreach ($all_institutions_flat as $ai): if (!in_array($ai['inst_id'], $suggested_i_ids)) continue; ?>
                  <option value="<?= $ai['inst_id'] ?>">★ <?= htmlspecialchars($ai['inst_name_ar']) ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="── باقي المؤسسات ──">
                <?php else: ?>
                <optgroup label="── المؤسسات ──">
                <?php endif; ?>
                  <?php foreach ($all_institutions_flat as $ai): if (in_array($ai['inst_id'], $suggested_i_ids)) continue; ?>
                  <option value="<?= $ai['inst_id'] ?>"><?= htmlspecialchars($ai['inst_name_ar']) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </div>
            <div class="flex items-center justify-between">
              <p class="text-xs text-gray-400">★ = اقترحها المستخدم سابقاً</p>
              <button type="submit" class="px-4 py-2 text-xs font-black text-white rounded-xl transition hover:opacity-90" style="background:linear-gradient(135deg,#7c3aed,#4f46e5)">
                <i class="fa-solid fa-link ml-1"></i>ربط
              </button>
            </div>
          </form>
        </div>

        <!-- Pages list -->
        <div class="divide-y divide-gray-50">
          <?php if (empty($view_personalities) && empty($view_institutions)): ?>
          <div class="py-10 text-center">
            <i class="fa-solid fa-id-card text-4xl text-gray-200 block mb-3"></i>
            <p class="text-gray-400 text-sm">لا توجد صفحات مرتبطة بعد</p>
          </div>
          <?php endif; ?>
          <?php foreach ($view_personalities as $vp):
            $mem = $vp['p_membership_type'];
            if ($mem==='executive') { $mbadge='تنفيذي'; $mcls='bg-amber-100 text-amber-700 border-amber-200'; }
            elseif ($mem==='verified'||$vp['p_verified']) { $mbadge='موثق'; $mcls='bg-blue-100 text-blue-700 border-blue-200'; }
            else { $mbadge='غير موثق'; $mcls='bg-gray-100 text-gray-500 border-gray-200'; }
          ?>
          <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition">
            <?php if ($vp['p_photo']): ?><img src="../<?= htmlspecialchars($vp['p_photo']) ?>" class="w-9 h-9 rounded-full object-cover flex-shrink-0 border border-gray-100">
            <?php else: ?><div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-user text-purple-500 text-xs"></i></div><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($vp['p_name_ar']) ?></p>
              <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($vp['p_title'] ?? '') ?></p>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
              <span class="text-[10px] px-2 py-0.5 rounded-full font-bold border <?= $mcls ?>"><?= $mbadge ?></span>
              <form method="POST" class="inline">
                <input type="hidden" name="uid" value="<?= $view_uid ?>">
                <input type="hidden" name="act" value="unlink_page">
                <input type="hidden" name="etype" value="personality">
                <input type="hidden" name="eid" value="<?= $vp['p_id'] ?>">
                <button type="submit" onclick="return confirm('إلغاء ربط هذه الصفحة؟')" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition" title="إلغاء الربط">
                  <i class="fa-solid fa-link-slash text-xs"></i>
                </button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
          <?php foreach ($view_institutions as $vi):
            $mem = $vi['inst_membership_type'];
            if ($mem==='executive') { $mbadge='تنفيذي'; $mcls='bg-amber-100 text-amber-700 border-amber-200'; }
            elseif ($mem==='verified'||$vi['inst_verified']) { $mbadge='موثقة'; $mcls='bg-blue-100 text-blue-700 border-blue-200'; }
            else { $mbadge='غير موثقة'; $mcls='bg-gray-100 text-gray-500 border-gray-200'; }
          ?>
          <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition">
            <?php if ($vi['inst_logo']): ?><img src="../<?= htmlspecialchars($vi['inst_logo']) ?>" class="w-9 h-9 rounded-xl object-cover flex-shrink-0 border border-gray-100">
            <?php else: ?><div class="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-building text-indigo-500 text-xs"></i></div><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($vi['inst_name_ar']) ?></p>
              <p class="text-xs text-indigo-400 font-semibold">مؤسسة</p>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
              <span class="text-[10px] px-2 py-0.5 rounded-full font-bold border <?= $mcls ?>"><?= $mbadge ?></span>
              <form method="POST" class="inline">
                <input type="hidden" name="uid" value="<?= $view_uid ?>">
                <input type="hidden" name="act" value="unlink_page">
                <input type="hidden" name="etype" value="institution">
                <input type="hidden" name="eid" value="<?= $vi['inst_id'] ?>">
                <button type="submit" onclick="return confirm('إلغاء ربط هذه الصفحة؟')" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition" title="إلغاء الربط">
                  <i class="fa-solid fa-link-slash text-xs"></i>
                </button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- User Info Card -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center">
            <i class="fa-solid fa-circle-info text-slate-500 text-sm"></i>
          </div>
          <p class="font-black text-gray-800 text-sm">معلومات الحساب</p>
        </div>
        <div class="p-5 space-y-3">
          <?php
          $info_rows = [
            ['icon'=>'fa-hashtag',      'label'=>'User ID',           'val'=>'#'.$view_user['u_id'],                    'mono'=>true],
            ['icon'=>'fa-envelope',     'label'=>'البريد الإلكتروني', 'val'=>$view_user['u_email'],                     'mono'=>true],
            ['icon'=>'fa-phone',        'label'=>'الهاتف',            'val'=>$view_user['u_phone']??'—',                'mono'=>false],
            ['icon'=>'fa-briefcase',    'label'=>'الوظيفة',           'val'=>$view_user['u_job']??'—',                  'mono'=>false],
            ['icon'=>'fa-flag',         'label'=>'الجنسية',           'val'=>$view_user['u_nationality']??'—',          'mono'=>false],
            ['icon'=>'fa-building',     'label'=>'الشركة',            'val'=>$view_user['u_company']??'—',              'mono'=>false],
            ['icon'=>'fa-venus-mars',   'label'=>'الجنس',             'val'=>($view_user['u_gender']==='male'?'ذكر':($view_user['u_gender']==='female'?'أنثى':'—')), 'mono'=>false],
            ['icon'=>'fa-calendar',     'label'=>'تاريخ التسجيل',    'val'=>date('Y/m/d', strtotime($view_user['u_created'])), 'mono'=>false],
          ];
          foreach ($info_rows as $row): ?>
          <div class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid <?= $row['icon'] ?> text-gray-400 text-xs"></i>
            </div>
            <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
              <span class="text-xs text-gray-400 flex-shrink-0"><?= $row['label'] ?></span>
              <span class="text-sm font-semibold text-gray-700 truncate <?= $row['mono'] ? 'font-mono text-xs' : '' ?>"><?= htmlspecialchars($row['val']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: Activity Center (3/5) -->
    <div class="xl:col-span-3">
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden" x-data="{tab:'subs'}">

        <!-- Tab bar -->
        <div class="flex border-b border-gray-100 overflow-x-auto">
          <button @click="tab='subs'"
            :class="tab==='subs' ? 'border-b-2 border-purple-600 text-purple-700 bg-purple-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
            class="flex items-center gap-2 px-5 py-4 text-sm font-bold whitespace-nowrap transition border-b-2 border-transparent">
            <i class="fa-solid fa-inbox text-xs"></i>
            الاقتراحات
            <?php if (count($view_subs)): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-black"
              :class="tab==='subs' ? 'bg-purple-200 text-purple-800' : 'bg-gray-200 text-gray-600'">
              <?= count($view_subs) ?>
            </span>
            <?php endif; ?>
          </button>
          <button @click="tab='edits'"
            :class="tab==='edits' ? 'border-b-2 border-purple-600 text-purple-700 bg-purple-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
            class="flex items-center gap-2 px-5 py-4 text-sm font-bold whitespace-nowrap transition border-b-2 border-transparent">
            <i class="fa-solid fa-pen-to-square text-xs"></i>
            طلبات التعديل
            <?php if ($edit_reqs_count): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-black"
              :class="tab==='edits' ? 'bg-purple-200 text-purple-800' : 'bg-gray-200 text-gray-600'">
              <?= $edit_reqs_count ?>
            </span>
            <?php endif; ?>
          </button>
          <button @click="tab='cmps'"
            :class="tab==='cmps' ? 'border-b-2 border-purple-600 text-purple-700 bg-purple-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
            class="flex items-center gap-2 px-5 py-4 text-sm font-bold whitespace-nowrap transition border-b-2 border-transparent">
            <i class="fa-solid fa-message text-xs"></i>
            الشكاوى
            <?php if (count($view_cmps)): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-black"
              :class="tab==='cmps' ? 'bg-purple-200 text-purple-800' : 'bg-gray-200 text-gray-600'">
              <?= count($view_cmps) ?>
            </span>
            <?php endif; ?>
          </button>
          <?php if (!empty($view_sponsors)): ?>
          <button @click="tab='sponsors'"
            :class="tab==='sponsors' ? 'border-b-2 border-purple-600 text-purple-700 bg-purple-50/50' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
            class="flex items-center gap-2 px-5 py-4 text-sm font-bold whitespace-nowrap transition border-b-2 border-transparent">
            <i class="fa-solid fa-handshake text-xs"></i>
            الرعايات
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-black"
              :class="tab==='sponsors' ? 'bg-purple-200 text-purple-800' : 'bg-gray-200 text-gray-600'">
              <?= count($view_sponsors) ?>
            </span>
          </button>
          <?php endif; ?>
        </div>

        <!-- Tab: Submissions -->
        <div x-show="tab==='subs'" class="divide-y divide-gray-50">
          <?php if (empty($view_subs)): ?>
          <div class="py-16 text-center">
            <i class="fa-solid fa-inbox text-5xl text-gray-200 block mb-3"></i>
            <p class="text-gray-400 font-semibold">لا توجد اقتراحات</p>
          </div>
          <?php else: foreach ($view_subs as $vs):
            $vd = json_decode($vs['sub_data'], true) ?? [];
            $vname = $vd['p_name_ar'] ?? $vd['inst_name_ar'] ?? 'بدون اسم';
            $vphoto = $vd['p_photo'] ?? $vd['inst_logo'] ?? '';
            $is_p = $vs['sub_type'] === 'personality';
            $sst = $sub_status[$vs['sub_status']];
          ?>
          <div class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition">
            <?php if ($vphoto): ?>
            <img src="../<?= htmlspecialchars($vphoto) ?>" class="w-10 h-10 <?= $is_p ? 'rounded-full' : 'rounded-xl' ?> object-cover flex-shrink-0 border border-gray-100">
            <?php else: ?>
            <div class="w-10 h-10 <?= $is_p ? 'rounded-full' : 'rounded-xl' ?> <?= $is_p ? 'bg-purple-100' : 'bg-indigo-100' ?> flex items-center justify-center flex-shrink-0">
              <i class="fa-solid <?= $is_p ? 'fa-user text-purple-500' : 'fa-building text-indigo-500' ?> text-sm"></i>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-0.5">
                <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($vname) ?></p>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold flex-shrink-0 <?= $sst['class'] ?>"><?= $sst['text'] ?></span>
              </div>
              <p class="text-xs text-gray-400">
                <span class="<?= $is_p ? 'text-purple-500' : 'text-indigo-500' ?> font-semibold"><?= $is_p ? 'شخصية' : 'مؤسسة' ?></span>
                · <?= date('d/m/Y', strtotime($vs['sub_created'])) ?>
              </p>
            </div>
            <a href="admin.php?p=submissions&highlight=<?= $vs['sub_id'] ?>" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-purple-700 bg-purple-50 border border-purple-200 rounded-xl hover:bg-purple-100 transition flex-shrink-0">
              <i class="fa-solid fa-eye text-[10px]"></i> عرض وتعديل
            </a>
          </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Tab: Edit Requests -->
        <?php
        $er_status_map = ['pending'=>['text'=>'قيد المراجعة','class'=>'bg-yellow-100 text-yellow-700'],'approved'=>['text'=>'مقبول','class'=>'bg-green-100 text-green-700'],'rejected'=>['text'=>'مرفوض','class'=>'bg-red-100 text-red-700']];
        $er_field_labels = ['name_ar'=>'الاسم عربي','name_en'=>'الاسم إنجليزي','title'=>'المسمى','nationality'=>'الجنسية','bio'=>'السيرة','description'=>'الوصف','photo'=>'الصورة','residence'=>'الإقامة'];
        $edit_reqs_list = array_filter($view_edit_reqs, function($e){ return $e['er_req_type'] === 'edit'; });
        $upgrade_reqs_list = array_filter($view_edit_reqs, function($e){ return $e['er_req_type'] === 'upgrade'; });
        ?>
        <div x-show="tab==='edits'" class="divide-y divide-gray-50">
          <?php if (empty($edit_reqs_list)): ?>
          <div class="py-16 text-center">
            <i class="fa-solid fa-pen-to-square text-5xl text-gray-200 block mb-3"></i>
            <p class="text-gray-400 font-semibold">لا توجد طلبات تعديل</p>
          </div>
          <?php else: foreach ($edit_reqs_list as $er):
            $ed = json_decode($er['er_edit_data'] ?? '{}', true) ?: [];
            $erst = $er_status_map[$er['er_status']];
          ?>
          <div class="px-5 py-4 hover:bg-gray-50 transition">
            <div class="flex items-start justify-between gap-3 mb-3">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($er['entity_name'] ?? 'محذوف') ?></p>
                  <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $erst['class'] ?>"><?= $erst['text'] ?></span>
                </div>
                <p class="text-xs text-gray-400">
                  <span class="<?= $er['er_entity_type']==='personality'?'text-purple-500':'text-indigo-500' ?> font-semibold"><?= $er['er_entity_type']==='personality'?'شخصية':'مؤسسة' ?></span>
                  · تعديل · <?= date('d/m/Y', strtotime($er['er_created'])) ?>
                </p>
              </div>
              <?php if ($er['er_status']==='pending'): ?>
              <a href="admin.php?p=edit_requests" class="text-xs font-bold text-purple-600 bg-purple-50 border border-purple-200 px-3 py-1.5 rounded-xl hover:bg-purple-100 transition flex-shrink-0">
                مراجعة <i class="fa-solid fa-arrow-left text-[10px] mr-1"></i>
              </a>
              <?php endif; ?>
            </div>
            <?php if (!empty($ed)): ?>
            <div class="bg-gray-50 rounded-xl p-3 space-y-1.5 border border-gray-100">
              <?php foreach ($ed as $k => $v): if (!$v) continue; ?>
              <div class="flex gap-3 text-xs">
                <span class="font-bold text-gray-400 w-24 flex-shrink-0"><?= $er_field_labels[$k] ?? $k ?>:</span>
                <span class="text-gray-700"><?= htmlspecialchars(mb_substr(strip_tags((string)$v), 0, 100)) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($er['er_notes']): ?>
            <div class="mt-2 flex items-start gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
              <i class="fa-solid fa-note-sticky mt-0.5"></i>
              <span><?= htmlspecialchars($er['er_notes']) ?></span>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Tab: Complaints -->
        <div x-show="tab==='cmps'" class="divide-y divide-gray-50">
          <?php if (empty($view_cmps)): ?>
          <div class="py-16 text-center">
            <i class="fa-solid fa-message text-5xl text-gray-200 block mb-3"></i>
            <p class="text-gray-400 font-semibold">لا توجد شكاوى</p>
          </div>
          <?php else: foreach ($view_cmps as $vc):
            $cst = $status_map[$vc['cmp_status']];
          ?>
          <div class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-message text-blue-500 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800 text-sm truncate mb-0.5"><?= htmlspecialchars($vc['cmp_subject']) ?></p>
              <p class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($vc['cmp_created'])) ?></p>
            </div>
            <span class="text-[10px] px-2.5 py-1 rounded-full font-bold flex-shrink-0 <?= $cst['class'] ?>"><?= $cst['text'] ?></span>
          </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Tab: Sponsors -->
        <?php if (!empty($view_sponsors)): ?>
        <div x-show="tab==='sponsors'" class="divide-y divide-gray-50">
          <?php foreach ($view_sponsors as $vsp): ?>
          <div class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition">
            <?php if ($vsp['sp_logo']): ?>
            <img src="../<?= htmlspecialchars($vsp['sp_logo']) ?>" class="w-10 h-10 rounded-xl object-contain border border-gray-100 flex-shrink-0">
            <?php else: ?>
            <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-handshake text-purple-500 text-sm"></i>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($vsp['sp_name']) ?></p>
              <?php if ($vsp['sp_url']): ?>
              <a href="<?= htmlspecialchars($vsp['sp_url']) ?>" target="_blank" class="text-xs text-purple-500 hover:underline" dir="ltr"><?= htmlspecialchars($vsp['sp_url']) ?></a>
              <?php endif; ?>
            </div>
            <a href="admin.php?p=sponsors&action=edit&id=<?= $vsp['sp_id'] ?>" class="text-xs font-bold text-gray-500 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl hover:bg-purple-50 hover:text-purple-600 transition flex-shrink-0">
              <i class="fa-solid fa-pen text-[10px] ml-1"></i>تعديل
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <script>
  function updateLinkSelect(uid) {
    var t  = document.getElementById('link-etype-'+uid).value;
    var sp = document.getElementById('link-eid-p-'+uid);
    var si = document.getElementById('link-eid-i-'+uid);
    sp.classList.toggle('hidden', t !== 'personality');
    si.classList.toggle('hidden', t !== 'institution');
    sp.disabled = (t !== 'personality');
    si.disabled = (t !== 'institution');
    var s = document.getElementById('link-search-'+uid);
    if (s) { s.value = ''; filterLinkOptions(uid); }
  }
  function filterLinkOptions(uid) {
    var q   = (document.getElementById('link-search-'+uid).value || '').toLowerCase();
    var t   = document.getElementById('link-etype-'+uid).value;
    var sel = document.getElementById('link-eid-'+(t==='institution'?'i':'p')+'-'+uid);
    var opts = sel ? sel.options : [];
    for (var i = 0; i < opts.length; i++) {
      opts[i].style.display = (!q || opts[i].text.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
    }
  }
  updateLinkSelect(<?= $view_uid ?>);
  </script>

  <?php else: ?>
  <!-- ═══ LIST VIEW ═══ -->

  <!-- Filters -->
  <form method="GET" action="admin.php" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3 items-center">
    <input type="hidden" name="p" value="users">
    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>" placeholder="بحث بالاسم أو البريد أو الهاتف..."
      class="flex-1 min-w-48 border border-gray-200 rounded-xl px-4 py-2 text-sm outline-none focus:border-purple-400">
    <select name="status" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
      <option value="">كل الحالات</option>
      <option value="active" <?= $filter_stat==='active'?'selected':'' ?>>نشط</option>
      <option value="blocked" <?= $filter_stat==='blocked'?'selected':'' ?>>محظور</option>
    </select>
    <button type="submit" class="px-5 py-2 pi-primary-bg text-white font-bold rounded-xl text-sm hover:opacity-90 transition">فلتر</button>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($users)): ?>
    <div class="text-center py-16 text-gray-400">
      <i class="fa-solid fa-users text-5xl mb-4 block opacity-30"></i>
      <p class="font-semibold">لا يوجد مستخدمون</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>المستخدم</th>
          <th>الحالة</th>
          <th>تاريخ التسجيل</th>
          <th>الاقتراحات</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $sub_r = $mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_user_id={$u['u_id']}");
          $sub_c = $sub_r ? (int)$sub_r->fetch_assoc()['c'] : 0;
        ?>
        <tr>
          <td class="text-gray-400 text-xs"><?= $u['u_id'] ?></td>
          <td>
            <div class="flex items-center gap-2">
              <?php if ($u['u_photo']): ?>
              <img src="../<?= htmlspecialchars($u['u_photo']) ?>" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
              <?php else: ?>
              <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-xs font-black flex-shrink-0">
                <?= mb_substr($u['u_name'],0,1) ?>
              </div>
              <?php endif; ?>
              <div>
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($u['u_name']) ?></p>
                <p class="text-gray-400 text-xs"><?= htmlspecialchars($u['u_email']) ?></p>
              </div>
            </div>
          </td>
          <td>
            <?php if ($u['u_active']): ?>
            <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-green-100 text-green-700">نشط</span>
            <?php else: ?>
            <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-red-100 text-red-700"><i class="fa-solid fa-ban ml-0.5"></i> محظور</span>
            <?php endif; ?>
          </td>
          <td class="text-xs text-gray-400 whitespace-nowrap"><?= date('Y/m/d', strtotime($u['u_created'])) ?></td>
          <td class="text-center">
            <?php if ($sub_c): ?>
            <span class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full font-bold"><?= $sub_c ?></span>
            <?php else: ?>
            <span class="text-gray-300 text-xs">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="flex items-center gap-1.5 justify-end flex-wrap">
              <a href="admin.php?p=users&view=<?= $u['u_id'] ?>"
                class="px-3 py-1.5 text-xs font-bold text-purple-600 border border-purple-200 rounded-lg hover:bg-purple-50 transition whitespace-nowrap">
                <i class="fa-solid fa-eye text-xs"></i> عرض
              </a>
              <a href="admin.php?p=users&impersonate=<?= $u['u_id'] ?>&view=<?= $u['u_id'] ?>"
                 onclick="return confirm('ستدخل كـ <?= htmlspecialchars(addslashes($u['u_name'])) ?>. متأكد؟')"
                 class="px-3 py-1.5 text-xs font-bold text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition whitespace-nowrap">
                 <i class="fa-solid fa-right-to-bracket text-xs"></i> دخول كـه
              </a>
              <form method="POST" class="inline">
                <input type="hidden" name="uid" value="<?= $u['u_id'] ?>">
                <?php if ($u['u_active']): ?>
                <button name="act" value="block" onclick="return confirm('حظر المستخدم؟')"
                  class="px-3 py-1.5 text-xs font-bold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition">
                  <i class="fa-solid fa-ban text-xs"></i>
                </button>
                <?php else: ?>
                <button name="act" value="unblock"
                  class="px-3 py-1.5 text-xs font-bold text-green-600 border border-green-200 rounded-lg hover:bg-green-50 transition">
                  <i class="fa-solid fa-circle-check text-xs"></i>
                </button>
                <?php endif; ?>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-center gap-2 py-4 border-t border-gray-100">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
      <a href="admin.php?p=users&page=<?= $i ?>&q=<?= urlencode($_GET['q']??'') ?>&plan=<?= urlencode($filter_plan) ?>&status=<?= urlencode($filter_stat) ?>"
        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition
          <?= $i==$page ? 'pi-primary-bg text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
