<?php
pi_require_perm('manage_memberships');
$mysqli->query("CREATE TABLE IF NOT EXISTS pi_memberships (
  mem_id INT AUTO_INCREMENT PRIMARY KEY,
  mem_type ENUM('verified','executive') DEFAULT 'verified',
  mem_plan ENUM('monthly','lifetime') NOT NULL,
  mem_name VARCHAR(200) NOT NULL,
  mem_phone VARCHAR(50) NOT NULL,
  mem_email VARCHAR(200) NOT NULL,
  mem_profile_url VARCHAR(500),
  mem_status ENUM('pending','active','cancelled') DEFAULT 'pending',
  mem_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// mem_type is already defined in CREATE TABLE above — no ALTER needed here

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'change_status') {
        $mid    = (int)$_POST['mem_id'];
        $status = in_array($_POST['status'], ['pending','active','cancelled']) ? $_POST['status'] : 'pending';
        $mysqli->query("UPDATE pi_memberships SET mem_status='$status' WHERE mem_id=$mid");
    }
    if ($act === 'delete') {
        $mid = (int)$_POST['mem_id'];
        $mysqli->query("DELETE FROM pi_memberships WHERE mem_id=$mid");
    }
}

// Active tab: verified | executive
$tab    = ($_GET['tab'] ?? 'verified') === 'executive' ? 'executive' : 'verified';
$filter = $_GET['filter'] ?? 'all';
$safe_filter = in_array($filter, ['pending','active','cancelled']) ? $filter : 'all';

// Counts per type & status
function mem_count($mysqli, $type, $status = null) {
    $w = "mem_type='$type'" . ($status ? " AND mem_status='$status'" : '');
    $r = $mysqli->query("SELECT COUNT(*) c FROM pi_memberships WHERE $w");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

$cnt = [
    'verified'  => ['all' => mem_count($mysqli,'verified'),  'pending' => mem_count($mysqli,'verified','pending'),  'active' => mem_count($mysqli,'verified','active'),  'cancelled' => mem_count($mysqli,'verified','cancelled')],
    'executive' => ['all' => mem_count($mysqli,'executive'), 'pending' => mem_count($mysqli,'executive','pending'), 'active' => mem_count($mysqli,'executive','active'), 'cancelled' => mem_count($mysqli,'executive','cancelled')],
];

// Fetch list
$where_type   = "mem_type='$tab'";
$where_status = $safe_filter !== 'all' ? " AND mem_status='$safe_filter'" : '';
$list = [];
$r = $mysqli->query("SELECT * FROM pi_memberships WHERE $where_type$where_status ORDER BY mem_created DESC");
if ($r) while ($row = $r->fetch_assoc()) $list[] = $row;

$status_labels = ['pending'=>'قيد المراجعة','active'=>'نشطة','cancelled'=>'ملغاة'];
$status_colors = ['pending'=>'bg-yellow-100 text-yellow-700','active'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700'];

$plan_label = function($plan, $type) {
    if ($type === 'executive') return $plan === 'monthly' ? 'شهري $210' : 'مدى الحياة $250';
    return $plan === 'monthly' ? 'شهري $90' : 'مدى الحياة $99';
};
$plan_color = function($t){ return $t === 'executive' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700'; };
?>

<!-- ══════════════════════════════════════════════
     TYPE TABS — التوثيق vs الرؤساء التنفيذيون
════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <h2 class="text-xl font-black text-gray-800">طلبات العضوية</h2>
  <div class="flex bg-gray-100 rounded-2xl p-1 gap-1">
    <a href="admin.php?p=memberships&tab=verified&filter=all"
      class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition
        <?= $tab==='verified' ? 'text-white' : 'text-gray-500 hover:text-blue-600' ?>"
      <?= $tab==='verified' ? 'style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)"' : '' ?>>
      <i class="fa-solid fa-circle-check <?= $tab==='verified'?'text-white':'text-blue-500' ?>"></i>
      العضوية الموثقة
      <span class="<?= $tab==='verified'?'bg-white/20 text-white':'bg-blue-100 text-blue-700' ?> px-2 py-0.5 rounded-full text-xs font-black">
        <?= $cnt['verified']['all'] ?>
      </span>
    </a>
    <a href="admin.php?p=memberships&tab=executive&filter=all"
      class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition
        <?= $tab==='executive' ? 'text-white' : 'text-gray-500 hover:text-amber-700' ?>"
      <?= $tab==='executive' ? 'style="background:linear-gradient(135deg,#92400e,#b45309)"' : '' ?>>
      <i class="fa-solid fa-crown <?= $tab==='executive'?'text-yellow-300':'text-yellow-500' ?>"></i>
      الرؤساء التنفيذيون
      <span class="<?= $tab==='executive'?'bg-white/20 text-white':'bg-amber-100 text-amber-700' ?> px-2 py-0.5 rounded-full text-xs font-black">
        <?= $cnt['executive']['all'] ?>
      </span>
    </a>
  </div>
</div>

<!-- ══ INFO BOX ══ -->
<?php if ($tab === 'verified'): ?>
<div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 mb-6 flex gap-4">
  <div class="w-10 h-10 rounded-xl bg-blue-500 flex items-center justify-center flex-shrink-0">
    <i class="fa-solid fa-circle-check text-white text-lg"></i>
  </div>
  <div>
    <h3 class="font-black text-blue-800 mb-1">العضوية الموثقة — ما يحتاجه المتقدم</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
      <?php
      $needs_v = [
        ['icon'=>'fa-user',        'color'=>'#3b82f6', 'label'=>'الاسم الكامل',         'req'=>true],
        ['icon'=>'fa-phone',       'color'=>'#3b82f6', 'label'=>'رقم الجوال',            'req'=>true],
        ['icon'=>'fa-envelope',    'color'=>'#3b82f6', 'label'=>'البريد الإلكتروني',     'req'=>true],
        ['icon'=>'fa-link',        'color'=>'#6b7280', 'label'=>'رابط ملفه على الموقع', 'req'=>false],
      ];
      foreach ($needs_v as $n): ?>
      <div class="flex items-center gap-2 bg-white rounded-xl px-3 py-2 border border-blue-100">
        <i class="fa-solid <?= $n['icon'] ?>" style="color:<?= $n['color'] ?>;font-size:14px;"></i>
        <span class="text-xs font-bold text-gray-700"><?= $n['label'] ?></span>
        <?php if ($n['req']): ?>
        <span class="mr-auto text-red-400 text-xs font-black">*</span>
        <?php else: ?>
        <span class="mr-auto text-gray-300 text-xs">اختياري</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-blue-600 text-xs mt-3 font-semibold">
      <i class="fa-solid fa-tag mr-1"></i>
      الباقة الشهرية: <strong>$90/شهر</strong> &nbsp;|&nbsp; مدى الحياة: <strong>$99</strong>
    </p>
  </div>
</div>

<?php else: ?>
<div class="rounded-2xl p-5 mb-6 flex gap-4 border border-amber-300" style="background:linear-gradient(135deg,#fef3c7,#fde68a22)">
  <div class="w-10 h-10 rounded-xl bg-amber-500 flex items-center justify-center flex-shrink-0">
    <i class="fa-solid fa-crown text-white text-lg"></i>
  </div>
  <div class="flex-1">
    <h3 class="font-black text-amber-800 mb-1">عضوية الرؤساء التنفيذيين — ما يحتاجه المتقدم</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
      <?php
      $needs_e = [
        ['icon'=>'fa-user',        'color'=>'#b45309', 'label'=>'الاسم الكامل',         'req'=>true],
        ['icon'=>'fa-phone',       'color'=>'#b45309', 'label'=>'رقم الجوال',            'req'=>true],
        ['icon'=>'fa-envelope',    'color'=>'#b45309', 'label'=>'البريد الإلكتروني',     'req'=>true],
        ['icon'=>'fa-link',        'color'=>'#6b7280', 'label'=>'رابط ملفه على الموقع', 'req'=>false],
      ];
      foreach ($needs_e as $n): ?>
      <div class="flex items-center gap-2 bg-white rounded-xl px-3 py-2 border border-amber-200">
        <i class="fa-solid <?= $n['icon'] ?>" style="color:<?= $n['color'] ?>;font-size:14px;"></i>
        <span class="text-xs font-bold text-gray-700"><?= $n['label'] ?></span>
        <?php if ($n['req']): ?>
        <span class="mr-auto text-red-400 text-xs font-black">*</span>
        <?php else: ?>
        <span class="mr-auto text-gray-300 text-xs">اختياري</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-3 flex flex-wrap gap-4">
      <p class="text-amber-700 text-xs font-semibold">
        <i class="fa-solid fa-tag mr-1"></i>
        الباقة الشهرية: <strong>$210/شهر</strong> &nbsp;|&nbsp; مدى الحياة: <strong>$250</strong>
      </p>
      <p class="text-amber-600 text-xs font-semibold">
        <i class="fa-solid fa-circle-check text-blue-500 mr-1"></i> تشمل التوثيق الكامل
        <i class="fa-solid fa-star text-yellow-500 mr-2 ml-1"></i> + شارة الرئيس التنفيذي
        <i class="fa-solid fa-headset text-green-500 mr-2 ml-1"></i> + دعم مخصص 24/7
      </p>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ STAT CARDS ══ -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
  <?php
  $c = $cnt[$tab];
  $type_color = $tab === 'executive' ? '#b45309' : '#3b82f6';
  $stats = [
    ['label'=>'إجمالي الطلبات',  'val'=>$c['all'],       'icon'=>$tab==='executive'?'fa-crown':'fa-circle-check', 'color'=>$type_color],
    ['label'=>'قيد المراجعة',    'val'=>$c['pending'],   'icon'=>'fa-clock',        'color'=>'#d97706'],
    ['label'=>'عضويات نشطة',    'val'=>$c['active'],    'icon'=>'fa-circle-check', 'color'=>'#16a34a'],
    ['label'=>'ملغاة',            'val'=>$c['cancelled'], 'icon'=>'fa-ban',          'color'=>'#dc2626'],
  ];
  foreach ($stats as $s): ?>
  <div class="card flex items-center gap-4">
    <div style="width:44px;height:44px;border-radius:12px;background:<?= $s['color'] ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa-solid <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;font-size:18px;"></i>
    </div>
    <div>
      <p style="font-size:22px;font-weight:900;color:#111827;line-height:1;"><?= number_format($s['val']) ?></p>
      <p style="font-size:12px;color:#6b7280;font-weight:600;margin-top:2px;"><?= $s['label'] ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══ STATUS FILTER TABS ══ -->
<div class="flex items-center gap-2 mb-5 flex-wrap">
  <?php
  $tabs_status = ['all'=>'الكل','pending'=>'قيد المراجعة','active'=>'نشطة','cancelled'=>'ملغاة'];
  foreach ($tabs_status as $key => $label):
    $active = $safe_filter === $key;
  ?>
  <a href="admin.php?p=memberships&tab=<?= $tab ?>&filter=<?= $key ?>"
    class="px-4 py-1.5 rounded-full text-sm font-bold transition <?= $active ? 'text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>"
    <?= $active ? 'style="background:linear-gradient(135deg,#8829C8,#5B1494)"' : '' ?>>
    <?= $label ?>
    <span style="font-size:11px;opacity:.8;">(<?= $cnt[$tab][$key] ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ══ TABLE ══ -->
<div class="card" style="padding:0;overflow:hidden;">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>الاسم</th>
        <th>الباقة</th>
        <th>الجوال</th>
        <th>البريد</th>
        <th>رابط الملف</th>
        <th>الحالة</th>
        <th>تاريخ الطلب</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($list)): ?>
      <tr><td colspan="9" style="text-align:center;padding:60px 20px;color:#9ca3af;">
        <i class="fa-solid <?= $tab==='executive'?'fa-crown':'fa-circle-check' ?>" style="font-size:40px;display:block;margin-bottom:12px;opacity:.3;"></i>
        لا توجد طلبات <?= $tab==='executive'?'رؤساء تنفيذيين':'توثيق' ?><?= $safe_filter!=='all'?' بهذه الحالة':'' ?>
      </td></tr>
      <?php else: ?>
      <?php foreach ($list as $m): ?>
      <tr>
        <td style="color:#9ca3af;font-size:12px;">#<?= $m['mem_id'] ?></td>
        <td>
          <p style="font-weight:700;color:#111827;font-size:14px;"><?= htmlspecialchars($m['mem_name']) ?></p>
        </td>
        <td>
          <span style="padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;"
            class="<?= $plan_color($m['mem_type']) ?>">
            <?= $plan_label($m['mem_plan'], $m['mem_type']) ?>
          </span>
        </td>
        <td style="font-size:13px;direction:ltr;text-align:right;"><?= htmlspecialchars($m['mem_phone']) ?></td>
        <td style="font-size:13px;"><?= htmlspecialchars($m['mem_email']) ?></td>
        <td>
          <?php if ($m['mem_profile_url']): ?>
          <a href="<?= htmlspecialchars($m['mem_profile_url']) ?>" target="_blank"
            style="font-size:12px;color:#8829C8;font-weight:700;text-decoration:none;"
            onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px;margin-left:4px;"></i>عرض
          </a>
          <?php else: ?>
          <span style="color:#d1d5db;font-size:12px;">—</span>
          <?php endif; ?>
        </td>
        <td>
          <span style="padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;"
            class="<?= $status_colors[$m['mem_status']] ?>">
            <?= $status_labels[$m['mem_status']] ?>
          </span>
        </td>
        <td style="font-size:12px;color:#9ca3af;white-space:nowrap;"><?= date('Y/m/d', strtotime($m['mem_created'])) ?></td>
        <td>
          <div style="display:flex;gap:6px;align-items:center;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="mem_id" value="<?= $m['mem_id'] ?>">
              <select name="status" onchange="this.form.submit()"
                style="border:1px solid #e5e7eb;border-radius:8px;padding:5px 8px;font-size:12px;font-family:'Cairo',sans-serif;font-weight:600;outline:none;cursor:pointer;">
                <option value="pending"   <?= $m['mem_status']==='pending'?'selected':''   ?>>قيد المراجعة</option>
                <option value="active"    <?= $m['mem_status']==='active'?'selected':''    ?>>نشطة</option>
                <option value="cancelled" <?= $m['mem_status']==='cancelled'?'selected':'' ?>>ملغاة</option>
              </select>
            </form>
            <form method="POST" onsubmit="return confirm('حذف هذا الطلب؟')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="mem_id" value="<?= $m['mem_id'] ?>">
              <button type="submit" class="btn-danger" style="padding:5px 10px;">
                <i class="fa-solid fa-trash" style="font-size:12px;"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
