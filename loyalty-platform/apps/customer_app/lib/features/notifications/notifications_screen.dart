import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

final notificationsProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final client = Supabase.instance.client;
  final uid = client.auth.currentUser!.id;
  final rows = await client
      .from('notifications')
      .select()
      .eq('user_id', uid)
      .order('created_at', ascending: false)
      .limit(50);
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
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل الإشعارات',
            onRetry: () => ref.invalidate(notificationsProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyView(
                icon: Icons.notifications_none_rounded,
                title: 'لا توجد إشعارات بعد');
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: list.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) {
              final n = list[i];
              final unread = n['read_at'] == null;
              return AppCard(
                color: unread ? AppColors.surfaceCream : null,
                child: Row(
                  children: [
                    const Icon(Icons.star_rounded, color: AppColors.primaryDark),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(n['title'] as String? ?? '',
                              style: Theme.of(context).textTheme.titleMedium),
                          if (n['body'] != null) ...[
                            const SizedBox(height: 4),
                            Text(n['body'] as String,
                                style: Theme.of(context).textTheme.bodyMedium),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}
