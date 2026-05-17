<?php
/**
 * SociAI OS - Trend Hunter Agent
 * Discovers viral trends and generates content opportunities.
 */

declare(strict_types=1);

namespace SociAI\Agents;

use SociAI\Core\{AI, Database, Security};

class TrendHunterAgent
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function hunt(array $params): array
    {
        $taskId  = Security::generateUUID();
        $brandId = $params['brand_id'] ?? null;

        $this->db->insert('agent_tasks', [
            'id'         => $taskId,
            'brand_id'   => $brandId,
            'agent_type' => 'trend_hunter',
            'task_name'  => 'Hunt Trends: ' . implode(',', (array)($params['platforms'] ?? ['all'])),
            'input_data' => json_encode($params),
            'status'     => 'running',
            'progress'   => 5,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $industry  = $params['industry'] ?? 'general';
            $region    = $params['region']   ?? 'global';
            $platforms = $params['platforms'] ?? ['instagram', 'tiktok', 'twitter', 'linkedin'];
            $language  = $params['language'] ?? 'english';

            $this->updateProgress($taskId, 20);

            $prompt = $this->buildTrendPrompt($industry, $region, $platforms, $language);
            $aiResult = AI::generate($prompt, $this->getSystemPrompt(), 3000, 0.9);

            $this->updateProgress($taskId, 70);

            $trends = $this->parseTrends($aiResult['text']);

            $this->updateProgress($taskId, 85);

            // Persist trends
            $savedIds = [];
            foreach ($trends as $trend) {
                $id = $this->db->insert('trend_opportunities', [
                    'platform'              => $trend['platform'] ?? 'general',
                    'region'                => $region,
                    'trend_name'            => $trend['trend_name'] ?? 'Unknown Trend',
                    'description'           => $trend['description'] ?? '',
                    'virality_score'        => (float)($trend['virality_score'] ?? 50),
                    'volume'                => $trend['volume'] ?? null,
                    'hashtags'              => json_encode($trend['hashtags'] ?? []),
                    'related_topics'        => json_encode($trend['related_topics'] ?? []),
                    'ai_content_suggestion' => $trend['content_suggestion'] ?? '',
                    'expires_at'            => date('Y-m-d H:i:s', strtotime('+48 hours')),
                ]);
                $savedIds[] = $id;
            }

            $this->updateProgress($taskId, 100);

            $output = [
                'trends'      => $trends,
                'trend_count' => count($trends),
                'saved_ids'   => $savedIds,
                'tokens_used' => $aiResult['input_tokens'] + $aiResult['output_tokens'],
                'cost_usd'    => $aiResult['cost_usd'],
            ];

            $this->db->update('agent_tasks', [
                'output_data' => json_encode($output),
                'status'      => 'completed',
                'progress'    => 100,
                'tokens_used' => $output['tokens_used'],
                'cost_usd'    => $output['cost_usd'],
                'completed_at'=> date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);

            return ['task_id' => $taskId, 'success' => true, 'output' => $output];

        } catch (\Throwable $e) {
            $this->db->update('agent_tasks', [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);
            throw $e;
        }
    }

    private function getSystemPrompt(): string
    {
        return "You are a viral trends analyst with deep expertise in social media, pop culture, and digital marketing. You identify trending topics that brands can authentically leverage for maximum engagement. Always respond with valid JSON.";
    }

    private function buildTrendPrompt(string $industry, string $region, array $platforms, string $language): string
    {
        $platformStr = implode(', ', $platforms);
        $today       = date('Y-m-d');
        return <<<PROMPT
Today is {$today}. Identify 10 trending topics/formats that a {$industry} brand can leverage on: {$platformStr}.

Region focus: {$region}
Language context: {$language}

For each trend, provide:
{
  "trends": [
    {
      "trend_name": "Trend name",
      "platform": "primary platform",
      "description": "What this trend is and why it's viral",
      "virality_score": 85.5,
      "volume": 1500000,
      "hashtags": ["#tag1","#tag2"],
      "related_topics": ["topic1","topic2"],
      "brand_relevance": "How a {$industry} brand can use this",
      "content_suggestion": "Specific content idea to ride this trend",
      "window": "Hours/days this trend will stay relevant",
      "risk_level": "low|medium|high",
      "example_angle": "Specific angle or hook for this brand"
    }
  ]
}
PROMPT;
    }

    private function parseTrends(string $rawText): array
    {
        $jsonStr = $rawText;
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/i', $rawText, $m)) {
            $jsonStr = $m[1];
        } elseif (preg_match('/\{[\s\S]+\}/s', $rawText, $m)) {
            $jsonStr = $m[0];
        }
        $parsed = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['trends'])) {
            return [];
        }
        return $parsed['trends'];
    }

    private function updateProgress(string $taskId, int $progress): void
    {
        $this->db->update('agent_tasks', ['progress' => $progress], 'id = ?', [$taskId]);
    }
}
