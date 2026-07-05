// أدوات مالك المنصّة (Super-Admin) + كتابة سجل التدقيق.
import { SupabaseClient } from "jsr:@supabase/supabase-js@2";

/** يتحقّق أن المستدعي مالك منصّة، ويرجّع معرّفه. */
export async function requireSuperAdmin(
  req: Request,
  svc: SupabaseClient,
): Promise<string> {
  const authHeader = req.headers.get("Authorization");
  if (!authHeader) throw new Error("غير مصرّح");
  const { data: { user }, error } = await svc.auth.getUser(
    authHeader.replace("Bearer ", ""),
  );
  if (error || !user) throw new Error("جلسة غير صالحة");
  const { data } = await svc.from("super_admins")
    .select("user_id").eq("user_id", user.id).maybeSingle();
  if (!data) throw new Error("هذه العملية لمالك المنصّة فقط");
  return user.id;
}

/** يكتب حدثًا في سجل التدقيق (best-effort). */
export async function audit(
  svc: SupabaseClient,
  e: {
    actorId?: string | null;
    actorRole?: string;
    action: string;
    entity?: string;
    entityId?: string;
    merchantId?: string | null;
    details?: Record<string, unknown>;
  },
): Promise<void> {
  try {
    await svc.from("audit_log").insert({
      actor_id: e.actorId ?? null,
      actor_role: e.actorRole ?? null,
      action: e.action,
      entity: e.entity ?? null,
      entity_id: e.entityId ?? null,
      merchant_id: e.merchantId ?? null,
      details: e.details ?? null,
    });
  } catch (_) { /* تجاهل فشل التدقيق */ }
}
