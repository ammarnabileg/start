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
      .select("id, user_id, status, title").eq("id", prize_id).maybeSingle();
    if (!prize) return badRequest("الهدية غير موجودة", 404);
    if (prize.user_id !== userId) return badRequest("غير مصرّح", 403);
    if (prize.status !== "delivering") {
      return badRequest("لا توجد عملية تسليم جارية لهذه الهدية", 409);
    }

    if (action === "confirm") {
      await svc.from("user_prizes").update({
        status: "redeemed",
        redeemed_at: new Date().toISOString(),
      }).eq("id", prize.id);
      return json({ status: "redeemed", title: prize.title });
    }
    // إلغاء → ترجع الهدية متاحة، ونمسح بيانات الكاشير.
    await svc.from("user_prizes").update({
      status: "won",
      redeemed_by_staff: null,
      redeemed_branch: null,
    }).eq("id", prize.id);
    return json({ status: "won" });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
