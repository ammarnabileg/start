import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../data/repositories/rewards_repository.dart';
import '../../data/repositories/scan_repository.dart';

/// ملف العميل بعد المسح — قلب شغل الكاشير. الأزرار الأربعة تستدعي Edge Functions.
class CustomerProfileScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> data; // ناتج verify-qr
  const CustomerProfileScreen({super.key, required this.data});
  @override
  ConsumerState<CustomerProfileScreen> createState() =>
      _CustomerProfileScreenState();
}

class _CustomerProfileScreenState extends ConsumerState<CustomerProfileScreen> {
  late Map<String, dynamic> d = widget.data;
  bool _busy = false;

  String get _userId => (d['user']['id']) as String;

  Future<void> _call(String fn, Map<String, dynamic> body,
      {required String okMsg, String? okDetail, String? idempotencyKey}) async {
    setState(() => _busy = true);
    try {
      final res = await ref
          .read(scanRepoProvider)
          .invoke(fn, body, idempotencyKey: idempotencyKey);
      if (res.data?['error'] != null) {
        _snack(res.data['error'] as String);
      } else {
        if (res.data?['available_points'] != null) {
          setState(() => d['available_points'] = res.data['available_points']);
        }
        if (mounted) {
          await AppFeedback.success(context, title: okMsg, message: okDetail);
        }
      }
    } on FunctionException catch (e) {
      // 409: عملية بنفس مفتاح الازدواج قيد المعالجة — لا نعيد الإرسال.
      if (e.status == 409) {
        _snack('عملية قيد المعالجة');
      } else {
        _snack('تعذّر تنفيذ العملية، تحقق من الاتصال');
      }
    } catch (_) {
      _snack('تعذّر تنفيذ العملية، تحقق من الاتصال');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String m) {
    if (!mounted) return;
    AppFeedback.toast(context, m, error: true);
  }

  Future<void> _addPoints() async {
    final pts = await showModalBottomSheet<int>(
      context: context,
      builder: (_) => const _QuickPointsSheet(),
    );
    if (pts != null) {
      // مفتاح ازدواج واحد يُولَّد لحظة تأكيد المبلغ (قبل أي await للشبكة)،
      // ويُعاد استخدامه لو حصلت إعادة محاولة لمنع الخصم/الإضافة المزدوجة.
      final idempotencyKey = genIdempotencyKey();
      await _call('add-points', {'user_id': _userId, 'points': pts},
          okMsg: 'تمت إضافة $pts نقطة',
          okDetail: 'أُضيفت النقاط إلى رصيد العميل بنجاح.',
          idempotencyKey: idempotencyKey);
    }
  }

  /// استبدال مكافأة من قائمة مكافآت المتجر (يخضع لإعداد تأكيد الطرفين).
  Future<void> _redeemReward() async {
    final merchantId = d['merchant_id'] as String?;
    List<dynamic> rewards;
    try {
      rewards = await ref
          .read(rewardsRepoProvider)
          .fetchActiveRewards(merchantId: merchantId);
    } catch (_) {
      _snack('تعذّر تحميل المكافآت');
      return;
    }
    if (!mounted) return;
    final rewardId = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.all(16),
        children: [
          Text('اختر مكافأة', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          for (final r in rewards)
            ListTile(
              title: Text(r['name'] as String),
              trailing: Text('${r['points_cost']} نقطة'),
              onTap: () => Navigator.pop(context, r['id'] as String),
            ),
        ],
      ),
    );
    if (rewardId == null) return;
    await _call('staff-redeem', {'user_id': _userId, 'reward_id': rewardId},
        okMsg: 'تم الاستبدال', idempotencyKey: genIdempotencyKey());
  }

  /// تطبيق كوبون بإدخال الكود.
  Future<void> _applyCoupon() async {
    final ctrl = TextEditingController();
    final code = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تطبيق كوبون'),
        content: TextField(
          controller: ctrl,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(labelText: 'كود الكوبون'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx), child: const Text('إلغاء')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, ctrl.text.trim()),
              child: const Text('تطبيق')),
        ],
      ),
    );
    if (code == null || code.isEmpty) return;
    await _call('apply-coupon', {'user_id': _userId, 'code': code},
        okMsg: 'تم تطبيق الكوبون', idempotencyKey: genIdempotencyKey());
  }

  @override
  Widget build(BuildContext context) {
    final user = d['user'] as Map<String, dynamic>;
    final isNew = d['is_new_customer'] == true;
    final visited = d['visited_today'] == true;

    return Scaffold(
      appBar: AppBar(title: const Text('ملف العميل')),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              AppCard(
                child: Row(
                  children: [
                    CircleAvatar(
                        radius: 26,
                        backgroundColor: AppColors.primaryLight,
                        child: Text(
                            (user['name'] as String).characters.first,
                            style: const TextStyle(
                                fontSize: 20, fontWeight: FontWeight.w800))),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(user['name'] as String,
                              style: Theme.of(context).textTheme.titleLarge),
                          if (d['level_name'] != null)
                            Text(d['level_name'] as String,
                                style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                    if (isNew)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                            color: AppColors.success.withValues(alpha: .15),
                            borderRadius: BorderRadius.circular(AppRadii.md)),
                        child: const Text('عميل جديد',
                            style: TextStyle(
                                color: AppColors.success,
                                fontWeight: FontWeight.w700)),
                      ),
                  ],
                ),
              ).animate().fadeIn(duration: 300.ms).slideY(begin: .06, end: 0),
              const SizedBox(height: AppSpacing.lg),
              // حالة العميل كصفّ بطاقات إحصائية.
              Row(
                children: [
                  Expanded(
                    child: StatCard(
                      icon: Icons.stars_rounded,
                      label: 'النقاط المتاحة',
                      value: '${d['available_points']}',
                      highlight: true,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: StatCard(
                      icon: Icons.military_tech_rounded,
                      label: 'المستوى',
                      value: (d['level_name'] as String?) ?? '—',
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: StatCard(
                      icon: visited
                          ? Icons.event_available_rounded
                          : Icons.event_busy_rounded,
                      label: 'زيارة اليوم',
                      value: visited ? 'تم' : 'لم تُسجّل',
                      accent: visited ? AppColors.success : null,
                    ),
                  ),
                ],
              )
                  .animate()
                  .fadeIn(duration: 300.ms, delay: 80.ms)
                  .slideY(begin: .06, end: 0),
              const SizedBox(height: AppSpacing.xl),
              const SectionHeader(title: 'إجراءات'),
              const SizedBox(height: AppSpacing.md),
              GridView.count(
                crossAxisCount: context.responsive(mobile: 2, tablet: 3),
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: AppSpacing.md,
                crossAxisSpacing: AppSpacing.md,
                childAspectRatio: 1.6,
                children: [
                  _ActionTile(
                      icon: Icons.event_available_rounded,
                      label: 'تسجيل زيارة',
                      onTap: () => _call('record-visit', {'user_id': _userId},
                          okMsg: 'تم تسجيل الزيارة',
                          okDetail: 'سُجّلت زيارة العميل لهذا اليوم.')),
                  _ActionTile(
                      icon: Icons.add_circle_outline_rounded,
                      label: 'إضافة نقاط',
                      onTap: _addPoints),
                  _ActionTile(
                      icon: Icons.redeem_rounded,
                      label: 'استبدال مكافأة',
                      onTap: _redeemReward),
                  _ActionTile(
                      icon: Icons.confirmation_num_outlined,
                      label: 'تطبيق كوبون',
                      onTap: _applyCoupon),
                ]
                    .animate(interval: 60.ms)
                    .fadeIn(duration: 300.ms)
                    .slideY(begin: .08, end: 0),
              ),
            ],
          ),
          if (_busy) const ColoredBox(color: Colors.black26, child: LoadingView()),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _ActionTile(
      {required this.icon, required this.label, required this.onTap});
  @override
  Widget build(BuildContext context) => AppCard(
        onTap: onTap,
        child: Row(
          children: [
            AppIconBadge(icon, size: 44, iconSize: 24),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Text(label,
                  style: Theme.of(context).textTheme.titleMedium,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis),
            ),
          ],
        ),
      );
}

class _QuickPointsSheet extends StatelessWidget {
  const _QuickPointsSheet();
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.xl, AppSpacing.lg, AppSpacing.xl, AppSpacing.xxl),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('إضافة نقاط', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: AppSpacing.sm),
          Text('اختر عدد النقاط لإضافتها لرصيد العميل',
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: AppColors.textSecondary)),
          const SizedBox(height: AppSpacing.lg),
          Wrap(
            spacing: AppSpacing.md,
            runSpacing: AppSpacing.md,
            alignment: WrapAlignment.center,
            children: [10, 20, 50, 100]
                .map((p) => InkWell(
                      onTap: () => Navigator.pop(context, p),
                      borderRadius: BorderRadius.circular(AppRadii.pill),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.xl, vertical: AppSpacing.md),
                        decoration: BoxDecoration(
                          color: AppColors.primaryLight,
                          borderRadius: BorderRadius.circular(AppRadii.pill),
                        ),
                        child: Text('+$p',
                            style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: AppColors.onPrimary)),
                      ),
                    ))
                .toList(),
          ),
        ],
      ),
    );
  }
}
