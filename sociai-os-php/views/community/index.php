<?php
$pageTitle  = 'Community Management';
$activePage = 'community';

// ── Mock data (controller passes real data via $comments, $templates, $metrics) ──
if (empty($comments)) {
    $comments = [
        [
            'id'        => 1,
            'platform'  => 'linkedin',
            'username'  => 'Sarah Mitchell',
            'handle'    => '@sarahmitch',
            'time_ago'  => '4 min ago',
            'text'      => 'This is exactly what I needed to hear today. Your content always hits different — keep up the incredible work! Would love to see more deep-dives like this.',
            'sentiment' => 'positive',
            'confidence'=> 94,
            'status'    => 'pending',
            'ai_reply'  => 'Thank you so much, Sarah! We really appreciate the kind words. We\'ll definitely be posting more in-depth content soon — stay tuned and make sure you\'re subscribed so you don\'t miss any updates!',
        ],
        [
            'id'        => 2,
            'platform'  => 'instagram',
            'username'  => 'Carlos Ruiz',
            'handle'    => '@carlos_creates',
            'time_ago'  => '11 min ago',
            'text'      => 'Honestly disappointed with the last update. It broke half of my workflow and I\'ve seen zero acknowledgment from you guys. Not a great look.',
            'sentiment' => 'negative',
            'confidence'=> 97,
            'status'    => 'pending',
            'ai_reply'  => 'Hi Carlos, we\'re really sorry to hear about the trouble you\'re experiencing. That\'s absolutely not the experience we want for you. Could you send us a DM with the details so our team can look into this right away? We\'ll make it right.',
        ],
        [
            'id'        => 3,
            'platform'  => 'tiktok',
            'username'  => 'Mia Johnson',
            'handle'    => '@miaj_official',
            'time_ago'  => '23 min ago',
            'text'      => 'Wait but what\'s the pricing for the pro plan? I checked the website and couldn\'t find anything clear 👀',
            'sentiment' => 'neutral',
            'confidence'=> 88,
            'status'    => 'pending',
            'ai_reply'  => 'Great question, Mia! Our Pro plan starts at $49/month and includes unlimited posts, AI-generated content, and analytics. You can check all the details and start a free trial at our website — link in bio!',
        ],
        [
            'id'        => 4,
            'platform'  => 'twitter',
            'username'  => 'buy_followers_cheap',
            'handle'    => '@bot_account_449',
            'time_ago'  => '31 min ago',
            'text'      => 'GET 10000 FOLLOWERS FOR FREE!!! CLICK HERE: spamlink.xyz/free-followers LIMITED TIME OFFER!!!',
            'sentiment' => 'spam',
            'confidence'=> 99,
            'status'    => 'pending',
            'ai_reply'  => '',
        ],
        [
            'id'        => 5,
            'platform'  => 'facebook',
            'username'  => 'James Okafor',
            'handle'    => '@james.okafor',
            'time_ago'  => '48 min ago',
            'text'      => 'This needs to be escalated — your AI tool gave my team completely wrong data for our Q3 report and now we have a board meeting in two hours. This is a serious issue.',
            'sentiment' => 'negative',
            'confidence'=> 91,
            'status'    => 'escalated',
            'ai_reply'  => 'Hi James, I sincerely apologize for the issue this has caused. I\'ve escalated this to our senior support team and someone will reach out to you directly within the next 15 minutes. Please check your registered email.',
        ],
        [
            'id'        => 6,
            'platform'  => 'youtube',
            'username'  => 'TechWithAlexa',
            'handle'    => '@techalex',
            'time_ago'  => '1 hr ago',
            'text'      => 'Subscribed! Any chance of a tutorial on integrating this with Zapier? That would be a game changer for automation workflows.',
            'sentiment' => 'positive',
            'confidence'=> 96,
            'status'    => 'pending',
            'ai_reply'  => 'Welcome aboard, Alexa! Great suggestion — a Zapier integration tutorial is actually already in our content calendar. Hit the notification bell so you\'re the first to know when it drops!',
        ],
    ];
}

if (empty($templates)) {
    $templates = [
        ['id'=>1,'name'=>'Thank You (Generic)',    'text'=>'Thank you so much for your kind words! We really appreciate your support. 🙏'],
        ['id'=>2,'name'=>'Apology & Support',       'text'=>'We\'re sorry to hear about your experience. Please DM us so we can resolve this ASAP!'],
        ['id'=>3,'name'=>'Question Redirect',       'text'=>'Great question! You can find all the details at our website. Feel free to DM us if you need more help.'],
        ['id'=>4,'name'=>'Feature Coming Soon',     'text'=>'We love this idea! It\'s actually on our roadmap — stay tuned for upcoming updates. 🚀'],
        ['id'=>5,'name'=>'Spam Block Notice',       'text'=>'This comment has been flagged and removed for violating our community guidelines.'],
        ['id'=>6,'name'=>'Welcome New Follower',    'text'=>'Welcome to our community! So glad to have you here. Don\'t forget to subscribe for more content!'],
    ];
}

$metrics = $metrics ?? [
    'avg_response_time' => '4.2 min',
    'reply_rate'        => '94.7%',
    'satisfaction'      => '4.8/5',
    'total_comments'    => 1847,
    'pending'           => 38,
    'auto_replied'      => 1612,
    'escalated'         => 14,
    'spam_rate'         => '3.2%',
    'blocked'           => 59,
    'false_positive'    => '0.4%',
];

$platformColors = [
    'linkedin'  => '#0A66C2',
    'instagram' => '#E1306C',
    'tiktok'    => '#010101',
    'twitter'   => '#1DA1F2',
    'facebook'  => '#1877F2',
    'youtube'   => '#FF0000',
];
$platformEmojis = [
    'linkedin'  => 'in',
    'instagram' => '📸',
    'tiktok'    => '♪',
    'twitter'   => '𝕏',
    'facebook'  => 'f',
    'youtube'   => '▶',
];

$sentimentClasses = [
    'positive' => 'badge-success',
    'negative' => 'badge-danger',
    'neutral'  => 'badge-neutral',
    'spam'     => 'badge-warning',
];
$csrfToken = bin2hex(random_bytes(16));
?>
<?php ob_start(); ?>

<style>
  .community-grid { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; }
  .filter-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 1rem; }
  .filter-tab {
    padding: 0.4rem 0.9rem; border-radius: 99px; font-size: 0.78rem; font-weight: 500;
    border: 1px solid var(--glass-border); background: var(--glass-bg);
    color: var(--text-secondary); cursor: pointer; transition: all var(--transition);
  }
  .filter-tab:hover { background: var(--glass-bg-hover); color: var(--text-primary); }
  .filter-tab.active { background: var(--gradient-primary); color: #fff; border-color: transparent; }
  .comment-card {
    background: var(--glass-bg); border: 1px solid var(--glass-border);
    border-radius: var(--radius-md); padding: 1rem; margin-bottom: 0.75rem;
    transition: all var(--transition);
  }
  .comment-card:hover { border-color: var(--glass-border-hover); }
  .comment-card[data-status="escalated"] { border-left: 3px solid var(--red); }
  .platform-pill {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 7px;
    font-size: 0.7rem; font-weight: 700; color: #fff; flex-shrink: 0;
  }
  .ai-reply-block {
    background: rgba(59,130,246,0.07); border: 1px solid rgba(59,130,246,0.2);
    border-radius: var(--radius-sm); padding: 0.75rem; margin-top: 0.75rem;
    display: none;
  }
  .ai-reply-block.open { display: block; }
  .badge-danger { background: rgba(239,68,68,0.15); color: #FC8181; border: 1px solid rgba(239,68,68,0.3); border-radius: 99px; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; }
  .badge-warning { background: rgba(249,115,22,0.15); color: #FDBA74; border: 1px solid rgba(249,115,22,0.3); border-radius: 99px; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; }
  .confidence-bar { height: 4px; background: var(--glass-bg); border-radius: 2px; width: 80px; overflow: hidden; }
  .confidence-fill { height: 100%; border-radius: 2px; background: var(--gradient-primary); }
  .template-item {
    display: flex; align-items: flex-start; gap: 0.6rem;
    padding: 0.6rem 0; border-bottom: 1px solid var(--glass-border);
  }
  .template-item:last-child { border-bottom: none; }
  .metric-row { display: flex; justify-content: space-between; align-items: center; padding: 0.55rem 0; border-bottom: 1px solid var(--glass-border); font-size: 0.85rem; }
  .metric-row:last-child { border-bottom: none; }
  .metric-row .val { font-weight: 600; color: var(--text-primary); }
  .comment-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.65rem; }
</style>

<!-- Page Header -->
<div class="page-header page-header-row" style="margin-bottom:1.5rem">
  <div>
    <h1>💬 Community Management</h1>
    <p>Monitor, reply and manage comments across all platforms with AI assistance</p>
  </div>
  <div style="display:flex;gap:0.75rem;align-items:center">
    <div class="live-indicator">
      <span class="live-dot"></span>
      Live Monitoring
    </div>
    <button class="btn btn-primary" onclick="autoReplyAll()" id="autoReplyBtn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m3 10 3 3 3-3"/><path d="M6 13V7a4 4 0 014-4h7"/><path d="m21 14-3-3-3 3"/><path d="M18 11v6a4 4 0 01-4 4H7"/></svg>
      Auto-Reply All
    </button>
  </div>
</div>

<!-- Stats Row -->
<div class="dashboard-grid grid-cols-4 mb-5" style="margin-bottom:1.5rem">
  <div class="metric-card">
    <div class="metric-header">
      <div>
        <div class="metric-label">Total Comments</div>
        <div class="metric-value"><?= number_format($metrics['total_comments']) ?></div>
      </div>
      <div class="metric-icon metric-icon-blue">💬</div>
    </div>
    <div class="metric-change trend-up">↑ +127 today</div>
  </div>
  <div class="metric-card">
    <div class="metric-header">
      <div>
        <div class="metric-label">Pending Replies</div>
        <div class="metric-value"><?= $metrics['pending'] ?></div>
      </div>
      <div class="metric-icon metric-icon-yellow">⏳</div>
    </div>
    <div class="metric-change" style="color:var(--yellow)">Needs attention</div>
  </div>
  <div class="metric-card">
    <div class="metric-header">
      <div>
        <div class="metric-label">Auto-Replied</div>
        <div class="metric-value"><?= number_format($metrics['auto_replied']) ?></div>
      </div>
      <div class="metric-icon metric-icon-green">🤖</div>
    </div>
    <div class="metric-change trend-up">↑ <?= $metrics['reply_rate'] ?> rate</div>
  </div>
  <div class="metric-card">
    <div class="metric-header">
      <div>
        <div class="metric-label">Escalated</div>
        <div class="metric-value"><?= $metrics['escalated'] ?></div>
      </div>
      <div class="metric-icon" style="background:rgba(239,68,68,0.15);color:var(--red)">🚨</div>
    </div>
    <div class="metric-change trend-down">↑ 2 new today</div>
  </div>
</div>

<!-- Main Two-Col Grid -->
<div class="community-grid">

  <!-- LEFT: Comment Queue -->
  <div>
    <div class="glass-card" style="padding:1.25rem">
      <div class="section-header" style="margin-bottom:1rem">
        <h3>Comment Queue</h3>
        <span style="font-size:0.8rem;color:var(--text-muted)"><?= count($comments) ?> comments</span>
      </div>

      <!-- Filter Tabs -->
      <div class="filter-tabs" id="commentFilters">
        <button class="filter-tab active" data-filter="all">All <span style="opacity:.6;font-weight:400">(<?= count($comments) ?>)</span></button>
        <button class="filter-tab" data-filter="positive">Positive</button>
        <button class="filter-tab" data-filter="negative">Negative</button>
        <button class="filter-tab" data-filter="spam">Spam</button>
        <button class="filter-tab" data-filter="neutral">Questions</button>
        <button class="filter-tab" data-filter="escalated">Escalated</button>
      </div>

      <!-- Comment Cards -->
      <div id="commentList">
        <?php foreach ($comments as $comment): ?>
        <div class="comment-card" data-comment-id="<?= $comment['id'] ?>" data-sentiment="<?= htmlspecialchars($comment['sentiment']) ?>" data-status="<?= htmlspecialchars($comment['status']) ?>">

          <!-- Card Header -->
          <div style="display:flex;align-items:flex-start;gap:0.75rem">
            <span class="platform-pill" style="background:<?= $platformColors[$comment['platform']] ?? '#555' ?>">
              <?= htmlspecialchars($platformEmojis[$comment['platform']] ?? '?') ?>
            </span>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                <span style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($comment['username']) ?></span>
                <span style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($comment['handle']) ?></span>
                <span style="font-size:0.72rem;color:var(--text-muted);margin-left:auto"><?= htmlspecialchars($comment['time_ago']) ?></span>
              </div>
              <p style="margin-top:0.35rem;font-size:0.83rem;color:var(--text-secondary);line-height:1.5"><?= htmlspecialchars($comment['text']) ?></p>
            </div>
          </div>

          <!-- Badges & Confidence -->
          <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.65rem;flex-wrap:wrap">
            <?php
              $sClass = $sentimentClasses[$comment['sentiment']] ?? 'badge-neutral';
              $sLabel = ucfirst($comment['sentiment']);
            ?>
            <span class="badge <?= $sClass ?>"><?= $sLabel ?></span>
            <?php if ($comment['status'] === 'escalated'): ?>
            <span class="badge badge-danger">Escalated</span>
            <?php endif ?>
            <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.72rem;color:var(--text-muted)">
              <span>AI Confidence</span>
              <div class="confidence-bar">
                <div class="confidence-fill" style="width:<?= $comment['confidence'] ?>%"></div>
              </div>
              <span style="color:var(--text-primary);font-weight:600"><?= $comment['confidence'] ?>%</span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="comment-actions">
            <?php if ($comment['sentiment'] !== 'spam'): ?>
            <button class="btn btn-sm btn-primary" onclick="openReplyModal(<?= $comment['id'] ?>, '<?= htmlspecialchars($comment['platform'], ENT_QUOTES) ?>')">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
              Reply
            </button>
            <?php endif ?>
            <button class="btn btn-sm btn-ghost" onclick="markSpam(<?= $comment['id'] ?>)">Mark Spam</button>
            <?php if ($comment['status'] !== 'escalated'): ?>
            <button class="btn btn-sm btn-ghost" style="color:var(--red)" onclick="escalateComment(<?= $comment['id'] ?>)">Escalate</button>
            <?php endif ?>
            <button class="btn btn-sm btn-ghost" onclick="dismissComment(<?= $comment['id'] ?>)">Dismiss</button>
            <?php if (!empty($comment['ai_reply'])): ?>
            <button class="btn btn-sm btn-ghost" style="margin-left:auto;color:var(--blue-light)" onclick="toggleAiReply(<?= $comment['id'] ?>)">
              ✨ AI Reply
            </button>
            <?php endif ?>
          </div>

          <!-- AI Suggested Reply -->
          <?php if (!empty($comment['ai_reply'])): ?>
          <div class="ai-reply-block" id="aiReply<?= $comment['id'] ?>">
            <div style="font-size:0.72rem;font-weight:600;color:var(--blue-light);margin-bottom:0.4rem;display:flex;align-items:center;gap:0.3rem">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              AI Suggested Reply
            </div>
            <p style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:0.65rem"><?= htmlspecialchars($comment['ai_reply']) ?></p>
            <div style="display:flex;gap:0.5rem">
              <button class="btn btn-sm btn-ghost" onclick="editReply(<?= $comment['id'] ?>, <?= htmlspecialchars(json_encode($comment['ai_reply']), ENT_QUOTES) ?>)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <button class="btn btn-sm btn-primary" onclick="sendAiReply(<?= $comment['id'] ?>, '<?= htmlspecialchars($comment['platform'], ENT_QUOTES) ?>')">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Send
              </button>
            </div>
          </div>
          <?php endif ?>

        </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <!-- RIGHT: Sidebar Widgets -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Quick Reply Templates -->
    <div class="glass-card">
      <div class="section-header" style="margin-bottom:0.75rem">
        <h3>📋 Quick Reply Templates</h3>
        <button class="btn btn-ghost btn-sm">+ New</button>
      </div>
      <?php foreach ($templates as $tpl): ?>
      <div class="template-item">
        <div style="flex:1;min-width:0">
          <div style="font-size:0.8rem;font-weight:600;color:var(--text-primary);margin-bottom:0.2rem"><?= htmlspecialchars($tpl['name']) ?></div>
          <div style="font-size:0.73rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($tpl['text']) ?></div>
        </div>
        <button class="btn btn-ghost btn-sm" style="flex-shrink:0;font-size:0.7rem" onclick="copyTemplate(<?= htmlspecialchars(json_encode($tpl['text']), ENT_QUOTES) ?>)" title="Copy">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          Copy
        </button>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Community Metrics -->
    <div class="glass-card">
      <div class="section-header" style="margin-bottom:0.75rem">
        <h3>📊 Community Metrics</h3>
      </div>
      <div class="metric-row">
        <span style="color:var(--text-secondary)">Avg. Response Time</span>
        <span class="val"><?= htmlspecialchars($metrics['avg_response_time']) ?></span>
      </div>
      <div class="metric-row">
        <span style="color:var(--text-secondary)">Reply Rate</span>
        <span class="val" style="color:var(--green-light)"><?= htmlspecialchars($metrics['reply_rate']) ?></span>
      </div>
      <div class="metric-row">
        <span style="color:var(--text-secondary)">Satisfaction Score</span>
        <span class="val" style="color:var(--yellow)">⭐ <?= htmlspecialchars($metrics['satisfaction']) ?></span>
      </div>
    </div>

    <!-- Spam Detection -->
    <div class="glass-card">
      <div class="section-header" style="margin-bottom:0.75rem">
        <h3>🛡️ Spam Detection</h3>
        <span class="badge badge-success" style="font-size:0.7rem">Active</span>
      </div>
      <div class="metric-row">
        <span style="color:var(--text-secondary)">Spam Rate</span>
        <span class="val" style="color:var(--orange)"><?= htmlspecialchars($metrics['spam_rate']) ?></span>
      </div>
      <div class="metric-row">
        <span style="color:var(--text-secondary)">Blocked Today</span>
        <span class="val"><?= $metrics['blocked'] ?></span>
      </div>
      <div class="metric-row">
        <span style="color:var(--text-secondary)">False Positive Rate</span>
        <span class="val" style="color:var(--green-light)"><?= htmlspecialchars($metrics['false_positive']) ?></span>
      </div>
      <div style="margin-top:0.75rem;padding:0.6rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-sm);font-size:0.75rem;color:var(--green-light)">
        ✅ All spam filters are operating normally
      </div>
    </div>

  </div>
</div>

<!-- ── Reply Modal ─────────────────────────────── -->
<div class="modal-overlay" id="replyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center">
  <div class="modal-content" style="width:100%;max-width:560px;margin:0 1rem">
    <div class="modal-header">
      <h3>💬 Reply to Comment</h3>
      <button class="modal-close" onclick="closeReplyModal()">×</button>
    </div>
    <div style="padding:1.25rem">
      <input type="hidden" id="replyCommentId" value="">
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Platform</label>
        <select class="form-select" id="replyPlatform">
          <option value="linkedin">LinkedIn</option>
          <option value="instagram">Instagram</option>
          <option value="tiktok">TikTok</option>
          <option value="twitter">Twitter / X</option>
          <option value="facebook">Facebook</option>
          <option value="youtube">YouTube</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label" style="display:flex;justify-content:space-between">
          <span>Your Reply</span>
          <span id="replyCharCount" style="font-size:0.72rem;color:var(--text-muted)">0 / 280</span>
        </label>
        <textarea class="form-textarea" id="replyText" rows="4" placeholder="Type your reply here..." style="resize:vertical" oninput="updateCharCount()"></textarea>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem">
        <?php foreach ($templates as $tpl): ?>
        <button class="btn btn-ghost btn-sm" style="font-size:0.72rem" onclick="insertTemplate(<?= htmlspecialchars(json_encode($tpl['text']), ENT_QUOTES) ?>)">
          <?= htmlspecialchars($tpl['name']) ?>
        </button>
        <?php endforeach ?>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeReplyModal()">Cancel</button>
      <button class="btn btn-primary" onclick="sendReply()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send Reply
      </button>
    </div>
  </div>
</div>

<!-- Toast Notification -->
<div id="communityToast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:2000;display:none">
  <div style="background:var(--navy-mid);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:0.75rem 1.25rem;font-size:0.85rem;box-shadow:var(--shadow-md)" id="communityToastText"></div>
</div>

<script>
(function() {
  // ── Filter Tabs ──────────────────────────────
  const filterBtns = document.querySelectorAll('.filter-tab');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      document.querySelectorAll('.comment-card').forEach(card => {
        const sentiment = card.dataset.sentiment;
        const status    = card.dataset.status;
        if (filter === 'all') {
          card.style.display = '';
        } else if (filter === 'escalated') {
          card.style.display = (status === 'escalated') ? '' : 'none';
        } else {
          card.style.display = (sentiment === filter) ? '' : 'none';
        }
      });
    });
  });

  // ── Toggle AI Reply Block ─────────────────────
  window.toggleAiReply = function(id) {
    const block = document.getElementById('aiReply' + id);
    if (block) block.classList.toggle('open');
  };

  // ── Open Reply Modal ──────────────────────────
  window.openReplyModal = function(id, platform) {
    document.getElementById('replyCommentId').value = id;
    const sel = document.getElementById('replyPlatform');
    if (sel) sel.value = platform || 'linkedin';
    document.getElementById('replyText').value = '';
    updateCharCount();
    const modal = document.getElementById('replyModal');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('replyText').focus(), 50);
  };

  window.closeReplyModal = function() {
    document.getElementById('replyModal').style.display = 'none';
  };

  document.getElementById('replyModal').addEventListener('click', function(e) {
    if (e.target === this) closeReplyModal();
  });

  // ── Edit AI Reply (populate modal) ───────────
  window.editReply = function(id, text) {
    const card = document.querySelector('[data-comment-id="' + id + '"]');
    const platform = card ? card.querySelector('.platform-pill') : null;
    openReplyModal(id, card ? card.closest('[data-comment-id]')?.dataset?.platform : 'linkedin');
    document.getElementById('replyText').value = text;
    updateCharCount();
  };

  // ── Send AI Reply directly ────────────────────
  window.sendAiReply = function(id, platform) {
    const block = document.getElementById('aiReply' + id);
    const text  = block ? block.querySelector('p').textContent : '';
    sendCommentAction('/api/community/reply', { comment_id: id, platform, text }, 'Reply sent successfully!');
    const card = document.querySelector('[data-comment-id="' + id + '"]');
    if (card) card.style.opacity = '0.5';
  };

  // ── Send Custom Reply ─────────────────────────
  window.sendReply = function() {
    const id       = document.getElementById('replyCommentId').value;
    const platform = document.getElementById('replyPlatform').value;
    const text     = document.getElementById('replyText').value.trim();
    if (!text) { showToast('Please enter a reply.'); return; }
    sendCommentAction('/api/community/reply', { comment_id: id, platform, text }, 'Reply sent!');
    closeReplyModal();
    const card = document.querySelector('[data-comment-id="' + id + '"]');
    if (card) card.style.opacity = '0.5';
  };

  // ── Mark Spam ─────────────────────────────────
  window.markSpam = function(id) {
    sendCommentAction('/api/community/spam', { comment_id: id }, 'Marked as spam.');
    const card = document.querySelector('[data-comment-id="' + id + '"]');
    if (card) { card.dataset.sentiment = 'spam'; card.style.opacity = '0.45'; }
  };

  // ── Escalate ──────────────────────────────────
  window.escalateComment = function(id) {
    sendCommentAction('/api/community/escalate', { comment_id: id }, 'Comment escalated to human agent.');
    const card = document.querySelector('[data-comment-id="' + id + '"]');
    if (card) { card.dataset.status = 'escalated'; card.style.borderLeftColor = 'var(--red)'; }
  };

  // ── Dismiss ───────────────────────────────────
  window.dismissComment = function(id) {
    const card = document.querySelector('[data-comment-id="' + id + '"]');
    if (card) {
      card.style.transition = 'opacity 0.3s, height 0.3s';
      card.style.opacity = '0';
      card.style.overflow = 'hidden';
      setTimeout(() => { card.style.height = card.offsetHeight + 'px'; setTimeout(() => { card.style.height = '0'; card.style.margin = '0'; card.style.padding = '0'; setTimeout(() => card.remove(), 300); }, 10); }, 300);
    }
  };

  // ── Auto Reply All ────────────────────────────
  window.autoReplyAll = function() {
    const btn = document.getElementById('autoReplyBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Processing...';
    sendCommentAction('/api/community/auto-reply-all', {}, 'Auto-reply completed for all pending comments!');
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m3 10 3 3 3-3"/><path d="M6 13V7a4 4 0 014-4h7"/><path d="m21 14-3-3-3 3"/><path d="M18 11v6a4 4 0 01-4 4H7"/></svg> Auto-Reply All';
    }, 3000);
  };

  // ── Template copy / insert ────────────────────
  window.copyTemplate = function(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Template copied to clipboard!'));
  };
  window.insertTemplate = function(text) {
    const ta = document.getElementById('replyText');
    ta.value = text;
    updateCharCount();
    ta.focus();
  };

  // ── Char counter ──────────────────────────────
  window.updateCharCount = function() {
    const ta    = document.getElementById('replyText');
    const count = document.getElementById('replyCharCount');
    if (ta && count) count.textContent = ta.value.length + ' / 280';
  };

  // ── Fetch helper ──────────────────────────────
  function sendCommentAction(url, body, successMsg) {
    fetch(url, {
      method : 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
      body   : JSON.stringify(body),
    })
    .then(r => r.json())
    .then(d => showToast(d.message || successMsg))
    .catch(() => showToast(successMsg)); // Optimistic UI
  }

  // ── Toast ─────────────────────────────────────
  window.showToast = function(msg) {
    const t = document.getElementById('communityToast');
    document.getElementById('communityToastText').textContent = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
  };
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
