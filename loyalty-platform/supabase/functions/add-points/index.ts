// add-points: الكاشير يضيف نقاط (earn). يفرض السقوف ويحدّث المستوى.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings, staffCan } from "../_shared/auth.ts";
import { sendPush } from "../_shared/push.ts";
import { withIdempotency } from "../_shared/idempotency.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
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

    // السقف اليومي لكل موظف
    const since = new Date(); since.setHours(0, 0, 0, 0);
    const { data: todayTx } = await svc
      .from("points_transactions")
      .select("points")
      .eq("staff_id", staff.staffId).eq("type", "earn")
      .gte("created_at", since.toISOString());
    const todaySum = (todayTx ?? []).reduce((a, r) => a + r.points, 0);
    if (todaySum + pts > s.daily_points_per_staff) {
      return badRequest("تم تجاوز الحد اليومي المسموح للموظف", 422);
    }

    // المعاملة محمية بـ idempotency (إعادة الإرسال لا تضاعف النقاط).
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "add-points", userId: user_id, merchantId: staff.merchantId },
      async () => {
        const { data: wallet } = await svc.rpc("get_or_create_wallet", {
          p_user: user_id, p_merchant: staff.merchantId, p_staff_branch: staff.branchId,
        }).single();

        const newAvailable = wallet.available_points + pts;
        const newLifetime = wallet.lifetime_points + pts;

        let levelId = wallet.current_level_id;
        if (s.enable_levels) {
          const { data: lvl } = await svc
            .from("loyalty_levels").select("id")
            .eq("merchant_id", staff.merchantId)
            .lte("threshold_lifetime_points", newLifetime)
            .order("threshold_lifetime_points", { ascending: false })
            .limit(1).maybeSingle();
          if (lvl) levelId = lvl.id;
        }

        await svc.from("user_stores").update({
          available_points: newAvailable,
          lifetime_points: newLifetime,
          current_level_id: levelId,
        }).eq("id", wallet.id);

        await svc.from("points_transactions").insert({
          user_store_id: wallet.id, branch_id: staff.branchId,
          type: "earn", points: pts, staff_id: staff.staffId, reason: reason ?? null,
        });

        await svc.from("notifications").insert({
          user_id, type: "points", title: "حصلت على نقاط",
          body: `حصلت على ${pts} نقطة`, data: { merchant_id: staff.merchantId },
        });
        await sendPush(svc, [user_id], {
          title: "حصلت على نقاط", body: `حصلت على ${pts} نقطة`,
        });

        return {
          available_points: newAvailable,
          lifetime_points: newLifetime,
          level_id: levelId,
          leveled_up: levelId !== wallet.current_level_id,
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
