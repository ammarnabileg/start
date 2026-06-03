<?php
pi_require_perm('manage_countries');
$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_country') {
        $id    = (int)($_POST['c_id'] ?? 0);
        $name  = pi_escape($_POST['c_name'] ?? '');
        $flag  = pi_escape($_POST['c_flag'] ?? '');
        $code  = pi_escape($_POST['c_code'] ?? '');
        $order = (int)($_POST['c_order'] ?? 0);

        if ($id) {
            $mysqli->query("UPDATE pi_countries SET c_name='$name',c_flag='$flag',c_code='$code',c_order=$order WHERE c_id=$id");
        } else {
            $mysqli->query("INSERT INTO pi_countries (c_name,c_flag,c_code,c_order) VALUES ('$name','$flag','$code',$order)");
        }
        $msg = 'تم حفظ الدولة بنجاح';
        $action = 'list';
    }

    if ($act === 'toggle_country') {
        $id = (int)($_POST['c_id'] ?? 0);
        $mysqli->query("UPDATE pi_countries SET c_active=!c_active WHERE c_id=$id");
    }

    if ($act === 'delete_country') {
        $id = (int)($_POST['c_id'] ?? 0);
        $mysqli->query("DELETE FROM pi_countries WHERE c_id=$id");
        $msg = 'تم الحذف';
    }
}

if ($action === 'add' || $action === 'edit') {
    $ec = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_countries WHERE c_id=$eid");
        if ($r && $r->num_rows) $ec = $r->fetch_assoc();
    }

    // Preset countries for quick add
    $presets = [
        ['🇸🇦','السعودية','sa'],['🇪🇬','مصر','eg'],['🇦🇪','الإمارات','ae'],
        ['🇰🇼','الكويت','kw'],['🇧🇭','البحرين','bh'],['🇴🇲','عمان','om'],
        ['🇶🇦','قطر','qa'],['🇸🇾','سوريا','sy'],['🇮🇶','العراق','iq'],
        ['🇯🇴','الأردن','jo'],['🇱🇧','لبنان','lb'],['🇲🇦','المغرب','ma'],
        ['🇹🇳','تونس','tn'],['🇩🇿','الجزائر','dz'],['🇱🇾','ليبيا','ly'],
        ['🇾🇪','اليمن','ye'],['🇸🇩','السودان','sd'],
    ];
?>
<div class="max-w-2xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=countries" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة دولة':'تعديل الدولة' ?></h2>
  </div>

  <?php if ($action === 'add'): ?>
  <!-- Quick presets -->
  <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-5">
    <p class="text-blue-700 font-bold text-sm mb-3">إضافة سريعة — اضغط على الدولة:</p>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($presets as $pr): ?>
      <button type="button"
        onclick="document.querySelector('[name=c_flag]').value='<?= $pr[0] ?>';document.querySelector('[name=c_name]').value='<?= $pr[1] ?>';document.querySelector('[name=c_code]').value='<?= $pr[2] ?>'"
        class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-blue-200 rounded-lg text-sm font-semibold hover:bg-blue-100 transition">
        <?= $pr[0] ?> <?= $pr[1] ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_country">
    <?php if ($ec): ?><input type="hidden" name="c_id" value="<?= $ec['c_id'] ?>"><?php endif; ?>

    <div class="grid grid-cols-2 gap-5">
      <div>
        <label class="form-label">العلم (Emoji) <span class="text-red-500">*</span></label>
        <input type="text" name="c_flag" required class="form-input text-2xl" placeholder="🇸🇦"
          value="<?= htmlspecialchars($ec['c_flag'] ?? '') ?>">
        <p class="text-xs text-gray-400 mt-1">الصق emoji العلم هنا</p>
      </div>
      <div>
        <label class="form-label">اسم الدولة <span class="text-red-500">*</span></label>
        <input type="text" name="c_name" required class="form-input" placeholder="السعودية"
          value="<?= htmlspecialchars($ec['c_name'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">كود الدولة</label>
        <input type="text" name="c_code" class="form-input" dir="ltr" placeholder="sa"
          value="<?= htmlspecialchars($ec['c_code'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">الترتيب</label>
        <input type="number" name="c_order" class="form-input" value="<?= $ec['c_order'] ?? 0 ?>">
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="btn-primary flex items-center gap-2">
        <i class="fa-solid fa-floppy-disk"></i> حفظ
      </button>
      <a href="admin.php?p=countries" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<?php } else { // LIST
$countries = [];
$r = $mysqli->query("SELECT c.*,
    (SELECT COUNT(*) FROM pi_personalities WHERE p_country_id=c.c_id AND p_active=1) as p_count,
    (SELECT COUNT(*) FROM pi_institutions WHERE inst_country_id=c.c_id AND inst_active=1) as inst_count
    FROM pi_countries c ORDER BY c.c_order,c.c_id");
if ($r) while ($row=$r->fetch_assoc()) $countries[] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">إدارة الدول</h2>
    <p class="text-gray-400 text-sm mt-0.5">تحكم في الدول الظاهرة في فلتر الموقع</p>
  </div>
  <a href="admin.php?p=countries&action=add" class="btn-primary flex items-center gap-2">
    <i class="fa-solid fa-plus"></i> إضافة دولة
  </a>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead>
      <tr>
        <th>الدولة</th>
        <th>الشخصيات</th>
        <th>المؤسسات</th>
        <th>الترتيب</th>
        <th>الحالة</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($countries as $c): ?>
      <tr class="hover:bg-gray-50 transition">
        <td>
          <div class="flex items-center gap-3">
            <span class="text-2xl"><?= htmlspecialchars($c['c_flag']) ?></span>
            <div>
              <p class="font-bold text-gray-800"><?= htmlspecialchars($c['c_name']) ?></p>
              <p class="text-gray-400 text-xs" dir="ltr"><?= htmlspecialchars($c['c_code']) ?></p>
            </div>
          </div>
        </td>
        <td>
          <a href="admin.php?p=personalities&country=<?= $c['c_id'] ?>"
            class="font-bold text-blue-600 hover:underline"><?= $c['p_count'] ?></a>
        </td>
        <td>
          <span class="font-bold text-indigo-600"><?= $c['inst_count'] ?></span>
        </td>
        <td class="text-gray-500"><?= $c['c_order'] ?></td>
        <td>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="toggle_country">
            <input type="hidden" name="c_id" value="<?= $c['c_id'] ?>">
            <button type="submit"
              class="<?= $c['c_active']?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> px-3 py-1 rounded-full text-xs font-bold hover:opacity-80 transition">
              <?= $c['c_active']?'نشطة':'معطلة' ?>
            </button>
          </form>
        </td>
        <td>
          <div class="flex items-center gap-2">
            <a href="admin.php?p=countries&action=edit&id=<?= $c['c_id'] ?>"
              class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-orange-50 hover:text-orange-500 transition">
              <i class="fa-solid fa-pen text-xs"></i>
            </a>
            <form method="POST" onsubmit="return confirm('حذف الدولة نهائياً؟')">
              <input type="hidden" name="action" value="delete_country">
              <input type="hidden" name="c_id" value="<?= $c['c_id'] ?>">
              <button type="submit"
                class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($countries)): ?>
      <tr><td colspan="6" class="text-center py-12 text-gray-400">
        <i class="fa-solid fa-globe text-4xl mb-3 block"></i>
        لا توجد دول مضافة بعد
      </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php } ?>
