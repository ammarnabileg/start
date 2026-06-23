import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';
import '../../core/perm_gate.dart';
import '../../data/repositories/announcements_repository.dart';

/// الحد الشهري للإشعارات (يحدّده مالك النظام) + المستهلَك + المتبقّي.
final notificationUsageProvider =
    FutureProvider.autoDispose<({int quota, int used, int remaining})>(
        (ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final res = await ref
      .read(announcementsRepoProvider)
      .notificationUsage(staff.merchantId);
  return (
    quota: (res['quota'] as num).toInt(),
    used: (res['used'] as num).toInt(),
    remaining: (res['remaining'] as num).toInt(),
  );
});

/// 2.12 — الإعلانات.
class AnnouncementsScreen extends ConsumerStatefulWidget {
  const AnnouncementsScreen({super.key});

  @override
  ConsumerState<AnnouncementsScreen> createState() =>
      _AnnouncementsScreenState();
}

class _AnnouncementsScreenState extends ConsumerState<AnnouncementsScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _body = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      // الإرسال الفعلي عبر Edge Function — يفرض الحد الشهري على السيرفر.
      final res = await ref.read(announcementsRepoProvider).sendAnnouncement(
            title: _title.text.trim(),
            body: _body.text.trim(),
          );
      final data = res.data as Map<String, dynamic>?;
      if (data?['error'] != null) {
        if (mounted) AppFeedback.toast(context, data!['error'] as String, error: true);
        return;
      }
      ref.invalidate(notificationUsageProvider);
      if (mounted) {
        _title.clear();
        _body.clear();
        final sent = data?['sent'] ?? 0;
        final remaining = data?['remaining'] ?? 0;
        await AppFeedback.success(
          context,
          title: 'تم إرسال الإعلان',
          message: 'وصل إلى $sent عميل. المتبقي هذا الشهر: $remaining.',
        );
      }
    } catch (_) {
      if (mounted) AppFeedback.toast(context, 'تعذّر الإرسال', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الإعلانات')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            AppCard(
              gradient: AppColors.goldGradient,
              child: Row(
                children: [
                  Container(
                    height: 48,
                    width: 48,
                    decoration: BoxDecoration(
                      color: AppColors.surface.withValues(alpha: .35),
                      borderRadius: BorderRadius.circular(AppRadii.md),
                    ),
                    child: const AppIcon(Icons.campaign_rounded,
                        color: AppColors.onPrimary),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('أرسل إشعارًا لكل عملائك',
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(color: AppColors.onPrimary)),
                        const SizedBox(height: 2),
                        Text('يصل فورًا لكل من فعّل الإشعارات.',
                            style: TextStyle(
                                color: AppColors.onPrimary
                                    .withValues(alpha: .85),
                                fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _QuotaBanner(usage: ref.watch(notificationUsageProvider)),
            const SizedBox(height: 16),
            const SectionHeader(title: 'محتوى الإعلان'),
            const SizedBox(height: 8),
            AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextFormField(
                    controller: _title,
                    decoration:
                        const InputDecoration(labelText: 'عنوان الإشعار'),
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _body,
                    decoration: const InputDecoration(labelText: 'النص'),
                    maxLines: 4,
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            if (ref.permCan(PermResource.announcements, PermAction.create))
              PrimaryButton(
                label: 'إرسال',
                icon: Icons.send_rounded,
                loading: _busy,
                onPressed: _send,
              )
            else
              const ReadOnlyNotice(),
          ],
        ).animate().fadeIn(duration: 300.ms).slideY(begin: .04, end: 0),
      ),
    );
  }
}

/// بانر الحد الشهري للإشعارات (يحدّده مالك النظام).
class _QuotaBanner extends StatelessWidget {
  final AsyncValue<({int quota, int used, int remaining})> usage;
  const _QuotaBanner({required this.usage});

  @override
  Widget build(BuildContext context) {
    return usage.when(
      loading: () => const Skeleton(height: 64, radius: AppRadii.xl),
      error: (_, __) => const SizedBox.shrink(),
      data: (u) {
        final ratio = u.quota == 0 ? 0.0 : u.used / u.quota;
        final low = u.remaining <= (u.quota * 0.1);
        return AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  AppIcon(Icons.notifications_active_outlined,
                      color: low ? AppColors.warning : AppColors.primaryDark),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text('رصيد الإشعارات هذا الشهر',
                        style: Theme.of(context).textTheme.titleMedium),
                  ),
                  Text('${u.remaining} / ${u.quota}',
                      style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: low ? AppColors.warning : AppColors.textPrimary)),
                ],
              ),
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LinearProgressIndicator(
                  value: ratio.clamp(0.0, 1.0),
                  minHeight: 8,
                  backgroundColor: AppColors.surfaceCream,
                  color: low ? AppColors.warning : AppColors.primary,
                ),
              ),
              const SizedBox(height: 6),
              const Text('الحد الأقصى يحدّده مزوّد المنصة.',
                  style: TextStyle(
                      color: AppColors.textSecondary, fontSize: 12)),
            ],
          ),
        );
      },
    );
  }
}
