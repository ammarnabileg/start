import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../leaderboard/leaderboard_screen.dart';
import '../rewards/reward_detail_screen.dart';

/// صفحة المتجر (Store Detail) — راجع CUSTOMER_APP.md 1.12.
/// تابات داخلية: نظرة عامة · الزيارات · النقاط · المكافآت · المستويات ·
/// الكوبونات · الأسئلة · السجل.

// ===================== Providers (per-store) =====================

/// مكافآت التاجر النشطة.
final storeRewardsProvider =
    FutureProvider.family<List<Reward>, String>((ref, merchantId) async {
  final client = Supabase.instance.client;
  final rows = await client
      .from('rewards')
      .select()
      .eq('merchant_id', merchantId)
      .eq('active', true)
      .order('points_cost');
  return (rows as List)
      .map((r) => Reward.fromJson(r as Map<String, dynamic>))
      .toList();
});

/// مستويات الولاء للتاجر مرتّبة.
final storeLevelsProvider =
    FutureProvider.family<List<LoyaltyLevel>, String>((ref, merchantId) async {
  final client = Supabase.instance.client;
  final rows = await client
      .from('loyalty_levels')
      .select()
      .eq('merchant_id', merchantId)
      .order('sort_order');
  return (rows as List)
      .map((r) => LoyaltyLevel.fromJson(r as Map<String, dynamic>))
      .toList();
});

/// الحملات الحالية للتاجر مع عدد زيارات العميل فيها.
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

final storeVisitsProvider =
    FutureProvider.family<List<CampaignProgress>, UserStore>((ref, store) async {
  final client = Supabase.instance.client;
  final uid = client.auth.currentUser!.id;

  final campaigns = await client
      .from('visit_campaigns')
      .select()
      .eq('merchant_id', store.merchantId)
      .eq('active', true);

  // عدد زيارات العميل عند هذا الفرع/التاجر.
  var visitsQuery = client
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
});

/// سجل حركات النقاط في هذا المتجر.
final storeHistoryProvider =
    FutureProvider.family<List<Map<String, dynamic>>, String>(
        (ref, userStoreId) async {
  final client = Supabase.instance.client;
  final rows = await client
      .from('points_transactions')
      .select()
      .eq('user_store_id', userStoreId)
      .order('created_at', ascending: false)
      .limit(100);
  return (rows as List).cast<Map<String, dynamic>>();
});

/// الكوبونات المتاحة للتاجر.
final storeCouponsProvider =
    FutureProvider.family<List<Map<String, dynamic>>, String>(
        (ref, merchantId) async {
  final client = Supabase.instance.client;
  final rows = await client
      .from('coupons')
      .select()
      .eq('merchant_id', merchantId)
      .order('valid_to', ascending: true);
  return (rows as List).cast<Map<String, dynamic>>();
});

/// أسئلة التاجر (بنقاط) + خياراتها + هل أجابها العميل.
final storeQuestionsProvider =
    FutureProvider.family<List<MerchantQuestion>, String>(
        (ref, merchantId) async {
  final client = Supabase.instance.client;
  final uid = client.auth.currentUser!.id;

  final rows = await client
      .from('merchant_questions')
      .select('*, question_options(*)')
      .eq('merchant_id', merchantId)
      .eq('active', true)
      .order('created_at');

  // معرفات الأسئلة المُجاب عليها مسبقًا.
  final responses = await client
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
});

// ===================== Screen =====================

class StoreDetailScreen extends StatelessWidget {
  final UserStore store;
  const StoreDetailScreen({super.key, required this.store});

  @override
  Widget build(BuildContext context) {
    const tabs = [
      'نظرة عامة',
      'الزيارات',
      'النقاط',
      'المكافآت',
      'المستويات',
      'الكوبونات',
      'الأسئلة',
      'السجل',
    ];
    return DefaultTabController(
      length: tabs.length,
      child: Scaffold(
        body: Column(
          children: [
            HeroHeader(
              title: store.merchantName ?? 'المتجر',
              subtitle: store.branchName ??
                  (store.currentLevelName != null
                      ? 'مستوى ${store.currentLevelName}'
                      : null),
              trailing: ClipRRect(
                borderRadius: BorderRadius.circular(AppRadii.md),
                child: store.merchantLogoUrl != null
                    ? CachedNetworkImage(
                        imageUrl: store.merchantLogoUrl!,
                        width: 56,
                        height: 56,
                        fit: BoxFit.cover)
                    : Container(
                        width: 56,
                        height: 56,
                        color: AppColors.onPrimary.withValues(alpha: .12),
                        child: const Icon(Icons.storefront,
                            color: AppColors.onPrimary),
                      ),
              ),
              bottom: PointsBadge(points: store.availablePoints),
            ),
            Material(
              color: AppColors.surface,
              child: TabBar(
                isScrollable: true,
                tabs: [for (final t in tabs) Tab(text: t)],
              ),
            ),
            Expanded(
              child: TabBarView(
                children: [
            _OverviewTab(store: store),
            _VisitsTab(store: store),
            _PointsTab(store: store),
            _RewardsTab(store: store),
            _LevelsTab(store: store),
            _CouponsTab(store: store),
                  _QuestionsTab(store: store),
                  _HistoryTab(store: store),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ===================== نظرة عامة =====================

class _OverviewTab extends StatelessWidget {
  final UserStore store;
  const _OverviewTab({required this.store});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const SectionHeader(title: 'حالتك في المتجر'),
        const SizedBox(height: 8),
        // بطاقة الحالة السريعة: المستوى + النقاط المتاحة + إجمالي النقاط.
        Row(
          children: [
            Expanded(
              child: StatCard(
                icon: Icons.workspace_premium_outlined,
                label: 'المستوى',
                value: store.currentLevelName ?? '—',
                highlight: true,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: StatCard(
                icon: Icons.star_rounded,
                label: 'النقاط المتاحة',
                value: '${store.availablePoints}',
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: StatCard(
                icon: Icons.event_available_outlined,
                label: 'إجمالي النقاط',
                value: '${store.lifetimePoints}',
              ),
            ),
          ],
        ),
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'لوحة صدارة المتجر',
          icon: Icons.emoji_events_outlined,
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => LeaderboardScreen(
              args: LeaderboardArgs(
                merchantId: store.merchantId,
                branchId: store.branchId,
                title: 'صدارة ${store.merchantName ?? "المتجر"}',
              ),
            ),
          )),
        ),
      ],
    );
  }
}

// ===================== الزيارات =====================

class _VisitsTab extends ConsumerWidget {
  final UserStore store;
  const _VisitsTab({required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(storeVisitsProvider(store));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الزيارات',
          onRetry: () => ref.invalidate(storeVisitsProvider(store))),
      data: (campaigns) {
        if (campaigns.isEmpty) {
          return const EmptyView(
            icon: Icons.local_cafe_outlined,
            title: 'لا توجد حملات زيارة حاليًا',
            message: 'تابع المتجر — قد تظهر حملات جديدة قريبًا.',
          );
        }
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(storeVisitsProvider(store)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: campaigns.length,
            separatorBuilder: (_, __) => const SizedBox(height: 14),
            itemBuilder: (_, i) => _CampaignCard(campaign: campaigns[i]),
          ),
        );
      },
    );
  }
}

class _CampaignCard extends StatelessWidget {
  final CampaignProgress campaign;
  const _CampaignCard({required this.campaign});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (campaign.completed) {
      return AppCard(
        color: AppColors.primaryLight,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('مكافأتك جاهزة! 🎁', style: theme.textTheme.titleLarge),
            const SizedBox(height: 8),
            Text('أرِ رمزك للكاشير لاستلامها.',
                style: theme.textTheme.bodyMedium),
          ],
        ),
      );
    }
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'زُر ${campaign.requiredVisits} مرات واحصل على ${campaign.rewardName}',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 16),
          // شريط التقدّم البصري: يوم 1 ✓ ... يوم N 🎁.
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (var day = 1; day <= campaign.requiredVisits; day++)
                _DayChip(
                  day: day,
                  total: campaign.requiredVisits,
                  done: day <= campaign.currentVisits,
                  current: day == campaign.currentVisits + 1,
                ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            campaign.remaining == 1
                ? 'زيارة واحدة متبقية للحصول على مكافأتك.'
                : campaign.remaining == 2
                    ? 'زيارتان متبقيتان للحصول على مكافأتك.'
                    : '${campaign.remaining} زيارات متبقية للحصول على مكافأتك.',
            style: theme.textTheme.bodyMedium
                ?.copyWith(color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

class _DayChip extends StatelessWidget {
  final int day;
  final int total;
  final bool done;
  final bool current;
  const _DayChip(
      {required this.day,
      required this.total,
      required this.done,
      required this.current});

  @override
  Widget build(BuildContext context) {
    final isReward = day == total;
    final Color bg;
    final Widget child;
    if (done) {
      bg = AppColors.success;
      child = const Icon(Icons.check_rounded, color: Colors.white, size: 22);
    } else if (current) {
      bg = AppColors.primary;
      child = Text('$day',
          style: const TextStyle(
              color: AppColors.onPrimary, fontWeight: FontWeight.w800));
    } else {
      bg = AppColors.surfaceCream;
      child = isReward
          ? const Text('🎁', style: TextStyle(fontSize: 20))
          : Text('$day',
              style: const TextStyle(
                  color: AppColors.textSecondary, fontWeight: FontWeight.w700));
    }
    return Container(
      width: 48,
      height: 48,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: bg,
        shape: BoxShape.circle,
        border: current
            ? Border.all(color: AppColors.primaryDark, width: 2)
            : null,
      ),
      child: child,
    );
  }
}

// ===================== النقاط =====================

class _PointsTab extends StatelessWidget {
  final UserStore store;
  const _PointsTab({required this.store});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        AppCard(
          color: AppColors.primaryLight,
          child: Column(
            children: [
              Text('${store.availablePoints}',
                  style: theme.textTheme.displayLarge ??
                      const TextStyle(
                          fontSize: 48, fontWeight: FontWeight.w900)),
              Text('نقطة',
                  style: theme.textTheme.titleMedium),
              const SizedBox(height: 6),
              Text('قابلة للاستبدال الآن.',
                  style: theme.textTheme.bodyMedium),
            ],
          ),
        ),
        const SizedBox(height: 16),
        AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${store.lifetimePoints} نقطة',
                  style: theme.textTheme.titleLarge),
              const SizedBox(height: 6),
              Text('إجمالي ما جمعته. تُحدّد مستواك ولا تُخصم أبدًا.',
                  style: theme.textTheme.bodyMedium
                      ?.copyWith(color: AppColors.textSecondary)),
            ],
          ),
        ),
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'استبدل نقاطك',
          icon: Icons.card_giftcard_outlined,
          onPressed: () {
            // التبديل لتاب المكافآت (index 3).
            DefaultTabController.of(context).animateTo(3);
          },
        ),
      ],
    );
  }
}

// ===================== المكافآت =====================

class _RewardsTab extends ConsumerWidget {
  final UserStore store;
  const _RewardsTab({required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(storeRewardsProvider(store.merchantId));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل المكافآت',
          onRetry: () =>
              ref.invalidate(storeRewardsProvider(store.merchantId))),
      data: (rewards) {
        if (rewards.isEmpty) {
          return const EmptyView(
            icon: Icons.card_giftcard_outlined,
            title: 'لا توجد مكافآت بعد',
            message: 'تابع المتجر — قد تُضاف مكافآت قريبًا.',
          );
        }
        return RefreshIndicator(
          onRefresh: () async =>
              ref.invalidate(storeRewardsProvider(store.merchantId)),
          child: GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 14,
              crossAxisSpacing: 14,
              childAspectRatio: 0.72,
            ),
            itemCount: rewards.length,
            itemBuilder: (_, i) => _RewardCard(
              reward: rewards[i],
              availablePoints: store.availablePoints,
            ),
          ),
        );
      },
    );
  }
}

class _RewardCard extends StatelessWidget {
  final Reward reward;
  final int availablePoints;
  const _RewardCard({required this.reward, required this.availablePoints});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final affordable = reward.affordableWith(availablePoints);
    final outOfStock = !reward.inStock;
    final missing = reward.pointsCost - availablePoints;
    final dim = !affordable || outOfStock;

    return Opacity(
      opacity: dim ? 0.55 : 1,
      child: AppCard(
        padding: const EdgeInsets.all(12),
        onTap: outOfStock
            ? null
            : () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => RewardDetailScreen(
                    reward: reward,
                    availablePoints: availablePoints,
                  ),
                )),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                children: [
                  Positioned.fill(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: reward.imageUrl != null
                          ? CachedNetworkImage(
                              imageUrl: reward.imageUrl!, fit: BoxFit.cover)
                          : Container(
                              color: AppColors.surfaceCream,
                              child: const Icon(Icons.card_giftcard,
                                  size: 40, color: AppColors.primaryDark),
                            ),
                    ),
                  ),
                  if (outOfStock)
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.error,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Text('نفدت الكمية',
                            style: TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w700)),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            Text(reward.name,
                style: theme.textTheme.titleSmall,
                maxLines: 1,
                overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            Text('${reward.pointsCost} نقطة',
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: AppColors.primaryDark)),
            const SizedBox(height: 8),
            if (outOfStock)
              const SizedBox.shrink()
            else if (affordable)
              SizedBox(
                width: double.infinity,
                child: PrimaryButton(
                  label: 'استبدال',
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => RewardDetailScreen(
                      reward: reward,
                      availablePoints: availablePoints,
                    ),
                  )),
                ),
              )
            else
              Text('تحتاج $missing نقطة إضافية',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: AppColors.textSecondary)),
          ],
        ),
      ),
    );
  }
}

// ===================== المستويات =====================

class _LevelsTab extends ConsumerWidget {
  final UserStore store;
  const _LevelsTab({required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(storeLevelsProvider(store.merchantId));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل المستويات',
          onRetry: () => ref.invalidate(storeLevelsProvider(store.merchantId))),
      data: (levels) {
        if (levels.isEmpty) {
          return const EmptyView(
            icon: Icons.workspace_premium_outlined,
            title: 'لا توجد مستويات',
          );
        }
        // المستوى الحالي = أعلى عتبة وصلها الـ lifetime.
        final lifetime = store.lifetimePoints;
        var currentIndex = 0;
        for (var i = 0; i < levels.length; i++) {
          if (lifetime >= levels[i].thresholdLifetimePoints) currentIndex = i;
        }
        final hasNext = currentIndex < levels.length - 1;
        final next = hasNext ? levels[currentIndex + 1] : null;
        final remaining =
            next == null ? 0 : next.thresholdLifetimePoints - lifetime;

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (next != null) ...[
              AppCard(
                color: AppColors.surfaceCream,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('متبقٍ $remaining نقطة للوصول إلى ${next.name}',
                        style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 12),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: LinearProgressIndicator(
                        value: next.thresholdLifetimePoints == 0
                            ? 1
                            : (lifetime / next.thresholdLifetimePoints)
                                .clamp(0.0, 1.0),
                        minHeight: 10,
                        backgroundColor: AppColors.divider,
                        valueColor: const AlwaysStoppedAnimation<Color>(
                            AppColors.primary),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],
            for (var i = 0; i < levels.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _LevelRow(
                  level: levels[i],
                  isCurrent: i == currentIndex,
                ),
              ),
          ],
        );
      },
    );
  }
}

class _LevelRow extends StatelessWidget {
  final LoyaltyLevel level;
  final bool isCurrent;
  const _LevelRow({required this.level, required this.isCurrent});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AppCard(
      color: isCurrent ? AppColors.primaryLight : null,
      child: Row(
        children: [
          CircleAvatar(
            radius: 20,
            backgroundColor: level.color,
            child: const Icon(Icons.workspace_premium,
                color: Colors.white, size: 20),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(level.name, style: theme.textTheme.titleMedium),
                    if (isCurrent) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.primaryDark,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Text('الحالي',
                            style: TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  level.rewardDescription ??
                      'العتبة: ${level.thresholdLifetimePoints} نقطة',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ===================== الكوبونات =====================

class _CouponsTab extends ConsumerWidget {
  final UserStore store;
  const _CouponsTab({required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(storeCouponsProvider(store.merchantId));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الكوبونات',
          onRetry: () =>
              ref.invalidate(storeCouponsProvider(store.merchantId))),
      data: (coupons) {
        if (coupons.isEmpty) {
          return const EmptyView(
            icon: Icons.confirmation_number_outlined,
            title: 'لا توجد كوبونات متاحة',
          );
        }
        return RefreshIndicator(
          onRefresh: () async =>
              ref.invalidate(storeCouponsProvider(store.merchantId)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: coupons.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _CouponCard(coupon: coupons[i]),
          ),
        );
      },
    );
  }
}

class _CouponCard extends StatelessWidget {
  final Map<String, dynamic> coupon;
  const _CouponCard({required this.coupon});

  String _valueLabel() {
    final type = coupon['type'] as String?;
    final value = coupon['value'];
    return switch (type) {
      'percent' => 'خصم $value%',
      'fixed' => 'خصم $value ر.س',
      'free_item' => 'منتج مجاني',
      _ => '$value',
    };
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final validTo = coupon['valid_to'] as String?;
    return AppCard(
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_valueLabel(), style: theme.textTheme.titleMedium),
                const SizedBox(height: 4),
                Text('الكود: ${coupon['code'] ?? '—'}',
                    style: theme.textTheme.bodySmall),
                if (validTo != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    'ينتهي: ${DateFormat('yyyy/MM/dd').format(DateTime.parse(validTo))}',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: AppColors.textSecondary),
                  ),
                ],
              ],
            ),
          ),
          PrimaryButton(
            label: 'استخدام',
            expanded: false,
            onPressed: () => _showCouponDialog(context),
          ),
        ],
      ),
    );
  }

  void _showCouponDialog(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('أرِ هذا الكود للكاشير'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('${coupon['code']}',
                style: const TextStyle(
                    fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: 2)),
            const SizedBox(height: 8),
            Text(_valueLabel()),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('إغلاق'),
          ),
        ],
      ),
    );
  }
}

// ===================== الأسئلة =====================

class _QuestionsTab extends ConsumerWidget {
  final UserStore store;
  const _QuestionsTab({required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(storeQuestionsProvider(store.merchantId));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الأسئلة',
          onRetry: () =>
              ref.invalidate(storeQuestionsProvider(store.merchantId))),
      data: (questions) {
        if (questions.isEmpty) {
          return const EmptyView(
            icon: Icons.help_outline_rounded,
            title: 'لا توجد أسئلة حاليًا',
            message: 'أجب عن أسئلة المتجر لتربح نقاطًا إضافية.',
          );
        }
        return RefreshIndicator(
          onRefresh: () async =>
              ref.invalidate(storeQuestionsProvider(store.merchantId)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: questions.length,
            separatorBuilder: (_, __) => const SizedBox(height: 14),
            itemBuilder: (_, i) => _QuestionCard(
              question: questions[i],
              onAnswered: () {
                ref.invalidate(storeQuestionsProvider(store.merchantId));
              },
            ),
          ),
        );
      },
    );
  }
}

class _QuestionCard extends StatefulWidget {
  final MerchantQuestion question;
  final VoidCallback onAnswered;
  const _QuestionCard({required this.question, required this.onAnswered});

  @override
  State<_QuestionCard> createState() => _QuestionCardState();
}

class _QuestionCardState extends State<_QuestionCard> {
  String? _singleChoice;
  final Set<String> _multiChoice = {};
  final _textCtrl = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _textCtrl.dispose();
    super.dispose();
  }

  bool get _canSubmit {
    final q = widget.question;
    return switch (q.type) {
      QuestionType.singleChoice => _singleChoice != null,
      QuestionType.multiChoice => _multiChoice.isNotEmpty,
      QuestionType.text => _textCtrl.text.trim().isNotEmpty,
    };
  }

  Future<void> _submit() async {
    if (!_canSubmit) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    final q = widget.question;
    try {
      final body = <String, dynamic>{'question_id': q.id};
      if (q.type == QuestionType.text) {
        body['answer_text'] = _textCtrl.text.trim();
      } else if (q.type == QuestionType.singleChoice) {
        body['selected_option_ids'] = [_singleChoice];
      } else {
        body['selected_option_ids'] = _multiChoice.toList();
      }
      final res = await Supabase.instance.client.functions
          .invoke('answer-question', body: body);
      final data = res.data as Map<String, dynamic>?;
      if (data != null && data['error'] != null) {
        setState(() => _error = data['error'] as String);
        return;
      }
      final awarded = (data?['points_awarded'] as int?) ?? q.pointsReward;
      if (!mounted) return;
      AppFeedback.success(
        context,
        title: 'أحسنت! 🎉',
        message: 'حصلت على $awarded نقطة',
      );
      widget.onAnswered();
    } catch (_) {
      setState(() => _error = 'تعذّر إرسال الإجابة، حاول مرة أخرى.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final q = widget.question;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                  child:
                      Text(q.title, style: theme.textTheme.titleMedium)),
              const SizedBox(width: 8),
              PointsBadge(points: q.pointsReward),
            ],
          ),
          if (q.description != null) ...[
            const SizedBox(height: 6),
            Text(q.description!, style: theme.textTheme.bodySmall),
          ],
          const SizedBox(height: 12),
          if (q.answeredByMe)
            Row(
              children: const [
                Icon(Icons.check_circle, color: AppColors.success, size: 20),
                SizedBox(width: 8),
                Text('تمت الإجابة ✓',
                    style: TextStyle(
                        color: AppColors.success, fontWeight: FontWeight.w700)),
              ],
            )
          else ...[
            _buildAnswerInput(q),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!,
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: AppColors.error)),
            ],
            const SizedBox(height: 12),
            PrimaryButton(
              label: 'إرسال الإجابة',
              loading: _loading,
              onPressed: _canSubmit ? _submit : null,
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildAnswerInput(MerchantQuestion q) {
    switch (q.type) {
      case QuestionType.singleChoice:
        return Column(
          children: [
            for (final o in q.options)
              RadioListTile<String>(
                contentPadding: EdgeInsets.zero,
                value: o.id,
                groupValue: _singleChoice,
                title: Text(o.label),
                onChanged: (v) => setState(() => _singleChoice = v),
              ),
          ],
        );
      case QuestionType.multiChoice:
        return Column(
          children: [
            for (final o in q.options)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: _multiChoice.contains(o.id),
                title: Text(o.label),
                controlAffinity: ListTileControlAffinity.leading,
                onChanged: (v) => setState(() {
                  if (v ?? false) {
                    _multiChoice.add(o.id);
                  } else {
                    _multiChoice.remove(o.id);
                  }
                }),
              ),
          ],
        );
      case QuestionType.text:
        return TextField(
          controller: _textCtrl,
          maxLines: 3,
          onChanged: (_) => setState(() {}),
          decoration: const InputDecoration(hintText: 'اكتب إجابتك هنا'),
        );
    }
  }
}

// ===================== السجل =====================

class _HistoryTab extends ConsumerWidget {
  final UserStore store;
  const _HistoryTab({required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(storeHistoryProvider(store.id));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل السجل',
          onRetry: () => ref.invalidate(storeHistoryProvider(store.id))),
      data: (rows) {
        if (rows.isEmpty) {
          return const EmptyView(
            icon: Icons.history_rounded,
            title: 'لا توجد حركات بعد',
          );
        }
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(storeHistoryProvider(store.id)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: rows.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) => _HistoryRow(tx: rows[i]),
          ),
        );
      },
    );
  }
}

class _HistoryRow extends StatelessWidget {
  final Map<String, dynamic> tx;
  const _HistoryRow({required this.tx});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final type = tx['type'] as String?;
    final points = tx['points'] as int? ?? 0;
    final earn = type == 'earn';
    final createdAt = tx['created_at'] as String?;
    final label = switch (type) {
      'earn' => 'إضافة نقاط',
      'redeem' => 'استبدال',
      'adjust' => 'تعديل',
      _ => 'حركة',
    };
    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor:
                earn ? AppColors.primaryLight : AppColors.surfaceCream,
            child: Icon(
              earn ? Icons.add_rounded : Icons.remove_rounded,
              color: AppColors.primaryDark,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tx['reason'] as String? ?? label,
                    style: theme.textTheme.titleMedium),
                if (createdAt != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    DateFormat('yyyy/MM/dd · HH:mm')
                        .format(DateTime.parse(createdAt).toLocal()),
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ],
            ),
          ),
          Text(
            '${earn ? '+' : '-'}${points.abs()}',
            style: theme.textTheme.titleMedium?.copyWith(
              color: earn ? AppColors.success : AppColors.error,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}
