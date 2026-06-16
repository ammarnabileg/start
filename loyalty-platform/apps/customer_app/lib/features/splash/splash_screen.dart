import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/user_repository.dart';
import '../onboarding/onboarding_screen.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});
  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await Future.delayed(const Duration(milliseconds: 600));
      if (!mounted) return;
      final loggedIn = ref.read(userRepoProvider).currentSession != null;
      if (loggedIn) {
        context.go('/');
        return;
      }
      // غير مسجّل: لو ما شافش شاشات التعريف → افتحها (push وليس go).
      const storage = FlutterSecureStorage();
      final seen = await storage.read(key: 'seen_onboarding');
      if (!mounted) return;
      if (seen == null) {
        Navigator.of(context).push(
          MaterialPageRoute<void>(builder: (_) => const OnboardingScreen()),
        );
      } else {
        context.go('/welcome');
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppColors.heroGradient),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // استبدل بالكتكوت: assets/mascot/wave.png
              Container(
                height: 120,
                width: 120,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .25),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.egg_alt_rounded,
                    size: 64, color: AppColors.onPrimary),
              )
                  .animate()
                  .scale(duration: 500.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
              const SizedBox(height: AppSpacing.xl),
              const Text('Hatchy',
                      style: TextStyle(
                          fontSize: 40,
                          fontWeight: FontWeight.w800,
                          color: AppColors.onPrimary))
                  .animate()
                  .fadeIn(delay: 200.ms, duration: 400.ms)
                  .slideY(begin: .3, end: 0),
            ],
          ),
        ),
      ),
    );
  }
}
