import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../dashboard/dashboard_screen.dart';
import '../management/management_hub_screen.dart';
import '../profile/business_profile_screen.dart';
import '../scanner/scanner_screen.dart';

/// الهيكل الرئيسي لتطبيق التاجر — Bottom Tab Bar بأربع تابات.
/// تاب المسح في النص كزرار كبير بارز (أكتر شاشة استخدامًا).
class MerchantShell extends StatefulWidget {
  const MerchantShell({super.key});

  @override
  State<MerchantShell> createState() => _MerchantShellState();
}

class _MerchantShellState extends State<MerchantShell> {
  int _index = 0;

  // ترتيب التابات: لوحة التحكم · المسح (نص) · الإدارة · حسابي.
  static const _tabs = <Widget>[
    DashboardScreen(),
    ScannerScreen(),
    ManagementHubScreen(),
    BusinessProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _tabs),
      // زر المسح الكبير في النص.
      floatingActionButton: FloatingActionButton.large(
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.onPrimary,
        onPressed: () => setState(() => _index = 1),
        shape: const CircleBorder(),
        child: const Icon(Icons.qr_code_scanner_rounded, size: 34),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      bottomNavigationBar: BottomAppBar(
        shape: const CircularNotchedRectangle(),
        notchMargin: 8,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _NavItem(
              icon: Icons.dashboard_rounded,
              label: 'لوحة التحكم',
              selected: _index == 0,
              onTap: () => setState(() => _index = 0),
            ),
            _NavItem(
              icon: Icons.qr_code_scanner_rounded,
              label: 'المسح',
              selected: _index == 1,
              onTap: () => setState(() => _index = 1),
            ),
            const SizedBox(width: 56), // مساحة للزر العائم في النص
            _NavItem(
              icon: Icons.settings_rounded,
              label: 'الإدارة',
              selected: _index == 2,
              onTap: () => setState(() => _index = 2),
            ),
            _NavItem(
              icon: Icons.storefront_rounded,
              label: 'حسابي',
              selected: _index == 3,
              onTap: () => setState(() => _index = 3),
            ),
          ],
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _NavItem({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primaryDark : AppColors.textSecondary;
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: color, size: 24),
              const SizedBox(height: 2),
              Text(
                label,
                style: TextStyle(
                  color: color,
                  fontSize: 11,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
