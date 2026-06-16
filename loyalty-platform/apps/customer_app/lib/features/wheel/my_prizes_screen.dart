import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'prize_qr_screen.dart';

/// هدايا العميل المكسوبة (status = won).
final myPrizesProvider = FutureProvider.autoDispose<List<UserPrize>>((ref) async {
  final client = Supabase.instance.client;
  final uid = client.auth.currentUser!.id;
  final rows = await client
      .from('user_prizes')
      .select('*, merchants(business_name)')
      .eq('user_id', uid)
      .eq('status', 'won')
      .order('created_at', ascending: false);
  return (rows as List).map((r) {
    final m = r as Map<String, dynamic>;
    final merchant = m['merchants'] as Map<String, dynamic>?;
    return UserPrize.fromJson({
      ...m,
      'merchant_name': merchant?['business_name'],
    });
  }).toList();
});

/// شاشة "هداياي" — قائمة الهدايا المكسوبة القابلة للتفعيل عند الكاشير.
class MyPrizesScreen extends ConsumerWidget {
  const MyPrizesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(myPrizesProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('هداياي'), centerTitle: true),
      body: data.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الهدايا',
          onRetry: () => ref.invalidate(myPrizesProvider),
        ),
        data: (prizes) {
          if (prizes.isEmpty) {
            return const EmptyView(
              icon: Icons.card_giftcard_outlined,
              title: 'لا توجد هدايا بعد',
              message: 'جرّب حظك في عجلة الحظ لتربح هدايا!',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myPrizesProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: prizes.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (_, i) => _PrizeCard(prize: prizes[i])
                  .animate()
                  .fadeIn(duration: 300.ms, delay: (i * 50).ms)
                  .slideY(begin: .06, end: 0, curve: Curves.easeOut),
            ),
          );
        },
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
            child: Icon(_kindIcon, color: AppColors.primaryDark),
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
          const Icon(Icons.chevron_left_rounded),
        ],
      ),
    );
  }
}
