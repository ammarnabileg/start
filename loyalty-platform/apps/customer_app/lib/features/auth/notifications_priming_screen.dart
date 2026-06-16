import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/push_service.dart';

/// 1.8 — تمهيد إذن الإشعارات (Priming).
class NotificationsPrimingScreen extends StatelessWidget {
  const NotificationsPrimingScreen({super.key});

  Future<void> _enable(BuildContext context) async {
    // طلب إذن الإشعارات وتسجيل توكن FCM في جدول device_tokens (أفضل جهد).
    await PushService.registerForUser();
    if (!context.mounted) return;
    context.go('/');
  }

  void _later(BuildContext context) {
    context.go('/');
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: Column(
            children: [
              const Spacer(),
              // استبدل بالكتكوت (assets/mascot/*.png).
              Container(
                height: 144,
                width: 144,
                decoration: const BoxDecoration(
                  gradient: AppColors.goldGradient,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                        color: AppColors.shadow,
                        blurRadius: 24,
                        offset: Offset(0, 10)),
                  ],
                ),
                child: const AppIcon(Icons.notifications_active_rounded,
                    size: 72, color: AppColors.onPrimary),
              )
                  .animate()
                  .scale(duration: 500.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
              const SizedBox(height: AppSpacing.xxxl),
              Text(
                'خلّيك على اطّلاع',
                style: theme.textTheme.displayLarge,
                textAlign: TextAlign.center,
              ).animate().fadeIn(delay: 150.ms, duration: 400.ms).slideY(
                  begin: .15, end: 0),
              const SizedBox(height: AppSpacing.lg),
              Text(
                'نرسل لك تنبيهات عند حصولك على نقاط أو توفّر مكافأة جديدة.',
                style: theme.textTheme.bodyLarge
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center,
              ).animate().fadeIn(delay: 250.ms, duration: 400.ms),
              const Spacer(),
              PrimaryButton(
                label: 'تفعيل الإشعارات',
                icon: Icons.notifications_active_rounded,
                onPressed: () => _enable(context),
              ).animate().fadeIn(delay: 350.ms).slideY(begin: .3, end: 0),
              const SizedBox(height: AppSpacing.md),
              PrimaryButton(
                label: 'ليس الآن',
                variant: AppButtonVariant.ghost,
                onPressed: () => _later(context),
              ).animate().fadeIn(delay: 450.ms).slideY(begin: .3, end: 0),
              const SizedBox(height: AppSpacing.sm),
            ],
          ),
        ),
      ),
    );
  }
}
