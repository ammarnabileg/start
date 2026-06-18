import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/reports_repository.dart';

final reportThreadProvider = FutureProvider.autoDispose
    .family<List<ReportMessage>, String>((ref, reportId) async {
  return ref.read(reportsRepoProvider).thread(reportId);
});

/// محادثة بلاغ (شات) من جهة العميل.
class ReportChatScreen extends ConsumerWidget {
  final String reportId;
  final String? merchantName;
  final String? subjectLabel;
  final String status;
  const ReportChatScreen({
    super.key,
    required this.reportId,
    this.merchantName,
    this.subjectLabel,
    this.status = 'open',
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(reportThreadProvider(reportId));
    return Scaffold(
      body: async.when(
        loading: () => const SafeArea(child: LoadingView()),
        error: (e, _) => SafeArea(
          child: ErrorView(
              message: 'تعذّر تحميل المحادثة',
              onRetry: () => ref.invalidate(reportThreadProvider(reportId))),
        ),
        data: (messages) => ReportChatView(
          title: merchantName ?? 'بلاغ',
          subtitle: 'محادثة البلاغ',
          subjectLabel: subjectLabel,
          status: status,
          messages: messages,
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
