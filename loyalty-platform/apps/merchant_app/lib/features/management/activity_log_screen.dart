import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/paginated_notifier.dart';
import '../../data/repositories/activity_repository.dart';
import '../../data/repositories/staff_repository.dart';

final _staffListProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(staffRepoProvider).fetchStaff(staff.merchantId);
});

final _activityProvider = StateNotifierProvider.autoDispose.family<
    PaginatedNotifier<Map<String, dynamic>>,
    PaginatedState<Map<String, dynamic>>, String?>((ref, staffId) {
  final repo = ref.read(activityRepoProvider);
  return PaginatedNotifier<Map<String, dynamic>>((offset, limit) async {
    final staff = await ref.read(currentStaffProvider.future);
    return repo.activity(staff.merchantId,
        staffId: staffId, limit: limit, offset: offset);
  });
});

/// تسمية الموظّف في الفلتر: الاسم + موبايله (وإلا دوره) — لتمييز الشخص.
String _staffOptionLabel(Map<String, dynamic> s) {
  final name = (s['name'] as String?)?.trim().isNotEmpty == true
      ? s['name'] as String
      : 'موظّف';
  final phone = (s['phone'] as String?)?.trim();
  return '$name · ${phone != null && phone.isNotEmpty ? phone : _roleLabel(s['role'] as String?)}';
}

String _roleLabel(String? r) => switch (r) {
      'merchant_owner' => 'المالك',
      'manager' => 'مدير',
      'branch_manager' => 'مدير فرع',
      'cashier' => 'كاشير',
      'admin' => 'إدارة المنصّة',
      _ => '',
    };

({String label, IconData icon, Color color}) _actionStyle(String action) =>
    switch (action) {
      'create' => (label: 'أضاف', icon: Icons.add_circle_outline_rounded, color: AppColors.success),
      'update' => (label: 'عدّل', icon: Icons.edit_outlined, color: AppColors.info),
      'delete' => (label: 'حذف', icon: Icons.delete_outline_rounded, color: AppColors.error),
      'grant_points' => (label: 'منح نقاطًا', icon: Icons.add_rounded, color: AppColors.primaryDark),
      'redeem_reward' => (label: 'سلّم مكافأة', icon: Icons.card_giftcard_rounded, color: AppColors.primaryDark),
      'redeem_prize' => (label: 'سلّم جائزة', icon: Icons.casino_rounded, color: AppColors.goldTier),
      'record_visit' => (label: 'سجّل زيارة', icon: Icons.repeat_rounded, color: AppColors.info),
      'apply_coupon' => (label: 'طبّق كوبونًا', icon: Icons.confirmation_num_outlined, color: AppColors.error),
      'qr_failed' => (label: 'فشل قراءة', icon: Icons.qr_code_scanner_rounded, color: AppColors.warning),
      'presence_blocked' => (label: 'محاولة خارج النطاق', icon: Icons.block_rounded, color: AppColors.error),
      'send_announcement' => (label: 'أرسل', icon: Icons.campaign_outlined, color: AppColors.primaryDark),
      _ => (label: action, icon: Icons.bolt, color: AppColors.textSecondary),
    };

String _entityLabel(String? e) => switch (e) {
      'reward' => 'مكافأة',
      'level' => 'مستوى',
      'coupon' => 'كوبون',
      'campaign' => 'حملة',
      'question' => 'سؤال',
      'wheel' => 'عجلة الحظ',
      'branch' => 'فرع',
      'staff' => 'موظّف',
      'role' => 'دور',
      'settings' => 'الإعدادات',
      'points' => 'نقاط',
      'prize' => 'جائزة',
      'visit' => 'زيارة',
      'scan' => 'QR',
      'announcement' => 'إعلان',
      'pos_key' => 'مفتاح POS',
      _ => e ?? '',
    };

/// شاشة سجل النشاط — مين عمل كل أكشن (للمالك). فلتر موظّف.
class ActivityLogScreen extends ConsumerStatefulWidget {
  const ActivityLogScreen({super.key});
  @override
  ConsumerState<ActivityLogScreen> createState() => _ActivityLogScreenState();
}

class _ActivityLogScreenState extends ConsumerState<ActivityLogScreen> {
  String? _staffId;

  @override
  Widget build(BuildContext context) {
    final staffList = ref.watch(_staffListProvider).valueOrNull ?? const [];
    final state = ref.watch(_activityProvider(_staffId));
    final notifier = ref.read(_activityProvider(_staffId).notifier);
    return Scaffold(
      appBar: AppBar(title: const Text('سجل النشاط')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: DropdownButtonFormField<String?>(
            value: _staffId,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'الموظّف',
              prefixIcon: AppIcon(Icons.badge_outlined),
            ),
            items: [
              const DropdownMenuItem(value: null, child: Text('كل الموظفين')),
              for (final s in staffList)
                DropdownMenuItem(
                  value: s['id'] as String,
                  child: Text(_staffOptionLabel(s)),
                ),
            ],
            onChanged: (v) => setState(() => _staffId = v),
          ),
        ),
        Expanded(
          child: PaginatedListView<Map<String, dynamic>>(
            state: state,
            onLoadMore: notifier.loadMore,
            onRefresh: notifier.refresh,
            emptyIcon: Icons.history_rounded,
            emptyTitle: 'لا يوجد نشاط بعد',
            emptyMessage: 'كل إجراءات موظفيك ستظهر هنا.',
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, row, i) => _ActivityCard(row: row),
          ),
        ),
      ]),
    );
  }
}

class _ActivityCard extends StatelessWidget {
  final Map<String, dynamic> row;
  const _ActivityCard({required this.row});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final a = _actionStyle(row['action'] as String? ?? '');
    final created = DateTime.tryParse((row['created_at'] ?? '').toString());
    final summary = (row['summary'] as String?)?.trim();
    final entity = _entityLabel(row['entity_type'] as String?);
    return AppCard(
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        CircleAvatar(
          radius: 18,
          backgroundColor: a.color.withValues(alpha: .14),
          child: AppIcon(a.icon, size: 18, color: a.color),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text.rich(TextSpan(children: [
              TextSpan(
                  text: '${row['staff_name'] ?? 'موظّف'} ',
                  style: theme.textTheme.titleSmall
                      ?.copyWith(fontWeight: FontWeight.w800)),
              if ((row['staff_phone'] as String?)?.trim().isNotEmpty == true)
                TextSpan(
                    text: '· ${row['staff_phone']}',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: AppColors.textSecondary)),
            ])),
            const SizedBox(height: 2),
            Text('${a.label} $entity${summary != null && summary.isNotEmpty ? ' · $summary' : ''}',
                style: theme.textTheme.bodyMedium),
          ]),
        ),
        const SizedBox(width: 8),
        if (created != null)
          Text(
            '${created.toLocal().year}/${created.toLocal().month}/${created.toLocal().day}\n${created.toLocal().hour.toString().padLeft(2, '0')}:${created.toLocal().minute.toString().padLeft(2, '0')}',
            textAlign: TextAlign.end,
            style: theme.textTheme.bodySmall
                ?.copyWith(color: AppColors.textSecondary, fontSize: 11),
          ),
      ]),
    );
  }
}
