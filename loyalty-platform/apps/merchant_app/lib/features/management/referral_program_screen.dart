import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/referral_repository.dart';

final _programProvider =
    FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(referralRepoProvider).program(staff.merchantId);
});

class _Milestone {
  int count;
  int points;
  String label;
  _Milestone({this.count = 1, this.points = 0, this.label = ''});
}

/// إعداد برنامج الإحالة: تفعيل + مسار مراحل تراكمي + مكافأة ترحيب.
class ReferralProgramScreen extends ConsumerStatefulWidget {
  const ReferralProgramScreen({super.key});
  @override
  ConsumerState<ReferralProgramScreen> createState() =>
      _ReferralProgramScreenState();
}

class _ReferralProgramScreenState extends ConsumerState<ReferralProgramScreen> {
  bool _loaded = false;
  bool _enabled = false;
  bool _saving = false;
  int _refereePoints = 0;
  final List<_Milestone> _milestones = [];

  void _hydrate(Map<String, dynamic>? p) {
    if (_loaded) return;
    _loaded = true;
    _enabled = p?['enabled'] == true;
    _refereePoints = (p?['referee_reward_points'] as num?)?.toInt() ?? 0;
    final ms = (p?['milestones'] as List?) ?? const [];
    for (final m in ms) {
      _milestones.add(_Milestone(
        count: (m['count'] as num?)?.toInt() ?? 1,
        points: (m['reward_points'] as num?)?.toInt() ?? 0,
        label: (m['label'] as String?) ?? '',
      ));
    }
    if (_milestones.isEmpty) _milestones.add(_Milestone(count: 3, points: 50));
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final ms = _milestones
          .where((m) => m.count > 0)
          .map((m) => {
                'count': m.count,
                'reward_points': m.points,
                'label': m.label.trim(),
              })
          .toList()
        ..sort((a, b) => (a['count'] as int).compareTo(b['count'] as int));
      await ref.read(referralRepoProvider).setProgram(
            staff.merchantId,
            enabled: _enabled,
            milestones: ms,
            refereePoints: _refereePoints,
          );
      if (mounted) {
        AppFeedback.success(context, title: 'تم الحفظ', message: 'تم تحديث برنامج الإحالة');
      }
    } catch (_) {
      if (mounted) AppFeedback.toast(context, 'تعذّر الحفظ', error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_programProvider);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('برنامج الإحالة')),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
            message: 'تعذّر التحميل',
            onRetry: () => ref.invalidate(_programProvider)),
        data: (p) {
          _hydrate(p);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              AppCard(
                child: Row(children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('تفعيل برنامج الإحالة',
                            style: theme.textTheme.titleMedium),
                        const SizedBox(height: 2),
                        Text(
                            'العميل يدعو أصدقاءه — تُحتسب الإحالة عند أول زيارة لصديقه، فتمنحه مكافآت المراحل.',
                            style: theme.textTheme.bodySmall),
                      ],
                    ),
                  ),
                  Switch(
                      value: _enabled,
                      onChanged: (v) => setState(() => _enabled = v)),
                ]),
              ),
              const SizedBox(height: 16),
              const SectionHeader(title: 'مراحل المكافآت (تراكمية)'),
              const SizedBox(height: 8),
              for (var i = 0; i < _milestones.length; i++)
                _MilestoneEditor(
                  index: i,
                  m: _milestones[i],
                  onChanged: () => setState(() {}),
                  onDelete: _milestones.length > 1
                      ? () => setState(() => _milestones.removeAt(i))
                      : null,
                ),
              const SizedBox(height: 4),
              OutlinedButton.icon(
                onPressed: () =>
                    setState(() => _milestones.add(_Milestone(count: 1))),
                icon: const AppIcon(Icons.add_rounded),
                label: const Text('أضف مرحلة'),
              ),
              const SizedBox(height: 20),
              const SectionHeader(title: 'مكافأة ترحيب للصاحب الجديد (اختياري)'),
              const SizedBox(height: 8),
              AppCard(
                child: Row(children: [
                  const Expanded(child: Text('نقاط ترحيب لكل صاحب جديد')),
                  SizedBox(
                    width: 90,
                    child: TextFormField(
                      initialValue: _refereePoints.toString(),
                      keyboardType: TextInputType.number,
                      textAlign: TextAlign.center,
                      decoration: const InputDecoration(isDense: true),
                      onChanged: (v) => _refereePoints = int.tryParse(v) ?? 0,
                    ),
                  ),
                ]),
              ),
              const SizedBox(height: 24),
              PrimaryButton(
                  label: 'حفظ', loading: _saving, onPressed: _save),
            ],
          );
        },
      ),
    );
  }
}

class _MilestoneEditor extends StatelessWidget {
  final int index;
  final _Milestone m;
  final VoidCallback onChanged;
  final VoidCallback? onDelete;
  const _MilestoneEditor(
      {required this.index,
      required this.m,
      required this.onChanged,
      this.onDelete});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      margin: const EdgeInsets.only(bottom: 10),
      child: Column(children: [
        Row(children: [
          CircleAvatar(
              radius: 14,
              backgroundColor: AppColors.primaryLight,
              child: Text('${index + 1}',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13))),
          const SizedBox(width: 10),
          Expanded(
            child: TextFormField(
              initialValue: m.count.toString(),
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'عدد الإحالات'),
              onChanged: (v) {
                m.count = int.tryParse(v) ?? 0;
                onChanged();
              },
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: TextFormField(
              initialValue: m.points.toString(),
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'نقاط'),
              onChanged: (v) => m.points = int.tryParse(v) ?? 0,
            ),
          ),
          if (onDelete != null)
            IconButton(
              onPressed: onDelete,
              icon: const AppIcon(Icons.delete_outline_rounded,
                  color: AppColors.error),
            ),
        ]),
        const SizedBox(height: 8),
        TextFormField(
          initialValue: m.label,
          decoration:
              const InputDecoration(labelText: 'وصف الهدية (اختياري)'),
          onChanged: (v) => m.label = v,
        ),
      ]),
    );
  }
}
