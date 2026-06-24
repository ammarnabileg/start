<?php
/**
 * Super Admin — Web Terminal (fragment, rendered into $content).
 * Whitelisted diagnostics only — commands proxied through POST /admin/terminal.
 * Wrapped by views/layouts/admin.php.
 */
$csrf = $csrf ?? '';
?>
<div class="px-4 sm:px-6 lg:px-8 py-6 max-w-6xl mx-auto fade-in" data-page="admin-terminal">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
      <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-violet-600 uppercase mb-1">
        <span class="inline-block w-2 h-2 rounded-full bg-violet-600"></span>
        <?= e(app_lang('System Diagnostics')) ?>
      </div>
      <h1 class="text-2xl font-bold text-gray-900"><?= e(app_lang('Admin Terminal')) ?></h1>
      <p class="text-sm text-gray-500 mt-1"><?= e(app_lang('Run safe, read-only server diagnostics from the browser.')) ?></p>
    </div>
    <div class="flex items-center gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 self-start">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      <span><?= e(app_lang('Only whitelisted diagnostics are permitted.')) ?></span>
    </div>
  </div>

  <!-- Quick command buttons -->
  <div class="card p-4 mb-4">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3"><?= e(app_lang('Quick Commands')) ?></p>
    <div id="quick-commands" class="flex flex-wrap gap-2">
      <!-- buttons injected by JS to keep label/key mapping in one place -->
    </div>
  </div>

  <!-- Terminal panel -->
  <div class="rounded-2xl overflow-hidden shadow-xl border border-slate-800" style="background:#0b0f1a;">
    <!-- Title bar -->
    <div class="flex items-center gap-2 px-4 py-2.5 border-b border-slate-800" style="background:#0d1322;">
      <span class="w-3 h-3 rounded-full bg-red-500"></span>
      <span class="w-3 h-3 rounded-full bg-amber-400"></span>
      <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
      <span class="ml-3 text-xs font-mono text-slate-400">admin@platform: ~/diagnostics</span>
      <div class="ltr:ml-auto rtl:mr-auto flex items-center gap-3">
        <button type="button" id="term-clear" class="text-xs font-mono text-slate-400 hover:text-slate-200 transition-colors">clear</button>
      </div>
    </div>

    <!-- Output -->
    <div id="terminal-output"
         class="px-4 py-4 font-mono text-[13px] leading-relaxed overflow-y-auto"
         style="height:58vh; min-height:340px; color:#cbd5e1;"
         aria-live="polite"></div>

    <!-- Prompt input -->
    <form id="terminal-form" class="flex items-center gap-2 px-4 py-3 border-t border-slate-800" style="background:#0d1322;">
      <span class="font-mono text-[13px] text-emerald-400 whitespace-nowrap select-none">admin@platform:~$</span>
      <input id="terminal-input" type="text" autocomplete="off" autocapitalize="off" spellcheck="false"
             placeholder="<?= e(app_lang('type a command (e.g. php_version) and press Enter')) ?>"
             class="flex-1 bg-transparent border-0 outline-none font-mono text-[13px] text-slate-100 placeholder-slate-600"
             style="caret-color:#34d399;" />
      <button type="submit" class="font-mono text-xs px-3 py-1.5 rounded-md bg-violet-600 hover:bg-violet-500 text-white transition-colors"><?= e(app_lang('Run')) ?></button>
    </form>
  </div>

  <p class="text-xs text-gray-400 mt-3">
    <?= e(app_lang('Available:')) ?>
    <span class="font-mono text-gray-500">php_version, php_modules, php_info, disk, memory, top, logs, clear_cache, db_test, permissions</span>.
    <?= e(app_lang('Type "help" to list commands or "clear" to reset.')) ?>
  </p>
</div>

<style>
  #terminal-output::-webkit-scrollbar { width: 8px; }
  #terminal-output::-webkit-scrollbar-thumb { background:#334155; border-radius:9999px; }
  .term-cursor { display:inline-block; width:8px; height:15px; background:#34d399; margin-left:2px; vertical-align:middle; animation: term-blink 1s steps(2, start) infinite; }
  @keyframes term-blink { to { visibility: hidden; } }
  .term-line pre { margin:0; white-space:pre-wrap; word-break:break-word; font-family: inherit; }
</style>

<script>
(function () {
  'use strict';
  var AR = window.AR || {};
  var esc = AR.esc || function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

  // Whitelisted commands: key + display label + aliases that map to the key.
  var COMMANDS = [
    { key: 'php_version',  label: 'PHP Version',       aliases: ['php_version', 'php -v', 'version'] },
    { key: 'php_modules',  label: 'PHP Modules',       aliases: ['php_modules', 'modules', 'php -m'] },
    { key: 'php_info',     label: 'PHP Info',          aliases: ['php_info', 'phpinfo', 'info'] },
    { key: 'disk',         label: 'Disk Usage',        aliases: ['disk', 'df', 'disk_usage'] },
    { key: 'memory',       label: 'Memory',            aliases: ['memory', 'mem', 'free'] },
    { key: 'top',          label: 'Top Processes',     aliases: ['top', 'ps', 'processes'] },
    { key: 'logs',         label: 'View Logs',         aliases: ['logs', 'log', 'tail'] },
    { key: 'clear_cache',  label: 'Clear Cache',       aliases: ['clear_cache', 'cache:clear', 'flush'] },
    { key: 'db_test',      label: 'Test DB',           aliases: ['db_test', 'db', 'ping_db'] },
    { key: 'permissions',  label: 'Check Permissions', aliases: ['permissions', 'perms', 'chmod_check'] }
  ];

  var aliasMap = {};
  COMMANDS.forEach(function (c) { c.aliases.forEach(function (a) { aliasMap[a.toLowerCase()] = c.key; }); aliasMap[c.key.toLowerCase()] = c.key; });

  var out = document.getElementById('terminal-output');
  var input = document.getElementById('terminal-input');
  var busy = false;

  function scrollBottom() { out.scrollTop = out.scrollHeight; }

  function appendPrompt(text) {
    var line = document.createElement('div');
    line.className = 'term-line';
    line.innerHTML = '<span style="color:#34d399;">admin@platform:~$</span> <span style="color:#e2e8f0;">' + esc(text) + '</span>';
    out.appendChild(line);
    scrollBottom();
  }

  function appendOutput(text, kind) {
    var color = kind === 'error' ? '#fca5a5' : kind === 'system' ? '#94a3b8' : '#cbd5e1';
    var wrap = document.createElement('div');
    wrap.className = 'term-line';
    wrap.style.margin = '2px 0 10px';
    var pre = document.createElement('pre');
    pre.style.color = color;
    pre.textContent = text == null ? '' : String(text);
    wrap.appendChild(pre);
    out.appendChild(wrap);
    scrollBottom();
  }

  function appendPending() {
    var p = document.createElement('div');
    p.className = 'term-line';
    p.style.margin = '2px 0 10px';
    p.innerHTML = '<span style="color:#64748b;">running…</span>';
    out.appendChild(p);
    scrollBottom();
    return p;
  }

  function welcome() {
    appendOutput(
      'Platform Diagnostics Console\n' +
      '----------------------------\n' +
      'Read-only, whitelisted commands only. Type "help" for the list.',
      'system'
    );
  }

  function showHelp() {
    var lines = COMMANDS.map(function (c) {
      var pad = c.key + '                '.slice(0, Math.max(0, 16 - c.key.length));
      return '  ' + pad + ' ' + c.label;
    });
    appendOutput('Available commands:\n' + lines.join('\n'), 'system');
  }

  async function runKey(key, typed) {
    if (busy) return;
    busy = true;
    var pending = appendPending();
    try {
      var res = await AR.Api.post('/admin/terminal', { command: key });
      pending.remove();
      var output = (res && (res.output != null ? res.output : res.result)) || '';
      if (output === '' && res && typeof res === 'string') output = res;
      appendOutput(output !== '' ? output : '(no output)');
    } catch (err) {
      pending.remove();
      appendOutput((err && err.message) || 'Command failed.', 'error');
      if (AR.Toast) AR.Toast.error((err && err.message) || 'Command failed.');
    } finally {
      busy = false;
      input.focus();
    }
  }

  function handle(raw) {
    var typed = (raw || '').trim();
    if (!typed) return;
    appendPrompt(typed);
    var lower = typed.toLowerCase();
    if (lower === 'clear' || lower === 'cls') { clearTerminal(false); return; }
    if (lower === 'help' || lower === '?') { showHelp(); return; }
    var key = aliasMap[lower];
    if (!key) {
      appendOutput('command not allowed: ' + typed + '\nType "help" to see the whitelist.', 'error');
      return;
    }
    runKey(key, typed);
  }

  function clearTerminal(reWelcome) {
    out.innerHTML = '';
    if (reWelcome !== false) welcome();
  }

  function buildButtons() {
    var wrap = document.getElementById('quick-commands');
    wrap.innerHTML = COMMANDS.map(function (c) {
      return '<button type="button" data-cmd="' + esc(c.key) + '" ' +
        'class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-700 ' +
        'hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 transition-colors inline-flex items-center gap-1.5">' +
        '<span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>' + esc(c.label) + '</button>';
    }).join('');
    wrap.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-cmd]');
      if (!b || busy) return;
      var key = b.getAttribute('data-cmd');
      appendPrompt(key);
      runKey(key, key);
    });
  }

  function init() {
    buildButtons();
    welcome();
    document.getElementById('terminal-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var v = input.value;
      input.value = '';
      handle(v);
    });
    document.getElementById('term-clear').addEventListener('click', function () { clearTerminal(true); input.focus(); });
    // Focus terminal when clicking anywhere in the output area.
    out.addEventListener('click', function () { if (!busy) input.focus(); });
    input.focus();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>
