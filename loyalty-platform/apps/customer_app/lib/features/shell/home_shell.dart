import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/notifications_repository.dart';

/// الـ Bottom Tab Bar — 5 تابات (رمزي بارز في النص · متاجري/الإشعارات على جنب
/// · هداياي/حسابي على الجنب الآخر) ليكون متوازنًا.
class HomeShell extends ConsumerWidget {
  final StatefulNavigationShell shell;
  const HomeShell({super.key, required this.shell});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // عدّاد غير المقروء لحظي على تبويب الإشعارات.
    final unread = ref.watch(unreadNotificationsProvider);
    return Scaffold(
      extendBody: true,
      body: shell,
      bottomNavigationBar: AppBottomNav(
        currentIndex: shell.currentIndex,
        onTap: (i) =>
            shell.goBranch(i, initialLocation: i == shell.currentIndex),
        items: [
          const AppBottomNavItem(
              icon: Icons.qr_code_2_rounded, label: 'رمزي', prominent: true),
          const AppBottomNavItem(
              icon: Icons.storefront_outlined,
              activeIcon: Icons.storefront_rounded,
              label: 'متاجري'),
          AppBottomNavItem(
              icon: Icons.notifications_none_rounded,
              activeIcon: Icons.notifications_rounded,
              label: 'الإشعارات',
              badgeCount: unread),
          const AppBottomNavItem(
              icon: Icons.card_giftcard_outlined,
              activeIcon: Icons.card_giftcard_rounded,
              label: 'هداياي'),
          const AppBottomNavItem(
              icon: Icons.person_outline_rounded,
              activeIcon: Icons.person_rounded,
              label: 'حسابي'),
        ],
      ),
    );
  }
}
