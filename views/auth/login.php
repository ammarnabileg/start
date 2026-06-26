<?php
// login.php — rendered inside views/layouts/auth.php via renderView()
$error = flash('error');
$success = flash('success');
?>

<?php if ($error): ?>
<div class="alert-error">
    ⚠️ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert-success">
    ✓ <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<div style="margin-bottom: 28px;">
    <h1 style="font-size: 1.375rem; font-weight: 700; color: #f1f5f9; margin-bottom: 6px;">Welcome back</h1>
    <p style="font-size: 0.875rem; color: #64748b;">Sign in to your HR dashboard</p>
</div>

<form method="POST" action="/login" id="loginForm">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

    <div class="form-group">
        <label class="form-label" for="email">Email address</label>
        <input
            class="form-input"
            type="email"
            id="email"
            name="email"
            placeholder="you@company.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            autocomplete="email"
            required
        >
    </div>

    <div class="form-group" style="margin-bottom: 8px;">
        <label class="form-label" for="password">
            Password
        </label>
        <div style="position: relative;">
            <input
                class="form-input"
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
                style="padding-right: 44px;"
            >
            <button
                type="button"
                onclick="togglePassword()"
                title="Show/hide password"
                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;font-size:1rem;padding:0;"
            >👁</button>
        </div>
    </div>

    <div style="text-align: right; margin-bottom: 24px;">
        <a href="/forgot-password" class="link-muted" style="font-size:0.8125rem;">Forgot password?</a>
    </div>

    <button type="submit" class="btn-primary" id="loginBtn">
        Sign In
    </button>

    <hr class="divider">

    <div style="text-align: center; font-size: 0.875rem; color: #64748b;">
        Looking for a job?
        <a href="/register" class="link-primary" style="margin-left: 4px;">Register here →</a>
    </div>
</form>

<style>
#loginBtn.loading {
    opacity: 0.7;
    pointer-events: none;
}
</style>

<script>
function togglePassword() {
    var p = document.getElementById('password');
    p.type = p.type === 'password' ? 'text' : 'password';
}
document.getElementById('loginForm').addEventListener('submit', function() {
    var btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
    btn.textContent = 'Signing in…';
});
</script>
