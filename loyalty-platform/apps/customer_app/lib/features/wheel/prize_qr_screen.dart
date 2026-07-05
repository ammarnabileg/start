import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/report_storage.dart';
import '../../data/repositories/wheel_repository.dart';

/// شاشة QR متغيّر لتفعيل هدية عند الكاشير.
/// الـ QR يتجدّد كل ثانية (نافذة TOTP-style) ويُلغى عند تحوّل الحالة لـ redeemed.
class PrizeQrScreen extends ConsumerStatefulWidget {
  final UserPrize prize;
  const PrizeQrScreen({super.key, required this.prize});

  @override
  ConsumerState<PrizeQrScreen> createState() => _PrizeQrScreenState();
}

class _PrizeQrScreenState extends ConsumerState<PrizeQrScreen> {
  Timer? _timer;
  String _payload = '';
  int _remaining = QrToken.defaultWindowSeconds;
  bool _redeemed = false;
  bool _dialogOpen = false;
  StreamSubscription<List<Map<String, dynamic>>>? _statusSub;

  bool get _expired {
    final exp = widget.prize.expiresAt;
    return exp != null && exp.isBefore(DateTime.now());
  }

  @override
  void initState() {
    super.initState();
    _redeemed = widget.prize.isRedeemed;
    _regenerate();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
    _subscribeStatus();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _statusSub?.cancel();
    super.dispose();
  }

  void _subscribeStatus() {
    _statusSub = ref
        .read(wheelRepoProvider)
        .prizeStatusStream(widget.prize.id)
        .listen((rows) {
      if (rows.isEmpty || !mounted) return;
      final status = rows.first['status'] as String?;
      if (status == 'redeemed') {
        if (_dialogOpen && Navigator.canPop(context)) Navigator.pop(context);
        setState(() => _redeemed = true);
      } else if (status == 'delivering') {
        // الكاشير بدأ التسليم → اطلب تأكيد العميل.
        if (!_dialogOpen) _showDeliveringSheet();
      } else if (_dialogOpen && Navigator.canPop(context)) {
        // عاد للحالة المتاحة (إلغاء من الكاشير) → أغلق النافذة.
        Navigator.pop(context);
      }
    });
  }

  /// نافذة تأكيد الاستلام: موافق / إلغاء / إبلاغ.
  Future<void> _showDeliveringSheet() async {
    _dialogOpen = true;
    final action = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      isDismissible: false,
      enableDrag: false,
      builder: (ctx) => _DeliverSheet(prize: widget.prize),
    );
    _dialogOpen = false;
    if (!mounted) return;
    if (action == 'confirm') {
      await _confirm(true);
    } else if (action == 'cancel') {
      await _confirm(false);
    } else if (action == 'report') {
      await _openReport();
    }
  }

  Future<void> _confirm(bool confirm) async {
    try {
      await ref
          .read(wheelRepoProvider)
          .confirmPrize(widget.prize.id, confirm: confirm);
      if (mounted && !confirm) {
        AppFeedback.toast(context, 'تم إلغاء العملية');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر تنفيذ العملية', error: true);
      }
    }
  }

  /// الإبلاغ: يفتح كاميرا الفيديو مباشرة للتوثيق، ثم نموذج الإرسال.
  Future<void> _openReport() async {
    final videoUrl = await ReportStorage.recordAndUpload();
    if (!mounted) return;
    await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _ReportSheet(prize: widget.prize, videoUrl: videoUrl),
    );
  }

  void _regenerate() {
    _payload = QrToken.generate(
      widget.prize.id,
      widget.prize.claimSecret,
      version: 'p1',
    );
  }

  void _tick() {
    if (!mounted) return;
    setState(() {
      _regenerate();
      _remaining = QrToken.secondsRemaining();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(widget.prize.title), centerTitle: true),
      body: SafeArea(
        child: _redeemed
            ? _RedeemedState(prize: widget.prize)
            : _expired
                ? const Center(
                    child: EmptyView(
                      icon: Icons.timer_off_outlined,
                      title: 'انتهت صلاحية الهدية',
                      message: 'لم تعُد هذه الهدية قابلة للتفعيل.',
                    ),
                  )
                : LayoutBuilder(
                    builder: (context, constraints) => SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: ConstrainedBox(
                        constraints:
                            BoxConstraints(minHeight: constraints.maxHeight - 48),
                        child: Column(
                      children: [
                        const Spacer(),
                        AppCard(
                          padding: const EdgeInsets.all(28),
                          child: Column(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius:
                                      BorderRadius.circular(AppRadii.md),
                                  boxShadow: const [
                                    BoxShadow(
                                        color: AppColors.shadowSoft,
                                        blurRadius: 12,
                                        offset: Offset(0, 4)),
                                  ],
                                ),
                                child: QrImageView(
                                  data: _payload,
                                  version: QrVersions.auto,
                                  size: context.cappedSize(220),
                                  backgroundColor: Colors.white,
                                ),
                              ),
                              const SizedBox(height: 20),
                              Text(widget.prize.title,
                                  style: theme.textTheme.titleMedium,
                                  textAlign: TextAlign.center),
                              const SizedBox(height: 8),
                              Text('اطلب من الكاشير تفعيل الهدية',
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                      color: AppColors.textSecondary),
                                  textAlign: TextAlign.center),
                              const SizedBox(height: 16),
                              _CountdownRing(remaining: _remaining),
                            ],
                          ),
                        )
                            .animate()
                            .fadeIn(duration: 400.ms)
                            .scale(
                                begin: const Offset(.96, .96),
                                end: const Offset(1, 1),
                                curve: Curves.easeOutBack),
                        const Spacer(),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 10),
                          decoration: BoxDecoration(
                            color: AppColors.surfaceCream,
                            borderRadius: BorderRadius.circular(AppRadii.pill),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const AppIcon(Icons.lock_outline_rounded,
                                  size: 16, color: AppColors.textSecondary),
                              const SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                  'يتجدّد الرمز تلقائيًا لحماية هديتك',
                                  textAlign: TextAlign.center,
                                  style: theme.textTheme.bodySmall,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                        ),
                      ),
                    ),
                  ),
      ),
    );
  }
}

class _RedeemedState extends StatelessWidget {
  final UserPrize prize;
  const _RedeemedState({required this.prize});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 96,
              width: 96,
              decoration: const BoxDecoration(
                  color: AppColors.successBg, shape: BoxShape.circle),
              child: const AppIcon(Icons.check_rounded,
                  size: 52, color: AppColors.success),
            ).animate().scale(
                duration: 420.ms, curve: Curves.easeOutBack).fadeIn(),
            const SizedBox(height: 20),
            Text('تم استلام الهدية ✓',
                style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(prize.title,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'تمام',
              expanded: false,
              onPressed: () => Navigator.of(context).pop(),
            ),
          ],
        ),
      ),
    );
  }
}

/// نافذة "يتم تسليمك الهدية" — موافق / إلغاء / إبلاغ.
class _DeliverSheet extends StatelessWidget {
  final UserPrize prize;
  const _DeliverSheet({required this.prize});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 44,
              height: 5,
              margin: const EdgeInsets.only(bottom: 18),
              decoration: BoxDecoration(
                  color: AppColors.divider,
                  borderRadius: BorderRadius.circular(3)),
            ),
            Container(
              height: 84,
              width: 84,
              decoration: const BoxDecoration(
                  gradient: AppColors.goldGradient, shape: BoxShape.circle),
              child: const AppIcon(Icons.card_giftcard_rounded,
                  size: 44, color: Colors.white),
            ).animate().scale(
                duration: 380.ms, curve: Curves.easeOutBack),
            const SizedBox(height: 16),
            Text('يتم تسليمك', style: theme.textTheme.bodyMedium),
            const SizedBox(height: 4),
            Text(prize.title,
                style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
            if (prize.description != null &&
                prize.description!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(prize.description!,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: AppColors.textSecondary)),
            ],
            const SizedBox(height: 22),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(context, 'cancel'),
                    style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text('إلغاء'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: () => Navigator.pop(context, 'confirm'),
                    style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text('موافق'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: () => Navigator.pop(context, 'report'),
              icon: const AppIcon(Icons.warning_amber_rounded,
                  size: 18, color: AppColors.error),
              label: const Text('إبلاغ عن مشكلة',
                  style: TextStyle(color: AppColors.error)),
            ),
          ],
        ),
      ),
    );
  }
}

/// نموذج الإبلاغ — فيديو توثيق (مرفق تلقائيًا) + المتجر/الفرع + رسالة + إرسال.
class _ReportSheet extends ConsumerStatefulWidget {
  final UserPrize prize;
  final String? videoUrl;
  const _ReportSheet({required this.prize, this.videoUrl});
  @override
  ConsumerState<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends ConsumerState<_ReportSheet> {
  final _message = TextEditingController();
  late String? _videoUrl = widget.videoUrl;
  bool _busy = false;

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _recordAgain() async {
    final url = await ReportStorage.recordAndUpload();
    if (mounted && url != null) setState(() => _videoUrl = url);
  }

  Future<void> _send() async {
    if (_videoUrl == null && _message.text.trim().isEmpty) {
      AppFeedback.toast(context, 'أضف رسالة أو فيديو', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      await ref.read(wheelRepoProvider).submitReport(
            merchantId: widget.prize.merchantId,
            branchId: widget.prize.branchScope,
            prizeId: widget.prize.id,
            message: _message.text.trim(),
            videoUrl: _videoUrl,
          );
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم إرسال بلاغك، شكرًا لك');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر إرسال البلاغ', error: true);
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('إبلاغ عن مشكلة',
              style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          // المتجر/الفرع يُختار تلقائيًا.
          AppCard(
            child: Row(children: [
              const AppIcon(Icons.storefront_rounded,
                  color: AppColors.primaryDark),
              const SizedBox(width: 10),
              Expanded(
                child: Text(widget.prize.merchantName ?? 'المتجر',
                    style: const TextStyle(fontWeight: FontWeight.w700)),
              ),
              const Text('تلقائي',
                  style: TextStyle(
                      color: AppColors.textSecondary, fontSize: 12)),
            ]),
          ),
          const SizedBox(height: 12),
          // الفيديو
          AppCard(
            onTap: _recordAgain,
            child: Row(children: [
              AppIconBadge(
                  _videoUrl == null ? Icons.camera_alt_outlined : Icons.check_rounded,
                  size: 44,
                  color: _videoUrl == null ? null : AppColors.success),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                    _videoUrl == null
                        ? 'تسجيل فيديو توثيق'
                        : 'تم إرفاق الفيديو — اضغط لإعادة التسجيل',
                    style: const TextStyle(fontWeight: FontWeight.w600)),
              ),
            ]),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _message,
            maxLines: 3,
            decoration: const InputDecoration(
                labelText: 'رسالتك (اختياري)',
                alignLabelWithHint: true),
          ),
          const SizedBox(height: 16),
          PrimaryButton(
              label: 'إرسال',
              icon: Icons.send_rounded,
              loading: _busy,
              onPressed: _send),
        ],
      ),
    );
  }
}

class _CountdownRing extends StatelessWidget {
  final int remaining;
  const _CountdownRing({required this.remaining});

  @override
  Widget build(BuildContext context) {
    final progress = remaining / QrToken.defaultWindowSeconds;
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        SizedBox(
          height: 22,
          width: 22,
          child: CircularProgressIndicator(
            value: progress,
            strokeWidth: 3,
            backgroundColor: AppColors.surfaceCream,
            color: AppColors.primary,
          ),
        ),
        const SizedBox(width: 8),
        Text('يتجدّد خلال $remaining ث',
            style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}
