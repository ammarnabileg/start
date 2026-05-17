<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SociAI OS — Setup Wizard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#0F0F1A;color:#f0f0ff;min-height:100vh;padding:2rem 1rem}
.container{max-width:780px;margin:0 auto}
.logo{text-align:center;margin-bottom:2rem}
.logo h1{font-size:2rem;font-weight:900;background:linear-gradient(135deg,#6C63FF,#FF6584);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.logo p{color:#7070a0;margin-top:.4rem}

/* Steps */
.steps{display:flex;gap:0;margin-bottom:2.5rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;overflow:hidden}
.step{flex:1;padding:.85rem .5rem;text-align:center;font-size:.78rem;font-weight:600;color:#7070a0;border-right:1px solid rgba(255,255,255,.08);cursor:default;transition:all .2s}
.step:last-child{border-right:none}
.step.active{background:rgba(108,99,255,.15);color:#a89cff}
.step.done{background:rgba(16,185,129,.1);color:#10B981}
.step-num{display:block;font-size:1.1rem;margin-bottom:.2rem}

/* Card */
.card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:2rem;margin-bottom:1.5rem}
.card h2{font-size:1.25rem;font-weight:700;margin-bottom:.4rem}
.card .desc{color:#7070a0;font-size:.875rem;margin-bottom:1.75rem;line-height:1.6}

/* Form */
.form-row{margin-bottom:1.1rem}
.form-row label{display:block;font-size:.82rem;font-weight:600;color:#b8b8d0;margin-bottom:.4rem}
.form-row label span{font-size:.72rem;color:#7070a0;font-weight:400;margin-left:.4rem}
.form-row input,.form-row select{
  width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);
  color:#f0f0ff;border-radius:8px;padding:.65rem 1rem;font-size:.88rem;outline:none;
  transition:border-color .2s;font-family:inherit;
}
.form-row input:focus,.form-row select:focus{border-color:#6C63FF}
.form-row input::placeholder{color:#4a4a6a}
.form-row select option{background:#1a1a30}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1.4rem;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,#6C63FF,#8B5CF6);color:#fff}
.btn-primary:hover{opacity:.88;transform:translateY(-1px)}
.btn-secondary{background:rgba(255,255,255,.07);color:#b8b8d0;border:1px solid rgba(255,255,255,.1)}
.btn-secondary:hover{background:rgba(255,255,255,.11)}
.btn-test{background:rgba(6,182,212,.15);color:#06B6D4;border:1px solid rgba(6,182,212,.2);padding:.45rem 1rem;font-size:.8rem}
.btn-test:hover{background:rgba(6,182,212,.25)}
.actions{display:flex;align-items:center;justify-content:space-between;margin-top:1.5rem;flex-wrap:wrap;gap:.75rem}

/* Alert */
.alert{padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem;display:none}
.alert.show{display:block}
.alert-success{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.25);color:#6BDDB3}
.alert-error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:#FCA5A5}
.alert-info{background:rgba(6,182,212,.12);border:1px solid rgba(6,182,212,.25);color:#67E8F9}

/* Platform cards */
.platform-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.platform-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:1.1rem}
.platform-card h4{font-size:.9rem;font-weight:700;margin-bottom:.2rem;display:flex;align-items:center;gap:.5rem}
.platform-card .badge{font-size:.65rem;padding:.15rem .45rem;border-radius:50px;font-weight:700}
.badge-required{background:rgba(245,158,11,.2);color:#F59E0B}
.badge-optional{background:rgba(107,114,128,.2);color:#9CA3AF}

/* Tips box */
.tip{background:rgba(108,99,255,.08);border:1px solid rgba(108,99,255,.2);border-radius:8px;padding:.85rem 1rem;font-size:.82rem;color:#a89cff;margin-top:1rem;line-height:1.6}
.tip strong{color:#c4b8ff}

/* Progress bar */
.progress{height:4px;background:rgba(255,255,255,.08);border-radius:2px;margin-bottom:2rem;overflow:hidden}
.progress-bar{height:100%;background:linear-gradient(90deg,#6C63FF,#8B5CF6);border-radius:2px;transition:width .4s ease}

/* Test result */
.test-result{font-size:.8rem;margin-top:.4rem;padding:.4rem .7rem;border-radius:6px;display:none}
.test-result.show{display:block}
.test-ok{background:rgba(16,185,129,.12);color:#10B981}
.test-fail{background:rgba(239,68,68,.12);color:#EF4444}

/* Final checklist */
.checklist{list-style:none}
.checklist li{padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:.88rem;display:flex;align-items:center;gap:.75rem}
.checklist li:last-child{border-bottom:none}
.check-icon{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0}
.check-ok{background:rgba(16,185,129,.2);color:#10B981}
.check-miss{background:rgba(239,68,68,.15);color:#EF4444}
.check-warn{background:rgba(245,158,11,.15);color:#F59E0B}
.cron-box{background:#0D0D1E;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:1rem;font-family:monospace;font-size:.8rem;color:#a89cff;margin-top:.75rem;line-height:1.8}
</style>
</head>
<body>
<div class="container">

  <div class="logo">
    <h1>⚡ SociAI OS — Setup Wizard</h1>
    <p>Configure your platform in 4 easy steps</p>
  </div>

  <!-- Steps bar -->
  <div class="steps">
    <div class="step" id="step-tab-1"><span class="step-num">1</span>Database</div>
    <div class="step" id="step-tab-2"><span class="step-num">2</span>App & AI</div>
    <div class="step" id="step-tab-3"><span class="step-num">3</span>Platforms</div>
    <div class="step" id="step-tab-4"><span class="step-num">4</span>Done</div>
  </div>

  <div class="progress"><div class="progress-bar" id="progressBar" style="width:25%"></div></div>

  <!-- ==================== STEP 1: Database ==================== -->
  <div id="step-1">
    <div class="card">
      <h2>🗄️ Database Connection</h2>
      <p class="desc">Enter your Plesk MySQL database credentials. You can find these in <strong>Plesk → Databases → your database → Edit</strong>.</p>

      <div id="db-alert" class="alert"></div>

      <div class="row-2">
        <div class="form-row">
          <label>DB Host <span>(usually localhost)</span></label>
          <input type="text" id="db_host" value="localhost" placeholder="localhost">
        </div>
        <div class="form-row">
          <label>DB Port <span>(usually 3306)</span></label>
          <input type="text" id="db_port" value="3306" placeholder="3306">
        </div>
      </div>
      <div class="form-row">
        <label>Database Name</label>
        <input type="text" id="db_name" placeholder="e.g. admin_sociai_db">
      </div>
      <div class="row-2">
        <div class="form-row">
          <label>DB Username</label>
          <input type="text" id="db_user" placeholder="e.g. admin_sociai">
        </div>
        <div class="form-row">
          <label>DB Password</label>
          <input type="password" id="db_pass" placeholder="••••••••••">
        </div>
      </div>

      <div id="db-test-result" class="test-result"></div>

      <div class="actions">
        <button class="btn btn-test" onclick="testDB()">🔌 Test Connection</button>
        <button class="btn btn-primary" onclick="goStep(2)">Next: App Settings →</button>
      </div>
    </div>
  </div>

  <!-- ==================== STEP 2: App & AI ==================== -->
  <div id="step-2" style="display:none">
    <div class="card">
      <h2>⚙️ App Settings & AI</h2>
      <p class="desc">Set your site URL and OpenAI API key. The AI key is used for generating replies and content.</p>

      <div id="app-alert" class="alert"></div>

      <div class="form-row">
        <label>Your Site URL <span>(no trailing slash)</span></label>
        <input type="text" id="app_url" placeholder="https://yourdomain.com">
      </div>
      <div class="row-2">
        <div class="form-row">
          <label>App Name</label>
          <input type="text" id="app_name" value="SociAI OS" placeholder="SociAI OS">
        </div>
        <div class="form-row">
          <label>Timezone</label>
          <select id="app_timezone">
            <option value="UTC">UTC</option>
            <option value="Africa/Cairo">Africa/Cairo (Egypt)</option>
            <option value="Asia/Riyadh">Asia/Riyadh (Saudi Arabia)</option>
            <option value="Asia/Dubai">Asia/Dubai (UAE)</option>
            <option value="Europe/London">Europe/London</option>
            <option value="America/New_York">America/New_York</option>
          </select>
        </div>
      </div>

      <hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:1.5rem 0">

      <h3 style="font-size:1rem;font-weight:700;margin-bottom:.4rem">🤖 OpenAI API Key</h3>
      <p style="color:#7070a0;font-size:.82rem;margin-bottom:1rem">Get your key from <strong style="color:#a89cff">platform.openai.com → API Keys</strong></p>

      <div class="form-row">
        <label>OpenAI API Key</label>
        <input type="password" id="openai_key" placeholder="sk-proj-...">
      </div>
      <div class="form-row">
        <label>Model <span>(GPT-4o recommended)</span></label>
        <select id="openai_model">
          <option value="gpt-4o">GPT-4o (recommended)</option>
          <option value="gpt-4o-mini">GPT-4o Mini (cheaper)</option>
          <option value="gpt-4-turbo">GPT-4 Turbo</option>
        </select>
      </div>

      <div id="ai-test-result" class="test-result"></div>

      <div class="actions">
        <button class="btn btn-secondary" onclick="goStep(1)">← Back</button>
        <div style="display:flex;gap:.75rem">
          <button class="btn btn-test" onclick="testOpenAI()">🧠 Test OpenAI</button>
          <button class="btn btn-primary" onclick="goStep(3)">Next: Platforms →</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== STEP 3: Platforms ==================== -->
  <div id="step-3" style="display:none">
    <div class="card">
      <h2>🔗 Social Media Platforms</h2>
      <p class="desc">Enter your OAuth app credentials for each platform. You only need to fill the platforms you want to use. All fields are optional — you can add them later from Settings.</p>

      <!-- Meta -->
      <div class="platform-card" style="margin-bottom:1rem">
        <h4>📘 Meta (Facebook + Instagram) <span class="badge badge-required">Recommended</span></h4>
        <p style="font-size:.78rem;color:#7070a0;margin-bottom:.85rem">
          Go to <strong style="color:#a89cff">developers.facebook.com → My Apps → Create App</strong><br>
          Add products: Facebook Login + Instagram Graph API<br>
          In App Settings → Basic: copy App ID and App Secret
        </p>
        <div class="row-2">
          <div class="form-row">
            <label>Meta App ID</label>
            <input type="text" id="meta_app_id" placeholder="1234567890">
          </div>
          <div class="form-row">
            <label>Meta App Secret</label>
            <input type="password" id="meta_app_secret" placeholder="abc123...">
          </div>
        </div>
        <div class="tip">📋 <strong>Redirect URI to add in your Meta App:</strong><br>
          <code id="meta_callback_url">https://yourdomain.com/oauth/facebook/callback</code><br>
          <code id="ig_callback_url">https://yourdomain.com/oauth/instagram/callback</code>
        </div>
      </div>

      <!-- Twitter -->
      <div class="platform-card" style="margin-bottom:1rem">
        <h4>🐦 Twitter / X <span class="badge badge-optional">Optional</span></h4>
        <p style="font-size:.78rem;color:#7070a0;margin-bottom:.85rem">
          Go to <strong style="color:#a89cff">developer.twitter.com → Projects & Apps → New App</strong><br>
          Enable OAuth 2.0, set permissions to: Read + Write + Direct Messages
        </p>
        <div class="row-2">
          <div class="form-row">
            <label>Client ID</label>
            <input type="text" id="twitter_client_id" placeholder="xxxxxxxxxxxxxxxxxx">
          </div>
          <div class="form-row">
            <label>Client Secret</label>
            <input type="password" id="twitter_client_secret" placeholder="xxxxxxxxxx">
          </div>
        </div>
        <div class="tip">📋 <strong>Redirect URI:</strong><br>
          <code id="twitter_callback_url">https://yourdomain.com/oauth/twitter/callback</code>
        </div>
      </div>

      <!-- LinkedIn -->
      <div class="platform-card" style="margin-bottom:1rem">
        <h4>💼 LinkedIn <span class="badge badge-optional">Optional</span></h4>
        <p style="font-size:.78rem;color:#7070a0;margin-bottom:.85rem">
          Go to <strong style="color:#a89cff">linkedin.com/developers → Create App</strong><br>
          Add products: Sign In with LinkedIn + Share on LinkedIn + Community Management API
        </p>
        <div class="row-2">
          <div class="form-row">
            <label>Client ID</label>
            <input type="text" id="linkedin_client_id" placeholder="77xxxxxxxx">
          </div>
          <div class="form-row">
            <label>Client Secret</label>
            <input type="password" id="linkedin_client_secret" placeholder="xxxxxxxxxx">
          </div>
        </div>
        <div class="tip">📋 <strong>Redirect URI:</strong><br>
          <code id="linkedin_callback_url">https://yourdomain.com/oauth/linkedin/callback</code>
        </div>
      </div>

      <!-- TikTok -->
      <div class="platform-card" style="margin-bottom:1rem">
        <h4>🎵 TikTok <span class="badge badge-optional">Optional</span></h4>
        <p style="font-size:.78rem;color:#7070a0;margin-bottom:.85rem">
          Go to <strong style="color:#a89cff">developers.tiktok.com → Manage Apps → Create app</strong><br>
          Add products: Login Kit + Content Posting API + Comment API
        </p>
        <div class="row-2">
          <div class="form-row">
            <label>Client Key</label>
            <input type="text" id="tiktok_client_key" placeholder="awxxxxxxxxxx">
          </div>
          <div class="form-row">
            <label>Client Secret</label>
            <input type="password" id="tiktok_client_secret" placeholder="xxxxxxxxxx">
          </div>
        </div>
        <div class="tip">📋 <strong>Redirect URI:</strong><br>
          <code id="tiktok_callback_url">https://yourdomain.com/oauth/tiktok/callback</code>
        </div>
      </div>

      <!-- YouTube -->
      <div class="platform-card" style="margin-bottom:1rem">
        <h4>▶️ YouTube <span class="badge badge-optional">Optional</span></h4>
        <p style="font-size:.78rem;color:#7070a0;margin-bottom:.85rem">
          Go to <strong style="color:#a89cff">console.cloud.google.com → APIs & Services → Credentials → Create OAuth client</strong><br>
          Enable: YouTube Data API v3. Choose "Web application" type.
        </p>
        <div class="row-2">
          <div class="form-row">
            <label>Google Client ID</label>
            <input type="text" id="google_client_id" placeholder="xxxx.apps.googleusercontent.com">
          </div>
          <div class="form-row">
            <label>Google Client Secret</label>
            <input type="password" id="google_client_secret" placeholder="GOCSPX-...">
          </div>
        </div>
        <div class="tip">📋 <strong>Redirect URI:</strong><br>
          <code id="youtube_callback_url">https://yourdomain.com/oauth/youtube/callback</code>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-secondary" onclick="goStep(2)">← Back</button>
        <button class="btn btn-primary" onclick="saveAndFinish()">💾 Save & Finish Setup →</button>
      </div>
    </div>
  </div>

  <!-- ==================== STEP 4: Done ==================== -->
  <div id="step-4" style="display:none">
    <div class="card" style="text-align:center;padding:2.5rem">
      <div style="font-size:3.5rem;margin-bottom:1rem">🎉</div>
      <h2 style="font-size:1.5rem;margin-bottom:.5rem">Setup Complete!</h2>
      <p style="color:#7070a0;margin-bottom:2rem">Your SociAI OS platform is ready. Here's what to do next:</p>
    </div>

    <div class="card">
      <h2>📋 Setup Checklist</h2>
      <ul class="checklist" id="checklist"></ul>
    </div>

    <div class="card">
      <h2>⏰ Cron Jobs — Add these in Plesk</h2>
      <p style="color:#7070a0;font-size:.85rem;margin-bottom:1rem">In Plesk: <strong style="color:#a89cff">Websites & Domains → Scheduled Tasks → Add Task</strong></p>
      <div class="cron-box" id="cron-box"></div>
    </div>

    <div style="text-align:center;margin-top:1.5rem">
      <a href="/dashboard" class="btn btn-primary" style="font-size:1rem;padding:.8rem 2.5rem">🚀 Open Dashboard</a>
    </div>
  </div>

</div>

<script>
let config = {};
let currentStep = 1;

// Update redirect URIs when URL is typed
document.getElementById('app_url')?.addEventListener('input', function() {
  updateCallbackUrls(this.value);
});

function updateCallbackUrls(url) {
  url = url.replace(/\/$/, '');
  const ids = ['meta','twitter','linkedin','tiktok','youtube'];
  const platforms = ['facebook','twitter','linkedin','tiktok','youtube'];
  ids.forEach((id, i) => {
    const el = document.getElementById(id + '_callback_url');
    if (el) el.textContent = url + '/oauth/' + platforms[i] + '/callback';
  });
  const ig = document.getElementById('ig_callback_url');
  if (ig) ig.textContent = url + '/oauth/instagram/callback';
}

function goStep(n) {
  document.getElementById('step-' + currentStep).style.display = 'none';
  document.querySelectorAll('.step').forEach((s, i) => {
    if (i + 1 < n) s.classList.add('done'), s.classList.remove('active');
    else if (i + 1 === n) s.classList.add('active'), s.classList.remove('done');
    else s.classList.remove('active', 'done');
  });
  document.getElementById('step-' + n).style.display = 'block';
  document.getElementById('progressBar').style.width = (n * 25) + '%';
  currentStep = n;
  window.scrollTo(0, 0);
}

function showAlert(id, type, msg) {
  const el = document.getElementById(id);
  el.className = 'alert alert-' + type + ' show';
  el.textContent = msg;
}

async function testDB() {
  const btn = event.target;
  btn.disabled = true; btn.textContent = 'Testing...';
  const res = document.getElementById('db-test-result');
  res.className = 'test-result';

  try {
    const r = await fetch('/setup/test-db', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        db_host: document.getElementById('db_host').value,
        db_port: document.getElementById('db_port').value,
        db_name: document.getElementById('db_name').value,
        db_user: document.getElementById('db_user').value,
        db_pass: document.getElementById('db_pass').value,
      })
    });
    const data = await r.json();
    res.className = 'test-result show ' + (data.success ? 'test-ok' : 'test-fail');
    res.textContent = data.success ? '✓ ' + data.message : '✕ ' + data.message;
  } catch(e) {
    res.className = 'test-result show test-fail';
    res.textContent = '✕ Connection failed: ' + e.message;
  }
  btn.disabled = false; btn.textContent = '🔌 Test Connection';
}

async function testOpenAI() {
  const btn = event.target;
  btn.disabled = true; btn.textContent = 'Testing...';
  const res = document.getElementById('ai-test-result');

  try {
    const r = await fetch('/setup/test-openai', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ openai_api_key: document.getElementById('openai_key').value })
    });
    const data = await r.json();
    res.className = 'test-result show ' + (data.success ? 'test-ok' : 'test-fail');
    res.textContent = data.success ? '✓ OpenAI connected: ' + data.message : '✕ ' + data.message;
  } catch(e) {
    res.className = 'test-result show test-fail';
    res.textContent = '✕ Test failed: ' + e.message;
  }
  btn.disabled = false; btn.textContent = '🧠 Test OpenAI';
}

async function saveAndFinish() {
  const btn = event.target;
  btn.disabled = true; btn.textContent = '💾 Saving...';

  config = {
    db_host:    document.getElementById('db_host').value,
    db_port:    document.getElementById('db_port').value,
    db_name:    document.getElementById('db_name').value,
    db_user:    document.getElementById('db_user').value,
    db_pass:    document.getElementById('db_pass').value,
    app_url:    document.getElementById('app_url').value,
    app_name:   document.getElementById('app_name').value,
    timezone: document.getElementById('app_timezone').value,
    openai_api_key: document.getElementById('openai_key').value,
    openai_model: document.getElementById('openai_model').value,
    meta_app_id:      document.getElementById('meta_app_id').value,
    meta_app_secret:  document.getElementById('meta_app_secret').value,
    twitter_client_id:     document.getElementById('twitter_client_id').value,
    twitter_client_secret: document.getElementById('twitter_client_secret').value,
    linkedin_client_id:     document.getElementById('linkedin_client_id').value,
    linkedin_client_secret: document.getElementById('linkedin_client_secret').value,
    tiktok_client_id:     document.getElementById('tiktok_client_key').value,
    tiktok_client_key:    document.getElementById('tiktok_client_key').value,
    tiktok_client_secret: document.getElementById('tiktok_client_secret').value,
    google_client_id:     document.getElementById('google_client_id').value,
    google_client_secret: document.getElementById('google_client_secret').value,
  };

  try {
    const r = await fetch('/setup/save', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(config)
    });
    const data = await r.json();
    if (data.success) {
      buildChecklist();
      goStep(4);
    } else {
      alert('Save failed: ' + data.message);
    }
  } catch(e) {
    alert('Save failed: ' + e.message);
  }
  btn.disabled = false; btn.textContent = '💾 Save & Finish Setup →';
}

function buildChecklist() {
  const url = config.app_url || 'https://yourdomain.com';
  const phpPath = '/var/www/vhosts/' + url.replace(/https?:\/\//, '') + '/httpdocs';

  const items = [
    { ok: !!config.db_name, label: 'Database configured', warn: false },
    { ok: !!config.openai_api_key, label: 'OpenAI API key set', warn: false },
    { ok: !!config.meta_app_id, label: 'Meta (Facebook/Instagram) app configured', warn: true },
    { ok: !!config.twitter_client_id, label: 'Twitter/X app configured', warn: true },
    { ok: !!config.linkedin_client_id, label: 'LinkedIn app configured', warn: true },
    { ok: !!config.tiktok_client_key, label: 'TikTok app configured', warn: true },
    { ok: !!config.google_client_id, label: 'YouTube/Google app configured', warn: true },
    { ok: true, label: 'Set up Cron Jobs in Plesk (see below)', warn: !true },
  ];

  const ul = document.getElementById('checklist');
  ul.innerHTML = items.map(item => {
    const cls = item.ok ? 'check-ok' : (item.warn ? 'check-warn' : 'check-miss');
    const icon = item.ok ? '✓' : (item.warn ? '!' : '✕');
    const note = item.ok ? '' : (item.warn ? ' <span style="color:#7070a0;font-size:.78rem">(optional — add later from Settings)</span>' : ' <span style="color:#EF4444;font-size:.78rem">(required)</span>');
    return `<li><span class="check-icon ${cls}">${icon}</span>${item.label}${note}</li>`;
  }).join('');

  document.getElementById('cron-box').innerHTML =
    `# Every 5 minutes — sync comments & messages from all platforms\n` +
    `*/5 * * * * /usr/bin/php ${phpPath}/cron/sync_interactions.php >> /tmp/sociai_sync.log 2>&1\n\n` +
    `# Every 10 minutes — generate AI replies for pending interactions\n` +
    `*/10 * * * * /usr/bin/php ${phpPath}/cron/generate_ai_replies.php >> /tmp/sociai_ai.log 2>&1`;
}

// Init
goStep(1);
</script>
</body>
</html>
