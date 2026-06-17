// submit-report: العميل يرسل بلاغًا (رسالة + فيديو توثيق اختياري) عن متجر/فرع.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);

    // حدّ معدّل: 5 بلاغات/ساعة لكل مستخدم (منع السبام).
    if (!await rateLimit(svc, `submit-report:${userId}`, 5, 3600)) {
      return badRequest("لقد أرسلت بلاغات كثيرة، حاول لاحقًا", 429);
    }

    const { merchant_id, branch_id, prize_id, message, video_url } =
      await req.json();
    if (!message && !video_url) return badRequest("أضف رسالة أو فيديو");
    if (message && String(message).length > 2000) {
      return badRequest("الرسالة طويلة جدًا");
    }
    if (video_url &&
        (typeof video_url !== "string" || !video_url.startsWith("https://") ||
         video_url.length > 1000)) {
      return badRequest("رابط الفيديو غير صالح");
    }

    // التحقق من الملكية: الهدية المُبلّغ عنها لازم تخصّ العميل، ونشتقّ منها التاجر.
    let m: string | null = merchant_id ?? null;
    if (prize_id) {
      const { data: prize } = await svc.from("user_prizes")
        .select("merchant_id, user_id").eq("id", prize_id).maybeSingle();
      if (!prize || prize.user_id !== userId) {
        return badRequest("هدية غير صالحة", 403);
      }
      m = prize.merchant_id as string;
    } else if (m) {
      // بلاغ عن متجر بدون هدية → لازم تكون للعميل علاقة (محفظة) به.
      const { data: rel } = await svc.from("user_stores")
        .select("id").eq("user_id", userId).eq("merchant_id", m).maybeSingle();
      if (!rel) return badRequest("لا علاقة لك بهذا المتجر", 403);
    }

    const { data } = await svc.from("reports").insert({
      user_id: userId,
      merchant_id: m,
      branch_id: branch_id ?? null,
      prize_id: prize_id ?? null,
      message: message ?? null,
      video_url: video_url ?? null,
    }).select("id").single();

    return json({ id: data.id, submitted: true });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
