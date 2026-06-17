<?php
// الصلاحيات: عرض / إضافة / تعديل / حذف (+ approve للتجار).

const RESOURCES = [
  'dashboard'     => 'لوحة التحكم',
  'merchants'     => 'التجار (CRM)',
  'users'         => 'المستخدمون',
  'lists'         => 'القوائم/الشرائح',
  'notifications' => 'الإشعارات',
  'reports'       => 'الشكاوى والبلاغات',
  'admins'        => 'حسابات المسؤولين',
  'roles'         => 'الأدوار والصلاحيات',
  'audit'         => 'سجلّ التدقيق',
  'settings'      => 'الإعدادات',
];
const ACTIONS = ['view' => 'عرض', 'create' => 'إضافة', 'edit' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد'];

function can(string $res, string $act = 'view'): bool {
  $a = current_admin();
  if (!$a) return false;
  if (!empty($a['is_super'])) return true;
  // admins/roles مقصورة على Super Admin
  if (in_array($res, ['admins', 'roles'], true)) return false;
  $perms = $a['permissions'] ? json_decode($a['permissions'], true) : [];
  return in_array($act, $perms[$res] ?? [], true);
}

function require_perm(string $res, string $act = 'view'): void {
  require_login();
  if (!can($res, $act)) {
    http_response_code(403);
    exit('غير مصرّح: لا تملك صلاحية "' . (ACTIONS[$act] ?? $act) . '" على "' . (RESOURCES[$res] ?? $res) . '".');
  }
}
