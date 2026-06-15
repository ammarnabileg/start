// apply-coupon: الكاشير يطبّق كوبونًا لعميل — يتحقق من الصلاحية والحدود ويسجّل الاستخدام.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    const { user_id, code } = await req.json();
    if (!user_id || !code) return badRequest("user_id و code مطلوبان");

    const s = await merchantSettings(svc, staff.merchantId);
    if (!s.enable_coupons) return badRequest("الكوبونات غير مفعّلة");

    const { data: coupon } = await svc.from("coupons")
      .select("id, type, value, valid_from, valid_to, usage_limit, per_user_limit, active")
      .eq("merchant_id", staff.merchantId).eq("code", code).maybeSingle();
    if (!coupon || !coupon.active) return badRequest("كوبون غير صالح");

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

    await svc.from("coupon_redemptions").insert({
      coupon_id: coupon.id, user_id, staff_id: staff.staffId,
    });

    return json({
      applied: true,
      type: coupon.type, // percent / fixed / free_item
      value: coupon.value,
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
