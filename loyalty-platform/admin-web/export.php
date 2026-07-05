<?php
require_once __DIR__ . '/lib/boot.php';
$type   = (string) get('type', '');
$format = get('format') === 'xlsx' ? 'xlsx' : 'csv';

function emit(string $base, array $headers, array $rows, string $format): void {
  if ($format === 'xlsx') stream_xlsx($base . '.xlsx', $headers, $rows);
  stream_csv($base . '.csv', $headers, $rows);
}

if ($type === 'users') {
  require_perm('users', 'view');
  $qstr = trim((string) get('q', '')); $w = []; $p = [];
  if ($qstr !== '') { $w[] = '(name ilike :q or phone ilike :q or email ilike :q)'; $p['q'] = '%' . $qstr . '%'; }
  $ws = $w ? ('where ' . implode(' and ', $w)) : '';
  $rows = all("select u.id,u.name,u.phone,u.email,u.created_at,
     (select count(*) from public.user_stores s where s.user_id=u.id) stores,
     (select coalesce(sum(available_points),0) from public.user_stores s where s.user_id=u.id) points
     from public.users u $ws order by u.created_at desc", $p);
  audit('export', 'users', null, ['rows' => count($rows), 'format' => $format]);
  emit('users', ['ID','الاسم','الجوال','البريد','الانضمام','عدد المتاجر','إجمالي النقاط'],
    array_map(fn($r) => [$r['id'],$r['name'],$r['phone'],$r['email'],dt($r['created_at']),(int)$r['stores'],(int)$r['points']], $rows), $format);
}

if ($type === 'merchants') {
  require_perm('merchants', 'view');
  $rows = all("select business_name,business_type,phone,email,status,created_at,
     (select count(*) from public.user_stores us where us.merchant_id=merchants.id) customers
     from public.merchants order by created_at desc");
  audit('export', 'merchants', null, ['rows' => count($rows), 'format' => $format]);
  emit('merchants', ['النشاط','النوع','الجوال','البريد','الحالة','الانضمام','العملاء'],
    array_map(fn($r) => [$r['business_name'],$r['business_type'],$r['phone'],$r['email'],$r['status'],dt($r['created_at']),(int)$r['customers']], $rows), $format);
}

if ($type === 'reports') {
  require_perm('reports', 'view');
  $rows = all("select r.created_at,r.status,u.name uname,u.phone,m.business_name,r.message
     from public.reports r left join public.users u on u.id=r.user_id left join public.merchants m on m.id=r.merchant_id
     order by r.created_at desc");
  audit('export', 'reports', null, ['rows' => count($rows), 'format' => $format]);
  emit('reports', ['التاريخ','الحالة','المستخدم','الجوال','التاجر','الرسالة'],
    array_map(fn($r) => [dt($r['created_at']),$r['status'],$r['uname'],$r['phone'],$r['business_name'],$r['message']], $rows), $format);
}

http_response_code(400);
exit('نوع تصدير غير معروف.');
