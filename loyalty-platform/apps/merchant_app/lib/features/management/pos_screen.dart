import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart' hide TextDirection;
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../core/perm_gate.dart';
import '../../data/repositories/pos_repository.dart';

final _posKeysProvider = FutureProvider.autoDispose<List<PosApiKey>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await ref.read(posRepoProvider).fetchKeys(staff.merchantId);
  return rows
      .map((r) => PosApiKey.fromJson(r as Map<String, dynamic>))
      .toList();
});

/// تكامل POS — يعرض رابط الـ API والمفاتيح وأمثلة الاستخدام.
class PosIntegrationScreen extends ConsumerWidget {
  const PosIntegrationScreen({super.key});

  static const _base =
      'https://<YOUR-PROJECT>.supabase.co/functions/v1/pos-api';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final keys = ref.watch(_posKeysProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('تكامل POS')),
      floatingActionButton:
          ref.permCan(PermResource.settings, PermAction.edit)
              ? FloatingActionButton.extended(
                  onPressed: () => _createKey(context, ref),
                  icon: const AppIcon(Icons.add_rounded),
                  label: const Text('مفتاح جديد'),
                )
              : null,
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          AppCard(
            gradient: AppColors.heroGradient,
            child: Row(children: [
              Container(
                height: 48,
                width: 48,
                decoration: BoxDecoration(
                    color: AppColors.surface.withValues(alpha: .35),
                    borderRadius: BorderRadius.circular(AppRadii.md)),
                child: const AppIcon(Icons.point_of_sale_rounded,
                    color: AppColors.onPrimary),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                    'اربط نظام الكاشير لديك وأضِف النقاط تلقائيًا من الفواتير.',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(color: AppColors.onPrimary)),
              ),
            ]),
          ),
          const SizedBox(height: 16),
          const SectionHeader(title: 'نقطة النهاية (Endpoint)'),
          const SizedBox(height: 8),
          const _CopyRow(label: 'POST', value: _base),
          const SizedBox(height: 16),
          const SectionHeader(title: 'مفاتيح API'),
          const SizedBox(height: 8),
          keys.when(
            loading: () => const Skeleton(height: 70, radius: AppRadii.xl),
            error: (e, _) => ErrorView(
                message: 'تعذّر تحميل المفاتيح',
                onRetry: () => ref.invalidate(_posKeysProvider)),
            data: (list) {
              if (list.isEmpty) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: Text('لا توجد مفاتيح بعد. أنشئ مفتاحًا للبدء.',
                      style: TextStyle(color: AppColors.textSecondary)),
                );
              }
              return Column(
                children: [
                  for (final k in list)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _KeyCard(k: k, ref: ref),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 16),
          const SectionHeader(title: 'مثال (إضافة نقاط من فاتورة)'),
          const SizedBox(height: 8),
          const _CodeBlock('''curl -X POST $_base \\
  -H "x-api-key: pos_live_xxx" \\
  -H "Content-Type: application/json" \\
  -d '{"action":"earn","phone":"+9665xxxxxxx","amount":120}' '''),
          const SizedBox(height: 12),
          Text('الأفعال المتاحة: lookup · earn · visit · redeem — راجع docs/POS_API.md',
              style: Theme.of(context).textTheme.bodySmall),
        ],
      ).animate().fadeIn(duration: 300.ms),
    );
  }

  Future<void> _createKey(BuildContext context, WidgetRef ref) async {
    final nameCtrl = TextEditingController(text: 'كاشير رئيسي');
    final name = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('مفتاح POS جديد'),
        content: TextField(
          controller: nameCtrl,
          decoration: const InputDecoration(labelText: 'اسم المفتاح'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx), child: const Text('إلغاء')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, nameCtrl.text.trim()),
              child: const Text('إنشاء')),
        ],
      ),
    );
    if (name == null || name.isEmpty) return;
    try {
      final res = await ref.read(posRepoProvider).createKey(name);
      final data = res.data as Map<String, dynamic>?;
      if (data?['key'] == null) {
        if (context.mounted) {
          AppFeedback.toast(context, data?['error'] as String? ?? 'فشل الإنشاء',
              error: true);
        }
        return;
      }
      ref.invalidate(_posKeysProvider);
      if (context.mounted) _showKeyOnce(context, data!['key'] as String);
    } catch (_) {
      if (context.mounted) AppFeedback.toast(context, 'تعذّر الإنشاء', error: true);
    }
  }

  void _showKeyOnce(BuildContext context, String key) {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('انسخ المفتاح الآن'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('لن يظهر المفتاح الكامل مرة أخرى. احفظه في مكان آمن.',
                style: TextStyle(color: AppColors.textSecondary)),
            const SizedBox(height: 12),
            SelectableText(key,
                style: const TextStyle(fontFamily: 'monospace', fontSize: 13)),
          ],
        ),
        actions: [
          FilledButton.icon(
            onPressed: () {
              Clipboard.setData(ClipboardData(text: key));
              Navigator.pop(ctx);
              AppFeedback.toast(context, 'تم نسخ المفتاح');
            },
            icon: const AppIcon(Icons.copy_rounded),
            label: const Text('نسخ وإغلاق'),
          ),
        ],
      ),
    );
  }
}

class _KeyCard extends StatelessWidget {
  final PosApiKey k;
  final WidgetRef ref;
  const _KeyCard({required this.k, required this.ref});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Row(children: [
        AppIcon(k.active ? Icons.vpn_key_rounded : Icons.key_off_rounded,
            color: k.active ? AppColors.primaryDark : AppColors.textSecondary),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(k.name, style: Theme.of(context).textTheme.titleMedium),
              Text('${k.keyPrefix}…  ·  ${k.active ? "نشط" : "ملغى"}',
                  style: Theme.of(context).textTheme.bodySmall),
              if (k.lastUsedAt != null)
                Text('آخر استخدام: ${DateFormat('yyyy/MM/dd').format(k.lastUsedAt!)}',
                    style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
        if (k.active && ref.permCan(PermResource.settings, PermAction.edit))
          IconButton(
            tooltip: 'إلغاء',
            icon: const AppIcon(Icons.block_rounded, color: AppColors.error),
            onPressed: () async {
              await ref.read(posRepoProvider).revokeKey(k.id);
              ref.invalidate(_posKeysProvider);
              if (context.mounted) AppFeedback.toast(context, 'تم إلغاء المفتاح');
            },
          ),
      ]),
    );
  }
}

class _CopyRow extends StatelessWidget {
  final String label;
  final String value;
  const _CopyRow({required this.label, required this.value});
  @override
  Widget build(BuildContext context) {
    return AppCard(
      onTap: () {
        Clipboard.setData(ClipboardData(text: value));
        AppFeedback.toast(context, 'تم النسخ');
      },
      child: Row(children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
              color: AppColors.primaryLight,
              borderRadius: BorderRadius.circular(8)),
          child: Text(label,
              style: const TextStyle(
                  fontWeight: FontWeight.w800, color: AppColors.onPrimary)),
        ),
        const SizedBox(width: 10),
        Expanded(
            child: Text(value,
                style: const TextStyle(fontSize: 12), overflow: TextOverflow.ellipsis)),
        const AppIcon(Icons.copy_rounded, size: 18, color: AppColors.textSecondary),
      ]),
    );
  }
}

class _CodeBlock extends StatelessWidget {
  final String code;
  const _CodeBlock(this.code);
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        Clipboard.setData(ClipboardData(text: code));
        AppFeedback.toast(context, 'تم نسخ المثال');
      },
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.darkBg,
          borderRadius: BorderRadius.circular(AppRadii.md),
        ),
        child: Text(code,
            textDirection: TextDirection.ltr,
            style: const TextStyle(
                color: Colors.white, fontFamily: 'monospace', fontSize: 12)),
      ),
    );
  }
}
