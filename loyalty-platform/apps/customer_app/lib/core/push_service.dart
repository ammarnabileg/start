import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// خدمة الإشعارات (Push / FCM).
///
/// كل النداءات الخاصة بـ Firebase مغلّفة داخل try/catch بحيث يظل التطبيق
/// قابلًا للتشغيل حتى لو لم تُضَف ملفات إعداد Firebase بعد.
class PushService {
  PushService._();

  static const _channelId = 'general';
  static const _channelName = 'إشعارات عامة';
  static const _channelDesc = 'تنبيهات عند حصولك على نقاط أو توفّر مكافأة.';

  static final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  static bool _firebaseReady = false;
  static bool _localReady = false;

  /// تهيئة الخدمة عند الإقلاع. آمنة عند غياب إعداد Firebase.
  static Future<void> init() async {
    // 1) تهيئة Firebase — إن فشلت (لا يوجد إعداد) نخرج بهدوء.
    try {
      await Firebase.initializeApp();
      _firebaseReady = true;
    } catch (_) {
      _firebaseReady = false;
      return;
    }

    // 2) تهيئة الإشعارات المحلية (قناة أندرويد).
    try {
      const androidInit =
          AndroidInitializationSettings('@mipmap/ic_launcher');
      const iosInit = DarwinInitializationSettings();
      const initSettings =
          InitializationSettings(android: androidInit, iOS: iosInit);
      await _localNotifications.initialize(initSettings);

      final androidImpl =
          _localNotifications.resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>();
      await androidImpl?.createNotificationChannel(
        const AndroidNotificationChannel(
          _channelId,
          _channelName,
          description: _channelDesc,
          importance: Importance.high,
        ),
      );
      _localReady = true;
    } catch (_) {
      _localReady = false;
    }

    // 3) مستمع الرسائل في المقدمة → عرض إشعار محلي.
    try {
      FirebaseMessaging.onMessage.listen(_showForegroundNotification);
    } catch (_) {
      // تجاهل — لا إعداد.
    }
  }

  static Future<void> _showForegroundNotification(RemoteMessage message) async {
    if (!_localReady) return;
    try {
      final notification = message.notification;
      final title = notification?.title ?? message.data['title'] as String?;
      final body = notification?.body ?? message.data['body'] as String?;
      if (title == null && body == null) return;

      await _localNotifications.show(
        message.hashCode,
        title,
        body,
        const NotificationDetails(
          android: AndroidNotificationDetails(
            _channelId,
            _channelName,
            channelDescription: _channelDesc,
            importance: Importance.high,
            priority: Priority.high,
          ),
          iOS: DarwinNotificationDetails(),
        ),
      );
    } catch (_) {
      // تجاهل.
    }
  }

  /// تسجيل توكن المستخدم الحالي في جدول device_tokens.
  /// يتابع أيضًا تحديثات التوكن (onTokenRefresh).
  static Future<void> registerForUser() async {
    if (!_firebaseReady) return;

    final client = Supabase.instance.client;
    final user = client.auth.currentUser;
    if (user == null) return;

    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();

      final token = await messaging.getToken();
      if (token != null) {
        await _upsertToken(user.id, token);
      }

      // إعادة الرفع عند تحديث التوكن.
      messaging.onTokenRefresh.listen((newToken) async {
        final current = client.auth.currentUser;
        if (current == null) return;
        await _upsertToken(current.id, newToken);
      });
    } catch (_) {
      // تجاهل — لا إعداد أو رفض الإذن.
    }
  }

  static String get _platform {
    try {
      if (Platform.isIOS) return 'ios';
      if (Platform.isAndroid) return 'android';
    } catch (_) {
      // على الويب أو منصة غير مدعومة، استخدم defaultTargetPlatform.
    }
    return defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
  }

  static Future<void> _upsertToken(String uid, String token) async {
    try {
      await Supabase.instance.client.from('device_tokens').upsert(
        {
          'user_id': uid,
          'token': token,
          'platform': _platform,
        },
        onConflict: 'user_id,token',
      );
    } catch (_) {
      // تجاهل أخطاء الشبكة/الإعداد.
    }
  }
}
