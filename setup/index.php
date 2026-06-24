<?php
/**
 * Installation wizard (5 steps). Self-contained — no framework, no SSH.
 * Posts to /setup/install.php for each action. If already installed, it
 * locks itself unless ?force=1 is passed.
 */
$alreadyInstalled = file_exists(dirname(__DIR__) . '/.env');
$force = isset($_GET['force']);
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · AI Recruit</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --brand:#7C3AED; --accent:#FBBF24; }
  body { font-family:'Inter',sans-serif; background:#0b0f1a; color:#e5e7eb; }
  .glass { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); backdrop-filter:blur(12px); }
  .grad { background:linear-gradient(135deg,#1E1B4B 0%,#7C3AED 60%,#a855f7 100%); }
  .grad-text { background:linear-gradient(135deg,#a78bfa,#fbbf24); -webkit-background-clip:text; background-clip:text; color:transparent; }
  .step-dot { transition:all .3s; }
  .step-dot.active { background:var(--brand); color:#fff; box-shadow:0 0 0 4px rgba(124,58,237,.25); }
  .step-dot.done { background:#22c55e; color:#fff; }
  input,select,textarea { background:#0f1626; border:1px solid rgba(255,255,255,.12); color:#e5e7eb; }
  input:focus,select:focus,textarea:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(124,58,237,.25); }
  .btn { border-radius:9999px; padding:.65rem 1.6rem; font-weight:600; transition:all .15s; cursor:pointer; }
  .btn-primary { background:var(--brand); color:#fff; } .btn-primary:hover { background:#6d28d9; transform:translateY(-1px); }
  .btn-accent { background:var(--accent); color:#111827; } .btn-accent:hover { filter:brightness(.95); }
  .btn-ghost { background:transparent; border:1px solid rgba(255,255,255,.15); color:#e5e7eb; }
  .field { margin-bottom:1rem; } .field label { display:block; font-size:.82rem; color:#9ca3af; margin-bottom:.35rem; }
  .field input,.field select,.field textarea { width:100%; border-radius:.6rem; padding:.6rem .8rem; }
  .check-row { display:flex; align-items:center; justify-content:space-between; padding:.6rem .8rem; border-radius:.6rem; background:rgba(255,255,255,.03); margin-bottom:.4rem; }
  .spin { animation:spin 1s linear infinite; } @keyframes spin { to { transform:rotate(360deg);} }
  .term { background:#05080f; border:1px solid rgba(255,255,255,.1); border-radius:.6rem; font-family:ui-monospace,Menlo,monospace; font-size:.8rem; color:#86efac; padding:.8rem; min-height:120px; max-height:240px; overflow:auto; white-space:pre-wrap; }
</style>
</head>
<body class="min-h-screen">

<?php if ($alreadyInstalled && !$force): ?>
  <div class="min-h-screen flex items-center justify-center p-6">
    <div class="glass rounded-3xl p-10 max-w-lg text-center">
      <div class="w-16 h-16 grad rounded-2xl mx-auto flex items-center justify-center text-2xl font-extrabold">AR</div>
      <h1 class="text-2xl font-bold mt-6">Already Installed</h1>
      <p class="text-gray-400 mt-3">AI Recruit is already set up on this server. For security, the installer is locked.</p>
      <div class="mt-6 flex gap-3 justify-center">
        <a href="/login" class="btn btn-primary">Go to Login</a>
        <a href="/setup/?force=1" class="btn btn-ghost">Re-run setup</a>
      </div>
      <p class="text-xs text-gray-600 mt-6">Tip: after install, delete the <code>/setup</code> directory or lock it for production.</p>
    </div>
  </div>
<?php else: ?>

<div class="min-h-screen grid lg:grid-cols-12">
  <!-- Branding panel -->
  <aside class="hidden lg:flex lg:col-span-4 grad p-10 flex-col justify-between">
    <div>
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-white/15 rounded-xl flex items-center justify-center text-xl font-extrabold">AR</div>
        <span class="text-xl font-bold">AI Recruit</span>
      </div>
      <h2 class="text-3xl font-extrabold mt-12 leading-tight">Let’s get you<br>set up.</h2>
      <p class="text-violet-100/80 mt-4">A guided, 5-step installation. No terminal required — everything happens right here in your browser.</p>
    </div>
    <ul class="space-y-3 text-violet-100/90 text-sm">
      <li class="flex items-center gap-2">✓ AI-powered interviews &amp; evaluations</li>
      <li class="flex items-center gap-2">✓ DISC &amp; Big-5 personality insights</li>
      <li class="flex items-center gap-2">✓ Multi-tenant, RBAC, multi-language</li>
      <li class="flex items-center gap-2">✓ HeyGen video avatars</li>
    </ul>
  </aside>

  <!-- Wizard -->
  <main class="lg:col-span-8 p-6 md:p-12 flex flex-col">
    <!-- Stepper -->
    <div class="flex items-center justify-between max-w-2xl mx-auto w-full mb-10">
      <?php
        $labels = ['Requirements', 'Database', 'Admin', 'AI & API', 'Install'];
        foreach ($labels as $i => $lbl):
          $n = $i + 1;
      ?>
        <div class="flex-1 flex items-center <?= $i < count($labels) - 1 ? '' : '' ?>">
          <div class="flex flex-col items-center">
            <div id="dot-<?= $n ?>" class="step-dot w-10 h-10 rounded-full bg-white/10 flex items-center justify-center font-semibold"><?= $n ?></div>
            <span class="text-xs text-gray-400 mt-2 whitespace-nowrap"><?= $lbl ?></span>
          </div>
          <?php if ($i < count($labels) - 1): ?>
            <div class="flex-1 h-px bg-white/10 mx-2"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="glass rounded-3xl p-6 md:p-10 max-w-2xl mx-auto w-full flex-1">
      <!-- STEP 1: Requirements -->
      <section data-step="1">
        <h3 class="text-xl font-bold">System Requirements</h3>
        <p class="text-gray-400 text-sm mt-1">We’ll verify your server meets the minimum requirements.</p>
        <div id="req-list" class="mt-6"><div class="text-gray-500 text-sm">Checking…</div></div>
        <div class="flex justify-between mt-8">
          <span></span>
          <button class="btn btn-primary" onclick="next(2)" id="req-next" disabled>Continue</button>
        </div>
      </section>

      <!-- STEP 2: Database -->
      <section data-step="2" class="hidden">
        <h3 class="text-xl font-bold">Database Connection</h3>
        <p class="text-gray-400 text-sm mt-1">Enter your MySQL credentials. We’ll create the database if it doesn’t exist.</p>
        <div class="grid md:grid-cols-2 gap-x-4 mt-6">
          <div class="field"><label>Host</label><input id="db_host" value="127.0.0.1"></div>
          <div class="field"><label>Port</label><input id="db_port" value="3306"></div>
          <div class="field md:col-span-2"><label>Database name</label><input id="db_database" value="airecruitment"></div>
          <div class="field"><label>Username</label><input id="db_username" value="root"></div>
          <div class="field"><label>Password</label><input id="db_password" type="password" placeholder="••••••••"></div>
        </div>
        <div id="db-result" class="text-sm mt-2"></div>
        <div class="flex justify-between mt-8">
          <button class="btn btn-ghost" onclick="next(1)">Back</button>
          <div class="flex gap-3">
            <button class="btn btn-accent" onclick="testDb()" id="db-test-btn">Test Connection</button>
            <button class="btn btn-primary" onclick="next(3)" id="db-next" disabled>Continue</button>
          </div>
        </div>
      </section>

      <!-- STEP 3: Admin -->
      <section data-step="3" class="hidden">
        <h3 class="text-xl font-bold">Super Admin Account</h3>
        <p class="text-gray-400 text-sm mt-1">This account manages the entire platform.</p>
        <div class="mt-6">
          <div class="field"><label>Full name</label><input id="admin_name" placeholder="Jane Doe"></div>
          <div class="field"><label>Email</label><input id="admin_email" type="email" placeholder="admin@company.com"></div>
          <div class="field"><label>Password (min 8 characters)</label><input id="admin_password" type="password" placeholder="••••••••"></div>
          <div class="field"><label>Confirm password</label><input id="admin_password2" type="password" placeholder="••••••••"></div>
        </div>
        <div id="admin-result" class="text-sm text-red-400"></div>
        <div class="flex justify-between mt-8">
          <button class="btn btn-ghost" onclick="next(2)">Back</button>
          <button class="btn btn-primary" onclick="validateAdmin()">Continue</button>
        </div>
      </section>

      <!-- STEP 4: AI & API -->
      <section data-step="4" class="hidden">
        <h3 class="text-xl font-bold">AI &amp; API Settings</h3>
        <p class="text-gray-400 text-sm mt-1">Add your keys now or later from Settings. The platform runs in a limited demo mode without them.</p>
        <div class="mt-6">
          <div class="field"><label>Platform name</label><input id="app_name" value="AI Recruit"></div>
          <div class="field"><label>App URL</label><input id="app_url" value="http://localhost"></div>
          <div class="field"><label>OpenAI API Key</label><input id="openai_api_key" type="password" placeholder="sk-..."></div>
          <div class="field"><label>OpenAI Model</label>
            <select id="openai_model">
              <option value="gpt-4-turbo-preview">gpt-4-turbo-preview</option>
              <option value="gpt-4o">gpt-4o</option>
              <option value="gpt-4o-mini">gpt-4o-mini</option>
              <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
            </select>
          </div>
          <div class="field"><label>HeyGen API Key</label><input id="heygen_api_key" type="password" placeholder="Optional"></div>
        </div>
        <div class="flex justify-between mt-8">
          <button class="btn btn-ghost" onclick="next(3)">Back</button>
          <button class="btn btn-primary" onclick="next(5)">Continue</button>
        </div>
      </section>

      <!-- STEP 5: Install -->
      <section data-step="5" class="hidden">
        <h3 class="text-xl font-bold">Install &amp; Finish</h3>
        <p class="text-gray-400 text-sm mt-1">Review and run the installation. This creates tables, seeds roles &amp; permissions, and your admin account.</p>
        <div id="install-summary" class="mt-6 space-y-2 text-sm"></div>
        <div id="install-log" class="term mt-4 hidden"></div>
        <div id="install-done" class="hidden mt-6 text-center">
          <div class="w-14 h-14 bg-green-500 rounded-full mx-auto flex items-center justify-center text-2xl">✓</div>
          <h4 class="text-lg font-bold mt-3">Installation complete!</h4>
          <p class="text-gray-400 text-sm mt-1">For security, delete or lock the <code>/setup</code> folder.</p>
          <a href="/login" class="btn btn-primary inline-block mt-5">Go to Login</a>
        </div>
        <div class="flex justify-between mt-8" id="install-actions">
          <button class="btn btn-ghost" onclick="next(4)">Back</button>
          <button class="btn btn-accent" onclick="runInstall()" id="install-btn">Run Installation</button>
        </div>
      </section>
    </div>

    <p class="text-center text-xs text-gray-600 mt-6">AI Recruit Installer · Everything is configured through this wizard — no SSH needed.</p>
  </main>
</div>

<script>
const state = {};
function $(id){ return document.getElementById(id); }

function next(step){
  document.querySelectorAll('[data-step]').forEach(s => s.classList.add('hidden'));
  const sec = document.querySelector('[data-step="'+step+'"]');
  if (sec) sec.classList.remove('hidden');
  for (let i=1;i<=5;i++){
    const dot = $('dot-'+i);
    dot.classList.remove('active','done');
    if (i < step) dot.classList.add('done');
    else if (i === step) dot.classList.add('active');
  }
  if (step === 5) buildSummary();
}

async function api(action, body){
  const res = await fetch('/setup/install.php?action='+action, {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body||{})
  });
  return res.json();
}

// Step 1
async function loadRequirements(){
  try {
    const r = await api('requirements', {});
    const list = $('req-list'); list.innerHTML = '';
    let allOk = true;
    (r.checks||[]).forEach(c => {
      if (!c.ok) allOk = false;
      const row = document.createElement('div');
      row.className = 'check-row';
      row.innerHTML = '<span class="text-sm">'+c.label+'</span>'+
        '<span class="text-xs '+(c.ok?'text-green-400':'text-red-400')+'">'+(c.ok?'✓ ':'✕ ')+c.value+'</span>';
      list.appendChild(row);
    });
    $('req-next').disabled = !allOk;
    if (!allOk){
      const note = document.createElement('div');
      note.className='text-amber-400 text-xs mt-3';
      note.textContent='Some checks failed. You can continue, but the platform may not work correctly until resolved.';
      list.appendChild(note);
      $('req-next').disabled = false; // allow continue with warning
    }
  } catch(e){ $('req-list').innerHTML = '<div class="text-red-400 text-sm">Could not run checks: '+e.message+'</div>'; $('req-next').disabled=false; }
}

// Step 2
async function testDb(){
  const btn = $('db-test-btn'); btn.disabled = true; btn.textContent = 'Testing…';
  state.db = {
    db_host:$('db_host').value, db_port:$('db_port').value, db_database:$('db_database').value,
    db_username:$('db_username').value, db_password:$('db_password').value
  };
  const r = await api('test_db', state.db);
  const out = $('db-result');
  if (r.success){
    out.className='text-sm mt-2 text-green-400'; out.textContent = '✓ '+r.message;
    $('db-next').disabled = false;
  } else {
    out.className='text-sm mt-2 text-red-400'; out.textContent = '✕ '+(r.error||'Failed');
    $('db-next').disabled = true;
  }
  btn.disabled = false; btn.textContent = 'Test Connection';
}

// Step 3
function validateAdmin(){
  const name=$('admin_name').value.trim(), email=$('admin_email').value.trim();
  const p1=$('admin_password').value, p2=$('admin_password2').value;
  const out=$('admin-result'); out.textContent='';
  if (!name){ out.textContent='Please enter your full name.'; return; }
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){ out.textContent='Please enter a valid email.'; return; }
  if (p1.length < 8){ out.textContent='Password must be at least 8 characters.'; return; }
  if (p1 !== p2){ out.textContent='Passwords do not match.'; return; }
  state.admin = { admin_name:name, admin_email:email, admin_password:p1 };
  next(4);
}

// Step 5
function buildSummary(){
  state.ai = {
    app_name:$('app_name').value, app_url:$('app_url').value,
    openai_api_key:$('openai_api_key').value, openai_model:$('openai_model').value,
    heygen_api_key:$('heygen_api_key').value
  };
  const db = state.db || {}; const ad = state.admin || {};
  $('install-summary').innerHTML =
    row('Database', (db.db_username||'root')+'@'+(db.db_host||'127.0.0.1')+':'+(db.db_port||'3306')+'/'+(db.db_database||'airecruitment')) +
    row('Super Admin', (ad.admin_email||'—')) +
    row('Platform', state.ai.app_name+' · '+state.ai.app_url) +
    row('OpenAI', state.ai.openai_api_key ? 'configured ('+state.ai.openai_model+')' : 'not set (demo mode)') +
    row('HeyGen', state.ai.heygen_api_key ? 'configured' : 'not set');
}
function row(k,v){ return '<div class="check-row"><span class="text-gray-400">'+k+'</span><span class="text-gray-200">'+escapeHtml(v)+'</span></div>'; }
function escapeHtml(s){ const d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }

async function runInstall(){
  const btn=$('install-btn'); btn.disabled=true; btn.innerHTML='<span class="spin inline-block">⟳</span> Installing…';
  const log=$('install-log'); log.classList.remove('hidden'); log.textContent='Starting installation…\n';
  const payload = Object.assign({}, state.db||{}, state.admin||{}, state.ai||{});
  try {
    const r = await api('install', payload);
    if (r.steps){ (r.steps||[]).forEach(s => log.textContent += '✓ '+s+'\n'); }
    if (r.success){
      log.textContent += '\nDone.';
      $('install-actions').classList.add('hidden');
      $('install-done').classList.remove('hidden');
    } else {
      log.textContent += '\n✕ '+(r.error||'Installation failed');
      btn.disabled=false; btn.textContent='Retry Installation';
    }
  } catch(e){
    log.textContent += '\n✕ '+e.message;
    btn.disabled=false; btn.textContent='Retry Installation';
  }
}

// init
next(1);
loadRequirements();
</script>

<?php endif; ?>
</body>
</html>
