import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart' show AuthException;

import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/merchant_repository.dart';
import 'pending_approval_screen.dart';

/// شاشة OTP لتسجيل النشاط. بعد التحقق تُنشأ صفوف merchants + merchant_staff owner
/// ثم ننتقل لشاشة "قيد المراجعة".
class MerchantOtpScreen extends ConsumerStatefulWidget {
  final String phone;
  final Map<String, dynamic>? draft;

  const MerchantOtpScreen({super.key, required this.phone, this.draft});

  @override
  ConsumerState<MerchantOtpScreen> createState() => _MerchantOtpScreenState();
}

class _MerchantOtpScreenState extends ConsumerState<MerchantOtpScreen> {
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
    final auth = ref.read(authRepoProvider);
    final merchantRepo = ref.read(merchantRepoProvider);
    try {
      await auth.verifyOtp(widget.phone, token);

      final uid = auth.currentUserId;
      if (uid == null) {
        _snack('تعذّر التحقق من الجلسة، حاول مرة أخرى');
        return;
      }

      final draft = widget.draft ?? const <String, dynamic>{};

      // إنشاء التاجر (pending) وربط المستخدم كمالك ذرّيًا عبر دالة آمنة.
      final merchantId = await merchantRepo.registerMerchant(draft, widget.phone);

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
      await ref.read(authRepoProvider).signInWithOtp(widget.phone);
      _snack('تم إرسال رمز جديد', error: false);
    } catch (_) {
      _snack('تعذّر إعادة الإرسال');
    }
  }

  void _snack(String m, {bool error = true}) {
    if (!mounted) return;
    AppFeedback.toast(context, m, error: error);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('تأكيد رقم الجوال')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.xxl, vertical: AppSpacing.lg),
          children: [
            const SizedBox(height: AppSpacing.md),
            Center(
              child: Container(
                width: 88,
                height: 88,
                decoration: const BoxDecoration(
                  gradient: AppColors.heroGradient,
                  shape: BoxShape.circle,
                ),
                child: const AppIcon(Icons.sms_outlined,
                    size: 44, color: AppColors.onPrimary),
              )
                  .animate()
                  .scale(duration: 450.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
            ),
            const SizedBox(height: AppSpacing.xl),
            Text('أدخل رمز التحقق',
                style: text.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'أرسلنا رمزًا مكوّنًا من 6 أرقام إلى ${widget.phone}',
              style: text.bodyMedium?.copyWith(color: AppColors.textSecondary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.xxl),
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
                filled: true,
                fillColor: AppColors.surfaceCream,
              ),
            ),
            const SizedBox(height: AppSpacing.xxl),
            PrimaryButton(
              label: 'تأكيد',
              loading: _busy,
              onPressed: _busy ? null : _verify,
            ),
            const SizedBox(height: AppSpacing.sm),
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
