<?php
/**
 * migrate.php — Run once to apply wallet schema migrations.
 * Usage: php migrate.php  OR  visit in browser (CLI or HTTP).
 */
require_once __DIR__ . '/includes/db.php';

$results = [];

// 1. Add wallet_balance column to users if not exists
try {
    get_pdo()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    $results[] = ['status' => 'ok', 'msg' => 'wallet_balance column ensured on users table'];
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'msg' => 'wallet_balance: ' . $e->getMessage()];
}

// 2. Create wallet_transactions table if not exists
try {
    get_pdo()->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        type ENUM('deposit','community_join','community_creation','course_purchase','refund','admin_credit','withdrawal') NOT NULL,
        description VARCHAR(500),
        reference_id INT DEFAULT NULL,
        balance_after DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = ['status' => 'ok', 'msg' => 'wallet_transactions table ensured'];
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'msg' => 'wallet_transactions: ' . $e->getMessage()];
}

// Output results
$is_cli = (php_sapi_name() === 'cli');
if ($is_cli) {
    foreach ($results as $r) {
        echo "[{$r['status']}] {$r['msg']}\n";
    }
} else {
    echo '<!DOCTYPE html><html><head><title>Migration</title></head><body style="font-family:sans-serif;padding:40px">';
    echo '<h2>Wallet Migration</h2><ul>';
    foreach ($results as $r) {
        $color = $r['status'] === 'ok' ? 'green' : 'red';
        echo '<li style="color:' . $color . '">[' . htmlspecialchars($r['status']) . '] ' . htmlspecialchars($r['msg']) . '</li>';
    }
    echo '</ul></body></html>';
}
