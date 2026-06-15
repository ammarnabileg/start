import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../qr/qr_providers.dart';
import '../leaderboard/leaderboard_screen.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final userAsync = ref.watch(currentUserProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('حسابي'), centerTitle: true),
      body: userAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => const ErrorView(message: 'تعذّر تحميل الملف'),
        data: (user) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            AppCard(
              child: Row(
                children: [
                  CircleAvatar(
                      radius: 28,
                      backgroundColor: AppColors.primaryLight,
                      child: Text(user.name.characters.first,
                          style: const TextStyle(
                              fontSize: 22, fontWeight: FontWeight.w800))),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(user.name,
                            style: Theme.of(context).textTheme.titleLarge),
                        Text('عضوية: ${user.id.substring(0, 8).toUpperCase()}',
                            style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _Tile(
              icon: Icons.emoji_events_outlined,
              label: 'لوحة الصدارة العامة',
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => const LeaderboardScreen(
                    args: LeaderboardArgs(title: 'لوحة الصدارة')),
              )),
            ),
            _Tile(icon: Icons.card_giftcard_outlined, label: 'دعوة صديق (إحالة)'),
            _Tile(icon: Icons.notifications_none_rounded, label: 'الإشعارات والخصوصية'),
            _Tile(icon: Icons.language_rounded, label: 'اللغة'),
            const Divider(height: 32),
            _Tile(
                icon: Icons.logout_rounded,
                label: 'تسجيل الخروج',
                onTap: () async {
                  await Supabase.instance.client.auth.signOut();
                  if (context.mounted) context.go('/welcome');
                }),
            _Tile(
                icon: Icons.delete_outline_rounded,
                label: 'حذف الحساب',
                danger: true),
          ],
        ),
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final bool danger;
  const _Tile(
      {required this.icon, required this.label, this.onTap, this.danger = false});

  @override
  Widget build(BuildContext context) {
    final color = danger ? AppColors.error : AppColors.textPrimary;
    return ListTile(
      leading: Icon(icon, color: color),
      title: Text(label, style: TextStyle(color: color)),
      trailing: const Icon(Icons.chevron_left_rounded),
      onTap: onTap,
    );
  }
}
