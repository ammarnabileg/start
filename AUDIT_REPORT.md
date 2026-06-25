# AUDIT REPORT — AI Recruitment SaaS Platform
**Date:** 2026-06-25  
**Status:** ❌ NOT PRODUCTION READY  
**Total Issues Found:** 52  
**Critical:** 18 | High: 16 | Medium: 12 | Low: 6

---

## EXECUTIVE SUMMARY

تم فحص المشروع بالكامل من الصفر بعد تحليل جميع الـ Views، APIs، Database Schema، AI/HeyGen Integrations، وUser Journeys.

**النتيجة:** المشروع يحتوي على مشاكل حرجة تمنع تشغيله في Production:
- ❌ إنشاء العروض الوظيفية (Offers) سيفشل بالكامل بسبب عدم توافق الـ Schema
- ❌ غرفة المقابلة (Interview Room) معطلة — جميع المتغيرات undefined
- ❌ صفحة Careers لم تُنفَّذ بعد (Stub)
- ❌ 6 endpoints مهمة ستعطي PHP fatal error بسبب Response::paginated()
- ❌ المرشحون لا يتلقون أي بريد إلكتروني (مقابلات ولا عروض)
- ❌ تسريب بيانات بين الشركات (Cross-Tenant Data Leak) في AI endpoint

---

## المرحلة الأولى: خريطة المشروع

### الصفحات الموجودة

| المسار | الملف | الحالة |
|--------|-------|--------|
| `/login` | views/auth/login.php | ✅ يعمل |
| `/register` | views/candidate/register.php | ⚠️ مشكلة في pre-fill |
| `/dashboard` | views/hr/dashboard.php | ✅ يعمل |
| `/jobs` | views/hr/jobs/index.php | ✅ يعمل |
| `/jobs/create` | views/hr/jobs/create.php | ✅ يعمل |
| `/pipeline` | views/hr/pipeline.php | ⚠️ بيانات Mock |
| `/candidates` | views/hr/candidates/index.php | ✅ يعمل |
| `/candidates/{id}` | views/hr/candidates/show.php | ✅ يعمل |
| `/ai-interviews` | views/hr/interviews/index.php | ⚠️ جزئي |
| `/ai-interviews/{id}` | views/hr/interviews/report.php | ✅ يعمل |
| `/human-interviews` | views/hr/human-interviews.php | ⚠️ بيانات Mock |
| `/offers` | views/hr/offers.php | ⚠️ بيانات Mock + API معطل |
| `/talent-pool` | views/hr/talent-pool.php | ⚠️ بيانات Mock |
| `/avatars` | views/hr/avatars.php | ❌ generateVideo() غير موجود |
| `/ai-analytics` | views/hr/ai-analytics.php | ⚠️ جزئي |
| `/users` | views/hr/users.php | ⚠️ بيانات Mock |
| `/roles` | views/hr/roles.php | ⚠️ بيانات Mock |
| `/settings` | views/hr/settings.php | ✅ بعد إصلاح ai_usage |
| `/super/dashboard` | views/super-admin/dashboard.php | ✅ يعمل |
| `/super/companies` | views/super-admin/companies.php | ✅ يعمل |
| `/super/users` | views/super-admin/users.php | ⚠️ جزئي |
| `/super/terminal` | views/super-admin/terminal.php | ⚠️ غير مؤكد |
| `/super/ai-usage` | views/super-admin/ai-analytics.php | ✅ يعمل |
| `/super/settings` | views/super-admin/settings.php | ✅ يعمل |
| `/c/dashboard` | views/candidate/dashboard.php | ⚠️ بيانات Mock |
| `/c/jobs` | views/candidate/jobs.php | ⚠️ بيانات Mock |
| `/c/applications` | views/candidate/applications.php | ⚠️ بيانات Mock |
| `/c/profile` | views/candidate/profile.php | ⚠️ جزئي |
| `/c/offers` | views/candidate/offers.php | ⚠️ بيانات Mock |
| `/interview/{token}` | views/interview/room.php | ❌ متغيرات undefined |
| `/careers/*` | CareerController | ❌ Stub — لم يُنفَّذ |

### الـ APIs الموجودة

| الـ Endpoint | الملف | الحالة |
|-------------|-------|--------|
| `GET/POST /api/v1/jobs` | api/v1/jobs.php | ✅ يعمل |
| `GET/POST /api/v1/candidates` | api/v1/candidates.php | ⚠️ paginated() معطل |
| `GET/POST /api/v1/applications` | api/v1/applications.php | ⚠️ paginated() معطل |
| `GET/POST /api/v1/offers` | api/v1/offers.php | ❌ Schema mismatch كامل |
| `GET/POST /api/v1/interviews` | api/v1/interviews.php | ⚠️ feedback columns خاطئة |
| `GET/POST /api/v1/users` | api/v1/users.php | ⚠️ paginated() معطل |
| `POST /api/v1/ai` | api/v1/ai.php | ❌ Cross-tenant data leak |
| `GET /api/v1/admin` | api/v1/admin.php | ⚠️ paginated() معطل |
| `/api/v1/auth` | api/v1/auth.php | ❌ غير مُوجَّه في index.php |
| `/api/v1/settings` | api/v1/index.php | ✅ يعمل |

### جداول قاعدة البيانات

| الجدول | tenant_id | الاستخدام |
|--------|-----------|-----------|
| tenants | N/A | ✅ |
| users | ✅ | ✅ |
| roles | ✅ | ✅ |
| permissions | ✅ | ✅ |
| role_permissions | via role | ✅ |
| user_roles | via user | ✅ |
| jobs | ✅ | ✅ |
| candidates | ✅ | ✅ |
| applications | ✅ | ✅ |
| interviews | ❌ مفقود | ⚠️ خطر تسريب |
| interview_messages | via interview | ⚠️ |
| interview_evaluations | ❌ مفقود | ⚠️ خطر تسريب |
| interview_feedback | via interview | ❌ أعمدة خاطئة |
| human_interviews | ✅ | ✅ |
| offers | ❌ مفقود | ❌ Schema مكسور |
| talent_pools | ✅ | ✅ |
| avatars | ✅ | ✅ |
| ai_usage_logs | ✅ | ✅ |
| audit_logs | ✅ | ✅ |
| notifications | ✅ | ❌ column names خاطئة |
| candidate_cvs | ✅ | ✅ |
| system_settings | ✅ | ✅ |
| career_page_settings | ✅ | ✅ |

---

## المرحلة الثانية: المشاكل المكتشفة

---

## 🔴 CRITICAL ISSUES — يجب إصلاحها قبل أي شيء

---

### CRITICAL-01: offers table — Schema مكسور بالكامل
**الملف:** `api/v1/offers.php` lines 51-69  
**المشكلة:** الكود يحاول insert في أعمدة غير موجودة في الـ Schema:

| العمود في الكود | الواقع في Schema |
|----------------|-----------------|
| `tenant_id` | ❌ غير موجود |
| `candidate_id` | ❌ غير موجود |
| `job_id` | ❌ غير موجود |
| `created_by` | ❌ غير موجود |
| `title` | ❌ غير موجود |
| `salary_amount` | Schema يحتوي `salary` |
| `salary_currency` | Schema يحتوي `currency` |
| `salary_type` | ❌ غير موجود |
| `benefits` | ❌ غير موجود |
| `conditions` | ❌ غير موجود |
| `offer_letter` | ❌ غير موجود |
| `updated_at` | ❌ غير موجود |
| `sent_at` (line 109) | ❌ غير موجود |
| `responded_at` (line 134) | ❌ غير موجود |

**التأثير:** كل عملية إنشاء أو تحديث offer ستعطي SQL error.  
**الإصلاح:** تحديث Schema لإضافة الأعمدة المفقودة:
```sql
ALTER TABLE offers
  ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL AFTER id,
  ADD COLUMN candidate_id BIGINT UNSIGNED NULL AFTER application_id,
  ADD COLUMN job_id BIGINT UNSIGNED NULL AFTER candidate_id,
  ADD COLUMN created_by BIGINT UNSIGNED NULL,
  ADD COLUMN title VARCHAR(255) NULL,
  ADD COLUMN salary_amount DECIMAL(12,2) NULL,
  ADD COLUMN salary_currency VARCHAR(10) DEFAULT 'USD',
  ADD COLUMN salary_type ENUM('annual','monthly','hourly') DEFAULT 'annual',
  ADD COLUMN benefits JSON NULL,
  ADD COLUMN conditions TEXT NULL,
  ADD COLUMN offer_letter LONGTEXT NULL,
  ADD COLUMN sent_at TIMESTAMP NULL,
  ADD COLUMN responded_at TIMESTAMP NULL,
  ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  ADD KEY idx_offer_tenant (tenant_id);
```

---

### CRITICAL-02: Response::paginated() — Signature مكسور (6 endpoints معطلة)
**الملف:** `core/Response.php` line 18  
**المشكلة:** التوقيع `paginated(array $data, array $meta)` لكن كل المُستدعيين يرسلون 4 معاملات:
```php
Response::paginated($result['data'], $result['total'], $page, 25);
```
**المُتأثرون:** jobs.php، candidates.php، applications.php، offers.php، users.php، admin.php  
**التأثير:** PHP fatal error "Too many arguments" على كل endpoint يعرض قائمة.  
**الإصلاح:** تغيير التوقيع في Response.php:
```php
public static function paginated(array $data, int $total, int $page, int $perPage): void
```

---

### CRITICAL-03: Interview Room — جميع المتغيرات undefined
**الملف:** `modules/Interviews/InterviewRoomController.php`  
**المشكلة:** الـ Controller يُمرِّر `$application` و`$token` فقط، لكن `views/interview/room.php` تحتاج:
- `$interview` — record من جدول interviews
- `$job` — بيانات الوظيفة
- `$candidate` — بيانات المرشح
- `$firstQuestion` — السؤال الأول من AI

**التأثير:** `INTERVIEW_ID = 0` في كل JavaScript calls — المقابلة معطلة بالكامل.  
**الإصلاح:** إضافة في InterviewRoomController بعد تحميل application:
```php
$interview = $db->fetch(
    "SELECT * FROM interviews WHERE application_id = ? ORDER BY created_at DESC LIMIT 1",
    [$application['id']]
);
$firstQuestion = '';
if ($interview) {
    $msg = $db->fetch(
        "SELECT content FROM interview_messages WHERE interview_id = ? AND role = 'ai' ORDER BY id ASC LIMIT 1",
        [$interview['id']]
    );
    $firstQuestion = $msg['content'] ?? 'Tell me about yourself.';
}
$candidate = ['full_name' => $application['candidate_name'] ?? 'Candidate'];
$job = ['title' => $application['job_title'] ?? 'Position'];
```

---

### CRITICAL-04: CareerController — لم يُنفَّذ (Stub)
**الملف:** `modules/Company/CareerController.php` lines 2-8  
**المشكلة:**
```php
public static function handle(string $path, Request $request): void {
    http_response_code(200);
    echo "Career page - coming soon";
}
```
**التأثير:** المرشحون لا يمكنهم رؤية أي وظائف ولا التقديم.  
**الإصلاح:** تنفيذ كامل يعرض الوظائف المنشورة للشركة.

---

### CRITICAL-05: Cross-Tenant Data Leak في ai.php
**الملف:** `api/v1/ai.php` lines 187-189, 199-202  
**المشكلة:** Query بدون tenant_id filter:
```php
WHERE c.id IN ({$placeholders})  // ← لا يوجد AND c.tenant_id = ?
```
**التأثير:** أي مستخدم مُصادق عليه يمكنه قراءة بيانات مرشحين من شركات أخرى.  
**الإصلاح:**
```php
WHERE c.id IN ({$placeholders}) AND c.tenant_id = ?
```

---

### CRITICAL-06: Offers Accept/Decline — لا يوجد فحص ملكية
**الملف:** `api/v1/offers.php` lines 126-143  
**المشكلة:** لا يوجد تحقق أن المستخدم الحالي هو صاحب العرض.  
**التأثير:** مرشح يمكنه قبول/رفض عرض مرشح آخر إذا عرف offer_id.  
**الإصلاح:** إضافة:
```php
if ($offer['candidate_id'] !== Auth::user()['id'] && !Auth::isTenantUser()) {
    Response::error('Forbidden', 403); exit;
}
```

---

### CRITICAL-07: auth.php — غير مُوجَّه في index.php
**الملف:** `api/v1/index.php` switch statement  
**المشكلة:** لا يوجد `case 'auth':` في الـ switch. أيضاً auth.php يستخدم `$GLOBALS['__api']` غير المُعرَّف.  
**التأثير:** `/api/v1/auth` يعطي 404.  
**الإصلاح:** إضافة في switch:
```php
case 'auth':
    require __DIR__ . '/auth.php';
    break;
```

---

### CRITICAL-08: Email لا يُرسَل للمرشحين
**الملفات:**
- `modules/Interviews/InterviewService.php` — رابط المقابلة لا يُرسَل
- `modules/Offers/OfferService.php` line 145 — `pretendSendEmail()` فقط تعمل Log

**التأثير:** المرشحون لا يعرفون أن لديهم مقابلة أو عرض وظيفي.  
**الإصلاح:** تنفيذ SMTP أو queue-based email system.

---

### CRITICAL-09: interview_feedback — أعمدة خاطئة
**الملف:** `api/v1/interviews.php` lines 251-256  
**المشكلة:**

| الكود | Schema الحقيقي |
|-------|---------------|
| `feedback` | `comments` |
| `candidate_id` | ❌ غير موجود |
| `suggestions` | ❌ غير موجود |

**الإصلاح:**
```sql
ALTER TABLE interview_feedback
  ADD COLUMN candidate_id BIGINT UNSIGNED NULL,
  ADD COLUMN suggestions TEXT NULL,
  CHANGE COLUMN comments feedback TEXT NULL;
```
أو تعديل الكود ليستخدم `comments` بدلاً من `feedback`.

---

### CRITICAL-10: HeyGen generateVideo() — Method غير موجود
**الملف:** `modules/HeyGen/AvatarController.php` line 187  
**المشكلة:** `$this->heygen->generateVideo()` لكن الـ method غير موجودة في HeyGenService.  
**التأثير:** Avatar preview معطل بالكامل — "Call to undefined method".

---

### CRITICAL-11: AI Modules — لا يوجد Error Handling
**الملفات:**
- `modules/AI/InterviewEvaluator.php` lines 66-72
- `modules/AI/InterviewConductor.php` lines 66-72, 144-148
- `modules/AI/CandidateMatcher.php` — كل الـ methods
- `modules/AI/JobBuilder.php` lines 43-50
- `modules/AI/RecruitmentCopilot.php` lines 68-71

**المشكلة:** `chatJson()` يرمي RuntimeException عند فشل OpenAI — لا يوجد try-catch.  
**التأثير:** المقابلة تنهار وسط الحديث إذا فشل OpenAI.  
**الإصلاح:** Wrap كل `chatJson()` في try-catch مع fallback response.

---

### CRITICAL-12: notifications table — أعمدة خاطئة
**الملف:** `api/v1/offers.php` lines 112-120  
**المشكلة:**

| الكود | Schema |
|-------|--------|
| `body` | `message` |
| `data` | ❌ غير موجود |
| `user_id = candidate_id` | candidates ≠ users |

---

### CRITICAL-13: offers table — لا يوجد tenant_id
**المشكلة:** جدول offers بالكامل لا يحتوي tenant_id مما يعني عدم عزل البيانات بين الشركات.

---

### CRITICAL-14: InterviewService::validateToken — لا يوجد فحص tenant
**الملف:** `modules/Interviews/InterviewService.php` lines 282-291  
**المشكلة:** JOIN إلى jobs وcandidates بدون فحص tenant_id المشترك.

---

### CRITICAL-15: interviews/interview_evaluations — لا يوجد tenant_id
**المشكلة:** جدولا interviews وinterview_evaluations لا يحتويان tenant_id، مما يجعل عزل البيانات مستحيلاً.  
**الإصلاح:**
```sql
ALTER TABLE interviews ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id, ADD KEY idx_interview_tenant (tenant_id);
ALTER TABLE interview_evaluations ADD COLUMN tenant_id BIGINT UNSIGNED NULL, ADD KEY idx_eval_tenant (tenant_id);
```

---

### CRITICAL-16: OpenAI Platform Key Fallback بدون Audit
**الملف:** `modules/AI/OpenAIService.php` lines 51-53  
**المشكلة:** إذا لم يكن للشركة API key، يستخدم الـ platform key بصمت دون تسجيل أو إشعار.  
**التأثير:** تكاليف OpenAI تُحمَّل على Platform بدلاً من الشركة.

---

### CRITICAL-17: ApiKeyManager — لا يوجد فحص Authorization
**الملف:** `core/ApiKeyManager.php` lines 52-60, 80-88  
**المشكلة:** `getTenantOpenAIKey($tenantId)` لا يتحقق أن المستدعي مُخوَّل لهذا الـ tenant.

---

### CRITICAL-18: JobService — Column name خاطئ
**الملف:** `modules/Jobs/JobService.php` line 143  
**المشكلة:** `'currency' => 'USD'` لكن Schema يحتوي `salary_currency`.

---

## 🟠 HIGH PRIORITY ISSUES

---

### HIGH-01: InterviewService — interview_link_used column غير موجود
**الملف:** `modules/Interviews/InterviewService.php` line 330  
**المشكلة:** `UPDATE applications SET interview_link_used = 1` لكن العمود غير موجود في schema.

---

### HIGH-02: ai.php — interview_evaluations بدون tenant check
**الملف:** `api/v1/ai.php` lines 199-202  
**المشكلة:** Fetch evaluation by application_id بدون tenant validation.

---

### HIGH-03: HeyGen — لا يوجد Retry Logic
**الملف:** `modules/HeyGen/HeyGenService.php` lines 245-305  
**المشكلة:** أي خطأ شبكي يوقف المقابلة فوراً — لا يوجد exponential backoff.

---

### HIGH-04: HeyGen Session Persistence مفقود
**الملف:** `modules/HeyGen/AvatarController.php` line 226  
**المشكلة:** Session ID لا يُحفظ في DB — إذا انقطع الاتصال لا يمكن الاستمرار.

---

### HIGH-05: HeyGenService — Silent Auto-Start Failure
**الملف:** `modules/HeyGen/HeyGenService.php` lines 86-93  
**المشكلة:** إذا فشل startSession()، يُعاد الـ session كـ "ready" رغم أنه معطل.

---

### HIGH-06: CVAnalyzer — لا يتحقق من نتيجة chatJson
**الملف:** `modules/AI/CVAnalyzer.php` lines 46-52  
**المشكلة:** إذا أعاد chatJson() بيانات فارغة بسبب خطأ API، normalize() تُعيد نتيجة تبدو حقيقية بأصفار.

---

### HIGH-07: ai.php — candidate query بدون tenant_id (مُكرَّر)
مُذكور في CRITICAL-05 — تأثير مرتفع.

---

### HIGH-08: quick_view endpoint — يمكن للمرشح رؤية مرشحين آخرين
**الملف:** `api/v1/candidates.php` lines 55-70  
**المشكلة:** requirePermission('candidates.view') لكن لا يوجد فحص `Auth::isCandidate()`.

---

### HIGH-09: AvatarController — Avatar fetch بدون tenant_id
**الملف:** `modules/HeyGen/AvatarController.php` line 121  
**المشكلة:** `SELECT * FROM avatars WHERE id = :id` بدون `AND tenant_id = ?`.

---

### HIGH-10: InterviewEvaluator — Unbounded Token Usage
**الملف:** `modules/AI/InterviewEvaluator.php` line 145  
**المشكلة:** النص الكامل للمقابلة + CV + معايير الوظيفة في prompt واحد = 8k-10k tokens.

---

### HIGH-11: OpenAIService — No max_tokens Default
**الملف:** `modules/AI/OpenAIService.php` line 196  
**المشكلة:** `complete()` بدون max_tokens — يمكن أن يستهلك كل tokens المتاحة.

---

### HIGH-12: AvatarController — No error handling on streaming token
**الملف:** `modules/HeyGen/AvatarController.php` lines 226-232  
**المشكلة:** إذا فشل HeyGen، الـ exception يصعد بدون catch → 500 error للمستخدم.

---

### HIGH-13: audit_logs — اسم columns غير موحَّد
**المشكلة:** بعض الملفات تستخدم `entity_type/entity_id` وأخرى `resource_type/resource_id`.

---

### HIGH-14: CV Upload — لا يتحقق من وجود storage directory
**الملف:** `modules/Candidates/CandidateController.php` line 387  
**المشكلة:** لا يوجد فحص أن `/storage/uploads/cv/` موجود وقابل للكتابة.

---

### HIGH-15: register.php — Form pre-fill مكسور
**الملف:** `views/candidate/register.php` line 54  
**المشكلة:** `value="<?= $old['name'] ?>"` لكن المتغير اسمه `full_name` ليس `name`.

---

### HIGH-16: Super Admin Terminal — API endpoint غير مؤكد
**الملف:** `views/super-admin/terminal.php`  
**المشكلة:** View تستدعي `POST /api/v1/admin/terminal` لكن لم يتم التحقق من تنفيذه.

---

## 🟡 MEDIUM PRIORITY ISSUES

---

### MED-01: ai_usage_logs — YEAR()/MONTH() تمنع استخدام Index
**المشكلة:** `WHERE YEAR(created_at)=? AND MONTH(created_at)=?` لا تستخدم index.  
**الإصلاح:** استخدام `WHERE created_at BETWEEN '2026-06-01' AND '2026-06-30'`.

---

### MED-02: candidates table — `OR tenant_id IS NULL` في ai.php
**المشكلة:** يسمح بـ global candidates بدون توثيق واضح للسبب.

---

### MED-03: HeyGen — لا يوجد Webhook Handling
**المشكلة:** لا يمكن معرفة متى تنتهي معالجة الفيديو async.

---

### MED-04: InterviewConductor — Language Detection غير دقيق
**الملف:** `modules/AI/InterviewConductor.php` lines 415-436  
**المشكلة:** عد حروف عربية/لاتينية فقط — لا يعمل مع النصوص المختلطة.

---

### MED-05: logUsage exceptions مُبتلَعة
**الملف:** `modules/AI/OpenAIService.php` lines 238-241  
**المشكلة:** فشل تسجيل الاستخدام يمر بصمت.

---

### MED-06: Pipeline/Human-Interviews/Users — بيانات Mock في Production
**الملفات:** views/hr/pipeline.php، views/hr/human-interviews.php، views/hr/users.php  
**المشكلة:** تعرض بيانات hardcoded مع بيانات حقيقية أو بدلاً منها.

---

### MED-07: candidate/dashboard — جميع البيانات Mock
**الملف:** `views/candidate/dashboard.php`  
**المشكلة:** Stats وجدول التقديمات كلها hardcoded.

---

### MED-08: candidate/jobs — الوظائف hardcoded
**الملف:** `views/candidate/jobs.php`  
**المشكلة:** بيانات وظائف ثابتة — لا تُجلب من DB.

---

### MED-09: candidate/applications — تقديمات Mock
**الملف:** `views/candidate/applications.php`  
**المشكلة:** لا تعرض تقديمات المرشح الحقيقية.

---

### MED-10: Missing Composite Indexes
**المشكلة:** Queries تفلتر على `(tenant_id, job_id, current_stage)` لكن indexes فردية فقط.

---

### MED-11: Offers — tabs/filters client-side فقط
**الملف:** `views/hr/offers.php`  
**المشكلة:** Tab counts hardcoded (line 49: `['all' => 24, 'draft' => 5, ...]`).

---

### MED-12: HeyGen — Error Response Structure غير موحَّدة
**الملف:** `modules/HeyGen/HeyGenService.php` lines 295-304  
**المشكلة:** أحياناً يُعيد `['error' => msg]` وأحياناً لا.

---

## 🟢 LOW PRIORITY ISSUES

---

### LOW-01: Super Admin Views تستخدم layout app.php
**المشكلة:** بعض views تستخدم app.php بدلاً من super-admin layout مخصص.

---

### LOW-02: candidates/compare.php — Route مفقود
**الملف:** `modules/HR/HRRouter.php`  
**المشكلة:** View `views/hr/candidates/compare.php` موجود لكن لا يوجد route له.

---

### LOW-03: views/hr/jobs/show.php — Route مفقود
**المشكلة:** View موجود لكن لا route في HRRouter.

---

### LOW-04: session secure = false
**الملف:** `public/index.php` line 22  
**المشكلة:** `'secure' => false` — في production يجب أن يكون `true` مع HTTPS.

---

### LOW-05: JWT Secret Default Value
**الملف:** `core/Auth.php` line 47  
**المشكلة:** `$_ENV['JWT_SECRET'] ?? 'default-secret'` — خطر إذا لم يُضبط في .env.

---

### LOW-06: Missing Rate Limiting على AI Endpoints
**المشكلة:** لا يوجد rate limiting على `/api/v1/ai` — يمكن استنزاف API keys.

---

## المرحلة الثالثة: اختبار رحلة المستخدم

### رحلة المرشح

| الخطوة | المتوقع | الواقع | المشكلة | الخطورة |
|--------|---------|--------|---------|---------|
| التسجيل | إنشاء حساب | يعمل جزئياً | pre-fill مكسور | Medium |
| تصفح الوظائف | رؤية وظائف الشركة | "Career page - coming soon" | CareerController stub | CRITICAL |
| التقديم | رفع CV + إنشاء طلب | يعمل (إذا وجد form) | يعتمد على خطوة سابقة | High |
| غرفة المقابلة | بدء المقابلة AI | ID=0، كل شيء معطل | InterviewRoomController | CRITICAL |
| نتيجة المقابلة | تقرير AI | لا يصل أبداً | مكسور من الخطوة السابقة | CRITICAL |
| متابعة الطلب | رؤية status | بيانات Mock فقط | candidate/applications | Medium |
| العرض الوظيفي | قبول/رفض | يعمل جزئياً | لا يصل email | Critical |

### رحلة HR

| الخطوة | المتوقع | الواقع | المشكلة | الخطورة |
|--------|---------|--------|---------|---------|
| تسجيل الدخول | dashboard | يعمل | — | ✅ |
| إنشاء وظيفة | form + save | يعمل | — | ✅ |
| نشر الوظيفة | status=published | يعمل | — | ✅ |
| مراجعة طلبات | قائمة بالطلبات | يعمل جزئياً | paginated() معطل أحياناً | High |
| تحليل CV | AI analysis | يعمل | لا يُشغَّل تلقائياً | Medium |
| إنشاء عرض | offer creation | SQL Error | Schema mismatch | CRITICAL |
| إرسال عرض | email للمرشح | يُسجَّل فقط | pretendSendEmail() | CRITICAL |

### رحلة Super Admin

| الخطوة | المتوقع | الواقع | المشكلة | الخطورة |
|--------|---------|--------|---------|---------|
| تسجيل الدخول | /super/dashboard | يعمل | — | ✅ |
| الشركات | عرض وإدارة | يعمل | — | ✅ |
| المستخدمون | قائمة كاملة | يعمل جزئياً | paginated() | High |
| Terminal | تنفيذ أوامر | غير مؤكد | endpoint لم يُتحقق | High |
| AI Analytics | إحصائيات | يعمل | — | ✅ |

---

## المرحلة الرابعة: فحص الترابط (Pipeline)

```
إنشاء وظيفة ✅
    ↓
إضافة معايير ✅
    ↓
نشر الوظيفة ✅
    ↓
تقديم مرشح ❌ (CareerController stub)
    ↓
تحليل CV ✅ (يعمل لكن يدوي)
    ↓
رابط المقابلة ✅ يُنشأ
    ↓ ❌ لا يُرسَل بالبريد
المقابلة AI ❌ (InterviewRoom undefined vars)
    ↓
التقييم ❌ (يعتمد على المقابلة)
    ↓
Pipeline ✅ (يعمل إذا وصلنا هنا)
    ↓
المقابلة البشرية ✅
    ↓
العرض الوظيفي ❌ (offers schema مكسور)
```

**5 نقاط مكسورة في السلسلة** تمنع إتمام أي رحلة كاملة.

---

## المرحلة الخامسة: فحص الكود

### Dead Code
- `auth.php` — كامل (غير مُوجَّه)
- `modules/Offers/OfferService.php::pretendSendEmail()` — log فقط
- `views/hr/candidates/compare.php` — لا route

### Mock Data في Production Views
- `views/hr/pipeline.php`
- `views/hr/human-interviews.php`
- `views/hr/users.php`
- `views/hr/roles.php`
- `views/candidate/dashboard.php`
- `views/candidate/jobs.php`
- `views/candidate/applications.php`

### N+1 Query Risks
- `views/hr/candidates/index.php` — قد يُنفَّذ query لكل مرشح

### Missing Validation
- Offers API لا يتحقق من salary_amount (numeric)
- CV upload لا يتحقق من وجود directory

---

## المرحلة السادسة: التقرير النهائي

## أولويات الإصلاح

### 🔴 P0 — يجب إصلاحها أولاً (Blockers)

| # | المشكلة | الملف | الجهد |
|---|---------|-------|-------|
| 1 | Schema Migration للـ offers table | database/schema.sql | 2 ساعة |
| 2 | Response::paginated() signature | core/Response.php | 30 دقيقة |
| 3 | InterviewRoomController — تمرير المتغيرات | InterviewRoomController.php | 1 ساعة |
| 4 | CareerController — تنفيذ كامل | CareerController.php | 4 ساعات |
| 5 | Cross-tenant fix في ai.php | api/v1/ai.php | 30 دقيقة |
| 6 | Offers accept/decline ownership check | api/v1/offers.php | 30 دقيقة |
| 7 | auth.php dispatch + rewrite | api/v1/index.php | 1 ساعة |
| 8 | Email integration (interview + offer) | OfferService + InterviewService | 4 ساعات |
| 9 | interview_feedback schema fix | schema.sql | 30 دقيقة |
| 10 | AI modules — try-catch على chatJson | 5 ملفات | 2 ساعة |
| 11 | HeyGen generateVideo() implementation | HeyGenService.php | 3 ساعات |
| 12 | tenant_id في interviews + evaluations | schema.sql | 1 ساعة |

### 🟠 P1 — خلال أسبوع

| # | المشكلة | الملف |
|---|---------|-------|
| 13 | Add interview_link_used column | schema.sql |
| 14 | AvatarController tenant_id check | AvatarController.php |
| 15 | HeyGen retry logic | HeyGenService.php |
| 16 | Fix notifications column names | offers.php |
| 17 | candidates/compare + jobs/show routes | HRRouter.php |
| 18 | CV upload directory validation | CandidateController.php |
| 19 | register.php form pre-fill | register.php |
| 20 | Session secure=true | index.php |

### 🟡 P2 — خلال أسبوعين

| # | المشكلة |
|---|---------|
| 21 | استبدال Mock data في candidate portal views |
| 22 | استبدال Mock data في HR views (pipeline, human-interviews) |
| 23 | YEAR()/MONTH() → range queries |
| 24 | Composite database indexes |
| 25 | HeyGen webhook handler |
| 26 | Rate limiting على AI endpoints |
| 27 | max_tokens defaults في OpenAI calls |
| 28 | Token budget estimator قبل AI calls |
| 29 | audit_logs column name standardization |
| 30 | JWT_SECRET validation at boot |

---

## إحصائيات نهائية

| الفئة | Critical | High | Medium | Low |
|-------|---------|------|--------|-----|
| Database | 5 | 3 | 3 | 1 |
| API Security | 5 | 4 | 2 | 1 |
| Views/Frontend | 2 | 4 | 5 | 2 |
| AI/HeyGen | 4 | 5 | 4 | 1 |
| User Journeys | 2 | 0 | 0 | 1 |
| **المجموع** | **18** | **16** | **14** | **6** |

---

**الخلاصة:** المشروع يحتاج إلى ~2 أسبوع لإصلاح الـ P0 وP1 قبل أي إطلاق فعلي.  
الـ P0 وحدها تستغرق ~20 ساعة عمل وتشمل إصلاحات في Schema وController وService وAPI.

---
*تم إنشاء هذا التقرير بواسطة Audit شامل في 2026-06-25*
