import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/rewards_repository.dart';
import '../wheel/my_prizes_screen.dart';

/// تفاصيل المكافأة (Reward Detail) — راجع CUSTOMER_APP.md 1.13.
class RewardDetailScreen extends ConsumerStatefulWidget {
  final Reward reward;
  final int availablePoints;
  const RewardDetailScreen({
    super.key,
    required this.reward,
    required this.availablePoints,
  });

  @override
  ConsumerState<RewardDetailScreen> createState() =>
      _RewardDetailScreenState();
}

class _RewardDetailScreenState extends ConsumerState<RewardDetailScreen> {
  bool _loading = false;

  bool get _affordable =>
      widget.reward.affordableWith(widget.availablePoints) &&
      widget.reward.inStock;

  Future<void> _confirmAndBuy() async {
    final reward = widget.reward;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تأكيد الشراء'),
        content: Text(
            'سيتم خصم ${reward.pointsCost} نقطة وإضافة «${reward.name}» إلى '
            '«هداياي»، تستلمها من الكاشير وقت ما تحب.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('إلغاء'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('تأكيد'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _loading = true);
    try {
      await ref
          .read(rewardsRepoProvider)
          .buyReward(reward.id, idempotencyKey: genIdempotencyKey());
      if (!mounted) return;
      AppFeedback.toast(context, 'تمت إضافة «${reward.name}» إلى هداياي');
      // ينتقل إلى "هداياي" حيث زر الاستبدال — الهدية أصبحت مملوكة.
      Navigator.of(context).pushReplacement(MaterialPageRoute(
        builder: (_) => const MyPrizesScreen(),
      ));
    } catch (e) {
      _showError(_friendly(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _friendly(Object e) {
    final m = e.toString();
    if (m.contains('نقاط')) return 'نقاطك غير كافية';
    if (m.contains('الكمية')) return 'نفدت الكمية';
    return 'تعذّر إتمام الشراء، حاول مرة أخرى.';
  }

  void _showError(String message) {
    if (!mounted) return;
    AppFeedback.toast(context, message, error: true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final reward = widget.reward;
    final missing = reward.pointsCost - widget.availablePoints;
    return Scaffold(
      appBar: AppBar(title: const Text('تفاصيل المكافأة'), centerTitle: true),
      body: ListView(
        children: [
          Stack(
            children: [
              AspectRatio(
                aspectRatio: 16 / 10,
                child: reward.imageUrl != null
                    ? CachedNetworkImage(
                        imageUrl: reward.imageUrl!, fit: BoxFit.cover)
                    : Container(
                        color: AppColors.surfaceCream,
                        child: const AppIcon(Icons.card_giftcard,
                            size: 64, color: AppColors.primaryDark),
                      ),
              ),
              // تدرّج سفلي ليبرز السعر فوق الصورة.
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.bottomCenter,
                      end: Alignment.center,
                      colors: [
                        AppColors.scrim,
                        AppColors.scrim.withValues(alpha: 0),
                      ],
                    ),
                  ),
                ),
              ),
              PositionedDirectional(
                start: 20,
                bottom: 16,
                child: PointsBadge(points: reward.pointsCost),
              ),
              if (!reward.inStock)
                PositionedDirectional(
                  top: 16,
                  end: 16,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: AppColors.error,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Text('نفدت الكمية',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w700)),
                  ),
                ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: ResponsiveCenter(
              child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(reward.name, style: theme.textTheme.headlineSmall),
                if (reward.description != null) ...[
                  const SizedBox(height: 12),
                  Text(reward.description!, style: theme.textTheme.bodyMedium),
                ],
                const SizedBox(height: 20),
                AppCard(
                  color: AppColors.surfaceCream,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const AppIcon(Icons.account_balance_wallet_outlined,
                              color: AppColors.primaryDark, size: 22),
                          const SizedBox(width: 8),
                          Text('رصيدك', style: theme.textTheme.titleMedium),
                        ],
                      ),
                      Text('${widget.availablePoints} نقطة',
                          style: theme.textTheme.titleMedium
                              ?.copyWith(color: AppColors.primaryDark)),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                if (!reward.inStock)
                  Text('نفدت الكمية',
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(color: AppColors.error))
                else if (!reward.affordableWith(widget.availablePoints))
                  Text(
                    'تحتاج $missing نقطة إضافية',
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(color: AppColors.textSecondary),
                  ),
                const SizedBox(height: 20),
                PrimaryButton(
                  label: 'احصل عليها بـ ${reward.pointsCost} نقطة',
                  icon: Icons.redeem_rounded,
                  loading: _loading,
                  onPressed: _affordable ? _confirmAndBuy : null,
                ),
              ],
            ),
            ),
          ),
        ],
      ),
    );
  }
}
