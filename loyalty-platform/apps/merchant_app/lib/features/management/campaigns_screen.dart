import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';

/// قائمة حملات الزيارة من جدول visit_campaigns.
final campaignsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await Supabase.instance.client
      .from('visit_campaigns')
      .select()
      .eq('merchant_id', staff.merchantId)
      .order('created_at');
  return List<Map<String, dynamic>>.from(rows);
});

/// 2.10.أ — حملات الزيارة.
class CampaignsScreen extends ConsumerWidget {
  const CampaignsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(campaignsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('حملات الزيارة')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openEditor(context, ref, null),
        icon: const Icon(Icons.add),
        label: const Text('حملة جديدة'),
      ),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الحملات',
          onRetry: () => ref.invalidate(campaignsProvider),
        ),
        data: (rows) {
          if (rows.isEmpty) {
            return EmptyView(
              icon: Icons.repeat_rounded,
              title: 'لا توجد حملات بعد',
              message: 'أنشئ أول حملة زيارات لتشجيع عملائك على العودة.',
              actionLabel: 'إنشاء حملة',
              onAction: () => _openEditor(context, ref, null),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: rows.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final c = rows[i];
              return AppCard(
                onTap: () => _openEditor(context, ref, c),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(c['name'] as String? ?? '—',
                              style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 4),
                          Text(
                            '${c['required_visits'] ?? 0} زيارة → ${c['reward_name'] ?? ''}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    _ActiveChip(active: c['active'] == true),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _openEditor(
      BuildContext context, WidgetRef ref, Map<String, dynamic>? existing) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CampaignEditor(existing: existing),
    );
    if (saved == true) ref.invalidate(campaignsProvider);
  }
}

class _ActiveChip extends StatelessWidget {
  final bool active;
  const _ActiveChip({required this.active});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
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

class _CampaignEditor extends ConsumerStatefulWidget {
  final Map<String, dynamic>? existing;
  const _CampaignEditor({this.existing});
  @override
  ConsumerState<_CampaignEditor> createState() => _CampaignEditorState();
}

class _CampaignEditorState extends ConsumerState<_CampaignEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _visits;
  late final TextEditingController _rewardName;
  late final TextEditingController _rewardDesc;
  late bool _active;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?['name'] as String? ?? '');
    _visits =
        TextEditingController(text: (e?['required_visits'] ?? '').toString());
    _rewardName =
        TextEditingController(text: e?['reward_name'] as String? ?? '');
    _rewardDesc =
        TextEditingController(text: e?['reward_description'] as String? ?? '');
    _active = e?['active'] as bool? ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _visits.dispose();
    _rewardName.dispose();
    _rewardDesc.dispose();
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
        'required_visits': int.tryParse(_visits.text.trim()) ?? 0,
        'reward_name': _rewardName.text.trim(),
        'reward_description': _rewardDesc.text.trim(),
        // TODO: رفع صورة المكافأة عبر image_picker + flutter_image_compress → reward_image_url
        'active': _active,
      };
      final client = Supabase.instance.client;
      if (widget.existing == null) {
        await client.from('visit_campaigns').insert(payload);
      } else {
        await client
            .from('visit_campaigns')
            .update(payload)
            .eq('id', widget.existing!['id'] as String);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('تعذّر الحفظ')));
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
            Text(widget.existing == null ? 'حملة جديدة' : 'تعديل الحملة',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم الحملة'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _visits,
              keyboardType: TextInputType.number,
              decoration:
                  const InputDecoration(labelText: 'عدد الزيارات المطلوبة'),
              validator: (v) {
                final n = int.tryParse(v?.trim() ?? '');
                if (n == null || n <= 0) return 'أدخل رقمًا صحيحًا';
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _rewardName,
              decoration: const InputDecoration(labelText: 'اسم المكافأة'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            // TODO: زر اختيار صورة المكافأة (image_picker).
            TextFormField(
              controller: _rewardDesc,
              decoration: const InputDecoration(labelText: 'وصف المكافأة'),
              maxLines: 2,
            ),
            const SizedBox(height: 8),
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
