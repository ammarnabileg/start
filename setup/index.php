<?php
declare(strict_types=1);

// If already installed, redirect to root
if (file_exists(dirname(__DIR__) . '/.installed')) {
    header('Location: /');
    exit;
}

// Detect RTL language
$rtlLangs = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug'];
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
$isRTL = in_array($browserLang, $rtlLangs, true);
$dir = $isRTL ? 'rtl' : 'ltr';
$textAlign = $isRTL ? 'right' : 'left';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($browserLang) ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Recruitment Platform — Setup Wizard</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --primary: #4f46e5;
    --primary-dark: #3730a3;
    --primary-light: #818cf8;
    --accent: #7c3aed;
    --accent-light: #a78bfa;
    --bg: #0f0e17;
    --bg2: #1a1827;
    --bg3: #231f35;
    --card: #1e1b2e;
    --border: #2d2a45;
    --text: #e8e6f0;
    --text-muted: #9790b8;
    --success: #10b981;
    --error: #ef4444;
    --warning: #f59e0b;
    --white: #ffffff;
    --radius: 12px;
    --radius-sm: 8px;
    --shadow: 0 25px 60px rgba(0,0,0,0.5);
    --shadow-glow: 0 0 40px rgba(79,70,229,0.3);
  }

  html, body {
    min-height: 100vh;
    background: var(--bg);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: var(--text);
    font-size: 15px;
    line-height: 1.6;
  }

  body {
    background-image:
      radial-gradient(ellipse 80% 50% at 50% -20%, rgba(79,70,229,0.25) 0%, transparent 60%),
      radial-gradient(ellipse 50% 40% at 80% 80%, rgba(124,58,237,0.15) 0%, transparent 60%);
    background-attachment: fixed;
  }

  .page-wrap {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
  }

  /* === HEADER === */
  .wizard-header { text-align: center; margin-bottom: 28px; }
  .logo-wrap {
    display: inline-flex; align-items: center; gap: 12px; margin-bottom: 6px;
  }
  .logo-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 20px rgba(79,70,229,0.5);
  }
  .logo-text {
    font-size: 22px; font-weight: 700;
    background: linear-gradient(135deg, #c7d2fe, #a78bfa);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  }
  .wizard-subtitle { color: var(--text-muted); font-size: 13px; }

  /* === STEPPER === */
  .stepper {
    display: flex; align-items: center; justify-content: center;
    flex-wrap: wrap; gap: 4px; margin-bottom: 28px;
  }
  .step-item { display: flex; align-items: center; gap: 4px; }
  .step-dot {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--bg3); border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 600; color: var(--text-muted);
    transition: all 0.3s ease; position: relative;
  }
  .step-dot.active {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-color: var(--primary-light); color: #fff;
    box-shadow: 0 0 20px rgba(79,70,229,0.5); transform: scale(1.1);
  }
  .step-dot.done { background: var(--success); border-color: var(--success); color: #fff; }
  .step-dot.done::after { content: '\2713'; position: absolute; }
  .step-dot.done span { display: none; }
  .step-label { font-size: 11px; color: var(--text-muted); white-space: nowrap; display: none; }
  .step-connector { width: 32px; height: 2px; background: var(--border); transition: background 0.3s; }
  .step-connector.done { background: var(--success); }

  @media (min-width: 600px) {
    .step-connector { width: 44px; }
    .step-label { display: block; }
    .step-item { flex-direction: column; align-items: center; gap: 5px; }
  }

  /* === CARD === */
  .wizard-card {
    width: 100%; max-width: 520px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 20px; box-shadow: var(--shadow), var(--shadow-glow);
    overflow: hidden; position: relative;
  }
  .wizard-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--accent), var(--primary-light));
  }
  .card-body { padding: 36px 36px 24px; }
  @media (max-width: 480px) { .card-body { padding: 24px 20px 18px; } }

  /* === STEPS === */
  .step-pane { display: none; animation: fadeIn 0.3s ease; }
  .step-pane.active { display: block; }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* === WELCOME === */
  .welcome-icon {
    width: 80px; height: 80px;
    background: linear-gradient(135deg, rgba(79,70,229,0.2), rgba(124,58,237,0.2));
    border: 1px solid rgba(79,70,229,0.4); border-radius: 24px;
    display: flex; align-items: center; justify-content: center; font-size: 36px;
    margin: 0 auto 22px; box-shadow: 0 8px 32px rgba(79,70,229,0.3);
  }
  .step-title {
    font-size: 22px; font-weight: 700; margin-bottom: 8px;
    background: linear-gradient(135deg, #e8e6f0, #c7d2fe);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    text-align: center;
  }
  .step-desc { color: var(--text-muted); font-size: 14px; text-align: center; margin-bottom: 24px; }
  .feature-list { list-style: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 28px; }
  .feature-list li {
    display: flex; align-items: center; gap: 10px;
    font-size: 13px; color: var(--text-muted);
  }
  .feature-icon {
    width: 28px; height: 28px; flex-shrink: 0;
    background: rgba(79,70,229,0.15); border: 1px solid rgba(79,70,229,0.3);
    border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;
  }

  /* === FORM === */
  .form-section-title { font-size: 17px; font-weight: 600; margin-bottom: 4px; color: var(--text); }
  .form-section-desc  { font-size: 13px; color: var(--text-muted); margin-bottom: 22px; }
  .form-group { margin-bottom: 14px; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 420px) { .form-row { grid-template-columns: 1fr; } }

  label {
    display: block; font-size: 12px; font-weight: 600;
    color: var(--text-muted); margin-bottom: 5px;
    text-transform: uppercase; letter-spacing: 0.5px;
    text-align: <?= $textAlign ?>;
  }
  input[type="text"], input[type="email"], input[type="password"],
  input[type="number"], input[type="url"] {
    width: 100%; background: var(--bg3); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 10px 14px;
    color: var(--text); font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s; outline: none; direction: ltr;
  }
  input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.2); }
  input.error   { border-color: var(--error); }
  input.success { border-color: var(--success); }
  .field-error {
    font-size: 11px; color: var(--error); margin-top: 4px;
    display: none; text-align: <?= $textAlign ?>;
  }
  .field-error.show { display: block; }

  /* === BUTTONS === */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 11px 24px; border: none; border-radius: var(--radius-sm);
    font-size: 14px; font-weight: 600; cursor: pointer;
    transition: all 0.2s ease; text-decoration: none; white-space: nowrap;
  }
  .btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: #fff; box-shadow: 0 4px 16px rgba(79,70,229,0.4);
  }
  .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(79,70,229,0.55); }
  .btn-primary:active { transform: translateY(0); }
  .btn-secondary { background: var(--bg3); color: var(--text-muted); border: 1px solid var(--border); }
  .btn-secondary:hover { background: var(--border); color: var(--text); }
  .btn-outline { background: transparent; color: var(--primary-light); border: 1px solid var(--primary); }
  .btn-outline:hover { background: rgba(79,70,229,0.1); }
  .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
  .btn-full { width: 100%; }
  .btn-sm { padding: 8px 16px; font-size: 13px; }

  .card-footer {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 18px 36px 26px; border-top: 1px solid var(--border);
  }
  @media (max-width: 480px) { .card-footer { padding: 14px 20px 22px; } }
  .card-footer-single { justify-content: flex-end; }

  /* === ALERTS === */
  .alert {
    display: flex; gap: 10px; padding: 12px 16px;
    border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 14px;
    text-align: <?= $textAlign ?>;
  }
  .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
  .alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  color: #fca5a5; }
  .alert-info    { background: rgba(79,70,229,0.1);  border: 1px solid rgba(79,70,229,0.3);  color: #a5b4fc; }
  .alert-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }

  /* === PROGRESS === */
  .progress-wrap { margin: 20px 0; }
  .progress-label {
    display: flex; justify-content: space-between;
    font-size: 13px; color: var(--text-muted); margin-bottom: 6px;
  }
  .progress-bar-outer { height: 8px; background: var(--bg3); border-radius: 100px; overflow: hidden; }
  .progress-bar-inner {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    border-radius: 100px; width: 0%; transition: width 0.4s ease;
  }
  .install-log {
    background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm);
    padding: 12px 14px; font-size: 12px; font-family: 'Courier New', monospace;
    color: var(--text-muted); max-height: 160px; overflow-y: auto; direction: ltr; text-align: left;
  }
  .log-entry { margin: 2px 0; }
  .log-ok   { color: #6ee7b7; }
  .log-err  { color: #fca5a5; }
  .log-info { color: #a5b4fc; }

  /* === SUCCESS === */
  .success-icon {
    width: 80px; height: 80px;
    background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 38px; margin: 0 auto 22px;
    animation: popIn 0.5s cubic-bezier(0.175,0.885,0.32,1.275);
  }
  @keyframes popIn {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
  }
  .success-details {
    background: var(--bg3); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 14px; margin-bottom: 18px; font-size: 13px;
  }
  .success-row {
    display: flex; justify-content: space-between; padding: 7px 0;
    border-bottom: 1px solid var(--border);
  }
  .success-row:last-child { border-bottom: none; }
  .success-row .lbl { color: var(--text-muted); }
  .success-row .val { color: var(--text); font-weight: 500; word-break: break-all; }

  /* === PASSWORD STRENGTH === */
  .pw-strength { margin-top: 5px; height: 4px; background: var(--bg3); border-radius: 100px; overflow: hidden; }
  .pw-bar { height: 100%; border-radius: 100px; width: 0%; transition: width 0.3s, background 0.3s; }
  .pw-hint { font-size: 11px; color: var(--text-muted); margin-top: 3px; text-align: <?= $textAlign ?>; }

  /* === SPINNER === */
  .spinner {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite; display: inline-block;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* === SYSTEM CHECKS === */
  .check-list { list-style: none; display: flex; flex-direction: column; gap: 7px; margin-bottom: 18px; }
  .check-item {
    display: flex; align-items: center; gap: 10px; font-size: 13px;
    padding: 9px 12px; background: var(--bg3); border: 1px solid var(--border);
    border-radius: var(--radius-sm);
  }
  .check-status {
    width: 20px; height: 20px; flex-shrink: 0; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;
  }
  .check-status.pass { background: rgba(16,185,129,0.2); color: var(--success); }
  .check-status.fail { background: rgba(239,68,68,0.2);  color: var(--error); }
</style>
</head>
<body>
<div class="page-wrap">

  <!-- Header -->
  <div class="wizard-header">
    <div class="logo-wrap">
      <div class="logo-icon">&#x1F916;</div>
      <div class="logo-text">AI Recruit</div>
    </div>
    <div class="wizard-subtitle">Setup Wizard &mdash; v1.0.0</div>
  </div>

  <!-- Stepper -->
  <div class="stepper" id="stepper">
    <div class="step-item">
      <div class="step-dot active" id="dot-1"><span>1</span></div>
      <div class="step-label">Welcome</div>
    </div>
    <div class="step-connector" id="conn-1"></div>
    <div class="step-item">
      <div class="step-dot" id="dot-2"><span>2</span></div>
      <div class="step-label">Database</div>
    </div>
    <div class="step-connector" id="conn-2"></div>
    <div class="step-item">
      <div class="step-dot" id="dot-3"><span>3</span></div>
      <div class="step-label">Admin</div>
    </div>
    <div class="step-connector" id="conn-3"></div>
    <div class="step-item">
      <div class="step-dot" id="dot-4"><span>4</span></div>
      <div class="step-label">Company</div>
    </div>
    <div class="step-connector" id="conn-4"></div>
    <div class="step-item">
      <div class="step-dot" id="dot-5"><span>5</span></div>
      <div class="step-label">Install</div>
    </div>
    <div class="step-connector" id="conn-5"></div>
    <div class="step-item">
      <div class="step-dot" id="dot-6"><span>6</span></div>
      <div class="step-label">Done</div>
    </div>
  </div>

  <!-- Wizard Card -->
  <div class="wizard-card">

    <!-- ══════ STEP 1: Welcome ══════ -->
    <div class="step-pane active" id="step-1">
      <div class="card-body">
        <div class="welcome-icon">&#x1F680;</div>
        <h1 class="step-title">Welcome to AI Recruit</h1>
        <p class="step-desc">Your intelligent recruitment platform. This wizard will guide you through the complete setup in just a few minutes.</p>

        <?php
        $checks = [
          ['PHP 8.1+',         PHP_VERSION_ID >= 80100, PHP_VERSION],
          ['PDO MySQL',        extension_loaded('pdo_mysql'), 'ext-pdo_mysql'],
          ['JSON',             extension_loaded('json'),    'ext-json'],
          ['OpenSSL',          extension_loaded('openssl'), 'ext-openssl'],
          ['Writable root',    is_writable(dirname(__DIR__)), dirname(__DIR__)],
        ];
        $allPass = true;
        foreach ($checks as $c) { if (!$c[1]) { $allPass = false; break; } }
        ?>

        <ul class="check-list">
          <?php foreach ($checks as [$label, $pass, $detail]): ?>
          <li class="check-item">
            <div class="check-status <?= $pass ? 'pass' : 'fail' ?>"><?= $pass ? '&#x2713;' : '&#x2717;' ?></div>
            <div style="flex:1">
              <span style="font-weight:600;color:var(--text)"><?= htmlspecialchars($label) ?></span>
              <span style="color:var(--text-muted);font-size:11px;margin-left:8px"><?= htmlspecialchars((string)$detail) ?></span>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php if (!$allPass): ?>
        <div class="alert alert-error">
          <span class="alert-icon">&#x26A0;&#xFE0F;</span>
          <div>Please resolve the failing requirements above before proceeding.</div>
        </div>
        <?php endif; ?>

        <ul class="feature-list">
          <li><div class="feature-icon">&#x1F5C4;&#xFE0F;</div> Automatic database creation &amp; schema setup</li>
          <li><div class="feature-icon">&#x1F510;</div> Secure super-admin account with role permissions</li>
          <li><div class="feature-icon">&#x1F3E2;</div> Multi-tenant company configuration</li>
          <li><div class="feature-icon">&#x1F916;</div> AI-powered candidate matching ready to go</li>
        </ul>
      </div>
      <div class="card-footer card-footer-single">
        <button class="btn btn-primary" onclick="goStep(2)" <?= !$allPass ? 'disabled' : '' ?>>
          Start Setup <span>&#x2192;</span>
        </button>
      </div>
    </div>

    <!-- ══════ STEP 2: Database ══════ -->
    <div class="step-pane" id="step-2">
      <div class="card-body">
        <div class="form-section-title">&#x1F5C4;&#xFE0F; Database Configuration</div>
        <div class="form-section-desc">Enter your MySQL connection details. The database will be created automatically if it does not exist.</div>

        <div id="db-test-result"></div>

        <div class="form-row">
          <div class="form-group">
            <label for="db_host">Host</label>
            <input type="text" id="db_host" name="db_host" value="127.0.0.1" placeholder="127.0.0.1">
            <div class="field-error" id="err-db_host">Host is required</div>
          </div>
          <div class="form-group">
            <label for="db_port">Port</label>
            <input type="number" id="db_port" name="db_port" value="3306" placeholder="3306">
            <div class="field-error" id="err-db_port">Port is required</div>
          </div>
        </div>

        <div class="form-group">
          <label for="db_name">Database Name</label>
          <input type="text" id="db_name" name="db_name" value="ai_recruitment" placeholder="ai_recruitment">
          <div class="field-error" id="err-db_name">Database name is required</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="db_user">Username</label>
            <input type="text" id="db_user" name="db_user" value="" placeholder="root">
            <div class="field-error" id="err-db_user">Username is required</div>
          </div>
          <div class="form-group">
            <label for="db_pass">Password</label>
            <input type="password" id="db_pass" name="db_pass" value="" placeholder="Leave blank if none">
            <div class="field-error" id="err-db_pass"></div>
          </div>
        </div>

        <button class="btn btn-outline btn-sm" id="btn-test-db" onclick="testDbConnection()">
          &#x1F50D; Test Connection
        </button>
      </div>
      <div class="card-footer">
        <button class="btn btn-secondary" onclick="goStep(1)">&#x2190; Back</button>
        <button class="btn btn-primary" onclick="validateAndNext(2)">Next &#x2192;</button>
      </div>
    </div>

    <!-- ══════ STEP 3: Admin Account ══════ -->
    <div class="step-pane" id="step-3">
      <div class="card-body">
        <div class="form-section-title">&#x1F510; Admin Account</div>
        <div class="form-section-desc">Create the super administrator account for this platform.</div>

        <div class="form-row">
          <div class="form-group">
            <label for="admin_first">First Name</label>
            <input type="text" id="admin_first" name="admin_first" placeholder="John">
            <div class="field-error" id="err-admin_first">First name is required</div>
          </div>
          <div class="form-group">
            <label for="admin_last">Last Name</label>
            <input type="text" id="admin_last" name="admin_last" placeholder="Doe">
            <div class="field-error" id="err-admin_last">Last name is required</div>
          </div>
        </div>

        <div class="form-group">
          <label for="admin_email">Email Address</label>
          <input type="email" id="admin_email" name="admin_email" placeholder="admin@company.com">
          <div class="field-error" id="err-admin_email">Valid email address is required</div>
        </div>

        <div class="form-group">
          <label for="admin_pass">Password</label>
          <input type="password" id="admin_pass" name="admin_pass" placeholder="Min. 8 characters" oninput="checkPwStrength(this.value)">
          <div class="pw-strength"><div class="pw-bar" id="pw-bar"></div></div>
          <div class="pw-hint" id="pw-hint">Enter a strong password</div>
          <div class="field-error" id="err-admin_pass">Minimum 8 characters required</div>
        </div>

        <div class="form-group">
          <label for="admin_pass2">Confirm Password</label>
          <input type="password" id="admin_pass2" name="admin_pass2" placeholder="Repeat password">
          <div class="field-error" id="err-admin_pass2">Passwords do not match</div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-secondary" onclick="goStep(2)">&#x2190; Back</button>
        <button class="btn btn-primary" onclick="validateAndNext(3)">Next &#x2192;</button>
      </div>
    </div>

    <!-- ══════ STEP 4: Company Info ══════ -->
    <div class="step-pane" id="step-4">
      <div class="card-body">
        <div class="form-section-title">&#x1F3E2; Company Information</div>
        <div class="form-section-desc">Configure your first company tenant on this platform.</div>

        <div class="form-group">
          <label for="company_name">Company Name</label>
          <input type="text" id="company_name" name="company_name" placeholder="Acme Corp" oninput="autoSlug(this.value)">
          <div class="field-error" id="err-company_name">Company name is required</div>
        </div>

        <div class="form-group">
          <label for="company_slug">Slug <span style="font-weight:400;text-transform:none;letter-spacing:0">(URL identifier)</span></label>
          <input type="text" id="company_slug" name="company_slug" placeholder="acme-corp">
          <div class="field-error" id="err-company_slug">Only lowercase letters, numbers and hyphens</div>
        </div>

        <div class="form-group">
          <label for="company_domain">Domain <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
          <input type="text" id="company_domain" name="company_domain" placeholder="app.acme.com">
          <div class="field-error" id="err-company_domain">Invalid domain format</div>
        </div>

        <div class="form-group">
          <label for="app_url">Application URL</label>
          <input type="url" id="app_url" name="app_url" placeholder="https://app.acme.com">
          <div class="field-error" id="err-app_url">Valid URL required (https://...)</div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-secondary" onclick="goStep(3)">&#x2190; Back</button>
        <button class="btn btn-primary" onclick="validateAndNext(4)">Next &#x2192;</button>
      </div>
    </div>

    <!-- ══════ STEP 5: Installing ══════ -->
    <div class="step-pane" id="step-5">
      <div class="card-body">
        <div style="text-align:center;margin-bottom:22px">
          <div id="install-spinner" style="font-size:48px;margin-bottom:12px">&#x2699;&#xFE0F;</div>
          <div class="form-section-title" id="install-title" style="text-align:center">Installing Platform</div>
          <div class="form-section-desc" id="install-subtitle">Please wait while we set everything up&hellip;</div>
        </div>

        <div class="progress-wrap">
          <div class="progress-label">
            <span id="progress-label-text">Initializing&hellip;</span>
            <span id="progress-pct">0%</span>
          </div>
          <div class="progress-bar-outer">
            <div class="progress-bar-inner" id="progress-bar"></div>
          </div>
        </div>

        <div class="install-log" id="install-log">
          <div class="log-entry log-info">[ INFO ] Setup process starting...</div>
        </div>

        <div id="install-error" style="display:none;margin-top:14px"></div>
      </div>
      <div class="card-footer" id="step5-footer" style="display:none">
        <button class="btn btn-secondary" onclick="goStep(4)">&#x2190; Back</button>
        <button class="btn btn-primary" id="btn-retry" onclick="runInstall()">Retry</button>
      </div>
    </div>

    <!-- ══════ STEP 6: Success ══════ -->
    <div class="step-pane" id="step-6">
      <div class="card-body">
        <div class="success-icon">&#x2705;</div>
        <h2 class="step-title">Setup Complete!</h2>
        <p class="step-desc">Your AI Recruitment Platform is fully installed and ready to use.</p>

        <div class="success-details" id="success-details"></div>

        <div class="alert alert-info">
          <span class="alert-icon">&#x1F4A1;</span>
          <div>For security, please delete the <code style="background:rgba(255,255,255,0.1);padding:1px 5px;border-radius:4px">setup/</code> directory from your server after logging in.</div>
        </div>
      </div>
      <div class="card-footer card-footer-single">
        <a class="btn btn-primary" href="/" id="btn-dashboard">Go to Dashboard &#x2192;</a>
      </div>
    </div>

  </div><!-- /.wizard-card -->
</div><!-- /.page-wrap -->

<script>
// ─── State ────────────────────────────────────────────────────────────────────
var currentStep = 1;

// ─── Step navigation ──────────────────────────────────────────────────────────
function goStep(n) {
  document.getElementById('step-' + currentStep).classList.remove('active');
  document.getElementById('dot-' + currentStep).classList.remove('active');

  if (n > currentStep) {
    document.getElementById('dot-' + currentStep).classList.add('done');
    for (var i = currentStep; i < n; i++) {
      var conn = document.getElementById('conn-' + i);
      if (conn) conn.classList.add('done');
    }
  } else {
    for (var j = n; j <= currentStep; j++) {
      document.getElementById('dot-' + j).classList.remove('done');
      var c2 = document.getElementById('conn-' + j);
      if (c2) c2.classList.remove('done');
    }
  }

  currentStep = n;
  document.getElementById('step-' + n).classList.add('active');
  document.getElementById('dot-' + n).classList.add('active');

  if (n === 5) runInstall();
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function v(id) {
  var el = document.getElementById(id);
  return el ? el.value.trim() : '';
}

function showError(id, msg) {
  var el = document.getElementById(id);
  if (el) el.classList.add('error');
  var err = document.getElementById('err-' + id);
  if (err) { if (msg) err.textContent = msg; err.classList.add('show'); }
}

function clearErrors(fields) {
  fields.forEach(function(id) {
    var el = document.getElementById(id);
    if (el) { el.classList.remove('error', 'success'); }
    var err = document.getElementById('err-' + id);
    if (err) err.classList.remove('show');
  });
}

// ─── Validation per step ─────────────────────────────────────────────────────
function validateAndNext(step) {
  var valid = true;

  if (step === 2) {
    clearErrors(['db_host','db_port','db_name','db_user']);
    if (!v('db_host')) { showError('db_host', 'Host is required'); valid = false; }
    if (!v('db_port')) { showError('db_port', 'Port is required'); valid = false; }
    if (!v('db_name')) { showError('db_name', 'Database name is required'); valid = false; }
    if (!v('db_user')) { showError('db_user', 'Username is required'); valid = false; }
  }

  if (step === 3) {
    clearErrors(['admin_first','admin_last','admin_email','admin_pass','admin_pass2']);
    if (!v('admin_first')) { showError('admin_first', 'First name is required'); valid = false; }
    if (!v('admin_last'))  { showError('admin_last',  'Last name is required');  valid = false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('admin_email'))) {
      showError('admin_email', 'Valid email address is required'); valid = false;
    }
    if (v('admin_pass').length < 8) {
      showError('admin_pass', 'Minimum 8 characters required'); valid = false;
    }
    if (v('admin_pass') !== v('admin_pass2')) {
      showError('admin_pass2', 'Passwords do not match'); valid = false;
    }
  }

  if (step === 4) {
    clearErrors(['company_name','company_slug','company_domain','app_url']);
    if (!v('company_name')) { showError('company_name', 'Company name is required'); valid = false; }
    if (!v('company_slug') || !/^[a-z0-9][a-z0-9-]*[a-z0-9]$/.test(v('company_slug'))) {
      showError('company_slug', 'Only lowercase letters, numbers and hyphens'); valid = false;
    }
    var domain = v('company_domain');
    if (domain && !/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?\.[a-zA-Z]{2,}$/.test(domain)) {
      showError('company_domain', 'Invalid domain format'); valid = false;
    }
    if (!v('app_url') || !/^https?:\/\/.+/.test(v('app_url'))) {
      showError('app_url', 'Valid URL required (https://...)'); valid = false;
    }
  }

  if (valid) goStep(step + 1);
}

// ─── Auto slug ────────────────────────────────────────────────────────────────
function autoSlug(name) {
  var slug = name.toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/[\s_]+/g, '-')
    .replace(/^-+|-+$/g, '');
  document.getElementById('company_slug').value = slug;
  var urlField = document.getElementById('app_url');
  if (!urlField.value) urlField.value = window.location.origin;
}

// ─── Password strength ────────────────────────────────────────────────────────
function checkPwStrength(pw) {
  var score = 0;
  if (pw.length >= 8)            score++;
  if (pw.length >= 12)           score++;
  if (/[A-Z]/.test(pw))         score++;
  if (/[0-9]/.test(pw))         score++;
  if (/[^A-Za-z0-9]/.test(pw))  score++;

  var pcts   = [0, 20, 40, 65, 85, 100];
  var colors = ['#ef4444','#f97316','#f59e0b','#84cc16','#10b981'];
  var labels = ['Too short','Weak','Fair','Good','Strong','Very strong'];

  var bar  = document.getElementById('pw-bar');
  var hint = document.getElementById('pw-hint');
  bar.style.width      = pcts[score] + '%';
  bar.style.background = colors[Math.max(0, score - 1)] || '#ef4444';
  hint.textContent     = labels[score] || labels[0];
}

// ─── Test DB connection ───────────────────────────────────────────────────────
function testDbConnection() {
  var btn    = document.getElementById('btn-test-db');
  var result = document.getElementById('db-test-result');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Testing&hellip;';
  result.innerHTML = '';

  ajax('test_db', {
    db_host: v('db_host'), db_port: v('db_port'),
    db_name: v('db_name'), db_user: v('db_user'), db_pass: v('db_pass')
  }).then(function(data) {
    if (data.success) {
      result.innerHTML = '<div class="alert alert-success"><span class="alert-icon">&#x2705;</span><div>' + esc(data.message) + '</div></div>';
      document.getElementById('db_host').classList.add('success');
    } else {
      result.innerHTML = '<div class="alert alert-error"><span class="alert-icon">&#x274C;</span><div>' + esc(data.message) + '</div></div>';
    }
  }).catch(function(err) {
    result.innerHTML = '<div class="alert alert-error"><span class="alert-icon">&#x274C;</span><div>Request failed: ' + esc(err.message) + '</div></div>';
  }).finally(function() {
    btn.disabled = false;
    btn.innerHTML = '&#x1F50D; Test Connection';
  });
}

// ─── Installation ─────────────────────────────────────────────────────────────
function setProgress(pct, label) {
  document.getElementById('progress-bar').style.width = pct + '%';
  document.getElementById('progress-pct').textContent  = pct + '%';
  document.getElementById('progress-label-text').textContent = label;
}

function addLog(msg, type) {
  type = type || 'info';
  var log   = document.getElementById('install-log');
  var entry = document.createElement('div');
  entry.className = 'log-entry log-' + type;
  var ts = new Date().toLocaleTimeString();
  entry.textContent = '[ ' + ts + ' ] ' + msg;
  log.appendChild(entry);
  log.scrollTop = log.scrollHeight;
}

function runInstall() {
  document.getElementById('step5-footer').style.display = 'none';
  document.getElementById('install-error').style.display = 'none';
  document.getElementById('install-log').innerHTML = '<div class="log-entry log-info">[ START ] Installation beginning...</div>';
  setProgress(0, 'Initializing...');

  var payload = {
    db_host:        v('db_host'),
    db_port:        v('db_port'),
    db_name:        v('db_name'),
    db_user:        v('db_user'),
    db_pass:        v('db_pass'),
    admin_first:    v('admin_first'),
    admin_last:     v('admin_last'),
    admin_email:    v('admin_email'),
    admin_pass:     v('admin_pass'),
    company_name:   v('company_name'),
    company_slug:   v('company_slug'),
    company_domain: v('company_domain'),
    app_url:        v('app_url')
  };

  // Animated progress ticks
  var steps = [
    [10,  'Creating .env configuration...'],
    [25,  'Connecting to database...'],
    [45,  'Running database schema...'],
    [62,  'Seeding default data...'],
    [75,  'Creating roles & permissions...'],
    [85,  'Creating admin account...'],
    [93,  'Setting up company tenant...'],
  ];
  var si = 0;
  var ticker = setInterval(function() {
    if (si < steps.length) {
      setProgress(steps[si][0], steps[si][1]);
      addLog(steps[si][1], 'info');
      si++;
    }
  }, 500);

  ajax('install', payload).then(function(data) {
    clearInterval(ticker);
    if (data.success) {
      setProgress(100, 'Installation complete!');
      addLog('All tasks completed successfully.', 'ok');
      if (data.log && Array.isArray(data.log)) {
        data.log.forEach(function(l) { addLog(l.msg, l.type || 'ok'); });
      }
      document.getElementById('install-title').textContent    = 'Installation Complete!';
      document.getElementById('install-subtitle').textContent = 'Redirecting...';
      document.getElementById('install-spinner').textContent  = '🎉';
      setTimeout(function() { showSuccessStep(data); }, 900);
    } else {
      clearInterval(ticker);
      addLog('Installation failed: ' + (data.message || 'Unknown error'), 'err');
      setProgress(0, 'Failed');
      var errDiv = document.getElementById('install-error');
      errDiv.innerHTML = '<div class="alert alert-error"><span class="alert-icon">&#x274C;</span><div>' + esc(data.message || 'Unknown error') + '</div></div>';
      errDiv.style.display = 'block';
      document.getElementById('step5-footer').style.display = 'flex';
    }
  }).catch(function(err) {
    clearInterval(ticker);
    addLog('Network error: ' + err.message, 'err');
    var errDiv = document.getElementById('install-error');
    errDiv.innerHTML = '<div class="alert alert-error"><span class="alert-icon">&#x274C;</span><div>Network error: ' + esc(err.message) + '</div></div>';
    errDiv.style.display = 'block';
    document.getElementById('step5-footer').style.display = 'flex';
  });
}

function showSuccessStep(data) {
  var details = document.getElementById('success-details');
  details.innerHTML = [
    row('Admin Email',   v('admin_email')),
    row('Company',       v('company_name')),
    row('Database',      v('db_name')),
    row('App URL',       v('app_url')),
    row('Version',       '1.0.0'),
  ].join('');
  goStep(6);
}

function row(label, val) {
  return '<div class="success-row"><span class="lbl">' + esc(label) + '</span><span class="val">' + esc(val) + '</span></div>';
}

// ─── AJAX helper ──────────────────────────────────────────────────────────────
function ajax(action, data) {
  var body = new FormData();
  body.append('action', action);
  Object.keys(data).forEach(function(k) { body.append(k, data[k]); });

  return fetch('ajax.php', { method: 'POST', body: body })
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
}

function esc(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Pre-fill app URL
document.addEventListener('DOMContentLoaded', function() {
  var urlField = document.getElementById('app_url');
  if (urlField && !urlField.value) urlField.value = window.location.origin;
});
</script>
</body>
</html>
