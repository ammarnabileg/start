<?php
declare(strict_types=1);

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private ?int $tenantId = null;

    private function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USERNAME'] ?? '';
        $pass = $_ENV['DB_PASSWORD'] ?? '';
        $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    }

    public static function getInstance(): static
    {
        if (!static::$instance) static::$instance = new static();
        return static::$instance;
    }

    public static function reset(): void { static::$instance = null; }

    public function setTenantId(?int $id): void { $this->tenantId = $id; }
    public function getTenantId(): ?int          { return $this->tenantId; }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `{$table}` ({$cols}) VALUES ({$ph})", array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function insertOrIgnore(string $table, array $data): int
    {
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT IGNORE INTO `{$table}` ({$cols}) VALUES ({$ph})", array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $set  = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $cond = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        return $this->query(
            "UPDATE `{$table}` SET {$set} WHERE {$cond}",
            [...array_values($data), ...array_values($where)]
        )->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        return $this->query("DELETE FROM `{$table}` WHERE {$cond}", array_values($where))->rowCount();
    }

    public function paginate(string $sql, array $params, int $page, int $perPage = 25): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $total  = (int)$this->fetchColumn("SELECT COUNT(*) FROM ({$sql}) AS _c", $params);
        $data   = $this->fetchAll("{$sql} LIMIT {$perPage} OFFSET {$offset}", $params);
        return [
            'data'     => $data,
            'total'    => $total,
            'pages'    => (int)ceil($total / max(1, $perPage)),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollback(): void         { $this->pdo->rollBack(); }
    public function lastInsertId(): string   { return $this->pdo->lastInsertId(); }
    public function getPdo(): PDO            { return $this->pdo; }
}
