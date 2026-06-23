import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../data/repositories/referral_repository.dart';

/// لوحة «ربط إحالة»: تعرض كود العميل (للمشاركة) + ربطه بمُحيل (كود أو QR) + فكّ.
class ReferralLinkSheet extends ConsumerStatefulWidget {
  final String referralCode;
  const ReferralLinkSheet({super.key, required this.referralCode});

  @override
  ConsumerState<ReferralLinkSheet> createState() => _ReferralLinkSheetState();
}

class _ReferralLinkSheetState extends ConsumerState<ReferralLinkSheet> {
  final _ctrl = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _link(String code) async {
    final c = code.trim();
    if (c.isEmpty || _busy) return;
    setState(() => _busy = true);
    try {
      await ref.read(referralRepoProvider).setReferrerByCode(c);
      if (mounted) {
        Navigator.of(context).pop();
        AppFeedback.success(context,
            title: 'تم الربط!', message: 'هتُحتسب الإحالة عند أول زيارة للمتجر.');
      }
    } catch (e) {
      if (mounted) {
        setState(() => _busy = false);
        AppFeedback.toast(context, 'كود غير صحيح أو لا يمكنك إحالة نفسك',
            error: true);
      }
    }
  }

  Future<void> _scan() async {
    final code = await Navigator.of(context).push<String>(
        MaterialPageRoute(builder: (_) => const ReferralScanScreen()));
    if (code != null) await _link(code);
  }

  Future<void> _unlink() async {
    setState(() => _busy = true);
    try {
      await ref.read(referralRepoProvider).clearReferrer();
      if (mounted) {
        Navigator.of(context).pop();
        AppFeedback.toast(context, 'تم فكّ الارتباط');
      }
    } catch (_) {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final code = widget.referralCode;
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 14, 20, 20 + bottom),
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                  color: AppColors.textSecondary.withValues(alpha: .3),
                  borderRadius: BorderRadius.circular(2)),
            ),
          ),
          const SizedBox(height: 16),
          Text('كود إحالتك', style: theme.textTheme.titleLarge),
          const SizedBox(height: 4),
          Text('شاركه مع أصحابك — لما يربطوا بيه ويزوروا متجرك تكسب هدايا 🎁',
              textAlign: TextAlign.center, style: theme.textTheme.bodySmall),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(AppRadii.md)),
            child: QrImageView(
                data: 'ref1.$code', size: 150, backgroundColor: Colors.white),
          ),
          const SizedBox(height: 12),
          GestureDetector(
            onTap: () {
              Clipboard.setData(ClipboardData(text: code));
              AppFeedback.toast(context, 'تم نسخ الكود');
            },
            child: Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
              decoration: BoxDecoration(
                  color: AppColors.surfaceCream,
                  borderRadius: BorderRadius.circular(AppRadii.pill)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Text(code,
                    style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900, letterSpacing: 2)),
                const SizedBox(width: 8),
                const AppIcon(Icons.copy_rounded,
                    size: 18, color: AppColors.primaryDark),
              ]),
            ),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 18),
            child: Divider(),
          ),
          Text('أحالك صديق؟ اربط بكوده',
              style: theme.textTheme.titleMedium),
          const SizedBox(height: 10),
          Row(children: [
            Expanded(
              child: TextField(
                controller: _ctrl,
                textCapitalization: TextCapitalization.characters,
                decoration: const InputDecoration(hintText: 'كود الصديق'),
              ),
            ),
            const SizedBox(width: 8),
            PrimaryButton(
                label: 'ربط',
                expanded: false,
                loading: _busy,
                onPressed: () => _link(_ctrl.text)),
          ]),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: _busy ? null : _scan,
            icon: const AppIcon(Icons.qr_code_scanner_rounded),
            label: const Text('امسح كود صديقك'),
          ),
          const SizedBox(height: 4),
          TextButton(
            onPressed: _busy ? null : _unlink,
            child: const Text('فكّ الارتباط الحالي',
                style: TextStyle(color: AppColors.textSecondary)),
          ),
        ]),
      ),
    );
  }
}

/// ماسح كود الإحالة — يرجّع الكود (من رمز ref1.<code>).
class ReferralScanScreen extends StatefulWidget {
  const ReferralScanScreen({super.key});
  @override
  State<ReferralScanScreen> createState() => _ReferralScanScreenState();
}

class _ReferralScanScreenState extends State<ReferralScanScreen> {
  final _controller = MobileScannerController();
  bool _done = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_done) return;
    final raw = capture.barcodes.isNotEmpty
        ? capture.barcodes.first.rawValue
        : null;
    if (raw == null || !raw.startsWith('ref1.')) return;
    _done = true;
    Navigator.of(context).pop(raw.substring(5));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('امسح كود صديقك')),
      body: Stack(children: [
        MobileScanner(controller: _controller, onDetect: _onDetect),
        const Align(
          alignment: Alignment.bottomCenter,
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text('وجّه الكاميرا لكود إحالة صديقك',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
          ),
        ),
      ]),
    );
  }
}
