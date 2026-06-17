import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/paginated_notifier.dart';
import '../../data/repositories/stores_repository.dart';
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

// ===================== Screen =====================

class StoreDetailScreen extends StatelessWidget {
  final UserStore store;
  const StoreDetailScreen({super.key, required this.store});

  @override
  Widget build(BuildContext context) {
    const tabs = [
      'نظرة عامة',
      'بطاقاتي',
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
                        child: const AppIcon(Icons.storefront,
                            color: AppColors.onPrimary),
                      ),
              ),
              bottom: PointsBadge(points: store.availablePoints),
            ),
            if (!store.merchantAvailable) const _UnavailableBanner(),
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
        return SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: LevelsJourney(
            levels: levels,
            lifetimePoints: store.lifetimePoints,
            title: 'رحلة مستوياتك',
          ),
        );
      },
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
