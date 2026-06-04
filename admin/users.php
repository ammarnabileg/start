<?php
pi_require_login();
pi_require_perm('manage_users');

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
foreach (['total'=>'SELECT COUNT(*) c FROM pi_users',
          'active'=>'SELECT COUNT(*) c FROM pi_users WHERE u_active=1',
          'blocked'=>'SELECT COUNT(*) c FROM pi_users WHERE u_active=0',
          'verified'=>"SELECT COUNT(*) c FROM pi_users WHERE u_plan='verified'",
          'executive'=>"SELECT COUNT(*) c FROM pi_users WHERE u_plan='executive'"] as $k=>$q) {
    $sr = $mysqli->query($q);
    $stats[$k] = $sr ? (int)$sr->fetch_assoc()['c'] : 0;
}

// Single view
$view_user = null;
$view_subs = [];
$view_cmps = [];
if ($view_uid) {
    $vr = $mysqli->query("SELECT * FROM pi_users WHERE u_id=$view_uid");
    if ($vr && $vr->num_rows) {
        $view_user = $vr->fetch_assoc();
        $sr = $mysqli->query("SELECT sub_id,sub_type,sub_status,sub_created,sub_data FROM pi_submissions WHERE sub_user_id=$view_uid ORDER BY sub_created DESC LIMIT 20");
        if ($sr) while ($row=$sr->fetch_assoc()) $view_subs[] = $row;
        $view_edit_reqs = [];
        $er = $mysqli->query("SELECT er.*,
          CASE WHEN er.er_entity_type='personality' THEN (SELECT p_name_ar FROM pi_personalities WHERE p_id=er.er_entity_id)
               ELSE (SELECT inst_name_ar FROM pi_institutions WHERE inst_id=er.er_entity_id) END AS entity_name
          FROM pi_edit_requests er WHERE er.er_user_id=$view_uid ORDER BY er.er_created DESC LIMIT 20");
        if ($er) while ($row=$er->fetch_assoc()) $view_edit_reqs[] = $row;
        $cr = $mysqli->query("SELECT cmp_id,cmp_type,cmp_subject,cmp_status,cmp_created FROM pi_complaints WHERE cmp_user_id=$view_uid ORDER BY cmp_created DESC LIMIT 20");
        if ($cr) while ($row=$cr->fetch_assoc()) $view_cmps[] = $row;
    }
}

$plan_map   = ['free'=>['text'=>'مجاني','class'=>'bg-gray-100 text-gray-600'],
               'verified'=>['text'=>'موثق','class'=>'bg-blue-100 text-blue-700'],
               'executive'=>['text'=>'تنفيذي','class'=>'bg-purple-100 text-purple-700']];
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
      ['label'=>'موثقون','val'=>$stats['verified'],'color'=>'blue','icon'=>'badge-check'],
      ['label'=>'تنفيذيون','val'=>$stats['executive'],'color'=>'indigo','icon'=>'crown'],
    ];
    foreach ($stat_cards as $sc): ?>
    <div class="bg-<?= $sc['color'] ?>-50 border border-<?= $sc['color'] ?>-200 rounded-2xl p-4 text-center">
      <p class="text-2xl font-black text-<?= $sc['color'] ?>-700"><?= $sc['val'] ?></p>
      <p class="text-<?= $sc['color'] ?>-600 text-xs font-semibold mt-1"><?= $sc['label'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($view_user): ?>
  <!-- ═══ SINGLE USER VIEW ═══ -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <!-- Header -->
    <div class="flex items-center gap-4 p-6 border-b border-gray-100">
      <a href="admin.php?p=users" class="text-gray-400 hover:text-gray-600">
        <i class="fa-solid fa-arrow-right text-lg"></i>
      </a>
      <?php if ($view_user['u_photo']): ?>
      <img src="../<?= htmlspecialchars($view_user['u_photo']) ?>" class="w-14 h-14 rounded-full object-cover border-2 border-purple-200">
      <?php else: ?>
      <div class="w-14 h-14 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-2xl font-black">
        <?= mb_substr($view_user['u_name'], 0, 1) ?>
      </div>
      <?php endif; ?>
      <div class="flex-1">
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="font-black text-gray-800 text-lg"><?= htmlspecialchars($view_user['u_name']) ?></h2>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $plan_map[$view_user['u_plan']]['class'] ?>"><?= $plan_map[$view_user['u_plan']]['text'] ?></span>
          <?php if (!$view_user['u_active']): ?>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-red-100 text-red-700"><i class="fa-solid fa-ban ml-1"></i>محظور</span>
          <?php endif; ?>
        </div>
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($view_user['u_email']) ?></p>
      </div>
      <!-- Action buttons -->
      <div class="flex gap-2 flex-wrap justify-end">
        <a href="admin.php?p=users&impersonate=<?= $view_user['u_id'] ?>"
           onclick="return confirm('ستدخل كـ <?= htmlspecialchars(addslashes($view_user['u_name'])) ?>. متأكد؟')"
           class="flex items-center gap-1.5 px-4 py-2 text-xs font-black bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition">
          <i class="fa-solid fa-eye"></i> دخول كـ المستخدم
        </a>
        <?php if ($view_user['u_active']): ?>
        <form method="POST">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <button name="act" value="block" onclick="return confirm('حظر المستخدم؟')"
            class="flex items-center gap-1.5 px-4 py-2 text-xs font-black bg-red-50 text-red-700 border border-red-200 rounded-xl hover:bg-red-100 transition">
            <i class="fa-solid fa-ban"></i> حظر
          </button>
        </form>
        <?php else: ?>
        <form method="POST">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <button name="act" value="unblock"
            class="flex items-center gap-1.5 px-4 py-2 text-xs font-black bg-green-50 text-green-700 border border-green-200 rounded-xl hover:bg-green-100 transition">
            <i class="fa-solid fa-circle-check"></i> رفع الحظر
          </button>
        </form>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('حذف الحساب نهائياً؟ لا يمكن التراجع.')">
          <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
          <button name="act" value="delete"
            class="flex items-center gap-1.5 px-4 py-2 text-xs font-black bg-red-600 text-white rounded-xl hover:bg-red-700 transition">
            <i class="fa-solid fa-trash"></i> حذف
          </button>
        </form>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-0 divide-y md:divide-y-0 md:divide-x md:divide-x-reverse divide-gray-100">

      <!-- Info + Edit -->
      <div class="p-6 md:col-span-2 space-y-6">
        <!-- Info grid -->
        <div class="grid grid-cols-2 gap-4 text-sm">
          <?php
          $fields = [
            'الهاتف'=>$view_user['u_phone'],'الوظيفة'=>$view_user['u_job'],
            'الجنسية'=>$view_user['u_nationality'],'الشركة'=>$view_user['u_company'],
            'تاريخ الميلاد'=>$view_user['u_birthdate'],'الجنس'=>($view_user['u_gender']==='male'?'ذكر':($view_user['u_gender']==='female'?'أنثى':'—')),
            'تاريخ التسجيل'=>date('Y/m/d', strtotime($view_user['u_created'])),
            'عدد الاقتراحات'=>count($view_subs).' اقتراح',
          ];
          foreach ($fields as $lbl=>$val): if (!$val) continue; ?>
          <div>
            <span class="text-gray-400 text-xs block"><?= $lbl ?></span>
            <p class="font-semibold text-gray-800"><?= htmlspecialchars($val) ?></p>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Edit user form -->
        <details class="border border-gray-100 rounded-xl">
          <summary class="px-4 py-3 font-bold text-sm text-gray-700 cursor-pointer hover:bg-gray-50 rounded-xl">
            <i class="fa-solid fa-pen ml-2 text-purple-500"></i>تعديل بيانات المستخدم
          </summary>
          <form method="POST" class="p-4 space-y-3 border-t border-gray-100">
            <input type="hidden" name="act" value="edit_user">
            <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="form-label text-xs">الاسم</label>
                <input type="text" name="u_name" class="form-input text-sm" value="<?= htmlspecialchars($view_user['u_name']) ?>">
              </div>
              <div>
                <label class="form-label text-xs">البريد</label>
                <input type="email" name="u_email" class="form-input text-sm" dir="ltr" value="<?= htmlspecialchars($view_user['u_email']) ?>">
              </div>
              <div>
                <label class="form-label text-xs">الهاتف</label>
                <input type="text" name="u_phone" class="form-input text-sm" value="<?= htmlspecialchars($view_user['u_phone'] ?? '') ?>">
              </div>
              <div>
                <label class="form-label text-xs">الوظيفة</label>
                <input type="text" name="u_job" class="form-input text-sm" value="<?= htmlspecialchars($view_user['u_job'] ?? '') ?>">
              </div>
            </div>
            <button type="submit" class="btn-primary text-sm px-5 py-2">حفظ التعديلات</button>
          </form>
        </details>

        <!-- Change password -->
        <details class="border border-gray-100 rounded-xl">
          <summary class="px-4 py-3 font-bold text-sm text-gray-700 cursor-pointer hover:bg-gray-50 rounded-xl">
            <i class="fa-solid fa-key ml-2 text-amber-500"></i>تغيير كلمة المرور
          </summary>
          <form method="POST" class="p-4 space-y-3 border-t border-gray-100">
            <input type="hidden" name="act" value="reset_password">
            <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
            <input type="text" name="new_password" class="form-input text-sm" placeholder="كلمة المرور الجديدة (6 أحرف على الأقل)" dir="ltr">
            <button type="submit" class="btn-primary text-sm px-5 py-2" style="background:linear-gradient(135deg,#f59e0b,#d97706);">تغيير كلمة المرور</button>
          </form>
        </details>

        <!-- Change plan -->
        <details class="border border-gray-100 rounded-xl">
          <summary class="px-4 py-3 font-bold text-sm text-gray-700 cursor-pointer hover:bg-gray-50 rounded-xl">
            <i class="fa-solid fa-crown ml-2 text-purple-500"></i>تغيير الخطة
          </summary>
          <form method="POST" class="p-4 flex gap-2 flex-wrap border-t border-gray-100">
            <input type="hidden" name="act" value="change_plan">
            <input type="hidden" name="uid" value="<?= $view_user['u_id'] ?>">
            <?php foreach (['free'=>'مجاني','verified'=>'موثق','executive'=>'تنفيذي'] as $pv=>$pl): ?>
            <button name="plan" value="<?= $pv ?>"
              class="px-5 py-2 text-sm font-black rounded-xl border transition <?= $view_user['u_plan']===$pv ? 'pi-primary-bg text-white border-transparent' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
              <?= $pl ?>
            </button>
            <?php endforeach; ?>
          </form>
        </details>
      </div>

      <!-- Submissions & Complaints -->
      <div class="p-6 space-y-5">
        <div>
          <h3 class="font-black text-gray-700 text-sm mb-3"><i class="fa-solid fa-inbox text-purple-500 ml-1"></i>الاقتراحات (<?= count($view_subs) ?>)</h3>
          <?php if (empty($view_subs)): ?>
          <p class="text-gray-400 text-xs text-center py-4">لا توجد اقتراحات</p>
          <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($view_subs as $vs):
              $vd = json_decode($vs['sub_data'], true) ?? [];
              $vname = $vd['p_name_ar'] ?? $vd['inst_name_ar'] ?? 'بدون اسم';
            ?>
            <div class="bg-gray-50 rounded-xl p-3">
              <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($vname) ?></p>
                <span class="text-xs px-2 py-0.5 rounded-full font-bold flex-shrink-0 <?= $sub_status[$vs['sub_status']]['class'] ?>">
                  <?= $sub_status[$vs['sub_status']]['text'] ?>
                </span>
              </div>
              <p class="text-xs text-gray-400 mt-1"><?= $vs['sub_type']==='personality'?'شخصية':'مؤسسة' ?> · <?= date('Y/m/d', strtotime($vs['sub_created'])) ?></p>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($view_edit_reqs)): ?>
        <div>
          <h3 class="font-black text-gray-700 text-sm mb-3"><i class="fa-solid fa-pen-to-square text-amber-500 ml-1"></i>طلبات التعديل والترقية (<?= count($view_edit_reqs) ?>)</h3>
          <div class="space-y-2">
            <?php
            $er_status_map = ['pending'=>['text'=>'قيد المراجعة','class'=>'bg-yellow-100 text-yellow-700'],'approved'=>['text'=>'مقبول','class'=>'bg-green-100 text-green-700'],'rejected'=>['text'=>'مرفوض','class'=>'bg-red-100 text-red-700']];
            foreach ($view_edit_reqs as $er): ?>
            <div class="bg-gray-50 rounded-xl p-3">
              <div class="flex items-center justify-between gap-2">
                <div>
                  <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($er['entity_name'] ?? 'محذوف') ?></p>
                  <p class="text-xs text-gray-400 mt-0.5">
                    <?= $er['er_entity_type']==='personality'?'شخصية':'مؤسسة' ?> ·
                    <?= $er['er_req_type']==='edit'?'تعديل':'ترقية '.($er['er_upgrade_to']==='executive'?'تنفيذي':'موثق') ?> ·
                    <?= date('Y/m/d', strtotime($er['er_created'])) ?>
                  </p>
                </div>
                <a href="admin.php?p=edit_requests&view=<?= $er['er_id'] ?>"
                   class="text-xs px-2 py-0.5 rounded-full font-bold <?= $er_status_map[$er['er_status']]['class'] ?>">
                  <?= $er_status_map[$er['er_status']]['text'] ?>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div>
          <h3 class="font-black text-gray-700 text-sm mb-3"><i class="fa-solid fa-message text-blue-500 ml-1"></i>الشكاوي (<?= count($view_cmps) ?>)</h3>
          <?php if (empty($view_cmps)): ?>
          <p class="text-gray-400 text-xs text-center py-4">لا توجد شكاوي</p>
          <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($view_cmps as $vc): ?>
            <div class="bg-gray-50 rounded-xl p-3">
              <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($vc['cmp_subject']) ?></p>
              <div class="flex items-center justify-between mt-1">
                <p class="text-xs text-gray-400"><?= date('Y/m/d', strtotime($vc['cmp_created'])) ?></p>
                <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $status_map[$vc['cmp_status']]['class'] ?>">
                  <?= $status_map[$vc['cmp_status']]['text'] ?>
                </span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ═══ LIST VIEW ═══ -->

  <!-- Filters -->
  <form method="GET" action="admin.php" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3 items-center">
    <input type="hidden" name="p" value="users">
    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>" placeholder="بحث بالاسم أو البريد أو الهاتف..."
      class="flex-1 min-w-48 border border-gray-200 rounded-xl px-4 py-2 text-sm outline-none focus:border-purple-400">
    <select name="plan" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
      <option value="">كل الخطط</option>
      <option value="free" <?= $filter_plan==='free'?'selected':'' ?>>مجاني</option>
      <option value="verified" <?= $filter_plan==='verified'?'selected':'' ?>>موثق</option>
      <option value="executive" <?= $filter_plan==='executive'?'selected':'' ?>>تنفيذي</option>
    </select>
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
          <th>الخطة</th>
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
            <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $plan_map[$u['u_plan']]['class'] ?>">
              <?= $plan_map[$u['u_plan']]['text'] ?>
            </span>
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
