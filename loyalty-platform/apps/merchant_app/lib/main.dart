import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'core/locale_controller.dart';
import 'core/push_service.dart';
import 'features/splash/splash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Supabase.initialize(url: Env.supabaseUrl, publishableKey: Env.supabaseAnonKey);
  // تهيئة الإشعارات/تقرير الأعطال — آمنة عند غياب إعداد Firebase.
  await PushService.init();
  runApp(const ProviderScope(child: MerchantApp()));
}

class MerchantApp extends ConsumerWidget {
  const MerchantApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locale = ref.watch(localeProvider);
    return MaterialApp(
      title: 'Hatchy Business',
      debugShowCheckedModeBanner: false,
      // داشبورد التاجر بالوضع الداكن (زي صورة Hatchy الداكنة) — اختياري.
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      locale: locale,
      builder: (context, child) => Directionality(
        textDirection: locale.languageCode == 'ar'
            ? TextDirection.rtl
            : TextDirection.ltr,
        child: child!,
      ),
      // نقطة البداية: Splash يقرّر التوجيه (لوحة التحكم أو الترحيب).
      home: const SplashScreen(),
    );
  }
}
