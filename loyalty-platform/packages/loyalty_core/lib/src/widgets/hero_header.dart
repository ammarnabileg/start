import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// هيدر متدرّج بحواف سفلية دائرية (هوية Hatchy) — يعطي عمقًا للشاشات الرئيسية.
class HeroHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget? bottom;
  final Gradient gradient;

  const HeroHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.bottom,
    this.gradient = AppColors.heroGradient,
  });

  @override
  Widget build(BuildContext context) {
    // لون النص يتكيّف مع سطوع التدرّج: أبيض فوق الخلفيات الداكنة، وداكن فوق الذهبي.
    final fg = _foreground();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
      decoration: BoxDecoration(
        gradient: gradient,
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: .3),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title,
                          style: Theme.of(context)
                              .textTheme
                              .headlineMedium
                              ?.copyWith(
                                  color: fg, fontWeight: FontWeight.w800)),
                      if (subtitle != null) ...[
                        const SizedBox(height: 4),
                        Text(subtitle!,
                            style: TextStyle(
                                color: fg.withValues(alpha: .82))),
                      ],
                    ],
                  ),
                ),
                if (trailing != null) trailing!,
              ],
            ),
            if (bottom != null) ...[const SizedBox(height: 16), bottom!],
          ],
        ),
      ),
    );
  }

  /// يختار لون نص مقروء حسب سطوع تدرّج الخلفية.
  Color _foreground() {
    final g = gradient;
    if (g is LinearGradient && g.colors.isNotEmpty) {
      final avg = g.colors
              .map((c) => c.computeLuminance())
              .reduce((a, b) => a + b) /
          g.colors.length;
      if (avg < 0.4) return Colors.white;
    }
    return AppColors.onPrimary;
  }
}
