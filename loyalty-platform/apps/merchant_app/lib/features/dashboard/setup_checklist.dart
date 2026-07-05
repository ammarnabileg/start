import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/supabase_providers.dart';
import '../management/branches_screen.dart';
import '../management/levels_screen.dart';
import '../management/rewards_screen.dart';
import '../management/staff_screen.dart';
import '../profile/edit_business_screen.dart';

/// حالة إعداد المتجر (لقائمة الإعداد الموجّهة).
final merchantSetupProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final res = await ref
      .read(supabaseClientProvider)
      .rpc('merchant_setup_status', params: {'p_merchant': staff.merchantId});
  return Map<String, dynamic>.from(res as Map);
});

class _Step {
  final String key;
  final String title;
  final IconData icon;
  final Widget Function() screen;
  const _Step(this.key, this.title, this.icon, this.screen);
}

/// قائمة إعداد موجّهة تظهر في لوحة التحكم حتى يكتمل الإعداد ثم تختفي تلقائيًا.
class SetupChecklist extends ConsumerWidget {
  const SetupChecklist({super.key});

  static final _steps = <_Step>[
    _Step('has_logo', 'اضبط شعار واسم متجرك', Icons.storefront_outlined,
        () => const EditBusinessProfileScreen()),
    _Step('has_branch', 'أضف فرعك الأول', Icons.store_mall_directory_outlined,
        () => const BranchesScreen()),
    _Step('has_reward', 'أنشئ أول مكافأة', Icons.card_giftcard_outlined,
        () => const RewardsManagementScreen()),
    _Step('has_level', 'حدّد مستويات الولاء', Icons.workspace_premium_outlined,
        () => const LevelsScreen()),
    _Step('has_staff', 'أضف موظف كاشير', Icons.person_add_alt_1_rounded,
        () => const StaffScreen()),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(merchantSetupProvider);
    return async.maybeWhen(
      orElse: () => const SizedBox.shrink(),
      data: (status) {
        final done = _steps.where((s) => status[s.key] == true).length;
        if (done == _steps.length) return const SizedBox.shrink(); // اكتمل → يختفي
        return Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          child: AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [
                  const AppIconBadge(Icons.rocket_launch_rounded, size: 44),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('جهّز متجرك',
                            style: Theme.of(context).textTheme.titleMedium),
                        Text('$done من ${_steps.length} خطوات مكتملة',
                            style: const TextStyle(
                                color: AppColors.textSecondary, fontSize: 12)),
                      ],
                    ),
                  ),
                ]),
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: done / _steps.length,
                    minHeight: 8,
                    backgroundColor: AppColors.surfaceCream,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(height: 6),
                for (final s in _steps)
                  _StepTile(
                    title: s.title,
                    icon: s.icon,
                    done: status[s.key] == true,
                    onTap: () async {
                      await Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => s.screen()));
                      ref.invalidate(merchantSetupProvider);
                    },
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _StepTile extends StatelessWidget {
  final String title;
  final IconData icon;
  final bool done;
  final VoidCallback onTap;
  const _StepTile(
      {required this.title,
      required this.icon,
      required this.done,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: done ? null : onTap,
      borderRadius: BorderRadius.circular(AppRadii.md),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(children: [
          AppIcon(
              done ? Icons.check_circle_rounded : icon,
              color: done ? AppColors.success : AppColors.primaryDark,
              size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Text(title,
                style: TextStyle(
                    fontWeight: FontWeight.w600,
                    color: done ? AppColors.textSecondary : AppColors.textPrimary,
                    decoration:
                        done ? TextDecoration.lineThrough : null)),
          ),
          if (!done)
            const AppIcon(Icons.chevron_left_rounded,
                color: AppColors.textSecondary),
        ]),
      ),
    );
  }
}
