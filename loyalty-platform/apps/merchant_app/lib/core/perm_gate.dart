import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import 'merchant_providers.dart';

/// أدوات تقييد الواجهة حسب صلاحيات الدور — متطابقة مع فرض RLS في الخادم.
extension PermGateRef on WidgetRef {
  /// هل يملك الموظف الحالي صلاحية معيّنة؟ (يُرجِع false حتى تُحمّل الصلاحيات.)
  bool permCan(String resource, String action) {
    final p = watch(permissionsProvider).valueOrNull;
    return p?.can(resource, action) ?? false;
  }
}

/// زر «إضافة» عائم يظهر فقط لمن يملك صلاحية الإنشاء على المورد.
class PermFab extends ConsumerWidget {
  final String resource;
  final String label;
  final VoidCallback onPressed;
  const PermFab({
    super.key,
    required this.resource,
    required this.label,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!ref.permCan(resource, PermAction.create)) {
      return const SizedBox.shrink();
    }
    return FloatingActionButton.extended(
      onPressed: onPressed,
      icon: const AppIcon(Icons.add),
      label: Text(label),
    );
  }
}

/// لافتة «وضع العرض فقط» تُعرض أعلى المحرّر لمن لا يملك صلاحية التعديل.
class ReadOnlyNotice extends StatelessWidget {
  const ReadOnlyNotice({super.key});
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceCream,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          const AppIcon(Icons.lock_outline, color: AppColors.primaryDark),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'صلاحيتك «عرض فقط» — لا يمكنك حفظ التعديلات.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        ],
      ),
    );
  }
}
