import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:loyalty_core/loyalty_core.dart';

import 'login_screen.dart';
import 'register_business_screen.dart';

/// 2.2 — شاشة الترحيب. لوجو + شعار + زرّي تسجيل نشاط جديد / تسجيل الدخول.
class MerchantWelcomeScreen extends StatelessWidget {
  const MerchantWelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xxl),
          child: Column(
            children: [
              const Spacer(flex: 2),
              Container(
                width: 140,
                height: 140,
                decoration: const BoxDecoration(
                  gradient: AppColors.heroGradient,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.shadow,
                      blurRadius: 24,
                      offset: Offset(0, 10),
                    ),
                  ],
                ),
                // الأصل النهائي: كتكوت Hatchy يلوّح.
                child: const Icon(Icons.storefront_rounded,
                    size: 72, color: AppColors.onPrimary),
              )
                  .animate()
                  .scale(duration: 500.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
              const SizedBox(height: AppSpacing.xxl),
              Text(
                'Hatchy Business',
                style: text.headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w800),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                'أدِر برنامج الولاء الخاص بمتجرك',
                style: text.titleMedium
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center,
              ),
              const Spacer(flex: 3),
              PrimaryButton(
                label: 'تسجيل نشاط جديد',
                icon: Icons.add_business_rounded,
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const RegisterBusinessScreen(),
                  ),
                ),
              )
                  .animate()
                  .fadeIn(duration: 400.ms, delay: 200.ms)
                  .slideY(begin: .12, end: 0),
              const SizedBox(height: AppSpacing.md),
              PrimaryButton(
                label: 'تسجيل الدخول',
                variant: AppButtonVariant.ghost,
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const MerchantLoginScreen(),
                  ),
                ),
              )
                  .animate()
                  .fadeIn(duration: 400.ms, delay: 320.ms)
                  .slideY(begin: .12, end: 0),
              const Spacer(),
            ],
          ),
        ),
      ),
    );
  }
}
