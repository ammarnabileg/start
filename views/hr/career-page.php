<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Careers at <?= htmlspecialchars($tenant['name'] ?? 'Our Company') ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{background:#0a0a14;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;font-size:15px}
.hero{background:linear-gradient(135deg,#1a1a2e 0%,#0f0f1a 100%);padding:80px 20px;text-align:center;border-bottom:1px solid rgba(79,70,229,.15)}
.hero-inner{max-width:700px;margin:0 auto}
.company-logo{width:72px;height:72px;border-radius:16px;object-fit:contain;background:#fff;padding:8px;margin-bottom:1.5rem}
.hero-title{font-size:2.2rem;font-weight:900;color:#f1f5f9;margin-bottom:.75rem}
.hero-sub{font-size:1rem;color:#94a3b8;line-height:1.7;max-width:560px;margin:0 auto 2rem}
.hero-stats{display:flex;justify-content:center;gap:2rem;flex-wrap:wrap}
.hero-stat{text-align:center}
.hero-stat-num{font-size:1.6rem;font-weight:800;background:linear-gradient(135deg,#4f46e5,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hero-stat-label{font-size:.78rem;color:#64748b;margin-top:2px}
.main{max-width:900px;margin:0 auto;padding:3rem 20px}
.section-title{font-size:1.2rem;font-weight:800;color:#f1f5f9;margin-bottom:1.25rem}
.filter-bar{display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap}
.filter-input{background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none}
.filter-input:focus{border-color:#4f46e5}
.job-card{background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;transition:border .15s;text-decoration:none;color:inherit}
.job-card:hover{border-color:rgba(79,70,229,.4)}
.job-title{font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:6px}
.job-meta{display:flex;flex-wrap:wrap;gap:8px;font-size:.78rem;color:#64748b}
.tag{background:rgba(79,70,229,.1);color:#818cf8;padding:2px 8px;border-radius:4px}
.apply-btn{padding:9px 20px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;display:inline-block}
.no-jobs{text-align:center;padding:3rem;color:#64748b}
</style>
</head>
<body>

<div class="hero">
    <div class="hero-inner">
        <?php if ($tenant['logo'] ?? null): ?>
        <img src="<?= htmlspecialchars($tenant['logo']) ?>" alt="Logo" class="company-logo">
        <?php endif; ?>
        <div class="hero-title">Join Our Team</div>
        <div class="hero-sub"><?= htmlspecialchars($tenant['description'] ?? 'We\'re looking for talented people to help us build the future.') ?></div>
        <div class="hero-stats">
            <div class="hero-stat"><div class="hero-stat-num"><?= (int)($jobCount ?? 0) ?></div><div class="hero-stat-label">Open Positions</div></div>
            <?php if ($tenant['company_size']): ?><div class="hero-stat"><div class="hero-stat-num"><?= htmlspecialchars($tenant['company_size']) ?></div><div class="hero-stat-label">Team Size</div></div><?php endif; ?>
            <?php if ($tenant['industry']): ?><div class="hero-stat"><div class="hero-stat-num" style="font-size:1.1rem;"><?= htmlspecialchars($tenant['industry']) ?></div><div class="hero-stat-label">Industry</div></div><?php endif; ?>
        </div>
    </div>
</div>

<div class="main">
    <h2 class="section-title">Open Positions</h2>
    <form method="GET" class="filter-bar">
        <input name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search roles…" class="filter-input" style="flex:1;min-width:180px;">
        <select name="type" class="filter-input">
            <option value="">All Types</option>
            <option value="full_time" <?= ($type??'')==='full_time'?'selected':'' ?>>Full Time</option>
            <option value="part_time" <?= ($type??'')==='part_time'?'selected':'' ?>>Part Time</option>
            <option value="contract" <?= ($type??'')==='contract'?'selected':'' ?>>Contract</option>
        </select>
        <button type="submit" style="padding:8px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">Search</button>
    </form>

    <?php if (empty($jobs)): ?>
    <div class="no-jobs"><div style="font-size:2.5rem;margin-bottom:.75rem;">📭</div>No open positions right now. Check back soon!</div>
    <?php else: ?>
    <?php foreach ($jobs as $job): ?>
    <a href="/careers/<?= htmlspecialchars($tenant['slug'] ?? $tenant['id']) ?>/jobs/<?= $job['id'] ?>" class="job-card">
        <div>
            <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
            <div class="job-meta">
                <?php if ($job['location']): ?><span>📍 <?= htmlspecialchars($job['location']) ?></span><?php endif; ?>
                <?php if ($job['job_type']): ?><span class="tag"><?= str_replace('_',' ',ucfirst($job['job_type'])) ?></span><?php endif; ?>
                <?php if ($job['salary_min']): ?><span>💰 <?= '$' . number_format($job['salary_min']) . '–$' . number_format($job['salary_max']) ?></span><?php endif; ?>
                <?php if ($job['remote_ok']): ?><span class="tag" style="background:rgba(16,185,129,.1);color:#4ade80;">Remote</span><?php endif; ?>
            </div>
        </div>
        <span class="apply-btn">Apply Now →</span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
