<?php
$flash = flash('flash_success');
$errors = $errors ?? [];
?>
<?php if ($flash): ?>
<div class="alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div style="margin-bottom:28px;">
    <h1 style="font-size:1.375rem;font-weight:700;color:#f1f5f9;margin-bottom:6px;">Forgot password?</h1>
    <p style="font-size:.875rem;color:#64748b;">Enter your email and we'll send you a reset link.</p>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-error">
    <?php foreach ($errors as $field => $msgs): ?>
        <?php foreach ((array)$msgs as $msg): ?>
            <?= htmlspecialchars($msg) ?><br>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" action="/forgot-password">
    <div class="form-group">
        <label class="form-label" for="email">Email address</label>
        <input class="form-input" type="email" id="email" name="email"
               value="<?= htmlspecialchars($old['email'] ?? '') ?>"
               placeholder="you@company.com" required>
    </div>
    <button type="submit" class="btn-primary">Send Reset Link</button>
    <hr class="divider">
    <div style="text-align:center;font-size:.875rem;">
        <a href="/login" class="link-muted">← Back to Sign In</a>
    </div>
</form>
