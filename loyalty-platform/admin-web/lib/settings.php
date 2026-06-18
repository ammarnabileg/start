<?php
// إعدادات اللوحة (admin.settings KV) + إعدادات المنصّة (public.platform_settings).
function setting_get(string $key, $default = null) {
  $r = one("select value from admin.settings where key=:k", ['k' => $key]);
  return $r ? json_decode($r['value'], true) : $default;
}
function setting_set(string $key, $value): void {
  q("insert into admin.settings (key, value) values (:k, :v)
     on conflict (key) do update set value=excluded.value, updated_at=now()",
    ['k' => $key, 'v' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
}
function platform_settings(): array {
  return one("select * from public.platform_settings where id=true") ?? [];
}
