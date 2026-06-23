import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// فلاتر قائمة عملاء التاجر (تُمرَّر كلها لـ RPC merchant_customers).
class CustomerFilters {
  final String? branchId;
  final String? level;
  final int? minPoints;
  final int? maxPoints;
  final int? minVisits;
  final bool? active; // true=نشِط آخر ٣٠ يوم، false=غير نشِط، null=الكل

  const CustomerFilters({
    this.branchId,
    this.level,
    this.minPoints,
    this.maxPoints,
    this.minVisits,
    this.active,
  });

  static const none = CustomerFilters();

  bool get isEmpty =>
      branchId == null &&
      level == null &&
      minPoints == null &&
      maxPoints == null &&
      minVisits == null &&
      active == null;

  int get count => [
        branchId,
        level,
        minPoints,
        maxPoints,
        minVisits,
        active,
      ].where((e) => e != null).length;

  @override
  bool operator ==(Object other) =>
      other is CustomerFilters &&
      other.branchId == branchId &&
      other.level == level &&
      other.minPoints == minPoints &&
      other.maxPoints == maxPoints &&
      other.minVisits == minVisits &&
      other.active == active;

  @override
  int get hashCode =>
      Object.hash(branchId, level, minPoints, maxPoints, minVisits, active);

  CustomerFilters copyWith({
    Object? branchId = _keep,
    Object? level = _keep,
    Object? minPoints = _keep,
    Object? maxPoints = _keep,
    Object? minVisits = _keep,
    Object? active = _keep,
  }) =>
      CustomerFilters(
        branchId: branchId == _keep ? this.branchId : branchId as String?,
        level: level == _keep ? this.level : level as String?,
        minPoints: minPoints == _keep ? this.minPoints : minPoints as int?,
        maxPoints: maxPoints == _keep ? this.maxPoints : maxPoints as int?,
        minVisits: minVisits == _keep ? this.minVisits : minVisits as int?,
        active: active == _keep ? this.active : active as bool?,
      );
}

const Object _keep = Object();

/// مستودع عملاء التاجر (RPC merchant_customers — يُرجِع الظاهرين فقط).
class CustomersRepository {
  CustomersRepository(this._client);
  final SupabaseClient _client;

  Future<List<dynamic>> fetchCustomers({
    required String merchantId,
    required String search,
    required int limit,
    required int offset,
    CustomerFilters filters = CustomerFilters.none,
  }) async {
    final rows = await _client.rpc('merchant_customers', params: {
      'p_merchant': merchantId,
      'p_search': search,
      'p_branch': filters.branchId,
      'p_level': filters.level,
      'p_min_points': filters.minPoints,
      'p_max_points': filters.maxPoints,
      'p_min_visits': filters.minVisits,
      'p_active': filters.active,
      'p_limit': limit,
      'p_offset': offset,
    });
    return rows as List;
  }
}

final customersRepoProvider = Provider<CustomersRepository>(
    (ref) => CustomersRepository(ref.read(supabaseClientProvider)));
