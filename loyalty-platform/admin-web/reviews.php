<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('reviews', 'view');

// إجراءات الإشراف: إخفاء / إظهار / حذف مراجعة.
if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  $id  = (string) post('id');
  if ($act === 'hide') {
    require_perm('reviews', 'edit');
    $reason = trim((string) post('reason'));
    q("update public.reviews set status='hidden', hidden_reason=:r where id=:id",
      ['r' => $reason !== '' ? $reason : null, 'id' => $id]);
    audit('hide', 'review', $id, ['reason' => $reason]);
    flash('تم إخفاء المراجعة عن الجمهور.');
  } elseif ($act === 'unhide') {
    require_perm('reviews', 'edit');
    q("update public.reviews set status='visible', hidden_reason=null where id=:id", ['id' => $id]);
    audit('unhide', 'review', $id);
    flash('تمت إعادة إظهار المراجعة.');
  } elseif ($act === 'delete') {
    require_perm('reviews', 'delete');
    q("delete from public.reviews where id=:id", ['id' => $id]);
    audit('delete', 'review', $id);
    flash('تم حذف المراجعة نهائيًا.');
  }
  redirect('reviews.php?' . http_build_query(array_filter([
    'status' => get('status'), 'rating' => get('rating'),
  ])));
}

// فلاتر: الحالة + التقييم.
$status = (string) get('status', '');
$rating = (string) get('rating', '');
$where = []; $params = [];
if (in_array($status, ['visible', 'hidden'], true)) { $where[] = 'r.status=:s'; $params['s'] = $status; }
if (in_array($rating, ['1','2','3','4','5'], true)) { $where[] = 'r.rating=:rt'; $params['rt'] = (int)$rating; }
$wsql = $where ? ('where ' . implode(' and ', $where)) : '';

$total = (int) scalar("select count(*) from public.reviews r $wsql", $params);
$page = page_num(); $off = ($page - 1) * per_page();
$rows = all("select r.*, u.name uname, m.business_name
   from public.reviews r
   left join public.users u on u.id = r.user_id
   left join public.merchants m on m.id = r.merchant_id
   $wsql order by r.created_at desc limit " . per_page() . " offset $off", $params);

$counts = [];
foreach (all("select status, count(*) c from public.reviews group by status") as $r) $counts[$r['status']] = (int)$r['c'];
$avg = scalar("select coalesce(round(avg(rating)::numeric,1),0) from public.reviews where status='visible'");

/** نجوم HTML من تقييم 1..5. */
function stars(int $n): string {
  $n = max(0, min(5, $n));
  return '<span class="text-amber-500 text-base tracking-tight">'
    . str_repeat('★', $n) . '<span class="text-gray-300">' . str_repeat('★', 5 - $n) . '</span></span>';
}

$title = 'التقييمات والمراجعات';
require __DIR__ . '/partials/header.php';
?>
<div class="flex flex-wrap items-center gap-2 mb-4">
  <?php foreach (['' => 'الكل', 'visible' => 'ظاهرة', 'hidden' => 'مخفية'] as $k => $lbl):
    $cls = $status === $k ? 'bg-amber-500 text-white' : 'bg-white border text-gray-600';
    $c = $k === '' ? array_sum($counts) : ($counts[$k] ?? 0); ?>
    <a href="?status=<?= $k ?><?= $rating ? '&rating=' . e($rating) : '' ?>" class="px-4 py-1.5 rounded-lg text-sm font-bold <?= $cls ?>"><?= e($lbl) ?> (<?= n($c) ?>)</a>
  <?php endforeach; ?>

  <form method="get" class="flex items-center gap-2 mr-auto">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <select name="rating" onchange="this.form.submit()" class="border rounded-lg px-3 py-1.5 text-sm bg-white">
      <option value="">كل التقييمات</option>
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <option value="<?= $i ?>" <?= $rating === (string)$i ? 'selected' : '' ?>><?= $i ?> نجوم</option>
      <?php endfor; ?>
    </select>
    <span class="px-4 py-1.5 rounded-lg text-sm font-bold bg-amber-50 text-amber-700 border border-amber-200">متوسط المنصّة: ★ <?= e($avg) ?></span>
  </form>
</div>

<div class="space-y-3">
  <?php foreach ($rows as $r): $hidden = $r['status'] === 'hidden'; ?>
    <div class="bg-white rounded-xl border p-4 <?= $hidden ? 'opacity-70' : '' ?>">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1 flex-wrap">
            <?= stars((int)$r['rating']) ?>
            <?php if ($hidden): ?><span class="text-xs font-bold bg-gray-200 text-gray-600 rounded-full px-2 py-0.5">مخفية</span><?php endif; ?>
            <span class="text-xs text-gray-400"><?= dt($r['created_at']) ?></span>
          </div>
          <div class="text-sm"><b><?= e($r['uname'] ?: 'مستخدم محذوف') ?></b>
            <?php if ($r['business_name']): ?><span class="text-gray-400">· عن</span> <b><?= e($r['business_name']) ?></b><?php endif; ?></div>
          <?php if (trim((string)$r['comment']) !== ''): ?>
            <p class="mt-2 text-gray-700"><?= nl2br(e($r['comment'])) ?></p>
          <?php endif; ?>
          <?php if (trim((string)$r['merchant_reply']) !== ''): ?>
            <div class="mt-2 bg-amber-50 border border-amber-100 rounded-lg p-2 text-sm">
              <span class="text-amber-700 font-bold">ردّ المتجر:</span> <?= nl2br(e($r['merchant_reply'])) ?>
            </div>
          <?php endif; ?>
          <?php if ($hidden && trim((string)$r['hidden_reason']) !== ''): ?>
            <div class="mt-2 text-xs text-gray-500">سبب الإخفاء: <?= e($r['hidden_reason']) ?></div>
          <?php endif; ?>
        </div>
        <div class="flex flex-col gap-1 items-stretch min-w-[150px]">
          <?php if (can('reviews', 'edit')): ?>
            <?php if ($hidden): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unhide"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <button class="w-full bg-emerald-100 text-emerald-700 rounded-lg py-1.5 text-xs font-bold">إعادة الإظهار</button></form>
            <?php else: ?>
              <form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <input name="reason" placeholder="سبب (اختياري)" class="border rounded-lg px-2 py-1 text-xs flex-1 min-w-0">
                <button class="bg-gray-800 text-white rounded-lg px-3 text-xs font-bold whitespace-nowrap">إخفاء</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (can('reviews', 'delete')): ?>
            <form method="post" onsubmit="return confirm('حذف المراجعة نهائيًا؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
              <button class="w-full bg-red-100 text-red-700 rounded-lg py-1 text-xs font-bold">حذف</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="bg-white rounded-xl border p-10 text-center text-gray-400">لا توجد مراجعات.</div><?php endif; ?>
</div>
<?= pager($total, $page, http_build_query(array_filter(['status' => $status, 'rating' => $rating]))) ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
