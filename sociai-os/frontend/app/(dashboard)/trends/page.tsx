"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { TrendingUp, Flame, Zap, Search, RefreshCw, ArrowUp, ArrowDown } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { PlatformId } from "@/lib/types";

const TRENDS = [
  { id: "t1", tag: "#AIArt", desc: "AI-generated visual content exploding", platforms: ["instagram", "tiktok"] as PlatformId[], score: 96, growth: "+340%", momentum: "peak" as const, category: "Technology", uses: "2.4M" },
  { id: "t2", tag: "#SummerVibes2025", desc: "Seasonal lifestyle content", platforms: ["instagram", "tiktok", "snapchat"] as PlatformId[], score: 88, growth: "+220%", momentum: "rising" as const, category: "Lifestyle", uses: "1.8M" },
  { id: "t3", tag: "#ProductivityHacks", desc: "Work efficiency tips going viral", platforms: ["twitter", "linkedin"] as PlatformId[], score: 82, growth: "+180%", momentum: "rising" as const, category: "Business", uses: "890K" },
  { id: "t4", tag: "#BeReal2025", desc: "Authentic unfiltered content", platforms: ["instagram", "tiktok"] as PlatformId[], score: 79, growth: "+156%", momentum: "rising" as const, category: "Social", uses: "1.2M" },
  { id: "t5", tag: "#EcoFashion", desc: "Sustainable fashion movement", platforms: ["instagram", "pinterest"] as PlatformId[], score: 72, growth: "+134%", momentum: "rising" as const, category: "Fashion", uses: "650K" },
  { id: "t6", tag: "#CryptoNews", desc: "Cryptocurrency market updates", platforms: ["twitter", "telegram"] as PlatformId[], score: 68, growth: "+89%", momentum: "peak" as const, category: "Finance", uses: "3.1M" },
  { id: "t7", tag: "#HomeWorkout", desc: "At-home fitness routines", platforms: ["tiktok", "youtube", "instagram"] as PlatformId[], score: 65, growth: "+76%", momentum: "declining" as const, category: "Health", uses: "4.2M" },
  { id: "t8", tag: "#FoodieLife", desc: "Gourmet food photography", platforms: ["instagram", "tiktok"] as PlatformId[], score: 61, growth: "+65%", momentum: "declining" as const, category: "Food", uses: "5.8M" },
];

const CATEGORIES = ["All", "Technology", "Lifestyle", "Business", "Fashion", "Health", "Food", "Finance"];

const COMPETITOR_ACTIVITY = [
  { brand: "CompetitorA", action: "Posted using #AIArt", platform: "instagram" as PlatformId, time: "1h ago", engagement: "2.4K" },
  { brand: "CompetitorB", action: "Joined #SummerVibes trend", platform: "tiktok" as PlatformId, time: "3h ago", engagement: "15K" },
  { brand: "CompetitorC", action: "Published #ProductivityHacks thread", platform: "twitter" as PlatformId, time: "6h ago", engagement: "892" },
];

const SCORE_COLOR = (score: number) => score >= 85 ? "#10B981" : score >= 70 ? "#3B82F6" : "#F59E0B";
const MOMENTUM_BADGE = { rising: "green" as const, peak: "blue" as const, declining: "yellow" as const };

export default function TrendsPage() {
  const [category, setCategory] = useState("All");
  const [search, setSearch] = useState("");
  const [usingTrend, setUsingTrend] = useState<string | null>(null);
  const [usedTrends, setUsedTrends] = useState<Set<string>>(new Set());

  const handleUseTrend = async (id: string) => {
    setUsingTrend(id);
    await new Promise(r => setTimeout(r, 1500));
    setUsingTrend(null);
    setUsedTrends(prev => new Set(Array.from(prev).concat(id)));
  };

  const filtered = TRENDS.filter(t => {
    if (category !== "All" && t.category !== category) return false;
    if (search && !t.tag.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  // Hashtag cloud sizing
  const maxScore = Math.max(...TRENDS.map(t => t.score));

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Trend Hunter</h1>
          <p className="text-white/40 text-sm">Live viral trends across all 11 platforms · Updated 5 minutes ago</p>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-2 h-2 rounded-full bg-neon-green animate-pulse" />
          <span className="text-white/40 text-sm">Live</span>
          <button className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/50 text-sm transition-all">
            <RefreshCw className="w-3.5 h-3.5" /> Refresh
          </button>
        </div>
      </motion.div>

      {/* Filters */}
      <div className="flex items-center gap-3 flex-wrap">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
          <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search trends..." className="input-glass pl-9 py-2 text-sm w-44" />
        </div>
        <div className="flex gap-1 flex-wrap">
          {CATEGORIES.map(c => (
            <button key={c} onClick={() => setCategory(c)} className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${category === c ? "bg-electric-blue/20 text-electric-blue border border-electric-blue/30" : "bg-white/5 border border-white/10 text-white/40 hover:text-white/60"}`}>
              {c}
            </button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Trends Grid */}
        <div className="xl:col-span-2 space-y-4">
          {filtered.map((trend, i) => (
            <motion.div
              key={trend.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: i * 0.05 }}
              className="glass-panel p-4 rounded-2xl border border-white/10 hover:border-white/20 transition-all group"
            >
              <div className="flex items-start gap-4">
                {/* Rank */}
                <div className="text-white/20 font-bold text-lg w-6 text-center flex-shrink-0">{i + 1}</div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap mb-1">
                    <h3 className="text-white font-bold text-lg">{trend.tag}</h3>
                    <NeonBadge variant={MOMENTUM_BADGE[trend.momentum]} size="sm" dot>
                      {trend.momentum === "rising" ? "↑ Rising" : trend.momentum === "peak" ? "⚡ Peak" : "↓ Declining"}
                    </NeonBadge>
                    <span className="text-xs bg-white/5 border border-white/10 text-white/40 px-2 py-0.5 rounded-full">{trend.category}</span>
                  </div>
                  <p className="text-white/50 text-sm mb-2">{trend.desc}</p>

                  <div className="flex items-center gap-4 flex-wrap">
                    <div className="flex gap-1">
                      {trend.platforms.slice(0, 4).map(p => <PlatformIcon key={p} platform={p} size="xs" />)}
                    </div>
                    <span className="text-white/30 text-xs">{trend.uses} posts</span>
                    <span className="text-neon-green text-xs font-semibold">{trend.growth}</span>
                  </div>
                </div>

                {/* Viral Score */}
                <div className="flex-shrink-0 text-center">
                  <div className="relative w-14 h-14">
                    <svg viewBox="0 0 36 36" className="w-14 h-14 -rotate-90">
                      <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255,255,255,0.1)" strokeWidth="3" />
                      <circle
                        cx="18" cy="18" r="15" fill="none"
                        stroke={SCORE_COLOR(trend.score)}
                        strokeWidth="3"
                        strokeDasharray={`${(trend.score / 100) * 94.2} 94.2`}
                        strokeLinecap="round"
                      />
                    </svg>
                    <div className="absolute inset-0 flex items-center justify-center">
                      <span className="text-white font-bold text-sm">{trend.score}</span>
                    </div>
                  </div>
                  <p className="text-white/30 text-xs mt-1">viral</p>
                </div>

                {/* Use Button */}
                <motion.button
                  onClick={() => handleUseTrend(trend.id)}
                  disabled={usingTrend === trend.id || usedTrends.has(trend.id)}
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className={`flex-shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold transition-all ${
                    usedTrends.has(trend.id)
                      ? "bg-neon-green/20 border border-neon-green/30 text-neon-green"
                      : "btn-primary text-white"
                  }`}
                >
                  {usingTrend === trend.id ? (
                    <motion.div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full" animate={{ rotate: 360 }} transition={{ duration: 0.8, repeat: Infinity, ease: "linear" }} />
                  ) : usedTrends.has(trend.id) ? (
                    <><svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><path d="M5 13l4 4L19 7" /></svg> Used</>
                  ) : (
                    <><Zap className="w-3.5 h-3.5" /> Use</>
                  )}
                </motion.button>
              </div>
            </motion.div>
          ))}
        </div>

        {/* Right: Hashtag Cloud + Competitor Activity */}
        <div className="space-y-5">
          {/* Hashtag Cloud */}
          <GlassCard padding="md">
            <div className="flex items-center gap-2 mb-4">
              <Flame className="w-5 h-5 text-orange-400" />
              <h3 className="text-white font-semibold">Hashtag Cloud</h3>
            </div>
            <div className="flex flex-wrap gap-2 items-center">
              {TRENDS.map(t => {
                const size = 0.7 + (t.score / maxScore) * 0.6;
                return (
                  <motion.button
                    key={t.id}
                    whileHover={{ scale: 1.05 }}
                    className="font-bold transition-all hover:text-electric-blue"
                    style={{
                      fontSize: `${size}rem`,
                      color: SCORE_COLOR(t.score),
                      opacity: 0.6 + (t.score / maxScore) * 0.4,
                    }}
                  >
                    {t.tag}
                  </motion.button>
                );
              })}
            </div>
          </GlassCard>

          {/* Competitor Activity */}
          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-4">Competitor Activity</h3>
            <div className="space-y-3">
              {COMPETITOR_ACTIVITY.map(a => (
                <div key={a.brand} className="flex items-start gap-3 p-3 rounded-xl bg-white/5 border border-white/8">
                  <div className="w-8 h-8 rounded-lg bg-neon-purple/20 border border-neon-purple/30 flex items-center justify-center text-neon-purple-400 font-bold text-sm flex-shrink-0">
                    {a.brand[0]}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-white text-sm font-medium">{a.brand}</p>
                    <p className="text-white/50 text-xs">{a.action}</p>
                    <div className="flex items-center gap-2 mt-1">
                      <PlatformIcon platform={a.platform} size="xs" />
                      <span className="text-white/25 text-xs">{a.time}</span>
                      <span className="text-neon-green text-xs ml-auto">{a.engagement} eng</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </GlassCard>

          {/* Top Platforms for Trends */}
          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-4">Trending Platforms</h3>
            <div className="space-y-2.5">
              {[
                { platform: "tiktok" as PlatformId, trends: 12, growth: "+45%" },
                { platform: "instagram" as PlatformId, trends: 9, growth: "+28%" },
                { platform: "twitter" as PlatformId, trends: 8, growth: "+32%" },
                { platform: "linkedin" as PlatformId, trends: 5, growth: "+19%" },
              ].map(p => (
                <div key={p.platform} className="flex items-center gap-3">
                  <PlatformIcon platform={p.platform} size="sm" showName />
                  <div className="flex-1 h-1.5 bg-white/10 rounded-full overflow-hidden">
                    <div className="h-full bg-electric-blue rounded-full" style={{ width: `${(p.trends / 12) * 100}%` }} />
                  </div>
                  <span className="text-neon-green text-xs font-semibold w-12 text-right">{p.growth}</span>
                </div>
              ))}
            </div>
          </GlassCard>
        </div>
      </div>
    </div>
  );
}
