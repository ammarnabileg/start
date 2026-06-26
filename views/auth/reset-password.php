<?php $errors = $errors ?? []; ?>
<div style="margin-bottom:28px;">
    <h1 style="font-size:1.375rem;font-weight:700;color:#f1f5f9;margin-bottom:6px;">Reset Password</h1>
    <p style="font-size:.875rem;color:#64748b;">Enter your new password below.</p>
</div>
<?php if (!empty($errors)): ?>
<div class="alert-error"><?php foreach ($errors as $f => $msgs): foreach ((array)$msgs as $m): echo htmlspecialchars($m).'<br>'; endforeach; endforeach; ?></div>
<?php endif; ?>
<form method="POST" action="/reset-password/<?= htmlspecialchars($token ?? '') ?>">
    <div class="form-group">
        <label class="form-label">New Password</label>
        <input class="form-input" type="password" name="password" minlength="8" required placeholder="At least 8 characters">
    </div>
    <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input class="form-input" type="password" name="password_confirmation" required placeholder="Repeat new password">
    </div>
    <button type="submit" class="btn-primary">Reset Password</button>
    <hr class="divider">
    <div style="text-align:center;font-size:.875rem;"><a href="/login" class="link-muted">← Back to Sign In</a></div>
</form>
