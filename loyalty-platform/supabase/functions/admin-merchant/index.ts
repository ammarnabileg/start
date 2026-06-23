// admin-merchant: مالك المنصّة يدير حالة التجار (موافقة/رفض/تعليق/إعادة تفعيل).
// الموافقة transactional: حالة + إعدادات + أدوار افتراضية + اشتراك تجريبي.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient } from "../_shared/auth.ts";
import { requireSuperAdmin, audit } from "../_shared/admin.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const adminId = await requireSuperAdmin(req, svc);
    const { action, merchant_id, reason, trial_days } = await req.json();
    if (!merchant_id || !action) return badRequest("action و merchant_id مطلوبان");

    const { data: m } = await svc.from("merchants")
      .select("id, status").eq("id", merchant_id).maybeSingle();
    if (!m) return badRequest("المتجر غير موجود", 404);

    switch (action) {
      case "approve": {
        await svc.from("merchants")
          .update({ status: "approved", approved_at: new Date().toISOString() })
          .eq("id", merchant_id);
        // إعدادات افتراضية + أدوار + اشتراك تجريبي (لو مش موجودين).
        await svc.from("merchant_settings")
          .upsert({ merchant_id }, { onConflict: "merchant_id" });
        await svc.rpc("seed_default_roles", { p_merchant: merchant_id });
        const { count } = await svc.from("subscriptions")
          .select("id", { count: "exact", head: true }).eq("merchant_id", merchant_id);
        if ((count ?? 0) === 0) {
          const days = Number(trial_days ?? 30);
          await svc.from("subscriptions").insert({
            merchant_id, plan: "trial", status: "trial",
            trial_ends_at: new Date(Date.now() + days * 86400_000).toISOString(),
          });
        }
        break;
      }
      case "reject":
        await svc.from("merchants").update({ status: "rejected" }).eq("id", merchant_id);
        break;
      case "suspend":
        await svc.from("merchants").update({ status: "suspended" }).eq("id", merchant_id);
        break;
      case "reactivate":
        await svc.from("merchants").update({ status: "approved" }).eq("id", merchant_id);
        break;
      default:
        return badRequest("action غير معروف");
    }

    await audit(svc, {
      actorId: adminId, actorRole: "super_admin",
      action: `merchant_${action}`, entity: "merchant", entityId: merchant_id,
      merchantId: merchant_id, details: { reason: reason ?? null, from: m.status },
    });

    return json({ ok: true, action, merchant_id });
  } catch (e) {
    return badRequest((e as Error).message, 403);
  }
});
