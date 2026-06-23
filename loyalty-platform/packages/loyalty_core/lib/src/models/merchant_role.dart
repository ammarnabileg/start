/// الموارد القابلة للتحكّم بالصلاحيات.
class PermResource {
  PermResource._();
  static const customers = 'customers';
  static const rewards = 'rewards';
  static const campaigns = 'campaigns';
  static const levels = 'levels';
  static const coupons = 'coupons';
  static const branches = 'branches';
  static const staff = 'staff';
  static const roles = 'roles';
  static const wheel = 'wheel';
  static const prizes = 'prizes';
  static const points = 'points';
  static const visits = 'visits';
  static const settings = 'settings';
  static const analytics = 'analytics';
  static const announcements = 'announcements';
  static const questions = 'questions';
  static const reports = 'reports';

  static const all = [
    customers, rewards, campaigns, levels, coupons, branches, staff, roles,
    wheel, prizes, points, visits, settings, analytics, announcements,
    questions, reports,
  ];

  static String label(String r) => switch (r) {
        customers => 'العملاء',
        rewards => 'المكافآت',
        campaigns => 'حملات الزيارة',
        levels => 'المستويات',
        coupons => 'الكوبونات',
        branches => 'الفروع',
        staff => 'الموظفين',
        roles => 'الأدوار',
        wheel => 'عجلة الحظ',
        prizes => 'الهدايا',
        points => 'النقاط',
        visits => 'الزيارات',
        settings => 'الإعدادات',
        analytics => 'التحليلات',
        announcements => 'الإعلانات',
        questions => 'الأسئلة',
        reports => 'البلاغات',
        _ => r,
      };
}

/// الإجراءات.
class PermAction {
  PermAction._();
  static const view = 'view';
  static const create = 'create';
  static const edit = 'edit';
  static const delete = 'delete';
  static const all = [view, create, edit, delete];

  static String label(String a) => switch (a) {
        view => 'عرض',
        create => 'إضافة',
        edit => 'تعديل',
        delete => 'حذف',
        'redeem' => 'تفعيل',
        'manage' => 'إدارة كاملة',
        _ => a,
      };
}

/// دور مخصّص لتاجر. متطابق مع public.merchant_roles.
/// permissions: { resource: [actions], "owner": true }
class MerchantRole {
  final String id;
  final String merchantId;
  final String name;
  final Map<String, dynamic> permissions;
  final bool isSystem;

  const MerchantRole({
    required this.id,
    required this.merchantId,
    required this.name,
    this.permissions = const {},
    this.isSystem = false,
  });

  bool get isOwner => permissions['owner'] == true;

  /// هل يملك الدور إجراءً على مورد؟
  bool can(String resource, String action) {
    if (isOwner) return true;
    final res = permissions[resource];
    if (res is List) {
      return res.contains(action) || res.contains('manage');
    }
    return false;
  }

  factory MerchantRole.fromJson(Map<String, dynamic> j) => MerchantRole(
        id: j['id'] as String,
        merchantId: j['merchant_id'] as String,
        name: j['name'] as String,
        permissions:
            (j['permissions'] as Map?)?.cast<String, dynamic>() ?? const {},
        isSystem: j['is_system'] as bool? ?? false,
      );

  Map<String, dynamic> toJson() => {
        if (id.isNotEmpty) 'id': id,
        'merchant_id': merchantId,
        'name': name,
        'permissions': permissions,
        'is_system': isSystem,
      };
}
