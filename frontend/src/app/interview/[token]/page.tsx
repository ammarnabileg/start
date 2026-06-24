"use client";

import { interviewApi } from "@/lib/api";
import { formatRelativeTime } from "@/lib/utils";
import { CheckCircle, Loader2, Mic, MicOff, Send, Video, VideoOff, Volume2, Zap } from "lucide-react";
import { useParams, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import toast from "react-hot-toast";

interface Message {
  role: "user" | "assistant";
  content: string;
  created_at: string;
}

interface SessionInfo {
  job: { title: string; company: string };
  interview_type: string;
  max_questions: number;
  questions_asked: number;
  status: string;
  messages: Message[];
}

export default function InterviewPage() {
  const params = useParams();
  const searchParams = useSearchParams();
  const token = params.token as string;

  const [status, setStatus] = useState<"loading" | "valid" | "invalid" | "expired" | "completed" | "started">("loading");
  const [session, setSession] = useState<SessionInfo | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState("");
  const [sending, setSending] = useState(false);
  const [starting, setStarting] = useState(false);
  const [interviewDone, setInterviewDone] = useState(false);
  const [feedbackDone, setFeedbackDone] = useState(false);
  const [showFeedback, setShowFeedback] = useState(false);
  const [feedback, setFeedback] = useState({ rating: 0, comment: "" });
  const [isRecording, setIsRecording] = useState(false);
  const [isMuted, setIsMuted] = useState(false);
  const [cameraOn, setCameraOn] = useState(true);
  const [heygenSessionId, setHeygenSessionId] = useState<string | null>(null);

  const bottomRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);
  const streamRef = useRef<MediaStream | null>(null);

  const interviewType = session?.interview_type || searchParams.get("type") || "text";

  useEffect(() => {
    interviewApi.validateToken(token).then((res) => {
      const { status: s, session: sess } = res.data;
      setStatus(s === "valid" ? "valid" : s);
      if (sess) {
        setSession(sess);
        setMessages(sess.messages || []);
        if (sess.status === "in_progress") setStatus("started");
        if (sess.status === "completed") { setStatus("completed"); setInterviewDone(true); }
      }
    }).catch(() => setStatus("invalid"));
  }, [token]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  const startInterview = async () => {
    setStarting(true);
    try {
      const res = await interviewApi.start(token);
      setSession(res.data.session);
      setMessages(res.data.session.messages || []);
      setStatus("started");
      if (interviewType === "video" && res.data.session) {
        const hRes = await interviewApi.heygenSession(token);
        setHeygenSessionId(hRes.data.session_id);
      }
    } catch {
      toast.error("خطأ في بدء المقابلة");
    } finally {
      setStarting(false);
    }
  };

  const sendMessage = async (content?: string) => {
    const text = content || input.trim();
    if (!text || sending) return;
    setInput("");
    setSending(true);
    const newMsg: Message = { role: "user", content: text, created_at: new Date().toISOString() };
    setMessages((p) => [...p, newMsg]);
    try {
      const res = await interviewApi.sendMessage(token, text);
      const data = res.data;
      if (data.reply) {
        setMessages((p) => [...p, { role: "assistant", content: data.reply, created_at: new Date().toISOString() }]);
      }
      if (data.interview_complete) {
        setInterviewDone(true);
        setTimeout(() => setShowFeedback(true), 1500);
      }
      if (data.session) setSession(data.session);
    } catch {
      toast.error("خطأ في إرسال الرسالة");
      setMessages((p) => p.filter((m) => m !== newMsg));
    } finally {
      setSending(false);
    }
  };

  const startRecording = useCallback(async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      streamRef.current = stream;
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];
      mediaRecorder.ondataavailable = (e) => audioChunksRef.current.push(e.data);
      mediaRecorder.onstop = async () => {
        const blob = new Blob(audioChunksRef.current, { type: "audio/webm" });
        const formData = new FormData();
        formData.append("audio", blob, "voice.webm");
        try {
          const res = await interviewApi.transcribe(token, formData);
          if (res.data.text) sendMessage(res.data.text);
        } catch { toast.error("خطأ في تحويل الصوت"); }
        stream.getTracks().forEach((t) => t.stop());
      };
      mediaRecorder.start();
      setIsRecording(true);
    } catch { toast.error("لا يمكن الوصول للميكروفون"); }
  }, [token]);

  const stopRecording = useCallback(() => {
    mediaRecorderRef.current?.stop();
    setIsRecording(false);
  }, []);

  const submitFeedback = async () => {
    try {
      await interviewApi.feedback(token, feedback);
      setFeedbackDone(true);
      setShowFeedback(false);
      toast.success("شكراً على تقييمك!");
    } catch { toast.error("خطأ في إرسال التقييم"); }
  };

  // --- Render states ---
  if (status === "loading") {
    return (
      <Screen>
        <Loader2 className="w-10 h-10 text-violet-400 animate-spin" />
        <p className="text-white/60 text-sm mt-4">جاري التحقق من الرابط...</p>
      </Screen>
    );
  }

  if (status === "invalid") {
    return (
      <Screen>
        <div className="text-center">
          <div className="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <span className="text-3xl">✗</span>
          </div>
          <h2 className="text-xl font-bold text-white mb-2">رابط غير صالح</h2>
          <p className="text-white/50 text-sm">هذا الرابط غير صحيح أو لم يتم العثور عليه.</p>
        </div>
      </Screen>
    );
  }

  if (status === "expired") {
    return (
      <Screen>
        <div className="text-center">
          <div className="w-16 h-16 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <span className="text-3xl">⏰</span>
          </div>
          <h2 className="text-xl font-bold text-white mb-2">انتهت صلاحية الرابط</h2>
          <p className="text-white/50 text-sm">هذا الرابط منتهي الصلاحية. تواصل مع جهة التوظيف للحصول على رابط جديد.</p>
        </div>
      </Screen>
    );
  }

  if (status === "completed" || interviewDone) {
    return (
      <Screen>
        <div className="text-center max-w-sm">
          <div className="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
            <CheckCircle className="w-10 h-10 text-green-400" />
          </div>
          <h2 className="text-2xl font-bold text-white mb-3">انتهت المقابلة!</h2>
          <p className="text-white/60 text-sm leading-relaxed">
            شكراً لإتمام مقابلتك معنا. سيتم مراجعة إجاباتك وسنتواصل معك قريباً.
          </p>
          {showFeedback && !feedbackDone && (
            <div className="mt-8 p-5 bg-white/10 backdrop-blur rounded-2xl text-right">
              <h3 className="text-white font-bold mb-4">كيف كانت تجربتك؟</h3>
              <div className="flex gap-2 justify-center mb-4">
                {[1, 2, 3, 4, 5].map((n) => (
                  <button key={n} onClick={() => setFeedback(p => ({ ...p, rating: n }))}
                    className={`w-10 h-10 rounded-full text-lg transition-all ${feedback.rating >= n ? "bg-violet-500 text-white scale-110" : "bg-white/10 text-white/50"}`}>
                    ★
                  </button>
                ))}
              </div>
              <textarea value={feedback.comment} onChange={(e) => setFeedback(p => ({ ...p, comment: e.target.value }))}
                placeholder="أي ملاحظات؟ (اختياري)" rows={2}
                className="w-full px-3 py-2 text-sm bg-white/10 border border-white/20 rounded-lg text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none mb-3" />
              <button onClick={submitFeedback} className="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white text-sm font-bold rounded-lg transition-colors">
                إرسال التقييم
              </button>
            </div>
          )}
        </div>
      </Screen>
    );
  }

  // Landing / pre-start
  if (status === "valid") {
    return (
      <Screen>
        <div className="text-center max-w-md px-4">
          <div className="w-16 h-16 bg-violet-500/20 rounded-2xl flex items-center justify-center mx-auto mb-6">
            <Zap className="w-8 h-8 text-violet-400" />
          </div>
          <h2 className="text-2xl font-bold text-white mb-2">مرحباً بك في المقابلة</h2>
          {session && (
            <>
              <p className="text-violet-300 font-semibold text-lg mb-1">{session.job?.title}</p>
              <p className="text-white/40 text-sm mb-6">{session.job?.company}</p>
            </>
          )}
          <div className="grid grid-cols-3 gap-3 mb-8 text-center">
            {[
              { label: "النوع", value: interviewType === "text" ? "نصي" : interviewType === "voice" ? "صوتي" : "فيديو" },
              { label: "عدد الأسئلة", value: `${session?.max_questions || 12} سؤال` },
              { label: "اللغة", value: "عربي / English" },
            ].map((info) => (
              <div key={info.label} className="p-3 bg-white/10 rounded-xl">
                <p className="text-white/40 text-xs mb-1">{info.label}</p>
                <p className="text-white text-sm font-medium">{info.value}</p>
              </div>
            ))}
          </div>
          <div className="p-4 bg-white/5 border border-white/10 rounded-xl text-right mb-6">
            <h3 className="text-white/80 text-sm font-bold mb-2">تعليمات المقابلة</h3>
            <ul className="text-white/40 text-xs space-y-1.5">
              <li>• أجب بوضوح وبتفصيل كافٍ</li>
              <li>• يمكنك استخدام العربية أو الإنجليزية</li>
              <li>• لا يمكن إيقاف المقابلة بعد البدء</li>
              {interviewType !== "text" && <li>• تأكد من سماح المتصفح بالوصول للميكروفون</li>}
            </ul>
          </div>
          <button onClick={startInterview} disabled={starting}
            className="w-full py-3.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl transition-all flex items-center justify-center gap-2">
            {starting ? <Loader2 className="w-5 h-5 animate-spin" /> : null}
            {starting ? "جاري التحضير..." : "بدء المقابلة"}
          </button>
        </div>
      </Screen>
    );
  }

  // --- Interview in progress ---
  const progress = session ? (session.questions_asked / session.max_questions) * 100 : 0;

  return (
    <div className="min-h-screen bg-[#0f0c29] flex flex-col" dir="rtl">
      {/* Header */}
      <header className="h-14 bg-black/30 backdrop-blur border-b border-white/10 flex items-center justify-between px-4 flex-shrink-0">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center">
            <Zap className="w-4 h-4 text-white" />
          </div>
          <div>
            <p className="text-white text-sm font-bold leading-none">{session?.job?.title}</p>
            <p className="text-white/40 text-xs">{session?.job?.company}</p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          {/* Progress */}
          <div className="flex items-center gap-2">
            <div className="w-24 h-1.5 bg-white/10 rounded-full overflow-hidden">
              <div className="h-full bg-violet-500 rounded-full transition-all" style={{ width: `${progress}%` }} />
            </div>
            <span className="text-white/40 text-xs">{session?.questions_asked}/{session?.max_questions}</span>
          </div>
          {/* Type controls */}
          {interviewType === "voice" && (
            <button onClick={() => setIsMuted(!isMuted)} className={`p-1.5 rounded-lg ${isMuted ? "bg-red-500/20 text-red-400" : "bg-white/10 text-white/60"}`}>
              {isMuted ? <MicOff className="w-4 h-4" /> : <Volume2 className="w-4 h-4" />}
            </button>
          )}
          {interviewType === "video" && (
            <button onClick={() => setCameraOn(!cameraOn)} className={`p-1.5 rounded-lg ${!cameraOn ? "bg-red-500/20 text-red-400" : "bg-white/10 text-white/60"}`}>
              {cameraOn ? <Video className="w-4 h-4" /> : <VideoOff className="w-4 h-4" />}
            </button>
          )}
        </div>
      </header>

      {/* Video / Avatar area (video type) */}
      {interviewType === "video" && heygenSessionId && (
        <div className="bg-black flex-shrink-0 h-48 flex items-center justify-center">
          <div className="flex items-center gap-4">
            <div className="w-32 h-32 bg-violet-900/50 rounded-2xl flex items-center justify-center border border-violet-500/30">
              <Video className="w-8 h-8 text-violet-400" />
            </div>
            <div className="text-white/40 text-xs">AI Avatar • {heygenSessionId.slice(0, 8)}...</div>
          </div>
        </div>
      )}

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
        {messages.map((msg, i) => (
          <div key={i} className={`flex gap-3 ${msg.role === "user" ? "flex-row-reverse" : "flex-row"}`}>
            <div className={`w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold ${
              msg.role === "assistant" ? "bg-violet-600 text-white" : "bg-white/20 text-white"
            }`}>
              {msg.role === "assistant" ? "AI" : "أنت"}
            </div>
            <div className={`max-w-[75%] px-4 py-3 rounded-2xl text-sm leading-relaxed ${
              msg.role === "assistant"
                ? "bg-white/10 text-white/90 rounded-tr-sm"
                : "bg-violet-600 text-white rounded-tl-sm"
            }`}>
              {msg.content}
            </div>
          </div>
        ))}
        {sending && (
          <div className="flex gap-3">
            <div className="w-8 h-8 rounded-full bg-violet-600 flex-shrink-0 flex items-center justify-center text-xs font-bold text-white">AI</div>
            <div className="px-4 py-3 bg-white/10 rounded-2xl rounded-tr-sm">
              <div className="flex gap-1">
                {[0, 1, 2].map((i) => (
                  <div key={i} className="w-2 h-2 bg-white/40 rounded-full animate-bounce" style={{ animationDelay: `${i * 0.15}s` }} />
                ))}
              </div>
            </div>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input area */}
      <div className="p-4 bg-black/20 backdrop-blur border-t border-white/10 flex-shrink-0">
        {interviewType === "text" && (
          <div className="flex gap-3 items-end">
            <textarea
              ref={inputRef}
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMessage(); } }}
              placeholder="اكتب إجابتك هنا... (Enter للإرسال)"
              rows={2}
              disabled={sending || interviewDone}
              className="flex-1 px-4 py-3 text-sm bg-white/10 border border-white/20 rounded-xl text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none disabled:opacity-50"
            />
            <button onClick={() => sendMessage()} disabled={!input.trim() || sending || interviewDone}
              className="w-11 h-11 bg-violet-600 hover:bg-violet-500 disabled:opacity-40 text-white rounded-xl flex items-center justify-center flex-shrink-0 transition-colors">
              <Send className="w-4 h-4" />
            </button>
          </div>
        )}
        {interviewType === "voice" && (
          <div className="flex flex-col items-center gap-3">
            <button
              onMouseDown={startRecording}
              onMouseUp={stopRecording}
              onTouchStart={startRecording}
              onTouchEnd={stopRecording}
              disabled={sending || interviewDone}
              className={`w-16 h-16 rounded-full flex items-center justify-center transition-all shadow-lg ${
                isRecording
                  ? "bg-red-500 shadow-red-500/40 scale-110 animate-pulse"
                  : "bg-violet-600 hover:bg-violet-500 shadow-violet-500/40"
              } disabled:opacity-50`}
            >
              {isRecording ? <MicOff className="w-7 h-7 text-white" /> : <Mic className="w-7 h-7 text-white" />}
            </button>
            <p className="text-white/40 text-xs">{isRecording ? "جاري التسجيل... اترك الزر عند الانتهاء" : "اضغط مطولاً للتحدث"}</p>
          </div>
        )}
        {interviewType === "video" && (
          <div className="flex items-center justify-center gap-4">
            <button
              onMouseDown={startRecording}
              onMouseUp={stopRecording}
              disabled={sending || interviewDone}
              className={`w-14 h-14 rounded-full flex items-center justify-center transition-all ${
                isRecording ? "bg-red-500 animate-pulse" : "bg-violet-600 hover:bg-violet-500"
              } disabled:opacity-50`}
            >
              {isRecording ? <MicOff className="w-6 h-6 text-white" /> : <Mic className="w-6 h-6 text-white" />}
            </button>
            <button onClick={() => setCameraOn(!cameraOn)}
              className={`w-12 h-12 rounded-full flex items-center justify-center ${cameraOn ? "bg-white/10" : "bg-red-500/30"}`}>
              {cameraOn ? <Video className="w-5 h-5 text-white/60" /> : <VideoOff className="w-5 h-5 text-red-400" />}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

function Screen({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-950 via-violet-950 to-indigo-900 flex items-center justify-center p-6" dir="rtl">
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        {[...Array(30)].map((_, i) => (
          <div key={i} className="absolute w-1 h-1 bg-white rounded-full opacity-15"
            style={{ top: `${(i * 47) % 100}%`, left: `${(i * 53) % 100}%` }} />
        ))}
      </div>
      <div className="relative z-10 flex flex-col items-center">{children}</div>
    </div>
  );
}
