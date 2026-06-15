import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// الـ Bottom Tab Bar — 4 تابات ثابتة (الرئيسية/متاجري/الإشعارات/حسابي).
class HomeShell extends StatelessWidget {
  final StatefulNavigationShell shell;
  const HomeShell({super.key, required this.shell});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: shell,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: shell.currentIndex,
        onTap: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
        items: const [
          BottomNavigationBarItem(
              icon: Icon(Icons.qr_code_2_rounded), label: 'الرئيسية'),
          BottomNavigationBarItem(
              icon: Icon(Icons.storefront_outlined), label: 'متاجري'),
          BottomNavigationBarItem(
              icon: Icon(Icons.notifications_none_rounded), label: 'الإشعارات'),
          BottomNavigationBarItem(
              icon: Icon(Icons.person_outline_rounded), label: 'حسابي'),
        ],
      ),
    );
  }
}
