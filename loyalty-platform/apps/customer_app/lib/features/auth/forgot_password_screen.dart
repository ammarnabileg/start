import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../data/repositories/user_repository.dart';

/// 1.7 — نسيت كلمة المرور.
/// التدفّق: إدخال رقم الجوال → OTP → كلمة مرور جديدة → نجاح → رجوع للدخول.
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() =>
      _ForgotPasswordScreenState();
}

enum _Step { phone, otp, newPassword, done }

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  _Step _step = _Step.phone;

  final _phoneCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _confirmCtrl = TextEditingController();

  bool _obscure = true;
  bool _loading = false;

  Timer? _timer;
  int _secondsLeft = 30;

  @override
  void initState() {
    super.initState();
    for (final c in [_phoneCtrl, _codeCtrl, _passwordCtrl, _confirmCtrl]) {
      c.addListener(() => setState(() {}));
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _phoneCtrl.dispose();
    _codeCtrl.dispose();
    _passwordCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  String get _digits => _phoneCtrl.text.replaceAll(RegExp(r'\D'), '');

  bool get _phoneValid {
    final d = _digits;
    if (d.length == 10 && d.startsWith('05')) return true;
    if (d.length == 9 && d.startsWith('5')) return true;
    return false;
  }

  String get _e164Phone {
    var d = _digits;
    if (d.startsWith('0')) d = d.substring(1);
    return '+966$d';
  }

  bool get _canResend => _secondsLeft == 0;

  void _startCountdown() {
    _timer?.cancel();
    setState(() => _secondsLeft = 30);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_secondsLeft <= 1) {
        t.cancel();
        setState(() => _secondsLeft = 0);
      } else {
        setState(() => _secondsLeft--);
      }
    });
  }

  String get _countdownLabel {
    final s = _secondsLeft.toString().padLeft(2, '0');
    return 'إعادة الإرسال خلال 00:$s';
  }

  // ===== خطوة 1: إرسال OTP =====
  Future<void> _sendOtp() async {
    if (!_phoneValid || _loading) return;
    setState(() => _loading = true);
    try {
      await ref.read(userRepoProvider).signInWithOtp(phone: _e164Phone);
      _startCountdown();
      setState(() => _step = _Step.otp);
    } on AuthException catch (e) {
      if (mounted) AppFeedback.toast(context, e.message, error: true);
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر إرسال الرمز، حاول مرة أخرى.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  // ===== خطوة 2: التحقق من OTP =====
  Future<void> _verifyOtp() async {
    if (_codeCtrl.text.length < 4 || _loading) return;
    setState(() => _loading = true);
    try {
      await ref.read(userRepoProvider).verifyOtp(
            phone: _e164Phone,
            token: _codeCtrl.text,
          );
      setState(() => _step = _Step.newPassword);
    } on AuthException {
      if (mounted) {
        AppFeedback.toast(context, 'رمز التحقق غير صحيح.', error: true);
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'حدث خطأ، حاول مرة أخرى.', error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  // ===== خطوة 3: كلمة مرور جديدة =====
  bool get _passwordsValid =>
      _passwordCtrl.text.length >= 8 &&
      _passwordCtrl.text == _confirmCtrl.text;

  Future<void> _setNewPassword() async {
    if (!_passwordsValid || _loading) return;
    setState(() => _loading = true);
    try {
      final repo = ref.read(userRepoProvider);
      await repo.updatePassword(_passwordCtrl.text);
      // إنهاء جلسة استعادة الباسورد للرجوع لتسجيل الدخول.
      await repo.signOut();
      setState(() => _step = _Step.done);
    } on AuthException catch (e) {
      if (mounted) AppFeedback.toast(context, e.message, error: true);
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر تحديث كلمة المرور، حاول مرة أخرى.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('استعادة كلمة المرور'),
        centerTitle: true,
      ),
      body: SafeArea(
        child: ResponsiveCenter(
          child: switch (_step) {
            _Step.phone => _buildPhoneStep(),
            _Step.otp => _buildOtpStep(),
            _Step.newPassword => _buildPasswordStep(),
            _Step.done => _buildDoneStep(),
          },
        ),
      ),
    );
  }

  Widget _buildPhoneStep() {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 8),
        Text('أدخل رقم جوالك', style: theme.textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(
          'سنرسل لك رمز تحقق لإعادة تعيين كلمة المرور.',
          style:
              theme.textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
        ),
        const SizedBox(height: 20),
        TextField(
          controller: _phoneCtrl,
          keyboardType: TextInputType.phone,
          textDirection: TextDirection.ltr,
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp(r'[0-9]')),
            LengthLimitingTextInputFormatter(10),
          ],
          decoration: const InputDecoration(
            hintText: '5XXXXXXXX',
            prefixIcon: Padding(
              padding: EdgeInsetsDirectional.only(start: 14, end: 6),
              child: Align(
                alignment: Alignment.center,
                widthFactor: 1,
                child: Text('+966  ',
                    style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.xxl),
        PrimaryButton(
          label: 'إرسال الرمز',
          loading: _loading,
          onPressed: _phoneValid ? _sendOtp : null,
        ),
      ],
    );
  }

  Widget _buildOtpStep() {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 8),
        Text('أدخل رمز التحقق', style: theme.textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(
          'أرسلنا رمزًا إلى $_e164Phone',
          style:
              theme.textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
        ),
        const SizedBox(height: 20),
        TextField(
          controller: _codeCtrl,
          keyboardType: TextInputType.number,
          textAlign: TextAlign.center,
          textDirection: TextDirection.ltr,
          maxLength: 6,
          style: theme.textTheme.headlineMedium
              ?.copyWith(fontWeight: FontWeight.w700, letterSpacing: 8),
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(
            counterText: '',
            hintText: '——————',
          ),
        ),
        const SizedBox(height: 12),
        Center(
          child: _canResend
              ? TextButton(
                  onPressed: _loading ? null : _sendOtp,
                  child: const Text('إعادة إرسال الرمز'),
                )
              : Text(_countdownLabel,
                  style: theme.textTheme.bodyMedium
                      ?.copyWith(color: AppColors.textSecondary)),
        ),
        const SizedBox(height: AppSpacing.lg),
        PrimaryButton(
          label: 'تأكيد',
          loading: _loading,
          onPressed: _codeCtrl.text.length >= 4 ? _verifyOtp : null,
        ),
      ],
    );
  }

  Widget _buildPasswordStep() {
    final theme = Theme.of(context);
    final mismatch = _confirmCtrl.text.isNotEmpty &&
        _confirmCtrl.text != _passwordCtrl.text;
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 8),
        Text('كلمة مرور جديدة', style: theme.textTheme.titleLarge),
        const SizedBox(height: 20),
        TextField(
          controller: _passwordCtrl,
          obscureText: _obscure,
          decoration: InputDecoration(
            hintText: '8 أحرف على الأقل',
            suffixIcon: IconButton(
              tooltip: _obscure ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور',
              icon: Icon(_obscure
                  ? Icons.visibility_outlined
                  : Icons.visibility_off_outlined),
              onPressed: () => setState(() => _obscure = !_obscure),
            ),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _confirmCtrl,
          obscureText: _obscure,
          decoration: InputDecoration(
            hintText: 'تأكيد كلمة المرور',
            errorText: mismatch ? 'كلمتا المرور غير متطابقتين' : null,
          ),
        ),
        const SizedBox(height: AppSpacing.xxl),
        PrimaryButton(
          label: 'حفظ كلمة المرور',
          loading: _loading,
          onPressed: _passwordsValid ? _setNewPassword : null,
        ),
      ],
    );
  }

  Widget _buildDoneStep() {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.all(24),
      shrinkWrap: true,
      children: [
        Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            height: 112,
            width: 112,
            decoration: const BoxDecoration(
                color: AppColors.successBg, shape: BoxShape.circle),
            child: const Icon(Icons.check_rounded,
                size: 60, color: AppColors.success),
          )
              .animate()
              .scale(duration: 460.ms, curve: Curves.easeOutBack)
              .fadeIn(),
          const SizedBox(height: AppSpacing.xxl),
          Text('تم تغيير كلمة المرور',
              style: theme.textTheme.displayLarge, textAlign: TextAlign.center),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.',
            style: theme.textTheme.bodyLarge
                ?.copyWith(color: AppColors.textSecondary),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 32),
          PrimaryButton(
            label: 'العودة لتسجيل الدخول',
            onPressed: () => Navigator.of(context).pop(),
          ),
        ],
        ),
      ],
    );
  }
}
