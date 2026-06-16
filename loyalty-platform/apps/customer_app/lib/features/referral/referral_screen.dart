import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:share_plus/share_plus.dart';

import '../../data/repositories/referral_repository.dart';
import '../qr/qr_providers.dart';

/// إحالات العميل (مَن دعاهم + حالتهم).
final myReferralsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  return ref.read(referralRepoProvider).myReferrals();
});

/// الإحالة (Referral) — راجع CUSTOMER_APP.md 1.16.
class ReferralScreen extends ConsumerWidget {
  const ReferralScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final userAsync = ref.watch(currentUserProvider);
    final referralsAsync = ref.watch(myReferralsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar:
          AppBar(title: const Text('دعوة صديق'), centerTitle: true),
      body: userAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل كود الإحالة',
            onRetry: () => ref.invalidate(currentUserProvider)),
        data: (user) => ListView(
          padding: AppSpacing.screen,
          children: [
            AppCard(
              gradient: AppColors.goldGradient,
              child: Column(
                children: [
                  Text('كود الإحالة الخاص بك',
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(color: AppColors.onPrimary)),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    user.referralCode,
                    style: theme.textTheme.displayLarge?.copyWith(
                          letterSpacing: 4,
                          fontWeight: FontWeight.w900,
                          color: AppColors.onPrimary,
                        ) ??
                        const TextStyle(
                            fontSize: 40,
                            letterSpacing: 4,
                            fontWeight: FontWeight.w900,
                            color: AppColors.onPrimary),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Row(
                    children: [
                      Expanded(
                        child: PrimaryButton(
                          label: 'نسخ',
                          icon: Icons.copy_rounded,
                          variant: AppButtonVariant.secondary,
                          onPressed: () {
                            Clipboard.setData(
                                ClipboardData(text: user.referralCode));
                            AppFeedback.toast(context, 'تم نسخ الكود');
                          },
                        ),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: PrimaryButton(
                          label: 'مشاركة',
                          icon: Icons.share_rounded,
                          variant: AppButtonVariant.secondary,
                          onPressed: () => Share.share(
                            'انضم إليّ في تطبيق الولاء واستخدم كود الإحالة الخاص بي: ${user.referralCode}',
                            subject: 'دعوة للانضمام',
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ).animate().fadeIn(duration: 300.ms).scale(
                begin: const Offset(.97, .97),
                end: const Offset(1, 1),
                curve: Curves.easeOut),
            AppSpacing.gapLg,
            AppCard(
              color: AppColors.infoBg,
              child: Row(
                children: [
                  const Icon(Icons.card_giftcard_rounded,
                      color: AppColors.primaryDark),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Text(
                      'ادعُ صديقًا، وعند أول زيارة له تحصل أنت على مكافأتك.',
                      style: theme.textTheme.bodyMedium,
                    ),
                  ),
                ],
              ),
            ),
            AppSpacing.gapXl,
            const SectionHeader(title: 'إحالاتك'),
            AppSpacing.gapMd,
            referralsAsync.when(
              loading: () => Column(
                children: List.generate(
                  3,
                  (i) => const Padding(
                    padding: EdgeInsets.only(bottom: AppSpacing.md),
                    child: _ReferralSkeleton(),
                  ),
                ),
              ),
              error: (e, _) => ErrorView(
                  message: 'تعذّر تحميل الإحالات',
                  onRetry: () => ref.invalidate(myReferralsProvider)),
              data: (list) {
                if (list.isEmpty) {
                  return const EmptyView(
                    icon: Icons.group_outlined,
                    title: 'لا توجد إحالات بعد',
                    message: 'شارك كودك لتبدأ في دعوة أصدقائك.',
                  );
                }
                return Column(
                  children: [
                    for (var i = 0; i < list.length; i++)
                      Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.md),
                        child: _ReferralRow(referral: list[i])
                            .animate()
                            .fadeIn(
                                delay: (60 * i).ms, duration: 280.ms)
                            .slideY(begin: .1, end: 0),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _ReferralSkeleton extends StatelessWidget {
  const _ReferralSkeleton();

  @override
  Widget build(BuildContext context) {
    return const AppCard(
      padding: EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Skeleton(height: 36, width: 36, radius: AppRadii.pill),
          SizedBox(width: 14),
          Expanded(child: Skeleton(height: 14, width: 120)),
          SizedBox(width: 14),
          Skeleton(height: 24, width: 72, radius: AppRadii.md),
        ],
      ),
    );
  }
}

class _ReferralRow extends StatelessWidget {
  final Map<String, dynamic> referral;
  const _ReferralRow({required this.referral});

  ({String label, Color color}) _statusInfo() {
    return switch (referral['status'] as String?) {
      'pending' => (label: 'بانتظار أول زيارة', color: AppColors.warning),
      'qualified' => (label: 'مكتمل', color: AppColors.info),
      'rewarded' => (label: 'تمت المكافأة', color: AppColors.success),
      _ => (label: 'غير معروف', color: AppColors.textSecondary),
    };
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final info = _statusInfo();
    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          const CircleAvatar(
            radius: 18,
            backgroundColor: AppColors.primaryLight,
            child: Icon(Icons.person_outline, color: AppColors.primaryDark),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text('صديق مدعو', style: theme.textTheme.titleMedium),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: info.color.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Text(info.label,
                style: TextStyle(
                    color: info.color, fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );
  }
}
