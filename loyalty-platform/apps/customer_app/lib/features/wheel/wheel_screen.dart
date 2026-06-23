import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/wheel_repository.dart';
import 'my_prizes_screen.dart';
import 'prize_qr_screen.dart';

/// عجلة حظ التاجر النشطة (إن وُجدت).
final activeWheelProvider =
    FutureProvider.autoDispose.family<LuckyWheel?, String>((ref, merchantId) async {
  return ref.read(wheelRepoProvider).activeWheel(merchantId);
});

/// شاشة عجلة الحظ — يلِفّ العميل بنقاطه ويربح هدايا/نقاط.
class WheelScreen extends ConsumerStatefulWidget {
  final String merchantId;
  final String? merchantName;
  const WheelScreen({super.key, required this.merchantId, this.merchantName});

  @override
  ConsumerState<WheelScreen> createState() => _WheelScreenState();
}

class _WheelScreenState extends ConsumerState<WheelScreen> {
  final LuckyWheelController _controller = LuckyWheelController();
  bool _spinning = false;

  Future<void> _spin(LuckyWheel wheel) async {
    if (_spinning) return;
    setState(() => _spinning = true);
    try {
      final data = await ref
          .read(wheelRepoProvider)
          .spinWheel(wheel.id, idempotencyKey: genIdempotencyKey());
      if (data == null) {
        if (mounted) {
          AppFeedback.toast(context, 'تعذّر اللفّ، حاول مرة أخرى.', error: true);
        }
        return;
      }
      if (data['error'] != null) {
        if (mounted) {
          AppFeedback.toast(context, data['error'].toString(), error: true);
        }
        return;
      }

      // نحدّد موقع المقطع الفائز على العجلة قبل كشف النتيجة.
      final segmentId = data['segment_id'] as String?;
      final index =
          wheel.segments.indexWhere((s) => s.id == segmentId);
      if (index >= 0) {
        await _controller.spinTo(index);
      }
      if (!mounted) return;

      final result = data['result'] as String?;
      final label = data['label'] as String? ?? 'هدية';
      switch (result) {
        case 'prize':
          final prizeJson = data['prize'] as Map<String, dynamic>?;
          await AppFeedback.success(
            context,
            title: 'مبروك! ربحت $label',
            message: 'احفظها في "هداياي" وفعّلها عند الكاشير',
            actionLabel: 'عرض الهدية',
          );
          if (!mounted) return;
          if (prizeJson != null) {
            final prize = UserPrize.fromJson({
              ...prizeJson,
              'user_id': ref.read(wheelRepoProvider).currentUserId!,
              'merchant_id': widget.merchantId,
              'merchant_name': widget.merchantName,
              'kind': prizeJson['kind'] ?? 'reward',
              'status': prizeJson['status'] ?? 'won',
              'source': prizeJson['source'] ?? 'wheel',
              'created_at':
                  prizeJson['created_at'] ?? DateTime.now().toIso8601String(),
            });
            await Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => PrizeQrScreen(prize: prize),
            ));
          } else {
            await Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => const MyPrizesScreen(),
            ));
          }
        case 'points':
          final points = data['points'] as int? ?? 0;
          await AppFeedback.success(
            context,
            title: 'ربحت $points نقطة',
            message: 'أُضيفت إلى رصيدك في ${widget.merchantName ?? 'المتجر'}',
          );
        default:
          AppFeedback.toast(context, 'حظ أوفر المرة القادمة');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر اللفّ، حاول مرة أخرى.', error: true);
      }
    } finally {
      if (mounted) setState(() => _spinning = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final wheelAsync = ref.watch(activeWheelProvider(widget.merchantId));
    return Scaffold(
      body: wheelAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل عجلة الحظ',
          onRetry: () =>
              ref.invalidate(activeWheelProvider(widget.merchantId)),
        ),
        data: (wheel) {
          if (wheel == null || wheel.segments.isEmpty) {
            return const Center(
              child: EmptyView(
                icon: Icons.casino_outlined,
                title: 'لا توجد عجلة حظ حاليًا',
                message: 'تابع المتجر — قد تُضاف عجلة حظ قريبًا.',
              ),
            );
          }
          return SafeArea(
            top: false,
            child: Column(
              children: [
                HeroHeader(
                  title: widget.merchantName ?? 'المتجر',
                  subtitle: 'عجلة الحظ',
                  trailing: TextButton.icon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const MyPrizesScreen(),
                      ),
                    ),
                    icon: const AppIcon(Icons.card_giftcard_outlined,
                        color: AppColors.onPrimary),
                    label: const Text('هداياي',
                        style: TextStyle(color: AppColors.onPrimary)),
                  ),
                ),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.all(24),
                    children: [
                      Center(
                        child: LuckyWheelView(
                          segments: wheel.segments,
                          controller: _controller,
                          size: context.cappedSize(300),
                        ),
                      )
                          .animate()
                          .fadeIn(duration: 400.ms)
                          .scale(
                              begin: const Offset(.95, .95),
                              end: const Offset(1, 1),
                              curve: Curves.easeOutBack),
                      const SizedBox(height: 24),
                      AppCard(
                        color: AppColors.primaryLight,
                        child: Row(
                          children: [
                            const AppIcon(Icons.star_rounded,
                                color: AppColors.primaryDark),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                'لِف بـ ${wheel.spinCostPoints} نقطة',
                                style:
                                    Theme.of(context).textTheme.titleMedium,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                      PrimaryButton(
                        label: _spinning ? 'جارٍ اللفّ…' : 'لِف الآن',
                        icon: Icons.casino_rounded,
                        loading: _spinning,
                        onPressed:
                            _spinning ? null : () => _spin(wheel),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
