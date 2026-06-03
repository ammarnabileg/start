<?php
// Create table if not exists
$mysqli->query("CREATE TABLE IF NOT EXISTS pi_memberships (
  mem_id INT AUTO_INCREMENT PRIMARY KEY,
  mem_plan ENUM('monthly','lifetime') NOT NULL,
  mem_name VARCHAR(200) NOT NULL,
  mem_phone VARCHAR(50) NOT NULL,
  mem_email VARCHAR(200) NOT NULL,
  mem_profile_url VARCHAR(500),
  mem_status ENUM('pending','active','cancelled') DEFAULT 'pending',
  mem_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle status change
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

// Filters
$filter = $_GET['filter'] ?? 'all';
$where = $filter !== 'all' ? "WHERE mem_status='".pi_escape($filter)."'" : '';

// Counts
$cnt = [];
foreach (['pending','active','cancelled'] as $s) {
    $r = $mysqli->query("SELECT COUNT(*) c FROM pi_memberships WHERE mem_status='$s'");
    $cnt[$s] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}
$cnt['all'] = array_sum($cnt);

// Fetch
$list = [];
$r = $mysqli->query("SELECT * FROM pi_memberships $where ORDER BY mem_created DESC");
if ($r) while ($row = $r->fetch_assoc()) $list[] = $row;

$status_labels = ['pending'=>'قيد المراجعة','active'=>'نشطة','cancelled'=>'ملغاة'];
$status_colors = ['pending'=>'bg-yellow-100 text-yellow-700','active'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700'];
$plan_labels   = ['monthly'=>'شهري $90','lifetime'=>'مدى الحياة $99'];
$plan_colors   = ['monthly'=>'bg-blue-50 text-blue-600','lifetime'=>'bg-purple-50 text-purple-600'];
?>

<!-- Stat cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <?php
  $stats = [
    ['label'=>'إجمالي الطلبات','val'=>$cnt['all'],'icon'=>'fa-crown','color'=>'#8829C8'],
    ['label'=>'قيد المراجعة','val'=>$cnt['pending'],'icon'=>'fa-clock','color'=>'#d97706'],
    ['label'=>'عضويات نشطة','val'=>$cnt['active'],'icon'=>'fa-circle-check','color'=>'#16a34a'],
    ['label'=>'ملغاة','val'=>$cnt['cancelled'],'icon'=>'fa-ban','color'=>'#dc2626'],
  ];
  foreach ($stats as $s):
  ?>
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

<!-- Filter tabs -->
<div class="flex items-center gap-2 mb-5 flex-wrap">
  <?php
  $tabs = ['all'=>'الكل','pending'=>'قيد المراجعة','active'=>'نشطة','cancelled'=>'ملغاة'];
  foreach ($tabs as $key => $label):
    $active = $filter === $key;
  ?>
  <a href="admin.php?p=memberships&filter=<?= $key ?>"
    class="px-4 py-1.5 rounded-full text-sm font-bold transition <?= $active ? 'text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-purple-50' ?>"
    <?= $active ? 'style="background:linear-gradient(135deg,#8829C8,#5B1494);color:#fff;"' : '' ?>>
    <?= $label ?>
    <span style="font-size:11px;opacity:.8;">(<?= $cnt[$key] ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden;">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>الاسم</th>
        <th>الخطة</th>
        <th>الجوال</th>
        <th>البريد</th>
        <th>رابط البروفايل</th>
        <th>الحالة</th>
        <th>التاريخ</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($list)): ?>
      <tr><td colspan="9" style="text-align:center;padding:60px 20px;color:#9ca3af;">
        <i class="fa-solid fa-crown" style="font-size:40px;display:block;margin-bottom:12px;"></i>
        لا توجد طلبات عضوية
      </td></tr>
      <?php else: ?>
      <?php foreach ($list as $m): ?>
      <tr>
        <td style="color:#9ca3af;font-size:12px;">#<?= $m['mem_id'] ?></td>
        <td>
          <p style="font-weight:700;color:#111827;font-size:14px;"><?= htmlspecialchars($m['mem_name']) ?></p>
        </td>
        <td>
          <span style="padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;" class="<?= $plan_colors[$m['mem_plan']] ?>">
            <?= $plan_labels[$m['mem_plan']] ?>
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
          <span style="padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;" class="<?= $status_colors[$m['mem_status']] ?>">
            <?= $status_labels[$m['mem_status']] ?>
          </span>
        </td>
        <td style="font-size:12px;color:#9ca3af;white-space:nowrap;"><?= date('Y/m/d', strtotime($m['mem_created'])) ?></td>
        <td>
          <div style="display:flex;gap:6px;align-items:center;">
            <!-- Change status -->
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
            <!-- Delete -->
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
