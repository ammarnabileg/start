import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// 1.8 — تمهيد إذن الإشعارات (Priming).
class NotificationsPrimingScreen extends StatelessWidget {
  const NotificationsPrimingScreen({super.key});

  Future<void> _enable(BuildContext context) async {
    // TODO: استدعِ FirebaseMessaging.instance.requestPermission() هنا،
    // ثم سجّل توكن FCM في جدول device_tokens مع push_opt_in = true.
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
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const Spacer(),
              // استبدل بالكتكوت (assets/mascot/*.png).
              const CircleAvatar(
                radius: 72,
                backgroundColor: AppColors.primaryLight,
                child: Icon(Icons.notifications_active_outlined,
                    size: 72, color: AppColors.primaryDark),
              ),
              const SizedBox(height: 32),
              Text(
                'خلّيك على اطّلاع',
                style: theme.textTheme.displayLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              Text(
                'نرسل لك تنبيهات عند حصولك على نقاط أو توفّر مكافأة جديدة.',
                style: theme.textTheme.bodyLarge
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center,
              ),
              const Spacer(),
              PrimaryButton(
                label: 'تفعيل الإشعارات',
                icon: Icons.notifications_active_outlined,
                onPressed: () => _enable(context),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => _later(context),
                child: const Text('ليس الآن'),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}
