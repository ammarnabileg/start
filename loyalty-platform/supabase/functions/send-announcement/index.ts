// send-announcement: التاجر يبعت إشعارًا جماعيًا لعملائه.
// يفرض الحد الشهري الأقصى اللي حدّده مالك النظام (merchant_limits / platform_settings).
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, merchantSettings } from "../_shared/auth.ts";
import { sendPush } from "../_shared/push.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);

    // الإعلانات لأصحاب الصلاحية فقط (مالك/مدير)
    if (!["merchant_owner", "manager"].includes(staff.role)) {
      return badRequest("هذه العملية متاحة لمدير المتجر فقط", 403);
    }

    const { title, body } = await req.json();
    if (!title || String(title).trim() === "") {
      return badRequest("عنوان الإشعار مطلوب");
    }

    const s = await merchantSettings(svc, staff.merchantId);
    if (!s.enable_announcements) {
      return badRequest("الإعلانات غير مفعّلة لهذا المتجر");
    }

    // الجمهور: العملاء المرتبطون بالتاجر (مميّزون) — باستثناء من عطّل المشاركة
    // (الخصوصية: "لا يمكن للتاجر التواصل معي"). svc يتجاوز RLS فنُرشّح صراحةً:
    //  • user_stores.visible = إخفاء لكل متجر.
    //  • users.share_profile_with_merchants = إخفاء عام (إعداد الملف الشخصي).
    const { data: rows } = await svc
      .from("user_stores")
      .select("user_id, users!inner(share_profile_with_merchants)")
      .eq("merchant_id", staff.merchantId)
      .eq("visible", true)
      .eq("users.share_profile_with_merchants", true);
    const recipients = [...new Set((rows ?? []).map((r) => r.user_id as string))];
    if (recipients.length === 0) {
      return badRequest("لا يوجد عملاء لإرسال الإشعار إليهم", 409);
    }

    // فرض الحد الشهري (يحدّده مالك النظام).
    const { data: usage } = await svc
      .rpc("merchant_notification_usage", { p_merchant: staff.merchantId })
      .single();
    const remaining = (usage?.remaining as number) ?? 0;
    if (recipients.length > remaining) {
      return badRequest(
        `تجاوزت الحد الشهري للإشعارات. المتبقي ${remaining} من أصل ${usage?.quota}.`,
        429,
      );
    }

    // إدراج الإشعارات داخل التطبيق (دفعة).
    const notifRows = recipients.map((uid) => ({
      user_id: uid,
      type: "announcement",
      title,
      body: body ?? null,
      data: { merchant_id: staff.merchantId },
    }));
    // إدراج على دفعات (chunks) لتفادي الحدود.
    for (let i = 0; i < notifRows.length; i += 500) {
      await svc.from("notifications").insert(notifRows.slice(i, i + 500));
    }

    // إرسال Push للأجهزة المفعّلة (best-effort).
    await sendPush(svc, recipients, {
      title, body: body ?? "", data: { merchant_id: staff.merchantId },
    });

    // تسجيل الحملة (لقياس الاستهلاك).
    await svc.from("notification_campaigns").insert({
      merchant_id: staff.merchantId,
      title,
      body: body ?? null,
      audience_count: recipients.length,
      created_by_staff: staff.staffId,
    });

    return json({
      sent: recipients.length,
      remaining: remaining - recipients.length,
      quota: usage?.quota,
    });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
