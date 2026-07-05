// confirm-prize: العميل يؤكّد استلام الهدية (موافق) أو يلغي التسليم (إلغاء)
// بعد ما يبدأ الكاشير التسليم (status = 'delivering').
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    const { prize_id, action } = await req.json();
    if (!prize_id || (action !== "confirm" && action !== "cancel")) {
      return badRequest("بيانات غير صحيحة");
    }

    const { data: prize } = await svc.from("user_prizes")
      .select("id, user_id, status, title, kind, points_value, merchant_id, redeemed_branch")
      .eq("id", prize_id).maybeSingle();
    if (!prize) return badRequest("الهدية غير موجودة", 404);
    if (prize.user_id !== userId) return badRequest("غير مصرّح", 403);
    if (prize.status !== "delivering") {
      return badRequest("لا توجد عملية تسليم جارية لهذه الهدية", 409);
    }

    if (action === "confirm") {
      // قلب الحالة + صرف نقاط kind='points' ذرّيًا في معاملة واحدة (قفل صف داخل
      // الدالة) — يمنع سَكّ النقاط من تأكيدات متزامنة وفقدان القيد عند التعطّل.
      const { data, error } = await svc.rpc("confirm_prize_collection", {
        p_prize: prize.id, p_user: userId,
      });
      if (error) {
        const m = error.message;
        if (m.includes("NOT_DELIVERING")) {
          return badRequest("تم تأكيد استلام هذه الهدية بالفعل", 409);
        }
        if (m.includes("NOT_OWNER")) return badRequest("غير مصرّح", 403);
        if (m.includes("PRIZE_NOT_FOUND")) return badRequest("الهدية غير موجودة", 404);
        throw new Error(m);
      }
      return json(data ?? { status: "redeemed", title: prize.title });
    }
    // إلغاء → ترجع الهدية متاحة، ونمسح بيانات الكاشير (مشروط بالحالة).
    const { data: reverted } = await svc.from("user_prizes").update({
      status: "won",
      redeemed_by_staff: null,
      redeemed_branch: null,
    }).eq("id", prize.id).eq("status", "delivering").select("id").maybeSingle();
    if (!reverted) {
      return badRequest("لا توجد عملية تسليم جارية لهذه الهدية", 409);
    }
    return json({ status: "won" });
  } catch (e) {
    return badRequest((e as Error).message, 400);
  }
});
