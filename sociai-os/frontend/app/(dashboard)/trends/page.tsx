"use client";
import { useState } from "react";
import { motion } from "framer-motion";
import { TrendingUp, Flame, Zap, RefreshCw, Clock, ArrowUpRight, Hash } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";

const TRENDS = [
  { platform: "TikTok", name: "AI Tool Comparisons", category: "Tech", virality: 9.4, volume: "2.4M", growth: "+340%", angle: "Show before/after using AI tools for your workflow", sound: "Original audio trending", hashtags: ["#AItools", "#Productivity", "#TechTok"], urgency: "2h left" },
  { platform: "Instagram", name: "Behind the Brand Stories", category: "Business", virality: 8.7, volume: "890K", growth: "+182%", angle: "Authentic day-in-the-life content humanizing your brand", sound: null, hashtags: ["#BehindTheBrand", "#Entrepreneur", "#SmallBusiness"], urgency: "18h left" },
  { platform: "LinkedIn", name: "Contrarian Business Takes", category: "Thought Leadership", virality: 8.9, volume: "340K", growth: "+215%", angle: "Challenge a widely accepted business belief with data", sound: null, hashtags: ["#BusinessStrategy", "#Leadership", "#Disruption"], urgency: "36h left" },
  { platform: "Twitter", name: "Real-Time Industry Commentary", category: "News", virality: 7.8, volume: "1.2M", growth: "+89%", angle: "Your expert take on breaking industry news", sound: null, hashtags: ["#AI", "#Marketing", "#BreakingNews"], urgency: "4h left" },
  { platform: "YouTube", name: "Honest Product Reviews", category: "Review", virality: 8.1, volume: "560K", growth: "+124%", angle: "Brutally honest review format with structured scoring", sound: null, hashtags: ["#Review", "#HonestReview", "#ProductTest"], urgency: "72h left" },
  { platform: "TikTok", name: "POV: Your Industry Job", category: "Comedy", virality: 9.1, volume: "3.8M", growth: "+520%", angle: "POV format showing relatable moments in your industry", sound: "Trending audio #2847", hashtags: ["#POV", "#WorkLife", "#Relatable"], urgency: "6h left" },
];

const VIRAL_SOUNDS = [
  { name: "Lo-fi Chill Study Beat #4", uses: "2.1M", genre: "Lo-fi", bestFor: "Educational, calm content" },
  { name: "Trending Pop Remix 2025", uses: "4.8M", genre: "Pop", bestFor: "Product reveals, transitions" },
  { name: "Motivational Speech Cut", uses: "890K", genre: "Speech", bestFor: "Business, inspiration content" },
  { name: "Cinematic Build Up", uses: "1.2M", genre: "Cinematic", bestFor: "Before/after, transformations" },
];

export default function TrendsPage() {
  const [activePlatform, setActivePlatform] = useState("All");
  const platforms = ["All", "TikTok", "Instagram", "LinkedIn", "Twitter", "YouTube"];
  const filtered = activePlatform === "All" ? TRENDS : TRENDS.filter((t) => t.platform === activePlatform);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">AI Trend Hunter</h1>
          <p className="text-sm text-slate-400 mt-0.5">Real-time trend intelligence with content generation</p>
        </div>
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-neon-green-500/10 border border-neon-green-500/20">
            <div className="w-2 h-2 rounded-full bg-neon-green-400 animate-pulse" />
            <span className="text-xs text-neon-green-400 font-medium">Live Scanning</span>
          </div>
          <button className="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/8 text-sm text-slate-300 hover:text-white transition-all">
            <RefreshCw size={13} /> Refresh
          </button>
        </div>
      </div>

      {/* Platform Filter */}
      <div className="flex gap-2">
        {platforms.map((p) => (
          <button key={p} onClick={() => setActivePlatform(p)} className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${activePlatform === p ? "bg-electric-blue-600 text-white" : "bg-white/5 text-slate-400 hover:text-white border border-white/8"}`}>
            {p}
          </button>
        ))}
      </div>

      {/* Trends Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {filtered.map((trend, i) => (
          <motion.div key={`${trend.platform}-${trend.name}`} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.06 }}>
            <GlassCard className="p-5 hover:border-orange-500/30 transition-all cursor-pointer group">
              <div className="flex items-start justify-between mb-3">
                <div>
                  <NeonBadge variant="warning" size="sm">{trend.platform}</NeonBadge>
                  <NeonBadge variant="default" size="sm" className="ml-1">{trend.category}</NeonBadge>
                </div>
                <div className="flex items-center gap-1 text-xs">
                  <Clock size={11} className="text-red-400" />
                  <span className="text-red-400 font-medium">{trend.urgency}</span>
                </div>
              </div>

              <h3 className="text-base font-bold text-white mb-1">{trend.name}</h3>

              <div className="flex items-center gap-3 mb-3">
                <div className="flex items-center gap-1">
                  <Flame size={12} className="text-orange-400" />
                  <span className="text-xs font-bold text-orange-400">{trend.virality}/10</span>
                </div>
                <div className="flex items-center gap-1">
                  <ArrowUpRight size={12} className="text-neon-green-400" />
                  <span className="text-xs text-neon-green-400 font-medium">{trend.growth}</span>
                </div>
                <span className="text-xs text-slate-500">{trend.volume} posts</span>
              </div>

              <p className="text-xs text-slate-400 mb-3 leading-relaxed">{trend.angle}</p>

              {trend.sound && (
                <div className="flex items-center gap-1.5 mb-3 px-2 py-1.5 rounded-lg bg-neon-purple-500/10 border border-neon-purple-500/20">
                  <span className="text-[10px] text-neon-purple-400">🎵 {trend.sound}</span>
                </div>
              )}

              <div className="flex flex-wrap gap-1 mb-4">
                {trend.hashtags.map((tag) => (
                  <span key={tag} className="text-[10px] px-2 py-0.5 rounded-full bg-white/5 text-slate-400 border border-white/8">
                    {tag}
                  </span>
                ))}
              </div>

              <button className="w-full flex items-center justify-center gap-2 py-2 rounded-xl bg-gradient-to-r from-orange-500/20 to-electric-blue-600/20 border border-orange-500/30 text-sm font-semibold text-orange-300 hover:from-orange-500/30 hover:to-electric-blue-600/30 hover:text-white transition-all group-hover:border-orange-500/50">
                <Zap size={13} />
                Generate Content for This Trend
              </button>
            </GlassCard>
          </motion.div>
        ))}
      </div>

      {/* Viral Sounds */}
      <GlassCard className="p-5">
        <div className="flex items-center gap-2 mb-4">
          <span className="text-base">🎵</span>
          <h2 className="text-sm font-semibold text-white">Viral Sounds to Use Now</h2>
          <NeonBadge variant="info" size="sm">TikTok & Reels</NeonBadge>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {VIRAL_SOUNDS.map((sound) => (
            <div key={sound.name} className="p-4 rounded-xl bg-white/3 border border-white/8 hover:border-neon-purple-500/30 transition-all cursor-pointer">
              <p className="text-sm font-semibold text-white mb-1 truncate">{sound.name}</p>
              <p className="text-[11px] text-neon-purple-400 mb-1">{sound.uses} uses</p>
              <p className="text-[11px] text-slate-500">{sound.bestFor}</p>
            </div>
          ))}
        </div>
      </GlassCard>
    </div>
  );
}
