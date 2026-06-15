import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// شاشة الترحيب (Auth Hub). شاشات التسجيل/الدخول/OTP تُبنى تحت features/auth/.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const Spacer(),
              // استبدل بالكتكوت يلوّح
              const CircleAvatar(
                  radius: 56,
                  backgroundColor: AppColors.primaryLight,
                  child: Icon(Icons.egg_alt_rounded,
                      size: 56, color: AppColors.primaryDark)),
              const SizedBox(height: 24),
              Text('أهلًا بك',
                  style: Theme.of(context).textTheme.displayLarge),
              const SizedBox(height: 8),
              Text('كل برامج الولاء في مكان واحد',
                  style: Theme.of(context).textTheme.bodyLarge),
              const Spacer(),
              PrimaryButton(
                label: 'إنشاء حساب جديد',
                onPressed: () {/* TODO: → RegisterScreen */},
              ),
              const SizedBox(height: 12),
              TextButton(
                  onPressed: () {/* TODO: → LoginScreen */},
                  child: const Text('تسجيل الدخول')),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}
