import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';

/// قائمة المستويات من جدول loyalty_levels مرتّبة تصاعديًا بالعتبة.
final levelsProvider =
    FutureProvider.autoDispose<List<LoyaltyLevel>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await Supabase.instance.client
      .from('loyalty_levels')
      .select()
      .eq('merchant_id', staff.merchantId)
      .order('threshold_lifetime_points');
  return List<Map<String, dynamic>>.from(rows)
      .map(LoyaltyLevel.fromJson)
      .toList();
});

/// 2.10.ج — المستويات.
class LevelsScreen extends ConsumerWidget {
  const LevelsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(levelsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('المستويات')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openEditor(context, ref, null),
        icon: const Icon(Icons.add),
        label: const Text('مستوى جديد'),
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
                const Icon(Icons.info_outline, color: AppColors.primaryDark),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'المستويات تعتمد على إجمالي النقاط (Lifetime) ولا تُخصم عند الوصول.',
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
                message: 'تعذّر تحميل المستويات',
                onRetry: () => ref.invalidate(levelsProvider),
              ),
              data: (levels) {
                if (levels.isEmpty) {
                  return EmptyView(
                    icon: Icons.military_tech_rounded,
                    title: 'لا توجد مستويات بعد',
                    message: 'أنشئ مستويات الولاء لتحفيز عملائك على تجميع النقاط.',
                    actionLabel: 'إنشاء مستوى',
                    onAction: () => _openEditor(context, ref, null),
                  );
                }
                return ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: levels.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (context, i) {
                    final lvl = levels[i];
                    return AppCard(
                      onTap: () => _openEditor(context, ref, lvl),
                      border: Border.all(
                          color: lvl.color.withValues(alpha: .35), width: 1.5),
                      child: Row(
                        children: [
                          // درجة السلّم: مؤشّر رتبة + ميدالية بلون المستوى.
                          Container(
                            width: 6,
                            height: 48,
                            decoration: BoxDecoration(
                              color: lvl.color,
                              borderRadius: BorderRadius.circular(4),
                            ),
                          ),
                          const SizedBox(width: 12),
                          CircleAvatar(
                            radius: 22,
                            backgroundColor: lvl.color.withValues(alpha: .2),
                            child: Icon(Icons.workspace_premium_rounded,
                                color: lvl.color),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(lvl.name,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium),
                                if (lvl.rewardDescription != null &&
                                    lvl.rewardDescription!.isNotEmpty) ...[
                                  const SizedBox(height: 4),
                                  Text(lvl.rewardDescription!,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall),
                                ],
                              ],
                            ),
                          ),
                          PointsBadge(points: lvl.thresholdLifetimePoints),
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

  Future<void> _openEditor(
      BuildContext context, WidgetRef ref, LoyaltyLevel? existing) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _LevelEditor(existing: existing),
    );
    if (saved == true) ref.invalidate(levelsProvider);
  }
}

class _LevelEditor extends ConsumerStatefulWidget {
  final LoyaltyLevel? existing;
  const _LevelEditor({this.existing});
  @override
  ConsumerState<_LevelEditor> createState() => _LevelEditorState();
}

class _LevelEditorState extends ConsumerState<_LevelEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _threshold;
  late final TextEditingController _rewardDesc;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _name = TextEditingController(text: e?.name ?? '');
    _threshold = TextEditingController(
        text: e == null ? '' : e.thresholdLifetimePoints.toString());
    _rewardDesc = TextEditingController(text: e?.rewardDescription ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _threshold.dispose();
    _rewardDesc.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final threshold = int.tryParse(_threshold.text.trim()) ?? 0;
      final payload = {
        'merchant_id': staff.merchantId,
        'name': _name.text.trim(),
        'threshold_lifetime_points': threshold,
        'reward_description': _rewardDesc.text.trim(),
        'sort_order': threshold, // الترتيب يتبع العتبة
      };
      final client = Supabase.instance.client;
      if (widget.existing == null) {
        await client.from('loyalty_levels').insert(payload);
      } else {
        await client
            .from('loyalty_levels')
            .update(payload)
            .eq('id', widget.existing!.id);
      }
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ المستوى');
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
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.existing == null ? 'مستوى جديد' : 'تعديل المستوى',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(
                  labelText: 'اسم المستوى (برونزي/فضي…)'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _threshold,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                  labelText: 'العتبة (إجمالي النقاط Lifetime)'),
              validator: (v) {
                final n = int.tryParse(v?.trim() ?? '');
                if (n == null || n < 0) return 'أدخل رقمًا صحيحًا';
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _rewardDesc,
              decoration: const InputDecoration(labelText: 'وصف المكافأة'),
              maxLines: 2,
            ),
            const SizedBox(height: 16),
            PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
          ],
        ),
        ),
      ),
    );
  }
}
