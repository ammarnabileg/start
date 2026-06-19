import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/merchant_providers.dart';
import '../../core/perm_gate.dart';
import '../../data/paginated_notifier.dart';
import '../../data/repositories/branches_repository.dart';
import '../../data/repositories/customers_repository.dart';
import '../announcements/announcements_screen.dart';

/// عميل التاجر (مجمّع من RPC merchant_customers — الظاهرون فقط).
class MerchantCustomer {
  final String userId;
  final String name;
  final String? phone;
  final String? email;
  final int availablePoints;
  final int lifetimePoints;
  final String? levelName;
  final int visits;
  final bool pushOptIn;
  final String? branchName;
  final DateTime? firstLinked;
  final DateTime? lastActivity;

  MerchantCustomer.fromJson(Map<String, dynamic> j)
      : userId = j['user_id'] as String,
        name = j['name'] as String,
        phone = j['phone'] as String?,
        email = j['email'] as String?,
        availablePoints = (j['available_points'] as num?)?.toInt() ?? 0,
        lifetimePoints = (j['lifetime_points'] as num?)?.toInt() ?? 0,
        levelName = j['level_name'] as String?,
        visits = (j['visits'] as num?)?.toInt() ?? 0,
        pushOptIn = j['push_opt_in'] as bool? ?? false,
        branchName = j['branch_name'] as String?,
        firstLinked = j['first_linked'] == null
            ? null
            : DateTime.parse(j['first_linked'] as String),
        lastActivity = j['last_activity'] == null
            ? null
            : DateTime.parse(j['last_activity'] as String);

  /// نشِط = نشاط خلال آخر ٣٠ يومًا.
  bool get isActive =>
      lastActivity != null &&
      lastActivity!.isAfter(DateTime.now().subtract(const Duration(days: 30)));
}

/// مفتاح الاستعلام (بحث + فلاتر) — تُعاد إنشاء القائمة عند أي تغيّر.
class _Query {
  final String search;
  final CustomerFilters filters;
  const _Query(this.search, this.filters);
  @override
  bool operator ==(Object other) =>
      other is _Query && other.search == search && other.filters == filters;
  @override
  int get hashCode => Object.hash(search, filters);
}

/// قائمة عملاء مرقّمة لكل (بحث + فلاتر).
final _customersProvider = StateNotifierProvider.autoDispose.family<
    PaginatedNotifier<MerchantCustomer>,
    PaginatedState<MerchantCustomer>,
    _Query>((ref, q) {
  final repo = ref.read(customersRepoProvider);
  return PaginatedNotifier<MerchantCustomer>((offset, limit) async {
    final staff = await ref.read(currentStaffProvider.future);
    final rows = await repo.fetchCustomers(
      merchantId: staff.merchantId,
      search: q.search,
      filters: q.filters,
      limit: limit,
      offset: offset,
    );
    return rows
        .map((r) => MerchantCustomer.fromJson(r as Map<String, dynamic>))
        .toList();
  });
});

/// خيارات الفروع لفلتر الفرع.
final _branchOptionsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(branchesRepoProvider).fetchActiveBranchOptions(staff.merchantId);
});

class CustomersScreen extends ConsumerStatefulWidget {
  const CustomersScreen({super.key});
  @override
  ConsumerState<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends ConsumerState<CustomersScreen> {
  String _search = '';
  CustomerFilters _filters = CustomerFilters.none;

  @override
  Widget build(BuildContext context) {
    final q = _Query(_search, _filters);
    final state = ref.watch(_customersProvider(q));
    final notifier = ref.read(_customersProvider(q).notifier);
    // ملاحظة أداء: أُزيل البثّ الحيّ الكامل لـ user_stores (كان يبثّ كل عملاء
    // المتجر) — التحديث الآن عبر السحب للتحديث (pull-to-refresh).

    return Scaffold(
      appBar: AppBar(
        title: const Text('العملاء'),
        actions: [
          IconButton(
            tooltip: 'تصفية',
            icon: Badge(
              isLabelVisible: _filters.count > 0,
              label: Text('${_filters.count}'),
              child: const AppIcon(Icons.tune_rounded),
            ),
            onPressed: _openFilters,
          ),
          if (ref.permCan(PermResource.announcements, PermAction.create))
            IconButton(
              tooltip: 'إرسال إشعار',
              icon: const AppIcon(Icons.campaign_outlined),
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
                hintText: 'ابحث بالاسم أو الجوال أو البريد',
                prefixIcon: AppIcon(Icons.search_rounded),
              ),
              onChanged: (v) => setState(() => _search = v.trim()),
            ),
          ),
          Expanded(
            child: PaginatedListView<MerchantCustomer>(
              state: state,
              onLoadMore: notifier.loadMore,
              onRefresh: notifier.refresh,
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.lg),
              emptyIcon: Icons.groups_2_outlined,
              emptyTitle: _filters.isEmpty && _search.isEmpty
                  ? 'لا يوجد عملاء بعد'
                  : 'لا نتائج مطابقة',
              emptyMessage: _filters.isEmpty && _search.isEmpty
                  ? 'سيظهر العملاء هنا فور مسح أكوادهم أول مرة. (من أوقف مشاركة معلوماته لا يظهر)'
                  : 'جرّب تعديل البحث أو الفلاتر.',
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (_, c, i) => _CustomerCard(c: c)
                  .animate()
                  .fadeIn(duration: 250.ms, delay: (i * 30).ms)
                  .slideY(begin: .06, end: 0),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openFilters() async {
    final result = await showModalBottomSheet<CustomerFilters>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _FiltersSheet(initial: _filters),
    );
    if (result != null && mounted) setState(() => _filters = result);
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
            child: Text(c.name.initialOrQuestion,
                style:
                    const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(c.name,
                    style: Theme.of(context).textTheme.titleMedium,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
                const SizedBox(height: 2),
                Text(
                  [
                    if (c.levelName != null) c.levelName!,
                    '${c.visits} زيارة',
                    if (!c.isActive) 'غير نشِط',
                  ].join(' · '),
                  style: Theme.of(context).textTheme.bodySmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
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

  /// فتح واتساب برقم العميل (تنسيق دولي: أرقام فقط بدون +/مسافات).
  Future<void> _openWhatsApp(BuildContext context) async {
    final digits = (c.phone ?? '').replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isEmpty) return;
    final uri = Uri.parse('https://wa.me/$digits');
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      AppFeedback.toast(context, 'تعذّر فتح واتساب', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
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
                  child: Text(c.name.initialOrQuestion,
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 20)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(c.name,
                          style: theme.textTheme.titleLarge,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis),
                      if (c.phone != null)
                        Text(c.phone!, style: theme.textTheme.bodySmall),
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
            _row('البريد', c.email ?? '—'),
            _row('الفرع', c.branchName ?? '—'),
            _row('الحالة', c.isActive ? 'نشِط' : 'غير نشِط'),
            _row('الإشعارات', c.pushOptIn ? 'مفعّلة' : 'غير مفعّلة'),
            _row('أول ارتباط', _date(c.firstLinked)),
            _row('آخر نشاط', _date(c.lastActivity)),
            // العميل ظاهر (الـ RPC لا يُرجِع المخفيين)، فالتواصل مسموح.
            if ((c.phone ?? '').isNotEmpty) ...[
              const SizedBox(height: 20),
              PrimaryButton(
                label: 'تواصل عبر واتساب',
                icon: Icons.chat_rounded,
                onPressed: () => _openWhatsApp(context),
              ),
            ],
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

/// شيت الفلاتر: فرع · مستوى · نطاق نقاط · أقل زيارات · نشِط/غير نشِط.
class _FiltersSheet extends ConsumerStatefulWidget {
  final CustomerFilters initial;
  const _FiltersSheet({required this.initial});
  @override
  ConsumerState<_FiltersSheet> createState() => _FiltersSheetState();
}

class _FiltersSheetState extends ConsumerState<_FiltersSheet> {
  late String? _branchId = widget.initial.branchId;
  late bool? _active = widget.initial.active;
  late final _level = TextEditingController(text: widget.initial.level ?? '');
  late final _minPts =
      TextEditingController(text: widget.initial.minPoints?.toString() ?? '');
  late final _maxPts =
      TextEditingController(text: widget.initial.maxPoints?.toString() ?? '');
  late final _minVisits =
      TextEditingController(text: widget.initial.minVisits?.toString() ?? '');

  @override
  void dispose() {
    _level.dispose();
    _minPts.dispose();
    _maxPts.dispose();
    _minVisits.dispose();
    super.dispose();
  }

  int? _int(TextEditingController c) =>
      c.text.trim().isEmpty ? null : int.tryParse(c.text.trim());
  String? _str(TextEditingController c) =>
      c.text.trim().isEmpty ? null : c.text.trim();

  void _apply() => Navigator.of(context).pop(CustomerFilters(
        branchId: _branchId,
        level: _str(_level),
        minPoints: _int(_minPts),
        maxPoints: _int(_maxPts),
        minVisits: _int(_minVisits),
        active: _active,
      ));

  @override
  Widget build(BuildContext context) {
    final branches = ref.watch(_branchOptionsProvider).valueOrNull ?? const [];
    return Padding(
      padding: EdgeInsets.fromLTRB(
          20, 12, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SectionHeader(title: 'تصفية العملاء'),
            const SizedBox(height: 12),
            if (branches.isNotEmpty) ...[
              const Text('الفرع',
                  style: TextStyle(color: AppColors.textSecondary)),
              const SizedBox(height: 6),
              DropdownButtonFormField<String?>(
                value: _branchId,
                isExpanded: true,
                decoration: const InputDecoration(
                    prefixIcon: AppIcon(Icons.store_mall_directory_outlined)),
                items: [
                  const DropdownMenuItem(value: null, child: Text('كل الفروع')),
                  for (final b in branches)
                    DropdownMenuItem(
                        value: b['id'] as String,
                        child: Text(b['name'] as String? ?? '—',
                            overflow: TextOverflow.ellipsis)),
                ],
                onChanged: (v) => setState(() => _branchId = v),
              ),
              const SizedBox(height: 14),
            ],
            TextField(
              controller: _level,
              decoration: const InputDecoration(
                  labelText: 'المستوى (اسمه)',
                  prefixIcon: AppIcon(Icons.workspace_premium_outlined)),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _minPts,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(labelText: 'نقاط من'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _maxPts,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(labelText: 'نقاط إلى'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _minVisits,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(
                  labelText: 'أقل عدد زيارات',
                  prefixIcon: AppIcon(Icons.event_repeat_outlined)),
            ),
            const SizedBox(height: 16),
            const Text('النشاط',
                style: TextStyle(color: AppColors.textSecondary)),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              children: [
                ChoiceChip(
                  label: const Text('الكل'),
                  selected: _active == null,
                  onSelected: (_) => setState(() => _active = null),
                ),
                ChoiceChip(
                  label: const Text('نشِط'),
                  selected: _active == true,
                  onSelected: (_) => setState(() => _active = true),
                ),
                ChoiceChip(
                  label: const Text('غير نشِط'),
                  selected: _active == false,
                  onSelected: (_) => setState(() => _active = false),
                ),
              ],
            ),
            const SizedBox(height: 22),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () =>
                        Navigator.of(context).pop(CustomerFilters.none),
                    child: const Text('مسح الفلاتر'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: PrimaryButton(label: 'تطبيق', onPressed: _apply),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
