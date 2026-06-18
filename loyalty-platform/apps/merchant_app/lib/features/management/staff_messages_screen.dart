import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/reports_repository.dart';
import '../../data/repositories/staff_repository.dart';
import 'report_chat_screen.dart';

/// موظّفو المتجر (للاختيار).
final _staffListProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(staffRepoProvider).fetchStaff(staff.merchantId);
});

/// رسائل موظّف معيّن (أو الكل) — تدقيق المالك.
final _staffMessagesProvider = FutureProvider.autoDispose
    .family<List<Map<String, dynamic>>, String?>((ref, staffId) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref
      .read(reportsRepoProvider)
      .staffMessages(staff.merchantId, staffId: staffId);
});

String _roleLabel(String? r) => switch (r) {
      'merchant_owner' => 'المالك',
      'manager' => 'مدير',
      'branch_manager' => 'مدير فرع',
      'cashier' => 'كاشير',
      _ => '',
    };

/// شاشة تدقيق رسائل الموظّفين — يرى المالك رسائل موظّف معيّن داخل البلاغات.
class StaffMessagesScreen extends ConsumerStatefulWidget {
  const StaffMessagesScreen({super.key});
  @override
  ConsumerState<StaffMessagesScreen> createState() => _StaffMessagesScreenState();
}

class _StaffMessagesScreenState extends ConsumerState<StaffMessagesScreen> {
  String? _staffId; // null = الكل

  @override
  Widget build(BuildContext context) {
    final staffList = ref.watch(_staffListProvider).valueOrNull ?? const [];
    final async = ref.watch(_staffMessagesProvider(_staffId));
    return Scaffold(
      appBar: AppBar(title: const Text('سجل رسائل الموظفين')),
      body: Column(
        children: [
          // اختيار الموظّف
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
                    child: Text(
                        '${s['name'] ?? 'موظّف'} · ${_roleLabel(s['role'] as String?)}'),
                  ),
              ],
              onChanged: (v) => setState(() => _staffId = v),
            ),
          ),
          Expanded(
            child: async.when(
              loading: () => const SkeletonList(),
              error: (e, _) => ErrorView(
                  message: 'تعذّر تحميل الرسائل',
                  onRetry: () => ref.invalidate(_staffMessagesProvider(_staffId))),
              data: (rows) {
                if (rows.isEmpty) {
                  return const EmptyView(
                    icon: Icons.sms_outlined,
                    title: 'لا توجد رسائل',
                    message: 'ردود موظفيك على البلاغات ستظهر هنا.',
                  );
                }
                return RefreshIndicator(
                  onRefresh: () async =>
                      ref.invalidate(_staffMessagesProvider(_staffId)),
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: rows.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (_, i) => _MessageCard(row: rows[i]),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _MessageCard extends StatelessWidget {
  final Map<String, dynamic> row;
  const _MessageCard({required this.row});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final created = DateTime.tryParse((row['created_at'] ?? '').toString());
    final hidden = row['hidden'] == true;
    return AppCard(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => ReportChatScreen(
          reportId: row['report_id'] as String,
          customerName: (row['customer_name'] as String?) ?? 'عميل',
          subjectLabel: row['subject_label'] as String?,
        ),
      )),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Expanded(
              child: Text(
                  '${row['staff_name'] ?? 'موظّف'} · ${_roleLabel(row['staff_role'] as String?)}',
                  style: theme.textTheme.titleSmall),
            ),
            if (hidden)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                    color: AppColors.textSecondary.withValues(alpha: .15),
                    borderRadius: BorderRadius.circular(AppRadii.pill)),
                child: const Text('مخفية',
                    style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textSecondary)),
              ),
            const SizedBox(width: 8),
            if (created != null)
              Text('${created.year}/${created.month}/${created.day}',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: AppColors.textSecondary)),
          ]),
          const SizedBox(height: 8),
          Text(row['body'] as String? ?? '', style: theme.textTheme.bodyMedium),
          const SizedBox(height: 6),
          Row(children: [
            const AppIcon(Icons.person_outline_rounded,
                size: 14, color: AppColors.textSecondary),
            const SizedBox(width: 4),
            Text('للعميل: ${row['customer_name'] ?? '—'}',
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: AppColors.textSecondary)),
            if ((row['subject_label'] as String?)?.isNotEmpty == true) ...[
              const SizedBox(width: 10),
              Flexible(
                child: Text('عن: ${row['subject_label']}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: AppColors.textSecondary)),
              ),
            ],
          ]),
        ],
      ),
    );
  }
}
