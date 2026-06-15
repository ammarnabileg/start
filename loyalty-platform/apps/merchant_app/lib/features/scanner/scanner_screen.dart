import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'customer_profile_screen.dart';

/// الماسح — أكتر شاشة الكاشير هيستخدمها. يمسح كود العميل ويستدعي verify-qr.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key});
  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
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
      final res = await Supabase.instance.client.functions
          .invoke('verify-qr', body: {'payload': payload});
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
              onPressed: () => _controller.toggleTorch(),
              icon: const Icon(Icons.flash_on)),
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
                  Icon(Icons.qr_code_2_rounded,
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
