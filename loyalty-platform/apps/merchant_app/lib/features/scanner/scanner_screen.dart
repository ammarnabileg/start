import 'package:flutter/material.dart';
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
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(msg)));
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
          // إطار المسح
          Container(
            width: 240,
            height: 240,
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.primary, width: 3),
              borderRadius: BorderRadius.circular(24),
            ),
          ),
          Positioned(
            bottom: 60,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(20)),
              child: const Text('وجّه الكاميرا نحو رمز العميل',
                  style: TextStyle(color: Colors.white)),
            ),
          ),
          if (_busy) const ColoredBox(color: Colors.black38, child: LoadingView()),
        ],
      ),
    );
  }
}
