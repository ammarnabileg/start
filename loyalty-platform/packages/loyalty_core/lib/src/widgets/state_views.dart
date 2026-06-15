import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'primary_button.dart';

/// حالات الشاشة الموحّدة (إجبارية في كل شاشة بتجيب داتا).

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});
  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator(color: AppColors.primary));
}

/// عنصر هيكلي (skeleton) بتأثير لمعان — أفضل من الـ spinner للقوائم.
class Skeleton extends StatelessWidget {
  final double height;
  final double? width;
  final double radius;
  const Skeleton(
      {super.key, this.height = 16, this.width, this.radius = AppRadii.sm});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      decoration: BoxDecoration(
        color: AppColors.surfaceCream,
        borderRadius: BorderRadius.circular(radius),
      ),
    )
        .animate(onPlay: (c) => c.repeat())
        .shimmer(
            duration: 1100.ms,
            color: Colors.white.withValues(alpha: .6));
  }
}

/// قائمة كروت هيكلية أثناء التحميل.
class SkeletonList extends StatelessWidget {
  final int count;
  const SkeletonList({super.key, this.count = 6});
  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: AppSpacing.screen,
      itemCount: count,
      separatorBuilder: (_, __) => AppSpacing.gapMd,
      itemBuilder: (_, __) => Container(
        padding: AppSpacing.card,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.xl),
          boxShadow: const [
            BoxShadow(color: AppColors.shadowSoft, blurRadius: 16, offset: Offset(0, 6)),
          ],
        ),
        child: Row(
          children: [
            const Skeleton(height: 52, width: 52, radius: AppRadii.md),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: const [
                  Skeleton(height: 14, width: 140),
                  SizedBox(height: 10),
                  Skeleton(height: 12, width: 90),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class EmptyView extends StatelessWidget {
  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final IconData icon;

  const EmptyView({
    super.key,
    required this.title,
    this.message,
    this.actionLabel,
    this.onAction,
    this.icon = Icons.inbox_outlined,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // ملاحظة: استبدل الأيقونة برسمة الكتكوت (assets/mascot/empty.png).
            Container(
              height: 96,
              width: 96,
              decoration: const BoxDecoration(
                  gradient: AppColors.goldGradient, shape: BoxShape.circle),
              child: Icon(icon, size: 44, color: AppColors.onPrimary),
            )
                .animate()
                .scale(duration: 400.ms, curve: Curves.easeOutBack)
                .fadeIn(),
            const SizedBox(height: 20),
            Text(title,
                style: Theme.of(context).textTheme.titleLarge,
                textAlign: TextAlign.center),
            if (message != null) ...[
              const SizedBox(height: 8),
              Text(message!,
                  style: Theme.of(context).textTheme.bodyMedium,
                  textAlign: TextAlign.center),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 24),
              PrimaryButton(
                  label: actionLabel!, onPressed: onAction, expanded: false),
            ],
          ],
        ).animate().fadeIn(duration: 300.ms).slideY(begin: .06, end: 0),
      ),
    );
  }
}

class ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;

  const ErrorView({super.key, required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 80,
              width: 80,
              decoration: const BoxDecoration(
                  color: AppColors.errorBg, shape: BoxShape.circle),
              child: const Icon(Icons.cloud_off_rounded,
                  size: 40, color: AppColors.error),
            ),
            const SizedBox(height: 16),
            Text(message,
                style: Theme.of(context).textTheme.bodyLarge,
                textAlign: TextAlign.center),
            if (onRetry != null) ...[
              const SizedBox(height: 20),
              PrimaryButton(
                  label: 'إعادة المحاولة',
                  onPressed: onRetry,
                  icon: Icons.refresh,
                  expanded: false),
            ],
          ],
        ),
      ),
    );
  }
}

/// بار علوي للأوفلاين.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: AppColors.warning,
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: const Text('لا يوجد اتصال بالإنترنت',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
    );
  }
}
