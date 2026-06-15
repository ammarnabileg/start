import 'package:flutter/material.dart';
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
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const Spacer(),
              // استبدل بالكتكوت (assets/mascot/*.png).
              const CircleAvatar(
                radius: 72,
                backgroundColor: AppColors.primaryLight,
                child: Icon(Icons.location_on_outlined,
                    size: 72, color: AppColors.primaryDark),
              ),
              const SizedBox(height: 32),
              Text(
                'نبّهك وأنت قريب',
                style: theme.textTheme.displayLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              Text(
                'نرسل لك تنبيهًا عندما تكون قريبًا من أحد المتاجر المضافة لديك. '
                'يمكنك إيقاف هذا في أي وقت.',
                style: theme.textTheme.bodyLarge
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center,
              ),
              const Spacer(),
              PrimaryButton(
                label: 'تفعيل',
                icon: Icons.location_on_outlined,
                onPressed: () => _enable(context),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => _later(context),
                child: const Text('لاحقًا'),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}
