// redeem-prize: الكاشير يمسح QR الهدية المتغيّر → نتحقق من التوكن + صلاحية
// الموظف لتفعيل الهدايا + نطاق الفرع (حسب إعدادات التاجر) → نفعّل الهدية.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, staffCan } from "../_shared/auth.ts";
import { verifyQr } from "../_shared/qr.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    const { payload } = await req.json();
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
      .select("id, user_id, merchant_id, title, kind, points_value, status, branch_scope, claim_secret, expires_at")
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

    // تفعيل
    await svc.from("user_prizes").update({
      status: "redeemed",
      redeemed_at: new Date().toISOString(),
      redeemed_by_staff: staff.staffId,
      redeemed_branch: staff.branchId,
    }).eq("id", prize.id);

    // إشعار العميل
    await svc.from("notifications").insert({
      user_id: prize.user_id, type: "prize_redeemed",
      title: "تم استلام هديتك",
      body: prize.title,
      data: { merchant_id: prize.merchant_id, prize_id: prize.id },
    });

    return json({
      redeemed: true,
      title: prize.title,
      kind: prize.kind,
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
