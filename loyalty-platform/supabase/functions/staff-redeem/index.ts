// staff-redeem: الكاشير يستبدل مكافأة للعميل مباشرة (بعد المسح).
// يحترم الأمان: لو require_redemption_confirm والمكافأة فوق العتبة → يرفض
// ويطلب أن يبدأ العميل الاستبدال من تطبيقه (تأكيد الطرفين).
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings, staffCan } from "../_shared/auth.ts";
import { withIdempotency } from "../_shared/idempotency.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    if (!await rateLimit(svc, `redeem:${staff.staffId}`, 100, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { user_id, reward_id, idempotency_key } = await req.json();
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
    // استهداف الفروع: المكافأة لازم تكون متاحة في فرع الكاشير.
    const { data: rAt } = await svc.rpc("entity_at_branch", {
      p_type: "reward", p_id: reward.id, p_branch: staff.branchId,
    });
    if (rAt === false) return badRequest("هذه المكافأة غير متاحة في فرعك", 403);

    const s = await merchantSettings(svc, staff.merchantId);
    // تأكيد الطرفين للمكافآت القيّمة → اطلب من العميل البدء من تطبيقه.
    if (s.require_redemption_confirm &&
        reward.points_cost >= s.redemption_confirm_threshold) {
      return badRequest(
        "هذه المكافأة تتطلّب تأكيد العميل — اطلب منه بدء الاستبدال من تطبيقه ثم امسح الرمز.",
        409,
      );
    }

    // المعاملة محمية بـ idempotency (إعادة الإرسال بنفس المفتاح لا تخصم مرتين).
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "staff-redeem", userId: user_id, merchantId: staff.merchantId },
      async () => {
        // الخصم + قيد الاستبدال + إنقاص المخزون ذرّيًا (قفل صف داخل الدالة).
        const { data, error } = await svc.rpc("staff_redeem_reward", {
          p_user: user_id, p_reward: reward.id,
          p_staff: staff.staffId, p_branch: staff.branchId,
        });
        if (error) throw new Error(error.message);

        await svc.rpc("log_merchant_activity", {
          p_merchant: staff.merchantId, p_action: "redeem_reward",
          p_entity_type: "reward", p_entity_id: reward.id, p_summary: reward.name,
          p_meta: { user_id }, p_staff_id: staff.staffId,
        }).then(() => {}, () => {});

        const res = data as Record<string, unknown>;
        return {
          redeemed: true,
          reward_name: reward.name,
          remaining_points: res.remaining_points,
        };
      },
    );

    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    const m = (e as Error).message;
    if (m.includes("INSUFFICIENT_POINTS")) return badRequest("نقاط العميل غير كافية", 422);
    if (m.includes("OUT_OF_STOCK")) return badRequest("نفدت الكمية", 409);
    if (m.includes("REWARD_UNAVAILABLE")) return badRequest("المكافأة غير متاحة", 409);
    if (m.includes("مصرّح") || m.includes("جلسة") || m.includes("صلاحية")) {
      return badRequest(m, 401);
    }
    return badRequest(m, 400);
  }
});
