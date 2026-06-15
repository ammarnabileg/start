import 'dart:async';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'notifications_priming_screen.dart';

/// 1.5 — تأكيد الجوال (OTP).
class OtpScreen extends StatefulWidget {
  final String phone;
  final bool isRegister;
  final Map<String, dynamic>? draft;

  const OtpScreen({
    super.key,
    required this.phone,
    this.isRegister = false,
    this.draft,
  });

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  static const int _codeLength = 6;

  final List<TextEditingController> _controllers =
      List.generate(_codeLength, (_) => TextEditingController());
  final List<FocusNode> _focusNodes =
      List.generate(_codeLength, (_) => FocusNode());

  Timer? _timer;
  int _secondsLeft = 30;
  bool _loading = false;
  bool _resending = false;

  String get _code => _controllers.map((c) => c.text).join();
  bool get _codeComplete => _code.length == _codeLength;
  bool get _canResend => _secondsLeft == 0;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (final c in _controllers) {
      c.dispose();
    }
    for (final f in _focusNodes) {
      f.dispose();
    }
    super.dispose();
  }

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

  void _onDigitChanged(int index, String value) {
    if (value.isNotEmpty) {
      // في حال لصق كود كامل.
      if (value.length > 1) {
        _distribute(value);
        return;
      }
      if (index < _codeLength - 1) {
        _focusNodes[index + 1].requestFocus();
      } else {
        _focusNodes[index].unfocus();
      }
    }
    if (_codeComplete) _verify();
  }

  void _distribute(String pasted) {
    final digits = pasted.replaceAll(RegExp(r'\D'), '');
    for (var i = 0; i < _codeLength; i++) {
      _controllers[i].text = i < digits.length ? digits[i] : '';
    }
    final next = min(digits.length, _codeLength - 1);
    _focusNodes[next].requestFocus();
    if (_codeComplete) _verify();
  }

  /// referral_code: أحرف الاسم الأولى + رقم عشوائي.
  String _generateReferralCode(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty);
    final initials = parts
        .take(2)
        .map((p) => p.characters.first)
        .join()
        .toUpperCase();
    final base = initials.isEmpty ? 'HC' : initials;
    final rand = Random().nextInt(900000) + 100000; // 6 أرقام
    return '$base$rand';
  }

  Future<void> _verify() async {
    if (!_codeComplete || _loading) return;
    setState(() => _loading = true);
    final client = Supabase.instance.client;
    try {
      await client.auth.verifyOTP(
        type: OtpType.sms,
        phone: widget.phone,
        token: _code,
      );

      if (widget.isRegister) {
        final draft = widget.draft ?? const {};
        final user = client.auth.currentUser;
        if (user != null) {
          final name = (draft['name'] as String?)?.trim() ?? '';
          // تسجيل صف الملف في جدول users.
          await client.from('users').upsert({
            'id': user.id,
            'name': name,
            'phone': widget.phone,
            'email': draft['email'],
            'date_of_birth': draft['dob'],
            'referral_code': _generateReferralCode(name),
          });

          // ضبط كلمة المرور (إن وُجدت في المسودة) للسماح بالدخول لاحقًا بالباسورد.
          final password = draft['password'] as String?;
          if (password != null && password.isNotEmpty) {
            try {
              await client.auth.updateUser(UserAttributes(password: password));
            } catch (_) {
              // غير حرج لإكمال التسجيل.
            }
          }
        }

        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => const NotificationsPrimingScreen(),
          ),
        );
      } else {
        if (!mounted) return;
        context.go('/');
      }
    } on AuthException catch (e) {
      if (mounted) AppFeedback.toast(context, e.message, error: true);
      _clearCode();
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'رمز التحقق غير صحيح، حاول مرة أخرى.',
            error: true);
      }
      _clearCode();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _clearCode() {
    for (final c in _controllers) {
      c.clear();
    }
    _focusNodes.first.requestFocus();
  }

  Future<void> _resend() async {
    if (!_canResend || _resending) return;
    setState(() => _resending = true);
    try {
      await Supabase.instance.client.auth.signInWithOtp(phone: widget.phone);
      _startCountdown();
      if (mounted) AppFeedback.toast(context, 'أرسلنا رمزًا جديدًا');
    } on AuthException catch (e) {
      if (mounted) AppFeedback.toast(context, e.message, error: true);
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر إعادة الإرسال، حاول لاحقًا.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(centerTitle: true),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          children: [
            const SizedBox(height: AppSpacing.sm),
            Center(
              child: Container(
                height: 96,
                width: 96,
                decoration: const BoxDecoration(
                  gradient: AppColors.goldGradient,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.sms_outlined,
                    size: 44, color: AppColors.onPrimary),
              )
                  .animate()
                  .scale(duration: 420.ms, curve: Curves.easeOutBack)
                  .fadeIn(),
            ),
            const SizedBox(height: AppSpacing.xxl),
            Text(
              'أدخل رمز التحقق',
              style: theme.textTheme.displayLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 12),
            Text(
              'أرسلنا رمزًا إلى ${widget.phone}',
              style: theme.textTheme.bodyLarge
                  ?.copyWith(color: AppColors.textSecondary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 32),
            Directionality(
              textDirection: TextDirection.ltr,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: List.generate(_codeLength, (i) => _OtpBox(
                      controller: _controllers[i],
                      focusNode: _focusNodes[i],
                      onChanged: (v) => _onDigitChanged(i, v),
                      onBackspaceEmpty: () {
                        if (i > 0) {
                          _focusNodes[i - 1].requestFocus();
                          _controllers[i - 1].clear();
                        }
                      },
                    )),
              ),
            ),
            const SizedBox(height: AppSpacing.xxl),
            PrimaryButton(
              label: 'تأكيد',
              loading: _loading,
              onPressed: _codeComplete ? _verify : null,
            ),
            const SizedBox(height: 20),
            Center(
              child: _canResend
                  ? TextButton(
                      onPressed: _resending ? null : _resend,
                      child: const Text('إعادة إرسال الرمز'),
                    )
                  : Text(
                      _countdownLabel,
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(color: AppColors.textSecondary),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OtpBox extends StatelessWidget {
  final TextEditingController controller;
  final FocusNode focusNode;
  final ValueChanged<String> onChanged;
  final VoidCallback onBackspaceEmpty;

  const _OtpBox({
    required this.controller,
    required this.focusNode,
    required this.onChanged,
    required this.onBackspaceEmpty,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 48,
      child: KeyboardListener(
        focusNode: FocusNode(skipTraversal: true),
        onKeyEvent: (event) {
          if (event is KeyDownEvent &&
              event.logicalKey == LogicalKeyboardKey.backspace &&
              controller.text.isEmpty) {
            onBackspaceEmpty();
          }
        },
        child: TextField(
          controller: controller,
          focusNode: focusNode,
          keyboardType: TextInputType.number,
          textAlign: TextAlign.center,
          maxLength: 1,
          style: Theme.of(context)
              .textTheme
              .headlineMedium
              ?.copyWith(fontWeight: FontWeight.w700),
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly,
          ],
          decoration: const InputDecoration(
            counterText: '',
            contentPadding: EdgeInsets.symmetric(vertical: 16),
          ),
          onChanged: onChanged,
        ),
      ),
    );
  }
}
