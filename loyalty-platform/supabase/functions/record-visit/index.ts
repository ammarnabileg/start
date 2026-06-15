// record-visit: تسجيل زيارة. يفرض قاعدة زيارة واحدة في اليوم على مستوى الـ DB.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings, staffCan } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    const { user_id, campaign_id, source } = await req.json();
    if (!user_id) return badRequest("user_id مفقود");

    if (staff.role !== "merchant_owner" &&
        !(await staffCan(svc, staff.staffId, "visits", "create"))) {
      return badRequest("ليس لديك صلاحية تسجيل الزيارات", 403);
    }

    const s = await merchantSettings(svc, staff.merchantId);
    if (!s.enable_visits) return badRequest("الزيارات غير مفعّلة لهذا المتجر");

    // نضمن وجود المحفظة (ربط العميل لو جديد)
    await svc.rpc("get_or_create_wallet", {
      p_user: user_id, p_merchant: staff.merchantId, p_staff_branch: staff.branchId,
    });

    // القيد الفريد (user, merchant, visit_date) يمنع زيارتين في اليوم
    const { error } = await svc.from("user_visits").insert({
      user_id,
      merchant_id: staff.merchantId,
      branch_id: staff.branchId,
      campaign_id: campaign_id ?? null,
      source: source ?? "qr_scan",
      scanned_by_staff_id: staff.staffId,
    });

    if (error) {
      // 23505 = unique_violation → زيارة متسجّلة بالفعل النهاردة
      if ((error as { code?: string }).code === "23505") {
        return badRequest("تم تسجيل زيارة لهذا العميل اليوم بالفعل", 409);
      }
      throw error;
    }

    // هل أكمل حملة؟ (عدّ زياراته للحملة)
    let rewardReady = false;
    if (campaign_id) {
      const { data: camp } = await svc.from("visit_campaigns")
        .select("required_visits, reward_name").eq("id", campaign_id).maybeSingle();
      if (camp) {
        const { count } = await svc.from("user_visits")
          .select("id", { count: "exact", head: true })
          .eq("user_id", user_id).eq("merchant_id", staff.merchantId)
          .eq("campaign_id", campaign_id);
        rewardReady = (count ?? 0) >= camp.required_visits;
        if (rewardReady) {
          await svc.from("notifications").insert({
            user_id, type: "reward_ready",
            title: "مكافأتك جاهزة! 🎁",
            body: `أكملت زياراتك للحصول على ${camp.reward_name}`,
            data: { merchant_id: staff.merchantId, campaign_id },
          });
        }
      }
    }

    return json({ recorded: true, reward_ready: rewardReady });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
