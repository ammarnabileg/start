<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Interview — <?= htmlspecialchars($jobTitle ?? 'Position') ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#0a0a14;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;font-size:15px}
.room{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
.room-sidebar{background:#11111e;border-right:1px solid rgba(79,70,229,.2);padding:20px;display:flex;flex-direction:column;gap:16px}
.room-brand{font-size:1.2rem;font-weight:800;background:linear-gradient(135deg,#4f46e5,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:8px}
.room-info{background:rgba(79,70,229,.08);border:1px solid rgba(79,70,229,.15);border-radius:10px;padding:14px}
.info-label{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;font-weight:600}
.info-value{font-size:.875rem;color:#e2e8f0;font-weight:600}
.avatar-box{background:linear-gradient(135deg,#1a1a2e,#16213e);border:1px solid rgba(79,70,229,.2);border-radius:12px;padding:20px;text-align:center;flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px}
.avatar-emoji{font-size:4rem}
.avatar-name{font-weight:700;color:#e2e8f0;font-size:1rem}
.avatar-role{font-size:.78rem;color:#64748b}
.speaking-indicator{display:flex;align-items:center;gap:4px;justify-content:center;height:20px}
.speaking-bar{width:3px;background:#4f46e5;border-radius:2px;animation:none}
.speaking-bar.active{animation:speakPulse .5s ease infinite alternate}
.speaking-bar:nth-child(2){animation-delay:.1s;height:12px}
.speaking-bar:nth-child(3){animation-delay:.2s;height:18px}
.speaking-bar:nth-child(4){animation-delay:.3s;height:10px}
.speaking-bar:nth-child(5){animation-delay:.4s;height:16px}
@keyframes speakPulse{0%{transform:scaleY(.4);opacity:.5}100%{transform:scaleY(1);opacity:1}}
.progress-info{font-size:.78rem;color:#64748b;text-align:center}
.room-main{display:flex;flex-direction:column}
.chat-area{flex:1;overflow-y:auto;padding:24px;display:flex;flex-direction:column;gap:16px;max-height:calc(100vh - 140px)}
.msg{display:flex;gap:12px;align-items:flex-start;max-width:75%;animation:msgIn .3s ease}
@keyframes msgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.msg-ai{align-self:flex-start}
.msg-user{align-self:flex-end;flex-direction:row-reverse}
.msg-bubble{padding:12px 16px;border-radius:14px;font-size:.9rem;line-height:1.6}
.msg-ai .msg-bubble{background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:4px 14px 14px 14px}
.msg-user .msg-bubble{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-radius:14px 4px 14px 14px}
.msg-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;background:#1a1a2e;border:1px solid rgba(79,70,229,.2)}
.msg-time{font-size:.7rem;color:#475569;margin-top:4px;padding-left:4px}
.msg-user .msg-time{text-align:right;padding-right:4px;padding-left:0}
.typing-indicator{display:flex;gap:4px;align-items:center;padding:8px 0}
.typing-dot{width:7px;height:7px;border-radius:50%;background:#4f46e5;animation:typingBounce 1.2s ease infinite}
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes typingBounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-8px)}}
.input-area{border-top:1px solid rgba(79,70,229,.15);padding:16px 24px;background:#0a0a14;display:flex;gap:12px;align-items:flex-end}
.input-wrap{flex:1;position:relative}
.chat-input{width:100%;background:#11111e;border:1px solid rgba(79,70,229,.3);border-radius:12px;padding:12px 48px 12px 16px;color:#e2e8f0;font-size:.9rem;font-family:inherit;resize:none;min-height:44px;max-height:120px;outline:none;line-height:1.5}
.chat-input:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.15)}
.send-btn{width:44px;height:44px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:10px;color:#fff;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;transition:opacity .15s;flex-shrink:0}
.send-btn:hover{opacity:.9}
.send-btn:disabled{opacity:.4;cursor:not-allowed}
.char-count{position:absolute;right:10px;bottom:8px;font-size:.7rem;color:#475569}
.complete-banner{background:linear-gradient(135deg,rgba(34,197,94,.1),rgba(20,184,166,.1));border:1px solid rgba(34,197,94,.3);border-radius:12px;padding:20px;text-align:center;margin:16px}
.complete-title{font-size:1.2rem;font-weight:700;color:#4ade80;margin-bottom:8px}
.complete-sub{color:#94a3b8;font-size:.875rem}
</style>
</head>
<body>
<div class="room">
    <!-- Sidebar -->
    <div class="room-sidebar">
        <div class="room-brand">🤖 AI Interview</div>

        <div class="room-info">
            <div class="info-label">Position</div>
            <div class="info-value"><?= htmlspecialchars($jobTitle ?? '—') ?></div>
        </div>

        <?php if ($guestName ?? ''): ?>
        <div class="room-info">
            <div class="info-label">Candidate</div>
            <div class="info-value"><?= htmlspecialchars($guestName) ?></div>
        </div>
        <?php endif; ?>

        <div class="avatar-box">
            <div class="avatar-emoji">🤖</div>
            <div class="avatar-name"><?= htmlspecialchars($avatar['name'] ?? 'Alex') ?></div>
            <div class="avatar-role">AI Interviewer</div>
            <div class="speaking-indicator" id="speakingIndicator">
                <div class="speaking-bar" style="height:8px"></div>
                <div class="speaking-bar"></div>
                <div class="speaking-bar"></div>
                <div class="speaking-bar"></div>
                <div class="speaking-bar" style="height:8px"></div>
            </div>
        </div>

        <div class="progress-info">
            Question <span id="qNum">1</span> of <?= (int)($totalQuestions ?? 10) ?>
        </div>
    </div>

    <!-- Main chat area -->
    <div class="room-main">
        <div class="chat-area" id="chatArea">
            <?php foreach ($messages ?? [] as $msg): ?>
            <div class="msg msg-<?= $msg['role'] === 'interviewer' ? 'ai' : 'user' ?>">
                <div class="msg-avatar"><?= $msg['role'] === 'interviewer' ? '🤖' : '👤' ?></div>
                <div>
                    <div class="msg-bubble"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                    <div class="msg-time"><?= $msg['created_at'] ? date('g:i A', strtotime($msg['created_at'])) : '' ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (($interview['status'] ?? '') === 'completed'): ?>
        <div class="complete-banner">
            <div class="complete-title">✅ Interview Completed</div>
            <div class="complete-sub">Thank you for completing the interview. Your responses have been recorded and will be reviewed by our team.</div>
        </div>
        <?php else: ?>
        <div class="input-area">
            <div class="input-wrap">
                <textarea class="chat-input" id="chatInput" placeholder="Type your response here…" rows="1"
                    onkeydown="handleKey(event)" oninput="autoResize(this);updateCharCount(this)"></textarea>
                <span class="char-count" id="charCount">0</span>
            </div>
            <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send (Ctrl+Enter)">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
var INTERVIEW_TOKEN = '<?= htmlspecialchars($interview['token'] ?? '') ?>';
var MESSAGE_COUNT = <?= count($messages ?? []) ?>;
var IS_COMPLETE = <?= json_encode(($interview['status'] ?? '') === 'completed') ?>;

function scrollToBottom() {
    var ca = document.getElementById('chatArea');
    if (ca) ca.scrollTop = ca.scrollHeight;
}
scrollToBottom();

function handleKey(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function updateCharCount(el) {
    var cc = document.getElementById('charCount');
    if (cc) cc.textContent = el.value.length;
}

function addMessage(role, content) {
    var ca = document.getElementById('chatArea');
    var div = document.createElement('div');
    div.className = 'msg msg-' + (role === 'interviewer' ? 'ai' : 'user');
    div.innerHTML = '<div class="msg-avatar">' + (role === 'interviewer' ? '🤖' : '👤') + '</div>' +
        '<div><div class="msg-bubble">' + content.replace(/\n/g, '<br>') + '</div>' +
        '<div class="msg-time">Just now</div></div>';
    ca.appendChild(div);
    scrollToBottom();
    MESSAGE_COUNT++;
    var qn = document.getElementById('qNum');
    if (qn) qn.textContent = Math.ceil(MESSAGE_COUNT / 2);
}

function setLoading(yes) {
    var btn = document.getElementById('sendBtn');
    var inp = document.getElementById('chatInput');
    if (btn) btn.disabled = yes;
    if (inp) inp.disabled = yes;

    var si = document.querySelectorAll('.speaking-bar');
    si.forEach(function(b) { b.classList.toggle('active', yes); });
}

function showTyping() {
    var ca = document.getElementById('chatArea');
    var div = document.createElement('div');
    div.id = '_typing';
    div.className = 'msg msg-ai';
    div.innerHTML = '<div class="msg-avatar">🤖</div><div><div class="msg-bubble"><div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div></div></div>';
    ca.appendChild(div);
    scrollToBottom();
}

function removeTyping() {
    var t = document.getElementById('_typing');
    if (t) t.remove();
}

function sendMessage() {
    var inp = document.getElementById('chatInput');
    var content = (inp ? inp.value : '').trim();
    if (!content || IS_COMPLETE) return;

    addMessage('candidate', content);
    if (inp) { inp.value = ''; inp.style.height = ''; }
    var cc = document.getElementById('charCount');
    if (cc) cc.textContent = '0';

    setLoading(true);
    showTyping();

    fetch('/interview/' + INTERVIEW_TOKEN + '/message', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({content: content})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        removeTyping();
        setLoading(false);
        if (res.success && res.ai_response) {
            addMessage('interviewer', res.ai_response);
            if (res.completed) {
                IS_COMPLETE = true;
                setTimeout(function() { location.reload(); }, 1500);
            }
        } else {
            addMessage('interviewer', 'Sorry, there was an issue. Please try again.');
        }
    })
    .catch(function() {
        removeTyping();
        setLoading(false);
        addMessage('interviewer', 'Connection error. Please check your internet and try again.');
    });
}

// Auto-start if no messages yet
<?php if (empty($messages)): ?>
window.addEventListener('DOMContentLoaded', function() {
    setLoading(true);
    showTyping();
    fetch('/interview/' + INTERVIEW_TOKEN + '/start', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: '{}'
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        removeTyping();
        setLoading(false);
        if (res.success && res.opening_message) {
            addMessage('interviewer', res.opening_message);
        }
    })
    .catch(function() {
        removeTyping();
        setLoading(false);
    });
});
<?php endif; ?>
</script>
</body>
</html>
