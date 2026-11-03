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
                save_connector($pdo, (int)$r['id'], 'zernio', ['api_key' => $r['api_key']],
                    $r['profile_id'] ? ['profile_id' => $r['profile_id']] : []);
            }
        },

        "ALTER TABLE workspaces DROP COLUMN api_key, DROP COLUMN profile_id",
    ],
];
