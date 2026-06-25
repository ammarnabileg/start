<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= $req->csrf() ?>">
    <title>AI Interview – <?= htmlspecialchars($link['job_title'] ?? 'Interview') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .pulse-ring { animation: pulse-ring 1.8s ease-out infinite; }
        @keyframes pulse-ring {
            0%   { transform: scale(0.8); opacity: 0.8; }
            80%, 100% { transform: scale(1.7); opacity: 0; }
        }
        .speaking-bar { animation: speaking-bar 0.7s ease-in-out infinite alternate; }
        .speaking-bar:nth-child(2) { animation-delay: 0.15s; }
        .speaking-bar:nth-child(3) { animation-delay: 0.3s; }
        .speaking-bar:nth-child(4) { animation-delay: 0.45s; }
        @keyframes speaking-bar { from { height: 8px; } to { height: 24px; } }
        .typing-dot { animation: typing-bounce 1.2s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing-bounce {
            0%, 80%, 100% { opacity: 0.3; transform: translateY(0); }
            40% { opacity: 1; transform: translateY(-5px); }
        }
        #messages { scrollbar-width: thin; scrollbar-color: rgba(99,102,241,0.3) transparent; }
        .msg-enter { animation: msg-slide 0.3s ease-out; }
        @keyframes msg-slide { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="h-full bg-gray-950 text-white flex flex-col overflow-hidden">

<?php
$token       = $token ?? '';
$link        = $link ?? [];
$jobTitle    = htmlspecialchars($link['job_title'] ?? 'Position');
$companyName = htmlspecialchars($link['company_name'] ?? 'Company');
$avatarName  = htmlspecialchars($link['avatar_name'] ?? 'AI Interviewer');
$avatarDesc  = htmlspecialchars($link['avatar_description'] ?? 'Your AI interview companion');
$avatarInit  = strtoupper(substr($link['avatar_name'] ?? 'A', 0, 1));
?>

<!-- Top Bar -->
<header class="flex-shrink-0 bg-gray-900 border-b border-white/10 px-4 sm:px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3 min-w-0">
        <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-white truncate"><?= $companyName ?></p>
            <p class="text-xs text-gray-400 truncate"><?= $jobTitle ?></p>
        </div>
    </div>
    <div class="flex items-center gap-4 flex-shrink-0">
        <div id="progress-pill" class="hidden items-center gap-2 bg-gray-800 rounded-full px-3 py-1 text-xs text-gray-300">
            Question <span id="q-num" class="font-bold text-white">1</span>
            <span class="text-gray-600">of</span>
            <span id="q-total" class="font-bold text-white">?</span>
        </div>
        <div class="flex items-center gap-2">
            <span id="status-dot" class="w-2 h-2 rounded-full bg-gray-600 transition-colors"></span>
            <span id="status-text" class="text-xs text-gray-400">Ready</span>
        </div>
    </div>
</header>

<!-- Body -->
<div class="flex-1 flex overflow-hidden">

    <!-- LEFT sidebar (avatar) -->
    <aside class="hidden lg:flex w-64 xl:w-72 flex-shrink-0 bg-gray-900/60 border-r border-white/10 flex-col p-6 gap-6">
        <div class="flex flex-col items-center gap-4 mt-6">
            <!-- Avatar with pulse ring -->
            <div class="relative flex items-center justify-center w-32 h-32">
                <div id="avatar-ring" class="hidden absolute w-32 h-32 rounded-full bg-indigo-500/25 pulse-ring"></div>
                <div class="w-28 h-28 rounded-full bg-gradient-to-br from-indigo-600 to-purple-700 border-2 border-white/10 flex items-center justify-center text-4xl font-bold shadow-2xl select-none">
                    <?= $avatarInit ?>
                </div>
            </div>

            <!-- Speaking waveform -->
            <div id="speaking-wave" class="hidden items-center gap-1 h-6">
                <div class="speaking-bar w-1.5 rounded-full bg-indigo-400"></div>
                <div class="speaking-bar w-1.5 rounded-full bg-indigo-500"></div>
                <div class="speaking-bar w-1.5 rounded-full bg-indigo-400"></div>
                <div class="speaking-bar w-1.5 rounded-full bg-indigo-500"></div>
            </div>

            <div class="text-center">
                <h3 class="font-semibold text-white"><?= $avatarName ?></h3>
                <p class="text-xs text-gray-400 mt-1"><?= $avatarDesc ?></p>
            </div>
        </div>

        <div class="border-t border-white/10 pt-5 space-y-3 mt-auto">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tips for success</p>
            <?php foreach ([
                'Take time to structure your thoughts',
                'Use specific examples (STAR method)',
                'Be concise and focused',
                'Ask for clarification if unsure',
            ] as $tip): ?>
            <div class="flex items-start gap-2.5 text-xs text-gray-400">
                <svg class="w-3.5 h-3.5 text-indigo-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <?= htmlspecialchars($tip) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- RIGHT main area -->
    <main class="flex-1 flex flex-col overflow-hidden">

        <!-- STATE: Waiting -->
        <div id="state-waiting" class="flex-1 flex flex-col items-center justify-center p-8">
            <div class="w-16 h-16 bg-indigo-600/20 border border-indigo-500/30 rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2 text-center">Ready to begin?</h2>
            <p class="text-gray-400 text-center mb-1">
                You're applying for <span class="text-white font-medium"><?= $jobTitle ?></span>
            </p>
            <p class="text-gray-500 text-sm text-center mb-8 max-w-sm">
                This AI-powered screening takes approximately 10–20 minutes. Find a quiet place, answer naturally, and take your time.
            </p>
            <button id="start-btn" onclick="startInterview()"
                class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white text-base font-semibold rounded-xl transition-all shadow-lg shadow-indigo-900/40">
                Start Interview
            </button>
            <p class="text-xs text-gray-600 mt-4 text-center max-w-xs">
                By proceeding, you consent to this AI-conducted interview being reviewed by the hiring team.
            </p>
        </div>

        <!-- STATE: In Progress -->
        <div id="state-in-progress" class="hidden flex-1 flex flex-col overflow-hidden">

            <!-- Messages -->
            <div id="messages" class="flex-1 overflow-y-auto px-4 sm:px-6 py-6 space-y-4"></div>

            <!-- Typing indicator -->
            <div id="typing-indicator" class="hidden px-4 sm:px-6 pb-2">
                <div class="flex items-center gap-2 text-gray-500 text-xs">
                    <div class="flex gap-1">
                        <div class="typing-dot w-1.5 h-1.5 bg-gray-500 rounded-full"></div>
                        <div class="typing-dot w-1.5 h-1.5 bg-gray-500 rounded-full"></div>
                        <div class="typing-dot w-1.5 h-1.5 bg-gray-500 rounded-full"></div>
                    </div>
                    <?= $avatarName ?> is composing a question…
                </div>
            </div>

            <!-- Input bar -->
            <div class="flex-shrink-0 bg-gray-900/80 border-t border-white/10 p-4">
                <div class="flex items-end gap-3 max-w-3xl mx-auto">
                    <!-- Voice -->
                    <button id="voice-btn" onclick="toggleVoice()" title="Voice input"
                        class="flex-shrink-0 w-10 h-10 rounded-xl bg-gray-800 border border-white/10 hover:bg-gray-700 flex items-center justify-center transition-colors">
                        <svg id="mic-icon" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        </svg>
                    </button>

                    <!-- Textarea -->
                    <textarea id="msg-input"
                        rows="2"
                        placeholder="Type your response… (Enter to send, Shift+Enter for newline)"
                        class="flex-1 bg-gray-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none leading-relaxed"
                        onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>

                    <!-- Send -->
                    <button id="send-btn" onclick="sendMessage()"
                        class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-500 flex items-center justify-center transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </div>
                <p class="text-center text-xs text-gray-700 mt-2">Responses are private and reviewed by the hiring team only.</p>
            </div>
        </div>

        <!-- STATE: Completed -->
        <div id="state-completed" class="hidden flex-1 flex flex-col items-center justify-center p-8 text-center">
            <div class="w-16 h-16 bg-emerald-600/20 border border-emerald-500/30 rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white mb-2">Interview Submitted!</h2>
            <p class="text-gray-400 mb-6">
                Thank you for completing your AI interview for<br>
                <span class="text-white font-semibold"><?= $jobTitle ?></span> at <span class="text-white font-semibold"><?= $companyName ?></span>
            </p>
            <div class="bg-gray-900 border border-white/10 rounded-2xl p-5 max-w-sm text-left space-y-3 mb-8">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">What happens next</p>
                <div class="space-y-2.5">
                    <?php foreach ([
                        'Our team will review your interview responses within 2–3 business days.',
                        'You will receive an email update on your application status.',
                        'If selected, you\'ll be invited for a human interview.',
                    ] as $idx => $step): ?>
                    <div class="flex items-start gap-3 text-sm text-gray-300">
                        <span class="w-5 h-5 rounded-full bg-indigo-700 text-indigo-200 flex items-center justify-center text-xs font-bold flex-shrink-0"><?= $idx + 1 ?></span>
                        <?= htmlspecialchars($step) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="/candidate/applications"
               class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl transition-colors">
                View My Applications
            </a>
        </div>

    </main>
</div>

<script>
const CSRF  = document.querySelector('meta[name=csrf]').content;
const TOKEN = <?= json_encode($token) ?>;
let waiting    = false;
let qNum       = 0;
let qTotal     = 0;
let speechRec  = null;
let recording  = false;

/* ─── State ─── */
function setState(s) {
    ['waiting','in-progress','completed'].forEach(id =>
        document.getElementById('state-' + id).classList.toggle('hidden', id !== s));
}

function setStatus(label, color) {
    document.getElementById('status-text').textContent = label;
    const dot = document.getElementById('status-dot');
    dot.className = `w-2 h-2 rounded-full transition-colors bg-${color}-500`;
}

function setSpeaking(on) {
    document.getElementById('avatar-ring').classList.toggle('hidden', !on);
    const wave = document.getElementById('speaking-wave');
    if (on) wave.classList.replace('hidden', 'flex');
    else    wave.classList.replace('flex', 'hidden');
}

/* ─── Messages ─── */
function appendMsg(role, text) {
    const msgs = document.getElementById('messages');
    const isAI = role === 'ai';
    const wrap = document.createElement('div');
    wrap.className = `flex msg-enter ${isAI ? 'justify-start' : 'justify-end'}`;
    wrap.innerHTML = `
        <div class="max-w-xl ${isAI
            ? 'bg-gray-800 border border-white/10 text-gray-100 rounded-2xl rounded-tl-sm'
            : 'bg-indigo-600 text-white rounded-2xl rounded-tr-sm'} px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap">
            ${esc(text)}
        </div>`;
    msgs.appendChild(wrap);
    msgs.scrollTop = msgs.scrollHeight;
}

function showTyping(show) {
    document.getElementById('typing-indicator').classList.toggle('hidden', !show);
    setSpeaking(show);
}

/* ─── API ─── */
async function startInterview() {
    const btn = document.getElementById('start-btn');
    btn.disabled = true; btn.textContent = 'Starting…';
    setStatus('Connecting', 'yellow');

    try {
        const r = await fetch(`/api/v1/interview/${TOKEN}/start`, {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': CSRF},
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.message || 'Could not start interview');

        if (j.data?.total_questions) {
            qTotal = j.data.total_questions;
            document.getElementById('q-total').textContent = qTotal;
        }

        setState('in-progress');
        setStatus('In progress', 'green');
        document.getElementById('progress-pill').classList.replace('hidden','flex');

        if (j.data?.message) {
            showTyping(true);
            await sleep(900);
            showTyping(false);
            appendMsg('ai', j.data.message);
            qNum = 1;
            updateProgress();
        }
        document.getElementById('msg-input').focus();
    } catch(e) {
        btn.disabled = false; btn.textContent = 'Start Interview';
        setStatus('Error', 'red');
        alert('Could not start the interview: ' + e.message);
    }
}

async function sendMessage() {
    const input = document.getElementById('msg-input');
    const text = input.value.trim();
    if (!text || waiting) return;

    input.value = ''; autoResize(input);
    appendMsg('candidate', text);
    setWaiting(true);
    showTyping(true);

    try {
        const r = await fetch(`/api/v1/interview/${TOKEN}/message`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
            body: JSON.stringify({message: text}),
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.message);

        await sleep(600);
        showTyping(false);
        appendMsg('ai', j.data.message);

        if (j.data.question_number) { qNum = j.data.question_number; updateProgress(); }
        if (j.data.total_questions)  { qTotal = j.data.total_questions; }
        updateProgress();

        if (j.data.is_complete) {
            await sleep(1800);
            setState('completed');
            setStatus('Completed', 'green');
            document.getElementById('progress-pill').classList.add('hidden');
        }
    } catch(e) {
        showTyping(false);
        appendMsg('ai', 'Sorry, there was a technical issue. Please try submitting your response again.');
    }
    setWaiting(false);
    input.focus();
}

function setWaiting(w) {
    waiting = w;
    const btn = document.getElementById('send-btn');
    const inp = document.getElementById('msg-input');
    btn.disabled = inp.disabled = w;
}

function updateProgress() {
    document.getElementById('q-num').textContent   = qNum   || '?';
    document.getElementById('q-total').textContent = qTotal || '?';
}

/* ─── Voice ─── */
function toggleVoice() {
    if (!('SpeechRecognition' in window) && !('webkitSpeechRecognition' in window)) {
        alert('Voice input requires Chrome or a compatible browser.');
        return;
    }
    if (recording) { speechRec?.stop(); return; }

    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    speechRec = new SR();
    speechRec.continuous = true;
    speechRec.interimResults = true;
    speechRec.lang = 'en-US';

    const input = document.getElementById('msg-input');
    const micBtn = document.getElementById('voice-btn');
    const micIcon = document.getElementById('mic-icon');

    speechRec.onstart = () => {
        recording = true;
        micBtn.classList.add('bg-red-700','border-red-500');
        micBtn.classList.remove('bg-gray-800');
        micIcon.setAttribute('class','w-5 h-5 text-white');
    };
    speechRec.onresult = e => {
        let t = '';
        for (let i = e.resultIndex; i < e.results.length; i++) t += e.results[i][0].transcript;
        input.value = t; autoResize(input);
    };
    speechRec.onend = () => {
        recording = false;
        micBtn.classList.remove('bg-red-700','border-red-500');
        micBtn.classList.add('bg-gray-800');
        micIcon.setAttribute('class','w-5 h-5 text-gray-400');
    };
    speechRec.onerror = () => speechRec.onend();
    speechRec.start();
}

/* ─── Helpers ─── */
function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function esc(s) {
    return String(s||'')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

setState('waiting');
setStatus('Ready', 'gray');
</script>
</body>
</html>
