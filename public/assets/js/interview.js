/* ============================================================
   AI Recruit — Interview Room client
   Supports ai_text / ai_voice / ai_video modes. Token-based,
   no server session required. Talks to /api/v1/interviews/*.
   ============================================================ */
(function () {
  'use strict';

  const API = '/api/v1/interviews';

  class InterviewApp {
    constructor() {
      this.token = null;
      this.type = 'ai_text';
      this.avatarId = null;
      this.questionsAsked = 0;
      this.estimatedTotal = 12;
      this.startTime = null;
      this.timerInterval = null;
      this.completed = false;
      this.waiting = false;
      this.recognition = null;
      this.recording = false;
      this.heygenSession = null;
      this.heygenPeer = null;
    }

    async init(token, type, avatarId) {
      this.token = token;
      this.type = type || 'ai_text';
      this.avatarId = avatarId || null;

      this.els = {
        chat: document.getElementById('chat-stream'),
        input: document.getElementById('msg-input'),
        send: document.getElementById('send-btn'),
        timer: document.getElementById('timer'),
        counter: document.getElementById('q-counter'),
        progress: document.getElementById('progress-bar'),
        thinking: document.getElementById('thinking'),
        mic: document.getElementById('mic-btn'),
        transcript: document.getElementById('voice-transcript'),
        startScreen: document.getElementById('start-screen'),
        startBtn: document.getElementById('start-btn'),
        avatarVideo: document.getElementById('avatar-video'),
        textZone: document.getElementById('text-zone'),
        voiceZone: document.getElementById('voice-zone'),
      };

      if (this.els.startBtn) {
        this.els.startBtn.addEventListener('click', () => this.start());
      }
      if (this.els.send) {
        this.els.send.addEventListener('click', () => this.handleSend());
      }
      if (this.els.input) {
        this.els.input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.handleSend(); }
        });
      }
      if (this.els.mic) {
        this.els.mic.addEventListener('click', () => this.toggleRecording());
      }
      this.setupVoice();
    }

    // -------- Lifecycle ----------------------------------------------------
    async start() {
      if (this.els.startScreen) this.els.startScreen.style.display = 'none';
      this.startTime = Date.now();
      this.startTimer();
      this.showThinking(true);
      try {
        const res = await this.post('/start/' + this.token, {});
        this.showThinking(false);
        const opening = (res && (res.opening || (res.interview && res.opening))) || (res && res.opening);
        const openText = res && (res.opening || res.message) ? (res.opening || res.message) : 'Hello! Thanks for joining. Let’s begin.';
        this.addMessage('ai', openText);
        this.questionsAsked = 1;
        this.updateCounter();
        if (this.type === 'ai_video') { await this.initHeyGen(); this.speak(openText); }
        if (this.type === 'ai_voice') { this.speakBrowser(openText); }
      } catch (e) {
        this.showThinking(false);
        this.addMessage('ai', 'We had trouble starting. Please refresh and try again.');
      }
    }

    async handleSend() {
      if (this.completed || this.waiting) return;
      const text = (this.els.input && this.els.input.value || '').trim();
      if (!text) return;
      this.els.input.value = '';
      await this.sendMessage(text);
    }

    async sendMessage(text) {
      this.addMessage('candidate', text);
      this.waiting = true;
      this.showThinking(true);
      try {
        const res = await this.post('/message/' + this.token, { message: text });
        this.showThinking(false);
        this.waiting = false;
        const reply = (res && res.reply) ? res.reply : '';
        if (typeof res.questions_asked === 'number') {
          this.questionsAsked = res.questions_asked;
          this.updateCounter();
        }
        if (reply) {
          this.addMessage('ai', reply);
          if (this.type === 'ai_video') this.speak(reply);
          if (this.type === 'ai_voice') this.speakBrowser(reply);
        }
        if (res && res.is_complete) {
          await this.complete();
        }
      } catch (e) {
        this.showThinking(false);
        this.waiting = false;
        this.addMessage('ai', 'Sorry, something went wrong. Please try again.');
      }
    }

    async complete() {
      if (this.completed) return;
      this.completed = true;
      this.showThinking(true);
      try {
        await this.post('/complete/' + this.token, {});
      } catch (e) { /* ignore */ }
      this.showThinking(false);
      this.stopTimer();
      if (this.recognition && this.recording) { try { this.recognition.stop(); } catch (e) {} }
      if (this.heygenSession) this.stopHeyGen();
      this.addMessage('ai', 'Thank you for completing the interview! You may now close this window.');
      setTimeout(() => { window.location.href = '/interview/complete/' + this.token; }, 1800);
    }

    // -------- UI -----------------------------------------------------------
    addMessage(role, content) {
      if (!this.els.chat) return;
      const wrap = document.createElement('div');
      wrap.className = 'flex ' + (role === 'candidate' ? 'justify-end' : 'justify-start') + ' fade-in';
      const bubble = document.createElement('div');
      bubble.className = 'chat-bubble ' + (role === 'candidate' ? 'chat-me' : 'chat-ai');
      bubble.textContent = content;
      wrap.appendChild(bubble);
      this.els.chat.appendChild(wrap);
      this.scrollToLatest();
    }

    scrollToLatest() {
      if (this.els.chat) this.els.chat.scrollTop = this.els.chat.scrollHeight;
    }

    showThinking(on) {
      if (this.els.thinking) this.els.thinking.style.display = on ? 'flex' : 'none';
      if (on) this.scrollToLatest();
    }

    updateCounter() {
      if (this.els.counter) {
        this.els.counter.textContent = 'Q' + this.questionsAsked + ' of ~' + this.estimatedTotal;
      }
      if (this.els.progress) {
        const pct = Math.min(100, Math.round((this.questionsAsked / this.estimatedTotal) * 100));
        this.els.progress.style.width = pct + '%';
      }
    }

    startTimer() {
      this.timerInterval = setInterval(() => {
        if (!this.startTime || !this.els.timer) return;
        const s = Math.floor((Date.now() - this.startTime) / 1000);
        const mm = String(Math.floor(s / 60)).padStart(2, '0');
        const ss = String(s % 60).padStart(2, '0');
        this.els.timer.textContent = mm + ':' + ss;
      }, 1000);
    }
    stopTimer() { if (this.timerInterval) clearInterval(this.timerInterval); }

    // -------- Voice mode (Web Speech API) ----------------------------------
    setupVoice() {
      const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!SR || this.type !== 'ai_voice') return;
      this.recognition = new SR();
      this.recognition.continuous = true;
      this.recognition.interimResults = true;
      this.recognition.lang = document.documentElement.lang === 'ar' ? 'ar-SA' : 'en-US';
      let finalText = '';
      let silenceTimer = null;

      this.recognition.onresult = (event) => {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
          const t = event.results[i][0].transcript;
          if (event.results[i].isFinal) finalText += t + ' ';
          else interim += t;
        }
        if (this.els.transcript) this.els.transcript.textContent = (finalText + interim).trim();
        if (silenceTimer) clearTimeout(silenceTimer);
        silenceTimer = setTimeout(() => {
          const toSend = finalText.trim();
          if (toSend && this.recording) {
            finalText = '';
            if (this.els.transcript) this.els.transcript.textContent = '';
            this.sendMessage(toSend);
          }
        }, 1800); // auto-send after pause
      };
      this.recognition.onend = () => {
        if (this.recording && !this.completed) { try { this.recognition.start(); } catch (e) {} }
      };
    }

    toggleRecording() {
      if (!this.recognition) {
        if (window.AR) AR.Toast.error('Speech recognition is not supported in this browser.');
        return;
      }
      this.recording = !this.recording;
      if (this.els.mic) this.els.mic.classList.toggle('recording', this.recording);
      if (this.recording) { try { this.recognition.start(); } catch (e) {} }
      else { try { this.recognition.stop(); } catch (e) {} }
    }

    speakBrowser(text) {
      if (!('speechSynthesis' in window)) return;
      const u = new SpeechSynthesisUtterance(text);
      u.lang = document.documentElement.lang === 'ar' ? 'ar-SA' : 'en-US';
      window.speechSynthesis.cancel();
      window.speechSynthesis.speak(u);
    }

    // -------- Video mode (HeyGen streaming) --------------------------------
    async initHeyGen() {
      try {
        const res = await this.post('/start/' + this.token, {}).catch(() => null);
        // Streaming session token comes from the avatars API.
        const session = await fetch('/api/v1/avatars/' + (this.avatarId || '0') + '/streaming', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (window.AR ? AR.csrfToken() : '') },
        }).then((r) => r.json()).catch(() => null);
        if (session && session.success && session.data) {
          this.heygenSession = session.data;
          // If a LiveKit/WebRTC url + token were returned, attach to the video element.
          // (HeyGen streaming SDK negotiation — the returned url/access_token drive playback.)
          if (this.els.avatarVideo && session.data.url) {
            this.els.avatarVideo.setAttribute('data-session', JSON.stringify(session.data));
          }
        }
      } catch (e) { /* graceful: video falls back to text */ }
    }

    async speak(text) {
      if (!this.heygenSession) return;
      try {
        await fetch('/api/v1/avatars/0/streaming', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'task', session_id: this.heygenSession.session_id, text: text }),
        }).catch(() => null);
      } catch (e) {}
    }

    async stopHeyGen() {
      if (!this.heygenSession) return;
      try {
        await fetch('/api/v1/avatars/0/streaming', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'stop', session_id: this.heygenSession.session_id }),
        }).catch(() => null);
      } catch (e) {}
    }

    // -------- HTTP ---------------------------------------------------------
    async post(path, body) {
      const res = await fetch(API + path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (window.AR ? AR.csrfToken() : '') },
        body: JSON.stringify(body || {}),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || (data && data.success === false)) {
        throw new Error((data && data.error) || 'Request failed');
      }
      return data ? data.data : null;
    }
  }

  window.InterviewApp = InterviewApp;
})();
