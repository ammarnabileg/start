// buy-reward: العميل يشتري مكافأة بنقاطه (امتلاك فوري). يخصم النقاط ويضيف الهدية
// إلى "هداياي" (user_prizes) قابلة للاستلام عند الكاشير لاحقًا. الخصم والإضافة
// يتمّان ذرّيًا داخل purchase_reward_with_points، والـ idempotency يمنع الخصم المزدوج.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser, merchantSettings } from "../_shared/auth.ts";
import { withIdempotency } from "../_shared/idempotency.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    const { reward_id, branch_id, idempotency_key } = await req.json();
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

    // الخصم الفعلي + إنشاء الهدية يتمّان داخل الدالة (ذرّي). idempotency يمنع التكرار.
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "buy-reward", userId, merchantId: reward.merchant_id },
      async () => {
        const { data, error } = await svc.rpc("purchase_reward_with_points", {
          p_reward: reward.id,
          p_branch: branch_id ?? null,
          p_user: userId, // هوية مُتحقّقة من الـ JWT (الدالة ترفض غيرها)
        });
        if (error) throw new Error(mapError(error.message));
        return data as Record<string, unknown>;
      },
    );

    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    return badRequest((e as Error).message, 400);
  }
});

// ترجمة أخطاء الدالة إلى رسائل عربية واضحة للعميل.
function mapError(msg: string): string {
  if (msg.includes("INSUFFICIENT_POINTS")) return "نقاطك غير كافية";
  if (msg.includes("OUT_OF_STOCK")) return "نفدت الكمية";
  if (msg.includes("REWARD_UNAVAILABLE")) return "المكافأة غير متاحة";
  if (msg.includes("NOT_AUTHENTICATED")) return "جلسة غير صالحة";
  return "تعذّر إتمام الشراء، حاول مرة أخرى";
}
