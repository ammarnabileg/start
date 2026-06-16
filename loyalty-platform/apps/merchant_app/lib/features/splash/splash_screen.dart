import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/auth_repository.dart';
import '../auth/welcome_screen.dart';
import '../shell/merchant_shell.dart';

/// 2.1 — Splash. توكن صالح → لوحة التحكم (MerchantShell)، وإلا → شاشة الترحيب.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _decideNext();
  }

  Future<void> _decideNext() async {
    // مهلة بسيطة لعرض شعار Hatchy.
    await Future<void>.delayed(const Duration(milliseconds: 1200));
    if (!mounted) return;

    final loggedIn = ref.read(authRepoProvider).isLoggedIn;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) =>
            loggedIn ? const MerchantShell() : const MerchantWelcomeScreen(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppColors.heroGradient),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.92),
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.08),
                      blurRadius: 24,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                // الأصل النهائي: شعار/كتكوت Hatchy (assets/mascot/wave.png).
                child: const Icon(Icons.storefront_rounded,
                    size: 64, color: AppColors.primaryDark),
              )
                  .animate()
                  .scale(duration: 500.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
              const SizedBox(height: 24),
              const Text(
                'Hatchy',
                style: TextStyle(
                  fontSize: 40,
                  fontWeight: FontWeight.w800,
                  color: AppColors.onPrimary,
                ),
              ).animate().fadeIn(duration: 400.ms, delay: 200.ms).slideY(
                  begin: .2, end: 0, curve: Curves.easeOut),
              const SizedBox(height: 6),
              const Text(
                'برنامج الولاء لمتجرك',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: AppColors.onPrimary,
                ),
              ).animate().fadeIn(duration: 400.ms, delay: 320.ms),
              const SizedBox(height: 36),
              const SizedBox(
                width: 26,
                height: 26,
                child: CircularProgressIndicator(
                  strokeWidth: 2.6,
                  color: AppColors.onPrimary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
