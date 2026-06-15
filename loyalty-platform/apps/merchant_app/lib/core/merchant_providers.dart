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
final currentStaffProvider = FutureProvider<StaffContext>((ref) async {
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
final merchantSettingsProvider = FutureProvider<MerchantSettings>((ref) async {
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

/// صلاحيات الموظف الحالي (مشتقّة من دوره). owner = كل شيء.
class Permissions {
  final MerchantRole? role;
  final String legacyRole;
  const Permissions({this.role, required this.legacyRole});

  bool can(String resource, String action) {
    if (legacyRole == 'merchant_owner') return true;
    return role?.can(resource, action) ?? false;
  }
}

/// يجلب دور الموظف الحالي وصلاحياته.
final permissionsProvider = FutureProvider<Permissions>((ref) async {
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
