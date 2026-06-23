import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/reports_repository.dart';
import 'report_chat_screen.dart';

final myReportsProvider =
    FutureProvider.autoDispose<List<ReportSummary>>((ref) async {
  return ref.read(reportsRepoProvider).myReports();
});

/// قائمة بلاغات العميل — يفتح منها محادثة كل بلاغ.
class ReportsListScreen extends ConsumerWidget {
  const ReportsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(myReportsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('بلاغاتي'), centerTitle: true),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل البلاغات',
            onRetry: () => ref.invalidate(myReportsProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyView(
              icon: Icons.inbox_outlined,
              title: 'لا توجد بلاغات',
              message: 'لو واجهت مشكلة مع متجر، أبلغنا وسنتابعها معك.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myReportsProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: list.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (_, i) => _ReportTile(report: list[i])
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

class _ReportTile extends StatelessWidget {
  final ReportSummary report;
  const _ReportTile({required this.report});

  (String, Color) get _status => switch (report.status) {
        'resolved' => ('محلول', AppColors.success),
        'reviewing' => ('قيد المراجعة', AppColors.info),
        _ => ('مفتوح', AppColors.warning),
      };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final (label, color) = _status;
    return AppCard(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => ReportChatScreen(
          reportId: report.id,
          merchantName: report.merchantName,
          subjectLabel: report.subjectLabel,
          status: report.status,
        ),
      )),
      child: Row(children: [
        const AppIconBadge(Icons.confirmation_num_outlined, size: 44),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(report.merchantName ?? 'بلاغ عام',
                  style: theme.textTheme.titleMedium,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis),
              const SizedBox(height: 2),
              Text(
                report.subjectLabel?.isNotEmpty == true
                    ? 'عن: ${report.subjectLabel}'
                    : 'آخر تحديث ${DateFormat('yyyy/MM/dd').format(report.lastMessageAt.toLocal())}',
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: AppColors.textSecondary),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
              color: color.withValues(alpha: .15),
              borderRadius: BorderRadius.circular(AppRadii.pill)),
          child: Text(label,
              style: TextStyle(
                  color: color, fontWeight: FontWeight.w800, fontSize: 12)),
        ),
      ]),
    );
  }
}
