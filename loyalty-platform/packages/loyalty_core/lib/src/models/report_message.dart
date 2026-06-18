/// رسالة في محادثة بلاغ (thread). تُرجع من report_thread RPC.
class ReportMessage {
  final String id;
  final String senderRole; // customer | merchant | admin
  final String senderName;
  final String? staffRole; // دور موظّف التاجر (merchant_owner/manager/cashier…)
  final String body;
  final String? attachmentUrl;
  final DateTime createdAt;
  final String? replyToId;
  final String? replyToName;
  final String? replyToBody;
  final bool isMine;

  const ReportMessage({
    required this.id,
    required this.senderRole,
    required this.senderName,
    required this.body,
    required this.createdAt,
    this.staffRole,
    this.attachmentUrl,
    this.replyToId,
    this.replyToName,
    this.replyToBody,
    this.isMine = false,
  });

  bool get hasReply => replyToId != null;
  bool get hasAttachment => (attachmentUrl ?? '').isNotEmpty;

  factory ReportMessage.fromJson(Map<String, dynamic> j) => ReportMessage(
        id: j['id'] as String,
        senderRole: j['sender_role'] as String? ?? 'customer',
        senderName: (j['sender_name'] as String?)?.trim().isNotEmpty == true
            ? j['sender_name'] as String
            : 'مستخدم',
        staffRole: j['staff_role'] as String?,
        body: j['body'] as String? ?? '',
        attachmentUrl: j['attachment_url'] as String?,
        createdAt: DateTime.parse(j['created_at'] as String),
        replyToId: j['reply_to_id'] as String?,
        replyToName: j['reply_to_name'] as String?,
        replyToBody: j['reply_to_body'] as String?,
        isMine: j['is_mine'] as bool? ?? false,
      );
}

/// ملخّص بلاغ في القائمة. يُرجع من my_reports / merchant_reports.
class ReportSummary {
  final String id;
  final String? merchantName;
  final String? subjectLabel;
  final String status; // open | reviewing | resolved
  final DateTime lastMessageAt;

  const ReportSummary({
    required this.id,
    required this.status,
    required this.lastMessageAt,
    this.merchantName,
    this.subjectLabel,
  });

  factory ReportSummary.fromJson(Map<String, dynamic> j) => ReportSummary(
        id: j['id'] as String,
        merchantName: j['merchant_name'] as String?,
        subjectLabel: j['subject_label'] as String?,
        status: j['status'] as String? ?? 'open',
        lastMessageAt: DateTime.parse(
            (j['last_message_at'] ?? j['created_at']) as String),
      );
}
