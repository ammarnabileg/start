import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/merchant_repository.dart';

/// 2.14 — الإعدادات المتقدمة (Merchant Settings) — "أوبشن في كل حاجة".
class MerchantSettingsScreen extends ConsumerWidget {
  const MerchantSettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(merchantSettingsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('الإعدادات')),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الإعدادات',
          onRetry: () => ref.invalidate(merchantSettingsProvider),
        ),
        data: (settings) => _SettingsForm(initial: settings),
      ),
    );
  }
}

class _SettingsForm extends ConsumerStatefulWidget {
  final MerchantSettings initial;
  const _SettingsForm({required this.initial});
  @override
  ConsumerState<_SettingsForm> createState() => _SettingsFormState();
}

class _SettingsFormState extends ConsumerState<_SettingsForm> {
  final _formKey = GlobalKey<FormState>();

  late PointsScope _scope;
  late bool _enableVisits,
      _enablePoints,
      _enableRewards,
      _enableLevels,
      _enableCoupons,
      _enableReferral,
      _enableBirthday,
      _enableProximity,
      _enableGpsCheckin,
      _enableAnnouncements;
  late bool _oneVisitPerDay, _requireRedemptionConfirm;

  late final TextEditingController _maxPointsPerTxn;
  late final TextEditingController _dailyPointsPerStaff;
  late final TextEditingController _redemptionConfirmThreshold;
  late final TextEditingController _qrRotationSeconds;
  late final TextEditingController _redemptionWindowMinutes;
  late final TextEditingController _earnRate;
  late final TextEditingController _brandName;
  late final TextEditingController _primaryColorHex;

  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final s = widget.initial;
    _scope = s.pointsScope;
    _enableVisits = s.enableVisits;
    _enablePoints = s.enablePoints;
    _enableRewards = s.enableRewards;
    _enableLevels = s.enableLevels;
    _enableCoupons = s.enableCoupons;
    _enableReferral = s.enableReferral;
    _enableBirthday = s.enableBirthday;
    _enableProximity = s.enableProximity;
    _enableGpsCheckin = s.enableGpsCheckin;
    _enableAnnouncements = s.enableAnnouncements;
    _oneVisitPerDay = s.oneVisitPerDay;
    _requireRedemptionConfirm = s.requireRedemptionConfirm;
    _maxPointsPerTxn =
        TextEditingController(text: s.maxPointsPerTxn.toString());
    _dailyPointsPerStaff =
        TextEditingController(text: s.dailyPointsPerStaff.toString());
    _redemptionConfirmThreshold =
        TextEditingController(text: s.redemptionConfirmThreshold.toString());
    _qrRotationSeconds =
        TextEditingController(text: s.qrRotationSeconds.toString());
    _redemptionWindowMinutes =
        TextEditingController(text: s.redemptionWindowMinutes.toString());
    _earnRate =
        TextEditingController(text: s.earnRatePerCurrency.toString());
    _brandName = TextEditingController(text: s.brandName ?? '');
    _primaryColorHex =
        TextEditingController(text: s.primaryColorHex ?? '');
  }

  @override
  void dispose() {
    _maxPointsPerTxn.dispose();
    _dailyPointsPerStaff.dispose();
    _redemptionConfirmThreshold.dispose();
    _qrRotationSeconds.dispose();
    _redemptionWindowMinutes.dispose();
    _earnRate.dispose();
    _brandName.dispose();
    _primaryColorHex.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final settings = MerchantSettings(
        merchantId: widget.initial.merchantId,
        pointsScope: _scope,
        enableVisits: _enableVisits,
        enablePoints: _enablePoints,
        enableRewards: _enableRewards,
        enableLevels: _enableLevels,
        enableCoupons: _enableCoupons,
        enableReferral: _enableReferral,
        enableBirthday: _enableBirthday,
        enableProximity: _enableProximity,
        enableGpsCheckin: _enableGpsCheckin,
        enableAnnouncements: _enableAnnouncements,
        maxPointsPerTxn: int.tryParse(_maxPointsPerTxn.text.trim()) ?? 500,
        dailyPointsPerStaff:
            int.tryParse(_dailyPointsPerStaff.text.trim()) ?? 5000,
        oneVisitPerDay: _oneVisitPerDay,
        requireRedemptionConfirm: _requireRedemptionConfirm,
        redemptionConfirmThreshold:
            int.tryParse(_redemptionConfirmThreshold.text.trim()) ?? 0,
        qrRotationSeconds:
            int.tryParse(_qrRotationSeconds.text.trim()) ?? 30,
        redemptionWindowMinutes:
            int.tryParse(_redemptionWindowMinutes.text.trim()) ?? 5,
        earnRatePerCurrency:
            double.tryParse(_earnRate.text.trim()) ?? 1,
        primaryColorHex: _primaryColorHex.text.trim().isEmpty
            ? null
            : _primaryColorHex.text.trim(),
        brandName:
            _brandName.text.trim().isEmpty ? null : _brandName.text.trim(),
      );

      await ref.read(merchantRepoProvider).upsertSettings(settings.toJson());

      ref.invalidate(merchantSettingsProvider);
      if (mounted) {
        await AppFeedback.success(
          context,
          title: 'تم حفظ الإعدادات',
          message: 'طُبّقت تغييراتك على المتجر.',
        );
      }
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
    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // --- نطاق النقاط ---
          const SectionHeader(title: 'نطاق النقاط'),
          const SizedBox(height: 8),
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                RadioListTile<PointsScope>(
                  contentPadding: EdgeInsets.zero,
                  value: PointsScope.merchant,
                  groupValue: _scope,
                  onChanged: (v) => setState(() => _scope = v!),
                  title: const Text('مشترك بين كل الفروع'),
                  subtitle:
                      const Text('رصيد ومستوى واحد للعميل عند المتجر كله.'),
                ),
                RadioListTile<PointsScope>(
                  contentPadding: EdgeInsets.zero,
                  value: PointsScope.branch,
                  groupValue: _scope,
                  onChanged: (v) => setState(() => _scope = v!),
                  title: const Text('منفصل لكل فرع'),
                  subtitle:
                      const Text('رصيد ومستوى مستقل للعميل عند كل فرع.'),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.warningBg,
                    borderRadius: BorderRadius.circular(AppRadii.md),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.warning_amber_rounded,
                          color: AppColors.warning, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'تغيير النطاق يؤثّر على طريقة احتساب المحافظ الجديدة.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // --- الميزات ---
          const SectionHeader(title: 'تفعيل الميزات'),
          const SizedBox(height: 8),
          AppCard(
            child: Column(
              children: [
                _FeatureSwitch('الزيارات', _enableVisits,
                    (v) => setState(() => _enableVisits = v)),
                _FeatureSwitch('النقاط', _enablePoints,
                    (v) => setState(() => _enablePoints = v)),
                _FeatureSwitch('المكافآت', _enableRewards,
                    (v) => setState(() => _enableRewards = v)),
                _FeatureSwitch('المستويات', _enableLevels,
                    (v) => setState(() => _enableLevels = v)),
                _FeatureSwitch('الكوبونات', _enableCoupons,
                    (v) => setState(() => _enableCoupons = v)),
                _FeatureSwitch('الإحالة', _enableReferral,
                    (v) => setState(() => _enableReferral = v)),
                _FeatureSwitch('مكافأة الميلاد', _enableBirthday,
                    (v) => setState(() => _enableBirthday = v)),
                _FeatureSwitch('إشعار القرب', _enableProximity,
                    (v) => setState(() => _enableProximity = v)),
                _FeatureSwitch('تسجيل الدخول بالموقع (GPS)',
                    _enableGpsCheckin,
                    (v) => setState(() => _enableGpsCheckin = v)),
                _FeatureSwitch('الإعلانات', _enableAnnouncements,
                    (v) => setState(() => _enableAnnouncements = v)),
              ],
            ),
          ),

          // --- حدود الأمان ---
          const SectionHeader(title: 'حدود الأمان'),
          const SizedBox(height: 8),
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _NumField(
                    controller: _maxPointsPerTxn,
                    label: 'سقف النقاط للعملية الواحدة'),
                const SizedBox(height: 12),
                _NumField(
                    controller: _dailyPointsPerStaff,
                    label: 'السقف اليومي لكل موظف'),
                const SizedBox(height: 12),
                _NumField(
                    controller: _qrRotationSeconds,
                    label: 'مدة تجدّد رمز العميل (ثانية)'),
                const SizedBox(height: 12),
                _NumField(
                    controller: _redemptionWindowMinutes,
                    label: 'عمر كود الاستلام (دقيقة)'),
                _FeatureSwitch('زيارة واحدة في اليوم', _oneVisitPerDay,
                    (v) => setState(() => _oneVisitPerDay = v)),
                _FeatureSwitch(
                    'تأكيد العميل على الاستبدال',
                    _requireRedemptionConfirm,
                    (v) =>
                        setState(() => _requireRedemptionConfirm = v)),
                if (_requireRedemptionConfirm)
                  _NumField(
                      controller: _redemptionConfirmThreshold,
                      label: 'التأكيد فقط فوق X نقطة (0 = دائمًا)'),
              ],
            ),
          ),

          // --- الاكتساب ---
          const SectionHeader(title: 'الاكتساب'),
          const SizedBox(height: 8),
          AppCard(
            child: _NumField(
              controller: _earnRate,
              label: 'نقاط لكل وحدة عملة',
              decimal: true,
            ),
          ),

          // --- العلامة ---
          const SectionHeader(title: 'العلامة (White-Label)'),
          const SizedBox(height: 8),
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _brandName,
                  decoration:
                      const InputDecoration(labelText: 'اسم العلامة'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _primaryColorHex,
                  decoration: const InputDecoration(
                    labelText: 'اللون الأساسي (Hex)',
                    hintText: '#FFC42E',
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),
          PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}

class _FeatureSwitch extends StatelessWidget {
  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;
  const _FeatureSwitch(this.label, this.value, this.onChanged);
  @override
  Widget build(BuildContext context) => SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        value: value,
        onChanged: onChanged,
      );
}

class _NumField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final bool decimal;
  const _NumField(
      {required this.controller, required this.label, this.decimal = false});
  @override
  Widget build(BuildContext context) => TextFormField(
        controller: controller,
        keyboardType: TextInputType.numberWithOptions(decimal: decimal),
        decoration: InputDecoration(labelText: label),
        validator: (v) {
          final t = v?.trim() ?? '';
          if (t.isEmpty) return 'مطلوب';
          final n = decimal ? double.tryParse(t) : int.tryParse(t);
          if (n == null) return 'أدخل رقمًا صحيحًا';
          return null;
        },
      );
}
