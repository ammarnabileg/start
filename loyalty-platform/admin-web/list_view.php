<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('lists', 'view');

$id = (string) get('id');
$list = one("select * from admin.user_lists where id=:id", ['id' => $id]);
if (!$list) { http_response_code(404); exit('القائمة غير موجودة'); }

if (is_post()) {
  csrf_check();
  $act = (string) post('action');

  if ($act === 'snapshot') {               // (1) حفظ شريحة ذكية كقائمة ثابتة
    require_perm('lists', 'create');
    $ids = list_user_ids($list);
    $newName = $list['name'] . ' (لقطة ' . date('Y-m-d') . ')';
    $nid = scalar("insert into admin.user_lists (name, description, created_by, is_smart)
                   values (:n, :d, :c, false) returning id",
      ['n' => $newName, 'd' => 'لقطة من شريحة ذكية', 'c' => current_admin()['id']]);
    if ($ids) {
      q("insert into admin.list_members (list_id, user_id)
           select :l, uid from unnest(:ids::uuid[]) uid on conflict do nothing",
        ['l' => $nid, 'ids' => uuid_array($ids)]);
    }
    audit('snapshot', 'list', $id, ['new' => $nid, 'members' => count($ids)]);
    flash('تم حفظ لقطة ثابتة بـ ' . count($ids) . ' عضو.');
    redirect('list_view.php?id=' . urlencode($nid));
  }

  if ($act === 'import' && empty($list['is_smart'])) {   // (5) استيراد CSV
    require_perm('lists', 'edit');
    $raw = (string) post('data');
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
      $raw .= "\n" . file_get_contents($_FILES['file']['tmp_name']);
    }
    $tokens = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $tokens = array_slice(array_unique($tokens), 0, 5000);
    $matched = 0; $missed = 0;
    foreach ($tokens as $tok) {
      if (strpos($tok, '@') !== false) {
        $uid = scalar("select id from public.users where lower(email)=lower(:t) limit 1", ['t' => $tok]);
      } else {
        $d = preg_replace('/[^0-9]/', '', $tok);
        if (strlen($d) < 7) { $missed++; continue; }
        $sfx = substr($d, -9);
        $uid = scalar("select id from public.users where regexp_replace(phone,'[^0-9]','','g') like :s limit 1", ['s' => '%' . $sfx]);
      }
      if ($uid) { q("insert into admin.list_members (list_id,user_id) values (:l,:u) on conflict do nothing", ['l'=>$id,'u'=>$uid]); $matched++; }
      else $missed++;
    }
    audit('import', 'list', $id, ['matched'=>$matched, 'missed'=>$missed]);
    flash("تم استيراد: طوبِق $matched · لم يُطابَق $missed.");
    redirect('list_view.php?id=' . urlencode($id));
  }

  if ($act === 'remove' && empty($list['is_smart'])) {
    require_perm('lists', 'edit');
    q("delete from admin.list_members where list_id=:l and user_id=:u", ['l'=>$id,'u'=>(string)post('user_id')]);
    redirect('list_view.php?id=' . urlencode($id));
  }
}

$ids   = list_user_ids($list);
$total = count($ids);
$page  = page_num(); $off = ($page - 1) * per_page();
$pageIds = array_slice($ids, $off, per_page());
$members = $pageIds
  ? all("select id,name,phone,email,created_at from public.users where id = any(:ids::uuid[]) order by name", ['ids' => uuid_array($pageIds)])
  : [];

$title = 'القائمة: ' . $list['name'];
require __DIR__ . '/partials/header.php';
?>
<a href="lists.php" class="text-sm text-amber-600">← القوائم</a>
<div class="flex items-center gap-3 mt-2 mb-5">
  <h2 class="text-2xl font-extrabold"><?= e($list['name']) ?></h2>
  <?= $list['is_smart'] ? badge('ذكية','blue') : badge('ثابتة','gray') ?>
  <span class="text-gray-500"><?= n($total) ?> عضو</span>
  <div class="mr-auto flex gap-2">
    <?php if ($list['is_smart'] && can('lists','create')): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="snapshot">
        <button class="bg-gray-800 text-white rounded-lg px-4 py-2 text-sm font-bold">📸 حفظ كلقطة ثابتة</button></form>
    <?php endif; ?>
    <?php if (can('notifications','create')): ?><a href="notifications.php?list=<?= e($id) ?>" class="bg-blue-100 text-blue-700 rounded-lg px-4 py-2 text-sm font-bold">🔔 إشعار للقائمة</a><?php endif; ?>
  </div>
</div>

<?php if ($list['is_smart']): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-sm">
    معايير الشريحة: <code class="ltr"><?= e($list['criteria'] ?: '{}') ?></code> — تتحدّث تلقائيًا. «حفظ كلقطة» يجمّدها كقائمة ثابتة.
  </div>
<?php elseif (can('lists','edit')): ?>
  <div class="bg-white border rounded-xl p-4 mb-4">
    <div class="font-bold mb-2">استيراد أعضاء (أرقام/إيميلات)</div>
    <form method="post" enctype="multipart/form-data" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="import">
      <textarea name="data" rows="3" placeholder="ألصق أرقامًا أو إيميلات (سطر لكل واحد أو مفصولة بفواصل)" class="w-full border rounded-lg px-3 py-2 text-sm ltr"></textarea>
      <div class="flex items-center gap-3">
        <input type="file" name="file" accept=".csv,.txt" class="text-sm">
        <button class="bg-amber-500 hover:bg-amber-600 text-white rounded-lg px-5 py-2 text-sm font-bold mr-auto">استيراد وإضافة</button>
      </div>
      <p class="text-xs text-gray-400">المطابقة بالبريد (تامّة) أو بآخر ٩ أرقام من الجوال. الحدّ ٥٠٠٠ سطر.</p>
    </form>
  </div>
<?php endif; ?>

<div class="bg-white rounded-xl border overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-gray-500 text-right"><tr>
      <th class="px-4 py-3 font-medium">الاسم</th><th class="px-4 py-3 font-medium">الجوال</th>
      <th class="px-4 py-3 font-medium">البريد</th><th class="px-4 py-3 font-medium">انضمّ</th><th class="px-4 py-3 font-medium"></th>
    </tr></thead>
    <tbody>
    <?php foreach ($members as $u): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="px-4 py-2.5 font-bold"><a href="user_view.php?id=<?= e($u['id']) ?>" class="hover:text-amber-600"><?= e($u['name']) ?></a></td>
        <td class="px-4 py-2.5"><?= e($u['phone']) ?></td>
        <td class="px-4 py-2.5 text-gray-500"><?= e($u['email'] ?: '—') ?></td>
        <td class="px-4 py-2.5 text-gray-500"><?= d($u['created_at']) ?></td>
        <td class="px-4 py-2.5">
          <?php if (!$list['is_smart'] && can('lists','edit')): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
            <button class="text-red-600 text-xs">إزالة</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$members): ?><tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">لا أعضاء.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= pager($total, $page, 'id=' . urlencode($id)) ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
