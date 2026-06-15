// redeem-reward: العميل يبدأ الاستبدال → ننشئ كود استلام مؤقت (pending).
// الخصم الفعلي ما بيحصلش هنا — بيحصل في confirm-redemption لما الكاشير يأكّد.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser, merchantSettings } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    const { reward_id } = await req.json();
    if (!reward_id) return badRequest("reward_id مفقود");

    const { data: reward } = await svc.from("rewards")
      .select("id, merchant_id, name, points_cost, stock_qty, active")
      .eq("id", reward_id).maybeSingle();
    if (!reward || !reward.active) return badRequest("المكافأة غير متاحة");
    if (reward.stock_qty !== null && reward.stock_qty <= 0) {
      return badRequest("نفدت الكمية", 409);
    }

    const s = await merchantSettings(svc, reward.merchant_id);
    if (!s.enable_rewards) return badRequest("المكافآت غير مفعّلة");

    // فحص مبدئي: عند العميل أي محفظة عند هذا التاجر فيها رصيد كافٍ؟
    const { data: wallets } = await svc.from("user_stores")
      .select("available_points")
      .eq("user_id", userId).eq("merchant_id", reward.merchant_id);
    const canAfford = (wallets ?? []).some(
      (w) => w.available_points >= reward.points_cost,
    );
    if (!canAfford) return badRequest("النقاط غير كافية", 422);

    const expires = new Date(Date.now() + s.redemption_window_minutes * 60_000);
    const { data: redemption } = await svc.from("reward_redemptions").insert({
      user_id: userId,
      merchant_id: reward.merchant_id,
      reward_id: reward.id,
      points_spent: reward.points_cost,
      status: "pending",
      expires_at: expires.toISOString(),
    }).select("id").single();

    return json({
      redemption_id: redemption.id,
      reward_name: reward.name,
      points_cost: reward.points_cost,
      expires_at: expires.toISOString(),
      requires_confirmation: true, // العميل يعرض الكود والكاشير يأكّد
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
