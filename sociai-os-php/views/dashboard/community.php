<?php
$pageTitle  = 'Community Management';
$activePage = 'community';
$stats = [
  ['label'=>'Pending Replies', 'value'=>'47',   'icon'=>'⏳','color'=>'yellow'],
  ['label'=>'Auto-Replied',    'value'=>'3,291', 'icon'=>'🤖','color'=>'blue'],
  ['label'=>'Leads Detected',  'value'=>'128',   'icon'=>'🎯','color'=>'green'],
  ['label'=>'Spam Filtered',   'value'=>'894',   'icon'=>'🛡️','color'=>'purple'],
];
$comments = [
  ['author'=>'Sarah Johnson',     'platform'=>'linkedin', 'avatar'=>'SJ','sentiment'=>'positive','message'=>'This is absolutely incredible! Your AI automation approach is exactly what we\'ve been looking for. How do we get started with the enterprise plan?','suggestion'=>'Hi Sarah! Thank you so much for the kind words! 🙏 Our enterprise plan is perfect for your needs. I\'d love to schedule a demo — feel free to DM us or visit sociai.io/enterprise','time'=>'3m ago','lead'=>true,'score'=>92],
  ['author'=>'Mohammed Al-Ahmed',  'platform'=>'instagram','avatar'=>'MA','sentiment'=>'positive','message'=>'الله يجزاك خير على هذا المحتوى الرائع! متى يكون المنتج متاح باللغة العربية؟','suggestion'=>'شكراً جزيلاً محمد! 🌟 نعم، النسخة العربية متاحة الآن بشكل كامل مع دعم الـ RTL! يمكنك التسجيل مباشرة على الموقع وتجربتها مجاناً لمدة 14 يوم 🚀','time'=>'8m ago','lead'=>true,'score'=>87],
  ['author'=>'Alex Thompson',      'platform'=>'tiktok',  'avatar'=>'AT','sentiment'=>'neutral', 'message'=>'How does this compare to just hiring a social media manager? Is it really worth the cost?','suggestion'=>'Great question Alex! SociAI OS handles the equivalent of 3 full-time social media managers — across 11 platforms, 24/7. Most users see 10x more content output at 1/5 the cost. Want me to share a case study? 📊','time'=>'15m ago','lead'=>false,'score'=>65],
  ['author'=>'Priya Patel',        'platform'=>'facebook', 'avatar'=>'PP','sentiment'=>'negative','message'=>'I tried signing up and the verification email never arrived. This is frustrating, been waiting 20 minutes!','suggestion'=>'Hi Priya, I\'m so sorry about that! 😟 Please check your spam folder first. If it\'s not there, reply here or email support@sociai.io and we\'ll get you sorted within 5 minutes. Our team is available 24/7!','time'=>'22m ago','lead'=>false,'score'=>0],
  ['author'=>'Carlos Martinez',    'platform'=>'twitter',  'avatar'=>'CM','sentiment'=>'positive','message'=>'Just hit 10K followers using @SociAI OS autopilot mode in 3 months. These results are insane! 🔥','suggestion'=>'Congrats Carlos, that\'s an incredible milestone! 🎉 10K in 3 months is exactly what our AI agents are designed to achieve. Mind if we feature your story? Tag us in your next post! 🚀','time'=>'1h ago','lead'=>true,'score'=>78],
];
$dms = [
  ['author'=>'Emma Wilson',   'platform'=>'instagram','avatar'=>'EW','message'=>'Hey! I run a 50-person marketing agency. Interested in a team plan. What\'s the pricing?','time'=>'5m ago','lead'=>true,'score'=>95],
  ['author'=>'Yusuf Hassan',  'platform'=>'linkedin', 'avatar'=>'YH','message'=>'We\'re a startup in Dubai and want to use SociAI for our launch campaign next month. Available for a call?','time'=>'12m ago','lead'=>true,'score'=>91],
  ['author'=>'Liu Wei',       'platform'=>'facebook', 'avatar'=>'LW','message'=>'Can SociAI handle Chinese social platforms like WeChat and Weibo too?','time'=>'45m ago','lead'=>true,'score'=>72],
];
?>
<?php ob_start() ?>
<div class="community-queue">
  <div class="page-header page-header-row">
    <div>
      <h1>Community Management 💬</h1>
      <p>AI-powered reply management across all platforms</p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center">
      <div class="live-indicator"><span class="live-dot"></span>Auto-Reply Active</div>
      <button class="btn btn-primary" onclick="SociAI.showToast('Running bulk AI reply...','info')">🤖 AI Bulk Reply</button>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="dashboard-grid grid-cols-4 mb-4">
    <?php foreach ($stats as $s): ?>
    <div class="metric-card" style="padding:1.25rem">
      <div class="metric-header">
        <div>
          <div class="metric-label"><?= htmlspecialchars($s['label']) ?></div>
          <div class="metric-value community-pending-count"><?= htmlspecialchars($s['value']) ?></div>
        </div>
        <div class="metric-icon metric-icon-<?= $s['color'] ?>" style="font-size:1.3rem"><?= $s['icon'] ?></div>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Tabs -->
  <div class="tabs tab-scope" style="margin-bottom:0">
    <button class="tab-btn active" data-tab="comments">💬 Comments <span class="nav-badge" style="font-size:0.7rem;padding:1px 6px"><?= count($comments) ?></span></button>
    <button class="tab-btn" data-tab="dms">📩 Direct Messages <span class="nav-badge" style="font-size:0.7rem;padding:1px 6px"><?= count($dms) ?></span></button>
    <button class="tab-btn" data-tab="escalations">🚨 Escalations <span class="nav-badge" style="font-size:0.7rem;padding:1px 6px;background:var(--red)">2</span></button>
  </div>

  <div style="margin-bottom:1.25rem;padding:0.875rem 1.25rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-top:none;border-radius:0 0 var(--radius-lg) var(--radius-lg);display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
    <select class="form-select" style="max-width:140px"><option>All Platforms</option><option>LinkedIn</option><option>Instagram</option><option>TikTok</option><option>Facebook</option><option>Twitter/X</option></select>
    <select class="form-select" style="max-width:140px"><option>All Sentiments</option><option>Positive</option><option>Neutral</option><option>Negative</option></select>
    <select class="form-select" style="max-width:140px"><option>All: Pending</option><option>Leads Only</option><option>Pending Review</option></select>
    <div style="margin-left:auto;display:flex;gap:0.5rem">
      <button class="btn btn-success btn-sm" onclick="SociAI.showToast('Approving all AI suggestions...','success')">✓ Approve All</button>
      <button class="btn btn-ghost btn-sm">Export</button>
    </div>
  </div>

  <div class="tab-pane active" data-tab-pane="comments">
    <?php foreach ($comments as $c): ?>
    <div class="reply-card">
      <div class="reply-author">
        <div class="user-avatar" style="background:var(--gradient-primary);font-size:0.72rem;flex-shrink:0"><?= htmlspecialchars($c['avatar']) ?></div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
            <span style="font-size:0.85rem;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($c['author']) ?></span>
            <span class="platform-badge platform-<?= $c['platform'] ?>"><?= ucfirst($c['platform']) ?></span>
            <span class="badge badge sentiment-<?= $c['sentiment'] ?>" style="font-size:0.65rem"><?= ucfirst($c['sentiment']) ?></span>
            <?php if ($c['lead']): ?>
            <span class="badge badge-success" style="font-size:0.65rem">🎯 Lead Score: <?= $c['score'] ?></span>
            <?php endif ?>
          </div>
          <div style="font-size:0.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['time']) ?></div>
        </div>
      </div>
      <p class="reply-text">"<?= htmlspecialchars($c['message']) ?>"</p>
      <div class="ai-suggestion">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.3rem">🤖 AI Suggested Reply:</div>
        <?= htmlspecialchars($c['suggestion']) ?>
      </div>
      <div class="reply-actions">
        <button class="btn btn-success btn-sm approve-btn">✓ Approve & Post</button>
        <button class="btn btn-ghost btn-sm edit-btn">✏️ Edit</button>
        <button class="btn btn-ghost btn-sm skip-btn">Skip</button>
        <?php if ($c['lead']): ?>
        <button class="btn btn-primary btn-sm" onclick="SociAI.showToast('Lead saved to CRM!','success')" style="margin-left:auto">💾 Save Lead</button>
        <?php endif ?>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <div class="tab-pane" data-tab-pane="dms">
    <?php foreach ($dms as $dm): ?>
    <div class="reply-card">
      <div class="reply-author">
        <div class="user-avatar" style="background:var(--gradient-primary);font-size:0.72rem;flex-shrink:0"><?= htmlspecialchars($dm['avatar']) ?></div>
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
            <span style="font-size:0.85rem;font-weight:600"><?= htmlspecialchars($dm['author']) ?></span>
            <span class="platform-badge platform-<?= $dm['platform'] ?>"><?= ucfirst($dm['platform']) ?></span>
            <span class="badge badge-success" style="font-size:0.65rem">🎯 Score: <?= $dm['score'] ?></span>
          </div>
          <div style="font-size:0.72rem;color:var(--text-muted)"><?= $dm['time'] ?></div>
        </div>
      </div>
      <p class="reply-text">"<?= htmlspecialchars($dm['message']) ?>"</p>
      <div class="reply-actions">
        <button class="btn btn-primary btn-sm" onclick="SociAI.showToast('Opening conversation...','info')">💬 Reply Now</button>
        <button class="btn btn-success btn-sm" onclick="SociAI.showToast('Lead saved!','success')">💾 Save Lead</button>
        <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Forwarded to sales team!','info')">📤 Forward to Sales</button>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <div class="tab-pane" data-tab-pane="escalations">
    <div class="glass-card" style="border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.05)">
      <div style="display:flex;align-items:flex-start;gap:0.75rem">
        <span style="font-size:1.5rem">🚨</span>
        <div style="flex:1">
          <div style="font-size:0.9rem;font-weight:600;color:#FCA5A5;margin-bottom:0.25rem">Negative Review — Requires Human Response</div>
          <p style="font-size:0.85rem;margin-bottom:0.75rem">"This product is completely broken. I've contacted support 3 times and no one has helped me. Do NOT waste your money on this." — James K. on Facebook</p>
          <div style="display:flex;gap:0.5rem">
            <button class="btn btn-danger btn-sm" onclick="SociAI.showToast('Assigned to support team!','warning')">🛎️ Assign to Support</button>
            <button class="btn btn-ghost btn-sm">View Full Thread</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
