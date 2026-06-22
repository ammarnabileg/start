// record-visit: تسجيل زيارة. يفرض قاعدة زيارة واحدة في اليوم على مستوى الـ DB.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings, staffCan } from "../_shared/auth.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    if (!await rateLimit(svc, `visit:${staff.staffId}`, 150, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { user_id, campaign_id, source } = await req.json();
    if (!user_id) return badRequest("user_id مفقود");

    if (staff.role !== "merchant_owner" &&
        !(await staffCan(svc, staff.staffId, "visits", "create"))) {
      return badRequest("ليس لديك صلاحية تسجيل الزيارات", 403);
    }

    const s = await merchantSettings(svc, staff.merchantId);
    if (!s.enable_visits) return badRequest("الزيارات غير مفعّلة لهذا المتجر");

    // تسجيل الزيارة + منح أختام/مكافأة الإكمال في معاملة واحدة ذرّية: يقفل نافذة
    // فقدان النقاط (لو تعطّل التنفيذ بعد إدراج الزيارة كان القيد الفريد يمنع
    // إعادة المنح فتضيع النقاط). القيد الفريد (user, merchant, date) ما زال
    // يمنع زيارتين في اليوم.
    const { data: res, error } = await svc.rpc("record_visit_atomic", {
      p_user: user_id, p_merchant: staff.merchantId, p_branch: staff.branchId,
      p_campaign: campaign_id ?? null, p_source: source ?? "qr_scan",
      p_staff: staff.staffId, p_enable_points: s.enable_points,
      p_enable_levels: s.enable_levels,
    });
    if (error) {
      if (error.message.includes("VISIT_EXISTS")) {
        return badRequest("تم تسجيل زيارة لهذا العميل اليوم بالفعل", 409);
      }
      throw new Error(error.message);
    }
    const awarded = (res.points_awarded as number) ?? 0;

    await svc.rpc("log_merchant_activity", {
      p_merchant: staff.merchantId, p_action: "record_visit", p_entity_type: "visit",
      p_summary: awarded > 0 ? `زيارة (+${awarded} نقطة)` : "زيارة",
      p_meta: { user_id }, p_staff_id: staff.staffId,
    }).then(() => {}, () => {});

    return json({
      recorded: true, reward_ready: res.reward_ready, points_awarded: awarded,
    });
  } catch (e) {
    const m = (e as Error).message;
    if (m.includes("مصرّح") || m.includes("جلسة") || m.includes("صلاحية")) {
      return badRequest(m, 401);
    }
    return badRequest(m, 400);
  }
});
