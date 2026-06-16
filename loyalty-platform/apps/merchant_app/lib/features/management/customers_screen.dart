import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/merchant_providers.dart';
import '../announcements/announcements_screen.dart';

/// عميل التاجر (مجمّع من RPC merchant_customers).
class MerchantCustomer {
  final String userId;
  final String name;
  final String? phone;
  final int availablePoints;
  final int lifetimePoints;
  final String? levelName;
  final int visits;
  final bool pushOptIn;
  final DateTime? firstLinked;
  final DateTime? lastActivity;

  MerchantCustomer.fromJson(Map<String, dynamic> j)
      : userId = j['user_id'] as String,
        name = j['name'] as String,
        phone = j['phone'] as String?,
        availablePoints = (j['available_points'] as num?)?.toInt() ?? 0,
        lifetimePoints = (j['lifetime_points'] as num?)?.toInt() ?? 0,
        levelName = j['level_name'] as String?,
        visits = (j['visits'] as num?)?.toInt() ?? 0,
        pushOptIn = j['push_opt_in'] as bool? ?? false,
        firstLinked = j['first_linked'] == null
            ? null
            : DateTime.parse(j['first_linked'] as String),
        lastActivity = j['last_activity'] == null
            ? null
            : DateTime.parse(j['last_activity'] as String);
}

final _customersProvider =
    FutureProvider.autoDispose.family<List<MerchantCustomer>, String>(
  (ref, search) async {
    final staff = await ref.watch(currentStaffProvider.future);
    final rows = await Supabase.instance.client.rpc('merchant_customers', params: {
      'p_merchant': staff.merchantId,
      'p_search': search,
      'p_limit': 100,
      'p_offset': 0,
    });
    return (rows as List)
        .map((r) => MerchantCustomer.fromJson(r as Map<String, dynamic>))
        .toList();
  },
);

class CustomersScreen extends ConsumerStatefulWidget {
  const CustomersScreen({super.key});
  @override
  ConsumerState<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends ConsumerState<CustomersScreen> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(_customersProvider(_search));
    return Scaffold(
      appBar: AppBar(
        title: const Text('العملاء'),
        actions: [
          IconButton(
            tooltip: 'إرسال إشعار',
            icon: const Icon(Icons.campaign_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const AnnouncementsScreen()),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: TextField(
              decoration: const InputDecoration(
                hintText: 'ابحث بالاسم أو رقم الجوال',
                prefixIcon: Icon(Icons.search_rounded),
              ),
              onChanged: (v) => setState(() => _search = v.trim()),
            ),
          ),
          Expanded(
            child: data.when(
              loading: () => const SkeletonList(),
              error: (e, _) => ErrorView(
                message: 'تعذّر تحميل العملاء',
                onRetry: () => ref.invalidate(_customersProvider(_search)),
              ),
              data: (list) {
                if (list.isEmpty) {
                  return const EmptyView(
                    icon: Icons.groups_2_outlined,
                    title: 'لا يوجد عملاء بعد',
                    message: 'سيظهر العملاء هنا فور مسح أكوادهم أول مرة.',
                  );
                }
                return RefreshIndicator(
                  onRefresh: () async =>
                      ref.invalidate(_customersProvider(_search)),
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(
                        AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.lg),
                    itemCount: list.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (_, i) => _CustomerCard(c: list[i])
                        .animate()
                        .fadeIn(duration: 250.ms, delay: (i * 30).ms)
                        .slideY(begin: .06, end: 0),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _CustomerCard extends StatelessWidget {
  final MerchantCustomer c;
  const _CustomerCard({required this.c});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      onTap: () => showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (_) => _CustomerDetailSheet(c: c),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 24,
            backgroundColor: AppColors.primaryLight,
            child: Text(c.name.characters.first,
                style: const TextStyle(
                    fontWeight: FontWeight.w800, fontSize: 18)),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(c.name, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 2),
                Text(
                  [
                    if (c.levelName != null) c.levelName!,
                    '${c.visits} زيارة',
                  ].join(' · '),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          PointsBadge(points: c.availablePoints),
        ],
      ),
    );
  }
}

class _CustomerDetailSheet extends StatelessWidget {
  final MerchantCustomer c;
  const _CustomerDetailSheet({required this.c});

  String _date(DateTime? d) =>
      d == null ? '—' : DateFormat('yyyy/MM/dd', 'ar').format(d);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
      child: SingleChildScrollView(
        child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: AppColors.primaryLight,
                child: Text(c.name.characters.first,
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 20)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(c.name,
                        style: Theme.of(context).textTheme.titleLarge),
                    if (c.phone != null)
                      Text(c.phone!,
                          style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                  child: StatCard(
                      icon: Icons.stars_rounded,
                      label: 'متاحة',
                      value: '${c.availablePoints}',
                      highlight: true)),
              const SizedBox(width: 10),
              Expanded(
                  child: StatCard(
                      icon: Icons.workspace_premium_rounded,
                      label: 'إجمالي',
                      value: '${c.lifetimePoints}')),
              const SizedBox(width: 10),
              Expanded(
                  child: StatCard(
                      icon: Icons.event_repeat_rounded,
                      label: 'زيارات',
                      value: '${c.visits}')),
            ],
          ),
          const SizedBox(height: 16),
          _row('المستوى', c.levelName ?? '—'),
          _row('الإشعارات', c.pushOptIn ? 'مفعّلة' : 'غير مفعّلة'),
          _row('أول ارتباط', _date(c.firstLinked)),
          _row('آخر نشاط', _date(c.lastActivity)),
        ],
        ),
      ),
    );
  }

  Widget _row(String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(k, style: const TextStyle(color: AppColors.textSecondary)),
            Flexible(
              child: Text(v,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                  overflow: TextOverflow.ellipsis,
                  maxLines: 1,
                  textAlign: TextAlign.end),
            ),
          ],
        ),
      );
}
