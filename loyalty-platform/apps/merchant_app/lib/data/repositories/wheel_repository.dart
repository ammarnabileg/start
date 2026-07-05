import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع عجلة الحظ ومقاطعها.
class WheelRepository {
  WheelRepository(this._client);
  final SupabaseClient _client;

  Future<Map<String, dynamic>?> fetchWheel(String merchantId) {
    return _client
        .from('lucky_wheels')
        .select('*, wheel_segments(*)')
        .eq('merchant_id', merchantId)
        .maybeSingle();
  }

  /// إنشاء عجلة ويرجّع المعرّف.
  Future<String> insertWheel(Map<String, dynamic> payload) async {
    final inserted = await _client
        .from('lucky_wheels')
        .insert(payload)
        .select('id')
        .single();
    return inserted['id'] as String;
  }

  Future<void> updateWheel(String id, Map<String, dynamic> payload) {
    return _client.from('lucky_wheels').update(payload).eq('id', id);
  }

  Future<void> deleteSegments(List<String> ids) {
    return _client.from('wheel_segments').delete().inFilter('id', ids);
  }

  Future<void> upsertSegments(List<Map<String, dynamic>> rows) {
    return _client.from('wheel_segments').upsert(rows);
  }
}

final wheelRepoProvider = Provider<WheelRepository>(
    (ref) => WheelRepository(ref.read(supabaseClientProvider)));
