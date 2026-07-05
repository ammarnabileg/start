import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/leaderboard_repository.dart';

/// مبدّل العرض: الفرع الحالي أو كل الفروع.
final leaderboardScopeProvider = StateProvider<bool>((ref) => true); // true = كل الفروع

final storeLeaderboardProvider =
    FutureProvider.autoDispose<List<LeaderboardEntry>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final allBranches = ref.watch(leaderboardScopeProvider);
  final pBranch = allBranches ? null : staff.branchId;

  final rows = await ref.read(leaderboardRepoProvider).storeLeaderboard(
        merchantId: staff.merchantId,
        branchId: pBranch,
        limit: 50,
      );
  return rows.map(LeaderboardEntry.fromJson).toList();
});

/// 2.15 — لوحة صدارة المتجر.
class StoreLeaderboardScreen extends ConsumerWidget {
  const StoreLeaderboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final allBranches = ref.watch(leaderboardScopeProvider);
    final settingsAsync = ref.watch(merchantSettingsProvider);
    final async = ref.watch(storeLeaderboardProvider);

    final isMerchantScope = settingsAsync.maybeWhen(
      data: (s) => s.pointsScope == PointsScope.merchant,
      orElse: () => false,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('لوحة الصدارة')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: SegmentedButton<bool>(
              segments: const [
                ButtonSegment(value: false, label: Text('الفرع الحالي')),
                ButtonSegment(value: true, label: Text('كل الفروع')),
              ],
              selected: {allBranches},
              onSelectionChanged: (s) =>
                  ref.read(leaderboardScopeProvider.notifier).state = s.first,
            ),
          ),
          if (isMerchantScope && !allBranches)
            Container(
              margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.warningBg,
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              child: Row(
                children: [
                  const AppIcon(Icons.info_outline,
                      color: AppColors.warning, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'نطاق النقاط "مشترك" — قد يكون عرض الفرع فارغًا، الأنسب "كل الفروع".',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                ],
              ),
            ),
          Expanded(
            child: async.when(
              loading: () => const SkeletonList(),
              error: (e, _) => ErrorView(
                message: 'تعذّر تحميل لوحة الصدارة',
                onRetry: () => ref.invalidate(storeLeaderboardProvider),
              ),
              data: (entries) {
                if (entries.isEmpty) {
                  return const EmptyView(
                    icon: Icons.leaderboard_rounded,
                    title: 'لا يوجد ترتيب بعد',
                    message:
                        'سيظهر ترتيب عملائك هنا بمجرد تجميعهم للنقاط.',
                  );
                }
                final top = entries.take(3).toList();
                final rest = entries.skip(3).toList();
                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _Podium(top: top),
                    if (rest.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      const SectionHeader(title: 'بقية الترتيب'),
                      const SizedBox(height: 8),
                      for (var i = 0; i < rest.length; i++)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: AppCard(
                            child: Row(
                              children: [
                                _RankBadge(rank: rest[i].rank),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: Text(rest[i].displayName,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleMedium),
                                ),
                                PointsBadge(points: rest[i].totalPoints),
                              ],
                            ),
                          )
                              .animate()
                              .fadeIn(
                                  duration: 300.ms, delay: (40 * i).ms)
                              .slideX(begin: .06, end: 0),
                        ),
                    ],
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// منصّة تتويج لأفضل ثلاثة (1 في المنتصف وأعلى، 2 يسار، 3 يمين).
class _Podium extends StatelessWidget {
  final List<LeaderboardEntry> top;
  const _Podium({required this.top});

  @override
  Widget build(BuildContext context) {
    LeaderboardEntry? at(int rank) {
      for (final e in top) {
        if (e.rank == rank) return e;
      }
      return null;
    }

    final first = at(1);
    final second = at(2);
    final third = at(3);

    return AppCard(
      gradient: AppColors.goldGradient,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
              child: _PodiumSpot(entry: second, rank: 2, height: 92)),
          Expanded(
              child: _PodiumSpot(entry: first, rank: 1, height: 120)),
          Expanded(
              child: _PodiumSpot(entry: third, rank: 3, height: 76)),
        ],
      ),
    );
  }
}

class _PodiumSpot extends StatelessWidget {
  final LeaderboardEntry? entry;
  final int rank;
  final double height;
  const _PodiumSpot(
      {required this.entry, required this.rank, required this.height});

  @override
  Widget build(BuildContext context) {
    if (entry == null) return const SizedBox.shrink();
    final medalColor = switch (rank) {
      1 => AppColors.goldTier,
      2 => AppColors.silver,
      _ => AppColors.bronze,
    };
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.topCenter,
            children: [
              CircleAvatar(
                radius: rank == 1 ? 28 : 22,
                backgroundColor: AppColors.surface,
                child: AppIcon(Icons.emoji_events_rounded,
                    color: medalColor, size: rank == 1 ? 32 : 26),
              ),
              if (rank == 1)
                const Positioned(
                  top: -16,
                  child: AppIcon(Icons.workspace_premium_rounded,
                      color: AppColors.surface, size: 22),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            entry!.displayName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: const TextStyle(
                color: AppColors.onPrimary, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 6),
          Container(
            width: double.infinity,
            height: height,
            alignment: Alignment.topCenter,
            padding: const EdgeInsets.only(top: 8),
            decoration: BoxDecoration(
              color: AppColors.surface.withValues(alpha: .35),
              borderRadius:
                  const BorderRadius.vertical(top: Radius.circular(12)),
            ),
            child: Column(
              children: [
                Text('$rank',
                    style: TextStyle(
                        color: AppColors.onPrimary,
                        fontWeight: FontWeight.w900,
                        fontSize: rank == 1 ? 24 : 18)),
                const SizedBox(height: 4),
                Text('${entry!.totalPoints}',
                    style: const TextStyle(
                        color: AppColors.onPrimary,
                        fontWeight: FontWeight.w700,
                        fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    )
        .animate()
        .fadeIn(duration: 350.ms, delay: (rank * 80).ms)
        .slideY(begin: .15, end: 0, curve: Curves.easeOutBack);
  }
}

class _RankBadge extends StatelessWidget {
  final int rank;
  const _RankBadge({required this.rank});

  @override
  Widget build(BuildContext context) {
    final medalColor = switch (rank) {
      1 => AppColors.goldTier,
      2 => AppColors.silver,
      3 => AppColors.bronze,
      _ => null,
    };
    if (medalColor != null) {
      return CircleAvatar(
        radius: 18,
        backgroundColor: medalColor.withValues(alpha: .25),
        child: AppIcon(Icons.emoji_events_rounded, color: medalColor, size: 22),
      );
    }
    return CircleAvatar(
      radius: 18,
      backgroundColor: AppColors.surfaceCream,
      child: Text('$rank',
          style: const TextStyle(
              fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
    );
  }
}
