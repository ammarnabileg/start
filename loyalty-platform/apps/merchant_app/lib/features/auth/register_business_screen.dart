import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/map_picker_screen.dart';
import '../../core/media_storage.dart';
import 'otp_screen.dart';

/// 2.3 — تسجيل النشاط. يجمع بيانات المتجر ثم يرسل OTP لرقم الجوال.
/// عند التحقق (شاشة OTP) يُنشأ صف merchants + merchant_staff owner.
class RegisterBusinessScreen extends StatefulWidget {
  const RegisterBusinessScreen({super.key});

  @override
  State<RegisterBusinessScreen> createState() => _RegisterBusinessScreenState();
}

class _RegisterBusinessScreenState extends State<RegisterBusinessScreen> {
  final _formKey = GlobalKey<FormState>();

  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _crCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();

  // قيم قائمة نوع النشاط: العربي للعرض + القيمة المخزّنة.
  static const _businessTypes = <String, String>{
    'restaurant': 'مطعم',
    'cafe': 'كافيه',
    'store': 'متجر',
    'salon': 'صالون',
    'other': 'أخرى',
  };
  String _businessType = 'restaurant';

  String? _logoUrl;
  bool _uploadingLogo = false;
  double? _lat;
  double? _lng;
  bool _agreedTerms = false;
  bool _busy = false;
  bool _obscure = true;

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _crCtrl.dispose();
    _addressCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _uploadLogo() async {
    setState(() => _uploadingLogo = true);
    try {
      final url = await MediaStorage.pickAndUpload(
        bucket: 'merchant-media',
        folder: 'logos',
      );
      if (!mounted) return;
      if (url != null) {
        setState(() => _logoUrl = url);
        _snack('تم رفع الشعار', error: false);
      } else {
        _snack('تعذّر رفع الشعار');
      }
    } catch (_) {
      _snack('تعذّر رفع الشعار');
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }

  Future<void> _pickLocation() async {
    final result = await Navigator.of(context).push<PickedLocation>(
      MaterialPageRoute(
        builder: (_) => MapPickerScreen(initialLat: _lat, initialLng: _lng),
      ),
    );
    if (result != null) {
      setState(() {
        _lat = result.lat;
        _lng = result.lng;
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_agreedTerms) {
      _snack('يجب الموافقة على الشروط والأحكام');
      return;
    }
    setState(() => _busy = true);
    final phone = _phoneCtrl.text.trim();
    try {
      // إرسال رمز التحقق لرقم الجوال.
      await Supabase.instance.client.auth.signInWithOtp(phone: phone);

      // مسوّدة بيانات التاجر تُمرّر لشاشة OTP لإنشاء الصفوف بعد التحقق.
      final draft = <String, dynamic>{
        'business_name': _nameCtrl.text.trim(),
        'business_type': _businessType,
        'phone': phone,
        'email': _emailCtrl.text.trim(),
        'cr_number': _crCtrl.text.trim().isEmpty ? null : _crCtrl.text.trim(),
        'logo_url': _logoUrl,
        'logo_local_path': null,
        'address': _addressCtrl.text.trim(),
        'lat': _lat,
        'lng': _lng,
        'password': _passwordCtrl.text,
      };

      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => MerchantOtpScreen(phone: phone, draft: draft),
        ),
      );
    } catch (e) {
      _snack('تعذّر إرسال رمز التحقق، تحقق من الرقم والاتصال');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String m, {bool error = true}) {
    if (!mounted) return;
    AppFeedback.toast(context, m, error: error);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تسجيل نشاط جديد')),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.xl, AppSpacing.md,
                AppSpacing.xl, AppSpacing.xxxl),
            children: [
              _logoPicker(),
              const SizedBox(height: AppSpacing.md),
              Center(
                child: TextButton.icon(
                  onPressed: _uploadingLogo ? null : _uploadLogo,
                  icon: _uploadingLogo
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.upload_outlined),
                  label: const Text('رفع الشعار'),
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              const SectionHeader(title: 'بيانات النشاط'),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _nameCtrl,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'اسم المتجر / النشاط',
                  prefixIcon: Icon(Icons.storefront_outlined),
                ),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
              ),
              const SizedBox(height: AppSpacing.lg),
              DropdownButtonFormField<String>(
                value: _businessType,
                decoration: const InputDecoration(
                  labelText: 'نوع النشاط',
                  prefixIcon: Icon(Icons.category_outlined),
                ),
                items: _businessTypes.entries
                    .map((e) => DropdownMenuItem<String>(
                          value: e.key,
                          child: Text(e.value),
                        ))
                    .toList(),
                onChanged: (v) =>
                    setState(() => _businessType = v ?? _businessType),
              ),
              const SizedBox(height: AppSpacing.xl),
              const SectionHeader(title: 'بيانات التواصل'),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'رقم الجوال',
                  hintText: '+9665XXXXXXXX',
                  prefixIcon: Icon(Icons.phone_outlined),
                ),
                validator: (v) =>
                    (v == null || v.trim().length < 9) ? 'رقم غير صالح' : null,
              ),
              const SizedBox(height: AppSpacing.lg),
              TextFormField(
                controller: _emailCtrl,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'البريد الإلكتروني',
                  prefixIcon: Icon(Icons.email_outlined),
                ),
                validator: (v) {
                  if (v == null || v.trim().isEmpty) return 'مطلوب';
                  if (!v.contains('@')) return 'بريد غير صالح';
                  return null;
                },
              ),
              const SizedBox(height: AppSpacing.lg),
              TextFormField(
                controller: _crCtrl,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'رقم السجل التجاري (اختياري)',
                  prefixIcon: Icon(Icons.assignment_outlined),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              TextFormField(
                controller: _addressCtrl,
                maxLines: 2,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'عنوان المتجر',
                  prefixIcon: Icon(Icons.location_on_outlined),
                  alignLabelWithHint: true,
                ),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
              ),
              const SizedBox(height: AppSpacing.sm),
              OutlinedButton.icon(
                onPressed: _pickLocation,
                icon: const Icon(Icons.map_outlined),
                label: Text(_lat == null
                    ? 'اختيار من الخريطة'
                    : 'تم تحديد الموقع (${_lat!.toStringAsFixed(3)}, ${_lng!.toStringAsFixed(3)})'),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppRadii.md),
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              const SectionHeader(title: 'الأمان'),
              const SizedBox(height: AppSpacing.md),
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
                    (v == null || v.length < 6) ? '6 أحرف على الأقل' : null,
              ),
              const SizedBox(height: AppSpacing.sm),
              CheckboxListTile(
                value: _agreedTerms,
                onChanged: (v) => setState(() => _agreedTerms = v ?? false),
                controlAffinity: ListTileControlAffinity.leading,
                contentPadding: EdgeInsets.zero,
                title: const Text('أوافق على الشروط والأحكام وسياسة الخصوصية'),
              ),
              const SizedBox(height: AppSpacing.lg),
              PrimaryButton(
                label: 'إرسال الطلب',
                loading: _busy,
                onPressed: _busy ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _logoPicker() {
    final hasLogo = _logoUrl != null && _logoUrl!.isNotEmpty;
    return Center(
      child: GestureDetector(
        onTap: _uploadingLogo ? null : _uploadLogo,
        child: Container(
          width: 110,
          height: 110,
          decoration: BoxDecoration(
            color: AppColors.surfaceCream,
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.divider, width: 1.5),
            image: hasLogo
                ? DecorationImage(
                    image: NetworkImage(_logoUrl!),
                    fit: BoxFit.cover,
                  )
                : null,
          ),
          child: hasLogo
              ? null
              : const Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.add_a_photo_outlined,
                        color: AppColors.primaryDark),
                    SizedBox(height: 4),
                    Text('شعار المتجر',
                        style: TextStyle(
                            fontSize: 12, color: AppColors.textSecondary)),
                  ],
                ),
        ),
      ),
    );
  }
}
