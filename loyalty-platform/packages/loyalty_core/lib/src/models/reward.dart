/// مكافأة. متطابق مع جدول public.rewards.
class Reward {
  final String id;
  final String merchantId;
  final String name;
  final String? imageUrl;
  final String? description;
  final int pointsCost;
  final int? stockQty; // null = غير محدود
  final bool active;

  const Reward({
    required this.id,
    required this.merchantId,
    required this.name,
    required this.pointsCost,
    this.imageUrl,
    this.description,
    this.stockQty,
    this.active = true,
  });

  bool get inStock => stockQty == null || stockQty! > 0;
  bool affordableWith(int available) => available >= pointsCost;

  factory Reward.fromJson(Map<String, dynamic> j) => Reward(
        id: j['id'] as String,
        merchantId: j['merchant_id'] as String,
        name: j['name'] as String,
        imageUrl: j['image_url'] as String?,
        description: j['description'] as String?,
        pointsCost: j['points_cost'] as int,
        stockQty: j['stock_qty'] as int?,
        active: j['active'] as bool? ?? true,
      );
}
