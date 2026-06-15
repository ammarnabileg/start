import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../auth/welcome_screen.dart';

/// شاشات التعريف (Onboarding) — تظهر أول مرة فقط (راجع CUSTOMER_APP.md 1.2).
class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingPage {
  final String title;
  final String line;
  final IconData icon;
  const _OnboardingPage(
      {required this.title, required this.line, required this.icon});
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final _controller = PageController();
  int _index = 0;

  static const _pages = <_OnboardingPage>[
    _OnboardingPage(
      title: 'كل برامج الولاء في مكان واحد',
      line: 'اجمع نقاطك ومكافآتك من كل المحلات اللي تزورها.',
      icon: Icons.workspace_premium_outlined,
    ),
    _OnboardingPage(
      title: 'كودك هو هويتك',
      line: 'أرِ المتجر رمز الـ QR الخاص بك، وابدأ في جمع النقاط.',
      icon: Icons.qr_code_2_rounded,
    ),
    _OnboardingPage(
      title: 'كل ما زرت أكثر، ربحت أكثر',
      line: 'نقاط، مكافآت، ومستويات تكبر معك.',
      icon: Icons.trending_up_rounded,
    ),
  ];

  bool get _isLast => _index == _pages.length - 1;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    // تخزين علامة إنه شاف شاشات التعريف عشان ما تتكررش.
    const storage = FlutterSecureStorage();
    await storage.write(key: 'seen_onboarding', value: '1');
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const WelcomeScreen()),
    );
  }

  void _next() {
    if (_isLast) {
      _finish();
    } else {
      _controller.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // زرار "تخطّي" فوق.
            Align(
              alignment: AlignmentDirectional.topEnd,
              child: Padding(
                padding: const EdgeInsets.only(top: 8, left: 8, right: 8),
                child: TextButton(
                  onPressed: _finish,
                  child: const Text('تخطّي'),
                ),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: _pages.length,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (_, i) => _OnboardingPageView(page: _pages[i]),
              ),
            ),
            _Dots(count: _pages.length, index: _index),
            const SizedBox(height: AppSpacing.xxl),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.xxl, 0, AppSpacing.xxl, AppSpacing.xxl),
              child: PrimaryButton(
                label: _isLast ? 'ابدأ الآن' : 'التالي',
                icon: _isLast ? Icons.rocket_launch_rounded : null,
                onPressed: _next,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OnboardingPageView extends StatelessWidget {
  final _OnboardingPage page;
  const _OnboardingPageView({required this.page});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xxxl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // استبدل برسمة الكتكوت المعبّرة (assets/mascot/*.png).
          Container(
            height: 156,
            width: 156,
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
            child: Icon(page.icon, size: 76, color: AppColors.onPrimary),
          )
              .animate()
              .scale(duration: 460.ms, curve: Curves.easeOutBack)
              .fadeIn(),
          const SizedBox(height: AppSpacing.xxxl),
          Text(
            page.title,
            style: Theme.of(context).textTheme.headlineMedium,
            textAlign: TextAlign.center,
          ).animate().fadeIn(delay: 120.ms, duration: 360.ms).slideY(
              begin: .12, end: 0),
          const SizedBox(height: AppSpacing.lg),
          Text(
            page.line,
            style: Theme.of(context).textTheme.bodyLarge,
            textAlign: TextAlign.center,
          ).animate().fadeIn(delay: 220.ms, duration: 360.ms),
        ],
      ),
    );
  }
}

class _Dots extends StatelessWidget {
  final int count;
  final int index;
  const _Dots({required this.count, required this.index});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(count, (i) {
        final active = i == index;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 250),
          margin: const EdgeInsets.symmetric(horizontal: 4),
          height: 8,
          width: active ? 24 : 8,
          decoration: BoxDecoration(
            color: active ? AppColors.primary : AppColors.divider,
            borderRadius: BorderRadius.circular(8),
          ),
        );
      }),
    );
  }
}
