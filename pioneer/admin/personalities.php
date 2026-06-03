<?php
pi_require_perm('view_personalities');

$action = $_GET['action'] ?? 'list';
$msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_personality') {
        $id        = (int)($_POST['p_id'] ?? 0);
        $name_ar   = pi_escape($_POST['p_name_ar'] ?? '');
        $name_en   = pi_escape($_POST['p_name_en'] ?? '');
        $title     = pi_escape($_POST['p_title'] ?? '');
        $national  = pi_escape($_POST['p_nationality'] ?? '');
        $residence = pi_escape($_POST['p_residence'] ?? '');
        $bio       = pi_escape($_POST['p_bio'] ?? '');
        $bio_plat  = pi_escape($_POST['p_bio_platform'] ?? '');
        $photo     = pi_escape($_POST['p_photo'] ?? '');
        $verified  = (int)($_POST['p_verified'] ?? 0);
        $mtype     = pi_escape($_POST['p_membership_type'] ?? 'standard');
        $cats      = $_POST['categories'] ?? [];

        if ($id) {
            pi_require_perm('edit_personality');
            $mysqli->query("UPDATE pi_personalities SET p_name_ar='$name_ar',p_name_en='$name_en',p_title='$title',p_nationality='$national',p_residence='$residence',p_bio='$bio',p_bio_platform='$bio_plat',p_photo='$photo',p_verified=$verified,p_membership_type='$mtype' WHERE p_id=$id");
            $mysqli->query("DELETE FROM pi_personality_categories WHERE p_id=$id");
        } else {
            pi_require_perm('add_personality');
            $mysqli->query("INSERT INTO pi_personalities (p_name_ar,p_name_en,p_title,p_nationality,p_residence,p_bio,p_bio_platform,p_photo,p_verified,p_membership_type) VALUES ('$name_ar','$name_en','$title','$national','$residence','$bio','$bio_plat','$photo',$verified,'$mtype')");
            $id = $mysqli->insert_id;
        }
        foreach ($cats as $cat_id) {
            $cat_id = (int)$cat_id;
            $mysqli->query("INSERT INTO pi_personality_categories (p_id,cat_id) VALUES ($id,$cat_id)");
        }
        $msg = 'تم الحفظ بنجاح';
        $action = 'list';
    }

    if ($act === 'delete_personality') {
        pi_require_perm('delete_personality');
        $id = (int)($_POST['p_id'] ?? 0);
        $mysqli->query("UPDATE pi_personalities SET p_active=0 WHERE p_id=$id");
        $msg = 'تم الحذف';
    }

    if ($act === 'toggle_verify') {
        pi_require_perm('edit_personality');
        $id = (int)($_POST['p_id'] ?? 0);
        $mysqli->query("UPDATE pi_personalities SET p_verified=!p_verified WHERE p_id=$id");
    }
}

$all_cats = pi_get_categories();
$filter = $_GET['filter'] ?? '';
$search = pi_escape($_GET['q'] ?? '');

if ($action === 'add' || $action === 'edit') {
    $edit_p = null;
    $edit_cats = [];
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_id=$eid");
        if ($r && $r->num_rows) $edit_p = $r->fetch_assoc();
        $r = $mysqli->query("SELECT cat_id FROM pi_personality_categories WHERE p_id=$eid");
        if ($r) while ($row=$r->fetch_assoc()) $edit_cats[] = $row['cat_id'];
    }
?>
<!-- Add/Edit form -->
<div class="max-w-3xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=personalities" class="text-gray-400 hover:text-gray-600 transition">
      <i class="fa-solid fa-arrow-right text-lg"></i>
    </a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة شخصية جديدة':'تعديل الشخصية' ?></h2>
  </div>

  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_personality">
    <?php if ($edit_p): ?><input type="hidden" name="p_id" value="<?= $edit_p['p_id'] ?>"><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="form-label">الاسم بالعربي <span class="text-red-500">*</span></label>
        <input type="text" name="p_name_ar" required class="form-input"
          value="<?= htmlspecialchars($edit_p['p_name_ar'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">الاسم بالإنجليزي</label>
        <input type="text" name="p_name_en" class="form-input" dir="ltr"
          value="<?= htmlspecialchars($edit_p['p_name_en'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">المسمى الوظيفي</label>
        <input type="text" name="p_title" class="form-input"
          value="<?= htmlspecialchars($edit_p['p_title'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">الجنسية</label>
        <input type="text" name="p_nationality" class="form-input"
          value="<?= htmlspecialchars($edit_p['p_nationality'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">بلد الإقامة</label>
        <input type="text" name="p_residence" class="form-input"
          value="<?= htmlspecialchars($edit_p['p_residence'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">رابط الصورة</label>
        <input type="url" name="p_photo" class="form-input" dir="ltr"
          value="<?= htmlspecialchars($edit_p['p_photo'] ?? '') ?>">
      </div>
      <div>
        <label class="form-label">نوع العضوية</label>
        <select name="p_membership_type" class="form-input">
          <option value="standard" <?= ($edit_p['p_membership_type']??'standard')==='standard'?'selected':'' ?>>عادية</option>
          <option value="verified" <?= ($edit_p['p_membership_type']??'')==='verified'?'selected':'' ?>>موثقة</option>
          <option value="executive" <?= ($edit_p['p_membership_type']??'')==='executive'?'selected':'' ?>>رئيس تنفيذي</option>
        </select>
      </div>
      <div class="flex items-center gap-3 mt-6">
        <input type="checkbox" name="p_verified" value="1" id="verified"
          <?= ($edit_p['p_verified']??0)?'checked':'' ?> class="w-5 h-5 accent-blue-500">
        <label for="verified" class="font-bold text-gray-700 text-sm flex items-center gap-1.5">
          <i class="fa-solid fa-circle-check text-blue-500"></i> موثقة
        </label>
      </div>
    </div>

    <div>
      <label class="form-label">السيرة الذاتية من المنصة</label>
      <textarea name="p_bio_platform" rows="3" class="form-input resize-y"><?= htmlspecialchars($edit_p['p_bio_platform'] ?? '') ?></textarea>
    </div>
    <div>
      <label class="form-label">السيرة الذاتية الكاملة</label>
      <textarea name="p_bio" rows="6" class="form-input resize-y"><?= htmlspecialchars($edit_p['p_bio'] ?? '') ?></textarea>
    </div>

    <div>
      <label class="form-label">التصنيفات</label>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-4 border border-gray-200 rounded-xl">
        <?php foreach ($all_cats as $cat): ?>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="categories[]" value="<?= $cat['cat_id'] ?>"
            <?= in_array($cat['cat_id'], $edit_cats)?'checked':'' ?> class="accent-orange-500">
          <span class="text-sm text-gray-700"><?= htmlspecialchars($cat['cat_name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="btn-primary flex items-center gap-2">
        <i class="fa-solid fa-floppy-disk"></i> حفظ الشخصية
      </button>
      <a href="admin.php?p=personalities" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<?php } else { // LIST view
$where = "p_active=1";
if ($search) $where .= " AND p_name_ar LIKE '%$search%'";
if ($filter === 'verified') $where .= " AND p_verified=1";
if ($filter === 'executive') $where .= " AND p_membership_type='executive'";

$page_num = max(1,(int)($_GET['page']??1));
$per_page = 20;
$offset   = ($page_num-1)*$per_page;
$total    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE $where")->fetch_assoc()['c'];
$total_pages = max(1,ceil($total/$per_page));

$list = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE $where ORDER BY p_created DESC LIMIT $offset,$per_page");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm flex items-center gap-2">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="flex flex-wrap items-center gap-3 mb-6">
  <?php if (pi_has_perm('add_personality')): ?>
  <a href="admin.php?p=personalities&action=add" class="btn-primary flex items-center gap-2">
    <i class="fa-solid fa-user-plus"></i> إضافة شخصية
  </a>
  <?php endif; ?>
  <form method="GET" class="flex gap-2 flex-1 max-w-sm">
    <input type="hidden" name="p" value="personalities">
    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>"
      placeholder="بحث عن شخصية..."
      class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-400 transition">
    <button type="submit" class="btn-primary py-2.5">بحث</button>
  </form>
  <div class="flex gap-2">
    <a href="admin.php?p=personalities" class="px-4 py-2 text-sm font-bold rounded-xl transition <?= !$filter?'bg-orange-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">الكل</a>
    <a href="admin.php?p=personalities&filter=verified" class="px-4 py-2 text-sm font-bold rounded-xl transition <?= $filter==='verified'?'bg-blue-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">الموثقون</a>
    <a href="admin.php?p=personalities&filter=executive" class="px-4 py-2 text-sm font-bold rounded-xl transition <?= $filter==='executive'?'bg-yellow-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">الرؤساء التنفيذيون</a>
  </div>
  <span class="text-gray-400 text-sm mr-auto"><?= number_format($total) ?> شخصية</span>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead>
      <tr>
        <th>الشخصية</th>
        <th>المسمى</th>
        <th>العضوية</th>
        <th>التوثيق</th>
        <th>الزيارات</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $row): ?>
      <tr class="hover:bg-gray-50 transition">
        <td>
          <div class="flex items-center gap-3">
            <?php if ($row['p_photo']): ?>
              <img src="<?= htmlspecialchars($row['p_photo']) ?>" class="w-9 h-9 rounded-full object-cover">
            <?php else: ?>
              <div class="w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xs">
                <?= mb_substr($row['p_name_ar'],0,1) ?>
              </div>
            <?php endif; ?>
            <div>
              <p class="font-bold text-gray-800"><?= htmlspecialchars($row['p_name_ar']) ?></p>
              <?php if ($row['p_name_en']): ?><p class="text-gray-400 text-xs" dir="ltr"><?= htmlspecialchars($row['p_name_en']) ?></p><?php endif; ?>
            </div>
          </div>
        </td>
        <td class="text-gray-500"><?= htmlspecialchars($row['p_title'] ?? '—') ?></td>
        <td>
          <?php
          $mt_labels = ['standard'=>['bg-gray-100 text-gray-600','عادية'],'verified'=>['bg-blue-100 text-blue-700','موثقة'],'executive'=>['bg-yellow-100 text-yellow-700','رئيس تنفيذي']];
          $mt = $mt_labels[$row['p_membership_type']] ?? $mt_labels['standard'];
          ?>
          <span class="px-2.5 py-1 <?= $mt[0] ?> rounded-full text-xs font-bold"><?= $mt[1] ?></span>
        </td>
        <td>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="toggle_verify">
            <input type="hidden" name="p_id" value="<?= $row['p_id'] ?>">
            <button type="submit" class="<?= $row['p_verified']?'text-blue-500 hover:text-blue-700':'text-gray-300 hover:text-blue-400' ?> transition text-xl">
              <i class="fa-solid fa-circle-check"></i>
            </button>
          </form>
        </td>
        <td class="font-semibold text-gray-600"><?= number_format($row['p_views']) ?></td>
        <td>
          <div class="flex items-center gap-2">
            <a href="../profile.php?id=<?= $row['p_id'] ?>" target="_blank"
              class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-blue-50 hover:text-blue-500 transition" title="عرض">
              <i class="fa-solid fa-eye text-xs"></i>
            </a>
            <?php if (pi_has_perm('edit_personality')): ?>
            <a href="admin.php?p=personalities&action=edit&id=<?= $row['p_id'] ?>"
              class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-orange-50 hover:text-orange-500 transition" title="تعديل">
              <i class="fa-solid fa-pen text-xs"></i>
            </a>
            <?php endif; ?>
            <?php if (pi_has_perm('delete_personality')): ?>
            <form method="POST" onsubmit="return confirm('تأكيد حذف الشخصية؟')">
              <input type="hidden" name="action" value="delete_personality">
              <input type="hidden" name="p_id" value="<?= $row['p_id'] ?>">
              <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition" title="حذف">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
      <tr><td colspan="6" class="text-center py-12 text-gray-400">
        <i class="fa-solid fa-users text-4xl mb-3 block"></i> لا توجد شخصيات
      </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-6">
  <?php for ($i=1; $i<=$total_pages; $i++): ?>
  <a href="admin.php?p=personalities&page=<?=$i?>&q=<?=urlencode($_GET['q']??'')?>&filter=<?=urlencode($filter)?>"
    class="w-9 h-9 flex items-center justify-center rounded-xl font-bold text-sm transition
      <?= $i==$page_num?'bg-orange-500 text-white':'bg-white text-gray-600 border border-gray-200 hover:bg-orange-50' ?>">
    <?=$i?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php } ?>
