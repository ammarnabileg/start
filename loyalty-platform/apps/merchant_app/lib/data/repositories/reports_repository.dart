import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// بلاغات العملاء للتاجر + محادثاتها (thread + رد).
class ReportsRepository {
  ReportsRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> fetchReports(String merchantId) async {
    final rows = await _client
        .rpc('merchant_reports', params: {'p_merchant': merchantId});
    return List<Map<String, dynamic>>.from(rows as List);
  }

  Future<List<ReportMessage>> thread(String reportId) async {
    final rows =
        await _client.rpc('report_thread', params: {'p_report': reportId});
    return ((rows as List?) ?? const [])
        .map((r) => ReportMessage.fromJson(r as Map<String, dynamic>))
        .toList();
  }

  Future<void> postMessage(String reportId, String body, {String? replyTo}) {
    return _client.rpc('post_report_message', params: {
      'p_report': reportId,
      'p_body': body,
      'p_reply_to': replyTo,
    });
  }
}

final reportsRepoProvider = Provider<ReportsRepository>(
    (ref) => ReportsRepository(ref.read(supabaseClientProvider)));
