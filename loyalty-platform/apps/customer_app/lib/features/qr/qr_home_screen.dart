import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:screen_brightness/screen_brightness.dart';

import 'qr_providers.dart';

/// الرئيسية / My QR — أهم شاشة. الـ QR متغيّر، يعلّي الإضاءة، ويشتغل أوفلاين.
class QrHomeScreen extends ConsumerStatefulWidget {
  const QrHomeScreen({super.key});
  @override
  ConsumerState<QrHomeScreen> createState() => _QrHomeScreenState();
}

class _QrHomeScreenState extends ConsumerState<QrHomeScreen>
    with WidgetsBindingObserver {
  Timer? _timer;
  String _payload = '';
  int _window = -1;
  // العدّاد يتحدّث كل ثانية عبر notifier — يعيد بناء الحلقة فقط، مش الـ QR.
  final ValueNotifier<int> _remaining =
      ValueNotifier<int>(QrToken.defaultWindowSeconds);
  double? _prevBrightness;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _boostBrightness();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  @override
  void dispose() {
    _timer?.cancel();
    _remaining.dispose();
    _restoreBrightness();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _boostBrightness();
    if (state == AppLifecycleState.paused) _restoreBrightness();
  }

  Future<void> _boostBrightness() async {
    try {
      _prevBrightness ??= await ScreenBrightness().current;
      await ScreenBrightness().setScreenBrightness(1.0);
    } catch (_) {}
  }

  Future<void> _restoreBrightness() async {
    try {
      if (_prevBrightness != null) {
        await ScreenBrightness().setScreenBrightness(_prevBrightness!);
      }
    } catch (_) {}
  }

  void _tick() {
    final user = ref.read(currentUserProvider).valueOrNull;
    if (user == null) return;
    final secs = DateTime.now().toUtc().millisecondsSinceEpoch ~/ 1000;
    final window = secs ~/ QrToken.defaultWindowSeconds;
    // نعيد توليد الـ QR (ونعيد بناء الكارت) فقط عند تغيّر النافذة الزمنية.
    if (window != _window) {
      _window = window;
      setState(() => _payload = QrToken.generate(user.id, user.qrSecret));
    }
    _remaining.value = QrToken.secondsRemaining();
  }

  @override
  Widget build(BuildContext context) {
    final userAsync = ref.watch(currentUserProvider);
    return Scaffold(
      body: SafeArea(
        top: false,
        child: userAsync.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(
              message: 'تعذّر تحميل بياناتك',
              onRetry: () => ref.invalidate(currentUserProvider)),
          data: (user) {
            // أول توليد فوري (من غير ما نستنى أول tick)
            if (_payload.isEmpty) {
              final secs = DateTime.now().toUtc().millisecondsSinceEpoch ~/ 1000;
              _window = secs ~/ QrToken.defaultWindowSeconds;
              _payload = QrToken.generate(user.id, user.qrSecret);
            }
            final firstName = user.name.split(' ').first;
            return Column(
              children: [
                HeroHeader(
                  title: 'أهلاً، $firstName',
                  subtitle: 'أرِ هذا الرمز للكاشير عند الدفع',
                  trailing: Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: AppColors.onPrimary.withValues(alpha: .12),
                      borderRadius: BorderRadius.circular(AppRadii.sm),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text('عضوية',
                            style: TextStyle(
                                color: AppColors.onPrimary, fontSize: 11)),
                        const SizedBox(height: 2),
                        Text(user.id.substring(0, 8).toUpperCase(),
                            style: const TextStyle(
                                color: AppColors.onPrimary,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1)),
                      ],
                    ),
                  ),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
                    child: Column(
                      children: [
                        const Spacer(),
                        AppCard(
                          padding: const EdgeInsets.all(28),
                          child: Column(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius:
                                      BorderRadius.circular(AppRadii.md),
                                  boxShadow: const [
                                    BoxShadow(
                                        color: AppColors.shadowSoft,
                                        blurRadius: 12,
                                        offset: Offset(0, 4)),
                                  ],
                                ),
                                child: QrImageView(
                                  data: _payload,
                                  version: QrVersions.auto,
                                  size: 230,
                                  backgroundColor: Colors.white,
                                ),
                              ),
                              const SizedBox(height: 20),
                              Text(user.name,
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleMedium),
                              const SizedBox(height: 16),
                              ValueListenableBuilder<int>(
                                valueListenable: _remaining,
                                builder: (_, value, __) =>
                                    _CountdownRing(remaining: value),
                              ),
                            ],
                          ),
                        )
                            .animate()
                            .fadeIn(duration: 400.ms)
                            .scale(
                                begin: const Offset(.96, .96),
                                end: const Offset(1, 1),
                                curve: Curves.easeOutBack),
                        const Spacer(),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 10),
                          decoration: BoxDecoration(
                            color: AppColors.surfaceCream,
                            borderRadius:
                                BorderRadius.circular(AppRadii.pill),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.lock_outline_rounded,
                                  size: 16, color: AppColors.textSecondary),
                              const SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                    'يتجدّد الرمز تلقائيًا للحفاظ على أمان حسابك',
                                    textAlign: TextAlign.center,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodySmall),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _CountdownRing extends StatelessWidget {
  final int remaining;
  const _CountdownRing({required this.remaining});
  @override
  Widget build(BuildContext context) {
    final progress = remaining / QrToken.defaultWindowSeconds;
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        SizedBox(
          height: 22,
          width: 22,
          child: CircularProgressIndicator(
            value: progress,
            strokeWidth: 3,
            backgroundColor: AppColors.surfaceCream,
            color: AppColors.primary,
          ),
        ),
        const SizedBox(width: 8),
        Text('يتجدّد خلال $remaining ث',
            style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}
