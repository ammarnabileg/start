import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/paginated_notifier.dart';
import '../../data/repositories/wheel_repository.dart';
import 'prize_qr_screen.dart';

/// هدايا العميل المكسوبة (status = won) — مرقّمة.
final myPrizesProvider = StateNotifierProvider.autoDispose<
    PaginatedNotifier<UserPrize>, PaginatedState<UserPrize>>((ref) {
  final repo = ref.read(wheelRepoProvider);
  return PaginatedNotifier<UserPrize>(
    (offset, limit) => repo.myPrizes(offset: offset, limit: limit),
  );
});

/// شاشة "هداياي" — قائمة الهدايا المكسوبة القابلة للتفعيل عند الكاشير.
class MyPrizesScreen extends ConsumerWidget {
  const MyPrizesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(myPrizesProvider);
    final notifier = ref.read(myPrizesProvider.notifier);
    return Scaffold(
      appBar: AppBar(title: const Text('هداياي'), centerTitle: true),
      body: PaginatedListView<UserPrize>(
        state: state,
        onLoadMore: notifier.loadMore,
        onRefresh: notifier.refresh,
        emptyIcon: Icons.card_giftcard_outlined,
        emptyTitle: 'لا توجد هدايا بعد',
        emptyMessage: 'جرّب حظك في عجلة الحظ لتربح هدايا!',
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (_, prize, i) => _PrizeCard(prize: prize)
            .animate()
            .fadeIn(duration: 300.ms, delay: (i * 50).ms)
            .slideY(begin: .06, end: 0, curve: Curves.easeOut),
      ),
    );
  }
}

class _PrizeCard extends StatelessWidget {
  final UserPrize prize;
  const _PrizeCard({required this.prize});

  IconData get _kindIcon => switch (prize.kind) {
        'reward' => Icons.card_giftcard_rounded,
        'coupon' => Icons.confirmation_number_rounded,
        'points' => Icons.star_rounded,
        _ => Icons.redeem_rounded,
      };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final expires = prize.expiresAt;
    return AppCard(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => PrizeQrScreen(prize: prize),
      )),
      child: Row(
        children: [
          CircleAvatar(
            radius: 24,
            backgroundColor: AppColors.primaryLight,
            child: AppIcon(_kindIcon, color: AppColors.primaryDark),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(prize.title,
                    style: theme.textTheme.titleMedium,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
                if (prize.merchantName != null) ...[
                  const SizedBox(height: 2),
                  Text(prize.merchantName!,
                      style: theme.textTheme.bodySmall
                          ?.copyWith(color: AppColors.textSecondary)),
                ],
                if (expires != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    'ينتهي: ${DateFormat('yyyy/MM/dd').format(expires.toLocal())}',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: AppColors.textSecondary),
                  ),
                ],
              ],
            ),
          ),
          const AppIcon(Icons.chevron_left_rounded),
        ],
      ),
    );
  }
}
