import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/paginated_notifier.dart';
import '../../data/repositories/notifications_repository.dart';

/// قائمة الإشعارات (مرقّمة، الأحدث أولًا).
/// عند أول تحميل، تُعلَّم الإشعارات غير المقروءة كمقروءة (أفضل جهد).
final notificationsProvider = StateNotifierProvider.autoDispose<
    PaginatedNotifier<Map<String, dynamic>>,
    PaginatedState<Map<String, dynamic>>>((ref) {
  final repo = ref.read(notificationsRepoProvider);
  // ملاحظة: تبقى ستايلات "غير مقروء" كما هي لهذا البناء (البيانات الحالية).
  repo.markAllRead().catchError((_) {
    // غير حرج.
  });
  return PaginatedNotifier<Map<String, dynamic>>(
    (offset, limit) => repo.list(offset: offset, limit: limit),
  );
});

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsProvider);
    final notifier = ref.read(notificationsProvider.notifier);
    // تحديث القائمة لحظيًا عند وصول/تغيّر إشعار (بثّ حيّ).
    ref.listen(notificationsStreamProvider, (_, __) => notifier.refresh());
    return Scaffold(
      appBar: AppBar(title: const Text('الإشعارات'), centerTitle: true),
      body: PaginatedListView<Map<String, dynamic>>(
        state: state,
        onLoadMore: notifier.loadMore,
        onRefresh: notifier.refresh,
        emptyIcon: Icons.notifications_none_rounded,
        emptyTitle: 'لا توجد إشعارات بعد',
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (_, notification, i) =>
            _NotificationCard(notification: notification)
                .animate()
                .fadeIn(duration: 300.ms, delay: (i * 50).ms)
                .slideY(begin: .08, end: 0, curve: Curves.easeOut),
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  final Map<String, dynamic> notification;
  const _NotificationCard({required this.notification});

  // badge == null ⇒ شارة ذهبية متدرّجة (الاستايل الموحّد)، وإلا لون دلالي صلب.
  ({IconData icon, Color? badge}) _style() {
    final type = (notification['type'] as String?)?.toLowerCase() ?? '';
    if (type.contains('reward') || type.contains('redeem')) {
      return (icon: Icons.card_giftcard_rounded, badge: null);
    }
    if (type.contains('level') || type.contains('tier')) {
      return (icon: Icons.workspace_premium_rounded, badge: null);
    }
    if (type.contains('point')) {
      return (icon: Icons.star_rounded, badge: null);
    }
    return (icon: Icons.notifications_rounded, badge: AppColors.info);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final unread = notification['read_at'] == null;
    final s = _style();
    return AppCard(
      color: unread ? AppColors.surfaceCream : null,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AppIconBadge(s.icon, size: 44, iconSize: 22, color: s.badge),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(notification['title'] as String? ?? '',
                          style: theme.textTheme.titleMedium),
                    ),
                    if (unread)
                      Container(
                        width: 9,
                        height: 9,
                        margin: const EdgeInsetsDirectional.only(start: 6),
                        decoration: const BoxDecoration(
                            color: AppColors.primaryDark,
                            shape: BoxShape.circle),
                      ),
                  ],
                ),
                if (notification['body'] != null) ...[
                  const SizedBox(height: 4),
                  Text(notification['body'] as String,
                      style: theme.textTheme.bodyMedium),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
