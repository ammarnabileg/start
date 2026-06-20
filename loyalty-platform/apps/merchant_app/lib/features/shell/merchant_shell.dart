import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../core/push_service.dart';
import '../../data/repositories/notifications_repository.dart';
import '../dashboard/dashboard_screen.dart';
import '../management/management_hub_screen.dart';
import '../notifications/notifications_screen.dart';
import '../profile/business_profile_screen.dart';
import '../scanner/scanner_screen.dart';
import '../subscription/merchant_unavailable_screen.dart';

/// الهيكل الرئيسي لتطبيق التاجر — شريط تنقّل موحّد مع زر مسح بارز.
class MerchantShell extends ConsumerStatefulWidget {
  const MerchantShell({super.key});

  @override
  ConsumerState<MerchantShell> createState() => _MerchantShellState();
}

class _MerchantShellState extends ConsumerState<MerchantShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    // تسجيل توكن الإشعارات للموظف بعد تأكيد الجلسة (آمن بدون إعداد Firebase).
    PushService.registerForUser();
  }

  // ترتيب التابات: لوحة التحكم · الإشعارات · المسح (بارز) · الإدارة · حسابي.
  static const _tabs = <Widget>[
    DashboardScreen(),
    MerchantNotificationsScreen(),
    ScannerScreen(),
    ManagementHubScreen(),
    BusinessProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    // بوّابة الاستحقاق: لو المتجر معلّق أو انتهى الاشتراك/التجربة نعرض شاشة
    // "غير متاح" بدل الهيكل. (حالة "بانتظار الموافقة" تُعالَج في مكان آخر.)
    final entitled = ref.watch(merchantEntitledProvider);
    return entitled.when(
      loading: () => const Scaffold(body: LoadingView()),
      // fail-open: لو فشل فحص الأهلية (خطأ شبكة عابر) نعرض الهيكل — السيرفر
      // يفرض الأهلية على كل عملية كتابة، فمفيش ثغرة، وبنتجنّب قفل تاجر شرعي.
      error: (_, __) => _buildShell(context),
      data: (ok) => ok ? _buildShell(context) : const MerchantUnavailableScreen(),
    );
  }

  Widget _buildShell(BuildContext context) {
    // عدّاد غير المقروء لحظيًّا على تبويب الإشعارات.
    final unread = ref.watch(unreadNotificationsProvider);
    return Scaffold(
      extendBody: true,
      body: IndexedStack(index: _index, children: _tabs),
      bottomNavigationBar: AppBottomNav(
        currentIndex: _index,
        onTap: (i) => setState(() => _index = i),
        items: [
          const AppBottomNavItem(
              icon: Icons.dashboard_outlined,
              activeIcon: Icons.dashboard_rounded,
              label: 'لوحة التحكم'),
          AppBottomNavItem(
              icon: Icons.notifications_none_rounded,
              activeIcon: Icons.notifications_rounded,
              label: 'الإشعارات',
              badgeCount: unread),
          const AppBottomNavItem(
              icon: Icons.qr_code_scanner_rounded,
              label: 'مسح',
              prominent: true),
          const AppBottomNavItem(
              icon: Icons.tune_rounded, label: 'الإدارة'),
          const AppBottomNavItem(
              icon: Icons.storefront_outlined,
              activeIcon: Icons.storefront_rounded,
              label: 'حسابي'),
        ],
      ),
    );
  }
}
