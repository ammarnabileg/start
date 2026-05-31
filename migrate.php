<?php
/**
 * migrate.php — Run to apply all schema migrations.
 * Usage: php migrate.php  OR  visit in browser.
 */
require_once __DIR__ . '/includes/db.php';

$results = [];

$migrations = [

    // Wallet
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00"
    => 'wallet_balance column on users',

    "CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        type ENUM('deposit','community_join','community_creation','course_purchase','refund','admin_credit','withdrawal') NOT NULL,
        description VARCHAR(500),
        reference_id INT DEFAULT NULL,
        balance_after DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    => 'wallet_transactions table',

    // Topics
    "CREATE TABLE IF NOT EXISTS topics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    => 'topics table',

    // Add topic_id to posts if not exists
    "ALTER TABLE posts ADD COLUMN IF NOT EXISTS topic_id INT DEFAULT NULL"
    => 'topic_id column on posts',

    // Badges
    "CREATE TABLE IF NOT EXISTS badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        icon VARCHAR(10) NOT NULL DEFAULT '🏅',
        description VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    => 'badges table',

    // User badges
    "CREATE TABLE IF NOT EXISTS user_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        badge_id INT NOT NULL,
        community_id INT NOT NULL,
        awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    => 'user_badges table',

    // Community points
    "CREATE TABLE IF NOT EXISTS community_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        community_id INT NOT NULL,
        points INT NOT NULL DEFAULT 0,
        reason VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    => 'community_points table',

    // Member points summary (leaderboard)
    "CREATE TABLE IF NOT EXISTS member_points (
        user_id INT NOT NULL,
        community_id INT NOT NULL,
        total_points INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, community_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    => 'member_points table',

    // User streaks
    "CREATE TABLE IF NOT EXISTS user_streaks (
        user_id INT NOT NULL,
        community_id INT NOT NULL,
        current_streak INT NOT NULL DEFAULT 0,
        longest_streak INT NOT NULL DEFAULT 0,
        last_active DATE DEFAULT NULL,
        PRIMARY KEY (user_id, community_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    => 'user_streaks table',

];

foreach ($migrations as $sql => $label) {
    try {
        get_pdo()->exec($sql);
        $results[] = ['status' => 'ok', 'msg' => $label];
    } catch (Exception $e) {
        $results[] = ['status' => 'error', 'msg' => $label . ': ' . $e->getMessage()];
    }
}

$is_cli = (php_sapi_name() === 'cli');
if ($is_cli) {
    foreach ($results as $r) {
        echo "[{$r['status']}] {$r['msg']}\n";
    }
} else {
    echo '<!DOCTYPE html><html><head><title>Migration</title><style>body{font-family:sans-serif;padding:40px;background:#111;color:#eee} li{margin:6px 0} .ok{color:#4ade80} .error{color:#f87171}</style></head><body>';
    echo '<h2>Platform Migration</h2><ul>';
    foreach ($results as $r) {
        echo '<li class="' . $r['status'] . '">[' . htmlspecialchars($r['status']) . '] ' . htmlspecialchars($r['msg']) . '</li>';
    }
    echo '</ul><p style="color:#aaa;margin-top:20px">Done. You can delete this file after running it.</p></body></html>';
}
