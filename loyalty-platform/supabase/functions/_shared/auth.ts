import { createClient, SupabaseClient } from "jsr:@supabase/supabase-js@2";

/** عميل service_role — يتخطّى RLS بعد ما نفرض القواعد بأنفسنا. */
export function serviceClient(): SupabaseClient {
  return createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
    { auth: { persistSession: false } },
  );
}

export interface StaffContext {
  staffId: string;
  userId: string; // حساب الموظف في auth
  merchantId: string;
  branchId: string | null;
  role: string;
  canRedeemPrizes: boolean;
}

/**
 * يتحقّق من JWT المستدعي ويرجّع سياق الموظف (التاجر/الفرع/الدور).
 * أي عملية تاجر لازم تمرّ من هنا الأول.
 */
export async function requireStaff(
  req: Request,
  svc: SupabaseClient,
): Promise<StaffContext> {
  const authHeader = req.headers.get("Authorization");
  if (!authHeader) throw new Error("غير مصرّح");

  const token = authHeader.replace("Bearer ", "");
  const { data: { user }, error } = await svc.auth.getUser(token);
  if (error || !user) throw new Error("جلسة غير صالحة");

  const { data: staff } = await svc
    .from("merchant_staff")
    .select("id, user_id, merchant_id, branch_id, role, status, can_redeem_prizes")
    .eq("user_id", user.id)
    .eq("status", "active")
    .maybeSingle();

  if (!staff) throw new Error("هذا الحساب غير مرتبط بمتجر نشط");

  // فرض الأهلية: المتجر معتمد وغير معلّق واشتراكه/تجربته سارية.
  const { data: entitled } = await svc.rpc("merchant_entitled", {
    p_merchant: staff.merchant_id,
  });
  if (entitled !== true) {
    throw new Error("المتجر غير متاح حاليًا (معلّق أو انتهى اشتراكه)");
  }

  return {
    staffId: staff.id,
    userId: staff.user_id,
    merchantId: staff.merchant_id,
    branchId: staff.branch_id,
    role: staff.role,
    canRedeemPrizes: staff.can_redeem_prizes ?? true,
  };
}

/** يتحقّق من JWT العميل ويرجّع user_id. */
export async function requireUser(
  req: Request,
  svc: SupabaseClient,
): Promise<string> {
  const authHeader = req.headers.get("Authorization");
  if (!authHeader) throw new Error("غير مصرّح");
  const token = authHeader.replace("Bearer ", "");
  const { data: { user }, error } = await svc.auth.getUser(token);
  if (error || !user) throw new Error("جلسة غير صالحة");
  return user.id;
}

/** يتحقّق من صلاحية موظف على مورد/إجراء عبر دالة staff_can في الـ DB. */
export async function staffCan(
  svc: SupabaseClient,
  staffId: string,
  resource: string,
  action: string,
): Promise<boolean> {
  const { data } = await svc.rpc("staff_can", {
    p_staff: staffId,
    p_resource: resource,
    p_action: action,
  });
  return data === true;
}

/** يجلب إعدادات التاجر (مع defaults آمنة لو الصف مش موجود). */
export async function merchantSettings(svc: SupabaseClient, merchantId: string) {
  const { data } = await svc
    .from("merchant_settings")
    .select("*")
    .eq("merchant_id", merchantId)
    .maybeSingle();
  return {
    points_scope: data?.points_scope ?? "branch",
    enable_points: data?.enable_points ?? true,
    enable_visits: data?.enable_visits ?? true,
    enable_rewards: data?.enable_rewards ?? true,
    enable_levels: data?.enable_levels ?? true,
    enable_coupons: data?.enable_coupons ?? false, // opt-in (يطابق default الجدول)
    enable_announcements: data?.enable_announcements ?? true,
    max_points_per_txn: data?.max_points_per_txn ?? 500,
    daily_points_per_staff: data?.daily_points_per_staff ?? 5000,
    one_visit_per_day: data?.one_visit_per_day ?? true,
    require_redemption_confirm: data?.require_redemption_confirm ?? true,
    redemption_confirm_threshold: data?.redemption_confirm_threshold ?? 0,
    qr_rotation_seconds: data?.qr_rotation_seconds ?? 30,
    redemption_window_minutes: data?.redemption_window_minutes ?? 5,
  };
}
