import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/merchant_repository.dart';

/// تسميات الخطط بالعربية.
const _planLabels = {
  'trial': 'تجربة مجانية',
  'monthly': 'اشتراك شهري',
  'yearly': 'اشتراك سنوي',
};

/// تسميات الحالة بالعربية.
const _statusLabels = {
  'active': 'نشط',
  'trialing': 'تجربة',
  'trial': 'تجربة',
  'past_due': 'متأخر السداد',
  'canceled': 'ملغى',
  'expired': 'منتهٍ',
  'inactive': 'غير نشط',
};

/// يجلب اشتراك التاجر الحالي (إن وُجد).
final _manageSubscriptionProvider =
    FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(merchantRepoProvider).fetchSubscription(staff.merchantId);
});

/// خيار خطة معروض كبطاقة.
class _PlanOption {
  final String key;
  final String title;
  final String price;
  final String note;
  final IconData icon;
  const _PlanOption({
    required this.key,
    required this.title,
    required this.price,
    required this.note,
    required this.icon,
  });
}

const _planOptions = [
  _PlanOption(
    key: 'trial',
    title: 'تجربة مجانية',
    price: '٣٠ يومًا',
    note: 'جرّب كل الميزات دون أي رسوم.',
    icon: Icons.rocket_launch_outlined,
  ),
  _PlanOption(
    key: 'monthly',
    title: 'اشتراك شهري',
    price: r'$9 / شهريًا',
    note: 'مرونة كاملة مع إمكانية الإلغاء في أي وقت.',
    icon: Icons.calendar_month_outlined,
  ),
  _PlanOption(
    key: 'yearly',
    title: 'اشتراك سنوي',
    price: r'$99 / سنويًا',
    note: 'وفّر أكثر مع الدفع السنوي.',
    icon: Icons.workspace_premium_outlined,
  ),
];

/// إدارة الاشتراك — الدفع يدوي في الإصدار الأول (تواصل مع الدعم).
class ManageSubscriptionScreen extends ConsumerStatefulWidget {
  const ManageSubscriptionScreen({super.key});

  @override
  ConsumerState<ManageSubscriptionScreen> createState() =>
      _ManageSubscriptionScreenState();
}

class _ManageSubscriptionScreenState
    extends ConsumerState<ManageSubscriptionScreen> {
  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_manageSubscriptionProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('إدارة الاشتراك')),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل بيانات الاشتراك',
          onRetry: () => ref.invalidate(_manageSubscriptionProvider),
        ),
        data: (sub) {
          if (sub == null) {
            return EmptyView(
              title: 'لا يوجد اشتراك بعد',
              message:
                  'ابدأ تجربتك المجانية لمدة ٣٠ يومًا وتمتّع بكل الميزات. تواصل مع الدعم لتفعيل اشتراكك.',
              icon: Icons.workspace_premium_outlined,
              actionLabel: 'تواصل مع الدعم',
              onAction: () => _showContactNote(context),
            );
          }
          return _SubscriptionBody(sub: sub);
        },
      ),
    );
  }
}

void _showContactNote(BuildContext context) {
  AppFeedback.toast(
    context,
    'للاشتراك أو الترقية تواصل مع الدعم: support@wataddigital.com',
  );
}

class _SubscriptionBody extends StatelessWidget {
  final Map<String, dynamic> sub;
  const _SubscriptionBody({required this.sub});

  @override
  Widget build(BuildContext context) {
    final df = DateFormat('yyyy/MM/dd');
    final plan = sub['plan'] as String?;
    final status = sub['status'] as String?;
    final trialEnds = sub['trial_ends_at'] as String?;
    final periodEnd = sub['current_period_end'] as String?;

    String? dateLine;
    if (trialEnds != null) {
      dateLine = 'تنتهي التجربة: ${df.format(DateTime.parse(trialEnds))}';
    } else if (periodEnd != null) {
      dateLine = 'التجديد القادم: ${df.format(DateTime.parse(periodEnd))}';
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        const SectionHeader(title: 'اشتراكك الحالي'),
        const SizedBox(height: AppSpacing.sm),
        AppCard(
          gradient: AppColors.goldGradient,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const AppIcon(Icons.workspace_premium_rounded,
                      color: AppColors.onPrimary),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      plan == null
                          ? 'خطة غير محدّدة'
                          : (_planLabels[plan] ?? plan),
                      style: Theme.of(context)
                          .textTheme
                          .titleLarge
                          ?.copyWith(color: AppColors.onPrimary),
                    ),
                  ),
                  if (status != null)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.onPrimary.withValues(alpha: .12),
                        borderRadius: BorderRadius.circular(AppRadii.pill),
                      ),
                      child: Text(
                        _statusLabels[status] ?? status,
                        style: const TextStyle(
                          color: AppColors.onPrimary,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ),
                ],
              ),
              if (dateLine != null) ...[
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    const AppIcon(Icons.event_outlined,
                        size: 18, color: AppColors.onPrimary),
                    const SizedBox(width: 6),
                    Text(
                      dateLine,
                      style: const TextStyle(
                          color: AppColors.onPrimary,
                          fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.xl),

        // --- خيارات الخطط ---
        const SectionHeader(title: 'الخطط المتاحة'),
        const SizedBox(height: AppSpacing.sm),
        ..._planOptions.map((p) {
          final isCurrent = p.key == plan;
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.md),
            child: AppCard(
              border: isCurrent
                  ? Border.all(color: AppColors.primaryDark, width: 2)
                  : null,
              child: Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: const BoxDecoration(
                      color: AppColors.surfaceCream,
                      shape: BoxShape.circle,
                    ),
                    child: AppIcon(p.icon, color: AppColors.primaryDark),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(p.title,
                                style: Theme.of(context)
                                    .textTheme
                                    .titleMedium),
                            if (isCurrent) ...[
                              const SizedBox(width: 8),
                              const AppIcon(Icons.check_circle,
                                  size: 18, color: AppColors.success),
                            ],
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(p.price,
                            style: const TextStyle(
                                color: AppColors.primaryDark,
                                fontWeight: FontWeight.w700)),
                        const SizedBox(height: 4),
                        Text(p.note,
                            style:
                                Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
        const SizedBox(height: AppSpacing.sm),

        // --- الدفع اليدوي ---
        AppCard(
          color: AppColors.infoBg,
          child: Row(
            children: [
              const AppIcon(Icons.support_agent_outlined,
                  color: AppColors.info),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Text(
                  'الدفع يتم يدويًا في هذه المرحلة. للاشتراك أو الترقية تواصل مع الدعم وسنفعّل خطتك خلال وقت قصير.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        PrimaryButton(
          label: 'للاشتراك أو الترقية تواصل مع الدعم',
          icon: Icons.support_agent_outlined,
          onPressed: () => _showContactNote(context),
        ),
        const SizedBox(height: AppSpacing.lg),
      ],
    );
  }
}
