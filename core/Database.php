<?php
class Database {
    private static ?self $instance = null;
    private PDO $pdo;
    private ?int $tenantId = null;

    private function __construct() {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_NAME'] ?? 'hireai';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function getInstance(): self {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function setTenantId(int $id): void { $this->tenantId = $id; }
    public function getTenantId(): ?int { return $this->tenantId; }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array {
        $r = $this->query($sql, $params)->fetch();
        return $r ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll() ?: [];
    }

    public function fetchColumn(string $sql, array $params = []): mixed {
        $r = $this->query($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public function insert(string $table, array $data): int {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $vals = implode(',', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `{$table}` ({$cols}) VALUES ({$vals})", array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int {
        $set   = implode(',', array_map(fn($c) => "`{$c}`=?", array_keys($data)));
        $cond  = implode(' AND ', array_map(fn($c) => "`{$c}`=?", array_keys($where)));
        $stmt  = $this->query("UPDATE `{$table}` SET {$set} WHERE {$cond}",
                              [...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    public function paginate(string $sql, array $params, int $page, int $perPage = 20): array {
        $total = (int)$this->fetchColumn("SELECT COUNT(*) FROM ({$sql}) _c", $params);
        $offset = ($page - 1) * $perPage;
        $data = $this->fetchAll("{$sql} LIMIT {$perPage} OFFSET {$offset}", $params);
        return ['data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void { $this->pdo->rollBack(); }
}
