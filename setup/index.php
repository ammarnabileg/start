<?php
/**
 * Setup Wizard — runs once, then locks itself.
 * Loaded BEFORE bootstrap.php so it works with no .env file.
 */
ob_start(); // catch any stray output before JSON responses

define('ROOT_DIR', dirname(__DIR__));

// ── Lock check ────────────────────────────────────────────────────────────────
$lockFile = ROOT_DIR . '/.setup_complete';

// ── AJAX handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_SETUP_ACTION'])) {
    ob_end_clean(); // discard any buffered output
    header('Content-Type: application/json');

    if (file_exists($lockFile)) {
        echo json_encode(['ok' => false, 'message' => 'Setup already completed.']);
        exit;
    }

    $action = $_SERVER['HTTP_X_SETUP_ACTION'];

    // ── Step 1: Write .env ────────────────────────────────────────────────────
    if ($action === 'write_env') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $appUrl = trim($body['app_url']  ?? 'http://localhost:8000');
        $dbHost = trim($body['db_host']  ?? 'localhost');
        $dbPort = trim($body['db_port']  ?? '3306');
        $dbName = trim($body['db_name']  ?? 'recruitai');
        $dbUser = trim($body['db_user']  ?? 'root');
        $dbPass = $body['db_pass']       ?? '';
        $secret = bin2hex(random_bytes(32));

        $env = <<<ENV
APP_URL={$appUrl}

DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

SESSION_SECRET={$secret}
ENV;
        if (file_put_contents(ROOT_DIR . '/.env', $env) === false) {
            echo json_encode(['ok' => false, 'message' => 'Cannot write .env — check folder permissions.']);
            exit;
        }
        echo json_encode(['ok' => true, 'message' => '.env file written successfully.']);
        exit;
    }

    // ── Step 2: Test DB connection ────────────────────────────────────────────
    if ($action === 'test_db') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $host   = trim($body['db_host'] ?? 'localhost');
        $port   = trim($body['db_port'] ?? '3306');
        $user   = trim($body['db_user'] ?? 'root');
        $pass   = $body['db_pass']      ?? '';
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo json_encode(['ok' => true, 'message' => 'Connection successful.']);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Step 3: Create DB + run schema ────────────────────────────────────────
    if ($action === 'run_schema') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $host   = trim($body['db_host'] ?? 'localhost');
        $port   = trim($body['db_port'] ?? '3306');
        $dbName = trim($body['db_name'] ?? 'recruitai');
        $user   = trim($body['db_user'] ?? 'root');
        $pass   = $body['db_pass']      ?? '';

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            $schema = file_get_contents(ROOT_DIR . '/database/schema.sql');
            if (!$schema) throw new RuntimeException('Cannot read schema.sql');

            // Split on statement delimiter and execute each
            $pdo->exec("SET NAMES utf8mb4");
            $pdo->exec("SET foreign_key_checks = 0");

            // Split by semicolons but preserve multi-line statements
            $statements = array_filter(
                array_map('trim', explode(";\n", $schema)),
                fn($s) => $s !== '' && !preg_match('/^(SET\s+NAMES|SET\s+foreign)/i', $s)
            );

            foreach ($statements as $stmt) {
                if (trim($stmt)) {
                    try { $pdo->exec($stmt); } catch (Throwable $e) {
                        // Ignore duplicate key / already exists errors during seed
                        if (!str_contains($e->getMessage(), '1062') && !str_contains($e->getMessage(), '1050')) {
                            throw $e;
                        }
                    }
                }
            }

            $pdo->exec("SET foreign_key_checks = 1");
            echo json_encode(['ok' => true, 'message' => "Database '{$dbName}' created and schema applied."]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Schema error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Step 4: Create Super Admin ────────────────────────────────────────────
    if ($action === 'create_admin') {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $host     = trim($body['db_host']     ?? 'localhost');
        $port     = trim($body['db_port']     ?? '3306');
        $dbName   = trim($body['db_name']     ?? 'recruitai');
        $user     = trim($body['db_user']     ?? 'root');
        $pass     = $body['db_pass']          ?? '';
        $email    = trim($body['admin_email'] ?? '');
        $password = $body['admin_password']   ?? '';
        $fname    = trim($body['admin_first'] ?? 'Super');
        $lname    = trim($body['admin_last']  ?? 'Admin');

        if (!$email || !$password) {
            echo json_encode(['ok' => false, 'message' => 'Admin email and password are required.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'message' => 'Invalid email address.']);
            exit;
        }
        if (strlen($password) < 8) {
            echo json_encode(['ok' => false, 'message' => 'Password must be at least 8 characters.']);
            exit;
        }

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Check if super admin already exists
            $existing = $pdo->prepare("SELECT id FROM users WHERE is_super_admin=1 LIMIT 1");
            $existing->execute();
            if ($existing->fetch()) {
                echo json_encode(['ok' => true, 'message' => 'Super admin already exists — skipped.', 'skipped' => true]);
                exit;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare(
                "INSERT INTO users (email, password_hash, first_name, last_name, is_super_admin, status, created_at)
                 VALUES (?, ?, ?, ?, 1, 'active', NOW())"
            );
            $stmt->execute([$email, $hash, $fname ?: 'Super', $lname ?: 'Admin']);
            echo json_encode(['ok' => true, 'message' => 'Super admin account created.']);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Admin creation error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Step 5: Lock setup ────────────────────────────────────────────────────
    if ($action === 'lock') {
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── If already set up, redirect ───────────────────────────────────────────────
if (file_exists($lockFile)) {
    header('Location: /login');
    exit;
}

// ── HTML Page ─────────────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RecruitAI — Setup</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
  body { font-family: 'Inter', sans-serif; }
  .step-line { transition: width 0.5s ease; }
  .log-line { animation: fadeIn 0.2s ease; }
  @keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }
  input:focus { outline: none; box-shadow: 0 0 0 3px rgba(99,102,241,.25); }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 flex items-center justify-center p-4">

<div class="w-full max-w-xl">

  <!-- Logo -->
  <div class="text-center mb-8">
    <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-600 rounded-2xl mb-4 shadow-lg">
      <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-gray-900">RecruitAI Setup</h1>
    <p class="text-gray-500 text-sm mt-1">Configure your platform in one click</p>
  </div>

  <!-- Card -->
  <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">

    <!-- Progress Bar -->
    <div class="h-1.5 bg-gray-100">
      <div id="progress-bar" class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 step-line" style="width:0%"></div>
    </div>

    <div class="p-8 space-y-6">

      <!-- Step Indicators -->
      <div class="flex items-center justify-between text-xs font-medium mb-2">
        <?php foreach (['Config','Database','Admin','Done'] as $i => $label): ?>
        <div class="flex flex-col items-center gap-1.5">
          <div id="step-dot-<?= $i ?>" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300
            <?= $i === 0 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-400' ?>">
            <?= $i + 1 ?>
          </div>
          <span id="step-label-<?= $i ?>" class="<?= $i === 0 ? 'text-indigo-600' : 'text-gray-400' ?>"><?= $label ?></span>
        </div>
        <?php if ($i < 3): ?>
        <div class="flex-1 h-px bg-gray-200 mx-2 mb-3"></div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <!-- ── SECTION 1: Config ──────────────────────────────────────────── -->
      <div id="section-0">
        <h2 class="text-base font-semibold text-gray-800 mb-4">Database & App Configuration</h2>
        <div class="space-y-3">
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
              <label class="block text-xs text-gray-500 mb-1 font-medium">App URL</label>
              <input id="app_url" type="text" value="http://localhost:8000"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-medium">DB Port</label>
              <input id="db_port" type="text" value="3306"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-medium">DB Host</label>
              <input id="db_host" type="text" value="localhost"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-medium">Database Name</label>
              <input id="db_name" type="text" value="recruitai"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-medium">DB Username</label>
              <input id="db_user" type="text" value="root"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-medium">DB Password</label>
              <input id="db_pass" type="password" placeholder="(empty if none)"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
          </div>
        </div>
      </div>

      <!-- ── SECTION 2: Admin Account ───────────────────────────────────── -->
      <div id="section-1" class="hidden">
        <h2 class="text-base font-semibold text-gray-800 mb-4">Super Admin Account</h2>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-medium">First Name</label>
              <input id="admin_first" type="text" value="Super"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1 font-medium">Last Name</label>
              <input id="admin_last" type="text" value="Admin"
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1 font-medium">Email</label>
            <input id="admin_email" type="email" placeholder="admin@yourcompany.com"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1 font-medium">Password <span class="text-gray-400">(min 8 chars)</span></label>
            <input id="admin_password" type="password" placeholder="Strong password"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm">
          </div>
        </div>
      </div>

      <!-- ── SECTION 3: Running log ─────────────────────────────────────── -->
      <div id="section-log" class="hidden">
        <h2 class="text-base font-semibold text-gray-800 mb-3">Installing…</h2>
        <div id="log" class="bg-gray-950 rounded-2xl p-4 font-mono text-xs space-y-1 min-h-[140px] max-h-56 overflow-y-auto"></div>
      </div>

      <!-- ── SECTION 4: Done ────────────────────────────────────────────── -->
      <div id="section-done" class="hidden text-center py-4">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-1">Setup Complete!</h2>
        <p class="text-gray-500 text-sm mb-6">Your RecruitAI platform is ready.</p>
        <a href="/login" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition-colors shadow-md">
          Go to Login
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
          </svg>
        </a>
      </div>

      <!-- Error banner -->
      <div id="error-banner" class="hidden bg-red-50 border border-red-200 rounded-xl p-3 flex items-start gap-2">
        <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p id="error-text" class="text-red-700 text-sm"></p>
      </div>

      <!-- Buttons -->
      <div id="btn-area" class="flex gap-3">
        <button id="btn-next" onclick="nextStep()"
          class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2">
          <span id="btn-label">Continue</span>
          <svg id="btn-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
          </svg>
          <svg id="btn-spin" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
        </button>
      </div>

    </div>
  </div>

  <p class="text-center text-xs text-gray-400 mt-4">This page will be disabled after setup completes.</p>
</div>

<script>
let currentStep = 0;   // 0=config, 1=admin, 2=installing, 3=done
let busy = false;

const sections   = ['section-0','section-1','section-log','section-done'];
const stepCount  = 4;
const progressAt = [0, 33, 66, 100];

function g(id) { return document.getElementById(id); }

function showError(msg) {
    g('error-banner').classList.remove('hidden');
    g('error-text').textContent = msg;
}
function clearError() { g('error-banner').classList.add('hidden'); }

function setLoading(state) {
    busy = state;
    g('btn-next').disabled = state;
    g('btn-icon').classList.toggle('hidden', state);
    g('btn-spin').classList.toggle('hidden', !state);
    g('btn-next').classList.toggle('opacity-60', state);
}

function addLog(msg, type = 'info') {
    const colors = { info: 'text-gray-300', ok: 'text-green-400', err: 'text-red-400', step: 'text-indigo-400 font-semibold' };
    const icons  = { info: '  ', ok: '✓ ', err: '✗ ', step: '▸ ' };
    const div = document.createElement('div');
    div.className = 'log-line ' + (colors[type] || 'text-gray-300');
    div.textContent = icons[type] + msg;
    const log = g('log');
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
}

function activateStep(idx) {
    // dots
    for (let i = 0; i < 4; i++) {
        const dot   = g('step-dot-' + i);
        const label = g('step-label-' + i);
        if (i < idx) {
            dot.className   = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 bg-green-500 text-white';
            dot.innerHTML   = '✓';
            label.className = 'text-green-600';
        } else if (i === idx) {
            dot.className   = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 bg-indigo-600 text-white';
            dot.textContent = i + 1;
            label.className = 'text-indigo-600';
        } else {
            dot.className   = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 bg-gray-100 text-gray-400';
            dot.textContent = i + 1;
            label.className = 'text-gray-400';
        }
    }
    // progress bar
    g('progress-bar').style.width = progressAt[idx] + '%';
    // sections
    sections.forEach((id, i) => g(id).classList.toggle('hidden', i !== idx));
}

function fields() {
    return {
        app_url:        g('app_url').value.trim(),
        db_host:        g('db_host').value.trim(),
        db_port:        g('db_port').value.trim(),
        db_name:        g('db_name').value.trim(),
        db_user:        g('db_user').value.trim(),
        db_pass:        g('db_pass').value,
        admin_email:    g('admin_email').value.trim(),
        admin_password: g('admin_password').value,
        admin_first:    g('admin_first').value.trim(),
        admin_last:     g('admin_last').value.trim(),
    };
}

async function api(action, body) {
    const r = await fetch('/setup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Setup-Action': action },
        body: JSON.stringify(body),
    });
    return r.json();
}

async function nextStep() {
    if (busy) return;
    clearError();

    // ── Step 0 → 1: Validate config inputs ───────────────────────────────────
    if (currentStep === 0) {
        const f = fields();
        if (!f.app_url || !f.db_host || !f.db_name || !f.db_user) {
            showError('Please fill in all required fields.'); return;
        }
        currentStep = 1;
        activateStep(1);
        g('btn-label').textContent = 'Install Now';
        return;
    }

    // ── Step 1 → 2: Validate admin then run everything ────────────────────────
    if (currentStep === 1) {
        const f = fields();
        if (!f.admin_email || !f.admin_password) {
            showError('Admin email and password are required.'); return;
        }
        if (f.admin_password.length < 8) {
            showError('Password must be at least 8 characters.'); return;
        }

        // Switch to log view
        currentStep = 2;
        activateStep(2);
        g('btn-area').classList.add('hidden');

        // ── Run all steps ─────────────────────────────────────────────────────
        setLoading(true);

        const steps = [
            { action: 'test_db',      label: 'Testing database connection…',  body: f },
            { action: 'write_env',    label: 'Writing .env configuration…',   body: f },
            { action: 'run_schema',   label: 'Creating database & tables…',   body: f },
            { action: 'create_admin', label: 'Creating super admin account…', body: f },
            { action: 'lock',         label: 'Finalizing setup…',             body: {} },
        ];

        for (const step of steps) {
            addLog(step.label, 'step');
            try {
                const res = await api(step.action, step.body);
                if (!res.ok) {
                    addLog(res.message || 'Failed.', 'err');
                    showError(res.message || 'An error occurred. Check the log above.');
                    setLoading(false);
                    g('btn-area').classList.remove('hidden');
                    g('btn-label').textContent = 'Retry';
                    currentStep = 1;
                    activateStep(1);
                    return;
                }
                addLog(res.message || 'Done.', 'ok');
            } catch(e) {
                addLog('Network error: ' + e.message, 'err');
                showError('Network error — is the server running?');
                setLoading(false);
                g('btn-area').classList.remove('hidden');
                g('btn-label').textContent = 'Retry';
                currentStep = 1;
                activateStep(1);
                return;
            }
        }

        // ── Done ──────────────────────────────────────────────────────────────
        setTimeout(() => {
            currentStep = 3;
            activateStep(3);
            g('progress-bar').style.width = '100%';
            g('btn-area').classList.add('hidden');
        }, 600);
    }
}

// Allow Enter key to advance
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !busy && currentStep < 2) nextStep();
});
</script>
</body>
</html>
