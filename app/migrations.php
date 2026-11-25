<?php
/**
 * تحديثات قاعدة البيانات.
 * كل تحديث له رقم ثابت. لا تعدّل تحديثاً قديماً أبداً، أضف واحداً جديداً بالأسفل.
 * صفحة setup تشغّل أي رقم لم يُشغَّل من قبل.
 */
return [
    '080_ws_approval' => [
        // نظام موافقة المنصة على الشركات + الاشتراك. الأعمدة ليها قيم افتراضية آمنة:
        // الشركات الموجودة وقت التحديث تاخد approved/active تلقائيًا فمتتوقفش، وبعدين نعلّمها legacy=1.
        "ALTER TABLE workspaces
            ADD COLUMN approval_status VARCHAR(20) NOT NULL DEFAULT 'approved',
            ADD COLUMN sub_status VARCHAR(20) NOT NULL DEFAULT 'active',
            ADD COLUMN plan VARCHAR(40) NOT NULL DEFAULT '',
            ADD COLUMN legacy TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN reviewed_by INT UNSIGNED NULL,
            ADD COLUMN reviewed_at DATETIME NULL,
            ADD COLUMN reject_reason VARCHAR(400) NULL,
            ADD COLUMN sub_start DATE NULL,
            ADD COLUMN sub_end DATE NULL,
            ADD COLUMN payment_verified TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN admin_override TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN request_meta TEXT NULL",
        // كل الشركات القائمة = قديمة معتمدة (grandfathered)، تحتفظ بوصولها الحالي
        "UPDATE workspaces SET legacy = 1 WHERE approval_status = 'approved'",
        "ALTER TABLE workspaces ADD INDEX idx_approval (approval_status), ADD INDEX idx_sub (sub_status)",
        // سجل كامل لقرارات مالك المنصة على الشركات (اعتماد/رفض/إيقاف/اشتراك)
        "CREATE TABLE IF NOT EXISTS ws_approvals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            admin_id INT UNSIGNED NULL,
            action VARCHAR(40) NOT NULL,
            old_state VARCHAR(60) NULL,
            new_state VARCHAR(60) NULL,
            reason VARCHAR(400) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY ws (workspace_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '079_mcp_actions' => [
        "CREATE TABLE IF NOT EXISTS mcp_actions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            token_id INT UNSIGNED NULL,
            type VARCHAR(40) NOT NULL,
            summary VARCHAR(400) NOT NULL DEFAULT '',
            payload MEDIUMTEXT NULL,
            status ENUM('pending','approved','rejected','done','failed') NOT NULL DEFAULT 'pending',
            result VARCHAR(600) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            KEY ws_status (workspace_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    '078_mcp_tokens' => [
        "CREATE TABLE IF NOT EXISTS mcp_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL DEFAULT '',
            token_hash CHAR(64) NOT NULL,
            scopes VARCHAR(190) NOT NULL DEFAULT 'read',
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_hash (token_hash),
            KEY ws (workspace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
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
                $pdo->prepare("INSERT IGNORE INTO connectors (workspace_id, provider, credentials, settings, info, checked_at) VALUES (?, 'zernio', ?, ?, '', NOW())")
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
    '017_media_library' => [
        "CREATE TABLE IF NOT EXISTS media_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            folder VARCHAR(120) NOT NULL DEFAULT '',
            name VARCHAR(255) NOT NULL,
            mime VARCHAR(100) NOT NULL,
            size INT UNSIGNED NOT NULL DEFAULT 0,
            width INT UNSIGNED NULL,
            height INT UNSIGNED NULL,
            path VARCHAR(255) NOT NULL,
            public_url VARCHAR(1000) NULL,
            tags VARCHAR(500) NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_folder (workspace_id, folder),
            INDEX ws_created (workspace_id, created_at),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '018_media_hash' => [
        "ALTER TABLE media_files ADD COLUMN sha1 CHAR(40) NULL, ADD INDEX ws_sha (workspace_id, sha1)",
    ],
    '019_media_trash_star' => [
        "ALTER TABLE media_files ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN starred TINYINT(1) NOT NULL DEFAULT 0, ADD INDEX ws_deleted (workspace_id, deleted_at)",
    ],
    '020_media_share_usage' => [
        "CREATE TABLE IF NOT EXISTS media_shares (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            token CHAR(40) NOT NULL,
            folder VARCHAR(120) NOT NULL DEFAULT '',
            expires_at DATETIME NULL,
            allow_download TINYINT(1) NOT NULL DEFAULT 1,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY tok (token),
            INDEX ws (workspace_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS media_usage (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            file_id INT UNSIGNED NOT NULL,
            ctx VARCHAR(40) NOT NULL,
            ref VARCHAR(200) NULL,
            used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_file (workspace_id, file_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '021_media_ai' => [
        "ALTER TABLE media_files ADD COLUMN ai_desc VARCHAR(500) NULL, ADD COLUMN ai_at DATETIME NULL",
    ],
    '022_sms_gate' => [
        "CREATE TABLE IF NOT EXISTS sms_gate_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            direction ENUM('in','out') NOT NULL,
            phone VARCHAR(32) NOT NULL,
            body TEXT NOT NULL,
            ext_id VARCHAR(64) NULL,
            state VARCHAR(20) NOT NULL DEFAULT '',
            error VARCHAR(255) NULL,
            sim TINYINT UNSIGNED NULL,
            sent_by INT UNSIGNED NULL,
            seen TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_phone (workspace_id, phone, id),
            INDEX ws_ext (workspace_id, ext_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '023_crm' => [
        "CREATE TABLE IF NOT EXISTS crm_prefs (
            workspace_id INT UNSIGNED NOT NULL PRIMARY KEY,
            icp TEXT NULL,
            daily_limit INT UNSIGNED NOT NULL DEFAULT 20,
            next_page INT UNSIGNED NOT NULL DEFAULT 1,
            batch INT UNSIGNED NOT NULL DEFAULT 0,
            auto_assign TINYINT(1) NOT NULL DEFAULT 0,
            rr_last INT UNSIGNED NULL,
            hook_token CHAR(32) NULL,
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS crm_companies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            ext_id VARCHAR(64) NOT NULL,
            name VARCHAR(255) NOT NULL,
            domain VARCHAR(255) NULL,
            industry VARCHAR(160) NULL,
            employees INT UNSIGNED NULL,
            city VARCHAR(120) NULL,
            country VARCHAR(120) NULL,
            logo VARCHAR(500) NULL,
            linkedin VARCHAR(500) NULL,
            phone VARCHAR(40) NULL,
            people TEXT NULL,
            people_at DATETIME NULL,
            first_batch INT UNSIGNED NOT NULL DEFAULT 0,
            last_batch INT UNSIGNED NOT NULL DEFAULT 0,
            hidden TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_ext (workspace_id, ext_id),
            INDEX ws_batch (workspace_id, last_batch),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS crm_leads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'manual',
            ext_id VARCHAR(64) NULL,
            company_id INT UNSIGNED NULL,
            company VARCHAR(255) NULL,
            name VARCHAR(255) NOT NULL,
            title VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(40) NULL,
            phone_pending TINYINT(1) NOT NULL DEFAULT 0,
            linkedin VARCHAR(500) NULL,
            country VARCHAR(120) NULL,
            stage VARCHAR(20) NOT NULL DEFAULT 'new',
            deal_value DECIMAL(14,2) NULL,
            note TEXT NULL,
            tags VARCHAR(255) NULL,
            owner_id INT UNSIGNED NULL,
            next_at DATETIME NULL,
            next_note VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX ws_stage (workspace_id, stage),
            INDEX ws_ext (workspace_id, ext_id),
            INDEX ws_email (workspace_id, email),
            INDEX ws_phone (workspace_id, phone),
            INDEX ws_next (workspace_id, next_at),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS crm_reveals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            person_ext_id VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_user_day (workspace_id, user_id, created_at),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '077_cp_item_logo' => [
        "ALTER TABLE cp_items ADD COLUMN media_raw TEXT NULL, ADD COLUMN logo TEXT NULL",
    ],
    '076_cp_jobs' => [
        "CREATE TABLE IF NOT EXISTS cp_jobs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id INT UNSIGNED NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            workspace_id INT UNSIGNED NOT NULL,
            kind VARCHAR(10) NOT NULL,
            slide TINYINT UNSIGNED NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'queued',
            ext_id VARCHAR(120) NULL,
            result TEXT NULL,
            error VARCHAR(300) NULL,
            tries TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX plan_st (plan_id, status),
            INDEX item_kind (item_id, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE cp_items ADD COLUMN fmt VARCHAR(10) NULL",
    ],
    '075_cp_fix' => [
        "ALTER TABLE cp_items ADD COLUMN fix_tries TINYINT UNSIGNED NOT NULL DEFAULT 0",
    ],
    '074_cp_log' => [
        "ALTER TABLE cp_plans ADD COLUMN log MEDIUMTEXT NULL",
    ],
    '073_cp_media_ids' => [
        "ALTER TABLE cp_items ADD COLUMN media_ids TEXT NULL",
    ],
    '072_cp_humanized' => [
        "ALTER TABLE cp_items ADD COLUMN humanized TINYINT(1) NOT NULL DEFAULT 0",
    ],
    '071_content_plans' => [
        "CREATE TABLE IF NOT EXISTS cp_plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            goal TEXT NOT NULL,
            cnt SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            opts TEXT NULL,
            refs TEXT NULL,
            visual TEXT NULL,
            stage VARCHAR(12) NOT NULL DEFAULT 'outline',
            status VARCHAR(12) NOT NULL DEFAULT 'generating',
            error VARCHAR(500) NULL,
            fails TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX ws_st (workspace_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cp_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id INT UNSIGNED NOT NULL,
            workspace_id INT UNSIGNED NOT NULL,
            seq SMALLINT UNSIGNED NOT NULL,
            topic TEXT NULL,
            linkedin TEXT NULL,
            x VARCHAR(600) NULL,
            headline VARCHAR(120) NULL,
            image_prompt TEXT NULL,
            video_prompt TEXT NULL,
            carousel TEXT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            media_id INT UNSIGNED NULL,
            post_id VARCHAR(64) NULL,
            scheduled_at DATETIME NULL,
            pub_error VARCHAR(300) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX plan_seq (plan_id, seq),
            INDEX ws_st (workspace_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '070_tender_awards' => [
        "CREATE TABLE IF NOT EXISTS tender_awards (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            ukey CHAR(40) NOT NULL,
            ref VARCHAR(40) NULL,
            tender_name VARCHAR(500) NOT NULL,
            agency VARCHAR(255) NULL,
            branch VARCHAR(255) NULL,
            activity VARCHAR(255) NULL,
            ttype VARCHAR(80) NULL,
            url VARCHAR(600) NULL,
            published_at DATETIME NULL,
            winner VARCHAR(255) NOT NULL,
            award_sar DECIMAL(16,2) NOT NULL DEFAULT 0,
            total_sar DECIMAL(16,2) NOT NULL DEFAULT 0,
            award_type VARCHAR(40) NULL,
            bidders TEXT NULL,
            bidder_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            actor VARCHAR(80) NULL,
            kw VARCHAR(120) NULL,
            contact TEXT NULL,
            lead_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_key (workspace_id, ukey),
            INDEX ws_pub (workspace_id, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '069_kb_big_files' => [
        "ALTER TABLE kb_files ADD COLUMN read_state VARCHAR(12) NULL, ADD COLUMN read_fp VARCHAR(160) NULL, ADD COLUMN read_done INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN read_total INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN chars BIGINT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN chunked TINYINT(1) NOT NULL DEFAULT 0",
        "CREATE TABLE IF NOT EXISTS kb_chunks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            file_id INT UNSIGNED NOT NULL,
            seq INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            INDEX ws_file (workspace_id, file_id, seq),
            FULLTEXT KEY ft_body (body)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '068_kb_crawl_text' => [
        "ALTER TABLE kb_crawls ADD COLUMN chars BIGINT UNSIGNED NOT NULL DEFAULT 0",
        "ALTER TABLE kb_crawl_urls ADD COLUMN chars INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN title VARCHAR(255) NULL, ADD COLUMN txt MEDIUMBLOB NULL",
    ],
    '067_kb_crawl' => [
        "CREATE TABLE IF NOT EXISTS kb_crawls (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            root VARCHAR(500) NOT NULL,
            host VARCHAR(255) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'running',
            found INT UNSIGNED NOT NULL DEFAULT 0,
            done INT UNSIGNED NOT NULL DEFAULT 0,
            failed INT UNSIGNED NOT NULL DEFAULT 0,
            meta TEXT NULL,
            acc LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX ws_status (workspace_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS kb_crawl_urls (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            crawl_id INT UNSIGNED NOT NULL,
            h CHAR(40) NOT NULL,
            url VARCHAR(2000) NOT NULL,
            st TINYINT NOT NULL DEFAULT 0,
            UNIQUE KEY crawl_h (crawl_id, h),
            INDEX crawl_st (crawl_id, st, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '066_crm_trash' => [
        "CREATE TABLE IF NOT EXISTS crm_trash (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            lead_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NULL,
            data LONGTEXT NOT NULL,
            deleted_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_time (workspace_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '065_booking_len' => [
        "ALTER TABLE crm_bookings ADD COLUMN len SMALLINT UNSIGNED NULL",
    ],
    '064_password_resets' => [
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (token_hash), INDEX (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '063_media_en_folders' => [
        // الفولدرات التلقائية بقت بأسماء إنجليزي
        "UPDATE media_files SET folder = 'AI Generated' WHERE folder IN ('مولدة بالذكاء الاصطناعي', 'مولدة بالذكاء الاصطناعى')",
        "UPDATE media_files SET folder = 'Designs' WHERE folder = 'التصميمات'",
        "UPDATE settings SET value = REPLACE(REPLACE(value, '\"مولدة بالذكاء الاصطناعي\"', '\"AI Generated\"'), '\"التصميمات\"', '\"Designs\"') WHERE name LIKE 'media\\_folders\\_%'", // الاسم كامل بين علامات التنصيص: مايلمسش فولدرات فيها نفس الكلمة
    ],
    '062_perf_company' => [
        // الشركات المستهدفة: ربط الشركة بالأشخاص (10 ثواني مع 20 ألف عميل)
        "ALTER TABLE crm_leads ADD INDEX ws_company (workspace_id, company_id)",
    ],
    '061_perf_indexes' => [
        // أداء مع بيانات كبيرة (+20 ألف عميل): الإحالات كانت بتعمل مقارنة كل عميل بكل عميل (162 ثانية)
        "ALTER TABLE crm_leads ADD INDEX ws_ref (workspace_id, referred_by)",
        "ALTER TABLE crm_leads ADD INDEX ws_link (workspace_id, link_code)",
        "ALTER TABLE crm_leads ADD INDEX ws_created (workspace_id, created_at)",
        "ALTER TABLE crm_leads ADD INDEX ws_owner (workspace_id, owner_id)",
        "ALTER TABLE crm_acts ADD INDEX ws_created (workspace_id, created_at)",
    ],
    '060_comp_b2b' => [
        "ALTER TABLE competitor_site ADD COLUMN b2b MEDIUMTEXT NULL",
        "ALTER TABLE competitor_site ADD COLUMN b2b_at DATETIME NULL",
    ],
    '059_plans_removed' => [
        "UPDATE crm_plan_runs SET state = 'stopped' WHERE state IN ('wait', 'due')",
    ],
    '058_comp_compare' => [
        "ALTER TABLE competitors ADD COLUMN is_self TINYINT(1) NOT NULL DEFAULT 0",
        "CREATE TABLE IF NOT EXISTS competitor_saved (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            competitor_id INT UNSIGNED NOT NULL,
            kind VARCHAR(10) NOT NULL,
            ref_id INT UNSIGNED NOT NULL,
            note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_ref (workspace_id, kind, ref_id),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '057_comp_maps' => [
        "ALTER TABLE competitors ADD COLUMN maps TEXT NULL",
        "CREATE TABLE IF NOT EXISTS competitor_places (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            competitor_id INT UNSIGNED NOT NULL,
            place_id VARCHAR(120) NOT NULL,
            title VARCHAR(255) NULL,
            address VARCHAR(500) NULL,
            category VARCHAR(190) NULL,
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            rating DECIMAL(2,1) NULL,
            reviews_count INT UNSIGNED NULL,
            hours TEXT NULL,
            url VARCHAR(700) NULL,
            closed TINYINT(1) NOT NULL DEFAULT 0,
            last_check DATETIME NULL,
            reviews_at DATETIME NULL,
            UNIQUE KEY comp_place (competitor_id, place_id),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_place_days (
            place_row INT UNSIGNED NOT NULL,
            day DATE NOT NULL,
            rating DECIMAL(2,1) NULL,
            reviews_count INT UNSIGNED NULL,
            PRIMARY KEY (place_row, day),
            FOREIGN KEY (place_row) REFERENCES competitor_places(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            place_row INT UNSIGNED NOT NULL,
            ext_id VARCHAR(190) NOT NULL,
            stars TINYINT UNSIGNED NULL,
            text TEXT NULL,
            author VARCHAR(190) NULL,
            at DATETIME NULL,
            reply TEXT NULL,
            reply_at DATETIME NULL,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY place_ext (place_row, ext_id),
            INDEX place_at (place_row, at),
            FOREIGN KEY (place_row) REFERENCES competitor_places(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '056_comp_site' => [
        "CREATE TABLE IF NOT EXISTS competitor_site (
            competitor_id INT UNSIGNED NOT NULL PRIMARY KEY,
            tech TEXT NULL,
            pay TEXT NULL,
            codes TEXT NULL,
            seo TEXT NULL,
            psi TEXT NULL,
            psi_at DATETIME NULL,
            domain TEXT NULL,
            domain_at DATETIME NULL,
            pages_n INT UNSIGNED NULL,
            sitemap_at DATETIME NULL,
            checked_at DATETIME NULL,
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_pages (
            competitor_id INT UNSIGNED NOT NULL,
            url_hash CHAR(32) NOT NULL,
            url VARCHAR(700) NOT NULL,
            lastmod DATE NULL,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (competitor_id, url_hash),
            INDEX comp_seen (competitor_id, first_seen),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_site_days (
            competitor_id INT UNSIGNED NOT NULL,
            day DATE NOT NULL,
            ok TINYINT(1) NOT NULL,
            ms INT UNSIGNED NULL,
            PRIMARY KEY (competitor_id, day),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '055_comp_ads' => [
        "CREATE TABLE IF NOT EXISTS competitor_ads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            competitor_id INT UNSIGNED NOT NULL,
            ext_id VARCHAR(64) NOT NULL,
            page_name VARCHAR(190) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            started DATE NULL,
            ended DATE NULL,
            platforms VARCHAR(190) NULL,
            body TEXT NULL,
            title VARCHAR(300) NULL,
            cta VARCHAR(80) NULL,
            link VARCHAR(700) NULL,
            format VARCHAR(20) NULL,
            thumb VARCHAR(700) NULL,
            variants SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NULL,
            UNIQUE KEY comp_ext (competitor_id, ext_id),
            INDEX comp_act (competitor_id, active),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_landing (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            competitor_id INT UNSIGNED NOT NULL,
            url VARCHAR(700) NOT NULL,
            url_hash CHAR(32) NOT NULL,
            title VARCHAR(300) NULL,
            h1 VARCHAR(300) NULL,
            descr VARCHAR(500) NULL,
            prices VARCHAR(500) NULL,
            ok TINYINT(1) NOT NULL DEFAULT 0,
            fetched_at DATETIME NULL,
            UNIQUE KEY comp_url (competitor_id, url_hash),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE competitors ADD COLUMN ads_check DATETIME NULL",
    ],
    '054_comp_social2' => [
        "CREATE TABLE IF NOT EXISTS competitor_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            social_id INT UNSIGNED NOT NULL,
            post_ext VARCHAR(120) NOT NULL,
            ext_id VARCHAR(120) NOT NULL,
            parent_ext VARCHAR(120) NULL,
            author VARCHAR(190) NULL,
            text TEXT NULL,
            is_owner TINYINT(1) NOT NULL DEFAULT 0,
            at DATETIME NULL,
            UNIQUE KEY soc_ext (social_id, ext_id),
            INDEX soc_post (social_id, post_ext),
            FOREIGN KEY (social_id) REFERENCES competitor_social(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE competitor_posts ADD COLUMN views7 INT UNSIGNED NULL",
        "ALTER TABLE competitor_posts ADD COLUMN music VARCHAR(190) NULL",
        "ALTER TABLE competitor_social ADD COLUMN ext_url VARCHAR(500) NULL",
        "ALTER TABLE competitor_social ADD COLUMN comments_at DATETIME NULL",
    ],
    '053_comp_social' => [
        "CREATE TABLE IF NOT EXISTS competitor_social (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            competitor_id INT UNSIGNED NOT NULL,
            platform VARCHAR(20) NOT NULL,
            handle VARCHAR(190) NOT NULL,
            url VARCHAR(500) NOT NULL,
            name VARCHAR(190) NULL,
            followers INT UNSIGNED NULL,
            following INT UNSIGNED NULL,
            posts_count INT UNSIGNED NULL,
            bio TEXT NULL,
            avatar VARCHAR(500) NULL,
            last_check DATETIME NULL,
            last_ok TINYINT(1) NOT NULL DEFAULT 0,
            err VARCHAR(300) NULL,
            UNIQUE KEY comp_plat (competitor_id, platform),
            INDEX ws (workspace_id),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_social_days (
            social_id INT UNSIGNED NOT NULL,
            day DATE NOT NULL,
            followers INT UNSIGNED NULL,
            posts_count INT UNSIGNED NULL,
            PRIMARY KEY (social_id, day),
            FOREIGN KEY (social_id) REFERENCES competitor_social(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            social_id INT UNSIGNED NOT NULL,
            ext_id VARCHAR(120) NOT NULL,
            url VARCHAR(500) NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'image',
            caption TEXT NULL,
            thumb VARCHAR(700) NULL,
            hashtags TEXT NULL,
            mentions TEXT NULL,
            likes INT UNSIGNED NULL,
            comments INT UNSIGNED NULL,
            views INT UNSIGNED NULL,
            shares INT UNSIGNED NULL,
            paid TINYINT(1) NOT NULL DEFAULT 0,
            posted_at DATETIME NULL,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY soc_ext (social_id, ext_id),
            INDEX soc_time (social_id, posted_at),
            FOREIGN KEY (social_id) REFERENCES competitor_social(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '052_multi_contact' => [
        "ALTER TABLE crm_leads ADD COLUMN phones TEXT NULL",
        "ALTER TABLE crm_leads ADD COLUMN emails TEXT NULL",
    ],
    '051_designs' => [
        "CREATE TABLE IF NOT EXISTS designs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL,
            w SMALLINT UNSIGNED NOT NULL,
            h SMALLINT UNSIGNED NOT NULL,
            pages SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            data MEDIUMTEXT NOT NULL,
            thumb MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY ws_upd (workspace_id, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '050_followup_notify' => [
        "ALTER TABLE crm_leads ADD COLUMN next_notified DATETIME NULL",
    ],
    '049_target_enrich' => [
        "ALTER TABLE crm_companies ADD COLUMN enriched_at DATETIME NULL",
        "ALTER TABLE crm_companies ADD COLUMN about VARCHAR(500) NULL",
    ],
    '048_target_source' => [
        "ALTER TABLE crm_companies ADD COLUMN source VARCHAR(12) NULL",
        "ALTER TABLE crm_companies ADD INDEX ws_src (workspace_id, source)",
    ],
    '047_tenders' => [
        "CREATE TABLE IF NOT EXISTS tenders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            src VARCHAR(20) NOT NULL DEFAULT 'etimad',
            ext_id BIGINT UNSIGNED NOT NULL,
            ref VARCHAR(40) NULL,
            id_str VARCHAR(80) NULL,
            name VARCHAR(500) NOT NULL,
            agency VARCHAR(255) NULL,
            branch VARCHAR(255) NULL,
            type VARCHAR(80) NULL,
            activity VARCHAR(255) NULL,
            booklet DECIMAL(12,2) NULL,
            published_at DATETIME NULL,
            inquiry_at DATETIME NULL,
            deadline DATETIME NULL,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY src_ext (src, ext_id),
            INDEX dl (deadline),
            INDEX fs (first_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS tender_marks (
            workspace_id INT UNSIGNED NOT NULL,
            tender_id INT UNSIGNED NOT NULL,
            status VARCHAR(12) NOT NULL,
            note VARCHAR(500) NULL,
            lead_id INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (workspace_id, tender_id),
            INDEX ws_st (workspace_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '046_competitors' => [
        "CREATE TABLE IF NOT EXISTS competitors (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            url VARCHAR(500) NOT NULL,
            links TEXT NULL,
            note VARCHAR(500) NULL,
            snap MEDIUMTEXT NULL,
            last_check DATETIME NULL,
            last_ok TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id, last_check)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS competitor_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            competitor_id INT UNSIGNED NOT NULL,
            kind VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            detail VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id, id),
            INDEX comp (competitor_id, id),
            FOREIGN KEY (competitor_id) REFERENCES competitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '045_campaigns' => [
        "CREATE TABLE IF NOT EXISTS campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            service VARCHAR(190) NOT NULL DEFAULT '',
            start_at DATE NULL,
            end_at DATE NULL,
            lp_slug VARCHAR(60) NOT NULL DEFAULT '',
            coupon VARCHAR(24) NULL,
            codes TEXT NULL,
            post_ids TEXT NULL,
            mode VARCHAR(10) NOT NULL DEFAULT 'draft',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '044_ad_campaign' => [
        "ALTER TABLE crm_leads ADD COLUMN ad_campaign VARCHAR(64) NULL",
        "ALTER TABLE crm_leads ADD INDEX ws_adc (workspace_id, ad_campaign)",
    ],
    '043_crm_tasks' => [
        "CREATE TABLE IF NOT EXISTS crm_tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            lead_id INT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            due_at DATETIME NULL,
            done_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_user (workspace_id, user_id, done_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE crm_leads ADD COLUMN pinned TINYINT(1) NOT NULL DEFAULT 0",
    ],
    '042_crm_custom' => [
        "ALTER TABLE crm_leads ADD COLUMN pipeline VARCHAR(20) NOT NULL DEFAULT 'sales'",
        "ALTER TABLE crm_leads ADD COLUMN custom TEXT NULL",
        "ALTER TABLE crm_leads ADD COLUMN unsub TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE crm_leads ADD COLUMN no_answer SMALLINT UNSIGNED NOT NULL DEFAULT 0",
        "ALTER TABLE crm_plan_runs ADD COLUMN last_seen_in DATETIME NULL",
    ],
    '041_capture' => [
        "ALTER TABLE crm_leads ADD COLUMN q_budget VARCHAR(60) NULL",
        "ALTER TABLE crm_leads ADD COLUMN q_when VARCHAR(10) NULL",
        "ALTER TABLE crm_leads ADD COLUMN referred_by INT UNSIGNED NULL",
    ],
    '040_engage' => [
        "CREATE TABLE IF NOT EXISTS post_coupons (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            code VARCHAR(24) NOT NULL,
            label VARCHAR(160) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_code (workspace_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE crm_leads ADD COLUMN coupon VARCHAR(24) NULL",
    ],
    '039_post_ab' => [
        "CREATE TABLE IF NOT EXISTS post_ab (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            a_id VARCHAR(64) NOT NULL,
            b_id VARCHAR(64) NOT NULL,
            a_text VARCHAR(500) NOT NULL,
            b_text VARCHAR(500) NOT NULL,
            gap_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '038_post_evergreen' => [
        "CREATE TABLE IF NOT EXISTS post_evergreen (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            post_id VARCHAR(64) NOT NULL,
            every_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            next_at DATETIME NOT NULL,
            runs INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_post (workspace_id, post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '037_post_approvals' => [
        "CREATE TABLE IF NOT EXISTS post_approvals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            draft_id VARCHAR(64) NULL,
            payload MEDIUMTEXT NOT NULL,
            extra TEXT NULL,
            want VARCHAR(40) NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            note VARCHAR(300) NULL,
            decided_by INT UNSIGNED NULL,
            decided_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_status (workspace_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '036_crm_more' => [
        "ALTER TABLE crm_leads ADD COLUMN lost_reason VARCHAR(60) NULL",
        "ALTER TABLE crm_bookings ADD COLUMN reminded_24 TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN reminded_1 TINYINT(1) NOT NULL DEFAULT 0",
    ],
    '035_crm_notifs' => [
        "CREATE TABLE IF NOT EXISTS crm_notifs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            lead_id INT UNSIGNED NULL,
            kind VARCHAR(20) NOT NULL,
            body VARCHAR(300) NOT NULL,
            seen TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_user (workspace_id, user_id, seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE crm_leads ADD COLUMN sla_notified DATETIME NULL, ADD COLUMN sla_moved DATETIME NULL",
    ],
    '034_post_links' => [
        "CREATE TABLE IF NOT EXISTS crm_links (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            code VARCHAR(16) NOT NULL,
            url VARCHAR(600) NOT NULL,
            label VARCHAR(160) NULL,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            last_click DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY code (code),
            INDEX ws (workspace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE crm_leads ADD COLUMN link_code VARCHAR(16) NULL",
    ],
    '033_crm_visits' => [
        "CREATE TABLE IF NOT EXISTS crm_visits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            lead_id INT UNSIGNED NOT NULL,
            url VARCHAR(500) NOT NULL,
            hot TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_lead (workspace_id, lead_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '032_crm_bookings' => [
        "CREATE TABLE IF NOT EXISTS crm_bookings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            lead_id INT UNSIGNED NULL,
            start_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ws_start (workspace_id, start_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '031_crm_deals' => [
        "ALTER TABLE crm_leads ADD COLUMN won_at DATETIME NULL",
        "UPDATE crm_leads SET won_at = created_at WHERE stage = 'won'",
        "CREATE TABLE IF NOT EXISTS crm_deals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            lead_id INT UNSIGNED NOT NULL,
            owner_id INT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            value DECIMAL(14,2) NOT NULL DEFAULT 0,
            status VARCHAR(10) NOT NULL DEFAULT 'open',
            close_at DATE NULL,
            closed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_lead (workspace_id, lead_id),
            INDEX ws_status (workspace_id, status, closed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '030_crm_mails' => [
        "CREATE TABLE IF NOT EXISTS crm_mails (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            lead_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            token CHAR(32) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            opens INT UNSIGNED NOT NULL DEFAULT 0,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            opened_at DATETIME NULL,
            clicked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY tok (token),
            INDEX ws_lead (workspace_id, lead_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '029_crm_plans' => [
        "CREATE TABLE IF NOT EXISTS crm_plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            steps TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS crm_plan_runs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            lead_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            step SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            state VARCHAR(10) NOT NULL DEFAULT 'wait',
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            due_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_error VARCHAR(255) NULL,
            INDEX ws_due (workspace_id, state, due_at),
            INDEX ws_lead (workspace_id, lead_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '028_crm_form' => [
        "CREATE TABLE IF NOT EXISTS crm_form_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            ip VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_ip (workspace_id, ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '027_crm_apollo_plus' => [
        "ALTER TABLE crm_leads ADD COLUMN email_status VARCHAR(20) NULL, ADD COLUMN enriched_at DATETIME NULL",
        "CREATE TABLE IF NOT EXISTS crm_searches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL,
            filters TEXT NOT NULL,
            seen MEDIUMTEXT NULL,
            new_ids TEXT NULL,
            new_count INT UNSIGNED NOT NULL DEFAULT 0,
            total INT UNSIGNED NOT NULL DEFAULT 0,
            run_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws (workspace_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '026_crm_social' => [
        "ALTER TABLE crm_leads ADD COLUMN src_url VARCHAR(500) NULL",
    ],
    '025_crm_acts' => [
        "CREATE TABLE IF NOT EXISTS crm_acts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            lead_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            kind VARCHAR(20) NOT NULL,
            body VARCHAR(2000) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_lead (workspace_id, lead_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE crm_leads ADD COLUMN last_contact_at DATETIME NULL",
    ],
    '024_post_notes' => [
        "CREATE TABLE IF NOT EXISTS post_notes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workspace_id INT UNSIGNED NOT NULL,
            post_id VARCHAR(64) NOT NULL,
            user_id INT UNSIGNED NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ws_post (workspace_id, post_id),
            FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    '081_cp_item_txt' => [
        "ALTER TABLE cp_items ADD COLUMN txt_opts TEXT NULL",
    ],
    // مستند التصميم القابل للإعادة (Fabric JSON) + قفل التعديل اليدوي: لا يُدهس عند إعادة التركيب
    '082_cp_item_design_doc' => [
        "ALTER TABLE cp_items ADD COLUMN design_doc MEDIUMTEXT NULL",
    ],
    // وسم استهلاك الذكاء بمرجع (مثل plan:123) لحساب التكلفة لكل حملة/منشور من بيانات ai_usage الحقيقية
    '083_ai_usage_ref' => [
        "ALTER TABLE ai_usage ADD COLUMN ref VARCHAR(40) NULL",
        "ALTER TABLE ai_usage ADD INDEX idx_ref (ref)",
    ],
    // سجل نسخ مستند التصميم (آخر نسخ محفوظة) للرجوع إليها دون فقدان
    '084_cp_item_doc_hist' => [
        "ALTER TABLE cp_items ADD COLUMN design_doc_hist MEDIUMTEXT NULL",
    ],
    // مراجع بصرية خاصة بكل منشور (تتجاوز مراجع الخطة عند التوليد) — JSON: {refs:[mediaIds], presets:[1..5], products:[mediaIds]}
    '085_cp_item_refs' => [
        "ALTER TABLE cp_items ADD COLUMN refs TEXT NULL",
    ],
    // وورك سبيس ديمو مشتركة (عرض فقط) لوضع التعلّم — تُزرع مرة واحدة ولا ترمي أبدًا (آمنة تمامًا)
    '086_demo_workspace' => [
        function (PDO $pdo) {
            try {
                // موجودة بالفعل؟ لا تكرّر
                $cur = $pdo->query("SELECT value FROM settings WHERE name = 'demo_ws_id'")->fetchColumn();
                if ($cur) {
                    $ok = $pdo->prepare("SELECT 1 FROM workspaces WHERE id = ? AND COALESCE(status,'active') <> 'deleted'");
                    $ok->execute([(int)$cur]); if ($ok->fetchColumn()) return;
                }
                $pdo->prepare("INSERT INTO workspaces (name, created_by, timezone, approval_status, sub_status, plan, legacy, admin_override)
                    VALUES (?, NULL, 'Asia/Riyadh', 'approved', 'active', 'pro', 0, 1)")
                    ->execute(['🎓 Demo']);
                $wid = (int)$pdo->lastInsertId();
                if ($wid <= 0) return;
                $pdo->prepare("INSERT INTO settings (name, value) VALUES ('demo_ws_id', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([(string)$wid]);
                $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, 'SA') ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute(['country_' . $wid]);

                $kb = function (string $section, array $data) use ($pdo, $wid) {
                    try { $pdo->prepare("INSERT INTO kb_sections (workspace_id, section, data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data)")
                        ->execute([$wid, $section, json_encode($data, JSON_UNESCAPED_UNICODE)]); } catch (Throwable $e) {}
                };
                $kb('company', [
                    'name' => 'وتد للحلول التقنية', 'industry' => 'الأمن السيبراني والأتمتة الصناعية والتحول الرقمي',
                    'tagline' => 'شريكك الموثوق في حماية البنية التحتية الصناعية (IT/OT) للمنشآت الكبرى',
                    'about' => "تأسست وتد عام 2013، وتضم أكثر من 180 موظفًا و4 فروع في المملكة. متخصّصة في الأمن السيبراني للتقنيات التشغيلية (OT)، جدران الحماية من الجيل القادم، مراكز العمليات الأمنية (SOC)، الأتمتة الصناعية (SCADA)، والذكاء الاصطناعي للمنشآت في قطاعات الطاقة والصناعة والقطاع الحكومي. أكثر من 312 عميلًا و معدّل رضا (NPS) يبلغ 71.",
                    'branches' => "الرياض — المقر الرئيسي\nالدمام — المنطقة الشرقية\nجدة — المنطقة الغربية\nالجبيل — المدينة الصناعية",
                    'service_area' => 'جميع مناطق المملكة العربية السعودية، مع تركيز على المنشآت الصناعية الكبرى',
                    'payment' => 'تحويل بنكي، عقود سنوية، أوامر شراء مؤسسية', 'delivery' => 'تنفيذ وتركيب في الموقع خلال 2–6 أسابيع حسب حجم المشروع',
                ]);
                $kb('services', ['rows' => [
                    ['name' => 'أمن التقنيات التشغيلية (OT Security)', 'price' => '420,000 ر.س', 'details' => 'حماية شبكات SCADA/ICS، عزل الشبكات، مراقبة التهديدات الصناعية والامتثال لضوابط الهيئة الوطنية للأمن السيبراني.'],
                    ['name' => 'جدار الحماية من الجيل القادم (NGFW)', 'price' => '180,000 ر.س', 'details' => 'جدران حماية متقدمة مع فحص عميق للحزم ومنع التسلل لحماية حدود الشبكة.'],
                    ['name' => 'مركز العمليات الأمنية (SOC)', 'price' => '650,000 ر.س', 'details' => 'مراقبة أمنية على مدار الساعة، كشف واستجابة للحوادث، فريق محلّلين معتمد.'],
                    ['name' => 'إدارة الأحداث الأمنية (SIEM)', 'price' => '300,000 ر.س', 'details' => 'تجميع وتحليل سجلات الأحداث وربط المؤشرات للكشف المبكر عن الاختراقات.'],
                    ['name' => 'الأتمتة الصناعية (SCADA)', 'price' => '540,000 ر.س', 'details' => 'تحديث وتأمين أنظمة التحكم الصناعي ورفع كفاءة الإنتاج.'],
                ]]);
                $kb('audience', [
                    'who' => 'مدراء أمن المعلومات (CISO)، مدراء العمليات الصناعية، والرؤساء التنفيذيون للتقنية (CTO) في منشآت الطاقة والصناعة والقطاع الحكومي الكبرى.',
                    'traits' => 'صناع قرار بميزانيات مؤسسية، يهتمون بالامتثال التنظيمي (NCA) وتقليل مخاطر توقّف الإنتاج، ويشترون عبر مناقصات وعقود طويلة.',
                    'problem' => 'تهديدات متزايدة على شبكات OT/SCADA، ضغوط الامتثال، وخطر توقّف إنتاج مكلف عند أي اختراق.',
                    'objections' => 'التكلفة العالية، تعقيد التكامل مع الأنظمة القائمة، والحاجة لإثبات العائد وخفض المخاطر فعليًا.',
                ]);
                $kb('tone', ['style' => ['professional', 'expert'], 'dialect' => 'saudi', 'lang_rule' => 'default', 'emoji' => 'light',
                    'notes' => 'نبرة احترافية واثقة موجّهة لصنّاع القرار، تركّز على المخاطر والعائد بلغة تقنية دقيقة بلا مبالغة.']);
                $kb('focus', ['text' => 'الترويج لخدمة أمن التقنيات التشغيلية (OT) ومراكز العمليات الأمنية (SOC) لقطاع الطاقة خلال الربع الحالي.']);
                $kb('pod', ['text' => "فريق متخصّص في أمن OT الصناعي تحديدًا (وليس IT فقط)، مع خبرة محلية في بيئات SCADA للطاقة والبتروكيماويات، ومركز عمليات أمنية يعمل 24/7 بمحلّلين سعوديين معتمدين."]);
                $kb('rtb', ['text' => "+312 عميلًا مؤسسيًا منذ 2013\nفريق محلّلين معتمد (GICSP, CISSP)\nمركز عمليات أمنية محلي 24/7\nامتثال كامل لضوابط الهيئة الوطنية للأمن السيبراني"]);

                // منافسون للعرض
                $comp = $pdo->prepare("INSERT INTO competitors (workspace_id, name, url, note) VALUES (?, ?, ?, ?)");
                foreach ([
                    ['SecureNet KSA', 'https://securenet.example.sa', 'منافس مباشر — قوي في IT security، أضعف في OT.'],
                    ['CyberGulf', 'https://cybergulf.example', 'ينشر بكثافة لكن تفاعله أقل. يركّز على الحكومي.'],
                    ['Gulf Defense Systems', 'https://gds.example', 'لاعب إقليمي في الأتمتة الصناعية.'],
                ] as $c) { try { $comp->execute([$wid, $c[0], $c[1], $c[2]]); } catch (Throwable $e) {} }
            } catch (Throwable $e) { /* أبدًا لا نكسر النشر بسبب بيانات الديمو */ }
        },
    ],
    // المرحلة 2 من بيانات الديمو: عملاء (CRM) + محتوى معتمد (مخزون المحتوى). منفصلة لأن 086 اتشغّلت بالفعل.
    '087_demo_data_2' => [
        function (PDO $pdo) {
            try {
                $wid = (int)($pdo->query("SELECT value FROM settings WHERE name = 'demo_ws_id'")->fetchColumn());
                if ($wid <= 0) return;
                // تصحيح اسم وورك سبيس الديمو (كان فيه اسم الشركة الوهمية)
                try { $pdo->prepare("UPDATE workspaces SET name = '🎓 Demo' WHERE id = ?")->execute([$wid]); } catch (Throwable $e) {}
                // لا تكرّر لو اتزرعت قبل كده
                $has = $pdo->prepare("SELECT COUNT(*) FROM crm_leads WHERE workspace_id = ?"); $has->execute([$wid]);
                if ((int)$has->fetchColumn() > 0) return;

                // عملاء محتملون (B2B)
                $lead = $pdo->prepare("INSERT INTO crm_leads (workspace_id, source, company, name, title, email, phone, country, stage, deal_value, note)
                    VALUES (?, 'manual', ?, ?, ?, ?, ?, 'SA', ?, ?, ?)");
                foreach ([
                    ['أرامكو السعودية', 'م. خالد العتيبي', 'مدير أمن المعلومات (CISO)', 'k.otaibi@example.sa', '+966500000001', 'qualified', 2400000, 'مهتم بحل أمن OT لمصفاة الرياض. اجتماع فني الأسبوع القادم.'],
                    ['سابك', 'أ. نورة القحطاني', 'مدير العمليات الصناعية', 'n.q@example.sa', '+966500000002', 'proposal', 3100000, 'أُرسل عرض تحديث SCADA. بانتظار موافقة اللجنة.'],
                    ['شركة الكهرباء السعودية', 'م. سعد الدوسري', 'مدير البنية التحتية', 's.d@example.sa', '+966500000003', 'contacted', 1800000, 'تواصل أولي حول SOC. طلب دراسة جدوى.'],
                    ['موانئ (الهيئة العامة)', 'أ. ريم الشمري', 'CTO', 'r.shamri@example.sa', '+966500000004', 'won', 650000, 'عقد جدار حماية موقّع. بدء التنفيذ.'],
                    ['معادن', 'م. فهد الحربي', 'رئيس الأمن السيبراني', 'f.h@example.sa', '+966500000005', 'new', 540000, 'عميل جديد من حملة LinkedIn.'],
                    ['البنك الأهلي', 'أ. لمى العنزي', 'مدير المخاطر التقنية', 'l.anzi@example.sa', '+966500000006', 'qualified', 900000, 'مهتم بـ SIEM. حدّدنا موعد عرض.'],
                ] as $l) { try { $lead->execute([$wid, $l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $l[6], $l[7]]); } catch (Throwable $e) {} }

                // خطة محتوى + منشورات معتمدة (تظهر في «مخزون المحتوى»)
                $pdo->prepare("INSERT INTO cp_plans (workspace_id, name, goal, cnt, stage, status, opts)
                    VALUES (?, ?, ?, ?, 'done', 'review', ?)")
                    ->execute([$wid, 'حملة أمن OT — الربع الرابع', 'الوصول لمدراء الأمن في قطاع الطاقة وحجز عروض OT', 5,
                        json_encode(['lang' => 'ar', 'dialect' => 'saudi', 'design_mode' => 'ai'], JSON_UNESCAPED_UNICODE)]);
                $pid = (int)$pdo->lastInsertId();
                if ($pid > 0) {
                    $it = $pdo->prepare("INSERT INTO cp_items (plan_id, workspace_id, seq, topic, linkedin, x, headline, image_prompt, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
                    $posts = [
                        ['من يراقب شبكة الـ SCADA ليلاً؟', 'أغلب اختراقات المنشآت الصناعية تبدأ بعد ساعات العمل، حين لا يراقب أحد. مركز عمليات أمنية (SOC) يعمل 24/7 يكشف الشذوذ في دقائق قبل أن يصل للإنتاج. النتيجة: توقّف أقل، ومخاطر أقل. احجز تقييمًا أمنيًا لبيئة OT لديك.\n#الأمن_السيبراني #OT #SCADA', 'من يراقب الـ SCADA بعد انتهاء الدوام؟ SOC يعمل 24/7 يكشف الاختراق في دقائق. #أمن_OT', 'من يراقب «SCADA»؟'],
                        ['النسخ الاحتياطي ليس خطة', 'النسخة الاحتياطية تعيد البيانات، لكنها لا توقف الهجوم ولا تمنع توقّف الإنتاج. الخطة الحقيقية: عزل الشبكات، مراقبة مستمرة، واستجابة سريعة. هكذا تتحول من رد الفعل إلى الاستباق. تحدّث معنا عن جاهزية منشأتك.\n#أمن_المعلومات #OT', 'النسخ الاحتياطي يعيد البيانات، لا يوقف الهجوم. الخطة = عزل + مراقبة + استجابة. #أمن_OT', '«النسخ» ليس خطة'],
                        ['٧٠٪ من الاختراقات تبدأ من الطرف', 'الأجهزة الطرفية هي الباب الخلفي لشبكتك الصناعية. جدار حماية من الجيل القادم (NGFW) يفحص كل اتصال ويمنع التسلل قبل أن يصل للأنظمة الحرجة. ابدأ من حيث يبدأ المهاجم. اطلب عرضًا فنيًا.\n#NGFW #الأمن_السيبراني', '٧٠٪ من الاختراقات تبدأ من الطرف. NGFW يفحص كل اتصال. #أمن_الشبكات', '«٧٠٪» من الطرف'],
                    ];
                    $seq = 1;
                    foreach ($posts as $p) { try { $it->execute([$pid, $wid, $seq++, json_encode(['title' => $p[0], 'angle' => '', 'service' => 'OT Security', 'audience' => 'CISO', 'objective' => 'leads'], JSON_UNESCAPED_UNICODE), $p[1], $p[2], $p[3], 'A premium full-canvas cybersecurity social design, dark control-room scene, brand teal accents, headline integrated top-left. 4:5 1080x1350.']); } catch (Throwable $e) {} }
                }
            } catch (Throwable $e) { /* بيانات الديمو لا تكسر النشر أبدًا */ }
        },
    ],
    // المرحلة 3: تعبئة كثيفة (عملاء ومنافسون ومحتوى أكثر) ليحسّها المستخدم وورك سبيس حقيقية مليانة
    '088_demo_data_3' => [
        function (PDO $pdo) {
            try {
                $wid = (int)($pdo->query("SELECT value FROM settings WHERE name = 'demo_ws_id'")->fetchColumn());
                if ($wid <= 0) return;
                if ((int)$pdo->query("SELECT value FROM settings WHERE name = 'demo_seed_v3'")->fetchColumn() === 1) return;
                $pdo->prepare("INSERT INTO settings (name, value) VALUES ('demo_seed_v3', '1') ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();

                // منافسون إضافيون
                $comp = $pdo->prepare("INSERT INTO competitors (workspace_id, name, url, note) VALUES (?, ?, ?, ?)");
                foreach ([
                    ['Nexus Security', 'https://nexus.example', 'قوي في SIEM، ضعيف في OT.'],
                    ['SahmTech', 'https://sahmtech.example', 'أسعار منخفضة، جودة متوسطة.'],
                    ['Falcon Cyber', 'https://falcon.example', 'يركّز على القطاع المالي.'],
                    ['TechGuard Arabia', 'https://techguard.example', 'تغطية إعلامية جيدة.'],
                    ['OT Shield', 'https://otshield.example', 'منافس متخصص في OT — الأقوى.'],
                    ['DataHaven', 'https://datahaven.example', 'حلول نسخ احتياطي وتعافي.'],
                ] as $c) { try { $comp->execute([$wid, $c[0], $c[1], $c[2]]); } catch (Throwable $e) {} }

                // عملاء إضافيون (كثير)
                $lead = $pdo->prepare("INSERT INTO crm_leads (workspace_id, source, company, name, title, email, phone, country, stage, deal_value, note)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'SA', ?, ?, ?)");
                $firsts = ['محمد', 'عبدالله', 'فيصل', 'سلطان', 'نواف', 'ماجد', 'بندر', 'تركي', 'ياسر', 'وليد', 'نورة', 'ريم', 'لمى', 'سارة', 'هند', 'العنود', 'منى', 'جواهر'];
                $lasts = ['العتيبي', 'القحطاني', 'الدوسري', 'الحربي', 'الشمري', 'الغامدي', 'الزهراني', 'المطيري', 'السبيعي', 'العنزي'];
                $titles = ['مدير أمن المعلومات (CISO)', 'مدير العمليات الصناعية', 'الرئيس التنفيذي للتقنية (CTO)', 'رئيس الأمن السيبراني', 'مدير البنية التحتية', 'مدير المخاطر التقنية', 'مهندس أمن أول'];
                $companies = ['أرامكو', 'سابك', 'معادن', 'شركة الكهرباء', 'موانئ', 'البنك الأهلي', 'stc', 'الراجحي', 'نيوم', 'البحري', 'ساسكو', 'المراعي', 'تداول', 'علم', 'الاتصالات المتكاملة', 'زين', 'موبايلي', 'أكوا باور'];
                $stages = ['new', 'contacted', 'qualified', 'proposal', 'won'];
                $srcs = ['linkedin', 'form', 'manual', 'ad'];
                for ($i = 0; $i < 22; $i++) {
                    $fn = $firsts[$i % count($firsts)]; $ln = $lasts[$i % count($lasts)];
                    $co = $companies[$i % count($companies)] . ' — ' . ['الرياض', 'الدمام', 'جدة', 'الجبيل', 'ينبع'][$i % 5];
                    $val = [250000, 420000, 540000, 650000, 900000, 1200000, 1800000, 2400000, 3100000][$i % 9];
                    try { $lead->execute([$wid, $srcs[$i % count($srcs)], $co, "$fn $ln", $titles[$i % count($titles)],
                        'lead' . ($i + 10) . '@example.sa', '+96650' . str_pad((string)(1000000 + $i), 7, '0'),
                        $stages[$i % count($stages)], $val, 'عميل محتمل من قطاع الطاقة/الصناعة.']); } catch (Throwable $e) {}
                }

                // كامبين محتوى كامل (١٢ منشور) — نصفها معتمد (مخزون المحتوى) والباقي مسودّات (مراجعة)
                $pdo->prepare("INSERT INTO cp_plans (workspace_id, name, goal, cnt, stage, status, opts)
                    VALUES (?, ?, ?, ?, 'done', 'review', ?)")
                    ->execute([$wid, 'حملة التوعية الأمنية — ديسمبر', 'بناء وعي صنّاع القرار بمخاطر OT وتوليد عملاء مؤهّلين', 12,
                        json_encode(['lang' => 'ar', 'dialect' => 'saudi', 'design_mode' => 'ai'], JSON_UNESCAPED_UNICODE)]);
                $pid = (int)$pdo->lastInsertId();
                if ($pid > 0) {
                    $it = $pdo->prepare("INSERT INTO cp_items (plan_id, workspace_id, seq, topic, linkedin, x, headline, image_prompt, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $topics = [
                        ['كم يكلّفك توقّف إنتاج لساعة؟', '«ساعة» تفرق'],
                        ['٣ أخطاء تترك شبكة OT مكشوفة', '٣ «أخطاء» قاتلة'],
                        ['كيف يكشف الـ SOC الاختراق في دقائق', 'يكشف في «دقائق»'],
                        ['الامتثال لضوابط الهيئة ليس اختياريًا', '«الامتثال» أولًا'],
                        ['جدار الحماية وحده لا يكفي', '«جدار» واحد لا يكفي'],
                        ['ماذا يحدث في أول ٦٠ ثانية من الهجوم؟', 'أول «٦٠» ثانية'],
                        ['قصة نجاح: صفر توقّف خلال ١٨ شهر', '«صفر» توقّف'],
                        ['لماذا تستهدف الهجمات قطاع الطاقة؟', 'لماذا «الطاقة»؟'],
                        ['الفرق بين IT security و OT security', 'IT مقابل «OT»'],
                        ['دليلك لاختيار مزود أمن سيبراني', 'كيف «تختار»؟'],
                        ['٥ علامات أن شبكتك مخترقة الآن', '٥ «علامات» خطر'],
                        ['لماذا السرعة في الاستجابة تكسب؟', '«السرعة» تكسب'],
                    ];
                    foreach ($topics as $k => $tp) {
                        $seq = $k + 1;
                        $st = $k < 6 ? 'approved' : 'draft'; // نصفها معتمد ونصفها مسودّة
                        $li = $tp[0] . "\n\n" . 'المنشآت الصناعية الكبرى هدف دائم للهجمات السيبرانية. الاستثمار في حماية OT اليوم أرخص بكثير من كلفة توقّف الإنتاج أو تسريب البيانات غدًا. تحدّث معنا عن جاهزية منشأتك.' . "\n#الأمن_السيبراني #OT #SCADA";
                        $x = $tp[0] . ' — احمِ بنيتك الصناعية قبل فوات الأوان. #أمن_OT';
                        try { $it->execute([$pid, $wid, $seq,
                            json_encode(['title' => $tp[0], 'angle' => '', 'service' => 'OT Security', 'audience' => 'CISO', 'objective' => 'awareness'], JSON_UNESCAPED_UNICODE),
                            $li, $x, $tp[1],
                            'A premium full-canvas cybersecurity social design, dark industrial control-room, brand teal accents, integrated headline. 4:5 1080x1350.', $st]); } catch (Throwable $e) {}
                    }
                }
            } catch (Throwable $e) { /* الديمو لا يكسر النشر */ }
        },
    ],
    // ضمان وجود محتوى معتمد في الديمو (لو فشل زرع المحتوى في المراحل السابقة لأي سبب)
    '089_demo_content_ensure' => [
        function (PDO $pdo) {
            try {
                $wid = (int)($pdo->query("SELECT value FROM settings WHERE name = 'demo_ws_id'")->fetchColumn());
                if ($wid <= 0) return;
                $ok = $pdo->prepare("SELECT COUNT(*) FROM cp_items WHERE workspace_id = ? AND status = 'approved'");
                $ok->execute([$wid]);
                if ((int)$ok->fetchColumn() > 0) return; // المحتوى موجود بالفعل

                $pdo->prepare("INSERT INTO cp_plans (workspace_id, name, goal, cnt, stage, status, opts)
                    VALUES (?, 'حملة المحتوى التجريبية', 'الوصول لصنّاع القرار في الأمن السيبراني وتوليد عملاء', 9, 'done', 'review', '{\"lang\":\"ar\",\"design_mode\":\"ai\"}')")
                    ->execute([$wid]);
                $pid = (int)$pdo->lastInsertId();
                if ($pid <= 0) return;
                $it = $pdo->prepare("INSERT INTO cp_items (plan_id, workspace_id, seq, topic, linkedin, x, headline, image_prompt, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $rows = [
                    ['من يراقب شبكة الـ SCADA ليلاً؟', 'من «SCADA»؟', 'approved'],
                    ['النسخ الاحتياطي ليس خطة', '«النسخ» ليس خطة', 'approved'],
                    ['٧٠٪ من الاختراقات تبدأ من الطرف', '«٧٠٪» من الطرف', 'approved'],
                    ['كم يكلّفك توقّف إنتاج لساعة؟', '«ساعة» تفرق', 'approved'],
                    ['كيف يكشف الـ SOC الاختراق في دقائق', 'يكشف في «دقائق»', 'approved'],
                    ['٣ أخطاء تترك شبكة OT مكشوفة', '٣ «أخطاء»', 'approved'],
                    ['جدار الحماية وحده لا يكفي', '«جدار» لا يكفي', 'draft'],
                    ['الامتثال لضوابط الهيئة ليس اختياريًا', '«الامتثال» أولًا', 'draft'],
                    ['٥ علامات أن شبكتك مخترقة الآن', '٥ «علامات»', 'draft'],
                ];
                $seq = 1;
                foreach ($rows as $r) {
                    $li = $r[0] . "\n\nحماية البنية الصناعية استثمار يوفّر كلفة التوقّف. تحدّث معنا عن جاهزية منشأتك.\n#الأمن_السيبراني #OT";
                    $x = $r[0] . ' — احمِ بنيتك قبل فوات الأوان. #أمن_OT';
                    try { $it->execute([$pid, $wid, $seq++, json_encode(['title' => $r[0], 'service' => 'OT Security', 'audience' => 'CISO', 'objective' => 'awareness'], JSON_UNESCAPED_UNICODE), $li, $x, $r[1], 'A premium full-canvas cybersecurity social design, dark control-room, teal accents, integrated headline. 4:5 1080x1350.', $r[2]]); } catch (Throwable $e) {}
                }
            } catch (Throwable $e) {}
        },
    ],
];
