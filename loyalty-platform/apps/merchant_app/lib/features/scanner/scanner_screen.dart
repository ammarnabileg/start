import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../data/repositories/scan_repository.dart';
import 'customer_profile_screen.dart';

/// الماسح — أكتر شاشة الكاشير هيستخدمها. يمسح كود العميل ويستدعي verify-qr.
class ScannerScreen extends ConsumerStatefulWidget {
  const ScannerScreen({super.key});
  @override
  ConsumerState<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends ConsumerState<ScannerScreen> {
  final _controller = MobileScannerController();
  bool _busy = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy) return;
    final payload = capture.barcodes.firstOrNull?.rawValue;
    if (payload == null) return;
    setState(() => _busy = true);

    try {
      // رمز هدية (يبدأ بـ p1.) → تفعيله عبر redeem-prize.
      if (payload.startsWith('p1.')) {
        final res = await ref
            .read(scanRepoProvider)
            .redeemPrize(payload, idempotencyKey: genIdempotencyKey());
        final data = res.data as Map<String, dynamic>?;
        if (data?['error'] != null) {
          _snack(data!['error'] as String);
          return;
        }
        if (!mounted) return;
        // نعرض للكاشير تفاصيل الهدية بوضوح ليسلّمها (نوعها + اسمها + وصفها).
        await showModalBottomSheet<void>(
          context: context,
          isScrollControlled: true,
          builder: (_) => _PrizeDeliverSheet(
            title: data?['title'] as String? ?? 'الهدية',
            kind: data?['kind'] as String? ?? 'reward',
            description: data?['description'] as String?,
          ),
        );
        return;
      }

      // رمز استلام مكافأة (يبدأ بـ r1.) → تأكيد الاستبدال عبر confirm-redemption.
      if (payload.startsWith('r1.')) {
        final res = await ref
            .read(scanRepoProvider)
            .confirmRedemption(payload.substring(3),
                idempotencyKey: genIdempotencyKey());
        final data = res.data as Map<String, dynamic>?;
        if (data?['error'] != null) {
          _snack(data!['error'] as String);
          return;
        }
        if (!mounted) return;
        await AppFeedback.success(context, title: 'تم تسليم المكافأة');
        return;
      }

      final res = await ref.read(scanRepoProvider).verifyQr(payload);
      if (res.data?['error'] != null) {
        _snack(res.data['error'] as String);
        return;
      }
      if (!mounted) return;
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => CustomerProfileScreen(data: res.data),
      ));
    } catch (e) {
      _snack('تعذّر المسح: تأكد من الاتصال بالإنترنت');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String msg) {
    if (!mounted) return;
    AppFeedback.toast(context, msg, error: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('مسح رمز العميل'),
        actions: [
          IconButton(
              tooltip: 'تشغيل/إطفاء الفلاش',
              onPressed: () => _controller.toggleTorch(),
              icon: const AppIcon(Icons.flash_on)),
        ],
      ),
      body: Stack(
        alignment: Alignment.center,
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          // تعتيم خفيف للخلفية لإبراز إطار المسح.
          const ColoredBox(
            color: Colors.black26,
            child: SizedBox.expand(),
          ),
          // إطار المسح بزوايا واضحة + خط مسح متحرّك.
          SizedBox(
            width: 250,
            height: 250,
            child: Stack(
              children: [
                CustomPaint(
                  size: const Size(250, 250),
                  painter: _ScanFramePainter(),
                ),
                Positioned.fill(
                  child: Align(
                    alignment: Alignment.topCenter,
                    child: Container(
                      margin: const EdgeInsets.symmetric(horizontal: 14),
                      height: 3,
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(2),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withValues(alpha: .6),
                            blurRadius: 8,
                          ),
                        ],
                      ),
                    )
                        .animate(onPlay: (c) => c.repeat(reverse: true))
                        .moveY(
                            begin: 0,
                            end: 240,
                            duration: 1600.ms,
                            curve: Curves.easeInOut),
                  ),
                ),
              ],
            ),
          ),
          Positioned(
            bottom: 72,
            child: Container(
              padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.lg, vertical: AppSpacing.md),
              decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: .6),
                  borderRadius: BorderRadius.circular(AppRadii.pill)),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  AppIcon(Icons.qr_code_2_rounded,
                      color: AppColors.primary, size: 20),
                  SizedBox(width: AppSpacing.sm),
                  Text('وجّه الكاميرا نحو رمز العميل',
                      style: TextStyle(
                          color: Colors.white, fontWeight: FontWeight.w600)),
                ],
              ),
            ),
          ),
          if (_busy)
            const ColoredBox(color: Colors.black54, child: LoadingView()),
        ],
      ),
    );
  }
}

/// يرسم أربع زوايا (أقواس) لإطار المسح بدل المربع الكامل — أوضح وأنظف.
class _ScanFramePainter extends CustomPainter {
  static const double _len = 34; // طول ذراع الزاوية
  static const double _r = 18; // نصف قطر الانحناء

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = AppColors.primary
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;

    final w = size.width;
    final h = size.height;

    // أعلى-يسار
    canvas.drawPath(
      Path()
        ..moveTo(0, _len + _r)
        ..lineTo(0, _r)
        ..arcToPoint(const Offset(_r, 0), radius: const Radius.circular(_r))
        ..lineTo(_len + _r, 0),
      paint,
    );
    // أعلى-يمين
    canvas.drawPath(
      Path()
        ..moveTo(w - _len - _r, 0)
        ..lineTo(w - _r, 0)
        ..arcToPoint(Offset(w, _r), radius: const Radius.circular(_r))
        ..lineTo(w, _len + _r),
      paint,
    );
    // أسفل-يمين
    canvas.drawPath(
      Path()
        ..moveTo(w, h - _len - _r)
        ..lineTo(w, h - _r)
        ..arcToPoint(Offset(w - _r, h), radius: const Radius.circular(_r))
        ..lineTo(w - _len - _r, h),
      paint,
    );
    // أسفل-يسار
    canvas.drawPath(
      Path()
        ..moveTo(_len + _r, h)
        ..lineTo(_r, h)
        ..arcToPoint(Offset(0, h - _r), radius: const Radius.circular(_r))
        ..lineTo(0, h - _len - _r),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// شيت يعرض للكاشير الهدية اللي العميل بيستلمها (نوعها + اسمها + وصفها)
/// مع حالة انتظار تأكيد العميل.
class _PrizeDeliverSheet extends StatelessWidget {
  final String title;
  final String kind; // reward / coupon / points
  final String? description;
  const _PrizeDeliverSheet(
      {required this.title, required this.kind, this.description});

  (IconData, String) get _kind => switch (kind) {
        'coupon' => (Icons.confirmation_num_outlined, 'كوبون'),
        'points' => (Icons.stars_rounded, 'نقاط'),
        _ => (Icons.card_giftcard_rounded, 'مكافأة'),
      };

  @override
  Widget build(BuildContext context) {
    final (icon, label) = _kind;
    final theme = Theme.of(context);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 44,
            height: 5,
            margin: const EdgeInsets.only(bottom: 18),
            decoration: BoxDecoration(
                color: AppColors.divider,
                borderRadius: BorderRadius.circular(3)),
          ),
          AppIconBadge(icon, size: 84, iconSize: 42, circle: true),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(AppRadii.pill)),
            child: Text(label,
                style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark)),
          ),
          const SizedBox(height: 10),
          Text('سلّم للعميل', style: theme.textTheme.bodyMedium),
          const SizedBox(height: 2),
          Text(title,
              style: theme.textTheme.headlineSmall,
              textAlign: TextAlign.center),
          if (description != null && description!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(description!,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: AppColors.textSecondary)),
          ],
          const SizedBox(height: 18),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
                color: AppColors.warningBg,
                borderRadius: BorderRadius.circular(AppRadii.md)),
            child: const Row(mainAxisSize: MainAxisSize.min, children: [
              AppIcon(Icons.hourglass_top_rounded,
                  size: 18, color: AppColors.warning),
              SizedBox(width: 8),
              Flexible(
                child: Text('بانتظار تأكيد العميل من شاشته',
                    style: TextStyle(
                        color: AppColors.warning, fontWeight: FontWeight.w700)),
              ),
            ]),
          ),
          const SizedBox(height: 16),
          PrimaryButton(
              label: 'تم',
              onPressed: () => Navigator.pop(context)),
        ]),
      ),
    );
  }
}
