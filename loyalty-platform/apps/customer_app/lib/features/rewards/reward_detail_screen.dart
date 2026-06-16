import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'show_to_cashier_screen.dart';

/// تفاصيل المكافأة (Reward Detail) — راجع CUSTOMER_APP.md 1.13.
class RewardDetailScreen extends StatefulWidget {
  final Reward reward;
  final int availablePoints;
  const RewardDetailScreen({
    super.key,
    required this.reward,
    required this.availablePoints,
  });

  @override
  State<RewardDetailScreen> createState() => _RewardDetailScreenState();
}

class _RewardDetailScreenState extends State<RewardDetailScreen> {
  bool _loading = false;

  bool get _affordable =>
      widget.reward.affordableWith(widget.availablePoints) &&
      widget.reward.inStock;

  Future<void> _confirmAndRedeem() async {
    final reward = widget.reward;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تأكيد الاستبدال'),
        content: Text(
            'هل تريد استبدال ${reward.name} مقابل ${reward.pointsCost} نقطة؟'),
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
      final res = await Supabase.instance.client.functions
          .invoke('redeem-reward', body: {'reward_id': reward.id});
      final data = res.data as Map<String, dynamic>?;
      if (data == null || data['error'] != null) {
        _showError(
            (data?['error'] as String?) ?? 'تعذّر بدء الاستبدال، حاول مرة أخرى.');
        return;
      }
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(
        builder: (_) => ShowToCashierScreen(redemption: data),
      ));
    } catch (_) {
      _showError('تعذّر بدء الاستبدال، حاول مرة أخرى.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
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
                        child: const Icon(Icons.card_giftcard,
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
                          const Icon(Icons.account_balance_wallet_outlined,
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
                  label: 'استبدال الآن',
                  icon: Icons.redeem_rounded,
                  loading: _loading,
                  onPressed: _affordable ? _confirmAndRedeem : null,
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
