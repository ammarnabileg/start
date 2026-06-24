<?php
/**
 * Interview room — self-contained full HTML page (NOT wrapped in a layout).
 * Token-based, works with no server session. Wired to /assets/js/interview.js
 * which expects these element IDs to exist:
 *   chat-stream, msg-input, send-btn, timer, q-counter, progress-bar, thinking,
 *   mic-btn, voice-transcript, start-screen, start-btn, avatar-video,
 *   text-zone, voice-zone
 *
 * Receives: $token (string).
 */
$__lang = $_COOKIE['lang'] ?? 'en';
$__dir  = $__lang === 'ar' ? 'rtl' : 'ltr';
$__token = (string)($token ?? '');
// The room endpoints are public/session-less; provide a CSRF meta anyway so the
// shared fetch helpers have a value to send (harmless for public endpoints).
$__csrf = '';
if (class_exists('App\\Core\\Request')) {
    $__csrf = \App\Core\Request::csrfToken();
}
?>
<!doctype html>
<html lang="<?= e($__lang) ?>" dir="<?= e($__dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= e($__csrf) ?>">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e(app_lang('interview')) ?> · <?= e(app_lang('app_name')) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        fontFamily: { sans: ['Inter', 'Tajawal', 'Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
        colors: { brand: { DEFAULT: '#7C3AED', dark: '#5B21B6', deep: '#1E1B4B' }, accent: '#FBBF24' },
      } },
    };
  </script>
  <link rel="stylesheet" href="/assets/css/app.css">
  <style>
    /* Room-specific immersive polish (complements app.css .interview-room helpers) */
    .room-bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
    .room-bg .glow { position: absolute; border-radius: 9999px; filter: blur(120px); opacity: .35; }
    .room-bg .g1 { width: 520px; height: 520px; top: -160px; left: -120px; background: #7C3AED; }
    .room-bg .g2 { width: 480px; height: 480px; bottom: -180px; right: -120px; background: #4338CA; opacity: .3; }
    .room-bg .g3 { width: 360px; height: 360px; top: 30%; left: 55%; background: #FBBF24; opacity: .12; }
    .room-shell { position: relative; z-index: 1; }
    .glass { background: rgba(17, 22, 36, .72); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,.08); }
    .glass-soft { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); }
    .chat-scroll { scrollbar-width: thin; }
    .chat-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.18); }
    .room-input { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); color: #f3f4f6; }
    .room-input::placeholder { color: rgba(229,231,235,.45); }
    .room-input:focus { outline: none; border-color: rgba(124,58,237,.8); box-shadow: 0 0 0 3px rgba(124,58,237,.25); }
    .avatar-poster { background: radial-gradient(circle at 50% 35%, #312e81 0%, #0b0f1a 75%); }
    .pulse-ring { animation: ring 2.4s ease-out infinite; }
    @keyframes ring { 0% { box-shadow: 0 0 0 0 rgba(124,58,237,.45);} 70% { box-shadow: 0 0 0 22px rgba(124,58,237,0);} 100% { box-shadow: 0 0 0 0 rgba(124,58,237,0);} }
  </style>
</head>
<body class="interview-room antialiased" data-token="<?= e($__token) ?>">

  <!-- Ambient background -->
  <div class="room-bg" aria-hidden="true">
    <span class="glow g1"></span><span class="glow g2"></span><span class="glow g3"></span>
  </div>

  <div class="room-shell min-h-screen flex flex-col">

    <!-- ============ Header ============ -->
    <header class="glass sticky top-0 z-20">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center gap-3 sm:gap-5">
        <!-- Company logo placeholder -->
        <div class="flex items-center gap-3 min-w-0">
          <div id="company-logo" class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center text-white font-extrabold text-xs shrink-0 shadow-lg">AR</div>
          <div class="min-w-0 leading-tight">
            <p id="job-title" class="text-sm sm:text-[15px] font-semibold text-white truncate"><?= e(app_lang('interview')) ?></p>
            <p id="company-name" class="text-[11px] sm:text-xs text-white/50 truncate"><?= e(app_lang('app_name')) ?></p>
          </div>
        </div>

        <div class="flex-1"></div>

        <!-- Question counter -->
        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full glass-soft text-xs font-semibold text-white/80">
          <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span id="q-counter">Q1 of ~12</span>
        </div>

        <!-- Live timer -->
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full glass-soft text-xs font-semibold text-white/90">
          <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
          <svg class="w-4 h-4 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span id="timer" class="tabular-nums">00:00</span>
        </div>
      </div>

      <!-- Progress bar -->
      <div class="max-w-5xl mx-auto px-4 sm:px-6 pb-3">
        <div class="progress-track">
          <span id="progress-bar" style="width:0%"></span>
        </div>
      </div>
    </header>

    <!-- ============ Main interview area ============ -->
    <main class="flex-1 relative">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 py-5 sm:py-7 h-full">

        <!-- ---- Loading state (replaced after room fetch) ---- -->
        <div id="room-loading" class="flex flex-col items-center justify-center text-center py-24">
          <div class="w-12 h-12 rounded-full border-2 border-white/15 border-t-brand spin"></div>
          <p class="mt-5 text-sm text-white/50"><?= e(app_lang('loading')) ?></p>
        </div>

        <!-- ---- Error / expired / completed state ---- -->
        <div id="room-error" class="hidden flex-col items-center justify-center text-center py-20">
          <div class="w-20 h-20 rounded-full bg-white/5 ring-1 ring-white/10 flex items-center justify-center">
            <svg class="w-10 h-10 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
          </div>
          <h2 id="room-error-title" class="mt-6 text-xl font-bold text-white"><?= e(app_lang('error_generic')) ?></h2>
          <p id="room-error-msg" class="mt-2 max-w-sm text-sm text-white/55"><?= e(app_lang('error_generic')) ?></p>
          <a href="/" class="mt-7 btn-ghost text-sm"><?= e(app_lang('back')) ?></a>
        </div>

        <!-- ---- Active room (hidden until ready) ---- -->
        <div id="room-active" class="hidden h-full">

          <!-- VIDEO avatar stage (only shown for ai_video) -->
          <div id="video-stage" class="hidden mb-4">
            <div class="relative rounded-2xl overflow-hidden glass aspect-video max-h-[44vh] mx-auto">
              <div id="avatar-poster" class="avatar-poster absolute inset-0 flex flex-col items-center justify-center">
                <div class="w-24 h-24 rounded-full gradient-brand flex items-center justify-center pulse-ring shadow-2xl">
                  <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                </div>
                <p class="mt-4 text-xs font-medium text-white/50">AI Interviewer</p>
              </div>
              <video id="avatar-video" class="absolute inset-0 w-full h-full object-cover" autoplay playsinline muted></video>
              <div class="absolute bottom-3 left-3 flex items-center gap-2 px-2.5 py-1 rounded-full bg-black/40 backdrop-blur text-[11px] font-medium text-white/80">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Live
              </div>
            </div>
          </div>

          <!-- ===== VOICE zone (waveform + live transcript; shown for ai_voice / ai_video) ===== -->
          <section id="voice-zone" class="hidden flex-col items-center mb-4">
            <div class="w-full glass rounded-2xl p-6 sm:p-7 flex flex-col items-center text-center">
              <!-- Waveform -->
              <div class="waveform mb-5" aria-hidden="true">
                <span style="height:10px"></span><span style="height:22px"></span><span style="height:34px"></span>
                <span style="height:18px"></span><span style="height:40px"></span><span style="height:24px"></span>
                <span style="height:32px"></span><span style="height:14px"></span><span style="height:26px"></span>
              </div>

              <!-- Live transcript (current spoken answer) -->
              <div id="voice-transcript" class="min-h-[2.5rem] max-w-md text-white/85 text-base leading-relaxed" aria-live="polite"></div>

              <p class="mt-4 text-xs text-white/45">Speak naturally — your answer sends automatically when you pause.</p>
            </div>
          </section>

          <!-- ===== TEXT zone wraps the shared, always-visible chat / transcript stream ===== -->
          <section id="text-zone" class="h-full flex flex-col">
            <div id="chat-stream" class="chat-scroll flex-1 overflow-y-auto space-y-4 pr-1 pb-2 min-h-[34vh] max-h-[56vh]">
              <!-- Messages injected by interview.js (and pre-rendered by the bootstrap below) -->

              <!-- Thinking indicator (toggled to display:flex by interview.js) -->
              <div id="thinking" class="flex justify-start" style="display:none;">
                <div class="chat-bubble chat-ai">
                  <div class="typing"><span></span><span></span><span></span></div>
                </div>
              </div>
            </div>
          </section>

        </div>
      </div>
    </main>

    <!-- ============ Bottom input bar ============ -->
    <footer id="room-footer" class="hidden glass sticky bottom-0 z-20">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 sm:py-4">

        <!-- Text input (default) -->
        <div id="input-text" class="flex items-end gap-3">
          <div class="flex-1">
            <textarea id="msg-input" rows="1" placeholder="<?= e(app_lang('your_answer')) ?>"
                      class="room-input w-full resize-none rounded-2xl px-4 py-3 text-sm leading-relaxed max-h-40"></textarea>
          </div>
          <button id="send-btn" type="button" aria-label="<?= e(app_lang('send')) ?>"
                  class="btn-primary shrink-0 !rounded-2xl !px-4 !py-3">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
            <span class="hidden sm:inline"><?= e(app_lang('send')) ?></span>
          </button>
        </div>

        <!-- Voice / video mic control (hidden unless voice/video) -->
        <div id="input-voice" class="hidden flex flex-col items-center gap-2">
          <button id="mic-btn" type="button" class="mic-btn shadow-xl" aria-label="Toggle microphone">
            <svg class="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" /></svg>
          </button>
          <p class="text-[11px] text-white/45">Tap to talk · tap again to stop</p>
        </div>
      </div>
    </footer>
  </div>

  <!-- ============ Start screen overlay ============ -->
  <div id="start-screen" class="fixed inset-0 z-40 flex items-center justify-center p-4" style="display:none;">
    <div class="absolute inset-0 bg-[#0b0f1a]/90 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-lg glass rounded-3xl p-8 sm:p-10 text-center shadow-2xl fade-in">
      <div class="mx-auto w-16 h-16 rounded-2xl gradient-brand flex items-center justify-center shadow-lg">
        <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a5.97 5.97 0 01-.59-.59c.02-.104.04-.208.06-.312m-2.969-2.969a14.926 14.926 0 00-.231 2.62l4.84-1.79z" /></svg>
      </div>

      <h1 class="mt-6 text-2xl sm:text-3xl font-extrabold text-white"><?= e(app_lang('welcome')) ?>!</h1>
      <p id="start-job" class="mt-2 text-white/70 text-sm sm:text-base">You're about to begin your interview.</p>

      <!-- What to expect -->
      <div class="mt-7 grid grid-cols-1 sm:grid-cols-3 gap-3 text-left">
        <div class="glass-soft rounded-xl p-3.5">
          <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
          <p class="mt-2 text-xs font-semibold text-white">10–14 questions</p>
          <p class="text-[11px] text-white/50">Conversational format</p>
        </div>
        <div class="glass-soft rounded-xl p-3.5">
          <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <p class="mt-2 text-xs font-semibold text-white">~15–20 minutes</p>
          <p class="text-[11px] text-white/50">Go at your own pace</p>
        </div>
        <div class="glass-soft rounded-xl p-3.5">
          <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <p class="mt-2 text-xs font-semibold text-white">Be yourself</p>
          <p class="text-[11px] text-white/50">Honest, thoughtful answers</p>
        </div>
      </div>

      <div id="start-mode-hint" class="mt-5 text-xs text-white/50"></div>

      <button id="start-btn" type="button" class="btn-primary mt-7 w-full justify-center !py-3 text-base">
        <?= e(app_lang('start_interview')) ?>
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
      </button>

      <p class="mt-4 text-[11px] text-white/35">Your responses are recorded for evaluation. Find a quiet place and good connection.</p>
    </div>
  </div>

  <div id="toast-root"></div>

  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/interview.js"></script>
  <script>
    (function () {
      'use strict';
      var token = <?= json_encode($__token) ?>;
      var esc = (window.AR && AR.esc) ? AR.esc : function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

      var els = {
        loading: document.getElementById('room-loading'),
        error: document.getElementById('room-error'),
        errorTitle: document.getElementById('room-error-title'),
        errorMsg: document.getElementById('room-error-msg'),
        active: document.getElementById('room-active'),
        footer: document.getElementById('room-footer'),
        startScreen: document.getElementById('start-screen'),
        textZone: document.getElementById('text-zone'),
        voiceZone: document.getElementById('voice-zone'),
        videoStage: document.getElementById('video-stage'),
        inputText: document.getElementById('input-text'),
        inputVoice: document.getElementById('input-voice'),
        chat: document.getElementById('chat-stream'),
        thinking: document.getElementById('thinking'),
        jobTitle: document.getElementById('job-title'),
        companyName: document.getElementById('company-name'),
        companyLogo: document.getElementById('company-logo'),
        startJob: document.getElementById('start-job'),
        startHint: document.getElementById('start-mode-hint'),
        modeHint: document.getElementById('start-mode-hint')
      };

      function showError(title, msg) {
        if (els.loading) els.loading.style.display = 'none';
        if (els.active) els.active.classList.add('hidden');
        if (els.footer) els.footer.classList.add('hidden');
        if (els.startScreen) els.startScreen.style.display = 'none';
        if (els.error) {
          els.error.classList.remove('hidden');
          els.error.classList.add('flex');
        }
        if (els.errorTitle && title) els.errorTitle.textContent = title;
        if (els.errorMsg && msg) els.errorMsg.textContent = msg;
      }

      // Pre-render an existing message into the chat stream (role: 'candidate' = me).
      function prerender(role, content) {
        if (!els.chat) return;
        var wrap = document.createElement('div');
        var mine = role === 'candidate' || role === 'user' || role === 'me';
        wrap.className = 'flex ' + (mine ? 'justify-end' : 'justify-start') + ' fade-in';
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble ' + (mine ? 'chat-me' : 'chat-ai');
        bubble.textContent = content;
        wrap.appendChild(bubble);
        // insert before the thinking indicator so it stays last
        if (els.thinking && els.thinking.parentNode === els.chat) {
          els.chat.insertBefore(wrap, els.thinking);
        } else {
          els.chat.appendChild(wrap);
        }
      }

      function configureMode(type) {
        var isVoice = type === 'ai_voice';
        var isVideo = type === 'ai_video';

        // The chat/transcript stream (#text-zone) stays visible in every mode so
        // there is always a running record of the conversation.
        if (isVideo && els.videoStage) els.videoStage.classList.remove('hidden');

        if (isVoice || isVideo) {
          // Show the voice panel (waveform + live transcript) above the chat,
          // and switch the footer to the mic control.
          if (els.voiceZone) { els.voiceZone.classList.remove('hidden'); els.voiceZone.classList.add('flex'); }
          if (els.inputText) els.inputText.classList.add('hidden');
          if (els.inputVoice) { els.inputVoice.classList.remove('hidden'); els.inputVoice.classList.add('flex'); }
          if (els.modeHint) {
            els.modeHint.textContent = isVideo
              ? 'Video interview with an AI avatar — answer by speaking.'
              : 'Voice interview — you’ll answer by speaking aloud.';
          }
        } else {
          // ai_text (default): typed answers.
          if (els.inputText) els.inputText.classList.remove('hidden');
          if (els.inputVoice) els.inputVoice.classList.add('hidden');
          if (els.modeHint) els.modeHint.textContent = 'Text interview — type your answers in the chat.';
        }
      }

      function autoGrow(ta) {
        if (!ta) return;
        ta.addEventListener('input', function () {
          ta.style.height = 'auto';
          ta.style.height = Math.min(ta.scrollHeight, 160) + 'px';
        });
      }

      function boot(room) {
        var type = (room && room.type) || 'ai_text';
        var status = (room && room.status) || 'pending';
        var job = (room && room.job) || '<?= e(app_lang('interview')) ?>';
        var company = (room && room.company) || '';
        var avatar = (room && room.avatar) || {};
        var avatarId = avatar.heygen_avatar_id || null;
        var messages = (room && room.messages) || [];

        // Completed / closed interviews cannot be re-taken.
        if (status === 'completed' || status === 'evaluated') {
          window.location.href = '/interview/complete/' + token;
          return;
        }
        if (status === 'cancelled' || status === 'expired' || status === 'archived') {
          showError('This interview is no longer available', 'The link has expired or the interview was cancelled. Please contact the hiring team.');
          return;
        }

        // Fill header.
        if (els.jobTitle) els.jobTitle.textContent = job;
        if (els.companyName) els.companyName.textContent = company || '<?= e(app_lang('app_name')) ?>';
        if (els.startJob) {
          els.startJob.textContent = company ? (job + ' · ' + company) : job;
        }
        if (els.companyLogo && company) {
          els.companyLogo.textContent = company.trim().charAt(0).toUpperCase() || 'AR';
        }
        document.title = job + ' · <?= e(app_lang('interview')) ?>';

        // Reveal the room.
        if (els.loading) els.loading.style.display = 'none';
        if (els.active) els.active.classList.remove('hidden');
        if (els.footer) els.footer.classList.remove('hidden');

        configureMode(type);
        autoGrow(document.getElementById('msg-input'));

        // Pre-render any prior conversation.
        var hadMessages = false;
        if (Array.isArray(messages) && messages.length) {
          messages.forEach(function (m) {
            if (!m) return;
            var role = m.role || (m.is_ai ? 'ai' : 'candidate');
            var content = m.content || m.message || m.text || '';
            if (content) { prerender(role, content); hadMessages = true; }
          });
        }

        // Show the start overlay only for a fresh interview.
        var alreadyStarted = hadMessages || status === 'in_progress' || status === 'started';
        if (els.startScreen) {
          els.startScreen.style.display = alreadyStarted ? 'none' : 'flex';
        }

        // Wire up the interview engine.
        try {
          var app = new InterviewApp();
          app.init(token, type, avatarId);
          window.__interview = app;
          if (els.chat) els.chat.scrollTop = els.chat.scrollHeight;
        } catch (e) {
          showError('<?= e(app_lang('error_generic')) ?>', 'We could not initialise the interview. Please refresh the page.');
        }
      }

      // ---- Fetch room data ------------------------------------------------
      function loadRoom() {
        fetch('/api/v1/interviews/room/' + encodeURIComponent(token), {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin'
        }).then(function (res) {
          return res.json().then(function (data) { return { ok: res.ok, status: res.status, data: data }; })
            .catch(function () { return { ok: res.ok, status: res.status, data: null }; });
        }).then(function (r) {
          if (!r.ok || !r.data || r.data.success === false || !r.data.data) {
            if (r.status === 404) {
              showError('Interview not found', 'We couldn’t find an interview for this link. Please double-check the URL from your invitation email.');
            } else {
              showError('<?= e(app_lang('error_generic')) ?>', 'We had trouble loading your interview. Please refresh and try again.');
            }
            return;
          }
          boot(r.data.data);
        }).catch(function () {
          showError('Connection problem', 'Please check your internet connection and refresh the page.');
        });
      }

      if (!token) {
        showError('Invalid link', 'This interview link is missing its token.');
      } else {
        loadRoom();
      }
    })();
  </script>
</body>
</html>
