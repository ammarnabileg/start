import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// تقييمات ومراجعات عملاء التاجر — قراءة + ردّ التاجر.
class ReviewsRepository {
  ReviewsRepository(this._client);
  final SupabaseClient _client;

  /// كل مراجعات المتجر (بما فيها المخفية بإشراف الأدمن) مع حالتها.
  Future<List<Review>> fetchReviews(String merchantId,
      {int limit = 30, int offset = 0}) async {
    final rows = await _client.rpc('merchant_reviews', params: {
      'p_merchant': merchantId,
      'p_limit': limit,
      'p_offset': offset,
    });
    return ((rows as List?) ?? const [])
        .map((r) => Review.fromJson(r as Map<String, dynamic>))
        .toList();
  }

  /// ملخّص التقييم (متوسط + عدد المراجعات المرئية).
  Future<RatingSummary> ratingSummary(String merchantId) async {
    final rows = await _client
        .rpc('merchant_rating', params: {'p_merchant': merchantId});
    final list = (rows as List?) ?? const [];
    if (list.isEmpty) return RatingSummary.empty;
    return RatingSummary.fromJson(list.first as Map<String, dynamic>);
  }

  /// الردّ على مراجعة (نص فارغ = إزالة الردّ) — عبر RPC آمنة (يتحقّق من العضوية).
  Future<void> reply(String reviewId, String reply) async {
    await _client.rpc('reply_to_review',
        params: {'p_review': reviewId, 'p_reply': reply});
  }
}

final reviewsRepoProvider = Provider<ReviewsRepository>(
    (ref) => ReviewsRepository(ref.read(supabaseClientProvider)));
