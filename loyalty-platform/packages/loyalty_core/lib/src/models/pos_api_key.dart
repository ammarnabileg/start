/// مفتاح POS API. متطابق مع public.pos_api_keys (المفتاح الخام لا يُخزَّن).
class PosApiKey {
  final String id;
  final String merchantId;
  final String? branchId;
  final String name;
  final String keyPrefix;
  final bool active;
  final DateTime? lastUsedAt;
  final DateTime createdAt;

  const PosApiKey({
    required this.id,
    required this.merchantId,
    required this.name,
    required this.keyPrefix,
    required this.active,
    required this.createdAt,
    this.branchId,
    this.lastUsedAt,
  });

  factory PosApiKey.fromJson(Map<String, dynamic> j) => PosApiKey(
        id: j['id'] as String,
        merchantId: j['merchant_id'] as String,
        branchId: j['branch_id'] as String?,
        name: j['name'] as String,
        keyPrefix: j['key_prefix'] as String,
        active: j['active'] as bool? ?? true,
        lastUsedAt: j['last_used_at'] == null
            ? null
            : DateTime.parse(j['last_used_at'] as String),
        createdAt: DateTime.parse(j['created_at'] as String),
      );
}
