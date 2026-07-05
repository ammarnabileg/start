// حدّ المعدّل (Rate limiting) — يستدعي rate_limit_hit في الـ DB.
import { SupabaseClient } from "jsr:@supabase/supabase-js@2";

/// يرجّع true لو ضمن الحد (مسموح)، false لو تجاوزه.
export async function rateLimit(
  svc: SupabaseClient,
  key: string,
  max: number,
  windowSeconds: number,
): Promise<boolean> {
  const { data } = await svc.rpc("rate_limit_hit", {
    p_key: key,
    p_max: max,
    p_window_seconds: windowSeconds,
  });
  return data !== false; // نسمح عند null/true، ونمنع فقط عند false صريح
}
