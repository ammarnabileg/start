<?php
// Interview room — standalone page (no layout)
// Variables: $interview, $token, $candidate, $job, $firstQuestion
$interviewType  = $interview['interview_type'] ?? 'text'; // text | voice | video
$timeLimit      = (int)($interview['time_limit_minutes'] ?? 30);
$totalQuestions = (int)($interview['total_questions'] ?? 10);
$currentQ       = (int)($interview['current_question'] ?? 1);
$jobTitle       = htmlspecialchars($job['title'] ?? 'Position');
$companyName    = htmlspecialchars($job['company_name'] ?? $job['company'] ?? 'Company');
$candidateName  = htmlspecialchars($candidate['full_name'] ?? $candidate['name'] ?? 'Candidate');
$safeToken      = htmlspecialchars($token ?? '');
$firstQ         = htmlspecialchars($firstQuestion ?? 'Tell me about yourself and why you are interested in this role.');
$interviewId    = (int)($interview['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $jobTitle ?> — AI Interview</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { font-family: 'Inter', sans-serif; }
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 3px; }

/* Typing animation dots */
@keyframes bounce { 0%,80%,100%{transform:scale(0.6);opacity:.4} 40%{transform:scale(1);opacity:1} }
.typing-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#9CA3AF; animation:bounce 1.2s infinite; }
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}

/* Voice pulse */
@keyframes pulse-ring { 0%{transform:scale(.95);box-shadow:0 0 0 0 rgba(124,58,237,.6)} 70%{transform:scale(1);box-shadow:0 0 0 18px rgba(124,58,237,0)} 100%{transform:scale(.95);box-shadow:0 0 0 0 rgba(124,58,237,0)} }
.pulse-mic { animation: pulse-ring 1.5s ease-in-out infinite; }

/* Score counter animation */
@keyframes countUp { from{opacity:0;transform:scale(.5)} to{opacity:1;transform:scale(1)} }
.score-anim { animation: countUp .5s cubic-bezier(.34,1.56,.64,1) forwards; }

/* Message slide-in */
@keyframes msgIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
.msg-in { animation: msgIn .25s ease forwards; }

/* Waveform bars */
@keyframes wave { 0%,100%{height:6px} 50%{height:28px} }
.wave-bar { width:4px; background:#7C3AED; border-radius:2px; display:inline-block; animation:wave 1.2s ease-in-out infinite; }

/* Completion overlay */
#completion-screen { display:none; }
#completion-screen.visible { display:flex; }

/* Sidebar collapse */
#progress-sidebar { transition: transform .3s ease, opacity .3s ease; }
</style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex flex-col" id="interview-body">

<!-- Warn before leaving -->
<script>
window.addEventListener('beforeunload', function(e) {
  if (window._interviewActive) {
    e.preventDefault(); e.returnValue = 'Your interview is in progress. Are you sure you want to leave?';
  }
});
window._interviewActive = true;
</script>

<!-- ═══════════════════════════════════════ TOP BAR ═══════════════════════════════════════ -->
<header class="h-14 bg-gray-900 border-b border-gray-800 flex items-center px-4 gap-4 flex-shrink-0 z-10">
  <!-- Left: Job info -->
  <div class="flex items-center gap-3 flex-1 min-w-0">
    <div class="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center flex-shrink-0">
      <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
    </div>
    <div class="min-w-0">
      <div class="text-sm font-semibold text-white truncate"><?= $jobTitle ?></div>
      <div class="text-xs text-gray-400 truncate"><?= $companyName ?></div>
    </div>
  </div>

  <!-- Center: Timer -->
  <div class="flex items-center gap-2 bg-gray-800 rounded-xl px-4 py-1.5">
    <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span id="timer-display" class="text-lg font-bold tabular-nums text-white"></span>
  </div>

  <!-- Right: Question counter + sidebar toggle -->
  <div class="flex items-center gap-3 flex-1 justify-end">
    <div class="text-right">
      <div class="text-xs text-gray-400">Progress</div>
      <div id="question-counter" class="text-sm font-semibold text-white">Question <span id="q-current"><?= $currentQ ?></span> of <?= $totalQuestions ?></div>
    </div>
    <button onclick="toggleSidebar()" class="w-8 h-8 bg-gray-800 hover:bg-gray-700 rounded-lg flex items-center justify-center transition-colors" title="Toggle sidebar">
      <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
    </button>
  </div>
</header>

<!-- ═══════════════════════════════════════ MAIN AREA ═══════════════════════════════════════ -->
<div class="flex flex-1 overflow-hidden relative">

  <!-- ─── PROGRESS SIDEBAR ─── -->
  <aside id="progress-sidebar" class="w-64 bg-gray-900 border-r border-gray-800 flex-shrink-0 flex flex-col p-4 overflow-y-auto">
    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Interview Progress</h3>

    <!-- Completion ring -->
    <div class="flex flex-col items-center mb-5">
      <div class="relative w-24 h-24">
        <svg class="w-24 h-24 -rotate-90" viewBox="0 0 36 36">
          <circle cx="18" cy="18" r="15.5" fill="none" stroke="#374151" stroke-width="2.5"/>
          <circle id="progress-ring" cx="18" cy="18" r="15.5" fill="none" stroke="#7C3AED" stroke-width="2.5"
            stroke-dasharray="97.4 97.4" stroke-dashoffset="97.4" stroke-linecap="round"/>
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
          <span id="completion-pct" class="text-lg font-bold text-white">0%</span>
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-2">Completion</p>
    </div>

    <!-- Current topic -->
    <div class="bg-gray-800 rounded-xl p-3 mb-4">
      <div class="text-xs text-gray-400 mb-1">Current Topic</div>
      <div id="current-topic" class="text-sm font-medium text-white">Introduction</div>
    </div>

    <!-- Time remaining bar -->
    <div class="mb-4">
      <div class="flex justify-between text-xs text-gray-400 mb-1.5">
        <span>Time remaining</span>
        <span id="sidebar-time"></span>
      </div>
      <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
        <div id="time-bar" class="h-full bg-violet-500 rounded-full transition-all duration-1000" style="width:100%"></div>
      </div>
    </div>

    <!-- Interview type badge -->
    <div class="mt-auto">
      <div class="flex items-center gap-2 bg-gray-800 rounded-xl p-3">
        <?php if ($interviewType === 'video'): ?>
        <div class="w-7 h-7 bg-violet-900 rounded-lg flex items-center justify-center">
          <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        </div>
        <div><div class="text-xs font-medium text-white">Video Interview</div><div class="text-xs text-gray-400">AI Avatar</div></div>
        <?php elseif ($interviewType === 'voice'): ?>
        <div class="w-7 h-7 bg-amber-900 rounded-lg flex items-center justify-center">
          <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
        </div>
        <div><div class="text-xs font-medium text-white">Voice Interview</div><div class="text-xs text-gray-400">Speech-to-text</div></div>
        <?php else: ?>
        <div class="w-7 h-7 bg-blue-900 rounded-lg flex items-center justify-center">
          <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </div>
        <div><div class="text-xs font-medium text-white">Text Interview</div><div class="text-xs text-gray-400">Chat format</div></div>
        <?php endif; ?>
      </div>

      <div class="mt-3 text-center">
        <div class="text-xs text-gray-500">Candidate</div>
        <div class="text-sm font-medium text-gray-300"><?= $candidateName ?></div>
      </div>
    </div>
  </aside>

  <!-- ─── CONTENT AREA ─── -->
  <main class="flex-1 flex flex-col overflow-hidden">

  <?php if ($interviewType === 'text'): ?>
  <!-- ══════════ MODE 1: TEXT CHAT ══════════ -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Messages -->
    <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4">
      <!-- AI greeting -->
      <div class="flex items-start gap-3 msg-in" id="msg-ai-0">
        <div class="w-8 h-8 bg-violet-700 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
        </div>
        <div class="flex-1 max-w-2xl">
          <div class="text-xs text-gray-500 mb-1">AI Interviewer</div>
          <div class="bg-gray-800 rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-gray-100 leading-relaxed">
            Hi <?= $candidateName ?>! I'm your AI interviewer for the <strong><?= $jobTitle ?></strong> position at <strong><?= $companyName ?></strong>.
            This interview will take approximately <?= $timeLimit ?> minutes. Please answer naturally and thoroughly. Ready to begin?
          </div>
          <div class="text-xs text-gray-600 mt-1">Just now</div>
        </div>
      </div>

      <div class="flex items-start gap-3 msg-in" id="msg-ai-1">
        <div class="w-8 h-8 bg-violet-700 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
        </div>
        <div class="flex-1 max-w-2xl">
          <div class="text-xs text-gray-500 mb-1">AI Interviewer · Question 1</div>
          <div class="bg-gray-800 rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-gray-100 leading-relaxed" id="first-question-text">
            <?= $firstQ ?>
          </div>
          <div class="text-xs text-gray-600 mt-1">Just now</div>
        </div>
      </div>
    </div>

    <!-- Typing indicator (hidden by default) -->
    <div id="typing-indicator" class="hidden px-4 pb-2">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-violet-700 rounded-full flex items-center justify-center flex-shrink-0">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18"/></svg>
        </div>
        <div class="bg-gray-800 rounded-2xl rounded-tl-sm px-4 py-3">
          <span class="typing-dot"></span>
          <span class="typing-dot" style="margin:0 2px"></span>
          <span class="typing-dot"></span>
        </div>
      </div>
    </div>

    <!-- Input area -->
    <div class="border-t border-gray-800 bg-gray-900 p-4">
      <div class="flex items-end gap-3 max-w-4xl mx-auto">
        <div class="flex-1 bg-gray-800 rounded-2xl border border-gray-700 focus-within:border-violet-500 transition-colors">
          <textarea
            id="chat-input"
            placeholder="Type your answer here... (Shift+Enter for new line, Enter to send)"
            rows="2"
            class="w-full bg-transparent text-sm text-gray-100 placeholder-gray-500 px-4 py-3 resize-none focus:outline-none"
            style="max-height:160px"
          ></textarea>
        </div>
        <!-- Voice-to-text btn -->
        <button id="stt-btn" onclick="toggleSTT()" title="Voice input"
          class="w-11 h-11 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-full flex items-center justify-center transition-colors flex-shrink-0">
          <svg id="stt-icon" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
        </button>
        <!-- Send btn -->
        <button id="send-btn" onclick="sendMessage()"
          class="w-11 h-11 bg-violet-600 hover:bg-violet-700 rounded-full flex items-center justify-center transition-colors flex-shrink-0 disabled:opacity-50">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
        </button>
      </div>
      <p class="text-xs text-gray-600 text-center mt-2">Answer thoroughly — the AI will follow up based on your responses</p>
    </div>
  </div>

  <?php elseif ($interviewType === 'voice'): ?>
  <!-- ══════════ MODE 2: VOICE ══════════ -->
  <div class="flex-1 flex flex-col items-center justify-center p-6 gap-6">
    <!-- Question display -->
    <div class="w-full max-w-2xl bg-gray-800 rounded-2xl p-6 text-center">
      <div class="text-xs text-violet-400 font-semibold uppercase tracking-wider mb-2">Current Question</div>
      <p id="voice-question-text" class="text-base text-gray-100 leading-relaxed"><?= $firstQ ?></p>
    </div>

    <!-- Waveform canvas -->
    <div class="w-full max-w-sm h-16 flex items-center justify-center gap-1" id="waveform-container">
      <?php for ($i = 0; $i < 20; $i++): ?>
      <div class="wave-bar" style="height:6px;animation-delay:<?= $i * 0.06 ?>s;opacity:.4" id="wave-<?= $i ?>"></div>
      <?php endfor; ?>
    </div>

    <!-- Mic button -->
    <div class="relative flex items-center justify-center">
      <div id="pulse-outer" class="absolute w-32 h-32 rounded-full bg-violet-600/20 hidden"></div>
      <button id="mic-btn" onclick="toggleVoiceRecording()"
        class="relative w-24 h-24 bg-violet-600 hover:bg-violet-700 rounded-full flex items-center justify-center shadow-2xl shadow-violet-900/50 transition-all duration-200">
        <svg id="mic-icon" class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
      </button>
    </div>

    <!-- Status label -->
    <div id="voice-status" class="text-sm text-gray-400 font-medium">Tap the microphone to begin speaking</div>

    <!-- Push-to-talk toggle -->
    <div class="flex items-center gap-3 text-sm text-gray-400">
      <span>Push-to-Talk</span>
      <button id="ptt-toggle" onclick="togglePTT()"
        class="relative w-11 h-6 bg-gray-700 rounded-full transition-colors">
        <span id="ptt-knob" class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"></span>
      </button>
      <span>Auto-detect</span>
    </div>

    <!-- Transcript -->
    <div class="w-full max-w-2xl">
      <div class="text-xs text-gray-500 mb-2 font-medium uppercase tracking-wider">Transcript</div>
      <div id="voice-transcript" class="bg-gray-800 rounded-xl p-4 min-h-20 text-sm text-gray-300 leading-relaxed">
        <span class="text-gray-600 italic">Your spoken answer will appear here...</span>
      </div>
      <div class="mt-3 flex justify-end">
        <button id="voice-submit-btn" onclick="submitVoiceAnswer()" disabled
          class="bg-violet-600 hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-5 py-2 rounded-full text-sm font-medium transition-colors">
          Submit Answer →
        </button>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ══════════ MODE 3: VIDEO ══════════ -->
  <div class="flex-1 flex overflow-hidden">
    <!-- Avatar area (60%) -->
    <div class="flex-[3] flex flex-col bg-black relative">
      <div id="heygen-container" class="flex-1 relative flex items-center justify-center">
        <!-- Loading state -->
        <div id="heygen-loading" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-950 z-10">
          <div class="w-16 h-16 border-4 border-violet-600 border-t-transparent rounded-full animate-spin mb-4"></div>
          <p class="text-gray-300 text-sm">Connecting to AI Avatar...</p>
        </div>
        <!-- HeyGen iframe/video injected here by JS -->
        <div id="heygen-video-wrapper" class="w-full h-full hidden"></div>
      </div>
      <!-- Subtitles -->
      <div id="subtitle-bar" class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent px-6 pb-4 pt-8">
        <p id="subtitle-text" class="text-white text-base text-center font-medium leading-snug"><?= $firstQ ?></p>
      </div>
    </div>

    <!-- Candidate cam (40%) -->
    <div class="flex-[2] flex flex-col bg-gray-900 border-l border-gray-800">
      <div class="relative flex-1 bg-gray-950 flex items-center justify-center">
        <video id="candidate-video" autoplay muted playsinline
          class="w-full h-full object-cover opacity-90"></video>
        <!-- Cam off placeholder -->
        <div id="cam-placeholder" class="absolute inset-0 flex flex-col items-center justify-center hidden">
          <div class="w-20 h-20 rounded-full bg-gray-800 flex items-center justify-center mb-3">
            <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          </div>
          <p class="text-gray-500 text-sm">Camera off</p>
        </div>
        <!-- Name label -->
        <div class="absolute bottom-2 left-2 bg-black/60 rounded-lg px-2 py-1 text-xs text-white font-medium"><?= $candidateName ?></div>
        <!-- Recording indicator -->
        <div id="rec-indicator" class="absolute top-2 right-2 flex items-center gap-1.5 bg-red-600 rounded-full px-2 py-0.5 hidden">
          <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
          <span class="text-xs text-white font-medium">REC</span>
        </div>
      </div>

      <!-- Video controls -->
      <div class="p-3 border-t border-gray-800 flex items-center justify-center gap-3">
        <button id="cam-toggle" onclick="toggleCamera()" title="Toggle camera"
          class="w-10 h-10 bg-gray-800 hover:bg-gray-700 rounded-full flex items-center justify-center transition-colors">
          <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        </button>
        <button id="mic-toggle-video" onclick="toggleMicVideo()" title="Toggle microphone"
          class="w-10 h-10 bg-gray-800 hover:bg-gray-700 rounded-full flex items-center justify-center transition-colors">
          <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
        </button>
        <button onclick="sendVideoResponse()"
          class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
          Done Answering
        </button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  </main>
</div>

<!-- ═══════════════════════════════════════ COMPLETION SCREEN ═══════════════════════════════════════ -->
<div id="completion-screen" class="fixed inset-0 bg-gray-950 z-50 flex-col items-center justify-center p-6">
  <div class="max-w-md w-full text-center">
    <!-- Checkmark animation -->
    <div class="w-24 h-24 bg-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-emerald-900/50">
      <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
    </div>

    <h1 class="text-3xl font-bold text-white mb-2">Interview Complete!</h1>
    <p class="text-gray-400 mb-8">Great job, <?= $candidateName ?>. Your responses have been submitted.</p>

    <!-- Score display -->
    <div class="bg-gray-800 rounded-2xl p-6 mb-6">
      <div class="text-sm text-gray-400 mb-2">Your AI Score</div>
      <div id="final-score" class="text-7xl font-black text-white mb-1">—</div>
      <div class="text-sm text-gray-400">out of 100</div>
      <div id="score-label" class="mt-3 text-base font-semibold text-emerald-400 hidden"></div>
    </div>

    <div id="completion-message" class="text-gray-300 text-sm mb-8 leading-relaxed">
      Thank you for completing the interview. Our team will review your responses and get back to you soon.
    </div>

    <a href="/candidate/applications" id="return-btn"
      class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-8 py-3 rounded-full font-semibold transition-colors">
      Return to My Applications
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
    </a>
  </div>
</div>

<!-- Toast notification -->
<div id="toast" class="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-800 border border-gray-700 text-white text-sm px-5 py-3 rounded-xl shadow-xl hidden z-50"></div>

<script>
// ══════════════════════════════════════════════════════════════
//  INTERVIEW ROOM — JAVASCRIPT
// ══════════════════════════════════════════════════════════════
const TOKEN        = '<?= $safeToken ?>';
const INTERVIEW_ID = <?= $interviewId ?>;
const TIME_LIMIT   = <?= $timeLimit * 60 ?>; // seconds
const TOTAL_Q      = <?= $totalQuestions ?>;
const INTERVIEW_TYPE = '<?= $interviewType ?>';
const API_BASE     = '/api/v1/interviews/' + TOKEN;

let secondsLeft       = TIME_LIMIT;
let currentQuestion   = <?= $currentQ ?>;
let timerInterval     = null;
let autoSaveInterval  = null;
let sttActive         = false;
let sttRecognition    = null;
let voiceRecording    = false;
let pushToTalk        = false;
let mediaStream       = null;
let camOn             = true;
let micOn             = true;
let transcript        = [];
let heygenSession     = null;

// ── TIMER ──────────────────────────────────────────────────────
function formatTime(s) {
  const m = Math.floor(s / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  return m + ':' + sec;
}

function startTimer() {
  const display      = document.getElementById('timer-display');
  const sidebarTime  = document.getElementById('sidebar-time');
  const timeBar      = document.getElementById('time-bar');

  display.textContent = formatTime(secondsLeft);

  timerInterval = setInterval(() => {
    secondsLeft--;
    if (secondsLeft < 0) { secondsLeft = 0; endInterview('time'); return; }

    display.textContent = formatTime(secondsLeft);
    if (sidebarTime) sidebarTime.textContent = formatTime(secondsLeft);

    const pct = (secondsLeft / TIME_LIMIT) * 100;
    if (timeBar) timeBar.style.width = pct + '%';
    if (pct < 20) timeBar.classList.replace('bg-violet-500', 'bg-red-500');
    else if (pct < 40) timeBar.classList.replace('bg-violet-500', 'bg-amber-500');

    // Warn at 5 min
    if (secondsLeft === 300) showToast('⏰ 5 minutes remaining');
    if (secondsLeft === 60)  showToast('⚠ 1 minute remaining!');
  }, 1000);
}

// ── AUTO-SAVE ──────────────────────────────────────────────────
function startAutoSave() {
  autoSaveInterval = setInterval(saveTranscript, 30000);
}

async function saveTranscript() {
  if (!transcript.length) return;
  try {
    await fetch(API_BASE + '/autosave', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ transcript })
    });
  } catch(e) { /* silent */ }
}

// ── SIDEBAR TOGGLE ─────────────────────────────────────────────
function toggleSidebar() {
  const sb = document.getElementById('progress-sidebar');
  sb.classList.toggle('hidden');
}

// ── PROGRESS UPDATE ────────────────────────────────────────────
function updateProgress() {
  const pct   = Math.round(((currentQuestion - 1) / TOTAL_Q) * 100);
  const ring  = document.getElementById('progress-ring');
  const pctEl = document.getElementById('completion-pct');
  const cntEl = document.getElementById('q-current');

  if (ring) {
    const circumference = 97.4;
    ring.style.strokeDashoffset = circumference - (circumference * pct / 100);
  }
  if (pctEl) pctEl.textContent = pct + '%';
  if (cntEl) cntEl.textContent = currentQuestion;
}

// ── TOAST ──────────────────────────────────────────────────────
function showToast(msg, duration = 3000) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), duration);
}

// ══════════════════════════════════════════════════════════════
//  TEXT CHAT MODE
// ══════════════════════════════════════════════════════════════
let messageCount = 1;

function appendMessage(role, text, label = '') {
  const container = document.getElementById('chat-messages');
  if (!container) return;

  const div = document.createElement('div');
  div.className = 'flex items-start gap-3 msg-in ' + (role === 'user' ? 'justify-end' : '');

  const ts = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  if (role === 'user') {
    div.innerHTML = `
      <div class="flex-1 max-w-2xl flex flex-col items-end">
        <div class="text-xs text-gray-500 mb-1">${ts}</div>
        <div class="bg-violet-700 rounded-2xl rounded-tr-sm px-4 py-3 text-sm text-white leading-relaxed">${escHtml(text)}</div>
      </div>
      <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 text-xs font-bold text-gray-300">
        ${escHtml('<?= strtoupper(substr($candidateName, 0, 1)) ?>')}
      </div>`;
  } else {
    div.innerHTML = `
      <div class="w-8 h-8 bg-violet-700 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18"/></svg>
      </div>
      <div class="flex-1 max-w-2xl">
        <div class="text-xs text-gray-500 mb-1">AI Interviewer${label ? ' · ' + label : ''} · ${ts}</div>
        <div class="bg-gray-800 rounded-2xl rounded-tl-sm px-4 py-3 text-sm text-gray-100 leading-relaxed">${escHtml(text)}</div>
      </div>`;
  }

  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
  return div;
}

function showTyping() {
  const t = document.getElementById('typing-indicator');
  if (t) t.classList.remove('hidden');
  const c = document.getElementById('chat-messages');
  if (c) c.scrollTop = c.scrollHeight;
}
function hideTyping() {
  const t = document.getElementById('typing-indicator');
  if (t) t.classList.add('hidden');
}

async function sendMessage() {
  const input = document.getElementById('chat-input');
  if (!input) return;
  const text = input.value.trim();
  if (!text) return;

  input.value = '';
  input.style.height = '';
  document.getElementById('send-btn').disabled = true;

  // Add to transcript
  transcript.push({ role: 'candidate', text, timestamp: Date.now() });
  appendMessage('user', text);

  showTyping();

  try {
    const res = await fetch(API_BASE + '/message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: text, question_number: currentQuestion })
    });

    const data = await res.json();
    hideTyping();

    if (data.success) {
      currentQuestion = data.question_number ?? (currentQuestion + 1);
      updateProgress();

      if (data.current_topic) {
        const el = document.getElementById('current-topic');
        if (el) el.textContent = data.current_topic;
      }

      if (data.interview_complete) {
        transcript.push({ role: 'ai', text: data.message, timestamp: Date.now() });
        appendMessage('ai', data.message);
        setTimeout(() => endInterview('completed', data.score), 2000);
      } else {
        const label = 'Question ' + currentQuestion;
        transcript.push({ role: 'ai', text: data.message, timestamp: Date.now() });
        appendMessage('ai', data.message, label);
      }
    } else {
      showToast('Error: ' + (data.message || 'Could not get response'));
    }
  } catch (e) {
    hideTyping();
    showToast('Connection error — retrying...');
    // Retry once
    setTimeout(() => sendMessage_retry(text), 3000);
  }

  document.getElementById('send-btn').disabled = false;
  input.focus();
}

async function sendMessage_retry(text) {
  try {
    const res = await fetch(API_BASE + '/message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: text, question_number: currentQuestion })
    });
    const data = await res.json();
    hideTyping();
    if (data.success && data.message) {
      appendMessage('ai', data.message, 'Question ' + currentQuestion);
    }
  } catch(e) {
    hideTyping();
    showToast('Could not reach server. Your answer was saved locally.');
  }
}

// Chat input keyboard handling
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('chat-input');
  if (input) {
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
    // Auto-resize
    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    });
  }
});

// ══════════════════════════════════════════════════════════════
//  VOICE MODE
// ══════════════════════════════════════════════════════════════
let voiceTranscriptText = '';

function toggleVoiceRecording() {
  if (voiceRecording) {
    stopVoiceRecording();
  } else {
    startVoiceRecording();
  }
}

function startVoiceRecording() {
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    showToast('Speech recognition not supported in your browser. Please use Chrome.');
    return;
  }

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  sttRecognition = new SpeechRecognition();
  sttRecognition.continuous     = true;
  sttRecognition.interimResults = true;
  sttRecognition.lang           = 'en-US';

  sttRecognition.onstart = () => {
    voiceRecording = true;
    document.getElementById('mic-btn').classList.add('pulse-mic', 'bg-red-600');
    document.getElementById('mic-btn').classList.remove('bg-violet-600');
    document.getElementById('pulse-outer').classList.remove('hidden');
    document.getElementById('voice-status').textContent = 'Listening... speak clearly';
    setWaveActive(true);
  };

  sttRecognition.onresult = (e) => {
    let interim = '';
    voiceTranscriptText = '';
    for (let i = 0; i < e.results.length; i++) {
      if (e.results[i].isFinal) voiceTranscriptText += e.results[i][0].transcript + ' ';
      else interim += e.results[i][0].transcript;
    }
    const el = document.getElementById('voice-transcript');
    if (el) el.innerHTML = (voiceTranscriptText + '<span class="text-gray-500">' + interim + '</span>') || '<span class="text-gray-600 italic">Listening...</span>';
    document.getElementById('voice-submit-btn').disabled = !voiceTranscriptText.trim();
  };

  sttRecognition.onerror = (e) => {
    showToast('Mic error: ' + e.error);
    stopVoiceRecording();
  };

  sttRecognition.onend = () => {
    if (voiceRecording && !pushToTalk) sttRecognition.start(); // continuous
  };

  sttRecognition.start();
}

function stopVoiceRecording() {
  voiceRecording = false;
  if (sttRecognition) sttRecognition.stop();
  const btn = document.getElementById('mic-btn');
  if (btn) { btn.classList.remove('pulse-mic', 'bg-red-600'); btn.classList.add('bg-violet-600'); }
  const outer = document.getElementById('pulse-outer');
  if (outer) outer.classList.add('hidden');
  const status = document.getElementById('voice-status');
  if (status) status.textContent = voiceTranscriptText ? 'Recording stopped. Review and submit.' : 'Tap to record your answer';
  setWaveActive(false);
}

function setWaveActive(active) {
  for (let i = 0; i < 20; i++) {
    const bar = document.getElementById('wave-' + i);
    if (!bar) continue;
    if (active) {
      bar.style.opacity = '1';
      bar.style.animationDuration = (0.6 + Math.random() * 0.8) + 's';
    } else {
      bar.style.opacity = '0.3';
      bar.style.height = '6px';
    }
  }
}

function togglePTT() {
  pushToTalk = !pushToTalk;
  const knob = document.getElementById('ptt-knob');
  const btn  = document.getElementById('ptt-toggle');
  if (pushToTalk) {
    knob.style.transform = 'translateX(20px)';
    btn.classList.replace('bg-gray-700', 'bg-violet-600');
  } else {
    knob.style.transform = 'translateX(0)';
    btn.classList.replace('bg-violet-600', 'bg-gray-700');
  }
}

async function submitVoiceAnswer() {
  const text = voiceTranscriptText.trim();
  if (!text) return;

  stopVoiceRecording();
  document.getElementById('voice-submit-btn').disabled = true;
  document.getElementById('voice-status').textContent = 'Submitting...';

  transcript.push({ role: 'candidate', text, timestamp: Date.now() });

  try {
    const res = await fetch(API_BASE + '/message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: text, question_number: currentQuestion })
    });
    const data = await res.json();

    if (data.success) {
      currentQuestion = data.question_number ?? (currentQuestion + 1);
      updateProgress();
      voiceTranscriptText = '';
      document.getElementById('voice-transcript').innerHTML = '<span class="text-gray-600 italic">Your spoken answer will appear here...</span>';

      if (data.interview_complete) {
        endInterview('completed', data.score);
      } else {
        const qEl = document.getElementById('voice-question-text');
        if (qEl) {
          qEl.style.opacity = '0';
          setTimeout(() => { qEl.textContent = data.message; qEl.style.opacity = '1'; }, 300);
        }
        document.getElementById('voice-status').textContent = 'Question updated. Tap to answer.';
      }
    }
  } catch(e) {
    showToast('Error submitting answer. Please try again.');
    document.getElementById('voice-status').textContent = 'Error — please try again.';
  }
  document.getElementById('voice-submit-btn').disabled = false;
}

// ══════════════════════════════════════════════════════════════
//  STT BUTTON (text chat mode)
// ══════════════════════════════════════════════════════════════
function toggleSTT() {
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    showToast('Speech recognition not supported. Use Chrome.');
    return;
  }

  if (sttActive) {
    sttActive = false;
    if (sttRecognition) sttRecognition.stop();
    document.getElementById('stt-btn').classList.remove('bg-red-700', 'border-red-600');
    document.getElementById('stt-icon').classList.replace('text-red-300', 'text-gray-400');
    return;
  }

  sttActive = true;
  document.getElementById('stt-btn').classList.add('bg-red-700', 'border-red-600');
  document.getElementById('stt-icon').classList.replace('text-gray-400', 'text-red-300');

  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  sttRecognition = new SR();
  sttRecognition.continuous = true;
  sttRecognition.interimResults = true;
  sttRecognition.lang = 'en-US';

  sttRecognition.onresult = (e) => {
    const input = document.getElementById('chat-input');
    if (!input) return;
    let text = '';
    for (let i = 0; i < e.results.length; i++) {
      if (e.results[i].isFinal) text += e.results[i][0].transcript;
    }
    input.value = text;
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 160) + 'px';
  };

  sttRecognition.onerror = () => {
    sttActive = false;
    document.getElementById('stt-btn').classList.remove('bg-red-700', 'border-red-600');
  };

  sttRecognition.start();
}

// ══════════════════════════════════════════════════════════════
//  VIDEO MODE
// ══════════════════════════════════════════════════════════════
async function initHeyGen() {
  try {
    const res = await fetch(API_BASE + '/heygen', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();

    const loading = document.getElementById('heygen-loading');
    const wrapper = document.getElementById('heygen-video-wrapper');

    if (data.success && data.session_url) {
      heygenSession = data.session_id;
      if (wrapper) {
        wrapper.innerHTML = `<iframe src="${data.session_url}" class="w-full h-full border-0" allow="camera;microphone;autoplay" allowfullscreen></iframe>`;
        wrapper.classList.remove('hidden');
      }
      if (loading) loading.classList.add('hidden');
    } else if (data.video_url) {
      // Fallback: direct video stream
      if (wrapper) {
        wrapper.innerHTML = `<video src="${data.video_url}" autoplay class="w-full h-full object-contain" id="heygen-video"></video>`;
        wrapper.classList.remove('hidden');
      }
      if (loading) loading.classList.add('hidden');
    } else {
      if (loading) loading.innerHTML = '<p class="text-gray-400 text-sm">Avatar unavailable — switching to text mode</p>';
    }
  } catch(e) {
    const loading = document.getElementById('heygen-loading');
    if (loading) loading.innerHTML = '<p class="text-red-400 text-sm">Could not connect to avatar</p>';
  }
}

async function initCamera() {
  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
    const vid = document.getElementById('candidate-video');
    const placeholder = document.getElementById('cam-placeholder');
    if (vid) { vid.srcObject = mediaStream; vid.classList.remove('hidden'); }
    if (placeholder) placeholder.classList.add('hidden');
    document.getElementById('rec-indicator').classList.remove('hidden');
  } catch(e) {
    const placeholder = document.getElementById('cam-placeholder');
    if (placeholder) placeholder.classList.remove('hidden');
    showToast('Camera access denied — video will continue without camera');
  }
}

function toggleCamera() {
  if (!mediaStream) return;
  camOn = !camOn;
  mediaStream.getVideoTracks().forEach(t => t.enabled = camOn);
  const btn = document.getElementById('cam-toggle');
  if (btn) btn.classList.toggle('bg-red-700', !camOn);
}

function toggleMicVideo() {
  if (!mediaStream) return;
  micOn = !micOn;
  mediaStream.getAudioTracks().forEach(t => t.enabled = micOn);
  const btn = document.getElementById('mic-toggle-video');
  if (btn) btn.classList.toggle('bg-red-700', !micOn);
}

async function sendVideoResponse() {
  try {
    const res = await fetch(API_BASE + '/message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: '[video_response]', question_number: currentQuestion, session_id: heygenSession })
    });
    const data = await res.json();
    if (data.success) {
      currentQuestion = data.question_number ?? (currentQuestion + 1);
      updateProgress();
      if (data.interview_complete) {
        endInterview('completed', data.score);
      } else if (data.message) {
        const sub = document.getElementById('subtitle-text');
        if (sub) sub.textContent = data.message;
      }
    }
  } catch(e) {
    showToast('Error — please try again');
  }
}

// ══════════════════════════════════════════════════════════════
//  END INTERVIEW
// ══════════════════════════════════════════════════════════════
async function endInterview(reason = 'completed', score = null) {
  clearInterval(timerInterval);
  clearInterval(autoSaveInterval);
  window._interviewActive = false;

  // Final save
  await saveTranscript();

  // Show completion screen
  const screen = document.getElementById('completion-screen');
  if (screen) screen.classList.add('visible');

  // Animate score
  if (score !== null) {
    animateScore(parseInt(score));
  } else {
    // Fetch score from server
    try {
      const r = await fetch(API_BASE + '/score');
      const d = await r.json();
      if (d.score) animateScore(parseInt(d.score));
    } catch(e) { /* no score */ }
  }
}

function animateScore(target) {
  const el = document.getElementById('final-score');
  const label = document.getElementById('score-label');
  if (!el) return;

  let current = 0;
  const step = Math.ceil(target / 60);
  const interval = setInterval(() => {
    current = Math.min(current + step, target);
    el.textContent = current;
    el.classList.add('score-anim');
    if (current >= target) {
      clearInterval(interval);
      // Color and label based on score
      if (target >= 80) {
        el.classList.add('text-emerald-400');
        if (label) { label.textContent = '🌟 Excellent performance!'; label.classList.remove('hidden'); }
      } else if (target >= 60) {
        el.classList.add('text-blue-400');
        if (label) { label.textContent = '👍 Good performance'; label.classList.remove('hidden'); }
      } else if (target >= 40) {
        el.classList.add('text-amber-400');
        if (label) { label.textContent = 'Solid effort'; label.classList.remove('hidden'); }
      } else {
        el.classList.add('text-gray-300');
      }
    }
  }, 30);
}

// ══════════════════════════════════════════════════════════════
//  UTILITY
// ══════════════════════════════════════════════════════════════
function escHtml(s) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(s));
  return d.innerHTML;
}

// ══════════════════════════════════════════════════════════════
//  INIT
// ══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  startTimer();
  startAutoSave();
  updateProgress();

  if (INTERVIEW_TYPE === 'video') {
    initHeyGen();
    initCamera();
  }
});
</script>
</body>
</html>
