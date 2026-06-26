<?= flash() ?>

<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">My Profile</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Keep your profile up to date to stand out to employers.</p>
</div>

<div style="max-width:720px;display:flex;flex-direction:column;gap:20px;">
    <!-- Personal Info -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Personal Information</h3>
        <form method="POST" action="/c/profile" enctype="multipart/form-data">
            <input type="hidden" name="section" value="personal">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">First Name *</label>
                    <input class="form-input" type="text" name="first_name" value="<?= htmlspecialchars($candidate['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Last Name *</label>
                    <input class="form-input" type="text" name="last_name" value="<?= htmlspecialchars($candidate['last_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-input" type="email" value="<?= htmlspecialchars($candidate['email'] ?? '') ?>" disabled style="opacity:.6;cursor:not-allowed;">
                <div style="font-size:.75rem;color:#475569;margin-top:4px;">Email cannot be changed here.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input class="form-input" type="tel" name="phone" value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <input class="form-input" type="text" name="location" value="<?= htmlspecialchars($candidate['location'] ?? '') ?>" placeholder="City, Country">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Years of Experience</label>
                    <input class="form-input" type="number" name="years_experience" min="0" max="50" value="<?= (int)($candidate['years_experience'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Expected Salary (USD)</label>
                    <input class="form-input" type="number" name="expected_salary" value="<?= htmlspecialchars($candidate['expected_salary'] ?? '') ?>" placeholder="Annual">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Professional Summary</label>
                <textarea class="form-input" name="bio" rows="4" style="resize:vertical;" placeholder="Tell employers about yourself…"><?= htmlspecialchars($candidate['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Skills (comma-separated)</label>
                <input class="form-input" type="text" name="skills" value="<?= htmlspecialchars($candidate['skills'] ?? '') ?>" placeholder="PHP, Python, React, SQL…">
            </div>
            <div class="form-group">
                <label class="form-label">LinkedIn URL</label>
                <input class="form-input" type="url" name="linkedin_url" value="<?= htmlspecialchars($candidate['linkedin_url'] ?? '') ?>" placeholder="https://linkedin.com/in/…">
            </div>
            <div class="form-group">
                <label class="form-label">Portfolio / GitHub</label>
                <input class="form-input" type="url" name="portfolio_url" value="<?= htmlspecialchars($candidate['portfolio_url'] ?? '') ?>" placeholder="https://…">
            </div>
            <button type="submit" class="btn-primary" style="padding:11px 28px;">Save Profile</button>
        </form>
    </div>

    <!-- Documents -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Documents</h3>
        <?php if (!empty($documents)): ?>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:1rem;">
            <?php foreach ($documents as $doc): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#0f0f1a;border-radius:8px;border:1px solid rgba(79,70,229,.1);">
                <div>
                    <div style="font-size:.85rem;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($doc['name']) ?></div>
                    <div style="font-size:.72rem;color:#64748b;text-transform:uppercase;"><?= htmlspecialchars($doc['type'] ?? '') ?> · <?= $doc['created_at'] ? date('M j, Y', strtotime($doc['created_at'])) : '' ?></div>
                </div>
                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" style="font-size:.78rem;color:#4f46e5;text-decoration:none;">Download</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <form method="POST" action="/c/profile/upload" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Upload CV / Resume</label>
                <input type="file" name="document" accept=".pdf,.doc,.docx" style="font-size:.875rem;color:#94a3b8;">
                <div style="font-size:.75rem;color:#475569;margin-top:4px;">PDF, DOC, DOCX. Max 10MB.</div>
            </div>
            <button type="submit" class="btn-primary" style="padding:10px 22px;">Upload Document</button>
        </form>
    </div>

    <!-- Change Password -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Change Password</h3>
        <form method="POST" action="/c/profile">
            <input type="hidden" name="section" value="password">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input class="form-input" type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input class="form-input" type="password" name="new_password" minlength="8" required>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input class="form-input" type="password" name="new_password_confirmation" required>
            </div>
            <button type="submit" class="btn-primary" style="padding:11px 28px;">Update Password</button>
        </form>
    </div>
</div>
