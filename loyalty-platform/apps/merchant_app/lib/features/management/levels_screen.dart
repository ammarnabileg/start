import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../core/perm_gate.dart';
import '../../data/repositories/branches_repository.dart';
import '../../data/repositories/levels_repository.dart';

/// المستويات حسب النطاق: لو [branchId] = null فهي مستويات الستور كله،
/// وإلا مستويات فرع محدّد (في وضع النقاط المنفصل لكل فرع).
final levelsProvider =
    FutureProvider.autoDispose.family<List<LoyaltyLevel>, String?>(
        (ref, branchId) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await ref
      .read(levelsRepoProvider)
      .fetchLevels(staff.merchantId, branchId: branchId);
  return rows.map(LoyaltyLevel.fromJson).toList();
});

/// فروع التاجر (id, name) لاختيار الفرع في وضع المستويات المنفصلة.
final _branchOptionsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(branchesRepoProvider).fetchActiveBranchOptions(staff.merchantId);
});

/// 2.10.ج — المستويات (لكل الستور أو لكل فرع حسب إعداد نطاق النقاط).
class LevelsScreen extends ConsumerStatefulWidget {
  const LevelsScreen({super.key});

  @override
  ConsumerState<LevelsScreen> createState() => _LevelsScreenState();
}

class _LevelsScreenState extends ConsumerState<LevelsScreen> {
  String? _branchId; // الفرع المختار (وضع منفصل) — null = الستور كله

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(merchantSettingsProvider);
    return settings.when(
      loading: () => const Scaffold(body: LoadingView()),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: const Text('المستويات')),
        body: ErrorView(
            message: 'تعذّر تحميل الإعدادات',
            onRetry: () => ref.invalidate(merchantSettingsProvider)),
      ),
      data: (s) {
        final perBranch = s.pointsScope == PointsScope.branch;
        if (!perBranch) {
          // الستور كله — مستويات واحدة تنطبق على كل الفروع.
          return const _LevelsBody(branchId: null, perBranch: false, branchPicker: null);
        }
        // منفصل لكل فرع — نختار الفرع ونعرض مستوياته.
        final opts = ref.watch(_branchOptionsProvider);
        return opts.when(
          loading: () => const Scaffold(body: LoadingView()),
          error: (e, _) => Scaffold(
            appBar: AppBar(title: const Text('المستويات')),
            body: ErrorView(
                message: 'تعذّر تحميل الفروع',
                onRetry: () => ref.invalidate(_branchOptionsProvider)),
          ),
          data: (branches) {
            if (branches.isEmpty) {
              return Scaffold(
                appBar: AppBar(title: const Text('المستويات')),
                body: const EmptyView(
                  icon: Icons.store_mall_directory_outlined,
                  title: 'لا توجد فروع بعد',
                  message:
                      'النقاط مضبوطة كمنفصلة لكل فرع — أضف فرعًا أولًا لتحديد مستوياته.',
                ),
              );
            }
            final selected = _branchId ?? branches.first['id'] as String;
            return _LevelsBody(
              branchId: selected,
              perBranch: true,
              branchPicker: _BranchPicker(
                branches: branches,
                value: selected,
                onChanged: (v) => setState(() => _branchId = v),
              ),
            );
          },
        );
      },
    );
  }
}

class _BranchPicker extends StatelessWidget {
  final List<Map<String, dynamic>> branches;
  final String value;
  final ValueChanged<String> onChanged;
  const _BranchPicker(
      {required this.branches, required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(
        children: [
          const AppIcon(Icons.store_mall_directory_outlined,
              color: AppColors.primaryDark),
          const SizedBox(width: 8),
          const Text('الفرع:', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(width: 8),
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                isExpanded: true,
                value: value,
                items: [
                  for (final b in branches)
                    DropdownMenuItem(
                      value: b['id'] as String,
                      child: Text(b['name'] as String? ?? 'فرع'),
                    ),
                ],
                onChanged: (v) {
                  if (v != null) onChanged(v);
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LevelsBody extends ConsumerWidget {
  final String? branchId;
  final bool perBranch;
  final Widget? branchPicker;
  const _LevelsBody(
      {required this.branchId, required this.perBranch, this.branchPicker});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(levelsProvider(branchId));
    return Scaffold(
      appBar: AppBar(title: const Text('المستويات')),
      floatingActionButton: PermFab(
        resource: PermResource.levels,
        label: 'مستوى جديد',
        onPressed: () => _openEditor(context, ref, null),
      ),
      body: Column(
        children: [
          if (branchPicker != null) branchPicker!,
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
                const AppIcon(Icons.info_outline, color: AppColors.primaryDark),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    perBranch
                        ? 'النقاط منفصلة لكل فرع — هذه المستويات تخصّ الفرع المختار فقط.'
                        : 'النقاط مشتركة بين الفروع — هذه المستويات تنطبق على الستور كله.',
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
                onRetry: () => ref.invalidate(levelsProvider(branchId)),
              ),
              data: (levels) {
                if (levels.isEmpty) {
                  return EmptyView(
                    icon: Icons.military_tech_rounded,
                    title: 'لا توجد مستويات بعد',
                    message:
                        'أنشئ مستويات الولاء لتحفيز عملائك على تجميع النقاط.',
                    actionLabel: 'إنشاء مستوى',
                    onAction: ref.permCan(PermResource.levels, PermAction.create)
                        ? () => _openEditor(context, ref, null)
                        : null,
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
                            child: AppIcon(Icons.workspace_premium_rounded,
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
    final readOnly =
        existing != null && !ref.permCan(PermResource.levels, PermAction.edit);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) =>
          _LevelEditor(existing: existing, branchId: branchId, readOnly: readOnly),
    );
    if (saved == true) ref.invalidate(levelsProvider(branchId));
  }
}

class _LevelEditor extends ConsumerStatefulWidget {
  final LoyaltyLevel? existing;
  final String? branchId;
  final bool readOnly;
  const _LevelEditor({this.existing, this.branchId, this.readOnly = false});
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
        'branch_id': widget.branchId, // null = الستور كله
        'name': _name.text.trim(),
        'threshold_lifetime_points': threshold,
        'reward_description': _rewardDesc.text.trim(),
        'sort_order': threshold, // الترتيب يتبع العتبة
      };
      final repo = ref.read(levelsRepoProvider);
      if (widget.existing == null) {
        await repo.insertLevel(payload);
      } else {
        await repo.updateLevel(widget.existing!.id, payload);
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
              if (widget.readOnly) const ReadOnlyNotice(),
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
              if (!widget.readOnly)
                PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
            ],
          ),
        ),
      ),
    );
  }
}
