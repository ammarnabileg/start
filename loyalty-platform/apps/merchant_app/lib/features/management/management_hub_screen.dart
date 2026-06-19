import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../analytics/analytics_screen.dart';
import '../announcements/announcements_screen.dart';
import '../leaderboard/store_leaderboard_screen.dart';
import '../settings/merchant_settings_screen.dart';
import 'activity_log_screen.dart';
import 'branches_screen.dart';
import 'campaigns_screen.dart';
import 'customers_screen.dart';
import 'coupons_screen.dart';
import 'levels_screen.dart';
import 'pos_screen.dart';
import 'questions_screen.dart';
import 'reports_screen.dart';
import 'reviews_screen.dart';
import 'rewards_screen.dart';
import 'roles_screen.dart';
import 'staff_messages_screen.dart';
import 'staff_screen.dart';
import 'wheel_screen.dart';

/// تاب الإدارة — مدخل لكل أقسام الإعداد. يخفي البلاطة لو الميزة معطّلة.
class ManagementHubScreen extends ConsumerWidget {
  const ManagementHubScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settingsAsync = ref.watch(merchantSettingsProvider);
    final perms = ref.watch(permissionsProvider).valueOrNull;

    return Scaffold(
      body: settingsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الإعدادات',
          onRetry: () => ref.invalidate(merchantSettingsProvider),
        ),
        data: (settings) {
          final tiles = <_HubTile>[
            _HubTile(
              icon: Icons.groups_2_outlined,
              accent: AppColors.info,
              title: 'العملاء',
              subtitle: 'اعرض عملاءك وأرسل لهم إشعارات',
              builder: (_) => const CustomersScreen(),
              resource: 'customers',
            ),
            _HubTile(
              icon: Icons.flag_outlined,
              accent: AppColors.error,
              title: 'البلاغات',
              subtitle: 'حادِث عملاءك وردّ على بلاغاتهم',
              builder: (_) => const ReportsScreen(),
              resource: 'reports',
            ),
            _HubTile(
              icon: Icons.star_rounded,
              accent: AppColors.goldTier,
              title: 'التقييمات',
              subtitle: 'مراجعات عملائك — اعرض وردّ عليها',
              builder: (_) => const ReviewsScreen(),
              resource: 'reports',
            ),
            if (settings.enableVisits)
              _HubTile(
                icon: Icons.repeat_rounded,
                accent: AppColors.info,
                title: 'حملات الزيارة',
                subtitle: 'كافئ العملاء على تكرار الزيارة',
                builder: (_) => const CampaignsScreen(),
                resource: 'campaigns',
              ),
            if (settings.enableRewards)
              _HubTile(
                icon: Icons.card_giftcard_rounded,
                accent: AppColors.primaryDark,
                title: 'المكافآت',
                subtitle: 'الجوائز القابلة للاستبدال بالنقاط',
                builder: (_) => const RewardsManagementScreen(),
                resource: 'rewards',
              ),
            if (settings.enableLevels)
              _HubTile(
                icon: Icons.military_tech_rounded,
                accent: AppColors.goldTier,
                title: 'المستويات',
                subtitle: 'مستويات الولاء حسب إجمالي النقاط',
                builder: (_) => const LevelsScreen(),
                resource: 'levels',
              ),
            if (settings.enableCoupons)
              _HubTile(
                icon: Icons.confirmation_num_outlined,
                accent: AppColors.error,
                title: 'الكوبونات',
                subtitle: 'أكواد خصم ومنتجات مجانية',
                builder: (_) => const CouponsScreen(),
                resource: 'coupons',
              ),
            _HubTile(
              icon: Icons.store_mall_directory_outlined,
              accent: AppColors.success,
              title: 'الفروع',
              subtitle: 'مواقع المتجر ونطاق إشعار القرب',
              builder: (_) => const BranchesScreen(),
              resource: 'branches',
            ),
            _HubTile(
              icon: Icons.badge_outlined,
              accent: AppColors.bronze,
              title: 'الموظفين',
              subtitle: 'الكاشير ومديرو الفروع وأدوارهم',
              builder: (_) => const StaffScreen(),
              resource: 'staff',
            ),
            _HubTile(
              icon: Icons.sms_outlined,
              accent: AppColors.info,
              title: 'سجل رسائل الموظفين',
              subtitle: 'راجع ردود موظّف معيّن على البلاغات',
              builder: (_) => const StaffMessagesScreen(),
              resource: 'staff',
            ),
            _HubTile(
              icon: Icons.history_rounded,
              accent: AppColors.primaryDark,
              title: 'سجل النشاط',
              subtitle: 'مين عمل كل أكشن في المتجر',
              builder: (_) => const ActivityLogScreen(),
              resource: 'staff',
            ),
            _HubTile(
              icon: Icons.quiz_outlined,
              accent: AppColors.info,
              title: 'الأسئلة',
              subtitle: 'اجمع آراء عملائك مقابل نقاط',
              builder: (_) => const QuestionsScreen(),
              resource: 'questions',
            ),
            _HubTile(
              icon: Icons.casino_rounded,
              accent: AppColors.goldTier,
              title: 'عجلة الحظ',
              subtitle: 'صمّم عجلة الجوائز ومقاطعها',
              builder: (_) => const WheelManagementScreen(),
              resource: 'wheel',
            ),
            _HubTile(
              icon: Icons.admin_panel_settings_outlined,
              accent: AppColors.primaryDark,
              title: 'الأدوار والصلاحيات',
              subtitle: 'أدوار مخصّصة وصلاحيات الموظفين',
              builder: (_) => const RolesScreen(),
              resource: 'roles',
            ),
            _HubTile(
              icon: Icons.insights_rounded,
              accent: AppColors.warning,
              title: 'التحليلات',
              subtitle: 'الزيارات والنقاط ومعدّل العودة',
              builder: (_) => const AnalyticsScreen(),
              resource: 'analytics',
            ),
            _HubTile(
              icon: Icons.leaderboard_rounded,
              accent: AppColors.goldTier,
              title: 'لوحة الصدارة',
              subtitle: 'ترتيب عملاء المتجر بالنقاط',
              builder: (_) => const StoreLeaderboardScreen(),
              resource: 'customers',
            ),
            _HubTile(
              icon: Icons.point_of_sale_rounded,
              accent: AppColors.success,
              title: 'تكامل POS',
              subtitle: 'API لربط نظام الكاشير ومفاتيحه',
              builder: (_) => const PosIntegrationScreen(),
              resource: 'settings',
            ),
            _HubTile(
              icon: Icons.settings_outlined,
              accent: AppColors.textSecondary,
              title: 'الإعدادات',
              subtitle: 'نطاق النقاط والميزات وحدود الأمان',
              builder: (_) => const MerchantSettingsScreen(),
              resource: 'settings',
            ),
            if (settings.enableAnnouncements)
              _HubTile(
                icon: Icons.campaign_outlined,
                accent: AppColors.primaryDark,
                title: 'الإعلانات',
                subtitle: 'أرسل إشعارًا لكل عملائك',
                builder: (_) => const AnnouncementsScreen(),
                resource: 'announcements',
              ),
          ];

          final visible = [
            for (final t in tiles)
              if (t.resource == null || perms == null || perms.can(t.resource!, 'view'))
                t,
          ];

          return CustomScrollView(
            slivers: [
              const SliverToBoxAdapter(
                child: HeroHeader(
                  title: 'الإدارة',
                  subtitle: 'كل أدوات متجرك في مكان واحد',
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                sliver: SliverGrid(
                  gridDelegate:
                      SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: context.responsive(mobile: 2, tablet: 3),
                    mainAxisSpacing: AppSpacing.md,
                    crossAxisSpacing: AppSpacing.md,
                    childAspectRatio: 0.95,
                  ),
                  delegate: SliverChildBuilderDelegate(
                    (context, i) {
                      final t = visible[i];
                      return AppCard(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: t.builder),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              height: 52,
                              width: 52,
                              decoration: BoxDecoration(
                                color: t.accent.withValues(alpha: .15),
                                borderRadius:
                                    BorderRadius.circular(AppRadii.md),
                              ),
                              child: AppIcon(t.icon, color: t.accent, size: 26),
                            ),
                            const Spacer(),
                            Text(t.title,
                                style:
                                    Theme.of(context).textTheme.titleMedium),
                            const SizedBox(height: 4),
                            Text(t.subtitle,
                                style:
                                    Theme.of(context).textTheme.bodySmall,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis),
                          ],
                        ),
                      )
                          .animate()
                          .fadeIn(duration: 300.ms, delay: (40 * i).ms)
                          .slideY(begin: .06, end: 0);
                    },
                    childCount: visible.length,
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _HubTile {
  final IconData icon;
  final Color accent;
  final String title;
  final String subtitle;
  final WidgetBuilder builder;
  final String? resource; // مورد الصلاحية (null = متاح للكل)
  const _HubTile({
    required this.icon,
    required this.accent,
    required this.title,
    required this.subtitle,
    required this.builder,
    this.resource,
  });
}
