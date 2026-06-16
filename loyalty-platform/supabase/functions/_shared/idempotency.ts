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

  // محاولة حجز المفتاح ذرّيًا.
  const { data: claimed } = await svc
    .from("idempotency_keys")
    .insert({
      key,
      endpoint: ctx.endpoint,
      user_id: ctx.userId ?? null,
      merchant_id: ctx.merchantId ?? null,
      status: "in_progress",
    })
    .select("key")
    .maybeSingle();

  if (!claimed) {
    // المفتاح موجود → نقرأ حالته.
    const { data: existing } = await svc
      .from("idempotency_keys")
      .select("status, response, created_at")
      .eq("key", key)
      .maybeSingle();

    if (existing?.status === "done") {
      return { data: (existing.response ?? {}) as T, replayed: true };
    }
    // in_progress: لو قديم (>2 دقيقة) نعتبره عالق ونعيد التنفيذ، وإلا 409.
    const age = existing?.created_at
      ? Date.now() - new Date(existing.created_at).getTime()
      : 0;
    if (age < 120_000) return { conflict: true };
  }

  const result = await run();
  await svc.from("idempotency_keys").update({
    status: "done",
    response: result,
  }).eq("key", key);
  return { data: result, replayed: false };
}
