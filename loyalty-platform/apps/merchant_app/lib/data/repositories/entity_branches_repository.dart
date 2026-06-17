import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// استهداف العناصر للفروع (مكافأة/كوبون/حملة/عجلة).
/// قائمة فارغة = العنصر موحّد (متاح في كل الفروع).
class EntityBranchesRepository {
  EntityBranchesRepository(this._client);
  final SupabaseClient _client;

  /// فروع عنصر معيّن (فارغة = كل الفروع).
  Future<List<String>> branchIdsFor(String type, String entityId) async {
    final rows = await _client
        .from('entity_branches')
        .select('branch_id')
        .eq('entity_type', type)
        .eq('entity_id', entityId);
    return (rows as List).map((r) => r['branch_id'] as String).toList();
  }

  /// يضبط استهداف عنصر: يستبدل كل صفوفه بالفروع المعطاة.
  /// [branchIds] فارغة ⇒ موحّد (يُحذف الاستهداف فيصبح متاحًا في كل الفروع).
  Future<void> setBranches(
    String type,
    String entityId,
    String merchantId,
    List<String> branchIds,
  ) async {
    await _client
        .from('entity_branches')
        .delete()
        .eq('entity_type', type)
        .eq('entity_id', entityId);
    if (branchIds.isNotEmpty) {
      await _client.from('entity_branches').insert([
        for (final b in branchIds)
          {
            'merchant_id': merchantId,
            'entity_type': type,
            'entity_id': entityId,
            'branch_id': b,
          }
      ]);
    }
  }
}

final entityBranchesRepoProvider = Provider<EntityBranchesRepository>(
    (ref) => EntityBranchesRepository(ref.read(supabaseClientProvider)));
