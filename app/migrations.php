<?php
/**
 * تحديثات قاعدة البيانات.
 * كل تحديث له رقم ثابت. لا تعدّل تحديثاً قديماً أبداً، أضف واحداً جديداً بالأسفل.
 * صفحة setup تشغّل أي رقم لم يُشغَّل من قبل.
 */
return [
    '001_initial' => [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'editor',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS settings (
            name VARCHAR(100) PRIMARY KEY,
            value TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS activity_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],

    // تعدد الشركات: كل شركة ليها مفتاح Zernio وفريق منفصل، ودعوات بلينك أو بالإيميل
    '002_workspaces' => [
        "CREATE TABLE IF NOT EXISTS workspaces (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            api_key TEXT NULL,
            profile_id VARCHAR(64) NULL,
            timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Cairo',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // role: owner (صاحب الشركة) أو editor (محرر). status: active أو pending (دعوة مستنية رد)
        "CREATE TABLE IF NOT EXISTS workspace_users (
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'editor',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            invited_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (workspace_id, user_id),
            INDEX (user_id, status),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // لينكات الدعوة. email فاضي = لينك عام لأي حد معاه اللينك
        "CREATE TABLE IF NOT EXISTS workspace_invites (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            token CHAR(48) NOT NULL UNIQUE,
            email VARCHAR(190) NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'editor',
            created_by INT UNSIGNED NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (email),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ترقية من الإصدار 1.0: الإعدادات القديمة تبقى أول شركة، والفريق القديم يتنقل ليها
        "INSERT INTO workspaces (name, api_key, profile_id, timezone)
         SELECT COALESCE((SELECT value FROM settings WHERE name = 'site_name'), 'الشركة الأولى'),
                (SELECT value FROM settings WHERE name = 'zernio_api_key'),
                (SELECT value FROM settings WHERE name = 'profile_id'),
                COALESCE((SELECT value FROM settings WHERE name = 'timezone'), 'Africa/Cairo')
         FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM workspaces)
           AND EXISTS (SELECT 1 FROM settings WHERE name = 'zernio_api_key' AND value <> '')",

        "INSERT IGNORE INTO workspace_users (workspace_id, user_id, role, status)
         SELECT (SELECT MIN(id) FROM workspaces), id, IF(role = 'admin', 'owner', 'editor'), 'active' FROM users
         WHERE EXISTS (SELECT 1 FROM workspaces)",

        // users.role بقى: admin (مدير النظام: التحديث وإعدادات الموقع) أو user
        "UPDATE users SET role = 'user' WHERE role <> 'admin'",

        "ALTER TABLE activity_log ADD COLUMN workspace_id INT UNSIGNED NULL",

        "DELETE FROM settings WHERE name IN ('zernio_api_key', 'profile_id', 'timezone')",
    ],

    // 1.2.0: المفاتيح بقت في جدول connectors ومشفرة، عشان نقدر نضيف خدمات تانية بعدين
    '003_connectors' => [
        "CREATE TABLE IF NOT EXISTS connectors (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            provider VARCHAR(50) NOT NULL,
            credentials TEXT NOT NULL,
            settings TEXT NULL,
            info VARCHAR(255) NULL,
            checked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (workspace_id, provider),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        function (PDO $pdo) {
            $rows = $pdo->query("SELECT id, api_key, profile_id FROM workspaces WHERE api_key IS NOT NULL AND api_key <> ''")->fetchAll();
            foreach ($rows as $r) {
                // SQL مباشر (مش save_connector) عشان التحديث ده يفضل شغال حتى لو الجدول اتغير بعدين
                $pdo->prepare("INSERT INTO connectors (workspace_id, provider, credentials, settings, info, checked_at) VALUES (?, 'zernio', ?, ?, '', NOW())")
                    ->execute([(int)$r['id'], encrypt_secret(json_encode(['api_key' => $r['api_key']])), json_encode($r['profile_id'] ? ['profile_id' => $r['profile_id']] : [])]);
            }
        },

        "ALTER TABLE workspaces DROP COLUMN api_key, DROP COLUMN profile_id",
    ],

    // 1.7.0: كل شركة تقدر تقفل الأقسام اللي مش بتستخدمها (قائمة JSON بالأقسام المقفولة)
    '004_workspace_modules' => [
        "ALTER TABLE workspaces ADD COLUMN disabled_modules TEXT NULL",
    ],

    // 1.9.0: لوحة إدارة النظام: إيقاف مستخدمين وشركات، وآخر دخول
    '005_admin' => [
        "ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active', ADD COLUMN last_login_at DATETIME NULL",
        "ALTER TABLE workspaces ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
        "ALTER TABLE activity_log ADD INDEX (user_id), ADD INDEX (workspace_id), ADD INDEX (action)",
    ],

    // 1.9.0: أكتر من اتصال لنفس الخدمة في الشركة الواحدة (كل اتصال ليه اسم)
    '006_multi_connections' => [
        "ALTER TABLE connectors ADD COLUMN label VARCHAR(120) NULL",
        "ALTER TABLE connectors ADD INDEX ws_provider (workspace_id, provider)",
        "ALTER TABLE connectors DROP INDEX workspace_id",
    ],

    // 2.1.0: أدوار مخصصة لكل شركة بصلاحيات يختارها المالك
    // workspace_users.role بقى: owner أو editor (أدوار جاهزة) أو r<رقم الدور> للأدوار المخصصة
    '007_roles' => [
        "CREATE TABLE IF NOT EXISTS workspace_roles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            name VARCHAR(80) NOT NULL,
            permissions TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (workspace_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],

    // 2.3.0: التقارير والأهداف الشهرية والتنبيهات
    '008_reports' => [
        "CREATE TABLE IF NOT EXISTS report_schedules (
            workspace_id INT UNSIGNED PRIMARY KEY,
            recipients TEXT NULL,
            frequency VARCHAR(10) NOT NULL DEFAULT 'off',
            lang VARCHAR(5) NOT NULL DEFAULT 'en',
            last_sent DATETIME NULL,
            last_result VARCHAR(255) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS report_goals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            month CHAR(7) NOT NULL,
            metric VARCHAR(30) NOT NULL,
            target DOUBLE NOT NULL DEFAULT 0,
            achieved DOUBLE NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_month_metric (workspace_id, month, metric),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS alert_rules (
            workspace_id INT UNSIGNED NOT NULL,
            rule VARCHAR(30) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            threshold DOUBLE NOT NULL DEFAULT 0,
            PRIMARY KEY (workspace_id, rule),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS alerts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            rule VARCHAR(30) NOT NULL,
            fingerprint VARCHAR(190) NOT NULL,
            data TEXT NULL,
            seen TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_fp (workspace_id, fingerprint),
            INDEX ws_seen (workspace_id, seen),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    // وسوم محلية على المنشورات لمقارنة الأداء حسب الوسم (التحليلات)
    '009_post_tags' => [
        "CREATE TABLE IF NOT EXISTS post_tags (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            post_id VARCHAR(64) NOT NULL,
            tag VARCHAR(40) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_post_tag (workspace_id, post_id, tag),
            INDEX ws_tag (workspace_id, tag),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    // سجل استهلاك الذكاء الاصطناعي: كل طلب بتكلفته، عشان حد الاستهلاك الشهري
    '010_ai_usage' => [
        "CREATE TABLE IF NOT EXISTS ai_usage (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            task VARCHAR(40) NOT NULL,
            provider VARCHAR(20) NOT NULL,
            model VARCHAR(160) NOT NULL,
            tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
            tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
            cost DECIMAL(12,6) NULL,
            ok TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_time (workspace_id, created_at),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    // تصنيف الذكاء الاصطناعي للمحادثات (بيشتغل في الخلفية)
    '011_ai_labels' => [
        "CREATE TABLE IF NOT EXISTS ai_labels (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            item_key VARCHAR(120) NOT NULL,
            category VARCHAR(20) NOT NULL DEFAULT '',
            sentiment VARCHAR(20) NOT NULL DEFAULT '',
            urgency TINYINT UNSIGNED NOT NULL DEFAULT 0,
            intent TINYINT(1) NOT NULL DEFAULT 0,
            summary VARCHAR(255) NOT NULL DEFAULT '',
            src_hash CHAR(32) NOT NULL DEFAULT '',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_item (workspace_id, item_key),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
