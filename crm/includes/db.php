<?php
/**
 * PDO connection (singleton).
 */

require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            CRM_DB_HOST, CRM_DB_NAME, CRM_DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, CRM_DB_USER, CRM_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

function tbl(string $name): string {
    return CRM_TBL_PREFIX . $name;
}

function db_exec(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array {
    $stmt = db_exec($sql, $params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array {
    return db_exec($sql, $params)->fetchAll();
}

function db_scalar(string $sql, array $params = []) {
    $stmt = db_exec($sql, $params);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

function db_insert(string $table, array $data): string {
    $cols = array_keys($data);
    $placeholders = array_map(fn($c) => ":$c", $cols);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(',', array_map(fn($c) => "`$c`", $cols)),
        implode(',', $placeholders)
    );
    db_exec($sql, $data);
    return db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int {
    $sets = [];
    $params = [];
    foreach ($data as $k => $v) {
        $sets[] = "`$k` = :set_$k";
        $params["set_$k"] = $v;
    }
    foreach ($whereParams as $k => $v) {
        $params[$k] = $v;
    }
    $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(',', $sets), $where);
    return db_exec($sql, $params)->rowCount();
}

function db_delete(string $table, string $where, array $params = []): int {
    return db_exec("DELETE FROM $table WHERE $where", $params)->rowCount();
}
