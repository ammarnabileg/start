import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// 1.9 — تمهيد إذن الموقع (لإشعار القرب).
/// يُفتح لاحقًا (ليس ضمن تدفّق التسجيل الأساسي) — يُفضّل بعد إضافة أول متجر.
class LocationPrimingScreen extends StatelessWidget {
  const LocationPrimingScreen({super.key});

  Future<void> _enable(BuildContext context) async {
    // TODO: اطلب إذن الموقع (Always) عبر geofence_service / النظام،
    // ثم اضبط proximity_opt_in = true لحساب المستخدم.
    if (!context.mounted) return;
    Navigator.of(context).maybePop();
  }

  void _later(BuildContext context) {
    Navigator.of(context).maybePop();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(centerTitle: true),
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
                child: const Icon(Icons.location_on_rounded,
                    size: 72, color: AppColors.onPrimary),
              )
                  .animate()
                  .scale(duration: 500.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
              const SizedBox(height: AppSpacing.xxxl),
              Text(
                'نبّهك وأنت قريب',
                style: theme.textTheme.displayLarge,
                textAlign: TextAlign.center,
              ).animate().fadeIn(delay: 150.ms, duration: 400.ms).slideY(
                  begin: .15, end: 0),
              const SizedBox(height: AppSpacing.lg),
              Text(
                'نرسل لك تنبيهًا عندما تكون قريبًا من أحد المتاجر المضافة لديك. '
                'يمكنك إيقاف هذا في أي وقت.',
                style: theme.textTheme.bodyLarge
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center,
              ).animate().fadeIn(delay: 250.ms, duration: 400.ms),
              const Spacer(),
              PrimaryButton(
                label: 'تفعيل',
                icon: Icons.location_on_rounded,
                onPressed: () => _enable(context),
              ).animate().fadeIn(delay: 350.ms).slideY(begin: .3, end: 0),
              const SizedBox(height: AppSpacing.md),
              PrimaryButton(
                label: 'لاحقًا',
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
