<?php
/**
 * HalaOps CRM — Configuration
 * Edit this file to match your environment.
 */

// ---- Database ----
// By default, reuse the parent project's DB. Override here if needed.
define('CRM_DB_HOST', 'localhost');
define('CRM_DB_NAME', 'Start_Main');
define('CRM_DB_USER', 'Start_Main');
define('CRM_DB_PASS', 'dacZ4350$');
define('CRM_DB_CHARSET', 'utf8mb4');

// All CRM tables share this prefix to avoid collisions.
define('CRM_TBL_PREFIX', 'crm_');

// ---- App ----
define('CRM_APP_NAME', 'HalaOps CRM');
define('CRM_BASE_URL', '/crm');         // path relative to web root
define('CRM_LOCALE', 'ar');              // 'ar' | 'en'
define('CRM_TIMEZONE', 'Asia/Riyadh');
define('CRM_SESSION_NAME', 'halaops_sess');

// ---- Security ----
define('CRM_PASSWORD_ALGO', PASSWORD_BCRYPT);
define('CRM_SESSION_LIFETIME', 60 * 60 * 8); // 8 hours
define('CRM_CSRF_TOKEN_KEY', '_csrf');

// ---- Currency ----
define('CRM_DEFAULT_CURRENCY', 'SAR');

date_default_timezone_set(CRM_TIMEZONE);
