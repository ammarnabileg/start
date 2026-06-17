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

    // حد اللفّات اليومي (لو مفعّل)
    if (wheel.max_spins_per_day > 0) {
      const since = new Date(); since.setHours(0, 0, 0, 0);
      const { count } = await svc.from("user_prizes")
        .select("id", { count: "exact", head: true })
        .eq("user_id", userId).eq("merchant_id", wheel.merchant_id)
        .eq("source", "wheel").gte("created_at", since.toISOString());
      if ((count ?? 0) >= wheel.max_spins_per_day) {
        return badRequest("لقد استنفدت لفّات اليوم", 429);
      }
    }

    // المقاطع
    const { data: segments } = await svc.from("wheel_segments")
      .select("*").eq("wheel_id", wheel_id).order("sort_order");
    if (!segments || segments.length === 0) {
      return badRequest("لا توجد عروض على العجلة بعد", 409);
    }
    // المقاطع المتاحة (مخزون > 0 أو غير محدود)
    const pool = segments.filter((s) =>
      s.stock === null || (s.stock as number) > 0);
    if (pool.length === 0) return badRequest("نفدت كل العروض", 409);

    // خصم النقاط: نختار محفظة للعميل عند هذا التاجر فيها رصيد كافٍ.
    const cost = wheel.spin_cost_points;
    const { data: wallets } = await svc.from("user_stores")
      .select("id, branch_id, available_points")
      .eq("user_id", userId).eq("merchant_id", wheel.merchant_id)
      .order("available_points", { ascending: false });
    const wallet = (wallets ?? []).find((w) => w.available_points >= cost);
    if (!wallet) return badRequest("النقاط غير كافية للّفّة", 422);

    // المعاملة محمية بـ idempotency: لفّة مكرّرة بنفس المفتاح لا تخصم مرتين
    // ولا تمنح جائزتين — تُعيد نفس النتيجة المخزّنة.
    const idem = await withIdempotency(
      svc,
      idempotency_key,
      { endpoint: "spin-wheel", userId, merchantId: wheel.merchant_id },
      async () => {
        await svc.from("user_stores")
          .update({ available_points: wallet.available_points - cost })
          .eq("id", wallet.id);
        await svc.from("points_transactions").insert({
          user_store_id: wallet.id, branch_id: wallet.branch_id,
          type: "redeem", points: -cost, reason: "wheel_spin",
        });

        // اختيار النصيب (weighted random) على السيرفر — لا يُتلاعب به.
        const total = pool.reduce((a, s) => a + Math.max(s.weight ?? 1, 1), 0);
        let r = Math.random() * total;
        let chosen = pool[pool.length - 1];
        for (const s of pool) {
          r -= Math.max(s.weight ?? 1, 1);
          if (r <= 0) { chosen = s; break; }
        }

        // إنقاص مخزون المقطع لو محدود
        if (chosen.stock !== null) {
          await svc.from("wheel_segments")
            .update({ stock: Math.max((chosen.stock as number) - 1, 0) })
            .eq("id", chosen.id);
        }

        // النصيب "لا شيء" → لا هدية
        if (chosen.kind === "nothing") {
          return {
            result: "nothing",
            segment_id: chosen.id,
            label: chosen.label,
            prize: null,
          };
        }

        // النصيب "نقاط" → نعيدها مباشرة (earn)
        if (chosen.kind === "points") {
          const pts = chosen.points_value ?? 0;
          // الرصيد بعد خصم سعر اللفّة + نقاط الجائزة (lifetime لا يتغيّر هنا).
          await svc.from("user_stores").update({
            available_points: wallet.available_points - cost + pts,
          }).eq("id", wallet.id);
          await svc.from("points_transactions").insert({
            user_store_id: wallet.id, branch_id: wallet.branch_id,
            type: "earn", points: pts, reason: "wheel_points",
          });
          return {
            result: "points",
            segment_id: chosen.id,
            label: chosen.label,
            points: pts,
            prize: null,
          };
        }

        // النصيب "مكافأة/كوبون" → نحفظ هدية قابلة للتفعيل بـ QR متغيّر.
        // نطاق الفرع للهدية حسب إعداد نطاق النقاط: لو branch نقيّدها بفرع المحفظة.
        const { data: settings } = await svc.from("merchant_settings")
          .select("points_scope").eq("merchant_id", wheel.merchant_id).maybeSingle();
        const branchScope =
          (settings?.points_scope ?? "branch") === "branch" ? wallet.branch_id : null;

        const expires = new Date(Date.now() + 30 * 24 * 3600_000); // 30 يوم
        const { data: prize } = await svc.from("user_prizes").insert({
          user_id: userId,
          merchant_id: wheel.merchant_id,
          source: "wheel",
          source_ref: wheel_id,
          title: chosen.label,
          kind: chosen.kind,
          points_value: chosen.points_value ?? 0,
          branch_scope: branchScope,
          expires_at: expires.toISOString(),
        }).select("id, claim_secret, title, expires_at").single();

        return {
          result: "prize",
          segment_id: chosen.id,
          label: chosen.label,
          prize: {
            id: prize.id,
            title: prize.title,
            claim_secret: prize.claim_secret, // العميل يولّد منه QR متغيّر
            expires_at: prize.expires_at,
          },
        };
      },
    );

    if ("conflict" in idem) {
      return badRequest("عملية قيد المعالجة، حاول مرة أخرى", 409);
    }
    return json(idem.data);
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
