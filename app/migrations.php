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
];
