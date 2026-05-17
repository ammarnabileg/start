"use client";
import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Bot, Play, Pause, RotateCcw, Zap, Activity, Clock, CheckCircle2, XCircle, ChevronRight, Sparkles } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";

const AGENTS_DATA = [
  {
    id: "strategy", name: "Strategy Agent", role: "Chief Strategy Officer",
    status: "idle", icon: "🧠", color: "electric-blue",
    description: "Analyzes strategy docs, builds content pillars, generates monthly plans",
    stats: { tasksCompleted: 142, avgDuration: "2.4m", successRate: 98.6, tokensUsed: "1.2M" },
    recentTasks: ["Generated Q2 content plan", "Extracted brand tone from guidelines", "Created campaign brief for Product X"],
  },
  {
    id: "copywriting", name: "Copywriting Agent", role: "Senior Copywriter",
    status: "running", icon: "✍️", color: "neon-purple",
    description: "Generates captions, scripts, hooks, CTAs, threads, ad copy in AR/EN",
    stats: { tasksCompleted: 891, avgDuration: "18s", successRate: 99.1, tokensUsed: "4.8M" },
    recentTasks: ["Writing 5 LinkedIn captions", "Generated reel hooks for Product Z", "Created Arabic ad copy"],
  },
  {
    id: "design", name: "Design Agent", role: "Creative Director",
    status: "idle", icon: "🎨", color: "cyan",
    description: "Generates images, carousels, thumbnails using AI with brand consistency",
    stats: { tasksCompleted: 234, avgDuration: "45s", successRate: 94.2, tokensUsed: "890K" },
    recentTasks: ["Created 3 product carousel slides", "Generated story template", "Resized assets for TikTok"],
  },
  {
    id: "video", name: "Video Agent", role: "Video Producer",
    status: "completed", icon: "🎬", color: "rose",
    description: "Scripts reels, TikToks, Shorts with hook-first editing and AI storyboards",
    stats: { tasksCompleted: 67, avgDuration: "3.1m", successRate: 96.8, tokensUsed: "560K" },
    recentTasks: ["Generated 60s reel script", "Created TikTok storyboard", "Wrote YouTube short concept"],
  },
  {
    id: "publishing", name: "Publishing Agent", role: "Publishing Manager",
    status: "running", icon: "📅", color: "neon-green",
    description: "Schedules and publishes content with optimal timing across all platforms",
    stats: { tasksCompleted: 2341, avgDuration: "2s", successRate: 99.7, tokensUsed: "120K" },
    recentTasks: ["Scheduled 8 posts for next week", "Optimized timing for Instagram", "Cross-posted to 4 platforms"],
  },
  {
    id: "analytics", name: "Analytics Agent", role: "Performance Analyst",
    status: "idle", icon: "📊", color: "amber",
    description: "Analyzes performance, calculates viral scores, benchmarks competitors",
    stats: { tasksCompleted: 445, avgDuration: "1.8m", successRate: 99.3, tokensUsed: "2.1M" },
    recentTasks: ["Generated weekly performance report", "Calculated viral scores for 12 posts", "Competitor benchmarking done"],
  },
  {
    id: "community", name: "Community Agent", role: "Community Manager",
    status: "running", icon: "💬", color: "emerald",
    description: "Auto-replies to comments/DMs, filters spam, qualifies leads, escalates",
    stats: { tasksCompleted: 1204, avgDuration: "3s", successRate: 97.8, tokensUsed: "1.6M" },
    recentTasks: ["Replied to 12 Instagram comments", "Qualified 3 leads from DMs", "Flagged 5 spam comments"],
  },
  {
    id: "research", name: "Research Agent", role: "Trend Researcher",
    status: "idle", icon: "🔍", color: "violet",
    description: "Scans trends, scrapes competitors, finds viral sounds, monitors news",
    stats: { tasksCompleted: 89, avgDuration: "5.2m", successRate: 93.5, tokensUsed: "780K" },
    recentTasks: ["Scanned TikTok trends", "Analyzed competitor content strategy", "Found 8 viral sound opportunities"],
  },
];

const WORKFLOWS = [
  { name: "Full Content Creation", steps: ["Research → Copy → Design → Schedule"], time: "~8 min", status: "ready" },
  { name: "Trend-Based Campaign", steps: ["Trends → Strategy → Copy → Design → Publish"], time: "~15 min", status: "ready" },
  { name: "Daily Operations", steps: ["Analytics → Trends → Community → Schedule"], time: "~3 min", status: "scheduled" },
  { name: "Weekly Strategy Review", steps: ["Analytics → Strategy → Plan → Brief"], time: "~20 min", status: "ready" },
];

const colorMap: Record<string, string> = {
  "electric-blue": "from-electric-blue-500 to-electric-blue-700",
  "neon-purple": "from-neon-purple-500 to-neon-purple-700",
  "cyan": "from-cyan-500 to-cyan-700",
  "rose": "from-rose-500 to-rose-700",
  "neon-green": "from-neon-green-500 to-emerald-700",
  "amber": "from-amber-500 to-amber-700",
  "emerald": "from-emerald-500 to-emerald-700",
  "violet": "from-violet-500 to-violet-700",
};

export default function AgentsPage() {
  const [selected, setSelected] = useState<string | null>(null);
  const [runningAll, setRunningAll] = useState(false);

  const selectedAgent = AGENTS_DATA.find((a) => a.id === selected);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">AI Agent Command Center</h1>
          <p className="text-sm text-slate-400 mt-0.5">8 specialized agents working autonomously for your brand</p>
        </div>
        <button
          onClick={() => setRunningAll(!runningAll)}
          className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-electric-blue-600 to-neon-purple-600 text-white text-sm font-semibold hover:shadow-lg hover:shadow-electric-blue-500/30 transition-all"
        >
          <Zap size={14} />
          Run All Agents
        </button>
      </div>

      {/* Agent Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {AGENTS_DATA.map((agent, i) => (
          <motion.div key={agent.id} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}>
            <GlassCard
              className={`p-4 cursor-pointer transition-all hover:border-electric-blue-500/30 ${selected === agent.id ? "border-electric-blue-500/50 shadow-lg shadow-electric-blue-500/10" : ""}`}
              onClick={() => setSelected(selected === agent.id ? null : agent.id)}
            >
              <div className="flex items-start justify-between mb-3">
                <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${colorMap[agent.color]} flex items-center justify-center text-xl`}>
                  {agent.icon}
                </div>
                <NeonBadge
                  variant={agent.status === "running" ? "info" : agent.status === "completed" ? "success" : "default"}
                  size="sm"
                >
                  {agent.status === "running" && <span className="w-1.5 h-1.5 rounded-full bg-current animate-pulse inline-block mr-1" />}
                  {agent.status}
                </NeonBadge>
              </div>
              <h3 className="text-sm font-bold text-white">{agent.name}</h3>
              <p className="text-[11px] text-slate-500 mb-3">{agent.role}</p>
              <div className="grid grid-cols-2 gap-1.5 text-[10px]">
                <div className="bg-white/3 rounded-lg px-2 py-1.5">
                  <p className="text-slate-500">Tasks</p>
                  <p className="text-white font-bold">{agent.stats.tasksCompleted.toLocaleString()}</p>
                </div>
                <div className="bg-white/3 rounded-lg px-2 py-1.5">
                  <p className="text-slate-500">Success</p>
                  <p className="text-neon-green-400 font-bold">{agent.stats.successRate}%</p>
                </div>
              </div>
            </GlassCard>
          </motion.div>
        ))}
      </div>

      {/* Selected Agent Detail */}
      <AnimatePresence>
        {selectedAgent && (
          <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: "auto" }} exit={{ opacity: 0, height: 0 }}>
            <GlassCard className="p-5">
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                  <div className={`w-12 h-12 rounded-2xl bg-gradient-to-br ${colorMap[selectedAgent.color]} flex items-center justify-center text-2xl shadow-lg`}>
                    {selectedAgent.icon}
                  </div>
                  <div>
                    <h2 className="text-lg font-bold text-white">{selectedAgent.name}</h2>
                    <p className="text-sm text-slate-400">{selectedAgent.description}</p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <button className="flex items-center gap-2 px-3 py-2 rounded-lg bg-electric-blue-600 text-white text-xs font-semibold hover:bg-electric-blue-500 transition-colors">
                    <Play size={12} /> Run Task
                  </button>
                  <button className="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-xs font-medium hover:bg-white/10 transition-colors">
                    <RotateCcw size={12} /> Reset
                  </button>
                </div>
              </div>
              <div className="grid grid-cols-4 gap-3 mb-4">
                {Object.entries(selectedAgent.stats).map(([key, value]) => (
                  <div key={key} className="bg-white/3 rounded-xl p-3">
                    <p className="text-[11px] text-slate-500 capitalize">{key.replace(/([A-Z])/g, " $1")}</p>
                    <p className="text-sm font-bold text-white mt-0.5">{value}</p>
                  </div>
                ))}
              </div>
              <div>
                <p className="text-xs font-medium text-slate-400 mb-2">Recent Tasks</p>
                <div className="space-y-1.5">
                  {selectedAgent.recentTasks.map((task, i) => (
                    <div key={i} className="flex items-center gap-2 text-sm text-slate-300">
                      <CheckCircle2 size={13} className="text-neon-green-400 flex-shrink-0" />
                      {task}
                    </div>
                  ))}
                </div>
              </div>
            </GlassCard>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Workflows */}
      <GlassCard className="p-5">
        <div className="flex items-center gap-2 mb-4">
          <Sparkles size={16} className="text-neon-purple-400" />
          <h2 className="text-sm font-semibold text-white">Multi-Agent Workflows</h2>
          <NeonBadge variant="info" size="sm">Automated</NeonBadge>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
          {WORKFLOWS.map((wf) => (
            <div key={wf.name} className="p-4 rounded-xl bg-white/3 border border-white/8 hover:border-neon-purple-500/30 transition-all cursor-pointer group">
              <div className="flex items-center justify-between mb-2">
                <p className="text-sm font-semibold text-white">{wf.name}</p>
                <NeonBadge variant={wf.status === "scheduled" ? "warning" : "success"} size="sm">{wf.status}</NeonBadge>
              </div>
              <p className="text-xs text-slate-400 mb-3">{wf.steps[0]}</p>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-1 text-xs text-slate-500">
                  <Clock size={11} /> {wf.time}
                </div>
                <button className="text-xs text-electric-blue-400 hover:text-electric-blue-300 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  Run <ChevronRight size={11} />
                </button>
              </div>
            </div>
          ))}
        </div>
      </GlassCard>
    </div>
  );
}
