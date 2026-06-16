import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart' hide TextDirection;
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../data/repositories/user_repository.dart';
import 'otp_screen.dart';

/// إنشاء حساب جديد (Register) — راجع CUSTOMER_APP.md 1.4.
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();

  DateTime? _dob;
  bool _obscure = true;
  bool _acceptedTerms = false;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    for (final c in [_nameCtrl, _phoneCtrl, _emailCtrl, _passwordCtrl]) {
      c.addListener(_onChanged);
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  void _onChanged() => setState(() {});

  // ===== فاليديشن =====
  String get _digits => _phoneCtrl.text.replaceAll(RegExp(r'\D'), '');

  bool get _nameValid => _nameCtrl.text.trim().length >= 2;

  bool get _phoneValid {
    // رقم سعودي محلي: 9 أرقام تبدأ بـ 5، أو 05XXXXXXXX (10 أرقام).
    final d = _digits;
    if (d.length == 10 && d.startsWith('05')) return true;
    if (d.length == 9 && d.startsWith('5')) return true;
    return false;
  }

  bool get _emailValid {
    final e = _emailCtrl.text.trim();
    if (e.isEmpty) return true; // اختياري
    return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(e);
  }

  bool get _passwordValid => _passwordCtrl.text.length >= 8;

  /// قوة كلمة المرور 0..4.
  int get _passwordStrength {
    final p = _passwordCtrl.text;
    var score = 0;
    if (p.length >= 8) score++;
    if (RegExp(r'[A-Z]').hasMatch(p)) score++;
    if (RegExp(r'[0-9]').hasMatch(p)) score++;
    if (RegExp(r'[!@#$%^&*(),.?":{}|<>_\-]').hasMatch(p)) score++;
    return score;
  }

  bool get _canSubmit =>
      _nameValid &&
      _phoneValid &&
      _emailValid &&
      _passwordValid &&
      _acceptedTerms &&
      !_loading;

  /// رقم بصيغة E.164 (+9665XXXXXXXX).
  String get _e164Phone {
    var d = _digits;
    if (d.startsWith('0')) d = d.substring(1);
    return '+966$d';
  }

  Future<void> _pickDob() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dob ?? DateTime(now.year - 20),
      firstDate: DateTime(1920),
      lastDate: now,
      helpText: 'اختر تاريخ ميلادك',
    );
    if (picked != null) setState(() => _dob = picked);
  }

  Future<void> _submit() async {
    if (!_canSubmit) return;
    setState(() => _loading = true);
    final phone = _e164Phone;
    try {
      await ref.read(userRepoProvider).signInWithOtp(phone: phone);
      if (!mounted) return;
      Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => OtpScreen(
          phone: phone,
          isRegister: true,
          draft: {
            'name': _nameCtrl.text.trim(),
            'email': _emailCtrl.text.trim().isEmpty
                ? null
                : _emailCtrl.text.trim(),
            'dob': _dob?.toIso8601String(),
            'password': _passwordCtrl.text,
          },
        ),
      ));
    } on AuthException catch (e) {
      if (mounted) AppFeedback.toast(context, e.message, error: true);
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
      appBar: AppBar(title: const Text('إنشاء حساب جديد'), centerTitle: true),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          children: [
            Text(
              'لننشئ حسابك ونبدأ بجمع النقاط.',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: AppSpacing.xxl),

            // الاسم الكامل
            const _Label('الاسم الكامل'),
            TextField(
              controller: _nameCtrl,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(hintText: 'اكتب اسمك الكامل'),
            ),
            const SizedBox(height: 18),

            // رقم الجوال
            const _Label('رقم الجوال'),
            TextField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9]')),
                LengthLimitingTextInputFormatter(10),
              ],
              textDirection: TextDirection.ltr,
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
            const SizedBox(height: 18),

            // البريد الإلكتروني (اختياري)
            const _Label('البريد الإلكتروني (اختياري)'),
            TextField(
              controller: _emailCtrl,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              textDirection: TextDirection.ltr,
              decoration: InputDecoration(
                hintText: 'name@example.com',
                errorText: _emailValid ? null : 'صيغة البريد غير صحيحة',
              ),
            ),
            const SizedBox(height: 18),

            // كلمة المرور
            const _Label('كلمة المرور'),
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
            if (_passwordCtrl.text.isNotEmpty) ...[
              const SizedBox(height: 8),
              _PasswordStrengthBar(strength: _passwordStrength),
            ],
            const SizedBox(height: 18),

            // تاريخ الميلاد (اختياري)
            const _Label('تاريخ الميلاد (اختياري)'),
            InkWell(
              onTap: _pickDob,
              borderRadius: BorderRadius.circular(16),
              child: InputDecorator(
                decoration: const InputDecoration(),
                child: Row(
                  children: [
                    const Icon(Icons.cake_outlined,
                        size: 20, color: AppColors.textSecondary),
                    const SizedBox(width: 10),
                    Text(
                      _dob == null
                          ? 'اختر تاريخ ميلادك'
                          : DateFormat('yyyy/MM/dd').format(_dob!),
                      style: theme.textTheme.bodyLarge?.copyWith(
                        color: _dob == null
                            ? AppColors.textSecondary
                            : AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'نستخدمه لإرسال هدية في عيد ميلادك.',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 18),

            // الشروط
            InkWell(
              onTap: () => setState(() => _acceptedTerms = !_acceptedTerms),
              borderRadius: BorderRadius.circular(12),
              child: Row(
                children: [
                  Checkbox(
                    value: _acceptedTerms,
                    onChanged: (v) =>
                        setState(() => _acceptedTerms = v ?? false),
                  ),
                  const Expanded(
                    child: Text('أوافق على الشروط وسياسة الخصوصية'),
                  ),
                ],
              ),
            ),

            const SizedBox(height: AppSpacing.xxl),
            PrimaryButton(
              label: 'إنشاء الحساب',
              loading: _loading,
              onPressed: _canSubmit ? _submit : null,
            ),
            const SizedBox(height: AppSpacing.md),
          ],
        ),
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

class _PasswordStrengthBar extends StatelessWidget {
  final int strength; // 0..4
  const _PasswordStrengthBar({required this.strength});

  @override
  Widget build(BuildContext context) {
    const labels = ['ضعيفة جدًا', 'ضعيفة', 'متوسطة', 'جيدة', 'قوية'];
    final colors = [
      AppColors.error,
      AppColors.error,
      AppColors.warning,
      AppColors.info,
      AppColors.success,
    ];
    final color = colors[strength];
    return Row(
      children: [
        Expanded(
          child: ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: strength / 4,
              minHeight: 6,
              backgroundColor: AppColors.divider,
              valueColor: AlwaysStoppedAnimation<Color>(color),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Text(labels[strength],
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(color: color)),
      ],
    );
  }
}
