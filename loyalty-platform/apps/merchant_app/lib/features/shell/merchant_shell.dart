import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../dashboard/dashboard_screen.dart';
import '../management/management_hub_screen.dart';
import '../profile/business_profile_screen.dart';
import '../scanner/scanner_screen.dart';

/// الهيكل الرئيسي لتطبيق التاجر — شريط تنقّل موحّد مع زر مسح بارز.
class MerchantShell extends StatefulWidget {
  const MerchantShell({super.key});

  @override
  State<MerchantShell> createState() => _MerchantShellState();
}

class _MerchantShellState extends State<MerchantShell> {
  int _index = 0;

  // ترتيب التابات: لوحة التحكم · المسح (بارز) · الإدارة · حسابي.
  static const _tabs = <Widget>[
    DashboardScreen(),
    ScannerScreen(),
    ManagementHubScreen(),
    BusinessProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      body: IndexedStack(index: _index, children: _tabs),
      bottomNavigationBar: AppBottomNav(
        currentIndex: _index,
        onTap: (i) => setState(() => _index = i),
        items: const [
          AppBottomNavItem(
              icon: Icons.dashboard_outlined,
              activeIcon: Icons.dashboard_rounded,
              label: 'لوحة التحكم'),
          AppBottomNavItem(
              icon: Icons.qr_code_scanner_rounded,
              label: 'مسح',
              prominent: true),
          AppBottomNavItem(
              icon: Icons.tune_rounded, label: 'الإدارة'),
          AppBottomNavItem(
              icon: Icons.storefront_outlined,
              activeIcon: Icons.storefront_rounded,
              label: 'حسابي'),
        ],
      ),
    );
  }
}
