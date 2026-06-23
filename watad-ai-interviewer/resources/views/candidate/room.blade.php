@extends('layouts.candidate')
@section('title', 'Interview · '.$interview->jobPosition->title)
@section('content')
<div x-data="interviewRoom('{{ $interview->public_id }}')" x-init="start()" x-cloak
     class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">

    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 bg-slate-50">
        <div class="text-sm">
            <span class="font-medium">{{ $interview->avatar?->name ?? 'AI Interviewer' }}</span>
            <span class="text-slate-400">· {{ $interview->jobPosition->title }}</span>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span x-show="concluded" class="text-emerald-600 font-medium">Completed</span>
            <span x-show="!concluded">Q <span x-text="progress.asked"></span> · <span x-text="progress.phase"></span></span>
        </div>
    </div>

    {{-- Transcript --}}
    <div class="h-[28rem] overflow-y-auto px-5 py-4 space-y-4" x-ref="log">
        <template x-for="(turn, i) in transcript" :key="i">
            <div :class="turn.role === 'agent' ? '' : 'flex justify-end'">
                <div :class="turn.role === 'agent'
                        ? 'max-w-[80%] rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-2.5'
                        : 'max-w-[80%] rounded-2xl rounded-br-sm bg-indigo-600 text-white px-4 py-2.5'">
                    <p class="text-sm whitespace-pre-wrap" x-text="turn.text"></p>
                </div>
            </div>
        </template>
        <div x-show="thinking" class="text-sm text-slate-400">{{ $interview->avatar?->name ?? 'Interviewer' }} is typing…</div>
    </div>

    {{-- Composer --}}
    <div class="border-t border-slate-200 p-4">
        <template x-if="!concluded">
            <form @submit.prevent="send()" class="flex items-end gap-2">
                <textarea x-model="draft" rows="2" :disabled="thinking" @keydown.enter.prevent="send()"
                          placeholder="Type your answer…"
                          class="flex-1 resize-none rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <button :disabled="thinking || !draft.trim()"
                        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                    Send
                </button>
            </form>
        </template>
        <template x-if="concluded">
            <div class="text-center text-sm text-slate-600 py-2">
                Thank you — your interview is complete. You may close this window.
            </div>
        </template>
    </div>
</div>

<script>
function interviewRoom(publicId) {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const post = (path, body) => fetch(`/interview/${publicId}/${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(body || {}),
    }).then(r => r.json());

    return {
        transcript: [], draft: '', thinking: false, concluded: false,
        progress: { asked: 0, phase: 'intro' },

        async start() {
            this.thinking = true;
            const res = await post('start');
            this.thinking = false;
            this.applyAgent(res);
        },
        async send() {
            const text = this.draft.trim();
            if (!text || this.thinking) return;
            this.transcript.push({ role: 'candidate', text });
            this.draft = '';
            this.scroll();
            this.thinking = true;
            const res = await post('answer', { text, client_token: crypto.randomUUID() });
            this.thinking = false;
            this.applyAgent(res);
        },
        applyAgent(res) {
            if (res.progress) this.progress = res.progress;
            if (res.agent && res.agent.text) {
                this.transcript.push({ role: 'agent', text: res.agent.text });
            }
            if (res.status === 'concluded') this.concluded = true;
            this.scroll();
        },
        scroll() {
            this.$nextTick(() => { this.$refs.log.scrollTop = this.$refs.log.scrollHeight; });
        },
    };
}
</script>
@endsection
