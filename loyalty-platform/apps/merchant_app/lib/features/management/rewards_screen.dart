import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';

/// قائمة المكافآت من جدول rewards.
final rewardsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await Supabase.instance.client
      .from('rewards')
      .select()
      .eq('merchant_id', staff.merchantId)
      .order('created_at');
  return List<Map<String, dynamic>>.from(rows);
});

/// 2.10.ب — المكافآت.
class RewardsManagementScreen extends ConsumerWidget {
  const RewardsManagementScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(rewardsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('المكافآت')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openEditor(context, ref, null),
        icon: const Icon(Icons.add),
        label: const Text('مكافأة جديدة'),
      ),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل المكافآت',
          onRetry: () => ref.invalidate(rewardsProvider),
        ),
        data: (rows) {
          if (rows.isEmpty) {
            return EmptyView(
              icon: Icons.card_giftcard_rounded,
              title: 'لا توجد مكافآت بعد',
              message: 'أضِف أول مكافأة يمكن لعملائك استبدالها بنقاطهم.',
              actionLabel: 'إنشاء مكافأة',
              onAction: () => _openEditor(context, ref, null),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: rows.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final r = rows[i];
              final stock = r['stock_qty'];
              return AppCard(
                onTap: () => _openEditor(context, ref, r),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 24,
                      backgroundColor: AppColors.surfaceCream,
                      // TODO: عرض صورة المكافأة من image_url لو متوفّرة.
                      child: const Icon(Icons.card_giftcard_rounded,
                          color: AppColors.primaryDark),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(r['name'] as String? ?? '—',
                              style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 4),
                          Text(
                            stock == null
                                ? 'الكمية: غير محدودة'
                                : 'الكمية: $stock',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        PointsBadge(points: (r['points_cost'] as int?) ?? 0),
                        const SizedBox(height: 6),
                        _ActiveChip(active: r['active'] == true),
                      ],
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
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _RewardEditor(existing: existing),
    );
    if (saved == true) ref.invalidate(rewardsProvider);
  }
}

class _ActiveChip extends StatelessWidget {
  final bool active;
  const _ActiveChip({required this.active});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: (active ? AppColors.success : AppColors.textSecondary)
              .withValues(alpha: .15),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Text(active ? 'مفعّلة' : 'متوقفة',
            style: TextStyle(
                color: active ? AppColors.success : AppColors.textSecondary,
                fontWeight: FontWeight.w700,
                fontSize: 12)),
      );
}

class _RewardEditor extends ConsumerStatefulWidget {
  final Map<String, dynamic>? existing;
  const _RewardEditor({this.existing});
  @override
  ConsumerState<_RewardEditor> createState() => _RewardEditorState();
}

class _RewardEditorState extends ConsumerState<_RewardEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _desc;
  late final TextEditingController _pointsCost;
  late final TextEditingController _stock;
  late bool _unlimited;
  late bool _active;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?['name'] as String? ?? '');
    _desc = TextEditingController(text: e?['description'] as String? ?? '');
    _pointsCost =
        TextEditingController(text: (e?['points_cost'] ?? '').toString());
    final stock = e?['stock_qty'];
    _unlimited = stock == null;
    _stock = TextEditingController(text: stock?.toString() ?? '');
    _active = e?['active'] as bool? ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _desc.dispose();
    _pointsCost.dispose();
    _stock.dispose();
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
        'description': _desc.text.trim(),
        'points_cost': int.tryParse(_pointsCost.text.trim()) ?? 0,
        'stock_qty':
            _unlimited ? null : (int.tryParse(_stock.text.trim()) ?? 0),
        // TODO: رفع صورة المكافأة عبر image_picker + flutter_image_compress → image_url
        'active': _active,
      };
      final client = Supabase.instance.client;
      if (widget.existing == null) {
        await client.from('rewards').insert(payload);
      } else {
        await client
            .from('rewards')
            .update(payload)
            .eq('id', widget.existing!['id'] as String);
      }
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ المكافأة');
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
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(widget.existing == null ? 'مكافأة جديدة' : 'تعديل المكافأة',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            // TODO: زر اختيار صورة المكافأة (image_picker).
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم الجائزة'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _desc,
              decoration: const InputDecoration(labelText: 'الوصف'),
              maxLines: 2,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _pointsCost,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'عدد النقاط'),
              validator: (v) {
                final n = int.tryParse(v?.trim() ?? '');
                if (n == null || n <= 0) return 'أدخل رقمًا صحيحًا';
                return null;
              },
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('كمية غير محدودة'),
              value: _unlimited,
              onChanged: (v) => setState(() => _unlimited = v),
            ),
            if (!_unlimited)
              TextFormField(
                controller: _stock,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'الكمية المتاحة'),
                validator: (v) {
                  if (_unlimited) return null;
                  final n = int.tryParse(v?.trim() ?? '');
                  if (n == null || n < 0) return 'أدخل رقمًا صحيحًا';
                  return null;
                },
              ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('مفعّلة'),
              value: _active,
              onChanged: (v) => setState(() => _active = v),
            ),
            const SizedBox(height: 8),
            PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
          ],
        ),
      ),
    );
  }
}
