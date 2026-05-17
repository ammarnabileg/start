<?php
/**
 * SociAI OS - Database Layer
 * PDO singleton with full CRUD helpers, transactions, and robust error handling.
 */

declare(strict_types=1);

namespace SociAI\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class DatabaseException extends RuntimeException {}

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo                  = null;
    private int  $queryCount           = 0;
    private bool $inTransaction        = false;

    private function __construct() {}
    private function __clone() {}

    // --------------------------------------------------------
    // Singleton accessor
    // --------------------------------------------------------
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    // --------------------------------------------------------
    // Connection
    // --------------------------------------------------------
    private function connect(): PDO
    {
        if ($this->pdo !== null) {
            // Cheap keepalive ping
            try {
                $this->pdo->query('SELECT 1');
                return $this->pdo;
            } catch (PDOException) {
                $this->pdo = null;
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            PDO::ATTR_TIMEOUT            => 10,
        ];

        $attempts = 0;
        do {
            try {
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $this->pdo->exec("SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
                $this->pdo->exec("SET time_zone = '+00:00'");
                return $this->pdo;
            } catch (PDOException $e) {
                $attempts++;
                if ($attempts >= 3) {
                    throw new DatabaseException(
                        "Database connection failed after {$attempts} attempts: " . $e->getMessage(),
                        (int)$e->getCode(),
                        $e
                    );
                }
                usleep(200_000 * $attempts); // 200ms, 400ms back-off
            }
        } while ($attempts < 3);

        throw new DatabaseException("Unable to connect to database.");
    }

    public function getPDO(): PDO
    {
        return $this->connect();
    }

    // --------------------------------------------------------
    // Core query executor
    // --------------------------------------------------------
    public function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = $this->connect();
        try {
            $stmt = $pdo->prepare($sql);
            // Bind typed params for robustness
            foreach ($params as $key => $value) {
                $paramKey  = is_int($key) ? $key + 1 : $key;
                $paramType = match (true) {
                    is_int($value)   => PDO::PARAM_INT,
                    is_bool($value)  => PDO::PARAM_BOOL,
                    is_null($value)  => PDO::PARAM_NULL,
                    default          => PDO::PARAM_STR,
                };
                $stmt->bindValue($paramKey, $value, $paramType);
            }
            $stmt->execute();
            $this->queryCount++;
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Query failed: " . $e->getMessage() . " | SQL: " . $sql,
                (int)$e->getCode(),
                $e
            );
        }
    }

    // --------------------------------------------------------
    // Fetch helpers
    // --------------------------------------------------------
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    // --------------------------------------------------------
    // INSERT
    // --------------------------------------------------------
    public function insert(string $table, array $data): string
    {
        if (empty($data)) {
            throw new DatabaseException("Insert data cannot be empty.");
        }
        $table   = $this->quoteIdentifier($table);
        $columns = array_map([$this, 'quoteIdentifier'], array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return $this->connect()->lastInsertId();
    }

    // --------------------------------------------------------
    // UPDATE
    // --------------------------------------------------------
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (empty($data)) {
            return 0;
        }
        $table  = $this->quoteIdentifier($table);
        $setParts = array_map(
            fn($col) => $this->quoteIdentifier($col) . ' = ?',
            array_keys($data)
        );
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    // --------------------------------------------------------
    // DELETE
    // --------------------------------------------------------
    public function delete(string $table, string $where, array $whereParams = []): int
    {
        $table = $this->quoteIdentifier($table);
        return $this->query("DELETE FROM {$table} WHERE {$where}", $whereParams)->rowCount();
    }

    // --------------------------------------------------------
    // Upsert (INSERT ... ON DUPLICATE KEY UPDATE)
    // --------------------------------------------------------
    public function upsert(string $table, array $insertData, array $updateData): string
    {
        $table   = $this->quoteIdentifier($table);
        $columns = array_map([$this, 'quoteIdentifier'], array_keys($insertData));
        $placeholders = implode(', ', array_fill(0, count($insertData), '?'));
        $updateParts  = array_map(
            fn($col) => $this->quoteIdentifier($col) . ' = VALUES(' . $this->quoteIdentifier($col) . ')',
            array_keys($updateData)
        );
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})"
             . " ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        $this->query($sql, array_values($insertData));
        return $this->connect()->lastInsertId();
    }

    // --------------------------------------------------------
    // Transactions
    // --------------------------------------------------------
    public function beginTransaction(): void
    {
        if ($this->inTransaction) {
            throw new DatabaseException("Transaction already started.");
        }
        $this->connect()->beginTransaction();
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        if (!$this->inTransaction) {
            throw new DatabaseException("No active transaction to commit.");
        }
        $this->connect()->commit();
        $this->inTransaction = false;
    }

    public function rollback(): void
    {
        if ($this->inTransaction) {
            try {
                $this->connect()->rollBack();
            } catch (PDOException) {
                // Ignore rollback errors
            }
            $this->inTransaction = false;
        }
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->connect()->prepare($sql);
    }

    public function lastInsertId(): string
    {
        return $this->connect()->lastInsertId();
    }

    public function rollBack(): void
    {
        $this->rollback();
    }

    /**
     * Execute callback inside a transaction; auto-rollback on exception.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // --------------------------------------------------------
    // Pagination helper
    // --------------------------------------------------------
    public function paginate(
        string $sql,
        array $params,
        int $page,
        int $perPage = 20
    ): array {
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS _count_subquery";
        $total    = (int) $this->fetchColumn($countSql, $params);

        // Data page
        $dataSql = $sql . " LIMIT ? OFFSET ?";
        $data    = $this->fetchAll($dataSql, array_merge($params, [$perPage, $offset]));

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
            'from'         => $offset + 1,
            'to'           => min($offset + $perPage, $total),
        ];
    }

    // --------------------------------------------------------
    // Utilities
    // --------------------------------------------------------
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    // --------------------------------------------------------
    // Disconnect
    // --------------------------------------------------------
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function __destruct()
    {
        if ($this->inTransaction) {
            $this->rollback();
        }
    }
}
