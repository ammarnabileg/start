import 'package:flutter/material.dart';
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
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            children: [
              const Spacer(flex: 2),
              Container(
                width: 140,
                height: 140,
                decoration: const BoxDecoration(
                  gradient: AppColors.heroGradient,
                  shape: BoxShape.circle,
                ),
                // الأصل النهائي: كتكوت Hatchy يلوّح.
                child: const Icon(Icons.storefront_rounded,
                    size: 72, color: AppColors.onPrimary),
              ),
              const SizedBox(height: 28),
              Text(
                'Hatchy Business',
                style: text.headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w800),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
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
              ),
              const SizedBox(height: 14),
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(56),
                  side: const BorderSide(color: AppColors.primaryDark),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppTheme.pill),
                  ),
                  foregroundColor: AppColors.textPrimary,
                  textStyle: const TextStyle(
                      fontSize: 16, fontWeight: FontWeight.w700),
                ),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const MerchantLoginScreen(),
                  ),
                ),
                child: const Text('تسجيل الدخول'),
              ),
              const Spacer(),
            ],
          ),
        ),
      ),
    );
  }
}
