// staff-redeem: الكاشير يستبدل مكافأة للعميل مباشرة (بعد المسح).
// يحترم الأمان: لو require_redemption_confirm والمكافأة فوق العتبة → يرفض
// ويطلب أن يبدأ العميل الاستبدال من تطبيقه (تأكيد الطرفين).
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings, staffCan } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    const { user_id, reward_id } = await req.json();
    if (!user_id || !reward_id) return badRequest("user_id و reward_id مطلوبان");

    // صلاحية الاستبدال (owner يتجاوز).
    if (staff.role !== "merchant_owner" &&
        !(await staffCan(svc, staff.staffId, "rewards", "edit")) &&
        !(await staffCan(svc, staff.staffId, "prizes", "redeem"))) {
      return badRequest("ليس لديك صلاحية الاستبدال", 403);
    }

    const { data: reward } = await svc.from("rewards")
      .select("id, name, points_cost, stock_qty, active")
      .eq("id", reward_id).eq("merchant_id", staff.merchantId).maybeSingle();
    if (!reward || !reward.active) return badRequest("المكافأة غير متاحة");
    if (reward.stock_qty !== null && reward.stock_qty <= 0) {
      return badRequest("نفدت الكمية", 409);
    }

    const s = await merchantSettings(svc, staff.merchantId);
    // تأكيد الطرفين للمكافآت القيّمة → اطلب من العميل البدء من تطبيقه.
    if (s.require_redemption_confirm &&
        reward.points_cost >= s.redemption_confirm_threshold) {
      return badRequest(
        "هذه المكافأة تتطلّب تأكيد العميل — اطلب منه بدء الاستبدال من تطبيقه ثم امسح الرمز.",
        409,
      );
    }

    const { data: wallet } = await svc.rpc("get_or_create_wallet", {
      p_user: user_id, p_merchant: staff.merchantId, p_staff_branch: staff.branchId,
    }).single();
    if (wallet.available_points < reward.points_cost) {
      return badRequest("نقاط العميل غير كافية", 422);
    }

    await svc.from("user_stores").update({
      available_points: wallet.available_points - reward.points_cost,
    }).eq("id", wallet.id);
    await svc.from("points_transactions").insert({
      user_store_id: wallet.id, branch_id: staff.branchId,
      type: "redeem", points: -reward.points_cost, staff_id: staff.staffId,
      reason: "reward_redemption",
    });
    await svc.from("reward_redemptions").insert({
      user_id, merchant_id: staff.merchantId, reward_id: reward.id,
      branch_id: staff.branchId, points_spent: reward.points_cost,
      staff_id: staff.staffId, status: "confirmed",
      confirmed_at: new Date().toISOString(),
    });
    await svc.rpc("decrement_stock", { p_reward: reward.id }).then(() => {}, () => {});

    return json({
      redeemed: true,
      reward_name: reward.name,
      remaining_points: wallet.available_points - reward.points_cost,
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
