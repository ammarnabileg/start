<?php
// Auto-detect base URL
if (!defined('BASE_URL')) {
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
 define('BASE_URL', $protocol . '://' . $host);
}
if (!defined('SITE_NAME')) {
 define('SITE_NAME', 'Discover');
}

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
 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
 PDO::ATTR_EMULATE_PREPARES => false,
 ];

 try {
 $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
 } catch (PDOException $e) {
 error_log('Database connection failed: ' . $e->getMessage());
 if (!headers_sent()) {
 header('HTTP/1.1 500 Internal Server Error');
 }
 // Return JSON for API requests, HTML for page requests
 $is_api = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
 if ($is_api) {
 header('Content-Type: application/json');
 die(json_encode(['error' => 'Service temporarily unavailable']));
 }
 die('<html><body style="font-family:sans-serif;text-align:center;padding:50px"><h2>Service temporarily unavailable</h2><p>Please try again later.</p></body></html>');
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

// One-time fix: clean legacy /platform/ prefix from stored notification links
function fix_notification_links(): void {
 static $done = false;
 if ($done) return;
 $done = true;
 try {
   db_execute("UPDATE notifications SET link = REPLACE(link, '/platform/', '/') WHERE link LIKE '%/platform/%'");
 } catch (Exception $e) {}
}
fix_notification_links();
