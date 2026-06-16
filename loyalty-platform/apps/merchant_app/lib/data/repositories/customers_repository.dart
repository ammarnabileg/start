import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع عملاء التاجر (RPC merchant_customers).
class CustomersRepository {
  CustomersRepository(this._client);
  final SupabaseClient _client;

  Future<List<dynamic>> fetchCustomers({
    required String merchantId,
    required String search,
    required int limit,
    required int offset,
  }) async {
    final rows = await _client.rpc('merchant_customers', params: {
      'p_merchant': merchantId,
      'p_search': search,
      'p_limit': limit,
      'p_offset': offset,
    });
    return rows as List;
  }
}

final customersRepoProvider = Provider<CustomersRepository>(
    (ref) => CustomersRepository(ref.read(supabaseClientProvider)));
