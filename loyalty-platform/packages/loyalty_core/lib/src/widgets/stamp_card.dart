import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';

import '../models/stamp_campaign.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'app_card.dart';
import 'app_icon.dart';

/// بطاقة أختام تفاعلية بهوية Hatchy — مدمجة وحيوية:
/// صفّ من الأختام الدائرية، المكتمل بصورة الختم (أو ختم افتراضي بشعار التطبيق)،
/// والخانة الأخيرة هي المكافأة، والخانة التالية تنبض لتشجّع الإكمال.
class StampCard extends StatelessWidget {
  final StampCampaign campaign;
  final double stampSize;

  const StampCard({super.key, required this.campaign, this.stampSize = 46});

  @override
  Widget build(BuildContext context) {
    final c = campaign;
    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (c.bannerImageUrl != null)
            ClipRRect(
              borderRadius:
                  const BorderRadius.vertical(top: Radius.circular(AppRadii.xl)),
              child: CachedNetworkImage(
                imageUrl: c.bannerImageUrl!,
                height: 96,
                fit: BoxFit.cover,
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(c.name,
                          style: const TextStyle(
                              fontWeight: FontWeight.w800, fontSize: 16),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis),
                    ),
                    if (c.completed)
                      _Chip('مكتملة 🎉', AppColors.success)
                    else
                      _Chip('${c.currentStamps}/${c.requiredCount}',
                          AppColors.primaryDark),
                  ],
                ),
                if (c.description != null && c.description!.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(c.description!,
                      style: const TextStyle(
                          color: AppColors.textSecondary, fontSize: 12),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis),
                ],
                const SizedBox(height: AppSpacing.md),
                // الأختام (Wrap مدمج يتسع لأي عدد).
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    for (var i = 0; i < c.requiredCount; i++)
                      _slot(context, i),
                  ],
                ),
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    AppIcon(Icons.redeem_rounded,
                        size: 16, color: AppColors.primaryDark),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                          c.completed
                              ? 'جاهزة للاستلام: ${c.rewardName ?? "مكافأتك"}'
                              : 'باقٍ ${c.remaining} ${c.actionVerb} للحصول على ${c.rewardName ?? "المكافأة"}',
                          style: const TextStyle(
                              fontSize: 12, fontWeight: FontWeight.w600),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis),
                    ),
                    if (c.pointsPerStamp > 0) ...[
                      const SizedBox(width: 8),
                      _Chip('+${c.pointsPerStamp} لكل ختم', AppColors.primaryDark,
                          soft: true),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _slot(BuildContext context, int i) {
    final c = campaign;
    final isReward = i == c.requiredCount - 1;
    final filled = i < c.currentStamps;
    final isNext = i == c.currentStamps && !c.completed;

    Widget circle;
    if (isReward) {
      // خانة المكافأة: صورة المكافأة أو هدية افتراضية بألوان التطبيق.
      circle = _RewardSlot(
          size: stampSize, imageUrl: c.rewardImageUrl, unlocked: c.completed);
    } else if (filled) {
      circle = _StampSlot(size: stampSize, imageUrl: c.stampImageUrl)
          .animate()
          .scale(
              duration: 280.ms,
              curve: Curves.easeOutBack,
              begin: const Offset(.4, .4),
              end: const Offset(1, 1));
    } else {
      circle = _EmptySlot(size: stampSize);
      if (isNext) {
        circle = circle
            .animate(onPlay: (ctrl) => ctrl.repeat(reverse: true))
            .scaleXY(
                begin: 1, end: 1.12, duration: 900.ms, curve: Curves.easeInOut);
      }
    }

    // تاريخ الختم تحت الخانة (لو متاح).
    String? date;
    if (filled && i < c.stampDates.length) {
      final d = c.stampDates[i];
      date = '${d.month}/${d.day}';
    }
    return SizedBox(
      width: stampSize,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          circle,
          if (date != null)
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Text(date,
                  style: const TextStyle(
                      fontSize: 9, color: AppColors.textSecondary)),
            ),
        ],
      ),
    );
  }
}

class _StampSlot extends StatelessWidget {
  final double size;
  final String? imageUrl;
  const _StampSlot({required this.size, this.imageUrl});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: AppColors.goldGradient,
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
              color: AppColors.primary.withValues(alpha: .3),
              blurRadius: 8,
              offset: const Offset(0, 3)),
        ],
      ),
      padding: const EdgeInsets.all(3),
      child: ClipOval(
        child: imageUrl != null
            ? CachedNetworkImage(imageUrl: imageUrl!, fit: BoxFit.cover)
            // ختم افتراضي: شعار التطبيق (Hatchy egg) بالأبيض.
            : Container(
                color: Colors.white.withValues(alpha: .18),
                alignment: Alignment.center,
                child: AppIcon(Icons.egg_alt_rounded,
                    size: size * .5, color: Colors.white),
              ),
      ),
    );
  }
}

class _EmptySlot extends StatelessWidget {
  final double size;
  const _EmptySlot({required this.size});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: AppColors.surfaceCream,
        shape: BoxShape.circle,
        border: Border.all(color: AppColors.divider, width: 2),
      ),
    );
  }
}

class _RewardSlot extends StatelessWidget {
  final double size;
  final String? imageUrl;
  final bool unlocked;
  const _RewardSlot(
      {required this.size, this.imageUrl, required this.unlocked});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: unlocked ? null : AppColors.surface,
        gradient: unlocked ? AppColors.buttonGradient : null,
        shape: BoxShape.circle,
        border: Border.all(
            color: unlocked ? Colors.transparent : AppColors.primary,
            width: 2),
        boxShadow: unlocked
            ? [
                BoxShadow(
                    color: AppColors.primary.withValues(alpha: .45),
                    blurRadius: 12,
                    offset: const Offset(0, 4))
              ]
            : null,
      ),
      padding: const EdgeInsets.all(3),
      child: ClipOval(
        child: imageUrl != null
            ? CachedNetworkImage(imageUrl: imageUrl!, fit: BoxFit.cover)
            : Center(
                child: AppIcon(Icons.card_giftcard_rounded,
                    size: size * .52,
                    color:
                        unlocked ? Colors.white : AppColors.primary),
              ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String text;
  final Color color;
  final bool soft;
  const _Chip(this.text, this.color, {this.soft = false});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: soft ? color.withValues(alpha: .12) : AppColors.surfaceCream,
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(text,
            style: TextStyle(
                fontWeight: FontWeight.w800, fontSize: 12, color: color)),
      );
}
