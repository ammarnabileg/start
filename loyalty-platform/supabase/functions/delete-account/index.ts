// delete-account: حذف حساب العميل نهائيًا (PDPL). يحذف مستخدم Auth،
// وصفوف public.users وكل ما يتبعه تُحذف تلقائيًا عبر on delete cascade.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    // حذف مستخدم Auth → يتسلسل الحذف على public.users وكل الجداول المرتبطة.
    const { error } = await svc.auth.admin.deleteUser(userId);
    if (error) throw error;
    return json({ deleted: true });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
