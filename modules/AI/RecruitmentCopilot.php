<?php
declare(strict_types=1);

namespace Modules\AI;

/**
 * RecruitmentCopilot - Conversational assistant over a tenant's recruitment
 * data. The caller assembles a compact, pre-aggregated context (job stats,
 * candidate summaries, recent activity) and the copilot answers natural
 * questions, returns any structured data it referenced, and proposes useful
 * follow-up actions.
 *
 * The copilot does NOT query the database itself; it reasons over the context
 * it is given. This keeps it fast, cheap and safe (no SQL generation), and the
 * answers stay grounded in the provided records.
 */
class RecruitmentCopilot
{
    private OpenAIService $ai;

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Answer a question about the recruitment database.
     *
     * @param string $question The HR user's question.
     * @param array  $context  Pre-aggregated data, e.g.:
     *                          [
     *                            'jobs'       => [...],   // job stats
     *                            'candidates' => [...],   // candidate summaries
     *                            'activity'   => [...],   // recent events
     *                            'pipeline'   => [...],   // stage counts
     *                            'history'    => [...],   // prior chat turns (role/content)
     *                          ]
     * @param int    $tenantId Tenant id (usage logging + scoping context).
     *
     * @return array{answer:string, data:array, suggestions:string[]}
     */
    public function chat(string $question, array $context, int $tenantId): array
    {
        $question = trim($question);
        if ($question === '') {
            return [
                'answer'      => 'What would you like to know about your candidates or pipeline?',
                'data'        => [],
                'suggestions' => $this->defaultSuggestions(),
            ];
        }

        $system = $this->systemPrompt();
        $contextBlock = $this->formatContext($context);

        $messages = [['role' => 'system', 'content' => $system]];

        // Replay a short history so follow-up questions have continuity.
        foreach ($this->recentHistory($context['history'] ?? []) as $turn) {
            $messages[] = $turn;
        }

        $messages[] = [
            'role'    => 'user',
            'content' => "=== RECRUITMENT DATA (the only data you may use) ===\n{$contextBlock}\n\n=== QUESTION ===\n{$question}",
        ];

        try {
            $result = $this->ai->chatJson(
                $messages,
                $this->schema(),
                ['temperature' => 0.3, 'max_tokens' => 1800]
            );
        } catch (\Throwable $e) {
            error_log('[RecruitmentCopilot] OpenAI error: ' . $e->getMessage());
            return [
                'answer'      => 'I encountered an error processing your request. Please try again.',
                'data'        => [],
                'suggestions' => $this->defaultSuggestions(),
            ];
        }

        $this->ai->logUsage(
            $tenantId,
            (int) ($context['user_id'] ?? 0),
            'recruitment_copilot',
            ['model' => $result['model']] + $result['tokens'] + ['cost' => $result['cost']],
            'copilot',
            0
        );

        return $this->normalize($result['data']);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are "Copilot", an expert recruitment analyst embedded in an AI hiring
platform. You help HR users understand their pipeline and candidates by
answering questions over the structured data they provide.

Rules:
- Answer ONLY from the provided recruitment data. If the data is insufficient to
  answer, say so plainly and suggest what data or filter would help. Never
  fabricate candidates, scores, names or numbers.
- Be concise and decision-oriented. Lead with the direct answer, then a short
  justification. Use the candidates' real names and scores from the data.
- When the question asks to find, compare, rank or shortlist candidates, put the
  referenced records into the "data" array (each item should include at least
  id, name and the relevant metric/score) so the UI can render them. Order them
  most-relevant first.
- For comparisons, briefly highlight the differentiators (scores, strengths,
  risks) rather than dumping every field.
- "suggestions" should contain 2-4 short, relevant follow-up actions or queries
  the user might take next.
- Be neutral and bias-free. Do not consider protected characteristics.
- Write in English. Output ONLY the JSON object defined by the schema.
PROMPT;
    }

    private function schema(): array
    {
        return [
            'name'   => 'copilot_answer',
            'strict' => false,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => true,
                'properties' => [
                    'answer' => ['type' => 'string'],
                    'data'   => [
                        'type'  => 'array',
                        'items' => ['type' => 'object'],
                    ],
                    'suggestions' => [
                        'type'  => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['answer'],
            ],
        ];
    }

    private function normalize(array $data): array
    {
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $item) {
            if (is_array($item)) {
                $rows[] = $item;
            }
        }

        $suggestions = [];
        foreach ((array) ($data['suggestions'] ?? []) as $s) {
            if (is_scalar($s)) {
                $str = trim((string) $s);
                if ($str !== '') {
                    $suggestions[] = $str;
                }
            }
        }
        if (empty($suggestions)) {
            $suggestions = $this->defaultSuggestions();
        }

        return [
            'answer'      => (string) ($data['answer'] ?? 'I could not find an answer in the available data.'),
            'data'        => $rows,
            'suggestions' => array_slice($suggestions, 0, 4),
        ];
    }

    // ==================================================================
    // Context formatting
    // ==================================================================

    /**
     * Render the provided context into a compact, token-efficient text block.
     */
    private function formatContext(array $context): string
    {
        $sections = [];

        if (!empty($context['pipeline'])) {
            $sections[] = "PIPELINE (stage => count):\n" . $this->kvLines($context['pipeline']);
        }

        if (!empty($context['jobs'])) {
            $sections[] = "JOBS:\n" . $this->recordsBlock($context['jobs'], [
                'id', 'title', 'seniority', 'status', 'applications_count',
            ]);
        }

        if (!empty($context['candidates'])) {
            $sections[] = "CANDIDATES:\n" . $this->recordsBlock($context['candidates'], [
                'id', 'full_name', 'name', 'job_title', 'stage', 'years_experience',
                'final_score', 'overall_score', 'avg_match_score', 'recommendation',
                'english_proficiency', 'skills', 'expected_salary', 'location',
            ]);
        }

        if (!empty($context['activity'])) {
            $sections[] = "RECENT ACTIVITY:\n" . $this->recordsBlock($context['activity'], [
                'created_at', 'action', 'title', 'body',
            ]);
        }

        // Allow arbitrary extra sections the caller may attach.
        foreach ($context as $key => $value) {
            if (in_array($key, ['pipeline', 'jobs', 'candidates', 'activity', 'history', 'user_id'], true)) {
                continue;
            }
            if (is_array($value) && !empty($value)) {
                $sections[] = strtoupper((string) $key) . ":\n" . $this->recordsBlock($value, []);
            }
        }

        if (empty($sections)) {
            return '(No recruitment data was provided.)';
        }

        $text = implode("\n\n", $sections);
        // Hard cap to protect the context window.
        return mb_strlen($text) > 24000 ? mb_substr($text, 0, 24000) . "\n…(truncated)" : $text;
    }

    /**
     * Render a list of records as compact lines, optionally projecting only a
     * whitelist of fields (when present on the record).
     */
    private function recordsBlock(array $records, array $fields): string
    {
        $lines = [];
        $count = 0;
        foreach ($records as $rec) {
            if (!is_array($rec)) {
                if (is_scalar($rec)) {
                    $lines[] = '- ' . (string) $rec;
                }
                continue;
            }

            $pairs = [];
            $use = empty($fields) ? array_keys($rec) : $fields;
            foreach ($use as $f) {
                if (!array_key_exists($f, $rec) || $rec[$f] === null || $rec[$f] === '') {
                    continue;
                }
                $val = $rec[$f];
                if (is_array($val)) {
                    $val = implode('/', array_map(static fn($v) => is_scalar($v) ? (string) $v : '', $val));
                }
                $pairs[] = $f . '=' . $this->trimVal((string) $val);
            }
            if (!empty($pairs)) {
                $lines[] = '- ' . implode(', ', $pairs);
            }

            if (++$count >= 200) {
                $lines[] = '- …(' . (count($records) - $count) . ' more)';
                break;
            }
        }
        return implode("\n", $lines);
    }

    private function kvLines(array $map): string
    {
        $lines = [];
        foreach ($map as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            $lines[] = '- ' . $k . ': ' . $v;
        }
        return implode("\n", $lines);
    }

    /**
     * Keep only the last few chat turns, mapped to OpenAI roles.
     */
    private function recentHistory($history): array
    {
        if (!is_array($history) || empty($history)) {
            return [];
        }
        $turns = [];
        foreach (array_slice($history, -6) as $h) {
            $role = (string) ($h['role'] ?? '');
            $content = trim((string) ($h['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $turns[] = [
                'role'    => in_array($role, ['user', 'assistant'], true) ? $role : ($role === 'ai' ? 'assistant' : 'user'),
                'content' => mb_substr($content, 0, 1500),
            ];
        }
        return $turns;
    }

    private function defaultSuggestions(): array
    {
        return [
            'Who are the top candidates this week?',
            'Which candidates are at risk of dropping out?',
            'Compare the strongest applicants for an open role.',
        ];
    }

    private function trimVal(string $v): string
    {
        $v = trim(preg_replace('/\s+/', ' ', $v));
        return mb_strlen($v) > 160 ? mb_substr($v, 0, 160) . '…' : $v;
    }
}
