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
      // انتقال ذرّي: فقط الطلب الذي يحوّل delivering→redeemed يصرف النقاط.
      // يمنع سَكّ النقاط عبر تأكيدات متزامنة (eq على الحالة = حارس ذرّي).
      const { data: flipped } = await svc.from("user_prizes").update({
        status: "redeemed",
        redeemed_at: new Date().toISOString(),
      }).eq("id", prize.id).eq("status", "delivering")
        .select("id, kind, points_value, redeemed_branch").maybeSingle();
      if (!flipped) {
        return badRequest("تم تأكيد استلام هذه الهدية بالفعل", 409);
      }

      // هدية من نوع "نقاط" → تُضاف فعليًا لرصيد العميل عند التأكيد (مرة واحدة).
      if (flipped.kind === "points" && (flipped.points_value ?? 0) > 0) {
        const { data: wallet } = await svc.rpc("get_or_create_wallet", {
          p_user: userId,
          p_merchant: prize.merchant_id,
          p_staff_branch: flipped.redeemed_branch,
        }).single();
        if (wallet) {
          const pts = flipped.points_value as number;
          const { error: applyErr } = await svc.rpc("wallet_apply", {
            p_wallet: wallet.id,
            p_available_delta: pts,
            p_lifetime_delta: pts,
            p_recompute_level: true,
          });
          if (applyErr) throw applyErr;
          await svc.from("points_transactions").insert({
            user_store_id: wallet.id, branch_id: wallet.branch_id,
            type: "earn", points: pts, reason: "prize_points",
          });
        }
      }
      return json({ status: "redeemed", title: prize.title });
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
