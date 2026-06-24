/**
 * interview.js — AI Interview Room Controller
 * Handles: timer, AI messaging, voice recognition, HeyGen video, auto-save
 */

(function() {
  'use strict';

  // ─── State ─────────────────────────────────────────────────────────────────
  const state = {
    token: window.INTERVIEW_TOKEN || '',
    type: window.INTERVIEW_TYPE || 'text', // text | voice | video
    timeLimit: (window.INTERVIEW_TIME_LIMIT || 30) * 60,
    timeElapsed: 0,
    questionCount: 0,
    maxQuestions: window.INTERVIEW_MAX_QUESTIONS || 12,
    isComplete: false,
    isRecording: false,
    recognition: null,
    heygenSession: null,
    lastSave: Date.now(),
    history: []
  };

  // ─── DOM Elements ──────────────────────────────────────────────────────────
  const $ = id => document.getElementById(id);
  const el = {
    timer:         $('interview-timer'),
    progress:      $('interview-progress'),
    qCounter:      $('question-counter'),
    chatMessages:  $('chat-messages'),
    chatInput:     $('chat-input'),
    sendBtn:       $('send-btn'),
    micBtn:        $('mic-btn'),
    voiceBtn:      $('voice-record-btn'),
    voiceStatus:   $('voice-status'),
    waveCanvas:    $('voice-waveform'),
    completeScreen:$('completion-screen'),
    interviewArea: $('interview-area'),
    warningBanner: $('time-warning')
  };

  // ─── Timer ─────────────────────────────────────────────────────────────────
  function startTimer() {
    const interval = setInterval(() => {
      state.timeElapsed++;
      const remaining = state.timeLimit - state.timeElapsed;
      if (remaining <= 0) { clearInterval(interval); completeInterview('timeout'); return; }

      const mins = Math.floor(remaining / 60).toString().padStart(2, '0');
      const secs = (remaining % 60).toString().padStart(2, '0');
      if (el.timer) el.timer.textContent = `${mins}:${secs}`;

      // Warning at 5 minutes
      if (remaining === 300 && el.warningBanner) {
        el.warningBanner.classList.remove('hidden');
        el.warningBanner.textContent = '⚠ 5 minutes remaining';
      }

      // Auto-save every 30s
      if (state.timeElapsed % 30 === 0) autoSave();
    }, 1000);
  }

  // ─── Progress ─────────────────────────────────────────────────────────────
  function updateProgress() {
    const pct = Math.min(100, Math.round((state.questionCount / state.maxQuestions) * 100));
    if (el.progress) el.progress.style.width = pct + '%';
    if (el.qCounter) el.qCounter.textContent = `${state.questionCount} / ${state.maxQuestions}`;
  }

  // ─── Chat (Text Mode) ─────────────────────────────────────────────────────
  function appendMessage(role, content, isTyping = false) {
    if (!el.chatMessages) return;
    const isAI = role === 'assistant';
    const div = document.createElement('div');
    div.className = `flex gap-3 ${isAI ? '' : 'flex-row-reverse'}`;
    div.innerHTML = `
      <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-sm font-bold
        ${isAI ? 'bg-violet-100 text-violet-700' : 'bg-gray-200 text-gray-700'}">
        ${isAI ? 'AI' : 'You'}
      </div>
      <div class="max-w-xs lg:max-w-md xl:max-w-lg">
        <div class="rounded-2xl px-4 py-3 ${isAI ? 'bg-white border border-gray-200 text-gray-800' : 'bg-violet-600 text-white'}">
          ${isTyping ? '<div class="typing-dots flex gap-1"><span></span><span></span><span></span></div>' : `<p class="text-sm leading-relaxed">${escapeHtml(content)}</p>`}
        </div>
        <p class="text-xs text-gray-400 mt-1 ${isAI ? '' : 'text-right'}">${new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</p>
      </div>
    `;
    el.chatMessages.appendChild(div);
    el.chatMessages.scrollTop = el.chatMessages.scrollHeight;
    return div;
  }

  async function sendMessage(content) {
    if (!content.trim() || state.isComplete) return;
    content = content.trim();

    appendMessage('user', content);
    state.history.push({ role: 'user', content });
    if (el.chatInput) el.chatInput.value = '';
    if (el.sendBtn) el.sendBtn.disabled = true;

    state.questionCount++;
    updateProgress();

    // Show typing indicator
    const typingMsg = appendMessage('assistant', '', true);

    try {
      const res = await fetch(`/api/v1/interviews/${state.token}/message`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ message: content, history: state.history })
      });
      const data = await res.json();

      typingMsg?.remove();

      if (data.ok) {
        const reply = data.data?.message || data.message;
        appendMessage('assistant', reply);
        state.history.push({ role: 'assistant', content: reply });

        if (data.data?.is_complete || data.data?.interview_complete) {
          setTimeout(() => completeInterview('finished'), 1500);
        }
      } else {
        appendMessage('assistant', 'I apologize, there was a technical issue. Please try again.');
      }
    } catch (err) {
      typingMsg?.remove();
      appendMessage('assistant', 'Connection interrupted. Please check your internet and try again.');
    } finally {
      if (el.sendBtn) el.sendBtn.disabled = false;
      if (el.chatInput) el.chatInput.focus();
    }
  }

  // ─── Voice Recognition ────────────────────────────────────────────────────
  function initVoiceRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      console.warn('Speech recognition not supported');
      return;
    }

    state.recognition = new SpeechRecognition();
    state.recognition.continuous = true;
    state.recognition.interimResults = true;
    state.recognition.lang = 'en-US';

    state.recognition.onresult = (event) => {
      let final = '', interim = '';
      for (let i = event.resultIndex; i < event.results.length; i++) {
        const t = event.results[i][0].transcript;
        if (event.results[i].isFinal) final += t;
        else interim += t;
      }
      if (el.chatInput) el.chatInput.value = (el.chatInput.value || '') + final;
      if (el.voiceStatus) el.voiceStatus.textContent = interim || '🎤 Listening...';
    };

    state.recognition.onerror = (event) => {
      console.error('Speech error:', event.error);
      stopRecording();
    };

    state.recognition.onend = () => {
      if (state.isRecording) state.recognition.start();
    };
  }

  function startRecording() {
    if (!state.recognition) return;
    state.isRecording = true;
    state.recognition.start();
    if (el.micBtn) {
      el.micBtn.classList.add('bg-red-500', 'text-white');
      el.micBtn.classList.remove('bg-gray-100', 'text-gray-600');
    }
    if (el.voiceBtn) el.voiceBtn.classList.add('recording-pulse');
    if (el.voiceStatus) el.voiceStatus.textContent = '🎤 Listening...';
  }

  function stopRecording() {
    state.isRecording = false;
    state.recognition?.stop();
    if (el.micBtn) {
      el.micBtn.classList.remove('bg-red-500', 'text-white');
      el.micBtn.classList.add('bg-gray-100', 'text-gray-600');
    }
    if (el.voiceBtn) el.voiceBtn.classList.remove('recording-pulse');
    if (el.voiceStatus) el.voiceStatus.textContent = '';
    // Auto-send if there's text
    if (state.type === 'voice' && el.chatInput?.value.trim()) {
      sendMessage(el.chatInput.value);
    }
  }

  function toggleRecording() {
    if (state.isRecording) stopRecording();
    else startRecording();
  }

  // ─── HeyGen Video Mode ────────────────────────────────────────────────────
  async function initHeyGen() {
    try {
      const res = await fetch(`/api/v1/interviews/${state.token}/heygen`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ action: 'create_session' })
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message);
      state.heygenSession = data.data;

      const videoEl = $('heygen-video');
      if (videoEl && data.data.url) {
        videoEl.src = data.data.url;
      }
    } catch (err) {
      console.error('HeyGen init failed:', err);
      // Fallback to text mode
      const heygenArea = $('heygen-container');
      if (heygenArea) heygenArea.innerHTML = `<div class="flex items-center justify-center h-full bg-gray-900 rounded-xl text-white text-sm p-4">Video avatar unavailable. Continuing in text mode.</div>`;
    }
  }

  async function sendHeyGenMessage(text) {
    if (!state.heygenSession?.session_id) return;
    await fetch(`/api/v1/interviews/${state.token}/heygen`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action: 'speak', text, session_id: state.heygenSession.session_id })
    });
  }

  // ─── Completion ───────────────────────────────────────────────────────────
  async function completeInterview(reason = 'finished') {
    if (state.isComplete) return;
    state.isComplete = true;

    try {
      const res = await fetch(`/api/v1/interviews/${state.token}/complete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ reason, transcript: state.history })
      });
      const data = await res.json();

      if (el.interviewArea) el.interviewArea.classList.add('hidden');
      if (el.completeScreen) {
        el.completeScreen.classList.remove('hidden');
        // Animate score if provided
        if (data.data?.score) animateScore(data.data.score);
      }
    } catch (err) {
      if (el.interviewArea) el.interviewArea.classList.add('hidden');
      if (el.completeScreen) el.completeScreen.classList.remove('hidden');
    }

    // Stop HeyGen session
    if (state.heygenSession?.session_id) {
      fetch(`/api/v1/interviews/${state.token}/heygen`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'stop', session_id: state.heygenSession.session_id })
      }).catch(() => {});
    }
  }

  function animateScore(target) {
    const scoreEl = $('completion-score');
    if (!scoreEl) return;
    let current = 0;
    const duration = 1500;
    const step = target / (duration / 16);
    const interval = setInterval(() => {
      current = Math.min(current + step, target);
      scoreEl.textContent = Math.round(current);
      if (current >= target) clearInterval(interval);
    }, 16);
  }

  // ─── Auto Save ───────────────────────────────────────────────────────────
  async function autoSave() {
    if (!state.history.length) return;
    try {
      await fetch(`/api/v1/interviews/${state.token}/save`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ transcript: state.history, time_elapsed: state.timeElapsed })
      });
      state.lastSave = Date.now();
    } catch (e) { /* silent */ }
  }

  // ─── Waveform Visualizer ──────────────────────────────────────────────────
  function initWaveform() {
    const canvas = el.waveCanvas;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let animFrame;
    function draw() {
      animFrame = requestAnimationFrame(draw);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (!state.isRecording) return;
      const bars = 40;
      const barW = canvas.width / bars;
      ctx.fillStyle = '#7C3AED';
      for (let i = 0; i < bars; i++) {
        const h = state.isRecording ? (Math.random() * canvas.height * 0.7 + canvas.height * 0.15) : canvas.height * 0.1;
        ctx.fillRect(i * barW + 1, (canvas.height - h) / 2, barW - 2, h);
      }
    }
    draw();
    return () => cancelAnimationFrame(animFrame);
  }

  // ─── Page Leave Warning ───────────────────────────────────────────────────
  window.addEventListener('beforeunload', e => {
    if (!state.isComplete && state.history.length > 0) {
      e.preventDefault();
      e.returnValue = 'You have an interview in progress. Are you sure you want to leave?';
    }
  });

  // ─── Helpers ──────────────────────────────────────────────────────────────
  function escapeHtml(text) {
    return (text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ─── Init ─────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    startTimer();
    updateProgress();
    initVoiceRecognition();
    initWaveform();

    if (state.type === 'video') initHeyGen();

    // Send button
    el.sendBtn?.addEventListener('click', () => {
      sendMessage(el.chatInput?.value || '');
    });

    // Enter to send (Shift+Enter for newline)
    el.chatInput?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(e.target.value);
      }
    });

    // Mic toggle
    el.micBtn?.addEventListener('click', toggleRecording);
    el.voiceBtn?.addEventListener('click', toggleRecording);

    // Complete button
    $('complete-btn')?.addEventListener('click', () => {
      if (confirm('Are you sure you want to end the interview now?')) completeInterview('manual');
    });
  });

  // Public API
  window.InterviewRoom = { send: sendMessage, complete: completeInterview, state };
})();
