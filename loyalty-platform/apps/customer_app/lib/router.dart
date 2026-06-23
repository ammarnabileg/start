import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'features/shell/home_shell.dart';
import 'features/qr/qr_home_screen.dart';
import 'features/stores/my_stores_screen.dart';
import 'features/notifications/notifications_screen.dart';
import 'features/wheel/my_prizes_screen.dart';
import 'features/profile/profile_screen.dart';
import 'features/auth/welcome_screen.dart';
import 'features/splash/splash_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/splash',
    redirect: (context, state) {
      final loggedIn = Supabase.instance.client.auth.currentSession != null;
      final loggingFlow = state.matchedLocation == '/welcome' ||
          state.matchedLocation == '/splash';
      if (!loggedIn && !loggingFlow) return '/welcome';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/welcome', builder: (_, __) => const WelcomeScreen()),
      // الـ Bottom Tab Bar (5 تابات ثابتة، زر QR بارز في النص)
      StatefulShellRoute.indexedStack(
        builder: (_, __, shell) => HomeShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/', builder: (_, __) => const QrHomeScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/stores', builder: (_, __) => const MyStoresScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/notifications',
                builder: (_, __) => const NotificationsScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/prizes', builder: (_, __) => const MyPrizesScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
          ]),
        ],
      ),
    ],
  );
});
