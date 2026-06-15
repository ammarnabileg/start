import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../shell/merchant_shell.dart';

/// 2.5 — تسجيل الدخول. رقم الجوال/البريد + كلمة المرور.
/// عند النجاح → MerchantShell.
class MerchantLoginScreen extends StatefulWidget {
  const MerchantLoginScreen({super.key});

  @override
  State<MerchantLoginScreen> createState() => _MerchantLoginScreenState();
}

class _MerchantLoginScreenState extends State<MerchantLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _identifierCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _busy = false;
  bool _obscure = true;

  @override
  void dispose() {
    _identifierCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  bool get _looksLikeEmail => _identifierCtrl.text.contains('@');

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    final client = Supabase.instance.client;
    final id = _identifierCtrl.text.trim();
    final password = _passwordCtrl.text;
    try {
      if (_looksLikeEmail) {
        await client.auth.signInWithPassword(email: id, password: password);
      } else {
        await client.auth.signInWithPassword(phone: id, password: password);
      }
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(builder: (_) => const MerchantShell()),
        (route) => false,
      );
    } on AuthException catch (e) {
      _snack(e.message);
    } catch (_) {
      _snack('تعذّر تسجيل الدخول، تحقق من البيانات والاتصال');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _forgotPassword() async {
    final id = _identifierCtrl.text.trim();
    if (id.isEmpty || !id.contains('@')) {
      _snack('أدخل بريدك الإلكتروني أولًا لإرسال رابط إعادة التعيين');
      return;
    }
    try {
      await Supabase.instance.client.auth.resetPasswordForEmail(id);
      if (mounted) {
        AppFeedback.toast(
            context, 'أرسلنا رابط إعادة تعيين كلمة المرور إلى بريدك');
      }
    } catch (_) {
      _snack('تعذّر إرسال رابط إعادة التعيين');
    }
  }

  void _snack(String m) {
    if (!mounted) return;
    AppFeedback.toast(context, m, error: true);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('تسجيل الدخول')),
      body: SafeArea(
        child: Form(
          key: _formKey,
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
                  child: const Icon(Icons.storefront_rounded,
                      size: 44, color: AppColors.onPrimary),
                )
                    .animate()
                    .scale(duration: 450.ms, curve: Curves.easeOutBack)
                    .fadeIn(),
              ),
              const SizedBox(height: AppSpacing.xl),
              Text('أهلًا بعودتك',
                  style: text.titleLarge, textAlign: TextAlign.center),
              const SizedBox(height: AppSpacing.xxl),
              TextFormField(
                controller: _identifierCtrl,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'رقم الجوال أو البريد الإلكتروني',
                  prefixIcon: Icon(Icons.person_outline),
                ),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _passwordCtrl,
                obscureText: _obscure,
                decoration: InputDecoration(
                  labelText: 'كلمة المرور',
                  prefixIcon: const Icon(Icons.lock_outline),
                  suffixIcon: IconButton(
                    icon: Icon(
                        _obscure ? Icons.visibility_off : Icons.visibility),
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),
                validator: (v) =>
                    (v == null || v.isEmpty) ? 'مطلوب' : null,
              ),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: TextButton(
                  onPressed: _busy ? null : _forgotPassword,
                  child: const Text('نسيت كلمة المرور؟'),
                ),
              ),
              const SizedBox(height: 12),
              PrimaryButton(
                label: 'دخول',
                loading: _busy,
                onPressed: _busy ? null : _login,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
