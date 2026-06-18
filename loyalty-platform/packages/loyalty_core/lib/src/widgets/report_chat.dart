import 'dart:async';

import 'package:flutter/material.dart';

import '../models/report_message.dart';
import '../theme/app_colors.dart';
import 'app_icon.dart';

/// عرض محادثة بلاغ (شات) — مشترك بين تطبيقَي العميل والتاجر.
/// رسائلي على اليمين، والباقي على الشمال، مع وسم دور كل مُرسِل، رد على رسالة،
/// والانتقال للرسالة الأصلية عند الضغط على الاقتباس.
class ReportChatView extends StatefulWidget {
  final String title;
  final String? subtitle;
  final String? subjectLabel;
  final String status; // open | reviewing | resolved
  final List<ReportMessage> messages;
  final bool canReply;

  /// نص يظهر بدل صندوق الكتابة عند عدم القدرة على الرد (مثلاً عرض فقط).
  final String? disabledHint;
  final Future<void> Function(String body, String? replyToId) onSend;

  /// تعديل رسالتي (اضغط مطوّلًا عليها). null = التعديل غير متاح.
  final Future<void> Function(String messageId, String newBody)? onEdit;

  const ReportChatView({
    super.key,
    required this.title,
    required this.status,
    required this.messages,
    required this.onSend,
    this.subtitle,
    this.subjectLabel,
    this.canReply = true,
    this.disabledHint,
    this.onEdit,
  });

  @override
  State<ReportChatView> createState() => _ReportChatViewState();
}

class _ReportChatViewState extends State<ReportChatView> {
  final _scroll = ScrollController();
  final _input = TextEditingController();
  final Map<String, GlobalKey> _keys = {};
  ReportMessage? _replyTo;
  String? _highlightId;
  bool _sending = false;

  @override
  void dispose() {
    _scroll.dispose();
    _input.dispose();
    super.dispose();
  }

  GlobalKey _keyFor(String id) => _keys.putIfAbsent(id, () => GlobalKey());

  void _jumpTo(String messageId) {
    final ctx = _keys[messageId]?.currentContext;
    if (ctx == null) return;
    Scrollable.ensureVisible(ctx,
        duration: const Duration(milliseconds: 350),
        alignment: 0.3,
        curve: Curves.easeInOut);
    setState(() => _highlightId = messageId);
    Timer(const Duration(milliseconds: 1600), () {
      if (mounted) setState(() => _highlightId = null);
    });
  }

  Future<void> _editMessage(ReportMessage m) async {
    final ctrl = TextEditingController(text: m.body);
    final newBody = await showDialog<String>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تعديل الرسالة'),
        content: TextField(
          controller: ctrl,
          maxLines: 4,
          maxLength: 4000,
          autofocus: true,
          decoration: const InputDecoration(hintText: 'عدّل نص رسالتك…'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(context).pop(), child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.of(context).pop(ctrl.text.trim()),
              child: const Text('حفظ')),
        ],
      ),
    );
    if (newBody == null || newBody.isEmpty || newBody == m.body) return;
    try {
      await widget.onEdit!(m.id, newBody);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تعذّر تعديل الرسالة')));
      }
    }
  }

  Future<void> _send() async {
    final body = _input.text.trim();
    if (body.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      await widget.onSend(body, _replyTo?.id);
      if (!mounted) return;
      _input.clear();
      setState(() => _replyTo = null);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تعذّر إرسال الرسالة، حاول مجددًا')));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      _Header(title: widget.title, subtitle: widget.subtitle, status: widget.status),
      if ((widget.subjectLabel ?? '').isNotEmpty) _SubjectChip(label: widget.subjectLabel!),
      Expanded(
        child: ListView.builder(
          controller: _scroll,
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
          itemCount: widget.messages.length,
          itemBuilder: (_, i) {
            final m = widget.messages[i];
            return _Bubble(
              key: _keyFor(m.id),
              msg: m,
              highlighted: _highlightId == m.id,
              onReply: widget.canReply ? () => setState(() => _replyTo = m) : null,
              onEdit: (widget.onEdit != null && m.isMine) ? () => _editMessage(m) : null,
              onTapQuote: m.replyToId == null ? null : () => _jumpTo(m.replyToId!),
            );
          },
        ),
      ),
      _InputArea(
        controller: _input,
        canReply: widget.canReply,
        disabledHint: widget.disabledHint,
        replyTo: _replyTo,
        sending: _sending,
        onCancelReply: () => setState(() => _replyTo = null),
        onSend: _send,
      ),
    ]);
  }
}

// ===================== role styling =====================
class _RoleStyle {
  final String label;
  final Color color;
  const _RoleStyle(this.label, this.color);
}

_RoleStyle _roleStyle(String role) => switch (role) {
      'merchant' => const _RoleStyle('المتجر', Color(0xFF1AA47C)),
      'admin' => const _RoleStyle('إدارة المنصّة', AppColors.info),
      _ => const _RoleStyle('عميل', AppColors.primaryDark),
    };

String? _staffRoleLabel(String? r) => switch (r) {
      'merchant_owner' => 'المالك',
      'manager' => 'مدير',
      'branch_manager' => 'مدير فرع',
      'cashier' => 'كاشير',
      _ => null,
    };

// ===================== header =====================
class _Header extends StatelessWidget {
  final String title;
  final String? subtitle;
  final String status;
  const _Header({required this.title, this.subtitle, required this.status});

  (String, Color) get _statusStyle => switch (status) {
        'resolved' => ('محلول', AppColors.success),
        'reviewing' => ('قيد المراجعة', AppColors.info),
        _ => ('مفتوح', AppColors.warning),
      };

  @override
  Widget build(BuildContext context) {
    final (label, color) = _statusStyle;
    return Container(
      decoration: const BoxDecoration(gradient: AppColors.goldGradient),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(8, 6, 16, 14),
          child: Row(children: [
            IconButton(
              onPressed: () => Navigator.of(context).maybePop(),
              icon: const AppIcon(Icons.chevron_left_rounded, color: AppColors.onPrimary),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          color: AppColors.onPrimary,
                          fontWeight: FontWeight.w900,
                          fontSize: 16)),
                  if ((subtitle ?? '').isNotEmpty)
                    Text(subtitle!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: AppColors.onPrimary.withValues(alpha: .85),
                            fontSize: 12)),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .22),
                  borderRadius: BorderRadius.circular(20)),
              child: Text(label,
                  style: const TextStyle(
                      color: AppColors.onPrimary,
                      fontSize: 11,
                      fontWeight: FontWeight.w800)),
            ),
            const SizedBox(width: 4),
            _StatusDot(color: color),
          ]),
        ),
      ),
    );
  }
}

class _StatusDot extends StatelessWidget {
  final Color color;
  const _StatusDot({required this.color});
  @override
  Widget build(BuildContext context) =>
      Container(width: 8, height: 8, decoration: BoxDecoration(color: color, shape: BoxShape.circle));
}

class _SubjectChip extends StatelessWidget {
  final String label;
  const _SubjectChip({required this.label});
  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        color: AppColors.surfaceCream,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
        child: Row(children: [
          const AppIcon(Icons.confirmation_num_outlined,
              size: 17, color: AppColors.primaryDark),
          const SizedBox(width: 8),
          Expanded(
            child: Text('عن: $label',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
          ),
        ]),
      );
}

// ===================== bubble =====================
class _Bubble extends StatelessWidget {
  final ReportMessage msg;
  final bool highlighted;
  final VoidCallback? onReply;
  final VoidCallback? onEdit;
  final VoidCallback? onTapQuote;
  const _Bubble({
    super.key,
    required this.msg,
    required this.highlighted,
    this.onReply,
    this.onEdit,
    this.onTapQuote,
  });

  @override
  Widget build(BuildContext context) {
    final me = msg.isMine;
    final rs = _roleStyle(msg.senderRole);
    final staffLabel = _staffRoleLabel(msg.staffRole);
    final header = StringBuffer(msg.senderName)..write(' · ${rs.label}');
    if (staffLabel != null) header.write(' ($staffLabel)');

    final bubbleInner = Container(
      constraints: const BoxConstraints(maxWidth: 250),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        gradient: me ? AppColors.goldGradient : null,
        color: me
            ? null
            : (msg.senderRole == 'merchant'
                ? AppColors.primaryLight
                : (msg.senderRole == 'admin'
                    ? AppColors.info.withValues(alpha: .14)
                    : AppColors.surface)),
        borderRadius: BorderRadiusDirectional.only(
          topStart: const Radius.circular(18),
          topEnd: const Radius.circular(18),
          bottomStart: Radius.circular(me ? 6 : 18),
          bottomEnd: Radius.circular(me ? 18 : 6),
        ).resolve(Directionality.of(context)),
        border: highlighted ? Border.all(color: AppColors.warning, width: 2.5) : null,
        boxShadow: [
          if (highlighted)
            BoxShadow(color: AppColors.warning.withValues(alpha: .5), blurRadius: 16)
          else
            BoxShadow(color: Colors.black.withValues(alpha: .05), blurRadius: 6, offset: const Offset(0, 2)),
        ],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(header.toString(),
            style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w900,
                color: me ? AppColors.onPrimary.withValues(alpha: .92) : rs.color)),
        if (msg.hasReply) ...[
          const SizedBox(height: 6),
          GestureDetector(
            onTap: onTapQuote,
            child: Container(
              padding: const EdgeInsets.fromLTRB(10, 6, 10, 6),
              decoration: BoxDecoration(
                color: me ? Colors.white.withValues(alpha: .20) : AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(8),
                border: BorderDirectional(
                    start: BorderSide(color: me ? Colors.white : AppColors.primary, width: 3)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(msg.replyToName ?? '',
                      style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w800,
                          color: me ? AppColors.onPrimary : AppColors.primaryDark)),
                  Text(msg.replyToBody ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 11,
                          color: me
                              ? AppColors.onPrimary.withValues(alpha: .85)
                              : AppColors.textSecondary)),
                ],
              ),
            ),
          ),
        ],
        const SizedBox(height: 6),
        Text(msg.body,
            style: TextStyle(
                color: me ? AppColors.onPrimary : AppColors.textPrimary,
                height: 1.45,
                fontSize: 14.5)),
        if (msg.isEdited) _EditedNote(msg: msg, onDark: me),
        if (msg.hasAttachment) ...[const SizedBox(height: 8), _Attachment(url: msg.attachmentUrl!, onDark: me)],
        const SizedBox(height: 4),
        Row(mainAxisSize: MainAxisSize.min, children: [
          Text(_time(msg.createdAt),
              style: TextStyle(
                  fontSize: 10,
                  color: me ? AppColors.onPrimary.withValues(alpha: .8) : AppColors.textSecondary)),
          if (me) ...[
            const SizedBox(width: 4),
            Text('✓✓',
                style: TextStyle(fontSize: 11, color: AppColors.onPrimary.withValues(alpha: .9))),
          ],
        ]),
      ]),
    );

    // اضغط مطوّلًا على رسالتي لتعديلها.
    final bubble = onEdit == null
        ? bubbleInner
        : GestureDetector(onLongPress: onEdit, child: bubbleInner);

    final avatar = _Avatar(name: msg.senderName, color: rs.color);
    final replyBtn = onReply == null
        ? const SizedBox.shrink()
        : GestureDetector(
            onTap: onReply,
            child: Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.textSecondary.withValues(alpha: .18)),
              ),
              child: const _ReplyIcon(size: 15, color: AppColors.primaryDark),
            ),
          );

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Align(
        alignment: me ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: me
              ? [bubble, const SizedBox(width: 6), replyBtn]
              : [if (onReply != null) ...[replyBtn, const SizedBox(width: 6)], bubble, const SizedBox(width: 8), avatar],
        ),
      ),
    );
  }

  static String _time(DateTime t) {
    final l = t.toLocal();
    final h = l.hour.toString().padLeft(2, '0');
    final m = l.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}

/// مؤشّر «مُعدّلة» + النص الأصلي (شفافية للأطراف كلها).
class _EditedNote extends StatelessWidget {
  final ReportMessage msg;
  final bool onDark;
  const _EditedNote({required this.msg, required this.onDark});
  @override
  Widget build(BuildContext context) {
    final muted = onDark
        ? AppColors.onPrimary.withValues(alpha: .75)
        : AppColors.textSecondary;
    return Padding(
      padding: const EdgeInsets.only(top: 5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('✏️ مُعدّلة',
              style: TextStyle(
                  fontSize: 10.5, fontWeight: FontWeight.w700, color: muted)),
          if ((msg.originalBody ?? '').trim().isNotEmpty)
            Text('الأصل: ${msg.originalBody}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    fontSize: 11,
                    fontStyle: FontStyle.italic,
                    decoration: TextDecoration.lineThrough,
                    color: muted)),
        ],
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  final String name;
  final Color color;
  const _Avatar({required this.name, required this.color});
  @override
  Widget build(BuildContext context) => Container(
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          gradient: LinearGradient(
              colors: [color, Color.lerp(color, Colors.black, .18)!],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight),
          shape: BoxShape.circle,
        ),
        alignment: Alignment.center,
        child: Text(name.trim().isEmpty ? '؟' : name.trim().substring(0, 1),
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 15)),
      );
}

class _Attachment extends StatelessWidget {
  final String url;
  final bool onDark;
  const _Attachment({required this.url, required this.onDark});

  bool get _isImage {
    final u = url.toLowerCase();
    return u.endsWith('.jpg') || u.endsWith('.jpeg') || u.endsWith('.png') || u.endsWith('.webp');
  }

  @override
  Widget build(BuildContext context) {
    if (_isImage) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: Image.network(url,
            width: 180,
            height: 120,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => _chip(context, 'صورة مرفقة')),
      );
    }
    return _chip(context, 'فيديو/ملف مرفق');
  }

  Widget _chip(BuildContext context, String label) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
        decoration: BoxDecoration(
          color: onDark ? Colors.white.withValues(alpha: .20) : AppColors.surfaceCream,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          AppIcon(Icons.image_outlined,
              size: 16, color: onDark ? AppColors.onPrimary : AppColors.primaryDark),
          const SizedBox(width: 6),
          Text(label,
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: onDark ? AppColors.onPrimary : AppColors.textPrimary)),
        ]),
      );
}

// ===================== input =====================
class _InputArea extends StatelessWidget {
  final TextEditingController controller;
  final bool canReply;
  final String? disabledHint;
  final ReportMessage? replyTo;
  final bool sending;
  final VoidCallback onCancelReply;
  final VoidCallback onSend;
  const _InputArea({
    required this.controller,
    required this.canReply,
    required this.disabledHint,
    required this.replyTo,
    required this.sending,
    required this.onCancelReply,
    required this.onSend,
  });

  @override
  Widget build(BuildContext context) {
    if (!canReply) {
      return SafeArea(
        top: false,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: const BoxDecoration(
            color: AppColors.surface,
            border: Border(top: BorderSide(color: Color(0x22999999))),
          ),
          child: Text(disabledHint ?? 'عرض فقط',
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w700)),
        ),
      );
    }
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: Color(0x22999999))),
      ),
      child: SafeArea(
        top: false,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          if (replyTo != null)
            Container(
              margin: const EdgeInsets.fromLTRB(12, 10, 12, 0),
              padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
              decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(12),
                border: const BorderDirectional(start: BorderSide(color: AppColors.primary, width: 3)),
              ),
              child: Row(children: [
                const _ReplyIcon(size: 15, color: AppColors.primaryDark),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('ترد على ${replyTo!.senderName}',
                          style: const TextStyle(
                              fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primaryDark)),
                      Text(replyTo!.body,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                    ],
                  ),
                ),
                GestureDetector(
                  onTap: onCancelReply,
                  child: const AppIcon(Icons.cancel_outlined, size: 18, color: AppColors.textSecondary),
                ),
              ]),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
            child: Row(children: [
              Expanded(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 48, maxHeight: 120),
                  child: TextField(
                    controller: controller,
                    minLines: 1,
                    maxLines: 5,
                    textInputAction: TextInputAction.newline,
                    decoration: InputDecoration(
                      hintText: 'اكتب رسالة…',
                      filled: true,
                      fillColor: AppColors.surfaceCream,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                      border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(26), borderSide: BorderSide.none),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: sending ? null : onSend,
                child: Container(
                  width: 48,
                  height: 48,
                  decoration: const BoxDecoration(gradient: AppColors.goldGradient, shape: BoxShape.circle),
                  child: sending
                      ? const Padding(
                          padding: EdgeInsets.all(14),
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const _SendIcon(size: 14, color: Colors.white),
                ),
              ),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ===================== custom-drawn icons =====================
class _ReplyIcon extends StatelessWidget {
  final double size;
  final Color color;
  const _ReplyIcon({required this.size, required this.color});
  @override
  Widget build(BuildContext context) =>
      SizedBox(width: size, height: size, child: CustomPaint(painter: _ReplyPainter(color)));
}

class _ReplyPainter extends CustomPainter {
  final Color color;
  _ReplyPainter(this.color);
  @override
  void paint(Canvas canvas, Size s) {
    final p = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = s.width * 0.11
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final w = s.width, h = s.height;
    canvas.drawPath(
        Path()
          ..moveTo(w * 0.46, h * 0.24)
          ..lineTo(w * 0.16, h * 0.50)
          ..lineTo(w * 0.46, h * 0.76),
        p);
    canvas.drawPath(
        Path()
          ..moveTo(w * 0.16, h * 0.50)
          ..lineTo(w * 0.66, h * 0.50)
          ..lineTo(w * 0.66, h * 0.28),
        p);
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}

class _SendIcon extends StatelessWidget {
  final double size;
  final Color color;
  const _SendIcon({required this.size, required this.color});
  @override
  Widget build(BuildContext context) =>
      SizedBox(width: size, height: size, child: CustomPaint(painter: _SendPainter(color)));
}

class _SendPainter extends CustomPainter {
  final Color color;
  _SendPainter(this.color);
  @override
  void paint(Canvas canvas, Size s) {
    final w = s.width, h = s.height;
    final p = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = w * 0.13
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawLine(Offset(w * 0.50, h * 0.80), Offset(w * 0.50, h * 0.24), p);
    canvas.drawPath(
        Path()
          ..moveTo(w * 0.26, h * 0.46)
          ..lineTo(w * 0.50, h * 0.21)
          ..lineTo(w * 0.74, h * 0.46),
        p);
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}
