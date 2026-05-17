<?php
/**
 * SociAI OS - Cron: Generate AI Replies for Pending Interactions
 * Run every 10 minutes via Plesk/cPanel cron:
 *   * /10 * * * * /usr/bin/php /var/www/vhosts/domain.com/httpdocs/cron/generate_ai_replies.php >> /var/log/sociai_ai_replies.log 2>&1
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
$limit     = 50; // Max per run to avoid timeouts

echo "[{$now}] Starting AI reply generation (limit: {$limit})...\n";

try {
    $db = Database::getInstance();

    // Get pending interactions that don't have AI replies yet
    $interactions = $db->fetchAll(
        "SELECT ci.*, b.name AS brand_name, b.settings AS brand_settings, b.description AS brand_description
         FROM community_interactions ci
         INNER JOIN brands b ON b.id = ci.brand_id
         WHERE ci.status = 'new'
           AND (ci.ai_suggested_reply IS NULL OR ci.ai_suggested_reply = '')
           AND ci.is_spam = 0
         ORDER BY ci.created_at ASC
         LIMIT {$limit}"
    );

    if (empty($interactions)) {
        echo "[{$now}] No pending interactions without AI replies found.\n";
        exit(0);
    }

    echo "[{$now}] Found " . count($interactions) . " interaction(s) to process.\n";

    $generated = 0;
    $failed    = 0;

    foreach ($interactions as $interaction) {
        $id        = $interaction['id'];
        $platform  = $interaction['platform'];
        $content   = $interaction['message_text'] ?? '';

        if (empty($content)) {
            echo "[{$now}]   Skipping ID {$id}: empty message text.\n";
            continue;
        }

        echo "[{$now}]   Generating reply for interaction {$id} ({$platform})...\n";

        try {
            $brand = [
                'id'          => $interaction['brand_id'],
                'name'        => $interaction['brand_name'],
                'description' => $interaction['brand_description'],
                'settings'    => $interaction['brand_settings'],
            ];

            $aiReply = PlatformManager::generateAIReply($interaction, $brand);

            if (!empty($aiReply)) {
                $db->update(
                    'community_interactions',
                    ['ai_suggested_reply' => $aiReply],
                    'id = ?',
                    [$id]
                );
                $generated++;
                echo "[{$now}]   ✓ Reply generated for ID {$id}\n";
            } else {
                $failed++;
                echo "[{$now}]   ✗ Empty reply returned for ID {$id}\n";
            }

            // Small delay to avoid rate limiting AI APIs
            usleep(500_000); // 0.5 seconds

        } catch (\Throwable $e) {
            $failed++;
            echo "[{$now}]   ERROR for ID {$id}: " . $e->getMessage() . "\n";
            error_log("[cron/generate_ai_replies] Interaction {$id} error: " . $e->getMessage());
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[{$now}] Done. Generated: {$generated}, Failed: {$failed}. Elapsed: {$elapsed}s\n";

    // Log to file
    $logLine = "[{$now}] generate_ai_replies: generated={$generated}, failed={$failed}, elapsed={$elapsed}s\n";
    file_put_contents(LOG_PATH . '/cron_ai_replies.log', $logLine, FILE_APPEND | LOCK_EX);

} catch (\Throwable $e) {
    $msg = "[{$now}] FATAL generate_ai_replies error: " . $e->getMessage();
    echo $msg . "\n";
    error_log($msg);
    exit(1);
}

exit(0);
