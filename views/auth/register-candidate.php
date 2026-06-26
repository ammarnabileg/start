<?php $errors = $errors ?? []; $old = $old ?? []; ?>
<div style="margin-bottom:24px;">
    <h1 style="font-size:1.375rem;font-weight:700;color:#f1f5f9;margin-bottom:6px;">Create Account</h1>
    <p style="font-size:.875rem;color:#64748b;">Join to apply for jobs and track your applications.</p>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-error">
    <?php foreach ($errors as $f => $msgs): foreach ((array)$msgs as $m): echo htmlspecialchars($m).'<br>'; endforeach; endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" action="/register">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
            <label class="form-label">First Name *</label>
            <input class="form-input" type="text" name="first_name" value="<?= htmlspecialchars($old['first_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input class="form-input" type="text" name="last_name" value="<?= htmlspecialchars($old['last_name'] ?? '') ?>" required>
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Email *</label>
        <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
    </div>
    <div class="form-group">
        <label class="form-label">Phone *</label>
        <input class="form-input" type="tel" name="phone" value="<?= htmlspecialchars($old['phone'] ?? '') ?>" required>
    </div>
    <div class="form-group">
        <label class="form-label">Years of Experience *</label>
        <input class="form-input" type="number" name="years_experience" value="<?= htmlspecialchars($old['years_experience'] ?? '0') ?>" min="0" max="50" required>
    </div>
    <div class="form-group">
        <label class="form-label">Expected Salary (optional)</label>
        <input class="form-input" type="number" name="expected_salary" value="<?= htmlspecialchars($old['expected_salary'] ?? '') ?>" placeholder="Annual, USD">
    </div>
    <div class="form-group">
        <label class="form-label">Password *</label>
        <input class="form-input" type="password" name="password" minlength="8" required placeholder="At least 8 characters">
    </div>
    <div class="form-group">
        <label class="form-label">Confirm Password *</label>
        <input class="form-input" type="password" name="password_confirmation" required>
    </div>
    <button type="submit" class="btn-primary">Create Account</button>
    <hr class="divider">
    <div style="text-align:center;font-size:.875rem;color:#64748b;">
        Already have an account? <a href="/login" class="link-primary">Sign in</a>
    </div>
</form>
