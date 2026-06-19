// notify-report-reply: يرسل Push للطرف الآخر عند ردّ جديد في محادثة بلاغ.
// الإشعار داخل التطبيق يُكتب في جدول notifications (عبر RPC/اللوحة)؛ هذه الدالة
// مسؤولة عن الدفع (Push) فقط. المُرسِل لازم يكون طرفًا في البلاغ (منعًا للإساءة).
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";
import { sendPush } from "../_shared/push.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const uid = await requireUser(req, svc);

    const { report_id } = await req.json();
    if (!report_id) return badRequest("report_id required");

    const { data: report } = await svc.from("reports")
      .select("user_id, merchant_id").eq("id", report_id).maybeSingle();
    if (!report) return badRequest("report not found", 404);

    const isOwner = report.user_id === uid;
    let isStaff = false;
    if (!isOwner && report.merchant_id) {
      const { data: st } = await svc.from("merchant_staff")
        .select("id").eq("merchant_id", report.merchant_id)
        .eq("user_id", uid).eq("status", "active").maybeSingle();
      isStaff = !!st;
    }
    // المُرسِل لازم يكون صاحب البلاغ أو موظّفًا نشطًا في متجره.
    if (!isOwner && !isStaff) return badRequest("forbidden", 403);

    let recipients: string[] = [];
    let title = "", body = "";
    if (isOwner) {
      // العميل ردّ → بلّغ موظّفي المتجر النشطين.
      if (report.merchant_id) {
        const { data: staff } = await svc.from("merchant_staff")
          .select("user_id").eq("merchant_id", report.merchant_id)
          .eq("status", "active").not("user_id", "is", null);
        recipients = [
          ...new Set((staff ?? [])
            .map((s) => s.user_id as string)
            .filter((x) => !!x)),
        ];
      }
      title = "رد جديد على بلاغ";
      body = "ردّ العميل على بلاغه";
    } else {
      // موظّف المتجر ردّ → بلّغ صاحب البلاغ.
      recipients = [report.user_id as string];
      title = "رد جديد على بلاغك";
      body = "لديك رد جديد على بلاغك من المتجر";
    }

    const sent = await sendPush(svc, recipients, {
      title,
      body,
      data: { report_id: String(report_id), type: "report_reply" },
    });
    return json({ sent });
  } catch (e) {
    return badRequest((e as Error).message, 400);
  }
});
