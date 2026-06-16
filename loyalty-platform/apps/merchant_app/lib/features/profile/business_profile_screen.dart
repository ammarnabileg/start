import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/locale_controller.dart';
import '../../core/merchant_providers.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/merchant_repository.dart';
import '../analytics/analytics_screen.dart';
import '../leaderboard/store_leaderboard_screen.dart';
import '../settings/merchant_settings_screen.dart';
import '../subscription/manage_subscription_screen.dart';
import 'edit_business_screen.dart';

/// بيانات النشاط (merchants).
final _merchantProvider =
    FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(merchantRepoProvider).fetchMerchant(staff.merchantId);
});

/// الاشتراك الحالي (subscriptions).
final _subscriptionProvider =
    FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(merchantRepoProvider).fetchLatestSubscription(staff.merchantId);
});

const _planLabels = {
  'trial': 'تجربة مجانية',
  'monthly': 'شهري',
  'yearly': 'سنوي',
};

/// 2.13 — حسابي (Business Profile & Settings).
class BusinessProfileScreen extends ConsumerWidget {
  const BusinessProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final merchantAsync = ref.watch(_merchantProvider);

    return Scaffold(
      body: merchantAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل بيانات النشاط',
          onRetry: () => ref.invalidate(_merchantProvider),
        ),
        data: (merchant) => ListView(
          padding: EdgeInsets.zero,
          children: [
            _businessHeader(context, merchant),
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.lg,
                  AppSpacing.lg, AppSpacing.xxl),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => _push(
                          context, const EditBusinessProfileScreen()),
                      icon: const AppIcon(Icons.edit_outlined),
                      label: const Text('تعديل بيانات المتجر'),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  const SectionHeader(title: 'الاشتراك'),
                  const SizedBox(height: AppSpacing.sm),
                  _subscriptionSection(context, ref),
                  const SizedBox(height: AppSpacing.xl),
                  const SectionHeader(title: 'النشاط'),
                  const SizedBox(height: AppSpacing.sm),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(
                      children: [
                        _NavTile(
                          icon: Icons.insights_rounded,
                          title: 'التحليلات',
                          onTap: () =>
                              _push(context, const AnalyticsScreen()),
                        ),
                        const Divider(height: 1),
                        _NavTile(
                          icon: Icons.leaderboard_rounded,
                          title: 'لوحة الصدارة',
                          onTap: () =>
                              _push(context, const StoreLeaderboardScreen()),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  const SectionHeader(title: 'الإعدادات'),
                  const SizedBox(height: AppSpacing.sm),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(
                      children: [
                        _NavTile(
                          icon: Icons.settings_outlined,
                          title: 'الإعدادات المتقدمة',
                          onTap: () =>
                              _push(context, const MerchantSettingsScreen()),
                        ),
                        const Divider(height: 1),
                        _NavTile(
                          icon: Icons.language_outlined,
                          title: 'اللغة',
                          subtitle: ref.watch(localeProvider).languageCode == 'ar'
                              ? 'العربية'
                              : 'English',
                          onTap: () => showModalBottomSheet<void>(
                            context: context,
                            builder: (_) => Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                ListTile(
                                  title: const Text('العربية'),
                                  onTap: () {
                                    ref
                                        .read(localeProvider.notifier)
                                        .setLocale(const Locale('ar'));
                                    Navigator.pop(context);
                                  },
                                ),
                                ListTile(
                                  title: const Text('English'),
                                  onTap: () {
                                    ref
                                        .read(localeProvider.notifier)
                                        .setLocale(const Locale('en'));
                                    Navigator.pop(context);
                                  },
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  const SectionHeader(title: 'الحساب'),
                  const SizedBox(height: AppSpacing.sm),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: _NavTile(
                      icon: Icons.logout_rounded,
                      title: 'تسجيل الخروج',
                      color: AppColors.error,
                      onTap: () => _signOut(context, ref),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _businessHeader(
      BuildContext context, Map<String, dynamic>? merchant) {
    final logoUrl = merchant?['logo_url'] as String?;
    return HeroHeader(
      title: merchant?['business_name'] as String? ?? 'متجري',
      subtitle: 'إدارة حسابك وإعدادات متجرك',
      gradient: AppColors.darkGradient,
      trailing: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: .12),
          shape: BoxShape.circle,
          image: (logoUrl != null && logoUrl.isNotEmpty)
              ? DecorationImage(
                  image: NetworkImage(logoUrl), fit: BoxFit.cover)
              : null,
        ),
        child: (logoUrl != null && logoUrl.isNotEmpty)
            ? null
            : const AppIcon(Icons.storefront_rounded,
                color: AppColors.gold, size: 30),
      ),
    );
  }

  Widget _subscriptionSection(BuildContext context, WidgetRef ref) {
    final subAsync = ref.watch(_subscriptionProvider);
    return subAsync.when(
      loading: () => const AppCard(
          child: Center(child: CircularProgressIndicator())),
      error: (_, __) => AppCard(
        child: Row(
          children: [
            const Expanded(child: Text('تعذّر تحميل الاشتراك')),
            TextButton(
                onPressed: () => ref.invalidate(_subscriptionProvider),
                child: const Text('إعادة')),
          ],
        ),
      ),
      data: (sub) {
        final df = DateFormat('yyyy/MM/dd');
        final plan = sub?['plan'] as String?;
        final trialEnds = sub?['trial_ends_at'] as String?;
        final periodEnd = sub?['current_period_end'] as String?;
        String renewalLine = '';
        if (trialEnds != null) {
          renewalLine =
              'تنتهي التجربة: ${df.format(DateTime.parse(trialEnds))}';
        } else if (periodEnd != null) {
          renewalLine =
              'التجديد: ${df.format(DateTime.parse(periodEnd))}';
        }
        return AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const AppIcon(Icons.workspace_premium_outlined,
                      color: AppColors.primaryDark),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      plan == null
                          ? 'لا يوجد اشتراك نشط'
                          : 'الخطة: ${_planLabels[plan] ?? plan}',
                      style: Theme.of(context).textTheme.titleMedium,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
              if (renewalLine.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(renewalLine,
                    style: Theme.of(context).textTheme.bodySmall),
              ],
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () =>
                      _push(context, const ManageSubscriptionScreen()),
                  icon: const AppIcon(Icons.credit_card_outlined),
                  label: const Text('إدارة الاشتراك'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  void _push(BuildContext context, Widget screen) =>
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));

  Future<void> _signOut(BuildContext context, WidgetRef ref) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تسجيل الخروج'),
        content: const Text('هل تريد تسجيل الخروج من حسابك؟'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('تأكيد')),
        ],
      ),
    );
    if (confirm == true) {
      await ref.read(authRepoProvider).signOut();
    }
  }
}

class _NavTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;
  final Color? color;
  const _NavTile({
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.color,
  });
  @override
  Widget build(BuildContext context) => ListTile(
        leading: AppIcon(icon, color: color ?? AppColors.primaryDark),
        title: Text(title, style: TextStyle(color: color)),
        subtitle: subtitle == null ? null : Text(subtitle!),
        trailing:
            const AppIcon(Icons.chevron_left, color: AppColors.textSecondary),
        onTap: onTap,
      );
}
