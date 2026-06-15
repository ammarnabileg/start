import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:loyalty_core/loyalty_core.dart';

import 'login_screen.dart';
import 'register_screen.dart';

/// شاشة الترحيب (Auth Hub). شاشات التسجيل/الدخول/OTP تُبنى تحت features/auth/.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: Column(
            children: [
              const Spacer(),
              // استبدل بالكتكوت يلوّح
              Container(
                height: 140,
                width: 140,
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
                child: const Icon(Icons.egg_alt_rounded,
                    size: 72, color: AppColors.onPrimary),
              )
                  .animate()
                  .scale(duration: 500.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
              const SizedBox(height: AppSpacing.xxl),
              Text('أهلًا بك',
                      style: Theme.of(context).textTheme.displayLarge,
                      textAlign: TextAlign.center)
                  .animate()
                  .fadeIn(delay: 150.ms, duration: 400.ms)
                  .slideY(begin: .2, end: 0),
              const SizedBox(height: AppSpacing.sm),
              Text('كل برامج الولاء في مكان واحد',
                      style: Theme.of(context).textTheme.bodyLarge,
                      textAlign: TextAlign.center)
                  .animate()
                  .fadeIn(delay: 250.ms, duration: 400.ms),
              const Spacer(),
              PrimaryButton(
                label: 'إنشاء حساب جديد',
                onPressed: () {
                  Navigator.of(context).push(MaterialPageRoute<void>(
                    builder: (_) => const RegisterScreen(),
                  ));
                },
              ).animate().fadeIn(delay: 350.ms).slideY(begin: .3, end: 0),
              const SizedBox(height: AppSpacing.md),
              PrimaryButton(
                label: 'تسجيل الدخول',
                variant: AppButtonVariant.ghost,
                onPressed: () {
                  Navigator.of(context).push(MaterialPageRoute<void>(
                    builder: (_) => const LoginScreen(),
                  ));
                },
              ).animate().fadeIn(delay: 450.ms).slideY(begin: .3, end: 0),
              const SizedBox(height: AppSpacing.sm),
            ],
          ),
        ),
      ),
    );
  }
}
