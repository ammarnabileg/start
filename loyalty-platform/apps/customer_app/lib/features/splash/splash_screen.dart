import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../onboarding/onboarding_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await Future.delayed(const Duration(milliseconds: 600));
      if (!mounted) return;
      final loggedIn = Supabase.instance.client.auth.currentSession != null;
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
    return const Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(gradient: AppColors.heroGradient),
        child: Center(
          // استبدل بالكتكوت: assets/mascot/wave.png
          child: Text('Hatchy',
              style: TextStyle(
                  fontSize: 40,
                  fontWeight: FontWeight.w800,
                  color: AppColors.onPrimary)),
        ),
      ),
    );
  }
}
