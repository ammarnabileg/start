// apply-coupon: الكاشير يطبّق كوبونًا لعميل — يتحقق من الصلاحية والحدود ويسجّل الاستخدام.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings } from "../_shared/auth.ts";
import { withIdempotency } from "../_shared/idempotency.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    if (!await rateLimit(svc, `coupon:${staff.staffId}`, 100, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { user_id, code, idempotency_key } = await req.json();
    if (!user_id || !code) return badRequest("user_id و code مطلوبان");

    const s = await merchantSettings(svc, staff.merchantId);
    if (!s.enable_coupons) return badRequest("الكوبونات غير مفعّلة");

    const { data: coupon } = await svc.from("coupons")
      .select("id, type, value, valid_from, valid_to, usage_limit, per_user_limit, active")
      .eq("merchant_id", staff.merchantId).eq("code", code).maybeSingle();
    if (!coupon || !coupon.active) return badRequest("كوبون غير صالح");
    // استهداف الفروع: الكوبون لازم يكون متاحًا في فرع الكاشير.
    const { data: cAt } = await svc.rpc("entity_at_branch", {
      p_type: "coupon", p_id: coupon.id, p_branch: staff.branchId,
    });
    if (cAt === false) return badRequest("هذا الكوبون غير متاح في فرعك", 403);

    const now = Date.now();
    if (coupon.valid_from && new Date(coupon.valid_from).getTime() > now) {
      return badRequest("الكوبون لم يبدأ بعد", 422);
    }
    if (coupon.valid_to && new Date(coupon.valid_to).getTime() < now) {
      return badRequest("انتهت صلاحية الكوبون", 410);
    }

    // الحدود
    if (coupon.usage_limit != null) {
      const { count } = await svc.from("coupon_redemptions")
        .select("id", { count: "exact", head: true }).eq("coupon_id", coupon.id);
      if ((count ?? 0) >= coupon.usage_limit) {
        return badRequest("تم استنفاد استخدامات الكوبون", 409);
      }
    }
    if (coupon.per_user_limit != null) {
      const { count } = await svc.from("coupon_redemptions")
        .select("id", { count: "exact", head: true })
        .eq("coupon_id", coupon.id).eq("user_id", user_id);
      if ((count ?? 0) >= coupon.per_user_limit) {
        return badRequest("استخدمت هذا الكوبون من قبل", 409);
      }
    }

    // المعاملة محمية بـ idempotency (تطبيق مكرّر لا يُسجَّل استخدامين).
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "apply-coupon", userId: user_id, merchantId: staff.merchantId },
      async () => {
        await svc.from("coupon_redemptions").insert({
          coupon_id: coupon.id, user_id, staff_id: staff.staffId,
        });

        await svc.rpc("log_merchant_activity", {
          p_merchant: staff.merchantId, p_action: "apply_coupon",
          p_entity_type: "coupon", p_entity_id: coupon.id, p_summary: "تطبيق كوبون",
          p_meta: { user_id, type: coupon.type, value: coupon.value },
          p_staff_id: staff.staffId,
        }).then(() => {}, () => {});

        return {
          applied: true,
          type: coupon.type, // percent / fixed / free_item
          value: coupon.value,
        };
      },
    );

    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
