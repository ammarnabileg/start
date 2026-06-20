import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع إشعارات التاجر: قائمة مرقّمة + بثّ حيّ + تعليم المقروء.
/// الإشعارات مفتاحها `user_id` للموظّف المسجَّل (صاحب المتجر/المدير)، فنفس
/// جدول الإشعارات يخدم العميل والتاجر — كلٌّ يرى إشعاراته فقط (RLS).
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

  /// بثّ حيّ لإشعارات التاجر (الأحدث أولًا) — للتحديث اللحظي + شارة غير المقروء.
  Stream<List<Map<String, dynamic>>> watch() {
    final uid = _client.auth.currentUser!.id;
    return _client
        .from('notifications')
        .stream(primaryKey: ['id'])
        .eq('user_id', uid)
        .order('created_at', ascending: false)
        .limit(50);
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

/// بثّ الإشعارات الحيّ (يُبقي الشارة والقائمة محدّثتين لحظيًا).
final notificationsStreamProvider =
    StreamProvider.autoDispose<List<Map<String, dynamic>>>(
        (ref) => ref.read(notificationsRepoProvider).watch());

/// عدد الإشعارات غير المقروءة — لشارة تبويب الإشعارات (لحظي).
final unreadNotificationsProvider = Provider.autoDispose<int>((ref) {
  return ref
          .watch(notificationsStreamProvider)
          .valueOrNull
          ?.where((n) => n['read_at'] == null)
          .length ??
      0;
});
