import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';
import '../analytics/analytics_screen.dart';
import '../leaderboard/store_leaderboard_screen.dart';
import '../settings/merchant_settings_screen.dart';

/// بيانات النشاط (merchants).
final _merchantProvider =
    FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return Supabase.instance.client
      .from('merchants')
      .select()
      .eq('id', staff.merchantId)
      .maybeSingle();
});

/// الاشتراك الحالي (subscriptions).
final _subscriptionProvider =
    FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return Supabase.instance.client
      .from('subscriptions')
      .select()
      .eq('merchant_id', staff.merchantId)
      .order('created_at', ascending: false)
      .limit(1)
      .maybeSingle();
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
      appBar: AppBar(title: const Text('حسابي')),
      body: merchantAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل بيانات النشاط',
          onRetry: () => ref.invalidate(_merchantProvider),
        ),
        data: (merchant) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _businessSection(context, merchant),
            const SizedBox(height: 20),
            _SectionLabel('الاشتراك'),
            _subscriptionSection(context, ref),
            const SizedBox(height: 20),
            _SectionLabel('روابط سريعة'),
            AppCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  _NavTile(
                    icon: Icons.insights_rounded,
                    title: 'التحليلات',
                    onTap: () => _push(context, const AnalyticsScreen()),
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
            const SizedBox(height: 20),
            _SectionLabel('الإعدادات'),
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
                    subtitle: 'العربية',
                    onTap: () {
                      // TODO: تبديل اللغة (عربي/إنجليزي) — يقلب الـ layout.
                      ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('تبديل اللغة — قريبًا')));
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            _SectionLabel('الحساب'),
            AppCard(
              padding: EdgeInsets.zero,
              child: _NavTile(
                icon: Icons.logout_rounded,
                title: 'تسجيل الخروج',
                color: AppColors.error,
                onTap: () => _signOut(context),
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _businessSection(
      BuildContext context, Map<String, dynamic>? merchant) {
    return AppCard(
      child: Column(
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: AppColors.surfaceCream,
                // TODO: عرض اللوجو من logo_url.
                child: const Icon(Icons.storefront_rounded,
                    color: AppColors.primaryDark, size: 28),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Text(
                  merchant?['business_name'] as String? ?? 'متجري',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () {
                // TODO: شاشة تعديل بيانات المتجر (الاسم/النوع/الوصف/اللوجو/العنوان/التواصل).
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                    content: Text('تعديل بيانات المتجر — قريبًا')));
              },
              icon: const Icon(Icons.edit_outlined),
              label: const Text('تعديل بيانات المتجر'),
            ),
          ),
        ],
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
                  const Icon(Icons.workspace_premium_outlined,
                      color: AppColors.primaryDark),
                  const SizedBox(width: 10),
                  Text(
                    plan == null
                        ? 'لا يوجد اشتراك نشط'
                        : 'الخطة: ${_planLabels[plan] ?? plan}',
                    style: Theme.of(context).textTheme.titleMedium,
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
                  onPressed: () {
                    // TODO: شاشة إدارة الاشتراك (مملوكة لوكيل آخر).
                    ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                            content: Text('إدارة الاشتراك — قريبًا')));
                  },
                  icon: const Icon(Icons.credit_card_outlined),
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

  Future<void> _signOut(BuildContext context) async {
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
      await Supabase.instance.client.auth.signOut();
    }
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;
  const _SectionLabel(this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10, right: 4),
        child: Text(text, style: Theme.of(context).textTheme.titleMedium),
      );
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
        leading: Icon(icon, color: color ?? AppColors.primaryDark),
        title: Text(title, style: TextStyle(color: color)),
        subtitle: subtitle == null ? null : Text(subtitle!),
        trailing:
            const Icon(Icons.chevron_left, color: AppColors.textSecondary),
        onTap: onTap,
      );
}
