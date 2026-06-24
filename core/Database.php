<?php
namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO singleton wrapper. Tenant-aware: when a tenant id is set, helper
 * methods that build SQL automatically scope to that tenant.
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private ?int $tenantId = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function instance(?array $config = null): Database
    {
        if (self::$instance === null) {
            $config = $config ?? require dirname(__DIR__) . '/config/database.php';
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function connect(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $c = $this->config;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], $c['port'], $c['database'], $c['charset'] ?? 'utf8mb4'
        );
        try {
            $this->pdo = new PDO($dsn, $c['username'], $c['password'], $c['options'] ?? []);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
        return $this->pdo;
    }

    public function pdo(): PDO
    {
        return $this->connect();
    }

    public function setTenantId(?int $id): void
    {
        $this->tenantId = $id;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Insert a row. If the table has a tenant_id column and a tenant is set,
     * tenant_id is injected automatically when not already present.
     */
    public function insert(string $table, array $data): int
    {
        if ($this->tenantId !== null && $this->hasTenantColumn($table) && !array_key_exists('tenant_id', $data)) {
            $data['tenant_id'] = $this->tenantId;
        }
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->ident($table),
            implode(', ', array_map([$this, 'ident'], $cols)),
            implode(', ', $placeholders)
        );
        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $this->normalize($v);
        }
        $this->query($sql, $params);
        return (int) $this->connect()->lastInsertId();
    }

    /**
     * Update rows matching $where (assoc array, ANDed).
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = $this->ident($k) . ' = :set_' . $k;
            $params[':set_' . $k] = $this->normalize($v);
        }
        [$whereSql, $whereParams] = $this->buildWhere($table, $where);
        $params = array_merge($params, $whereParams);
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->ident($table), implode(', ', $set), $whereSql);
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        [$whereSql, $params] = $this->buildWhere($table, $where);
        $sql = sprintf('DELETE FROM %s WHERE %s', $this->ident($table), $whereSql);
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction(): void { $this->connect()->beginTransaction(); }
    public function commit(): void { $this->connect()->commit(); }
    public function rollback(): void { if ($this->connect()->inTransaction()) { $this->connect()->rollBack(); } }

    private function buildWhere(string $table, array $where): array
    {
        $clauses = [];
        $params = [];
        foreach ($where as $k => $v) {
            $clauses[] = $this->ident($k) . ' = :where_' . $k;
            $params[':where_' . $k] = $this->normalize($v);
        }
        if ($this->tenantId !== null && $this->hasTenantColumn($table) && !array_key_exists('tenant_id', $where)) {
            $clauses[] = 'tenant_id = :where_tenant_id';
            $params[':where_tenant_id'] = $this->tenantId;
        }
        if (empty($clauses)) {
            $clauses[] = '1=1';
        }
        return [implode(' AND ', $clauses), $params];
    }

    private function hasTenantColumn(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            // information_schema supports bound parameters (SHOW COLUMNS ... LIKE ? does not).
            $stmt = $this->connect()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
                  LIMIT 1'
            );
            $stmt->execute([':t' => $table, ':c' => 'tenant_id']);
            $cache[$table] = $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }

    private function normalize($value)
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return $value;
    }

    private function ident(string $name): string
    {
        // Allow only safe identifier characters.
        return '`' . str_replace('`', '', $name) . '`';
    }
}
