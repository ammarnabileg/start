import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع الإشعارات: قائمة مرقّمة + تعليم المقروء.
class NotificationsRepository {
  NotificationsRepository(this._client);
  final SupabaseClient _client;

  /// قائمة الإشعارات (مرقّمة، الأحدث أولًا).
  Future<List<Map<String, dynamic>>> list({
    required int offset,
    required int limit,
  }) async {
    final uid = _client.auth.currentUser!.id;
    final rows = await _client
        .from('notifications')
        .select()
        .eq('user_id', uid)
        .order('created_at', ascending: false)
        .range(offset, offset + limit - 1);
    return (rows as List).cast<Map<String, dynamic>>();
  }

  /// تعليم كل الإشعارات غير المقروءة كمقروءة (أفضل جهد).
  Future<void> markAllRead() async {
    final uid = _client.auth.currentUser!.id;
    await _client
        .from('notifications')
        .update({'read_at': DateTime.now().toIso8601String()})
        .eq('user_id', uid)
        .isFilter('read_at', null);
  }
}

final notificationsRepoProvider = Provider<NotificationsRepository>(
    (ref) => NotificationsRepository(ref.read(supabaseClientProvider)));
