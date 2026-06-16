import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../data/repositories/rewards_repository.dart';

/// شاشة الاستلام (Show to Cashier) — راجع CUSTOMER_APP.md 1.14.
/// تعرض QR لعملية الاستبدال + عداد صلاحية + حالة لحظية (confirmed/expired).
class ShowToCashierScreen extends ConsumerStatefulWidget {
  /// نتيجة دالة redeem-reward:
  /// {redemption_id, reward_name, points_cost, expires_at}
  final Map<String, dynamic> redemption;
  const ShowToCashierScreen({super.key, required this.redemption});

  @override
  ConsumerState<ShowToCashierScreen> createState() =>
      _ShowToCashierScreenState();
}

enum _RedemptionStatus { pending, confirmed, expired }

class _ShowToCashierScreenState extends ConsumerState<ShowToCashierScreen> {
  late final String _redemptionId;
  late final String _rewardName;
  late final DateTime _expiresAt;

  _RedemptionStatus _status = _RedemptionStatus.pending;
  Timer? _ticker;
  Timer? _poller;
  StreamSubscription<List<Map<String, dynamic>>>? _sub;
  Duration _remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    _redemptionId = widget.redemption['redemption_id'] as String;
    _rewardName = widget.redemption['reward_name'] as String? ?? 'المكافأة';
    _expiresAt =
        DateTime.parse(widget.redemption['expires_at'] as String).toLocal();
    _tick();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
    _subscribe();
    // احتياط: polling كل 3 ثوانٍ في حال لم يصل الحدث اللحظي.
    _poller =
        Timer.periodic(const Duration(seconds: 3), (_) => _pollStatus());
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _poller?.cancel();
    _sub?.cancel();
    super.dispose();
  }

  void _tick() {
    final now = DateTime.now();
    final diff = _expiresAt.difference(now);
    if (diff.isNegative) {
      if (_status == _RedemptionStatus.pending) {
        setState(() {
          _remaining = Duration.zero;
          _status = _RedemptionStatus.expired;
        });
      }
      _ticker?.cancel();
      _poller?.cancel();
    } else {
      setState(() => _remaining = diff);
    }
  }

  void _subscribe() {
    _sub = ref
        .read(rewardsRepoProvider)
        .redemptionStream(_redemptionId)
        .listen((rows) {
      if (rows.isEmpty) return;
      _applyStatus(rows.first['status'] as String?);
    });
  }

  Future<void> _pollStatus() async {
    if (_status != _RedemptionStatus.pending) return;
    try {
      final row =
          await ref.read(rewardsRepoProvider).redemptionStatus(_redemptionId);
      _applyStatus(row?['status'] as String?);
    } catch (_) {
      // تجاهل أخطاء الـ polling المؤقتة.
    }
  }

  void _applyStatus(String? status) {
    if (!mounted) return;
    if (status == 'confirmed' && _status != _RedemptionStatus.confirmed) {
      setState(() => _status = _RedemptionStatus.confirmed);
      _ticker?.cancel();
      _poller?.cancel();
      AppFeedback.success(
        context,
        title: 'تم الاستلام بنجاح',
        message: 'استمتع بـ $_rewardName!',
      );
    } else if (status == 'expired' || status == 'cancelled') {
      if (_status == _RedemptionStatus.pending) {
        setState(() => _status = _RedemptionStatus.expired);
        _ticker?.cancel();
        _poller?.cancel();
      }
    }
  }

  String get _countdownText {
    final m = _remaining.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = _remaining.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('استلام المكافأة'), centerTitle: true),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: switch (_status) {
            _RedemptionStatus.confirmed => _ResultView(
                icon: Icons.check_rounded,
                color: AppColors.success,
                bg: AppColors.successBg,
                title: 'تم الاستلام بنجاح ✓',
                message: 'استمتع بـ $_rewardName!',
              ),
            _RedemptionStatus.expired => const _ResultView(
                icon: Icons.timer_off_outlined,
                color: AppColors.error,
                bg: AppColors.errorBg,
                title: 'انتهت الصلاحية',
                message: 'لم يتم تأكيد الاستلام في الوقت المحدد. لم تُخصم نقاطك.',
              ),
            _RedemptionStatus.pending => Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_rewardName, style: theme.textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Text('أرِ هذا الرمز للكاشير',
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(color: AppColors.textSecondary)),
                  const SizedBox(height: 24),
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(AppTheme.radius),
                      border: Border.all(
                          color: AppColors.primaryLight, width: 2),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.05),
                          blurRadius: 20,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: QrImageView(
                      // بادئة r1. عشان الـ Scanner يفرّق رمز استلام المكافأة.
                      data: 'r1.$_redemptionId',
                      size: context.cappedSize(220),
                      backgroundColor: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 24),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 18, vertical: 12),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceCream,
                      borderRadius: BorderRadius.circular(AppRadii.pill),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const AppIcon(Icons.timer_outlined,
                            size: 20, color: AppColors.primaryDark),
                        const SizedBox(width: 8),
                        Text('صالح لمدة $_countdownText',
                            style: theme.textTheme.titleLarge
                                ?.copyWith(color: AppColors.primaryDark)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text('اطلب من الكاشير تأكيد الاستلام.',
                      style: theme.textTheme.bodyMedium,
                      textAlign: TextAlign.center),
                ],
              ),
          },
        ),
      ),
    );
  }
}

class _ResultView extends StatelessWidget {
  final IconData icon;
  final Color color;
  final Color bg;
  final String title;
  final String message;
  const _ResultView({
    required this.icon,
    required this.color,
    required this.bg,
    required this.title,
    required this.message,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          height: 100,
          width: 100,
          decoration: BoxDecoration(color: bg, shape: BoxShape.circle),
          child: AppIcon(icon, size: 56, color: color),
        ),
        const SizedBox(height: 20),
        Text(title,
            style: theme.textTheme.headlineSmall, textAlign: TextAlign.center),
        const SizedBox(height: 10),
        Text(message,
            style: theme.textTheme.bodyMedium, textAlign: TextAlign.center),
        const SizedBox(height: 28),
        PrimaryButton(
          label: 'تم',
          expanded: false,
          onPressed: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}
