@extends('layouts.candidate')
@section('title', 'Interview · '.$interview->jobPosition->title)
@php
    $rtl    = $interview->language === 'ar';
    $lang   = $rtl ? 'ar-EG' : 'en-US';
    $maxQ   = $interview->template?->max_questions ?? config('watad.interview.default_max_questions');
    $maxMin = $interview->template?->max_duration_min ?? config('watad.interview.default_max_duration_min');
@endphp
@section('content')
<div x-data="interviewRoom('{{ $interview->public_id }}', '{{ $interview->mode->value }}', '{{ $lang }}', {{ (int) $maxQ }}, {{ (int) $maxMin }})"
     x-init="start()" x-cloak dir="{{ $rtl ? 'rtl' : 'ltr' }}"
     class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">

    {{-- ============ Completion screen (shown when concluded) ============ --}}
    <div x-show="concluded" x-cloak class="px-6 py-16 text-center">
        <div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-full bg-emerald-100">
            <svg class="h-9 w-9 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </div>
        <h2 class="text-xl font-semibold text-slate-800">
            {{ $rtl ? 'تمت المقابلة بنجاح' : 'Interview Completed Successfully' }}
        </h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600">
            {{ $rtl
                ? 'شكرًا لإتمامك المقابلة. سيقوم فريق التوظيف بمراجعة طلبك والتواصل معك بخصوص المرحلة التالية.'
                : 'Thank you for completing your interview. Our recruitment team will review your application and contact you regarding the next stage.' }}
        </p>
        <div class="mx-auto mt-6 max-w-xs">
            <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-600">
                    {{ $rtl ? 'تم الإرسال' : 'Submitted' }}
                </span>
                <span>100%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" style="width:100%"></div>
            </div>
        </div>
    </div>

    {{-- ============ Live interview (hidden once concluded) ============ --}}
    <template x-if="!concluded">
    <div>
    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 bg-slate-50">
        <div class="text-sm">
            <span class="font-medium">{{ $interview->avatar?->name ?? 'AI Interviewer' }}</span>
            <span class="text-slate-400">· {{ $interview->jobPosition->title }}</span>
            <span class="ms-1 rounded bg-slate-200 px-1.5 text-[10px] uppercase">{{ $interview->mode->value }}</span>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <span class="text-slate-500">{{ $rtl ? 'سؤال' : 'Question' }}
                <span class="font-semibold text-slate-700" x-text="Math.max(progress.asked, 1)"></span> /
                <span x-text="maxQuestions"></span></span>
            <span class="tabular-nums font-medium" :class="timeLeft <= 120 ? 'text-red-500' : 'text-slate-500'"
                  x-show="timeLeft !== null" x-text="formatTime(timeLeft)"></span>
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="h-1.5 w-full bg-slate-100">
        <div class="h-full bg-brand transition-all duration-500" :style="`width:${progressPct()}%`"></div>
    </div>

    {{-- Video stage (video mode) --}}
    <div x-show="mode === 'video'" x-cloak class="grid grid-cols-3 gap-px bg-slate-200">
        <div class="col-span-2 bg-black aspect-video flex items-center justify-center">
            <iframe x-show="avatarUrl" :src="avatarUrl" allow="camera; microphone; autoplay"
                    class="w-full h-full" frameborder="0"></iframe>
            <p x-show="!avatarUrl" class="text-slate-400 text-sm">{{ $rtl ? 'جارٍ تجهيز المُحاوِر…' : 'Preparing your interviewer…' }}</p>
        </div>
        <div class="bg-slate-900 aspect-video flex items-center justify-center relative">
            <video x-ref="selfcam" autoplay muted playsinline class="w-full h-full object-cover"></video>
            <span class="absolute bottom-1 start-1 text-[10px] text-white/70 bg-black/40 px-1 rounded">{{ $rtl ? 'أنت' : 'You' }}</span>
        </div>
    </div>

    {{-- Transcript --}}
    <div class="h-[26rem] overflow-y-auto px-5 py-4 space-y-4" x-ref="log">
        <template x-for="(turn, i) in transcript" :key="i">
            <div :class="turn.role === 'agent' ? '' : 'flex justify-end'">
                <div :class="turn.role === 'agent'
                        ? 'max-w-[80%] rounded-2xl bg-slate-100 px-4 py-2.5'
                        : 'max-w-[80%] rounded-2xl bg-brand text-white px-4 py-2.5'">
                    <p class="text-sm whitespace-pre-wrap" x-text="turn.text"></p>
                </div>
            </div>
        </template>
        <div x-show="thinking" class="text-sm text-slate-400">{{ $interview->avatar?->name ?? 'Interviewer' }}…</div>
    </div>

    {{-- Composer --}}
    <div class="border-t border-slate-200 p-4">
        <template x-if="!concluded">
            <div class="flex items-end gap-2">
                <template x-if="usesMic">
                    <button @click="toggleMic()" :disabled="thinking"
                            :class="listening ? 'bg-red-600 animate-pulse' : 'bg-slate-800'"
                            class="rounded-full h-11 w-11 text-white text-lg shrink-0" title="Speak">🎤</button>
                </template>
                <textarea x-model="draft" rows="2" :disabled="thinking" @keydown.enter.prevent="send()"
                          :placeholder="listening ? '{{ $rtl ? 'جارٍ الاستماع…' : 'Listening…' }}' : '{{ $rtl ? 'اكتب إجابتك…' : 'Type your answer…' }}'"
                          class="flex-1 resize-none rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <button @click="send()" :disabled="thinking || !draft.trim()"
                        class="rounded-lg bg-brand px-4 py-2.5 text-white text-sm font-medium hover:bg-brand-dark disabled:opacity-50">
                    {{ $rtl ? 'إرسال' : 'Send' }}
                </button>
            </div>
        </template>
        <template x-if="!concluded && !startError && progress.asked > 0">
            <div class="text-center pt-3">
                <button @click="endInterview()" :disabled="thinking"
                        class="text-xs text-slate-400 hover:text-slate-600 underline disabled:opacity-50">
                    {{ $rtl ? 'إنهاء المقابلة الآن' : 'End interview now' }}
                </button>
            </div>
        </template>
        <template x-if="startError">
            <div class="text-center py-2">
                <button @click="retryStart()"
                        class="rounded-lg bg-brand px-5 py-2 text-white text-sm font-medium hover:bg-brand-dark">
                    {{ $rtl ? 'إعادة المحاولة' : 'Retry' }}
                </button>
            </div>
        </template>
    </div>
    </div>
    </template>
</div>

<script>
function interviewRoom(publicId, mode, lang, maxQuestions, maxDurationMin) {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const json = (path, body) => fetch(`/interview/${publicId}/${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(body || {}),
    }).then(r => r.json());

    return {
        mode, lang, maxQuestions, maxDurationMin,
        transcript: [], draft: '', thinking: false, concluded: false, startError: false,
        progress: { asked: 0, phase: 'intro' },
        usesMic: mode !== 'text',
        usesTTS: mode === 'voice',
        usesAvatar: mode === 'video',
        listening: false,
        avatarUrl: '',
        timeLeft: null, timer: null,
        recognition: null, recorder: null, chunks: [], stream: null,

        progressPct() {
            const max = this.progress.max || this.maxQuestions || 14;
            return Math.min(100, Math.round((this.progress.asked / max) * 100));
        },
        formatTime(s) {
            const m = Math.floor(s / 60), sec = s % 60;
            return `${m}:${sec < 10 ? '0' : ''}${sec}`;
        },
        startTimer() {
            if (this.timer || !this.maxDurationMin) return;
            this.timeLeft = this.maxDurationMin * 60;
            this.timer = setInterval(() => {
                if (this.timeLeft === null) return;
                this.timeLeft--;
                if (this.timeLeft <= 0) { this.stopTimer(); this.autoComplete(); }
            }, 1000);
        },
        stopTimer() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },

        async start() {
            if (this.usesAvatar || this.usesMic) await this.initMedia();
            this.thinking = true;
            this.startError = false;
            try {
                const res = await json('start');
                this.thinking = false;
                if (res.status === 'error') {
                    this.startError = true;
                    this.transcript.push({ role: 'agent', text: res.message || 'Service unavailable. Please retry.' });
                } else {
                    if (res.avatar && res.avatar.room_url) this.avatarUrl = res.avatar.room_url;
                    this.apply(res);
                    this.startTimer();
                }
            } catch (e) {
                this.thinking = false;
                this.startError = true;
                this.transcript.push({ role: 'agent', text: 'Connection error. Please retry.' });
            }
        },

        async retryStart() {
            this.transcript = [];
            await this.start();
        },

        async endInterview() {
            if (this.thinking) return;
            if (!confirm('{{ $rtl ? 'هل تريد إنهاء المقابلة الآن؟' : 'End the interview now?' }}')) return;
            await this.autoComplete();
        },

        // Concludes the interview (manual end, or auto when the timer runs out). The AI agent
        // produces the closing message server-side, then the completion screen is shown.
        async autoComplete() {
            if (this.concluded) return;
            this.stopTimer();
            this.thinking = true;
            try {
                const res = await json('complete');
                this.thinking = false;
                this.apply(res);
            } catch (e) {
                this.thinking = false;
                // Even if the network call fails, show the completion screen — the server-side
                // abandon sweep will finalize and score the interview.
                this.concluded = true;
                this.stopMedia();
            }
        },

        async send() {
            const text = this.draft.trim();
            if (!text || this.thinking) return;
            this.transcript.push({ role: 'candidate', text });
            this.draft = '';
            this.scroll();
            this.thinking = true;
            try {
                await this.flushAudio();
                const res = await json('answer', { text, client_token: crypto.randomUUID() });
                this.thinking = false;
                if (res.status === 'error') {
                    this.transcript.push({ role: 'agent', text: res.message || 'Temporary error. Please try again.' });
                } else {
                    this.apply(res);
                }
            } catch (e) {
                this.thinking = false;
                this.transcript.push({ role: 'agent', text: 'Connection error. Please try again.' });
            }
        },

        apply(res) {
            if (res.progress) this.progress = res.progress;
            if (res.agent && res.agent.text) {
                this.transcript.push({ role: 'agent', text: res.agent.text });
                if (this.usesTTS) this.speak(res.agent.text);
            }
            if (res.status === 'concluded') { this.concluded = true; this.stopTimer(); this.stopMedia(); }
            this.scroll();
        },

        /* ---- speech ---- */
        async initMedia() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    audio: true, video: this.usesAvatar,
                });
                if (this.usesAvatar && this.$refs.selfcam) this.$refs.selfcam.srcObject = this.stream;
            } catch (e) { /* mic/cam denied → fall back to typing */ this.usesMic = this.usesMic && false; }

            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (SR && this.usesMic) {
                this.recognition = new SR();
                this.recognition.lang = this.lang;
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.recognition.onresult = (ev) => {
                    let finalT = '';
                    for (let i = ev.resultIndex; i < ev.results.length; i++) finalT += ev.results[i][0].transcript;
                    this.draft = finalT;
                };
                this.recognition.onend = () => { this.listening = false; };
            }
        },

        toggleMic() {
            if (!this.recognition) return;
            if (this.listening) { this.recognition.stop(); this.stopRecorder(); this.listening = false; }
            else {
                this.draft = '';
                try { this.recognition.start(); this.startRecorder(); this.listening = true; } catch (e) {}
            }
        },

        startRecorder() {
            if (!this.stream || !window.MediaRecorder) return;
            this.chunks = [];
            try {
                this.recorder = new MediaRecorder(this.stream, { mimeType: 'audio/webm' });
                this.recorder.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data); };
                this.recorder.start();
            } catch (e) {}
        },
        stopRecorder() { try { this.recorder && this.recorder.state !== 'inactive' && this.recorder.stop(); } catch (e) {} },

        async flushAudio() {
            this.stopRecorder();
            if (!this.chunks.length) return;
            const blob = new Blob(this.chunks, { type: 'audio/webm' });
            this.chunks = [];
            const fd = new FormData();
            fd.append('audio', blob, 'turn.webm');
            try {
                await fetch(`/interview/${publicId}/audio`, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd,
                });
            } catch (e) {}
        },

        speak(text) {
            if (!window.speechSynthesis) return;
            const u = new SpeechSynthesisUtterance(text);
            u.lang = this.lang;
            const voices = window.speechSynthesis.getVoices();
            const v = voices.find(v => v.lang && v.lang.startsWith(this.lang.split('-')[0]));
            if (v) u.voice = v;
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(u);
        },

        stopMedia() {
            try { this.recognition && this.recognition.stop(); } catch (e) {}
            this.stopRecorder();
            if (this.stream) this.stream.getTracks().forEach(t => t.stop());
        },

        scroll() { this.$nextTick(() => { this.$refs.log.scrollTop = this.$refs.log.scrollHeight; }); },
    };
}
</script>
@endsection
