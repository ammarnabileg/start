"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import Link from "next/link";
import { Zap, User, Mail, Lock, Building, ChevronRight, ChevronLeft, Check, Eye, EyeOff } from "lucide-react";

const STEPS = [
  { id: 1, title: "Your Info", description: "Tell us about yourself" },
  { id: 2, title: "Business", description: "About your brand" },
  { id: 3, title: "Security", description: "Secure your account" },
];

const INDUSTRIES = [
  "E-commerce", "Fashion & Beauty", "Food & Beverage", "Technology",
  "Healthcare", "Real Estate", "Education", "Entertainment", "Finance", "Other"
];

export default function RegisterPage() {
  const [step, setStep] = useState(1);
  const [isLoading, setIsLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [agreed, setAgreed] = useState(false);
  const [form, setForm] = useState({
    name: "", email: "",
    businessName: "", industry: "",
    password: "", confirmPassword: "",
  });

  const update = (key: string, value: string) => setForm(f => ({ ...f, [key]: value }));

  const handleSubmit = async () => {
    setIsLoading(true);
    await new Promise(r => setTimeout(r, 1500));
    window.location.href = "/connect-platforms";
  };

  const passwordStrength = () => {
    const p = form.password;
    if (p.length < 6) return { score: 0, label: "Too short", color: "#EF4444" };
    if (p.length < 8) return { score: 1, label: "Weak", color: "#F59E0B" };
    if (/(?=.*[A-Z])(?=.*[0-9])/.test(p)) return { score: 3, label: "Strong", color: "#10B981" };
    return { score: 2, label: "Good", color: "#3B82F6" };
  };
  const strength = passwordStrength();

  return (
    <div className="min-h-screen bg-[#0A0B1A] flex items-center justify-center relative overflow-hidden py-10">
      {/* Glow orbs */}
      <div className="absolute top-1/4 right-1/4 w-96 h-96 bg-neon-purple/10 rounded-full blur-[120px] pointer-events-none" />
      <div className="absolute bottom-1/4 left-1/4 w-96 h-96 bg-electric-blue/10 rounded-full blur-[120px] pointer-events-none" />

      <div className="relative z-10 w-full max-w-lg px-4">
        {/* Logo */}
        <motion.div initial={{ opacity: 0, y: -20 }} animate={{ opacity: 1, y: 0 }} className="text-center mb-8">
          <Link href="/login" className="inline-flex items-center gap-2 mb-4">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-electric-blue to-neon-purple flex items-center justify-center">
              <Zap className="w-5 h-5 text-white" />
            </div>
            <span className="text-xl font-bold text-gradient">SociAI OS</span>
          </Link>
          <h1 className="text-2xl font-bold text-white">Create your account</h1>
          <p className="text-white/40 text-sm mt-1">Join thousands of brands growing with AI</p>
        </motion.div>

        {/* Step Indicator */}
        <div className="flex items-center justify-center mb-8 gap-0">
          {STEPS.map((s, idx) => (
            <div key={s.id} className="flex items-center">
              <motion.div
                animate={{
                  background: step > s.id ? "linear-gradient(135deg,#10B981,#059669)" :
                    step === s.id ? "linear-gradient(135deg,#3B82F6,#8B5CF6)" : "rgba(255,255,255,0.08)"
                }}
                className="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white border border-white/10 relative z-10"
              >
                {step > s.id ? <Check className="w-5 h-5" /> : s.id}
              </motion.div>
              {idx < STEPS.length - 1 && (
                <div className={`w-16 h-0.5 transition-all duration-500 ${step > s.id ? "bg-electric-blue" : "bg-white/10"}`} />
              )}
            </div>
          ))}
        </div>

        {/* Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="glass-panel p-8 rounded-2xl"
        >
          <div className="mb-6">
            <h2 className="text-xl font-bold text-white">{STEPS[step - 1].title}</h2>
            <p className="text-white/40 text-sm">{STEPS[step - 1].description}</p>
          </div>

          <AnimatePresence mode="wait">
            {step === 1 && (
              <motion.div key="step1" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} className="space-y-4">
                <div>
                  <label className="text-white/60 text-sm font-medium mb-1.5 block">Full Name</label>
                  <div className="relative">
                    <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                    <input type="text" value={form.name} onChange={e => update("name", e.target.value)} placeholder="John Doe" className="input-glass pl-10" />
                  </div>
                </div>
                <div>
                  <label className="text-white/60 text-sm font-medium mb-1.5 block">Email Address</label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                    <input type="email" value={form.email} onChange={e => update("email", e.target.value)} placeholder="you@company.com" className="input-glass pl-10" />
                  </div>
                </div>
              </motion.div>
            )}

            {step === 2 && (
              <motion.div key="step2" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} className="space-y-4">
                <div>
                  <label className="text-white/60 text-sm font-medium mb-1.5 block">Business / Brand Name</label>
                  <div className="relative">
                    <Building className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                    <input type="text" value={form.businessName} onChange={e => update("businessName", e.target.value)} placeholder="Acme Corp" className="input-glass pl-10" />
                  </div>
                </div>
                <div>
                  <label className="text-white/60 text-sm font-medium mb-1.5 block">Industry</label>
                  <div className="grid grid-cols-2 gap-2">
                    {INDUSTRIES.map(ind => (
                      <button
                        key={ind}
                        type="button"
                        onClick={() => update("industry", ind)}
                        className={`text-left px-3 py-2 rounded-lg text-sm border transition-all ${
                          form.industry === ind
                            ? "bg-electric-blue/20 border-electric-blue/50 text-electric-blue"
                            : "bg-white/5 border-white/10 text-white/50 hover:bg-white/8 hover:text-white/70"
                        }`}
                      >
                        {ind}
                      </button>
                    ))}
                  </div>
                </div>
              </motion.div>
            )}

            {step === 3 && (
              <motion.div key="step3" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} className="space-y-4">
                <div>
                  <label className="text-white/60 text-sm font-medium mb-1.5 block">Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                    <input type={showPassword ? "text" : "password"} value={form.password} onChange={e => update("password", e.target.value)} placeholder="Min. 8 characters" className="input-glass pl-10 pr-10" />
                    <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3 top-1/2 -translate-y-1/2 text-white/30 hover:text-white/60">
                      {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                  {form.password && (
                    <div className="mt-2">
                      <div className="flex gap-1 mb-1">
                        {[1, 2, 3].map(i => (
                          <div key={i} className="h-1 flex-1 rounded-full transition-all" style={{ background: i <= strength.score ? strength.color : "rgba(255,255,255,0.1)" }} />
                        ))}
                      </div>
                      <span className="text-xs" style={{ color: strength.color }}>{strength.label}</span>
                    </div>
                  )}
                </div>
                <div>
                  <label className="text-white/60 text-sm font-medium mb-1.5 block">Confirm Password</label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                    <input type="password" value={form.confirmPassword} onChange={e => update("confirmPassword", e.target.value)} placeholder="Repeat your password" className="input-glass pl-10" />
                    {form.confirmPassword && (
                      <div className={`absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full flex items-center justify-center ${form.password === form.confirmPassword ? "bg-neon-green" : "bg-red-500"}`}>
                        {form.password === form.confirmPassword ? <Check className="w-3 h-3 text-white" /> : <span className="text-white text-xs">✕</span>}
                      </div>
                    )}
                  </div>
                </div>
                <label className="flex items-start gap-3 cursor-pointer">
                  <div onClick={() => setAgreed(!agreed)} className={`w-4 h-4 mt-0.5 rounded border transition-all flex-shrink-0 ${agreed ? "bg-electric-blue border-electric-blue" : "border-white/20 bg-white/5"}`}>
                    {agreed && <Check className="w-full h-full text-white p-0.5" />}
                  </div>
                  <span className="text-white/50 text-sm">
                    I agree to the{" "}
                    <Link href="#" className="text-electric-blue hover:underline">Terms of Service</Link>{" "}
                    and{" "}
                    <Link href="#" className="text-electric-blue hover:underline">Privacy Policy</Link>
                  </span>
                </label>
              </motion.div>
            )}
          </AnimatePresence>

          {/* Navigation */}
          <div className="flex gap-3 mt-8">
            {step > 1 && (
              <button onClick={() => setStep(s => s - 1)} className="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/60 text-sm font-medium transition-all">
                <ChevronLeft className="w-4 h-4" /> Back
              </button>
            )}
            {step < 3 ? (
              <button
                onClick={() => setStep(s => s + 1)}
                disabled={
                  (step === 1 && (!form.name || !form.email)) ||
                  (step === 2 && !form.businessName)
                }
                className="flex-1 btn-primary py-2.5 rounded-xl font-semibold text-white flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Continue <ChevronRight className="w-4 h-4" />
              </button>
            ) : (
              <motion.button
                onClick={handleSubmit}
                disabled={isLoading || !agreed || !form.password || form.password !== form.confirmPassword}
                whileHover={{ scale: 1.01 }}
                whileTap={{ scale: 0.99 }}
                className="flex-1 btn-primary py-2.5 rounded-xl font-semibold text-white flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isLoading ? (
                  <motion.div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full" animate={{ rotate: 360 }} transition={{ duration: 0.8, repeat: Infinity, ease: "linear" }} />
                ) : (
                  <><Check className="w-4 h-4" /> Create Account</>
                )}
              </motion.button>
            )}
          </div>
        </motion.div>

        <p className="text-center text-white/40 text-sm mt-6">
          Already have an account?{" "}
          <Link href="/login" className="text-electric-blue hover:text-blue-300 transition-colors font-medium">Sign in</Link>
        </p>
      </div>
    </div>
  );
}
