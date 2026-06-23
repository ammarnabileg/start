// admin-push: إرسال إشعار FCM من لوحة الأدمن لكل المستخدمين أو لقائمة (user_ids).
// المصادقة: سر مشترك ADMIN_PUSH_SECRET (يرسله الباك‑إند في Authorization: Bearer).
// الإشعار داخل التطبيق تكتبه اللوحة مباشرةً في جدول notifications؛ هذه الدالة
// مسؤولة عن الدفع (Push) فقط عبر sendPush المشتركة.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient } from "../_shared/auth.ts";
import { sendPush } from "../_shared/push.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const secret = Deno.env.get("ADMIN_PUSH_SECRET");
    const auth = req.headers.get("Authorization") ?? "";
    if (!secret || auth !== `Bearer ${secret}`) return badRequest("unauthorized", 401);

    const { title, body, audience, user_ids } = await req.json();
    if (!title) return badRequest("title required");

    const svc = serviceClient();
    let ids: string[] = [];
    if (audience === "all") {
      const { data } = await svc.from("users").select("id");
      ids = (data ?? []).map((u: { id: string }) => u.id);
    } else if (Array.isArray(user_ids)) {
      ids = user_ids.filter((x) => typeof x === "string");
    }
    if (ids.length === 0) return json({ sent: 0, targets: 0 });

    const sent = await sendPush(svc, ids, {
      title,
      body: body ?? "",
      data: { source: "admin" },
    });
    return json({ sent, targets: ids.length });
  } catch (e) {
    return badRequest((e as Error).message, 400);
  }
});
