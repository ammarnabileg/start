import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../data/repositories/user_repository.dart';
import '../../core/locale_controller.dart';
import '../../core/proximity_service.dart';
import '../../core/push_service.dart';
import '../auth/location_priming_screen.dart';
import '../qr/qr_providers.dart';

/// الإعدادات (Settings) — راجع CUSTOMER_APP.md 1.17.
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  bool _busy = false;

  Future<void> _updateFlag(String column, bool value) async {
    setState(() => _busy = true);
    try {
      await ref.read(userRepoProvider).updateFlag(column, value);
      ref.invalidate(currentUserProvider);
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر حفظ الإعداد، حاول مرة أخرى.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _onPushChanged(bool value) async {
    await _updateFlag('push_opt_in', value);
    if (value) {
      // عند التفعيل → اطلب الإذن وسجّل توكن FCM (أفضل جهد).
      await PushService.registerForUser();
    }
  }

  Future<void> _onProximityChanged(bool value, bool currentlyOff) async {
    if (value && currentlyOff) {
      // أول تفعيل → شاشة تمهيد الإذن قبل طلبه.
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => const LocationPrimingScreen(),
      ));
    }
    await _updateFlag('proximity_opt_in', value);
    // بعد حفظ التفضيل، شغّل/أوقف خدمة القرب (أفضل جهد).
    if (value) {
      await ProximityService.instance.start();
    } else {
      await ProximityService.instance.stop();
    }
  }

  Future<void> _exportData() async {
    setState(() => _busy = true);
    try {
      final data = await ref.read(userRepoProvider).exportData();
      final pretty =
          const JsonEncoder.withIndent('  ').convert(data);
      final bytes = Uint8List.fromList(utf8.encode(pretty));
      final file = XFile.fromData(
        bytes,
        name: 'hatchy-my-data.json',
        mimeType: 'application/json',
      );
      if (!mounted) return;
      await Share.shareXFiles([file], subject: 'نسخة من بياناتي');
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر تصدير بياناتك، حاول مرة أخرى.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirmDelete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('حذف الحساب'),
        content: const Text(
            'سيتم حذف بياناتك ونقاطك نهائيًا. لا يمكن التراجع.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('إلغاء'),
          ),
          TextButton(
            style: TextButton.styleFrom(foregroundColor: AppColors.error),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('حذف نهائيًا'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _busy = true);
    try {
      await ref.read(userRepoProvider).deleteAccount();
      if (mounted) context.go('/welcome');
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر حذف الحساب، حاول مرة أخرى.',
            error: true);
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final userAsync = ref.watch(currentUserProvider);
    final theme = Theme.of(context);
    return Scaffold(
      appBar:
          AppBar(title: const Text('الإشعارات والخصوصية'), centerTitle: true),
      body: userAsync.when(
        loading: () => const SkeletonList(count: 5),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل الإعدادات',
            onRetry: () => ref.invalidate(currentUserProvider)),
        data: (user) => ListView(
          padding: AppSpacing.screen,
          children: [
            const SectionHeader(title: 'الإشعارات'),
            AppCard(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
              child: Column(
                children: [
                  SwitchListTile(
                    title: const Text('إشعارات عامة'),
                    subtitle:
                        const Text('تنبيهات عند حصولك على نقاط أو مكافآت.'),
                    value: user.pushOptIn,
                    onChanged: _busy ? null : (v) => _onPushChanged(v),
                  ),
                  const Divider(height: 1, indent: 16, endIndent: 16),
                  SwitchListTile(
                    title: const Text('إشعارات القرب (الموقع)'),
                    subtitle:
                        const Text('تنبيه عندما تكون قريبًا من أحد متاجرك.'),
                    value: user.proximityOptIn,
                    onChanged: _busy
                        ? null
                        : (v) => _onProximityChanged(v, !user.proximityOptIn),
                  ),
                ],
              ),
            ),
            AppSpacing.gapLg,
            const SectionHeader(title: 'الخصوصية'),
            AppCard(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
              child: Column(
                children: [
                  SwitchListTile(
                    title: const Text('الظهور في لوحات الصدارة'),
                    subtitle: const Text(
                        'عند الإيقاف، لن يظهر اسمك أو مركزك لأي مستخدم.'),
                    value: user.leaderboardOptIn,
                    onChanged: _busy
                        ? null
                        : (v) => _updateFlag('leaderboard_opt_in', v),
                  ),
                  const Divider(height: 1, indent: 16, endIndent: 16),
                  SwitchListTile(
                    title: const Text('مشاركة بياناتي مع المتاجر'),
                    subtitle: const Text(
                        'السماح للمتاجر المرتبط بها بعرض بيانات تواصلك والتواصل '
                        'معك. عند الإيقاف تختفي من قوائم العملاء وصدارة المتاجر '
                        '(تبقى نقاطك وزياراتك ومكافآتك كما هي).'),
                    value: user.shareProfileWithMerchants,
                    onChanged: _busy
                        ? null
                        : (v) => _updateFlag('share_profile_with_merchants', v),
                  ),
                  const Divider(height: 1, indent: 16, endIndent: 16),
                  ListTile(
                    leading: const AppIcon(Icons.privacy_tip_outlined),
                    title: const Text('سياسة الخصوصية'),
                    trailing: const AppIcon(Icons.chevron_left_rounded),
                    onTap: () => launchUrl(
                      Uri.parse('https://wataddigital.com/privacy'),
                      mode: LaunchMode.externalApplication,
                    ),
                  ),
                ],
              ),
            ),
            AppSpacing.gapLg,
            const SectionHeader(title: 'اللغة'),
            AppCard(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
              child: ListTile(
                leading: const AppIcon(Icons.language_rounded),
                title: const Text('اللغة'),
                subtitle:
                    Text(ref.watch(localeProvider).languageCode == 'ar'
                        ? 'العربية'
                        : 'English'),
                trailing: const AppIcon(Icons.chevron_left_rounded),
                onTap: () => showModalBottomSheet<void>(
                  context: context,
                  builder: (_) => Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      ListTile(
                        title: const Text('العربية'),
                        trailing:
                            ref.read(localeProvider).languageCode == 'ar'
                                ? const AppIcon(Icons.check, color: AppColors.success)
                                : null,
                        onTap: () {
                          ref
                              .read(localeProvider.notifier)
                              .setLocale(const Locale('ar'));
                          Navigator.pop(context);
                        },
                      ),
                      ListTile(
                        title: const Text('English'),
                        trailing:
                            ref.read(localeProvider).languageCode == 'en'
                                ? const AppIcon(Icons.check, color: AppColors.success)
                                : null,
                        onTap: () {
                          ref
                              .read(localeProvider.notifier)
                              .setLocale(const Locale('en'));
                          Navigator.pop(context);
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
            AppSpacing.gapLg,
            const SectionHeader(title: 'الحساب'),
            AppCard(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
              child: Column(
                children: [
                  ListTile(
                    leading: const AppIcon(Icons.download_rounded),
                    title: const Text('تصدير بياناتي'),
                    subtitle: const Text(
                        'احصل على نسخة من بياناتك ونقاطك ومعاملاتك.'),
                    trailing: const AppIcon(Icons.chevron_left_rounded),
                    onTap: _busy ? null : _exportData,
                  ),
                  const Divider(height: 1, indent: 16, endIndent: 16),
                  ListTile(
                    leading: const AppIcon(Icons.delete_outline_rounded,
                        color: AppColors.error),
                    title: Text('حذف الحساب',
                        style: theme.textTheme.titleMedium
                            ?.copyWith(color: AppColors.error)),
                    trailing: const AppIcon(Icons.chevron_left_rounded),
                    onTap: _busy ? null : _confirmDelete,
                  ),
                ],
              ),
            ),
          ],
        ).animate().fadeIn(duration: 300.ms).slideY(begin: .04, end: 0),
      ),
    );
  }
}
