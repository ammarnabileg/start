<?php
pi_require_perm('manage_advertise');
// Ensure table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS pi_advertise (
  adv_id INT AUTO_INCREMENT PRIMARY KEY,
  adv_company VARCHAR(200) NOT NULL,
  adv_contact VARCHAR(200) NOT NULL,
  adv_phone VARCHAR(50) NOT NULL,
  adv_email VARCHAR(200) NOT NULL,
  adv_note TEXT,
  adv_status ENUM('new','contacted','done') DEFAULT 'new',
  adv_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'update_status') {
    $id     = (int)($_POST['adv_id'] ?? 0);
    $status = pi_escape($_POST['adv_status'] ?? 'new');
    $mysqli->query("UPDATE pi_advertise SET adv_status='$status' WHERE adv_id=$id");
    $msg = 'تم تحديث الحالة';
}

$status_filter = $_GET['status'] ?? 'all';
$where = $status_filter !== 'all' ? "WHERE adv_status='".pi_escape($status_filter)."'" : '';
$list = [];
$r = $mysqli->query("SELECT * FROM pi_advertise $where ORDER BY adv_created DESC");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;

$counts = ['all'=>0,'new'=>0,'contacted'=>0,'done'=>0];
$rc = $mysqli->query("SELECT adv_status, COUNT(*) c FROM pi_advertise GROUP BY adv_status");
if ($rc) while ($row=$rc->fetch_assoc()) { $counts[$row['adv_status']] = $row['c']; $counts['all'] += $row['c']; }
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">طلبات الإعلان</h2>
    <p class="text-gray-400 text-sm mt-0.5">الشركات المهتمة بالإعلان على الموقع</p>
  </div>
  <div class="flex gap-2">
    <?php foreach (['all'=>'الكل','new'=>'جديد','contacted'=>'تم التواصل','done'=>'منتهي'] as $k=>$v): ?>
    <a href="admin.php?p=advertise&status=<?= $k ?>"
      class="px-3 py-1.5 text-xs font-bold rounded-full transition <?= $status_filter===$k ? 'text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-purple-50' ?>"
      <?= $status_filter===$k ? 'style="background:linear-gradient(135deg,#8829C8,#5B1494)"' : '' ?>>
      <?= $v ?> (<?= $counts[$k] ?>)
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($list)): ?>
<div class="bg-white rounded-2xl shadow-sm p-12 text-center text-gray-400">
  <i class="fa-solid fa-bullhorn text-5xl mb-4 block"></i>
  <p class="font-bold">لا توجد طلبات إعلان بعد</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead>
      <tr>
        <th>الشركة</th><th>المسؤول</th><th>التواصل</th><th>الملاحظات</th><th>التاريخ</th><th>الحالة</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $adv):
        $badge = ['new'=>['bg-blue-100 text-blue-700','جديد'],'contacted'=>['bg-yellow-100 text-yellow-700','تم التواصل'],'done'=>['bg-green-100 text-green-700','منتهي']];
        [$bc,$bl] = $badge[$adv['adv_status']] ?? ['bg-gray-100 text-gray-600','—'];
      ?>
      <tr class="hover:bg-gray-50">
        <td class="font-bold text-gray-800"><?= htmlspecialchars($adv['adv_company']) ?></td>
        <td><?= htmlspecialchars($adv['adv_contact']) ?></td>
        <td>
          <p class="text-sm font-semibold" dir="ltr"><?= htmlspecialchars($adv['adv_phone']) ?></p>
          <a href="mailto:<?= htmlspecialchars($adv['adv_email']) ?>" class="text-xs text-purple-600 hover:underline" dir="ltr"><?= htmlspecialchars($adv['adv_email']) ?></a>
        </td>
        <td class="text-gray-500 text-xs max-w-xs"><?= htmlspecialchars(mb_substr($adv['adv_note']??'',0,80)) ?><?= mb_strlen($adv['adv_note']??'')>80?'...':'' ?></td>
        <td class="text-gray-400 text-xs"><?= date('d/m/Y', strtotime($adv['adv_created'])) ?></td>
        <td>
          <form method="POST" class="flex items-center gap-2">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="adv_id" value="<?= $adv['adv_id'] ?>">
            <select name="adv_status" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded-lg px-2 py-1 outline-none focus:border-purple-400">
              <option value="new" <?= $adv['adv_status']==='new'?'selected':'' ?>>جديد</option>
              <option value="contacted" <?= $adv['adv_status']==='contacted'?'selected':'' ?>>تم التواصل</option>
              <option value="done" <?= $adv['adv_status']==='done'?'selected':'' ?>>منتهي</option>
            </select>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
