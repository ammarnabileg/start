// spin-wheel: العميل يلفّ عجلة الحظ. السيرفر يخصم النقاط ويختار النصيب
// (weighted random) ويحفظ الهدية المكسوبة بـ claim_secret لتوليد QR متغيّر.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireUser } from "../_shared/auth.ts";
import { withIdempotency } from "../_shared/idempotency.ts";
import { rateLimit } from "../_shared/ratelimit.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const userId = await requireUser(req, svc);
    // حدّ اندفاع: 10 لفّات/دقيقة كحماية (بالإضافة للحد اليومي على العجلة).
    if (!await rateLimit(svc, `spin:${userId}`, 10, 60)) {
      return badRequest("محاولات كثيرة، انتظر قليلًا", 429);
    }
    const { wheel_id, idempotency_key } = await req.json();
    if (!wheel_id) return badRequest("wheel_id مفقود");

    const { data: wheel } = await svc.from("lucky_wheels")
      .select("id, merchant_id, spin_cost_points, max_spins_per_day, active")
      .eq("id", wheel_id).maybeSingle();
    if (!wheel || !wheel.active) return badRequest("العجلة غير متاحة");
    // استهداف الفروع: العجلة لازم تكون متاحة في فرع محفظة العميل.
    {
      const { data: anyWallet } = await svc.from("user_stores")
        .select("branch_id").eq("user_id", userId)
        .eq("merchant_id", wheel.merchant_id)
        .order("first_linked_at", { ascending: false }).limit(1).maybeSingle();
      const { data: wAt } = await svc.rpc("entity_at_branch", {
        p_type: "wheel", p_id: wheel.id, p_branch: anyWallet?.branch_id ?? null,
      });
      if (wAt === false) return badRequest("العجلة غير متاحة في فرعك", 403);
    }

    // اللفّة كاملة (الحد اليومي + اختيار النصيب الموزون + إنقاص المخزون +
    // خصم سعر اللفّة ومنح الجائزة) تتم ذرّيًا داخل spin_wheel_execute بقفل صف
    // العجلة — يمنع تجاوز الحد اليومي وبيع مخزون أكثر من المتاح تحت التزامن.
    // محمية أيضًا بـ idempotency: لفّة مكرّرة بنفس المفتاح تُعيد نفس النتيجة.
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "spin-wheel", userId, merchantId: wheel.merchant_id },
      async () => {
        const { data, error } = await svc.rpc("spin_wheel_execute", {
          p_user: userId, p_wheel: wheel_id,
        });
        // نرمي بدل تخزين فشلٍ كنجاحٍ في مفتاح idempotency.
        if (error) throw new Error(error.message);
        return data as Record<string, unknown>;
      },
    );

    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    const m = (e as Error).message;
    if (m.includes("INSUFFICIENT_POINTS")) {
      return badRequest("النقاط غير كافية للّفّة", 422);
    }
    if (m.includes("DAILY_LIMIT")) return badRequest("لقد استنفدت لفّات اليوم", 429);
    if (m.includes("NO_SEGMENTS")) return badRequest("نفدت كل العروض", 409);
    if (m.includes("WHEEL_UNAVAILABLE")) return badRequest("العجلة غير متاحة", 400);
    if (m.includes("مصرّح") || m.includes("جلسة") || m.includes("صلاحية")) {
      return badRequest(m, 401);
    }
    return badRequest(m, 400);
  }
});
