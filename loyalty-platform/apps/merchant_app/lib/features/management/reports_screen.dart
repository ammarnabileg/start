import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/reports_repository.dart';

final reportsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(reportsRepoProvider).fetchReports(staff.merchantId);
});

/// بلاغات العملاء — عرض فقط (لا تعديل). يظهر بيانات الراسل: الاسم الأول + الموبايل + الإيميل.
class ReportsScreen extends ConsumerWidget {
  const ReportsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(reportsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('البلاغات')),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل البلاغات',
            onRetry: () => ref.invalidate(reportsProvider)),
        data: (rows) {
          if (rows.isEmpty) {
            return const EmptyView(
              icon: Icons.inbox_outlined,
              title: 'لا توجد بلاغات',
              message: 'بلاغات عملائك ستظهر هنا.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(reportsProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: rows.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (_, i) => _ReportCard(report: rows[i])
                  .animate()
                  .fadeIn(duration: 300.ms, delay: (40 * i).ms)
                  .slideY(begin: .06, end: 0),
            ),
          );
        },
      ),
    );
  }
}

class _ReportCard extends StatelessWidget {
  final Map<String, dynamic> report;
  const _ReportCard({required this.report});

  String get _firstName {
    final full = (report['sender_name'] as String?)?.trim() ?? '';
    return full.isEmpty ? 'عميل' : full.split(RegExp(r'\s+')).first;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final phone = report['sender_phone'] as String?;
    final email = report['sender_email'] as String?;
    final message = (report['message'] as String?)?.trim();
    final videoUrl = report['video_url'] as String?;
    final branch = report['branch_name'] as String?;
    final prize = report['prize_title'] as String?;
    final created = DateTime.tryParse((report['created_at'] ?? '').toString());

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // بيانات الراسل (للعرض فقط): الاسم الأول + الموبايل + الإيميل.
          Row(
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: AppColors.primaryLight,
                child: Text(_firstName.characters.first,
                    style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_firstName, style: theme.textTheme.titleMedium),
                    const SizedBox(height: 2),
                    if (phone != null && phone.isNotEmpty)
                      _Info(Icons.phone_outlined, phone),
                    if (email != null && email.isNotEmpty)
                      _Info(Icons.email_outlined, email),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  _StatusChip(report['status'] as String? ?? 'open'),
                  if (created != null) ...[
                    const SizedBox(height: 4),
                    Text('${created.year}/${created.month}/${created.day}',
                        style: theme.textTheme.bodySmall),
                  ],
                ],
              ),
            ],
          ),
          if (branch != null || prize != null) ...[
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 6, children: [
              if (branch != null) _Tag(Icons.store_mall_directory_outlined, branch),
              if (prize != null) _Tag(Icons.card_giftcard_outlined, prize),
            ]),
          ],
          if (message != null && message.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              child: Text(message),
            ),
          ],
          if (videoUrl != null && videoUrl.isNotEmpty) ...[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: () {
                Clipboard.setData(ClipboardData(text: videoUrl));
                AppFeedback.toast(context, 'تم نسخ رابط الفيديو');
              },
              icon: const AppIcon(Icons.videocam_outlined),
              label: const Text('فيديو مرفق · نسخ الرابط'),
            ),
          ],
        ],
      ),
    );
  }
}

/// حالة البلاغ (للعرض فقط — التعديل من بانل الأدمن).
class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip(this.status);

  (String, Color) get _style => switch (status) {
        'resolved' => ('تم الحل', AppColors.success),
        'reviewing' => ('قيد المراجعة', AppColors.info),
        _ => ('مفتوح', AppColors.warning),
      };

  @override
  Widget build(BuildContext context) {
    final (label, color) = _style;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .15),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(label,
          style: TextStyle(
              color: color, fontWeight: FontWeight.w800, fontSize: 12)),
    );
  }
}

class _Info extends StatelessWidget {
  final IconData icon;
  final String text;
  const _Info(this.icon, this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Row(children: [
          AppIcon(icon, size: 14, color: AppColors.textSecondary),
          const SizedBox(width: 6),
          Flexible(
            child: Text(text,
                style: const TextStyle(
                    color: AppColors.textSecondary, fontSize: 13),
                maxLines: 1,
                overflow: TextOverflow.ellipsis),
          ),
        ]),
      );
}

class _Tag extends StatelessWidget {
  final IconData icon;
  final String text;
  const _Tag(this.icon, this.text);
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: AppColors.surfaceCream,
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          AppIcon(icon, size: 14, color: AppColors.primaryDark),
          const SizedBox(width: 5),
          Text(text,
              style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryDark)),
        ]),
      );
}
