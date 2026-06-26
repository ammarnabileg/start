<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Platform Settings</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Configure global platform settings.</p>
</div>

<?= flash() ?>

<div style="max-width:720px;display:flex;flex-direction:column;gap:20px;">
    <!-- General Settings -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">General Settings</h3>
        <form method="POST" action="/super/settings">
            <input type="hidden" name="section" value="general">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Platform Name</label>
                    <input class="form-input" type="text" name="platform_name" value="<?= htmlspecialchars($settings['platform_name'] ?? 'AI Recruit') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Support Email</label>
                    <input class="form-input" type="email" name="support_email" value="<?= htmlspecialchars($settings['support_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Default Subscription Plan</label>
                    <select class="form-input" name="default_plan">
                        <?php foreach (['free','starter','pro','enterprise'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($settings['default_plan'] ?? 'free') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Max AI Interviews per Day (0 = unlimited)</label>
                    <input class="form-input" type="number" name="max_ai_interviews_per_day" min="0" value="<?= (int)($settings['max_ai_interviews_per_day'] ?? 0) ?>">
                </div>
            </div>
            <div style="margin-top:1.25rem;">
                <button type="submit" class="btn-primary" style="padding:10px 24px;">Save Settings</button>
            </div>
        </form>
    </div>

    <!-- Registration Settings -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Registration & Access</h3>
        <form method="POST" action="/super/settings">
            <input type="hidden" name="section" value="access">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" name="allow_company_registration" value="1" <?= ($settings['allow_company_registration'] ?? '1') === '1' ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#4f46e5;">
                    <div>
                        <div style="font-size:.875rem;font-weight:600;color:#e2e8f0;">Allow Company Self-Registration</div>
                        <div style="font-size:.78rem;color:#64748b;">Companies can sign up on their own</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" name="allow_candidate_registration" value="1" <?= ($settings['allow_candidate_registration'] ?? '1') === '1' ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#4f46e5;">
                    <div>
                        <div style="font-size:.875rem;font-weight:600;color:#e2e8f0;">Allow Candidate Self-Registration</div>
                        <div style="font-size:.78rem;color:#64748b;">Candidates can create accounts</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" name="require_email_verification" value="1" <?= ($settings['require_email_verification'] ?? '0') === '1' ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#4f46e5;">
                    <div>
                        <div style="font-size:.875rem;font-weight:600;color:#e2e8f0;">Require Email Verification</div>
                        <div style="font-size:.78rem;color:#64748b;">Users must verify email before logging in</div>
                    </div>
                </label>
            </div>
            <div style="margin-top:1.25rem;">
                <button type="submit" class="btn-primary" style="padding:10px 24px;">Save Settings</button>
            </div>
        </form>
    </div>

    <!-- SMTP Settings -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:.25rem;">Email / SMTP</h3>
        <p style="font-size:.82rem;color:#64748b;margin-bottom:1.25rem;">Used for all system emails (notifications, interview links, etc.)</p>
        <form method="POST" action="/super/settings">
            <input type="hidden" name="section" value="smtp">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input class="form-input" type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Port</label>
                    <input class="form-input" type="number" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Username</label>
                    <input class="form-input" type="text" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Password</label>
                    <input class="form-input" type="password" name="smtp_pass" placeholder="Leave blank to keep current">
                </div>
                <div class="form-group">
                    <label class="form-label">From Name</label>
                    <input class="form-input" type="text" name="smtp_from_name" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? 'AI Recruit') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">From Email</label>
                    <input class="form-input" type="email" name="smtp_from_email" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>">
                </div>
            </div>
            <div style="margin-top:1.25rem;">
                <button type="submit" class="btn-primary" style="padding:10px 24px;">Save SMTP Settings</button>
            </div>
        </form>
    </div>
</div>
