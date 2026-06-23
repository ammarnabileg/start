/// محفظة العميل عند فرع/تاجر. متطابق مع جدول public.user_stores.
///
/// ملاحظة: [branchId] ممكن يكون null لو التاجر مفعّل نطاق نقاط "مشترك"
/// (points_scope = merchant). لو "منفصل لكل فرع" (branch) بيكون فيه قيمة.
class UserStore {
  final String id;
  final String userId;
  final String merchantId;
  final String? branchId;
  final int availablePoints;
  final int lifetimePoints;
  final String? currentLevelId;

  /// خصوصية لكل متجر: true = يشارك معلوماته مع هذا التاجر (الافتراضي).
  /// false = مخفي عن هذا التاجر (قوائمه/بحثه/صدارته) — لكن النقاط/الزيارات تستمر.
  final bool visible;

  /// متجر مفضّل لدى العميل (يظهر أعلى القائمة).
  final bool isFavorite;

  // حقول للعرض (تيجي من join — اختيارية)
  final String? merchantName;
  final String? merchantLogoUrl;
  final String? currentLevelName;
  final String? branchName;

  /// حالة المتجر (approved/suspended/…) — لعرض "غير متاح حاليًا".
  final String? merchantStatus;

  const UserStore({
    required this.id,
    required this.userId,
    required this.merchantId,
    required this.availablePoints,
    required this.lifetimePoints,
    this.visible = true,
    this.isFavorite = false,
    this.branchId,
    this.currentLevelId,
    this.merchantName,
    this.merchantLogoUrl,
    this.currentLevelName,
    this.branchName,
    this.merchantStatus,
  });

  /// المتجر متاح للتعامل: معتمد صراحةً فقط. أي حالة أخرى — أو غياب الحالة لأن
  /// صف التاجر محجوب بـ RLS عند التعليق — تُعدّ "غير متاح" (fail-closed).
  bool get merchantAvailable => merchantStatus == 'approved';

  factory UserStore.fromJson(Map<String, dynamic> j) => UserStore(
        id: j['id'] as String,
        userId: j['user_id'] as String,
        merchantId: j['merchant_id'] as String,
        branchId: j['branch_id'] as String?,
        availablePoints: j['available_points'] as int? ?? 0,
        lifetimePoints: j['lifetime_points'] as int? ?? 0,
        visible: j['visible'] as bool? ?? true,
        isFavorite: j['is_favorite'] as bool? ?? false,
        currentLevelId: j['current_level_id'] as String?,
        merchantName: j['merchant_name'] as String?,
        merchantLogoUrl: j['merchant_logo_url'] as String?,
        currentLevelName: j['current_level_name'] as String?,
        branchName: j['branch_name'] as String?,
        merchantStatus: j['merchant_status'] as String?,
      );
}
