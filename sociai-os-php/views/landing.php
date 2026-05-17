<?php
// SociAI OS - Public Landing Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> &mdash; Enterprise AI Social Media OS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6C63FF;
            --secondary: #FF6584;
            --dark: #0F0F1A;
            --card-bg: #1A1A2E;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--dark); color: #fff; font-family: 'Inter', sans-serif; }

        .hero { min-height: 100vh; display: flex; align-items: center; padding: 6rem 0 4rem;
                background: radial-gradient(ellipse at 20% 50%, rgba(108,99,255,.15) 0%, transparent 60%),
                            radial-gradient(ellipse at 80% 20%, rgba(255,101,132,.1) 0%, transparent 60%); }
        .hero-badge { background: rgba(108,99,255,.2); border: 1px solid rgba(108,99,255,.4);
                      color: #a89cff; padding: .3rem 1rem; border-radius: 50px; font-size: .8rem;
                      font-weight: 600; letter-spacing: .05em; display: inline-block; margin-bottom: 1.5rem; }
        .hero h1 { font-size: clamp(2.5rem, 6vw, 5rem); font-weight: 900; line-height: 1.1;
                   background: linear-gradient(135deg, #fff 0%, #a89cff 50%, #FF6584 100%);
                   -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .hero p.lead { color: #8b8b9e; font-size: 1.2rem; max-width: 540px; line-height: 1.7; }
        .btn-hero-primary { background: linear-gradient(135deg, var(--primary), #8B5CF6);
                            border: none; padding: .875rem 2.5rem; border-radius: 50px; font-weight: 600;
                            font-size: 1rem; color: #fff; text-decoration: none; transition: all .3s;
                            box-shadow: 0 8px 30px rgba(108,99,255,.4); }
        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(108,99,255,.5); color: #fff; }
        .btn-hero-secondary { border: 1px solid rgba(255,255,255,.2); padding: .875rem 2rem;
                              border-radius: 50px; font-weight: 500; color: #ccc; text-decoration: none; transition: all .3s; }
        .btn-hero-secondary:hover { background: rgba(255,255,255,.05); color: #fff; }

        .feature-card { background: var(--card-bg); border: 1px solid rgba(255,255,255,.07); border-radius: 16px;
                        padding: 2rem; transition: all .3s; height: 100%; }
        .feature-card:hover { border-color: rgba(108,99,255,.4); transform: translateY(-4px); }
        .feature-icon { width: 52px; height: 52px; border-radius: 12px;
                        background: linear-gradient(135deg, rgba(108,99,255,.2), rgba(139,92,246,.2));
                        display: flex; align-items: center; justify-content: center;
                        font-size: 1.5rem; margin-bottom: 1rem; }
        .feature-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: .5rem; }
        .feature-card p { color: #8b8b9e; font-size: .9rem; line-height: 1.6; }

        .stat-card { text-align: center; padding: 2rem; }
        .stat-number { font-size: 3rem; font-weight: 900;
                       background: linear-gradient(135deg, var(--primary), #FF6584);
                       -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stat-label { color: #8b8b9e; font-size: .9rem; }

        .platforms-row { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: center; }
        .platform-badge { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
                          padding: .5rem 1.2rem; border-radius: 50px; font-size: .85rem; font-weight: 500;
                          color: #ccc; }

        .cta-section { background: radial-gradient(ellipse at center, rgba(108,99,255,.2) 0%, transparent 70%);
                       padding: 6rem 0; text-align: center; }
        .section-label { color: var(--primary); font-size: .8rem; font-weight: 700;
                         letter-spacing: .15em; text-transform: uppercase; margin-bottom: .75rem; }
        .section-divider { height: 1px; background: rgba(255,255,255,.07); margin: 4rem 0; }

        nav.main-nav { background: rgba(15,15,26,.8); backdrop-filter: blur(20px);
                       border-bottom: 1px solid rgba(255,255,255,.07); padding: 1rem 0;
                       position: sticky; top: 0; z-index: 1000; }
    </style>
</head>
<body>
<nav class="main-nav">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="/" class="text-decoration-none">
            <span style="font-size:1.3rem; font-weight:900; background:linear-gradient(135deg,#6C63FF,#FF6584);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
                ⚡ <?= APP_NAME ?>
            </span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="/auth/login" class="btn btn-hero-secondary" style="padding:.5rem 1.5rem; font-size:.9rem;">Login</a>
            <a href="/auth/register" class="btn btn-hero-primary" style="padding:.5rem 1.5rem; font-size:.9rem;">Get Started Free</a>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-badge">🤖 AI-Powered Social Media Platform</div>
                <h1>The Operating System for Social Media Teams</h1>
                <p class="lead mt-3 mb-4">Create, schedule, and analyse social media content across 11 platforms with AI agents that think, write, and optimise &mdash; in English and Arabic.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/auth/register" class="btn-hero-primary">Start Free Trial</a>
                    <a href="#features" class="btn-hero-secondary">See Features</a>
                </div>
                <div class="d-flex gap-4 mt-4">
                    <div><span style="font-weight:700;color:#fff">11</span> <span style="color:#8b8b9e;font-size:.85rem">Platforms</span></div>
                    <div><span style="font-weight:700;color:#fff">5</span> <span style="color:#8b8b9e;font-size:.85rem">AI Agents</span></div>
                    <div><span style="font-weight:700;color:#fff">2</span> <span style="color:#8b8b9e;font-size:.85rem">Languages</span></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div style="background:var(--card-bg);border:1px solid rgba(108,99,255,.3);border-radius:20px;padding:2rem;font-family:monospace;">
                    <div style="color:#6C63FF;margin-bottom:1rem;font-weight:700;">// AI Agent Running...</div>
                    <div style="color:#a89cff;">✓ Strategy extracted from document</div>
                    <div style="color:#a89cff;">✓ Content pillars identified: 5</div>
                    <div style="color:#a89cff;">✓ Target audience profiled</div>
                    <div style="color:#fff;margin-top:.5rem;">⚡ Generating 30 posts...</div>
                    <div style="color:#4ade80;margin-top:.5rem;">✓ Arabic variants created</div>
                    <div style="color:#4ade80;">✓ Viral score: 87.3 🔥</div>
                    <div style="color:#4ade80;">✓ Scheduled across 6 platforms</div>
                    <div style="color:#8b8b9e;margin-top:1rem;font-size:.8rem;">Cost: $0.0034 | Tokens: 2,847</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Platform Support -->
<div class="container py-3">
    <div class="section-divider"></div>
    <p class="text-center text-muted mb-3" style="font-size:.85rem;letter-spacing:.1em;text-transform:uppercase;">Publish to 11 platforms from one dashboard</p>
    <div class="platforms-row">
        <?php foreach (['LinkedIn','Instagram','Facebook','TikTok','Twitter/X','YouTube','Snapchat','Threads','Pinterest','WhatsApp','Telegram'] as $p): ?>
        <span class="platform-badge"><?= $p ?></span>
        <?php endforeach; ?>
    </div>
    <div class="section-divider"></div>
</div>

<!-- Features -->
<section id="features" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">Capabilities</div>
            <h2 style="font-size:2.5rem;font-weight:800;">Everything your social team needs</h2>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['icon'=>'🤖','title'=>'AI Content Generation','desc'=>'Generate platform-optimised posts, reels, stories, and carousels in seconds. Supports English and Arabic with dialect-aware writing.'],
                ['icon'=>'📄','title'=>'Strategy Extraction','desc'=>'Upload your brand strategy document and AI extracts pillars, audience personas, tone of voice, and KPIs automatically.'],
                ['icon'=>'🔥','title'=>'Trend Hunter Agent','desc'=>'Real-time trend detection across all platforms. Get content suggestions aligned to viral opportunities before they peak.'],
                ['icon'=>'💬','title'=>'Community AI','desc'=>'Auto-analyse sentiment, detect leads, filter spam, and generate on-brand replies to every comment and DM.'],
                ['icon'=>'📊','title'=>'Deep Analytics','desc'=>'Viral scoring algorithm, platform breakdowns, engagement trends, and AI cost tracking in one unified dashboard.'],
                ['icon'=>'👥','title'=>'Team Collaboration','desc'=>'Role-based access (owner/admin/manager/editor/viewer), content approval workflows, and full audit logging.'],
            ];
            foreach ($features as $f): ?>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><?= $f['icon'] ?></div>
                    <h3><?= $f['title'] ?></h3>
                    <p><?= $f['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="section-label">Get Started Today</div>
        <h2 style="font-size:3rem;font-weight:900;margin-bottom:1rem;">Ready to transform your social media?</h2>
        <p style="color:#8b8b9e;max-width:500px;margin:0 auto 2rem;">Join the brands already using AI to create better content, faster &mdash; at a fraction of the cost.</p>
        <a href="/auth/register" class="btn-hero-primary">Create Free Account</a>
    </div>
</section>

<footer class="text-center py-4" style="border-top:1px solid rgba(255,255,255,.07);color:#8b8b9e;font-size:.85rem;">
    <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Built with pure PHP &amp; AI.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
