import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/stores_repository.dart';
import '../../data/supabase_providers.dart';
import 'store_detail_screen.dart';

/// متاجري — المحافظ المرتبطة بالعميل (محفظة لكل فرع/تاجر حسب الإعداد).
final myStoresProvider = FutureProvider.autoDispose<List<UserStore>>((ref) async {
  return ref.read(storesRepoProvider).myStores();
});

/// بثّ حيّ لتغيّرات محافظ العميل (نقاط/مستوى) — يُبطل [myStoresProvider] عند أي تغيّر
/// ليُحدّث الكاشير النقاط لحظيًا.
final userStoresChangesProvider =
    StreamProvider.autoDispose<List<Map<String, dynamic>>>((ref) {
  final client = ref.watch(supabaseClientProvider);
  final uid = client.auth.currentUser?.id;
  if (uid == null) return const Stream.empty();
  return ref.read(storesRepoProvider).watchUserStores(uid);
});

class MyStoresScreen extends ConsumerWidget {
  const MyStoresScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // تحديث حيّ: عند تغيّر محافظ العميل، أعد تحميل القائمة.
    ref.listen(userStoresChangesProvider, (_, __) {
      ref.invalidate(myStoresProvider);
    });
    final stores = ref.watch(myStoresProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('متاجري'), centerTitle: true),
      body: stores.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل المتاجر',
            onRetry: () => ref.invalidate(myStoresProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyView(
              icon: Icons.storefront_outlined,
              title: 'لا توجد متاجر بعد',
              message: 'أرِ رمزك لأي متجر مشارك ليظهر هنا تلقائيًا.',
            );
          }
          final totalPoints =
              list.fold<int>(0, (sum, s) => sum + s.availablePoints);
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myStoresProvider),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: list.length + 1,
              itemBuilder: (_, idx) {
                if (idx == 0) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: _SummaryHeader(
                        stores: list.length, points: totalPoints),
                  );
                }
                final i = idx - 1;
                return Padding(
                  padding:
                      EdgeInsets.only(bottom: i == list.length - 1 ? 0 : 12),
                  child: _StoreCard(store: list[i])
                      .animate()
                      .fadeIn(duration: 300.ms, delay: (i * 60).ms)
                      .slideY(begin: .08, end: 0, curve: Curves.easeOut),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _StoreCard extends StatelessWidget {
  final UserStore store;
  const _StoreCard({required this.store});

  @override
  Widget build(BuildContext context) {
    final unavailable = !store.merchantAvailable;
    return Opacity(
      opacity: unavailable ? 0.6 : 1,
      child: AppCard(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => StoreDetailScreen(store: store),
        )),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: store.merchantLogoUrl != null
                  ? CachedNetworkImage(
                      imageUrl: store.merchantLogoUrl!,
                      width: 56, height: 56, fit: BoxFit.cover)
                  : const _LogoFallback(),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(store.merchantName ?? 'متجر',
                      style: Theme.of(context).textTheme.titleMedium,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      if (store.branchName != null)
                        Text(store.branchName!,
                            style: Theme.of(context).textTheme.bodySmall),
                      if (store.currentLevelName != null) _LevelChip(store.currentLevelName!),
                      if (unavailable) const _UnavailableBadge(),
                    ],
                  ),
                  const SizedBox(height: 10),
                  PointsBadge(points: store.availablePoints),
                ],
              ),
            ),
            const SizedBox(width: 4),
            Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _FavStar(store: store),
                const AppIcon(Icons.chevron_left_rounded,
                    color: AppColors.textSecondary),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// نجمة "مفضّل" بتحديث متفائل لحظي.
class _FavStar extends ConsumerStatefulWidget {
  final UserStore store;
  const _FavStar({required this.store});
  @override
  ConsumerState<_FavStar> createState() => _FavStarState();
}

class _FavStarState extends ConsumerState<_FavStar> {
  bool? _optimistic;
  bool _busy = false;

  Future<void> _toggle() async {
    final v = !(_optimistic ?? widget.store.isFavorite);
    setState(() {
      _optimistic = v;
      _busy = true;
    });
    try {
      await ref
          .read(storesRepoProvider)
          .setFavorite(widget.store.merchantId, v);
      ref.invalidate(myStoresProvider);
    } catch (_) {
      if (mounted) {
        setState(() => _optimistic = null);
        AppFeedback.toast(context, 'تعذّر تحديث المفضّلة', error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final fav = _optimistic ?? widget.store.isFavorite;
    return IconButton(
      visualDensity: VisualDensity.compact,
      padding: EdgeInsets.zero,
      constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
      onPressed: _busy ? null : _toggle,
      tooltip: fav ? 'إزالة من المفضّلة' : 'إضافة للمفضّلة',
      icon: AppIcon(
        fav ? Icons.star_rounded : Icons.star_outline_rounded,
        color: fav ? AppColors.gold : AppColors.textSecondary,
      ),
    );
  }
}

/// رأس "متاجري": إجمالي النقاط المتاحة عبر كل المتاجر + عددها.
class _SummaryHeader extends StatelessWidget {
  final int stores;
  final int points;
  const _SummaryHeader({required this.stores, required this.points});

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    Color on(double a) => AppColors.onPrimary.withValues(alpha: a);
    return AppCard(
      gradient: AppColors.goldGradient,
      child: Row(
        children: [
          const AppIcon(Icons.stars_rounded,
              color: AppColors.onPrimary, size: 30),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('إجمالي نقاطك المتاحة',
                    style: t.textTheme.bodySmall?.copyWith(color: on(.85))),
                Text('$points',
                    style: t.textTheme.headlineSmall?.copyWith(
                        color: AppColors.onPrimary,
                        fontWeight: FontWeight.w800)),
              ],
            ),
          ),
          Column(
            children: [
              Text('$stores',
                  style: t.textTheme.titleLarge?.copyWith(
                      color: AppColors.onPrimary,
                      fontWeight: FontWeight.w800)),
              Text('متجر',
                  style: t.textTheme.bodySmall?.copyWith(color: on(.85))),
            ],
          ),
        ],
      ),
    );
  }
}

class _UnavailableBadge extends StatelessWidget {
  const _UnavailableBadge();
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: AppColors.textSecondary.withValues(alpha: .15),
          borderRadius: BorderRadius.circular(20),
        ),
        child: const Text('غير متاح حاليًا',
            style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: AppColors.textSecondary)),
      );
}

class _LevelChip extends StatelessWidget {
  final String label;
  const _LevelChip(this.label);
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: AppColors.surfaceCream,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const AppIcon(Icons.workspace_premium_outlined,
                size: 14, color: AppColors.primaryDark),
            const SizedBox(width: 4),
            Text(label,
                style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark)),
          ],
        ),
      );
}

class _LogoFallback extends StatelessWidget {
  const _LogoFallback();
  @override
  Widget build(BuildContext context) => Container(
        width: 56,
        height: 56,
        color: AppColors.surfaceCream,
        child: const AppIcon(Icons.storefront, color: AppColors.primaryDark),
      );
}
