<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('merchants', 'view');

// فلاتر: متجر + موظّف داخله.
$merchant = (string) get('merchant', '');
$staff    = (string) get('staff', '');

$where = []; $params = [];
if ($merchant !== '') { $where[] = 'a.merchant_id = :m'; $params['m'] = $merchant; }
if ($staff !== '')    { $where[] = 'a.staff_id = :st'; $params['st'] = $staff; }
$wsql = $where ? ('where ' . implode(' and ', $where)) : '';

$total = (int) scalar("select count(*) from public.merchant_activity_log a $wsql", $params);
$page = page_num(); $off = ($page - 1) * per_page();
$rows = all("select a.*, mr.business_name
  from public.merchant_activity_log a
  left join public.merchants mr on mr.id = a.merchant_id
  $wsql order by a.created_at desc limit " . per_page() . " offset $off", $params);

$merchants = all("select id, business_name from public.merchants order by business_name");
$staffOpts = $merchant !== ''
  ? all("select id, name, role from public.merchant_staff where merchant_id=:m order by name", ['m' => $merchant])
  : [];

function al(string $a): string {
  return [
    'create' => 'أضاف', 'update' => 'عدّل', 'delete' => 'حذف',
    'grant_points' => 'منح نقاطًا', 'redeem_reward' => 'سلّم مكافأة',
    'redeem_prize' => 'سلّم جائزة', 'record_visit' => 'سجّل زيارة',
    'apply_coupon' => 'طبّق كوبونًا', 'qr_failed' => 'فشل قراءة',
    'send_announcement' => 'أرسل',
  ][$a] ?? $a;
}
function el(?string $e): string {
  return [
    'reward' => 'مكافأة', 'level' => 'مستوى', 'coupon' => 'كوبون', 'campaign' => 'حملة',
    'question' => 'سؤال', 'wheel' => 'عجلة الحظ', 'branch' => 'فرع', 'staff' => 'موظّف',
    'role' => 'دور', 'settings' => 'الإعدادات', 'points' => 'نقاط', 'prize' => 'جائزة',
    'visit' => 'زيارة', 'scan' => 'QR', 'announcement' => 'إعلان', 'pos_key' => 'مفتاح POS',
  ][$e] ?? (string) $e;
}
function rl(?string $r): string {
  return ['merchant_owner' => 'المالك', 'manager' => 'مدير', 'branch_manager' => 'مدير فرع',
    'cashier' => 'كاشير', 'admin' => 'إدارة المنصّة'][$r] ?? '';
}

$title = 'سجل نشاط المتاجر';
require __DIR__ . '/partials/header.php';
?>
<h2 class="text-xl font-extrabold mb-3">سجل نشاط المتاجر — مين عمل كل أكشن</h2>

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
      <option value="<?= e($s['id']) ?>" <?= $staff === $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> · <?= e(rl($s['role'])) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="text-sm text-gray-500 mr-auto"><?= n($total) ?> إجراء</span>
</form>

<div class="space-y-2">
  <?php foreach ($rows as $r): ?>
    <div class="bg-white rounded-xl border p-3 flex items-start justify-between gap-3">
      <div class="flex-1 min-w-0">
        <div class="text-sm">
          <b><?= e($r['staff_name'] ?: 'موظّف') ?></b>
          <?php if (trim((string)$r['staff_phone']) !== ''): ?><span class="text-gray-400">· <?= e($r['staff_phone']) ?></span><?php endif; ?>
          <?php if ($r['business_name']): ?><span class="text-emerald-700">· <?= e($r['business_name']) ?></span><?php endif; ?>
        </div>
        <div class="text-gray-800 mt-0.5">
          <b><?= e(al($r['action'])) ?></b> <?= e(el($r['entity_type'])) ?><?php if (trim((string)$r['summary']) !== ''): ?> · <?= e($r['summary']) ?><?php endif; ?>
        </div>
      </div>
      <span class="text-[11px] text-gray-400 whitespace-nowrap"><?= dt($r['created_at']) ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="bg-white rounded-xl border p-10 text-center text-gray-400">لا يوجد نشاط.</div><?php endif; ?>
</div>
<?= pager($total, $page, http_build_query(array_filter(['merchant' => $merchant, 'staff' => $staff]))) ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
