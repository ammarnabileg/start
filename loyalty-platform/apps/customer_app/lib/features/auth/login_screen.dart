import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'forgot_password_screen.dart';

/// 1.6 — تسجيل الدخول (Login).
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _phoneCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();

  bool _obscure = true;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _phoneCtrl.addListener(_onChanged);
    _passwordCtrl.addListener(_onChanged);
  }

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  void _onChanged() => setState(() {});

  String get _digits => _phoneCtrl.text.replaceAll(RegExp(r'\D'), '');

  bool get _phoneValid {
    final d = _digits;
    if (d.length == 10 && d.startsWith('05')) return true;
    if (d.length == 9 && d.startsWith('5')) return true;
    return false;
  }

  /// رقم بصيغة E.164 (+9665XXXXXXXX).
  String get _e164Phone {
    var d = _digits;
    if (d.startsWith('0')) d = d.substring(1);
    return '+966$d';
  }

  bool get _canSubmit =>
      _phoneValid && _passwordCtrl.text.isNotEmpty && !_loading;

  Future<void> _submit() async {
    if (!_canSubmit) return;
    setState(() => _loading = true);
    try {
      await Supabase.instance.client.auth.signInWithPassword(
        phone: _e164Phone,
        password: _passwordCtrl.text,
      );
      if (!mounted) return;
      context.go('/');
    } on AuthException {
      if (mounted) {
        AppFeedback.toast(context, 'رقم الجوال أو كلمة المرور غير صحيحة',
            error: true);
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'حدث خطأ غير متوقع، حاول مرة أخرى.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('تسجيل الدخول'), centerTitle: true),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          children: [
            const SizedBox(height: AppSpacing.sm),
            Text('أهلًا بعودتك', style: theme.textTheme.displayLarge)
                .animate()
                .fadeIn(duration: 350.ms)
                .slideY(begin: .12, end: 0),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'سجّل دخولك للوصول إلى نقاطك ومكافآتك.',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: AppSpacing.xxl),
            // رقم الجوال
            const _Label('رقم الجوال'),
            TextField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
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
            const SizedBox(height: AppSpacing.lg),

            // كلمة المرور
            const _Label('كلمة المرور'),
            TextField(
              controller: _passwordCtrl,
              obscureText: _obscure,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _canSubmit ? _submit() : null,
              decoration: InputDecoration(
                hintText: 'كلمة المرور',
                suffixIcon: IconButton(
                  icon: Icon(_obscure
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
            ),

            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton(
                onPressed: () {
                  Navigator.of(context).push(MaterialPageRoute<void>(
                    builder: (_) => const ForgotPasswordScreen(),
                  ));
                },
                child: const Text('نسيت كلمة المرور؟'),
              ),
            ),

            const SizedBox(height: AppSpacing.lg),
            PrimaryButton(
              label: 'تسجيل الدخول',
              loading: _loading,
              onPressed: _canSubmit ? _submit : null,
            ),
          ],
        ),
      ),
    );
  }
}

class _Label extends StatelessWidget {
  final String text;
  const _Label(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(text, style: Theme.of(context).textTheme.titleMedium),
    );
  }
}
