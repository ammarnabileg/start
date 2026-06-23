import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../theme/app_colors.dart';
import 'primary_button.dart';
import 'app_icon.dart';

/// تغذية راجعة موحّدة للنجاح/الخطأ (toast + شيت نجاح بحركة لطيفة).
class AppFeedback {
  AppFeedback._();

  /// Toast بسيط (snackbar مُنسّق من الثيم).
  static void toast(BuildContext context, String message, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: Row(children: [
          AppIcon(error ? Icons.error_outline : Icons.check_circle_outline,
              color: error ? AppColors.error : AppColors.success, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(message)),
        ]),
      ));
  }

  /// شيت نجاح كبير بعلامة صح متحرّكة (للحظات المهمة: استبدال/مكافأة/تسجيل).
  static Future<void> success(
    BuildContext context, {
    required String title,
    String? message,
    String actionLabel = 'تمام',
  }) {
    HapticFeedback.mediumImpact();
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 12, 24, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 84,
              width: 84,
              decoration: const BoxDecoration(
                  color: AppColors.successBg, shape: BoxShape.circle),
              child: AppIcon(Icons.check_rounded,
                  size: 48, color: AppColors.success),
            ).animate().scale(
                duration: 420.ms, curve: Curves.easeOutBack).fadeIn(),
            const SizedBox(height: 18),
            Text(title,
                style: Theme.of(ctx).textTheme.titleLarge,
                textAlign: TextAlign.center),
            if (message != null) ...[
              const SizedBox(height: 8),
              Text(message,
                  style: Theme.of(ctx).textTheme.bodyMedium,
                  textAlign: TextAlign.center),
            ],
            const SizedBox(height: 22),
            PrimaryButton(
                label: actionLabel, onPressed: () => Navigator.pop(ctx)),
          ],
        ),
      ),
    );
  }
}
