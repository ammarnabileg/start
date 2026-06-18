import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

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
        .order('is_favorite', ascending: false)
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

  /// تبديل "مشاركة معلوماتي مع هذا المتجر" (خصوصية لكل متجر).
  /// عبر RPC آمنة (لا تمنح العميل صلاحية UPDATE عامة على user_stores).
  Future<void> setVisibility(String merchantId, bool visible) async {
    await _client.rpc('set_store_visibility', params: {
      'p_merchant': merchantId,
      'p_visible': visible,
    });
  }

  /// تبديل "متجر مفضّل" (يظهر أعلى القائمة) — عبر RPC آمنة.
  Future<void> setFavorite(String merchantId, bool favorite) async {
    await _client.rpc('set_store_favorite', params: {
      'p_merchant': merchantId,
      'p_fav': favorite,
    });
  }

  /// خريطة استهداف الفروع لنوع عناصر معيّن: id → فروعه (فارغ = موحّد).
  Future<Map<String, Set<String>>> _branchTargets(
      String type, String merchantId) async {
    final rows = await _client
        .from('entity_branches')
        .select('entity_id, branch_id')
        .eq('merchant_id', merchantId)
        .eq('entity_type', type);
    final map = <String, Set<String>>{};
    for (final r in rows as List) {
      (map[r['entity_id'] as String] ??= <String>{})
          .add(r['branch_id'] as String);
    }
    return map;
  }

  /// هل العنصر متاح في فرع العميل؟ موحّد (بدون استهداف) = متاح دائمًا.
  bool _availableAt(
      Map<String, Set<String>> targets, String id, String? branchId) {
    final t = targets[id];
    if (t == null || t.isEmpty) return true; // موحّد
    if (branchId == null) return true; // لا سياق فرع → لا نُخفي (يفرضه الكاشير)
    return t.contains(branchId);
  }

  /// مكافآت التاجر النشطة المتاحة في فرع محفظة العميل.
  Future<List<Reward>> rewards(String merchantId, {String? branchId}) async {
    final rows = await _client
        .from('rewards')
        .select()
        .eq('merchant_id', merchantId)
        .eq('active', true)
        .order('points_cost');
    final targets = await _branchTargets('reward', merchantId);
    return (rows as List)
        .map((r) => Reward.fromJson(r as Map<String, dynamic>))
        .where((r) => _availableAt(targets, r.id, branchId))
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

  /// حملات بطاقة الأختام للتاجر مع تقدّم العميل فيها.
  Future<List<StampCampaign>> visits(UserStore store) async {
    final uid = _client.auth.currentUser!.id;

    final campaignsRaw = await _client
        .from('visit_campaigns')
        .select()
        .eq('merchant_id', store.merchantId)
        .eq('active', true);
    // استهداف الفروع: نعرض فقط الحملات المتاحة في فرع العميل.
    final targets = await _branchTargets('campaign', store.merchantId);
    final campaigns = (campaignsRaw as List)
        .where((c) => _availableAt(
            targets, (c as Map<String, dynamic>)['id'] as String, store.branchId))
        .toList();

    // أختام/زيارات العميل عند هذا الفرع/التاجر (بتاريخها).
    var visitsQuery = _client
        .from('user_visits')
        .select('visit_date')
        .eq('user_id', uid)
        .eq('merchant_id', store.merchantId);
    if (store.branchId != null) {
      visitsQuery = visitsQuery.eq('branch_id', store.branchId!);
    }
    final visits = await visitsQuery.order('visit_date', ascending: true);
    final dates = (visits as List)
        .map((v) => DateTime.tryParse((v['visit_date'] ?? '').toString()))
        .whereType<DateTime>()
        .toList();
    final visitCount = dates.length;

    return campaigns.map((c) {
      final m = c as Map<String, dynamic>;
      final required = m['required_visits'] as int? ?? 1;
      // الأختام تُحسب ضمن دورة الحملة الحالية.
      final inCycle = required == 0 ? 0 : visitCount % required;
      final current = (visitCount > 0 && inCycle == 0) ? required : inCycle;
      // أحدث [current] تواريخ كأختام الدورة الحالية.
      final cycleDates =
          dates.length >= current ? dates.sublist(dates.length - current) : dates;
      return StampCampaign.fromJson(m,
          currentStamps: current, stampDates: cycleDates);
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

  /// إعدادات التاجر (الميزات المفعّلة + الهوية) — لإخفاء التبويبات المعطّلة
  /// في تطبيق العميل بحيث يعكس ما فعّله التاجر فعلًا.
  Future<MerchantSettings> merchantSettings(String merchantId) async {
    final row = await _client
        .from('merchant_settings')
        .select()
        .eq('merchant_id', merchantId)
        .maybeSingle();
    if (row == null) return MerchantSettings(merchantId: merchantId);
    return MerchantSettings.fromJson(row);
  }

  /// الكوبونات المتاحة للتاجر في فرع محفظة العميل.
  Future<List<Map<String, dynamic>>> coupons(String merchantId,
      {String? branchId}) async {
    final rows = await _client
        .from('coupons')
        .select()
        .eq('merchant_id', merchantId)
        .order('valid_to', ascending: true);
    final targets = await _branchTargets('coupon', merchantId);
    return (rows as List)
        .cast<Map<String, dynamic>>()
        .where((c) => _availableAt(targets, c['id'] as String, branchId))
        .toList();
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
