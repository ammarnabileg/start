import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../analytics/analytics_screen.dart';
import '../announcements/announcements_screen.dart';
import '../leaderboard/store_leaderboard_screen.dart';
import '../settings/merchant_settings_screen.dart';
import 'branches_screen.dart';
import 'campaigns_screen.dart';
import 'coupons_screen.dart';
import 'levels_screen.dart';
import 'questions_screen.dart';
import 'rewards_screen.dart';
import 'staff_screen.dart';

/// تاب الإدارة — مدخل لكل أقسام الإعداد. يخفي البلاطة لو الميزة معطّلة.
class ManagementHubScreen extends ConsumerWidget {
  const ManagementHubScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settingsAsync = ref.watch(merchantSettingsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('الإدارة')),
      body: settingsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الإعدادات',
          onRetry: () => ref.invalidate(merchantSettingsProvider),
        ),
        data: (settings) {
          final tiles = <_HubTile>[
            if (settings.enableVisits)
              _HubTile(
                icon: Icons.repeat_rounded,
                title: 'حملات الزيارة',
                subtitle: 'كافئ العملاء على تكرار الزيارة',
                builder: (_) => const CampaignsScreen(),
              ),
            if (settings.enableRewards)
              _HubTile(
                icon: Icons.card_giftcard_rounded,
                title: 'المكافآت',
                subtitle: 'الجوائز القابلة للاستبدال بالنقاط',
                builder: (_) => const RewardsManagementScreen(),
              ),
            if (settings.enableLevels)
              _HubTile(
                icon: Icons.military_tech_rounded,
                title: 'المستويات',
                subtitle: 'مستويات الولاء حسب إجمالي النقاط',
                builder: (_) => const LevelsScreen(),
              ),
            if (settings.enableCoupons)
              _HubTile(
                icon: Icons.confirmation_num_outlined,
                title: 'الكوبونات',
                subtitle: 'أكواد خصم ومنتجات مجانية',
                builder: (_) => const CouponsScreen(),
              ),
            _HubTile(
              icon: Icons.store_mall_directory_outlined,
              title: 'الفروع',
              subtitle: 'مواقع المتجر ونطاق إشعار القرب',
              builder: (_) => const BranchesScreen(),
            ),
            _HubTile(
              icon: Icons.badge_outlined,
              title: 'الموظفين',
              subtitle: 'الكاشير ومديرو الفروع وأدوارهم',
              builder: (_) => const StaffScreen(),
            ),
            _HubTile(
              icon: Icons.quiz_outlined,
              title: 'الأسئلة',
              subtitle: 'اجمع آراء عملائك مقابل نقاط',
              builder: (_) => const QuestionsScreen(),
            ),
            _HubTile(
              icon: Icons.insights_rounded,
              title: 'التحليلات',
              subtitle: 'الزيارات والنقاط ومعدّل العودة',
              builder: (_) => const AnalyticsScreen(),
            ),
            _HubTile(
              icon: Icons.leaderboard_rounded,
              title: 'لوحة الصدارة',
              subtitle: 'ترتيب عملاء المتجر بالنقاط',
              builder: (_) => const StoreLeaderboardScreen(),
            ),
            _HubTile(
              icon: Icons.settings_outlined,
              title: 'الإعدادات',
              subtitle: 'نطاق النقاط والميزات وحدود الأمان',
              builder: (_) => const MerchantSettingsScreen(),
            ),
            if (settings.enableAnnouncements)
              _HubTile(
                icon: Icons.campaign_outlined,
                title: 'الإعلانات',
                subtitle: 'أرسل إشعارًا لكل عملائك',
                builder: (_) => const AnnouncementsScreen(),
              ),
          ];

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: tiles.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final t = tiles[i];
              return AppCard(
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: t.builder),
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 24,
                      backgroundColor: AppColors.surfaceCream,
                      child: Icon(t.icon, color: AppColors.primaryDark),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(t.title,
                              style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 2),
                          Text(t.subtitle,
                              style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_left, color: AppColors.textSecondary),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _HubTile {
  final IconData icon;
  final String title;
  final String subtitle;
  final WidgetBuilder builder;
  const _HubTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.builder,
  });
}
