<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Version: " . phpversion() . "</h2>";

// Test DB connection
echo "<h3>Testing DB Connection...</h3>";
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=admin_discover;charset=utf8mb4',
        'discover_user',
        'LBAk1ef_h9icl6%s',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p style='color:green'>DB Connected OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>DB Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test includes
echo "<h3>Testing includes...</h3>";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "<p style='color:green'>db.php OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>db.php Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

try {
    require_once __DIR__ . '/includes/auth.php';
    echo "<p style='color:green'>auth.php OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>auth.php Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

try {
    require_once __DIR__ . '/includes/functions.php';
    echo "<p style='color:green'>functions.php OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>functions.php Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Done</h3>";
