import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../data/repositories/reviews_repository.dart';

/// ملخّص تقييم المتجر (متوسط + عدد).
final merchantRatingProvider =
    FutureProvider.autoDispose<RatingSummary>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(reviewsRepoProvider).ratingSummary(staff.merchantId);
});

/// كل مراجعات المتجر (مع المخفية).
final merchantReviewsProvider =
    FutureProvider.autoDispose<List<Review>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(reviewsRepoProvider).fetchReviews(staff.merchantId);
});

/// شاشة التقييمات للتاجر — يعرض متوسط متجره ومراجعات عملائه ويردّ عليها.
class ReviewsScreen extends ConsumerWidget {
  const ReviewsScreen({super.key});

  void _refresh(WidgetRef ref) {
    ref.invalidate(merchantRatingProvider);
    ref.invalidate(merchantReviewsProvider);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summary = ref.watch(merchantRatingProvider).valueOrNull;
    final async = ref.watch(merchantReviewsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('التقييمات')),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل التقييمات',
            onRetry: () => _refresh(ref)),
        data: (rows) {
          if (rows.isEmpty) {
            return RefreshIndicator(
              onRefresh: () async => _refresh(ref),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (summary != null) _SummaryCard(summary: summary),
                  const SizedBox(height: 16),
                  const SizedBox(
                    height: 260,
                    child: EmptyView(
                      icon: Icons.format_quote_rounded,
                      title: 'لا توجد تقييمات بعد',
                      message: 'تقييمات عملائك ومراجعاتهم ستظهر هنا.',
                    ),
                  ),
                ],
              ),
            );
          }
          // ListView.builder = بناء كسول (يتعامل مع آلاف المراجعات بكفاءة).
          return RefreshIndicator(
            onRefresh: () async => _refresh(ref),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: rows.length + 1,
              itemBuilder: (_, idx) {
                if (idx == 0) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: summary != null
                        ? _SummaryCard(summary: summary)
                        : const SizedBox.shrink(),
                  );
                }
                final i = idx - 1;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _ReviewCard(review: rows[i])
                      .animate()
                      .fadeIn(duration: 300.ms, delay: (40 * i).ms)
                      .slideY(begin: .06, end: 0),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _Stars extends StatelessWidget {
  final double value;
  final double size;
  const _Stars({required this.value, this.size = 16});
  @override
  Widget build(BuildContext context) {
    final filled = value.round().clamp(0, 5);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 1; i <= 5; i++)
          AppIcon(Icons.star_rounded,
              size: size,
              color: i <= filled
                  ? AppColors.goldTier
                  : AppColors.textSecondary.withValues(alpha: .3)),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final RatingSummary summary;
  const _SummaryCard({required this.summary});
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AppCard(
      child: Row(
        children: [
          Column(
            children: [
              Text(summary.hasRatings ? summary.average.toStringAsFixed(1) : '—',
                  style: theme.textTheme.displaySmall
                          ?.copyWith(fontWeight: FontWeight.w900) ??
                      const TextStyle(fontSize: 34, fontWeight: FontWeight.w900)),
              _Stars(value: summary.average, size: 16),
            ],
          ),
          const SizedBox(width: 18),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('تقييم متجرك', style: theme.textTheme.titleMedium),
                const SizedBox(height: 4),
                Text(
                  summary.hasRatings
                      ? 'بناءً على ${summary.count} مراجعة'
                      : 'لا توجد مراجعات بعد',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ReviewCard extends ConsumerWidget {
  final Review review;
  const _ReviewCard({required this.review});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: AppColors.primaryLight,
                child: Text(review.reviewerName.initialOrQuestion,
                    style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(review.reviewerName,
                              style: theme.textTheme.titleMedium,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis),
                        ),
                        if (review.isHidden) ...[
                          const SizedBox(width: 8),
                          const _HiddenBadge(),
                        ],
                      ],
                    ),
                    const SizedBox(height: 2),
                    _Stars(value: review.rating.toDouble(), size: 14),
                  ],
                ),
              ),
              Text(
                '${review.createdAt.toLocal().year}/${review.createdAt.toLocal().month}/${review.createdAt.toLocal().day}',
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
          if ((review.comment ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              child: Text(review.comment!),
            ),
          ],
          if (review.hasReply) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.primaryLight,
                borderRadius: BorderRadius.circular(AppRadii.md),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('ردّك',
                      style: theme.textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: AppColors.primaryDark)),
                  const SizedBox(height: 4),
                  Text(review.merchantReply!),
                ],
              ),
            ),
          ],
          const SizedBox(height: 6),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              onPressed: () => _openReplySheet(context, ref, review),
              icon: AppIcon(
                  review.hasReply
                      ? Icons.edit_outlined
                      : Icons.send_rounded,
                  size: 18),
              label: Text(review.hasReply ? 'تعديل الردّ' : 'الردّ'),
            ),
          ),
        ],
      ),
    );
  }
}

class _HiddenBadge extends StatelessWidget {
  const _HiddenBadge();
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: AppColors.textSecondary.withValues(alpha: .15),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: const Text('مخفية بالإشراف',
            style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                color: AppColors.textSecondary)),
      );
}

Future<void> _openReplySheet(
    BuildContext context, WidgetRef ref, Review review) async {
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (_) => _ReplySheet(review: review),
  );
}

class _ReplySheet extends ConsumerStatefulWidget {
  final Review review;
  const _ReplySheet({required this.review});
  @override
  ConsumerState<_ReplySheet> createState() => _ReplySheetState();
}

class _ReplySheetState extends ConsumerState<_ReplySheet> {
  late final TextEditingController _ctrl =
      TextEditingController(text: widget.review.merchantReply ?? '');
  bool _saving = false;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _saving = true);
    try {
      await ref.read(reviewsRepoProvider).reply(widget.review.id, _ctrl.text.trim());
      ref.invalidate(merchantReviewsProvider);
      if (mounted) {
        Navigator.of(context).pop();
        AppFeedback.toast(context, 'تم حفظ ردّك');
      }
    } catch (_) {
      if (mounted) {
        setState(() => _saving = false);
        AppFeedback.toast(context, 'تعذّر حفظ الردّ، حاول مجددًا', error: true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20, 20 + bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.textSecondary.withValues(alpha: .3),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('الردّ على ${widget.review.reviewerName}',
              style: theme.textTheme.titleLarge),
          const SizedBox(height: 12),
          TextField(
            controller: _ctrl,
            maxLines: 4,
            maxLength: 500,
            autofocus: true,
            decoration: const InputDecoration(
              hintText: 'اكتب ردًّا لطيفًا لعميلك…',
            ),
          ),
          const SizedBox(height: 8),
          PrimaryButton(
            label: 'حفظ الردّ',
            loading: _saving,
            onPressed: _submit,
          ),
        ],
      ),
    );
  }
}
