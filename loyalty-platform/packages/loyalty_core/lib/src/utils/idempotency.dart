import 'dart:math';

/// يولّد مفتاح منع تكرار (Idempotency) فريد لكل محاولة عملية.
/// يُمرَّر مع طلبات المعاملات (add-points/redeem/spin…) فيمنع المضاعفة عند
/// إعادة الإرسال أو الضغط المزدوج.
String genIdempotencyKey() {
  final ts = DateTime.now().microsecondsSinceEpoch;
  final r = Random.secure().nextInt(1 << 32);
  return 'idem_${ts}_${r.toRadixString(16)}';
}
