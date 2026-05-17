"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Bot, Play, Square, Clock, CheckCircle, AlertCircle, Zap, Activity } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";
import { AgentStatusCard } from "@/components/ui/AgentStatusCard";
import { AgentStatus } from "@/lib/types";

const INITIAL_AGENTS = [
  { id: "a1", type: "content-generator", name: "Content Generator", description: "Creates platform-optimized content using your brand voice and strategy", status: "running" as AgentStatus, icon: "Sparkles", color: "#3B82F6", gradientFrom: "#3B82F6", gradientTo: "#8B5CF6", currentTask: "Creating 5 Instagram carousel posts...", progress: 68, tasksCompleted: 142, lastRun: "2m ago", capabilities: ["Multi-platform", "Brand voice", "Hashtags", "Optimal length"] },
  { id: "a2", type: "trend-hunter", name: "Trend Hunter", description: "Monitors and analyzes viral trends across all platforms in real-time", status: "running" as AgentStatus, icon: "TrendingUp", color: "#10B981", gradientFrom: "#10B981", gradientTo: "#3B82F6", currentTask: "Scanning 11 platforms for trending topics...", progress: 45, tasksCompleted: 89, lastRun: "Just now", capabilities: ["Real-time monitor", "Viral prediction", "Hashtag analysis"] },
  { id: "a3", type: "copywriter", name: "AI Copywriter", description: "Crafts compelling copy for all content types with conversion focus", status: "idle" as AgentStatus, icon: "PenTool", color: "#8B5CF6", gradientFrom: "#8B5CF6", gradientTo: "#EC4899", currentTask: undefined, progress: 0, tasksCompleted: 67, lastRun: "30m ago", capabilities: ["Ad copy", "Captions", "CTAs", "Headlines"] },
  { id: "a4", type: "analytics-analyst", name: "Analytics Analyst", description: "Deep-dives into performance data and generates actionable insights", status: "complete" as AgentStatus, icon: "BarChart2", color: "#F59E0B", gradientFrom: "#F59E0B", gradientTo: "#EF4444", currentTask: "Weekly performance report complete", progress: 100, tasksCompleted: 214, lastRun: "1h ago", capabilities: ["Performance analysis", "ROI calculation", "Growth forecasting"] },
  { id: "a5", type: "community-manager", name: "Community Manager", description: "Manages comments, DMs, and community interactions intelligently", status: "idle" as AgentStatus, icon: "Users", color: "#EC4899", gradientFrom: "#EC4899", gradientTo: "#8B5CF6", currentTask: undefined, progress: 0, tasksCompleted: 523, lastRun: "15m ago", capabilities: ["Comment replies", "DM handling", "Lead qualification"] },
  { id: "a6", type: "campaign-planner", name: "Campaign Planner", description: "Designs comprehensive multi-platform campaign strategies", status: "idle" as AgentStatus, icon: "Calendar", color: "#6366F1", gradientFrom: "#6366F1", gradientTo: "#3B82F6", currentTask: undefined, progress: 0, tasksCompleted: 18, lastRun: "2d ago", capabilities: ["Campaign design", "Timeline planning", "Budget allocation"] },
  { id: "a7", type: "strategy-advisor", name: "Strategy Advisor", description: "Analyzes your brand strategy and provides high-level recommendations", status: "idle" as AgentStatus, icon: "Brain", color: "#14B8A6", gradientFrom: "#14B8A6", gradientTo: "#10B981", currentTask: undefined, progress: 0, tasksCompleted: 12, lastRun: "3d ago", capabilities: ["Strategy analysis", "Market positioning", "Growth roadmap"] },
  { id: "a8", type: "scheduler", name: "Smart Scheduler", description: "Optimizes posting schedule based on audience activity patterns", status: "complete" as AgentStatus, icon: "Clock", color: "#F97316", gradientFrom: "#F97316", gradientTo: "#EAB308", currentTask: "Scheduled 24 posts for next 7 days", progress: 100, tasksCompleted: 341, lastRun: "20m ago", capabilities: ["Optimal timing", "Auto-scheduling", "Queue management"] },
];

const TASK_FEED = [
  { id: "tf1", agent: "Content Generator", task: "Created 3 Instagram Reels scripts", status: "complete", time: "2m ago", icon: "✨" },
  { id: "tf2", agent: "Trend Hunter", task: "Detected #AIArt trending — score 96", status: "complete", time: "8m ago", icon: "📈" },
  { id: "tf3", agent: "Smart Scheduler", task: "Scheduled 24 posts across 6 platforms", status: "complete", time: "20m ago", icon: "📅" },
  { id: "tf4", agent: "Analytics Analyst", task: "Generated weekly performance report", status: "complete", time: "1h ago", icon: "📊" },
  { id: "tf5", agent: "Community Manager", task: "Replied to 47 Instagram comments", status: "complete", time: "1.5h ago", icon: "💬" },
  { id: "tf6", agent: "Content Generator", task: "5 LinkedIn articles queued for review", status: "complete", time: "2h ago", icon: "✅" },
];

export default function AgentsPage() {
  const [agents, setAgents] = useState(INITIAL_AGENTS);

  const runningCount = agents.filter(a => a.status === "running").length;
  const completedCount = agents.filter(a => a.status === "complete").length;
  const idleCount = agents.filter(a => a.status === "idle").length;

  const handleRun = (id: string) => {
    setAgents(prev => prev.map(a => a.id === id ? {
      ...a,
      status: "running" as AgentStatus,
      currentTask: "Initializing...",
      progress: 10,
    } : a));
    // Simulate progress
    let prog = 10;
    const interval = setInterval(() => {
      prog += Math.random() * 15;
      if (prog >= 100) {
        clearInterval(interval);
        setAgents(prev => prev.map(a => a.id === id ? {
          ...a,
          status: "complete" as AgentStatus,
          currentTask: "Task completed successfully",
          progress: 100,
          tasksCompleted: a.tasksCompleted + 1,
        } : a));
      } else {
        setAgents(prev => prev.map(a => a.id === id ? { ...a, progress: Math.min(prog, 99) } : a));
      }
    }, 500);
  };

  const handleStop = (id: string) => {
    setAgents(prev => prev.map(a => a.id === id ? { ...a, status: "idle" as AgentStatus, progress: 0, currentTask: undefined } : a));
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">AI Agent Command Center</h1>
          <p className="text-white/40 text-sm">8 autonomous agents · {runningCount} active · {completedCount} completed</p>
        </div>
        <button className="flex items-center gap-2 px-4 py-2.5 rounded-xl btn-primary text-white text-sm font-semibold">
          <Zap className="w-4 h-4" /> Run All Agents
        </button>
      </motion.div>

      {/* Status Overview */}
      <div className="grid grid-cols-3 gap-4">
        {[
          { label: "Running", count: runningCount, color: "#3B82F6", icon: Activity, pulse: true },
          { label: "Complete", count: completedCount, color: "#10B981", icon: CheckCircle, pulse: false },
          { label: "Idle", count: idleCount, color: "#6B7280", icon: Clock, pulse: false },
        ].map(s => {
          const Icon = s.icon;
          return (
            <GlassCard key={s.label} padding="sm">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ background: `${s.color}20`, border: `1px solid ${s.color}40` }}>
                  <Icon className="w-5 h-5" style={{ color: s.color }} />
                  {s.pulse && <span className="absolute w-2 h-2 rounded-full top-0 right-0 animate-ping" style={{ background: s.color }} />}
                </div>
                <div>
                  <p className="text-white text-2xl font-bold">{s.count}</p>
                  <p className="text-white/40 text-xs">{s.label}</p>
                </div>
              </div>
            </GlassCard>
          );
        })}
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Agent Cards */}
        <div className="xl:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
          {agents.map((agent, i) => (
            <AgentStatusCard
              key={agent.id}
              name={agent.name}
              description={agent.description}
              status={agent.status}
              icon={agent.icon}
              color={agent.color}
              gradientFrom={agent.gradientFrom}
              gradientTo={agent.gradientTo}
              currentTask={agent.currentTask}
              progress={agent.progress}
              tasksCompleted={agent.tasksCompleted}
              lastRun={agent.lastRun}
              capabilities={agent.capabilities}
              onRun={() => handleRun(agent.id)}
              onStop={() => handleStop(agent.id)}
              delay={i * 0.05}
            />
          ))}
        </div>

        {/* Task Feed */}
        <div className="space-y-4">
          <GlassCard padding="md">
            <div className="flex items-center gap-2 mb-4">
              <Activity className="w-5 h-5 text-electric-blue" />
              <h3 className="text-white font-semibold">Live Task Feed</h3>
              <span className="w-2 h-2 rounded-full bg-neon-green animate-pulse ml-auto" />
            </div>
            <div className="space-y-3">
              {TASK_FEED.map((task, i) => (
                <motion.div
                  key={task.id}
                  initial={{ opacity: 0, x: 10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: i * 0.05 }}
                  className="flex items-start gap-3 p-2.5 rounded-xl hover:bg-white/5 transition-colors"
                >
                  <span className="text-lg flex-shrink-0">{task.icon}</span>
                  <div className="flex-1 min-w-0">
                    <p className="text-white/60 text-xs font-medium">{task.agent}</p>
                    <p className="text-white/80 text-xs mt-0.5">{task.task}</p>
                    <p className="text-white/25 text-xs mt-0.5">{task.time}</p>
                  </div>
                  <NeonBadge variant="green" size="sm">Done</NeonBadge>
                </motion.div>
              ))}
            </div>
          </GlassCard>

          {/* Agent Stats */}
          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-4">Total Tasks Completed</h3>
            <div className="space-y-2.5">
              {agents.sort((a, b) => b.tasksCompleted - a.tasksCompleted).slice(0, 5).map(a => (
                <div key={a.id} className="flex items-center gap-3">
                  <div className="w-6 h-6 rounded-lg flex items-center justify-center text-xs" style={{ background: `${a.gradientFrom}20`, color: a.color }}>
                    {a.name[0]}
                  </div>
                  <span className="text-white/60 text-sm flex-1 truncate">{a.name}</span>
                  <span className="text-white font-bold text-sm">{a.tasksCompleted}</span>
                </div>
              ))}
            </div>
          </GlassCard>
        </div>
      </div>
    </div>
  );
}
