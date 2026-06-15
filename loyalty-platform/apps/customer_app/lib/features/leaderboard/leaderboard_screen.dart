import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// مصدر اللوحة: عامة (كل التطبيق) أو ستور معيّن (فرع/كامل).
class LeaderboardArgs {
  final String? merchantId; // null = اللوحة العامة
  final String? branchId; // null = الستور ككل (لو merchantId موجود)
  final String title;
  const LeaderboardArgs({this.merchantId, this.branchId, required this.title});
}

final leaderboardProvider = FutureProvider.family<List<LeaderboardEntry>, LeaderboardArgs>(
  (ref, args) async {
    final client = Supabase.instance.client;
    final List<dynamic> rows;
    if (args.merchantId == null) {
      rows = await client.rpc('global_leaderboard', params: {'p_limit': 50});
    } else {
      rows = await client.rpc('store_leaderboard', params: {
        'p_merchant': args.merchantId,
        'p_branch': args.branchId,
        'p_limit': 50,
      });
    }
    return rows.map((r) => LeaderboardEntry.fromJson(r)).toList();
  },
);

class LeaderboardScreen extends ConsumerWidget {
  final LeaderboardArgs args;
  const LeaderboardScreen({super.key, required this.args});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(leaderboardProvider(args));
    final myId = Supabase.instance.client.auth.currentUser?.id;
    return Scaffold(
      appBar: AppBar(title: Text(args.title), centerTitle: true),
      body: data.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل لوحة الصدارة',
            onRetry: () => ref.invalidate(leaderboardProvider(args))),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyView(
              icon: Icons.emoji_events_outlined,
              title: 'لا توجد بيانات بعد',
              message: 'كن أول من يتصدّر القائمة بجمع النقاط!',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(leaderboardProvider(args)),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: list.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (_, i) =>
                  _RankRow(entry: list[i], isMe: list[i].userId == myId),
            ),
          );
        },
      ),
    );
  }
}

class _RankRow extends StatelessWidget {
  final LeaderboardEntry entry;
  final bool isMe;
  const _RankRow({required this.entry, required this.isMe});

  Color _medal() => switch (entry.rank) {
        1 => AppColors.goldTier,
        2 => AppColors.silver,
        3 => AppColors.bronze,
        _ => AppColors.surfaceCream,
      };

  @override
  Widget build(BuildContext context) {
    return AppCard(
      color: isMe ? AppColors.primaryLight : null,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: _medal(),
            child: Text('${entry.rank}',
                style: const TextStyle(
                    fontWeight: FontWeight.w800, color: AppColors.onPrimary)),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(isMe ? '${entry.displayName} (أنت)' : entry.displayName,
                style: Theme.of(context).textTheme.titleMedium),
          ),
          PointsBadge(points: entry.totalPoints),
        ],
      ),
    );
  }
}
