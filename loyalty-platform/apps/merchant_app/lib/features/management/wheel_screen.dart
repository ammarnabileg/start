import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/wheel_repository.dart';

/// عجلة التاجر (مع مقاطعها) أو null لو لم تُنشأ بعد.
final wheelProvider = FutureProvider.autoDispose<LuckyWheel?>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final row = await ref.read(wheelRepoProvider).fetchWheel(staff.merchantId);
  if (row == null) return null;
  return LuckyWheel.fromJson(row);
});

/// لوحة ألوان بسيطة لاختيار لون المقطع.
const _palette = <String>[
  '#F5A800',
  '#FFD66B',
  '#2E7D32',
  '#1976D2',
  '#C62828',
  '#6A1B9A',
  '#00897B',
  '#EF6C00',
];

const _kindLabels = {
  SegmentKind.reward: 'مكافأة',
  SegmentKind.coupon: 'كوبون',
  SegmentKind.points: 'نقاط',
  SegmentKind.nothing: 'لا شيء',
};

/// (أ) إدارة عجلة الحظ.
class WheelManagementScreen extends ConsumerStatefulWidget {
  const WheelManagementScreen({super.key});

  @override
  ConsumerState<WheelManagementScreen> createState() =>
      _WheelManagementScreenState();
}

class _WheelManagementScreenState
    extends ConsumerState<WheelManagementScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _spinCost = TextEditingController(text: '50');
  final _maxSpins = TextEditingController(text: '0');
  bool _active = true;
  bool _busy = false;
  bool _loaded = false;
  String? _wheelId;
  final _previewController = LuckyWheelController();

  // مقاطع قيد التحرير + معرّفات المقاطع المحذوفة لمزامنتها مع السيرفر.
  List<_EditableSegment> _segments = [];
  final List<String> _removedIds = [];

  @override
  void dispose() {
    _name.dispose();
    _spinCost.dispose();
    _maxSpins.dispose();
    super.dispose();
  }

  void _hydrate(LuckyWheel? wheel) {
    if (_loaded) return;
    _loaded = true;
    if (wheel != null) {
      _wheelId = wheel.id;
      _name.text = wheel.name;
      _spinCost.text = wheel.spinCostPoints.toString();
      _maxSpins.text = wheel.maxSpinsPerDay.toString();
      _active = wheel.active;
      _segments = wheel.segments.map(_EditableSegment.fromModel).toList();
    } else {
      _name.text = 'عجلة الحظ';
      _segments = [
        _EditableSegment.create(label: 'جائزة', colorHex: _palette[0]),
        _EditableSegment.create(label: 'لا شيء', colorHex: _palette[1]),
      ];
    }
  }

  List<WheelSegment> _previewSegments() {
    final id = _wheelId ?? 'preview';
    return [
      for (var i = 0; i < _segments.length; i++)
        _segments[i].toModel(wheelId: id, sortOrder: i),
    ];
  }

  void _addSegment() {
    setState(() {
      _segments.add(_EditableSegment.create(
        label: 'مقطع جديد',
        colorHex: _palette[_segments.length % _palette.length],
      ));
    });
  }

  void _removeSegment(int index) {
    setState(() {
      final seg = _segments.removeAt(index);
      if (seg.serverId != null) _removedIds.add(seg.serverId!);
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (_segments.length < 2) {
      AppFeedback.toast(context, 'أضِف مقطعين على الأقل', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final repo = ref.read(wheelRepoProvider);

      final wheelPayload = {
        'merchant_id': staff.merchantId,
        'name': _name.text.trim(),
        'spin_cost_points': int.tryParse(_spinCost.text.trim()) ?? 0,
        'max_spins_per_day': int.tryParse(_maxSpins.text.trim()) ?? 0,
        'active': _active,
      };

      String wheelId;
      if (_wheelId == null) {
        wheelId = await repo.insertWheel(wheelPayload);
        _wheelId = wheelId;
      } else {
        wheelId = _wheelId!;
        await repo.updateWheel(wheelId, wheelPayload);
      }

      // حذف المقاطع المُزالة.
      if (_removedIds.isNotEmpty) {
        await repo.deleteSegments(_removedIds);
        _removedIds.clear();
      }

      // upsert للمقاطع الحالية.
      final rows = <Map<String, dynamic>>[];
      for (var i = 0; i < _segments.length; i++) {
        final json = _segments[i].toModel(wheelId: wheelId, sortOrder: i).toJson();
        if (_segments[i].serverId != null) {
          json['id'] = _segments[i].serverId;
        }
        rows.add(json);
      }
      await repo.upsertSegments(rows);

      if (mounted) {
        ref.invalidate(wheelProvider);
        await AppFeedback.success(context,
            title: 'تم حفظ العجلة',
            message: 'صار بإمكان عملائك تدوير العجلة بنقاطهم.');
        if (mounted) setState(() => _busy = false);
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
    final async = ref.watch(wheelProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('عجلة الحظ')),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل العجلة',
          onRetry: () => ref.invalidate(wheelProvider),
        ),
        data: (wheel) {
          _hydrate(wheel);
          return Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                Center(
                  child: LuckyWheelView(
                    segments: _previewSegments(),
                    controller: _previewController,
                    size: 240,
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                const SectionHeader(title: 'إعدادات العجلة'),
                const SizedBox(height: AppSpacing.sm),
                TextFormField(
                  controller: _name,
                  decoration: const InputDecoration(labelText: 'اسم العجلة'),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                ),
                const SizedBox(height: AppSpacing.md),
                TextFormField(
                  controller: _spinCost,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                      labelText: 'تكلفة الدورة (نقاط)'),
                  validator: (v) {
                    final n = int.tryParse(v?.trim() ?? '');
                    if (n == null || n < 0) return 'أدخل رقمًا صحيحًا';
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.md),
                TextFormField(
                  controller: _maxSpins,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'أقصى دورات يوميًا (0 = غير محدود)',
                  ),
                  validator: (v) {
                    final n = int.tryParse(v?.trim() ?? '');
                    if (n == null || n < 0) return 'أدخل رقمًا صحيحًا';
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.sm),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _active,
                  title: const Text('مفعّلة'),
                  subtitle: const Text('تظهر للعملاء في التطبيق'),
                  onChanged: (v) => setState(() => _active = v),
                ),
                const SizedBox(height: AppSpacing.md),
                SectionHeader(
                  title: 'المقاطع (${_segments.length})',
                  actionLabel: 'إضافة',
                  onAction: _addSegment,
                ),
                const SizedBox(height: AppSpacing.sm),
                for (var i = 0; i < _segments.length; i++)
                  _SegmentEditorCard(
                    key: ObjectKey(_segments[i]),
                    segment: _segments[i],
                    onChanged: () => setState(() {}),
                    onRemove: _segments.length > 2
                        ? () => _removeSegment(i)
                        : null,
                  ),
                const SizedBox(height: AppSpacing.lg),
                PrimaryButton(
                  label: 'حفظ العجلة',
                  loading: _busy,
                  onPressed: _save,
                ),
                const SizedBox(height: AppSpacing.lg),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// مقطع قابل للتحرير في الذاكرة.
class _EditableSegment {
  final String? serverId;
  final TextEditingController label;
  final TextEditingController pointsValue;
  final TextEditingController weight;
  final TextEditingController stock;
  SegmentKind kind;
  String colorHex;

  _EditableSegment({
    this.serverId,
    required this.label,
    required this.pointsValue,
    required this.weight,
    required this.stock,
    required this.kind,
    required this.colorHex,
  });

  factory _EditableSegment.create({
    required String label,
    required String colorHex,
  }) =>
      _EditableSegment(
        label: TextEditingController(text: label),
        pointsValue: TextEditingController(text: '0'),
        weight: TextEditingController(text: '1'),
        stock: TextEditingController(text: ''),
        kind: SegmentKind.nothing,
        colorHex: colorHex,
      );

  factory _EditableSegment.fromModel(WheelSegment s) => _EditableSegment(
        serverId: s.id,
        label: TextEditingController(text: s.label),
        pointsValue: TextEditingController(text: s.pointsValue.toString()),
        weight: TextEditingController(text: s.weight.toString()),
        stock: TextEditingController(text: s.stock?.toString() ?? ''),
        kind: s.kind,
        colorHex: s.colorHex ?? _palette[0],
      );

  WheelSegment toModel({required String wheelId, required int sortOrder}) {
    final stockText = stock.text.trim();
    return WheelSegment(
      id: serverId ?? '',
      wheelId: wheelId,
      label: label.text.trim().isEmpty ? '—' : label.text.trim(),
      kind: kind,
      pointsValue: int.tryParse(pointsValue.text.trim()) ?? 0,
      weight: int.tryParse(weight.text.trim()) ?? 1,
      colorHex: colorHex,
      stock: stockText.isEmpty ? null : int.tryParse(stockText),
      sortOrder: sortOrder,
    );
  }
}

class _SegmentEditorCard extends StatelessWidget {
  final _EditableSegment segment;
  final VoidCallback onChanged;
  final VoidCallback? onRemove;

  const _SegmentEditorCard({
    super.key,
    required this.segment,
    required this.onChanged,
    this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: _colorFromHex(segment.colorHex),
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.surface, width: 2),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextFormField(
                    controller: segment.label,
                    decoration:
                        const InputDecoration(labelText: 'العنوان'),
                    onChanged: (_) => onChanged(),
                  ),
                ),
                if (onRemove != null)
                  IconButton(
                    tooltip: 'حذف',
                    onPressed: onRemove,
                    icon: const AppIcon(Icons.delete_outline,
                        color: AppColors.error),
                  ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Wrap(
              spacing: 8,
              children: [
                for (final entry in _kindLabels.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: segment.kind == entry.key,
                    onSelected: (_) {
                      segment.kind = entry.key;
                      onChanged();
                    },
                  ),
              ],
            ),
            if (segment.kind == SegmentKind.points) ...[
              const SizedBox(height: AppSpacing.sm),
              TextFormField(
                controller: segment.pointsValue,
                keyboardType: TextInputType.number,
                decoration:
                    const InputDecoration(labelText: 'عدد النقاط'),
              ),
            ],
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: segment.weight,
                    keyboardType: TextInputType.number,
                    decoration:
                        const InputDecoration(labelText: 'الاحتمالية'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: segment.stock,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'المخزون',
                      hintText: 'غير محدود',
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Text('اللون', style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final hex in _palette)
                  GestureDetector(
                    onTap: () {
                      segment.colorHex = hex;
                      onChanged();
                    },
                    child: Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(
                        color: _colorFromHex(hex),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: segment.colorHex == hex
                              ? AppColors.primaryDark
                              : AppColors.surface,
                          width: 3,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Color _colorFromHex(String hex) {
    final h = hex.replaceAll('#', '');
    final v = int.tryParse(h.length == 6 ? 'FF$h' : h, radix: 16);
    return v == null ? AppColors.primary : Color(v);
  }
}
