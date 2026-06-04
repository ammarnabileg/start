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
          FROM pi_edit_requests er WHERE er.er_user_id=$view_uid ORDER BY er.er_created DESC LIMIT 20");
        if ($er) while ($row=$er->fetch_assoc()) $view_edit_reqs[] = $row;
        $cr = $mysqli->query("SELECT cmp_id,cmp_type,cmp_subject,cmp_status,cmp_created FROM pi_complaints WHERE cmp_user_id=$view_uid ORDER BY cmp_created DESC LIMIT 20");
        if ($cr) while ($row=$cr->fetch_assoc()) $view_cmps[] = $row;
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
          <?php if ($view_user['u_active']): ?>
          <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-green-100 text-green-700"><i class="fa-solid fa-circle-check ml-1"></i>نشط</span>
          <?php else: ?>
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

        <!-- Personalities & Institutions managed by user -->
        <div class="border border-gray-100 rounded-xl overflow-hidden">
          <div class="px-4 py-3 bg-gray-50 font-bold text-sm text-gray-700 flex items-center justify-between">
            <span><i class="fa-solid fa-id-card text-purple-500 ml-1"></i>الصفحات المدارة</span>
            <button onclick="document.getElementById('link-page-form-<?= $view_uid ?>').classList.toggle('hidden')"
              class="px-3 py-1 text-xs font-black bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
              <i class="fa-solid fa-plus ml-1"></i>ربط صفحة
            </button>
          </div>

          <!-- Link form -->
          <div id="link-page-form-<?= $view_uid ?>" class="hidden border-b border-gray-100 p-4 bg-purple-50">
            <form method="POST" class="space-y-3">
              <input type="hidden" name="uid" value="<?= $view_uid ?>">
              <input type="hidden" name="act" value="link_page">
              <div class="flex gap-2 flex-wrap items-end">
                <div class="flex-shrink-0 min-w-36">
                  <label class="block text-xs font-bold text-gray-600 mb-1">النوع</label>
                  <select name="etype" id="link-etype-<?= $view_uid ?>" onchange="updateLinkSelect(<?= $view_uid ?>)"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white">
                    <option value="personality">شخصية</option>
                    <option value="institution">مؤسسة</option>
                  </select>
                </div>
                <div class="flex-1 min-w-52">
                  <label class="block text-xs font-bold text-gray-600 mb-1">بحث سريع</label>
                  <input type="text" id="link-search-<?= $view_uid ?>" placeholder="اكتب للفلترة..."
                    oninput="filterLinkOptions(<?= $view_uid ?>)"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white">
                </div>
                <button type="submit" class="px-4 py-2 text-xs font-black bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex-shrink-0">
                  <i class="fa-solid fa-link ml-1"></i>ربط
                </button>
              </div>
              <div>
                <?php
                // User's suggested entities (approved submissions) shown first
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
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white max-h-48 overflow-y-auto">
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
                <select name="eid" id="link-eid-i-<?= $view_uid ?>" class="hidden w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-purple-400 bg-white">
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
                <p class="text-xs text-gray-400 mt-1">★ = اقترحها المستخدم سابقاً</p>
              </div>
            </form>
          </div>

          <div class="p-4 space-y-2">
          <?php if (empty($view_personalities) && empty($view_institutions)): ?>
          <p class="text-gray-400 text-sm text-center py-4">لا توجد صفحات مرتبطة بعد</p>
          <?php endif; ?>
          <?php foreach ($view_personalities as $vp):
            $mem = $vp['p_membership_type'];
            $badge = $mem==='executive'?['تنفيذي','bg-amber-100 text-amber-700']:($mem==='verified'||$vp['p_verified']?['موثق','bg-blue-100 text-blue-700']:['غير موثق','bg-gray-100 text-gray-500']);
          ?>
          <div class="flex items-center gap-3 p-2 rounded-lg border border-gray-100">
            <?php if ($vp['p_photo']): ?><img src="../<?= htmlspecialchars($vp['p_photo']) ?>" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
            <?php else: ?><div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-user text-purple-500 text-xs"></i></div><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($vp['p_name_ar']) ?></p>
              <p class="text-xs text-gray-400"><?= htmlspecialchars($vp['p_title'] ?? '') ?></p>
            </div>
            <div class="flex gap-1 items-center flex-shrink-0">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-purple-100 text-purple-700">شخصية</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $badge[1] ?>"><?= $badge[0] ?></span>
              <form method="POST" class="inline">
                <input type="hidden" name="uid" value="<?= $view_uid ?>">
                <input type="hidden" name="act" value="unlink_page">
                <input type="hidden" name="etype" value="personality">
                <input type="hidden" name="eid" value="<?= $vp['p_id'] ?>">
                <button type="submit" onclick="return confirm('إلغاء الربط؟')" class="text-red-400 hover:text-red-600 transition px-1" title="إلغاء الربط">
                  <i class="fa-solid fa-link-slash text-xs"></i>
                </button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
          <?php foreach ($view_institutions as $vi):
            $mem = $vi['inst_membership_type'];
            $badge = $mem==='executive'?['تنفيذي','bg-amber-100 text-amber-700']:($mem==='verified'||$vi['inst_verified']?['موثقة','bg-blue-100 text-blue-700']:['غير موثقة','bg-gray-100 text-gray-500']);
          ?>
          <div class="flex items-center gap-3 p-2 rounded-lg border border-gray-100">
            <?php if ($vi['inst_logo']): ?><img src="../<?= htmlspecialchars($vi['inst_logo']) ?>" class="w-8 h-8 rounded-xl object-cover flex-shrink-0">
            <?php else: ?><div class="w-8 h-8 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-building text-indigo-500 text-xs"></i></div><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($vi['inst_name_ar']) ?></p>
            </div>
            <div class="flex gap-1 items-center flex-shrink-0">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold bg-indigo-100 text-indigo-700">مؤسسة</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $badge[1] ?>"><?= $badge[0] ?></span>
              <form method="POST" class="inline">
                <input type="hidden" name="uid" value="<?= $view_uid ?>">
                <input type="hidden" name="act" value="unlink_page">
                <input type="hidden" name="etype" value="institution">
                <input type="hidden" name="eid" value="<?= $vi['inst_id'] ?>">
                <button type="submit" onclick="return confirm('إلغاء الربط؟')" class="text-red-400 hover:text-red-600 transition px-1" title="إلغاء الربط">
                  <i class="fa-solid fa-link-slash text-xs"></i>
                </button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
          </div>
        </div>
        <script>
        function updateLinkSelect(uid) {
          var t = document.getElementById('link-etype-'+uid).value;
          var sp = document.getElementById('link-eid-p-'+uid);
          var si = document.getElementById('link-eid-i-'+uid);
          sp.classList.toggle('hidden', t !== 'personality');
          si.classList.toggle('hidden', t !== 'institution');
          sp.disabled = (t !== 'personality');
          si.disabled = (t !== 'institution');
          // Reset search
          var s = document.getElementById('link-search-'+uid);
          if (s) { s.value = ''; filterLinkOptions(uid); }
        }
        function filterLinkOptions(uid) {
          var q = (document.getElementById('link-search-'+uid).value || '').toLowerCase();
          var t = document.getElementById('link-etype-'+uid).value;
          var sel = document.getElementById('link-eid-'+(t==='institution'?'i':'p')+'-'+uid);
          var opts = sel ? sel.options : [];
          for (var i = 0; i < opts.length; i++) {
            opts[i].style.display = (!q || opts[i].text.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
          }
        }
        updateLinkSelect(<?= $view_uid ?>);
        </script>
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
            $er_field_labels = ['name_ar'=>'الاسم عربي','name_en'=>'الاسم إنجليزي','title'=>'المسمى','nationality'=>'الجنسية','bio'=>'السيرة','description'=>'الوصف','photo'=>'الصورة','residence'=>'الإقامة'];
            foreach ($view_edit_reqs as $er):
              $ed = json_decode($er['er_edit_data'] ?? '{}', true) ?: [];
            ?>
            <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
              <div class="flex items-start justify-between gap-2 mb-2">
                <div>
                  <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($er['entity_name'] ?? 'محذوف') ?></p>
                  <p class="text-xs text-gray-400 mt-0.5">
                    <?= $er['er_entity_type']==='personality'?'شخصية':'مؤسسة' ?> ·
                    <?php if ($er['er_req_type']==='edit'): ?>تعديل
                    <?php else: ?>ترقية — <span class="font-bold <?= $er['er_upgrade_to']==='executive'?'text-amber-600':'text-blue-600' ?>"><?= $er['er_upgrade_to']==='executive'?'تنفيذي':'موثق' ?></span>
                    <?php endif; ?> ·
                    <?= date('Y/m/d', strtotime($er['er_created'])) ?>
                  </p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full font-bold flex-shrink-0 <?= $er_status_map[$er['er_status']]['class'] ?>">
                  <?= $er_status_map[$er['er_status']]['text'] ?>
                </span>
              </div>
              <?php if (!empty($ed)): ?>
              <div class="bg-white rounded-lg p-2 space-y-1 border border-gray-100 mb-2">
                <?php foreach ($ed as $k => $v): if (!$v) continue; ?>
                <div class="flex gap-2 text-xs">
                  <span class="font-bold text-gray-400 w-20 flex-shrink-0"><?= $er_field_labels[$k] ?? $k ?>:</span>
                  <span class="text-gray-700 break-all"><?= htmlspecialchars(mb_substr(strip_tags($v), 0, 120)) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <?php if ($er['er_notes']): ?><p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1 mb-1"><i class="fa-solid fa-note-sticky ml-1"></i><?= htmlspecialchars($er['er_notes']) ?></p><?php endif; ?>
              <?php if ($er['er_admin_note']): ?><p class="text-xs text-blue-700 bg-blue-50 rounded px-2 py-1"><i class="fa-solid fa-comment ml-1"></i><?= htmlspecialchars($er['er_admin_note']) ?></p><?php endif; ?>
              <?php if ($er['er_status']==='pending'): ?>
              <a href="admin.php?p=edit_requests" class="text-xs font-bold text-purple-600 hover:underline mt-1 inline-block">مراجعة الطلب ←</a>
              <?php endif; ?>
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
