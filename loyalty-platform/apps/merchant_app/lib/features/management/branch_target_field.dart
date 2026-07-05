import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/branches_repository.dart';

/// يحمل اختيار الفروع لعنصر. [selected] فارغة = كل الفروع (موحّد).
class BranchTargetController {
  final Set<String> selected;
  BranchTargetController([Iterable<String>? initial])
      : selected = {...?initial};
  bool get allBranches => selected.isEmpty;
  List<String> get branchIds => selected.toList();
}

final _activeBranchesProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(branchesRepoProvider).fetchActiveBranchOptions(staff.merchantId);
});

/// حقل اختيار إتاحة العنصر: «كل الفروع» أو فروع محدّدة (متعدّد).
/// تحكّم سهل: زر للكل، ورقائق لكل فرع تُفعَّل/تُلغى بنقرة.
class BranchTargetField extends ConsumerStatefulWidget {
  final BranchTargetController controller;
  final String label;
  const BranchTargetField({
    super.key,
    required this.controller,
    this.label = 'الإتاحة في الفروع',
  });

  @override
  ConsumerState<BranchTargetField> createState() => _BranchTargetFieldState();
}

class _BranchTargetFieldState extends ConsumerState<BranchTargetField> {
  late bool _all = widget.controller.allBranches;

  @override
  Widget build(BuildContext context) {
    final branches = ref.watch(_activeBranchesProvider);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Row(
            children: [
              const AppIcon(Icons.store_mall_directory_outlined,
                  size: 20, color: AppColors.primaryDark),
              const SizedBox(width: 6),
              Text(widget.label,
                  style: const TextStyle(fontWeight: FontWeight.w700)),
            ],
          ),
          subtitle: Text(_all
              ? 'متاح في كل فروعك'
              : 'متاح في ${widget.controller.selected.length} فرع/فروع مختارة'),
          value: _all,
          onChanged: (v) async {
            if (v) {
              // رجوع للوضع الموحّد.
              setState(() {
                _all = true;
                widget.controller.selected.clear();
              });
            } else {
              // إلغاء التوحيد → بوب أب يوجّهه لاستقطاب الفروع.
              final ok = await _confirmCustomize();
              if (ok == true && mounted) setState(() => _all = false);
            }
          },
        ),
        if (!_all) ...[
          const SizedBox(height: 6),
          const SectionHeader(title: 'استقطب الفروع'),
          const SizedBox(height: 10),
          branches.when(
            loading: () => const Padding(
                padding: EdgeInsets.all(8), child: LinearProgressIndicator()),
            error: (e, _) => const Text('تعذّر تحميل الفروع',
                style: TextStyle(color: AppColors.error)),
            data: (list) {
              if (list.isEmpty) {
                return const Text('لا توجد فروع — أضف فرعًا أولًا.',
                    style: TextStyle(color: AppColors.textSecondary));
              }
              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final b in list)
                    _BranchChip(
                      label: b['name'] as String? ?? 'فرع',
                      selected:
                          widget.controller.selected.contains(b['id'] as String),
                      onTap: () => setState(() {
                        final id = b['id'] as String;
                        if (!widget.controller.selected.remove(id)) {
                          widget.controller.selected.add(id);
                        }
                      }),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 4),
          const Text(
              'استقطب نفس العرض لأي فرع، أو ابنِ عنصرًا خاصًا بفرع من الصفر.',
              style: TextStyle(color: AppColors.textSecondary, fontSize: 12)),
        ],
      ],
    );
  }

  /// بوب أب عند إلغاء التوحيد — يوضّح إن العنصر هيتخصّص للفروع المختارة.
  Future<bool?> _confirmCustomize() => showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('تخصيص لفروع معيّنة'),
          content: const Text(
              'العنصر مش هيكون موحّدًا على كل الفروع. اختار الفروع اللي هيتاح '
              'فيها — تقدر تستقطب نفس العرض لأكتر من فرع، وترجّعه موحّدًا في أي وقت.'),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('إلغاء')),
            FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('اختيار الفروع')),
          ],
        ),
      );
}

class _BranchChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _BranchChip(
      {required this.label, required this.selected, required this.onTap});
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryLight : AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.pill),
          border: Border.all(
              color: selected ? AppColors.primary : AppColors.divider,
              width: 1.5),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppIcon(
                selected ? Icons.check_circle_rounded : Icons.add,
                size: 16,
                color: selected ? AppColors.primaryDark : AppColors.textSecondary),
            const SizedBox(width: 6),
            Text(label,
                style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: selected
                        ? AppColors.primaryDark
                        : AppColors.textPrimary)),
          ],
        ),
      ),
    );
  }
}
