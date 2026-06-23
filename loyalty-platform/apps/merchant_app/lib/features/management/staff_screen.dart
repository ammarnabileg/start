import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../core/perm_gate.dart';
import '../../data/repositories/staff_repository.dart';

/// قائمة الموظفين من جدول merchant_staff.
final staffListProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(staffRepoProvider).fetchStaff(staff.merchantId);
});

/// فروع التاجر لربط الموظف (id → name).
final branchOptionsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(staffRepoProvider).fetchBranchOptions(staff.merchantId);
});

/// أدوار التاجر لربطها بالموظف (id → name).
final staffRoleOptionsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(staffRepoProvider).fetchRoleOptions(staff.merchantId);
});

const _roleLabels = {
  'manager': 'مدير',
  'cashier': 'كاشير',
  'branch_manager': 'مدير فرع',
};

const _roleColors = {
  'manager': AppColors.primaryDark,
  'cashier': AppColors.info,
  'branch_manager': AppColors.success,
};

/// 2.10.و — الموظفين.
class StaffScreen extends ConsumerWidget {
  const StaffScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(staffListProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('الموظفين')),
      floatingActionButton: PermFab(
        resource: PermResource.staff,
        label: 'موظف جديد',
        onPressed: () => _openEditor(context, ref, null),
      ),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الموظفين',
          onRetry: () => ref.invalidate(staffListProvider),
        ),
        data: (rows) {
          if (rows.isEmpty) {
            return EmptyView(
              icon: Icons.badge_outlined,
              title: 'لا يوجد موظفون بعد',
              message: 'أضِف الكاشير ومديري الفروع وحدّد صلاحياتهم.',
              actionLabel: 'إضافة موظف',
              onAction: ref.permCan(PermResource.staff, PermAction.create)
                  ? () => _openEditor(context, ref, null)
                  : null,
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: rows.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final s = rows[i];
              final role = s['role'] as String?;
              final roleColor = _roleColors[role] ?? AppColors.primaryDark;
              return AppCard(
                onTap: () => _openEditor(context, ref, s),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 22,
                      backgroundColor: roleColor.withValues(alpha: .15),
                      child: AppIcon(Icons.person_outline, color: roleColor),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(s['name'] as String? ?? '—',
                              style: Theme.of(context).textTheme.titleMedium),
                          if (s['phone'] != null) ...[
                            const SizedBox(height: 4),
                            Text('${s['phone']}',
                                style:
                                    Theme.of(context).textTheme.bodySmall),
                          ],
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: roleColor.withValues(alpha: .15),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Text(
                        _roleLabels[role] ?? role ?? '',
                        style: TextStyle(
                            color: roleColor,
                            fontWeight: FontWeight.w700,
                            fontSize: 12),
                      ),
                    ),
                  ],
                ),
              )
                  .animate()
                  .fadeIn(duration: 300.ms, delay: (40 * i).ms)
                  .slideY(begin: .06, end: 0);
            },
          );
        },
      ),
    );
  }

  Future<void> _openEditor(BuildContext context, WidgetRef ref,
      Map<String, dynamic>? existing) async {
    final readOnly =
        existing != null && !ref.permCan(PermResource.staff, PermAction.edit);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _StaffEditor(existing: existing, readOnly: readOnly),
    );
    if (saved == true) ref.invalidate(staffListProvider);
  }
}

class _StaffEditor extends ConsumerStatefulWidget {
  final Map<String, dynamic>? existing;
  final bool readOnly;
  const _StaffEditor({this.existing, this.readOnly = false});
  @override
  ConsumerState<_StaffEditor> createState() => _StaffEditorState();
}

class _StaffEditorState extends ConsumerState<_StaffEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _phone;
  String _role = 'cashier';
  String? _branchId;
  String? _roleId;
  bool _canRedeemPrizes = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?['name'] as String? ?? '');
    _phone = TextEditingController(text: e?['phone'] as String? ?? '');
    _role = e?['role'] as String? ?? 'cashier';
    _branchId = e?['branch_id'] as String?;
    _roleId = e?['role_id'] as String?;
    _canRedeemPrizes = e?['can_redeem_prizes'] as bool? ?? false;
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final payload = {
        'merchant_id': staff.merchantId,
        'name': _name.text.trim(),
        'phone': _phone.text.trim(),
        'role': _role,
        'branch_id': _branchId,
        'role_id': _roleId,
        'can_redeem_prizes': _canRedeemPrizes,
      };
      final repo = ref.read(staffRepoProvider);
      if (widget.existing == null) {
        await repo.insertStaff(payload);
      } else {
        await repo.updateStaff(widget.existing!['id'] as String, payload);
      }
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ الموظف');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر الحفظ', error: true);
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final branchesAsync = ref.watch(branchOptionsProvider);
    final rolesAsync = ref.watch(staffRoleOptionsProvider);
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.existing == null ? 'موظف جديد' : 'تعديل الموظف',
                  style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            if (widget.readOnly) const ReadOnlyNotice(),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'الاسم'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'رقم الجوال'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _role,
              decoration: const InputDecoration(labelText: 'الدور'),
              items: _roleLabels.entries
                  .map((e) =>
                      DropdownMenuItem(value: e.key, child: Text(e.value)))
                  .toList(),
              onChanged: (v) => setState(() => _role = v ?? 'cashier'),
            ),
            const SizedBox(height: 12),
            branchesAsync.when(
              loading: () => const LinearProgressIndicator(),
              error: (_, __) => const SizedBox.shrink(),
              data: (branches) => DropdownButtonFormField<String?>(
                value: _branchId,
                decoration:
                    const InputDecoration(labelText: 'الفرع المرتبط'),
                items: [
                  const DropdownMenuItem<String?>(
                      value: null, child: Text('بدون فرع محدّد')),
                  ...branches.map((b) => DropdownMenuItem<String?>(
                        value: b['id'] as String,
                        child: Text(b['name'] as String? ?? '—'),
                      )),
                ],
                onChanged: (v) => setState(() => _branchId = v),
              ),
            ),
            const SizedBox(height: 12),
            rolesAsync.when(
              loading: () => const LinearProgressIndicator(),
              error: (_, __) => const SizedBox.shrink(),
              data: (roles) => DropdownButtonFormField<String?>(
                value: roles.any((r) => r['id'] == _roleId) ? _roleId : null,
                decoration:
                    const InputDecoration(labelText: 'الدور والصلاحيات'),
                items: [
                  const DropdownMenuItem<String?>(
                      value: null, child: Text('بدون دور محدّد')),
                  ...roles.map((r) => DropdownMenuItem<String?>(
                        value: r['id'] as String,
                        child: Text(r['name'] as String? ?? '—'),
                      )),
                ],
                onChanged: (v) => setState(() => _roleId = v),
              ),
            ),
            const SizedBox(height: 4),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _canRedeemPrizes,
              title: const Text('تفعيل الهدايا'),
              subtitle: const Text('السماح للموظف بتفعيل هدايا العملاء'),
              onChanged: (v) => setState(() => _canRedeemPrizes = v),
            ),
            const SizedBox(height: 16),
            if (!widget.readOnly)
              PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
            ],
          ),
        ),
      ),
    );
  }
}
