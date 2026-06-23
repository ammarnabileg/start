<?php
// حلّ أعضاء القائمة: ثابتة (list_members) أو ذكية (معايير تُحسب لحظيًا).
function list_user_ids(array $list): array {
  if (!empty($list['is_smart'])) {
    $rows = all("select s::text id from admin.smart_list_users(:c::jsonb) s", ['c' => $list['criteria'] ?? '{}']);
  } else {
    $rows = all("select user_id id from admin.list_members where list_id=:l", ['l' => $list['id']]);
  }
  return array_map(fn($r) => $r['id'], $rows);
}
function list_count(array $list): int {
  if (!empty($list['is_smart'])) {
    return (int) scalar("select count(*) from admin.smart_list_users(:c::jsonb)", ['c' => $list['criteria'] ?? '{}']);
  }
  return (int) scalar("select count(*) from admin.list_members where list_id=:l", ['l' => $list['id']]);
}
// تحويل مصفوفة UUID إلى Postgres array literal آمن.
function uuid_array(array $ids): string {
  $ids = array_values(array_filter($ids, fn($x) => preg_match('/^[0-9a-fA-F-]{36}$/', (string)$x)));
  return '{' . implode(',', $ids) . '}';
}
