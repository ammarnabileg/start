import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../data/repositories/wheel_repository.dart';

/// شاشة QR متغيّر لتفعيل هدية عند الكاشير.
/// الـ QR يتجدّد كل ثانية (نافذة TOTP-style) ويُلغى عند تحوّل الحالة لـ redeemed.
class PrizeQrScreen extends ConsumerStatefulWidget {
  final UserPrize prize;
  const PrizeQrScreen({super.key, required this.prize});

  @override
  ConsumerState<PrizeQrScreen> createState() => _PrizeQrScreenState();
}

class _PrizeQrScreenState extends ConsumerState<PrizeQrScreen> {
  Timer? _timer;
  String _payload = '';
  int _remaining = QrToken.defaultWindowSeconds;
  bool _redeemed = false;
  StreamSubscription<List<Map<String, dynamic>>>? _statusSub;

  bool get _expired {
    final exp = widget.prize.expiresAt;
    return exp != null && exp.isBefore(DateTime.now());
  }

  @override
  void initState() {
    super.initState();
    _redeemed = widget.prize.isRedeemed;
    _regenerate();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
    _subscribeStatus();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _statusSub?.cancel();
    super.dispose();
  }

  void _subscribeStatus() {
    _statusSub = ref
        .read(wheelRepoProvider)
        .prizeStatusStream(widget.prize.id)
        .listen((rows) {
      if (rows.isEmpty) return;
      final status = rows.first['status'] as String?;
      if (status == 'redeemed' && mounted) {
        setState(() => _redeemed = true);
      }
    });
  }

  void _regenerate() {
    _payload = QrToken.generate(
      widget.prize.id,
      widget.prize.claimSecret,
      version: 'p1',
    );
  }

  void _tick() {
    if (!mounted) return;
    setState(() {
      _regenerate();
      _remaining = QrToken.secondsRemaining();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(widget.prize.title), centerTitle: true),
      body: SafeArea(
        child: _redeemed
            ? _RedeemedState(prize: widget.prize)
            : _expired
                ? const Center(
                    child: EmptyView(
                      icon: Icons.timer_off_outlined,
                      title: 'انتهت صلاحية الهدية',
                      message: 'لم تعُد هذه الهدية قابلة للتفعيل.',
                    ),
                  )
                : LayoutBuilder(
                    builder: (context, constraints) => SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: ConstrainedBox(
                        constraints:
                            BoxConstraints(minHeight: constraints.maxHeight - 48),
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
                                  size: context.cappedSize(220),
                                  backgroundColor: Colors.white,
                                ),
                              ),
                              const SizedBox(height: 20),
                              Text(widget.prize.title,
                                  style: theme.textTheme.titleMedium,
                                  textAlign: TextAlign.center),
                              const SizedBox(height: 8),
                              Text('اطلب من الكاشير تفعيل الهدية',
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                      color: AppColors.textSecondary),
                                  textAlign: TextAlign.center),
                              const SizedBox(height: 16),
                              _CountdownRing(remaining: _remaining),
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
                            borderRadius: BorderRadius.circular(AppRadii.pill),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const AppIcon(Icons.lock_outline_rounded,
                                  size: 16, color: AppColors.textSecondary),
                              const SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                  'يتجدّد الرمز تلقائيًا لحماية هديتك',
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodySmall,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                        ),
                      ),
                    ),
                  ),
      ),
    );
  }
}

class _RedeemedState extends StatelessWidget {
  final UserPrize prize;
  const _RedeemedState({required this.prize});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 96,
              width: 96,
              decoration: const BoxDecoration(
                  color: AppColors.successBg, shape: BoxShape.circle),
              child: const AppIcon(Icons.check_rounded,
                  size: 52, color: AppColors.success),
            ).animate().scale(
                duration: 420.ms, curve: Curves.easeOutBack).fadeIn(),
            const SizedBox(height: 20),
            Text('تم استلام الهدية ✓',
                style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(prize.title,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'تمام',
              expanded: false,
              onPressed: () => Navigator.of(context).pop(),
            ),
          ],
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
