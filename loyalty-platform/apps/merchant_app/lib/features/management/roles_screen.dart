import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';

/// أدوار التاجر. لو فارغة، نزرع الأدوار الافتراضية ثم نعيد التحميل.
final rolesProvider =
    FutureProvider.autoDispose<List<MerchantRole>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final client = Supabase.instance.client;
  Future<List<MerchantRole>> fetch() async {
    final rows = await client
        .from('merchant_roles')
        .select()
        .eq('merchant_id', staff.merchantId)
        .order('is_system', ascending: false)
        .order('name');
    return List<Map<String, dynamic>>.from(rows)
        .map(MerchantRole.fromJson)
        .toList();
  }

  var roles = await fetch();
  if (roles.isEmpty) {
    await client.rpc('seed_default_roles',
        params: {'p_merchant': staff.merchantId});
    roles = await fetch();
  }
  return roles;
});

/// (ب) الأدوار والصلاحيات.
class RolesScreen extends ConsumerStatefulWidget {
  const RolesScreen({super.key});

  @override
  ConsumerState<RolesScreen> createState() => _RolesScreenState();
}

class _RolesScreenState extends ConsumerState<RolesScreen> {
  @override
  Widget build(BuildContext context) {
    final async = ref.watch(rolesProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('الأدوار والصلاحيات')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openEditor(null),
        icon: const Icon(Icons.add),
        label: const Text('دور جديد'),
      ),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الأدوار',
          onRetry: () => ref.invalidate(rolesProvider),
        ),
        data: (roles) {
          if (roles.isEmpty) {
            return EmptyView(
              icon: Icons.admin_panel_settings_outlined,
              title: 'لا توجد أدوار بعد',
              message: 'أنشئ أدوارًا مخصّصة وحدّد صلاحيات كل دور.',
              actionLabel: 'إنشاء دور',
              onAction: () => _openEditor(null),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: roles.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final r = roles[i];
              final accent =
                  r.isOwner ? AppColors.goldTier : AppColors.primaryDark;
              return AppCard(
                onTap: () => _openEditor(r),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 22,
                      backgroundColor: accent.withValues(alpha: .15),
                      child: Icon(
                        r.isOwner
                            ? Icons.workspace_premium_outlined
                            : Icons.shield_outlined,
                        color: accent,
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(r.name,
                              style:
                                  Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 4),
                          Text(
                            r.isOwner
                                ? 'صلاحيات كاملة'
                                : _permsSummary(r),
                            style: Theme.of(context).textTheme.bodySmall,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    if (r.isSystem)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: AppColors.info.withValues(alpha: .15),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: const Text('نظامي',
                            style: TextStyle(
                                color: AppColors.info,
                                fontWeight: FontWeight.w700,
                                fontSize: 12)),
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

  String _permsSummary(MerchantRole r) {
    final granted = PermResource.all
        .where((res) => PermAction.all.any((a) => r.can(res, a)))
        .map(PermResource.label)
        .toList();
    if (granted.isEmpty) return 'بدون صلاحيات';
    return granted.join('، ');
  }

  Future<void> _openEditor(MerchantRole? role) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _RoleEditor(existing: role),
    );
    if (saved == true) ref.invalidate(rolesProvider);
  }
}

class _RoleEditor extends ConsumerStatefulWidget {
  final MerchantRole? existing;
  const _RoleEditor({this.existing});
  @override
  ConsumerState<_RoleEditor> createState() => _RoleEditorState();
}

class _RoleEditorState extends ConsumerState<_RoleEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  // resource -> set of selected actions.
  final Map<String, Set<String>> _matrix = {};
  bool _busy = false;

  bool get _readOnly => widget.existing?.isOwner ?? false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?.name ?? '');
    for (final res in PermResource.all) {
      final selected = <String>{};
      final actions = [...PermAction.all, if (res == PermResource.prizes) 'redeem'];
      for (final a in actions) {
        if (e != null && e.can(res, a)) selected.add(a);
      }
      _matrix[res] = selected;
    }
  }

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Map<String, dynamic> _buildPermissions() {
    final perms = <String, dynamic>{};
    _matrix.forEach((res, actions) {
      if (actions.isNotEmpty) perms[res] = actions.toList();
    });
    return perms;
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final client = Supabase.instance.client;
      final role = MerchantRole(
        id: widget.existing?.id ?? '',
        merchantId: staff.merchantId,
        name: _name.text.trim(),
        permissions: _buildPermissions(),
        isSystem: widget.existing?.isSystem ?? false,
      );
      if (widget.existing == null) {
        await client.from('merchant_roles').insert(role.toJson());
      } else {
        await client
            .from('merchant_roles')
            .update(role.toJson())
            .eq('id', widget.existing!.id);
      }
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ الدور');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر الحفظ', error: true);
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف الدور'),
        content: const Text('هل تريد حذف هذا الدور؟'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('حذف',
                  style: TextStyle(color: AppColors.error))),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _busy = true);
    try {
      await Supabase.instance.client
          .from('merchant_roles')
          .delete()
          .eq('id', widget.existing!.id);
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حذف الدور');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر الحذف', error: true);
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final canDelete =
        widget.existing != null && !widget.existing!.isSystem;
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
              Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.existing == null
                          ? 'دور جديد'
                          : 'تعديل الدور',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  if (canDelete)
                    IconButton(
                      tooltip: 'حذف',
                      onPressed: _busy ? null : _delete,
                      icon: const Icon(Icons.delete_outline,
                          color: AppColors.error),
                    ),
                ],
              ),
              if (_readOnly) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceCream,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.lock_outline,
                          color: AppColors.primaryDark),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'دور المالك يملك صلاحيات كاملة ولا يمكن تعديله.',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 16),
              TextFormField(
                controller: _name,
                enabled: !_readOnly,
                decoration:
                    const InputDecoration(labelText: 'اسم الدور'),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
              ),
              const SizedBox(height: 16),
              const SectionHeader(title: 'الصلاحيات'),
              const SizedBox(height: 8),
              for (final res in PermResource.all)
                _buildResourceRow(context, res),
              const SizedBox(height: 16),
              if (!_readOnly)
                PrimaryButton(
                    label: 'حفظ', loading: _busy, onPressed: _save),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildResourceRow(BuildContext context, String res) {
    final actions = [
      ...PermAction.all,
      if (res == PermResource.prizes) 'redeem',
    ];
    final selected = _readOnly ? null : _matrix[res]!;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(PermResource.label(res),
              style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              for (final a in actions)
                FilterChip(
                  label: Text(PermAction.label(a)),
                  selected: _readOnly || (selected?.contains(a) ?? false),
                  onSelected: _readOnly
                      ? null
                      : (v) {
                          setState(() {
                            if (v) {
                              selected!.add(a);
                            } else {
                              selected!.remove(a);
                            }
                          });
                        },
                ),
            ],
          ),
        ],
      ),
    );
  }
}
