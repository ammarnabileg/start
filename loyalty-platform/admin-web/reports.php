<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('reports', 'view');

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  $id  = (string) post('id');
  if ($act === 'status') {
    require_perm('reports', 'edit');
    $s = (string) post('status');
    if (in_array($s, ['open','reviewing','resolved'], true)) {
      q("update public.reports set status=:s where id=:id", ['s'=>$s,'id'=>$id]);
      audit('status','report',$id,['status'=>$s]); flash('تم تحديث حالة البلاغ.');
    }
  } elseif ($act === 'delete') {
    require_perm('reports', 'delete');
    q("delete from public.reports where id=:id", ['id'=>$id]);
    audit('delete','report',$id); flash('تم حذف البلاغ.');
  }
  redirect('reports.php?' . http_build_query(array_filter(['status'=>get('status')])));
}

$status = (string) get('status', '');
$where = []; $params = [];
if (in_array($status, ['open','reviewing','resolved'], true)) { $where[]='r.status=:s'; $params['s']=$status; }
$wsql = $where ? ('where '.implode(' and ',$where)) : '';
$total = (int) scalar("select count(*) from public.reports r $wsql", $params);
$page = page_num(); $off = ($page-1)*per_page();
$rows = all("select r.*, u.name uname, u.phone uphone, m.business_name
   from public.reports r
   left join public.users u on u.id=r.user_id
   left join public.merchants m on m.id=r.merchant_id
   $wsql order by r.created_at desc limit ".per_page()." offset $off", $params);

$counts = [];
foreach (all("select status, count(*) c from public.reports group by status") as $r) $counts[$r['status']]=(int)$r['c'];

$title = 'الشكاوى والبلاغات';
require __DIR__ . '/partials/header.php';
?>
<div class="flex gap-2 mb-4">
  <?php foreach (['' => 'الكل', 'open'=>'مفتوح', 'reviewing'=>'قيد المراجعة', 'resolved'=>'محلول'] as $k=>$lbl):
    $cls = $status===$k ? 'bg-amber-500 text-white' : 'bg-white border text-gray-600';
    $c = $k==='' ? array_sum($counts) : ($counts[$k]??0); ?>
    <a href="?status=<?= $k ?>" class="px-4 py-1.5 rounded-lg text-sm font-bold <?= $cls ?>"><?= e($lbl) ?> (<?= n($c) ?>)</a>
  <?php endforeach; ?>
  <a href="export.php?type=reports" class="px-4 py-1.5 rounded-lg text-sm font-bold bg-green-600 text-white mr-auto">⬇ CSV</a>
  <a href="export.php?format=xlsx&type=reports" class="px-4 py-1.5 rounded-lg text-sm font-bold bg-emerald-700 text-white">⬇ Excel</a>
</div>

<div class="space-y-3">
  <?php foreach ($rows as $r): ?>
    <div class="bg-white rounded-xl border p-4">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1">
          <div class="flex items-center gap-2 mb-1">
            <?= status_badge($r['status']) ?>
            <span class="text-xs text-gray-400"><?= dt($r['created_at']) ?></span>
          </div>
          <div class="text-sm"><b><?= e($r['uname'] ?: 'مستخدم محذوف') ?></b> <span class="text-gray-400"><?= e($r['uphone']) ?></span>
            <?php if ($r['business_name']): ?>· عن <b><?= e($r['business_name']) ?></b><?php endif; ?></div>
          <p class="mt-2 text-gray-700"><?= nl2br(e($r['message'] ?: '—')) ?></p>
          <?php if ($r['video_url']): ?><a href="<?= e($r['video_url']) ?>" target="_blank" class="text-blue-600 text-sm">▶ فيديو مرفق</a><?php endif; ?>
        </div>
        <div class="flex flex-col gap-1 items-stretch min-w-[140px]">
          <?php if (can('reports','edit')): ?>
          <form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <select name="status" class="border rounded-lg px-2 py-1 text-xs flex-1">
              <?php foreach (['open'=>'مفتوح','reviewing'=>'قيد المراجعة','resolved'=>'محلول'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $r['status']===$k?'selected':'' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="bg-gray-800 text-white rounded-lg px-3 text-xs font-bold">حفظ</button>
          </form>
          <?php endif; ?>
          <?php if (can('reports','delete')): ?>
          <form method="post" onsubmit="return confirm('حذف البلاغ؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <button class="w-full bg-red-100 text-red-700 rounded-lg py-1 text-xs font-bold">حذف</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="bg-white rounded-xl border p-10 text-center text-gray-400">لا بلاغات.</div><?php endif; ?>
</div>
<?= pager($total, $page, http_build_query(array_filter(['status'=>$status]))) ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
