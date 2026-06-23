// redeem-prize: الكاشير يمسح QR الهدية المتغيّر → نتحقق من التوكن + صلاحية
// الموظف لتفعيل الهدايا + نطاق الفرع (حسب إعدادات التاجر) → نفعّل الهدية.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, staffCan } from "../_shared/auth.ts";
import { verifyQr } from "../_shared/qr.ts";
import { withIdempotency } from "../_shared/idempotency.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    if (!await rateLimit(svc, `prize:${staff.staffId}`, 100, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { payload, idempotency_key } = await req.json();
    if (!payload) return badRequest("payload مفقود");

    // صلاحية تفعيل الهدايا: علم الموظف + صلاحية الدور (prizes:redeem).
    const allowed = staff.canRedeemPrizes &&
      (staff.role === "merchant_owner" ||
        await staffCan(svc, staff.staffId, "prizes", "redeem"));
    if (!allowed) {
      return badRequest("ليس لديك صلاحية تفعيل الهدايا", 403);
    }

    // prize id من الـ payload (prefix 'p1')
    const prizeId = String(payload).split(".")[1];
    if (!prizeId) return badRequest("الرمز غير صالح");

    const { data: prize } = await svc.from("user_prizes")
      .select("id, user_id, merchant_id, title, description, kind, points_value, status, branch_scope, claim_secret, expires_at")
      .eq("id", prizeId).maybeSingle();
    if (!prize) return badRequest("الرمز غير صالح");

    // عزل التاجر
    if (prize.merchant_id !== staff.merchantId) {
      return badRequest("هذه الهدية لا تخص متجرك", 403);
    }

    // التحقق من التوكن المتغيّر (prefix 'p1') بسرّ الهدية
    const verified = verifyQr(payload, prize.claim_secret, 30, 1, "p1");
    if (verified !== prize.id) {
      return badRequest("الرمز غير صالح، اطلب من العميل تحديث الشاشة.", 422);
    }

    // الحالة
    if (prize.status !== "won") {
      return badRequest(
        prize.status === "redeemed" ? "تم تفعيل هذه الهدية مسبقًا" : "الهدية غير قابلة للتفعيل",
        409,
      );
    }
    if (prize.expires_at && new Date(prize.expires_at).getTime() < Date.now()) {
      await svc.from("user_prizes").update({ status: "expired" }).eq("id", prize.id);
      return badRequest("انتهت صلاحية الهدية", 410);
    }

    // نطاق الفرع: لو الهدية مقيّدة بفرع، لازم الكاشير من نفس الفرع.
    if (prize.branch_scope && prize.branch_scope !== staff.branchId) {
      return badRequest("هذه الهدية تُفعَّل في فرع آخر فقط", 403);
    }

    // المعاملة محمية بـ idempotency (تفعيل مكرّر لا يُسجَّل مرتين).
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "redeem-prize", userId: prize.user_id, merchantId: staff.merchantId },
      async () => {
        // بدء التسليم: انتقال ذرّي won→delivering (eq على الحالة يمنع مسحَين
        // متزامنين من إنشاء تسليمَين/إشعارَين لنفس الهدية).
        const { data: started } = await svc.from("user_prizes").update({
          status: "delivering",
          redeemed_by_staff: staff.staffId,
          redeemed_branch: staff.branchId,
        }).eq("id", prize.id).eq("status", "won").select("id").maybeSingle();
        if (!started) {
          return { delivering: false, already: true, title: prize.title };
        }

        // إشعار العميل ليؤكّد الاستلام من شاشته (تتحدّث لحظيًا أيضًا).
        await svc.from("notifications").insert({
          user_id: prize.user_id, type: "prize_delivering",
          title: "بانتظار تأكيدك",
          body: `يتم تسليمك: ${prize.title}`,
          data: { merchant_id: prize.merchant_id, prize_id: prize.id },
        });

        await svc.rpc("log_merchant_activity", {
          p_merchant: staff.merchantId, p_action: "redeem_prize",
          p_entity_type: "prize", p_entity_id: prize.id, p_summary: prize.title,
          p_meta: { user_id: prize.user_id }, p_staff_id: staff.staffId,
        }).then(() => {}, () => {});

        return {
          delivering: true,
          title: prize.title,
          kind: prize.kind,
          description: prize.description ?? null,
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
