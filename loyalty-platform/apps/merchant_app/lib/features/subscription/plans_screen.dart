import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/merchant_repository.dart';
import '../shell/merchant_shell.dart';

/// 2.6 — اختيار الباقة. تجربة مجانية 30 يوم (مميّزة) / شهري $9 / سنوي $99.
/// الدفع يدوي في النسخة الأولى: الزرار يبدأ التجربة، والمدفوع يفعّله الأدمن.
class PlansScreen extends ConsumerStatefulWidget {
  final String merchantId;
  const PlansScreen({super.key, required this.merchantId});

  @override
  ConsumerState<PlansScreen> createState() => _PlansScreenState();
}

class _PlansScreenState extends ConsumerState<PlansScreen> {
  bool _busy = false;

  Future<void> _startTrial() async {
    setState(() => _busy = true);
    final trialEnds = DateTime.now().toUtc().add(const Duration(days: 30));
    try {
      // إنشاء/تحديث اشتراك التجربة. upsert على merchant_id لتجنّب التكرار.
      await ref.read(merchantRepoProvider).upsertSubscription({
        'merchant_id': widget.merchantId,
        'plan': 'trial',
        'status': 'trial',
        'trial_ends_at': trialEnds.toIso8601String(),
      });
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(builder: (_) => const MerchantShell()),
        (route) => false,
      );
    } catch (_) {
      _snack('تعذّر بدء التجربة، حاول مرة أخرى');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showPaidInfo(String planLabel) {
    showDialog<void>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تفعيل الاشتراك'),
        content: Text(
          'لإكمال الاشتراك في الباقة $planLabel، سيتواصل معك فريقنا لإتمام '
          'الدفع (تحويل/فاتورة) وتفعيل الاشتراك يدويًا. ستصلك رسالة فور التفعيل.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('حسنًا'),
          ),
        ],
      ),
    );
  }

  void _snack(String m) {
    if (!mounted) return;
    AppFeedback.toast(context, m, error: true);
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('اختر باقتك')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.xl, vertical: AppSpacing.md),
          children: [
            Text(
              'ابدأ مجانًا، وطوّر متجرك متى شئت',
              style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'كل الباقات تفتح المزايا كاملة — اختر ما يناسبك.',
              style: text.bodyMedium?.copyWith(color: AppColors.textSecondary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.xxl),
            _PlanCard(
              featured: true,
              title: 'تجربة مجانية',
              price: 'مجانًا',
              period: 'لمدة 30 يومًا',
              perks: const [
                'كل المزايا مفتوحة',
                'مسح غير محدود وإضافة نقاط',
                'حملات ومكافآت ومستويات',
              ],
              actionLabel: 'ابدأ التجربة المجانية',
              loading: _busy,
              onAction: _busy ? null : _startTrial,
            )
                .animate()
                .fadeIn(duration: 350.ms)
                .slideY(begin: .08, end: 0),
            const SizedBox(height: AppSpacing.lg),
            _PlanCard(
              title: 'الباقة الشهرية',
              price: r'$9',
              period: 'شهريًا',
              perks: const [
                'كل مزايا المنصة',
                'دعم فني',
                'تجديد شهري مرن',
              ],
              actionLabel: 'اشترك',
              onAction: _busy ? null : () => _showPaidInfo('الشهرية'),
            )
                .animate()
                .fadeIn(duration: 350.ms, delay: 80.ms)
                .slideY(begin: .08, end: 0),
            const SizedBox(height: AppSpacing.lg),
            _PlanCard(
              title: 'الباقة السنوية',
              price: r'$99',
              period: 'سنويًا',
              badge: 'وفّر ~8%',
              perks: const [
                'كل مزايا المنصة',
                'أفضل قيمة على المدى الطويل',
                'دعم فني',
              ],
              actionLabel: 'اشترك',
              onAction: _busy ? null : () => _showPaidInfo('السنوية'),
            )
                .animate()
                .fadeIn(duration: 350.ms, delay: 160.ms)
                .slideY(begin: .08, end: 0),
          ],
        ),
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  final bool featured;
  final String title;
  final String price;
  final String period;
  final String? badge;
  final List<String> perks;
  final String actionLabel;
  final bool loading;
  final VoidCallback? onAction;

  const _PlanCard({
    this.featured = false,
    required this.title,
    required this.price,
    required this.period,
    this.badge,
    required this.perks,
    required this.actionLabel,
    this.loading = false,
    required this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    const onFeatured = AppColors.onPrimary;
    final titleColor = featured ? onFeatured : null;
    final periodColor =
        featured ? onFeatured.withValues(alpha: .7) : AppColors.textSecondary;
    final perkColor = featured ? onFeatured : null;
    final checkColor = featured ? AppColors.onPrimary : AppColors.success;

    return AppCard(
      gradient: featured ? AppColors.goldGradient : null,
      border: featured
          ? null
          : Border.all(color: AppColors.divider, width: 1.5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(title,
                    style: text.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800, color: titleColor)),
              ),
              if (featured)
                const _Pill(
                    label: 'الأكثر شيوعًا',
                    bg: AppColors.onPrimary,
                    fg: AppColors.primary)
              else if (badge != null)
                _Pill(
                    label: badge!,
                    bg: AppColors.success,
                    fg: Colors.white),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(price,
                  style: text.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w900, color: titleColor)),
              const SizedBox(width: AppSpacing.sm),
              Text(period,
                  style: text.bodyMedium?.copyWith(color: periodColor)),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          ...perks.map(
            (p) => Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: Row(
                children: [
                  AppIcon(Icons.check_circle_rounded,
                      size: 20, color: checkColor),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                      child: Text(p,
                          style: text.bodyMedium?.copyWith(color: perkColor))),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          PrimaryButton(
            label: actionLabel,
            loading: loading,
            variant:
                featured ? AppButtonVariant.primary : AppButtonVariant.secondary,
            onPressed: onAction,
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  final String label;
  final Color bg;
  final Color fg;
  const _Pill({required this.label, required this.bg, required this.fg});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppTheme.pill),
      ),
      child: Text(label,
          style: TextStyle(
              color: fg, fontSize: 12, fontWeight: FontWeight.w700)),
    );
  }
}
