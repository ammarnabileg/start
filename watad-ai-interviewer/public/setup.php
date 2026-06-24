<?php
/**
 * =====================================================================
 *  Watad — TEMPORARY install terminal
 * ---------------------------------------------------------------------
 *  Lets you run install commands (php artisan / composer / git ...) from
 *  the browser while setting the project up, BEFORE an admin account
 *  exists. It is a web shell, so it is protected by a secret token and an
 *  allow-list of commands.
 *
 *  HOW TO USE
 *   1. Edit the line below and set SETUP_TOKEN to a long random secret.
 *   2. Open  https://YOUR-DOMAIN/setup.php  and enter that token.
 *   3. Run the install commands (buttons provided).
 *   4. DELETE THIS FILE when done (there is a "Delete this file" button).
 *
 *  ⚠️  Never leave this file on a live site. Delete it after installing.
 * =====================================================================
 */

const SETUP_TOKEN = 'CHANGE-ME-to-a-long-random-secret-at-least-16-chars';

/**
 * Absolute path to the PHP **CLI** binary (NOT php-fpm). On Plesk this is
 * /opt/plesk/php/<version>/bin/php — adjust the version if needed.
 * We must hard-code it: under FPM, PHP_BINARY is php-fpm and open_basedir
 * usually hides /opt from is_file(), so auto-detection is unreliable.
 */
const PHP_BIN = '/opt/plesk/php/8.3/bin/php';

/** Plesk's bundled Composer phar (run as: PHP_BIN composer.phar ...). */
const COMPOSER_PHAR = '/usr/lib/plesk-9.0/composer.phar';

/* ----- binaries this terminal may run (default-deny everything else) ----- */
const ALLOWED = [
    'php', 'artisan', 'composer', 'git',
    'ls', 'cat', 'tail', 'head', 'pwd', 'whoami', 'df', 'stat', 'find',
    'cp', 'mv', 'ln', 'mkdir', 'touch', 'chmod', 'echo',
];

session_start();
header('X-Robots-Tag: noindex, nofollow');

$baseDir = dirname(__DIR__); // project root (this file lives in public/)
$tokenOk = strlen(SETUP_TOKEN) >= 16 && SETUP_TOKEN !== 'CHANGE-ME-to-a-long-random-secret-at-least-16-chars';

/* ----- self-delete ----- */
if (($_POST['action'] ?? '') === 'self_delete' && ($_SESSION['setup_ok'] ?? false)) {
    @unlink(__FILE__);
    session_destroy();
    header('Content-Type: text/html; charset=utf-8');
    exit('<h2 style="font-family:sans-serif">✓ setup.php deleted. This terminal is now gone.</h2>');
}

/* ----- auth ----- */
if (($_POST['action'] ?? '') === 'login') {
    if ($tokenOk && hash_equals(SETUP_TOKEN, (string) ($_POST['token'] ?? ''))) {
        $_SESSION['setup_ok'] = true;
    } else {
        $_SESSION['setup_err'] = 'Invalid token.';
    }
    header('Location: '.strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (($_POST['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: '.strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ----- command execution (JSON) ----- */
if (($_POST['action'] ?? '') === 'run') {
    header('Content-Type: application/json');
    if (! ($_SESSION['setup_ok'] ?? false)) {
        http_response_code(403);
        echo json_encode(['output' => 'Not authenticated.', 'code' => 403]);
        exit;
    }

    $raw    = trim((string) ($_POST['command'] ?? ''));
    $tokens = $raw === '' ? [] : preg_split('/\s+/', $raw);
    if (! $tokens) {
        echo json_encode(['output' => '', 'code' => 0]);
        exit;
    }

    $first = $tokens[0];
    if (! in_array($first, ALLOWED, true)) {
        echo json_encode(['output' => "Command not allowed: {$first}\nAllowed: ".implode(', ', ALLOWED), 'code' => 126]);
        exit;
    }

    echo json_encode(run_command($tokens, $baseDir));
    exit;
}

/* ----- helpers ----- */
function php_binary(): string
{
    // Hard-coded (see PHP_BIN note): is_file() is unreliable under FPM open_basedir,
    // and PHP_BINARY would be php-fpm — which cannot run artisan.
    return PHP_BIN;
}

function composer_argv(string $php, array $rest): array
{
    // Run the Plesk composer phar with the CLI php. If your composer lives elsewhere,
    // edit COMPOSER_PHAR, or use Plesk → PHP Composer for the install step instead.
    return array_merge([$php, COMPOSER_PHAR], $rest);
}

/** Run as an argument array → NO shell is involved, so command chaining is impossible. */
function run_command(array $tokens, string $cwd): array
{
    $php   = php_binary();
    $first = array_shift($tokens);

    $argv = match ($first) {
        'php'      => array_merge([$php], $tokens),
        'artisan'  => array_merge([$php, 'artisan'], $tokens),
        'composer' => composer_argv($php, $tokens),
        default    => array_merge([$first], $tokens),
    };

    @set_time_limit(310);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($argv, $descriptors, $pipes, $cwd, ['HOME' => $cwd, 'COMPOSER_HOME' => $cwd.'/.composer']);
    if (! is_resource($proc)) {
        return ['output' => 'Failed to start process. Is shell/exec disabled for PHP on this host?', 'code' => 1];
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    $text = trim($out."\n".$err);
    return ['output' => $text !== '' ? $text : '(no output)', 'code' => $code];
}

$authed = $_SESSION['setup_ok'] ?? false;
$err    = $_SESSION['setup_err'] ?? null;
unset($_SESSION['setup_err']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Watad — Install Terminal</title>
<style>
    *{box-sizing:border-box} body{margin:0;font-family:ui-sans-serif,system-ui,sans-serif;background:#0f172a;color:#e2e8f0}
    .wrap{max-width:920px;margin:0 auto;padding:24px}
    h1{font-size:18px;margin:0 0 4px} .sub{color:#94a3b8;font-size:13px;margin:0 0 20px}
    .card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px;margin-bottom:16px}
    input,button{font:inherit} input[type=text],input[type=password]{width:100%;padding:10px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#e2e8f0}
    .btn{cursor:pointer;border:0;border-radius:8px;padding:9px 14px;font-weight:600;background:#2563eb;color:#fff}
    .btn:hover{background:#1d4ed8} .btn.gray{background:#334155} .btn.red{background:#dc2626}
    .chips{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}
    .chip{cursor:pointer;border:1px solid #475569;background:#0f172a;color:#cbd5e1;border-radius:999px;padding:5px 10px;font-size:12px}
    .chip:hover{border-color:#2563eb;color:#fff}
    pre{background:#020617;border:1px solid #334155;border-radius:8px;padding:12px;min-height:200px;max-height:48vh;overflow:auto;white-space:pre-wrap;font-size:13px;line-height:1.5}
    .row{display:flex;gap:8px} .row input{flex:1}
    .warn{background:#7f1d1d;border:1px solid #b91c1c;color:#fecaca;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:16px}
    .ok{color:#4ade80} .bad{color:#f87171} code{background:#0f172a;padding:1px 5px;border-radius:4px}
</style>
</head>
<body>
<div class="wrap">
    <h1>Watad — Install Terminal</h1>
    <p class="sub">Temporary setup tool. Delete this file when you're done.</p>

<?php if (! $tokenOk): ?>
    <div class="warn">
        ⚠️ This terminal is disabled. Open <code>public/setup.php</code> and set
        <code>SETUP_TOKEN</code> to a long random secret (≥ 16 characters), then reload.
    </div>
<?php elseif (! $authed): ?>
    <div class="card">
        <form method="post">
            <input type="hidden" name="action" value="login">
            <label class="sub">Enter setup token</label>
            <input type="password" name="token" autofocus placeholder="SETUP_TOKEN">
            <?php if ($err): ?><p class="bad" style="font-size:13px"><?= htmlspecialchars($err) ?></p><?php endif; ?>
            <p><button class="btn" type="submit">Unlock</button></p>
        </form>
    </div>
<?php else: ?>
    <div class="warn">
        🔒 You're running commands on the server. When installation is finished, click
        <b>Delete this file</b> below — never leave setup.php on a live site.
    </div>

    <div class="card">
        <div class="sub">Quick install sequence (run top to bottom):</div>
        <div class="chips">
            <span class="chip" onclick="setCmd(this)">composer install --no-dev --optimize-autoloader</span>
            <span class="chip" onclick="setCmd(this)">cp .env.plesk .env</span>
            <span class="chip" onclick="setCmd(this)">php artisan key:generate</span>
            <span class="chip" onclick="setCmd(this)">php artisan migrate:fresh --seed --force</span>
            <span class="chip" onclick="setCmd(this)">php artisan storage:link</span>
            <span class="chip" onclick="setCmd(this)">chmod -R 775 storage bootstrap/cache</span>
            <span class="chip" onclick="setCmd(this)">php artisan config:clear</span>
            <span class="chip" onclick="setCmd(this)">php artisan route:clear</span>
            <span class="chip" onclick="setCmd(this)">php artisan view:clear</span>
            <span class="chip" onclick="setCmd(this)">php -v</span>
        </div>
        <div class="row">
            <input id="cmd" type="text" placeholder="php artisan ..." onkeydown="if(event.key==='Enter')runCmd()">
            <button class="btn" onclick="runCmd()">Run</button>
        </div>
        <p class="sub" style="margin:8px 0 0">PHP CLI: <code><?= htmlspecialchars(PHP_BIN) ?></code> · Allowed: <?= implode(', ', ALLOWED) ?></p>
        <p class="sub" style="margin:4px 0 0">Tip: run <code>php -v</code> first to confirm the PHP path is correct. If it shows php-fpm usage, edit <code>PHP_BIN</code> at the top of this file.</p>
    </div>

    <pre id="out">Ready. Run the steps above in order.
After "key:generate", edit .env (DB_*, OPENAI_API_KEY) before "migrate".</pre>

    <div class="row" style="margin-top:12px">
        <form method="post" onsubmit="return confirm('Delete setup.php now? You will lose this terminal.')">
            <input type="hidden" name="action" value="self_delete">
            <button class="btn red" type="submit">🗑 Delete this file</button>
        </form>
        <form method="post"><input type="hidden" name="action" value="logout"><button class="btn gray" type="submit">Lock</button></form>
    </div>

    <script>
        function setCmd(el){ document.getElementById('cmd').value = el.textContent.trim(); }
        async function runCmd(){
            const input = document.getElementById('cmd');
            const out = document.getElementById('out');
            const cmd = input.value.trim();
            if(!cmd) return;
            out.textContent += '\n\n$ ' + cmd + '\n';
            out.scrollTop = out.scrollHeight;
            try{
                const fd = new FormData();
                fd.append('action','run'); fd.append('command', cmd);
                const r = await fetch(location.pathname, { method:'POST', body: fd });
                const j = await r.json();
                out.textContent += j.output + '\n[exit ' + j.code + ']';
            }catch(e){ out.textContent += 'Request failed: ' + e; }
            out.scrollTop = out.scrollHeight;
        }
    </script>
<?php endif; ?>
</div>
</body>
</html>
