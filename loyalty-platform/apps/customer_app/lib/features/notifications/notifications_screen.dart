import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

final notificationsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final client = Supabase.instance.client;
  final uid = client.auth.currentUser!.id;
  final rows = await client
      .from('notifications')
      .select()
      .eq('user_id', uid)
      .order('created_at', ascending: false)
      .limit(50);
  // عند تحميل الشاشة، علّم الإشعارات غير المقروءة كمقروءة (أفضل جهد).
  // ملاحظة: تبقى ستايلات "غير مقروء" كما هي لهذا البناء (البيانات الحالية).
  try {
    await client
        .from('notifications')
        .update({'read_at': DateTime.now().toIso8601String()})
        .eq('user_id', uid)
        .isFilter('read_at', null);
  } catch (_) {
    // غير حرج.
  }
  return (rows as List).cast<Map<String, dynamic>>();
});

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(notificationsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('الإشعارات'), centerTitle: true),
      body: data.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل الإشعارات',
            onRetry: () => ref.invalidate(notificationsProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyView(
                icon: Icons.notifications_none_rounded,
                title: 'لا توجد إشعارات بعد');
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(notificationsProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: list.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (_, i) => _NotificationCard(notification: list[i])
                  .animate()
                  .fadeIn(duration: 300.ms, delay: (i * 50).ms)
                  .slideY(begin: .08, end: 0, curve: Curves.easeOut),
            ),
          );
        },
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  final Map<String, dynamic> notification;
  const _NotificationCard({required this.notification});

  ({IconData icon, Color color, Color bg}) _style() {
    final type = (notification['type'] as String?)?.toLowerCase() ?? '';
    if (type.contains('reward') || type.contains('redeem')) {
      return (
        icon: Icons.card_giftcard_rounded,
        color: AppColors.primaryDark,
        bg: AppColors.surfaceCream
      );
    }
    if (type.contains('level') || type.contains('tier')) {
      return (
        icon: Icons.workspace_premium_rounded,
        color: AppColors.primaryDark,
        bg: AppColors.warningBg
      );
    }
    if (type.contains('point')) {
      return (
        icon: Icons.star_rounded,
        color: AppColors.primaryDark,
        bg: AppColors.surfaceCream
      );
    }
    return (
      icon: Icons.notifications_rounded,
      color: AppColors.info,
      bg: AppColors.infoBg
    );
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
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: s.bg,
              borderRadius: BorderRadius.circular(AppRadii.sm),
            ),
            child: Icon(s.icon, color: s.color, size: 22),
          ),
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
