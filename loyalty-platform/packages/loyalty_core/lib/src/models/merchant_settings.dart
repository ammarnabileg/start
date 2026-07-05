/// نطاق احتساب النقاط/المستويات — اختيار التاجر.
enum PointsScope {
  /// رصيد ومستوى واحد للعميل على مستوى التاجر كله (مشترك بين كل الفروع).
  merchant,

  /// رصيد ومستوى منفصل للعميل عند كل فرع.
  branch;

  static PointsScope fromString(String? v) =>
      v == 'merchant' ? PointsScope.merchant : PointsScope.branch;

  String get value => name;
}

/// كل إعدادات/خيارات التاجر — White-Label قابل للتهيئة بالكامل.
/// متطابق مع جدول public.merchant_settings (صف واحد لكل تاجر).
class MerchantSettings {
  final String merchantId;

  // --- نطاق النقاط (الخيار اللي طلبه التاجر) ---
  final PointsScope pointsScope;

  // --- تفعيل/تعطيل الميزات (التاجر يقرّر يشغّل إيه) ---
  final bool enableVisits;
  final bool enablePoints;
  final bool enableRewards;
  final bool enableLevels;
  final bool enableCoupons;
  final bool enableReferral;
  final bool enableBirthday;
  final bool enableProximity;
  final bool enableGpsCheckin;
  final bool enableAnnouncements;

  // --- ضوابط الأمان (التاجر يضبطها) ---
  final int maxPointsPerTxn; // سقف النقاط في العملية الواحدة
  final int dailyPointsPerStaff; // سقف يومي لكل موظف
  final bool oneVisitPerDay; // زيارة واحدة في اليوم
  final bool requireRedemptionConfirm; // تأكيد العميل على الاستبدال
  final int redemptionConfirmThreshold; // التأكيد فقط فوق X نقطة (0 = دايمًا)
  final int qrRotationSeconds; // مدة تجدّد QR العميل
  final int redemptionWindowMinutes; // عمر كود الاستلام

  // --- الاكتساب ---
  final double earnRatePerCurrency; // كام نقطة لكل ريال (للإضافة التلقائية)

  // --- العلامة (White-Label branding) ---
  final String? primaryColorHex; // لون أساسي مخصّص للتاجر (اختياري)
  final String? brandName;

  const MerchantSettings({
    required this.merchantId,
    this.pointsScope = PointsScope.branch,
    this.enableVisits = true,
    this.enablePoints = true,
    this.enableRewards = true,
    this.enableLevels = true,
    this.enableCoupons = false,
    this.enableReferral = false,
    this.enableBirthday = false,
    this.enableProximity = false,
    this.enableGpsCheckin = false,
    this.enableAnnouncements = true,
    this.maxPointsPerTxn = 500,
    this.dailyPointsPerStaff = 5000,
    this.oneVisitPerDay = true,
    this.requireRedemptionConfirm = true,
    this.redemptionConfirmThreshold = 0,
    this.qrRotationSeconds = 30,
    this.redemptionWindowMinutes = 5,
    this.earnRatePerCurrency = 1,
    this.primaryColorHex,
    this.brandName,
  });

  factory MerchantSettings.fromJson(Map<String, dynamic> j) => MerchantSettings(
        merchantId: j['merchant_id'] as String,
        pointsScope: PointsScope.fromString(j['points_scope'] as String?),
        enableVisits: j['enable_visits'] as bool? ?? true,
        enablePoints: j['enable_points'] as bool? ?? true,
        enableRewards: j['enable_rewards'] as bool? ?? true,
        enableLevels: j['enable_levels'] as bool? ?? true,
        enableCoupons: j['enable_coupons'] as bool? ?? false,
        enableReferral: j['enable_referral'] as bool? ?? false,
        enableBirthday: j['enable_birthday'] as bool? ?? false,
        enableProximity: j['enable_proximity'] as bool? ?? false,
        enableGpsCheckin: j['enable_gps_checkin'] as bool? ?? false,
        enableAnnouncements: j['enable_announcements'] as bool? ?? true,
        maxPointsPerTxn: j['max_points_per_txn'] as int? ?? 500,
        dailyPointsPerStaff: j['daily_points_per_staff'] as int? ?? 5000,
        oneVisitPerDay: j['one_visit_per_day'] as bool? ?? true,
        requireRedemptionConfirm:
            j['require_redemption_confirm'] as bool? ?? true,
        redemptionConfirmThreshold:
            j['redemption_confirm_threshold'] as int? ?? 0,
        qrRotationSeconds: j['qr_rotation_seconds'] as int? ?? 30,
        redemptionWindowMinutes: j['redemption_window_minutes'] as int? ?? 5,
        earnRatePerCurrency:
            (j['earn_rate_per_currency'] as num?)?.toDouble() ?? 1,
        primaryColorHex: j['primary_color_hex'] as String?,
        brandName: j['brand_name'] as String?,
      );

  Map<String, dynamic> toJson() => {
        'merchant_id': merchantId,
        'points_scope': pointsScope.value,
        'enable_visits': enableVisits,
        'enable_points': enablePoints,
        'enable_rewards': enableRewards,
        'enable_levels': enableLevels,
        'enable_coupons': enableCoupons,
        'enable_referral': enableReferral,
        'enable_birthday': enableBirthday,
        'enable_proximity': enableProximity,
        'enable_gps_checkin': enableGpsCheckin,
        'enable_announcements': enableAnnouncements,
        'max_points_per_txn': maxPointsPerTxn,
        'daily_points_per_staff': dailyPointsPerStaff,
        'one_visit_per_day': oneVisitPerDay,
        'require_redemption_confirm': requireRedemptionConfirm,
        'redemption_confirm_threshold': redemptionConfirmThreshold,
        'qr_rotation_seconds': qrRotationSeconds,
        'redemption_window_minutes': redemptionWindowMinutes,
        'earn_rate_per_currency': earnRatePerCurrency,
        'primary_color_hex': primaryColorHex,
        'brand_name': brandName,
      };
}
