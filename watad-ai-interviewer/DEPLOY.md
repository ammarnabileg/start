# Watad AI Interviewer — Deployment Guide / دليل النشر

This bundle ships with `vendor/` **already installed** (Composer dependencies included
and slimmed). On most servers you do **not** need to run `composer install`.

هذا الأرشيف فيه `vendor/` **جاهزة** (مكتبات Composer متضمَّنة ومُنظّفة). في أغلب
السيرفرات **مش محتاج** تشغّل `composer install`.

---

## 1) Requirements / المتطلبات

- PHP **8.3+** with extensions: `pdo`, `pdo_mysql` (or `pdo_sqlite`), `mbstring`,
  `openssl`, `curl`, `gd` or `imagick` (for PDF), `zip`.
- A web server (Nginx or Apache) **OR** shared hosting (cPanel).
- (Optional) MySQL 8 / MariaDB. SQLite works out of the box with zero DB server.
- (Recommended) Redis for cache/queue, and the ability to run a background worker.

---

## 2) Upload & document root / الرفع وجذر الموقع

1. Extract this archive and upload the **`watad-ai-interviewer/`** folder to the server.
2. **CRITICAL:** point the web server document root at **`watad-ai-interviewer/public`**,
   **not** the project root. Otherwise `.env` (your API keys) and source code are exposed.

   ⚠️ **مهم جدًا:** خلّي الـ document root على **`watad-ai-interviewer/public`** مش على
   فولدر المشروع — وإلا مفاتيح الـ API والكود هيبقوا مكشوفين.

**Nginx example:**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/watad-ai-interviewer/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }   # block dotfiles like .env
}
```

**Apache (cPanel):** put the app outside `public_html`, then point the domain’s
document root to the app’s `public/` folder (or symlink it). A `.htaccess` already
exists in `public/`.

---

## 3) Permissions / الصلاحيات

```bash
cd watad-ai-interviewer
chmod -R 775 storage bootstrap/cache
# set the web-server user as owner if needed, e.g.:
# chown -R www-data:www-data storage bootstrap/cache
```

---

## 4) Run the installer / شغّل المثبّت

Open in your browser:

```
https://your-domain.com/install.php
```

The wizard does everything on one page:

- checks requirements,
- database: SQLite (create fresh **or** upload a `.sqlite` file) or MySQL
  (migrate fresh **or** import a `.sql` dump),
- **all API subscriptions** (Claude/OpenAI, SMTP email, S3 storage, video avatar,
  WhatsApp, Google Sheets, Reverb) — entered here and written to `.env`,
- generates `APP_KEY`, runs migrations + seeds, creates the **Super Admin**,
- then **locks itself** (`storage/installed.lock`).

A guarded **RESET** button (type `DELETE`) wipes everything and returns to a fresh
install — keep `install.php` only on trusted environments, or delete it after go-live.

بعد التثبيت: **احذف أو احمِ `public/install.php`** لأن زر RESET بيمسح كل البيانات.

---

## 5) Background worker (required for AI) / عامل الخلفية (ضروري للـ AI)

The AI work (CV analysis, scoring, reports, notifications) runs on the queue. Without a
worker, interviews are saved but **never analyzed**.

شغّل عامل الطابور — من غيره المقابلة بتتسجّل بس **التحليل مش هيشتغل**:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Run it as a **systemd** service or **Supervisor** program so it stays up:

```ini
# /etc/supervisor/conf.d/watad-worker.conf
[program:watad-worker]
command=php /var/www/watad-ai-interviewer/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/watad-ai-interviewer/storage/logs/worker.log
```

(Live video interviews also need `php artisan reverb:start` if you enable Reverb.)

---

## 6) Production caching (optional) / تسريع الإنتاج (اختياري)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ⚠️ Security note: Laravel framework version / ملاحظة أمنية

This build runs on **Laravel 11**. As of mid-2026, three advisories
(medium/high) affect Laravel 11 with fixes available only in **Laravel 12.60+/13**
(Laravel 11 is past its security-patch window):

- Temporary Signed URL path confusion (medium)
- CRLF injection in the default `email` validation rule (high, CVE-2026-48019)

**Recommendation:** plan an upgrade to the latest Laravel 12 LTS line before handling
real candidate data at scale, and re-run the test suite after upgrading.

**توصية:** يُفضّل الترقية لأحدث Laravel 12 قبل التشغيل الواسع ببيانات مرشحين حقيقية،
مع إعادة الاختبار بعد الترقية.

---

## Demo logins (only if you load demo data) / حسابات تجريبية

- HR / Super Admin: `admin@watad.com` · `password`
- Candidate portal (`/portal/login`): `candidate@watad.com` · `password`

> Change or remove these before going live.
