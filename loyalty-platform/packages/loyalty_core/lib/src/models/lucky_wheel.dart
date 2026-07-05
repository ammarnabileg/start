import 'dart:ui';

enum SegmentKind {
  reward,
  coupon,
  points,
  nothing;

  static SegmentKind fromString(String v) => switch (v) {
        'reward' => SegmentKind.reward,
        'coupon' => SegmentKind.coupon,
        'points' => SegmentKind.points,
        _ => SegmentKind.nothing,
      };
  String get value => name;
}

/// مقطع على عجلة الحظ. متطابق مع public.wheel_segments.
class WheelSegment {
  final String id;
  final String wheelId;
  final String label;
  final SegmentKind kind;
  final String? rewardId;
  final int pointsValue;
  final int weight;
  final String? colorHex;
  final int? stock;
  final int sortOrder;

  const WheelSegment({
    required this.id,
    required this.wheelId,
    required this.label,
    required this.kind,
    this.rewardId,
    this.pointsValue = 0,
    this.weight = 1,
    this.colorHex,
    this.stock,
    this.sortOrder = 0,
  });

  Color? get color {
    if (colorHex == null) return null;
    final h = colorHex!.replaceAll('#', '');
    final v = int.tryParse(h.length == 6 ? 'FF$h' : h, radix: 16);
    return v == null ? null : Color(v);
  }

  factory WheelSegment.fromJson(Map<String, dynamic> j) => WheelSegment(
        id: j['id'] as String,
        wheelId: j['wheel_id'] as String,
        label: j['label'] as String,
        kind: SegmentKind.fromString(j['kind'] as String),
        rewardId: j['reward_id'] as String?,
        pointsValue: j['points_value'] as int? ?? 0,
        weight: j['weight'] as int? ?? 1,
        colorHex: j['color_hex'] as String?,
        stock: j['stock'] as int?,
        sortOrder: j['sort_order'] as int? ?? 0,
      );

  Map<String, dynamic> toJson() => {
        'wheel_id': wheelId,
        'label': label,
        'kind': kind.value,
        'reward_id': rewardId,
        'points_value': pointsValue,
        'weight': weight,
        'color_hex': colorHex,
        'stock': stock,
        'sort_order': sortOrder,
      };
}

/// عجلة حظ. متطابق مع public.lucky_wheels.
class LuckyWheel {
  final String id;
  final String merchantId;
  final String name;
  final int spinCostPoints;
  final int maxSpinsPerDay;
  final bool active;
  final List<WheelSegment> segments;

  const LuckyWheel({
    required this.id,
    required this.merchantId,
    required this.name,
    this.spinCostPoints = 50,
    this.maxSpinsPerDay = 0,
    this.active = true,
    this.segments = const [],
  });

  factory LuckyWheel.fromJson(Map<String, dynamic> j) => LuckyWheel(
        id: j['id'] as String,
        merchantId: j['merchant_id'] as String,
        name: j['name'] as String,
        spinCostPoints: j['spin_cost_points'] as int? ?? 50,
        maxSpinsPerDay: j['max_spins_per_day'] as int? ?? 0,
        active: j['active'] as bool? ?? true,
        segments: (j['wheel_segments'] as List<dynamic>? ?? [])
            .map((s) => WheelSegment.fromJson(s as Map<String, dynamic>))
            .toList()
          ..sort((a, b) => a.sortOrder.compareTo(b.sortOrder)),
      );
}
