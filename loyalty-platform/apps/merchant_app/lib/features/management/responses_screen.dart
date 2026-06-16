import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/questions_repository.dart';

/// بيانات السؤال + خياراته (لعرض التوزيع بالأسماء).
final _questionDetailProvider = FutureProvider.autoDispose
    .family<MerchantQuestion, String>((ref, questionId) async {
  final row = await ref.read(questionsRepoProvider).fetchQuestion(questionId);
  return MerchantQuestion.fromJson(row);
});

/// إجابات السؤال من question_responses (مع فلتر فرع اختياري).
final _responsesProvider = FutureProvider.autoDispose
    .family<List<Map<String, dynamic>>, String>((ref, questionId) async {
  return ref.read(questionsRepoProvider).fetchResponses(questionId);
});

/// 2.10.ز — عرض إجابات سؤال.
class QuestionResponsesScreen extends ConsumerWidget {
  final String questionId;
  const QuestionResponsesScreen({super.key, required this.questionId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final questionAsync = ref.watch(_questionDetailProvider(questionId));
    final responsesAsync = ref.watch(_responsesProvider(questionId));

    return Scaffold(
      appBar: AppBar(title: const Text('الإجابات')),
      body: questionAsync.when(
        loading: () => const SkeletonList(count: 4),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل السؤال',
          onRetry: () => ref.invalidate(_questionDetailProvider(questionId)),
        ),
        data: (question) => responsesAsync.when(
          loading: () => const SkeletonList(count: 4),
          error: (e, _) => ErrorView(
            message: 'تعذّر تحميل الإجابات',
            onRetry: () => ref.invalidate(_responsesProvider(questionId)),
          ),
          data: (responses) {
            if (responses.isEmpty) {
              return const EmptyView(
                icon: Icons.bar_chart_rounded,
                title: 'لا توجد إجابات بعد',
                message: 'ستظهر إجابات عملائك هنا فور مشاركتهم.',
              );
            }
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                AppCard(
                  gradient: AppColors.goldGradient,
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(question.title,
                                style: Theme.of(context)
                                    .textTheme
                                    .titleLarge
                                    ?.copyWith(color: AppColors.onPrimary)),
                            const SizedBox(height: 4),
                            Text('${responses.length} إجابة',
                                style: TextStyle(
                                    color: AppColors.onPrimary
                                        .withValues(alpha: .85))),
                          ],
                        ),
                      ),
                      const Icon(Icons.insights_rounded,
                          size: 32, color: AppColors.onPrimary),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                SectionHeader(
                    title: question.type.isChoice
                        ? 'توزيع الإجابات'
                        : 'الإجابات النصية'),
                const SizedBox(height: 8),
                if (question.type.isChoice)
                  _ChoiceDistribution(
                      question: question, responses: responses)
                else
                  _TextAnswers(responses: responses),
              ],
            ).animate().fadeIn(duration: 300.ms).slideY(begin: .04, end: 0);
          },
        ),
      ),
    );
  }
}

/// توزيع إجابات الأسئلة الاختيارية كنِسَب وأشرطة.
class _ChoiceDistribution extends StatelessWidget {
  final MerchantQuestion question;
  final List<Map<String, dynamic>> responses;
  const _ChoiceDistribution(
      {required this.question, required this.responses});

  @override
  Widget build(BuildContext context) {
    final counts = <String, int>{
      for (final o in question.options) o.id: 0,
    };
    for (final r in responses) {
      final ids = r['selected_option_ids'];
      if (ids is List) {
        for (final id in ids) {
          if (counts.containsKey(id)) counts[id] = counts[id]! + 1;
        }
      }
    }
    final total = counts.values.fold<int>(0, (a, b) => a + b);
    const palette = [
      AppColors.primaryDark,
      AppColors.info,
      AppColors.success,
      AppColors.warning,
      AppColors.error,
      AppColors.bronze,
    ];

    final options = question.options;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < options.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: _DistributionBar(
              label: options[i].label,
              count: counts[options[i].id] ?? 0,
              total: total,
              color: palette[i % palette.length],
            ),
          ),
      ],
    );
  }
}

class _DistributionBar extends StatelessWidget {
  final String label;
  final int count;
  final int total;
  final Color color;
  const _DistributionBar(
      {required this.label,
      required this.count,
      required this.total,
      required this.color});

  @override
  Widget build(BuildContext context) {
    final fraction = total == 0 ? 0.0 : count / total;
    final pct = (fraction * 100).round();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
                child: Text(label,
                    style: Theme.of(context).textTheme.bodyMedium)),
            Text('$count ($pct%)',
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(fontWeight: FontWeight.w700, color: color)),
          ],
        ),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: fraction),
            duration: AppDurations.slow,
            curve: Curves.easeOutCubic,
            builder: (_, v, __) => LinearProgressIndicator(
              value: v,
              minHeight: 12,
              backgroundColor: AppColors.surfaceCream,
              valueColor: AlwaysStoppedAnimation<Color>(color),
            ),
          ),
        ),
      ],
    );
  }
}

/// قائمة الإجابات النصية.
class _TextAnswers extends StatelessWidget {
  final List<Map<String, dynamic>> responses;
  const _TextAnswers({required this.responses});

  @override
  Widget build(BuildContext context) {
    final texts = responses
        .map((r) => r['answer_text'] as String?)
        .where((t) => t != null && t.trim().isNotEmpty)
        .toList();
    if (texts.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 24),
        child: Center(child: Text('لا توجد إجابات نصية.')),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final t in texts)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: AppCard(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.format_quote_rounded,
                      color: AppColors.primaryDark, size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(t!,
                        style: Theme.of(context).textTheme.bodyMedium),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
