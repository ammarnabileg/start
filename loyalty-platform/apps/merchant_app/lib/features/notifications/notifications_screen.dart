import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/paginated_notifier.dart';
import '../../data/repositories/notifications_repository.dart';

/// قائمة إشعارات التاجر (مرقّمة، الأحدث أولًا).
/// عند أول تحميل تُعلَّم الإشعارات غير المقروءة كمقروءة (أفضل جهد).
final notificationsProvider = StateNotifierProvider.autoDispose<
    PaginatedNotifier<Map<String, dynamic>>,
    PaginatedState<Map<String, dynamic>>>((ref) {
  final repo = ref.read(notificationsRepoProvider);
  repo.markAllRead().catchError((_) {});
  return PaginatedNotifier<Map<String, dynamic>>(
    (offset, limit) => repo.list(offset: offset, limit: limit),
  );
});

class MerchantNotificationsScreen extends ConsumerWidget {
  const MerchantNotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsProvider);
    final notifier = ref.read(notificationsProvider.notifier);
    // تحديث القائمة لحظيًا عند وصول إشعار جديد (بثّ حيّ).
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

  // أنواع إشعارات التاجر: بلاغ/تقييم/إحالة/تنبيه احتيال/إعلان.
  ({IconData icon, Color? badge}) _style() {
    final type = (notification['type'] as String?)?.toLowerCase() ?? '';
    if (type.contains('fraud') || type.contains('presence')) {
      return (icon: Icons.gpp_maybe_rounded, badge: AppColors.error);
    }
    if (type.contains('report')) {
      return (icon: Icons.flag_rounded, badge: AppColors.error);
    }
    if (type.contains('review') || type.contains('rating')) {
      return (icon: Icons.star_rounded, badge: null);
    }
    if (type.contains('referral')) {
      return (icon: Icons.group_add_rounded, badge: AppColors.success);
    }
    if (type.contains('announce') || type.contains('campaign')) {
      return (icon: Icons.campaign_rounded, badge: AppColors.info);
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
