import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع الإعلانات: دالة send-announcement + RPC استهلاك الإشعارات.
class AnnouncementsRepository {
  AnnouncementsRepository(this._client);
  final SupabaseClient _client;

  Future<Map<String, dynamic>> notificationUsage(String merchantId) {
    return _client
        .rpc('merchant_notification_usage', params: {'p_merchant': merchantId})
        .single();
  }

  Future<FunctionResponse> sendAnnouncement({
    required String title,
    required String body,
  }) {
    return _client.functions
        .invoke('send-announcement', body: {'title': title, 'body': body});
  }
}

final announcementsRepoProvider = Provider<AnnouncementsRepository>(
    (ref) => AnnouncementsRepository(ref.read(supabaseClientProvider)));
