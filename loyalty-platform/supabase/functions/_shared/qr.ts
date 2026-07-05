// تحقّق توكن الـ QR المتغيّر — نفس خوارزمية packages/loyalty_core/.../qr_token.dart
import { createHmac } from "node:crypto";

function code(version: string, userId: string, secret: string, window: number): string {
  const h = createHmac("sha256", secret);
  // النسخة مُضمَّنة في التوقيع (مطابق لـ qr_token.dart) — يمنع إعادة استخدام
  // كود نسخة كنسخة أخرى حتى مع تطابق السرّ والنافذة.
  h.update(`${version}:${userId}:${window}`);
  // base64url مختصر (16 حرف) زي جانب الـ Dart
  return h.digest("base64url").substring(0, 16);
}

function constEq(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

/**
 * يرجّع userId لو التوكن صالح، وإلا null.
 * payload: v1.<userId>.<window>.<code>
 */
export function verifyQr(
  payload: string,
  secret: string,
  windowSeconds = 30,
  tolerance = 1,
  version = "v1",
): string | null {
  const parts = payload.split(".");
  if (parts.length !== 4 || parts[0] !== version) return null;
  const id = parts[1];
  const got = parts[3];
  const current = Math.floor(Date.now() / 1000 / windowSeconds);
  for (let w = current - tolerance; w <= current + tolerance; w++) {
    if (constEq(code(version, id, secret, w), got)) return id;
  }
  return null;
}
