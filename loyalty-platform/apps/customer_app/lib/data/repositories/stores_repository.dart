import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// تقدّم العميل في حملة زيارات (للعرض في تاب الزيارات).
class CampaignProgress {
  final String id;
  final String rewardName;
  final String? rewardImageUrl;
  final int requiredVisits;
  final int currentVisits;
  const CampaignProgress({
    required this.id,
    required this.rewardName,
    required this.requiredVisits,
    required this.currentVisits,
    this.rewardImageUrl,
  });

  bool get completed => currentVisits >= requiredVisits;
  int get remaining =>
      (requiredVisits - currentVisits) < 0 ? 0 : requiredVisits - currentVisits;
}

/// مستودع المتاجر: محافظ العميل + بيانات التاجر (مكافآت/مستويات/حملات/كوبونات/أسئلة/سجل).
class StoresRepository {
  StoresRepository(this._client);
  final SupabaseClient _client;

  /// متاجري — المحافظ المرتبطة بالعميل.
  Future<List<UserStore>> myStores() async {
    final uid = _client.auth.currentUser!.id;
    final rows = await _client
        .from('user_stores')
        .select(
            '*, merchants(business_name, logo_url, status), loyalty_levels(name), branches(name)')
        .eq('user_id', uid)
        .order('first_linked_at', ascending: false);

    return (rows as List).map((r) {
      final m = r['merchants'] as Map<String, dynamic>?;
      final lvl = r['loyalty_levels'] as Map<String, dynamic>?;
      final br = r['branches'] as Map<String, dynamic>?;
      return UserStore.fromJson({
        ...r,
        'merchant_name': m?['business_name'],
        'merchant_logo_url': m?['logo_url'],
        'merchant_status': m?['status'],
        'current_level_name': lvl?['name'],
        'branch_name': br?['name'],
      });
    }).toList();
  }

  /// بثّ حيّ لمحافظ العميل — يُستخدم لتحديث النقاط/المستوى لحظيًا.
  Stream<List<Map<String, dynamic>>> watchUserStores(String uid) {
    return _client
        .from('user_stores')
        .stream(primaryKey: ['id']).eq('user_id', uid);
  }

  /// مكافآت التاجر النشطة.
  Future<List<Reward>> rewards(String merchantId) async {
    final rows = await _client
        .from('rewards')
        .select()
        .eq('merchant_id', merchantId)
        .eq('active', true)
        .order('points_cost');
    return (rows as List)
        .map((r) => Reward.fromJson(r as Map<String, dynamic>))
        .toList();
  }

  /// مستويات الولاء المطبّقة على محفظة العميل (مستويات الفرع أو الستور — حسب
  /// إعداد نطاق النقاط عند التاجر). [branchId] = فرع محفظة العميل (قد يكون null).
  Future<List<LoyaltyLevel>> levels(String merchantId, {String? branchId}) async {
    final rows = await _client.rpc('levels_for', params: {
      'p_merchant': merchantId,
      'p_branch': branchId,
    });
    return (rows as List)
        .map((r) => LoyaltyLevel.fromJson(r as Map<String, dynamic>))
        .toList();
  }

  /// الحملات الحالية للتاجر مع عدد زيارات العميل فيها.
  Future<List<CampaignProgress>> visits(UserStore store) async {
    final uid = _client.auth.currentUser!.id;

    final campaigns = await _client
        .from('visit_campaigns')
        .select()
        .eq('merchant_id', store.merchantId)
        .eq('active', true);

    // عدد زيارات العميل عند هذا الفرع/التاجر.
    var visitsQuery = _client
        .from('user_visits')
        .select('visit_date')
        .eq('user_id', uid)
        .eq('merchant_id', store.merchantId);
    if (store.branchId != null) {
      visitsQuery = visitsQuery.eq('branch_id', store.branchId!);
    }
    final visits = await visitsQuery;
    final visitCount = (visits as List).length;

    return (campaigns as List).map((c) {
      final m = c as Map<String, dynamic>;
      final required = m['required_visits'] as int? ?? 0;
      // الزيارات تُحسب ضمن دورة الحملة الحالية.
      final inCycle = required == 0 ? 0 : visitCount % required;
      final current = (visitCount > 0 && inCycle == 0) ? required : inCycle;
      return CampaignProgress(
        id: m['id'] as String,
        rewardName: m['reward_name'] as String? ?? 'مكافأة',
        rewardImageUrl: m['reward_image_url'] as String?,
        requiredVisits: required,
        currentVisits: current,
      );
    }).toList();
  }

  /// سجل حركات النقاط في هذا المتجر (مرقّم).
  Future<List<Map<String, dynamic>>> history(
    String userStoreId, {
    required int offset,
    required int limit,
  }) async {
    final rows = await _client
        .from('points_transactions')
        .select()
        .eq('user_store_id', userStoreId)
        .order('created_at', ascending: false)
        .range(offset, offset + limit - 1);
    return (rows as List).cast<Map<String, dynamic>>();
  }

  /// الكوبونات المتاحة للتاجر.
  Future<List<Map<String, dynamic>>> coupons(String merchantId) async {
    final rows = await _client
        .from('coupons')
        .select()
        .eq('merchant_id', merchantId)
        .order('valid_to', ascending: true);
    return (rows as List).cast<Map<String, dynamic>>();
  }

  /// أسئلة التاجر (بنقاط) + خياراتها + هل أجابها العميل.
  Future<List<MerchantQuestion>> questions(String merchantId) async {
    final uid = _client.auth.currentUser!.id;

    final rows = await _client
        .from('merchant_questions')
        .select('*, question_options(*)')
        .eq('merchant_id', merchantId)
        .eq('active', true)
        .order('created_at');

    // معرفات الأسئلة المُجاب عليها مسبقًا.
    final responses = await _client
        .from('question_responses')
        .select('question_id')
        .eq('user_id', uid);
    final answeredIds = (responses as List)
        .map((r) => (r as Map<String, dynamic>)['question_id'] as String)
        .toSet();

    return (rows as List).map((r) {
      final m = r as Map<String, dynamic>;
      return MerchantQuestion.fromJson({
        ...m,
        'answered_by_me': answeredIds.contains(m['id']),
      });
    }).toList();
  }

  /// إرسال إجابة سؤال عبر دالة الحافة.
  Future<Map<String, dynamic>?> answerQuestion(
      Map<String, dynamic> body) async {
    final res =
        await _client.functions.invoke('answer-question', body: body);
    return res.data as Map<String, dynamic>?;
  }
}

final storesRepoProvider = Provider<StoresRepository>(
    (ref) => StoresRepository(ref.read(supabaseClientProvider)));
