import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/paginated_notifier.dart';
import '../../data/repositories/stores_repository.dart';
import 'my_stores_screen.dart';
import '../leaderboard/leaderboard_screen.dart';
import '../rewards/reward_detail_screen.dart';
import '../wheel/wheel_screen.dart';

/// صفحة المتجر (Store Detail) — راجع CUSTOMER_APP.md 1.12.
/// تابات داخلية: نظرة عامة · الزيارات · النقاط · المكافآت · المستويات ·
/// الكوبونات · الأسئلة · السجل.

// ===================== Providers (per-store) =====================

/// مكافآت التاجر المتاحة في فرع محفظة العميل.
final storeRewardsProvider =
    FutureProvider.autoDispose.family<List<Reward>, UserStore>((ref, store) async {
  return ref
      .read(storesRepoProvider)
      .rewards(store.merchantId, branchId: store.branchId);
});

/// مستويات الولاء المطبّقة على محفظة العميل (فرعها أو الستور حسب الإعداد).
final storeLevelsProvider =
    FutureProvider.autoDispose.family<List<LoyaltyLevel>, UserStore>((ref, store) async {
  return ref
      .read(storesRepoProvider)
      .levels(store.merchantId, branchId: store.branchId);
});

final storeVisitsProvider =
    FutureProvider.autoDispose.family<List<StampCampaign>, UserStore>((ref, store) async {
  return ref.read(storesRepoProvider).visits(store);
});

/// إعدادات التاجر — تحدّد التبويبات الظاهرة (تحترم ما فعّله التاجر).
final storeSettingsProvider = FutureProvider.autoDispose
    .family<MerchantSettings, String>((ref, merchantId) async {
  return ref.read(storesRepoProvider).merchantSettings(merchantId);
});

/// سجل حركات النقاط في هذا المتجر (مرقّم).
final storeHistoryProvider = StateNotifierProvider.autoDispose
    .family<PaginatedNotifier<Map<String, dynamic>>,
        PaginatedState<Map<String, dynamic>>, String>((ref, userStoreId) {
  final repo = ref.read(storesRepoProvider);
  return PaginatedNotifier<Map<String, dynamic>>(
    (offset, limit) =>
        repo.history(userStoreId, offset: offset, limit: limit),
  );
});

/// الكوبونات المتاحة للتاجر في فرع محفظة العميل.
final storeCouponsProvider =
    FutureProvider.autoDispose.family<List<Map<String, dynamic>>, UserStore>(
        (ref, store) async {
  return ref
      .read(storesRepoProvider)
      .coupons(store.merchantId, branchId: store.branchId);
});

/// أسئلة التاجر (بنقاط) + خياراتها + هل أجابها العميل.
final storeQuestionsProvider =
    FutureProvider.autoDispose.family<List<MerchantQuestion>, String>(
        (ref, merchantId) async {
  return ref.read(storesRepoProvider).questions(merchantId);
});

/// ملخّص تقييم المتجر (متوسط + عدد).
final storeRatingProvider = FutureProvider.autoDispose
    .family<RatingSummary, String>((ref, merchantId) async {
  return ref.read(storesRepoProvider).ratingSummary(merchantId);
});

/// مراجعات المتجر المرئية (مراجعتي أولًا).
final storeReviewsProvider = FutureProvider.autoDispose
    .family<List<Review>, String>((ref, merchantId) async {
  return ref.read(storesRepoProvider).storeReviews(merchantId);
});

// ===================== Screen =====================

class StoreDetailScreen extends ConsumerWidget {
  final UserStore store;
  const StoreDetailScreen({super.key, required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // محفظة حيّة: نتابع بثّ المحافظ فتتحدّث النقاط/المستوى لحظيًا بعد المسح حتى
    // وشاشة التفاصيل مفتوحة (يحلّ مشكلة البيانات القديمة على هذه الشاشة).
    ref.watch(userStoresChangesProvider);
    final live = ref.watch(myStoresProvider).valueOrNull?.firstWhere(
              (s) => s.id == store.id,
              orElse: () => store,
            ) ??
        store;

    // الإعدادات تحدّد التبويبات الظاهرة. أثناء التحميل نُظهر الكل ثم نطوي المعطّل.
    final settings = ref.watch(storeSettingsProvider(live.merchantId)).valueOrNull;
    bool on(bool Function(MerchantSettings) f) => settings == null ? true : f(settings);

    final sections = <({String label, Widget view})>[
      (label: 'نظرة عامة', view: _OverviewTab(store: live)),
      if (on((s) => s.enableVisits)) (label: 'بطاقاتي', view: _VisitsTab(store: live)),
      (label: 'النقاط', view: _PointsTab(store: live)),
      if (on((s) => s.enableRewards)) (label: 'المكافآت', view: _RewardsTab(store: live)),
      if (on((s) => s.enableLevels)) (label: 'المستويات', view: _LevelsTab(store: live)),
      if (on((s) => s.enableCoupons)) (label: 'الكوبونات', view: _CouponsTab(store: live)),
      (label: 'الأسئلة', view: _QuestionsTab(store: live)),
      (label: 'التقييمات', view: _ReviewsTab(store: live)),
      (label: 'السجل', view: _HistoryTab(store: live)),
    ];
    return DefaultTabController(
      length: sections.length,
      child: Scaffold(
        body: Column(
          children: [
            HeroHeader(
              title: live.merchantName ?? 'المتجر',
              subtitle: live.branchName ??
                  (live.currentLevelName != null
                      ? 'مستوى ${live.currentLevelName}'
                      : null),
              trailing: ClipRRect(
                borderRadius: BorderRadius.circular(AppRadii.md),
                child: live.merchantLogoUrl != null
                    ? CachedNetworkImage(
                        imageUrl: live.merchantLogoUrl!,
                        width: 56,
                        height: 56,
                        fit: BoxFit.cover)
                    : Container(
                        width: 56,
                        height: 56,
                        color: AppColors.onPrimary.withValues(alpha: .12),
                        child: const AppIcon(Icons.storefront,
                            color: AppColors.onPrimary),
                      ),
              ),
              bottom: PointsBadge(points: live.availablePoints),
            ),
            if (!live.merchantAvailable) const _UnavailableBanner(),
            Material(
              color: AppColors.surface,
              child: TabBar(
                isScrollable: true,
                tabs: [for (final s in sections) Tab(text: s.label)],
              ),
            ),
            Expanded(
              child: TabBarView(
                children: [for (final s in sections) s.view],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// لافتة علوية تُعرض عندما يكون المتجر معلّقًا/غير متاح.
class _UnavailableBanner extends StatelessWidget {
  const _UnavailableBanner();
  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        color: AppColors.warning,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: const Row(
          children: [
            AppIcon(Icons.info_outline_rounded, color: Colors.white, size: 20),
            SizedBox(width: 8),
            Expanded(
              child: Text('هذا المتجر غير متاح حاليًا',
                  style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      );
}

/// رسالة موحّدة عند محاولة التفاعل مع متجر غير متاح.
void _notifyUnavailable(BuildContext context) {
  AppFeedback.toast(context, 'المتجر غير متاح حاليًا', error: true);
}

// ===================== نظرة عامة =====================

class _OverviewTab extends StatelessWidget {
  final UserStore store;
  const _OverviewTab({required this.store});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
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
        const SizedBox(height: 12),
        AppCard(
          gradient: AppColors.goldGradient,
          onTap: () => store.merchantAvailable
              ? Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => WheelScreen(
                    merchantId: store.merchantId,
                    merchantName: store.merchantName,
                  ),
                ))
              : _notifyUnavailable(context),
          child: Row(
            children: [
              const AppIcon(Icons.casino_rounded,
                  color: AppColors.onPrimary, size: 28),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('عجلة الحظ',
                        style: theme.textTheme.titleMedium
                            ?.copyWith(color: AppColors.onPrimary)),
                    const SizedBox(height: 2),
                    Text('لِف واربح هدايا ونقاط',
                        style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.onPrimary.withValues(alpha: .85))),
                  ],
                ),
              ),
              const AppIcon(Icons.chevron_left_rounded,
                  color: AppColors.onPrimary),
            ],
          ),
        ),
        const SizedBox(height: 20),
        const SectionHeader(title: 'الخصوصية'),
        const SizedBox(height: 8),
        _SharingCard(store: store),
      ],
    );
  }
}

/// تبديل "مشاركة معلوماتي مع هذا المتجر" (خصوصية لكل متجر). تحديث متفائل +
/// لحظي: التبديل يستدعي RPC ثم يُبطل myStoresProvider، والبثّ الحيّ يعكس الحالة.
class _SharingCard extends ConsumerStatefulWidget {
  final UserStore store;
  const _SharingCard({required this.store});
  @override
  ConsumerState<_SharingCard> createState() => _SharingCardState();
}

class _SharingCardState extends ConsumerState<_SharingCard> {
  bool? _pending; // قيمة متفائلة أثناء الحفظ
  bool _busy = false;

  Future<void> _toggle(bool v) async {
    setState(() {
      _pending = v;
      _busy = true;
    });
    final name = widget.store.merchantName ?? 'المتجر';
    try {
      await ref.read(storesRepoProvider).setVisibility(widget.store.merchantId, v);
      ref.invalidate(myStoresProvider);
      if (mounted) {
        AppFeedback.toast(
            context,
            v
                ? 'تمت مشاركة معلوماتك مع $name'
                : 'تم إخفاء معلوماتك عن $name');
      }
    } catch (_) {
      if (mounted) {
        setState(() => _pending = null); // تراجع
        AppFeedback.toast(context, 'تعذّر تحديث الإعداد، حاول مجددًا',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final value = _pending ?? widget.store.visible;
    return AppCard(
      child: Row(
        children: [
          AppIconBadge(
              value ? Icons.visibility_rounded : Icons.visibility_off_rounded,
              size: 44,
              iconSize: 22,
              color: value ? null : AppColors.textSecondary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('مشاركة معلوماتي مع هذا المتجر',
                    style: theme.textTheme.titleMedium),
                const SizedBox(height: 2),
                Text(
                  value
                      ? 'هذا المتجر يستطيع رؤية ملفك والتواصل معك.'
                      : 'أنت مخفي عن قوائم وصدارة هذا المتجر. نقاطك وزياراتك ومكافآتك تستمر كالمعتاد.',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Switch(value: value, onChanged: _busy ? null : _toggle),
        ],
      ),
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
            icon: Icons.card_giftcard_outlined,
            title: 'لا توجد بطاقات أختام حاليًا',
            message: 'تابع المتجر — قد تظهر بطاقات جديدة قريبًا.',
          );
        }
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(storeVisitsProvider(store)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: campaigns.length,
            separatorBuilder: (_, __) => const SizedBox(height: 14),
            itemBuilder: (_, i) => StampCard(campaign: campaigns[i]),
          ),
        );
      },
    );
  }
}

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
    final data = ref.watch(storeRewardsProvider(store));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل المكافآت',
          onRetry: () =>
              ref.invalidate(storeRewardsProvider(store))),
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
              ref.invalidate(storeRewardsProvider(store)),
          child: GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: context.responsive(mobile: 2, tablet: 3),
              mainAxisSpacing: 14,
              crossAxisSpacing: 14,
              childAspectRatio: 0.72,
            ),
            itemCount: rewards.length,
            itemBuilder: (_, i) => _RewardCard(
              reward: rewards[i],
              availablePoints: store.availablePoints,
              available: store.merchantAvailable,
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
  final bool available;
  const _RewardCard(
      {required this.reward,
      required this.availablePoints,
      required this.available});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final affordable = reward.affordableWith(availablePoints);
    final outOfStock = !reward.inStock;
    final missing = reward.pointsCost - availablePoints;
    final dim = !affordable || outOfStock || !available;

    return Opacity(
      opacity: dim ? 0.55 : 1,
      child: AppCard(
        padding: const EdgeInsets.all(12),
        onTap: outOfStock
            ? null
            : !available
                ? () => _notifyUnavailable(context)
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
                              child: const AppIcon(Icons.card_giftcard,
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
                  onPressed: !available
                      ? () => _notifyUnavailable(context)
                      : () => Navigator.of(context).push(MaterialPageRoute(
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
    final data = ref.watch(storeLevelsProvider(store));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل المستويات',
          onRetry: () => ref.invalidate(storeLevelsProvider(store))),
      data: (levels) {
        if (levels.isEmpty) {
          return const EmptyView(
            icon: Icons.workspace_premium_outlined,
            title: 'لا توجد مستويات',
          );
        }
        // مستويات لها وصف مزايا — نعرضها كبطاقات تحت الرحلة (كانت تُحفظ ولا تظهر).
        final withPerks = [
          for (final l in levels)
            if ((l.rewardDescription ?? '').trim().isNotEmpty) l,
        ]..sort((a, b) =>
            a.thresholdLifetimePoints.compareTo(b.thresholdLifetimePoints));
        return SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              LevelsJourney(
                levels: levels,
                lifetimePoints: store.lifetimePoints,
                title: 'رحلة مستوياتك',
              ),
              if (withPerks.isNotEmpty) ...[
                const SizedBox(height: 20),
                const SectionHeader(title: 'مزايا المستويات'),
                const SizedBox(height: 8),
                for (final l in withPerks)
                  _LevelPerkCard(
                    level: l,
                    unlocked: l.thresholdLifetimePoints <= store.lifetimePoints,
                  ),
              ],
            ],
          ),
        );
      },
    );
  }
}

/// بطاقة ميزة مستوى — تُظهر وصف المزايا التي يحدّدها التاجر (مفتوحة/مقفلة).
class _LevelPerkCard extends StatelessWidget {
  final LoyaltyLevel level;
  final bool unlocked;
  const _LevelPerkCard({required this.level, required this.unlocked});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppCard(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AppIcon(
              unlocked ? Icons.lock_open_rounded : Icons.lock_outline_rounded,
              color: unlocked ? AppColors.success : AppColors.textSecondary,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(level.name,
                      style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 2),
                  Text(level.rewardDescription ?? '',
                      style: Theme.of(context).textTheme.bodySmall),
                  if (!unlocked) ...[
                    const SizedBox(height: 4),
                    Text(
                      'تُفتح عند ${level.thresholdLifetimePoints} نقطة كليّة',
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: AppColors.textSecondary),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
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
    final data = ref.watch(storeCouponsProvider(store));
    return data.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الكوبونات',
          onRetry: () =>
              ref.invalidate(storeCouponsProvider(store))),
      data: (coupons) {
        if (coupons.isEmpty) {
          return const EmptyView(
            icon: Icons.confirmation_number_outlined,
            title: 'لا توجد كوبونات متاحة',
          );
        }
        return RefreshIndicator(
          onRefresh: () async =>
              ref.invalidate(storeCouponsProvider(store)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: coupons.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _CouponCard(
                coupon: coupons[i], available: store.merchantAvailable),
          ),
        );
      },
    );
  }
}

class _CouponCard extends StatelessWidget {
  final Map<String, dynamic> coupon;
  final bool available;
  const _CouponCard({required this.coupon, required this.available});

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
            onPressed: available
                ? () => _showCouponDialog(context)
                : () => _notifyUnavailable(context),
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
              available: store.merchantAvailable,
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

class _QuestionCard extends ConsumerStatefulWidget {
  final MerchantQuestion question;
  final bool available;
  final VoidCallback onAnswered;
  const _QuestionCard(
      {required this.question,
      required this.available,
      required this.onAnswered});

  @override
  ConsumerState<_QuestionCard> createState() => _QuestionCardState();
}

class _QuestionCardState extends ConsumerState<_QuestionCard> {
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
      final data =
          await ref.read(storesRepoProvider).answerQuestion(body);
      if (data != null && data['error'] != null) {
        setState(() => _error = data['error'] as String);
        return;
      }
      final awarded = (data?['points_awarded'] as int?) ?? q.pointsReward;
      if (!mounted) return;
      AppFeedback.success(
        context,
        title: 'أحسنت!',
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
            const Row(
              children: [
                AppIcon(Icons.check_circle, color: AppColors.success, size: 20),
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
              onPressed: !widget.available
                  ? () => _notifyUnavailable(context)
                  : (_canSubmit ? _submit : null),
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

// ===================== التقييمات =====================

class _ReviewsTab extends ConsumerWidget {
  final UserStore store;
  const _ReviewsTab({required this.store});

  void _refresh(WidgetRef ref) {
    ref.invalidate(storeRatingProvider(store.merchantId));
    ref.invalidate(storeReviewsProvider(store.merchantId));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summary =
        ref.watch(storeRatingProvider(store.merchantId)).valueOrNull ??
            RatingSummary.empty;
    final reviews = ref.watch(storeReviewsProvider(store.merchantId));
    return RefreshIndicator(
      onRefresh: () async => _refresh(ref),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _RatingSummaryCard(summary: summary),
          const SizedBox(height: 14),
          PrimaryButton(
            label: 'قيّم هذا المتجر',
            icon: Icons.star_rounded,
            onPressed: store.merchantAvailable
                ? () => _openRatingSheet(context, ref, store)
                : () => _notifyUnavailable(context),
          ),
          const SizedBox(height: 22),
          const SectionHeader(title: 'آراء العملاء'),
          const SizedBox(height: 8),
          reviews.when(
            loading: () => const SizedBox(
                height: 200, child: Center(child: CircularProgressIndicator())),
            error: (_, __) => SizedBox(
              height: 200,
              child: ErrorView(
                  message: 'تعذّر تحميل المراجعات',
                  onRetry: () => _refresh(ref)),
            ),
            data: (list) {
              if (list.isEmpty) {
                return const SizedBox(
                  height: 220,
                  child: EmptyView(
                    icon: Icons.format_quote_rounded,
                    title: 'لا توجد مراجعات بعد',
                    message: 'كن أول من يقيّم هذا المتجر.',
                  ),
                );
              }
              return Column(
                children: [
                  for (final r in list)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ReviewTile(review: r, store: store),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

/// صفّ نجوم للعرض (ممتلئة ذهبية حتى التقييم، والباقي رمادية).
class _Stars extends StatelessWidget {
  final double value;
  final double size;
  const _Stars({required this.value, this.size = 16});
  @override
  Widget build(BuildContext context) {
    final filled = value.round().clamp(0, 5);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 1; i <= 5; i++)
          AppIcon(Icons.star_rounded,
              size: size,
              color: i <= filled
                  ? AppColors.gold
                  : AppColors.textSecondary.withValues(alpha: .3)),
      ],
    );
  }
}

class _RatingSummaryCard extends StatelessWidget {
  final RatingSummary summary;
  const _RatingSummaryCard({required this.summary});
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (!summary.hasRatings) {
      return AppCard(
        child: Row(
          children: [
            const AppIcon(Icons.format_quote_rounded,
                color: AppColors.primaryDark, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Text('لا توجد تقييمات بعد — رأيك يهمّنا!',
                  style: theme.textTheme.titleMedium),
            ),
          ],
        ),
      );
    }
    return AppCard(
      child: Row(
        children: [
          Column(
            children: [
              Text(summary.average.toStringAsFixed(1),
                  style: theme.textTheme.displaySmall?.copyWith(
                          fontWeight: FontWeight.w900) ??
                      const TextStyle(fontSize: 34, fontWeight: FontWeight.w900)),
              _Stars(value: summary.average, size: 16),
            ],
          ),
          const SizedBox(width: 18),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('تقييم المتجر', style: theme.textTheme.titleMedium),
                const SizedBox(height: 4),
                Text('بناءً على ${summary.count} مراجعة',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: AppColors.textSecondary)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ReviewTile extends ConsumerWidget {
  final Review review;
  final UserStore store;
  const _ReviewTile({required this.review, required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final name = review.isMine ? '${review.reviewerName} (أنت)' : review.reviewerName;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: AppColors.surfaceCream,
                child: Text(
                  review.reviewerName.trim().isEmpty
                      ? '؟'
                      : review.reviewerName.trim().substring(0, 1),
                  style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.primaryDark),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, style: theme.textTheme.titleSmall),
                    const SizedBox(height: 2),
                    _Stars(value: review.rating.toDouble(), size: 14),
                  ],
                ),
              ),
              Text(
                DateFormat('yyyy/MM/dd').format(review.createdAt.toLocal()),
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
          if ((review.comment ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(review.comment!, style: theme.textTheme.bodyMedium),
          ],
          if (review.hasReply) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const AppIcon(Icons.storefront_rounded,
                          size: 16, color: AppColors.primaryDark),
                      const SizedBox(width: 6),
                      Text('ردّ المتجر',
                          style: theme.textTheme.bodySmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              color: AppColors.primaryDark)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(review.merchantReply!, style: theme.textTheme.bodySmall),
                ],
              ),
            ),
          ],
          if (review.isMine) ...[
            const SizedBox(height: 6),
            Row(
              children: [
                TextButton.icon(
                  onPressed: store.merchantAvailable
                      ? () => _openRatingSheet(context, ref, store,
                          initialRating: review.rating,
                          initialComment: review.comment)
                      : () => _notifyUnavailable(context),
                  icon: const AppIcon(Icons.edit_outlined, size: 18),
                  label: const Text('تعديل'),
                ),
                TextButton.icon(
                  onPressed: () => _confirmDelete(context, ref, store),
                  icon: const AppIcon(Icons.delete_outline_rounded,
                      size: 18, color: AppColors.error),
                  label: const Text('حذف',
                      style: TextStyle(color: AppColors.error)),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

Future<void> _confirmDelete(
    BuildContext context, WidgetRef ref, UserStore store) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (_) => AlertDialog(
      title: const Text('حذف تقييمك؟'),
      content: const Text('سيُحذف تقييمك وتعليقك لهذا المتجر نهائيًا.'),
      actions: [
        TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('إلغاء')),
        TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('حذف',
                style: TextStyle(color: AppColors.error))),
      ],
    ),
  );
  if (ok != true) return;
  try {
    await ref.read(storesRepoProvider).deleteMyReview(store.merchantId);
    ref.invalidate(storeRatingProvider(store.merchantId));
    ref.invalidate(storeReviewsProvider(store.merchantId));
    if (context.mounted) AppFeedback.toast(context, 'تم حذف تقييمك');
  } catch (_) {
    if (context.mounted) {
      AppFeedback.toast(context, 'تعذّر الحذف، حاول مجددًا', error: true);
    }
  }
}

Future<void> _openRatingSheet(
  BuildContext context,
  WidgetRef ref,
  UserStore store, {
  int? initialRating,
  String? initialComment,
}) async {
  // إن لم تُمرّر قيمة مبدئية، نحاول جلب تقييمي الحالي لتعبئة المحرّر.
  var rating = initialRating;
  var comment = initialComment;
  if (rating == null) {
    try {
      final mine = await ref.read(storesRepoProvider).myReview(store.merchantId);
      if (mine != null) {
        rating = mine.rating;
        comment = mine.comment;
      }
    } catch (_) {/* تجاهل — نبدأ فارغًا */}
  }
  if (!context.mounted) return;
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (_) => _RatingSheet(
      store: store,
      initialRating: rating ?? 0,
      initialComment: comment,
    ),
  );
}

class _RatingSheet extends ConsumerStatefulWidget {
  final UserStore store;
  final int initialRating;
  final String? initialComment;
  const _RatingSheet(
      {required this.store, required this.initialRating, this.initialComment});
  @override
  ConsumerState<_RatingSheet> createState() => _RatingSheetState();
}

class _RatingSheetState extends ConsumerState<_RatingSheet> {
  late int _rating = widget.initialRating;
  late final TextEditingController _ctrl =
      TextEditingController(text: widget.initialComment ?? '');
  bool _saving = false;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_rating < 1) {
      AppFeedback.toast(context, 'اختر تقييمًا من 1 إلى 5 نجوم', error: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await ref.read(storesRepoProvider).upsertReview(
            widget.store.merchantId,
            _rating,
            comment: _ctrl.text.trim().isEmpty ? null : _ctrl.text.trim(),
          );
      ref.invalidate(storeRatingProvider(widget.store.merchantId));
      ref.invalidate(storeReviewsProvider(widget.store.merchantId));
      if (mounted) {
        Navigator.of(context).pop();
        AppFeedback.success(context,
            title: 'شكرًا لك!', message: 'تم نشر تقييمك للمتجر');
      }
    } catch (_) {
      if (mounted) {
        setState(() => _saving = false);
        AppFeedback.toast(context, 'تعذّر إرسال التقييم، حاول مجددًا',
            error: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20, 20 + bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.textSecondary.withValues(alpha: .3),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('قيّم ${widget.store.merchantName ?? "المتجر"}',
              style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var i = 1; i <= 5; i++)
                IconButton(
                  onPressed: () => setState(() => _rating = i),
                  iconSize: 40,
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  constraints: const BoxConstraints(),
                  icon: AppIcon(
                    Icons.star_rounded,
                    size: 40,
                    color: i <= _rating
                        ? AppColors.gold
                        : AppColors.textSecondary.withValues(alpha: .3),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _ctrl,
            maxLines: 3,
            maxLength: 500,
            decoration: const InputDecoration(
              hintText: 'شاركنا تجربتك (اختياري)',
            ),
          ),
          const SizedBox(height: 8),
          PrimaryButton(
            label: 'نشر التقييم',
            loading: _saving,
            onPressed: _submit,
          ),
        ],
      ),
    );
  }
}

// ===================== السجل =====================

class _HistoryTab extends ConsumerWidget {
  final UserStore store;
  const _HistoryTab({required this.store});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(storeHistoryProvider(store.id));
    final notifier = ref.read(storeHistoryProvider(store.id).notifier);
    return PaginatedListView<Map<String, dynamic>>(
      state: state,
      onLoadMore: notifier.loadMore,
      onRefresh: notifier.refresh,
      emptyIcon: Icons.history_rounded,
      emptyTitle: 'لا توجد حركات بعد',
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (_, tx, __) => _HistoryRow(tx: tx),
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
            child: AppIcon(
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
