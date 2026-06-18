<?php
// الصلاحيات: عرض / إضافة / تعديل / حذف (+ approve للتجار).

const RESOURCES = [
  'dashboard'     => 'لوحة التحكم',
  'analytics'     => 'التحليلات',
  'merchants'     => 'التجار (CRM)',
  'finance'       => 'المالية والاشتراكات',
  'users'         => 'المستخدمون',
  'points'        => 'منح/خصم النقاط',
  'devices'       => 'الأجهزة والحظر',
  'lists'         => 'القوائم/الشرائح',
  'notifications' => 'الإشعارات',
  'reports'       => 'الشكاوى والبلاغات',
  'reviews'       => 'التقييمات والمراجعات',
  'content'       => 'مركز المحتوى',
  'health'        => 'صحة النظام',
  'admins'        => 'حسابات المسؤولين',
  'roles'         => 'الأدوار والصلاحيات',
  'audit'         => 'سجلّ التدقيق',
];
const ACTIONS = ['view' => 'مشاهدة', 'create' => 'إضافة', 'edit' => 'تعديل', 'delete' => 'حذف', 'approve' => 'اعتماد'];

// صلاحيات الكتابة تستلزم المشاهدة دائمًا.
const WRITE_ACTIONS = ['create', 'edit', 'delete', 'approve'];

function can(string $res, string $act = 'view'): bool {
  $a = current_admin();
  if (!$a) return false;
  if (!empty($a['is_super'])) return true;
  // admins/roles مقصورة على Super Admin
  if (in_array($res, ['admins', 'roles'], true)) return false;
  $perms = $a['permissions'] ? json_decode($a['permissions'], true) : [];
  $list = $perms[$res] ?? [];
  // أي صلاحية على المورد تعني ضمنيًا المشاهدة (لا كتابة بلا مشاهدة).
  if ($act === 'view') return $list !== [];
  return in_array($act, $list, true);
}

function require_perm(string $res, string $act = 'view'): void {
  require_login();
  if (!can($res, $act)) {
    http_response_code(403);
    exit('غير مصرّح: لا تملك صلاحية "' . (ACTIONS[$act] ?? $act) . '" على "' . (RESOURCES[$res] ?? $res) . '".');
  }
}
