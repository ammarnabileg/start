import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
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
          final top = list.take(3).toList();
          final rest = list.length > 3 ? list.sublist(3) : <LeaderboardEntry>[];
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(leaderboardProvider(args)),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _Podium(top: top, myId: myId)
                    .animate()
                    .fadeIn(duration: 350.ms)
                    .slideY(begin: .08, end: 0, curve: Curves.easeOut),
                if (rest.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  const SectionHeader(title: 'بقية القائمة'),
                  const SizedBox(height: 4),
                  for (var i = 0; i < rest.length; i++)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _RankRow(
                              entry: rest[i], isMe: rest[i].userId == myId)
                          .animate()
                          .fadeIn(duration: 300.ms, delay: (i * 50).ms)
                          .slideY(begin: .08, end: 0, curve: Curves.easeOut),
                    ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

/// منصّة المتصدّرين (Top 3) — ميداليات وتدرّجات ذهبية.
class _Podium extends StatelessWidget {
  final List<LeaderboardEntry> top;
  final String? myId;
  const _Podium({required this.top, required this.myId});

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

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
            child: second == null
                ? const SizedBox.shrink()
                : _PodiumPillar(
                    entry: second, isMe: second.userId == myId, height: 130)),
        const SizedBox(width: 10),
        Expanded(
            child: first == null
                ? const SizedBox.shrink()
                : _PodiumPillar(
                    entry: first, isMe: first.userId == myId, height: 168)),
        const SizedBox(width: 10),
        Expanded(
            child: third == null
                ? const SizedBox.shrink()
                : _PodiumPillar(
                    entry: third, isMe: third.userId == myId, height: 112)),
      ],
    );
  }
}

class _PodiumPillar extends StatelessWidget {
  final LeaderboardEntry entry;
  final bool isMe;
  final double height;
  const _PodiumPillar(
      {required this.entry, required this.isMe, required this.height});

  Color get _accent => switch (entry.rank) {
        1 => AppColors.goldTier,
        2 => AppColors.silver,
        _ => AppColors.bronze,
      };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isFirst = entry.rank == 1;
    return Column(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        Icon(Icons.workspace_premium_rounded, color: _accent, size: 34),
        const SizedBox(height: 6),
        Text(
          isMe ? '${entry.displayName} (أنت)' : entry.displayName,
          style: theme.textTheme.bodySmall
              ?.copyWith(fontWeight: FontWeight.w700),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 6),
        Container(
          height: height,
          width: double.infinity,
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            gradient: isFirst
                ? AppColors.goldGradient
                : LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      _accent.withValues(alpha: .85),
                      _accent.withValues(alpha: .55),
                    ],
                  ),
            borderRadius:
                const BorderRadius.vertical(top: Radius.circular(AppRadii.lg)),
            border: isMe
                ? Border.all(color: AppColors.primaryDark, width: 2)
                : null,
            boxShadow: const [
              BoxShadow(
                  color: AppColors.shadow, blurRadius: 14, offset: Offset(0, 6)),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('${entry.rank}',
                  style: theme.textTheme.headlineMedium
                      ?.copyWith(color: AppColors.onPrimary)),
              const SizedBox(height: 4),
              Text('${entry.totalPoints}',
                  style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.onPrimary)),
              Text('نقطة',
                  style: TextStyle(
                      fontSize: 11,
                      color: AppColors.onPrimary.withValues(alpha: .8))),
            ],
          ),
        ),
      ],
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
