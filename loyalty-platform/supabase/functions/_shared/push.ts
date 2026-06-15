// خدمة إرسال إشعارات FCM (HTTP v1) — موحّدة لكل الدوال.
// تتطلّب env: FCM_SERVICE_ACCOUNT = محتوى ملف service-account JSON.
// لو غير مُعدّة، الدالة تتجاهل الإرسال بهدوء (التطبيق يبقى شغّال).
import { SupabaseClient } from "jsr:@supabase/supabase-js@2";

interface ServiceAccount {
  client_email: string;
  private_key: string;
  project_id: string;
}

function pemToDer(pem: string): Uint8Array {
  const b64 = pem
    .replace(/-----BEGIN PRIVATE KEY-----/, "")
    .replace(/-----END PRIVATE KEY-----/, "")
    .replace(/\s+/g, "");
  const bin = atob(b64);
  return Uint8Array.from(bin, (c) => c.charCodeAt(0));
}

function b64url(data: Uint8Array | string): string {
  const bytes = typeof data === "string"
    ? new TextEncoder().encode(data)
    : data;
  return btoa(String.fromCharCode(...bytes))
    .replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

async function getAccessToken(sa: ServiceAccount): Promise<string> {
  const now = Math.floor(Date.now() / 1000);
  const header = b64url(JSON.stringify({ alg: "RS256", typ: "JWT" }));
  const claim = b64url(JSON.stringify({
    iss: sa.client_email,
    scope: "https://www.googleapis.com/auth/firebase.messaging",
    aud: "https://oauth2.googleapis.com/token",
    iat: now,
    exp: now + 3600,
  }));
  const unsigned = `${header}.${claim}`;

  const key = await crypto.subtle.importKey(
    "pkcs8",
    pemToDer(sa.private_key),
    { name: "RSASSA-PKCS1-v1_5", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sig = new Uint8Array(
    await crypto.subtle.sign("RSASSA-PKCS1-v1_5", key,
      new TextEncoder().encode(unsigned)),
  );
  const jwt = `${unsigned}.${b64url(sig)}`;

  const res = await fetch("https://oauth2.googleapis.com/token", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      grant_type: "urn:ietf:params:oauth:grant-type:jwt-bearer",
      assertion: jwt,
    }),
  });
  const data = await res.json();
  return data.access_token as string;
}

/**
 * يرسل إشعار FCM لمستخدمين (لكل أجهزتهم المفعّلة opt-in). best-effort.
 * يُسجّل الإشعار في جدول notifications أيضًا (للعرض داخل التطبيق).
 */
export async function sendPush(
  svc: SupabaseClient,
  userIds: string[],
  payload: { title: string; body?: string; data?: Record<string, string> },
): Promise<number> {
  if (userIds.length === 0) return 0;

  const raw = Deno.env.get("FCM_SERVICE_ACCOUNT");
  if (!raw) return 0; // FCM غير مُعدّ → تجاهل بهدوء

  let sa: ServiceAccount;
  try {
    sa = JSON.parse(raw);
  } catch {
    return 0;
  }

  // الأجهزة المفعّلة opt-in فقط
  const { data: users } = await svc.from("users")
    .select("id").in("id", userIds).eq("push_opt_in", true);
  const optedIn = new Set((users ?? []).map((u) => u.id));
  if (optedIn.size === 0) return 0;

  const { data: tokens } = await svc.from("device_tokens")
    .select("token, user_id").in("user_id", [...optedIn]);
  if (!tokens || tokens.length === 0) return 0;

  const accessToken = await getAccessToken(sa);
  const url =
    `https://fcm.googleapis.com/v1/projects/${sa.project_id}/messages:send`;

  let sent = 0;
  for (const t of tokens) {
    try {
      const r = await fetch(url, {
        method: "POST",
        headers: {
          "Authorization": `Bearer ${accessToken}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          message: {
            token: t.token,
            notification: { title: payload.title, body: payload.body ?? "" },
            data: payload.data ?? {},
            android: { priority: "high" },
            apns: { headers: { "apns-priority": "10" } },
          },
        }),
      });
      if (r.ok) sent++;
    } catch (_) { /* تجاهل الفشل الفردي */ }
  }
  return sent;
}
