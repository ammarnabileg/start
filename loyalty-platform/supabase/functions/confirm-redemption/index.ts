// confirm-redemption: الكاشير يأكّد الاستلام → الخصم الفعلي يحصل هنا.
// المحفظة تتحدّد بفرع الكاشير (مهم لنطاق النقاط المنفصل لكل فرع).
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    const { redemption_id } = await req.json();
    if (!redemption_id) return badRequest("redemption_id مفقود");

    const { data: r } = await svc.from("reward_redemptions")
      .select("id, user_id, merchant_id, reward_id, points_spent, status, expires_at")
      .eq("id", redemption_id).maybeSingle();
    if (!r) return badRequest("عملية الاستبدال غير موجودة");
    if (r.merchant_id !== staff.merchantId) return badRequest("غير مصرّح", 403);
    if (r.status !== "pending") return badRequest("العملية غير قابلة للتأكيد", 409);
    if (new Date(r.expires_at).getTime() < Date.now()) {
      await svc.from("reward_redemptions").update({ status: "expired" }).eq("id", r.id);
      return badRequest("انتهت صلاحية الكود، اطلب من العميل إعادة المحاولة", 410);
    }

    // المحفظة حسب فرع الكاشير (نطاق النقاط)
    const { data: wallet } = await svc.rpc("get_or_create_wallet", {
      p_user: r.user_id, p_merchant: staff.merchantId, p_staff_branch: staff.branchId,
    }).single();

    if (wallet.available_points < r.points_spent) {
      return badRequest("رصيد العميل في هذا الفرع غير كافٍ", 422);
    }

    // خصم من available فقط (lifetime ثابت) + خصم المخزون + تأكيد
    await svc.from("user_stores").update({
      available_points: wallet.available_points - r.points_spent,
    }).eq("id", wallet.id);

    await svc.from("points_transactions").insert({
      user_store_id: wallet.id,
      branch_id: staff.branchId,
      type: "redeem",
      points: -r.points_spent,
      staff_id: staff.staffId,
      reason: "reward_redemption",
    });

    await svc.from("reward_redemptions").update({
      status: "confirmed",
      branch_id: staff.branchId,
      staff_id: staff.staffId,
      confirmed_at: new Date().toISOString(),
    }).eq("id", r.id);

    // إنقاص المخزون لو محدود
    await svc.rpc("decrement_stock", { p_reward: r.reward_id }).then(() => {}, () => {});

    return json({
      confirmed: true,
      remaining_points: wallet.available_points - r.points_spent,
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
