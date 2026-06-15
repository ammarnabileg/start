import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:share_plus/share_plus.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../qr/qr_providers.dart';

/// إحالات العميل (مَن دعاهم + حالتهم).
final myReferralsProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final client = Supabase.instance.client;
  final uid = client.auth.currentUser!.id;
  final rows = await client
      .from('referrals')
      .select()
      .eq('referrer_id', uid)
      .order('created_at', ascending: false);
  return (rows as List).cast<Map<String, dynamic>>();
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
          padding: const EdgeInsets.all(16),
          children: [
            AppCard(
              color: AppColors.surfaceCream,
              child: Column(
                children: [
                  Text('كود الإحالة الخاص بك',
                      style: theme.textTheme.bodyMedium),
                  const SizedBox(height: 10),
                  Text(
                    user.referralCode,
                    style: theme.textTheme.displayLarge?.copyWith(
                          letterSpacing: 4,
                          fontWeight: FontWeight.w900,
                        ) ??
                        const TextStyle(
                            fontSize: 40,
                            letterSpacing: 4,
                            fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () {
                            Clipboard.setData(
                                ClipboardData(text: user.referralCode));
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('تم نسخ الكود')),
                            );
                          },
                          icon: const Icon(Icons.copy_rounded),
                          label: const Text('نسخ'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: PrimaryButton(
                          label: 'مشاركة',
                          icon: Icons.share_rounded,
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
            ),
            const SizedBox(height: 16),
            AppCard(
              child: Row(
                children: [
                  const Icon(Icons.card_giftcard_outlined,
                      color: AppColors.primaryDark),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'ادعُ صديقًا، وعند أول زيارة له تحصل أنت على مكافأتك.',
                      style: theme.textTheme.bodyMedium,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Text('إحالاتك', style: theme.textTheme.titleLarge),
            const SizedBox(height: 12),
            referralsAsync.when(
              loading: () => const Padding(
                padding: EdgeInsets.all(24),
                child: LoadingView(),
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
                    for (final r in list)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ReferralRow(referral: r),
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
