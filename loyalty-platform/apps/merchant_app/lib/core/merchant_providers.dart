import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// سياق الموظف الحالي (المستخدم المسجّل دخوله في تطبيق التاجر).
/// يحدّد التاجر والفرع والدور — تُبنى عليه كل عمليات الإدارة.
class StaffContext {
  final String staffId;
  final String merchantId;
  final String role;
  final String? branchId;

  const StaffContext({
    required this.staffId,
    required this.merchantId,
    required this.role,
    this.branchId,
  });

  bool get isManager => role == 'merchant_owner' || role == 'manager';
  bool get isBranchManager => role == 'branch_manager';
  bool get isCashier => role == 'cashier';
}

/// يجلب صف الموظف الحالي من merchant_staff حسب user_id.
/// يرمي استثناء لو المستخدم غير مرتبط بأي تاجر.
final currentStaffProvider = FutureProvider.autoDispose<StaffContext>((ref) async {
  final client = Supabase.instance.client;
  final user = client.auth.currentUser;
  if (user == null) {
    throw StateError('لا توجد جلسة مستخدم نشطة');
  }

  final row = await client
      .from('merchant_staff')
      .select('id, merchant_id, role, branch_id')
      .eq('user_id', user.id)
      .maybeSingle();

  if (row == null) {
    throw StateError('هذا الحساب غير مرتبط بأي تاجر');
  }

  return StaffContext(
    staffId: row['id'] as String,
    merchantId: row['merchant_id'] as String,
    role: row['role'] as String,
    branchId: row['branch_id'] as String?,
  );
});

/// إعدادات التاجر الحالية. لو ما فيش صف، يرجّع الافتراضي.
final merchantSettingsProvider = FutureProvider.autoDispose<MerchantSettings>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final client = Supabase.instance.client;

  final row = await client
      .from('merchant_settings')
      .select()
      .eq('merchant_id', staff.merchantId)
      .maybeSingle();

  if (row == null) {
    return MerchantSettings(merchantId: staff.merchantId);
  }
  return MerchantSettings.fromJson(row);
});

/// هل التاجر مستحقّ للخدمة حاليًا؟ (غير معلّق + اشتراك/تجربة سارية).
/// يعتمد على دالة قاعدة البيانات `merchant_entitled`.
final merchantEntitledProvider = FutureProvider.autoDispose<bool>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final res = await Supabase.instance.client
      .rpc('merchant_entitled', params: {'p_merchant': staff.merchantId});
  return res == true;
});

/// صلاحيات الموظف الحالي (مشتقّة من دوره). owner = كل شيء.
/// لو للموظف دور مخصّص (role) فهو الفيصل (دور «عرض فقط» يمنع الكتابة فعليًا)،
/// وإلا نرجع لصلاحيات الدور القديم. مطابقة لدالة current_staff_can في الخادم.
class Permissions {
  final MerchantRole? role;
  final String legacyRole;
  const Permissions({this.role, required this.legacyRole});

  bool can(String resource, String action) {
    if (legacyRole == 'merchant_owner') return true;
    if (role != null) return role!.can(resource, action);
    return _legacyCan(legacyRole, resource, action);
  }

  /// احتياطي الأدوار القديمة — مطابق لـ public.legacy_role_can في SQL.
  static bool _legacyCan(String role, String res, String action) {
    const writeRes = [
      'rewards', 'campaigns', 'levels', 'coupons', 'wheel', 'questions'
    ];
    const crud = ['view', 'create', 'edit', 'delete'];
    switch (role) {
      case 'manager':
      case 'branch_manager':
        if (writeRes.contains(res) && crud.contains(action)) return true;
        if ((res == 'customers' || res == 'analytics' || res == 'reports') &&
            action == 'view') {
          return true;
        }
        if (res == 'announcements' &&
            (action == 'view' || action == 'create')) {
          return true;
        }
        if (res == 'points' && action == 'create') return true;
        if (res == 'visits' && action == 'create') return true;
        if (res == 'prizes' && action == 'redeem') return true;
        return false;
      case 'cashier':
        if (res == 'points' && action == 'create') return true;
        if (res == 'visits' && action == 'create') return true;
        if (res == 'prizes' && action == 'redeem') return true;
        if (res == 'customers' && action == 'view') return true;
        return false;
      default:
        return false;
    }
  }
}

/// يجلب دور الموظف الحالي وصلاحياته.
final permissionsProvider = FutureProvider.autoDispose<Permissions>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final row = await Supabase.instance.client
      .from('merchant_staff')
      .select('role_id, merchant_roles(*)')
      .eq('id', staff.staffId)
      .maybeSingle();
  final roleJson = row?['merchant_roles'] as Map<String, dynamic>?;
  return Permissions(
    role: roleJson == null ? null : MerchantRole.fromJson(roleJson),
    legacyRole: staff.role,
  );
});
