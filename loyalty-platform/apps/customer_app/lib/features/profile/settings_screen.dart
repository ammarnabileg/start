import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

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
      final client = Supabase.instance.client;
      final uid = client.auth.currentUser!.id;
      await client.from('users').update({column: value}).eq('id', uid);
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

  Future<void> _onProximityChanged(bool value, bool currentlyOff) async {
    if (value && currentlyOff) {
      // أول تفعيل → شاشة تمهيد الإذن قبل طلبه.
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => const LocationPrimingScreen(),
      ));
    }
    await _updateFlag('proximity_opt_in', value);
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
      await Supabase.instance.client.functions.invoke('delete-account');
      await Supabase.instance.client.auth.signOut();
      if (mounted) context.go('/welcome');
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تعذّر حذف الحساب، حاول مرة أخرى.')),
        );
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
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل الإعدادات',
            onRetry: () => ref.invalidate(currentUserProvider)),
        data: (user) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _SectionTitle('الإشعارات'),
            SwitchListTile(
              title: const Text('إشعارات عامة'),
              subtitle: const Text('تنبيهات عند حصولك على نقاط أو مكافآت.'),
              value: user.pushOptIn,
              onChanged: _busy
                  ? null
                  : (v) => _updateFlag('push_opt_in', v),
            ),
            SwitchListTile(
              title: const Text('إشعارات القرب (الموقع)'),
              subtitle:
                  const Text('تنبيه عندما تكون قريبًا من أحد متاجرك.'),
              value: user.proximityOptIn,
              onChanged: _busy
                  ? null
                  : (v) => _onProximityChanged(v, !user.proximityOptIn),
            ),
            const Divider(height: 32),
            _SectionTitle('الخصوصية'),
            SwitchListTile(
              title: const Text('الظهور في لوحات الصدارة'),
              subtitle: const Text(
                  'عند الإيقاف، لن يظهر اسمك أو مركزك لأي مستخدم.'),
              value: user.leaderboardOptIn,
              onChanged: _busy
                  ? null
                  : (v) => _updateFlag('leaderboard_opt_in', v),
            ),
            ListTile(
              leading: const Icon(Icons.privacy_tip_outlined),
              title: const Text('سياسة الخصوصية'),
              trailing: const Icon(Icons.chevron_left_rounded),
              onTap: () => launchUrl(
                Uri.parse('https://wataddigital.com/privacy'),
                mode: LaunchMode.externalApplication,
              ),
            ),
            const Divider(height: 32),
            _SectionTitle('اللغة'),
            ListTile(
              leading: const Icon(Icons.language_rounded),
              title: const Text('اللغة'),
              subtitle: const Text('العربية'),
              trailing: const Text('العربية / English'),
              // TODO: تبديل اللغة (ar/en) عبر مزوّد الـ locale.
              onTap: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                      content: Text('تبديل اللغة سيتوفر قريبًا.')),
                );
              },
            ),
            const Divider(height: 32),
            _SectionTitle('الحساب'),
            ListTile(
              leading: const Icon(Icons.delete_outline_rounded,
                  color: AppColors.error),
              title: Text('حذف الحساب',
                  style: theme.textTheme.titleMedium
                      ?.copyWith(color: AppColors.error)),
              trailing: const Icon(Icons.chevron_left_rounded),
              onTap: _busy ? null : _confirmDelete,
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8, top: 4),
      child: Text(text,
          style: Theme.of(context)
              .textTheme
              .titleMedium
              ?.copyWith(color: AppColors.textSecondary)),
    );
  }
}
