<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseAgent.php';

class PublishingAgent extends BaseAgent
{
    public function execute(string $task, array $params): array
    {
        return match ($task) {
            'schedulePost'         => $this->schedulePost(
                (int) ($params['contentId'] ?? 0),
                (array) ($params['platformAccountIds'] ?? []),
                $params['scheduledAt'] ?? date('Y-m-d H:i:s', strtotime('+1 hour'))
            ),
            'optimizePostingTime'  => $this->optimizePostingTime(
                $params['platform'] ?? 'instagram',
                $params['timezone'] ?? 'UTC'
            ),
            'crossPost'            => $this->crossPost(
                (array) ($params['content'] ?? []),
                $params['sourcePlatform']  ?? 'instagram',
                (array) ($params['targetPlatforms'] ?? [])
            ),
            'emergencyStop'        => (function() use ($params) {
                $this->emergencyStop((int) ($params['brandId'] ?? $this->brandId));
                return ['stopped' => true];
            })(),
            'recycleTopPerforming' => $this->recycleTopPerforming(
                (int) ($params['brandId'] ?? $this->brandId),
                (float) ($params['threshold'] ?? 5.0)
            ),
            'abTestSetup'          => $this->abTestSetup(
                (int) ($params['contentAId'] ?? 0),
                (int) ($params['contentBId'] ?? 0)
            ),
            default => throw new \InvalidArgumentException("Unknown task: {$task}"),
        };
    }

    // =========================================================================
    // Schedule a post
    // =========================================================================

    public function schedulePost(int $contentId, array $platformAccountIds, string $scheduledAt): array
    {
        if ($contentId <= 0) {
            throw new \InvalidArgumentException('Invalid content ID');
        }

        if (strtotime($scheduledAt) < time()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future');
        }

        // Verify content exists and belongs to brand
        $stmt = $this->db->prepare(
            'SELECT id, brand_id, platform, content_text, status, media_urls FROM content_posts WHERE id = ? AND brand_id = ? LIMIT 1'
        );
        $stmt->execute([$contentId, $this->brandId]);
        $content = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$content) {
            throw new \RuntimeException("Content #{$contentId} not found");
        }

        if (!in_array($content['status'], ['approved', 'draft'], true)) {
            throw new \RuntimeException("Content must be approved or draft to schedule. Current: {$content['status']}");
        }

        $this->db->beginTransaction();

        try {
            // Update content post status
            $this->db->prepare(
                'UPDATE content_posts
                 SET status = "scheduled", scheduled_at = ?, platform_account_ids = ?, updated_at = NOW()
                 WHERE id = ?'
            )->execute([$scheduledAt, json_encode($platformAccountIds), $contentId]);

            // Create scheduled job records for each platform account
            $jobIds = [];
            foreach ($platformAccountIds as $accountId) {
                $stmt = $this->db->prepare(
                    'INSERT INTO scheduled_jobs
                     (brand_id, content_post_id, platform_account_id, scheduled_at, status, created_at)
                     VALUES (?, ?, ?, ?, "pending", NOW())'
                );
                $stmt->execute([$this->brandId, $contentId, $accountId, $scheduledAt]);
                $jobIds[] = (int) $this->db->lastInsertId();
            }

            $this->db->commit();

            $this->saveTask('schedulePost', [
                'content_id'           => $contentId,
                'platform_account_ids' => $platformAccountIds,
                'scheduled_at'         => $scheduledAt,
            ], ['job_ids' => $jobIds], 'completed');

            $this->log("Scheduled post #{$contentId} for {$scheduledAt}");

            return [
                'success'      => true,
                'content_id'   => $contentId,
                'scheduled_at' => $scheduledAt,
                'job_ids'      => $jobIds,
                'accounts'     => count($platformAccountIds),
            ];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // Optimize posting time
    // =========================================================================

    public function optimizePostingTime(string $platform, string $timezone): array
    {
        $cacheKey = "optimal_time_{$platform}_{$timezone}";
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (array) $cached;

        // Analyze historical post performance
        $stmt = $this->db->prepare(
            'SELECT HOUR(cp.published_at) AS hour, DAYOFWEEK(cp.published_at) AS day_of_week,
                    AVG(pm.engagement_rate) AS avg_engagement,
                    COUNT(cp.id) AS post_count
             FROM content_posts cp
             INNER JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ? AND cp.platform = ? AND cp.status = "published"
               AND cp.published_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY HOUR(cp.published_at), DAYOFWEEK(cp.published_at)
             ORDER BY avg_engagement DESC
             LIMIT 10'
        );
        $stmt->execute([$this->brandId, $platform]);
        $historicalData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Platform-based defaults + AI refinement
        $platformOptimal = $this->getPlatformOptimalTimes($platform);

        $system = 'You are a social media timing optimization expert. Analyze data and provide precise recommendations.';

        $historicalJson = json_encode($historicalData ?: []);
        $prompt = <<<PROMPT
Optimize posting times for {$platform} in {$timezone} timezone.

Historical performance data:
{$historicalJson}

Platform best practices for {$platform} in 2025.

Return JSON:
{
  "best_times": [
    {
      "day": "Monday-Sunday",
      "hour": 0-23,
      "score": 1-100,
      "reasoning": "brief explanation"
    }
  ],
  "best_days": ["Monday", "Wednesday"],
  "worst_days": ["Saturday"],
  "optimal_posting_frequency": "X posts per week",
  "timezone_notes": "note about timezone",
  "data_confidence": "high|medium|low (based on historical data available)"
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 800);
        $result = $this->parseJsonFromAI($raw);

        if (empty($result)) {
            $result = $platformOptimal;
        }

        $this->setMemory($cacheKey, $result, 86400); // 24hr cache

        return $result;
    }

    // =========================================================================
    // Cross-post to multiple platforms
    // =========================================================================

    public function crossPost(array $content, string $sourcePlatform, array $targetPlatforms): array
    {
        if (empty($targetPlatforms)) {
            throw new \InvalidArgumentException('No target platforms specified');
        }

        $results = [];
        $originalText = $content['content_text'] ?? '';

        foreach ($targetPlatforms as $targetPlatform) {
            if ($targetPlatform === $sourcePlatform) continue;

            // Adapt content for each platform
            $adaptedText = $this->adaptContentForPlatform($originalText, $sourcePlatform, $targetPlatform);

            // Create a new post for this platform
            try {
                $stmt = $this->db->prepare(
                    'INSERT INTO content_posts
                     (brand_id, platform, content_text, content_type, hashtags, media_urls, status,
                      parent_post_id, cross_posted_from, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, "draft", ?, ?, ?, NOW())'
                );
                $stmt->execute([
                    $this->brandId,
                    $targetPlatform,
                    $adaptedText,
                    $content['content_type'] ?? 'post',
                    $content['hashtags'] ?? '[]',
                    $content['media_urls'] ?? '[]',
                    $content['id'] ?? null,
                    $sourcePlatform,
                    $content['created_by'] ?? null,
                ]);
                $newPostId = (int) $this->db->lastInsertId();

                $results[] = [
                    'platform'    => $targetPlatform,
                    'post_id'     => $newPostId,
                    'status'      => 'draft',
                    'adapted_text'=> mb_substr($adaptedText, 0, 100) . '...',
                    'success'     => true,
                ];

            } catch (\Throwable $e) {
                $results[] = [
                    'platform' => $targetPlatform,
                    'success'  => false,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        $this->saveTask('crossPost', [
            'source'  => $sourcePlatform,
            'targets' => $targetPlatforms,
        ], $results, 'completed');

        return $results;
    }

    // =========================================================================
    // Emergency stop — cancel all scheduled posts
    // =========================================================================

    public function emergencyStop(int $brandId): void
    {
        $this->log("EMERGENCY STOP triggered for brand #{$brandId}", 'warning');

        $this->db->prepare(
            'UPDATE content_posts
             SET status = "emergency_paused", updated_at = NOW()
             WHERE brand_id = ? AND status = "scheduled" AND scheduled_at > NOW()'
        )->execute([$brandId]);

        $this->db->prepare(
            'UPDATE scheduled_jobs
             SET status = "cancelled", updated_at = NOW()
             WHERE brand_id = ? AND status = "pending"'
        )->execute([$brandId]);

        // Log the emergency stop
        $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, resource_type, resource_id, meta, created_at)
             VALUES (NULL, "emergency_stop", "brand", ?, ?, NOW())'
        )->execute([$brandId, json_encode(['triggered_by' => 'PublishingAgent'])]);

        $this->saveTask('emergencyStop', ['brand_id' => $brandId], ['stopped' => true], 'completed');
    }

    // =========================================================================
    // Recycle top performing content
    // =========================================================================

    public function recycleTopPerforming(int $brandId, float $threshold = 5.0): array
    {
        // Find posts with engagement rate above threshold
        $stmt = $this->db->prepare(
            'SELECT cp.id, cp.platform, cp.content_text, cp.content_type, cp.media_urls, cp.hashtags,
                    pm.engagement_rate, pm.impressions, cp.published_at
             FROM content_posts cp
             INNER JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ? AND cp.status = "published"
               AND pm.engagement_rate >= ?
               AND cp.published_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
             ORDER BY pm.engagement_rate DESC
             LIMIT 10'
        );
        $stmt->execute([$brandId, $threshold]);
        $topPosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($topPosts)) {
            return ['recycled' => [], 'message' => 'No posts meet the recycling threshold'];
        }

        $recycled = [];

        foreach ($topPosts as $post) {
            // Check if already recycled recently
            $checkStmt = $this->db->prepare(
                'SELECT id FROM content_posts
                 WHERE parent_post_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                 LIMIT 1'
            );
            $checkStmt->execute([$post['id']]);
            if ($checkStmt->fetch()) continue; // Already recycled recently

            // Create recycled draft
            $newText = $this->refreshPostText($post['content_text']);

            $insertStmt = $this->db->prepare(
                'INSERT INTO content_posts
                 (brand_id, platform, content_text, content_type, media_urls, hashtags, status, parent_post_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, "draft", ?, NOW())'
            );
            $insertStmt->execute([
                $brandId,
                $post['platform'],
                $newText,
                $post['content_type'],
                $post['media_urls'],
                $post['hashtags'],
                $post['id'],
            ]);
            $newId = (int) $this->db->lastInsertId();

            $recycled[] = [
                'original_post_id' => $post['id'],
                'new_post_id'      => $newId,
                'platform'         => $post['platform'],
                'original_engagement' => $post['engagement_rate'],
            ];
        }

        $this->saveTask('recycleTopPerforming', ['threshold' => $threshold], $recycled, 'completed');

        return ['recycled' => $recycled, 'count' => count($recycled)];
    }

    // =========================================================================
    // A/B Test Setup
    // =========================================================================

    public function abTestSetup(int $contentAId, int $contentBId): array
    {
        $stmtA = $this->db->prepare('SELECT * FROM content_posts WHERE id = ? AND brand_id = ? LIMIT 1');
        $stmtA->execute([$contentAId, $this->brandId]);
        $contentA = $stmtA->fetch(\PDO::FETCH_ASSOC);

        $stmtB = $this->db->prepare('SELECT * FROM content_posts WHERE id = ? AND brand_id = ? LIMIT 1');
        $stmtB->execute([$contentBId, $this->brandId]);
        $contentB = $stmtB->fetch(\PDO::FETCH_ASSOC);

        if (!$contentA || !$contentB) {
            throw new \RuntimeException('One or both content posts not found');
        }

        // Create A/B test record
        $stmt = $this->db->prepare(
            'INSERT INTO ab_tests
             (brand_id, content_a_id, content_b_id, status, created_at)
             VALUES (?, ?, ?, "active", NOW())'
        );
        $stmt->execute([$this->brandId, $contentAId, $contentBId]);
        $testId = (int) $this->db->lastInsertId();

        // Schedule variant A at one optimal time, B 30min later
        $optimalTime   = $this->optimizePostingTime($contentA['platform'], 'UTC');
        $bestDay       = $optimalTime['best_days'][0] ?? 'Monday';
        $bestHour      = $optimalTime['best_times'][0]['hour'] ?? 9;

        $nextBestDay   = date('Y-m-d', strtotime('next ' . $bestDay));
        $scheduleA     = $nextBestDay . ' ' . str_pad($bestHour, 2, '0', STR_PAD_LEFT) . ':00:00';
        $scheduleB     = $nextBestDay . ' ' . str_pad($bestHour, 2, '0', STR_PAD_LEFT) . ':30:00';

        return [
            'test_id'      => $testId,
            'variant_a'    => ['content_id' => $contentAId, 'schedule' => $scheduleA],
            'variant_b'    => ['content_id' => $contentBId, 'schedule' => $scheduleB],
            'winner_criteria' => 'engagement_rate',
            'test_duration'   => '24h',
            'instructions'  => 'Monitor for 24 hours. Winner determined by engagement rate.',
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function adaptContentForPlatform(string $text, string $from, string $to): string
    {
        $system = "You are a cross-platform content adapter. Reformat content from {$from} to {$to}.";

        $limits = [
            'twitter' => 280, 'threads' => 500, 'instagram' => 2200,
            'linkedin' => 3000, 'facebook' => 63206, 'tiktok' => 2200,
        ];
        $limit = $limits[$to] ?? 2200;

        $prompt = <<<PROMPT
Adapt this {$from} content for {$to}:
ORIGINAL: {$text}

Rules for {$to}:
- Max {$limit} characters
- Platform-appropriate tone and format
- Adjust hashtag count for {$to}
- Keep core message intact
- Sound native to {$to}

Return ONLY the adapted content text.
PROMPT;

        return $this->callClaude($prompt, $system, 600);
    }

    private function refreshPostText(string $text): string
    {
        $system = 'You refresh old social media posts to feel new while keeping the core message.';

        $prompt = <<<PROMPT
Refresh this social media post to feel new and current. Keep the core message but:
- Update the hook/opening
- Vary the wording
- Keep roughly the same length
- Keep hashtags similar

ORIGINAL: {$text}

Return ONLY the refreshed post text.
PROMPT;

        try {
            return $this->callClaude($prompt, $system, 500);
        } catch (\Throwable $e) {
            return $text; // Return original on failure
        }
    }

    private function getPlatformOptimalTimes(string $platform): array
    {
        $times = match ($platform) {
            'instagram' => [
                ['day' => 'Tuesday', 'hour' => 11, 'score' => 95],
                ['day' => 'Wednesday', 'hour' => 11, 'score' => 94],
                ['day' => 'Monday', 'hour' => 11, 'score' => 90],
            ],
            'linkedin' => [
                ['day' => 'Tuesday', 'hour' => 8, 'score' => 95],
                ['day' => 'Wednesday', 'hour' => 9, 'score' => 93],
                ['day' => 'Thursday', 'hour' => 10, 'score' => 90],
            ],
            'twitter' => [
                ['day' => 'Wednesday', 'hour' => 9, 'score' => 95],
                ['day' => 'Friday', 'hour' => 9, 'score' => 92],
                ['day' => 'Tuesday', 'hour' => 9, 'score' => 90],
            ],
            'tiktok' => [
                ['day' => 'Tuesday', 'hour' => 19, 'score' => 95],
                ['day' => 'Thursday', 'hour' => 20, 'score' => 93],
                ['day' => 'Friday', 'hour' => 19, 'score' => 90],
            ],
            default => [
                ['day' => 'Tuesday', 'hour' => 10, 'score' => 85],
                ['day' => 'Wednesday', 'hour' => 11, 'score' => 83],
            ],
        };

        return [
            'best_times'               => $times,
            'best_days'                => ['Tuesday', 'Wednesday', 'Thursday'],
            'worst_days'               => ['Sunday', 'Saturday'],
            'optimal_posting_frequency'=> '5-7 posts per week',
            'timezone_notes'           => 'Times in UTC',
            'data_confidence'          => 'medium',
        ];
    }
}
