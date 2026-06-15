import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import 'primary_button.dart';

/// حالات الشاشة الموحّدة (إجبارية في كل شاشة بتجيب داتا).

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});
  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator(color: AppColors.primary));
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
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // ملاحظة: استبدل الأيقونة برسمة الكتكوت (assets/mascot/empty.png).
            CircleAvatar(
              radius: 44,
              backgroundColor: AppColors.surfaceCream,
              child: Icon(icon, size: 42, color: AppColors.primaryDark),
            ),
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
        ),
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
            const Icon(Icons.error_outline, size: 48, color: AppColors.error),
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
