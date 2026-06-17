import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../core/perm_gate.dart';
import '../../data/repositories/coupons_repository.dart';
import '../../data/repositories/entity_branches_repository.dart';
import 'branch_target_field.dart';

/// قائمة الكوبونات من جدول coupons.
final couponsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(couponsRepoProvider).fetchCoupons(staff.merchantId);
});

const _couponTypeLabels = {
  'percent': 'نسبة %',
  'fixed': 'مبلغ ثابت',
  'free_item': 'منتج مجاني',
};

/// 2.10.د — الكوبونات.
class CouponsScreen extends ConsumerWidget {
  const CouponsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(couponsProvider);
    final df = DateFormat('yyyy/MM/dd');

    return Scaffold(
      appBar: AppBar(title: const Text('الكوبونات')),
      floatingActionButton: PermFab(
        resource: PermResource.coupons,
        label: 'كوبون جديد',
        onPressed: () => _openEditor(context, ref, null),
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.surfaceCream,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: [
                const AppIcon(Icons.lightbulb_outline,
                    color: AppColors.primaryDark),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'الكوبون خصم فوري بكود، بينما المكافأة تُستبدل بالنقاط. تجنّب تكرار نفس العرض في الاثنين حتى لا يلتبس على العميل.',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: async.when(
              loading: () => const SkeletonList(),
              error: (e, _) => ErrorView(
                message: 'تعذّر تحميل الكوبونات',
                onRetry: () => ref.invalidate(couponsProvider),
              ),
              data: (rows) {
                if (rows.isEmpty) {
                  return EmptyView(
                    icon: Icons.confirmation_num_outlined,
                    title: 'لا توجد كوبونات بعد',
                    message: 'أنشئ كوبون خصم لجذب عملائك وتشجيعهم على الشراء.',
                    actionLabel: 'إنشاء كوبون',
                    onAction: ref.permCan(PermResource.coupons, PermAction.create)
                        ? () => _openEditor(context, ref, null)
                        : null,
                  );
                }
                return ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: rows.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (context, i) {
                    final c = rows[i];
                    final from = c['valid_from'];
                    final to = c['valid_to'];
                    String validity = '';
                    if (from != null && to != null) {
                      validity =
                          '${df.format(DateTime.parse(from as String))} — ${df.format(DateTime.parse(to as String))}';
                    }
                    return AppCard(
                      onTap: () => _openEditor(context, ref, c),
                      child: Row(
                        children: [
                          Container(
                            height: 48,
                            width: 48,
                            decoration: BoxDecoration(
                              color: AppColors.error.withValues(alpha: .12),
                              borderRadius: BorderRadius.circular(AppRadii.md),
                            ),
                            child: const AppIcon(
                                Icons.confirmation_num_outlined,
                                color: AppColors.error),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(c['code'] as String? ?? '—',
                                          style: Theme.of(context)
                                              .textTheme
                                              .titleMedium),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 10, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: AppColors.primaryLight,
                                        borderRadius:
                                            BorderRadius.circular(16),
                                      ),
                                      child: Text(
                                        _couponTypeLabels[c['type']] ??
                                            (c['type'] as String? ?? ''),
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                            fontSize: 12,
                                            color: AppColors.onPrimary),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  'القيمة: ${c['value'] ?? '-'}'
                                  '${validity.isEmpty ? '' : '  •  $validity'}',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
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
          ),
        ],
      ),
    );
  }

  Future<void> _openEditor(BuildContext context, WidgetRef ref,
      Map<String, dynamic>? existing) async {
    final readOnly =
        existing != null && !ref.permCan(PermResource.coupons, PermAction.edit);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CouponEditor(existing: existing, readOnly: readOnly),
    );
    if (saved == true) ref.invalidate(couponsProvider);
  }
}

class _CouponEditor extends ConsumerStatefulWidget {
  final Map<String, dynamic>? existing;
  final bool readOnly;
  const _CouponEditor({this.existing, this.readOnly = false});
  @override
  ConsumerState<_CouponEditor> createState() => _CouponEditorState();
}

class _CouponEditorState extends ConsumerState<_CouponEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _code;
  late final TextEditingController _value;
  late final TextEditingController _usageLimit;
  late final TextEditingController _perUserLimit;
  String _type = 'percent';
  DateTime? _from;
  DateTime? _to;
  bool _busy = false;
  final BranchTargetController _branchTarget = BranchTargetController();
  bool _targetLoaded = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    if (e == null) {
      _targetLoaded = true;
    } else {
      _loadTarget(e['id'] as String);
    }
    _code = TextEditingController(text: e?['code'] as String? ?? '');
    _value = TextEditingController(text: (e?['value'] ?? '').toString());
    _usageLimit =
        TextEditingController(text: (e?['usage_limit'] ?? '').toString());
    _perUserLimit =
        TextEditingController(text: (e?['per_user_limit'] ?? '').toString());
    _type = e?['type'] as String? ?? 'percent';
    if (e?['valid_from'] != null) {
      _from = DateTime.tryParse(e!['valid_from'] as String);
    }
    if (e?['valid_to'] != null) {
      _to = DateTime.tryParse(e!['valid_to'] as String);
    }
  }

  @override
  void dispose() {
    _code.dispose();
    _value.dispose();
    _usageLimit.dispose();
    _perUserLimit.dispose();
    super.dispose();
  }

  Future<void> _loadTarget(String id) async {
    final ids =
        await ref.read(entityBranchesRepoProvider).branchIdsFor('coupon', id);
    if (mounted) {
      setState(() {
        _branchTarget.selected.addAll(ids);
        _targetLoaded = true;
      });
    }
  }

  Future<void> _pickDate({required bool isFrom}) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: (isFrom ? _from : _to) ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 5),
    );
    if (picked != null) {
      setState(() {
        if (isFrom) {
          _from = picked;
        } else {
          _to = picked;
        }
      });
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final payload = {
        'merchant_id': staff.merchantId,
        'code': _code.text.trim(),
        'type': _type,
        'value': double.tryParse(_value.text.trim()) ?? 0,
        'valid_from': _from?.toIso8601String(),
        'valid_to': _to?.toIso8601String(),
        'usage_limit': int.tryParse(_usageLimit.text.trim()),
        'per_user_limit': int.tryParse(_perUserLimit.text.trim()),
      };
      final repo = ref.read(couponsRepoProvider);
      final String couponId;
      if (widget.existing == null) {
        couponId = await repo.insertCoupon(payload);
      } else {
        couponId = widget.existing!['id'] as String;
        await repo.updateCoupon(couponId, payload);
      }
      await ref.read(entityBranchesRepoProvider).setBranches(
          'coupon', couponId, staff.merchantId, _branchTarget.branchIds);
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ الكوبون');
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
    final df = DateFormat('yyyy/MM/dd');
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
              Text(widget.existing == null ? 'كوبون جديد' : 'تعديل الكوبون',
                  style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              if (widget.readOnly) const ReadOnlyNotice(),
              TextFormField(
                controller: _code,
                decoration: const InputDecoration(labelText: 'الكود'),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: _type,
                decoration: const InputDecoration(labelText: 'النوع'),
                items: _couponTypeLabels.entries
                    .map((e) => DropdownMenuItem(
                        value: e.key, child: Text(e.value)))
                    .toList(),
                onChanged: (v) => setState(() => _type = v ?? 'percent'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _value,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'القيمة'),
                validator: (v) {
                  if (_type == 'free_item') return null;
                  final n = double.tryParse(v?.trim() ?? '');
                  if (n == null || n <= 0) return 'أدخل رقمًا صحيحًا';
                  return null;
                },
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _pickDate(isFrom: true),
                      icon: const AppIcon(Icons.calendar_today, size: 18),
                      label: Text(
                          _from == null ? 'من تاريخ' : df.format(_from!)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _pickDate(isFrom: false),
                      icon: const AppIcon(Icons.event, size: 18),
                      label:
                          Text(_to == null ? 'إلى تاريخ' : df.format(_to!)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _usageLimit,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                    labelText: 'حد الاستخدام الإجمالي (اختياري)'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _perUserLimit,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                    labelText: 'حد الاستخدام لكل عميل (اختياري)'),
              ),
              const Divider(height: 24),
              if (_targetLoaded)
                BranchTargetField(controller: _branchTarget)
              else
                const Padding(
                    padding: EdgeInsets.all(8),
                    child: LinearProgressIndicator()),
              const SizedBox(height: 16),
              if (!widget.readOnly)
                PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
              if (widget.existing != null)
                DeleteActionButton(
                  resource: PermResource.coupons,
                  onDelete: () => ref
                      .read(couponsRepoProvider)
                      .deleteCoupon(widget.existing!['id'] as String),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
