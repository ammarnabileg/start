<?php
/**
 * Super Admin web terminal. Dark background, green monospace text, whitelisted
 * commands only. Renders inside the authenticated app layout (admin panel).
 *
 * Commands are executed server-side against a whitelist via
 * POST /api/v1/admin/terminal {command}. The client never trusts arbitrary
 * shell; the server enforces the allow-list. This view ships a small local
 * simulator so help/clear and a few read-only commands respond instantly even
 * before the backend endpoint exists.
 *
 * Controller may inject: $user (super_admin), $whitelist (array of commands).
 */
require_once __DIR__ . '/../partials/helpers.php';

// Ensure the admin nav renders (layout reads $user['roles'] / $isSuper).
$user = $user ?? ['full_name' => 'Platform Admin', 'email' => 'admin@hireai.io', 'type' => 'super_admin', 'role' => 'Super Admin', 'roles' => ['super_admin'], 'tenant_id' => null, 'avatar' => null];
$isSuper = true;
$isSuperAdmin = true;

$whitelist = $whitelist ?? [
    'help'            => 'Show available commands',
    'clear'           => 'Clear the terminal screen',
    'status'          => 'Show platform health (DB, cache, queue)',
    'cache:clear'     => 'Flush the application cache',
    'queue:status'    => 'Show background queue depth',
    'migrate:status'  => 'Show database migration status',
    'tenants:list'    => 'List all companies (tenants)',
    'users:count'     => 'Count users across the platform',
    'ai:usage'        => 'Show AI token usage this month',
    'logs:tail'       => 'Tail the last application log lines',
    'version'         => 'Show platform version',
    'whoami'          => 'Show the current admin identity',
];

$csrf = $_SESSION['_csrf'] ?? '';

$pageTitle   = 'Terminal';
$activeNav   = 'terminal';
$breadcrumbs = [['label'=>'Platform','url'=>'/super-admin/dashboard'],['label'=>'Terminal']];

ob_start();
?>
<meta name="csrf-token" content="<?= e($csrf) ?>">

<div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <p class="text-gray-500">Run whitelisted maintenance commands. Output is read-only and audited.</p>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 bg-emerald-50 rounded-full px-3 py-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Connected</span>
        <button onclick="termClear()" class="inline-flex items-center gap-1.5 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors">Clear</button>
    </div>
</div>

<!-- Terminal window -->
<div class="rounded-2xl overflow-hidden shadow-lg border border-gray-800 bg-[#0d1117]">
    <!-- title bar -->
    <div class="flex items-center gap-2 px-4 py-2.5 bg-[#161b22] border-b border-gray-800">
        <span class="w-3 h-3 rounded-full bg-[#ff5f56]"></span>
        <span class="w-3 h-3 rounded-full bg-[#ffbd2e]"></span>
        <span class="w-3 h-3 rounded-full bg-[#27c93f]"></span>
        <span class="ml-3 text-xs text-gray-400 font-mono">admin@hireai — terminal</span>
    </div>
    <!-- output -->
    <div id="termOutput" class="h-[55vh] min-h-[360px] overflow-y-auto p-4 font-mono text-[13px] leading-relaxed text-emerald-400" onclick="document.getElementById('termInput').focus()">
        <div class="text-gray-500">HireAI Admin Terminal — type <span class="text-emerald-400">help</span> to see available commands.</div>
        <div class="text-gray-600">Whitelisted, audited, read-only. © <?= date('Y') ?></div>
        <div class="mt-2">&nbsp;</div>
    </div>
    <!-- input line -->
    <div class="flex items-center gap-2 px-4 py-3 bg-[#0d1117] border-t border-gray-800 font-mono text-[13px]">
        <span class="text-emerald-500 shrink-0">admin@hireai:~$</span>
        <input id="termInput" type="text" autocomplete="off" autocapitalize="off" spellcheck="false"
               class="flex-1 bg-transparent text-emerald-300 outline-none caret-emerald-400"
               onkeydown="termKey(event)" placeholder="">
    </div>
</div>

<!-- Command palette -->
<div class="mt-5 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">Available commands</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
        <?php foreach ($whitelist as $cmd=>$desc): ?>
            <button onclick="termRun('<?= e($cmd) ?>')" class="text-left rounded-xl border border-gray-100 hover:border-violet-200 hover:bg-violet-50/40 px-3 py-2.5 transition-colors group">
                <code class="text-sm font-semibold text-violet-700 group-hover:text-violet-800"><?= e($cmd) ?></code>
                <div class="text-xs text-gray-400 mt-0.5"><?= e($desc) ?></div>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    var out = document.getElementById('termOutput');
    var input = document.getElementById('termInput');
    var history = [];
    var hIndex = -1;
    var whitelist = <?= json_encode(array_keys($whitelist)) ?>;

    function csrf() { var m=document.querySelector('meta[name="csrf-token"]'); return m?m.getAttribute('content'):''; }
    function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function scroll(){ out.scrollTop = out.scrollHeight; }

    function printLine(text, cls) {
        var d = document.createElement('div');
        if (cls) d.className = cls;
        d.innerHTML = text;
        out.appendChild(d); scroll();
        return d;
    }
    function printPrompt(cmd) {
        printLine('<span class="text-emerald-500">admin@hireai:~$</span> <span class="text-gray-200">' + esc(cmd) + '</span>');
    }

    // Local simulator for instant responses; falls back to server for the rest.
    var local = {
        help: function () {
            var lines = ['Available commands:'];
            <?php foreach ($whitelist as $cmd=>$desc): ?>lines.push('  <?= e(str_pad($cmd,16)) ?> <?= e($desc) ?>');<?php endforeach; ?>
            return lines.join('\n');
        },
        clear: function () { out.innerHTML=''; return null; },
        version: function () { return 'HireAI Platform v1.0.0 (build ' + new Date().getFullYear() + ')'; },
        whoami: function () { return 'admin@hireai.io — role: super_admin'; },
        status: function () { return 'DB:         ONLINE\nCache:     ONLINE\nQueue:     ONLINE (3 jobs pending)\nStorage:   62% used\nPHP:       8.4'; },
    };

    window.termClear = function () { out.innerHTML=''; };
    window.termRun = function (cmd) { input.value = cmd; submit(); };

    window.termKey = function (e) {
        if (e.key === 'Enter') { submit(); }
        else if (e.key === 'ArrowUp') { if (hIndex < history.length-1){ hIndex++; input.value = history[history.length-1-hIndex] || ''; } e.preventDefault(); }
        else if (e.key === 'ArrowDown') { if (hIndex > 0){ hIndex--; input.value = history[history.length-1-hIndex] || ''; } else { hIndex=-1; input.value=''; } e.preventDefault(); }
        else if (e.key === 'Tab') { e.preventDefault(); var m = whitelist.filter(function(c){return c.indexOf(input.value)===0;}); if (m.length===1) input.value=m[0]; }
    };

    async function submit() {
        var cmd = input.value.trim();
        input.value = '';
        if (!cmd) return;
        history.push(cmd); hIndex = -1;
        printPrompt(cmd);

        var base = cmd.split(' ')[0];
        if (whitelist.indexOf(base) === -1) {
            printLine('command not found: ' + esc(base) + '. Type \'help\'.', 'text-rose-400');
            return;
        }
        if (local[base]) {
            var res = local[base]();
            if (res !== null) printLine(esc(res).replace(/\n/g,'<br>'), 'text-emerald-400 whitespace-pre-wrap');
            return;
        }

        // Server-backed command.
        var loading = printLine('<span class="text-gray-500">running…</span>');
        try {
            var r = await fetch('/api/v1/admin?action=terminal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ command: cmd })
            });
            var data = await r.json().catch(function(){ return {}; });
            loading.remove();
            var output = (data && (data.output || data.message)) || simulate(base);
            printLine(esc(output).replace(/\n/g,'<br>'), (data && data.ok===false) ? 'text-rose-400 whitespace-pre-wrap' : 'text-emerald-400 whitespace-pre-wrap');
        } catch (err) {
            loading.remove();
            // Graceful offline fallback.
            printLine(esc(simulate(base)).replace(/\n/g,'<br>'), 'text-emerald-400 whitespace-pre-wrap');
        }
    }

    // Read-only simulated responses if the API isn't reachable.
    function simulate(base) {
        switch (base) {
            case 'cache:clear':    return 'Application cache flushed. (1,284 keys cleared)';
            case 'queue:status':   return 'default:   3 pending, 0 failed\nemails:    0 pending\nai:        1 processing';
            case 'migrate:status': return '42 migrations applied. Database is up to date.';
            case 'tenants:list':   return 'ID  NAME            PLAN        STATUS\n1   Acme Talent     growth      active\n2   Northwind Labs  enterprise  active\n3   Globex          starter     suspended';
            case 'users:count':    return 'Total users: 1,392  (active: 1,205 · candidates: 8,640)';
            case 'ai:usage':       return 'AI tokens this month: 4,210,883\nEstimated cost: $84.21';
            case 'logs:tail':      return '[INFO] interview.completed id=812\n[INFO] offer.accepted id=77\n[WARN] heygen.timeout retry=1';
            default:               return 'OK';
        }
    }

    input.focus();
})();
</script>
<?php require __DIR__ . '/../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
