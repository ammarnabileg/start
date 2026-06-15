import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'pending_approval_screen.dart';

/// شاشة OTP لتسجيل النشاط. بعد التحقق تُنشأ صفوف merchants + merchant_staff owner
/// ثم ننتقل لشاشة "قيد المراجعة".
class MerchantOtpScreen extends StatefulWidget {
  final String phone;
  final Map<String, dynamic>? draft;

  const MerchantOtpScreen({super.key, required this.phone, this.draft});

  @override
  State<MerchantOtpScreen> createState() => _MerchantOtpScreenState();
}

class _MerchantOtpScreenState extends State<MerchantOtpScreen> {
  final _codeCtrl = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _codeCtrl.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final token = _codeCtrl.text.trim();
    if (token.length < 4) {
      _snack('أدخل الرمز المكوّن من 6 أرقام');
      return;
    }
    setState(() => _busy = true);
    final client = Supabase.instance.client;
    try {
      await client.auth.verifyOTP(
        type: OtpType.sms,
        phone: widget.phone,
        token: token,
      );

      final uid = client.auth.currentUser?.id;
      if (uid == null) {
        _snack('تعذّر التحقق من الجلسة، حاول مرة أخرى');
        return;
      }

      final draft = widget.draft ?? const <String, dynamic>{};

      // إنشاء صف التاجر بحالة pending.
      final merchant = await client
          .from('merchants')
          .insert({
            'business_name': draft['business_name'],
            'business_type': draft['business_type'],
            'phone': draft['phone'] ?? widget.phone,
            'email': draft['email'],
            'cr_number': draft['cr_number'],
            'logo_url': draft['logo_url'],
            'address': draft['address'],
            'status': 'pending',
          })
          .select()
          .single();

      final merchantId = merchant['id'] as String;

      // ربط المستخدم الحالي كمالك للمتجر.
      await client.from('merchant_staff').insert({
        'user_id': uid,
        'merchant_id': merchantId,
        'role': 'merchant_owner',
        'status': 'active',
      });

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => PendingApprovalScreen(merchantId: merchantId),
        ),
      );
    } on AuthException catch (e) {
      _snack(e.message);
    } catch (e) {
      _snack('تعذّر إكمال التسجيل، حاول مرة أخرى');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    try {
      await Supabase.instance.client.auth.signInWithOtp(phone: widget.phone);
      _snack('تم إرسال رمز جديد');
    } catch (_) {
      _snack('تعذّر إعادة الإرسال');
    }
  }

  void _snack(String m) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(m)));
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('تأكيد رقم الجوال')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          children: [
            const SizedBox(height: 12),
            const Center(
              child: CircleAvatar(
                radius: 40,
                backgroundColor: AppColors.surfaceCream,
                child: Icon(Icons.sms_outlined,
                    size: 40, color: AppColors.primaryDark),
              ),
            ),
            const SizedBox(height: 20),
            Text('أدخل رمز التحقق',
                style: text.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(
              'أرسلنا رمزًا مكوّنًا من 6 أرقام إلى ${widget.phone}',
              style: text.bodyMedium?.copyWith(color: AppColors.textSecondary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 28),
            TextField(
              controller: _codeCtrl,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              maxLength: 6,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              style: const TextStyle(
                  fontSize: 28, fontWeight: FontWeight.w700, letterSpacing: 8),
              decoration: const InputDecoration(
                counterText: '',
                hintText: '------',
              ),
            ),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'تأكيد',
              loading: _busy,
              onPressed: _busy ? null : _verify,
            ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: _busy ? null : _resend,
              child: const Text('إعادة إرسال الرمز'),
            ),
          ],
        ),
      ),
    );
  }
}
