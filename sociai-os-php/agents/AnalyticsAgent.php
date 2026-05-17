<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseAgent.php';

class AnalyticsAgent extends BaseAgent
{
    public function execute(string $task, array $params): array
    {
        return match ($task) {
            'calculateViralScore'  => $this->calculateViralScore($params['metrics'] ?? []),
            'generateReport'       => $this->generateReport(
                (int) ($params['brand_id'] ?? $this->brandId),
                $params['period'] ?? '30d'
            ),
            'analyzeSentiment'     => $this->analyzeSentiment(
                (int) ($params['brand_id'] ?? $this->brandId),
                $params['period'] ?? '30d'
            ),
            'benchmarkCompetitors' => $this->benchmarkCompetitors(
                (array) ($params['handles'] ?? []),
                $params['platform'] ?? 'instagram'
            ),
            'predictPerformance'   => $this->predictPerformance(
                (array) ($params['content'] ?? []),
                $params['platform'] ?? 'instagram'
            ),
            'identifyWeaknesses'   => $this->identifyWeaknesses(
                (int) ($params['brand_id'] ?? $this->brandId)
            ),
            'generateRecommendations' => $this->generateRecommendations($params['data'] ?? []),
            default => throw new \InvalidArgumentException("Unknown task: {$task}"),
        };
    }

    // =========================================================================
    // 9-Dimension Viral Score Calculator
    // =========================================================================

    public function calculateViralScore(array $metrics): array
    {
        // Dimension 1: Reach Score (impressions vs follower count)
        $followers       = max(1, (int) ($metrics['followers'] ?? 0));
        $impressions     = (int) ($metrics['impressions'] ?? 0);
        $reachMultiplier = $followers > 0 ? ($impressions / $followers) : 0;
        $reachScore      = min(10, round($reachMultiplier * 5, 1));

        // Dimension 2: Engagement Score (engagement rate)
        $engagementRate  = (float) ($metrics['engagement_rate'] ?? 0);
        $engagementScore = min(10, round($engagementRate * 100, 1));

        // Dimension 3: Shareability Score (shares/saves ratio)
        $likes           = max(1, (int) ($metrics['likes'] ?? 0));
        $shares          = (int) ($metrics['shares'] ?? 0);
        $saves           = (int) ($metrics['saves'] ?? 0);
        $shareRatio      = ($shares + $saves) / $likes;
        $shareScore      = min(10, round($shareRatio * 20, 1));

        // Dimension 4: Sentiment Score (positive comments ratio)
        $totalComments   = max(1, (int) ($metrics['comments'] ?? 0));
        $positiveComments = (int) ($metrics['positive_comments'] ?? ($totalComments * 0.7));
        $sentimentScore  = round(($positiveComments / $totalComments) * 10, 1);

        // Dimension 5: Timing Score (posted at optimal time)
        $postedHour      = (int) ($metrics['posted_hour'] ?? date('G'));
        $platform        = $metrics['platform'] ?? 'instagram';
        $timingScore     = $this->calculateTimingScore($postedHour, $platform);

        // Dimension 6: Hashtag Score (hashtag performance)
        $hashtagCount    = (int) ($metrics['hashtag_count'] ?? 0);
        $hashtagScore    = $this->calculateHashtagScore($hashtagCount, $platform);

        // Dimension 7: Visual Score (media type bonus)
        $mediaType       = $metrics['media_type'] ?? 'image';
        $visualScore     = $this->calculateVisualScore($mediaType);

        // Dimension 8: Hook Score (first-line performance)
        $bounceRate      = (float) ($metrics['swipe_away_rate'] ?? 0.3);
        $hookScore       = min(10, round((1 - $bounceRate) * 12, 1));

        // Dimension 9: CTA Score (link clicks or profile visits)
        $ctaClicks       = (int) ($metrics['link_clicks'] ?? 0);
        $ctaScore        = min(10, round(($ctaClicks / max(1, $impressions)) * 1000, 1));

        $weights = [
            'reach'       => 0.15,
            'engagement'  => 0.20,
            'shareability'=> 0.15,
            'sentiment'   => 0.10,
            'timing'      => 0.10,
            'hashtag'     => 0.05,
            'visual'      => 0.10,
            'hook'        => 0.10,
            'cta'         => 0.05,
        ];

        $overallScore = (
            $reachScore      * $weights['reach']       +
            $engagementScore * $weights['engagement']  +
            $shareScore      * $weights['shareability']+
            $sentimentScore  * $weights['sentiment']   +
            $timingScore     * $weights['timing']      +
            $hashtagScore    * $weights['hashtag']     +
            $visualScore     * $weights['visual']      +
            $hookScore       * $weights['hook']        +
            $ctaScore        * $weights['cta']
        );

        $result = [
            'overall_score'      => round($overallScore, 2),
            'reach_score'        => $reachScore,
            'engagement_score'   => $engagementScore,
            'shareability_score' => $shareScore,
            'sentiment_score'    => $sentimentScore,
            'timing_score'       => $timingScore,
            'hashtag_score'      => $hashtagScore,
            'visual_score'       => $visualScore,
            'hook_score'         => $hookScore,
            'cta_score'          => $ctaScore,
            'grade'              => $this->scoreToGrade($overallScore),
            'top_dimension'      => $this->getTopDimension([
                'reach' => $reachScore, 'engagement' => $engagementScore,
                'shareability' => $shareScore, 'hook' => $hookScore, 'visual' => $visualScore,
            ]),
        ];

        // Persist to viral_scores table
        try {
            $this->db->prepare(
                'INSERT INTO viral_scores
                 (brand_id, content_post_id, overall_score, reach_score, engagement_score, shareability_score,
                  sentiment_score, timing_score, hashtag_score, visual_score, hook_score, cta_score, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $this->brandId,
                $metrics['post_id'] ?? null,
                $result['overall_score'],
                $reachScore, $engagementScore, $shareScore, $sentimentScore,
                $timingScore, $hashtagScore, $visualScore, $hookScore, $ctaScore,
            ]);
        } catch (\Throwable $e) {
            $this->log('Failed to persist viral score: ' . $e->getMessage(), 'warning');
        }

        return $result;
    }

    // =========================================================================
    // Generate full analytics report
    // =========================================================================

    public function generateReport(int $brandId, string $period): array
    {
        $cacheKey = "analytics_report_{$brandId}_{$period}";
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (array) $cached;

        $days = $this->periodToDays($period);

        // Summary metrics
        $summaryStmt = $this->db->prepare(
            'SELECT
               COUNT(cp.id) AS posts_published,
               COALESCE(SUM(pm.impressions),0) AS total_reach,
               COALESCE(SUM(pm.likes),0) AS total_likes,
               COALESCE(SUM(pm.comments),0) AS total_comments,
               COALESCE(SUM(pm.shares),0) AS total_shares,
               COALESCE(SUM(pm.saves),0) AS total_saves,
               COALESCE(SUM(pm.link_clicks),0) AS total_link_clicks,
               COALESCE(AVG(pm.engagement_rate),0) AS avg_engagement_rate
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ? AND cp.status = "published"
               AND cp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $summaryStmt->execute([$brandId, $days]);
        $summary = $summaryStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Platform breakdown
        $platformStmt = $this->db->prepare(
            'SELECT cp.platform,
                    COUNT(cp.id) AS posts,
                    COALESCE(SUM(pm.impressions),0) AS reach,
                    COALESCE(AVG(pm.engagement_rate),0) AS avg_engagement
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ? AND cp.status = "published"
               AND cp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY cp.platform'
        );
        $platformStmt->execute([$brandId, $days]);
        $platforms = $platformStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Top 5 posts
        $topPostsStmt = $this->db->prepare(
            'SELECT cp.id, cp.platform, cp.content_text, pm.impressions, pm.engagement_rate, cp.published_at
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ? AND cp.status = "published"
               AND cp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY pm.engagement_rate DESC
             LIMIT 5'
        );
        $topPostsStmt->execute([$brandId, $days]);
        $topPosts = $topPostsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Follower growth
        $growthStmt = $this->db->prepare(
            'SELECT SUM(follower_count) AS total_followers, MIN(snapshot_date) AS start_date
             FROM follower_snapshots
             WHERE brand_id = ? AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY snapshot_date ASC
             LIMIT 1'
        );
        $growthStmt->execute([$brandId, $days]);
        $startGrowth = $growthStmt->fetch(\PDO::FETCH_ASSOC);

        $currentGrowthStmt = $this->db->prepare(
            'SELECT SUM(follower_count) AS total_followers FROM follower_snapshots
             WHERE brand_id = ? ORDER BY snapshot_date DESC LIMIT 1'
        );
        $currentGrowthStmt->execute([$brandId]);
        $currentGrowth = $currentGrowthStmt->fetch(\PDO::FETCH_ASSOC);

        $startFollowers   = (int) ($startGrowth['total_followers'] ?? 0);
        $currentFollowers = (int) ($currentGrowth['total_followers'] ?? 0);
        $followerGrowth   = $currentFollowers - $startFollowers;
        $growthPct        = $startFollowers > 0 ? round(($followerGrowth / $startFollowers) * 100, 2) : 0;

        // Daily reach trend
        $trendStmt = $this->db->prepare(
            'SELECT DATE(cp.published_at) AS day,
                    COALESCE(SUM(pm.impressions),0) AS daily_reach,
                    COALESCE(AVG(pm.engagement_rate),0) AS daily_engagement
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ? AND cp.status = "published"
               AND cp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(cp.published_at)
             ORDER BY day ASC'
        );
        $trendStmt->execute([$brandId, $days]);
        $trend = $trendStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $report = [
            'period'           => $period,
            'days'             => $days,
            'summary'          => $summary,
            'platforms'        => $platforms,
            'top_posts'        => $topPosts,
            'follower_growth'  => [
                'start'    => $startFollowers,
                'current'  => $currentFollowers,
                'gained'   => $followerGrowth,
                'pct'      => $growthPct,
            ],
            'daily_trend'      => $trend,
            'generated_at'     => date('Y-m-d H:i:s'),
        ];

        $this->saveTask('generateReport', ['brand_id' => $brandId, 'period' => $period], $report, 'completed');
        $this->setMemory($cacheKey, $report, 3600); // 1hr cache

        return $report;
    }

    // =========================================================================
    // Sentiment Analysis
    // =========================================================================

    public function analyzeSentiment(int $brandId, string $period): array
    {
        $days = $this->periodToDays($period);

        $stmt = $this->db->prepare(
            'SELECT comment_text, platform, created_at
             FROM community_comments
             WHERE brand_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY created_at DESC
             LIMIT 200'
        );
        $stmt->execute([$brandId, $days]);
        $comments = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($comments)) {
            return [
                'positive_pct' => 0, 'neutral_pct' => 0, 'negative_pct' => 0,
                'overall' => 'neutral', 'sample_positive' => [], 'sample_negative' => [],
            ];
        }

        // Batch analyze with Claude
        $sampleComments = array_slice($comments, 0, 50);
        $commentTexts   = implode("\n---\n", array_column($sampleComments, 'comment_text'));

        $system = 'You are a social media sentiment analyst. Return precise JSON only.';
        $prompt = <<<PROMPT
Analyze the sentiment of these social media comments and return a JSON summary:
COMMENTS:
{$commentTexts}

Return JSON:
{
  "positive_count": number,
  "neutral_count": number,
  "negative_count": number,
  "dominant_emotions": ["emotion1", "emotion2", "emotion3"],
  "common_themes": ["theme1", "theme2", "theme3"],
  "key_complaints": ["complaint1", "complaint2"],
  "key_praises": ["praise1", "praise2"],
  "sample_positive": ["comment1", "comment2"],
  "sample_negative": ["comment1", "comment2"]
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 1000);
        $parsed = $this->parseJsonFromAI($raw);

        $total    = ($parsed['positive_count'] ?? 0) + ($parsed['neutral_count'] ?? 0) + ($parsed['negative_count'] ?? 0);
        $total    = max(1, $total);

        $result = [
            'positive_pct'     => round(($parsed['positive_count'] ?? 0) / $total * 100, 1),
            'neutral_pct'      => round(($parsed['neutral_count'] ?? 0) / $total * 100, 1),
            'negative_pct'     => round(($parsed['negative_count'] ?? 0) / $total * 100, 1),
            'total_analyzed'   => count($comments),
            'overall'          => $this->determineSentimentOverall($parsed),
            'dominant_emotions'=> $parsed['dominant_emotions'] ?? [],
            'common_themes'    => $parsed['common_themes'] ?? [],
            'key_complaints'   => $parsed['key_complaints'] ?? [],
            'key_praises'      => $parsed['key_praises'] ?? [],
            'sample_positive'  => $parsed['sample_positive'] ?? [],
            'sample_negative'  => $parsed['sample_negative'] ?? [],
        ];

        return $result;
    }

    // =========================================================================
    // Competitor Benchmarking
    // =========================================================================

    public function benchmarkCompetitors(array $handles, string $platform): array
    {
        $brandName = $this->getBrandName();
        $handleList = implode(', ', $handles);

        $system = 'You are a competitive intelligence analyst for social media. Provide realistic benchmark data.';

        $prompt = <<<PROMPT
Provide a competitive benchmark analysis for these {$platform} accounts: {$handleList}
Compare against brand: {$brandName}

For each competitor, estimate and return:
{
  "competitors": [
    {
      "handle": "@handle",
      "estimated_followers": number,
      "avg_engagement_rate": "X.X%",
      "posting_frequency": "X per week",
      "top_content_types": ["type1", "type2"],
      "content_strategy": "brief description",
      "strengths": ["strength1", "strength2"],
      "weaknesses": ["weakness1", "weakness2"]
    }
  ],
  "industry_benchmarks": {
    "avg_engagement_rate": "X.X%",
    "avg_posting_frequency": "X per week",
    "top_performing_content": "type"
  },
  "opportunities": ["opportunity1", "opportunity2", "opportunity3"],
  "recommendations": ["rec1", "rec2", "rec3"]
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 1500);
        $result = $this->parseJsonFromAI($raw);

        $this->saveTask('benchmarkCompetitors', ['handles' => $handles, 'platform' => $platform], $result, 'completed');

        return $result;
    }

    // =========================================================================
    // Performance Prediction
    // =========================================================================

    public function predictPerformance(array $content, string $platform): array
    {
        $contentText = is_array($content) ? ($content['content'] ?? json_encode($content)) : (string) $content;
        $brandName   = $this->getBrandName();

        $system = 'You are a social media performance prediction model. Provide data-backed performance estimates.';

        $prompt = <<<PROMPT
Predict the performance of this {$platform} content:
CONTENT: {$contentText}
BRAND: {$brandName}

Return performance prediction:
{
  "predicted_reach_multiplier": X.X,
  "predicted_engagement_rate": "X.X%",
  "viral_potential": "low|medium|high|very_high",
  "best_posting_time": "HH:MM",
  "best_posting_day": "Monday-Sunday",
  "estimated_impressions_range": {"min": number, "max": number},
  "content_strengths": ["strength1", "strength2"],
  "content_weaknesses": ["weakness1", "weakness2"],
  "optimization_suggestions": ["suggestion1", "suggestion2", "suggestion3"],
  "predicted_viral_score": X.X
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 800);
        $result = $this->parseJsonFromAI($raw);

        return empty($result) ? [
            'predicted_reach_multiplier' => 1.2,
            'predicted_engagement_rate' => '2.5%',
            'viral_potential' => 'medium',
            'error' => 'Prediction model returned no data',
        ] : $result;
    }

    // =========================================================================
    // Identify Weaknesses
    // =========================================================================

    public function identifyWeaknesses(int $brandId): array
    {
        $report = $this->generateReport($brandId, '30d');

        $system = 'You are a social media audit expert who identifies growth blockers and weaknesses.';

        $promptData = json_encode([
            'avg_engagement_rate' => $report['summary']['avg_engagement_rate'] ?? 0,
            'posts_published'     => $report['summary']['posts_published'] ?? 0,
            'platforms'           => $report['platforms'] ?? [],
            'follower_growth_pct' => $report['follower_growth']['pct'] ?? 0,
        ]);

        $prompt = <<<PROMPT
Analyze this 30-day social media performance data and identify weaknesses:
DATA: {$promptData}

Return JSON:
{
  "critical_weaknesses": [
    {"issue": "description", "impact": "high|medium|low", "fix": "actionable fix"}
  ],
  "growth_blockers": ["blocker1", "blocker2"],
  "missed_opportunities": ["opportunity1", "opportunity2"],
  "quick_wins": ["win1", "win2", "win3"],
  "priority_actions": ["action1", "action2", "action3"]
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 1000);
        return $this->parseJsonFromAI($raw);
    }

    // =========================================================================
    // Generate AI Recommendations
    // =========================================================================

    public function generateRecommendations(array $analyticsData): array
    {
        $dataJson = json_encode(array_slice($analyticsData, 0, 10)); // Limit size

        $system = 'You are a social media growth strategist. Generate specific, actionable recommendations.';

        $prompt = <<<PROMPT
Based on this analytics data, generate growth recommendations:
DATA: {$dataJson}

Return JSON:
{
  "immediate_actions": [
    {"action": "description", "expected_impact": "description", "effort": "low|medium|high"}
  ],
  "weekly_tactics": ["tactic1", "tactic2", "tactic3", "tactic4"],
  "content_adjustments": ["adjustment1", "adjustment2"],
  "platform_specific": {
    "instagram": ["rec1", "rec2"],
    "linkedin": ["rec1"],
    "twitter": ["rec1"]
  },
  "growth_forecast": "Expected growth if recommendations implemented",
  "top_priority": "The single most impactful action to take now"
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 1200);
        return $this->parseJsonFromAI($raw);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function calculateTimingScore(int $hour, string $platform): float
    {
        $optimalHours = match ($platform) {
            'instagram' => [6, 7, 8, 11, 12, 17, 18, 19, 20],
            'linkedin'  => [7, 8, 9, 10, 12, 17, 18],
            'twitter'   => [8, 9, 12, 13, 17, 18, 19, 20, 21],
            'tiktok'    => [6, 7, 11, 12, 19, 20, 21, 22],
            'facebook'  => [9, 10, 11, 13, 14, 15, 18, 19],
            default     => [8, 9, 10, 12, 17, 18, 19],
        };

        if (in_array($hour, $optimalHours, true)) {
            return 10.0;
        }

        // Partial score for near-optimal
        $minDist = PHP_INT_MAX;
        foreach ($optimalHours as $optimal) {
            $dist    = min(abs($hour - $optimal), 24 - abs($hour - $optimal));
            $minDist = min($minDist, $dist);
        }

        return max(0, round(10 - ($minDist * 2), 1));
    }

    private function calculateHashtagScore(int $count, string $platform): float
    {
        $optimal = match ($platform) {
            'instagram' => ['min' => 5,  'max' => 20],
            'twitter'   => ['min' => 1,  'max' => 3],
            'linkedin'  => ['min' => 3,  'max' => 7],
            'tiktok'    => ['min' => 5,  'max' => 10],
            'facebook'  => ['min' => 2,  'max' => 5],
            default     => ['min' => 5,  'max' => 15],
        };

        if ($count >= $optimal['min'] && $count <= $optimal['max']) {
            return 10.0;
        }
        if ($count < $optimal['min']) {
            return round(($count / $optimal['min']) * 10, 1);
        }
        return round(max(0, 10 - (($count - $optimal['max']) * 0.5)), 1);
    }

    private function calculateVisualScore(string $mediaType): float
    {
        return match ($mediaType) {
            'reel', 'video', 'short' => 10.0,
            'carousel'               => 9.0,
            'image'                  => 7.0,
            'story'                  => 8.0,
            'text'                   => 4.0,
            'link'                   => 3.0,
            default                  => 5.0,
        };
    }

    private function scoreToGrade(float $score): string
    {
        if ($score >= 9.0) return 'A+';
        if ($score >= 8.0) return 'A';
        if ($score >= 7.0) return 'B+';
        if ($score >= 6.0) return 'B';
        if ($score >= 5.0) return 'C+';
        if ($score >= 4.0) return 'C';
        if ($score >= 3.0) return 'D';
        return 'F';
    }

    private function getTopDimension(array $scores): string
    {
        arsort($scores);
        return (string) array_key_first($scores);
    }

    private function determineSentimentOverall(array $parsed): string
    {
        $pos = $parsed['positive_count'] ?? 0;
        $neg = $parsed['negative_count'] ?? 0;
        $neu = $parsed['neutral_count'] ?? 0;

        if ($pos > $neg && $pos > $neu) return 'positive';
        if ($neg > $pos && $neg > $neu) return 'negative';
        return 'neutral';
    }

    private function periodToDays(string $period): int
    {
        return match ($period) {
            '7d'   => 7, '14d' => 14, '30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365, default => 30,
        };
    }
}
