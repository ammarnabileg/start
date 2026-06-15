import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../shell/merchant_shell.dart';

/// دخول الموظف: صاحب المتجر يضيفه برقم جواله، والموظف يسجّل دخوله بنفس الرقم.
/// بعد التحقق نربط حسابه بالدعوة عبر claim-staff ويحصل على دوره وصلاحياته.
class StaffLoginScreen extends StatefulWidget {
  const StaffLoginScreen({super.key});
  @override
  State<StaffLoginScreen> createState() => _StaffLoginScreenState();
}

class _StaffLoginScreenState extends State<StaffLoginScreen> {
  final _phone = TextEditingController(text: '+966');
  final _otp = TextEditingController();
  bool _sent = false;
  bool _busy = false;

  @override
  void dispose() {
    _phone.dispose();
    _otp.dispose();
    super.dispose();
  }

  String get _normalizedPhone => _phone.text.trim();

  Future<void> _sendCode() async {
    if (_normalizedPhone.length < 9) {
      AppFeedback.toast(context, 'أدخل رقم جوال صحيح', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      await Supabase.instance.client.auth.signInWithOtp(phone: _normalizedPhone);
      if (mounted) setState(() => _sent = true);
    } catch (_) {
      if (mounted) AppFeedback.toast(context, 'تعذّر إرسال الرمز', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify() async {
    if (_otp.text.trim().length < 4) return;
    setState(() => _busy = true);
    try {
      final client = Supabase.instance.client;
      await client.auth.verifyOTP(
        type: OtpType.sms,
        phone: _normalizedPhone,
        token: _otp.text.trim(),
      );
      // ربط الدعوة
      final res = await client.functions.invoke('claim-staff');
      final data = res.data as Map<String, dynamic>?;
      if (data?['linked'] == true) {
        if (mounted) {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const MerchantShell()),
            (r) => false,
          );
        }
      } else {
        await client.auth.signOut();
        if (mounted) {
          AppFeedback.toast(
            context,
            (data?['error'] as String?) ??
                'لا توجد دعوة بهذا الرقم. تواصل مع صاحب المتجر.',
            error: true,
          );
        }
      }
    } catch (_) {
      if (mounted) AppFeedback.toast(context, 'رمز غير صحيح', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('دخول موظف')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const SizedBox(height: 12),
          Container(
            height: 84,
            width: 84,
            decoration: const BoxDecoration(
                gradient: AppColors.goldGradient, shape: BoxShape.circle),
            child: const Icon(Icons.badge_rounded,
                size: 40, color: AppColors.onPrimary),
          ),
          const SizedBox(height: 16),
          Text('سجّل دخولك كموظف',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 6),
          const Text('استخدم رقم الجوال الذي أضافه صاحب المتجر.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.textSecondary)),
          const SizedBox(height: 28),
          if (!_sent) ...[
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              textDirection: TextDirection.ltr,
              decoration: const InputDecoration(labelText: 'رقم الجوال'),
            ),
            const SizedBox(height: 20),
            PrimaryButton(
                label: 'إرسال رمز التحقق',
                loading: _busy,
                onPressed: _sendCode),
          ] else ...[
            TextField(
              controller: _otp,
              keyboardType: TextInputType.number,
              textDirection: TextDirection.ltr,
              maxLength: 6,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(labelText: 'رمز التحقق'),
            ),
            const SizedBox(height: 12),
            PrimaryButton(
                label: 'دخول', loading: _busy, onPressed: _verify),
            const SizedBox(height: 8),
            TextButton(
                onPressed: _busy ? null : () => setState(() => _sent = false),
                child: const Text('تغيير الرقم')),
          ],
        ],
      ),
    );
  }
}
