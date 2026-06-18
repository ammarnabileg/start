import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/reports_repository.dart';

final reportThreadProvider = FutureProvider.autoDispose
    .family<List<ReportMessage>, String>((ref, reportId) async {
  return ref.read(reportsRepoProvider).thread(reportId);
});

/// محادثة بلاغ (شات) من جهة التاجر — الرد محميّ بصلاحية reports.reply.
class ReportChatScreen extends ConsumerWidget {
  final String reportId;
  final String customerName;
  final String? subjectLabel;
  final String status;
  const ReportChatScreen({
    super.key,
    required this.reportId,
    required this.customerName,
    this.subjectLabel,
    this.status = 'open',
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(reportThreadProvider(reportId));
    final perms = ref.watch(permissionsProvider).valueOrNull;
    final canReply = perms?.can('reports', 'reply') ?? false;
    return Scaffold(
      body: async.when(
        loading: () => const SafeArea(child: LoadingView()),
        error: (e, _) => SafeArea(
          child: ErrorView(
              message: 'تعذّر تحميل المحادثة',
              onRetry: () => ref.invalidate(reportThreadProvider(reportId))),
        ),
        data: (messages) => ReportChatView(
          title: customerName,
          subtitle: 'بلاغ عميل',
          subjectLabel: subjectLabel,
          status: status,
          messages: messages,
          canReply: canReply,
          disabledHint: 'ليس لديك صلاحية الرد على البلاغات',
          onSend: (body, replyTo) async {
            await ref
                .read(reportsRepoProvider)
                .postMessage(reportId, body, replyTo: replyTo);
            ref.invalidate(reportThreadProvider(reportId));
          },
        ),
      ),
    );
  }
}
