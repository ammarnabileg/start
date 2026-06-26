<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply – <?= htmlspecialchars($job['title'] ?? 'Position') ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{background:#0a0a14;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;font-size:15px}
.wrap{max-width:620px;margin:0 auto;padding:3rem 1rem}
.back{display:inline-flex;align-items:center;gap:4px;font-size:.8rem;color:#64748b;text-decoration:none;margin-bottom:1.5rem}
.job-header{background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;margin-bottom:1.5rem}
.job-title{font-size:1.1rem;font-weight:800;color:#f1f5f9;margin-bottom:4px}
.job-meta{font-size:.82rem;color:#64748b}
.card{background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px}
.card-title{font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem}
.form-group{margin-bottom:1rem}
.form-label{display:block;font-size:.82rem;color:#94a3b8;font-weight:600;margin-bottom:6px}
.form-input{width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:10px 14px;color:#e2e8f0;font-size:.9rem;font-family:inherit;outline:none;transition:border .15s}
.form-input:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.btn-primary{width:100%;padding:13px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:10px;color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity .15s}
.btn-primary:hover{opacity:.9}
.alert-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:12px 16px;color:#fca5a5;font-size:.85rem;margin-bottom:1rem}
</style>
</head>
<body>
<div class="wrap">
    <a href="/careers/<?= htmlspecialchars($tenant['slug'] ?? $tenant['id']) ?>" class="back">← Back to Careers</a>

    <div class="job-header">
        <div class="job-title"><?= htmlspecialchars($job['title'] ?? '—') ?></div>
        <div class="job-meta"><?= htmlspecialchars($tenant['name'] ?? '') ?> <?= $job['location'] ? '· ' . htmlspecialchars($job['location']) : '' ?> <?= $job['job_type'] ? '· ' . str_replace('_',' ',ucfirst($job['job_type'])) : '' ?></div>
    </div>

    <div class="card">
        <div class="card-title">Submit Your Application</div>

        <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e): ?><?= htmlspecialchars($e) ?><br><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/careers/<?= htmlspecialchars($tenant['slug'] ?? $tenant['id']) ?>/jobs/<?= $job['id'] ?>/apply" enctype="multipart/form-data">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">First Name *</label>
                    <input class="form-input" type="text" name="first_name" value="<?= htmlspecialchars($old['first_name'] ?? $user['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name *</label>
                    <input class="form-input" type="text" name="last_name" value="<?= htmlspecialchars($old['last_name'] ?? $user['last_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? $user['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input class="form-input" type="tel" name="phone" value="<?= htmlspecialchars($old['phone'] ?? $user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Years of Experience</label>
                <input class="form-input" type="number" name="years_experience" min="0" max="50" value="<?= htmlspecialchars($old['years_experience'] ?? $user['years_experience'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">CV / Resume *</label>
                <input type="file" name="cv" accept=".pdf,.doc,.docx" style="font-size:.875rem;color:#94a3b8;" required>
                <div style="font-size:.72rem;color:#475569;margin-top:4px;">PDF, DOC, DOCX. Max 10MB.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Cover Letter (optional)</label>
                <textarea class="form-input" name="cover_letter" rows="4" style="resize:vertical;" placeholder="Why are you a great fit for this role?"><?= htmlspecialchars($old['cover_letter'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn-primary">Submit Application</button>
        </form>
    </div>
</div>
</body>
</html>
