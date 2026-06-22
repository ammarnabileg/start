// confirm-redemption: الكاشير يأكّد الاستلام → الخصم الفعلي يحصل هنا.
// المحفظة تتحدّد بفرع الكاشير (مهم لنطاق النقاط المنفصل لكل فرع).
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff } from "../_shared/auth.ts";
import { withIdempotency } from "../_shared/idempotency.ts";
import { verifyQr } from "../_shared/qr.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    if (!await rateLimit(svc, `confirm:${staff.staffId}`, 100, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { payload, idempotency_key } = await req.json();
    if (!payload || typeof payload !== "string") return badRequest("رمز الاستلام مفقود");
    const redemptionId = payload.split(".")[1];
    if (!redemptionId) return badRequest("رمز غير صالح");

    const { data: r } = await svc.from("reward_redemptions")
      .select("id, user_id, merchant_id, reward_id, points_spent, status, expires_at, claim_secret")
      .eq("id", redemptionId).maybeSingle();
    if (!r) return badRequest("عملية الاستبدال غير موجودة");
    // تحقّق توقيع الـ QR المتغيّر (r1) — يمنع إعادة استخدام لقطة شاشة قديمة.
    if (verifyQr(payload, r.claim_secret, 30, 1, "r1") !== redemptionId) {
      return badRequest("رمز غير صالح أو منتهي، اطلب من العميل تحديث الكود", 403);
    }
    if (r.merchant_id !== staff.merchantId) return badRequest("غير مصرّح", 403);
    if (r.status !== "pending") return badRequest("العملية غير قابلة للتأكيد", 409);
    // استهداف الفروع: المكافأة لازم تكون متاحة في فرع الكاشير.
    const { data: rAt } = await svc.rpc("entity_at_branch", {
      p_type: "reward", p_id: r.reward_id, p_branch: staff.branchId,
    });
    if (rAt === false) return badRequest("هذه المكافأة غير متاحة في فرعك", 403);
    if (new Date(r.expires_at).getTime() < Date.now()) {
      await svc.from("reward_redemptions").update({ status: "expired" }).eq("id", r.id);
      return badRequest("انتهت صلاحية الكود، اطلب من العميل إعادة المحاولة", 410);
    }

    // المعاملة محمية بـ idempotency (تأكيد مكرّر بنفس المفتاح يرجّع نفس النتيجة).
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "confirm-redemption", userId: r.user_id, merchantId: staff.merchantId },
      async () => {
        // الانتقال + الخصم + القيد + المخزون ذرّيًا (قفل صف + حارس حالة داخل الدالة)
        // — يمنع الخصم المزدوج حتى مع مفاتيح idempotency مختلفة لنفس العملية.
        const { data, error } = await svc.rpc("confirm_reward_redemption", {
          p_redemption: r.id, p_staff: staff.staffId, p_branch: staff.branchId,
        });
        if (error) throw new Error(error.message);

        await svc.rpc("log_merchant_activity", {
          p_merchant: staff.merchantId, p_action: "redeem_reward",
          p_entity_type: "reward", p_entity_id: r.reward_id,
          p_summary: "تأكيد استرداد مكافأة", p_meta: { user_id: r.user_id },
          p_staff_id: staff.staffId,
        }).then(() => {}, () => {});

        return {
          confirmed: true,
          remaining_points: (data as Record<string, unknown>).remaining_points,
        };
      },
    );

    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    return mapRedeemError(e as Error);
  }
});

// تحويل أخطاء الدالة الذرّية إلى أكواد حالة صحيحة (بدل 401 لكل خطأ).
function mapRedeemError(e: Error): Response {
  const m = e.message;
  if (m.includes("INSUFFICIENT_POINTS")) return badRequest("رصيد العميل غير كافٍ", 422);
  if (m.includes("NOT_PENDING")) return badRequest("العملية غير قابلة للتأكيد", 409);
  if (m.includes("EXPIRED")) return badRequest("انتهت صلاحية الكود", 410);
  if (m.includes("REDEMPTION_NOT_FOUND")) {
    return badRequest("عملية الاستبدال غير موجودة", 404);
  }
  if (m.includes("مصرّح") || m.includes("جلسة") || m.includes("صلاحية")) {
    return badRequest(m, 401);
  }
  return badRequest(m, 400);
}
