import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';

void main() {
  const userId = '11111111-1111-1111-1111-111111111111';
  const secret = 'super-secret-qr-key-base64==';

  group('QrToken', () {
    test('generate produces v1 payload with userId embedded', () {
      final p = QrToken.generate(userId, secret);
      final parts = p.split('.');
      expect(parts.length, 4);
      expect(parts[0], 'v1');
      expect(parts[1], userId);
      expect(parts[3].length, 16); // كود مختصر 16 حرف
    });

    test('verify accepts a freshly generated token', () {
      final now = DateTime.utc(2026, 1, 1, 12, 0, 0);
      final p = QrToken.generate(userId, secret, now: now);
      expect(QrToken.verify(p, secret, now: now), userId);
    });

    test('verify rejects a token signed with a different secret', () {
      final now = DateTime.utc(2026, 1, 1, 12, 0, 0);
      final p = QrToken.generate(userId, secret, now: now);
      expect(QrToken.verify(p, 'wrong-secret', now: now), isNull);
    });

    test('verify rejects an old screenshot (window expired)', () {
      final t0 = DateTime.utc(2026, 1, 1, 12, 0, 0);
      final p = QrToken.generate(userId, secret, now: t0);
      // بعد دقيقتين (نوافذ 30 ثانية × tolerance 1) → خارج النطاق المسموح
      final later = t0.add(const Duration(minutes: 2));
      expect(QrToken.verify(p, secret, now: later), isNull);
    });

    test('verify tolerates small clock skew (previous window)', () {
      final t0 = DateTime.utc(2026, 1, 1, 12, 0, 0);
      final p = QrToken.generate(userId, secret, now: t0);
      final skew = t0.add(const Duration(seconds: 20)); // داخل النافذة التالية
      expect(QrToken.verify(p, secret, now: skew), userId);
    });

    test('verify rejects tampered payload', () {
      final now = DateTime.utc(2026, 1, 1, 12, 0, 0);
      final p = QrToken.generate(userId, secret, now: now);
      final tampered = '${p.substring(0, p.length - 1)}X';
      expect(QrToken.verify(tampered, secret, now: now), isNull);
    });

    test('secondsRemaining is within the rotation window', () {
      final r = QrToken.secondsRemaining();
      expect(r, greaterThan(0));
      expect(r, lessThanOrEqualTo(QrToken.defaultWindowSeconds));
    });
  });
}
