<?php
namespace App\Core;

/**
 * Simple database-backed job queue. Uses a `queue_jobs` table created on
 * demand so it works even if the schema did not pre-create it.
 */
class Queue
{
    private Database $db;
    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
        $this->ensureTable();
    }

    public function register(string $job, callable $handler): void
    {
        $this->handlers[$job] = $handler;
    }

    public function push(string $job, array $data = [], int $delay = 0): int
    {
        return $this->db->insert('queue_jobs', [
            'job'          => $job,
            'payload'      => json_encode($data),
            'available_at' => date('Y-m-d H:i:s', time() + $delay),
            'status'       => 'pending',
            'attempts'     => 0,
        ]);
    }

    /**
     * Process up to $limit due jobs. Returns the count processed.
     */
    public function process(int $limit = 25): int
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM queue_jobs
              WHERE status = 'pending' AND available_at <= NOW()
              ORDER BY id ASC LIMIT $limit"
        );
        $count = 0;
        foreach ($rows as $row) {
            $this->db->update('queue_jobs', ['status' => 'processing'], ['id' => $row['id']]);
            try {
                $payload = json_decode($row['payload'] ?? '[]', true) ?: [];
                if (isset($this->handlers[$row['job']])) {
                    ($this->handlers[$row['job']])($payload);
                }
                $this->db->update('queue_jobs', [
                    'status'       => 'done',
                    'processed_at' => date('Y-m-d H:i:s'),
                ], ['id' => $row['id']]);
                $count++;
            } catch (\Throwable $e) {
                $this->fail((int) $row['id'], $e->getMessage());
            }
        }
        return $count;
    }

    public function fail(int $jobId, string $error): void
    {
        $row = $this->db->fetch('SELECT attempts FROM queue_jobs WHERE id = :id', [':id' => $jobId]);
        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        $this->db->update('queue_jobs', [
            'status'   => $attempts >= 3 ? 'failed' : 'pending',
            'attempts' => $attempts,
            'error'    => $error,
        ], ['id' => $jobId]);
    }

    private function ensureTable(): void
    {
        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS queue_jobs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    job VARCHAR(150) NOT NULL,
                    payload LONGTEXT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT "pending",
                    attempts INT NOT NULL DEFAULT 0,
                    error TEXT NULL,
                    available_at TIMESTAMP NULL,
                    processed_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable $e) {
            // If the DB is not yet configured, ignore; push() will surface it.
        }
    }
}
