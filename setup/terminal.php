<?php
/**
 * HireAI - Setup Web Terminal
 * ---------------------------------------------------------------------------
 * A dark, monospace terminal UI for safe server diagnostics during setup.
 * Commands are dispatched to ajax.php?action=run_command and validated against
 * a server-side whitelist (NO arbitrary shell execution).
 *
 * Self-contained: Tailwind via CDN + vanilla JS. Gated by the same lock file
 * as the rest of setup.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('hireai_setup');
    session_start();
}

define('SETUP_DIR', __DIR__);

// Honor the setup lock.
if (is_file(SETUP_DIR . '/.locked') && empty($_SESSION['setup_unlocked'])) {
    header('Location: index.php');
    exit;
}

$commands = [
    ['cmd' => 'php_version',      'label' => 'Check PHP Version',    'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
    ['cmd' => 'php_extensions',   'label' => 'Check Extensions',     'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
    ['cmd' => 'file_permissions', 'label' => 'Check File Permissions','icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['cmd' => 'view_error_log',   'label' => 'View Error Log',       'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a1 1 0 01.7.3l5.4 5.4a1 1 0 01.3.7V19a2 2 0 01-2 2z'],
    ['cmd' => 'clear_cache',      'label' => 'Clear Cache',          'icon' => 'M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h12'],
    ['cmd' => 'system_info',      'label' => 'System Info',          'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['cmd' => 'test_db',          'label' => 'Test DB Connection',   'icon' => 'M4 7v10c0 2.2 3.6 4 8 4s8-1.8 8-4V7M4 7c0 2.2 3.6 4 8 4s8-1.8 8-4M4 7c0-2.2 3.6-4 8-4s8 1.8 8 4'],
    ['cmd' => 'disk_space',       'label' => 'Disk Space',           'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8'],
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Web Terminal &middot; HireAI Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: {
                    sans: ['Inter','ui-sans-serif','system-ui','sans-serif'],
                    mono: ['JetBrains Mono','ui-monospace','SFMono-Regular','monospace']
                },
                colors: { brand: { 400:'#A78BFA',500:'#8B5CF6',600:'#7C3AED',700:'#6D28D9' }, gold: { 400:'#FBBF24' } }
            } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .term { font-family: 'JetBrains Mono', monospace; }
        #term-output::-webkit-scrollbar { width: 10px; }
        #term-output::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }
        #term-output::-webkit-scrollbar-track { background: transparent; }
        .blink { animation: blink 1s step-end infinite; }
        @keyframes blink { 50% { opacity: 0; } }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-slate-950 to-slate-900 text-gray-200">
<div class="min-h-full flex flex-col">

    <!-- Top bar -->
    <header class="flex items-center justify-between px-5 py-3 border-b border-slate-800 bg-slate-900/60 backdrop-blur">
        <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-xl bg-brand-600 flex items-center justify-center shadow-lg shadow-brand-600/30">
                <svg class="h-5 w-5 text-gold-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.7 2.3a1 1 0 011.4 0l2 2a1 1 0 010 1.4l-2 2a1 1 0 11-1.4-1.4L7 9l-1.3-1.3a1 1 0 010-1.4zM11 12a1 1 0 100 2h2a1 1 0 100-2h-2z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-white leading-none">HireAI Web Terminal</p>
                <p class="text-xs text-slate-400 mt-0.5">Safe diagnostics &middot; whitelisted commands only</p>
            </div>
        </div>
        <a href="index.php" class="text-xs font-semibold text-brand-400 hover:text-brand-300 transition-colors duration-200 flex items-center gap-1.5">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.7 5.3a1 1 0 010 1.4L9.4 10l3.3 3.3a1 1 0 01-1.4 1.4l-4-4a1 1 0 010-1.4l4-4a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            Back to Setup
        </a>
    </header>

    <div class="flex-1 flex flex-col lg:flex-row gap-4 p-4 lg:p-5 max-w-7xl w-full mx-auto">

        <!-- Command palette -->
        <aside class="lg:w-64 shrink-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Quick Commands</p>
            <div class="grid grid-cols-2 lg:grid-cols-1 gap-2">
                <?php foreach ($commands as $c): ?>
                    <button type="button" onclick="runCmd('<?= htmlspecialchars($c['cmd'], ENT_QUOTES) ?>')"
                        class="group flex items-center gap-2.5 rounded-xl border border-slate-800 bg-slate-900/50 hover:bg-slate-800 hover:border-brand-600 px-3 py-2.5 text-left transition-all duration-200">
                        <svg class="h-4 w-4 text-brand-400 group-hover:text-brand-300 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $c['icon'] ?>"/></svg>
                        <span class="text-xs font-medium text-slate-200 group-hover:text-white"><?= htmlspecialchars($c['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 rounded-xl border border-slate-800 bg-slate-900/40 p-3">
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    <span class="text-gold-400 font-semibold">Security:</span> only whitelisted commands run. There is no arbitrary shell access.
                </p>
            </div>
        </aside>

        <!-- Terminal window -->
        <main class="flex-1 flex flex-col rounded-2xl border border-slate-800 bg-black/70 overflow-hidden shadow-2xl min-h-[420px]">
            <!-- Title bar -->
            <div class="flex items-center gap-2 px-4 py-2.5 border-b border-slate-800 bg-slate-900/80">
                <span class="h-3 w-3 rounded-full bg-red-500/90"></span>
                <span class="h-3 w-3 rounded-full bg-yellow-500/90"></span>
                <span class="h-3 w-3 rounded-full bg-green-500/90"></span>
                <span class="ml-3 text-xs term text-slate-400">hireai@setup: ~</span>
                <button onclick="clearTerm()" class="ml-auto text-xs text-slate-500 hover:text-slate-300 transition-colors duration-200">clear</button>
            </div>

            <!-- Output -->
            <div id="term-output" class="flex-1 overflow-y-auto p-4 term text-[13px] leading-relaxed space-y-2">
                <div class="text-slate-500">HireAI diagnostic terminal. Type <span class="text-brand-400">help</span> or click a command on the left.</div>
            </div>

            <!-- Input -->
            <form id="term-form" class="flex items-center gap-2 px-4 py-3 border-t border-slate-800 bg-slate-900/60" onsubmit="return submitCmd(event)">
                <span class="term text-green-400 select-none">$</span>
                <input id="term-input" type="text" autocomplete="off" autofocus spellcheck="false"
                    placeholder="type a command (e.g. system_info)…"
                    class="flex-1 bg-transparent term text-[13px] text-green-300 placeholder-slate-600 outline-none">
                <button type="submit" class="rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 transition-all duration-200">Run</button>
            </form>
        </main>
    </div>
</div>

<script>
const ALLOWED = <?= json_encode(array_column($commands, 'cmd')) ?>;
const ALL_COMMANDS = ALLOWED.concat(['memory_info','env_check','mysql_version','list_uploads','installed_date','check_writable']);
const out = document.getElementById('term-output');
const input = document.getElementById('term-input');
const history = [];
let histIdx = -1;

function appendLine(html, cls) {
    const div = document.createElement('div');
    if (cls) div.className = cls;
    div.innerHTML = html;
    out.appendChild(div);
    out.scrollTop = out.scrollHeight;
    return div;
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}

function echoPrompt(cmd) {
    appendLine('<span class="text-green-400">$</span> <span class="text-slate-200">' + escapeHtml(cmd) + '</span>');
}

function runCmd(cmd) {
    echoPrompt(cmd);

    if (cmd === 'help') {
        appendLine('<span class="text-slate-400">Available commands:</span>\n' +
            '<span class="text-brand-400">' + ALL_COMMANDS.join('  ') + '</span>', 'whitespace-pre-wrap');
        return;
    }
    if (cmd === 'clear') { clearTerm(); return; }

    const loading = appendLine('<span class="text-slate-500">running ' + escapeHtml(cmd) + '<span class="blink">…</span></span>');

    const fd = new FormData();
    fd.append('cmd', cmd);
    fetch('ajax.php?action=run_command', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r){ return r.json(); })
        .then(function(res){
            loading.remove();
            if (res.ok) {
                appendLine('<pre class="whitespace-pre-wrap text-slate-100">' + escapeHtml(res.output || '(no output)') + '</pre>');
            } else {
                appendLine('<span class="text-red-400">' + escapeHtml(res.output || res.msg || 'Command failed') + '</span>');
            }
        })
        .catch(function(){
            loading.remove();
            appendLine('<span class="text-red-400">Network error. Could not reach ajax.php.</span>');
        });
}

function submitCmd(e) {
    e.preventDefault();
    const cmd = input.value.trim();
    if (!cmd) return false;
    history.push(cmd); histIdx = history.length;
    input.value = '';
    runCmd(cmd);
    return false;
}

function clearTerm() {
    out.innerHTML = '<div class="text-slate-500">Terminal cleared.</div>';
}

// Up/Down arrow history.
input.addEventListener('keydown', function(e){
    if (e.key === 'ArrowUp') {
        if (histIdx > 0) { histIdx--; input.value = history[histIdx] || ''; }
        e.preventDefault();
    } else if (e.key === 'ArrowDown') {
        if (histIdx < history.length - 1) { histIdx++; input.value = history[histIdx] || ''; }
        else { histIdx = history.length; input.value = ''; }
        e.preventDefault();
    }
});
</script>
</body>
</html>
