<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('reports', 'view');

// فلاتر: متجر معيّن + موظّف معيّن داخله.
$merchant = (string) get('merchant', '');
$staff    = (string) get('staff', '');

$where = []; $params = [];
if ($merchant !== '') { $where[] = 'r.merchant_id = :m'; $params['m'] = $merchant; }
if ($staff !== '')    { $where[] = 'm.sender_staff_id = :st'; $params['st'] = $staff; }
$wsql = $where ? ('where ' . implode(' and ', $where)) : '';

$total = (int) scalar("select count(*) from public.report_messages m
  join public.reports r on r.id = m.report_id $wsql", $params);
$page = page_num(); $off = ($page - 1) * per_page();
$rows = all("select m.id, m.report_id, m.sender_role, m.sender_name, m.body,
    m.hidden, m.created_at, m.edited_at,
    ms.role staff_role, u.name cust_name, mr.business_name, r.subject_label
  from public.report_messages m
  join public.reports r on r.id = m.report_id
  left join public.merchants mr on mr.id = r.merchant_id
  left join public.merchant_staff ms on ms.id = m.sender_staff_id
  left join public.users u on u.id = r.user_id
  $wsql order by m.created_at desc limit " . per_page() . " offset $off", $params);

$merchants = all("select id, business_name from public.merchants order by business_name");
$staffOpts = $merchant !== ''
  ? all("select id, name, role, phone from public.merchant_staff where merchant_id=:m order by name", ['m' => $merchant])
  : [];

function rl(string $r): string {
  return ['customer' => 'عميل', 'merchant' => 'المتجر', 'admin' => 'إدارة المنصّة'][$r] ?? $r;
}
function sl(?string $r): string {
  return ['merchant_owner' => 'المالك', 'manager' => 'مدير', 'branch_manager' => 'مدير فرع', 'cashier' => 'كاشير'][$r] ?? '';
}

$title = 'رسائل المتاجر';
require __DIR__ . '/partials/header.php';
?>
<h2 class="text-xl font-extrabold mb-3">رسائل المتاجر والموظفين</h2>

<form method="get" class="flex flex-wrap items-center gap-2 mb-4 bg-white rounded-xl border p-3">
  <select name="merchant" onchange="this.form.staff.value=''; this.form.submit()" class="border rounded-lg px-3 py-1.5 text-sm">
    <option value="">كل المتاجر</option>
    <?php foreach ($merchants as $mr): ?>
      <option value="<?= e($mr['id']) ?>" <?= $merchant === $mr['id'] ? 'selected' : '' ?>><?= e($mr['business_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="staff" onchange="this.form.submit()" class="border rounded-lg px-3 py-1.5 text-sm" <?= $merchant === '' ? 'disabled' : '' ?>>
    <option value="">كل الموظفين</option>
    <?php foreach ($staffOpts as $s): ?>
      <option value="<?= e($s['id']) ?>" <?= $staff === $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> · <?= e(trim((string)$s['phone']) !== '' ? $s['phone'] : sl($s['role'])) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="text-sm text-gray-500 mr-auto"><?= n($total) ?> رسالة</span>
</form>

<div class="space-y-2">
  <?php foreach ($rows as $r): $hidden = $r['hidden']; ?>
    <div class="bg-white rounded-xl border p-3 <?= $hidden ? 'opacity-60' : '' ?>">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <div class="text-xs text-gray-500 mb-1">
            <b><?= e($r['sender_name'] ?: rl($r['sender_role'])) ?></b> · <?= e(rl($r['sender_role'])) ?><?php if ($r['sender_role'] === 'merchant' && $r['staff_role']): ?> (<?= e(sl($r['staff_role'])) ?>)<?php endif; ?>
            <?php if ($r['business_name']): ?> · <span class="text-emerald-700"><?= e($r['business_name']) ?></span><?php endif; ?>
            <?php if ($hidden): ?> · <span class="text-red-600 font-bold">مخفية</span><?php endif; ?>
          </div>
          <div class="text-gray-800 whitespace-pre-line"><?= nl2br(e($r['body'])) ?></div>
          <div class="text-[11px] text-gray-400 mt-1">
            للعميل: <?= e($r['cust_name'] ?: '—') ?><?php if ($r['subject_label']): ?> · عن: <?= e($r['subject_label']) ?><?php endif; ?>
            · <?= dt($r['created_at']) ?><?php if (!empty($r['edited_at'])): ?> · <span>✏️ مُعدّلة</span><?php endif; ?>
          </div>
        </div>
        <a href="report_view.php?id=<?= e($r['report_id']) ?>" class="text-amber-600 text-sm font-bold whitespace-nowrap">فتح البلاغ ←</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="bg-white rounded-xl border p-10 text-center text-gray-400">لا توجد رسائل.</div><?php endif; ?>
</div>
<?= pager($total, $page, http_build_query(array_filter(['merchant' => $merchant, 'staff' => $staff]))) ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
