# HalaOps CRM (PHP Native)

نظام CRM متكامل لشركة هلا كارير، PHP native (بدون أي framework)، MySQL، Tailwind.
يحتوي على: مستخدمين، أدوار وصلاحيات، عملاء، صفقات، مهام، توظيف (مرشحين/شواغر/تعيينات)،
أداء، Gamification (XP/Levels/Badges/Streaks)، إشعارات، AI Copilot، و REST API.

## ✅ المميزات

- **Auth + RBAC** — مستخدمون، أدوار قابلة للتخصيص، صلاحيات حبيبية (granular)
- **Scoped access** — `*.view.own` vs `*.view.all` للحد من الرؤية
- **CSRF** على كل الـ POST forms
- **Bcrypt** لكلمات المرور
- **Audit log** على كل عملية حساسة
- **PDO + Prepared statements** ضد SQL injection
- **Bilingual-ready** — RTL/LTR (الواجهة عربية حاليًا)
- **Tailwind via CDN** — UI نظيف بدون build step

## 📋 المتطلبات

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- mod_rewrite (اختياري)

## 🚀 التثبيت

### 1) ضع المجلد على الخادم
```bash
# المشروع موجود بالفعل في /crm/ بجانب موقع هلا كارير
```

### 2) عدّل `config.php`
ضع بيانات قاعدة البيانات الصحيحة:
```php
define('CRM_DB_HOST', 'localhost');
define('CRM_DB_NAME', 'Start_Main');
define('CRM_DB_USER', '...');
define('CRM_DB_PASS', '...');
define('CRM_BASE_URL', '/crm');  // المسار من جذر الموقع
```

> **ملاحظة:** كل جداول CRM لها بادئة `crm_` فلا تتعارض مع جداول الموقع الحالي.

### 3) شغّل المثبّت
افتح في المتصفح:
```
https://your-domain.com/crm/install.php
```
- **الخطوة 2:** ينشئ كل الجداول.
- **الخطوة 3:** يُنشئ حساب المدير الأول.
- **الخطوة 4:** **احذف `install.php` بعد ذلك للأمان.**

### 4) سجّل الدخول
```
https://your-domain.com/crm/login.php
```

## 🗂 هيكل المشروع

```
crm/
├── config.php              # إعدادات DB + التطبيق
├── install.php             # المثبّت (احذفه بعد الإعداد)
├── index.php               # entrypoint
├── login.php / logout.php
├── dashboard.php
├── includes/
│   ├── db.php              # PDO singleton + helpers
│   ├── auth.php            # session + RBAC
│   ├── helpers.php         # csrf, escape, flash, redirect
│   ├── permissions.php     # كاتالوج الصلاحيات + الأدوار الافتراضية
│   ├── header.php / footer.php / sidebar.php
│   └── forbidden.php
├── modules/
│   ├── users/              # إدارة المستخدمين
│   ├── roles/              # إدارة الأدوار والصلاحيات
│   ├── clients/            # العملاء + جهات الاتصال
│   ├── deals/              # الصفقات (Kanban + List)
│   ├── tasks/              # المهام
│   └── activities/         # سجل الأنشطة
├── assets/
│   ├── style.css
│   └── app.js
└── .htaccess
```

## 👥 الأدوار الافتراضية

| الدور | الوصف |
|------|------|
| **مدير النظام (admin)** | كل الصلاحيات `*` |
| **مدير (manager)** | عرض كل البيانات + إدارة العملاء/الصفقات/المهام |
| **مبيعات (sales)** | يرى ملفاته فقط، يدير ما يخصه |
| **مشاهد (viewer)** | عرض ملفاته فقط بدون تعديل |

تقدر تعدّلها أو تُنشئ أدوار جديدة من **الأدوار → دور جديد**.

## 🔐 كاتالوج الصلاحيات

| المجموعة | الصلاحيات |
|----------|----------|
| لوحة التحكم | `dashboard.view` |
| المستخدمون | `users.view`, `users.manage` |
| الأدوار | `roles.view`, `roles.manage` |
| العملاء | `clients.view.own`, `clients.view.all`, `clients.manage` |
| الصفقات | `deals.view.own`, `deals.view.all`, `deals.manage` |
| المهام | `tasks.view.own`, `tasks.view.all`, `tasks.manage` |
| السجلات | `activities.view` |
| الإعدادات | `settings.manage` |

`*` تعني كل الصلاحيات (super admin).

## 🛡 الأمان

- ✅ Prepared statements في كل query
- ✅ CSRF tokens على كل POST
- ✅ Bcrypt password hashing
- ✅ Session cookie httpOnly + SameSite=Lax
- ✅ Audit log على create/update/delete
- ⚠️ **يُفضّل** تشغيل HTTPS في الإنتاج (الـ session cookie يتفعّل secure تلقائيًا)
- ⚠️ **احذف `install.php`** بعد التثبيت

## 🧪 اختبار سريع

1. سجّل دخول بحساب admin.
2. اذهب لـ **العملاء** → أضف عميل جديد.
3. **الصفقات** → أنشئ صفقة على هذا العميل.
4. **المهام** → أنشئ مهمة مرتبطة بالصفقة.
5. **الأدوار** → أضف دور "Sales Lite" بصلاحيات محدودة.
6. **المستخدمون** → أنشئ موظف بهذا الدور.
7. سجّل خروج، ادخل بحساب الموظف، تأكد إنه يرى فقط ما يخصه.

## ✨ الموديولات الإضافية (V1.1)

### التوظيف
- **المرشحون**: قاعدة بيانات كاملة + مهارات + CV + LinkedIn
- **الشواغر**: تتبع كل شاغر مع headcount + progress bar
- **التعيينات**: pipeline من مُرسل → مقابلة → عرض → تم تعيينه

### الأداء + Gamification (Arena)
- **5 درجات**: Performance، Reliability محسوبة آليًا من events حقيقية
- **XP System**: كل عمل (إكمال مهمة، إغلاق صفقة، تعيين مرشح) يكسب XP
- **Levels**: منحنى نمو `xp = 50 × level^1.6`
- **Streaks**: عدّاد التزام يومي
- **Badges**: 10 شارات افتراضية (common → mythic) قابلة للتوسعة
- **Leaderboard**: ترتيب الموظفين بالـ XP والأداء
- **Anti-cheat**: cap يومي 500 XP لمنع الـ farming

### الإشعارات
- جرس مع عدّاد في الـ header
- إشعارات تلقائية: مهمة موكلة، صفقة مكسوبة، Level Up، Badge Unlock
- صفحة كاملة لإدارة الإشعارات (mark read / clear all)

### AI Copilot
- محادثات مستقلة (multi-conversation)
- يستخدم Claude API لو ضبطت `ANTHROPIC_API_KEY` كمتغير بيئة
- يحقن سياق الحساب الحالي (صفقات/مهام) في الـ system prompt

### REST API
- Token-based auth (`Authorization: Bearer crm_xxx...`)
- Endpoints: `/api/v1/me|clients|deals|tasks|candidates|dashboard`
- إنشاء tokens من **الإعدادات → API Tokens**

## 🔌 استخدام REST API
```bash
# 1) من الإعدادات أنشئ token
# 2) ثم:
curl https://your-domain/crm/api/v1/clients \
  -H "Authorization: Bearer crm_xxxxxxxxxxxxx"

curl -X POST https://your-domain/crm/api/v1/tasks \
  -H "Authorization: Bearer crm_xxx" \
  -H "Content-Type: application/json" \
  -d '{"title":"اتصال متابعة","priority":"high","due_at":"2026-06-01 14:00"}'
```

## 🛣 الخطوات التالية

- Mobile app (React Native/Flutter يستهلك REST API)
- WebSocket للـ realtime
- Workflow Automation builder
- Email/SMS integrations
- تفاصيل أكثر في [`docs/crm/00-MASTER-BLUEPRINT.md`](../docs/crm/00-MASTER-BLUEPRINT.md).

## 📝 الترخيص

داخلي — لاستخدام شركة هلا كارير.
