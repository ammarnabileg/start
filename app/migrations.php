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
    // قاعدة المعرفة: أقسام الشركة، والملفات المرفوعة متقسمة لأجزاء للبحث
    '012_knowledge_base' => [
        "CREATE TABLE IF NOT EXISTS kb_sections (
            workspace_id INT UNSIGNED NOT NULL,
            section VARCHAR(30) NOT NULL,
            data MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (workspace_id, section),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS kb_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            mime VARCHAR(100) NOT NULL DEFAULT '',
            size INT UNSIGNED NOT NULL DEFAULT 0,
            method VARCHAR(30) NOT NULL DEFAULT '',
            content MEDIUMTEXT NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    // حملات الرسائل: طابور بيتبعت على دفعات عشان الخدمة ماتقفش وماتتحظرش
    '013_campaigns' => [
        "CREATE TABLE IF NOT EXISTS msg_campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            message TEXT NOT NULL,
            personalize TINYINT(1) NOT NULL DEFAULT 0,
            per_minute SMALLINT UNSIGNED NOT NULL DEFAULT 20,
            status VARCHAR(12) NOT NULL DEFAULT 'running',
            total INT UNSIGNED NOT NULL DEFAULT 0,
            sent INT UNSIGNED NOT NULL DEFAULT 0,
            failed INT UNSIGNED NOT NULL DEFAULT 0,
            skipped INT UNSIGNED NOT NULL DEFAULT 0,
            last_tick DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            INDEX ws_status (workspace_id, status),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS msg_campaign_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            conv_id VARCHAR(120) NOT NULL,
            account_id VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL DEFAULT '',
            platform VARCHAR(30) NOT NULL DEFAULT '',
            last_text VARCHAR(400) NOT NULL DEFAULT '',
            status VARCHAR(10) NOT NULL DEFAULT 'queued',
            error VARCHAR(255) NOT NULL DEFAULT '',
            sent_at DATETIME NULL,
            INDEX camp_status (campaign_id, status),
            FOREIGN KEY (campaign_id) REFERENCES msg_campaigns(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    // أدوات الفريق في الرسائل: إسناد، حالة، ملاحظات داخلية، وردود محفوظة
    '014_inbox_team' => [
        "CREATE TABLE IF NOT EXISTS conv_meta (
            workspace_id INT UNSIGNED NOT NULL,
            conv_id VARCHAR(120) NOT NULL,
            assignee_id INT UNSIGNED NULL,
            state VARCHAR(12) NOT NULL DEFAULT 'open',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (workspace_id, conv_id),
            INDEX ws_assignee (workspace_id, assignee_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conv_notes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            conv_id VARCHAR(120) NOT NULL,
            user_id INT UNSIGNED NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_conv (workspace_id, conv_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS saved_replies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            shortcut VARCHAR(40) NOT NULL,
            body TEXT NOT NULL,
            uses INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '015_lead_pipeline' => [
        "CREATE TABLE IF NOT EXISTS lead_meta (
            workspace_id INT UNSIGNED NOT NULL,
            lead_id VARCHAR(120) NOT NULL,
            campaign_id VARCHAR(120) NULL,
            stage VARCHAR(20) NOT NULL DEFAULT 'new',
            deal_value DECIMAL(14,2) NULL,
            note VARCHAR(500) NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (workspace_id, lead_id),
            INDEX ws_campaign (workspace_id, campaign_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '016_ad_builder' => [
        "CREATE TABLE IF NOT EXISTS ad_builds (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            name VARCHAR(200) NOT NULL,
            platform VARCHAR(30) NOT NULL DEFAULT 'meta',
            data MEDIUMTEXT NOT NULL,
            state VARCHAR(20) NOT NULL DEFAULT 'draft',
            progress MEDIUMTEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX ws (workspace_id, updated_at),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];
