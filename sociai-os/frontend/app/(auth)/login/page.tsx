"use client";
import { useState } from "react";
import { motion } from "framer-motion";
import { Eye, EyeOff, Zap, Lock, Mail, ArrowRight, Shield } from "lucide-react";
import Link from "next/link";

export default function LoginPage() {
  const [showPassword, setShowPassword] = useState(false);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    await new Promise((r) => setTimeout(r, 1500));
    window.location.href = "/dashboard";
  };

  return (
    <div className="min-h-screen bg-deep-navy flex items-center justify-center p-4 relative overflow-hidden">
      {/* Background particles */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        {Array.from({ length: 20 }).map((_, i) => (
          <motion.div key={i} className="absolute w-1 h-1 rounded-full bg-electric-blue-500/30"
            style={{ left: `${Math.random() * 100}%`, top: `${Math.random() * 100}%` }}
            animate={{ y: [-20, 20], opacity: [0.2, 0.8, 0.2] }}
            transition={{ duration: 3 + Math.random() * 4, repeat: Infinity, delay: Math.random() * 5 }}
          />
        ))}
        <div className="absolute top-1/4 left-1/4 w-96 h-96 rounded-full bg-electric-blue-600/5 blur-3xl" />
        <div className="absolute bottom-1/4 right-1/4 w-96 h-96 rounded-full bg-neon-purple-600/5 blur-3xl" />
      </div>

      <motion.div initial={{ opacity: 0, y: 30, scale: 0.95 }} animate={{ opacity: 1, y: 0, scale: 1 }} transition={{ duration: 0.4, ease: "easeOut" }}
        className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-electric-blue-500 to-neon-purple-500 flex items-center justify-center mx-auto mb-4 shadow-2xl shadow-electric-blue-500/40">
            <Zap size={28} className="text-white" />
          </div>
          <h1 className="text-3xl font-bold text-white">Welcome back</h1>
          <p className="text-slate-400 mt-1">Sign in to your SociAI OS dashboard</p>
        </div>

        {/* Card */}
        <div className="bg-white/4 backdrop-blur-xl border border-white/10 rounded-3xl p-8 shadow-2xl">
          <form onSubmit={handleLogin} className="space-y-5">
            <div>
              <label className="text-xs font-medium text-slate-400 block mb-2">Email Address</label>
              <div className="relative">
                <Mail size={15} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@company.com"
                  className="w-full bg-white/5 border border-white/10 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-electric-blue-500/60 transition-all" />
              </div>
            </div>
            <div>
              <label className="text-xs font-medium text-slate-400 block mb-2">Password</label>
              <div className="relative">
                <Lock size={15} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
                <input type={showPassword ? "text" : "password"} value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••"
                  className="w-full bg-white/5 border border-white/10 rounded-xl pl-11 pr-11 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-electric-blue-500/60 transition-all" />
                <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300">
                  {showPassword ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
              </div>
            </div>
            <div className="flex items-center justify-between text-xs">
              <label className="flex items-center gap-2 text-slate-400 cursor-pointer">
                <input type="checkbox" className="rounded border-white/20 bg-white/5" />
                Remember me
              </label>
              <a href="#" className="text-electric-blue-400 hover:text-electric-blue-300">Forgot password?</a>
            </div>
            <button type="submit" disabled={isLoading}
              className="w-full flex items-center justify-center gap-2 py-3.5 rounded-xl bg-gradient-to-r from-electric-blue-600 to-neon-purple-600 text-white font-bold hover:shadow-lg hover:shadow-electric-blue-500/40 transition-all disabled:opacity-70">
              {isLoading ? (
                <motion.div animate={{ rotate: 360 }} transition={{ duration: 1, repeat: Infinity, ease: "linear" }}>
                  <Zap size={16} />
                </motion.div>
              ) : (
                <><span>Sign In</span><ArrowRight size={16} /></>
              )}
            </button>
          </form>

          <div className="mt-5 pt-5 border-t border-white/8 text-center">
            <p className="text-sm text-slate-400">
              Don't have an account?{" "}
              <Link href="/register" className="text-electric-blue-400 hover:text-electric-blue-300 font-semibold">Start free trial</Link>
            </p>
          </div>
        </div>

        <div className="flex items-center justify-center gap-2 mt-6 text-xs text-slate-600">
          <Shield size={12} />
          <span>Protected by 256-bit AES encryption & 2FA</span>
        </div>
      </motion.div>
    </div>
  );
}
