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

    // نضمن وجود المحفظة (ربط العميل لو جديد)
    const { data: wallet } = await svc.rpc("get_or_create_wallet", {
      p_user: user_id, p_merchant: staff.merchantId, p_staff_branch: staff.branchId,
    }).single();

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

    // هل أكمل البطاقة؟ + منح نقاط الختم/الإكمال.
    let rewardReady = false;
    let awarded = 0;
    if (campaign_id) {
      const { data: camp } = await svc.from("visit_campaigns")
        .select("required_visits, reward_name, reward_description, reward_image_url, points_per_stamp, reward_points")
        .eq("id", campaign_id).maybeSingle();
      if (camp) {
        const { count } = await svc.from("user_visits")
          .select("id", { count: "exact", head: true })
          .eq("user_id", user_id).eq("merchant_id", staff.merchantId)
          .eq("campaign_id", campaign_id);
        const stamps = count ?? 0;
        // الإكمال عند كل دورة (عدد الأختام يقبل القسمة على المطلوب).
        rewardReady = stamps > 0 && stamps % camp.required_visits === 0;

        // منح النقاط: نقاط لكل ختم + نقاط الإكمال (تحترم تفعيل النقاط).
        if (s.enable_points && wallet) {
          awarded = (camp.points_per_stamp ?? 0) +
            (rewardReady ? (camp.reward_points ?? 0) : 0);
          if (awarded > 0) {
            const { error: applyErr } = await svc.rpc("wallet_apply", {
              p_wallet: wallet.id,
              p_available_delta: awarded,
              p_lifetime_delta: awarded,
              p_recompute_level: s.enable_levels,
            });
            if (applyErr) throw applyErr;
            await svc.from("points_transactions").insert({
              user_store_id: wallet.id, branch_id: staff.branchId,
              type: "earn", points: awarded, staff_id: staff.staffId,
              reason: "stamp",
            });
          }
        }

        if (rewardReady) {
          // إنشاء هدية قابلة للاستلام بنفس آلية الـ QR (p1) — مثل أي هدية.
          const expires = new Date(Date.now() + 30 * 24 * 3600_000);
          await svc.from("user_prizes").insert({
            user_id,
            merchant_id: staff.merchantId,
            source: "campaign",
            source_ref: campaign_id,
            title: camp.reward_name ?? "مكافأة بطاقتك",
            description: camp.reward_description ?? null,
            kind: "reward",
            branch_scope: staff.branchId,
            expires_at: expires.toISOString(),
          });
          await svc.from("notifications").insert({
            user_id, type: "reward_ready",
            title: "مكافأتك جاهزة! 🎁",
            body: `أكملت بطاقتك — استلم ${camp.reward_name} من "هداياي"`,
            data: { merchant_id: staff.merchantId, campaign_id },
          });
        }
      }
    }

    await svc.rpc("log_merchant_activity", {
      p_merchant: staff.merchantId, p_action: "record_visit", p_entity_type: "visit",
      p_summary: awarded > 0 ? `زيارة (+${awarded} نقطة)` : "زيارة",
      p_meta: { user_id }, p_staff_id: staff.staffId,
    }).then(() => {}, () => {});

    return json({ recorded: true, reward_ready: rewardReady, points_awarded: awarded });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
