// submit-report: العميل يرسل بلاغًا (رسالة + فيديو توثيق اختياري) عن متجر/فرع.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    const { merchant_id, branch_id, prize_id, message, video_url } =
      await req.json();
    if (!message && !video_url) return badRequest("أضف رسالة أو فيديو");

    const { data } = await svc.from("reports").insert({
      user_id: userId,
      merchant_id: merchant_id ?? null,
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
