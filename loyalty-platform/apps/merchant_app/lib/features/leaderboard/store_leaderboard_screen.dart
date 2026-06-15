import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';

/// مبدّل العرض: الفرع الحالي أو كل الفروع.
final leaderboardScopeProvider = StateProvider<bool>((ref) => true); // true = كل الفروع

final storeLeaderboardProvider =
    FutureProvider.autoDispose<List<LeaderboardEntry>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final allBranches = ref.watch(leaderboardScopeProvider);
  final pBranch = allBranches ? null : staff.branchId;

  final rows = await Supabase.instance.client.rpc('store_leaderboard', params: {
    'p_merchant': staff.merchantId,
    'p_branch': pBranch,
    'p_limit': 50,
  });
  return List<Map<String, dynamic>>.from(rows as List)
      .map(LeaderboardEntry.fromJson)
      .toList();
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
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text(
                'نطاق النقاط "مشترك" — قد يكون عرض الفرع فارغًا، الأنسب "كل الفروع".',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          Expanded(
            child: async.when(
              loading: () => const LoadingView(),
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
                return ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: entries.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, i) {
                    final e = entries[i];
                    return AppCard(
                      child: Row(
                        children: [
                          _RankBadge(rank: e.rank),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Text(e.displayName,
                                style:
                                    Theme.of(context).textTheme.titleMedium),
                          ),
                          PointsBadge(points: e.totalPoints),
                        ],
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
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
        child: Icon(Icons.emoji_events_rounded, color: medalColor, size: 22),
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
