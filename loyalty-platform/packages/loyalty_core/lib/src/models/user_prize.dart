/// هدية يملكها العميل (مكسب عجلة/مكافأة). متطابق مع public.user_prizes.
/// كل هدية لها claim_secret لتوليد QR متغيّر يفعّله الكاشير المخوّل.
class UserPrize {
  final String id;
  final String userId;
  final String merchantId;
  final String source; // wheel / reward / manual
  final String title;
  final String? description;
  final String kind; // reward / coupon / points / nothing
  final int pointsValue;
  final String status; // won / redeemed / expired / canceled
  final String? branchScope;
  final String claimSecret;
  final DateTime? expiresAt;
  final DateTime createdAt;

  // للعرض (join اختياري)
  final String? merchantName;

  const UserPrize({
    required this.id,
    required this.userId,
    required this.merchantId,
    required this.source,
    required this.title,
    required this.kind,
    required this.status,
    required this.claimSecret,
    required this.createdAt,
    this.description,
    this.pointsValue = 0,
    this.branchScope,
    this.expiresAt,
    this.merchantName,
  });

  bool get isClaimable => status == 'won';
  bool get isRedeemed => status == 'redeemed';

  factory UserPrize.fromJson(Map<String, dynamic> j) => UserPrize(
        id: j['id'] as String,
        userId: j['user_id'] as String,
        merchantId: j['merchant_id'] as String,
        source: j['source'] as String? ?? 'wheel',
        title: j['title'] as String,
        description: j['description'] as String?,
        kind: j['kind'] as String? ?? 'reward',
        pointsValue: j['points_value'] as int? ?? 0,
        status: j['status'] as String? ?? 'won',
        branchScope: j['branch_scope'] as String?,
        claimSecret: j['claim_secret'] as String? ?? '',
        expiresAt: j['expires_at'] == null
            ? null
            : DateTime.parse(j['expires_at'] as String),
        createdAt: DateTime.parse(j['created_at'] as String),
        merchantName: j['merchant_name'] as String?,
      );
}
