// proximity-hit: التطبيق يستدعيها عند دخول geofence فرع (ENTER).
// تفرض الـ cooldown (لكل عميل/فرع) على السيرفر وترسل إشعار قرب مرة واحدة فقط.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";
import { sendPush } from "../_shared/push.ts";

const COOLDOWN_HOURS = 8;

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    const { branch_id } = await req.json();
    if (!branch_id) return badRequest("branch_id مفقود");

    // موافقة العميل على إشعارات القرب
    const { data: user } = await svc.from("users")
      .select("proximity_opt_in").eq("id", userId).maybeSingle();
    if (!user?.proximity_opt_in) return json({ sent: false, reason: "opt_out" });

    const { data: branch } = await svc.from("branches")
      .select("id, merchant_id, name").eq("id", branch_id).maybeSingle();
    if (!branch) return badRequest("الفرع غير موجود");

    // العميل لازم يكون مرتبطًا بهذا التاجر
    const { count: linked } = await svc.from("user_stores")
      .select("id", { count: "exact", head: true })
      .eq("user_id", userId).eq("merchant_id", branch.merchant_id);
    if ((linked ?? 0) === 0) return json({ sent: false, reason: "not_linked" });

    // cooldown
    const { data: log } = await svc.from("proximity_notifications_log")
      .select("last_notified_at")
      .eq("user_id", userId).eq("branch_id", branch_id).maybeSingle();
    if (log?.last_notified_at) {
      const since = Date.now() - new Date(log.last_notified_at).getTime();
      if (since < COOLDOWN_HOURS * 3600_000) {
        return json({ sent: false, reason: "cooldown" });
      }
    }

    // تحديث السجل + إرسال
    await svc.from("proximity_notifications_log").upsert({
      user_id: userId, merchant_id: branch.merchant_id,
      branch_id, last_notified_at: new Date().toISOString(),
    }, { onConflict: "user_id,branch_id" });

    const title = "أنت قريب!";
    const body = `أنت قريب من ${branch.name} — تقدر تزوره وتجمّع نقاطك.`;
    await svc.from("notifications").insert({
      user_id: userId, type: "proximity", title, body,
      data: { merchant_id: branch.merchant_id, branch_id },
    });
    await sendPush(svc, [userId], {
      title, body, data: { merchant_id: branch.merchant_id, branch_id },
    });

    return json({ sent: true });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
