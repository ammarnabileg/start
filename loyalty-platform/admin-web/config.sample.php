<?php
// انسخ هذا الملف إلى config.php واضبط الاتصال بقاعدة بيانات Supabase.
// استخدم سلسلة اتصال بصلاحية كاملة (Direct Postgres / service)، فاللوحة
// تتجاوز RLS لتعطيك تحكّمًا كاملًا. لا تشارك هذا الملف.
return [
  // مثال Supabase: pgsql:host=db.<ref>.supabase.co;port=5432;dbname=postgres;sslmode=require
  'db_dsn'   => getenv('ADMIN_DB_DSN')  ?: 'pgsql:host=127.0.0.1;port=5432;dbname=postgres',
  'db_user'  => getenv('ADMIN_DB_USER') ?: 'postgres',
  'db_pass'  => getenv('ADMIN_DB_PASS') ?: '',

  'app_name' => 'Hatchy Admin',
  'brand'    => '#F4B400',           // ذهبي Hatchy
  'session'  => 'hatchy_admin_sess',
  'csrf_key' => 'change-this-random-secret',
];
