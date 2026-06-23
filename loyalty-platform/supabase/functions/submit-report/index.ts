// submit-report: العميل يرسل بلاغًا (رسالة + فيديو توثيق اختياري) عن متجر/فرع.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";
import { rateLimit } from "../_shared/ratelimit.ts";
import { withIdempotency } from "../_shared/idempotency.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);

    // حدّ معدّل: 5 بلاغات/ساعة لكل مستخدم (منع السبام).
    if (!await rateLimit(svc, `submit-report:${userId}`, 5, 3600)) {
      return badRequest("لقد أرسلت بلاغات كثيرة، حاول لاحقًا", 429);
    }

    const {
      merchant_id, branch_id, prize_id, message, video_url,
      subject_type, subject_id, subject_label, idempotency_key,
    } = await req.json();
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

    // محمي بـ idempotency: إعادة الإرسال بنفس المفتاح لا تنشئ بلاغًا مكرّرًا.
    const idem = await withIdempotency(
      svc, idempotency_key, { endpoint: "submit-report", userId, merchantId: m },
      async () => {
        const { data } = await svc.from("reports").insert({
          user_id: userId,
          merchant_id: m,
          branch_id: branch_id ?? null,
          prize_id: prize_id ?? null,
          message: message ?? null,
          video_url: video_url ?? null,
          subject_type: subject_type ?? null,
          subject_id: subject_id ?? null,
          subject_label: subject_label ?? null,
        }).select("id").single();

        // رسالة الافتتاح في الـthread = البلاغ نفسه (أول رسالة من العميل).
        const { data: me } = await svc.from("users")
          .select("name").eq("id", userId).maybeSingle();
        await svc.from("report_messages").insert({
          report_id: data.id,
          sender_role: "customer",
          sender_user_id: userId,
          sender_name: me?.name ?? null,
          body: (message && String(message).trim()) || "(بلاغ بدون نص)",
          attachment_url: video_url ?? null,
        });

        return { id: data.id, submitted: true };
      },
    );
    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    const msg = (e as Error).message;
    if (msg.includes("مصرّح") || msg.includes("جلسة") || msg.includes("صلاحية")) {
      return badRequest(msg, 401);
    }
    return badRequest(msg, 400);
  }
});
