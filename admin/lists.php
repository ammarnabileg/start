<?php
pi_require_any_perm('manage_lists','view_lists','add_list','edit_list','delete_list');

// Migrate: add list_sponsors_json column if missing
try { $mysqli->query("ALTER TABLE pi_lists ADD COLUMN list_sponsors_json TEXT DEFAULT NULL"); } catch(Exception $e) { /* already exists */ }

$msg      = '';
$msg_type = 'green';
$edit_id  = isset($_GET['edit']) ? (trim($_GET['edit']) === 'new' ? 'new' : (int)$_GET['edit']) : null;

// ── AJAX handlers (output JSON and exit) ──────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_GET['ajax'] === 'search_entities') {
        $q = pi_escape(trim($_GET['q'] ?? ''));
        $results = [];
        if (strlen($q) >= 1) {
            $r = $mysqli->query("SELECT p_id AS id, 'personality' AS type, p_name_ar AS name, p_photo AS photo, p_title AS subtitle
                FROM pi_personalities WHERE p_active=1 AND (p_name_ar LIKE '%$q%' OR p_name_en LIKE '%$q%') LIMIT 10");
            if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;

            $r = $mysqli->query("SELECT inst_id AS id, 'institution' AS type, inst_name_ar AS name, inst_logo AS photo, inst_description AS subtitle
                FROM pi_institutions WHERE inst_active=1 AND (inst_name_ar LIKE '%$q%' OR inst_name_en LIKE '%$q%') LIMIT 10");
            if ($r) while ($row = $r->fetch_assoc()) {
                $row['subtitle'] = mb_substr(strip_tags($row['subtitle'] ?? ''), 0, 80);
                $results[] = $row;
            }
        }
        echo json_encode($results);
        exit;
    }

    echo json_encode(['error' => 'unknown ajax action']);
    exit;
}

// ── POST actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── Save list ──────────────────────────────────────────────────────────
    if ($act === 'save_list') {
        $id          = (int)($_POST['list_id'] ?? 0);
        $title       = pi_escape(trim($_POST['list_title'] ?? ''));
        $title_en    = pi_escape(trim($_POST['list_title_en'] ?? ''));
        $year        = pi_escape(trim($_POST['list_year'] ?? ''));
        $description = pi_escape(trim($_POST['list_description'] ?? ''));
        $criteria    = pi_escape(trim($_POST['list_criteria'] ?? ''));
        $active      = (int)(!empty($_POST['list_active']));
        $order       = (int)($_POST['list_order'] ?? 0);

        // Slug
        $raw_slug = trim($_POST['list_slug'] ?? '');
        if (!$raw_slug) {
            $raw_slug = mb_strtolower(trim($_POST['list_title'] ?? ''));
            $raw_slug = preg_replace('/\s+/', '-', $raw_slug);
            $raw_slug = preg_replace('/[^\p{L}\p{N}\-]/u', '', $raw_slug);
        }
        $slug = pi_escape(mb_substr($raw_slug, 0, 200));

        // Cover upload
        $cover = pi_escape(trim($_POST['list_cover_keep'] ?? ''));
        if (!empty($_FILES['list_cover_file']['name']) && $_FILES['list_cover_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['list_cover_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $udir = dirname(__DIR__).'/uploads/';
                if (!is_dir($udir)) mkdir($udir, 0755, true);
                $fname = 'lc_'.time().'_'.rand(100,999).'.'.$ext;
                if (move_uploaded_file($_FILES['list_cover_file']['tmp_name'], $udir.$fname))
                    $cover = pi_escape('uploads/'.$fname);
            }
        }

        // Logo upload
        $logo = pi_escape(trim($_POST['list_logo_keep'] ?? ''));
        if (!empty($_FILES['list_logo_file']['name']) && $_FILES['list_logo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['list_logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $udir = dirname(__DIR__).'/uploads/';
                if (!is_dir($udir)) mkdir($udir, 0755, true);
                $fname = 'll_'.time().'_'.rand(100,999).'.'.$ext;
                if (move_uploaded_file($_FILES['list_logo_file']['tmp_name'], $udir.$fname))
                    $logo = pi_escape('uploads/'.$fname);
            }
        }

        // Registered sponsor (from dropdown)
        $sponsor_id  = (int)($_POST['list_sponsor_id'] ?? 0);
        // Primary custom sponsor fields cleared — all go through csp_* json system
        $sponsor_url  = '';
        $sponsor_name = '';
        $sponsor_img  = '';

        // Multiple custom sponsors
        $custom_sponsors = [];
        $sp_names = $_POST['csp_name'] ?? [];
        $sp_urls  = $_POST['csp_url']  ?? [];
        $sp_keeps = $_POST['csp_img_keep'] ?? [];
        foreach ($sp_names as $si => $sp_name) {
            $sp_name = trim($sp_name);
            if (!$sp_name) continue;
            $sp_img_val = trim($sp_keeps[$si] ?? '');
            if (!empty($_FILES['csp_img']['name'][$si]) && $_FILES['csp_img']['error'][$si] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['csp_img']['name'][$si], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
                    $udir = dirname(__DIR__).'/uploads/';
                    if (!is_dir($udir)) mkdir($udir, 0755, true);
                    $fname = 'csp_'.time().'_'.rand(100,999).'.'.$ext;
                    if (move_uploaded_file($_FILES['csp_img']['tmp_name'][$si], $udir.$fname))
                        $sp_img_val = 'uploads/'.$fname;
                }
            }
            $custom_sponsors[] = ['name'=>$sp_name, 'url'=>trim($sp_urls[$si]??''), 'img'=>$sp_img_val];
        }
        $custom_sponsors_json = pi_escape(json_encode($custom_sponsors, JSON_UNESCAPED_UNICODE));

        // Spotlight: array of "type-id" strings
        $spotlight_raw = $_POST['list_spotlight'] ?? [];
        $spotlight_json = pi_escape(json_encode(array_values($spotlight_raw), JSON_UNESCAPED_UNICODE));

        // Columns JSON
        $cols_raw = $_POST['list_columns_json'] ?? '[]';
        $cols_decoded = json_decode($cols_raw, true);
        $cols_json = pi_escape(json_encode($cols_decoded ?: [], JSON_UNESCAPED_UNICODE));

        $sponsor_id_sql = $sponsor_id ? $sponsor_id : 'NULL';
        if ($id) {
            $mysqli->query("UPDATE pi_lists SET
                list_title='$title', list_title_en='$title_en', list_slug='$slug',
                list_description='$description', list_criteria='$criteria',
                list_cover='$cover', list_logo='$logo', list_year='$year',
                list_columns='$cols_json', list_active=$active, list_order=$order,
                list_sponsor_id=$sponsor_id_sql, list_sponsor_img='$sponsor_img',
                list_sponsor_url='$sponsor_url', list_sponsor_name='$sponsor_name',
                list_spotlight='$spotlight_json', list_sponsors_json='$custom_sponsors_json'
                WHERE list_id=$id");
        } else {
            $mysqli->query("INSERT INTO pi_lists (list_title,list_title_en,list_slug,list_description,list_criteria,list_cover,list_logo,list_year,list_columns,list_active,list_order,list_sponsor_id,list_sponsor_img,list_sponsor_url,list_sponsor_name,list_spotlight,list_sponsors_json)
                VALUES ('$title','$title_en','$slug','$description','$criteria','$cover','$logo','$year','$cols_json',$active,$order,$sponsor_id_sql,'$sponsor_img','$sponsor_url','$sponsor_name','$spotlight_json','$custom_sponsors_json')");
            $id = $mysqli->insert_id;
        }

        // Blocks: delete & re-insert
        $mysqli->query("DELETE FROM pi_list_blocks WHERE lb_list_id=$id");
        $block_types    = $_POST['block_type']    ?? [];
        $block_contents = $_POST['block_content'] ?? [];
        $block_orders   = $_POST['block_order']   ?? [];

        // Image block file uploads
        $uploaded_images = [];
        if (!empty($_FILES['block_image'])) {
            foreach ($_FILES['block_image']['name'] as $bi => $bname) {
                if ($_FILES['block_image']['error'][$bi] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($bname, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = dirname(__DIR__).'/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'lb_'.time().'_'.rand(100,999).'.'.$ext;
                        if (move_uploaded_file($_FILES['block_image']['tmp_name'][$bi], $udir.$fname))
                            $uploaded_images[$bi] = 'uploads/'.$fname;
                    }
                }
            }
        }

        foreach ($block_types as $bi => $btype) {
            $btype    = in_array($btype, ['text','image','video']) ? $btype : 'text';
            $bcontent = '';
            if ($btype === 'image' && isset($uploaded_images[$bi])) {
                $bcontent = $uploaded_images[$bi];
            } elseif ($btype === 'image') {
                $bcontent = trim($block_contents[$bi] ?? ''); // existing path (keep field)
            } else {
                $bcontent = $block_contents[$bi] ?? '';
            }
            $bcontent_esc = pi_escape($bcontent);
            $bord = (int)($block_orders[$bi] ?? $bi);
            $mysqli->query("INSERT INTO pi_list_blocks (lb_list_id,lb_type,lb_content,lb_order) VALUES ($id,'$btype','$bcontent_esc',$bord)");
        }

        // Items: delete & re-insert
        $mysqli->query("DELETE FROM pi_list_items WHERE li_list_id=$id");
        $item_types   = $_POST['item_entity_type'] ?? [];
        $item_ids     = $_POST['item_entity_id']   ?? [];
        $item_ranks   = $_POST['item_rank']         ?? [];
        $item_data_all= $_POST['item_data']         ?? [];

        foreach ($item_types as $ii => $itype) {
            $itype   = in_array($itype, ['personality','institution']) ? $itype : 'personality';
            $ieid    = (int)($item_ids[$ii] ?? 0);
            $irank   = (int)($item_ranks[$ii] ?? 0);
            $idata   = $item_data_all[$ii] ?? [];
            $idata_json = pi_escape(json_encode($idata, JSON_UNESCAPED_UNICODE));
            if ($ieid) {
                $mysqli->query("INSERT INTO pi_list_items (li_list_id,li_entity_type,li_entity_id,li_rank,li_data)
                    VALUES ($id,'$itype',$ieid,$irank,'$idata_json')");
            }
        }

        $msg = 'تم الحفظ بنجاح';
        $msg_type = 'green';
    }

    // ── Delete list ────────────────────────────────────────────────────────
    if ($act === 'delete_list') {
        $id = (int)($_POST['list_id'] ?? 0);
        if ($id) {
            $mysqli->query("DELETE FROM pi_list_items WHERE li_list_id=$id");
            $mysqli->query("DELETE FROM pi_list_blocks WHERE lb_list_id=$id");
            $mysqli->query("DELETE FROM pi_lists WHERE list_id=$id");
        }
        $msg = 'تم حذف القائمة';
        $msg_type = 'red';
    }

    // ── Toggle active ──────────────────────────────────────────────────────
    if ($act === 'toggle_active') {
        $id = (int)($_POST['list_id'] ?? 0);
        $mysqli->query("UPDATE pi_lists SET list_active=1-list_active WHERE list_id=$id");
        header('Location: admin.php?p=lists');
        exit;
    }
}

// ── Flash messages ─────────────────────────────────────────────────────────
if (isset($_GET['saved']))   { $msg = 'تم الحفظ بنجاح'; $msg_type = 'green'; }
if (isset($_GET['deleted'])) { $msg = 'تم حذف القائمة'; $msg_type = 'red'; }

// ══════════════════════════════════════════════════════════════════════════════
// EDIT/CREATE VIEW
// ══════════════════════════════════════════════════════════════════════════════
if ($edit_id !== null) {
    $list = null;
    $list_columns = [];
    $list_blocks  = [];
    $list_items   = [];

    if ($edit_id !== 'new' && $edit_id > 0) {
        $r = $mysqli->query("SELECT * FROM pi_lists WHERE list_id=$edit_id");
        if ($r && $r->num_rows) {
            $list = $r->fetch_assoc();
            $list_columns = json_decode($list['list_columns'] ?? '[]', true) ?: [];
        }
        $r = $mysqli->query("SELECT * FROM pi_list_blocks WHERE lb_list_id=$edit_id ORDER BY lb_order,lb_id");
        if ($r) while ($row = $r->fetch_assoc()) $list_blocks[] = $row;

        $r = $mysqli->query("SELECT * FROM pi_list_items WHERE li_list_id=$edit_id ORDER BY li_rank,li_id");
        if ($r) while ($row = $r->fetch_assoc()) {
            $row['li_data'] = json_decode($row['li_data'] ?? '{}', true) ?: [];
            // Load entity info
            if ($row['li_entity_type'] === 'personality') {
                $eid = (int)$row['li_entity_id'];
                $er = $mysqli->query("SELECT p_id,p_name_ar,p_photo,p_title,p_nationality,p_residence FROM pi_personalities WHERE p_id=$eid");
                $row['entity'] = ($er && $er->num_rows) ? $er->fetch_assoc() : null;
            } else {
                $eid = (int)$row['li_entity_id'];
                $er = $mysqli->query("SELECT inst_id,inst_name_ar,inst_logo,inst_country_id FROM pi_institutions WHERE inst_id=$eid");
                $row['entity'] = ($er && $er->num_rows) ? $er->fetch_assoc() : null;
            }
            $list_items[] = $row;
        }
    }

    // Load sponsors for dropdown
    $sponsors_list = [];
    $sr = $mysqli->query("SELECT sp_id, sp_name FROM pi_sponsors WHERE sp_active=1 ORDER BY sp_name");
    if ($sr) while ($srow = $sr->fetch_assoc()) $sponsors_list[] = $srow;

    // Parse spotlight selection
    $spotlight_selected = json_decode($list['list_spotlight'] ?? '[]', true) ?: [];

    $is_new = ($edit_id === 'new');
    $list_id_val = $is_new ? 0 : (int)$edit_id;
?>
<div class="max-w-5xl">
  <!-- Header -->
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=lists" class="text-gray-400 hover:text-gray-600 transition">
      <i class="fa-solid fa-arrow-right text-lg"></i>
    </a>
    <h2 class="text-xl font-black text-gray-800"><?= $is_new ? 'قائمة جديدة' : 'تعديل القائمة: '.htmlspecialchars($list['list_title'] ?? '') ?></h2>
  </div>

  <?php if ($msg): ?>
  <div class="mb-4 px-4 py-3 rounded-xl text-sm font-bold
    <?= $msg_type==='green' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="listForm">
    <input type="hidden" name="action" value="save_list">
    <input type="hidden" name="list_id" value="<?= $list_id_val ?>">
    <input type="hidden" name="list_columns_json" id="list_columns_json" value="<?= htmlspecialchars(json_encode($list_columns, JSON_UNESCAPED_UNICODE)) ?>">

    <!-- Tabs -->
    <div x-data="{ tab: 'meta' }" class="bg-white rounded-2xl shadow-sm overflow-hidden">
      <!-- Tab bar -->
      <div class="flex border-b border-gray-100 bg-gray-50">
        <button type="button" @click="tab='meta'"
          :class="tab==='meta' ? 'border-b-2 border-purple-600 text-purple-700 bg-white' : 'text-gray-500 hover:text-gray-700'"
          class="px-5 py-3 font-bold text-sm transition flex items-center gap-2">
          <i class="fa-solid fa-info-circle text-xs"></i> بيانات القائمة
        </button>
        <button type="button" @click="tab='columns'"
          :class="tab==='columns' ? 'border-b-2 border-purple-600 text-purple-700 bg-white' : 'text-gray-500 hover:text-gray-700'"
          class="px-5 py-3 font-bold text-sm transition flex items-center gap-2">
          <i class="fa-solid fa-table-columns text-xs"></i> الأعمدة
        </button>
        <button type="button" @click="tab='blocks'"
          :class="tab==='blocks' ? 'border-b-2 border-purple-600 text-purple-700 bg-white' : 'text-gray-500 hover:text-gray-700'"
          class="px-5 py-3 font-bold text-sm transition flex items-center gap-2">
          <i class="fa-solid fa-layer-group text-xs"></i> المحتوى
        </button>
        <button type="button" @click="tab='members'"
          :class="tab==='members' ? 'border-b-2 border-purple-600 text-purple-700 bg-white' : 'text-gray-500 hover:text-gray-700'"
          class="px-5 py-3 font-bold text-sm transition flex items-center gap-2">
          <i class="fa-solid fa-users text-xs"></i> الأعضاء
        </button>
        <button type="button" @click="tab='sponsor'"
          :class="tab==='sponsor' ? 'border-b-2 border-purple-600 text-purple-700 bg-white' : 'text-gray-500 hover:text-gray-700'"
          class="px-5 py-3 font-bold text-sm transition flex items-center gap-2">
          <i class="fa-solid fa-handshake text-xs"></i> الرعاية
        </button>
        <button type="button" @click="tab='spotlight'"
          :class="tab==='spotlight' ? 'border-b-2 border-purple-600 text-purple-700 bg-white' : 'text-gray-500 hover:text-gray-700'"
          class="px-5 py-3 font-bold text-sm transition flex items-center gap-2">
          <i class="fa-solid fa-star text-xs"></i> تحت الضوء
        </button>
      </div>

      <!-- ── TAB 1: Meta ─────────────────────────────────────────────────── -->
      <div x-show="tab==='meta'" class="p-6 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="form-label">عنوان القائمة (عربي) <span class="text-red-500">*</span></label>
            <input type="text" name="list_title" id="inp_title_ar" required class="form-input"
              value="<?= htmlspecialchars($list['list_title'] ?? '') ?>"
              oninput="autoSlug(this.value)">
          </div>
          <div>
            <label class="form-label">عنوان القائمة (إنجليزي)</label>
            <input type="text" name="list_title_en" class="form-input"
              value="<?= htmlspecialchars($list['list_title_en'] ?? '') ?>">
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="form-label">الرابط (Slug)</label>
            <input type="text" name="list_slug" id="inp_slug" class="form-input" dir="ltr"
              value="<?= htmlspecialchars($list['list_slug'] ?? '') ?>">
          </div>
          <div>
            <label class="form-label">السنة</label>
            <input type="text" name="list_year" class="form-input" placeholder="مثال: 2024"
              value="<?= htmlspecialchars($list['list_year'] ?? '') ?>">
          </div>
        </div>

        <div>
          <label class="form-label">الوصف</label>
          <textarea name="list_description" class="form-input" rows="4"><?= htmlspecialchars($list['list_description'] ?? '') ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Cover -->
          <div>
            <label class="form-label">صورة الغلاف</label>
            <div class="space-y-2">
              <div class="pi-upload-zone" onclick="document.getElementById('cover_file').click()">
                <?php $cv = $list['list_cover'] ?? ''; ?>
                <img id="cover_preview" src="<?= $cv ? htmlspecialchars($cv) : '' ?>"
                  class="preview-img <?= $cv ? '' : 'hidden' ?>">
                <div id="cover_ph" <?= $cv ? 'style="display:none"' : '' ?>>
                  <i class="fa-solid fa-image text-gray-300 text-3xl mb-2"></i>
                  <p class="preview-label">انقر لرفع صورة الغلاف</p>
                </div>
              </div>
              <input type="hidden" name="list_cover_keep" value="<?= htmlspecialchars($cv) ?>">
              <input type="file" id="cover_file" name="list_cover_file" accept="image/*" class="hidden"
                data-preview="cover_preview" data-placeholder="cover_ph">
            </div>
          </div>

          <!-- Logo -->
          <div>
            <label class="form-label">شعار القائمة</label>
            <div class="space-y-2">
              <div class="pi-upload-zone flex flex-col items-center justify-center cursor-pointer" style="min-height:180px" onclick="document.getElementById('logo_file').click()">
                <?php $lg = $list['list_logo'] ?? ''; ?>
                <img id="logo_preview" src="<?= $lg ? htmlspecialchars($lg) : '' ?>"
                  class="<?= $lg ? '' : 'hidden' ?>" style="max-height:140px;max-width:100%;object-fit:contain;border-radius:1rem">
                <div id="logo_ph" <?= $lg ? 'style="display:none"' : '' ?> class="flex flex-col items-center justify-center">
                  <i class="fa-solid fa-award text-gray-300 text-5xl mb-3"></i>
                  <p class="preview-label text-sm text-gray-400">انقر لرفع شعار القائمة</p>
                </div>
              </div>
              <input type="hidden" name="list_logo_keep" value="<?= htmlspecialchars($lg) ?>">
              <input type="file" id="logo_file" name="list_logo_file" accept="image/*" class="hidden"
                data-preview="logo_preview" data-placeholder="logo_ph">
            </div>
          </div>
        </div>

        <div>
          <label class="form-label">معايير القائمة <span class="text-gray-400 font-normal text-xs">(تُعرض في الصفحة)</span></label>
          <textarea name="list_criteria" class="form-input" rows="3" placeholder="اشرح معايير اختيار الأعضاء..."><?= htmlspecialchars($list['list_criteria'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center gap-5">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="list_active" value="1" class="w-4 h-4 accent-purple-600"
              <?= ($list['list_active'] ?? 1) ? 'checked' : '' ?>>
            <span class="text-sm font-bold text-gray-700">نشطة (مرئية للزوار)</span>
          </label>
          <div>
            <label class="form-label mb-1">الترتيب</label>
            <input type="number" name="list_order" class="form-input w-24" value="<?= (int)($list['list_order'] ?? 0) ?>">
          </div>
        </div>
      </div>

      <!-- ── TAB 2: Columns ──────────────────────────────────────────────── -->
      <div x-show="tab==='columns'" x-cloak class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-gray-700">تعريف أعمدة الجدول</h3>
          <button type="button" onclick="addColumn()" class="btn-primary text-sm">
            <i class="fa-solid fa-plus"></i> أضف عمود
          </button>
        </div>
        <p class="text-xs text-gray-400 mb-4">أعمدة المصدر "ملف" تُسحب تلقائياً من بيانات الشخصية/المؤسسة. أعمدة "يدوي" تُدخل عند إضافة الأعضاء.</p>

        <div id="columns_container" class="space-y-3">
          <?php foreach ($list_columns as $ci => $col): ?>
          <div class="col-row flex flex-wrap items-center gap-2 bg-gray-50 rounded-xl p-3 border border-gray-200" data-index="<?= $ci ?>">
            <div class="flex-1 min-w-32">
              <label class="text-xs text-gray-400 font-bold">التسمية</label>
              <input type="text" class="form-input text-sm col-label" placeholder="مثال: القيمة بالدولار"
                value="<?= htmlspecialchars($col['label'] ?? '') ?>" oninput="updateColKey(this)">
            </div>
            <div class="w-36">
              <label class="text-xs text-gray-400 font-bold">المفتاح</label>
              <input type="text" class="form-input text-sm col-key" dir="ltr" placeholder="value_usd"
                value="<?= htmlspecialchars($col['key'] ?? '') ?>">
            </div>
            <div class="w-32">
              <label class="text-xs text-gray-400 font-bold">النوع</label>
              <select class="form-input text-sm col-type">
                <?php foreach (['text'=>'نص','number'=>'رقم','currency'=>'عملة','percent'=>'نسبة','badge'=>'شارة','country'=>'دولة'] as $tv=>$tl): ?>
                <option value="<?= $tv ?>" <?= ($col['type']??'text')===$tv?'selected':'' ?>><?= $tl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="w-28">
              <label class="text-xs text-gray-400 font-bold">المصدر</label>
              <select class="form-input text-sm col-source">
                <option value="manual" <?= ($col['source']??'manual')==='manual'?'selected':'' ?>>يدوي</option>
                <option value="profile" <?= ($col['source']??'')==='profile'?'selected':'' ?>>ملف</option>
              </select>
            </div>
            <div class="flex items-center gap-1 mt-4">
              <input type="checkbox" class="col-filterable w-4 h-4 accent-purple-600" <?= ($col['filterable']??0)?'checked':'' ?>>
              <label class="text-xs text-gray-500 font-bold">قابل للتصفية</label>
            </div>
            <button type="button" onclick="this.closest('.col-row').remove(); serializeColumns()"
              class="mt-4 btn-danger text-xs px-2 py-1"><i class="fa-solid fa-trash"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="text-xs text-gray-400 mt-3">أعمدة الملف المدعومة: country, position, residence</p>
      </div>

      <!-- ── TAB 3: Blocks ───────────────────────────────────────────────── -->
      <div x-show="tab==='blocks'" x-cloak class="p-6">
        <div class="flex items-center gap-3 mb-5">
          <h3 class="font-bold text-gray-700">كتل المحتوى</h3>
          <button type="button" onclick="addBlock('text')" class="btn-secondary text-xs px-3 py-2">
            <i class="fa-solid fa-paragraph"></i> نص
          </button>
          <button type="button" onclick="addBlock('image')" class="btn-secondary text-xs px-3 py-2">
            <i class="fa-solid fa-image"></i> صورة
          </button>
          <button type="button" onclick="addBlock('video')" class="btn-secondary text-xs px-3 py-2">
            <i class="fa-solid fa-play-circle"></i> فيديو
          </button>
        </div>

        <div id="blocks_container" class="space-y-4">
          <?php foreach ($list_blocks as $bi => $block): ?>
          <div class="block-card bg-gray-50 rounded-xl p-4 border border-gray-200" data-block-idx="<?= $bi ?>">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-black text-gray-500 uppercase">
                <?php echo ['text'=>'نص','image'=>'صورة','video'=>'فيديو'][$block['lb_type']] ?? 'كتلة'; ?>
              </span>
              <div class="flex gap-2">
                <button type="button" onclick="moveBlock(this,-1)" class="text-gray-400 hover:text-gray-600">
                  <i class="fa-solid fa-arrow-up text-xs"></i>
                </button>
                <button type="button" onclick="moveBlock(this,1)" class="text-gray-400 hover:text-gray-600">
                  <i class="fa-solid fa-arrow-down text-xs"></i>
                </button>
                <button type="button" onclick="this.closest('.block-card').remove()"
                  class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
              </div>
            </div>
            <input type="hidden" name="block_type[]" value="<?= htmlspecialchars($block['lb_type']) ?>">
            <input type="hidden" name="block_order[]" value="<?= $bi ?>">
            <?php if ($block['lb_type'] === 'text'): ?>
            <div class="quill-block" data-content="<?= htmlspecialchars($block['lb_content'] ?? '') ?>"></div>
            <textarea name="block_content[]" class="quill-hidden hidden"><?= htmlspecialchars($block['lb_content'] ?? '') ?></textarea>
            <?php elseif ($block['lb_type'] === 'image'): ?>
            <?php $bi_img = $block['lb_content'] ?? ''; ?>
            <input type="hidden" name="block_content[]" class="block-img-keep" value="<?= htmlspecialchars($bi_img) ?>">
            <?php if ($bi_img): ?>
            <img src="<?= htmlspecialchars($bi_img) ?>" class="w-20 h-14 object-cover rounded-xl border border-gray-200 mb-2">
            <?php endif; ?>
            <input type="file" name="block_image[<?= $bi ?>]" accept="image/*" class="w-full text-sm text-gray-600 border border-gray-200 rounded-xl px-3 py-2">
            <p class="text-xs text-gray-400 mt-1">اختر صورة جديدة أو اترك فارغاً للإبقاء على الحالية</p>
            <?php else: ?>
            <input type="text" name="block_content[]" class="form-input text-sm" dir="ltr"
              placeholder="رابط الفيديو أو كود التضمين" value="<?= htmlspecialchars($block['lb_content'] ?? '') ?>">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── TAB 4: Members ─────────────────────────────────────────────── -->
      <div x-show="tab==='members'" x-cloak class="p-6">
        <!-- Search box -->
        <div class="mb-5">
          <label class="form-label">بحث عن شخصية أو مؤسسة</label>
          <div class="relative">
            <input type="text" id="entity_search" class="form-input pl-10" placeholder="اكتب اسم الشخصية أو المؤسسة..."
              oninput="searchEntities(this.value)" autocomplete="off">
            <i class="fa-solid fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
          </div>
          <div id="entity_results" class="hidden mt-2 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-y-auto z-10 relative"></div>
        </div>

        <!-- Items list -->
        <div id="items_container" class="space-y-3">
          <?php foreach ($list_items as $ii => $item):
            $ent = $item['entity'];
            if (!$ent) continue;
            $ename = $item['li_entity_type']==='personality' ? ($ent['p_name_ar']??'') : ($ent['inst_name_ar']??'');
            $ephoto = $item['li_entity_type']==='personality' ? ($ent['p_photo']??'') : ($ent['inst_logo']??'');
          ?>
          <div class="item-row bg-gray-50 rounded-xl p-3 border border-gray-200 flex flex-wrap items-start gap-3">
            <input type="hidden" name="item_entity_type[]" value="<?= htmlspecialchars($item['li_entity_type']) ?>">
            <input type="hidden" name="item_entity_id[]" value="<?= (int)$item['li_entity_id'] ?>">

            <!-- Rank -->
            <div class="w-16">
              <label class="text-xs text-gray-400 font-bold">الترتيب</label>
              <input type="number" name="item_rank[]" class="form-input text-sm" value="<?= (int)$item['li_rank'] ?>">
            </div>

            <!-- Photo + Name -->
            <div class="flex items-center gap-2 flex-1 min-w-40">
              <?php if ($ephoto): ?>
              <img src="<?= htmlspecialchars($ephoto) ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200">
              <?php else: ?>
              <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-black text-sm flex-shrink-0">
                <?= mb_substr($ename,0,1) ?>
              </div>
              <?php endif; ?>
              <div>
                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($ename) ?></p>
                <span class="text-xs px-2 py-0.5 rounded-full <?= $item['li_entity_type']==='personality'?'bg-blue-100 text-blue-700':'bg-orange-100 text-orange-700' ?>">
                  <?= $item['li_entity_type']==='personality'?'شخصية':'مؤسسة' ?>
                </span>
              </div>
            </div>

            <!-- Column data inputs -->
            <?php foreach ($list_columns as $col):
              if (($col['source']??'manual') !== 'manual') continue;
            ?>
            <div class="w-36">
              <label class="text-xs text-gray-400 font-bold"><?= htmlspecialchars($col['label']??'') ?></label>
              <input type="text" name="item_data[<?= $ii ?>][<?= htmlspecialchars($col['key']??'') ?>]"
                class="form-input text-sm"
                value="<?= htmlspecialchars($item['li_data'][$col['key']??''] ?? '') ?>">
            </div>
            <?php endforeach; ?>

            <button type="button" onclick="this.closest('.item-row').remove()"
              class="mt-5 btn-danger text-xs px-2 py-1"><i class="fa-solid fa-trash"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── TAB 5: Sponsor ────────────────────────────────────────────────── -->
      <div x-show="tab==='sponsor'" x-cloak class="p-6">
        <div class="mb-5">
          <h3 class="font-bold text-gray-700 mb-1">الراعي الرسمي للقائمة</h3>
          <p class="text-xs text-gray-400">اختر راعياً من قائمة الرعاة المسجلين، أو أدخل بيانات راعٍ مخصص.</p>
        </div>

        <!-- Select from sponsors -->
        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 mb-5">
          <label class="form-label">اختر من الرعاة المسجلين</label>
          <select name="list_sponsor_id" id="sponsor_select" class="form-input max-w-sm" onchange="onSponsorSelect(this.value)">
            <option value="">— بدون راعٍ مسجل —</option>
            <?php foreach ($sponsors_list as $sp): ?>
            <option value="<?= $sp['sp_id'] ?>" <?= (($list['list_sponsor_id'] ?? 0) == $sp['sp_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($sp['sp_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($sponsors_list)): ?>
          <p class="text-xs text-gray-400 mt-2">لا يوجد رعاة مسجلون حتى الآن. أضف رعاة من <a href="admin.php?p=sponsors" class="text-purple-600 underline">إدارة الرعاة</a>.</p>
          <?php endif; ?>
        </div>

        <!-- ── Custom Sponsors (all via add button) ── -->
        <div class="mt-4">
          <div class="flex items-center justify-between mb-3">
            <h4 class="font-bold text-gray-700 text-sm">رعاة مخصصون</h4>
            <button type="button" onclick="addCustomSponsor()" class="btn-secondary text-xs px-3 py-2">
              <i class="fa-solid fa-plus ml-1"></i> إضافة راعٍ
            </button>
          </div>
          <div id="custom_sponsors_list" class="space-y-4">
            <?php
            $custom_sponsors_saved = json_decode($list['list_sponsors_json'] ?? '[]', true) ?: [];
            // Migrate old primary custom sponsor into the list
            $old_sp_name = trim($list['list_sponsor_name'] ?? '');
            if ($old_sp_name) {
                $already = false;
                foreach ($custom_sponsors_saved as $x) { if (($x['name']??'') === $old_sp_name) { $already=true; break; } }
                if (!$already) array_unshift($custom_sponsors_saved, ['name'=>$old_sp_name,'url'=>$list['list_sponsor_url']??'','img'=>$list['list_sponsor_img']??'']);
            }
            foreach ($custom_sponsors_saved as $csi => $csp):
            ?>
            <div class="csp-row bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-3">
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-bold text-gray-500">راعٍ مخصص</span>
                <button type="button" onclick="this.closest('.csp-row').remove()" class="text-red-400 hover:text-red-600 text-xs"><i class="fa-solid fa-trash"></i></button>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-bold text-gray-500 block mb-1">اسم الراعي</label>
                  <input type="text" name="csp_name[]" class="form-input text-sm" value="<?= htmlspecialchars($csp['name']??'') ?>" placeholder="مثال: شركة X">
                </div>
                <div>
                  <label class="text-xs font-bold text-gray-500 block mb-1">رابط الموقع</label>
                  <input type="text" name="csp_url[]" class="form-input text-sm" dir="ltr" value="<?= htmlspecialchars($csp['url']??'') ?>" placeholder="https://...">
                </div>
              </div>
              <div>
                <label class="text-xs font-bold text-gray-500 block mb-1">الشعار</label>
                <?php if (!empty($csp['img'])): ?>
                <img src="<?= htmlspecialchars($csp['img']) ?>" class="h-10 max-w-28 object-contain border border-gray-200 rounded-lg mb-2">
                <?php endif; ?>
                <input type="hidden" name="csp_img_keep[]" value="<?= htmlspecialchars($csp['img']??'') ?>">
                <input type="file" name="csp_img[<?= $csi ?>]" accept="image/*" class="w-full text-sm text-gray-600">
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ── TAB 6: Spotlight ───────────────────────────────────────────────── -->
      <div x-show="tab==='spotlight'" x-cloak class="p-6">
        <div class="mb-5">
          <h3 class="font-bold text-gray-700 mb-1">تحت الضوء</h3>
          <p class="text-xs text-gray-400 leading-relaxed">
            اختر الأعضاء الذين يظهرون في قسم "تحت الضوء" في صفحة القائمة.
            إذا لم تختر أحداً، سيظهر تلقائياً الأعضاء الموثقون ضمن القائمة.
          </p>
        </div>

        <?php if (empty($list_items)): ?>
        <div class="text-center py-10 text-gray-400">
          <i class="fa-solid fa-users text-4xl mb-3 opacity-20"></i>
          <p class="text-sm font-bold">أضف أعضاء للقائمة أولاً من تبويب "الأعضاء"</p>
        </div>
        <?php else: ?>
        <div class="flex items-center justify-between mb-4">
          <p class="text-sm font-bold text-gray-600"><?= count($list_items) ?> عضو في القائمة</p>
          <div class="flex gap-2">
            <button type="button" onclick="document.querySelectorAll('.spotlight-cb').forEach(c=>c.checked=true)"
              class="text-xs font-bold text-purple-600 hover:underline">تحديد الكل</button>
            <span class="text-gray-300">|</span>
            <button type="button" onclick="document.querySelectorAll('.spotlight-cb').forEach(c=>c.checked=false)"
              class="text-xs font-bold text-gray-400 hover:underline">إلغاء الكل</button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
          <?php foreach ($list_items as $item):
            $ent = $item['entity'];
            if (!$ent) continue;
            $key = $item['li_entity_type'].'-'.$item['li_entity_id'];
            $ename = $item['li_entity_type']==='personality' ? ($ent['p_name_ar']??'') : ($ent['inst_name_ar']??'');
            $ephoto = $item['li_entity_type']==='personality' ? ($ent['p_photo']??'') : ($ent['inst_logo']??'');
            $is_checked = in_array($key, $spotlight_selected);
          ?>
          <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition hover:border-purple-300 hover:bg-purple-50
            <?= $is_checked ? 'border-purple-400 bg-purple-50' : 'border-gray-200 bg-gray-50' ?>">
            <input type="checkbox" name="list_spotlight[]" value="<?= htmlspecialchars($key) ?>"
              class="spotlight-cb w-4 h-4 accent-purple-600 flex-shrink-0"
              <?= $is_checked ? 'checked' : '' ?>>
            <?php if ($ephoto): ?>
            <img src="<?= htmlspecialchars($ephoto) ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 flex-shrink-0">
            <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-black text-sm flex-shrink-0">
              <?= mb_substr($ename,0,1) ?>
            </div>
            <?php endif; ?>
            <div class="min-w-0">
              <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($ename) ?></p>
              <div class="flex items-center gap-1 mt-0.5">
                <span class="text-xs px-1.5 py-0.5 rounded-full <?= $item['li_entity_type']==='personality'?'bg-blue-100 text-blue-700':'bg-orange-100 text-orange-700' ?>">
                  <?= $item['li_entity_type']==='personality'?'شخصية':'مؤسسة' ?>
                </span>
                <span class="text-xs text-gray-400">#<?= (int)$item['li_rank'] ?></span>
              </div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div><!-- /tab panels -->

    <!-- Save button -->
    <div class="flex items-center gap-3 mt-6">
      <button type="submit" class="btn-primary">
        <i class="fa-solid fa-save"></i> حفظ القائمة
      </button>
      <a href="admin.php?p=lists" class="btn-secondary">إلغاء</a>
      <?php if (!$is_new): ?>
      <a href="../list.php?id=<?= $list_id_val ?>" target="_blank" class="btn-secondary">
        <i class="fa-solid fa-eye"></i> معاينة
      </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Quill CDN -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<script>
// ── Slug auto-generate ────────────────────────────────────────────────────
function autoSlug(val) {
  var s = val.trim().toLowerCase().replace(/\s+/g,'-').replace(/[^؀-ۿa-z0-9\-]/g,'');
  document.getElementById('inp_slug').value = s;
}

// ── Columns ───────────────────────────────────────────────────────────────
var colIndex = <?= count($list_columns) ?>;

function addColumn() {
  var c = document.getElementById('columns_container');
  var div = document.createElement('div');
  div.className = 'col-row flex flex-wrap items-center gap-2 bg-gray-50 rounded-xl p-3 border border-gray-200';
  div.setAttribute('data-index', colIndex++);
  div.innerHTML = `
    <div class="flex-1 min-w-32">
      <label class="text-xs text-gray-400 font-bold">التسمية</label>
      <input type="text" class="form-input text-sm col-label" placeholder="مثال: القيمة بالدولار" oninput="updateColKey(this)">
    </div>
    <div class="w-36">
      <label class="text-xs text-gray-400 font-bold">المفتاح</label>
      <input type="text" class="form-input text-sm col-key" dir="ltr" placeholder="value_usd">
    </div>
    <div class="w-32">
      <label class="text-xs text-gray-400 font-bold">النوع</label>
      <select class="form-input text-sm col-type">
        <option value="text">نص</option>
        <option value="number">رقم</option>
        <option value="currency">عملة</option>
        <option value="percent">نسبة</option>
        <option value="badge">شارة</option>
        <option value="country">دولة</option>
      </select>
    </div>
    <div class="w-28">
      <label class="text-xs text-gray-400 font-bold">المصدر</label>
      <select class="form-input text-sm col-source">
        <option value="manual">يدوي</option>
        <option value="profile">ملف</option>
      </select>
    </div>
    <div class="flex items-center gap-1 mt-4">
      <input type="checkbox" class="col-filterable w-4 h-4 accent-purple-600">
      <label class="text-xs text-gray-500 font-bold">قابل للتصفية</label>
    </div>
    <button type="button" onclick="this.closest('.col-row').remove(); serializeColumns()" class="mt-4 btn-danger text-xs px-2 py-1"><i class="fa-solid fa-trash"></i></button>
  `;
  c.appendChild(div);
}

function updateColKey(labelInput) {
  var row = labelInput.closest('.col-row');
  var keyInp = row.querySelector('.col-key');
  var val = labelInput.value.trim().toLowerCase().replace(/\s+/g,'_').replace(/[^؀-ۿa-z0-9_]/g,'');
  keyInp.value = val;
  serializeColumns();
}

function serializeColumns() {
  var rows = document.querySelectorAll('.col-row');
  var cols = [];
  rows.forEach(function(row) {
    var label = row.querySelector('.col-label')?.value || '';
    var key   = row.querySelector('.col-key')?.value || '';
    var type  = row.querySelector('.col-type')?.value || 'text';
    var src   = row.querySelector('.col-source')?.value || 'manual';
    var filt  = row.querySelector('.col-filterable')?.checked ? 1 : 0;
    if (key) cols.push({key:key, label:label, type:type, source:src, filterable:filt});
  });
  document.getElementById('list_columns_json').value = JSON.stringify(cols);
}

document.getElementById('columns_container').addEventListener('change', serializeColumns);
document.getElementById('columns_container').addEventListener('input', serializeColumns);

// ── Blocks ────────────────────────────────────────────────────────────────
var quillInstances = {};
var blockIdx = <?= count($list_blocks) ?>;

function addBlock(type) {
  var c = document.getElementById('blocks_container');
  var div = document.createElement('div');
  div.className = 'block-card bg-gray-50 rounded-xl p-4 border border-gray-200';
  div.setAttribute('data-block-idx', blockIdx);

  var typeLabel = {text:'نص',image:'صورة',video:'فيديو'}[type] || type;
  var inner = `
    <div class="flex items-center justify-between mb-3">
      <span class="text-xs font-black text-gray-500 uppercase">${typeLabel}</span>
      <div class="flex gap-2">
        <button type="button" onclick="moveBlock(this,-1)" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-up text-xs"></i></button>
        <button type="button" onclick="moveBlock(this,1)" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-down text-xs"></i></button>
        <button type="button" onclick="this.closest('.block-card').remove()" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
      </div>
    </div>
    <input type="hidden" name="block_type[]" value="${type}">
    <input type="hidden" name="block_order[]" value="${blockIdx}">
  `;

  if (type === 'text') {
    inner += `<div class="quill-block" id="quill-${blockIdx}"></div><textarea name="block_content[]" class="quill-hidden hidden"></textarea>`;
  } else if (type === 'image') {
    inner += `<input type="hidden" name="block_content[]" value=""><input type="file" name="block_image[${blockIdx}]" accept="image/*" class="w-full text-sm text-gray-600 border border-gray-200 rounded-xl px-3 py-2">`;
  } else {
    inner += `<input type="text" name="block_content[]" class="form-input text-sm" dir="ltr" placeholder="رابط الفيديو أو كود التضمين">`;
  }

  div.innerHTML = inner;
  c.appendChild(div);

  if (type === 'text') {
    initQuill(div.querySelector('.quill-block'), div.querySelector('.quill-hidden'), '');
  }
  blockIdx++;
}

function moveBlock(btn, dir) {
  var card = btn.closest('.block-card');
  var container = card.parentNode;
  if (dir === -1 && card.previousElementSibling) {
    container.insertBefore(card, card.previousElementSibling);
  } else if (dir === 1 && card.nextElementSibling) {
    container.insertBefore(card.nextElementSibling, card);
  }
}

function initQuill(el, textarea, content) {
  var q = new Quill(el, {
    theme: 'snow',
    placeholder: 'اكتب المحتوى هنا...',
    modules: { toolbar: [['bold','italic','underline'],['blockquote'],['link'],['clean']] }
  });
  if (content) q.root.innerHTML = content;
  q.on('text-change', function() {
    if (textarea) textarea.value = q.root.innerHTML;
  });
  return q;
}

// Init existing quill blocks
document.querySelectorAll('.quill-block').forEach(function(el) {
  var content = el.getAttribute('data-content') || '';
  var textarea = el.nextElementSibling;
  initQuill(el, textarea, content);
});

// ── Entity search ─────────────────────────────────────────────────────────
var searchTimer = null;
var currentColumns = <?= json_encode($list_columns, JSON_UNESCAPED_UNICODE) ?>;

function searchEntities(q) {
  clearTimeout(searchTimer);
  if (q.length < 1) { document.getElementById('entity_results').classList.add('hidden'); return; }
  searchTimer = setTimeout(function() {
    fetch('admin.php?p=lists&ajax=search_entities&q=' + encodeURIComponent(q))
      .then(r=>r.json()).then(renderResults);
  }, 300);
}

function renderResults(items) {
  var box = document.getElementById('entity_results');
  if (!items.length) {
    box.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">لا نتائج</p>';
    box.classList.remove('hidden');
    return;
  }
  box.innerHTML = items.map(function(item) {
    var photo = item.photo
      ? `<img src="${item.photo}" class="w-9 h-9 rounded-full object-cover border border-gray-200 flex-shrink-0">`
      : `<div class="w-9 h-9 rounded-full bg-purple-100 text-purple-600 font-black text-sm flex items-center justify-center flex-shrink-0">${item.name.charAt(0)}</div>`;
    var badge = item.type==='personality'
      ? '<span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">شخصية</span>'
      : '<span class="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">مؤسسة</span>';
    return `<button type="button" class="flex items-center gap-3 px-4 py-2.5 hover:bg-purple-50 w-full text-right transition"
      onclick='addEntity(${JSON.stringify(item)})'>
      ${photo}
      <div class="flex-1 min-w-0">
        <p class="font-bold text-sm text-gray-800 truncate">${item.name}</p>
        <p class="text-xs text-gray-400 truncate">${item.subtitle||''}</p>
      </div>
      ${badge}
    </button>`;
  }).join('');
  box.classList.remove('hidden');
}

var addedEntityIds = new Set(<?= json_encode(array_map(function($i){ return $i['li_entity_type'].'-'.$i['li_entity_id']; }, $list_items)) ?>);

function addEntity(item) {
  document.getElementById('entity_results').classList.add('hidden');
  document.getElementById('entity_search').value = '';

  var key = item.type + '-' + item.id;
  if (addedEntityIds.has(key)) { alert('هذا العنصر مضاف بالفعل'); return; }
  addedEntityIds.add(key);

  var idx = document.querySelectorAll('.item-row').length;
  var c = document.getElementById('items_container');
  var div = document.createElement('div');
  div.className = 'item-row bg-gray-50 rounded-xl p-3 border border-gray-200 flex flex-wrap items-start gap-3';

  var photo = item.photo
    ? `<img src="${item.photo}" class="w-10 h-10 rounded-full object-cover border border-gray-200">`
    : `<div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-black text-sm flex-shrink-0">${item.name.charAt(0)}</div>`;
  var badge = item.type==='personality'
    ? '<span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">شخصية</span>'
    : '<span class="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">مؤسسة</span>';

  // Manual column inputs
  var colInputs = '';
  currentColumns.forEach(function(col) {
    if (col.source !== 'manual') return;
    colInputs += `<div class="w-36">
      <label class="text-xs text-gray-400 font-bold">${col.label||col.key}</label>
      <input type="text" name="item_data[${idx}][${col.key}]" class="form-input text-sm" placeholder="${col.label||''}">
    </div>`;
  });

  div.innerHTML = `
    <input type="hidden" name="item_entity_type[]" value="${item.type}">
    <input type="hidden" name="item_entity_id[]" value="${item.id}">
    <div class="w-16">
      <label class="text-xs text-gray-400 font-bold">الترتيب</label>
      <input type="number" name="item_rank[]" class="form-input text-sm" value="${idx+1}">
    </div>
    <div class="flex items-center gap-2 flex-1 min-w-40">
      ${photo}
      <div>
        <p class="font-bold text-gray-800 text-sm">${item.name}</p>
        ${badge}
      </div>
    </div>
    ${colInputs}
    <button type="button" onclick="this.closest('.item-row').remove()"
      class="mt-5 btn-danger text-xs px-2 py-1"><i class="fa-solid fa-trash"></i></button>
  `;
  c.appendChild(div);
}

// ── File upload previews ──────────────────────────────────────────────────
document.querySelectorAll('input[type=file][data-preview]').forEach(function(inp) {
  inp.addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    var img = document.getElementById(this.getAttribute('data-preview'));
    var ph  = document.getElementById(this.getAttribute('data-placeholder'));
    if (img) {
      if (img._objUrl) URL.revokeObjectURL(img._objUrl);
      img._objUrl = URL.createObjectURL(file);
      img.src = img._objUrl;
      img.classList.remove('hidden');
    }
    if (ph) ph.style.display = 'none';
  });
});

// logo file change → update preview img style
document.getElementById('logo_file').addEventListener('change', function() {
  var file = this.files[0];
  if (!file) return;
  var img = document.getElementById('logo_preview');
  var ph  = document.getElementById('logo_ph');
  if (img) {
    if (img._objUrl) URL.revokeObjectURL(img._objUrl);
    img._objUrl = URL.createObjectURL(file);
    img.src = img._objUrl;
    img.classList.remove('hidden');
  }
  if (ph) ph.style.display = 'none';
});

// ── Sponsor select ────────────────────────────────────────────────────────
function onSponsorSelect(val) {
  if (val) {
    var ni = document.getElementById('inp_sponsor_name');
    if (ni) ni.value = '';
  }
}

// ── Multiple custom sponsors ──────────────────────────────────────────────
var cspIdx = <?= count($custom_sponsors_saved ?? []) ?>;
function addCustomSponsor() {
  var list = document.getElementById('custom_sponsors_list');
  var div = document.createElement('div');
  div.className = 'csp-row bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-3';
  div.innerHTML = `
    <div class="flex items-center justify-between mb-1">
      <span class="text-xs font-bold text-gray-500">راعٍ مخصص</span>
      <button type="button" onclick="this.closest('.csp-row').remove()" class="text-red-400 hover:text-red-600 text-xs"><i class="fa-solid fa-trash"></i></button>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="text-xs font-bold text-gray-500 block mb-1">اسم الراعي</label>
        <input type="text" name="csp_name[]" class="form-input text-sm" placeholder="مثال: شركة X">
      </div>
      <div>
        <label class="text-xs font-bold text-gray-500 block mb-1">رابط الموقع</label>
        <input type="text" name="csp_url[]" class="form-input text-sm" dir="ltr" placeholder="https://...">
      </div>
    </div>
    <div>
      <label class="text-xs font-bold text-gray-500 block mb-1">الشعار</label>
      <input type="hidden" name="csp_img_keep[]" value="">
      <input type="file" name="csp_img[${cspIdx}]" accept="image/*" class="w-full text-sm text-gray-600">
    </div>
  `;
  list.appendChild(div);
  cspIdx++;
}

// ── Serialize columns before form submit ─────────────────────────────────
document.getElementById('listForm').addEventListener('submit', function() {
  serializeColumns();
  // Sync quill textareas
  document.querySelectorAll('.quill-block').forEach(function(el) {
    var ta = el.nextElementSibling;
    if (ta && ta.tagName === 'TEXTAREA') {
      ta.value = el.querySelector('.ql-editor')?.innerHTML || '';
    }
  });
});
</script>
<?php
    return; // End edit view
}

// ══════════════════════════════════════════════════════════════════════════════
// LIST VIEW
// ══════════════════════════════════════════════════════════════════════════════
?>
<div class="max-w-5xl">
  <!-- Header -->
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-black text-gray-800">إدارة القوائم</h2>
    <a href="admin.php?p=lists&edit=new" class="btn-primary">
      <i class="fa-solid fa-plus"></i> قائمة جديدة
    </a>
  </div>

  <?php if ($msg): ?>
  <div class="mb-4 px-4 py-3 rounded-xl text-sm font-bold
    <?= $msg_type==='green' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <?php
    $lists = [];
    $r = $mysqli->query("SELECT l.*, (SELECT COUNT(*) FROM pi_list_items WHERE li_list_id=l.list_id) AS items_count
        FROM pi_lists l ORDER BY l.list_order ASC, l.list_id DESC");
    if ($r) while ($row = $r->fetch_assoc()) $lists[] = $row;
    ?>
    <?php if (empty($lists)): ?>
    <div class="text-center py-16 text-gray-400">
      <i class="fa-solid fa-list-ol text-5xl mb-4 opacity-20"></i>
      <p class="font-bold">لا توجد قوائم بعد</p>
      <a href="admin.php?p=lists&edit=new" class="btn-primary mt-4 inline-flex">أضف أول قائمة</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>الغلاف</th>
            <th>العنوان</th>
            <th>السنة</th>
            <th>الأعضاء</th>
            <th>الحالة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lists as $lst): ?>
          <tr>
            <td class="text-gray-400 font-bold"><?= $lst['list_id'] ?></td>
            <td>
              <?php if ($lst['list_cover']): ?>
              <img src="<?= htmlspecialchars($lst['list_cover']) ?>" class="w-12 h-10 rounded-lg object-cover border border-gray-200">
              <?php else: ?>
              <div class="w-12 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-purple-800 flex items-center justify-center">
                <i class="fa-solid fa-list-ol text-white text-xs"></i>
              </div>
              <?php endif; ?>
            </td>
            <td>
              <p class="font-bold text-gray-800"><?= htmlspecialchars($lst['list_title']) ?></p>
              <?php if ($lst['list_title_en']): ?>
              <p class="text-xs text-gray-400"><?= htmlspecialchars($lst['list_title_en']) ?></p>
              <?php endif; ?>
            </td>
            <td class="text-gray-500"><?= htmlspecialchars($lst['list_year'] ?? '') ?></td>
            <td>
              <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-black bg-purple-50 text-purple-700">
                <i class="fa-solid fa-users text-xs"></i> <?= (int)$lst['items_count'] ?>
              </span>
            </td>
            <td>
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="list_id" value="<?= $lst['list_id'] ?>">
                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-black border transition
                  <?= $lst['list_active'] ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100' : 'bg-gray-100 text-gray-500 border-gray-200 hover:bg-gray-200' ?>">
                  <i class="fa-solid fa-circle text-xs"></i>
                  <?= $lst['list_active'] ? 'نشطة' : 'مخفية' ?>
                </button>
              </form>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <a href="admin.php?p=lists&edit=<?= $lst['list_id'] ?>" class="btn-secondary text-xs px-3 py-1.5">
                  <i class="fa-solid fa-pen text-xs"></i> تعديل
                </a>
                <form method="POST" onsubmit="return confirm('هل تريد حذف هذه القائمة؟')">
                  <input type="hidden" name="action" value="delete_list">
                  <input type="hidden" name="list_id" value="<?= $lst['list_id'] ?>">
                  <button type="submit" class="btn-danger text-xs px-3 py-1.5">
                    <i class="fa-solid fa-trash text-xs"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
