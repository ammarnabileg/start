"use client";

import { AuthProvider, useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Eye, EyeOff, Zap } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import toast from "react-hot-toast";

function LoginForm() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      const { redirect } = await login(email, password);
      toast.success("مرحباً بعودتك!");
      router.push(redirect || "/dashboard");
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      setError(error?.response?.data?.message || "البريد الإلكتروني أو كلمة المرور غير صحيحة");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex" dir="rtl">
      {/* Left panel - Dark cosmic */}
      <div className="hidden lg:flex lg:w-1/2 bg-[#1e1b4b] relative overflow-hidden flex-col items-center justify-center p-12">
        {/* Stars background */}
        <div className="absolute inset-0 overflow-hidden">
          {[...Array(40)].map((_, i) => (
            <div key={i} className="absolute w-1 h-1 bg-white rounded-full opacity-30" style={{ top: `${Math.random() * 100}%`, left: `${Math.random() * 100}%`, animationDelay: `${Math.random() * 3}s` }} />
          ))}
        </div>

        {/* Gradient orbs */}
        <div className="absolute top-1/4 left-1/4 w-64 h-64 bg-violet-600/20 rounded-full blur-3xl" />
        <div className="absolute bottom-1/4 right-1/4 w-48 h-48 bg-indigo-500/20 rounded-full blur-3xl" />

        <div className="relative z-10 text-center text-white">
          <div className="w-16 h-16 bg-violet-500 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-violet-500/30">
            <Zap className="w-8 h-8 text-white" />
          </div>
          <h1 className="text-3xl font-bold mb-3">منصة التوظيف الذكية</h1>
          <p className="text-violet-200 text-base leading-relaxed max-w-xs mx-auto">
            أتمتة كاملة للمرحلة الأولى من التوظيف باستخدام الذكاء الاصطناعي
          </p>

          <div className="mt-12 space-y-4">
            {["تحليل السيرة الذاتية تلقائياً", "مقابلات ذكاء اصطناعي 24/7", "تقييم شامل للمرشحين", "لوحة قرارات متكاملة"].map((f, i) => (
              <div key={i} className="flex items-center gap-3 text-violet-200">
                <div className="w-5 h-5 rounded-full bg-violet-500/30 flex items-center justify-center flex-shrink-0">
                  <div className="w-2 h-2 rounded-full bg-violet-400" />
                </div>
                <span className="text-sm">{f}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Right panel - White form */}
      <div className="flex-1 flex flex-col items-center justify-center p-8 bg-white">
        <div className="w-full max-w-sm">
          {/* Logo for mobile */}
          <div className="flex items-center gap-2 mb-8 lg:mb-10">
            <div className="w-9 h-9 bg-violet-600 rounded-xl flex items-center justify-center">
              <Zap className="w-5 h-5 text-white" />
            </div>
            <span className="text-lg font-bold text-gray-900">AI Recruit</span>
          </div>

          <h2 className="text-2xl font-bold text-gray-900 mb-1">مرحباً بعودتك</h2>
          <p className="text-gray-500 text-sm mb-8">سجّل الدخول للوصول إلى لوحة التحكم</p>

          <form onSubmit={handleLogin} className="space-y-4">
            <Input
              label="البريد الإلكتروني"
              type="email"
              placeholder="email@company.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
            <div className="relative">
              <Input
                label="كلمة المرور"
                type={showPwd ? "text" : "password"}
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
              <button type="button" onClick={() => setShowPwd(!showPwd)} className="absolute left-3 top-9 text-gray-400 hover:text-gray-600">
                {showPwd ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>

            {error && (
              <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">{error}</div>
            )}

            <Button type="submit" variant="primary" loading={loading} className="w-full py-3 text-base font-bold mt-2">
              تسجيل الدخول
            </Button>
          </form>

          <p className="mt-8 text-center text-xs text-gray-400">
            منصة التوظيف الذكية — جميع الحقوق محفوظة
          </p>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <AuthProvider>
      <LoginForm />
    </AuthProvider>
  );
}
