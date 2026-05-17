<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/AI.php';

abstract class BaseAgent
{
    protected \PDO $db;
    protected AI   $ai;
    protected int  $brandId;

    private string $cacheDir;
    private string $logDir;

    public function __construct(int $brandId)
    {
        $this->db       = Database::getInstance();
        $this->ai       = new AI();
        $this->brandId  = $brandId;
        $this->cacheDir = __DIR__ . '/../cache/';
        $this->logDir   = __DIR__ . '/../logs/';

        foreach ([$this->cacheDir, $this->logDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Execute a named task with arbitrary parameters.
     */
    abstract public function execute(string $task, array $params): array;

    // =========================================================================
    // AI Calls
    // =========================================================================

    protected function callClaude(string $prompt, string $system = '', int $maxTokens = 1000): string
    {
        try {
            $result = $this->ai->callClaude($prompt, $system, $maxTokens);
            return is_string($result) ? $result : (string) ($result['content'] ?? $result['text'] ?? json_encode($result));
        } catch (\Throwable $e) {
            $this->log('Claude API error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    protected function callOpenAI(string $prompt, string $system = '', int $maxTokens = 1000): string
    {
        try {
            $result = $this->ai->callOpenAI($prompt, $system, $maxTokens);
            return is_string($result) ? $result : (string) ($result['content'] ?? $result['text'] ?? json_encode($result));
        } catch (\Throwable $e) {
            $this->log('OpenAI API error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    // =========================================================================
    // Task persistence
    // =========================================================================

    protected function saveTask(
        string $taskName,
        array  $input,
        array  $output,
        string $status,
        int    $tokensUsed = 0
    ): void {
        try {
            $this->db->prepare(
                'INSERT INTO agent_tasks
                 (brand_id, agent_type, task_name, status, input_params, output_result, tokens_used, started_at, completed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
            )->execute([
                $this->brandId,
                static::class,
                $taskName,
                $status,
                json_encode($input),
                json_encode($output),
                $tokensUsed,
            ]);
        } catch (\Throwable $e) {
            $this->log('saveTask failed: ' . $e->getMessage(), 'error');
        }
    }

    // =========================================================================
    // File-based memory/cache
    // =========================================================================

    protected function getMemory(string $key): mixed
    {
        $file = $this->cacheDir . $this->safeCacheKey($key) . '.cache';
        if (!file_exists($file)) return null;

        $raw = file_get_contents($file);
        if ($raw === false) return null;

        $data = @unserialize($raw);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) return null;
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    protected function setMemory(string $key, mixed $value, int $ttl = 3600): void
    {
        $file = $this->cacheDir . $this->safeCacheKey($key) . '.cache';
        $data = serialize([
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ]);
        file_put_contents($file, $data, LOCK_EX);
    }

    protected function forgetMemory(string $key): void
    {
        $file = $this->cacheDir . $this->safeCacheKey($key) . '.cache';
        if (file_exists($file)) @unlink($file);
    }

    // =========================================================================
    // Logging
    // =========================================================================

    protected function log(string $message, string $level = 'info'): void
    {
        $agentClass = static::class;
        $timestamp  = date('Y-m-d H:i:s');
        $line       = "[{$timestamp}] [{$level}] [{$agentClass}] {$message}" . PHP_EOL;

        $logFile = $this->logDir . 'agents-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function getBrandContext(): array
    {
        $stmt = $this->db->prepare('SELECT field_name, field_value FROM brand_strategy WHERE brand_id = ?');
        $stmt->execute([$this->brandId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $ctx  = [];
        foreach ($rows as $r) {
            $ctx[$r['field_name']] = $r['field_value'];
        }
        return $ctx;
    }

    protected function getBrandName(): string
    {
        $stmt = $this->db->prepare('SELECT name FROM brands WHERE id = ? LIMIT 1');
        $stmt->execute([$this->brandId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row['name'] : 'Brand';
    }

    /**
     * Parse a JSON string from Claude output, stripping markdown code fences.
     */
    protected function parseJsonFromAI(string $raw): array
    {
        // Strip markdown code blocks
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', $raw);
        $clean = trim($clean ?? $raw);

        $data = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract first JSON object/array from string
            if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $clean, $m)) {
                $data = json_decode($m[1], true);
            }
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Extract a plain list (one item per line) from AI text output.
     */
    protected function parseListFromAI(string $raw): array
    {
        $lines = preg_split('/[\r\n]+/', trim($raw));
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            // Strip numbered/bulleted prefixes
            $line = preg_replace('/^[\d]+[.)]\s*/', '', $line);
            $line = preg_replace('/^[-*•]\s*/', '', $line);
            $line = trim($line ?? '');
            if (!empty($line)) {
                $items[] = $line;
            }
        }
        return $items;
    }

    private function safeCacheKey(string $key): string
    {
        return 'brand_' . $this->brandId . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }
}
