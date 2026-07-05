// منع التكرار (Idempotency) — استراتيجية موحّدة لكل نقاط المعاملات.
// الاستخدام: مرّر idempotency_key من العميل؛ لو مفيش، تُنفّذ العملية عاديًا.
import { SupabaseClient } from "jsr:@supabase/supabase-js@2";

export interface IdemContext {
  endpoint: string;
  userId?: string | null;
  merchantId?: string | null;
}

/**
 * يلفّ منطق المعاملة بحماية idempotency:
 *  - لو المفتاح اتنفّذ قبل كده → يرجّع الرد المخزّن (إعادة تشغيل آمنة).
 *  - لو فيه طلب شقيق شغّال → يرجّع 409.
 *  - غير كده → يحجز المفتاح، ينفّذ، يخزّن الرد.
 * لو مفيش key → ينفّذ بدون حماية (سلوك قديم).
 */
export async function withIdempotency<T extends Record<string, unknown>>(
  svc: SupabaseClient,
  key: string | undefined | null,
  ctx: IdemContext,
  run: () => Promise<T>,
): Promise<{ data: T; replayed: boolean } | { conflict: true }> {
  if (!key) {
    return { data: await run(), replayed: false };
  }

  // حجز ذرّي عبر دالة DB: مفتاح واحد فقط يفوز بالتنفيذ (حتى لو كان عالقًا
  // ومنتهي المهلة) — يمنع التنفيذ المزدوج/المتزامن لعمليات النقاط.
  const { data: claim } = await svc.rpc("idem_claim", {
    p_key: key,
    p_endpoint: ctx.endpoint,
    p_user: ctx.userId ?? null,
    p_merchant: ctx.merchantId ?? null,
  }).single();

  if (!claim?.claimed) {
    if (claim?.status === "done") {
      return { data: (claim.response ?? {}) as T, replayed: true };
    }
    // in_progress حديث (طلب شقيق شغّال) → 409.
    return { conflict: true };
  }

  const result = await run();
  await svc.from("idempotency_keys").update({
    status: "done",
    response: result,
  }).eq("key", key);
  return { data: result, replayed: false };
}
