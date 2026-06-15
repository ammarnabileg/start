import 'dart:async';
import 'package:flutter/material.dart';
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
  int _remaining = QrToken.defaultWindowSeconds;
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
    setState(() {
      _payload = QrToken.generate(user.id, user.qrSecret);
      _remaining = QrToken.secondsRemaining();
    });
  }

  @override
  Widget build(BuildContext context) {
    final userAsync = ref.watch(currentUserProvider);
    return Scaffold(
      body: SafeArea(
        child: userAsync.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(
              message: 'تعذّر تحميل بياناتك',
              onRetry: () => ref.invalidate(currentUserProvider)),
          data: (user) {
            // أول توليد فوري (من غير ما نستنى أول tick)
            if (_payload.isEmpty) {
              _payload = QrToken.generate(user.id, user.qrSecret);
            }
            return Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  const SizedBox(height: 12),
                  Text('أرِ هذا الرمز للكاشير',
                      style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 28),
                  AppCard(
                    padding: const EdgeInsets.all(28),
                    child: Column(
                      children: [
                        QrImageView(
                          data: _payload,
                          version: QrVersions.auto,
                          size: 240,
                          backgroundColor: Colors.white,
                        ),
                        const SizedBox(height: 20),
                        Text(user.name,
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 4),
                        Text('عضوية: ${user.id.substring(0, 8).toUpperCase()}',
                            style: Theme.of(context).textTheme.bodySmall),
                        const SizedBox(height: 18),
                        _CountdownRing(remaining: _remaining),
                      ],
                    ),
                  ),
                  const Spacer(),
                  Text('يتجدّد الرمز تلقائيًا للحفاظ على أمان حسابك',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 12),
                ],
              ),
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
