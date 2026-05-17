"use client";
import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { CheckCircle2, Lock, ChevronRight, Shield, Zap, AlertTriangle } from "lucide-react";

const PLATFORMS = [
  { id: "linkedin", name: "LinkedIn", icon: "💼", color: "from-blue-600 to-blue-800", description: "Professional network & B2B content", authType: "oauth", required: false },
  { id: "instagram", name: "Instagram", icon: "📸", color: "from-pink-600 to-purple-600", description: "Visual content & stories", authType: "oauth", required: false },
  { id: "facebook", name: "Facebook", icon: "📘", color: "from-blue-600 to-blue-700", description: "Community & advertising", authType: "oauth", required: false },
  { id: "tiktok", name: "TikTok", icon: "🎵", color: "from-black to-gray-800", description: "Short-form video content", authType: "oauth", required: false },
  { id: "twitter", name: "X (Twitter)", icon: "🐦", color: "from-gray-800 to-black", description: "Real-time conversations", authType: "oauth", required: false },
  { id: "youtube", name: "YouTube", icon: "▶️", color: "from-red-600 to-red-800", description: "Long-form video content", authType: "oauth", required: false },
  { id: "snapchat", name: "Snapchat", icon: "👻", color: "from-yellow-400 to-yellow-500", description: "Stories & AR content", authType: "oauth", required: false },
  { id: "threads", name: "Threads", icon: "🧵", color: "from-gray-700 to-black", description: "Text-based conversations", authType: "oauth", required: false },
  { id: "pinterest", name: "Pinterest", icon: "📌", color: "from-red-600 to-red-700", description: "Visual discovery & boards", authType: "oauth", required: false },
  { id: "whatsapp", name: "WhatsApp Business", icon: "💬", color: "from-green-500 to-green-700", description: "Direct messaging & broadcasts", authType: "token", required: false },
  { id: "telegram", name: "Telegram Channels", icon: "✈️", color: "from-sky-500 to-sky-700", description: "Channel broadcasting", authType: "token", required: false },
];

export default function ConnectPlatformsPage() {
  const [connected, setConnected] = useState<Set<string>>(new Set());
  const [connecting, setConnecting] = useState<string | null>(null);

  const handleConnect = async (platformId: string) => {
    setConnecting(platformId);
    await new Promise((r) => setTimeout(r, 1800));
    setConnected((prev) => new Set(Array.from(prev).concat(platformId)));
    setConnecting(null);
  };

  const connectedCount = connected.size;

  return (
    <div className="min-h-screen bg-deep-navy flex items-center justify-center p-6">
      <div className="w-full max-w-3xl">
        {/* Header */}
        <motion.div initial={{ opacity: 0, y: -20 }} animate={{ opacity: 1, y: 0 }} className="text-center mb-10">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-electric-blue-500 to-neon-purple-500 flex items-center justify-center mx-auto mb-4 shadow-2xl shadow-electric-blue-500/30">
            <span className="text-2xl">⚡</span>
          </div>
          <h1 className="text-3xl font-bold text-white mb-2">Connect Your Platforms</h1>
          <p className="text-slate-400">Connect at least one platform to start automating your social media presence</p>
          <div className="flex items-center justify-center gap-2 mt-3">
            <Shield size={14} className="text-neon-green-400" />
            <span className="text-xs text-neon-green-400">End-to-end encrypted • OAuth 2.0 • Read our security policy</span>
          </div>
        </motion.div>

        {/* Progress */}
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.1 }} className="bg-white/5 border border-white/8 rounded-2xl p-4 mb-6 flex items-center gap-4">
          <div className="flex-1">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-slate-300">{connectedCount} of {PLATFORMS.length} platforms connected</span>
              <span className="text-sm font-bold text-white">{Math.round(connectedCount / PLATFORMS.length * 100)}%</span>
            </div>
            <div className="h-2 bg-white/10 rounded-full overflow-hidden">
              <motion.div className="h-full bg-gradient-to-r from-electric-blue-500 to-neon-green-500 rounded-full" animate={{ width: `${(connectedCount / PLATFORMS.length) * 100}%` }} transition={{ duration: 0.5 }} />
            </div>
          </div>
        </motion.div>

        {/* Platforms Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-8">
          {PLATFORMS.map((platform, i) => {
            const isConnected = connected.has(platform.id);
            const isConnecting = connecting === platform.id;
            return (
              <motion.div key={platform.id} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.04 }}>
                <div className={`p-4 rounded-2xl border transition-all ${isConnected ? "bg-neon-green-500/8 border-neon-green-500/25" : "bg-white/3 border-white/10 hover:border-white/20"}`}>
                  <div className="flex items-center gap-3">
                    <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ${platform.color} flex items-center justify-center text-xl shadow-lg flex-shrink-0`}>
                      {platform.icon}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-bold text-white">{platform.name}</p>
                        {platform.authType === "token" && (
                          <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/25">API Key</span>
                        )}
                      </div>
                      <p className="text-xs text-slate-500 truncate">{platform.description}</p>
                    </div>
                    {isConnected ? (
                      <CheckCircle2 size={20} className="text-neon-green-400 flex-shrink-0" />
                    ) : (
                      <button
                        onClick={() => handleConnect(platform.id)}
                        disabled={isConnecting}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-electric-blue-600 text-white text-xs font-semibold hover:bg-electric-blue-500 transition-colors disabled:opacity-60 flex-shrink-0"
                      >
                        {isConnecting ? (
                          <motion.div animate={{ rotate: 360 }} transition={{ duration: 1, repeat: Infinity, ease: "linear" }}>
                            <Lock size={12} />
                          </motion.div>
                        ) : (
                          <Lock size={12} />
                        )}
                        {isConnecting ? "Auth..." : "Connect"}
                      </button>
                    )}
                  </div>
                </div>
              </motion.div>
            );
          })}
        </div>

        {/* Continue Button */}
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: connectedCount > 0 ? 1 : 0.4 }} className="text-center">
          <button
            disabled={connectedCount === 0}
            className="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-gradient-to-r from-electric-blue-600 to-neon-purple-600 text-white font-bold text-lg hover:shadow-2xl hover:shadow-electric-blue-500/40 transition-all disabled:cursor-not-allowed"
          >
            <Zap size={20} />
            Launch SociAI OS
            <ChevronRight size={20} />
          </button>
          {connectedCount === 0 && <p className="text-xs text-slate-600 mt-2">Connect at least one platform to continue</p>}
        </motion.div>
      </div>
    </div>
  );
}
