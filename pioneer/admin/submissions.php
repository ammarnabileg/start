<?php
// No special permission needed — any logged-in admin can view submissions
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action'] ?? '';
    $sub_id = (int)($_POST['sub_id'] ?? 0);
    $note  = pi_escape($_POST['note'] ?? '');

    if ($act === 'approve') {
        $r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_id=$sub_id");
        if ($r && $r->num_rows) {
            $sub = $r->fetch_assoc();
            $data = json_decode($sub['sub_data'], true);

            if ($sub['sub_type'] === 'personality') {
                $name_ar = pi_escape($data['p_name_ar'] ?? '');
                $name_en = pi_escape($data['p_name_en'] ?? '');
                $title   = pi_escape($data['p_title'] ?? '');
                $nat     = pi_escape($data['p_nationality'] ?? '');
                $res     = pi_escape($data['p_residence'] ?? '');
                $bio     = pi_escape($data['p_bio'] ?? '');
                $photo   = pi_escape($data['p_photo'] ?? '');
                $mysqli->query("INSERT INTO pi_personalities (p_name_ar,p_name_en,p_title,p_nationality,p_residence,p_bio,p_photo) VALUES ('$name_ar','$name_en','$title','$nat','$res','$bio','$photo')");
                $new_id = $mysqli->insert_id;
                foreach (($data['categories'] ?? []) as $cat_id) {
                    $cat_id = (int)$cat_id;
                    $mysqli->query("INSERT INTO pi_personality_categories (p_id,cat_id) VALUES ($new_id,$cat_id)");
                }
            } elseif ($sub['sub_type'] === 'institution') {
                $name_ar = pi_escape($data['inst_name_ar'] ?? '');
                $name_en = pi_escape($data['inst_name_en'] ?? '');
                $desc    = pi_escape($data['inst_description'] ?? '');
                $logo    = pi_escape($data['inst_logo'] ?? '');
                $mysqli->query("INSERT INTO pi_institutions (inst_name_ar,inst_name_en,inst_description,inst_logo) VALUES ('$name_ar','$name_en','$desc','$logo')");
                $new_id = $mysqli->insert_id;
                foreach (($data['categories'] ?? []) as $cat_id) {
                    $cat_id = (int)$cat_id;
                    $mysqli->query("INSERT INTO pi_institution_categories (inst_id,cat_id) VALUES ($new_id,$cat_id)");
                }
            }
            $mysqli->query("UPDATE pi_submissions SET sub_status='approved',sub_note='$note' WHERE sub_id=$sub_id");
            $msg = 'تم قبول الاقتراح ونشره';
        }
    }

    if ($act === 'reject') {
        $mysqli->query("UPDATE pi_submissions SET sub_status='rejected',sub_note='$note' WHERE sub_id=$sub_id");
        $msg = 'تم رفض الاقتراح';
    }
}

$status_filter = $_GET['status'] ?? 'pending';
$submissions = [];
$r = $mysqli->query("SELECT * FROM pi_submissions WHERE sub_status='$status_filter' ORDER BY sub_created DESC LIMIT 50");
if ($r) while ($row=$r->fetch_assoc()) $submissions[] = $row;

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $counts[$s] = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_status='$s'")->fetch_assoc()['c'];
}
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">مقترحات المستخدمين</h2>
  <div class="flex gap-2">
    <a href="admin.php?p=submissions&status=pending" class="px-4 py-1.5 text-sm font-bold rounded-full transition flex items-center gap-1.5 <?= $status_filter==='pending'?'pi-gradient text-white':'bg-white border border-gray-200 text-gray-600' ?>">
      قيد المراجعة <span class="bg-white/30 px-1.5 rounded-full text-xs"><?= $counts['pending'] ?></span>
    </a>
    <a href="admin.php?p=submissions&status=approved" class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $status_filter==='approved'?'bg-green-500 text-white':'bg-white border border-gray-200 text-gray-600' ?>">مقبولة (<?= $counts['approved'] ?>)</a>
    <a href="admin.php?p=submissions&status=rejected" class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $status_filter==='rejected'?'bg-red-500 text-white':'bg-white border border-gray-200 text-gray-600' ?>">مرفوضة (<?= $counts['rejected'] ?>)</a>
  </div>
</div>

<div class="space-y-4">
  <?php foreach ($submissions as $sub):
    $data = json_decode($sub['sub_data'], true) ?? [];
  ?>
  <div class="bg-white rounded-2xl shadow-sm p-5" x-data="{open:false}">
    <div class="flex items-start justify-between">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-2">
          <span class="px-2.5 py-0.5 <?= $sub['sub_type']==='personality'?'bg-blue-100 text-blue-700':'bg-indigo-100 text-indigo-700' ?> rounded-full text-xs font-bold">
            <?= $sub['sub_type']==='personality'?'شخصية':'مؤسسة' ?>
          </span>
          <span class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($sub['sub_created'])) ?></span>
          <?php if ($sub['sub_submitter_name']): ?>
          <span class="text-gray-500 text-xs">بواسطة: <?= htmlspecialchars($sub['sub_submitter_name']) ?></span>
          <?php endif; ?>
        </div>
        <h3 class="font-black text-gray-800 text-base"><?= htmlspecialchars($data['p_name_ar'] ?? $data['inst_name_ar'] ?? 'بدون اسم') ?></h3>
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($data['p_title'] ?? $data['inst_description'] ?? '') ?></p>
      </div>

      <div class="flex items-center gap-2">
        <button @click="open=!open" class="px-4 py-1.5 bg-gray-100 text-gray-600 text-sm font-bold rounded-lg hover:bg-gray-200 transition">
          <span x-text="open?'إخفاء':'تفاصيل'"></span>
        </button>
        <?php if ($status_filter === 'pending'): ?>
        <button onclick="document.getElementById('approve-<?= $sub['sub_id'] ?>').showModal()"
          class="px-4 py-1.5 bg-green-500 text-white text-sm font-bold rounded-lg hover:bg-green-600 transition">
          قبول
        </button>
        <button onclick="document.getElementById('reject-<?= $sub['sub_id'] ?>').showModal()"
          class="px-4 py-1.5 bg-red-50 text-red-600 text-sm font-bold rounded-lg hover:bg-red-100 transition">
          رفض
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Details -->
    <div x-show="open" x-cloak x-transition class="mt-4 pt-4 border-t border-gray-100">
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
        <?php if ($data['p_name_en'] ?? null): ?><div><span class="text-gray-400 text-xs">الاسم الإنجليزي</span><p class="font-semibold" dir="ltr"><?= htmlspecialchars($data['p_name_en']) ?></p></div><?php endif; ?>
        <?php if ($data['p_nationality'] ?? null): ?><div><span class="text-gray-400 text-xs">الجنسية</span><p class="font-semibold"><?= htmlspecialchars($data['p_nationality']) ?></p></div><?php endif; ?>
        <?php if ($data['p_residence'] ?? null): ?><div><span class="text-gray-400 text-xs">بلد الإقامة</span><p class="font-semibold"><?= htmlspecialchars($data['p_residence']) ?></p></div><?php endif; ?>
        <?php if ($sub['sub_submitter_email']): ?><div><span class="text-gray-400 text-xs">البريد الإلكتروني</span><p class="font-semibold text-xs" dir="ltr"><?= htmlspecialchars($sub['sub_submitter_email']) ?></p></div><?php endif; ?>
      </div>
      <?php if ($data['p_bio'] ?? null): ?>
      <div class="mt-3"><span class="text-gray-400 text-xs block mb-1">السيرة الذاتية</span>
      <p class="text-gray-700 text-sm leading-7 bg-gray-50 rounded-lg p-3"><?= nl2br(htmlspecialchars($data['p_bio'])) ?></p></div>
      <?php endif; ?>
      <?php if (!empty($data['categories'])): ?>
      <div class="mt-3"><span class="text-gray-400 text-xs block mb-1">التصنيفات المختارة</span>
      <div class="flex flex-wrap gap-1">
        <?php foreach ($data['categories'] as $cid):
          $r2 = $mysqli->query("SELECT cat_name FROM pi_categories WHERE cat_id=".(int)$cid);
          if ($r2 && $r2->num_rows) { $cn=$r2->fetch_assoc()['cat_name']; ?>
          <span class="px-2 py-0.5 bg-purple-50 text-purple-800 text-xs font-semibold rounded-full"><?= htmlspecialchars($cn) ?></span>
          <?php } endforeach; ?>
      </div></div>
      <?php endif; ?>
      <?php if ($sub['sub_note']): ?>
      <div class="mt-3 bg-yellow-50 rounded-lg p-3"><span class="text-yellow-700 text-xs font-bold">ملاحظة المراجع: </span><span class="text-yellow-800 text-sm"><?= htmlspecialchars($sub['sub_note']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Approve dialog -->
  <?php if ($status_filter === 'pending'): ?>
  <dialog id="approve-<?= $sub['sub_id'] ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50">
    <h3 class="font-black text-gray-800 mb-3">تأكيد القبول</h3>
    <p class="text-gray-500 text-sm mb-4">سيتم نشر هذه الشخصية على الموقع فوراً</p>
    <form method="POST">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">
      <textarea name="note" rows="2" placeholder="ملاحظة (اختياري)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-green-400"></textarea>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 py-2.5 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition">قبول ونشر</button>
        <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
      </div>
    </form>
  </dialog>

  <dialog id="reject-<?= $sub['sub_id'] ?>" class="rounded-2xl p-6 shadow-2xl max-w-sm w-full backdrop:bg-black/50">
    <h3 class="font-black text-gray-800 mb-3">رفض الاقتراح</h3>
    <form method="POST">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="sub_id" value="<?= $sub['sub_id'] ?>">
      <textarea name="note" rows="2" placeholder="سبب الرفض (اختياري)" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-red-400"></textarea>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 py-2.5 bg-red-500 text-white font-bold rounded-xl hover:bg-red-600 transition">رفض</button>
        <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">إلغاء</button>
      </div>
    </form>
  </dialog>
  <?php endif; ?>
  <?php endforeach; ?>

  <?php if (empty($submissions)): ?>
  <div class="text-center py-16 text-gray-400">
    <i class="fa-solid fa-inbox text-5xl mb-4 block"></i>
    <p class="font-bold">لا توجد اقتراحات <?= $status_filter==='pending'?'قيد المراجعة':($status_filter==='approved'?'مقبولة':'مرفوضة') ?></p>
  </div>
  <?php endif; ?>
</div>
