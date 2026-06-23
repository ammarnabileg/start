import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// خدمة الإشعارات (Push / FCM) لتطبيق التاجر + تقرير الأعطال (Crashlytics).
///
/// كل نداءات Firebase مغلّفة بـ try/catch بحيث يظل التطبيق يعمل حتى قبل إضافة
/// ملفات إعداد Firebase (google-services.json). الموظفون حسابات Auth، فيُسجَّل
/// توكنهم في نفس جدول device_tokens المستخدَم للعملاء.
class PushService {
  PushService._();

  static bool _firebaseReady = false;

  /// تهيئة عند الإقلاع — آمنة عند غياب إعداد Firebase.
  static Future<void> init() async {
    try {
      await Firebase.initializeApp();
      _firebaseReady = true;
    } catch (_) {
      _firebaseReady = false;
      return;
    }

    // تقرير الأعطال: توجيه أخطاء Flutter وغير المُلتقَطة إلى Crashlytics.
    try {
      FlutterError.onError = FirebaseCrashlytics.instance.recordFlutterError;
      PlatformDispatcher.instance.onError = (error, stack) {
        FirebaseCrashlytics.instance.recordError(error, stack, fatal: true);
        return true;
      };
    } catch (_) {
      // تجاهل — لا إعداد Crashlytics.
    }
  }

  /// تسجيل توكن FCM للموظف الحالي في device_tokens (يتابع التحديثات).
  static Future<void> registerForUser() async {
    if (!_firebaseReady) return;
    final client = Supabase.instance.client;
    final user = client.auth.currentUser;
    if (user == null) return;

    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();

      final token = await messaging.getToken();
      if (token != null) await _upsertToken(user.id, token);

      messaging.onTokenRefresh.listen((newToken) async {
        final current = client.auth.currentUser;
        if (current == null) return;
        await _upsertToken(current.id, newToken);
      });
    } catch (_) {
      // تجاهل — لا إعداد أو رُفض الإذن.
    }
  }

  static String get _platform {
    try {
      if (Platform.isIOS) return 'ios';
      if (Platform.isAndroid) return 'android';
    } catch (_) {
      // على منصة غير مدعومة، استخدم defaultTargetPlatform.
    }
    return defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
  }

  static Future<void> _upsertToken(String uid, String token) async {
    try {
      await Supabase.instance.client.from('device_tokens').upsert(
        {'user_id': uid, 'token': token, 'platform': _platform},
        onConflict: 'user_id,token',
      );
    } catch (_) {
      // تجاهل أخطاء الشبكة/الإعداد.
    }
  }
}
