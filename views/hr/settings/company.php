<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Company Settings</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Manage your company profile and branding.</p>
</div>

<?= flash() ?>

<div style="max-width:720px;display:flex;flex-direction:column;gap:20px;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Company Profile</h3>
        <form method="POST" action="/settings/company" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Company Name *</label>
                <input class="form-input" type="text" name="name" value="<?= htmlspecialchars($tenant['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Industry</label>
                <select class="form-input" name="industry">
                    <option value="">— Select Industry —</option>
                    <?php foreach (['Technology','Healthcare','Finance','Education','Retail','Manufacturing','Media','Consulting','Real Estate','Other'] as $ind): ?>
                    <option value="<?= $ind ?>" <?= ($tenant['industry'] ?? '') === $ind ? 'selected' : '' ?>><?= $ind ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Company Size</label>
                    <select class="form-input" name="company_size">
                        <option value="">— Select —</option>
                        <?php foreach (['1-10','11-50','51-200','201-500','501-1000','1000+'] as $sz): ?>
                        <option value="<?= $sz ?>" <?= ($tenant['company_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?> employees</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <input class="form-input" type="text" name="country" value="<?= htmlspecialchars($tenant['country'] ?? '') ?>" placeholder="e.g. United States">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Website</label>
                <input class="form-input" type="url" name="website" value="<?= htmlspecialchars($tenant['website'] ?? '') ?>" placeholder="https://yourcompany.com">
            </div>
            <div class="form-group">
                <label class="form-label">Company Description</label>
                <textarea class="form-input" name="description" rows="4" style="resize:vertical;"><?= htmlspecialchars($tenant['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Company Logo</label>
                <?php if ($tenant['logo'] ?? null): ?>
                <div style="margin-bottom:8px;">
                    <img src="<?= htmlspecialchars($tenant['logo']) ?>" alt="Logo" style="height:48px;border-radius:6px;background:#fff;padding:4px;">
                </div>
                <?php endif; ?>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="font-size:.875rem;color:#94a3b8;">
                <div style="font-size:.75rem;color:#475569;margin-top:4px;">PNG, JPEG, or SVG. Max 2MB.</div>
            </div>
            <button type="submit" class="btn-primary" style="padding:11px 28px;">Save Changes</button>
        </form>
    </div>

    <!-- Subscription Info -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">Subscription</h3>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:rgba(79,70,229,.08);border-radius:8px;border:1px solid rgba(79,70,229,.15);">
            <div>
                <div style="font-weight:700;color:#e2e8f0;text-transform:capitalize;font-size:1.1rem;"><?= htmlspecialchars($subscription['plan'] ?? 'Free') ?> Plan</div>
                <div style="font-size:.8rem;color:#64748b;margin-top:2px;">
                    <?= (int)($subscription['max_users'] ?? 0) ?> users · <?= (int)($subscription['max_jobs'] ?? 0) ?> jobs · <?= (int)($subscription['max_ai_interviews_per_month'] ?? 0) ?> AI interviews/mo
                </div>
            </div>
            <span style="background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:5px 16px;border-radius:6px;font-size:.82rem;font-weight:700;color:#fff;"><?= strtoupper($subscription['plan'] ?? 'FREE') ?></span>
        </div>
    </div>
</div>
