/// حملة بطاقة الأختام (Stamp Card): كرّر إجراءً (شراء/تبرّع/زيارة) عددًا من المرات
/// لتحصل على المكافأة، مع نقاط لكل ختم. متطابق مع جدول public.visit_campaigns.
class StampCampaign {
  final String id;
  final String name;
  final String? description;

  /// نوع التكرار: visit / purchase / donation / custom.
  final String actionType;
  final String? actionLabel;

  /// عدد التكرارات المطلوبة (آخر خانة = المكافأة).
  final int requiredCount;
  final int pointsPerStamp;
  final int rewardPoints;

  final String? rewardName;
  final String? rewardImageUrl;
  final String? rewardDescription;
  final String? stampImageUrl;
  final String? bannerImageUrl;

  /// تقدّم العميل.
  final int currentStamps;

  /// تواريخ الأختام المكتسبة (اختياري — للعرض تحت كل ختم).
  final List<DateTime> stampDates;

  const StampCampaign({
    required this.id,
    required this.name,
    required this.requiredCount,
    this.description,
    this.actionType = 'visit',
    this.actionLabel,
    this.pointsPerStamp = 0,
    this.rewardPoints = 0,
    this.rewardName,
    this.rewardImageUrl,
    this.rewardDescription,
    this.stampImageUrl,
    this.bannerImageUrl,
    this.currentStamps = 0,
    this.stampDates = const [],
  });

  bool get completed => currentStamps >= requiredCount;
  int get remaining =>
      (requiredCount - currentStamps) < 0 ? 0 : requiredCount - currentStamps;

  /// اسم الإجراء للعرض ("اشترِ"، "تبرّع"، …).
  String get actionVerb =>
      actionLabel?.isNotEmpty == true
          ? actionLabel!
          : switch (actionType) {
              'purchase' => 'عملية شراء',
              'donation' => 'تبرّع',
              'custom' => 'تكرار',
              _ => 'زيارة',
            };

  factory StampCampaign.fromJson(
    Map<String, dynamic> j, {
    int currentStamps = 0,
    List<DateTime> stampDates = const [],
  }) =>
      StampCampaign(
        id: j['id'] as String,
        name: j['name'] as String? ?? '',
        description: j['description'] as String?,
        actionType: j['action_type'] as String? ?? 'visit',
        actionLabel: j['action_label'] as String?,
        requiredCount: j['required_visits'] as int? ?? 1,
        pointsPerStamp: j['points_per_stamp'] as int? ?? 0,
        rewardPoints: j['reward_points'] as int? ?? 0,
        rewardName: j['reward_name'] as String?,
        rewardImageUrl: j['reward_image_url'] as String?,
        rewardDescription: j['reward_description'] as String?,
        stampImageUrl: j['stamp_image_url'] as String?,
        bannerImageUrl: j['banner_image_url'] as String?,
        currentStamps: currentStamps,
        stampDates: stampDates,
      );
}
