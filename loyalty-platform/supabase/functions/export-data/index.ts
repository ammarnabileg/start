// export-data: العميل يطلب نسخة من بياناته (حق الوصول/النقل — PDPL/GDPR).
// نجمع صفوف المستخدم من الجداول الأساسية ونعيدها JSON ليحفظها/يشاركها.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);

    // حدّ معدّل: طلبات تصدير قليلة (3/ساعة) — العملية ثقيلة نسبيًا.
    if (!await rateLimit(svc, `export-data:${userId}`, 3, 3600)) {
      return badRequest("لقد طلبت تصدير بياناتك عدة مرات، حاول لاحقًا", 429);
    }

    // الملف الشخصي.
    const { data: profile } = await svc
      .from("users").select("*").eq("id", userId).maybeSingle();

    // محافظ المتاجر، الزيارات، المعاملات، الهدايا، البلاغات، توكنات الأجهزة.
    const { data: wallets } = await svc
      .from("user_stores").select("*").eq("user_id", userId);
    const { data: visits } = await svc
      .from("user_visits").select("*").eq("user_id", userId);
    const { data: prizes } = await svc
      .from("user_prizes").select("*").eq("user_id", userId);
    const { data: reports } = await svc
      .from("reports").select("*").eq("user_id", userId);
    const { data: devices } = await svc
      .from("device_tokens").select("platform, created_at").eq("user_id", userId);

    // معاملات النقاط عبر محافظ المستخدم.
    const walletIds = (wallets ?? []).map((w) => w.id);
    let transactions: unknown[] = [];
    if (walletIds.length > 0) {
      const { data: tx } = await svc
        .from("points_transactions").select("*").in("user_store_id", walletIds);
      transactions = tx ?? [];
    }

    return json({
      exported_at: new Date().toISOString(),
      user_id: userId,
      profile: profile ?? null,
      wallets: wallets ?? [],
      visits: visits ?? [],
      points_transactions: transactions,
      prizes: prizes ?? [],
      reports: reports ?? [],
      devices: devices ?? [],
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
