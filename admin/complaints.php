<?php
pi_require_login();
pi_require_perm('manage_complaints');
$action = $_GET['action'] ?? 'list';
$msg = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['cmp_id'] ?? 0);
    $status = in_array($_POST['cmp_status']??'', ['new','read','resolved']) ? pi_escape($_POST['cmp_status']) : '';
    if ($id && $status) {
        $mysqli->query("UPDATE pi_complaints SET cmp_status='$status' WHERE cmp_id=$id");
        $msg = 'تم تحديث الحالة';
    }
    if (!empty($_POST['delete_id'])) {
        $did = (int)$_POST['delete_id'];
        $mysqli->query("DELETE FROM pi_complaints WHERE cmp_id=$did");
        $msg = 'تم الحذف';
    }
}

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type']   ?? '';
$search        = pi_escape($_GET['q'] ?? '');

$where = "WHERE 1";
if ($filter_status) $where .= " AND cmp_status='" . pi_escape($filter_status) . "'";
if ($filter_type)   $where .= " AND cmp_type='"   . pi_escape($filter_type)   . "'";
if ($search)        $where .= " AND (cmp_subject LIKE '%$search%' OR cmp_name LIKE '%$search%' OR cmp_email LIKE '%$search%')";

$per_page = 20;
$page = max(1,(int)($_GET['page']??1));
$offset = ($page-1)*$per_page;

$r = $mysqli->query("SELECT COUNT(*) c FROM pi_complaints $where");
$total = $r ? (int)$r->fetch_assoc()['c'] : 0;
$total_pages = max(1,ceil($total/$per_page));

$complaints = [];
$r = $mysqli->query("SELECT c.*, u.u_name FROM pi_complaints c LEFT JOIN pi_users u ON c.cmp_user_id=u.u_id $where ORDER BY c.cmp_id DESC LIMIT $per_page OFFSET $offset");
if ($r) while ($row=$r->fetch_assoc()) $complaints[] = $row;

// Stats
$stats = ['new'=>0,'read'=>0,'resolved'=>0];
$rs = $mysqli->query("SELECT cmp_status, COUNT(*) c FROM pi_complaints GROUP BY cmp_status");
if ($rs) while ($row=$rs->fetch_assoc()) $stats[$row['cmp_status']] = (int)$row['c'];

$type_labels  = ['complaint'=>'شكوى','suggestion'=>'اقتراح','feedback'=>'ملاحظة','request'=>'طلب'];
$status_map   = ['new'=>['text'=>'جديدة','class'=>'bg-yellow-100 text-yellow-800'],
                 'read'=>['text'=>'مقروءة','class'=>'bg-blue-100 text-blue-800'],
                 'resolved'=>['text'=>'محلولة','class'=>'bg-green-100 text-green-800']];

// View single complaint
$view = null;
if ($action === 'view' && isset($_GET['id'])) {
    $vid = (int)$_GET['id'];
    $rv = $mysqli->query("SELECT c.*, u.u_name FROM pi_complaints c LEFT JOIN pi_users u ON c.cmp_user_id=u.u_id WHERE c.cmp_id=$vid");
    if ($rv && $rv->num_rows) {
        $view = $rv->fetch_assoc();
        if ($view['cmp_status'] === 'new') {
            $mysqli->query("UPDATE pi_complaints SET cmp_status='read' WHERE cmp_id=$vid");
            $view['cmp_status'] = 'read';
        }
    }
}
?>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-black text-gray-800">الشكاوي والملاحظات</h1>
    <span class="bg-purple-100 text-purple-700 text-sm font-black px-3 py-1 rounded-full"><?= number_format($total) ?> رسالة</span>
  </div>

  <?php if ($msg): ?>
  <div class="bg-green-50 border border-green-200 rounded-xl p-3 text-green-700 text-sm font-semibold">
    <i class="fa-solid fa-circle-check ml-2"></i><?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="grid grid-cols-3 gap-4">
    <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 text-center">
      <p class="text-3xl font-black text-yellow-700"><?= $stats['new'] ?></p>
      <p class="text-yellow-600 text-sm font-semibold mt-1">جديدة</p>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-center">
      <p class="text-3xl font-black text-blue-700"><?= $stats['read'] ?></p>
      <p class="text-blue-600 text-sm font-semibold mt-1">مقروءة</p>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-2xl p-4 text-center">
      <p class="text-3xl font-black text-green-700"><?= $stats['resolved'] ?></p>
      <p class="text-green-600 text-sm font-semibold mt-1">محلولة</p>
    </div>
  </div>

  <?php if ($view): ?>
  <!-- SINGLE VIEW -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <div class="flex items-center gap-3 mb-6">
      <a href="admin.php?p=complaints" class="text-gray-400 hover:text-gray-600">
        <i class="fa-solid fa-arrow-right text-lg"></i>
      </a>
      <h2 class="font-black text-gray-800 text-lg flex-1"><?= htmlspecialchars($view['cmp_subject']) ?></h2>
      <span class="px-3 py-1 text-xs font-black rounded-full <?= $status_map[$view['cmp_status']]['class'] ?>">
        <?= $status_map[$view['cmp_status']]['text'] ?>
      </span>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
      <div><span class="text-gray-400 font-semibold">المُرسِل: </span><span class="font-bold text-gray-800"><?= htmlspecialchars($view['cmp_name']) ?></span></div>
      <div><span class="text-gray-400 font-semibold">البريد: </span><span class="font-bold text-gray-800 dir-ltr"><?= htmlspecialchars($view['cmp_email']) ?></span></div>
      <div><span class="text-gray-400 font-semibold">النوع: </span><span class="font-bold text-gray-800"><?= $type_labels[$view['cmp_type']] ?? $view['cmp_type'] ?></span></div>
      <div><span class="text-gray-400 font-semibold">التاريخ: </span><span class="font-bold text-gray-800"><?= date('Y/m/d H:i', strtotime($view['cmp_created'])) ?></span></div>
      <?php if ($view['u_name']): ?>
      <div><span class="text-gray-400 font-semibold">الحساب: </span><span class="font-bold text-purple-600"><?= htmlspecialchars($view['u_name']) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="bg-gray-50 rounded-xl p-4 mb-6 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">
      <?= htmlspecialchars($view['cmp_message']) ?>
    </div>

    <div class="flex items-center gap-3">
      <form method="POST" class="flex gap-2 flex-wrap">
        <input type="hidden" name="cmp_id" value="<?= $view['cmp_id'] ?>">
        <?php foreach (['new'=>'جديدة','read'=>'مقروءة','resolved'=>'محلولة'] as $sv => $sl): ?>
        <button type="submit" name="cmp_status" value="<?= $sv ?>"
          class="px-4 py-2 text-xs font-black rounded-xl border transition <?= $view['cmp_status'] === $sv ? 'pi-primary-bg text-white border-transparent' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
          <?= $sl ?>
        </button>
        <?php endforeach; ?>
      </form>
      <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
        <input type="hidden" name="delete_id" value="<?= $view['cmp_id'] ?>">
        <button type="submit" class="px-4 py-2 text-xs font-black rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition">
          <i class="fa-solid fa-trash ml-1"></i> حذف
        </button>
      </form>
      <a href="mailto:<?= htmlspecialchars($view['cmp_email']) ?>"
        class="px-4 py-2 text-xs font-black rounded-xl border border-purple-200 text-purple-600 hover:bg-purple-50 transition">
        <i class="fa-solid fa-envelope ml-1"></i> رد بالبريد
      </a>
    </div>
  </div>

  <?php else: ?>
  <!-- LIST VIEW -->

  <!-- Filters -->
  <form method="GET" action="admin.php" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3 items-center">
    <input type="hidden" name="p" value="complaints">
    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>" placeholder="بحث..."
      class="flex-1 min-w-40 border border-gray-200 rounded-xl px-4 py-2 text-sm outline-none focus:border-purple-400">
    <select name="status" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
      <option value="">كل الحالات</option>
      <option value="new" <?= $filter_status==='new'?'selected':'' ?>>جديدة</option>
      <option value="read" <?= $filter_status==='read'?'selected':'' ?>>مقروءة</option>
      <option value="resolved" <?= $filter_status==='resolved'?'selected':'' ?>>محلولة</option>
    </select>
    <select name="type" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
      <option value="">كل الأنواع</option>
      <?php foreach ($type_labels as $tk => $tl): ?>
      <option value="<?= $tk ?>" <?= $filter_type===$tk?'selected':'' ?>><?= $tl ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="px-5 py-2 pi-primary-bg text-white font-bold rounded-xl text-sm hover:opacity-90 transition">فلتر</button>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($complaints)): ?>
    <div class="text-center py-16 text-gray-400">
      <i class="fa-solid fa-inbox text-5xl mb-4"></i>
      <p class="font-semibold">لا توجد رسائل</p>
    </div>
    <?php else: ?>
    <table class="w-full">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-right px-4 py-3 text-xs font-black text-gray-500">#</th>
          <th class="text-right px-4 py-3 text-xs font-black text-gray-500">المُرسِل</th>
          <th class="text-right px-4 py-3 text-xs font-black text-gray-500">الموضوع</th>
          <th class="text-right px-4 py-3 text-xs font-black text-gray-500">النوع</th>
          <th class="text-right px-4 py-3 text-xs font-black text-gray-500">الحالة</th>
          <th class="text-right px-4 py-3 text-xs font-black text-gray-500">التاريخ</th>
          <th class="text-right px-4 py-3 text-xs font-black text-gray-500"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($complaints as $c): ?>
        <tr class="hover:bg-gray-50 transition <?= $c['cmp_status']==='new' ? 'font-semibold' : '' ?>">
          <td class="px-4 py-3 text-sm text-gray-400"><?= $c['cmp_id'] ?></td>
          <td class="px-4 py-3">
            <p class="text-sm text-gray-800 font-semibold"><?= htmlspecialchars($c['cmp_name']) ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($c['cmp_email']) ?></p>
          </td>
          <td class="px-4 py-3 text-sm text-gray-700 max-w-48">
            <p class="truncate"><?= htmlspecialchars($c['cmp_subject']) ?></p>
          </td>
          <td class="px-4 py-3">
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-bold">
              <?= $type_labels[$c['cmp_type']] ?? $c['cmp_type'] ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $status_map[$c['cmp_status']]['class'] ?>">
              <?= $status_map[$c['cmp_status']]['text'] ?>
            </span>
          </td>
          <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
            <?= date('Y/m/d', strtotime($c['cmp_created'])) ?>
          </td>
          <td class="px-4 py-3">
            <a href="admin.php?p=complaints&action=view&id=<?= $c['cmp_id'] ?>"
              class="px-3 py-1.5 text-xs font-bold text-purple-600 border border-purple-200 rounded-lg hover:bg-purple-50 transition whitespace-nowrap">
              عرض
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-center gap-2 py-4 border-t border-gray-100">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
      <a href="admin.php?p=complaints&page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&type=<?= urlencode($filter_type) ?>&q=<?= urlencode($_GET['q']??'') ?>"
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
