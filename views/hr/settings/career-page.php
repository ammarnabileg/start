<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Career Page Settings</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Customize how your public career page appears to candidates.</p>
</div>

<?= flash() ?>

<?php $slug = $tenant['slug'] ?? ''; $careerUrl = '/careers/' . $slug; ?>

<?php if ($slug): ?>
<div style="display:flex;align-items:center;gap:12px;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.15);border-radius:10px;padding:14px 18px;margin-bottom:1.5rem;">
    <span style="color:#4ade80;font-size:1.2rem;">🌐</span>
    <div style="flex:1;">
        <div style="font-size:.82rem;font-weight:600;color:#e2e8f0;">Your Career Page</div>
        <a href="<?= htmlspecialchars($careerUrl) ?>" target="_blank" style="font-size:.8rem;color:#4ade80;text-decoration:none;"><?= htmlspecialchars(($_SERVER['HTTP_HOST'] ?? 'yoursite.com') . $careerUrl) ?> →</a>
    </div>
    <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($careerUrl) ?>')" style="padding:6px 14px;background:transparent;border:1px solid rgba(16,185,129,.25);border-radius:6px;color:#4ade80;font-size:.78rem;cursor:pointer;">Copy URL</button>
</div>
<?php endif; ?>

<div style="max-width:720px;display:flex;flex-direction:column;gap:20px;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Career Page Configuration</h3>
        <form method="POST" action="/settings/career-page">
            <div class="form-group">
                <label class="form-label">Career Page Slug (URL identifier) *</label>
                <div style="display:flex;align-items:center;gap:0;">
                    <span style="padding:10px 14px;background:#0f0f1a;border:1px solid rgba(79,70,229,.2);border-right:none;border-radius:8px 0 0 8px;color:#64748b;font-size:.875rem;white-space:nowrap;">/careers/</span>
                    <input class="form-input" type="text" name="slug" value="<?= htmlspecialchars($slug) ?>" required pattern="[a-z0-9\-]+" placeholder="your-company" style="border-radius:0 8px 8px 0;border-left:none;">
                </div>
                <div style="font-size:.72rem;color:#475569;margin-top:4px;">Lowercase letters, numbers and hyphens only.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Hero Headline</label>
                <input class="form-input" type="text" name="career_page_headline" value="<?= htmlspecialchars($careerSettings['career_page_headline'] ?? 'Join Our Team') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Hero Subtext</label>
                <textarea class="form-input" name="career_page_subtext" rows="3" style="resize:vertical;"><?= htmlspecialchars($careerSettings['career_page_subtext'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Accent Color</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="color" name="career_page_color" value="<?= htmlspecialchars($careerSettings['career_page_color'] ?? '#4f46e5') ?>" style="width:44px;height:36px;border-radius:6px;border:1px solid rgba(79,70,229,.3);background:transparent;cursor:pointer;padding:2px;">
                    <span style="font-size:.82rem;color:#64748b;">Customize your career page brand color</span>
                </div>
            </div>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:1rem;">
                <input type="checkbox" name="career_page_active" value="1" <?= ($careerSettings['career_page_active'] ?? '1') === '1' ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#4f46e5;">
                <span style="font-size:.875rem;color:#e2e8f0;">Career page is publicly visible</span>
            </label>
            <button type="submit" class="btn-primary" style="padding:11px 28px;">Save Settings</button>
        </form>
    </div>
</div>
