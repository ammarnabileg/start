"use client";

import { setupApi } from "@/lib/api";
import { CheckCircle, Database, Key, Loader2, Server, Settings, User, Zap } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

const STEPS = [
  { id: 1, title: "فحص النظام", icon: Server },
  { id: 2, title: "قاعدة البيانات", icon: Database },
  { id: 3, title: "المسؤول الأعلى", icon: User },
  { id: 4, title: "مفاتيح الذكاء الاصطناعي", icon: Key },
  { id: 5, title: "التثبيت", icon: Settings },
];

export default function SetupPage() {
  const router = useRouter();
  const [step, setStep] = useState(1);
  const [checking, setChecking] = useState(true);
  const [sysInfo, setSysInfo] = useState<Record<string, { status: boolean; value: string }>>({});
  const [installing, setInstalling] = useState(false);
  const [installed, setInstalled] = useState(false);

  const [db, setDb] = useState({ host: "127.0.0.1", port: "3306", name: "ai_recruitment", user: "root", password: "" });
  const [admin, setAdmin] = useState({ name: "", email: "", password: "", password_confirmation: "" });
  const [ai, setAi] = useState({ openai_key: "", heygen_key: "", app_name: "AI Recruit" });

  useEffect(() => {
    setupApi.status().then((res) => {
      if (res.data.installed) { router.replace("/login"); return; }
      setupApi.check().then((r) => { setSysInfo(r.data); setChecking(false); });
    }).catch(() => {
      setupApi.check().then((r) => { setSysInfo(r.data); setChecking(false); });
    });
  }, [router]);

  const allChecksPass = Object.values(sysInfo).every((v) => v.status);

  const install = async () => {
    setInstalling(true);
    try {
      await setupApi.install({ db, admin, ai });
      setInstalled(true);
      setTimeout(() => router.push("/login"), 3000);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      toast.error(e?.response?.data?.message || "فشل التثبيت");
      setInstalling(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-950 via-violet-950 to-indigo-900 flex items-center justify-center p-6" dir="rtl">
      {/* Stars */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        {[...Array(50)].map((_, i) => (
          <div key={i} className="absolute w-1 h-1 bg-white rounded-full opacity-20"
            style={{ top: `${(i * 37) % 100}%`, left: `${(i * 61) % 100}%` }} />
        ))}
      </div>

      <div className="relative z-10 w-full max-w-2xl">
        {/* Logo */}
        <div className="flex items-center justify-center gap-3 mb-8">
          <div className="w-12 h-12 bg-violet-500 rounded-2xl flex items-center justify-center shadow-lg shadow-violet-500/30">
            <Zap className="w-7 h-7 text-white" />
          </div>
          <span className="text-2xl font-bold text-white">AI Recruit</span>
        </div>

        {/* Step indicators */}
        <div className="flex items-center justify-center mb-8 gap-0">
          {STEPS.map((s, i) => (
            <div key={s.id} className="flex items-center">
              <div className={`flex flex-col items-center ${i < STEPS.length - 1 ? "min-w-0" : ""}`}>
                <div className={`w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all ${
                  step > s.id ? "bg-violet-500 text-white" : step === s.id ? "bg-white text-violet-700 ring-2 ring-violet-400 ring-offset-2 ring-offset-transparent" : "bg-white/10 text-white/40"
                }`}>
                  {step > s.id ? <CheckCircle className="w-5 h-5" /> : s.id}
                </div>
                <span className={`text-xs mt-1.5 font-medium whitespace-nowrap ${step === s.id ? "text-white" : "text-white/40"}`}>{s.title}</span>
              </div>
              {i < STEPS.length - 1 && (
                <div className={`h-0.5 w-12 mx-1 mb-5 ${step > s.id + 1 ? "bg-violet-500" : step > s.id ? "bg-violet-400" : "bg-white/10"}`} />
              )}
            </div>
          ))}
        </div>

        {/* Card */}
        <div className="bg-white rounded-2xl shadow-2xl shadow-black/30 overflow-hidden">
          <div className="bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-4">
            <h2 className="text-white font-bold text-lg">{STEPS[step - 1].title}</h2>
            <p className="text-violet-200 text-sm">خطوة {step} من {STEPS.length}</p>
          </div>

          <div className="p-6">
            {/* Step 1: System Check */}
            {step === 1 && (
              <div className="space-y-3">
                {checking ? (
                  <div className="flex items-center justify-center py-8 gap-3">
                    <Loader2 className="w-6 h-6 text-violet-600 animate-spin" />
                    <span className="text-gray-500">جاري فحص النظام...</span>
                  </div>
                ) : (
                  <>
                    {Object.entries(sysInfo).map(([key, info]) => (
                      <div key={key} className="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                        <div className="flex items-center gap-2">
                          <div className={`w-2 h-2 rounded-full ${info.status ? "bg-green-500" : "bg-red-500"}`} />
                          <span className="text-sm font-medium text-gray-700">{key}</span>
                        </div>
                        <span className={`text-xs font-medium ${info.status ? "text-green-600" : "text-red-600"}`}>{info.value}</span>
                      </div>
                    ))}
                    {!allChecksPass && (
                      <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
                        يرجى إصلاح المتطلبات غير المستوفاة قبل المتابعة.
                      </div>
                    )}
                  </>
                )}
              </div>
            )}

            {/* Step 2: Database */}
            {step === 2 && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <FormField label="المضيف" value={db.host} onChange={(v) => setDb(p => ({ ...p, host: v }))} placeholder="127.0.0.1" />
                  <FormField label="المنفذ" value={db.port} onChange={(v) => setDb(p => ({ ...p, port: v }))} placeholder="3306" />
                </div>
                <FormField label="اسم قاعدة البيانات" value={db.name} onChange={(v) => setDb(p => ({ ...p, name: v }))} placeholder="ai_recruitment" />
                <div className="grid grid-cols-2 gap-4">
                  <FormField label="المستخدم" value={db.user} onChange={(v) => setDb(p => ({ ...p, user: v }))} placeholder="root" />
                  <FormField label="كلمة المرور" value={db.password} onChange={(v) => setDb(p => ({ ...p, password: v }))} placeholder="••••••••" type="password" />
                </div>
              </div>
            )}

            {/* Step 3: Admin */}
            {step === 3 && (
              <div className="space-y-4">
                <FormField label="الاسم الكامل" value={admin.name} onChange={(v) => setAdmin(p => ({ ...p, name: v }))} placeholder="مسؤول النظام" />
                <FormField label="البريد الإلكتروني" value={admin.email} onChange={(v) => setAdmin(p => ({ ...p, email: v }))} placeholder="admin@company.com" type="email" />
                <FormField label="كلمة المرور" value={admin.password} onChange={(v) => setAdmin(p => ({ ...p, password: v }))} placeholder="••••••••" type="password" />
                <FormField label="تأكيد كلمة المرور" value={admin.password_confirmation} onChange={(v) => setAdmin(p => ({ ...p, password_confirmation: v }))} placeholder="••••••••" type="password" />
              </div>
            )}

            {/* Step 4: AI Keys */}
            {step === 4 && (
              <div className="space-y-4">
                <FormField label="اسم التطبيق" value={ai.app_name} onChange={(v) => setAi(p => ({ ...p, app_name: v }))} placeholder="AI Recruit" />
                <div>
                  <FormField label="مفتاح OpenAI API" value={ai.openai_key} onChange={(v) => setAi(p => ({ ...p, openai_key: v }))} placeholder="sk-..." type="password" />
                  <p className="text-xs text-gray-400 mt-1">مطلوب لجميع ميزات الذكاء الاصطناعي</p>
                </div>
                <div>
                  <FormField label="مفتاح HeyGen API (اختياري)" value={ai.heygen_key} onChange={(v) => setAi(p => ({ ...p, heygen_key: v }))} placeholder="..." type="password" />
                  <p className="text-xs text-gray-400 mt-1">مطلوب فقط لمقابلات الفيديو بالأفاتار</p>
                </div>
              </div>
            )}

            {/* Step 5: Install */}
            {step === 5 && (
              <div className="py-4">
                {installed ? (
                  <div className="text-center space-y-4">
                    <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
                      <CheckCircle className="w-9 h-9 text-green-500" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900">تم التثبيت بنجاح!</h3>
                    <p className="text-gray-500 text-sm">جاري تحويلك لصفحة تسجيل الدخول...</p>
                    <div className="flex justify-center">
                      <Loader2 className="w-5 h-5 text-violet-500 animate-spin" />
                    </div>
                  </div>
                ) : (
                  <div className="space-y-4">
                    <div className="p-4 bg-violet-50 border border-violet-200 rounded-xl">
                      <h3 className="font-bold text-violet-900 mb-3">ملخص التثبيت</h3>
                      <div className="space-y-2 text-sm text-violet-700">
                        <div className="flex justify-between"><span>قاعدة البيانات</span><span className="font-medium">{db.name} @ {db.host}</span></div>
                        <div className="flex justify-between"><span>المسؤول</span><span className="font-medium">{admin.email}</span></div>
                        <div className="flex justify-between"><span>OpenAI</span><span className="font-medium">{ai.openai_key ? "✓ مضبوط" : "✗ غير مضبوط"}</span></div>
                        <div className="flex justify-between"><span>HeyGen</span><span className="font-medium">{ai.heygen_key ? "✓ مضبوط" : "— اختياري"}</span></div>
                      </div>
                    </div>
                    <p className="text-sm text-gray-500">سيتم تشغيل عمليات قاعدة البيانات والإعداد الأولي. قد يستغرق هذا دقيقة.</p>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Footer */}
          {!installed && (
            <div className="px-6 pb-6 flex items-center justify-between">
              {step > 1 ? (
                <button onClick={() => setStep(p => p - 1)} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 font-medium">
                  السابق
                </button>
              ) : <div />}

              {step < 5 ? (
                <button
                  onClick={() => setStep(p => p + 1)}
                  disabled={step === 1 && (!allChecksPass || checking)}
                  className="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-lg transition-colors"
                >
                  التالي
                </button>
              ) : (
                <button
                  onClick={install}
                  disabled={installing}
                  className="px-6 py-2.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 text-white text-sm font-bold rounded-lg transition-colors flex items-center gap-2"
                >
                  {installing && <Loader2 className="w-4 h-4 animate-spin" />}
                  {installing ? "جاري التثبيت..." : "تثبيت المنصة"}
                </button>
              )}
            </div>
          )}
        </div>

        <p className="text-center text-white/30 text-xs mt-6">AI Recruit — منصة التوظيف الذكية</p>
      </div>
    </div>
  );
}

function FormField({ label, value, onChange, placeholder, type = "text" }: {
  label: string; value: string; onChange: (v: string) => void; placeholder?: string; type?: string;
}) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full px-3 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all"
      />
    </div>
  );
}
