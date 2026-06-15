import 'dart:async';

import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../subscription/plans_screen.dart';

/// 2.4 — قيد المراجعة. يراقب حالة صف التاجر (realtime + زرار تحديث يدوي).
/// عند الموافقة (status == 'approved') → اختيار الباقة.
class PendingApprovalScreen extends StatefulWidget {
  final String merchantId;
  const PendingApprovalScreen({super.key, required this.merchantId});

  @override
  State<PendingApprovalScreen> createState() => _PendingApprovalScreenState();
}

class _PendingApprovalScreenState extends State<PendingApprovalScreen> {
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
    _sub = Supabase.instance.client
        .from('merchants')
        .stream(primaryKey: ['id'])
        .eq('id', widget.merchantId)
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
      final row = await Supabase.instance.client
          .from('merchants')
          .select('status')
          .eq('id', widget.merchantId)
          .maybeSingle();
      final status = row?['status'] as String? ?? 'pending';
      if (!mounted) return;
      if (status == 'approved') {
        _goToPlans();
      } else if (status == 'rejected') {
        setState(() => _status = status);
      } else {
        setState(() => _status = status);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('طلبك ما زال قيد المراجعة')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تعذّر تحديث الحالة، تحقق من الاتصال')),
        );
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
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircleAvatar(
                radius: 56,
                backgroundColor: rejected
                    ? AppColors.error.withValues(alpha: 0.12)
                    : AppColors.surfaceCream,
                child: Icon(
                  rejected ? Icons.cancel_outlined : Icons.access_time_rounded,
                  size: 56,
                  color: rejected ? AppColors.error : AppColors.primaryDark,
                ),
              ),
              const SizedBox(height: 28),
              Text(
                rejected ? 'تم رفض الطلب' : 'طلبك قيد المراجعة',
                style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 14),
              Text(
                rejected
                    ? 'عذرًا، لم تتم الموافقة على طلبك. تواصل معنا لمزيد من التفاصيل.'
                    : 'سنخطرك فور الموافقة على حسابك. عادةً خلال 24-48 ساعة.',
                style:
                    text.bodyLarge?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 36),
              if (!rejected)
                PrimaryButton(
                  label: 'تحديث الحالة',
                  icon: Icons.refresh,
                  loading: _checking,
                  onPressed: _checking ? null : _refreshStatus,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
