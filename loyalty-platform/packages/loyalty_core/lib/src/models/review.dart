/// مراجعة عميل لمتجر (نجوم 1..5 + تعليق اختياري + ردّ التاجر).
/// تُرجع من store_reviews (عرض عام) و merchant_reviews (للتاجر).
class Review {
  final String id;
  final int rating;
  final String? comment;
  final String? merchantReply;
  final DateTime? merchantRepliedAt;
  final DateTime createdAt;
  final String reviewerName;

  /// هل هذه مراجعتي أنا؟ (يأتي من store_reviews للعميل).
  final bool isMine;

  /// حالة الإشراف (visible/hidden) — يأتي من merchant_reviews للتاجر فقط.
  final String? status;

  const Review({
    required this.id,
    required this.rating,
    required this.createdAt,
    required this.reviewerName,
    this.comment,
    this.merchantReply,
    this.merchantRepliedAt,
    this.isMine = false,
    this.status,
  });

  bool get hasReply => (merchantReply ?? '').trim().isNotEmpty;
  bool get isHidden => status == 'hidden';

  factory Review.fromJson(Map<String, dynamic> j) => Review(
        id: j['id'] as String,
        rating: (j['rating'] as num).toInt(),
        comment: j['comment'] as String?,
        merchantReply: j['merchant_reply'] as String?,
        merchantRepliedAt: j['merchant_replied_at'] == null
            ? null
            : DateTime.parse(j['merchant_replied_at'] as String),
        createdAt: DateTime.parse(j['created_at'] as String),
        reviewerName: j['user_name'] as String? ?? 'عميل',
        isMine: j['is_mine'] as bool? ?? false,
        status: j['status'] as String?,
      );
}

/// ملخّص تقييم متجر (متوسط + عدد) — من merchant_rating.
class RatingSummary {
  final double average;
  final int count;

  const RatingSummary({required this.average, required this.count});

  static const empty = RatingSummary(average: 0, count: 0);
  bool get hasRatings => count > 0;

  factory RatingSummary.fromJson(Map<String, dynamic> j) => RatingSummary(
        average: (j['avg_rating'] as num?)?.toDouble() ?? 0,
        count: (j['review_count'] as num?)?.toInt() ?? 0,
      );
}
