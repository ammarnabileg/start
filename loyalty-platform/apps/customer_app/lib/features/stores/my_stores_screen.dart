import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'store_detail_screen.dart';

/// متاجري — المحافظ المرتبطة بالعميل (محفظة لكل فرع/تاجر حسب الإعداد).
final myStoresProvider = FutureProvider.autoDispose<List<UserStore>>((ref) async {
  final client = Supabase.instance.client;
  final uid = client.auth.currentUser!.id;
  final rows = await client
      .from('user_stores')
      .select(
          '*, merchants(business_name, logo_url), loyalty_levels(name), branches(name)')
      .eq('user_id', uid)
      .order('first_linked_at', ascending: false);

  return (rows as List).map((r) {
    final m = r['merchants'] as Map<String, dynamic>?;
    final lvl = r['loyalty_levels'] as Map<String, dynamic>?;
    final br = r['branches'] as Map<String, dynamic>?;
    return UserStore.fromJson({
      ...r,
      'merchant_name': m?['business_name'],
      'merchant_logo_url': m?['logo_url'],
      'current_level_name': lvl?['name'],
      'branch_name': br?['name'],
    });
  }).toList();
});

class MyStoresScreen extends ConsumerWidget {
  const MyStoresScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stores = ref.watch(myStoresProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('متاجري'), centerTitle: true),
      body: stores.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل المتاجر',
            onRetry: () => ref.invalidate(myStoresProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const EmptyView(
              icon: Icons.storefront_outlined,
              title: 'لا توجد متاجر بعد',
              message: 'أرِ رمزك لأي متجر مشارك ليظهر هنا تلقائيًا.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myStoresProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: list.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (_, i) => _StoreCard(store: list[i])
                  .animate()
                  .fadeIn(duration: 300.ms, delay: (i * 60).ms)
                  .slideY(begin: .08, end: 0, curve: Curves.easeOut),
            ),
          );
        },
      ),
    );
  }
}

class _StoreCard extends StatelessWidget {
  final UserStore store;
  const _StoreCard({required this.store});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => StoreDetailScreen(store: store),
      )),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: store.merchantLogoUrl != null
                ? CachedNetworkImage(
                    imageUrl: store.merchantLogoUrl!,
                    width: 56, height: 56, fit: BoxFit.cover)
                : const _LogoFallback(),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(store.merchantName ?? 'متجر',
                    style: Theme.of(context).textTheme.titleMedium,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    if (store.branchName != null)
                      Text(store.branchName!,
                          style: Theme.of(context).textTheme.bodySmall),
                    if (store.currentLevelName != null) _LevelChip(store.currentLevelName!),
                  ],
                ),
                const SizedBox(height: 10),
                PointsBadge(points: store.availablePoints),
              ],
            ),
          ),
          const SizedBox(width: 8),
          const Icon(Icons.chevron_left_rounded,
              color: AppColors.textSecondary),
        ],
      ),
    );
  }
}

class _LevelChip extends StatelessWidget {
  final String label;
  const _LevelChip(this.label);
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: AppColors.surfaceCream,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.workspace_premium_outlined,
                size: 14, color: AppColors.primaryDark),
            const SizedBox(width: 4),
            Text(label,
                style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark)),
          ],
        ),
      );
}

class _LogoFallback extends StatelessWidget {
  const _LogoFallback();
  @override
  Widget build(BuildContext context) => Container(
        width: 56,
        height: 56,
        color: AppColors.surfaceCream,
        child: const Icon(Icons.storefront, color: AppColors.primaryDark),
      );
}
