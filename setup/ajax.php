<?php
declare(strict_types=1);
header('Content-Type: application/json');

$rootDir = dirname(__DIR__);

// Block access if already installed
if (file_exists($rootDir . '/.installed') && ($_POST['action'] ?? '') !== 'test_db') {
    echo json_encode(['success' => false, 'message' => 'Platform already installed.']);
    exit;
}

// Load install helpers
require_once __DIR__ . '/install.php';

$action = trim($_POST['action'] ?? '');

// ─── Sanitise input ───────────────────────────────────────────────────────────
function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

// ─── Router ───────────────────────────────────────────────────────────────────
try {
    match ($action) {
        'test_db' => handleTestDb(),
        'install' => handleInstall($rootDir),
        default   => throw new RuntimeException('Unknown action.'),
    };
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ═════════════════════════════════════════════════════════════════════════════
// ACTION: test_db
// ═════════════════════════════════════════════════════════════════════════════
function handleTestDb(): void
{
    $host = post('db_host', '127.0.0.1');
    $port = post('db_port', '3306');
    $user = post('db_user');
    $pass = post('db_pass');

    if (!$host || !$user) {
        echo json_encode(['success' => false, 'message' => 'Host and username are required.']);
        return;
    }

    try {
        // Connect without specifying DB to test credentials
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo json_encode([
            'success' => true,
            'message' => "Connected successfully! MySQL version: {$version}",
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Connection failed: ' . $e->getMessage(),
        ]);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// ACTION: install
// ═════════════════════════════════════════════════════════════════════════════
function handleInstall(string $rootDir): void
{
    $log = [];

    // Collect inputs
    $dbHost       = post('db_host', '127.0.0.1');
    $dbPort       = post('db_port', '3306');
    $dbName       = post('db_name', 'ai_recruitment');
    $dbUser       = post('db_user');
    $dbPass       = post('db_pass');
    $adminFirst   = post('admin_first');
    $adminLast    = post('admin_last');
    $adminEmail   = post('admin_email');
    $adminPass    = post('admin_pass');
    $companyName  = post('company_name');
    $companySlug  = post('company_slug');
    $companyDomain = post('company_domain');
    $appUrl       = rtrim(post('app_url'), '/');

    // ── Validate ──────────────────────────────────────────────────────────────
    $errors = [];
    if (!$dbHost)     $errors[] = 'Database host is required.';
    if (!$dbUser)     $errors[] = 'Database username is required.';
    if (!$dbName)     $errors[] = 'Database name is required.';
    if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Valid admin email is required.';
    if (strlen($adminPass) < 8)
        $errors[] = 'Admin password must be at least 8 characters.';
    if (!$companyName) $errors[] = 'Company name is required.';
    if (!$companySlug || !preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $companySlug))
        $errors[] = 'Company slug must contain only lowercase letters, numbers and hyphens.';
    if (!$appUrl || !filter_var($appUrl, FILTER_VALIDATE_URL))
        $errors[] = 'Valid application URL is required.';

    if ($errors) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        return;
    }

    // ── Connect (create DB if needed) ─────────────────────────────────────────
    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $pdoRoot = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Create database if not exists
        $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $log[] = ['type' => 'ok', 'msg' => "Database `{$dbName}` ready."];

        // Re-connect with DB selected
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        return;
    }

    // ── 1. Write .env ──────────────────────────────────────────────────────────
    try {
        createEnvFile($rootDir, [
            'db_host'        => $dbHost,
            'db_port'        => $dbPort,
            'db_name'        => $dbName,
            'db_user'        => $dbUser,
            'db_pass'        => $dbPass,
            'app_url'        => $appUrl,
            'app_name'       => $companyName,
        ]);
        $log[] = ['type' => 'ok', 'msg' => '.env file created.'];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to write .env: ' . $e->getMessage()]);
        return;
    }

    // ── 2. Run schema ──────────────────────────────────────────────────────────
    try {
        runSchema($pdo, $rootDir);
        $log[] = ['type' => 'ok', 'msg' => 'Database schema applied.'];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Schema failed: ' . $e->getMessage()]);
        return;
    }

    // ── 3. Seed default data ───────────────────────────────────────────────────
    try {
        seedDefaultData($pdo);
        $log[] = ['type' => 'ok', 'msg' => 'Default data seeded.'];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Seeding failed: ' . $e->getMessage()]);
        return;
    }

    // ── 4. Create default tenant ───────────────────────────────────────────────
    try {
        $tenantId = createDefaultTenant($pdo, [
            'name'   => $companyName,
            'slug'   => $companySlug,
            'domain' => $companyDomain ?: null,
        ]);
        $log[] = ['type' => 'ok', 'msg' => "Tenant \"{$companyName}\" created (ID: {$tenantId})."];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Tenant creation failed: ' . $e->getMessage()]);
        return;
    }

    // ── 5. Create super admin ──────────────────────────────────────────────────
    try {
        $adminId = createSuperAdmin($pdo, [
            'first_name' => $adminFirst,
            'last_name'  => $adminLast,
            'email'      => $adminEmail,
            'password'   => $adminPass,
            'tenant_id'  => $tenantId,
        ]);
        $log[] = ['type' => 'ok', 'msg' => "Super admin \"{$adminEmail}\" created (ID: {$adminId})."];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Admin creation failed: ' . $e->getMessage()]);
        return;
    }

    // ── 6. Link tenant owner ───────────────────────────────────────────────────
    try {
        $pdo->prepare("UPDATE `tenants` SET `owner_id` = ?, `status` = 'active' WHERE `id` = ?")
            ->execute([$adminId, $tenantId]);
        $log[] = ['type' => 'ok', 'msg' => 'Tenant owner linked.'];
    } catch (Throwable $e) {
        // Non-fatal
        $log[] = ['type' => 'info', 'msg' => 'Could not link tenant owner (non-fatal).'];
    }

    // ── 7. Assign super-admin role ─────────────────────────────────────────────
    try {
        $superRole = $pdo->query("SELECT id FROM `roles` WHERE `slug` = 'super-admin' LIMIT 1")->fetch();
        if ($superRole) {
            $pdo->prepare("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES (?, ?)")
                ->execute([$adminId, $superRole['id']]);
            $log[] = ['type' => 'ok', 'msg' => 'Super-admin role assigned.'];
        }
    } catch (Throwable $e) {
        $log[] = ['type' => 'info', 'msg' => 'Role assignment skipped: ' . $e->getMessage()];
    }

    // ── 8. Seed system settings ────────────────────────────────────────────────
    try {
        seedSystemSettings($pdo, $appUrl, $companyName);
        $log[] = ['type' => 'ok', 'msg' => 'System settings seeded.'];
    } catch (Throwable $e) {
        $log[] = ['type' => 'info', 'msg' => 'System settings skipped: ' . $e->getMessage()];
    }

    // ── 9. Create .installed lock file ────────────────────────────────────────
    try {
        $installedContent = json_encode([
            'installed_at'  => date('Y-m-d H:i:s'),
            'version'       => '1.0.0',
            'admin_email'   => $adminEmail,
            'company'       => $companyName,
        ], JSON_PRETTY_PRINT);
        file_put_contents($rootDir . '/.installed', $installedContent);
        $log[] = ['type' => 'ok', 'msg' => '.installed lock file created.'];
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Could not create .installed file: ' . $e->getMessage()]);
        return;
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Installation completed successfully.',
        'log'      => $log,
        'redirect' => '/',
    ]);
}
