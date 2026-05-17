<?php
/**
 * SociAI OS - Strategy Extractor Agent
 * Extracts structured marketing strategy from uploaded documents.
 */

declare(strict_types=1);

namespace SociAI\Agents;

use SociAI\Core\{AI, Database, Security};
use SociAI\Models\Brand;

class StrategyExtractorAgent
{
    private Database $db;
    private Brand    $brandModel;

    public function __construct()
    {
        $this->db         = Database::getInstance();
        $this->brandModel = new Brand();
    }

    public function extract(array $params): array
    {
        $taskId  = Security::generateUUID();
        $brandId = $params['brand_id'];

        $this->db->insert('agent_tasks', [
            'id'         => $taskId,
            'brand_id'   => $brandId,
            'user_id'    => $params['user_id'] ?? null,
            'agent_type' => 'strategy_extractor',
            'task_name'  => 'Extract Marketing Strategy',
            'input_data' => json_encode(['document_path' => $params['document_path'] ?? '']),
            'status'     => 'running',
            'progress'   => 0,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->updateProgress($taskId, 10);

            // Read document content
            $documentText = $this->readDocument($params['document_path'] ?? '', $params['document_text'] ?? '');

            $this->updateProgress($taskId, 25);

            // Extract strategy with AI
            $systemPrompt = $this->getSystemPrompt();
            $userPrompt   = $this->buildExtractionPrompt($documentText);

            $aiResult = AI::generate($userPrompt, $systemPrompt, 4096, 0.3);

            $this->updateProgress($taskId, 70);

            // Parse structured data
            $extracted = $this->parseExtractionResult($aiResult['text']);

            $this->updateProgress($taskId, 85);

            // Generate executive summary
            $summaryResult = AI::generate(
                "Write a 3-paragraph executive summary of this marketing strategy in plain language:\n\n" . json_encode($extracted),
                "You are a senior marketing strategist. Be concise and actionable.",
                512
            );

            $this->updateProgress($taskId, 95);

            // Save strategy to database
            $strategyId = $this->brandModel->createStrategy([
                'brand_id'         => $brandId,
                'name'             => $params['name'] ?? 'AI Extracted Strategy',
                'raw_document_url' => $params['document_url'] ?? null,
                'extracted_data'   => $extracted,
                'brand_tone'       => $extracted['brand_tone'] ?? null,
                'content_pillars'  => $extracted['content_pillars'] ?? [],
                'target_audience'  => $extracted['target_audience'] ?? [],
                'business_goals'   => $extracted['business_goals'] ?? [],
                'ai_summary'       => $summaryResult['text'],
                'created_by'       => $params['user_id'] ?? null,
            ]);

            $this->updateProgress($taskId, 100);

            $totalTokens = $aiResult['input_tokens'] + $aiResult['output_tokens']
                         + $summaryResult['input_tokens'] + $summaryResult['output_tokens'];
            $totalCost   = $aiResult['cost_usd'] + $summaryResult['cost_usd'];

            $output = [
                'strategy_id'  => $strategyId,
                'extracted'    => $extracted,
                'summary'      => $summaryResult['text'],
                'tokens_used'  => $totalTokens,
                'cost_usd'     => $totalCost,
            ];

            $this->db->update('agent_tasks', [
                'output_data' => json_encode($output),
                'status'      => 'completed',
                'progress'    => 100,
                'tokens_used' => $totalTokens,
                'cost_usd'    => $totalCost,
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

    private function readDocument(string $filePath, string $directText = ''): string
    {
        if ($directText !== '') {
            return substr($directText, 0, 50000);
        }
        if (!$filePath || !file_exists($filePath)) {
            throw new \RuntimeException("Document not found: {$filePath}");
        }
        $ext     = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $content = match ($ext) {
            'txt', 'md' => file_get_contents($filePath),
            'pdf'       => $this->extractPdfText($filePath),
            default     => file_get_contents($filePath),
        };
        return substr($content, 0, 50000); // Limit to ~12k tokens
    }

    private function extractPdfText(string $path): string
    {
        // Attempt using pdftotext CLI (poppler-utils)
        if (shell_exec("which pdftotext 2>/dev/null")) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'sociai_pdf');
            exec("pdftotext " . escapeshellarg($path) . " " . escapeshellarg($tmpFile), $out, $code);
            if ($code === 0 && file_exists($tmpFile)) {
                $text = file_get_contents($tmpFile);
                unlink($tmpFile);
                return $text;
            }
        }
        // Fallback: raw binary read (limited)
        return file_get_contents($path);
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
You are a marketing strategy analyst specialising in extracting structured data from brand documents.
Your task is to parse marketing strategy documents and extract actionable, structured insights.
Always return valid JSON. Be thorough but concise. Extract ALL relevant strategic elements.
PROMPT;
    }

    private function buildExtractionPrompt(string $documentText): string
    {
        return <<<PROMPT
Analyse this marketing strategy document and extract structured data.

DOCUMENT:
---
{$documentText}
---

Return a JSON object with this exact structure:
{
  "brand_tone": "Description of brand voice and tone",
  "brand_values": ["value1", "value2", ...],
  "content_pillars": ["pillar1", "pillar2", ...],
  "target_audience": {
    "primary": {"age_range": "", "gender": "", "interests": [], "pain_points": [], "platforms": []},
    "secondary": {"description": ""}
  },
  "business_goals": ["goal1", "goal2", ...],
  "kpis": ["kpi1", "kpi2", ...],
  "competitors": ["competitor1", ...],
  "unique_selling_points": ["usp1", ...],
  "content_themes": ["theme1", ...],
  "posting_frequency": {"recommended": "", "by_platform": {}},
  "campaign_ideas": [{"name": "", "goal": "", "platforms": []}],
  "keywords": ["keyword1", ...],
  "do_list": ["do1", ...],
  "dont_list": ["dont1", ...],
  "budget_allocation": {}
}

If any section is not mentioned in the document, use an empty array/object or null. Do not invent data.
PROMPT;
    }

    private function parseExtractionResult(string $rawText): array
    {
        $jsonStr = $rawText;
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/i', $rawText, $m)) {
            $jsonStr = $m[1];
        } elseif (preg_match('/\{[\s\S]+\}/s', $rawText, $m)) {
            $jsonStr = $m[0];
        }
        $parsed = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['raw_extraction' => $rawText, 'parse_error' => json_last_error_msg()];
        }
        return $parsed;
    }

    private function updateProgress(string $taskId, int $progress): void
    {
        $this->db->update('agent_tasks', ['progress' => $progress], 'id = ?', [$taskId]);
    }
}
