<?php
// Quick server test — DELETE after use
echo "<h2>PHP is working!</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</p>";

// Test config load
try {
    require_once __DIR__ . '/config/config.php';
    echo "<p style='color:green'>✅ config.php loaded OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ config.php error: " . $e->getMessage() . "</p>";
}

// Test DB
if (defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset='.DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "<p style='color:green'>✅ Database connected: " . DB_NAME . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Database error: " . $e->getMessage() . "</p>";
    }
}

// Test autoloader + namespaces
try {
    define('BASE_PATH', __DIR__);
    require_once __DIR__ . '/core/Response.php';
    if (class_exists('SociAI\Core\Response')) {
        echo "<p style='color:green'>✅ Namespace autoload OK (SociAI\Core\Response found)</p>";
    } else {
        echo "<p style='color:red'>❌ SociAI\Core\Response class not found after require</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Autoload error: " . $e->getMessage() . "</p>";
}

echo "<hr><p style='color:orange'><strong>⚠️ DELETE test.php after done!</strong></p>";
