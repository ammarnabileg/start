// pos-keys: التاجر يولّد/يلغي مفاتيح POS API. المفتاح الخام يظهر مرة واحدة فقط.
import { corsHeaders, badRequest, json } from "../_shared/cors.ts";
import { serviceClient, requireStaff, staffCan } from "../_shared/auth.ts";
import { sha256Hex, generatePosKey } from "../_shared/hash.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: corsHeaders });
  try {
    const svc = serviceClient();
    const staff = await requireStaff(req, svc);
    // إدارة المفاتيح صلاحية إعدادات (owner يتجاوز).
    if (staff.role !== "merchant_owner" &&
        !(await staffCan(svc, staff.staffId, "settings", "edit"))) {
      return badRequest("ليس لديك صلاحية إدارة مفاتيح POS", 403);
    }

    const { action, name, branch_id, key_id } = await req.json();

    if (action === "create") {
      const raw = generatePosKey();
      const hash = await sha256Hex(raw);
      const { data, error } = await svc.from("pos_api_keys").insert({
        merchant_id: staff.merchantId,
        branch_id: branch_id ?? null,
        name: name ?? "POS Key",
        key_prefix: raw.slice(0, 16),
        key_hash: hash,
      }).select("id, key_prefix").single();
      if (error) throw error;
      // المفتاح الخام يُعرض مرة واحدة فقط.
      return json({ id: data.id, prefix: data.key_prefix, key: raw });
    }

    if (action === "revoke") {
      if (!key_id) return badRequest("key_id مفقود");
      await svc.from("pos_api_keys")
        .update({ active: false })
        .eq("id", key_id).eq("merchant_id", staff.merchantId);
      return json({ revoked: true });
    }

    return badRequest("action غير معروف");
  } catch (e) {
    return badRequest((e as Error).message, 401);
  }
});
