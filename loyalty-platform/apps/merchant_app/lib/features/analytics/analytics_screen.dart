import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';

/// فترة التحليلات.
enum AnalyticsPeriod { day, week, month }

extension on AnalyticsPeriod {
  String get label => switch (this) {
        AnalyticsPeriod.day => 'اليوم',
        AnalyticsPeriod.week => 'الأسبوع',
        AnalyticsPeriod.month => 'الشهر',
      };

  /// بداية الفترة من الآن.
  DateTime get since {
    final now = DateTime.now();
    return switch (this) {
      AnalyticsPeriod.day => DateTime(now.year, now.month, now.day),
      AnalyticsPeriod.week => now.subtract(const Duration(days: 7)),
      AnalyticsPeriod.month => now.subtract(const Duration(days: 30)),
    };
  }
}

/// تجميع بيانات التحليلات. يعتمد على استعلامات مجمّعة بسيطة.
/// TODO: استبدالها بـ Materialized Views على Postgres لأداء أفضل.
class AnalyticsData {
  final int newCustomers;
  final int totalCustomers;
  final double returnRate; // 0..1
  final List<int> visitsPerDay; // طول = period.days
  final int pointsDistributed;
  final int pointsRedeemed;
  final List<MapEntry<String, int>> topRewards;

  const AnalyticsData({
    required this.newCustomers,
    required this.totalCustomers,
    required this.returnRate,
    required this.visitsPerDay,
    required this.pointsDistributed,
    required this.pointsRedeemed,
    required this.topRewards,
  });

  bool get isEmpty =>
      totalCustomers == 0 &&
      pointsDistributed == 0 &&
      visitsPerDay.every((v) => v == 0);
}

class AnalyticsFilter {
  final AnalyticsPeriod period;
  final String? branchId;
  const AnalyticsFilter({required this.period, this.branchId});
}

final analyticsFilterProvider = StateProvider<AnalyticsFilter>(
    (ref) => const AnalyticsFilter(period: AnalyticsPeriod.week));

final analyticsProvider =
    FutureProvider.autoDispose<AnalyticsData>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final filter = ref.watch(analyticsFilterProvider);
  final client = Supabase.instance.client;
  final merchantId = staff.merchantId;
  final sinceDateIsoOrNull = filter.period.since.toIso8601String();
  final branchOrNull = filter.branchId;

  // استدعاء واحد يحسب كل المقاييس على السيرفر (بدل عدّة استعلامات مجمّعة).
  final res = await client.rpc('analytics_summary', params: {
    'p_merchant': merchantId,
    'p_branch': branchOrNull,
    'p_since': sinceDateIsoOrNull,
  });
  final m = res as Map<String, dynamic>;

  final topRewards = ((m['top_rewards'] as List?) ?? [])
      .map((r) => MapEntry(
            (r['name'] as String?) ?? 'مكافأة',
            (r['redemptions'] as num?)?.toInt() ?? 0,
          ))
      .toList();

  // سلسلة الزيارات اليومية بالترتيب الزمني.
  final visitsPerDay = ((m['visits_series'] as List?) ?? [])
      .map((e) => (e['visits'] as num?)?.toInt() ?? 0)
      .toList();

  return AnalyticsData(
    newCustomers: (m['new_customers'] as num?)?.toInt() ?? 0,
    totalCustomers: (m['total_customers'] as num?)?.toInt() ?? 0,
    returnRate: (m['return_rate'] as num?)?.toDouble() ?? 0.0,
    visitsPerDay: visitsPerDay,
    pointsDistributed: (m['points_distributed'] as num?)?.toInt() ?? 0,
    pointsRedeemed: (m['points_redeemed'] as num?)?.toInt() ?? 0,
    topRewards: topRewards,
  );
});

/// فروع التاجر لفلتر التحليلات.
final _analyticsBranchesProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final rows = await Supabase.instance.client
      .from('branches')
      .select('id, name')
      .eq('merchant_id', staff.merchantId)
      .order('name');
  return List<Map<String, dynamic>>.from(rows);
});

/// 2.11 — التحليلات.
class AnalyticsScreen extends ConsumerWidget {
  const AnalyticsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filter = ref.watch(analyticsFilterProvider);
    final async = ref.watch(analyticsProvider);
    final branchesAsync = ref.watch(_analyticsBranchesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('التحليلات')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Row(
              children: [
                Expanded(
                  child: SegmentedButton<AnalyticsPeriod>(
                    segments: AnalyticsPeriod.values
                        .map((p) => ButtonSegment(
                            value: p, label: Text(p.label)))
                        .toList(),
                    selected: {filter.period},
                    onSelectionChanged: (s) => ref
                        .read(analyticsFilterProvider.notifier)
                        .state = AnalyticsFilter(
                            period: s.first, branchId: filter.branchId),
                  ),
                ),
              ],
            ),
          ),
          branchesAsync.maybeWhen(
            data: (branches) => branches.isEmpty
                ? const SizedBox.shrink()
                : SizedBox(
                    height: 44,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(left: 8),
                          child: ChoiceChip(
                            label: const Text('كل الفروع'),
                            selected: filter.branchId == null,
                            selectedColor: AppColors.primary,
                            onSelected: (_) => ref
                                .read(analyticsFilterProvider.notifier)
                                .state = AnalyticsFilter(
                                    period: filter.period, branchId: null),
                          ),
                        ),
                        ...branches.map((b) {
                          final id = b['id'] as String;
                          return Padding(
                            padding: const EdgeInsets.only(left: 8),
                            child: ChoiceChip(
                              label: Text(b['name'] as String? ?? '—'),
                              selected: filter.branchId == id,
                              selectedColor: AppColors.primary,
                              onSelected: (_) => ref
                                  .read(analyticsFilterProvider.notifier)
                                  .state = AnalyticsFilter(
                                      period: filter.period, branchId: id),
                            ),
                          );
                        }),
                      ],
                    ),
                  ),
            orElse: () => const SizedBox.shrink(),
          ),
          const SizedBox(height: 4),
          Expanded(
            child: async.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                message: 'تعذّر تحميل التحليلات',
                onRetry: () => ref.invalidate(analyticsProvider),
              ),
              data: (data) {
                if (data.isEmpty) {
                  return const EmptyView(
                    icon: Icons.insights_rounded,
                    title: 'لا توجد بيانات بعد',
                    message: 'ستظهر تحليلاتك هنا بمجرد بدء نشاط عملائك.',
                  );
                }
                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _StatRow(data: data),
                    const SizedBox(height: 12),
                    const SectionHeader(title: 'الزيارات على مدار الوقت'),
                    const SizedBox(height: 12),
                    AppCard(
                      child: SizedBox(
                        height: 200,
                        child: _VisitsChart(visits: data.visitsPerDay),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const SectionHeader(
                        title: 'النقاط: الموزّعة مقابل المُستبدلة'),
                    const SizedBox(height: 12),
                    _PointsCompare(
                      distributed: data.pointsDistributed,
                      redeemed: data.pointsRedeemed,
                    ),
                    const SizedBox(height: 12),
                    const SectionHeader(title: 'أكثر المكافآت استبدالًا'),
                    const SizedBox(height: 12),
                    if (data.topRewards.isEmpty)
                      const AppCard(child: Text('لا توجد استبدالات بعد.'))
                    else
                      ...data.topRewards.map((e) => Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: AppCard(
                              child: Row(
                                children: [
                                  const Icon(Icons.card_giftcard_rounded,
                                      color: AppColors.primaryDark, size: 20),
                                  const SizedBox(width: 10),
                                  Expanded(child: Text(e.key)),
                                  PointsBadge(
                                      points: e.value, suffix: 'مرة'),
                                ],
                              ),
                            ),
                          )),
                  ],
                ).animate().fadeIn(duration: 300.ms).slideY(begin: .04, end: 0);
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _StatRow extends StatelessWidget {
  final AnalyticsData data;
  const _StatRow({required this.data});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
            child: StatCard(
                label: 'عملاء جدد',
                value: '${data.newCustomers}',
                icon: Icons.person_add_alt_1_rounded,
                accent: AppColors.info)),
        const SizedBox(width: 12),
        Expanded(
            child: StatCard(
                label: 'إجمالي العملاء',
                value: '${data.totalCustomers}',
                icon: Icons.groups_rounded,
                accent: AppColors.primaryDark)),
        const SizedBox(width: 12),
        Expanded(
            child: StatCard(
                label: 'معدّل العودة',
                value: '${(data.returnRate * 100).round()}%',
                icon: Icons.repeat_rounded,
                accent: AppColors.success,
                highlight: true)),
      ],
    );
  }
}

class _VisitsChart extends StatelessWidget {
  final List<int> visits;
  const _VisitsChart({required this.visits});

  @override
  Widget build(BuildContext context) {
    final spots = [
      for (var i = 0; i < visits.length; i++)
        FlSpot(i.toDouble(), visits[i].toDouble()),
    ];
    final maxY = visits.isEmpty
        ? 1.0
        : (visits.reduce((a, b) => a > b ? a : b).toDouble() + 1);
    return LineChart(
      LineChartData(
        minY: 0,
        maxY: maxY,
        gridData: const FlGridData(show: true, drawVerticalLine: false),
        titlesData: const FlTitlesData(
          leftTitles:
              AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 28)),
          rightTitles:
              AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles:
              AxisTitles(sideTitles: SideTitles(showTitles: false)),
          bottomTitles:
              AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        lineBarsData: [
          LineChartBarData(
            spots: spots,
            isCurved: true,
            color: AppColors.primary,
            barWidth: 3,
            dotData: const FlDotData(show: false),
            belowBarData: BarAreaData(
              show: true,
              color: AppColors.primary.withValues(alpha: .15),
            ),
          ),
        ],
      ),
    );
  }
}

class _PointsCompare extends StatelessWidget {
  final int distributed;
  final int redeemed;
  const _PointsCompare({required this.distributed, required this.redeemed});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        children: [
          _PointsRow(
              label: 'النقاط الموزّعة',
              value: distributed,
              color: AppColors.success),
          const SizedBox(height: 12),
          _PointsRow(
              label: 'النقاط المُستبدلة',
              value: redeemed,
              color: AppColors.warning),
        ],
      ),
    );
  }
}

class _PointsRow extends StatelessWidget {
  final String label;
  final int value;
  final Color color;
  const _PointsRow(
      {required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 12,
          height: 12,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(label)),
        Text('$value',
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.w700)),
      ],
    );
  }
}
