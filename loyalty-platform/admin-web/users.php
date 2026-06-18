<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('users', 'view');

$lists = all("select id, name from admin.user_lists order by name");

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'add_to_lists') {
    require_perm('lists', 'edit');
    $uids = array_filter((array) post('user_ids', []));
    $lids = array_filter((array) post('list_ids', []));
    $added = 0;
    foreach ($lids as $lid) foreach ($uids as $uid) {
      q("insert into admin.list_members (list_id, user_id) values (:l,:u) on conflict do nothing", ['l'=>$lid,'u'=>$uid]);
      $added += db()->lastInsertId() !== false ? 1 : 1;
    }
    audit('add_to_lists', 'users', null, ['users'=>count($uids),'lists'=>count($lids)]);
    flash('تمت إضافة ' . count($uids) . ' مستخدم إلى ' . count($lids) . ' قائمة.');
  } elseif ($act === 'delete') {
    require_perm('users', 'delete');
    q("delete from public.users where id=:id", ['id'=>(string)post('id')]);
    audit('delete', 'user', (string)post('id'));
    flash('تم حذف المستخدم.');
  }
  redirect('users.php?' . http_build_query(array_filter(['q'=>get('q'),'filter'=>get('filter')])));
}

$qstr = trim((string) get('q', ''));
$filter = (string) get('filter', '');
$where = []; $params = [];
if ($qstr !== '') { $where[]='(u.name ilike :q or u.phone ilike :q or u.email ilike :q)'; $params['q']='%'.$qstr.'%'; }
if ($filter === 'shared')   $where[] = 'u.share_profile_with_merchants';
if ($filter === 'hidden')   $where[] = 'not u.share_profile_with_merchants';
if ($filter === 'new7')     $where[] = "u.created_at >= now()-interval '7 days'";
$wsql = $where ? ('where '.implode(' and ',$where)) : '';
$total = (int) scalar("select count(*) from public.users u $wsql", $params);
$page = page_num(); $off = ($page-1)*per_page();
$rows = all("select u.*,
   (select count(*) from public.user_stores us where us.user_id=u.id) stores,
   (select coalesce(sum(available_points),0) from public.user_stores us where us.user_id=u.id) points
   from public.users u $wsql order by u.created_at desc limit ".per_page()." offset $off", $params);

$title = 'المستخدمون';
require __DIR__ . '/partials/header.php';
?>
<form class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="<?= e($qstr) ?>" placeholder="بحث: اسم / جوال / بريد" class="border rounded-lg px-4 py-2 flex-1 min-w-[220px]">
  <select name="filter" class="border rounded-lg px-4 py-2">
    <option value="">الكل</option>
    <option value="new7" <?= $filter==='new7'?'selected':'' ?>>الجدد (٧ أيام)</option>
    <option value="shared" <?= $filter==='shared'?'selected':'' ?>>يشارك بياناته</option>
    <option value="hidden" <?= $filter==='hidden'?'selected':'' ?>>مخفي عن المتاجر</option>
  </select>
  <button class="bg-gray-800 text-white rounded-lg px-5 py-2 font-bold">تصفية</button>
  <a href="export.php?type=users&<?= e(http_build_query(array_filter(['q'=>$qstr]))) ?>" class="bg-green-600 text-white rounded-lg px-5 py-2 font-bold flex items-center">⬇ CSV</a>
</form>

<form method="post" id="bulk">
  <?= csrf_field() ?><input type="hidden" name="action" value="add_to_lists">
  <?php if (can('lists','edit') && $lists): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 flex flex-wrap items-center gap-3">
    <span class="font-bold text-amber-800">إضافة المحدّدين إلى قوائم:</span>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($lists as $l): ?>
        <label class="inline-flex items-center gap-1.5 bg-white border rounded-lg px-3 py-1.5 text-sm">
          <input type="checkbox" name="list_ids[]" value="<?= e($l['id']) ?>"> <?= e($l['name']) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <button class="bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg px-5 py-1.5 mr-auto">إضافة</button>
  </div>
  <?php endif; ?>

  <div class="bg-white rounded-xl border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-500 text-right"><tr>
        <th class="px-4 py-3"><input type="checkbox" onclick="document.querySelectorAll('.urow').forEach(c=>c.checked=this.checked)"></th>
        <th class="px-4 py-3 font-medium">الاسم</th><th class="px-4 py-3 font-medium">الجوال</th>
        <th class="px-4 py-3 font-medium">البريد</th><th class="px-4 py-3 font-medium">متاجر/نقاط</th>
        <th class="px-4 py-3 font-medium">المشاركة</th><th class="px-4 py-3 font-medium">انضمّ</th><th class="px-4 py-3 font-medium"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="px-4 py-2.5"><input class="urow" type="checkbox" name="user_ids[]" value="<?= e($u['id']) ?>"></td>
          <td class="px-4 py-2.5 font-bold"><?= e($u['name']) ?></td>
          <td class="px-4 py-2.5"><?= e($u['phone']) ?></td>
          <td class="px-4 py-2.5 text-gray-500"><?= e($u['email'] ?: '—') ?></td>
          <td class="px-4 py-2.5"><?= n($u['stores']) ?> / <?= n($u['points']) ?></td>
          <td class="px-4 py-2.5"><?= $u['share_profile_with_merchants']?badge('يشارك','green'):badge('مخفي','gray') ?></td>
          <td class="px-4 py-2.5 text-gray-500"><?= d($u['created_at']) ?></td>
          <td class="px-4 py-2.5"><a href="user_view.php?id=<?= e($u['id']) ?>" class="text-amber-600">عرض</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">لا نتائج.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</form>
<?= pager($total, $page, http_build_query(array_filter(['q'=>$qstr,'filter'=>$filter]))) ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
