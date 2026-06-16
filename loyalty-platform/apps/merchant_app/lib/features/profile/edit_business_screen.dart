import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/media_storage.dart';
import '../../core/merchant_providers.dart';

/// أنواع النشاط المتاحة في القائمة المنسدلة.
const _businessTypes = ['مطعم', 'كافيه', 'متجر', 'صالون', 'أخرى'];

/// يجلب صف التاجر الحالي لتعبئة النموذج.
final _editMerchantProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return Supabase.instance.client
      .from('merchants')
      .select()
      .eq('id', staff.merchantId)
      .single();
});

/// تعديل بيانات النشاط (merchants): الاسم/النوع/العنوان/التواصل/الشعار.
class EditBusinessProfileScreen extends ConsumerStatefulWidget {
  const EditBusinessProfileScreen({super.key});

  @override
  ConsumerState<EditBusinessProfileScreen> createState() =>
      _EditBusinessProfileScreenState();
}

class _EditBusinessProfileScreenState
    extends ConsumerState<EditBusinessProfileScreen> {
  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_editMerchantProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('تعديل بيانات المتجر')),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل بيانات النشاط',
          onRetry: () => ref.invalidate(_editMerchantProvider),
        ),
        data: (merchant) => _EditForm(initial: merchant),
      ),
    );
  }
}

class _EditForm extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const _EditForm({required this.initial});

  @override
  ConsumerState<_EditForm> createState() => _EditFormState();
}

class _EditFormState extends ConsumerState<_EditForm> {
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _businessName;
  late final TextEditingController _address;
  late final TextEditingController _phone;
  late final TextEditingController _email;

  late String _businessType;
  String? _logoUrl;

  bool _busy = false;
  bool _uploadingLogo = false;

  @override
  void initState() {
    super.initState();
    final m = widget.initial;
    _businessName =
        TextEditingController(text: m['business_name'] as String? ?? '');
    _address = TextEditingController(text: m['address'] as String? ?? '');
    _phone = TextEditingController(text: m['phone'] as String? ?? '');
    _email = TextEditingController(text: m['email'] as String? ?? '');
    final type = m['business_type'] as String?;
    _businessType =
        (type != null && _businessTypes.contains(type)) ? type : 'أخرى';
    _logoUrl = m['logo_url'] as String?;
  }

  @override
  void dispose() {
    _businessName.dispose();
    _address.dispose();
    _phone.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _changeLogo() async {
    setState(() => _uploadingLogo = true);
    try {
      final url = await MediaStorage.pickAndUpload(
        bucket: 'merchant-media',
        folder: 'logos',
      );
      if (url != null) {
        setState(() => _logoUrl = url);
      } else if (mounted) {
        AppFeedback.toast(context, 'لم يتم اختيار صورة', error: true);
      }
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      await Supabase.instance.client.from('merchants').update({
        'business_name': _businessName.text.trim(),
        'business_type': _businessType,
        'address': _address.text.trim(),
        'phone': _phone.text.trim(),
        'email': _email.text.trim(),
        'logo_url': _logoUrl,
      }).eq('id', staff.merchantId);

      if (mounted) {
        await AppFeedback.success(
          context,
          title: 'تم حفظ البيانات',
          message: 'حُدّثت بيانات متجرك بنجاح.',
        );
      }
      if (mounted) Navigator.of(context).pop();
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر الحفظ', error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasLogo = _logoUrl != null && _logoUrl!.isNotEmpty;

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          // --- الشعار ---
          Center(
            child: Column(
              children: [
                Container(
                  width: 110,
                  height: 110,
                  decoration: BoxDecoration(
                    color: AppColors.surfaceCream,
                    shape: BoxShape.circle,
                    image: hasLogo
                        ? DecorationImage(
                            image: NetworkImage(_logoUrl!),
                            fit: BoxFit.cover,
                          )
                        : null,
                  ),
                  child: hasLogo
                      ? null
                      : const Icon(Icons.storefront_rounded,
                          size: 48, color: AppColors.primaryDark),
                ),
                const SizedBox(height: AppSpacing.md),
                OutlinedButton.icon(
                  onPressed: _uploadingLogo ? null : _changeLogo,
                  icon: _uploadingLogo
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2),
                        )
                      : const Icon(Icons.image_outlined),
                  label: const Text('تغيير الشعار'),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xl),

          // --- بيانات النشاط ---
          const SectionHeader(title: 'بيانات النشاط'),
          const SizedBox(height: AppSpacing.sm),
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _businessName,
                  decoration:
                      const InputDecoration(labelText: 'اسم النشاط'),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                ),
                const SizedBox(height: AppSpacing.md),
                DropdownButtonFormField<String>(
                  value: _businessType,
                  decoration:
                      const InputDecoration(labelText: 'نوع النشاط'),
                  items: _businessTypes
                      .map((t) =>
                          DropdownMenuItem(value: t, child: Text(t)))
                      .toList(),
                  onChanged: (v) =>
                      setState(() => _businessType = v ?? _businessType),
                ),
                const SizedBox(height: AppSpacing.md),
                TextFormField(
                  controller: _address,
                  decoration: const InputDecoration(labelText: 'العنوان'),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xl),

          // --- التواصل ---
          const SectionHeader(title: 'بيانات التواصل'),
          const SizedBox(height: AppSpacing.sm),
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration:
                      const InputDecoration(labelText: 'رقم الهاتف'),
                ),
                const SizedBox(height: AppSpacing.md),
                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                      labelText: 'البريد الإلكتروني'),
                  validator: (v) {
                    final t = v?.trim() ?? '';
                    if (t.isEmpty) return null;
                    if (!t.contains('@') || !t.contains('.')) {
                      return 'بريد إلكتروني غير صالح';
                    }
                    return null;
                  },
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xxl),
          PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
          const SizedBox(height: AppSpacing.lg),
        ],
      ),
    );
  }
}
