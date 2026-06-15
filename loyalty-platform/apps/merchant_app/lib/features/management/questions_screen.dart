import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';
import 'responses_screen.dart';

/// قائمة الأسئلة مع خياراتها من merchant_questions + question_options.
final questionsProvider =
    FutureProvider.autoDispose<List<MerchantQuestion>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await Supabase.instance.client
      .from('merchant_questions')
      .select('*, question_options(*)')
      .eq('merchant_id', staff.merchantId)
      .order('created_at');
  return List<Map<String, dynamic>>.from(rows)
      .map(MerchantQuestion.fromJson)
      .toList();
});

const _typeLabels = {
  QuestionType.singleChoice: 'اختيار واحد',
  QuestionType.multiChoice: 'اختيار متعدد',
  QuestionType.text: 'نص حر',
};

const _typeIcons = {
  QuestionType.singleChoice: Icons.radio_button_checked_rounded,
  QuestionType.multiChoice: Icons.check_box_rounded,
  QuestionType.text: Icons.short_text_rounded,
};

/// 2.10.ز — الأسئلة.
class QuestionsScreen extends ConsumerWidget {
  const QuestionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(questionsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('الأسئلة')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openEditor(context, ref, null),
        icon: const Icon(Icons.add),
        label: const Text('سؤال جديد'),
      ),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الأسئلة',
          onRetry: () => ref.invalidate(questionsProvider),
        ),
        data: (questions) {
          if (questions.isEmpty) {
            return EmptyView(
              icon: Icons.quiz_outlined,
              title: 'لا توجد أسئلة بعد',
              message: 'اطرح أسئلة على عملائك واجمع آراءهم مقابل نقاط.',
              actionLabel: 'إنشاء سؤال',
              onAction: () => _openEditor(context, ref, null),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: questions.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final q = questions[i];
              return AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          height: 44,
                          width: 44,
                          decoration: BoxDecoration(
                            color: AppColors.info.withValues(alpha: .15),
                            borderRadius: BorderRadius.circular(AppRadii.md),
                          ),
                          child: Icon(_typeIcons[q.type],
                              color: AppColors.info, size: 22),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(q.title,
                              style: Theme.of(context).textTheme.titleMedium),
                        ),
                        PointsBadge(points: q.pointsReward),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _Tag(label: _typeLabels[q.type] ?? ''),
                        _Tag(
                          label: q.active ? 'مفعّل' : 'متوقف',
                          color: q.active
                              ? AppColors.success
                              : AppColors.textSecondary,
                        ),
                        if (q.required)
                          const _Tag(
                              label: 'إجباري', color: AppColors.warning),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        TextButton.icon(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) =>
                                  QuestionResponsesScreen(questionId: q.id),
                            ),
                          ),
                          icon: const Icon(Icons.bar_chart_rounded, size: 18),
                          label: const Text('عرض الإجابات'),
                        ),
                        TextButton.icon(
                          onPressed: () => _openEditor(context, ref, q),
                          icon: const Icon(Icons.edit_outlined, size: 18),
                          label: const Text('تعديل'),
                        ),
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

  Future<void> _openEditor(
      BuildContext context, WidgetRef ref, MerchantQuestion? existing) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _QuestionEditor(existing: existing),
    );
    if (saved == true) ref.invalidate(questionsProvider);
  }
}

/// شارة صغيرة لوسم حالة/نوع السؤال.
class _Tag extends StatelessWidget {
  final String label;
  final Color color;
  const _Tag({required this.label, this.color = AppColors.info});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .15),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Text(label,
            style: TextStyle(
                color: color, fontWeight: FontWeight.w700, fontSize: 12)),
      );
}

class _QuestionEditor extends ConsumerStatefulWidget {
  final MerchantQuestion? existing;
  const _QuestionEditor({this.existing});
  @override
  ConsumerState<_QuestionEditor> createState() => _QuestionEditorState();
}

class _QuestionEditorState extends ConsumerState<_QuestionEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _desc;
  late final TextEditingController _points;
  QuestionType _type = QuestionType.singleChoice;
  late bool _required;
  late bool _active;
  late List<TextEditingController> _options;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _title = TextEditingController(text: e?.title ?? '');
    _desc = TextEditingController(text: e?.description ?? '');
    _points =
        TextEditingController(text: e == null ? '' : e.pointsReward.toString());
    _type = e?.type ?? QuestionType.singleChoice;
    _required = e?.required ?? false;
    _active = e?.active ?? true;
    _options = (e?.options ?? [])
        .map((o) => TextEditingController(text: o.label))
        .toList();
    if (_options.isEmpty && _type.isChoice) {
      _options = [TextEditingController(), TextEditingController()];
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _desc.dispose();
    _points.dispose();
    for (final c in _options) {
      c.dispose();
    }
    super.dispose();
  }

  void _addOption() => setState(() => _options.add(TextEditingController()));
  void _removeOption(int i) => setState(() => _options.removeAt(i).dispose());

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    final labels = _options
        .map((c) => c.text.trim())
        .where((t) => t.isNotEmpty)
        .toList();
    if (_type.isChoice && labels.length < 2) {
      AppFeedback.toast(context, 'أضِف خيارين على الأقل', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final client = Supabase.instance.client;
      final payload = {
        'merchant_id': staff.merchantId,
        'title': _title.text.trim(),
        'description': _desc.text.trim(),
        'type': _type.value,
        'points_reward': int.tryParse(_points.text.trim()) ?? 0,
        'required': _required,
        'active': _active,
      };

      String questionId;
      if (widget.existing == null) {
        final inserted = await client
            .from('merchant_questions')
            .insert(payload)
            .select('id')
            .single();
        questionId = inserted['id'] as String;
      } else {
        questionId = widget.existing!.id;
        await client
            .from('merchant_questions')
            .update(payload)
            .eq('id', questionId);
        // إعادة بناء الخيارات: حذف القديم ثم إدراج الجديد.
        await client
            .from('question_options')
            .delete()
            .eq('question_id', questionId);
      }

      if (_type.isChoice && labels.isNotEmpty) {
        await client.from('question_options').insert([
          for (var i = 0; i < labels.length; i++)
            {
              'question_id': questionId,
              'label': labels[i],
              'sort_order': i,
            }
        ]);
      }
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ السؤال');
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
              Text(widget.existing == null ? 'سؤال جديد' : 'تعديل السؤال',
                  style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              TextFormField(
                controller: _title,
                decoration: const InputDecoration(labelText: 'نص السؤال'),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _desc,
                decoration:
                    const InputDecoration(labelText: 'وصف (اختياري)'),
                maxLines: 2,
              ),
              const SizedBox(height: 16),
              Text('نوع السؤال',
                  style: Theme.of(context).textTheme.titleSmall),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: _typeLabels.entries.map((e) {
                  final selected = _type == e.key;
                  return ChoiceChip(
                    label: Text(e.value),
                    avatar: Icon(_typeIcons[e.key],
                        size: 18,
                        color: selected
                            ? AppColors.onPrimary
                            : AppColors.textSecondary),
                    selected: selected,
                    selectedColor: AppColors.primary,
                    onSelected: (_) {
                      setState(() {
                        _type = e.key;
                        if (_type.isChoice && _options.isEmpty) {
                          _options = [
                            TextEditingController(),
                            TextEditingController()
                          ];
                        }
                      });
                    },
                  );
                }).toList(),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _points,
                keyboardType: TextInputType.number,
                decoration:
                    const InputDecoration(labelText: 'النقاط الممنوحة'),
                validator: (v) {
                  final n = int.tryParse(v?.trim() ?? '');
                  if (n == null || n < 0) return 'أدخل رقمًا صحيحًا';
                  return null;
                },
              ),
              if (_type.isChoice) ...[
                const SizedBox(height: 16),
                Text('الخيارات',
                    style: Theme.of(context).textTheme.titleSmall),
                const SizedBox(height: 8),
                for (var i = 0; i < _options.length; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: _options[i],
                            decoration: InputDecoration(
                                labelText: 'الخيار ${i + 1}'),
                          ),
                        ),
                        IconButton(
                          onPressed: _options.length > 2
                              ? () => _removeOption(i)
                              : null,
                          icon: const Icon(Icons.remove_circle_outline),
                          color: AppColors.error,
                        ),
                      ],
                    ),
                  ),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton.icon(
                    onPressed: _addOption,
                    icon: const Icon(Icons.add),
                    label: const Text('إضافة خيار'),
                  ),
                ),
              ],
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('إجباري'),
                value: _required,
                onChanged: (v) => setState(() => _required = v),
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
      ),
    );
  }
}
