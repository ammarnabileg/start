<?php
/**
 * SociAI OS - Community Reply Agent
 * AI-powered reply suggestions for community interactions.
 */

declare(strict_types=1);

namespace SociAI\Agents;

use SociAI\Core\{AI, Database, Security};
use SociAI\Models\Brand;

class CommunityReplyAgent
{
    private Database $db;
    private Brand    $brandModel;

    public function __construct()
    {
        $this->db         = Database::getInstance();
        $this->brandModel = new Brand();
    }

    // --------------------------------------------------------
    // Process a batch of new interactions
    // --------------------------------------------------------
    public function processBatch(string $brandId, array $interactionIds = []): array
    {
        $taskId = Security::generateUUID();
        $brand  = $this->brandModel->find($brandId);
        $strategy = $this->brandModel->getActiveStrategy($brandId);

        $this->db->insert('agent_tasks', [
            'id'         => $taskId,
            'brand_id'   => $brandId,
            'agent_type' => 'reply_agent',
            'task_name'  => 'Process Community Replies',
            'input_data' => json_encode(['interaction_ids' => $interactionIds]),
            'status'     => 'running',
            'progress'   => 5,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Get interactions to process
            if (!empty($interactionIds)) {
                $placeholders  = implode(',', array_fill(0, count($interactionIds), '?'));
                $interactions  = $this->db->fetchAll(
                    "SELECT * FROM community_interactions WHERE id IN ({$placeholders}) AND brand_id = ?",
                    array_merge($interactionIds, [$brandId])
                );
            } else {
                $interactions = $this->db->fetchAll(
                    "SELECT * FROM community_interactions
                     WHERE brand_id = ? AND status = 'new' AND ai_suggested_reply IS NULL
                     ORDER BY created_at DESC LIMIT 20",
                    [$brandId]
                );
            }

            $processed = 0;
            $total     = count($interactions);

            foreach ($interactions as $i => $interaction) {
                $progress = 10 + (int)(($i / max(1, $total)) * 80);
                $this->updateProgress($taskId, $progress);

                $result = $this->processInteraction($interaction, $brand, $strategy);

                // Update DB with AI suggestions
                $this->db->update('community_interactions', [
                    'sentiment'          => $result['sentiment'],
                    'sentiment_score'    => $result['sentiment_score'],
                    'is_spam'            => $result['is_spam'] ? 1 : 0,
                    'is_lead'            => $result['is_lead'] ? 1 : 0,
                    'ai_suggested_reply' => $result['suggested_reply'],
                    'status'             => $result['is_spam'] ? 'ignored' : 'in_review',
                ], 'id = ?', [$interaction['id']]);

                $processed++;
            }

            $this->updateProgress($taskId, 100);

            $output = ['processed' => $processed, 'total' => $total];
            $this->db->update('agent_tasks', [
                'output_data' => json_encode($output),
                'status'      => 'completed',
                'progress'    => 100,
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

    // --------------------------------------------------------
    // Process single interaction
    // --------------------------------------------------------
    private function processInteraction(array $interaction, ?array $brand, ?array $strategy): array
    {
        $brandName = $brand['name'] ?? 'the brand';
        $brandTone = $strategy['brand_tone'] ?? 'professional and friendly';
        $message   = $interaction['message_text'] ?? '';
        $platform  = $interaction['platform']    ?? 'social media';
        $type      = $interaction['interaction_type'] ?? 'comment';

        $prompt = <<<PROMPT
Analyse this {$platform} {$type} for brand "{$brandName}" and provide a response plan.

Message: "{$message}"

Brand tone: {$brandTone}

Return JSON:
{
  "sentiment": "positive|neutral|negative|mixed",
  "sentiment_score": 0.75,
  "is_spam": false,
  "is_lead": false,
  "intent": "complaint|question|praise|purchase_intent|trolling|general",
  "priority": "high|medium|low",
  "suggested_reply": "The actual reply text (in same language as original message)",
  "reply_tone": "empathetic|informative|enthusiastic|professional|defensive",
  "escalate": false,
  "escalation_reason": null,
  "follow_up_action": "none|send_dm|offer_discount|collect_info"
}
PROMPT;

        try {
            $aiResult = AI::generate($prompt, "You are a community management expert. Analyse social media interactions and craft perfect brand replies. Always respond in JSON.", 600, 0.4);
            $parsed   = $this->parseReply($aiResult['text']);
        } catch (\Throwable) {
            // Fallback if AI fails
            $parsed = [
                'sentiment'      => 'neutral',
                'sentiment_score'=> 0.0,
                'is_spam'        => false,
                'is_lead'        => false,
                'suggested_reply'=> "Thank you for reaching out! Our team will get back to you shortly.",
            ];
        }

        return $parsed;
    }

    private function parseReply(string $rawText): array
    {
        $jsonStr = $rawText;
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/i', $rawText, $m)) {
            $jsonStr = $m[1];
        } elseif (preg_match('/\{[\s\S]+\}/s', $rawText, $m)) {
            $jsonStr = $m[0];
        }
        $parsed = json_decode($jsonStr, true);
        if (!is_array($parsed)) {
            return ['sentiment' => 'neutral', 'sentiment_score' => 0.0, 'is_spam' => false, 'is_lead' => false, 'suggested_reply' => $rawText];
        }
        return $parsed;
    }

    private function updateProgress(string $taskId, int $progress): void
    {
        $this->db->update('agent_tasks', ['progress' => $progress], 'id = ?', [$taskId]);
    }
}
