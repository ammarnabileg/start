<?php
/**
 * SociAI OS - Cron: Sync Social Media Interactions
 * Run every 5 minutes via Plesk/cPanel cron:
 *   * /5 * * * * /usr/bin/php /var/www/vhosts/domain.com/httpdocs/cron/sync_interactions.php >> /var/log/sociai_sync.log 2>&1
 */

declare(strict_types=1);

// CLI only
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/Security.php';
require_once BASE_PATH . '/core/Auth.php';
require_once BASE_PATH . '/core/AI.php';
require_once BASE_PATH . '/core/PlatformManager.php';

use SociAI\Core\{Database, PlatformManager};

$startTime = microtime(true);
$now       = date('Y-m-d H:i:s');

echo "[{$now}] Starting interaction sync...\n";

try {
    $db = Database::getInstance();

    // Get all active brands
    $brands = $db->fetchAll(
        "SELECT DISTINCT b.id, b.name
         FROM brands b
         INNER JOIN platform_accounts pa ON pa.brand_id = b.id
         WHERE b.is_active = 1 AND pa.is_active = 1"
    );

    if (empty($brands)) {
        echo "[{$now}] No active brands with connected platforms found.\n";
        exit(0);
    }

    echo "[{$now}] Found " . count($brands) . " brand(s) to sync.\n";

    $totalNew = 0;

    foreach ($brands as $brand) {
        $brandId   = $brand['id'];
        $brandName = $brand['name'];

        echo "[{$now}] Syncing brand: {$brandName} ({$brandId})\n";

        try {
            $newCount = PlatformManager::syncAllInteractions($brandId);
            $totalNew += $newCount;
            echo "[{$now}]   → {$newCount} new interaction(s) for {$brandName}\n";
        } catch (\Throwable $e) {
            echo "[{$now}]   ERROR syncing {$brandName}: " . $e->getMessage() . "\n";
            error_log("[cron/sync_interactions] Brand {$brandId} error: " . $e->getMessage());
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[{$now}] Sync complete. Total new interactions: {$totalNew}. Elapsed: {$elapsed}s\n";

    // Log to file
    $logLine = "[{$now}] sync_interactions completed: {$totalNew} new interactions in {$elapsed}s\n";
    file_put_contents(LOG_PATH . '/cron_sync.log', $logLine, FILE_APPEND | LOCK_EX);
} catch (\Throwable $e) {
    $msg = "[{$now}] FATAL sync_interactions error: " . $e->getMessage();
    echo $msg . "\n";
    error_log($msg);
    exit(1);
}

exit(0);
