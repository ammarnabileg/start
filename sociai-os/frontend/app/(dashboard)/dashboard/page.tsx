"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import {
  Users, Eye, TrendingUp, Zap, Activity, Calendar, ChevronRight,
  Bot, Play, Clock, BarChart2, Globe, Flame, RefreshCw
} from "lucide-react";
import { MetricCard } from "@/components/ui/MetricCard";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge, StatusBadge } from "@/components/ui/NeonBadge";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { MetricsChart } from "@/components/analytics/MetricsChart";
import { PlatformId } from "@/lib/types";

const CHART_DATA = [
  { date: "Jun 1", reach: 12400, engagement: 3200, followers: 45200 },
  { date: "Jun 5", reach: 15800, engagement: 4100, followers: 45890 },
  { date: "Jun 10", reach: 18200, engagement: 5300, followers: 47200 },
  { date: "Jun 15", reach: 22100, engagement: 6800, followers: 48900 },
  { date: "Jun 20", reach: 19800, engagement: 5900, followers: 50100 },
  { date: "Jun 25", reach: 28400, engagement: 8200, followers: 52400 },
  { date: "Jun 30", reach: 35200, engagement: 10400, followers: 54800 },
];

const ACTIVITY_FEED = [
  { id: "a1", icon: "✨", text: "AI generated 5 posts for Instagram", time: "2m ago", type: "ai" },
  { id: "a2", icon: "🎯", text: "Campaign 'Summer Sale' reached 50K impressions", time: "15m ago", type: "campaign" },
  { id: "a3", icon: "💬", text: "47 new comments need review", time: "32m ago", type: "community" },
  { id: "a4", icon: "📈", text: "#AIArt trending — viral score 94/100", time: "1h ago", type: "trend" },
  { id: "a5", icon: "✅", text: "LinkedIn article published successfully", time: "1.5h ago", type: "publish" },
  { id: "a6", icon: "🏆", text: "Reached 50K followers on Instagram!", time: "2h ago", type: "milestone" },
];

const PLATFORM_HEALTH = [
  { id: "instagram" as PlatformId, followers: 54800, engagement: 4.2, status: "excellent", posts: 3 },
  { id: "facebook" as PlatformId, followers: 28300, engagement: 2.1, status: "good", posts: 1 },
  { id: "twitter" as PlatformId, followers: 12900, engagement: 3.8, status: "excellent", posts: 5 },
  { id: "tiktok" as PlatformId, followers: 89200, engagement: 7.3, status: "excellent", posts: 2 },
  { id: "youtube" as PlatformId, followers: 34100, engagement: 5.6, status: "good", posts: 0 },
  { id: "linkedin" as PlatformId, followers: 8700, engagement: 6.1, status: "good", posts: 1 },
];

const QUICK_ACTIONS = [
  { label: "Generate Content", icon: Zap, color: "from-electric-blue to-neon-purple", href: "/content/generate" },
  { label: "Write Copy", icon: Activity, color: "from-neon-purple to-pink-500", href: "/copywriting" },
  { label: "View Analytics", icon: BarChart2, color: "from-neon-green to-electric-blue", href: "/analytics" },
  { label: "Hunt Trends", icon: Flame, color: "from-orange-500 to-red-500", href: "/trends" },
];

const AGENTS = [
  { name: "Content Gen", status: "running" as const, task: "Creating 5 Instagram posts...", progress: 68, color: "#3B82F6" },
  { name: "Trend Hunter", status: "running" as const, task: "Scanning 11 platforms...", progress: 45, color: "#10B981" },
  { name: "Copywriter", status: "idle" as const, task: null, progress: 0, color: "#8B5CF6" },
  { name: "Scheduler", status: "complete" as const, task: "24 posts scheduled", progress: 100, color: "#F59E0B" },
];

const TRENDING = [
  { tag: "#AI2025", score: 96, platform: "twitter" as PlatformId, growth: "+340%" },
  { tag: "#SummerVibes", score: 88, platform: "instagram" as PlatformId, growth: "+220%" },
  { tag: "#ProductLaunch", score: 72, platform: "linkedin" as PlatformId, growth: "+180%" },
  { tag: "#TechTrends", score: 65, platform: "tiktok" as PlatformId, growth: "+156%" },
];

export default function DashboardPage() {
  const [refreshing, setRefreshing] = useState(false);

  const handleRefresh = async () => {
    setRefreshing(true);
    await new Promise(r => setTimeout(r, 1000));
    setRefreshing(false);
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Command Center</h1>
          <p className="text-white/40 text-sm mt-0.5">Sunday, June 30, 2025 · All systems operational</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={handleRefresh}
            className="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/60 text-sm transition-all"
          >
            <RefreshCw className={`w-4 h-4 ${refreshing ? "animate-spin" : ""}`} />
            Refresh
          </button>
          <button className="flex items-center gap-2 px-4 py-2.5 rounded-xl btn-primary text-white text-sm font-semibold">
            <Zap className="w-4 h-4" />
            Create Post
          </button>
        </div>
      </motion.div>

      {/* KPI Metrics */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <MetricCard label="Total Reach" value={287400} change={+24.3} icon={<Eye className="w-4 h-4" />} color="blue" delay={0} sparkline={[30,45,42,58,65,72,88,94]} />
        <MetricCard label="Engagement Rate" value="5.8%" change={+1.2} icon={<TrendingUp className="w-4 h-4" />} color="purple" delay={0.05} sparkline={[42,38,55,48,62,58,70,75]} />
        <MetricCard label="Total Followers" value={228000} change={+8.7} icon={<Users className="w-4 h-4" />} color="green" delay={0.1} sparkline={[55,58,60,64,68,72,78,85]} />
        <MetricCard label="Viral Score" value="87/100" change={+5} icon={<Zap className="w-4 h-4" />} color="pink" delay={0.15} sparkline={[65,70,68,75,80,82,85,87]} />
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Chart - 2 cols */}
        <div className="xl:col-span-2">
          <GlassCard padding="md">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h2 className="text-white font-semibold">Performance Overview</h2>
                <p className="text-white/40 text-xs mt-0.5">Last 30 days across all platforms</p>
              </div>
              <div className="flex gap-2">
                {["7D", "30D", "90D"].map(range => (
                  <button key={range} className={`text-xs px-2.5 py-1 rounded-lg transition-all ${range === "30D" ? "bg-electric-blue/20 text-electric-blue border border-electric-blue/30" : "text-white/40 hover:text-white/60 bg-white/5 border border-white/10"}`}>
                    {range}
                  </button>
                ))}
              </div>
            </div>
            <MetricsChart
              data={CHART_DATA}
              type="area"
              metrics={[
                { key: "reach", label: "Reach", color: "#3B82F6" },
                { key: "engagement", label: "Engagement", color: "#8B5CF6" },
                { key: "followers", label: "Followers", color: "#10B981" },
              ]}
              height={220}
            />
          </GlassCard>
        </div>

        {/* Activity Feed */}
        <GlassCard padding="md">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-white font-semibold">Live Activity</h2>
            <span className="w-2 h-2 rounded-full bg-neon-green animate-pulse" />
          </div>
          <div className="space-y-3">
            {ACTIVITY_FEED.map((item, i) => (
              <motion.div
                key={item.id}
                initial={{ opacity: 0, x: 10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: i * 0.05 }}
                className="flex items-start gap-3 p-2.5 rounded-xl hover:bg-white/5 transition-colors group cursor-pointer"
              >
                <span className="text-lg flex-shrink-0">{item.icon}</span>
                <div className="flex-1 min-w-0">
                  <p className="text-white/70 text-xs leading-relaxed">{item.text}</p>
                  <p className="text-white/30 text-xs mt-0.5">{item.time}</p>
                </div>
              </motion.div>
            ))}
          </div>
        </GlassCard>
      </div>

      {/* Second Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* AI Agents */}
        <GlassCard padding="md">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Bot className="w-5 h-5 text-electric-blue" />
              <h2 className="text-white font-semibold">AI Agents</h2>
            </div>
            <a href="/agents" className="text-electric-blue text-xs hover:underline flex items-center gap-1">
              View all <ChevronRight className="w-3 h-3" />
            </a>
          </div>
          <div className="space-y-3">
            {AGENTS.map(agent => (
              <div key={agent.name} className="p-3 rounded-xl bg-white/5 border border-white/8 hover:border-white/15 transition-all">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-white text-sm font-medium">{agent.name}</span>
                  <div className="flex items-center gap-1.5">
                    <div
                      className={`w-1.5 h-1.5 rounded-full ${agent.status === "running" ? "animate-pulse" : ""}`}
                      style={{ background: agent.status === "running" ? "#3B82F6" : agent.status === "complete" ? "#10B981" : "#6B7280" }}
                    />
                    <span className="text-xs text-white/40 capitalize">{agent.status}</span>
                  </div>
                </div>
                {agent.status === "running" && (
                  <>
                    <p className="text-white/40 text-xs mb-1.5 truncate">{agent.task}</p>
                    <div className="h-1 bg-white/10 rounded-full">
                      <motion.div
                        className="h-full rounded-full"
                        style={{ background: agent.color }}
                        animate={{ width: `${agent.progress}%` }}
                        transition={{ duration: 0.5 }}
                      />
                    </div>
                    <div className="flex justify-between mt-1">
                      <span className="text-xs text-white/25">{agent.progress}%</span>
                    </div>
                  </>
                )}
                {agent.status === "complete" && (
                  <p className="text-neon-green text-xs">{agent.task}</p>
                )}
              </div>
            ))}
          </div>
        </GlassCard>

        {/* Quick Actions */}
        <GlassCard padding="md">
          <h2 className="text-white font-semibold mb-4">Quick Actions</h2>
          <div className="grid grid-cols-2 gap-3">
            {QUICK_ACTIONS.map(action => {
              const Icon = action.icon;
              return (
                <motion.a
                  key={action.label}
                  href={action.href}
                  whileHover={{ scale: 1.03, y: -2 }}
                  whileTap={{ scale: 0.97 }}
                  className={`flex flex-col items-center justify-center p-4 rounded-xl bg-gradient-to-br ${action.color} relative overflow-hidden text-white font-medium text-sm text-center gap-2 shadow-lg`}
                >
                  <Icon className="w-6 h-6" />
                  {action.label}
                </motion.a>
              );
            })}
          </div>

          {/* Calendar preview */}
          <div className="mt-4 p-3 rounded-xl bg-white/5 border border-white/8">
            <div className="flex items-center gap-2 mb-3">
              <Calendar className="w-4 h-4 text-white/40" />
              <span className="text-white/60 text-sm">Upcoming Posts</span>
            </div>
            <div className="space-y-2">
              {[
                { time: "Today 9:00 AM", platform: "instagram" as PlatformId, title: "Morning Motivation" },
                { time: "Today 2:00 PM", platform: "twitter" as PlatformId, title: "Product Teaser" },
                { time: "Tomorrow 8:00 AM", platform: "linkedin" as PlatformId, title: "Industry Insights" },
              ].map((p, i) => (
                <div key={i} className="flex items-center gap-2">
                  <PlatformIcon platform={p.platform} size="xs" />
                  <div className="flex-1 min-w-0">
                    <p className="text-white/60 text-xs truncate">{p.title}</p>
                    <p className="text-white/30 text-xs">{p.time}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </GlassCard>

        {/* Trending Now */}
        <GlassCard padding="md">
          <div className="flex items-center gap-2 mb-4">
            <Flame className="w-5 h-5 text-orange-400" />
            <h2 className="text-white font-semibold">Trending Now</h2>
          </div>
          <div className="space-y-3">
            {TRENDING.map((trend, i) => (
              <motion.div
                key={trend.tag}
                initial={{ opacity: 0, x: 10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: i * 0.07 }}
                className="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/8 hover:border-white/15 hover:bg-white/8 transition-all cursor-pointer group"
              >
                <span className="text-white/30 text-sm font-bold w-4">{i + 1}</span>
                <div className="flex-1 min-w-0">
                  <p className="text-white text-sm font-semibold truncate">{trend.tag}</p>
                  <div className="flex items-center gap-2">
                    <PlatformIcon platform={trend.platform} size="xs" />
                    <span className="text-neon-green text-xs">{trend.growth}</span>
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-xs font-bold" style={{ color: trend.score > 85 ? "#10B981" : trend.score > 70 ? "#3B82F6" : "#F59E0B" }}>
                    {trend.score}
                  </div>
                  <div className="text-white/20 text-xs">viral</div>
                </div>
                <button className="opacity-0 group-hover:opacity-100 transition-opacity text-xs bg-electric-blue/20 border border-electric-blue/30 text-electric-blue px-2 py-1 rounded-lg">
                  Use
                </button>
              </motion.div>
            ))}
          </div>
        </GlassCard>
      </div>

      {/* Platform Health Grid */}
      <GlassCard padding="md">
        <div className="flex items-center justify-between mb-5">
          <div className="flex items-center gap-2">
            <Globe className="w-5 h-5 text-white/50" />
            <h2 className="text-white font-semibold">Platform Health</h2>
          </div>
          <a href="/analytics" className="text-electric-blue text-xs hover:underline flex items-center gap-1">
            Full report <ChevronRight className="w-3 h-3" />
          </a>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
          {PLATFORM_HEALTH.map(p => (
            <motion.div
              key={p.id}
              whileHover={{ y: -3 }}
              className="flex flex-col items-center text-center p-3 rounded-xl bg-white/5 border border-white/8 hover:border-white/15 transition-all"
            >
              <PlatformIcon platform={p.id} size="lg" />
              <div className="mt-2">
                <p className="text-white font-semibold text-sm">{p.followers >= 1000 ? `${(p.followers / 1000).toFixed(1)}K` : p.followers}</p>
                <p className="text-white/40 text-xs">followers</p>
              </div>
              <div className="mt-1.5">
                <span className={`text-xs font-medium ${p.engagement > 5 ? "text-neon-green" : p.engagement > 3 ? "text-electric-blue" : "text-yellow-400"}`}>
                  {p.engagement}% eng
                </span>
              </div>
              {p.posts > 0 && (
                <div className="mt-1 flex items-center gap-1">
                  <Clock className="w-3 h-3 text-white/30" />
                  <span className="text-white/30 text-xs">{p.posts} queued</span>
                </div>
              )}
              <NeonBadge variant={p.status === "excellent" ? "green" : "blue"} size="sm" className="mt-2">
                {p.status}
              </NeonBadge>
            </motion.div>
          ))}
        </div>
      </GlassCard>
    </div>
  );
}
