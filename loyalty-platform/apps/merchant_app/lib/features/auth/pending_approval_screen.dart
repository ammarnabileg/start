import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/merchant_repository.dart';
import '../subscription/plans_screen.dart';

/// 2.4 — قيد المراجعة. يراقب حالة صف التاجر (realtime + زرار تحديث يدوي).
/// عند الموافقة (status == 'approved') → اختيار الباقة.
class PendingApprovalScreen extends ConsumerStatefulWidget {
  final String merchantId;
  const PendingApprovalScreen({super.key, required this.merchantId});

  @override
  ConsumerState<PendingApprovalScreen> createState() =>
      _PendingApprovalScreenState();
}

class _PendingApprovalScreenState
    extends ConsumerState<PendingApprovalScreen> {
  bool _checking = false;
  String _status = 'pending';
  StreamSubscription<List<Map<String, dynamic>>>? _sub;

  @override
  void initState() {
    super.initState();
    _subscribeRealtime();
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  /// اشتراك realtime على صف التاجر — أول ما الأدمن يوافق تتحوّل الشاشة تلقائيًا.
  void _subscribeRealtime() {
    _sub = ref
        .read(merchantRepoProvider)
        .watchMerchant(widget.merchantId)
        .listen((rows) {
      if (rows.isEmpty) return;
      final status = rows.first['status'] as String? ?? 'pending';
      _applyStatus(status);
    });
  }

  void _applyStatus(String status) {
    if (!mounted) return;
    setState(() => _status = status);
    if (status == 'approved') {
      _goToPlans();
    }
  }

  void _goToPlans() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => PlansScreen(merchantId: widget.merchantId),
      ),
    );
  }

  Future<void> _refreshStatus() async {
    setState(() => _checking = true);
    try {
      final row = await ref
          .read(merchantRepoProvider)
          .fetchMerchantStatus(widget.merchantId);
      final status = row?['status'] as String? ?? 'pending';
      if (!mounted) return;
      if (status == 'approved') {
        _goToPlans();
      } else if (status == 'rejected') {
        setState(() => _status = status);
      } else {
        setState(() => _status = status);
        AppFeedback.toast(context, 'طلبك ما زال قيد المراجعة');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر تحديث الحالة، تحقق من الاتصال',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final rejected = _status == 'rejected';

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xxxl),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _WaitingMascot(rejected: rejected),
              const SizedBox(height: AppSpacing.xxl),
              Text(
                rejected ? 'تم رفض الطلب' : 'طلبك قيد المراجعة',
                style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                rejected
                    ? 'عذرًا، لم تتم الموافقة على طلبك. تواصل معنا لمزيد من التفاصيل.'
                    : 'سنخطرك فور الموافقة على حسابك. عادةً خلال 24-48 ساعة.',
                style:
                    text.bodyLarge?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.xxxl),
              if (!rejected)
                PrimaryButton(
                  label: 'تحديث الحالة',
                  icon: Icons.refresh,
                  loading: _checking,
                  onPressed: _checking ? null : _refreshStatus,
                ),
            ],
          ).animate().fadeIn(duration: 350.ms).slideY(begin: .05, end: 0),
        ),
      ),
    );
  }
}

/// دائرة الانتظار — تنبض بلطف أثناء المراجعة، وتتحوّل لحالة رفض ثابتة عند الرفض.
class _WaitingMascot extends StatelessWidget {
  final bool rejected;
  const _WaitingMascot({required this.rejected});

  @override
  Widget build(BuildContext context) {
    final circle = Container(
      width: 132,
      height: 132,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: rejected ? null : AppColors.goldGradient,
        color: rejected ? AppColors.errorBg : null,
        boxShadow: rejected
            ? null
            : const [
                BoxShadow(
                  color: AppColors.shadow,
                  blurRadius: 28,
                  offset: Offset(0, 12),
                ),
              ],
      ),
      child: AppIcon(
        rejected ? Icons.cancel_outlined : Icons.hourglass_top_rounded,
        size: 60,
        color: rejected ? AppColors.error : AppColors.onPrimary,
      ),
    );

    if (rejected) {
      return circle
          .animate()
          .scale(duration: 400.ms, curve: Curves.easeOutBack)
          .fadeIn();
    }

    // نبض لطيف مستمر يعطي إحساس "قيد المعالجة".
    return circle
        .animate(onPlay: (c) => c.repeat(reverse: true))
        .scaleXY(begin: 1, end: 1.06, duration: 1400.ms, curve: Curves.easeInOut);
  }
}
