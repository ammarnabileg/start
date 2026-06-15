import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// الـ Bottom Tab Bar — 4 تابات ثابتة (الرئيسية/متاجري/الإشعارات/حسابي).
class HomeShell extends StatelessWidget {
  final StatefulNavigationShell shell;
  const HomeShell({super.key, required this.shell});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: shell,
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          boxShadow: [
            BoxShadow(
                color: AppColors.shadow, blurRadius: 20, offset: Offset(0, -4)),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: shell.currentIndex,
          onTap: (i) =>
              shell.goBranch(i, initialLocation: i == shell.currentIndex),
          type: BottomNavigationBarType.fixed,
          backgroundColor: Colors.transparent,
          elevation: 0,
          selectedItemColor: AppColors.primaryDark,
          unselectedItemColor: AppColors.textSecondary,
          showUnselectedLabels: true,
          items: const [
            BottomNavigationBarItem(
                icon: Icon(Icons.qr_code_2_rounded), label: 'الرئيسية'),
            BottomNavigationBarItem(
                icon: Icon(Icons.storefront_outlined),
                activeIcon: Icon(Icons.storefront_rounded),
                label: 'متاجري'),
            BottomNavigationBarItem(
                icon: Icon(Icons.notifications_none_rounded),
                activeIcon: Icon(Icons.notifications_rounded),
                label: 'الإشعارات'),
            BottomNavigationBarItem(
                icon: Icon(Icons.person_outline_rounded),
                activeIcon: Icon(Icons.person_rounded),
                label: 'حسابي'),
          ],
        ),
      ),
    );
  }
}
