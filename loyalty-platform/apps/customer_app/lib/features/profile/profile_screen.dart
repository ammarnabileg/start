import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/user_repository.dart';
import '../qr/qr_providers.dart';
import '../leaderboard/leaderboard_screen.dart';
import '../referral/referral_screen.dart';
import '../wheel/my_prizes_screen.dart';
import 'edit_profile_screen.dart';
import 'settings_screen.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final userAsync = ref.watch(currentUserProvider);
    return Scaffold(
      body: userAsync.when(
        loading: () => const SafeArea(child: SkeletonList(count: 5)),
        error: (e, _) => const ErrorView(message: 'تعذّر تحميل الملف'),
        data: (user) => ListView(
          padding: EdgeInsets.zero,
          children: [
            HeroHeader(
              title: user.name,
              subtitle: 'عضوية: ${user.id.substring(0, 8).toUpperCase()}',
              trailing: CircleAvatar(
                radius: 30,
                backgroundColor: Colors.white.withValues(alpha: .35),
                child: Text(
                  user.name.characters.first,
                  style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      color: AppColors.onPrimary),
                ),
              ),
              bottom: PrimaryButton(
                label: 'تعديل الملف',
                icon: Icons.edit_outlined,
                variant: AppButtonVariant.secondary,
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => const EditProfileScreen(),
                )),
              ),
            ),
            Padding(
              padding: AppSpacing.screen,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SectionHeader(title: 'النشاط'),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(
                      children: [
                        _Tile(
                          icon: Icons.emoji_events_outlined,
                          label: 'لوحة الصدارة العامة',
                          onTap: () =>
                              Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) => const LeaderboardScreen(
                                args: LeaderboardArgs(title: 'لوحة الصدارة')),
                          )),
                        ),
                        const Divider(height: 1, indent: 16, endIndent: 16),
                        _Tile(
                          icon: Icons.card_giftcard_outlined,
                          label: 'هداياي',
                          onTap: () =>
                              Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) => const MyPrizesScreen(),
                          )),
                        ),
                        const Divider(height: 1, indent: 16, endIndent: 16),
                        _Tile(
                          icon: Icons.group_add_outlined,
                          label: 'دعوة صديق (إحالة)',
                          onTap: () =>
                              Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) => const ReferralScreen(),
                          )),
                        ),
                      ],
                    ),
                  ),
                  AppSpacing.gapLg,
                  const SectionHeader(title: 'الإعدادات'),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: _Tile(
                      icon: Icons.notifications_none_rounded,
                      label: 'الإشعارات والخصوصية',
                      onTap: () =>
                          Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => const SettingsScreen(),
                      )),
                    ),
                  ),
                  AppSpacing.gapLg,
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(
                      children: [
                        _Tile(
                          icon: Icons.logout_rounded,
                          label: 'تسجيل الخروج',
                          onTap: () async {
                            await ref.read(userRepoProvider).signOut();
                            if (context.mounted) context.go('/welcome');
                          },
                        ),
                        const Divider(height: 1, indent: 16, endIndent: 16),
                        const _Tile(
                          icon: Icons.delete_outline_rounded,
                          label: 'حذف الحساب',
                          danger: true,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ).animate().fadeIn(duration: 300.ms).slideY(begin: .04, end: 0),
          ],
        ),
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final bool danger;
  const _Tile(
      {required this.icon, required this.label, this.onTap, this.danger = false});

  @override
  Widget build(BuildContext context) {
    final color = danger ? AppColors.error : AppColors.textPrimary;
    return ListTile(
      leading: AppIcon(icon, color: color),
      title: Text(label, style: TextStyle(color: color)),
      trailing: const AppIcon(Icons.chevron_left_rounded),
      onTap: onTap,
    );
  }
}
