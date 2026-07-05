// add-points: الكاشير يضيف نقاط (earn). يفرض السقوف ويحدّث المستوى.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings, staffCan } from "../_shared/auth.ts";
import { sendPush } from "../_shared/push.ts";
import { withIdempotency } from "../_shared/idempotency.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    if (!await rateLimit(svc, `addpts:${staff.staffId}`, 150, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { user_id, points, reason, idempotency_key } = await req.json();
    const pts = Number(points);

    if (!user_id || !Number.isInteger(pts) || pts <= 0) {
      return badRequest("قيمة النقاط غير صحيحة");
    }

    // صلاحية إضافة النقاط (owner يتجاوز).
    if (staff.role !== "merchant_owner" &&
        !(await staffCan(svc, staff.staffId, "points", "create"))) {
      return badRequest("ليس لديك صلاحية إضافة النقاط", 403);
    }

    const s = await merchantSettings(svc, staff.merchantId);
    if (!s.enable_points) return badRequest("النقاط غير مفعّلة لهذا المتجر");

    // سقف العملية الواحدة
    if (pts > s.max_points_per_txn) {
      return badRequest(`الحد الأقصى للعملية ${s.max_points_per_txn} نقطة`, 422);
    }

    // السقف اليومي لكل موظف يُفحَص ويُطبَّق ذرّيًا داخل add_points_atomic: قفل
    // صف الموظف يسلسل عملياته فلا يتجاوز طلبان متزامنان السقف معًا (كان فحص
    // اقرأ-المجموع-ثم-أضِف سباقًا يسمح بالتجاوز).
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "add-points", userId: user_id, merchantId: staff.merchantId },
      async () => {
        const { data: res, error } = await svc.rpc("add_points_atomic", {
          p_user: user_id, p_merchant: staff.merchantId, p_branch: staff.branchId,
          p_staff: staff.staffId, p_points: pts, p_reason: reason ?? null,
          p_daily_cap: s.daily_points_per_staff, p_recompute_level: s.enable_levels,
        });
        if (error) throw new Error(error.message);
        const applied = res.apply;

        await svc.from("notifications").insert({
          user_id, type: "points", title: "حصلت على نقاط",
          body: `حصلت على ${pts} نقطة`, data: { merchant_id: staff.merchantId },
        });
        await sendPush(svc, [user_id], {
          title: "حصلت على نقاط", body: `حصلت على ${pts} نقطة`,
        });

        // سجل النشاط: مين منح النقاط (أفضل جهد).
        await svc.rpc("log_merchant_activity", {
          p_merchant: staff.merchantId, p_action: "grant_points",
          p_entity_type: "points", p_summary: `+${pts} نقطة`,
          p_meta: { user_id, reason: reason ?? null }, p_staff_id: staff.staffId,
        }).then(() => {}, () => {});

        return {
          available_points: applied.available_points,
          lifetime_points: applied.lifetime_points,
          level_id: applied.current_level_id,
          leveled_up: applied.leveled_up,
        };
      },
    );

    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    const m = (e as Error).message;
    if (m.includes("DAILY_CAP")) {
      return badRequest("تم تجاوز الحد اليومي المسموح للموظف", 422);
    }
    if (m.includes("مصرّح") || m.includes("جلسة") || m.includes("صلاحية")) {
      return badRequest(m, 401);
    }
    return badRequest(m, 400);
  }
});
