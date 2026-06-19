// verify-qr: الكاشير يمسح كود العميل → نتحقق من التوكن المتغيّر،
// نربط العميل بالتاجر تلقائيًا (حسب نطاق النقاط)، ونرجّع ملف العميل.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings } from "../_shared/auth.ts";
import { verifyQr } from "../_shared/qr.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    if (!await rateLimit(svc, `verify:${staff.staffId}`, 200, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { payload } = await req.json();
    if (!payload) return badRequest("payload مفقود");

    // userId من الـ payload عشان نجيب الـ secret، ثم نتحقق منه فعليًا
    const claimedUserId = String(payload).split(".")[1];
    if (!claimedUserId) return badRequest("الرمز غير صالح");

    const { data: user } = await svc
      .from("users")
      .select("id, name, phone, qr_secret, avatar_url")
      .eq("id", claimedUserId)
      .maybeSingle();
    if (!user) {
      await svc.rpc("log_merchant_activity", {
        p_merchant: staff.merchantId, p_action: "qr_failed", p_entity_type: "scan",
        p_summary: "رمز غير صالح (مستخدم غير معروف)", p_meta: { claimed_user: claimedUserId },
        p_staff_id: staff.staffId,
      }).then(() => {}, () => {});
      return badRequest("الرمز غير صالح");
    }

    const settings = await merchantSettings(svc, staff.merchantId);
    const verifiedUserId = verifyQr(payload, user.qr_secret, settings.qr_rotation_seconds);
    if (verifiedUserId !== user.id) {
      await svc.rpc("log_merchant_activity", {
        p_merchant: staff.merchantId, p_action: "qr_failed", p_entity_type: "scan",
        p_summary: "رمز منتهٍ أو غير صالح", p_meta: { user_id: user.id },
        p_staff_id: staff.staffId,
      }).then(() => {}, () => {});
      return badRequest("الرمز غير صالح، اطلب من العميل تحديث الشاشة.", 422);
    }

    // ربط/إنشاء المحفظة حسب نطاق النقاط (مشترك أو لكل فرع)
    const { data: wallet } = await svc.rpc("get_or_create_wallet", {
      p_user: user.id,
      p_merchant: staff.merchantId,
      p_staff_branch: staff.branchId,
    }).single();

    const isNew =
      new Date(wallet.first_linked_at).getTime() > Date.now() - 5000;

    // المستوى الحالي
    let levelName: string | null = null;
    if (wallet.current_level_id) {
      const { data: lvl } = await svc
        .from("loyalty_levels").select("name")
        .eq("id", wallet.current_level_id).maybeSingle();
      levelName = lvl?.name ?? null;
    }

    // هل سجّل زيارة النهاردة؟
    const today = new Date().toISOString().slice(0, 10);
    const { count: visitedToday } = await svc
      .from("user_visits")
      .select("id", { count: "exact", head: true })
      .eq("user_id", user.id).eq("merchant_id", staff.merchantId)
      .eq("visit_date", today);

    return json({
      user: { id: user.id, name: user.name, avatar_url: user.avatar_url },
      merchant_id: staff.merchantId,
      wallet_id: wallet.id,
      branch_id: wallet.branch_id,
      available_points: wallet.available_points,
      lifetime_points: wallet.lifetime_points,
      level_name: levelName,
      visited_today: (visitedToday ?? 0) > 0,
      is_new_customer: isNew,
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
