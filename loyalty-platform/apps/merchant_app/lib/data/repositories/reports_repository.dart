import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// بلاغات العملاء للتاجر (قراءة فقط) — مع بيانات الراسل.
class ReportsRepository {
  ReportsRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> fetchReports(String merchantId) async {
    final rows = await _client
        .rpc('merchant_reports', params: {'p_merchant': merchantId});
    return List<Map<String, dynamic>>.from(rows as List);
  }
}

final reportsRepoProvider = Provider<ReportsRepository>(
    (ref) => ReportsRepository(ref.read(supabaseClientProvider)));
