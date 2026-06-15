import 'dart:convert';
import 'package:crypto/crypto.dart';

/// توليد توكن QR متغيّر (TOTP-style) على جهاز العميل — يشتغل أوفلاين.
///
/// الفكرة: التطبيق يولّد كودًا قصير العمر من `qr_secret` (المخزّن مشفّرًا
/// على الجهاز). السيرفر (Edge Function `verify-qr`) يعيد حساب نفس الكود
/// للنوافذ الزمنية المسموحة ويقارن. السكرين شوت القديم يفشل لأن نافذته انتهت.
///
/// نفس الخوارزمية مكتوبة في supabase/functions/_shared/qr.ts للتحقق.
class QrToken {
  QrToken._();

  /// مدة صلاحية النافذة الزمنية بالثواني (افتراضي 30 — يمكن للتاجر تغييرها).
  static const int defaultWindowSeconds = 30;

  /// يولّد الـ payload اللي بيتحط جوّه الـ QR.
  /// الشكل: `v1.<userId>.<window>.<code>`
  static String generate(
    String userId,
    String qrSecret, {
    int windowSeconds = defaultWindowSeconds,
    DateTime? now,
  }) {
    final ts = (now ?? DateTime.now()).toUtc();
    final window = ts.millisecondsSinceEpoch ~/ 1000 ~/ windowSeconds;
    final code = _code(userId, qrSecret, window);
    return 'v1.$userId.$window.$code';
  }

  /// كم تبقّى (بالثواني) قبل تجدّد التوكن — لمؤشر العدّاد الدائري في الـ UI.
  static int secondsRemaining({
    int windowSeconds = defaultWindowSeconds,
    DateTime? now,
  }) {
    final secs = (now ?? DateTime.now()).toUtc().millisecondsSinceEpoch ~/ 1000;
    return windowSeconds - (secs % windowSeconds);
  }

  /// تحقّق (يُستخدم في الاختبارات؛ التحقق الحقيقي على السيرفر).
  /// يقبل النافذة الحالية والسابقة لتحمّل اختلاف الساعة (clock skew).
  static String? verify(
    String payload,
    String qrSecret, {
    int windowSeconds = defaultWindowSeconds,
    int tolerance = 1,
    DateTime? now,
  }) {
    final parts = payload.split('.');
    if (parts.length != 4 || parts[0] != 'v1') return null;
    final userId = parts[1];
    final code = parts[3];
    final secs = (now ?? DateTime.now()).toUtc().millisecondsSinceEpoch ~/ 1000;
    final current = secs ~/ windowSeconds;
    for (var w = current - tolerance; w <= current + tolerance; w++) {
      if (_constEq(_code(userId, qrSecret, w), code)) return userId;
    }
    return null;
  }

  static String _code(String userId, String secret, int window) {
    final hmac = Hmac(sha256, utf8.encode(secret));
    final digest = hmac.convert(utf8.encode('$userId:$window'));
    // base64url مختصر (16 حرف) — كافي للأمان وقصير للـ QR.
    return base64Url.encode(digest.bytes).substring(0, 16);
  }

  /// مقارنة ثابتة الزمن لمنع timing attacks.
  static bool _constEq(String a, String b) {
    if (a.length != b.length) return false;
    var diff = 0;
    for (var i = 0; i < a.length; i++) {
      diff |= a.codeUnitAt(i) ^ b.codeUnitAt(i);
    }
    return diff == 0;
  }
}
