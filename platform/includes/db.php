<?php
// Database configuration - use environment variables or fallback to constants
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'admin_discover');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'discover_user');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'LBAk1ef_h9icl6%s');
define('DB_CHARSET', 'utf8mb4');

$pdo = null;

function get_pdo(): PDO {
    global $pdo;
    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // In production, log the error and show a friendly message
        error_log('Database connection failed: ' . $e->getMessage());
        die(json_encode(['error' => 'Database connection failed']) );
    }

    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch(string $sql, array $params = []): ?array {
    $stmt = db_query($sql, $params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_fetch_all(string $sql, array $params = []): array {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

function db_insert(string $sql, array $params = []): int {
    db_query($sql, $params);
    return (int) get_pdo()->lastInsertId();
}

function db_execute(string $sql, array $params = []): int {
    $stmt = db_query($sql, $params);
    return $stmt->rowCount();
}

function get_platform_setting(string $key, string $default = ''): string {
    $row = db_fetch('SELECT setting_value FROM platform_settings WHERE setting_key = ?', [$key]);
    return $row ? $row['setting_value'] : $default;
}
