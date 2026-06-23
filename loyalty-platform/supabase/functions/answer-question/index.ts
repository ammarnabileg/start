// answer-question: العميل يجاوب سؤال التاجر → ياخد النقاط اللي حدّدها التاجر.
// كل عميل يجاوب السؤال مرة واحدة (قيد فريد). الإجابة تظهر في لوحة التاجر.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser, merchantSettings } from "../_shared/auth.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    if (!await rateLimit(svc, `answer:${userId}`, 60, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
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

    // تسجيل الإجابة + منح نقاطها ذرّيًا في معاملة واحدة — يقفل نافذة فقدان
    // النقاط (لو تعطّل التنفيذ بعد الإدراج كان القيد الفريد يمنع إعادة المنح).
    const s = await merchantSettings(svc, q.merchant_id);
    const { data: res, error } = await svc.rpc("answer_question_atomic", {
      p_question: question_id, p_user: userId, p_merchant: q.merchant_id,
      p_branch: anyWallet.branch_id,
      p_answer_text: q.type === "text" ? answer_text : null,
      p_options: q.type === "text" ? null : selected_option_ids,
      p_is_text: q.type === "text",
      p_points: q.points_reward, p_enable_points: s.enable_points,
      p_enable_levels: s.enable_levels,
    });
    if (error) {
      if (error.message.includes("ALREADY_ANSWERED")) {
        return badRequest("لقد أجبت على هذا السؤال من قبل", 409);
      }
      throw new Error(error.message);
    }

    return json({ response_id: res.response_id, points_awarded: res.points_awarded });
  } catch (e) {
    const m = (e as Error).message;
    if (m.includes("مصرّح") || m.includes("جلسة") || m.includes("صلاحية")) {
      return badRequest(m, 401);
    }
    return badRequest(m, 400);
  }
});
