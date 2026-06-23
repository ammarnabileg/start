// claim-staff: موظف جديد يسجّل دخوله بالجوال → نربط حساب Auth بصفّ الدعوة
// (merchant_staff الذي أضافه صاحب المتجر بنفس رقم الجوال وبدون user_id).
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient } from "../_shared/auth.ts";

// آخر 9 أرقام (لمطابقة الجوال بصرف النظر عن كود الدولة/الصيغة).
function digits(p: string): string {
  const d = (p ?? "").replace(/\D/g, "");
  return d.slice(-9);
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const authHeader = req.headers.get("Authorization");
    if (!authHeader) return badRequest("غير مصرّح", 401);
    const { data: { user }, error } = await svc.auth.getUser(
      authHeader.replace("Bearer ", ""),
    );
    if (error || !user) return badRequest("جلسة غير صالحة", 401);

    const phone = user.phone ?? "";
    if (!phone) return badRequest("لا يوجد رقم جوال مرتبط بالحساب", 400);
    // الجوال لازم يكون موثّقًا (OTP) — لا نربط دعوة بناءً على رقم غير مؤكَّد.
    if (!user.phone_confirmed_at) {
      return badRequest("لم يتم التحقق من رقم جوالك", 403);
    }
    const suffix = digits(phone);

    // لو الموظف مربوط بالفعل
    const { data: existing } = await svc.from("merchant_staff")
      .select("id, merchant_id, role").eq("user_id", user.id)
      .eq("status", "active").maybeSingle();
    if (existing) {
      return json({ linked: true, merchant_id: existing.merchant_id, role: existing.role });
    }

    // دعوات معلّقة مطابقة لآخر 9 أرقام (مفلترة في الـ DB بدل تحميل الكل).
    const { data: invites } = await svc.from("merchant_staff")
      .select("id, merchant_id, role, phone")
      .is("user_id", null)
      .ilike("phone", `%${suffix}`);
    const matches = (invites ?? []).filter((s) => digits(s.phone ?? "") === suffix);
    if (matches.length === 0) {
      return badRequest("لا توجد دعوة موظف بهذا الرقم. تواصل مع صاحب المتجر.", 404);
    }
    // أكثر من دعوة بنفس الرقم → غموض، لا نربط عشوائيًا (يمنع الربط بمتجر خاطئ).
    if (matches.length > 1) {
      return badRequest("توجد أكثر من دعوة بهذا الرقم، تواصل مع صاحب المتجر.", 409);
    }
    const match = matches[0];

    // ربط ذرّي: لا نربط إلا لو الدعوة ما زالت بدون حساب (يمنع سباق طلبين).
    const { data: updated } = await svc.from("merchant_staff")
      .update({ user_id: user.id, status: "active" })
      .eq("id", match.id).is("user_id", null)
      .select("id").maybeSingle();
    if (!updated) {
      return badRequest("تم استخدام هذه الدعوة بالفعل.", 409);
    }

    return json({ linked: true, merchant_id: match.merchant_id, role: match.role });
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
