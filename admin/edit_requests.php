<?php
pi_require_any_perm('manage_edit_requests','view_edit_requests');
$msg = '';

/* ── Word-level diff helper ────────────────────────────────────────── */
function pi_word_diff($old, $new) {
    if ($old === $new) return '<span class="text-gray-700">'.htmlspecialchars($new).'</span>';
    if ($old === '') return '<ins class="diff-add">'.htmlspecialchars($new).'</ins>';
    if ($new === '') return '<del class="diff-del">'.htmlspecialchars($old).'</del>';

    // For very long texts fall back to block-level diff
    if (mb_strlen($old) + mb_strlen($new) > 4000) {
        return '<del class="diff-del diff-block">'.htmlspecialchars(mb_substr($old,0,300)).(mb_strlen($old)>300?'…':'').'</del>'
              .'<ins class="diff-add diff-block">'.htmlspecialchars(mb_substr($new,0,300)).(mb_strlen($new)>300?'…':'').'</ins>';
    }

    $ow = preg_split('/(\s+)/u', $old, -1, PREG_SPLIT_DELIM_CAPTURE);
    $nw = preg_split('/(\s+)/u', $new, -1, PREG_SPLIT_DELIM_CAPTURE);
    $m = count($ow); $n = count($nw);
    $dp = array_fill(0, $m+1, array_fill(0, $n+1, 0));
    for ($i=1;$i<=$m;$i++)
        for ($j=1;$j<=$n;$j++)
            $dp[$i][$j] = $ow[$i-1]===$nw[$j-1] ? $dp[$i-1][$j-1]+1 : max($dp[$i-1][$j],$dp[$i][$j-1]);
    $res=[]; $i=$m; $j=$n;
    while($i>0||$j>0){
        if($i>0&&$j>0&&$ow[$i-1]===$nw[$j-1]){array_unshift($res,['s',$ow[$i-1]]);$i--;$j--;}
        elseif($j>0&&($i===0||$dp[$i][$j-1]>=$dp[$i-1][$j])){array_unshift($res,['+',$nw[$j-1]]);$j--;}
        else{array_unshift($res,['-',$ow[$i-1]]);$i--;}
    }
    $html='';
    foreach($res as $r){
        if($r[0]==='s') $html.=htmlspecialchars($r[1]);
        elseif($r[0]==='+') $html.='<ins class="diff-add">'.htmlspecialchars($r[1]).'</ins>';
        else $html.='<del class="diff-del">'.htmlspecialchars($r[1]).'</del>';
    }
    return $html;
}

/* ── Handle POST actions ───────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action'] ?? '';
    $er_id = (int)($_POST['er_id'] ?? 0);
    $note  = pi_escape(trim($_POST['admin_note'] ?? ''));

    if ($act === 'approve' && $er_id) {
        $r = $mysqli->query("SELECT * FROM pi_edit_requests WHERE er_id=$er_id");
        if ($r && $r->num_rows) {
            $req = $r->fetch_assoc();
            $edit_data = json_decode($req['er_edit_data'] ?? '{}', true) ?? [];
            if ($req['er_req_type'] === 'upgrade') {
                $upto = pi_escape($req['er_upgrade_to']);
                if ($req['er_entity_type'] === 'personality') {
                    $verified = $upto ? 1 : 0;
                    $mtype = $upto === 'executive' ? 'executive' : 'verified';
                    $mysqli->query("UPDATE pi_personalities SET p_verified=$verified, p_membership_type='$mtype' WHERE p_id={$req['er_entity_id']}");
                } else {
                    $verified = $upto ? 1 : 0;
                    $mtype = $upto === 'executive' ? 'executive' : 'verified';
                    $mysqli->query("UPDATE pi_institutions SET inst_verified=$verified, inst_membership_type='$mtype' WHERE inst_id={$req['er_entity_id']}");
                }
            } elseif ($req['er_req_type'] === 'edit' && !empty($edit_data)) {
                if ($req['er_entity_type'] === 'personality') {
                    $sets = [];
                    $map = ['name_ar'=>'p_name_ar','name_en'=>'p_name_en','title'=>'p_title','nationality'=>'p_nationality','residence'=>'p_residence','bio'=>'p_bio','website'=>'p_website','photo'=>'p_photo'];
                    foreach ($map as $key => $col) {
                        if (array_key_exists($key, $edit_data)) $sets[] = "$col='" . pi_escape($edit_data[$key]) . "'";
                    }
                    if ($sets) $mysqli->query("UPDATE pi_personalities SET " . implode(',', $sets) . " WHERE p_id={$req['er_entity_id']}");
                } else {
                    $sets = [];
                    $map = ['name_ar'=>'inst_name_ar','name_en'=>'inst_name_en','description'=>'inst_description','photo'=>'inst_logo'];
                    foreach ($map as $key => $col) {
                        if (array_key_exists($key, $edit_data)) $sets[] = "$col='" . pi_escape($edit_data[$key]) . "'";
                    }
                    if (!empty($edit_data['country'])) {
                        $cn_esc = pi_escape($edit_data['country']);
                        $cnr = $mysqli->query("SELECT c_id FROM pi_countries WHERE c_name='$cn_esc' LIMIT 1");
                        if ($cnr && $cnr->num_rows) { $cid=(int)$cnr->fetch_assoc()['c_id']; $sets[]="inst_country_id=$cid"; }
                    }
                    if ($sets) $mysqli->query("UPDATE pi_institutions SET " . implode(',', $sets) . " WHERE inst_id={$req['er_entity_id']}");
                }
            }
            $mysqli->query("UPDATE pi_edit_requests SET er_status='approved', er_admin_note='$note' WHERE er_id=$er_id");
            $msg = 'تم قبول الطلب وتطبيق التعديلات';
        }
    }

    if ($act === 'reject' && $er_id) {
        $mysqli->query("UPDATE pi_edit_requests SET er_status='rejected', er_admin_note='$note' WHERE er_id=$er_id");
        $msg = 'تم رفض الطلب';
    }

    if ($act === 'apply_edit' && $er_id) {
        $r = $mysqli->query("SELECT * FROM pi_edit_requests WHERE er_id=$er_id");
        if ($r && $r->num_rows) {
            $req = $r->fetch_assoc();
            $is_p = $req['er_entity_type'] === 'personality';
            if ($is_p) {
                $map = ['name_ar'=>'p_name_ar','name_en'=>'p_name_en','title'=>'p_title','nationality'=>'p_nationality','residence'=>'p_residence','bio'=>'p_bio','website'=>'p_website'];
                $sets = [];
                foreach ($map as $key => $col) {
                    if (array_key_exists($key, $_POST)) $sets[] = "$col='" . pi_escape(trim($_POST[$key])) . "'";
                }
                if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = dirname(__DIR__).'/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'er_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $udir.$fname))
                            $sets[] = "p_photo='uploads/$fname'";
                    }
                }
                if ($sets) $mysqli->query("UPDATE pi_personalities SET " . implode(',', $sets) . " WHERE p_id={$req['er_entity_id']}");
            } else {
                $map = ['name_ar'=>'inst_name_ar','name_en'=>'inst_name_en','description'=>'inst_description'];
                $sets = [];
                foreach ($map as $key => $col) {
                    if (isset($_POST[$key])) $sets[] = "$col='" . pi_escape(trim($_POST[$key])) . "'";
                }
                if (!empty($_POST['country'])) {
                    $cn_esc = pi_escape(trim($_POST['country']));
                    $cnr = $mysqli->query("SELECT c_id FROM pi_countries WHERE c_name='$cn_esc' LIMIT 1");
                    if ($cnr && $cnr->num_rows) { $cid=(int)$cnr->fetch_assoc()['c_id']; $sets[]="inst_country_id=$cid"; }
                }
                if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                        $udir = dirname(__DIR__).'/uploads/';
                        if (!is_dir($udir)) mkdir($udir, 0755, true);
                        $fname = 'er_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $udir.$fname))
                            $sets[] = "inst_logo='uploads/$fname'";
                    }
                }
                if ($sets) $mysqli->query("UPDATE pi_institutions SET " . implode(',', $sets) . " WHERE inst_id={$req['er_entity_id']}");
            }
            $mysqli->query("UPDATE pi_edit_requests SET er_status='approved', er_admin_note='$note' WHERE er_id=$er_id");
            $msg = 'تم تطبيق التعديلات بنجاح';
        }
    }
}

$all_countries = pi_get_countries();

/* ── Filters ───────────────────────────────────────────────────────── */
$status_filter = $_GET['status'] ?? 'pending';
$type_filter   = $_GET['etype'] ?? '';
$allowed_statuses = ['pending','approved','rejected'];
if (!in_array($status_filter, $allowed_statuses)) $status_filter = 'pending';

$where = "WHERE er.er_status='" . pi_escape($status_filter) . "' AND er.er_req_type='edit'";
if ($type_filter === 'personality' || $type_filter === 'institution') $where .= " AND er.er_entity_type='" . pi_escape($type_filter) . "'";

$counts = [];
foreach ($allowed_statuses as $s) {
    $cr = $mysqli->query("SELECT COUNT(*) c FROM pi_edit_requests WHERE er_status='$s' AND er_req_type='edit'");
    $counts[$s] = $cr ? (int)$cr->fetch_assoc()['c'] : 0;
}

$requests = [];
$fetch_sql = "SELECT er.*, u.u_name, u.u_email, u.u_id AS uid FROM pi_edit_requests er LEFT JOIN pi_users u ON er.er_user_id=u.u_id $where ORDER BY er.er_created DESC LIMIT 100";
$r = $mysqli->query($fetch_sql);
$fetch_error = $r === false ? $mysqli->error : '';
if ($r) while ($row=$r->fetch_assoc()) $requests[] = $row;

$status_map = [
    'pending'  => ['text'=>'قيد المراجعة','class'=>'bg-yellow-100 text-yellow-700'],
    'approved' => ['text'=>'مقبول',       'class'=>'bg-green-100 text-green-700'],
    'rejected' => ['text'=>'مرفوض',       'class'=>'bg-red-100 text-red-700'],
];
?>
<style>
ins.diff-add{background:#dcfce7;color:#15803d;text-decoration:none;border-radius:2px;padding:0 1px}
del.diff-del{background:#fee2e2;color:#dc2626;border-radius:2px;padding:0 1px}
ins.diff-add.diff-block,del.diff-del.diff-block{display:block;padding:2px 4px;margin:1px 0;border-radius:4px}
.diff-text{font-size:.75rem;line-height:1.7;word-break:break-word}
</style>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check ml-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <h2 class="text-xl font-black text-gray-800">طلبات التعديل</h2>
  <div class="flex gap-2 flex-wrap">
    <?php foreach (['pending'=>'قيد المراجعة','approved'=>'مقبولة','rejected'=>'مرفوضة'] as $sk=>$sl): ?>
    <a href="admin.php?p=edit_requests&status=<?= $sk ?>"
      class="px-4 py-1.5 text-sm font-bold rounded-full transition flex items-center gap-1.5 <?= $status_filter===$sk?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
      <?= $sl ?> <span class="<?= $status_filter===$sk?'bg-white/30':'bg-gray-100' ?> px-1.5 rounded-full text-xs"><?= $counts[$sk] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Filter bar -->
<form method="GET" action="admin.php" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex gap-3 items-center mb-5 flex-wrap">
  <input type="hidden" name="p" value="edit_requests">
  <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
  <select name="etype" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">
    <option value="">كل الأنواع</option>
    <option value="personality" <?= $type_filter==='personality'?'selected':'' ?>>شخصيات فقط</option>
    <option value="institution" <?= $type_filter==='institution'?'selected':'' ?>>مؤسسات فقط</option>
  </select>
  <button type="submit" class="px-5 py-2 pi-primary-bg text-white font-bold rounded-xl text-sm hover:opacity-90 transition">فلتر</button>
</form>

<?php if ($fetch_error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-5 py-3 mb-5 text-sm font-mono"><?= htmlspecialchars($fetch_error) ?></div>
<?php endif; ?>
<?php if (empty($requests)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-16 text-gray-300">
  <i class="fa-solid fa-inbox text-5xl mb-4 block"></i>
  <p class="font-semibold text-gray-400">لا توجد طلبات <?= $status_filter === 'pending' ? 'قيد المراجعة' : '' ?></p>
</div>
<?php else: ?>
<div class="space-y-5">
<?php foreach ($requests as $req):
  $edit_data = json_decode($req['er_edit_data'] ?? '{}', true) ?? [];
  $is_p = $req['er_entity_type'] === 'personality';

  /* ── Load full current entity ── */
  $current = [];
  $entity_name = ''; $entity_photo = '';
  if ($is_p) {
      $ecr = $mysqli->query("SELECT p_name_ar,p_name_en,p_title,p_nationality,p_residence,p_bio,p_website,p_photo FROM pi_personalities WHERE p_id={$req['er_entity_id']}");
      if ($ecr && $ecr->num_rows) {
          $row = $ecr->fetch_assoc();
          $current = [
            'name_ar'   => $row['p_name_ar'],
            'name_en'   => $row['p_name_en'],
            'title'     => $row['p_title'],
            'nationality'=> $row['p_nationality'],
            'residence' => $row['p_residence'],
            'bio'       => $row['p_bio'],
            'website'   => $row['p_website'] ?? '',
            'photo'     => $row['p_photo'],
          ];
          $entity_name  = $row['p_name_ar'];
          $entity_photo = $row['p_photo'];
      }
  } else {
      $ecr = $mysqli->query("SELECT inst_name_ar,inst_name_en,inst_description,inst_logo,
          (SELECT c_name FROM pi_countries WHERE c_id=inst_country_id LIMIT 1) AS country_name
          FROM pi_institutions WHERE inst_id={$req['er_entity_id']}");
      if ($ecr && $ecr->num_rows) {
          $row = $ecr->fetch_assoc();
          $current = [
            'name_ar'     => $row['inst_name_ar'],
            'name_en'     => $row['inst_name_en'],
            'country'     => $row['country_name'] ?? '',
            'description' => $row['inst_description'],
            'photo'       => $row['inst_logo'],
          ];
          $entity_name  = $row['inst_name_ar'];
          $entity_photo = $row['inst_logo'];
      }
  }

  $entity_url = $is_p
    ? '../profile.php?id=' . $req['er_entity_id']
    : '../institution.php?id=' . $req['er_entity_id'];

  $all_fields = $is_p
    ? ['name_ar'=>'الاسم بالعربي','name_en'=>'الاسم بالإنجليزي','title'=>'المسمى','nationality'=>'الجنسية','residence'=>'الإقامة','bio'=>'السيرة الذاتية','website'=>'الموقع الإلكتروني']
    : ['name_ar'=>'الاسم بالعربي','name_en'=>'الاسم بالإنجليزي','country'=>'الدولة','description'=>'الوصف'];

  /* count changed fields */
  $changed_count = 0;
  foreach (array_keys($all_fields) as $fk) {
      $cur = $current[$fk] ?? '';
      if (array_key_exists($fk, $edit_data) && $edit_data[$fk] !== $cur) $changed_count++;
  }
  $photo_changed = array_key_exists('photo', $edit_data) && $edit_data['photo'] !== ($current['photo'] ?? '');
  if ($photo_changed) $changed_count++;
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

  <!-- Header -->
  <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50/70 flex-wrap gap-3">
    <div class="flex items-center gap-3">
      <!-- Entity photo -->
      <?php if ($entity_photo): ?>
      <img src="<?= htmlspecialchars($entity_photo) ?>" class="w-11 h-11 <?= $is_p?'rounded-full':'rounded-xl' ?> object-cover border border-gray-200 flex-shrink-0">
      <?php else: ?>
      <div class="w-11 h-11 <?= $is_p?'rounded-full':'rounded-xl' ?> bg-gray-200 flex items-center justify-center text-gray-400 text-lg flex-shrink-0">
        <i class="fa-solid fa-<?= $is_p?'user':'building' ?>"></i>
      </div>
      <?php endif; ?>
      <div>
        <div class="flex items-center gap-2 flex-wrap mb-0.5">
          <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $is_p?'bg-blue-100 text-blue-700':'bg-indigo-100 text-indigo-700' ?>">
            <?= $is_p?'شخصية':'مؤسسة' ?>
          </span>
          <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-purple-100 text-purple-700">تعديل</span>
          <?php if ($changed_count > 0): ?>
          <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">
            <?= $changed_count ?> حقل<?= $changed_count===1?'':'ات' ?> معدّلة
          </span>
          <?php endif; ?>
          <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($req['er_created'])) ?></span>
        </div>
        <h3 class="font-black text-gray-800 text-sm"><?= htmlspecialchars($entity_name ?: 'محذوف') ?></h3>
        <?php if ($req['u_name']): ?>
        <a href="admin.php?p=users&view=<?= $req['uid'] ?>" class="text-xs text-purple-600 font-semibold hover:underline">
          <i class="fa-solid fa-circle-user ml-0.5"></i><?= htmlspecialchars($req['u_name']) ?> — <?= htmlspecialchars($req['u_email'] ?? '') ?>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-xs px-2.5 py-1 rounded-full font-bold <?= $status_map[$req['er_status']]['class'] ?>">
        <?= $status_map[$req['er_status']]['text'] ?>
      </span>
      <!-- View entity button -->
      <a href="<?= htmlspecialchars($entity_url) ?>" target="_blank"
        class="px-3 py-1.5 text-xs font-bold bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200 transition flex items-center gap-1">
        <i class="fa-solid fa-eye"></i> مشاهدة الصفحة
      </a>
      <?php if ($status_filter === 'pending'): ?>
      <?php if ($req['er_req_type'] === 'edit'): ?>
      <button onclick="openApplyModal(<?= htmlspecialchars(json_encode([
        'er_id'       => $req['er_id'],
        'entity_name' => $entity_name,
        'entity_type' => $req['er_entity_type'],
        'is_p'        => ($req['er_entity_type']==='personality'),
        'edit_data'   => $edit_data,
        'current'     => $current,
      ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
        class="px-3 py-1.5 text-xs font-black bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition flex items-center gap-1">
        <i class="fa-solid fa-pen-to-square"></i> تعديل وتطبيق
      </button>
      <?php endif; ?>
      <button onclick="openAction(<?= $req['er_id'] ?>,'approve')"
        class="px-3 py-1.5 text-xs font-black bg-green-500 text-white rounded-xl hover:bg-green-600 transition flex items-center gap-1">
        <i class="fa-solid fa-check"></i> قبول مباشر
      </button>
      <button onclick="openAction(<?= $req['er_id'] ?>,'reject')"
        class="px-3 py-1.5 text-xs font-black bg-red-100 text-red-600 rounded-xl hover:bg-red-200 transition flex items-center gap-1">
        <i class="fa-solid fa-xmark"></i> رفض
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($req['er_req_type'] === 'edit'): ?>
  <!-- Diff table -->
  <div class="overflow-x-auto">
    <table class="w-full text-sm" style="border-collapse:collapse">
      <thead>
        <tr>
          <th class="px-4 py-2.5 text-xs font-black text-gray-500 bg-gray-50 border-b border-gray-100 text-right w-32">الحقل</th>
          <th class="px-4 py-2.5 text-xs font-black text-gray-600 bg-gray-50 border-b border-gray-100 text-right w-1/2">البيانات الحالية</th>
          <th class="px-4 py-2.5 text-xs font-black text-purple-600 bg-purple-50 border-b border-gray-100 text-right w-1/2">التعديلات المقترحة</th>
        </tr>
      </thead>
      <tbody>
      <?php
      /* ── Text fields ── */
      foreach ($all_fields as $fk => $fl):
        $cur_val = $current[$fk] ?? '';
        $submitted = array_key_exists($fk, $edit_data);
        $new_val   = $submitted ? ($edit_data[$fk] ?? '') : null;
        $changed   = $submitted && ($new_val !== $cur_val);
        $is_long   = ($fk === 'bio' || $fk === 'description');
        $ltr = ($fk === 'name_en' || $fk === 'website');
      ?>
      <tr class="border-t border-gray-100 <?= $changed ? 'bg-amber-50/40' : '' ?>">
        <td class="px-4 py-3 text-xs font-bold text-gray-500 align-top whitespace-nowrap">
          <?= $fl ?>
          <?php if ($changed): ?>
          <span class="block text-amber-500 text-xs font-bold mt-0.5"><i class="fa-solid fa-circle-dot text-xs"></i> معدّل</span>
          <?php endif; ?>
        </td>
        <td class="px-4 py-3 align-top border-r border-gray-100">
          <?php if ($cur_val !== ''): ?>
          <div class="diff-text <?= $is_long?'whitespace-pre-wrap':'' ?> <?= $ltr?'text-left':'text-right' ?> text-gray-700">
            <?php if ($changed): ?>
              <?= pi_word_diff($cur_val, $new_val ?? '') ?>
            <?php else: ?>
              <?= htmlspecialchars($is_long ? $cur_val : mb_substr($cur_val, 0, 200)) ?>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <span class="text-gray-300 text-xs">—</span>
          <?php endif; ?>
        </td>
        <td class="px-4 py-3 align-top <?= $changed ? 'bg-green-50/50' : '' ?>">
          <?php if (!$submitted): ?>
            <span class="text-gray-300 text-xs italic">لم يُغيَّر</span>
          <?php elseif ($new_val === '' && $cur_val !== ''): ?>
            <span class="text-orange-500 text-xs font-bold">— (طلب مسح)</span>
          <?php elseif ($new_val === '' && $cur_val === ''): ?>
            <span class="text-gray-300 text-xs">—</span>
          <?php else: ?>
            <div class="diff-text <?= $is_long?'whitespace-pre-wrap':'' ?> <?= $ltr?'text-left':'text-right' ?> <?= $changed?'text-gray-800 font-semibold':'text-gray-600' ?>">
              <?= htmlspecialchars($is_long ? $new_val : mb_substr($new_val, 0, 200)) ?>
            </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>

      <?php
      /* ── Photo row ── */
      $cur_photo = $current['photo'] ?? '';
      $new_photo = array_key_exists('photo', $edit_data) ? $edit_data['photo'] : null;
      $photo_changed = ($new_photo !== null && $new_photo !== $cur_photo);
      $photo_label  = $is_p ? 'الصورة الشخصية' : 'الشعار';
      ?>
      <tr class="border-t border-gray-100 <?= $photo_changed ? 'bg-amber-50/40' : '' ?>">
        <td class="px-4 py-3 text-xs font-bold text-gray-500 align-top whitespace-nowrap">
          <?= $photo_label ?>
          <?php if ($photo_changed): ?>
          <span class="block text-amber-500 text-xs font-bold mt-0.5"><i class="fa-solid fa-circle-dot text-xs"></i> معدّل</span>
          <?php endif; ?>
        </td>
        <td class="px-4 py-3 align-top border-r border-gray-100">
          <?php if ($cur_photo): ?>
          <div class="flex items-start gap-2">
            <img src="<?= htmlspecialchars($cur_photo) ?>"
              class="<?= $is_p?'rounded-full':'rounded-xl' ?> object-cover border border-gray-200 <?= $photo_changed?'ring-2 ring-red-300':'' ?>"
              style="width:72px;height:72px">
            <?php if ($photo_changed): ?>
            <span class="text-xs text-red-500 font-bold mt-1">الحالية</span>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <span class="text-gray-300 text-xs">لا توجد صورة</span>
          <?php endif; ?>
        </td>
        <td class="px-4 py-3 align-top <?= $photo_changed ? 'bg-green-50/50' : '' ?>">
          <?php if ($new_photo === null): ?>
            <span class="text-gray-300 text-xs italic">لم تُغيَّر</span>
          <?php elseif ($new_photo === '' && $cur_photo !== ''): ?>
            <span class="text-orange-500 text-xs font-bold">— (طلب حذف الصورة)</span>
          <?php elseif ($new_photo !== ''): ?>
          <div class="flex items-start gap-2">
            <img src="<?= htmlspecialchars($new_photo) ?>"
              class="<?= $is_p?'rounded-full':'rounded-xl' ?> object-cover border border-gray-200 ring-2 ring-green-400"
              style="width:72px;height:72px">
            <?php if ($photo_changed): ?>
            <span class="text-xs text-green-600 font-bold mt-1">الجديدة</span>
            <?php endif; ?>
          </div>
          <?php else: ?>
            <span class="text-gray-300 text-xs">—</span>
          <?php endif; ?>
        </td>
      </tr>
      </tbody>
    </table>
  </div>
  <?php endif; /* er_req_type === edit */ ?>

  <?php if ($req['er_notes'] || $req['er_admin_note']): ?>
  <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap gap-3">
    <?php if ($req['er_notes']): ?>
    <p class="text-xs text-gray-600 bg-yellow-50 rounded-lg px-3 py-1.5 flex-1 min-w-0">
      <i class="fa-solid fa-note-sticky text-yellow-500 ml-1"></i><?= htmlspecialchars($req['er_notes']) ?>
    </p>
    <?php endif; ?>
    <?php if ($req['er_admin_note']): ?>
    <p class="text-xs text-gray-600 bg-blue-50 rounded-lg px-3 py-1.5 flex-1 min-w-0">
      <i class="fa-solid fa-comment text-blue-400 ml-1"></i><?= htmlspecialchars($req['er_admin_note']) ?>
    </p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Action modal -->
<div id="action-modal" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,.5)">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" dir="rtl">
      <div id="action-modal-header" class="px-6 py-4 rounded-t-2xl">
        <h3 class="font-black text-white text-base" id="action-modal-title">تأكيد</h3>
      </div>
      <form method="POST" class="p-6 space-y-4">
        <input type="hidden" name="er_id" id="action-er-id">
        <input type="hidden" name="action" id="action-type">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">ملاحظة للمستخدم <span class="text-gray-400 font-normal">(اختياري)</span></label>
          <textarea name="admin_note" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-purple-400 resize-none" placeholder="أكتب ملاحظة..."></textarea>
        </div>
        <div class="flex gap-3">
          <button type="submit" id="action-submit-btn" class="flex-1 py-3 text-white font-black rounded-xl hover:opacity-90 transition">تأكيد</button>
          <button type="button" onclick="closeAction()" class="flex-1 py-3 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Apply Edit Modal -->
<div id="apply-modal" class="fixed inset-0 z-50 hidden" style="background:rgba(0,0,0,.5)">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl" dir="rtl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100" style="background:linear-gradient(135deg,#7c3aed,#a855f7);border-radius:1rem 1rem 0 0">
        <h3 class="font-black text-white text-base">تعديل وتطبيق التغييرات</h3>
        <button type="button" onclick="closeApplyModal()" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
      </div>
      <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
        <input type="hidden" name="action" value="apply_edit">
        <input type="hidden" name="er_id" id="apply-er-id">
        <p class="text-xs text-gray-400 bg-gray-50 rounded-lg px-3 py-2">عدّل القيم حسب الحاجة ثم اضغط «تطبيق» — ستُحفظ مباشرة في الملف وسيُقبل الطلب.</p>
        <div id="apply-fields" class="space-y-3"></div>
        <div>
          <label class="block text-xs font-bold text-gray-600 mb-1">ملاحظة للمستخدم <span class="text-gray-400 font-normal">(اختياري)</span></label>
          <textarea name="admin_note" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 resize-none"></textarea>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="submit" class="flex-1 py-3 text-white font-black rounded-xl hover:opacity-90 transition" style="background:linear-gradient(135deg,#7c3aed,#a855f7)">
            <i class="fa-solid fa-check ml-1"></i> تطبيق
          </button>
          <button type="button" onclick="closeApplyModal()" class="flex-1 py-3 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAction(id, type) {
  document.getElementById('action-er-id').value = id;
  document.getElementById('action-type').value  = type;
  var isA = type === 'approve';
  document.getElementById('action-modal-header').style.background = isA ? 'linear-gradient(135deg,#22c55e,#16a34a)' : 'linear-gradient(135deg,#ef4444,#dc2626)';
  document.getElementById('action-modal-title').textContent = isA ? 'تأكيد القبول وتطبيق التعديلات' : 'تأكيد الرفض';
  document.getElementById('action-submit-btn').style.background = isA ? 'linear-gradient(135deg,#22c55e,#16a34a)' : 'linear-gradient(135deg,#ef4444,#dc2626)';
  document.getElementById('action-modal').classList.remove('hidden');
}
function closeAction() { document.getElementById('action-modal').classList.add('hidden'); }
document.getElementById('action-modal').addEventListener('click', function(e){ if(e.target===this) closeAction(); });

var _countryOpts = <?= json_encode(array_map(function($c){ return ['v'=>$c['c_name'],'l'=>$c['c_flag'].' '.$c['c_name']]; }, $all_countries), JSON_UNESCAPED_UNICODE) ?>;

function openApplyModal(d) {
  if (typeof d === 'string') d = JSON.parse(d);
  document.getElementById('apply-er-id').value = d.er_id;
  var isP = d.is_p;

  function mkSelect(name, val, highlight) {
    var opts = '<option value="">— اختر —</option>';
    _countryOpts.forEach(function(o){ opts += '<option value="'+esc(o.v)+'"'+(o.v===val?' selected':'')+'>'+esc(o.l)+'</option>'; });
    return '<select name="'+name+'" class="w-full border '+(highlight?'border-purple-400 bg-purple-50':'border-gray-200')+' rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400">'+opts+'</select>';
  }

  var fieldDefs = isP
    ? [{k:'name_ar',l:'الاسم بالعربي',t:'text'},{k:'name_en',l:'الاسم بالإنجليزي',t:'text',ltr:true},{k:'title',l:'المسمى الوظيفي',t:'text'},{k:'nationality',l:'الجنسية',t:'select'},{k:'residence',l:'الإقامة',t:'text',ph:'مثال: دبي، لندن...'},{k:'bio',l:'السيرة الذاتية',t:'textarea'},{k:'website',l:'الموقع الإلكتروني',t:'text',ltr:true}]
    : [{k:'name_ar',l:'الاسم بالعربي',t:'text'},{k:'name_en',l:'الاسم بالإنجليزي',t:'text',ltr:true},{k:'country',l:'الدولة',t:'select'},{k:'description',l:'الوصف',t:'textarea'}];

  var html = '';
  fieldDefs.forEach(function(f) {
    var reqVal = (d.edit_data && d.edit_data[f.k] !== undefined) ? d.edit_data[f.k] : '';
    var curVal = (d.current && d.current[f.k]) ? d.current[f.k] : '';
    var val = reqVal !== '' ? reqVal : curVal;
    var changed = reqVal !== '' && reqVal !== curVal;
    var hi = changed ? 'border-purple-400 bg-purple-50' : 'border-gray-200';
    var lbl = f.l + (changed ? ' <span class="text-purple-500 font-normal">(مُعدَّل)</span>' : '');
    var dir = f.ltr ? ' dir="ltr"' : '';
    html += '<div><label class="block text-xs font-bold text-gray-600 mb-1">'+lbl+'</label>';
    if (f.t === 'select') {
      html += mkSelect(f.k, val, changed);
      if (changed && curVal) html += '<p class="text-xs text-gray-400 mt-0.5">الحالي: <span class="line-through text-red-400">'+esc(curVal)+'</span></p>';
    } else if (f.t === 'textarea') {
      html += '<textarea name="'+f.k+'" rows="4" class="w-full border '+hi+' rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400 resize-y"'+dir+'>'+esc(val)+'</textarea>';
      if (changed && curVal) html += '<p class="text-xs text-gray-400 mt-0.5">الحالي: <span class="line-through text-red-400">'+esc(curVal.substring(0,80))+'…</span></p>';
    } else {
      html += '<input type="text" name="'+f.k+'" class="w-full border '+hi+' rounded-xl px-3 py-2 text-sm outline-none focus:border-purple-400"'+dir+' value="'+esc(val)+'" placeholder="'+(f.ph?esc(f.ph):'')+'">';
      if (changed && curVal) html += '<p class="text-xs text-gray-400 mt-0.5">الحالي: <span class="line-through text-red-400">'+esc(curVal)+'</span></p>';
    }
    html += '</div>';
  });

  // Photo
  var photoLbl = isP ? 'الصورة الشخصية' : 'الشعار';
  var curPhoto = (d.current && d.current.photo) ? d.current.photo : '';
  var newPhoto = (d.edit_data && d.edit_data.photo) ? d.edit_data.photo : '';
  html += '<div class="border border-gray-200 rounded-xl p-3"><label class="block text-xs font-bold text-gray-600 mb-2">'+photoLbl+'</label>';
  html += '<div class="flex gap-4 mb-3">';
  if (curPhoto) html += '<div class="text-center"><img src="'+esc(curPhoto)+'" class="'+(isP?'rounded-full':'rounded-xl')+' object-cover border border-gray-200 mb-1" style="width:56px;height:56px"><span class="text-xs text-gray-400">الحالية</span></div>';
  if (newPhoto && newPhoto !== curPhoto) html += '<div class="text-center"><img src="'+esc(newPhoto)+'" class="'+(isP?'rounded-full':'rounded-xl')+' object-cover border-2 border-green-400 mb-1" style="width:56px;height:56px"><span class="text-xs text-green-600 font-bold">المقترحة</span></div>';
  html += '</div>';
  html += '<label class="text-xs text-gray-500 block mb-1">رفع صورة جديدة <span class="text-gray-400">(اتركه فارغاً للإبقاء على الحالية)</span></label>';
  html += '<input type="file" name="photo_file" accept="image/*" class="w-full text-sm text-gray-600"></div>';

  document.getElementById('apply-fields').innerHTML = html;
  document.getElementById('apply-modal').classList.remove('hidden');
}
function closeApplyModal() { document.getElementById('apply-modal').classList.add('hidden'); }
document.getElementById('apply-modal').addEventListener('click', function(e){ if(e.target===this) closeApplyModal(); });
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
