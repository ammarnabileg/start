// pos-api: نقطة تكامل أنظمة الكاشير (POS). المصادقة بمفتاح API في الترويسة:
//   x-api-key: pos_live_xxx
// الأفعال: lookup / earn / visit / redeem — كلها مقيّدة بتاجر المفتاح وفرعه.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, merchantSettings } from "../_shared/auth.ts";
import { sha256Hex } from "../_shared/hash.ts";
import { withIdempotency } from "../_shared/idempotency.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();

    // مصادقة المفتاح
    const apiKey = req.headers.get("x-api-key") ?? "";
    if (!apiKey.startsWith("pos_")) return badRequest("مفتاح API مفقود", 401);
    const hash = await sha256Hex(apiKey);
    const { data: key } = await svc.from("pos_api_keys")
      .select("id, merchant_id, branch_id, active")
      .eq("key_hash", hash).maybeSingle();
    if (!key || !key.active) return badRequest("مفتاح API غير صالح", 401);
    await svc.from("pos_api_keys")
      .update({ last_used_at: new Date().toISOString() }).eq("id", key.id);

    const merchantId = key.merchant_id as string;
    const branchId = key.branch_id as string | null;

    // فرض الأهلية: المتجر متاح واشتراكه سارٍ.
    const { data: entitled } = await svc.rpc("merchant_entitled", {
      p_merchant: merchantId,
    });
    if (entitled !== true) {
      return badRequest("المتجر غير متاح حاليًا أو انتهى اشتراكه", 403);
    }
    const body = await req.json();
    const action = body.action as string;
    // مفتاح ازدواج اختياري من نظام الكاشير (نفس رقم الإيصال مثلًا).
    const idempotencyKey = body.idempotency_key as string | undefined;

    // إيجاد العميل (بالجوال أو المعرّف)
    async function findUser(): Promise<{ id: string; name: string } | null> {
      if (body.customer_id) {
        const { data } = await svc.from("users").select("id, name")
          .eq("id", body.customer_id).maybeSingle();
        return data ?? null;
      }
      if (body.phone) {
        const { data } = await svc.from("users").select("id, name")
          .eq("phone", body.phone).maybeSingle();
        return data ?? null;
      }
      return null;
    }

    const user = await findUser();
    if (!user) return badRequest("العميل غير موجود", 404);

    // المحفظة حسب فرع المفتاح ونطاق نقاط التاجر
    const { data: wallet } = await svc.rpc("get_or_create_wallet", {
      p_user: user.id, p_merchant: merchantId, p_staff_branch: branchId,
    }).single();

    if (action === "lookup") {
      let levelName: string | null = null;
      if (wallet.current_level_id) {
        const { data: l } = await svc.from("loyalty_levels")
          .select("name").eq("id", wallet.current_level_id).maybeSingle();
        levelName = l?.name ?? null;
      }
      return json({
        customer_id: user.id,
        name: user.name,
        available_points: wallet.available_points,
        lifetime_points: wallet.lifetime_points,
        level: levelName,
      });
    }

    if (action === "earn") {
      const s = await merchantSettings(svc, merchantId);
      if (!s.enable_points) return badRequest("النقاط غير مفعّلة");
      const amount = Number(body.amount ?? 0);
      let pts = body.points != null ? Number(body.points)
        : Math.round(amount * (await earnRate(svc, merchantId)));
      if (!Number.isInteger(pts) || pts <= 0) return badRequest("قيمة غير صحيحة");
      if (pts > s.max_points_per_txn) pts = s.max_points_per_txn; // سقف العملية

      // المعاملة محمية بـ idempotency (إعادة إرسال نفس الإيصال لا تضاعف النقاط).
      const idem = await withIdempotency(
        svc,
        idempotencyKey,
        { endpoint: "pos-api:earn", userId: user.id, merchantId },
        async () => {
          const newLifetime = wallet.lifetime_points + pts;
          let levelId = wallet.current_level_id;
          if (s.enable_levels) {
            const { data: lvl } = await svc.from("loyalty_levels").select("id")
              .eq("merchant_id", merchantId)
              .lte("threshold_lifetime_points", newLifetime)
              .order("threshold_lifetime_points", { ascending: false })
              .limit(1).maybeSingle();
            if (lvl) levelId = lvl.id;
          }
          await svc.from("user_stores").update({
            available_points: wallet.available_points + pts,
            lifetime_points: newLifetime,
            current_level_id: levelId,
          }).eq("id", wallet.id);
          await svc.from("points_transactions").insert({
            user_store_id: wallet.id, branch_id: branchId,
            type: "earn", points: pts, reason: "pos",
          });
          await svc.from("notifications").insert({
            user_id: user.id, type: "points",
            title: "حصلت على نقاط", body: `حصلت على ${pts} نقطة`,
            data: { merchant_id: merchantId },
          });
          return {
            earned: pts,
            available_points: wallet.available_points + pts,
            lifetime_points: newLifetime,
          };
        },
      );
      if ("conflict" in idem) return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
      return json(idem.data);
    }

    if (action === "visit") {
      const { error } = await svc.from("user_visits").insert({
        user_id: user.id, merchant_id: merchantId, branch_id: branchId,
        source: "qr_scan",
      });
      if (error && (error as { code?: string }).code === "23505") {
        return badRequest("تم تسجيل زيارة اليوم بالفعل", 409);
      }
      if (error) throw error;
      return json({ recorded: true });
    }

    if (action === "redeem") {
      const { data: reward } = await svc.from("rewards")
        .select("id, points_cost, stock_qty, active")
        .eq("id", body.reward_id).eq("merchant_id", merchantId).maybeSingle();
      if (!reward || !reward.active) return badRequest("المكافأة غير متاحة");
      if (wallet.available_points < reward.points_cost) {
        return badRequest("النقاط غير كافية", 422);
      }
      // المعاملة محمية بـ idempotency (إعادة الإرسال لا تخصم مرتين).
      const idem = await withIdempotency(
        svc,
        idempotencyKey,
        { endpoint: "pos-api:redeem", userId: user.id, merchantId },
        async () => {
          await svc.from("user_stores").update({
            available_points: wallet.available_points - reward.points_cost,
          }).eq("id", wallet.id);
          await svc.from("points_transactions").insert({
            user_store_id: wallet.id, branch_id: branchId,
            type: "redeem", points: -reward.points_cost, reason: "pos_redeem",
          });
          await svc.from("reward_redemptions").insert({
            user_id: user.id, merchant_id: merchantId, reward_id: reward.id,
            branch_id: branchId, points_spent: reward.points_cost, status: "confirmed",
            confirmed_at: new Date().toISOString(),
          });
          return {
            redeemed: true,
            remaining_points: wallet.available_points - reward.points_cost,
          };
        },
      );
      if ("conflict" in idem) return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
      return json(idem.data);
    }

    return badRequest("action غير معروف");
  } catch (e) {
    return badRequest((e as Error).message, 400);
  }
});

async function earnRate(svc: ReturnType<typeof serviceClient>, m: string): Promise<number> {
  const { data } = await svc.from("merchant_settings")
    .select("earn_rate_per_currency").eq("merchant_id", m).maybeSingle();
  return Number(data?.earn_rate_per_currency ?? 1);
}
