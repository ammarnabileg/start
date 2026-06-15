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
  name        text not null,                       -- Bronze / Silver / Gold ...
  threshold_lifetime_points integer not null,      -- أقل lifetime للوصول
  reward_description text,
  sort_order  integer not null default 0
);

create table public.user_stores (
  id               uuid primary key default gen_random_uuid(),
  user_id          uuid not null references public.users(id) on delete cascade,
  merchant_id      uuid not null references public.merchants(id) on delete cascade,
  available_points integer not null default 0,     -- القابل للصرف
  lifetime_points  integer not null default 0,      -- لا يُخصم أبدًا → يحدد المستوى
  current_level_id uuid references public.loyalty_levels(id),
  first_linked_at  timestamptz not null default now(),
  unique (user_id, merchant_id)                    -- ⭐ محفظة واحدة لكل (عميل, تاجر)
);

-- =====================================================================
-- 4) الزيارات + الحملات + النقاط
-- =====================================================================
create table public.visit_campaigns (
  id               uuid primary key default gen_random_uuid(),
  merchant_id      uuid not null references public.merchants(id) on delete cascade,
  name             text not null,
  required_visits  integer not null check (required_visits > 0),
  reward_name      text,
  reward_image_url text,
  reward_description text,
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
alter table public.coupons              enable row level security;
alter table public.merchant_staff       enable row level security;
alter table public.subscriptions        enable row level security;

-- --- العميل: يقرأ/يعدّل صفّه فقط ---
create policy users_self on public.users
  for all using (id = auth.uid()) with check (id = auth.uid());

create policy tokens_self on public.device_tokens
  for all using (user_id = auth.uid()) with check (user_id = auth.uid());

create policy stores_self_read on public.user_stores
  for select using (user_id = auth.uid() or public.is_merchant_member(merchant_id));

create policy visits_self_read on public.user_visits
  for select using (user_id = auth.uid() or public.is_merchant_member(merchant_id));

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

create policy branches_manage on public.branches
  for all using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

create policy rewards_manage on public.rewards
  for all using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

create policy campaigns_manage on public.visit_campaigns
  for all using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

create policy levels_manage on public.loyalty_levels
  for all using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

create policy coupons_manage on public.coupons
  for all using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

create policy staff_manage on public.merchant_staff
  for all using (public.is_merchant_member(merchant_id))
  with check (public.is_merchant_member(merchant_id));

create policy subs_read on public.subscriptions
  for select using (public.is_merchant_member(merchant_id));

-- ملاحظة: super_admin يُدار بدور منفصل (custom claim) أو عبر لوحة الأدمن
-- بمفتاح service_role. الموافقة على التجار وتفعيل الاشتراك تتم من هناك.
