-- =====================================================================
-- منصة الولاء — Schema كامل لـ Supabase (Postgres)
-- ينفّذ في: Supabase Dashboard > SQL Editor
-- يشمل: الجداول + الفهارس + القيود + RLS (عزل التجار)
-- راجع 00_PROJECT_OVERVIEW.md للسياق الكامل
-- =====================================================================

-- امتدادات مطلوبة
create extension if not exists "pgcrypto";   -- gen_random_uuid
create extension if not exists "pg_cron";    -- المهام المجدولة (Birthday / Coupons)

-- =====================================================================
-- 1) المستخدمين (العملاء)
-- ملاحظة: auth.users بتديره Supabase Auth. الجدول ده الـ profile المرتبط بيه.
-- =====================================================================
create table public.users (
  id              uuid primary key references auth.users(id) on delete cascade,
  name            text not null,
  phone           text unique not null,
  email           text,
  date_of_birth   date,                          -- حسّاس (PDPL) — بموافقة صريحة
  qr_secret       text not null default encode(gen_random_bytes(20), 'base64'),
  referral_code   text unique not null,
  referred_by     uuid references public.users(id),
  push_opt_in     boolean not null default false,
  proximity_opt_in boolean not null default false,
  leaderboard_opt_in boolean not null default true,   -- يظهر اسمه في لوحات الصدارة
  -- خصوصية عامة: مشاركة بيانات التواصل مع كل المتاجر المرتبط بها (الافتراضي true).
  -- عند false: يختفي من دليل عملاء أي تاجر ومن كل لوحات الصدارة (المتجر/الفروع)
  -- ولا يمكن للتاجر التواصل معه — مع بقاء النقاط/الزيارات/المكافآت/الربط كما هي.
  -- يعمل بالتراكب مع user_stores.visible (تحكّم لكل متجر): الظهور = الاثنان معًا.
  share_profile_with_merchants boolean not null default true,
  avatar_url      text,
  created_at      timestamptz not null default now()
);

create table public.device_tokens (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references public.users(id) on delete cascade,
  token       text not null,
  platform    text not null check (platform in ('ios','android')),
  created_at  timestamptz not null default now(),
  unique (user_id, token)
);

-- =====================================================================
-- 2) التجار + الفروع + الاشتراك + الموظفين
-- =====================================================================
create table public.merchants (
  id            uuid primary key default gen_random_uuid(),
  business_name text not null,
  business_type text,                              -- مطعم / كافيه / متجر / صالون / أخرى
  phone         text,
  email         text,
  cr_number     text,                              -- السجل التجاري (اختياري)
  logo_url      text,
  address       text,
  status        text not null default 'pending'
                check (status in ('pending','approved','rejected','suspended')),
  approved_at   timestamptz,
  created_at    timestamptz not null default now()
);

create table public.branches (
  id               uuid primary key default gen_random_uuid(),
  merchant_id      uuid not null references public.merchants(id) on delete cascade,
  name             text not null,
  address          text,
  lat              double precision,
  lng              double precision,
  geofence_radius_m integer not null default 150,
  active           boolean not null default true,
  created_at       timestamptz not null default now()
);

create table public.subscriptions (
  id                  uuid primary key default gen_random_uuid(),
  merchant_id         uuid not null references public.merchants(id) on delete cascade,
  plan                text not null default 'trial' check (plan in ('trial','monthly','yearly')),
  status              text not null default 'trial'
                      check (status in ('trial','active','past_due','canceled','expired')),
  trial_ends_at       timestamptz,
  current_period_end  timestamptz,
  gateway_ref         text,                        -- جاهز لبوابة الدفع لاحقًا
  created_at          timestamptz not null default now()
);

-- إعدادات/خيارات التاجر — White-Label قابل للتهيئة بالكامل (صف واحد لكل تاجر).
-- التاجر بيتحكّم في كل حاجة من هنا: نطاق النقاط، تفعيل الميزات، حدود الأمان.
create table public.merchant_settings (
  merchant_id   uuid primary key references public.merchants(id) on delete cascade,

  -- نطاق احتساب النقاط/المستويات (الخيار اللي طلبه التاجر)
  points_scope  text not null default 'branch' check (points_scope in ('merchant','branch')),

  -- تفعيل/تعطيل الميزات
  enable_visits        boolean not null default true,
  enable_points        boolean not null default true,
  enable_rewards       boolean not null default true,
  enable_levels        boolean not null default true,
  enable_coupons       boolean not null default false,
  enable_referral      boolean not null default false,
  enable_birthday      boolean not null default false,
  enable_proximity     boolean not null default false,
  enable_gps_checkin   boolean not null default false,
  enable_announcements boolean not null default true,

  -- حدود الأمان (يضبطها التاجر)
  max_points_per_txn          integer not null default 500,
  daily_points_per_staff      integer not null default 5000,
  one_visit_per_day           boolean not null default true,
  require_redemption_confirm  boolean not null default true,
  redemption_confirm_threshold integer not null default 0,  -- التأكيد فوق X نقطة (0 = دايمًا)
  qr_rotation_seconds         integer not null default 30,
  redemption_window_minutes   integer not null default 5,

  -- الاكتساب والعلامة
  earn_rate_per_currency numeric not null default 1,         -- نقاط لكل وحدة عملة
  primary_color_hex      text,                               -- لون مخصّص (White-Label)
  brand_name             text,

  updated_at timestamptz not null default now()
);

-- ربط مستخدم Auth بدور داخل تاجر (owner/staff). ده أساس الـ RLS للتجار.
create table public.merchant_staff (
  id          uuid primary key default gen_random_uuid(),
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  user_id     uuid references auth.users(id) on delete cascade, -- حساب الدخول للموظف
  branch_id   uuid references public.branches(id) on delete set null,
  name        text not null,
  phone       text,
  role        text not null check (role in ('merchant_owner','manager','branch_manager','cashier')),
  status      text not null default 'active' check (status in ('active','disabled')),
  created_at  timestamptz not null default now()
);

-- =====================================================================
-- 3) المستويات + محفظة العميل عند كل تاجر (أهم جدول)
-- =====================================================================
create table public.loyalty_levels (
  id          uuid primary key default gen_random_uuid(),
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  -- نطاق المستوى يتبع إعداد نطاق النقاط (merchant_settings.points_scope):
  --   • NULL        → مستوى على مستوى الستور كله (للوضع المشترك 'merchant').
  --   • branch_id   → مستوى خاص بفرع محدّد (للوضع المنفصل 'branch').
  branch_id   uuid references public.branches(id) on delete cascade,
  name        text not null,                       -- Bronze / Silver / Gold ...
  threshold_lifetime_points integer not null,      -- أقل lifetime للوصول
  reward_description text,
  sort_order  integer not null default 0
);

-- استهداف العناصر للفروع (مكافأة/كوبون/حملة/عجلة):
--   • لا توجد صفوف لعنصر  → موحّد (متاح في كل الفروع).
--   • توجد صفوف           → متاح فقط في الفروع المذكورة.
-- التاجر يضيف/يشيل فرعًا من العنصر بسهولة (صفّ لكل فرع).
create table public.entity_branches (
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  entity_type text not null
    check (entity_type in ('reward','coupon','campaign','wheel')),
  entity_id   uuid not null,
  branch_id   uuid not null references public.branches(id) on delete cascade,
  primary key (entity_type, entity_id, branch_id)
);
create index entity_branches_lookup
  on public.entity_branches (entity_type, entity_id);
create index entity_branches_branch
  on public.entity_branches (branch_id);

-- المحفظة تدعم الوضعين حسب إعداد التاجر (merchant_settings.points_scope):
--   • scope = 'merchant' (مشترك): branch_id = NULL ، محفظة واحدة لكل (عميل, تاجر)
--   • scope = 'branch'  (منفصل): branch_id = الفرع ، محفظة لكل (عميل, تاجر, فرع)
-- نضمن التفرّد بفهرسين جزئيين بدل قيد unique واحد.
create table public.user_stores (
  id               uuid primary key default gen_random_uuid(),
  user_id          uuid not null references public.users(id) on delete cascade,
  merchant_id      uuid not null references public.merchants(id) on delete cascade,
  branch_id        uuid references public.branches(id) on delete cascade,  -- NULL = مشترك
  available_points integer not null default 0,     -- القابل للصرف
  lifetime_points  integer not null default 0,      -- لا يُخصم أبدًا → يحدد المستوى
  current_level_id uuid references public.loyalty_levels(id),
  -- خصوصية لكل متجر (Customer Visibility): true = يشارك معلوماته مع هذا التاجر
  -- ويظهر في قوائمه/صدارته. false = مخفي عن هذا التاجر فقط (النقاط/الزيارات
  -- تستمر). مستوى الربط (مستقل لكل تاجر، ومع branch_id يدعم لاحقًا التخصيص للفرع).
  visible          boolean not null default true,
  -- متجر مفضّل لدى العميل (يظهر أعلى قائمته) — تفضيل خاص بالعميل.
  is_favorite      boolean not null default false,
  first_linked_at  timestamptz not null default now()
);

-- تفرّد الوضع المشترك (branch_id IS NULL)
create unique index uq_wallet_merchant_scope
  on public.user_stores (user_id, merchant_id)
  where branch_id is null;

-- تفرّد الوضع المنفصل لكل فرع (branch_id NOT NULL)
create unique index uq_wallet_branch_scope
  on public.user_stores (user_id, merchant_id, branch_id)
  where branch_id is not null;

-- =====================================================================
-- 4) الزيارات + الحملات + النقاط
-- =====================================================================
create table public.visit_campaigns (
  id               uuid primary key default gen_random_uuid(),
  merchant_id      uuid not null references public.merchants(id) on delete cascade,
  name             text not null,
  description      text,                                    -- وصف الحملة
  -- بطاقة الأختام: التكرار قد يكون زيارة/شراء/تبرّع/مخصّص.
  action_type      text not null default 'visit'
                     check (action_type in ('visit','purchase','donation','custom')),
  action_label     text,                                    -- مثال: "شراء قهوة"
  required_visits  integer not null check (required_visits > 0), -- عدد التكرارات
  points_per_stamp integer not null default 0,              -- نقاط مع كل ختم
  reward_points    integer not null default 0,              -- نقاط مكافأة الإكمال
  reward_name      text,
  reward_image_url text,                                    -- صورة المكافأة (آخر خانة)
  reward_description text,
  stamp_image_url  text,                                    -- صورة الختم
  banner_image_url text,                                    -- بنر الهيدر (اختياري)
  active           boolean not null default true,
  created_at       timestamptz not null default now()
);

create table public.user_visits (
  id           uuid primary key default gen_random_uuid(),
  user_id      uuid not null references public.users(id) on delete cascade,
  merchant_id  uuid not null references public.merchants(id) on delete cascade,
  branch_id    uuid references public.branches(id) on delete set null,
  campaign_id  uuid references public.visit_campaigns(id) on delete set null,
  visit_date   date not null default current_date,
  source       text not null default 'qr_scan' check (source in ('qr_scan','gps_checkin')),
  scanned_by_staff_id uuid references public.merchant_staff(id),
  created_at   timestamptz not null default now(),
  unique (user_id, merchant_id, visit_date)        -- ⭐ زيارة واحدة في اليوم
);

create table public.points_transactions (
  id            uuid primary key default gen_random_uuid(),
  user_store_id uuid not null references public.user_stores(id) on delete cascade,
  branch_id     uuid references public.branches(id) on delete set null,
  type          text not null check (type in ('earn','redeem','adjust')),
  points        integer not null,
  staff_id      uuid references public.merchant_staff(id),
  reason        text,
  created_at    timestamptz not null default now()
);

-- =====================================================================
-- 5) المكافآت + الاستبدال
-- =====================================================================
create table public.rewards (
  id          uuid primary key default gen_random_uuid(),
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  name        text not null,
  image_url   text,
  description text,
  points_cost integer not null check (points_cost >= 0),
  stock_qty   integer,                             -- null = غير محدود
  active      boolean not null default true,
  created_at  timestamptz not null default now()
);

create table public.reward_redemptions (
  id           uuid primary key default gen_random_uuid(),
  user_id      uuid not null references public.users(id) on delete cascade,
  merchant_id  uuid not null references public.merchants(id) on delete cascade,
  reward_id    uuid not null references public.rewards(id),
  branch_id    uuid references public.branches(id) on delete set null,
  points_spent integer not null,
  staff_id     uuid references public.merchant_staff(id),
  status       text not null default 'pending'     -- pending → confirmed/expired
               check (status in ('pending','confirmed','expired','canceled')),
  expires_at   timestamptz,                         -- كود الاستلام (5 دقائق)
  confirmed_at timestamptz,
  claim_secret text not null default encode(gen_random_bytes(20), 'base64'), -- لتوقيع QR متغيّر
  created_at   timestamptz not null default now()
);

-- =====================================================================
-- 6) الكوبونات + الإحالة + إشعار القرب + الإشعارات
-- =====================================================================
create table public.coupons (
  id              uuid primary key default gen_random_uuid(),
  merchant_id     uuid not null references public.merchants(id) on delete cascade,
  code            text not null,
  type            text not null check (type in ('percent','fixed','free_item')),
  value           numeric,
  valid_from      timestamptz,
  valid_to        timestamptz,
  usage_limit     integer,
  per_user_limit  integer,
  active          boolean not null default true,
  created_at      timestamptz not null default now(),
  unique (merchant_id, code)                        -- فريد لكل تاجر
);

create table public.coupon_redemptions (
  id          uuid primary key default gen_random_uuid(),
  coupon_id   uuid not null references public.coupons(id) on delete cascade,
  user_id     uuid not null references public.users(id) on delete cascade,
  staff_id    uuid references public.merchant_staff(id),
  created_at  timestamptz not null default now()
);

create table public.referrals (
  id               uuid primary key default gen_random_uuid(),
  referrer_id      uuid not null references public.users(id) on delete cascade,
  referee_id       uuid references public.users(id) on delete cascade,
  status           text not null default 'pending'
                   check (status in ('pending','qualified','rewarded')),
  qualifying_event text,
  reward_granted_at timestamptz,
  created_at       timestamptz not null default now()
);

create table public.proximity_notifications_log (
  id              uuid primary key default gen_random_uuid(),
  user_id         uuid not null references public.users(id) on delete cascade,
  merchant_id     uuid not null references public.merchants(id) on delete cascade,
  branch_id       uuid not null references public.branches(id) on delete cascade,
  last_notified_at timestamptz not null default now(),
  unique (user_id, branch_id)
);

create table public.notifications (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references public.users(id) on delete cascade,
  type        text not null,                       -- points/reward_ready/level_up/proximity/birthday/announcement
  title       text not null,
  body        text,
  data        jsonb,                               -- deep-link payload
  read_at     timestamptz,
  created_at  timestamptz not null default now()
);

-- =====================================================================
-- 6.5) الأسئلة (Questions / Surveys)
-- التاجر يضيف أسئلة (اختيار/نص) بنقاط يحدّدها، العميل يجاوب وياخد النقاط،
-- والتاجر يشوف الإجابات في لوحته.
-- =====================================================================
create table public.merchant_questions (
  id            uuid primary key default gen_random_uuid(),
  merchant_id   uuid not null references public.merchants(id) on delete cascade,
  title         text not null,                         -- نص السؤال
  description   text,
  type          text not null check (type in ('single_choice','multi_choice','text')),
  points_reward integer not null default 0,            -- النقاط اللي ياخدها العميل (يحددها التاجر)
  required      boolean not null default false,
  active        boolean not null default true,
  sort_order    integer not null default 0,
  created_at    timestamptz not null default now()
);

create table public.question_options (
  id          uuid primary key default gen_random_uuid(),
  question_id uuid not null references public.merchant_questions(id) on delete cascade,
  label       text not null,
  sort_order  integer not null default 0
);

create table public.question_responses (
  id                 uuid primary key default gen_random_uuid(),
  question_id        uuid not null references public.merchant_questions(id) on delete cascade,
  user_id            uuid not null references public.users(id) on delete cascade,
  merchant_id        uuid not null references public.merchants(id) on delete cascade,
  branch_id          uuid references public.branches(id) on delete set null,
  answer_text        text,                              -- للنوع text
  selected_option_ids uuid[],                           -- للاختيارات
  points_awarded     integer not null default 0,
  created_at         timestamptz not null default now(),
  unique (question_id, user_id)                         -- كل عميل يجاوب مرة واحدة
);

create index idx_questions_merchant on public.merchant_questions(merchant_id) where active;
create index idx_qresponses_question on public.question_responses(question_id);
create index idx_qresponses_merchant on public.question_responses(merchant_id);

-- =====================================================================
-- 6.6) لوحات الصدارة (Leaderboards)
-- (أ) عامة لكل التطبيق بناءً على إجمالي النقاط (lifetime عبر كل المحافظ).
-- (ب) لكل ستور: حسب الفرع أو الستور ككل — تبعًا لإعداد التاجر (points_scope)
--     ولاختيار العرض (تمرير p_branch = فرع معيّن أو NULL للستور كامل).
-- الخصوصية: تُحترم users.leaderboard_opt_in (المنسحب لا يظهر اسمه/مركزه).
-- =====================================================================

-- لوحة الصدارة العامة (Top N) — إجمالي ما جمعه العميل عبر كل المتاجر.
create or replace function public.global_leaderboard(p_limit int default 50)
returns table(rank bigint, user_id uuid, display_name text, total_points bigint)
language sql security definer stable as $$
  select row_number() over (order by sum(us.lifetime_points) desc) as rank,
         u.id,
         u.name,
         sum(us.lifetime_points)::bigint as total_points
  from public.user_stores us
  join public.users u on u.id = us.user_id
  where u.leaderboard_opt_in
    and u.share_profile_with_merchants          -- المنسحب عامًّا يخرج من الترتيب
  group by u.id, u.name
  order by total_points desc
  limit p_limit;
$$;

-- مركز العميل الحالي في اللوحة العامة (عشان يشوف ترتيبه حتى لو بره الـ Top N).
create or replace function public.my_global_rank()
returns table(rank bigint, total_points bigint)
language sql security definer stable as $$
  with scores as (
    select us.user_id, sum(us.lifetime_points) as pts
    from public.user_stores us
    join public.users u on u.id = us.user_id
    where u.leaderboard_opt_in
      and u.share_profile_with_merchants
    group by us.user_id
  ), ranked as (
    select user_id, pts, row_number() over (order by pts desc) as rnk from scores
  )
  select rnk, pts::bigint from ranked where user_id = auth.uid();
$$;

-- لوحة صدارة الستور.
--   p_branch IS NULL  → الستور ككل (تجميع عبر كل الفروع لكل عميل)
--   p_branch = فرع    → ترتيب داخل الفرع ده فقط
-- ملاحظة: لو إعداد التاجر points_scope='merchant' فالنقاط أصلًا مشتركة
-- (branch_id = NULL) والعرض بالفرع بيرجع فاضي، فالأنسب وقتها تمرير NULL.
create or replace function public.store_leaderboard(
  p_merchant uuid, p_branch uuid default null, p_limit int default 50
) returns table(rank bigint, user_id uuid, display_name text, total_points bigint)
language sql security definer stable as $$
  select row_number() over (order by sum(us.lifetime_points) desc) as rank,
         u.id,
         u.name,
         sum(us.lifetime_points)::bigint as total_points
  from public.user_stores us
  join public.users u on u.id = us.user_id
  where us.merchant_id = p_merchant
    and u.leaderboard_opt_in
    and us.visible                              -- إخفاء من عطّل المشاركة مع هذا المتجر
    and u.share_profile_with_merchants          -- إخفاء من عطّل المشاركة العامة (الملف الشخصي)
    and (p_branch is null or us.branch_id = p_branch)
  group by u.id, u.name
  order by total_points desc
  limit p_limit;
$$;

-- =====================================================================
-- 7) الفهارس (Performance)
-- =====================================================================
create index idx_user_stores_user      on public.user_stores(user_id);
create index idx_user_stores_merchant  on public.user_stores(merchant_id);
create index idx_visits_merchant_date  on public.user_visits(merchant_id, visit_date);
create index idx_points_store          on public.points_transactions(user_store_id);
create index idx_rewards_merchant      on public.rewards(merchant_id) where active;
create index idx_staff_user            on public.merchant_staff(user_id);
create index idx_notifications_user    on public.notifications(user_id) where read_at is null;

-- =====================================================================
-- 8) دالة مساعدة: هل المستخدم الحالي يتبع هذا التاجر؟ (لـ RLS)
-- =====================================================================
create or replace function public.is_merchant_member(p_merchant uuid)
returns boolean language sql security definer stable as $$
  select exists (
    select 1 from public.merchant_staff
    where merchant_id = p_merchant
      and user_id = auth.uid()
      and status = 'active'
  );
$$;

-- صلاحيات الأدوار القديمة (legacy) كاحتياطي عندما لا يكون لموظف دور مخصّص.
-- تطابق الأدوار الافتراضية المزروعة (مالك/مدير/كاشير) — لضمان التوافق الرجعي.
create or replace function public.legacy_role_can(
  p_role text, p_resource text, p_action text
) returns boolean language sql immutable as $$
  select case
    when p_role = 'merchant_owner' then true
    when p_role in ('manager','branch_manager') then
      (p_resource in ('rewards','campaigns','levels','coupons','wheel','questions')
         and p_action in ('view','create','edit','delete'))
      or (p_resource in ('customers','analytics') and p_action = 'view')
      or (p_resource = 'reports' and p_action in ('view','reply'))
      or (p_resource = 'announcements' and p_action in ('view','create'))
      or (p_resource = 'points' and p_action = 'create')
      or (p_resource = 'visits' and p_action = 'create')
      or (p_resource = 'prizes' and p_action = 'redeem')
    when p_role = 'cashier' then
      (p_resource = 'points' and p_action = 'create')
      or (p_resource = 'visits' and p_action = 'create')
      or (p_resource = 'prizes' and p_action = 'redeem')
      or (p_resource = 'customers' and p_action = 'view')
    else false
  end;
$$;

-- هل يملك المستخدم الحالي (auth.uid()) صلاحية إجراء على مورد عند تاجر معيّن؟
-- تُستخدم في سياسات RLS لفرض صلاحيات الدور على الكتابة المباشرة في الجداول.
-- القاعدة: المالك يمرّ دائمًا؛ لو للموظف دور مخصّص (role_id) فصلاحياته وحدها
-- هي الفيصل (فدور «عرض فقط» يمنع الإضافة/التعديل/الحذف فعليًا)؛ وإلا نرجع
-- لصلاحيات الدور القديم. plpgsql لتأجيل التحقق من merchant_roles (يُنشأ لاحقًا).
create or replace function public.current_staff_can(
  p_merchant uuid, p_resource text, p_action text
) returns boolean language plpgsql security definer stable as $$
declare ok boolean;
begin
  select exists (
    select 1 from public.merchant_staff s
    left join public.merchant_roles r on r.id = s.role_id
    where s.merchant_id = p_merchant
      and s.user_id = auth.uid()
      and s.status = 'active' and (
        s.role = 'merchant_owner'
        or (r.permissions ->> 'owner')::boolean is true
        or (s.role_id is not null and (
              (r.permissions -> p_resource) ? p_action
              or (r.permissions -> p_resource) ? 'manage'))
        or (s.role_id is null
              and public.legacy_role_can(s.role, p_resource, p_action))
      )
  ) into ok;
  return ok;
end;
$$;


-- لو points_scope = 'merchant' → branch_id يتجاهل (محفظة مشتركة، branch_id = NULL).
-- لو points_scope = 'branch'  → محفظة منفصلة لفرع الكاشير.
-- تُستدعى من Edge Functions (بمفتاح service_role) قبل أي earn/redeem/visit.
-- إنقاص مخزون مكافأة (لو محدود) بشكل آمن.
create or replace function public.decrement_stock(p_reward uuid)
returns void language sql security definer as $$
  update public.rewards
  set stock_qty = greatest(stock_qty - 1, 0)
  where id = p_reward and stock_qty is not null;
$$;

create or replace function public.get_or_create_wallet(
  p_user uuid, p_merchant uuid, p_staff_branch uuid
) returns public.user_stores language plpgsql security definer as $$
declare
  v_scope text;
  v_branch uuid;
  v_wallet public.user_stores;
begin
  select points_scope into v_scope
  from public.merchant_settings where merchant_id = p_merchant;
  v_scope := coalesce(v_scope, 'branch');

  v_branch := case when v_scope = 'merchant' then null else p_staff_branch end;

  -- محاولة جلب المحفظة الموجودة
  if v_branch is null then
    select * into v_wallet from public.user_stores
    where user_id = p_user and merchant_id = p_merchant and branch_id is null;
  else
    select * into v_wallet from public.user_stores
    where user_id = p_user and merchant_id = p_merchant and branch_id = v_branch;
  end if;

  if found then
    return v_wallet;
  end if;

  insert into public.user_stores (user_id, merchant_id, branch_id)
  values (p_user, p_merchant, v_branch)
  returning * into v_wallet;
  return v_wallet;
end;
$$;

-- تطبيق ذرّي على رصيد المحفظة — يمنع فقدان التحديثات والصرف المزدوج تحت التزامن.
-- الخصم والتحقق من الكفاية يتمّان في عبارة UPDATE واحدة (قفل صف ضمني).
-- p_available_delta: موجب = إضافة، سالب = خصم. p_lifetime_delta يزيد فقط (لا ينقص).
-- p_recompute_level: يعيد حساب مستوى الولاء من lifetime. يرمي INSUFFICIENT_POINTS
-- عند عدم كفاية الرصيد. يرجّع الحالة الجديدة + هل تغيّر المستوى.
create or replace function public.wallet_apply(
  p_wallet uuid,
  p_available_delta integer,
  p_lifetime_delta integer default 0,
  p_recompute_level boolean default false
) returns jsonb language plpgsql security definer as $$
declare
  r record;
  v_level uuid;
  v_prev_level uuid;
begin
  select current_level_id into v_prev_level
    from public.user_stores where id = p_wallet;

  update public.user_stores
     set available_points = available_points + p_available_delta,
         lifetime_points  = lifetime_points + greatest(p_lifetime_delta, 0)
   where id = p_wallet
     and available_points + p_available_delta >= 0
   returning id, merchant_id, branch_id, available_points, lifetime_points,
             current_level_id
   into r;

  if not found then
    raise exception 'INSUFFICIENT_POINTS' using errcode = 'P0001';
  end if;

  v_level := r.current_level_id;
  if p_recompute_level then
    select public.level_for(r.merchant_id, r.branch_id, r.lifetime_points)
      into v_level;
    if v_level is not null and v_level is distinct from r.current_level_id then
      update public.user_stores set current_level_id = v_level
        where id = p_wallet;
    else
      v_level := r.current_level_id;
    end if;
  end if;

  return jsonb_build_object(
    'wallet_id', r.id,
    'available_points', r.available_points,
    'lifetime_points', r.lifetime_points,
    'current_level_id', v_level,
    'leveled_up', (v_level is distinct from v_prev_level)
  );
end;
$$;

-- =====================================================================
-- المستويات حسب النطاق: لو نطاق النقاط 'branch' والفرع له مستويات خاصة
-- نستخدمها، وإلا نرجع لمستويات الستور العامة (branch_id = NULL).
-- p_branch = فرع المحفظة (NULL في الوضع المشترك).
-- =====================================================================
create or replace function public.levels_scope_branch(
  p_merchant uuid, p_branch uuid
) returns uuid language plpgsql stable security definer
set search_path = public as $$
declare v_scope text;
begin
  select points_scope into v_scope
  from public.merchant_settings where merchant_id = p_merchant;
  v_scope := coalesce(v_scope, 'branch');
  if v_scope = 'branch' and p_branch is not null and exists (
       select 1 from public.loyalty_levels
       where merchant_id = p_merchant and branch_id = p_branch) then
    return p_branch;           -- استخدم مستويات هذا الفرع
  end if;
  return null;                 -- استخدم مستويات الستور العامة
end;
$$;

-- يرجّع معرّف المستوى المناسب لرصيد lifetime معيّن (حسب النطاق/الفرع).
create or replace function public.level_for(
  p_merchant uuid, p_branch uuid, p_lifetime integer
) returns uuid language plpgsql stable security definer
set search_path = public as $$
declare v_use uuid; v_level uuid;
begin
  v_use := public.levels_scope_branch(p_merchant, p_branch);
  select id into v_level
  from public.loyalty_levels
  where merchant_id = p_merchant
    and branch_id is not distinct from v_use
    and threshold_lifetime_points <= p_lifetime
  order by threshold_lifetime_points desc
  limit 1;
  return v_level;
end;
$$;

-- يرجّع قائمة المستويات المطبّقة على فرع/ستور (للعرض في تطبيق العميل).
create or replace function public.levels_for(
  p_merchant uuid, p_branch uuid
) returns setof public.loyalty_levels language plpgsql stable security definer
set search_path = public as $$
declare v_use uuid;
begin
  v_use := public.levels_scope_branch(p_merchant, p_branch);
  return query
    select * from public.loyalty_levels
    where merchant_id = p_merchant and branch_id is not distinct from v_use
    order by threshold_lifetime_points;
end;
$$;

-- حالة إعداد المتجر (لقائمة الإعداد الموجّهة في لوحة التحكم).
create or replace function public.merchant_setup_status(p_merchant uuid)
returns json language plpgsql stable security definer
set search_path = public as $$
begin
  if not public.is_merchant_member(p_merchant) then
    raise exception 'forbidden';
  end if;
  return json_build_object(
    'has_branch', exists(select 1 from public.branches where merchant_id = p_merchant),
    'has_reward', exists(select 1 from public.rewards where merchant_id = p_merchant),
    'has_level',  exists(select 1 from public.loyalty_levels where merchant_id = p_merchant),
    'has_staff',  (select count(*) from public.merchant_staff where merchant_id = p_merchant) > 1,
    'has_logo',   exists(select 1 from public.merchants where id = p_merchant and logo_url is not null)
  );
end $$;

-- بلاغات التاجر مع بيانات الراسل (الاسم الأول/الموبايل/الإيميل) — للعرض فقط.
-- security definer لأن RLS يمنع التاجر من قراءة صفوف users مباشرة. مرقّمة.
drop function if exists public.merchant_reports(uuid);
create or replace function public.merchant_reports(
  p_merchant uuid, p_limit int default 30, p_offset int default 0)
returns table (
  id uuid, created_at timestamptz, status text, message text, video_url text,
  branch_id uuid, branch_name text, prize_id uuid, prize_title text,
  sender_name text, sender_phone text, sender_email text,
  subject_label text, last_message_at timestamptz
) language plpgsql stable security definer set search_path = public as $$
begin
  if not public.is_merchant_member(p_merchant) then
    raise exception 'forbidden';
  end if;
  return query
    select r.id, r.created_at, r.status, r.message, r.video_url,
           r.branch_id, b.name, r.prize_id, p.title,
           u.name, u.phone, u.email,
           r.subject_label, r.last_message_at
    from public.reports r
    left join public.branches b on b.id = r.branch_id
    left join public.user_prizes p on p.id = r.prize_id
    left join public.users u on u.id = r.user_id
    where r.merchant_id = p_merchant
    order by r.last_message_at desc
    limit greatest(1, least(p_limit, 100)) offset greatest(0, p_offset);
end $$;

-- هل العنصر متاح في فرع معيّن؟ موحّد (بدون استهداف) = متاح في كل الفروع.
-- لو مستهدَف لفروع: متاح فقط لو الفرع ضمنها (والفرع غير NULL).
create or replace function public.entity_at_branch(
  p_type text, p_id uuid, p_branch uuid
) returns boolean language sql stable security definer
set search_path = public as $$
  select
    not exists (select 1 from public.entity_branches
                where entity_type = p_type and entity_id = p_id)
    or (p_branch is not null and exists (
          select 1 from public.entity_branches
          where entity_type = p_type and entity_id = p_id
            and branch_id = p_branch));
$$;

-- =====================================================================
-- تبديل نطاق النقاط بأمان (بدون فقدان بيانات / تيتيم محافظ).
-- البيانات المعتمدة على النطاق = المحافظ (user_stores) + المستويات (loyalty_levels).
-- (المكافآت/الكوبونات/الحملات/العجلة على مستوى الستور دائمًا — لا تتأثّر.)
--
-- عام → منفصل  : كل بيانات الستور العامة (NULL) تنتقل لأول فرع (أو الفرع المحدّد).
-- منفصل → عام  : mode='adopt' → بيانات الفرع المصدر تصبح العامة، وباقي الفروع
--                تبقى محفوظة (خاملة) للرجوع. mode='fresh' → لا نلمس أي بيانات.
-- =====================================================================
create or replace function public.apply_points_scope(
  p_merchant uuid,
  p_new_scope text,
  p_mode text default 'fresh',
  p_source_branch uuid default null
) returns void language plpgsql security definer set search_path = public as $$
declare
  v_old text;
  v_primary uuid;
begin
  if not public.is_merchant_member(p_merchant) then
    raise exception 'forbidden';
  end if;
  if p_new_scope not in ('merchant','branch') then
    raise exception 'bad_scope';
  end if;

  select points_scope into v_old from public.merchant_settings
    where merchant_id = p_merchant;
  v_old := coalesce(v_old, 'branch');

  if v_old = p_new_scope then
    return; -- لا تغيير
  end if;

  if p_new_scope = 'branch' then
    -- (عام → منفصل): البيانات العامة تروح لأول فرع.
    v_primary := coalesce(
      p_source_branch,
      (select id from public.branches
         where merchant_id = p_merchant and active
         order by created_at limit 1),
      (select id from public.branches
         where merchant_id = p_merchant order by created_at limit 1));
    if v_primary is null then
      raise exception 'no_branch'; -- لازم فرع واحد على الأقل
    end if;
    update public.user_stores set branch_id = v_primary
      where merchant_id = p_merchant and branch_id is null;
    update public.loyalty_levels set branch_id = v_primary
      where merchant_id = p_merchant and branch_id is null;

  else
    -- (منفصل → عام)
    if p_mode = 'adopt' and p_source_branch is not null then
      -- المحافظ: ندمج محافظ الفرع المصدر كمحافظ مشتركة (NULL).
      -- (تنظيف دفاعي لأي محفظة مشتركة شاذّة لنفس العميل قبل النقل.)
      delete from public.user_stores us
        where us.merchant_id = p_merchant and us.branch_id is null
          and exists (select 1 from public.user_stores b
                      where b.merchant_id = p_merchant
                        and b.branch_id = p_source_branch
                        and b.user_id = us.user_id);
      update public.user_stores set branch_id = null
        where merchant_id = p_merchant and branch_id = p_source_branch;
      -- المستويات: مستويات الفرع المصدر تصبح عامة (تنظيف دفاعي للعامة الشاذّة).
      delete from public.loyalty_levels
        where merchant_id = p_merchant and branch_id is null;
      update public.loyalty_levels set branch_id = null
        where merchant_id = p_merchant and branch_id = p_source_branch;
    end if;
    -- mode='fresh': لا نلمس المحافظ/المستويات (تبدأ العامة فارغة، والفروع محفوظة).
  end if;

  update public.merchant_settings set points_scope = p_new_scope
    where merchant_id = p_merchant;
end;
$$;

-- =====================================================================
-- 9) RLS — تفعيل + سياسات العزل
-- القاعدة: العميل يشوف بياناته هو. التاجر يشوف بيانات تاجره فقط.
-- الكتابة الحسّاسة (نقاط/زيارات/استبدال) تتم عبر Edge Functions
-- بمفتاح service_role (يتخطّى RLS) بعد فرض القواعد.
-- =====================================================================
alter table public.users               enable row level security;
alter table public.device_tokens       enable row level security;
alter table public.user_stores         enable row level security;
alter table public.user_visits         enable row level security;
alter table public.points_transactions enable row level security;
alter table public.reward_redemptions  enable row level security;
alter table public.notifications        enable row level security;
alter table public.referrals            enable row level security;
alter table public.merchants            enable row level security;
alter table public.branches             enable row level security;
alter table public.rewards              enable row level security;
alter table public.visit_campaigns      enable row level security;
alter table public.loyalty_levels       enable row level security;
alter table public.entity_branches      enable row level security;
alter table public.coupons              enable row level security;
alter table public.merchant_staff       enable row level security;
alter table public.subscriptions        enable row level security;
alter table public.merchant_settings    enable row level security;
alter table public.merchant_questions   enable row level security;
alter table public.question_options     enable row level security;
alter table public.question_responses   enable row level security;

-- --- العميل: يقرأ/يعدّل صفّه فقط ---
create policy users_self on public.users
  for all using (id = auth.uid()) with check (id = auth.uid());

create policy tokens_self on public.device_tokens
  for all using (user_id = auth.uid()) with check (user_id = auth.uid());

create policy stores_self_read on public.user_stores
  for select using (user_id = auth.uid() or public.is_merchant_member(merchant_id));

create policy visits_self_read on public.user_visits
  for select using (user_id = auth.uid() or public.is_merchant_member(merchant_id));

-- العميل يقرأ حركات نقاطه (عبر ملكيته للمحفظة)، والتاجر يقرأ حركات متجره.
-- بدون هذه السياسة كان سجل النقاط فارغًا تمامًا للعميل (RLS يرفض الكل).
create policy points_tx_read on public.points_transactions
  for select using (
    exists (select 1 from public.user_stores us
              where us.id = user_store_id
                and (us.user_id = auth.uid()
                     or public.is_merchant_member(us.merchant_id))));

create policy notifications_self on public.notifications
  for select using (user_id = auth.uid());
create policy notifications_self_upd on public.notifications
  for update using (user_id = auth.uid());

create policy redemptions_visible on public.reward_redemptions
  for select using (user_id = auth.uid() or public.is_merchant_member(merchant_id));

create policy referrals_self on public.referrals
  for select using (referrer_id = auth.uid() or referee_id = auth.uid());

-- --- المتاجر العامة: أي عميل يقرأ بيانات المتجر/المكافآت/الحملات (للعرض) ---
create policy merchants_public_read on public.merchants
  for select using (status = 'approved' or public.is_merchant_member(id));

create policy branches_read on public.branches
  for select using (active or public.is_merchant_member(merchant_id));

create policy rewards_read on public.rewards
  for select using (active or public.is_merchant_member(merchant_id));

create policy campaigns_read on public.visit_campaigns
  for select using (active or public.is_merchant_member(merchant_id));

create policy levels_read on public.loyalty_levels
  for select using (true);

-- --- التاجر: إدارة كاملة لبيانات تاجره (عزل عبر is_merchant_member) ---
create policy merchants_manage on public.merchants
  for update using (public.is_merchant_member(id));

-- إدارة مقيّدة بالصلاحيات: الإضافة/التعديل/الحذف لكلٍّ منها حسب صلاحية الدور.
create policy branches_insert on public.branches
  for insert with check (public.current_staff_can(merchant_id, 'branches', 'create'));
create policy branches_update on public.branches
  for update using (public.current_staff_can(merchant_id, 'branches', 'edit'))
  with check (public.current_staff_can(merchant_id, 'branches', 'edit'));
create policy branches_delete on public.branches
  for delete using (public.current_staff_can(merchant_id, 'branches', 'delete'));

create policy rewards_insert on public.rewards
  for insert with check (public.current_staff_can(merchant_id, 'rewards', 'create'));
create policy rewards_update on public.rewards
  for update using (public.current_staff_can(merchant_id, 'rewards', 'edit'))
  with check (public.current_staff_can(merchant_id, 'rewards', 'edit'));
create policy rewards_delete on public.rewards
  for delete using (public.current_staff_can(merchant_id, 'rewards', 'delete'));

create policy campaigns_insert on public.visit_campaigns
  for insert with check (public.current_staff_can(merchant_id, 'campaigns', 'create'));
create policy campaigns_update on public.visit_campaigns
  for update using (public.current_staff_can(merchant_id, 'campaigns', 'edit'))
  with check (public.current_staff_can(merchant_id, 'campaigns', 'edit'));
create policy campaigns_delete on public.visit_campaigns
  for delete using (public.current_staff_can(merchant_id, 'campaigns', 'delete'));

create policy levels_insert on public.loyalty_levels
  for insert with check (public.current_staff_can(merchant_id, 'levels', 'create'));
create policy levels_update on public.loyalty_levels
  for update using (public.current_staff_can(merchant_id, 'levels', 'edit'))
  with check (public.current_staff_can(merchant_id, 'levels', 'edit'));
create policy levels_delete on public.loyalty_levels
  for delete using (public.current_staff_can(merchant_id, 'levels', 'delete'));

-- استهداف الفروع: التاجر يدير استهداف عناصره، والجميع يقرأ (للعرض والفلترة).
create policy entity_branches_read on public.entity_branches
  for select using (true);
create policy entity_branches_manage on public.entity_branches
  for all using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

create policy coupons_read on public.coupons
  for select using (public.is_merchant_member(merchant_id));
create policy coupons_insert on public.coupons
  for insert with check (public.current_staff_can(merchant_id, 'coupons', 'create'));
create policy coupons_update on public.coupons
  for update using (public.current_staff_can(merchant_id, 'coupons', 'edit'))
  with check (public.current_staff_can(merchant_id, 'coupons', 'edit'));
create policy coupons_delete on public.coupons
  for delete using (public.current_staff_can(merchant_id, 'coupons', 'delete'));

create policy staff_read on public.merchant_staff
  for select using (public.is_merchant_member(merchant_id));
create policy staff_insert on public.merchant_staff
  for insert with check (public.current_staff_can(merchant_id, 'staff', 'create'));
create policy staff_update on public.merchant_staff
  for update using (public.current_staff_can(merchant_id, 'staff', 'edit'))
  with check (public.current_staff_can(merchant_id, 'staff', 'edit'));
create policy staff_delete on public.merchant_staff
  for delete using (public.current_staff_can(merchant_id, 'staff', 'delete'));

create policy subs_read on public.subscriptions
  for select using (public.is_merchant_member(merchant_id));

-- إعدادات التاجر: العميل يقرأها (عشان يعرف الميزات المفعّلة)، التاجر يعدّلها.
create policy settings_read on public.merchant_settings
  for select using (true);
create policy settings_insert on public.merchant_settings
  for insert with check (public.current_staff_can(merchant_id, 'settings', 'create')
    or public.current_staff_can(merchant_id, 'settings', 'edit'));
create policy settings_update on public.merchant_settings
  for update using (public.current_staff_can(merchant_id, 'settings', 'edit'))
  with check (public.current_staff_can(merchant_id, 'settings', 'edit'));
create policy settings_delete on public.merchant_settings
  for delete using (public.current_staff_can(merchant_id, 'settings', 'delete'));

-- الأسئلة: العميل يقرأ الأسئلة المفعّلة ويشوف إجاباته هو فقط.
-- التاجر يدير أسئلته ويشوف كل إجابات عملائه في لوحته.
create policy questions_read on public.merchant_questions
  for select using (active or public.is_merchant_member(merchant_id));
create policy questions_insert on public.merchant_questions
  for insert with check (public.current_staff_can(merchant_id, 'questions', 'create'));
create policy questions_update on public.merchant_questions
  for update using (public.current_staff_can(merchant_id, 'questions', 'edit'))
  with check (public.current_staff_can(merchant_id, 'questions', 'edit'));
create policy questions_delete on public.merchant_questions
  for delete using (public.current_staff_can(merchant_id, 'questions', 'delete'));

create policy options_read on public.question_options
  for select using (true);
create policy options_insert on public.question_options
  for insert with check (exists (
    select 1 from public.merchant_questions q
    where q.id = question_id and public.current_staff_can(q.merchant_id, 'questions', 'create')));
create policy options_update on public.question_options
  for update using (exists (
    select 1 from public.merchant_questions q
    where q.id = question_id and public.current_staff_can(q.merchant_id, 'questions', 'edit')));
create policy options_delete on public.question_options
  for delete using (exists (
    select 1 from public.merchant_questions q
    where q.id = question_id and public.current_staff_can(q.merchant_id, 'questions', 'edit')));

create policy qresponses_self on public.question_responses
  for select using (user_id = auth.uid() or public.is_merchant_member(merchant_id));

-- ملاحظة: super_admin يُدار بدور منفصل (custom claim) أو عبر لوحة الأدمن
-- بمفتاح service_role. الموافقة على التجار وتفعيل الاشتراك تتم من هناك.

-- =====================================================================
-- 12) عرض العملاء + الإشعارات الجماعية بحد أقصى يحدّده مالك المنصة
-- التاجر يشوف عملاءه (المرتبطين بيه) ويبعتلهم إشعارات، لكن بحد شهري
-- يحدّده مالك النظام (super admin) — مش التاجر.
-- =====================================================================

-- إعدادات المنصة (صف واحد) — يتحكّم فيها مالك النظام فقط (service_role/admin).
create table public.platform_settings (
  id boolean primary key default true check (id),       -- يضمن صفًّا واحدًا
  default_notifications_monthly_quota integer not null default 2000,
  default_customers_view_enabled      boolean not null default true,
  updated_at timestamptz not null default now()
);
insert into public.platform_settings (id) values (true) on conflict (id) do nothing;

-- حدود لكل تاجر — يحدّدها مالك النظام (override للافتراضي). NULL = الافتراضي.
create table public.merchant_limits (
  merchant_id uuid primary key references public.merchants(id) on delete cascade,
  notifications_monthly_quota integer,   -- NULL → افتراضي المنصة
  customers_view_enabled      boolean,   -- NULL → افتراضي المنصة
  updated_at timestamptz not null default now()
);

-- سجل حملات الإشعارات (لقياس الاستهلاك مقابل الحد الشهري).
create table public.notification_campaigns (
  id              uuid primary key default gen_random_uuid(),
  merchant_id     uuid not null references public.merchants(id) on delete cascade,
  title           text not null,
  body            text,
  audience_count  integer not null default 0,
  created_by_staff uuid references public.merchant_staff(id),
  created_at      timestamptz not null default now()
);
create index idx_campaigns_merchant_month
  on public.notification_campaigns(merchant_id, created_at desc);

-- الحد الشهري الفعّال للتاجر (override ثم افتراضي المنصة).
create or replace function public.merchant_notification_quota(p_merchant uuid)
returns integer language sql security definer stable as $$
  select coalesce(
    (select notifications_monthly_quota from public.merchant_limits where merchant_id = p_merchant),
    (select default_notifications_monthly_quota from public.platform_settings where id),
    2000
  );
$$;

-- استهلاك الشهر الحالي + المتبقّي.
create or replace function public.merchant_notification_usage(p_merchant uuid)
returns table(quota integer, used integer, remaining integer)
language sql security definer stable as $$
  with q as (select public.merchant_notification_quota(p_merchant) as quota),
       u as (
         select coalesce(sum(audience_count), 0)::int as used
         from public.notification_campaigns
         where merchant_id = p_merchant
           and created_at >= date_trunc('month', now())
       )
  select q.quota, u.used, greatest(q.quota - u.used, 0)
  from q, u;
$$;

-- عرض عملاء التاجر مع كل خصائصهم (مجمّعة عبر فروعه). محمي بعضوية التاجر.
create or replace function public.merchant_customers(
  p_merchant   uuid,
  p_search     text    default null,
  p_branch     uuid    default null,   -- عملاء زاروا هذا الفرع
  p_level      text    default null,   -- اسم المستوى الحالي
  p_min_points int     default null,   -- نطاق النقاط المتاحة (حد أدنى)
  p_max_points int     default null,   -- نطاق النقاط المتاحة (حد أقصى)
  p_min_visits int     default null,   -- أقل عدد زيارات
  p_active     boolean default null,   -- true=نشِط آخر ٣٠ يوم، false=غير نشِط، null=الكل
  p_limit      int     default 50,
  p_offset     int     default 0
) returns table(
  user_id          uuid,
  name             text,
  phone            text,
  email            text,
  avatar_url       text,
  available_points bigint,
  lifetime_points  bigint,
  level_name       text,
  visits           bigint,
  push_opt_in      boolean,
  branch_name      text,
  first_linked     timestamptz,
  last_activity    timestamptz
) language plpgsql security definer stable
  set search_path = public, pg_temp as $$
begin
  if not public.is_merchant_member(p_merchant) then
    raise exception 'غير مصرّح';
  end if;
  return query
  with agg as (
    select
      u.id as uid, u.name as uname, u.phone as uphone, u.email as uemail,
      u.avatar_url as uavatar, u.push_opt_in as upush,
      sum(us.available_points)::bigint as avail,
      sum(us.lifetime_points)::bigint  as life,
      (array_agg(l.name order by l.threshold_lifetime_points desc)
         filter (where l.name is not null))[1] as lvl,
      (select count(*) from public.user_visits v
         where v.user_id = u.id and v.merchant_id = p_merchant)::bigint as vis,
      -- الفرع المرتبط: فرع آخر زيارة للعميل لدى هذا التاجر.
      (select b.name from public.user_visits v
         join public.branches b on b.id = v.branch_id
        where v.user_id = u.id and v.merchant_id = p_merchant
          and v.branch_id is not null
        order by v.created_at desc limit 1) as branch_nm,
      min(us.first_linked_at) as linked,
      (select max(pt.created_at) from public.points_transactions pt
         join public.user_stores us2 on us2.id = pt.user_store_id
         where us2.user_id = u.id and us2.merchant_id = p_merchant) as last_act
    from public.user_stores us
    join public.users u on u.id = us.user_id
    left join public.loyalty_levels l on l.id = us.current_level_id
    where us.merchant_id = p_merchant
      and us.visible                            -- مخفي لكل متجر
      and u.share_profile_with_merchants        -- مخفي عامًّا (إعداد الملف الشخصي)
      and (p_search is null or p_search = ''
           or u.name  ilike '%' || p_search || '%'
           or u.phone ilike '%' || p_search || '%'
           or u.email ilike '%' || p_search || '%')
      and (p_branch is null or exists (
            select 1 from public.user_visits v
             where v.user_id = u.id and v.merchant_id = p_merchant
               and v.branch_id = p_branch))
    group by u.id, u.name, u.phone, u.email, u.avatar_url, u.push_opt_in
  )
  select uid, uname, uphone, uemail, uavatar, avail, life, lvl, vis, upush,
         branch_nm, linked, last_act
  from agg
  where (p_level      is null or lvl   = p_level)
    and (p_min_points is null or avail >= p_min_points)
    and (p_max_points is null or avail <= p_max_points)
    and (p_min_visits is null or vis   >= p_min_visits)
    and (p_active is null
         or (p_active     and last_act >= now() - interval '30 days')
         or (not p_active and (last_act is null or last_act < now() - interval '30 days')))
  order by linked desc
  limit p_limit offset p_offset;
end;
$$;

-- RLS للجداول الجديدة
alter table public.platform_settings     enable row level security; -- لا سياسات → admin فقط
alter table public.merchant_limits        enable row level security;
alter table public.notification_campaigns enable row level security;

-- التاجر يقرأ حدوده وسجلّ حملاته (للقراءة فقط؛ الكتابة عبر Edge/Admin).
create policy limits_read on public.merchant_limits
  for select using (public.is_merchant_member(merchant_id));
create policy campaigns_read on public.notification_campaigns
  for select using (public.is_merchant_member(merchant_id));

-- =====================================================================
-- 13) أداء: ملخّص لوحة التحكم في استدعاء واحد (بدل ~10 round-trips)
-- يحسب كل مقاييس اللوحة على السيرفر ويرجّعها jsonb واحد.
-- =====================================================================
create or replace function public.dashboard_summary(
  p_merchant uuid, p_branch uuid default null
) returns jsonb language plpgsql security definer stable as $$
declare
  v jsonb;
  v_customers     int;
  v_today         int;
  v_week          int;
  v_points        bigint;
  v_redemptions   int;
  v_distinct      int;
  v_returners     int;
  v_trial_days    int;
  v_activity      jsonb;
begin
  if not public.is_merchant_member(p_merchant) then
    raise exception 'غير مصرّح';
  end if;

  select count(*) into v_customers from public.user_stores
   where merchant_id = p_merchant and (p_branch is null or branch_id = p_branch);

  select count(*) into v_today from public.user_visits
   where merchant_id = p_merchant and visit_date = current_date
     and (p_branch is null or branch_id = p_branch);

  select count(*) into v_week from public.user_visits
   where merchant_id = p_merchant and visit_date >= current_date - 7
     and (p_branch is null or branch_id = p_branch);

  select coalesce(sum(pt.points),0) into v_points
   from public.points_transactions pt
   join public.user_stores us on us.id = pt.user_store_id
   where us.merchant_id = p_merchant and pt.type = 'earn'
     and (p_branch is null or pt.branch_id = p_branch);

  select count(*) into v_redemptions from public.reward_redemptions
   where merchant_id = p_merchant and (p_branch is null or branch_id = p_branch);

  -- معدّل العودة: عملاء بزيارتين فأكثر ÷ إجمالي العملاء الزائرين.
  with per_user as (
    select user_id, count(*) c from public.user_visits
     where merchant_id = p_merchant and (p_branch is null or branch_id = p_branch)
     group by user_id
  )
  select count(*), count(*) filter (where c >= 2) into v_distinct, v_returners
  from per_user;

  select greatest(extract(day from (trial_ends_at - now()))::int, 0)
    into v_trial_days
  from public.subscriptions
  where merchant_id = p_merchant and status = 'trial' and trial_ends_at is not null
  limit 1;

  select coalesce(jsonb_agg(a), '[]'::jsonb) into v_activity from (
    select jsonb_build_object('type', pt.type, 'points', pt.points, 'created_at', pt.created_at) a
    from public.points_transactions pt
    join public.user_stores us on us.id = pt.user_store_id
    where us.merchant_id = p_merchant and (p_branch is null or pt.branch_id = p_branch)
    order by pt.created_at desc limit 8
  ) t;

  v := jsonb_build_object(
    'customers', v_customers,
    'visits_today', v_today,
    'visits_week', v_week,
    'points_awarded', v_points,
    'redemptions', v_redemptions,
    'return_rate', case when v_distinct = 0 then 0
                        else round((v_returners::numeric / v_distinct) * 100, 1) end,
    'trial_days_left', v_trial_days,
    'recent_activity', v_activity
  );
  return v;
end;
$$;

-- فهارس أداء إضافية لأنماط الاستعلام الفعلية.
create index if not exists idx_points_store_created
  on public.points_transactions(user_store_id, created_at desc);
create index if not exists idx_redemptions_merchant
  on public.reward_redemptions(merchant_id);
create index if not exists idx_visits_merchant_user
  on public.user_visits(merchant_id, user_id);

-- =====================================================================
-- 14) الأدوار والصلاحيات (RBAC) — أدوار مخصّصة لكل تاجر
-- صاحب المتجر ينشئ أدوارًا بصلاحيات دقيقة (view/create/edit/delete/manage)
-- لكل مورد، ويربط كل موظف بدور.
-- =====================================================================
create table public.merchant_roles (
  id          uuid primary key default gen_random_uuid(),
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  name        text not null,
  -- permissions: { "resource": ["view","create","edit","delete"], ... }  أو  {"owner": true}
  permissions jsonb not null default '{}'::jsonb,
  is_system   boolean not null default false,   -- أدوار افتراضية لا تُحذف
  created_at  timestamptz not null default now(),
  unique (merchant_id, name)
);

-- ربط الموظف بدور + صلاحية تفعيل الهدايا (للجزء الـ gamification).
alter table public.merchant_staff
  add column if not exists role_id uuid references public.merchant_roles(id) on delete set null;
alter table public.merchant_staff
  add column if not exists can_redeem_prizes boolean not null default true;

-- فحص صلاحية موظف على مورد/إجراء (يُستخدم في Edge Functions + إخفاء عناصر الواجهة).
-- صلاحيات موظف (تُستدعى من Edge Functions). الدور المخصّص — إن وُجد — هو الفيصل،
-- وإلا نرجع لصلاحيات الدور القديم. المالك يمرّ دائمًا.
create or replace function public.staff_can(p_staff uuid, p_resource text, p_action text)
returns boolean language sql security definer stable as $$
  select exists (
    select 1 from public.merchant_staff s
    left join public.merchant_roles r on r.id = s.role_id
    where s.id = p_staff and s.status = 'active' and (
      s.role = 'merchant_owner'
      or (r.permissions ->> 'owner')::boolean is true
      or (s.role_id is not null and (
            (r.permissions -> p_resource) ? p_action
            or (r.permissions -> p_resource) ? 'manage'))
      or (s.role_id is null
            and public.legacy_role_can(s.role, p_resource, p_action))
    )
  );
$$;

-- يزرع الأدوار الافتراضية لتاجر (يُستدعى بعد الموافقة).
create or replace function public.seed_default_roles(p_merchant uuid)
returns void language plpgsql security definer as $$
begin
  insert into public.merchant_roles (merchant_id, name, permissions, is_system) values
    (p_merchant, 'مالك', '{"owner": true}'::jsonb, true),
    (p_merchant, 'مدير', '{"customers":["view"],"rewards":["view","create","edit","delete"],"campaigns":["view","create","edit","delete"],"levels":["view","create","edit","delete"],"coupons":["view","create","edit","delete"],"wheel":["view","create","edit","delete"],"questions":["view","create","edit","delete"],"reports":["view","reply"],"analytics":["view"],"announcements":["view","create"],"points":["create"],"prizes":["redeem"]}'::jsonb, true),
    (p_merchant, 'كاشير', '{"points":["create"],"visits":["create"],"prizes":["redeem"],"customers":["view"]}'::jsonb, true)
  on conflict (merchant_id, name) do nothing;
end;
$$;

alter table public.merchant_roles enable row level security;
create policy roles_read on public.merchant_roles
  for select using (public.is_merchant_member(merchant_id));
create policy roles_insert on public.merchant_roles
  for insert with check (public.current_staff_can(merchant_id, 'roles', 'create'));
create policy roles_update on public.merchant_roles
  for update using (public.current_staff_can(merchant_id, 'roles', 'edit'))
  with check (public.current_staff_can(merchant_id, 'roles', 'edit'));
create policy roles_delete on public.merchant_roles
  for delete using (public.current_staff_can(merchant_id, 'roles', 'delete'));

-- =====================================================================
-- 15) Gamification — عجلة الحظ + الهدايا القابلة للتفعيل بـ QR متغيّر
-- =====================================================================
create table public.lucky_wheels (
  id          uuid primary key default gen_random_uuid(),
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  name        text not null,
  spin_cost_points integer not null default 50,   -- سعر اللفّة (يحدّده التاجر)
  max_spins_per_day integer not null default 0,    -- 0 = غير محدود
  active      boolean not null default true,
  created_at  timestamptz not null default now()
);

create table public.wheel_segments (
  id          uuid primary key default gen_random_uuid(),
  wheel_id    uuid not null references public.lucky_wheels(id) on delete cascade,
  label       text not null,
  kind        text not null check (kind in ('reward','coupon','points','nothing')),
  reward_id   uuid references public.rewards(id) on delete set null,
  points_value integer not null default 0,
  weight      integer not null default 1,          -- احتمالية النصيب (وزن)
  color_hex   text,
  stock       integer,                              -- null = غير محدود
  sort_order  integer not null default 0
);

-- الهدايا التي يملكها العميل (مكاسب العجلة وغيرها) — كل هدية لها QR متغيّر.
create table public.user_prizes (
  id           uuid primary key default gen_random_uuid(),
  user_id      uuid not null references public.users(id) on delete cascade,
  merchant_id  uuid not null references public.merchants(id) on delete cascade,
  source       text not null default 'wheel' check (source in ('wheel','reward','manual','campaign')),
  source_ref   uuid,
  title        text not null,
  description  text,
  kind         text not null check (kind in ('reward','coupon','points','nothing')),
  points_value integer not null default 0,
  status       text not null default 'won' check (status in ('won','delivering','redeemed','expired','canceled')),
  branch_scope uuid references public.branches(id) on delete set null, -- الفرع المؤهّل (لو مقيّد)
  claim_secret text not null default encode(gen_random_bytes(20), 'base64'), -- لتوليد QR متغيّر
  expires_at   timestamptz,
  redeemed_at  timestamptz,
  redeemed_by_staff uuid references public.merchant_staff(id),
  redeemed_branch   uuid references public.branches(id),
  created_at   timestamptz not null default now()
);
create index idx_user_prizes_user on public.user_prizes(user_id) where status = 'won';
create index idx_user_prizes_merchant on public.user_prizes(merchant_id);

-- بلاغات العملاء (إبلاغ عن مشكلة في التسليم) — مع فيديو توثيق اختياري.
create table public.reports (
  id          uuid primary key default gen_random_uuid(),
  user_id     uuid not null references public.users(id) on delete cascade,
  merchant_id uuid references public.merchants(id) on delete set null,
  branch_id   uuid references public.branches(id) on delete set null,
  prize_id    uuid references public.user_prizes(id) on delete set null,
  message     text,
  video_url   text,
  status      text not null default 'open' check (status in ('open','reviewing','resolved')),
  created_at  timestamptz not null default now()
);
create index idx_reports_merchant on public.reports(merchant_id);
create index idx_reports_user on public.reports(user_id);

-- حدّ المعدّل (Rate limiting) لدوال الحافة — عدّاد لكل نافذة زمنية.
create table public.rate_limits (
  bucket     text primary key,
  count      integer not null default 0,
  expires_at timestamptz not null
);

-- يزيد العدّاد ويرجّع true لو ضمن الحد، false لو تجاوزه (يُستدعى بـ service_role).
create or replace function public.rate_limit_hit(
  p_key text, p_max integer, p_window_seconds integer
) returns boolean language plpgsql security definer set search_path = public as $$
declare v_bucket text; v_count integer;
begin
  v_bucket := p_key || ':' ||
    (floor(extract(epoch from now()) / p_window_seconds))::bigint::text;
  insert into public.rate_limits (bucket, count, expires_at)
    values (v_bucket, 1, now() + make_interval(secs => p_window_seconds))
  on conflict (bucket)
    do update set count = public.rate_limits.count + 1
    returning count into v_count;
  return v_count <= p_max;
end $$;

create or replace function public.purge_rate_limits()
returns void language sql security definer set search_path = public as $$
  delete from public.rate_limits where expires_at < now();
$$;

alter table public.lucky_wheels  enable row level security;
alter table public.wheel_segments enable row level security;
alter table public.user_prizes    enable row level security;
alter table public.reports        enable row level security;

-- البلاغات: العميل ينشئ/يقرأ بلاغاته، والتاجر يقرأ بلاغات متجره.
create policy reports_insert on public.reports
  for insert with check (user_id = auth.uid());
create policy reports_read_own on public.reports
  for select using (
    user_id = auth.uid()
    or (merchant_id is not null
        and public.current_staff_can(merchant_id, 'reports', 'view')));
-- الأدمن (بانل المنصة الرئيسي) يقرأ كل البلاغات ويعدّل حالتها.
create policy reports_admin_all on public.reports
  for all using (public.is_super_admin())
  with check (public.is_super_admin());

create policy wheels_read on public.lucky_wheels
  for select using (active or public.is_merchant_member(merchant_id));
create policy wheels_insert on public.lucky_wheels
  for insert with check (public.current_staff_can(merchant_id, 'wheel', 'create'));
create policy wheels_update on public.lucky_wheels
  for update using (public.current_staff_can(merchant_id, 'wheel', 'edit'))
  with check (public.current_staff_can(merchant_id, 'wheel', 'edit'));
create policy wheels_delete on public.lucky_wheels
  for delete using (public.current_staff_can(merchant_id, 'wheel', 'delete'));

create policy segments_read on public.wheel_segments
  for select using (true);
create policy segments_insert on public.wheel_segments
  for insert with check (exists (
    select 1 from public.lucky_wheels w
    where w.id = wheel_id and public.current_staff_can(w.merchant_id, 'wheel', 'create')));
create policy segments_update on public.wheel_segments
  for update using (exists (
    select 1 from public.lucky_wheels w
    where w.id = wheel_id and public.current_staff_can(w.merchant_id, 'wheel', 'edit')));
create policy segments_delete on public.wheel_segments
  for delete using (exists (
    select 1 from public.lucky_wheels w
    where w.id = wheel_id and public.current_staff_can(w.merchant_id, 'wheel', 'delete')));

create policy prizes_self on public.user_prizes
  for select using (user_id = auth.uid() or public.is_merchant_member(merchant_id));

-- =====================================================================
-- 16) POS Integration — API لأنظمة الكاشير (Server-to-Server)
-- التاجر يولّد مفاتيح API، وأنظمة الـ POS تستدعي pos-api بالمفتاح.
-- =====================================================================
create table public.pos_api_keys (
  id          uuid primary key default gen_random_uuid(),
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  branch_id   uuid references public.branches(id) on delete set null, -- مفتاح خاص بفرع (اختياري)
  name        text not null,
  key_prefix  text not null,          -- أول أحرف للعرض/التعريف
  key_hash    text not null,          -- sha256 للمفتاح الكامل (لا يُخزَّن المفتاح نفسه)
  active      boolean not null default true,
  last_used_at timestamptz,
  created_at  timestamptz not null default now()
);
create index idx_pos_keys_merchant on public.pos_api_keys(merchant_id);
create unique index idx_pos_keys_hash on public.pos_api_keys(key_hash);

alter table public.pos_api_keys enable row level security;
-- التاجر يقرأ مفاتيحه ويعطّلها؛ الإنشاء عبر Edge Function (service_role).
create policy pos_keys_read on public.pos_api_keys
  for select using (public.is_merchant_member(merchant_id));
create policy pos_keys_update on public.pos_api_keys
  for update using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

-- =====================================================================
-- 17) المهام المجدولة (pg_cron): مكافآت الميلاد + انتهاء الكوبونات
-- =====================================================================

-- إعداد مكافأة الميلاد لكل تاجر (اختياري).
alter table public.merchant_settings
  add column if not exists birthday_reward_points integer not null default 0;
alter table public.merchant_settings
  add column if not exists birthday_reward_title text;

-- يمنح هدايا الميلاد: لكل عميل عيد ميلاده اليوم وله محفظة عند تاجر مفعّل الميزة.
create or replace function public.grant_birthday_rewards()
returns void language plpgsql security definer as $$
declare r record;
begin
  for r in
    select distinct u.id as user_id, us.merchant_id,
           ms.birthday_reward_points, coalesce(ms.birthday_reward_title,'هدية عيد ميلادك') as title
    from public.users u
    join public.user_stores us on us.user_id = u.id
    join public.merchant_settings ms on ms.merchant_id = us.merchant_id
    where ms.enable_birthday
      and u.date_of_birth is not null
      and extract(month from u.date_of_birth) = extract(month from current_date)
      and extract(day   from u.date_of_birth) = extract(day   from current_date)
  loop
    -- لا نكرّر هدية الميلاد لنفس العميل/التاجر في نفس السنة.
    if not exists (
      select 1 from public.user_prizes p
      where p.user_id = r.user_id and p.merchant_id = r.merchant_id
        and p.source = 'manual' and p.kind = 'reward'
        and p.title = r.title
        and date_trunc('year', p.created_at) = date_trunc('year', now())
    ) then
      insert into public.user_prizes (user_id, merchant_id, source, title, kind, points_value, expires_at)
      values (r.user_id, r.merchant_id, 'manual', r.title, 'reward',
              r.birthday_reward_points, now() + interval '14 days');
      insert into public.notifications (user_id, type, title, body, data)
      values (r.user_id, 'birthday', 'كل سنة وأنت طيب!', r.title,
              jsonb_build_object('merchant_id', r.merchant_id));
    end if;
  end loop;
end;
$$;

-- يعطّل الكوبونات المنتهية.
create or replace function public.expire_coupons()
returns void language sql security definer as $$
  update public.coupons set active = false
  where active and valid_to is not null and valid_to < now();
$$;

-- جدولة يومية (تتطلب امتداد pg_cron مفعّلًا في المشروع).
-- ملاحظة: نفّذ هذه الأسطر مرة واحدة (تتجاهل التكرار لو الوظيفة موجودة).
select cron.schedule('birthday-rewards', '0 6 * * *', $$select public.grant_birthday_rewards();$$)
  where not exists (select 1 from cron.job where jobname = 'birthday-rewards');
select cron.schedule('expire-coupons', '15 0 * * *', $$select public.expire_coupons();$$)
  where not exists (select 1 from cron.job where jobname = 'expire-coupons');

-- =====================================================================
-- 18) معالجة الإحالة تلقائيًا — عند أول زيارة مؤكّدة للمُحال
-- (مكافأة الإحالة لا تُصرف إلا بعد حدث حقيقي + تأكيد الجوال عند التسجيل).
-- =====================================================================
create or replace function public.process_referral_on_visit()
returns trigger language plpgsql security definer as $$
declare v_first_visit boolean; v_ref record;
begin
  -- أول زيارة للعميل على الإطلاق؟
  select count(*) = 1 into v_first_visit
  from public.user_visits where user_id = new.user_id;
  if not v_first_visit then return new; end if;

  -- هل هو مُحال بإحالة معلّقة؟
  select * into v_ref from public.referrals
  where referee_id = new.user_id and status = 'pending' limit 1;
  if not found then return new; end if;

  update public.referrals
  set status = 'rewarded', qualifying_event = 'first_visit', reward_granted_at = now()
  where id = v_ref.id;

  -- إشعار الطرفين (المكافأة الفعلية قرار منصّة — تُمنح هنا أو عبر Admin).
  insert into public.notifications (user_id, type, title, body)
  values
    (v_ref.referrer_id, 'referral', 'تمت إحالتك بنجاح!', 'صديقك أتمّ أول زيارة — مكافأتك في الطريق.'),
    (v_ref.referee_id, 'referral', 'مرحبًا بك!', 'لقد انضممت عبر دعوة صديق.');
  return new;
end;
$$;

drop trigger if exists trg_referral_on_visit on public.user_visits;
create trigger trg_referral_on_visit
  after insert on public.user_visits
  for each row execute function public.process_referral_on_visit();

-- =====================================================================
-- 19) Proximity — أقرب الفروع لموقع العميل (لمراقبة geofence ديناميكيًا)
-- ترجّع أقرب N فرعًا من متاجر العميل المرتبط بها فقط.
-- =====================================================================
create or replace function public.nearest_branches(
  p_user uuid, p_lat double precision, p_lng double precision, p_limit int default 20
) returns table(
  branch_id uuid, merchant_id uuid, name text,
  lat double precision, lng double precision, radius_m int, distance_m double precision
) language sql security definer stable as $$
  select b.id, b.merchant_id, b.name, b.lat, b.lng, b.geofence_radius_m,
    (6371000 * acos(least(1,
        cos(radians(p_lat)) * cos(radians(b.lat)) *
        cos(radians(b.lng) - radians(p_lng))
        + sin(radians(p_lat)) * sin(radians(b.lat))))) as distance_m
  from public.branches b
  where b.active and b.lat is not null and b.lng is not null
    and exists (
      select 1 from public.user_stores us
      where us.user_id = p_user and us.merchant_id = b.merchant_id
    )
  order by distance_m asc
  limit p_limit;
$$;

-- =====================================================================
-- 20) التخزين (Storage) — صور الشعارات/المكافآت/الأفاتار
-- =====================================================================
insert into storage.buckets (id, name, public) values
  ('merchant-media', 'merchant-media', true),
  ('avatars', 'avatars', true)
on conflict (id) do nothing;

-- قراءة عامة للصور (الباقتان عامتان)
create policy "media public read" on storage.objects
  for select using (bucket_id in ('merchant-media', 'avatars'));

-- رفع/تعديل صور التاجر (أي مستخدم مصادق؛ التطبيق يتحكّم بالمسارات = merchant_id/..)
create policy "merchant media insert" on storage.objects
  for insert to authenticated with check (bucket_id = 'merchant-media');
create policy "merchant media update" on storage.objects
  for update to authenticated using (bucket_id = 'merchant-media');
create policy "merchant media delete" on storage.objects
  for delete to authenticated using (bucket_id = 'merchant-media');

-- أفاتار العميل: مجلده باسم معرّفه فقط
create policy "avatar manage own" on storage.objects
  for all to authenticated
  using (bucket_id = 'avatars' and (storage.foldername(name))[1] = auth.uid()::text)
  with check (bucket_id = 'avatars' and (storage.foldername(name))[1] = auth.uid()::text);

-- =====================================================================
-- 21) Analytics — Materialized View + ملخّص في استدعاء واحد
-- يحسّن أداء التحليلات (تجميع مسبق + تحديث يومي).
-- =====================================================================
create materialized view if not exists public.mv_daily_visits as
  select merchant_id, branch_id, visit_date as day, count(*)::int as visits
  from public.user_visits
  group by merchant_id, branch_id, visit_date;
create index if not exists idx_mv_daily_visits
  on public.mv_daily_visits(merchant_id, day);
-- فهرس فريد يتيح REFRESH MATERIALIZED VIEW CONCURRENTLY (تحديث بلا قفل القرّاء).
create unique index if not exists uq_mv_daily_visits
  on public.mv_daily_visits(merchant_id, branch_id, day) nulls not distinct;

create or replace function public.refresh_analytics()
returns void language sql security definer as $$
  refresh materialized view public.mv_daily_visits;
$$;

-- ملخّص تحليلات التاجر للفترة (افتراضي آخر 30 يومًا) في jsonb واحد.
create or replace function public.analytics_summary(
  p_merchant uuid, p_branch uuid default null, p_since date default null
) returns jsonb language plpgsql security definer stable as $$
declare
  v_since date := coalesce(p_since, current_date - 30);
  v_new int; v_total int; v_distinct int; v_returners int;
  v_earned bigint; v_redeemed bigint;
  v_top jsonb; v_series jsonb;
begin
  if not public.is_merchant_member(p_merchant) then
    raise exception 'غير مصرّح';
  end if;

  select count(*) into v_total from public.user_stores
   where merchant_id = p_merchant and (p_branch is null or branch_id = p_branch);

  select count(*) into v_new from public.user_stores
   where merchant_id = p_merchant and first_linked_at >= v_since
     and (p_branch is null or branch_id = p_branch);

  with per_user as (
    select user_id, count(*) c from public.user_visits
     where merchant_id = p_merchant and visit_date >= v_since
       and (p_branch is null or branch_id = p_branch)
     group by user_id
  )
  select count(*), count(*) filter (where c >= 2) into v_distinct, v_returners
  from per_user;

  select coalesce(sum(pt.points) filter (where pt.type='earn'),0),
         coalesce(abs(sum(pt.points) filter (where pt.type='redeem')),0)
    into v_earned, v_redeemed
  from public.points_transactions pt
  join public.user_stores us on us.id = pt.user_store_id
  where us.merchant_id = p_merchant and pt.created_at >= v_since
    and (p_branch is null or pt.branch_id = p_branch);

  select coalesce(jsonb_agg(t),'[]'::jsonb) into v_top from (
    select r.name, count(*) as redemptions
    from public.reward_redemptions rr
    join public.rewards r on r.id = rr.reward_id
    where rr.merchant_id = p_merchant and rr.created_at >= v_since
      and (p_branch is null or rr.branch_id = p_branch)
    group by r.name order by redemptions desc limit 5
  ) t;

  select coalesce(jsonb_agg(jsonb_build_object('day', day, 'visits', visits) order by day),'[]'::jsonb)
    into v_series
  from public.mv_daily_visits
  where merchant_id = p_merchant and day >= v_since
    and (p_branch is null or branch_id = p_branch);

  return jsonb_build_object(
    'new_customers', v_new,
    'total_customers', v_total,
    'return_rate', case when v_distinct=0 then 0 else round((v_returners::numeric/v_distinct)*100,1) end,
    'points_distributed', v_earned,
    'points_redeemed', v_redeemed,
    'top_rewards', v_top,
    'visits_series', v_series
  );
end;
$$;

select cron.schedule('refresh-analytics', '30 0 * * *', $$select public.refresh_analytics();$$)
  where not exists (select 1 from cron.job where jobname = 'refresh-analytics');

-- =====================================================================
-- 22) AUDIT FIXES — RLS gaps, storage scoping, leaderboard materialization
-- =====================================================================

-- [CRITICAL] تفعيل RLS على الجدولين المكشوفين + سياسات عزل.
alter table public.coupon_redemptions       enable row level security;
alter table public.proximity_notifications_log enable row level security;

create policy coupon_redemptions_visible on public.coupon_redemptions
  for select using (
    user_id = auth.uid()
    or exists (select 1 from public.coupons c
               where c.id = coupon_id and public.is_merchant_member(c.merchant_id))
  );
create policy proximity_log_self on public.proximity_notifications_log
  for select using (user_id = auth.uid());
-- (الكتابة على الجدولين تتم عبر Edge Functions بمفتاح service_role فقط.)

-- [HIGH] قصر الكتابة في تخزين صور التاجر على مجلد تاجره (أو مجلد logos المؤقت).
drop policy if exists "merchant media insert" on storage.objects;
drop policy if exists "merchant media update" on storage.objects;
drop policy if exists "merchant media delete" on storage.objects;
create policy "merchant media write" on storage.objects
  for all to authenticated
  using (
    bucket_id = 'merchant-media' and (
      (storage.foldername(name))[1] = 'logos'
      or exists (select 1 from public.merchant_staff s
                 where s.user_id = auth.uid() and s.status = 'active'
                   and s.merchant_id::text = (storage.foldername(name))[1])
    )
  )
  with check (
    bucket_id = 'merchant-media' and (
      (storage.foldername(name))[1] = 'logos'
      or exists (select 1 from public.merchant_staff s
                 where s.user_id = auth.uid() and s.status = 'active'
                   and s.merchant_id::text = (storage.foldername(name))[1])
    )
  );

-- [HIGH] ماتيرياليزد فيو لدرجات الصدارة العامة (بدل تجميع حيّ كل مرة).
create materialized view if not exists public.mv_global_scores as
  select us.user_id, sum(us.lifetime_points)::bigint as total_points
  from public.user_stores us
  join public.users u on u.id = us.user_id
  where us.visible                              -- لا تُحتسب المتاجر المخفية لكل متجر
    and u.share_profile_with_merchants          -- من عطّل المشاركة العامة يخرج من الترتيب
  group by us.user_id;
create unique index if not exists idx_mv_global_scores on public.mv_global_scores(user_id);

-- إعادة تعريف دوال الصدارة العامة لتقرأ من الـ MV (أداء O(N) → O(log N)).
create or replace function public.global_leaderboard(p_limit int default 50)
returns table(rank bigint, user_id uuid, display_name text, total_points bigint)
language sql security definer stable as $$
  select row_number() over (order by s.total_points desc) as rank,
         u.id, u.name, s.total_points
  from public.mv_global_scores s
  join public.users u on u.id = s.user_id
  where u.leaderboard_opt_in and s.total_points > 0
  order by s.total_points desc
  limit p_limit;
$$;

create or replace function public.my_global_rank()
returns table(rank bigint, total_points bigint)
language sql security definer stable as $$
  with ranked as (
    select s.user_id, s.total_points,
           row_number() over (order by s.total_points desc) as rnk
    from public.mv_global_scores s
    join public.users u on u.id = s.user_id
    where u.leaderboard_opt_in
  )
  select rnk, total_points from ranked where user_id = auth.uid();
$$;

-- ضمّ تحديث درجات الصدارة لمهمة التحديث اليومية.
create or replace function public.refresh_analytics()
returns void language sql security definer as $$
  refresh materialized view public.mv_daily_visits;
  refresh materialized view public.mv_global_scores;
$$;

-- [MEDIUM-DB] فهارس مركّبة تدعم التحليلات والسجلات.
create index if not exists idx_pt_store_type_created
  on public.points_transactions(user_store_id, type, created_at desc);

-- [PERF] فهارس مفاتيح أجنبية/استعلامات متكرّرة كانت ناقصة (تجنّب seq scans).
create index if not exists idx_pt_staff           on public.points_transactions(staff_id);
create index if not exists idx_rr_user            on public.reward_redemptions(user_id);
create index if not exists idx_rr_reward          on public.reward_redemptions(reward_id);
create index if not exists idx_rr_staff           on public.reward_redemptions(staff_id);
create index if not exists idx_coupon_redemptions_coupon on public.coupon_redemptions(coupon_id);
create index if not exists idx_coupon_redemptions_user   on public.coupon_redemptions(user_id);
create index if not exists idx_user_prizes_spin   on public.user_prizes(user_id, merchant_id, source, created_at);
create index if not exists idx_reports_prize      on public.reports(prize_id);
create index if not exists idx_ncamp_staff        on public.notification_campaigns(created_by_staff);
create index if not exists idx_visits_scanned_by  on public.user_visits(scanned_by_staff_id);
create index if not exists idx_rr_merchant_created
  on public.reward_redemptions(merchant_id, created_at desc);

-- [MEDIUM] فهرس لتسريع مطابقة دعوات الموظفين بالجوال (claim-staff).
create index if not exists idx_staff_pending_phone
  on public.merchant_staff(phone) where user_id is null;

-- =====================================================================
-- 23) SPRINT 1 — Entitlement (F1) · Super-Admin & Audit (F3) ·
--     Idempotency (F4) · Subscription/Trial enforcement · Dormant store
-- =====================================================================

-- F1 · هل التاجر "متاح" الآن؟ (معتمد + غير معلّق + اشتراك/تجربة سارية).
create or replace function public.merchant_entitled(p_merchant uuid)
returns boolean language sql security definer stable as $$
  -- كل تاجر مُعتمَد له وصول أساسي (الباقة المجانية: نقاط + تكرار زيارات).
  -- المزايا المدفوعة تُحجَب عبر plan gating لا عبر منع الدخول كليًّا.
  select exists (
    select 1 from public.merchants m
    where m.id = p_merchant and m.status = 'approved'
  );
$$;

-- F2 · تسجيل تاجر جديد ذاتيًا: ينشئ صف التاجر (pending) ويربط المستخدم الحالي
-- كمالك في معاملة واحدة وبصلاحية مرتفعة (يتجاوز RLS) — لأنه لا توجد سياسة INSERT
-- على merchants، والمالك الجديد ليس عضوًا بعد ليُدرج صف موظفه. يحلّ مشكلة البيضة والدجاجة.
create or replace function public.register_merchant(
  p_business_name text,
  p_business_type text default null,
  p_phone text default null,
  p_email text default null,
  p_cr_number text default null,
  p_logo_url text default null,
  p_address text default null
) returns uuid language plpgsql security definer as $$
declare
  v_uid uuid := auth.uid();
  v_merchant uuid;
begin
  if v_uid is null then
    raise exception 'يجب تسجيل الدخول أولًا';
  end if;
  if p_business_name is null or length(trim(p_business_name)) = 0 then
    raise exception 'اسم النشاط مطلوب';
  end if;
  if exists (select 1 from public.merchant_staff
              where user_id = v_uid and status = 'active') then
    raise exception 'هذا الحساب مرتبط بتاجر بالفعل';
  end if;

  insert into public.merchants
    (business_name, business_type, phone, email, cr_number, logo_url, address, status)
  values
    (trim(p_business_name), p_business_type, p_phone, p_email, p_cr_number,
     p_logo_url, p_address, 'pending')
  returning id into v_merchant;

  insert into public.merchant_staff (user_id, merchant_id, name, role, status)
  values (v_uid, v_merchant, trim(p_business_name), 'merchant_owner', 'active');

  return v_merchant;
end;
$$;

-- F3 · هوية مالك المنصّة + سجل تدقيق عام.
create table if not exists public.super_admins (
  user_id uuid primary key references auth.users(id) on delete cascade,
  created_at timestamptz not null default now()
);
-- لا سياسات → يُقرأ فقط عبر is_super_admin() (security definer). يمنع كشف هوية الأدمن.
alter table public.super_admins enable row level security;
create or replace function public.is_super_admin()
returns boolean language sql security definer stable as $$
  select exists (select 1 from public.super_admins where user_id = auth.uid());
$$;

create table if not exists public.audit_log (
  id          uuid primary key default gen_random_uuid(),
  actor_id    uuid,
  actor_role  text,
  action      text not null,
  entity      text,
  entity_id   text,
  merchant_id uuid,
  details     jsonb,
  created_at  timestamptz not null default now()
);
create index if not exists idx_audit_merchant on public.audit_log(merchant_id, created_at desc);
create index if not exists idx_audit_action on public.audit_log(action, created_at desc);
alter table public.audit_log enable row level security;
create policy audit_admin_read on public.audit_log
  for select using (public.is_super_admin());
create policy audit_merchant_read on public.audit_log
  for select using (merchant_id is not null and public.is_merchant_member(merchant_id));

-- F4 · مفاتيح منع التكرار (Idempotency). الوصول عبر service_role فقط.
create table if not exists public.idempotency_keys (
  key          text primary key,
  endpoint     text not null,
  user_id      uuid,
  merchant_id  uuid,
  request_hash text,
  status       text not null default 'in_progress' check (status in ('in_progress','done')),
  response     jsonb,
  created_at   timestamptz not null default now(),
  expires_at   timestamptz not null default now() + interval '48 hours'
);
create index if not exists idx_idem_expires on public.idempotency_keys(expires_at);
alter table public.idempotency_keys enable row level security; -- لا سياسات → service_role فقط
create or replace function public.purge_idempotency()
returns void language sql security definer as $$
  delete from public.idempotency_keys where expires_at < now();
$$;

-- حجز مفتاح idempotency ذرّيًا (يستبدل قراءة-ثم-كتابة غير الذرّية في الحافة).
-- يرجّع claimed=true لمن يفوز بالحجز (مفتاح جديد أو إعادة حجز مفتاح عالق منتهي
-- المهلة)، وإلا claimed=false مع الحالة/الرد المخزّن. يمنع التنفيذ المزدوج
-- لعملية عالقة أو متزامنة (لا يُعاد تشغيل الجسم مرتين).
create or replace function public.idem_claim(
  p_key text, p_endpoint text, p_user uuid, p_merchant uuid,
  p_stale_seconds int default 120
) returns table(claimed boolean, status text, response jsonb)
language plpgsql security definer as $$
declare v_claimed boolean := false;
begin
  insert into public.idempotency_keys(key, endpoint, user_id, merchant_id, status)
  values (p_key, p_endpoint, p_user, p_merchant, 'in_progress')
  on conflict (key) do update
     set status = 'in_progress', created_at = now()
     where public.idempotency_keys.status = 'in_progress'
       and public.idempotency_keys.created_at
           < now() - make_interval(secs => p_stale_seconds)
  returning true into v_claimed;

  if coalesce(v_claimed, false) then
    return query select true, 'in_progress'::text, null::jsonb;
  else
    return query
      select false, k.status, k.response
      from public.idempotency_keys k where k.key = p_key;
  end if;
end;
$$;

-- Subscription/Trial enforcement (cron يومي يقلب الحالات المنتهية).
create or replace function public.expire_subscriptions()
returns void language sql security definer as $$
  update public.subscriptions set status = 'expired'
   where status = 'trial' and trial_ends_at is not null and trial_ends_at < now();
  update public.subscriptions set status = 'past_due'
   where status = 'active' and current_period_end is not null and current_period_end < now();
$$;

-- نظرة عامة على المنصّة (للأدمن فقط).
create or replace function public.platform_overview()
returns jsonb language plpgsql security definer stable as $$
begin
  if not public.is_super_admin() then raise exception 'forbidden'; end if;
  return jsonb_build_object(
    'merchants_total',     (select count(*) from public.merchants),
    'merchants_pending',   (select count(*) from public.merchants where status='pending'),
    'merchants_active',    (select count(*) from public.merchants where status='approved'),
    'merchants_suspended', (select count(*) from public.merchants where status='suspended'),
    'customers_total',     (select count(*) from public.users),
    'active_subscriptions',(select count(*) from public.subscriptions where status in ('active','trial')),
    'redemptions_30d',     (select count(*) from public.reward_redemptions where created_at > now()-interval '30 days'),
    'points_issued_30d',   (select coalesce(sum(points),0) from public.points_transactions where type='earn' and created_at > now()-interval '30 days')
  );
end; $$;

-- سياسات أدمن: مالك المنصّة يقرأ/يدير كل التجار والاشتراكات والحدود.
create policy merchants_admin_all on public.merchants
  for all using (public.is_super_admin()) with check (public.is_super_admin());
create policy subs_admin_all on public.subscriptions
  for all using (public.is_super_admin()) with check (public.is_super_admin());
create policy limits_admin_all on public.merchant_limits
  for all using (public.is_super_admin()) with check (public.is_super_admin());
create policy platform_admin_all on public.platform_settings
  for all using (public.is_super_admin()) with check (public.is_super_admin());

-- جدولة Sprint-1
select cron.schedule('expire-subscriptions','0 1 * * *', $$select public.expire_subscriptions();$$)
  where not exists (select 1 from cron.job where jobname='expire-subscriptions');
select cron.schedule('purge-idempotency','30 * * * *', $$select public.purge_idempotency();$$)
  where not exists (select 1 from cron.job where jobname='purge-idempotency');
-- تنظيف عدّادات حدّ المعدّل + بلاغات/فيديوهات قديمة (احتفاظ ٩٠ يوم — PDPL).
alter table public.rate_limits enable row level security;
select cron.schedule('purge-rate-limits','*/15 * * * *', $$select public.purge_rate_limits();$$)
  where not exists (select 1 from cron.job where jobname='purge-rate-limits');
select cron.schedule('purge-old-reports','45 1 * * *',
  $$delete from public.reports where created_at < now() - interval '90 days';$$)
  where not exists (select 1 from cron.job where jobname='purge-old-reports');

-- =====================================================================
-- 24) ميزة خصوصية العميل وإدارة التواصل (Customer Visibility & Contact)
--     عمود user_stores.visible (مُعرّف أعلاه في جدول user_stores).
--     • كل ربط (عميل↔تاجر) له visibility مستقلّة → "ظاهر في متجر، مخفي في آخر".
--     • التجميعات (الإحصاءات) تظل تحتسب المخفيين؛ التقارير المُعرِّفة بالهوية لا.
-- =====================================================================

-- فهرس جزئي يسرّع قوائم/صدارة العملاء الظاهرين لكل تاجر.
create index if not exists idx_user_stores_merchant_visible
  on public.user_stores(merchant_id) where visible;

-- RLS: طاقم التاجر يقرأ مباشرةً صفوف عملائه "الظاهرين فقط". هذا يضمن على مستوى
-- قاعدة البيانات (وليس الواجهة) أن العملاء المخفيين لا تُرجَع صفوفهم إطلاقًا —
-- لا في الاستعلام المباشر ولا في اشتراكات الـ Realtime.
drop policy if exists stores_merchant_read on public.user_stores;
create policy stores_merchant_read on public.user_stores
  for select using (visible and public.is_merchant_member(merchant_id));

-- تبديل الخصوصية لمتجر معيّن (العميل فقط على صفوفه). SECURITY DEFINER كي يحدّث
-- العمود دون منح العميل صلاحية UPDATE عامة على user_stores (تمنع تلاعب النقاط).
create or replace function public.set_store_visibility(
  p_merchant uuid, p_visible boolean
) returns void language plpgsql security definer
  set search_path = public, pg_temp as $$
begin
  update public.user_stores
     set visible = p_visible
   where user_id = auth.uid() and merchant_id = p_merchant;
end;
$$;
grant execute on function public.set_store_visibility(uuid, boolean) to authenticated;

-- =====================================================================
-- 25) خصوصية عامة على مستوى الملف الشخصي (share_profile_with_merchants)
--     العمود مُعرّف أعلاه في جدول users. الإنفاذ في merchant_customers،
--     store_leaderboard، mv_global_scores، و send-announcement.
--     هنا: Trigger لجعل تبديل الإعداد ينعكس فورًا في دليل عملاء التاجر
--     عبر الـ Realtime (يلمس صفوف user_stores للعميل — تبقى visible كما هي
--     فيمرّ الحدث عبر سياسة stores_merchant_read، فيُحدّث التاجر قائمته ويُعيد
--     استدعاء merchant_customers التي تستبعد العميل المخفي).
-- =====================================================================
create or replace function public.touch_user_stores_on_sharing_change()
returns trigger language plpgsql
  set search_path = public, pg_temp as $$
begin
  if new.share_profile_with_merchants is distinct from old.share_profile_with_merchants then
    update public.user_stores
       set first_linked_at = first_linked_at      -- لمسة بلا تغيير قيمة → إشارة Realtime
     where user_id = new.id;
  end if;
  return new;
end;
$$;

drop trigger if exists trg_sharing_touch on public.users;
create trigger trg_sharing_touch
  after update of share_profile_with_merchants on public.users
  for each row execute function public.touch_user_stores_on_sharing_change();

-- =====================================================================
-- 26) المتاجر المفضّلة لدى العميل (Favorites)
--     عمود user_stores.is_favorite (مُعرّف أعلاه). تبديل عبر RPC آمنة
--     (لا تمنح العميل صلاحية UPDATE عامة على user_stores).
-- =====================================================================
create or replace function public.set_store_favorite(p_merchant uuid, p_fav boolean)
returns void language plpgsql security definer
  set search_path = public, pg_temp as $$
begin
  update public.user_stores
     set is_favorite = p_fav
   where user_id = auth.uid() and merchant_id = p_merchant;
end;
$$;
grant execute on function public.set_store_favorite(uuid, boolean) to authenticated;

-- =====================================================================
-- 27) التقييمات والمراجعات (Ratings & Reviews)
--     العميل يقيّم متجره المرتبط به (نجوم + تعليق، واحد لكل متجر، قابل للتعديل).
--     التاجر يردّ على المراجعات. الأدمن يُشرف (إخفاء/إظهار) من admin-web.
--     كل الكتابة عبر دوال definer (لا صلاحيات INSERT/UPDATE مباشرة للعميل/التاجر).
-- =====================================================================
create table public.reviews (
  id           uuid primary key default gen_random_uuid(),
  user_id      uuid not null references public.users(id) on delete cascade,
  merchant_id  uuid not null references public.merchants(id) on delete cascade,
  rating       smallint not null check (rating between 1 and 5),
  comment      text check (comment is null or char_length(comment) <= 500),
  merchant_reply      text check (merchant_reply is null or char_length(merchant_reply) <= 500),
  merchant_replied_at timestamptz,
  -- إشراف الأدمن: visible (افتراضي) / hidden (مخفي عن الجمهور).
  status        text not null default 'visible' check (status in ('visible','hidden')),
  hidden_reason text,
  created_at    timestamptz not null default now(),
  updated_at    timestamptz not null default now(),
  unique (user_id, merchant_id)                    -- مراجعة واحدة لكل عميل/تاجر
);
create index idx_reviews_merchant on public.reviews(merchant_id) where status = 'visible';
create index idx_reviews_user on public.reviews(user_id);

alter table public.reviews enable row level security;

-- قراءة: المراجعات المرئية (عامة للمسجّلين) + صاحبها يراها دائمًا + التاجر يرى مراجعات متجره.
create policy reviews_read on public.reviews for select to authenticated
  using (
    status = 'visible'
    or user_id = auth.uid()
    or public.is_merchant_member(merchant_id)
  );

-- العميل: إضافة/تعديل تقييمه لمتجر مرتبط به (upsert). يرجّع id المراجعة.
create or replace function public.upsert_review(
  p_merchant uuid, p_rating smallint, p_comment text default null
) returns uuid language plpgsql security definer
  set search_path = public, pg_temp as $$
declare v_id uuid;
begin
  if p_rating is null or p_rating < 1 or p_rating > 5 then
    raise exception 'rating must be between 1 and 5';
  end if;
  if not exists (select 1 from public.user_stores
                 where user_id = auth.uid() and merchant_id = p_merchant) then
    raise exception 'not a customer of this merchant';
  end if;
  insert into public.reviews (user_id, merchant_id, rating, comment)
    values (auth.uid(), p_merchant, p_rating, nullif(btrim(p_comment), ''))
  on conflict (user_id, merchant_id) do update
    set rating = excluded.rating,
        comment = excluded.comment,
        updated_at = now()
  returning id into v_id;
  return v_id;
end $$;
grant execute on function public.upsert_review(uuid, smallint, text) to authenticated;

-- العميل: حذف تقييمه.
create or replace function public.delete_my_review(p_merchant uuid)
returns void language sql security definer set search_path = public, pg_temp as $$
  delete from public.reviews where user_id = auth.uid() and merchant_id = p_merchant;
$$;
grant execute on function public.delete_my_review(uuid) to authenticated;

-- التاجر: الردّ على مراجعة لمتجره (تمرير نص فارغ = إزالة الردّ).
create or replace function public.reply_to_review(p_review uuid, p_reply text)
returns void language plpgsql security definer
  set search_path = public, pg_temp as $$
declare v_merchant uuid; v_clean text;
begin
  select merchant_id into v_merchant from public.reviews where id = p_review;
  if v_merchant is null then raise exception 'review not found'; end if;
  if not public.is_merchant_member(v_merchant) then raise exception 'forbidden'; end if;
  v_clean := nullif(btrim(p_reply), '');
  update public.reviews
     set merchant_reply = v_clean,
         merchant_replied_at = case when v_clean is null then null else now() end
   where id = p_review;
end $$;
grant execute on function public.reply_to_review(uuid, text) to authenticated;

-- ملخّص تقييم متجر (متوسط + عدد) — لعرضه في تطبيقَي العميل والتاجر.
create or replace function public.merchant_rating(p_merchant uuid)
returns table (avg_rating numeric, review_count bigint)
language sql stable security definer set search_path = public, pg_temp as $$
  select coalesce(round(avg(rating)::numeric, 2), 0), count(*)
  from public.reviews where merchant_id = p_merchant and status = 'visible';
$$;
grant execute on function public.merchant_rating(uuid) to authenticated, anon;

-- العميل: مراجعات متجر المرئية للعرض العام (مع اسم صاحبها، ومراجعتي أولًا).
create or replace function public.store_reviews(p_merchant uuid, p_limit int default 20)
returns table (
  id uuid, rating smallint, comment text, merchant_reply text,
  merchant_replied_at timestamptz, created_at timestamptz,
  user_name text, is_mine boolean
) language sql stable security definer set search_path = public, pg_temp as $$
  select r.id, r.rating, r.comment, r.merchant_reply, r.merchant_replied_at,
         r.created_at, u.name, (r.user_id = auth.uid())
  from public.reviews r
  left join public.users u on u.id = r.user_id
  where r.merchant_id = p_merchant and r.status = 'visible'
  order by (r.user_id = auth.uid()) desc, r.created_at desc
  limit greatest(1, least(p_limit, 50));
$$;
grant execute on function public.store_reviews(uuid, int) to authenticated;

-- التاجر: كل مراجعات متجره (بما فيها المخفية، مع الحالة) للعرض والردّ. مرقّمة.
drop function if exists public.merchant_reviews(uuid);
create or replace function public.merchant_reviews(
  p_merchant uuid, p_limit int default 30, p_offset int default 0)
returns table (
  id uuid, rating smallint, comment text, merchant_reply text,
  merchant_replied_at timestamptz, status text, created_at timestamptz,
  user_name text
) language plpgsql stable security definer set search_path = public, pg_temp as $$
begin
  if not public.is_merchant_member(p_merchant) then raise exception 'forbidden'; end if;
  return query
    select r.id, r.rating, r.comment, r.merchant_reply, r.merchant_replied_at,
           r.status, r.created_at, u.name
    from public.reviews r
    left join public.users u on u.id = r.user_id
    where r.merchant_id = p_merchant
    order by r.created_at desc
    limit greatest(1, least(p_limit, 100)) offset greatest(0, p_offset);
end $$;
grant execute on function public.merchant_reviews(uuid, int, int) to authenticated;

-- =====================================================================
-- 28) محادثة البلاغات (Report conversations) — شات ثلاثي: عميل ↔ تاجر ↔ أدمن.
--     يبني فوق public.reports: ربط عام (subject) + رسائل thread + رد على رسالة
--     + إخفاء رسالة بإشراف الأدمن. الإرفاق على مستوى الرسالة (للأدمن من البانل).
-- =====================================================================

-- ربط عام: يفتح البلاغ/الشات عن أي عنصر (معاملة/مكافأة/جائزة/كوبون/فرع…).
alter table public.reports
  add column if not exists subject_type text,   -- transaction|reward|prize|coupon|branch|review|general
  add column if not exists subject_id   uuid,
  add column if not exists subject_label text,  -- نص للعرض (مثلاً رقم الفاتورة)
  add column if not exists last_message_at timestamptz not null default now();

create table if not exists public.report_messages (
  id              uuid primary key default gen_random_uuid(),
  report_id       uuid not null references public.reports(id) on delete cascade,
  sender_role     text not null check (sender_role in ('customer','merchant','admin')),
  sender_user_id  uuid references auth.users(id) on delete set null, -- مرسِل العميل/موظّف التاجر
  sender_staff_id uuid references public.merchant_staff(id) on delete set null,
  sender_name     text,                          -- اسم المُرسِل (denormalized، خاصةً الأدمن)
  body            text not null check (char_length(body) between 1 and 4000),
  attachment_url  text,                          -- إرفاق (للأدمن من البانل)
  reply_to_id     uuid references public.report_messages(id) on delete set null, -- رد/اقتباس
  hidden          boolean not null default false, -- أخفاها الأدمن عن الطرفين (تظهر له فقط)
  created_at      timestamptz not null default now()
);
create index if not exists idx_report_messages_report
  on public.report_messages(report_id, created_at);

alter table public.report_messages enable row level security;

-- قراءة: الأدمن يرى الكل؛ والطرفان يريان الرسائل غير المخفاة لبلاغاتهما فقط.
create policy report_messages_read on public.report_messages for select using (
  public.is_super_admin()
  or (not hidden and exists (
        select 1 from public.reports r
        where r.id = report_id and (
          r.user_id = auth.uid()
          or (r.merchant_id is not null
              and public.current_staff_can(r.merchant_id, 'reports', 'view')))))
);
-- لا سياسات كتابة: العميل/التاجر عبر RPC؛ الأدمن عبر service_role/PDO (يتجاوز RLS).

-- العميل أو موظّف التاجر يرسل رسالة في بلاغ (نص فقط — لا إرفاق). يرجّع id الرسالة.
create or replace function public.post_report_message(
  p_report uuid, p_body text, p_reply_to uuid default null
) returns uuid language plpgsql security definer set search_path = public, pg_temp as $$
declare
  v_uid uuid := auth.uid();
  v_report public.reports;
  v_role text; v_staff_id uuid; v_name text; v_msg uuid;
  v_clean text := nullif(btrim(p_body), '');
begin
  if v_clean is null or char_length(v_clean) > 4000 then
    raise exception 'invalid message';
  end if;
  select * into v_report from public.reports where id = p_report;
  if v_report.id is null then raise exception 'report not found'; end if;

  if v_report.user_id = v_uid then
    v_role := 'customer';
    select name into v_name from public.users where id = v_uid;
  elsif v_report.merchant_id is not null
        and public.current_staff_can(v_report.merchant_id, 'reports', 'reply') then
    v_role := 'merchant';
    select id, name into v_staff_id, v_name from public.merchant_staff
      where merchant_id = v_report.merchant_id and user_id = v_uid and status = 'active'
      limit 1;
  else
    raise exception 'forbidden';
  end if;

  -- هدف الرد لازم يكون رسالة في نفس البلاغ.
  if p_reply_to is not null and not exists (
      select 1 from public.report_messages where id = p_reply_to and report_id = p_report) then
    p_reply_to := null;
  end if;

  insert into public.report_messages(
      report_id, sender_role, sender_user_id, sender_staff_id, sender_name, body, reply_to_id)
    values (p_report, v_role, v_uid, v_staff_id, v_name, v_clean, p_reply_to)
    returning id into v_msg;

  update public.reports
     set last_message_at = now(),
         status = case when status = 'resolved' then 'reviewing' else status end
   where id = p_report;

  -- إشعار داخل التطبيق: لو التاجر ردّ → بلّغ صاحب البلاغ.
  if v_role = 'merchant' then
    insert into public.notifications(user_id, type, title, body, data)
      values (v_report.user_id, 'report_reply', 'رد جديد على بلاغك',
              'لديك رد جديد على بلاغك من المتجر',
              jsonb_build_object('report_id', p_report));
  end if;

  return v_msg;
end $$;
grant execute on function public.post_report_message(uuid, text, uuid) to authenticated;

-- thread البلاغ (للعميل والتاجر) — مع هوية المُرسِل والرسالة المُقتبَسة، وإخفاء المخفي.
create or replace function public.report_thread(p_report uuid)
returns table (
  id uuid, sender_role text, sender_name text, staff_role text,
  body text, attachment_url text, created_at timestamptz,
  reply_to_id uuid, reply_to_name text, reply_to_body text, is_mine boolean,
  original_body text, edited_at timestamptz
) language plpgsql stable security definer set search_path = public, pg_temp as $$
declare v_report public.reports; v_uid uuid := auth.uid();
begin
  select r.* into v_report from public.reports r where r.id = p_report;
  if v_report.id is null then raise exception 'report not found'; end if;
  if not (v_report.user_id = v_uid
          or (v_report.merchant_id is not null
              and public.current_staff_can(v_report.merchant_id, 'reports', 'view'))
          or public.is_super_admin()) then
    raise exception 'forbidden';
  end if;
  return query
    select m.id, m.sender_role, m.sender_name, ms.role,
           m.body, m.attachment_url, m.created_at,
           m.reply_to_id, rm.sender_name, rm.body,
           (m.sender_user_id = v_uid),
           m.original_body, m.edited_at
    from public.report_messages m
    left join public.merchant_staff ms on ms.id = m.sender_staff_id
    left join public.report_messages rm on rm.id = m.reply_to_id
    where m.report_id = p_report
      and (not m.hidden or public.is_super_admin())
    order by m.created_at;
end $$;
grant execute on function public.report_thread(uuid) to authenticated;

-- قائمة بلاغات العميل (لا توجد شاشة سابقة) — مع اسم المتجر وآخر رسالة.
create or replace function public.my_reports()
returns table (id uuid, merchant_id uuid, merchant_name text, subject_label text,
               status text, last_message_at timestamptz, created_at timestamptz)
language sql stable security definer set search_path = public, pg_temp as $$
  select r.id, r.merchant_id, m.business_name, r.subject_label,
         r.status, r.last_message_at, r.created_at
  from public.reports r
  left join public.merchants m on m.id = r.merchant_id
  where r.user_id = auth.uid()
  order by r.last_message_at desc;
$$;
grant execute on function public.my_reports() to authenticated;

-- تعبئة رسالة الافتتاح للبلاغات الموجودة (البلاغ الأصلي = أول رسالة من العميل).
insert into public.report_messages(report_id, sender_role, sender_user_id, sender_name,
                                   body, attachment_url, created_at)
select r.id, 'customer', r.user_id, u.name,
       coalesce(nullif(btrim(r.message), ''), '(بلاغ بدون نص)'), r.video_url, r.created_at
from public.reports r
left join public.users u on u.id = r.user_id
where not exists (select 1 from public.report_messages m where m.report_id = r.id);

-- =====================================================================
-- 29) تدقيق رسائل الموظّفين — صاحب المتجر يرى رسائل موظّف معيّن داخل بلاغاته.
--     (الأدمن يستعرضها من admin-web عبر PDO مباشرة.)
-- =====================================================================
drop function if exists public.merchant_staff_messages(uuid, uuid);
create or replace function public.merchant_staff_messages(
  p_merchant uuid, p_staff uuid default null,
  p_limit int default 30, p_offset int default 0
) returns table (
  message_id uuid, report_id uuid, staff_id uuid, staff_name text, staff_role text,
  body text, hidden boolean, created_at timestamptz,
  customer_name text, subject_label text
) language plpgsql stable security definer set search_path = public, pg_temp as $$
begin
  -- صلاحية إدارة الموظّفين (المالك افتراضيًا) — تدقيق حسّاس.
  if not public.current_staff_can(p_merchant, 'staff', 'view') then
    raise exception 'forbidden';
  end if;
  return query
    select m.id, m.report_id, m.sender_staff_id, ms.name, ms.role,
           m.body, m.hidden, m.created_at, u.name, r.subject_label
    from public.report_messages m
    join public.reports r on r.id = m.report_id and r.merchant_id = p_merchant
    left join public.merchant_staff ms on ms.id = m.sender_staff_id
    left join public.users u on u.id = r.user_id
    where m.sender_role = 'merchant'
      and (p_staff is null or m.sender_staff_id = p_staff)
    order by m.created_at desc
    limit greatest(1, least(p_limit, 100)) offset greatest(0, p_offset);
end $$;
grant execute on function public.merchant_staff_messages(uuid, uuid, int, int) to authenticated;

-- =====================================================================
-- 30) تعديل رسالة مع شفافية — يحفظ النص الأصلي ويعلّم أنها عُدّلت (يراها الكل).
-- =====================================================================
alter table public.report_messages
  add column if not exists original_body text,        -- النص قبل أول تعديل
  add column if not exists edited_at timestamptz;     -- وقت آخر تعديل

-- المُرسِل يعدّل رسالته (العميل/موظّف التاجر). الأدمن يعدّل من admin-web (PDO).
create or replace function public.edit_report_message(p_message uuid, p_new_body text)
returns void language plpgsql security definer set search_path = public, pg_temp as $$
declare v_msg public.report_messages; v_uid uuid := auth.uid();
        v_clean text := nullif(btrim(p_new_body), '');
begin
  if v_clean is null or char_length(v_clean) > 4000 then raise exception 'invalid message'; end if;
  select * into v_msg from public.report_messages where id = p_message;
  if v_msg.id is null then raise exception 'message not found'; end if;
  if v_msg.sender_user_id is distinct from v_uid then raise exception 'forbidden'; end if;
  update public.report_messages
     set original_body = coalesce(original_body, body),  -- احفظ الأصل أول مرة فقط
         body = v_clean,
         edited_at = now()
   where id = p_message;
end $$;
grant execute on function public.edit_report_message(uuid, text) to authenticated;

-- =====================================================================
-- 31) سجل نشاط التاجر (Audit trail) — مين عمل كل أكشن (مهم مع تعدّد الموظفين).
--     • تعديلات الإعداد (مكافآت/مستويات/كوبونات/حملات/أسئلة/عجلة/فروع/موظفين/
--       أدوار/إعدادات) تُلتقط تلقائيًا عبر Trigger (الفاعل = auth.uid → موظّف).
--     • العمليات (نقاط/استرداد/زيارة) تُسجَّل صراحةً من دوال الحافة (staff_id معروف).
-- =====================================================================
create table if not exists public.merchant_activity_log (
  id          bigint generated always as identity primary key,
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  staff_id    uuid references public.merchant_staff(id) on delete set null,
  staff_name  text,                 -- لقطة اسم الفاعل (يبقى ولو حُذف الموظّف)
  staff_phone text,                 -- موبايل الفاعل (لتمييز الشخص نفسه)
  staff_user_id uuid,               -- حساب الفاعل (هوية شخصية ثابتة)
  actor_role  text,                 -- دور الفاعل وقتها (سياق إضافي فقط)
  action      text not null,        -- create|update|delete|grant_points|redeem_reward|redeem_prize|record_visit|apply_coupon
  entity_type text not null,        -- reward|level|coupon|campaign|question|wheel|branch|staff|role|settings|points|prize|visit|coupon_use
  entity_id   uuid,
  summary     text,                 -- نص للعرض
  meta        jsonb,
  created_at  timestamptz not null default now()
);
create index if not exists idx_mactivity_merchant_created
  on public.merchant_activity_log(merchant_id, created_at desc);
create index if not exists idx_mactivity_staff
  on public.merchant_activity_log(staff_id, created_at desc);

alter table public.merchant_activity_log enable row level security;
-- قراءة: المالك (صلاحية staff) لمتجره؛ والأدمن عبر admin-web (PDO).
create policy mactivity_read on public.merchant_activity_log for select using (
  public.is_super_admin() or public.current_staff_can(merchant_id, 'staff', 'view')
);
-- لا كتابة مباشرة: عبر دالة definer فقط.

-- تسجيل نشاط (يُستدعى من Trigger أو من دوال الحافة مع staff_id معروف).
create or replace function public.log_merchant_activity(
  p_merchant uuid, p_action text, p_entity_type text,
  p_entity_id uuid default null, p_summary text default null,
  p_meta jsonb default null, p_staff_id uuid default null
) returns void language plpgsql security definer set search_path = public, pg_temp as $$
declare v_sid uuid := p_staff_id; v_name text; v_role text; v_phone text; v_uid uuid;
begin
  if p_merchant is null then return; end if;
  if v_sid is null then
    select id, name, role, phone, user_id into v_sid, v_name, v_role, v_phone, v_uid
    from public.merchant_staff
    where merchant_id = p_merchant and user_id = auth.uid() and status = 'active'
    limit 1;
  else
    select name, role, phone, user_id into v_name, v_role, v_phone, v_uid
    from public.merchant_staff where id = v_sid;
  end if;
  -- موبايل الشخص: من سجل الموظّف، وإلا من حساب المستخدم.
  if (v_phone is null or v_phone = '') and v_uid is not null then
    select phone into v_phone from public.users where id = v_uid;
  end if;
  insert into public.merchant_activity_log(
      merchant_id, staff_id, staff_name, staff_phone, staff_user_id, actor_role,
      action, entity_type, entity_id, summary, meta)
    values (p_merchant, v_sid, v_name, v_phone, v_uid, v_role, p_action,
            p_entity_type, p_entity_id, p_summary, p_meta);
end $$;
grant execute on function public.log_merchant_activity(uuid, text, text, uuid, text, jsonb, uuid)
  to authenticated, service_role;

-- Trigger عام لجداول الإعداد — يلتقط من/ماذا تلقائيًا (لا يُفشل العملية الأصلية أبدًا).
create or replace function public.tg_log_mgmt()
returns trigger language plpgsql security definer set search_path = public, pg_temp as $$
declare v_j jsonb; v_mer uuid; v_id uuid; v_summary text;
begin
  -- نُسجّل أفعال الموظفين فقط (auth.uid موجود). كتابات النظام/الحافة (service_role
  -- مثل decrement_stock وقت الاسترداد، أو الكرون) تُتجاهَل لتفادي الضوضاء.
  if auth.uid() is null then return null; end if;
  v_j := to_jsonb(coalesce(NEW, OLD));
  v_mer := nullif(v_j->>'merchant_id','')::uuid;
  v_id  := nullif(v_j->>'id','')::uuid;
  v_summary := coalesce(v_j->>'name', v_j->>'title', v_j->>'code', v_j->>'business_name');
  begin
    perform public.log_merchant_activity(
      v_mer, lower(TG_OP), TG_ARGV[0], v_id, v_summary, null, null);
  exception when others then null; -- التسجيل أفضل جهد — لا يعطّل الكتابة الأصلية
  end;
  return null; -- AFTER trigger
end $$;

-- ربط الـTrigger بجداول الإعداد.
create trigger log_activity after insert or update or delete on public.rewards
  for each row execute function public.tg_log_mgmt('reward');
create trigger log_activity after insert or update or delete on public.loyalty_levels
  for each row execute function public.tg_log_mgmt('level');
create trigger log_activity after insert or update or delete on public.coupons
  for each row execute function public.tg_log_mgmt('coupon');
create trigger log_activity after insert or update or delete on public.visit_campaigns
  for each row execute function public.tg_log_mgmt('campaign');
create trigger log_activity after insert or update or delete on public.merchant_questions
  for each row execute function public.tg_log_mgmt('question');
create trigger log_activity after insert or update or delete on public.lucky_wheels
  for each row execute function public.tg_log_mgmt('wheel');
create trigger log_activity after insert or update or delete on public.branches
  for each row execute function public.tg_log_mgmt('branch');
create trigger log_activity after insert or update or delete on public.merchant_staff
  for each row execute function public.tg_log_mgmt('staff');
create trigger log_activity after insert or update or delete on public.merchant_roles
  for each row execute function public.tg_log_mgmt('role');
create trigger log_activity after insert or update or delete on public.merchant_settings
  for each row execute function public.tg_log_mgmt('settings');

-- قائمة سجل النشاط للتاجر (مرقّمة، فلتر موظّف اختياري) — صلاحية staff (المالك).
create or replace function public.merchant_activity(
  p_merchant uuid, p_staff uuid default null,
  p_limit int default 30, p_offset int default 0
) returns table (
  id bigint, staff_id uuid, staff_name text, staff_phone text, staff_user_id uuid,
  actor_role text, action text, entity_type text, entity_id uuid, summary text,
  meta jsonb, created_at timestamptz
) language plpgsql stable security definer set search_path = public, pg_temp as $$
begin
  if not public.current_staff_can(p_merchant, 'staff', 'view') then
    raise exception 'forbidden';
  end if;
  return query
    select a.id, a.staff_id, a.staff_name, a.staff_phone, a.staff_user_id,
           a.actor_role, a.action, a.entity_type,
           a.entity_id, a.summary, a.meta, a.created_at
    from public.merchant_activity_log a
    where a.merchant_id = p_merchant
      and (p_staff is null or a.staff_id = p_staff)
    order by a.created_at desc
    limit greatest(1, least(p_limit, 100)) offset greatest(0, p_offset);
end $$;
grant execute on function public.merchant_activity(uuid, uuid, int, int) to authenticated;

-- تنظيف دوري: احذف سجلات أقدم من سنة.
create or replace function public.purge_activity_log() returns void
language sql security definer set search_path = public as $$
  delete from public.merchant_activity_log where created_at < now() - interval '365 days';
$$;
select cron.schedule('purge-activity-log','40 1 * * *', $$select public.purge_activity_log();$$)
  where not exists (select 1 from cron.job where jobname = 'purge-activity-log');

-- =====================================================================
-- 32) إحالة يموّلها التاجر (per‑merchant) + حضور (Geofence/WiFi) — الأساس.
--     • الربط عام قابل للتغيير؛ يُحتسب ويُقفل لكل متجر عند أول دخول للمُحال إليه.
--     • مسار مكافآت تراكمي يحدّده التاجر؛ كل مرحلة تُمنح مرة.
--     • حضور: الفرع له موقع + نطاق + (اختياري) بصمات WiFi.
-- =====================================================================

-- بصمات راوتر الفرع (اختياري) — إشارة حضور أدقّ داخل الأماكن المقفولة.
alter table public.branches add column if not exists wifi_bssids text[];

-- المؤشّر العام: مين يحيل المستخدم حاليًا (يتغيّر لحد ما يتقفل لكل متجر).
alter table public.users add column if not exists current_referrer_id uuid references public.users(id);

-- برنامج إحالة التاجر (مسار تراكمي).
create table if not exists public.referral_programs (
  merchant_id uuid primary key references public.merchants(id) on delete cascade,
  enabled     boolean not null default false,
  -- milestones: [{"count":3,"reward_points":50,"label":"قهوة مجانية"}, ...]
  milestones  jsonb not null default '[]'::jsonb,
  referee_reward_points int not null default 0,   -- مكافأة ترحيب للصاحب الجديد (اختياري)
  updated_at  timestamptz not null default now()
);
alter table public.referral_programs enable row level security;
create policy refprog_read on public.referral_programs for select to authenticated using (true);

-- سجل إحالة لكل (تاجر، مُحال إليه) — يتقفل عند أول دخول (unique).
create table if not exists public.merchant_referrals (
  id uuid primary key default gen_random_uuid(),
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  referee_id  uuid not null references public.users(id) on delete cascade,
  referrer_id uuid not null references public.users(id) on delete cascade,
  branch_id   uuid references public.branches(id) on delete set null,
  counted_at  timestamptz not null default now(),
  unique (merchant_id, referee_id)
);
create index if not exists idx_mref_referrer on public.merchant_referrals(merchant_id, referrer_id);
alter table public.merchant_referrals enable row level security;
create policy mref_read on public.merchant_referrals for select using (
  referee_id = auth.uid() or referrer_id = auth.uid()
  or public.current_staff_can(merchant_id, 'customers', 'view') or public.is_super_admin()
);

-- منع تكرار منح المرحلة.
create table if not exists public.referral_milestone_grants (
  merchant_id uuid not null references public.merchants(id) on delete cascade,
  referrer_id uuid not null references public.users(id) on delete cascade,
  milestone_index int not null,
  granted_at timestamptz not null default now(),
  primary key (merchant_id, referrer_id, milestone_index)
);

-- العميل يضبط/يغيّر مُحيله العام (بكود أو بـ id من QR). حارس ضد إحالة النفس.
create or replace function public.set_referrer(p_code text default null, p_referrer uuid default null)
returns void language plpgsql security definer set search_path = public, pg_temp as $$
declare v_ref uuid := p_referrer;
begin
  if v_ref is null and p_code is not null then
    select id into v_ref from public.users where upper(referral_code) = upper(btrim(p_code));
  end if;
  if v_ref is null then raise exception 'كود إحالة غير صحيح'; end if;
  if v_ref = auth.uid() then raise exception 'لا يمكنك إحالة نفسك'; end if;
  update public.users set current_referrer_id = v_ref where id = auth.uid();
end $$;
grant execute on function public.set_referrer(text, uuid) to authenticated;

create or replace function public.clear_referrer() returns void
language sql security definer set search_path = public, pg_temp as $$
  update public.users set current_referrer_id = null where id = auth.uid();
$$;
grant execute on function public.clear_referrer() to authenticated;

-- الاحتساب: عند إنشاء محفظة جديدة للمُحال إليه في متجر، لو مربوط بمُحيل عميل في
-- نفس المتجر → سجّل الإحالة (تتقفل)، وامنح مراحل المسار للمُحيل (لو مفعّل).
create or replace function public.tg_merchant_referral()
returns trigger language plpgsql security definer set search_path = public, pg_temp as $$
declare v_ref uuid; v_count int; v_prog public.referral_programs;
        v_idx int; v_pts int; v_rwallet uuid;
begin
  select current_referrer_id into v_ref from public.users where id = NEW.user_id;
  if v_ref is null or v_ref = NEW.user_id then return NEW; end if;
  -- المُحيل لازم يكون عميل في نفس المتجر (وله محفظة)
  select id into v_rwallet from public.user_stores
    where user_id = v_ref and merchant_id = NEW.merchant_id limit 1;
  if v_rwallet is null then return NEW; end if;

  begin
    insert into public.merchant_referrals(merchant_id, referee_id, referrer_id, branch_id)
      values (NEW.merchant_id, NEW.user_id, v_ref, NEW.branch_id);
  exception when unique_violation then return NEW;  -- مسجّلة من قبل
  end;

  select * into v_prog from public.referral_programs where merchant_id = NEW.merchant_id;
  -- مكافأة ترحيب للصاحب الجديد (اختياري)
  if found and v_prog.enabled and coalesce(v_prog.referee_reward_points, 0) > 0 then
    update public.user_stores
       set available_points = available_points + v_prog.referee_reward_points,
           lifetime_points  = lifetime_points  + v_prog.referee_reward_points
     where id = NEW.id;
    insert into public.points_transactions(user_store_id, type, points, reason)
      values (NEW.id, 'earn', v_prog.referee_reward_points, 'referral_welcome');
  end if;

  if not found or not v_prog.enabled then return NEW; end if;

  select count(*) into v_count from public.merchant_referrals
    where merchant_id = NEW.merchant_id and referrer_id = v_ref;

  for v_idx in 0 .. coalesce(jsonb_array_length(v_prog.milestones), 0) - 1 loop
    if (v_prog.milestones->v_idx->>'count')::int <= v_count then
      begin
        insert into public.referral_milestone_grants(merchant_id, referrer_id, milestone_index)
          values (NEW.merchant_id, v_ref, v_idx);
        v_pts := coalesce((v_prog.milestones->v_idx->>'reward_points')::int, 0);
        if v_pts > 0 then
          update public.user_stores
             set available_points = available_points + v_pts,
                 lifetime_points  = lifetime_points  + v_pts
           where id = v_rwallet;
          insert into public.points_transactions(user_store_id, type, points, reason)
            values (v_rwallet, 'earn', v_pts, 'referral_reward');
        end if;
        insert into public.notifications(user_id, type, title, body, data)
          values (v_ref, 'referral', 'مكافأة إحالة! 🎁',
                  coalesce(v_prog.milestones->v_idx->>'label', 'وصلت لمرحلة جديدة — مكافأتك أُضيفت'),
                  jsonb_build_object('merchant_id', NEW.merchant_id, 'milestone', v_idx));
      exception when unique_violation then null;  -- ممنوحة من قبل
      end;
    end if;
  end loop;
  return NEW;
end $$;
drop trigger if exists merchant_referral_on_wallet on public.user_stores;
create trigger merchant_referral_on_wallet after insert on public.user_stores
  for each row execute function public.tg_merchant_referral();

-- تقدّم إحالة العميل عند متجر (لعرض المسار). يرجّع json.
create or replace function public.my_referral_progress(p_merchant uuid)
returns jsonb language sql stable security definer set search_path = public, pg_temp as $$
  select jsonb_build_object(
    'enabled', coalesce((select enabled from public.referral_programs where merchant_id = p_merchant), false),
    'milestones', coalesce((select milestones from public.referral_programs where merchant_id = p_merchant), '[]'::jsonb),
    'count', (select count(*) from public.merchant_referrals
              where merchant_id = p_merchant and referrer_id = auth.uid()),
    'granted', coalesce((select array_agg(milestone_index) from public.referral_milestone_grants
              where merchant_id = p_merchant and referrer_id = auth.uid()), '{}')
  );
$$;
grant execute on function public.my_referral_progress(uuid) to authenticated;

-- التاجر يحفظ إعداد برنامج الإحالة (صلاحية settings.edit).
create or replace function public.set_referral_program(
  p_merchant uuid, p_enabled boolean, p_milestones jsonb, p_referee_points int default 0
) returns void language plpgsql security definer set search_path = public, pg_temp as $$
begin
  if not public.current_staff_can(p_merchant, 'settings', 'edit') then
    raise exception 'forbidden';
  end if;
  insert into public.referral_programs(merchant_id, enabled, milestones, referee_reward_points, updated_at)
    values (p_merchant, p_enabled, coalesce(p_milestones,'[]'::jsonb), greatest(0, coalesce(p_referee_points,0)), now())
  on conflict (merchant_id) do update
    set enabled = excluded.enabled, milestones = excluded.milestones,
        referee_reward_points = excluded.referee_reward_points, updated_at = now();
end $$;
grant execute on function public.set_referral_program(uuid, boolean, jsonb, int) to authenticated;

-- حضور: هل الموظّف ضمن نطاق الفرع (GPS بهامش الدقّة أو WiFi)؟ بدون إعداد = مسموح.
create or replace function public.branch_presence_ok(
  p_branch uuid, p_lat double precision, p_lng double precision,
  p_accuracy double precision default 0, p_bssid text default null
) returns boolean language plpgsql stable security definer set search_path = public, pg_temp as $$
declare b public.branches; v_dist double precision; v_has_geo boolean; v_has_wifi boolean;
begin
  select * into b from public.branches where id = p_branch;
  if b.id is null then return true; end if;            -- بلا فرع محدّد = لا فرض
  v_has_geo  := b.lat is not null and b.lng is not null;
  v_has_wifi := b.wifi_bssids is not null and array_length(b.wifi_bssids, 1) > 0;
  if not v_has_geo and not v_has_wifi then return true; end if;  -- غير مُعدّ = مسموح
  -- WiFi: تطابق البصمة = حضور مؤكّد
  if v_has_wifi and p_bssid is not null and p_bssid = any(b.wifi_bssids) then
    return true;
  end if;
  -- GPS: المسافة ناقص الدقّة ضمن النطاق (هافرسين)
  if v_has_geo and p_lat is not null and p_lng is not null then
    v_dist := 2 * 6371000 * asin(sqrt(
      power(sin(radians(p_lat - b.lat) / 2), 2) +
      cos(radians(b.lat)) * cos(radians(p_lat)) *
      power(sin(radians(p_lng - b.lng) / 2), 2)));
    if (v_dist - coalesce(p_accuracy, 0)) <= b.geofence_radius_m then
      return true;
    end if;
  end if;
  return false;
end $$;
grant execute on function public.branch_presence_ok(uuid, double precision, double precision, double precision, text)
  to authenticated, service_role;

-- =====================================================================
-- 33) إشعارات التاجر — إعلام أصحاب/مديري المتجر بالأحداث المهمة
--     (بلاغ جديد، تقييم جديد). تنبيه الاحتيال يُنشأ من verify-qr،
--     وإشعارات الإحالة من tg_merchant_referral.
-- =====================================================================

-- يُدخل إشعارًا لكل أصحاب/مديري المتجر (دون الكاشير). SECURITY DEFINER
-- ليتجاوز RLS عند الكتابة لمستخدمين آخرين.
create or replace function public.notify_merchant_owners(
  p_merchant uuid,
  p_type     text,
  p_title    text,
  p_body     text,
  p_data     jsonb default '{}'::jsonb
) returns void
language plpgsql security definer set search_path = public as $$
begin
  if p_merchant is null then
    return;
  end if;
  insert into public.notifications (user_id, type, title, body, data)
  select distinct ms.user_id, p_type, p_title, p_body,
         coalesce(p_data, '{}'::jsonb) || jsonb_build_object('merchant_id', p_merchant)
  from public.merchant_staff ms
  where ms.merchant_id = p_merchant
    and ms.user_id is not null
    and ms.role in ('merchant_owner', 'manager', 'branch_manager');
end $$;

grant execute on function public.notify_merchant_owners(uuid, text, text, text, jsonb)
  to authenticated, service_role;

-- بلاغ جديد من عميل → إشعار للتاجر.
create or replace function public.tg_notify_new_report() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  perform public.notify_merchant_owners(
    NEW.merchant_id, 'new_report', 'بلاغ جديد من عميل',
    coalesce(nullif(left(NEW.message, 80), ''), 'وصلك بلاغ جديد — افتحه للرد عليه'),
    jsonb_build_object('report_id', NEW.id));
  return NEW;
end $$;

drop trigger if exists trg_notify_new_report on public.reports;
create trigger trg_notify_new_report
  after insert on public.reports
  for each row execute function public.tg_notify_new_report();

-- تقييم جديد → إشعار للتاجر (يُطلق على الإدراج الجديد فقط؛ تعديل التقييم
-- يتحوّل إلى UPDATE في upsert_review فلا يُطلق هذا المُحفّز).
create or replace function public.tg_notify_new_review() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  perform public.notify_merchant_owners(
    NEW.merchant_id, 'review',
    'تقييم جديد ' || repeat('⭐', greatest(NEW.rating, 1)),
    coalesce(nullif(left(NEW.comment, 80), ''), 'ترك أحد عملائك تقييمًا جديدًا'),
    jsonb_build_object('review_id', NEW.id, 'rating', NEW.rating));
  return NEW;
end $$;

drop trigger if exists trg_notify_new_review on public.reviews;
create trigger trg_notify_new_review
  after insert on public.reviews
  for each row execute function public.tg_notify_new_review();

-- =====================================================================
-- 34) تحجيم الباقات (Plan gating) — المجانية: نقاط + تكرار زيارات فقط.
--     الذهبية/المؤسسات (والتجربة): كل المزايا. فرض على الواجهة والباك-إند.
-- =====================================================================

-- توسيع أكواد الباقات لتشمل free/gold/enterprise (مع إبقاء القديمة للتوافق).
alter table public.subscriptions drop constraint if exists subscriptions_plan_check;
alter table public.subscriptions
  add constraint subscriptions_plan_check
  check (plan in ('trial','monthly','yearly','free','gold','enterprise'));

-- مصفوفة المزايا لكل باقة. المجانية = نقاط + تكرار زيارات فقط.
create or replace function public.plan_allows(p_plan text, p_feature text)
returns boolean language sql immutable as $$
  select case
    -- النقاط وتكرار الزيارات متاحة للجميع (بما فيهم المجانية).
    when p_feature in ('points', 'visits') then true
    -- الباقات المدفوعة والتجربة تفتح كل المزايا.
    when coalesce(p_plan, 'free') in ('gold', 'enterprise', 'trial', 'monthly', 'yearly')
      then true
    -- المجانية (أو غير معروف) → باقي المزايا مقفولة.
    else false
  end;
$$;

-- الباقة الفعلية للتاجر: مدفوعة سارية → باقتها؛ تجربة سارية → trial؛ غير ذلك → free.
create or replace function public.merchant_current_plan(p_merchant uuid)
returns text language sql stable security definer set search_path = public as $$
  select coalesce((
    select case
      when s.status = 'active' and s.current_period_end is not null
           and s.current_period_end > now()
        then coalesce(nullif(s.plan, 'trial'), 'gold')
      when s.status = 'trial' and s.trial_ends_at is not null
           and s.trial_ends_at > now()
        then 'trial'
      else 'free'
    end
    from public.subscriptions s
    where s.merchant_id = p_merchant
    order by s.created_at desc
    limit 1
  ), 'free');
$$;

create or replace function public.merchant_plan_allows(p_merchant uuid, p_feature text)
returns boolean language sql stable security definer set search_path = public as $$
  select public.plan_allows(public.merchant_current_plan(p_merchant), p_feature);
$$;

grant execute on function public.merchant_current_plan(uuid) to authenticated, service_role;
grant execute on function public.merchant_plan_allows(uuid, text) to authenticated, service_role;
grant execute on function public.plan_allows(text, text) to authenticated, service_role;

-- مُحفّز عام يمنع إنشاء ميزة مدفوعة على الباقة المجانية (backstop أمني).
create or replace function public.tg_plan_gate() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if not public.merchant_plan_allows(NEW.merchant_id, TG_ARGV[0]) then
    raise exception 'هذه الميزة (%) تتطلب ترقية باقتك', TG_ARGV[0]
      using errcode = 'P0001', hint = 'upgrade_plan';
  end if;
  return NEW;
end $$;

drop trigger if exists trg_plan_gate on public.rewards;
create trigger trg_plan_gate before insert on public.rewards
  for each row execute function public.tg_plan_gate('rewards');

drop trigger if exists trg_plan_gate on public.loyalty_levels;
create trigger trg_plan_gate before insert on public.loyalty_levels
  for each row execute function public.tg_plan_gate('levels');

drop trigger if exists trg_plan_gate on public.coupons;
create trigger trg_plan_gate before insert on public.coupons
  for each row execute function public.tg_plan_gate('coupons');

drop trigger if exists trg_plan_gate on public.lucky_wheels;
create trigger trg_plan_gate before insert on public.lucky_wheels
  for each row execute function public.tg_plan_gate('wheel');

drop trigger if exists trg_plan_gate on public.merchant_questions;
create trigger trg_plan_gate before insert on public.merchant_questions
  for each row execute function public.tg_plan_gate('questions');

drop trigger if exists trg_plan_gate on public.referral_programs;
create trigger trg_plan_gate before insert on public.referral_programs
  for each row execute function public.tg_plan_gate('referrals');

-- =====================================================================
-- 35) شراء مكافأة بالنقاط (امتلاك فوري). يخصم النقاط لحظة الشراء ويضيف الهدية
--     إلى "هداياي" (user_prizes, source='reward') قابلة للاستلام عند الكاشير.
--     لو لم تُستلم خلال المدة → تنتهي وتُرجَع النقاط تلقائيًا. راجع CUSTOMER_APP.
-- =====================================================================

-- مدة صلاحية الهدية المشتراة بالنقاط قبل استرجاعها (أيام).
alter table public.merchant_settings
  add column if not exists reward_prize_ttl_days integer not null default 30;

create or replace function public.purchase_reward_with_points(
  p_reward uuid, p_branch uuid default null, p_user uuid default null
) returns jsonb language plpgsql security definer set search_path = public as $$
declare
  v_uid    uuid := coalesce(p_user, auth.uid());
  v_reward record;
  v_wallet public.user_stores;
  v_ttl    integer;
  v_prize  public.user_prizes;
begin
  if v_uid is null then
    raise exception 'NOT_AUTHENTICATED' using errcode = 'P0001';
  end if;
  -- منع انتحال هوية عميل آخر من واجهة عميل عادي (الحافة تمرّر هوية مُتحقّقة).
  if p_user is not null and auth.uid() is not null and p_user <> auth.uid() then
    raise exception 'NOT_AUTHENTICATED' using errcode = 'P0001';
  end if;

  select id, merchant_id, name, points_cost, stock_qty, active
    into v_reward
  from public.rewards where id = p_reward;
  if not found or not v_reward.active then
    raise exception 'REWARD_UNAVAILABLE' using errcode = 'P0001';
  end if;
  if v_reward.stock_qty is not null and v_reward.stock_qty <= 0 then
    raise exception 'OUT_OF_STOCK' using errcode = 'P0001';
  end if;

  -- المحفظة حسب نطاق نقاط التاجر (merchant/branch).
  v_wallet := public.get_or_create_wallet(v_uid, v_reward.merchant_id, p_branch);

  -- إنقاص المخزون ذرّيًا أولًا (يمنع البيع الزائد تحت التزامن — لا نعتمد على القراءة فوق).
  if v_reward.stock_qty is not null then
    update public.rewards set stock_qty = stock_qty - 1
      where id = p_reward and stock_qty > 0;
    if not found then
      raise exception 'OUT_OF_STOCK' using errcode = 'P0001';
    end if;
  end if;

  -- خصم النقاط ذرّيًا — يرمي INSUFFICIENT_POINTS لو الرصيد لا يكفي (يلغي العملية كلها).
  perform public.wallet_apply(v_wallet.id, -v_reward.points_cost);

  select coalesce(reward_prize_ttl_days, 30) into v_ttl
    from public.merchant_settings where merchant_id = v_reward.merchant_id;
  v_ttl := coalesce(v_ttl, 30);

  -- الهدية المملوكة: نخزّن تكلفة النقاط في points_value لاستردادها عند الانتهاء.
  -- (تأكيد الكاشير لا يصرف points_value إلا لـ kind='points'، فالمكافأة آمنة.)
  insert into public.user_prizes(
    user_id, merchant_id, source, source_ref, title, kind,
    points_value, status, branch_scope, expires_at
  ) values (
    v_uid, v_reward.merchant_id, 'reward', p_reward, v_reward.name, 'reward',
    v_reward.points_cost, 'won', v_wallet.branch_id,
    now() + make_interval(days => v_ttl)
  ) returning * into v_prize;

  -- قيد صرف بإشارة سالبة (موافِق لباقي مسارات الـredeem في المنصّة).
  insert into public.points_transactions(user_store_id, branch_id, type, points, reason)
  values (v_wallet.id, v_wallet.branch_id, 'redeem', -v_reward.points_cost,
          'reward_purchase');

  return jsonb_build_object(
    'prize_id', v_prize.id,
    'title', v_prize.title,
    'claim_secret', v_prize.claim_secret,
    'expires_at', v_prize.expires_at,
    'status', v_prize.status,
    'available_points',
      (select available_points from public.user_stores where id = v_wallet.id)
  );
end;
$$;

grant execute on function public.purchase_reward_with_points(uuid, uuid, uuid)
  to authenticated, service_role;

-- استرجاع النقاط للهدايا المشتراة بالنقاط التي انتهت صلاحيتها قبل الاستلام.
create or replace function public.expire_reward_prizes()
returns void language plpgsql security definer set search_path = public as $$
declare
  p        record;
  v_wallet public.user_stores;
begin
  for p in
    select * from public.user_prizes
    where source = 'reward' and kind = 'reward' and status = 'won'
      and expires_at is not null and expires_at < now()
    for update skip locked      -- يقفل الصف فيمنع استرجاعًا مزدوجًا مع تأكيد الكاشير
  loop
    v_wallet := public.get_or_create_wallet(p.user_id, p.merchant_id, p.branch_scope);
    perform public.wallet_apply(v_wallet.id, p.points_value);
    insert into public.points_transactions(user_store_id, branch_id, type, points, reason)
    values (v_wallet.id, v_wallet.branch_id, 'earn', p.points_value, 'reward_refund');
    -- إرجاع المخزون إن كان محدودًا.
    update public.rewards set stock_qty = stock_qty + 1
      where id = p.source_ref and stock_qty is not null;
    update public.user_prizes set status = 'expired' where id = p.id;
    insert into public.notifications(user_id, type, title, body, data)
    values (p.user_id, 'reward_refund', 'انتهت صلاحية هديتك',
      'تم استرجاع ' || p.points_value || ' نقطة لأن «' || p.title ||
      '» لم تُستلم في الوقت المحدد.',
      jsonb_build_object('merchant_id', p.merchant_id, 'prize_id', p.id));
  end loop;
end;
$$;

select cron.schedule('expire-reward-prizes', '20 1 * * *',
  $$select public.expire_reward_prizes();$$)
  where not exists (select 1 from cron.job where jobname = 'expire-reward-prizes');

-- =====================================================================
-- 36) عمليات استبدال ذرّية (نزاهة النقاط). تنقل الحالة + الخصم + القيد + المخزون
--     في معاملة واحدة بقفل صف، فتمنع الخصم المزدوج/الحالة الجزئية تحت التزامن.
-- =====================================================================

-- تأكيد استرداد مكافأة (الكاشير): pending → confirmed + خصم ذرّي مرة واحدة.
create or replace function public.confirm_reward_redemption(
  p_redemption uuid, p_staff uuid, p_branch uuid
) returns jsonb language plpgsql security definer set search_path = public as $$
declare
  r        record;
  v_wallet public.user_stores;
begin
  select * into r from public.reward_redemptions where id = p_redemption for update;
  if not found then
    raise exception 'REDEMPTION_NOT_FOUND' using errcode = 'P0001';
  end if;
  if r.status <> 'pending' then
    raise exception 'NOT_PENDING' using errcode = 'P0001';
  end if;
  if r.expires_at is not null and r.expires_at < now() then
    update public.reward_redemptions set status = 'expired' where id = r.id;
    raise exception 'EXPIRED' using errcode = 'P0001';
  end if;

  v_wallet := public.get_or_create_wallet(r.user_id, r.merchant_id, p_branch);
  -- خصم ذرّي — يرمي INSUFFICIENT_POINTS لو الرصيد لا يكفي (يلغي المعاملة كلها).
  perform public.wallet_apply(v_wallet.id, -r.points_spent);

  insert into public.points_transactions(user_store_id, branch_id, type, points, staff_id, reason)
  values (v_wallet.id, p_branch, 'redeem', -r.points_spent, p_staff, 'reward_redemption');

  update public.reward_redemptions
     set status = 'confirmed', branch_id = p_branch, staff_id = p_staff,
         confirmed_at = now()
   where id = r.id;

  perform public.decrement_stock(r.reward_id);

  return jsonb_build_object(
    'confirmed', true,
    'reward_id', r.reward_id,
    'user_id', r.user_id,
    'remaining_points',
      (select available_points from public.user_stores where id = v_wallet.id)
  );
end;
$$;

-- استبدال مباشر من الكاشير: خصم + قيد استبدال confirmed + إنقاص مخزون ذرّيًا.
create or replace function public.staff_redeem_reward(
  p_user uuid, p_reward uuid, p_staff uuid, p_branch uuid
) returns jsonb language plpgsql security definer set search_path = public as $$
declare
  v_reward record;
  v_wallet public.user_stores;
begin
  select id, merchant_id, name, points_cost, stock_qty, active
    into v_reward
  from public.rewards where id = p_reward for update;
  if not found or not v_reward.active then
    raise exception 'REWARD_UNAVAILABLE' using errcode = 'P0001';
  end if;
  if v_reward.stock_qty is not null then
    update public.rewards set stock_qty = stock_qty - 1
      where id = p_reward and stock_qty > 0;
    if not found then
      raise exception 'OUT_OF_STOCK' using errcode = 'P0001';
    end if;
  end if;

  v_wallet := public.get_or_create_wallet(p_user, v_reward.merchant_id, p_branch);
  perform public.wallet_apply(v_wallet.id, -v_reward.points_cost);

  insert into public.points_transactions(user_store_id, branch_id, type, points, staff_id, reason)
  values (v_wallet.id, p_branch, 'redeem', -v_reward.points_cost, p_staff, 'reward_redemption');

  insert into public.reward_redemptions(
    user_id, merchant_id, reward_id, branch_id, points_spent, staff_id,
    status, confirmed_at
  ) values (
    p_user, v_reward.merchant_id, p_reward, p_branch, v_reward.points_cost,
    p_staff, 'confirmed', now()
  );

  return jsonb_build_object(
    'redeemed', true,
    'reward_name', v_reward.name,
    'remaining_points',
      (select available_points from public.user_stores where id = v_wallet.id)
  );
end;
$$;

grant execute on function public.confirm_reward_redemption(uuid, uuid, uuid)
  to service_role;
grant execute on function public.staff_redeem_reward(uuid, uuid, uuid, uuid)
  to service_role;
