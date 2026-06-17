// answer-question: العميل يجاوب سؤال التاجر → ياخد النقاط اللي حدّدها التاجر.
// كل عميل يجاوب السؤال مرة واحدة (قيد فريد). الإجابة تظهر في لوحة التاجر.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser, merchantSettings } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    const { question_id, answer_text, selected_option_ids } = await req.json();
    if (!question_id) return badRequest("question_id مفقود");

    const { data: q } = await svc.from("merchant_questions")
      .select("id, merchant_id, type, points_reward, active")
      .eq("id", question_id).maybeSingle();
    if (!q || !q.active) return badRequest("السؤال غير متاح");

    // التحقق من شكل الإجابة حسب النوع
    if (q.type === "text") {
      if (!answer_text || String(answer_text).trim() === "") {
        return badRequest("الإجابة مطلوبة");
      }
    } else {
      if (!Array.isArray(selected_option_ids) || selected_option_ids.length === 0) {
        return badRequest("اختر إجابة");
      }
      if (q.type === "single_choice" && selected_option_ids.length !== 1) {
        return badRequest("اختر إجابة واحدة فقط");
      }
      // تأكد أن الخيارات تتبع نفس السؤال
      const { count } = await svc.from("question_options")
        .select("id", { count: "exact", head: true })
        .eq("question_id", question_id).in("id", selected_option_ids);
      if ((count ?? 0) !== selected_option_ids.length) {
        return badRequest("خيار غير صالح");
      }
    }

    // العميل لازم يكون مرتبط بالتاجر — نحدّد الفرع للمحفظة:
    // ناخد أحدث محفظة للعميل عند هذا التاجر (أو نتركها للوضع المشترك).
    const { data: anyWallet } = await svc.from("user_stores")
      .select("branch_id").eq("user_id", userId)
      .eq("merchant_id", q.merchant_id)
      .order("first_linked_at", { ascending: false }).limit(1).maybeSingle();
    if (!anyWallet) return badRequest("لست مرتبطًا بهذا المتجر بعد", 409);

    // تسجيل الإجابة (القيد الفريد يمنع التكرار)
    const { data: resp, error } = await svc.from("question_responses").insert({
      question_id,
      user_id: userId,
      merchant_id: q.merchant_id,
      branch_id: anyWallet.branch_id,
      answer_text: q.type === "text" ? answer_text : null,
      selected_option_ids: q.type === "text" ? null : selected_option_ids,
      points_awarded: q.points_reward,
    }).select("id").single();

    if (error) {
      if ((error as { code?: string }).code === "23505") {
        return badRequest("لقد أجبت على هذا السؤال من قبل", 409);
      }
      throw error;
    }

    // منح النقاط عبر نفس مسار earn (يحترم نطاق النقاط + المستوى)
    let awarded = 0;
    const s = await merchantSettings(svc, q.merchant_id);
    if (q.points_reward > 0 && s.enable_points) {
      const { data: wallet } = await svc.rpc("get_or_create_wallet", {
        p_user: userId, p_merchant: q.merchant_id, p_staff_branch: anyWallet.branch_id,
      }).single();

      const newLifetime = wallet.lifetime_points + q.points_reward;
      let levelId = wallet.current_level_id;
      if (s.enable_levels) {
        const { data: lid } = await svc.rpc("level_for", {
          p_merchant: q.merchant_id,
          p_branch: wallet.branch_id,
          p_lifetime: newLifetime,
        });
        if (lid) levelId = lid as string;
      }
      await svc.from("user_stores").update({
        available_points: wallet.available_points + q.points_reward,
        lifetime_points: newLifetime,
        current_level_id: levelId,
      }).eq("id", wallet.id);
      await svc.from("points_transactions").insert({
        user_store_id: wallet.id, branch_id: anyWallet.branch_id,
        type: "earn", points: q.points_reward, reason: "question_answer",
      });
      awarded = q.points_reward;
    }

    return json({ response_id: resp.id, points_awarded: awarded });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
