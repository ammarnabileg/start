import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';
import '../scanner/scanner_screen.dart';

/// 2.7 — لوحة التحكم ⭐. فلتر الفرع + بطاقات الأرقام + زر مسح كبير
/// + آخر النشاطات + بانر تنبيه التجربة.
class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  /// null = كل الفروع.
  String? _branchId;

  @override
  Widget build(BuildContext context) {
    final staffAsync = ref.watch(currentStaffProvider);

    return Scaffold(
      body: staffAsync.when(
        loading: () => const SkeletonList(count: 5),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل بيانات الحساب',
          onRetry: () => ref.invalidate(currentStaffProvider),
        ),
        data: (staff) {
          final dataAsync = ref.watch(
            _dashboardDataProvider(
              (merchantId: staff.merchantId, branchId: _branchId),
            ),
          );
          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(_dashboardDataProvider(
                (merchantId: staff.merchantId, branchId: _branchId),
              ));
              ref.invalidate(_branchesProvider(staff.merchantId));
            },
            child: dataAsync.when(
              loading: () => const SkeletonList(count: 5),
              error: (e, _) => ListView(
                children: [
                  const SizedBox(height: 120),
                  ErrorView(
                    message: 'تعذّر تحميل الإحصائيات',
                    onRetry: () => ref.invalidate(_dashboardDataProvider(
                      (merchantId: staff.merchantId, branchId: _branchId),
                    )),
                  ),
                ],
              ),
              data: (data) => _buildBody(context, staff, data),
            ),
          );
        },
      ),
    );
  }

  Widget _buildBody(
    BuildContext context,
    StaffContext staff,
    _DashboardData data,
  ) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        HeroHeader(
          title: 'لوحة التحكم',
          subtitle: 'نظرة سريعة على أداء متجرك اليوم',
          gradient: AppColors.darkGradient,
          trailing: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: .12),
              borderRadius: BorderRadius.circular(AppRadii.md),
            ),
            child: const Icon(Icons.storefront_rounded,
                color: AppColors.gold, size: 26),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg, AppSpacing.lg, AppSpacing.lg, AppSpacing.xxxl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (data.trialDaysLeft != null && data.trialDaysLeft! <= 5)
                _TrialBanner(daysLeft: data.trialDaysLeft!),
              _BranchFilter(
                merchantId: staff.merchantId,
                selected: _branchId,
                onChanged: (v) => setState(() => _branchId = v),
              ),
              const SizedBox(height: AppSpacing.lg),
              _StatsGrid(data: data),
              const SizedBox(height: AppSpacing.xl),
              // زرار كبير "مسح رمز العميل".
              PrimaryButton(
                label: 'مسح رمز العميل',
                icon: Icons.qr_code_scanner_rounded,
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                      builder: (_) => const ScannerScreen()),
                ),
              ),
              const SizedBox(height: AppSpacing.xxl),
              const SectionHeader(title: 'آخر النشاطات'),
              const SizedBox(height: AppSpacing.md),
              if (data.recentActivity.isEmpty)
                const EmptyView(
                  title: 'لا يوجد نشاط بعد',
                  message: 'ستظهر هنا آخر عمليات النقاط والاستبدال.',
                  icon: Icons.history_rounded,
                )
              else
                ...data.recentActivity.asMap().entries.map(
                      (e) => _ActivityTile(activity: e.value)
                          .animate()
                          .fadeIn(duration: 300.ms, delay: (e.key * 40).ms)
                          .slideY(begin: .06, end: 0),
                    ),
            ],
          ),
        ),
      ],
    );
  }
}

// ---------------- بطاقات الأرقام ----------------

class _StatsGrid extends StatelessWidget {
  final _DashboardData data;
  const _StatsGrid({required this.data});

  @override
  Widget build(BuildContext context) {
    final cards = <Widget>[
      const StatCard(
        icon: Icons.people_alt_rounded,
        label: 'عدد العملاء',
        value: '',
      ),
      const StatCard(
        icon: Icons.today_rounded,
        label: 'زيارات اليوم',
        value: '',
      ),
      const StatCard(
        icon: Icons.date_range_rounded,
        label: 'زيارات الأسبوع',
        value: '',
      ),
      const StatCard(
        icon: Icons.stars_rounded,
        label: 'النقاط الموزّعة',
        value: '',
      ),
      const StatCard(
        icon: Icons.card_giftcard_rounded,
        label: 'المكافآت المُستبدلة',
        value: '',
      ),
      const StatCard(
        icon: Icons.replay_rounded,
        label: 'معدّل العودة',
        value: '',
      ),
    ];

    // قيم البطاقات بالترتيب نفسه أعلاه.
    final values = <String>[
      '${data.customers}',
      '${data.visitsToday}',
      '${data.visitsWeek}',
      NumberFormat.decimalPattern('ar').format(data.pointsAwarded),
      '${data.redemptions}',
      '${data.returnRate.toStringAsFixed(0)}%',
    ];

    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: AppSpacing.md,
      crossAxisSpacing: AppSpacing.md,
      childAspectRatio: 1.45,
      children: [
        for (var i = 0; i < cards.length; i++)
          StatCard(
            icon: (cards[i] as StatCard).icon,
            label: (cards[i] as StatCard).label,
            value: values[i],
            highlight: i == 0,
          )
              .animate()
              .fadeIn(duration: 300.ms, delay: (i * 60).ms)
              .slideY(begin: .08, end: 0),
      ],
    );
  }
}

// ---------------- فلتر الفرع ----------------

class _BranchFilter extends ConsumerWidget {
  final String merchantId;
  final String? selected;
  final ValueChanged<String?> onChanged;

  const _BranchFilter({
    required this.merchantId,
    required this.selected,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final branchesAsync = ref.watch(_branchesProvider(merchantId));
    return branchesAsync.maybeWhen(
      data: (branches) {
        // لا تعرض الفلتر لو فرع واحد أو أقل.
        if (branches.length <= 1) return const SizedBox.shrink();
        return AppCard(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String?>(
              isExpanded: true,
              value: selected,
              icon: const Icon(Icons.keyboard_arrow_down_rounded),
              items: [
                const DropdownMenuItem<String?>(
                  value: null,
                  child: Text('كل الفروع'),
                ),
                ...branches.map(
                  (b) => DropdownMenuItem<String?>(
                    value: b.id,
                    child: Text(b.name),
                  ),
                ),
              ],
              onChanged: onChanged,
            ),
          ),
        );
      },
      orElse: () => const SizedBox.shrink(),
    );
  }
}

// ---------------- بانر التجربة ----------------

class _TrialBanner extends StatelessWidget {
  final int daysLeft;
  const _TrialBanner({required this.daysLeft});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppTheme.radius),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          const Icon(Icons.timer_outlined, color: AppColors.warning),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'تنتهي تجربتك خلال $daysLeft ${daysLeft == 1 ? "يوم" : "أيام"} — اشترك للمتابعة.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textPrimary, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------- عنصر النشاط ----------------

class _ActivityTile extends StatelessWidget {
  final _Activity activity;
  const _ActivityTile({required this.activity});

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppCard(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(
              radius: 20,
              backgroundColor: AppColors.surfaceCream,
              child: Icon(activity.icon,
                  size: 20, color: AppColors.primaryDark),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(activity.title, style: text.bodyMedium),
            ),
            Text(
              activity.timeLabel,
              style: text.bodySmall?.copyWith(color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------- بيانات/مزوّدات ----------------

typedef _DashKey = ({String merchantId, String? branchId});

class _Branch {
  final String id;
  final String name;
  const _Branch(this.id, this.name);
}

class _Activity {
  final String title;
  final IconData icon;
  final String timeLabel;
  const _Activity(
      {required this.title, required this.icon, required this.timeLabel});
}

class _DashboardData {
  final int customers;
  final int visitsToday;
  final int visitsWeek;
  final int pointsAwarded;
  final int redemptions;
  final double returnRate;
  final int? trialDaysLeft;
  final List<_Activity> recentActivity;

  const _DashboardData({
    required this.customers,
    required this.visitsToday,
    required this.visitsWeek,
    required this.pointsAwarded,
    required this.redemptions,
    required this.returnRate,
    required this.trialDaysLeft,
    required this.recentActivity,
  });
}

/// فروع التاجر — لملء فلتر الفرع.
final _branchesProvider =
    FutureProvider.family<List<_Branch>, String>((ref, merchantId) async {
  final client = Supabase.instance.client;
  final rows = await client
      .from('branches')
      .select('id, name')
      .eq('merchant_id', merchantId)
      .eq('active', true)
      .order('name');
  return (rows as List)
      .map((r) => _Branch(r['id'] as String, (r['name'] as String?) ?? 'فرع'))
      .toList();
});

/// تجميع أرقام اللوحة. يحترم فلتر الفرع لو مُحدّد.
final _dashboardDataProvider =
    FutureProvider.family<_DashboardData, _DashKey>((ref, key) async {
  final client = Supabase.instance.client;
  final merchantId = key.merchantId;
  final branchId = key.branchId;

  final now = DateTime.now();
  final todayStr = DateFormat('yyyy-MM-dd').format(now);
  final weekAgo = now.subtract(const Duration(days: 7));

  // عدد العملاء (محافظ user_stores) — أحد مفاتيح اللوحة.
  var storesQuery = client
      .from('user_stores')
      .select('user_id')
      .eq('merchant_id', merchantId)
      .count(CountOption.exact);
  if (branchId != null) {
    storesQuery = client
        .from('user_stores')
        .select('user_id')
        .eq('merchant_id', merchantId)
        .eq('branch_id', branchId)
        .count(CountOption.exact);
  }
  final storesRes = await storesQuery;
  final customers = storesRes.count;

  // زيارات اليوم.
  final visitsToday = await _count(
    client,
    table: 'user_visits',
    merchantId: merchantId,
    branchId: branchId,
    extra: (q) => q.eq('visit_date', todayStr),
  );

  // زيارات الأسبوع.
  final visitsWeek = await _count(
    client,
    table: 'user_visits',
    merchantId: merchantId,
    branchId: branchId,
    extra: (q) => q.gte('visit_date', DateFormat('yyyy-MM-dd').format(weekAgo)),
  );

  // إجمالي النقاط الموزّعة (earn).
  var pointsQuery = client
      .from('points_transactions')
      .select('points')
      .eq('merchant_id', merchantId)
      .eq('type', 'earn');
  if (branchId != null) {
    pointsQuery = client
        .from('points_transactions')
        .select('points')
        .eq('merchant_id', merchantId)
        .eq('type', 'earn')
        .eq('branch_id', branchId);
  }
  final pointsRows = await pointsQuery;
  final pointsAwarded = (pointsRows as List)
      .fold<int>(0, (sum, r) => sum + ((r['points'] as num?)?.toInt() ?? 0));

  // المكافآت المُستبدلة.
  final redemptions = await _count(
    client,
    table: 'reward_redemptions',
    merchantId: merchantId,
    branchId: branchId,
  );

  // معدّل العودة: نسبة العملاء بزيارتين فأكثر إلى إجمالي العملاء.
  var returningQuery = client
      .from('user_visits')
      .select('user_id')
      .eq('merchant_id', merchantId);
  if (branchId != null) {
    returningQuery = client
        .from('user_visits')
        .select('user_id')
        .eq('merchant_id', merchantId)
        .eq('branch_id', branchId);
  }
  final visitRows = await returningQuery;
  final counts = <String, int>{};
  for (final r in visitRows as List) {
    final uid = r['user_id'] as String?;
    if (uid != null) counts[uid] = (counts[uid] ?? 0) + 1;
  }
  final returners = counts.values.where((c) => c >= 2).length;
  final returnRate =
      counts.isEmpty ? 0.0 : (returners / counts.length) * 100.0;

  // تنبيه التجربة.
  int? trialDaysLeft;
  final sub = await client
      .from('subscriptions')
      .select('status, trial_ends_at')
      .eq('merchant_id', merchantId)
      .maybeSingle();
  if (sub != null && sub['status'] == 'trial' && sub['trial_ends_at'] != null) {
    final ends = DateTime.tryParse(sub['trial_ends_at'] as String);
    if (ends != null) {
      final diff = ends.difference(now).inDays;
      trialDaysLeft = diff < 0 ? 0 : diff;
    }
  }

  // آخر النشاطات (آخر 8 عمليات نقاط).
  var activityQuery = client
      .from('points_transactions')
      .select('type, points, reason, created_at')
      .eq('merchant_id', merchantId);
  if (branchId != null) {
    activityQuery = client
        .from('points_transactions')
        .select('type, points, reason, created_at')
        .eq('merchant_id', merchantId)
        .eq('branch_id', branchId);
  }
  final actRows =
      await activityQuery.order('created_at', ascending: false).limit(8);
  final recent = (actRows as List).map((r) {
    final type = r['type'] as String? ?? 'earn';
    final pts = (r['points'] as num?)?.toInt() ?? 0;
    final created = DateTime.tryParse(r['created_at'] as String? ?? '');
    final isRedeem = type == 'redeem';
    return _Activity(
      title: isRedeem
          ? 'استبدال مكافأة بـ $pts نقطة'
          : 'إضافة $pts نقطة لعميل',
      icon: isRedeem
          ? Icons.card_giftcard_rounded
          : Icons.add_circle_outline_rounded,
      timeLabel: created == null ? '' : _relativeTime(created),
    );
  }).toList();

  return _DashboardData(
    customers: customers,
    visitsToday: visitsToday,
    visitsWeek: visitsWeek,
    pointsAwarded: pointsAwarded,
    redemptions: redemptions,
    returnRate: returnRate,
    trialDaysLeft: trialDaysLeft,
    recentActivity: recent,
  );
});

/// عدّاد عام يفلتر بالتاجر والفرع مع شرط إضافي اختياري.
Future<int> _count(
  SupabaseClient client, {
  required String table,
  required String merchantId,
  String? branchId,
  PostgrestFilterBuilder Function(PostgrestFilterBuilder q)? extra,
}) async {
  PostgrestFilterBuilder query =
      client.from(table).select('id').eq('merchant_id', merchantId);
  if (branchId != null) query = query.eq('branch_id', branchId);
  if (extra != null) query = extra(query);
  final res = await query.count(CountOption.exact);
  return res.count;
}

String _relativeTime(DateTime t) {
  final diff = DateTime.now().difference(t);
  if (diff.inMinutes < 1) return 'الآن';
  if (diff.inMinutes < 60) return 'قبل ${diff.inMinutes} د';
  if (diff.inHours < 24) return 'قبل ${diff.inHours} س';
  if (diff.inDays < 7) return 'قبل ${diff.inDays} يوم';
  return DateFormat('yyyy/MM/dd').format(t);
}
