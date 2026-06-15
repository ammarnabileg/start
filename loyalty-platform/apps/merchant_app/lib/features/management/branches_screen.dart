import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';

/// قائمة الفروع من جدول branches.
final branchesProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await Supabase.instance.client
      .from('branches')
      .select()
      .eq('merchant_id', staff.merchantId)
      .order('created_at');
  return List<Map<String, dynamic>>.from(rows);
});

/// 2.10.هـ — الفروع.
class BranchesScreen extends ConsumerWidget {
  const BranchesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(branchesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('الفروع')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openEditor(context, ref, null),
        icon: const Icon(Icons.add),
        label: const Text('فرع جديد'),
      ),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الفروع',
          onRetry: () => ref.invalidate(branchesProvider),
        ),
        data: (rows) {
          if (rows.isEmpty) {
            return EmptyView(
              icon: Icons.store_mall_directory_outlined,
              title: 'لا توجد فروع بعد',
              message: 'أضِف فروع متجرك لتفعيل تقارير الفروع وإشعار القرب.',
              actionLabel: 'إضافة فرع',
              onAction: () => _openEditor(context, ref, null),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: rows.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final b = rows[i];
              return AppCard(
                onTap: () => _openEditor(context, ref, b),
                child: Row(
                  children: [
                    Container(
                      height: 48,
                      width: 48,
                      decoration: BoxDecoration(
                        color: AppColors.success.withValues(alpha: .15),
                        borderRadius: BorderRadius.circular(AppRadii.md),
                      ),
                      child: const Icon(Icons.location_on_outlined,
                          color: AppColors.success),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(b['name'] as String? ?? '—',
                              style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 4),
                          Text(b['address'] as String? ?? 'بدون عنوان',
                              style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                    _ActiveChip(active: b['active'] == true),
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
      builder: (_) => _BranchEditor(existing: existing),
    );
    if (saved == true) ref.invalidate(branchesProvider);
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
        child: Text(active ? 'مفعّل' : 'متوقف',
            style: TextStyle(
                color: active ? AppColors.success : AppColors.textSecondary,
                fontWeight: FontWeight.w700,
                fontSize: 12)),
      );
}

class _BranchEditor extends ConsumerStatefulWidget {
  final Map<String, dynamic>? existing;
  const _BranchEditor({this.existing});
  @override
  ConsumerState<_BranchEditor> createState() => _BranchEditorState();
}

class _BranchEditorState extends ConsumerState<_BranchEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _address;
  late final TextEditingController _radius;
  double? _lat;
  double? _lng;
  late bool _active;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?['name'] as String? ?? '');
    _address = TextEditingController(text: e?['address'] as String? ?? '');
    _radius = TextEditingController(
        text: (e?['geofence_radius_m'] ?? 150).toString());
    _lat = (e?['lat'] as num?)?.toDouble();
    _lng = (e?['lng'] as num?)?.toDouble();
    _active = e?['active'] as bool? ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _address.dispose();
    _radius.dispose();
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
        'address': _address.text.trim(),
        // TODO: اختيار الموقع من الخريطة لتعبئة lat/lng.
        'lat': _lat,
        'lng': _lng,
        'geofence_radius_m': int.tryParse(_radius.text.trim()) ?? 150,
        'active': _active,
      };
      final client = Supabase.instance.client;
      if (widget.existing == null) {
        await client.from('branches').insert(payload);
      } else {
        await client
            .from('branches')
            .update(payload)
            .eq('id', widget.existing!['id'] as String);
      }
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ الفرع');
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
            Text(widget.existing == null ? 'فرع جديد' : 'تعديل الفرع',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم الفرع'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _address,
              decoration: const InputDecoration(labelText: 'العنوان'),
              maxLines: 2,
            ),
            const SizedBox(height: 12),
            // TODO: اختيار الموقع على الخريطة لتحديد lat/lng.
            OutlinedButton.icon(
              onPressed: () {
                AppFeedback.toast(
                    context, 'اختيار الموقع على الخريطة — قريبًا');
              },
              icon: const Icon(Icons.map_outlined),
              label: Text(_lat == null || _lng == null
                  ? 'تحديد الموقع على الخريطة'
                  : 'الموقع: ${_lat!.toStringAsFixed(4)}, ${_lng!.toStringAsFixed(4)}'),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _radius,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                  labelText: 'نطاق إشعار القرب (متر)'),
              validator: (v) {
                final n = int.tryParse(v?.trim() ?? '');
                if (n == null || n <= 0) return 'أدخل رقمًا صحيحًا';
                return null;
              },
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('مفعّل'),
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
