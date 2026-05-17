"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import { BarChart2, TrendingUp, Users, Eye, Calendar, Download, Zap, ChevronUp, ChevronDown, Minus, Brain } from "lucide-react";
import {
  RadialBarChart, RadialBar, PieChart, Pie, Cell,
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend, CartesianGrid
} from "recharts";
import { GlassCard } from "@/components/ui/GlassCard";
import { MetricCard } from "@/components/ui/MetricCard";
import { MetricsChart } from "@/components/analytics/MetricsChart";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { NeonBadge } from "@/components/ui/NeonBadge";
import { PlatformId } from "@/lib/types";

const DATE_RANGES = ["7D", "30D", "90D", "6M", "1Y"];

const OVERVIEW_DATA = [
  { date: "Jun 1", reach: 12400, engagement: 3200, impressions: 28000, clicks: 1800 },
  { date: "Jun 5", reach: 15800, engagement: 4100, impressions: 34000, clicks: 2300 },
  { date: "Jun 10", reach: 18200, engagement: 5300, impressions: 41000, clicks: 2900 },
  { date: "Jun 15", reach: 22100, engagement: 6800, impressions: 48000, clicks: 3600 },
  { date: "Jun 20", reach: 19800, engagement: 5900, impressions: 44000, clicks: 3100 },
  { date: "Jun 25", reach: 28400, engagement: 8200, impressions: 62000, clicks: 4500 },
  { date: "Jun 30", reach: 35200, engagement: 10400, impressions: 78000, clicks: 5800 },
];

const PLATFORM_DATA = [
  { platform: "instagram" as PlatformId, followers: 54800, growth: 8.7, engagement: 4.2, impressions: 284000, posts: 48 },
  { platform: "tiktok" as PlatformId, followers: 89200, growth: 24.1, engagement: 7.3, impressions: 520000, posts: 32 },
  { platform: "facebook" as PlatformId, followers: 28300, growth: 2.1, engagement: 2.1, impressions: 142000, posts: 24 },
  { platform: "twitter" as PlatformId, followers: 12900, growth: 5.4, engagement: 3.8, impressions: 89000, posts: 96 },
  { platform: "linkedin" as PlatformId, followers: 8700, growth: 12.3, engagement: 6.1, impressions: 52000, posts: 16 },
  { platform: "youtube" as PlatformId, followers: 34100, growth: 6.8, engagement: 5.6, impressions: 210000, posts: 8 },
];

const SENTIMENT_DATA = [
  { name: "Positive", value: 68, fill: "#10B981" },
  { name: "Neutral", value: 22, fill: "#6B7280" },
  { name: "Negative", value: 10, fill: "#EF4444" },
];

const COMPETITORS = [
  { name: "Competitor A", followers: "245K", engRate: "3.2%", posts: 4.5, growth: "+5.2%", vs: "ahead" },
  { name: "Competitor B", followers: "189K", engRate: "6.1%", posts: 7.2, growth: "+18.4%", vs: "behind" },
  { name: "Competitor C", followers: "312K", engRate: "2.8%", posts: 3.1, growth: "+2.1%", vs: "ahead" },
  { name: "Our Brand", followers: "228K", engRate: "5.8%", posts: 5.8, growth: "+8.7%", vs: "you" },
];

const PREDICTIONS = [
  { date: "Jul 7", followers: 58200, engagement: 11200 },
  { date: "Jul 14", followers: 61800, engagement: 12500 },
  { date: "Jul 21", followers: 65400, engagement: 13800 },
  { date: "Jul 28", followers: 69100, engagement: 15200 },
];

const AI_RECS = [
  { icon: "🎯", title: "Increase TikTok frequency", description: "Your TikTok engagement is 73% above average. Post 2x more for maximum growth.", impact: "High", effort: "Low" },
  { icon: "⏰", title: "Post at 8-9 PM on weekdays", description: "Analysis shows 43% higher engagement during evening hours for your audience.", impact: "High", effort: "Low" },
  { icon: "📸", title: "Use more carousel posts", description: "Carousel posts get 3x more saves than single images on Instagram.", impact: "Medium", effort: "Medium" },
  { icon: "🔥", title: "Capitalize on #AI2025 trend", description: "This hashtag is at peak velocity — ideal window is next 48 hours.", impact: "High", effort: "Low" },
];

const CustomTooltip = ({ active, payload, label }: { active?: boolean; payload?: { color: string; name: string; value: number }[]; label?: string }) => {
  if (!active || !payload?.length) return null;
  return (
    <div className="glass-panel-dark border border-white/10 rounded-xl px-3 py-2.5 shadow-glass">
      <p className="text-white/50 text-xs mb-1.5">{label}</p>
      {payload.map((p) => (
        <div key={p.name} className="flex items-center gap-2 text-xs">
          <div className="w-2 h-2 rounded-full" style={{ background: p.color }} />
          <span className="text-white/60">{p.name}:</span>
          <span className="text-white font-semibold">{p.value.toLocaleString()}</span>
        </div>
      ))}
    </div>
  );
};

export default function AnalyticsPage() {
  const [activeRange, setActiveRange] = useState("30D");
  const [activeMetric, setActiveMetric] = useState("reach");

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white">Analytics</h1>
          <p className="text-white/40 text-sm">Deep performance insights across all platforms</p>
        </div>
        <div className="flex items-center gap-3">
          {/* Date range */}
          <div className="flex gap-1 bg-white/5 border border-white/10 rounded-xl p-1">
            {DATE_RANGES.map(r => (
              <button
                key={r}
                onClick={() => setActiveRange(r)}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${activeRange === r ? "bg-electric-blue/20 text-electric-blue" : "text-white/40 hover:text-white/70"}`}
              >
                {r}
              </button>
            ))}
          </div>
          <button className="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/60 text-sm transition-all">
            <Download className="w-4 h-4" /> Export
          </button>
        </div>
      </motion.div>

      {/* Top KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <MetricCard label="Total Reach" value={287400} change={+24.3} changeLabel="vs last period" icon={<Eye className="w-4 h-4" />} color="blue" delay={0} />
        <MetricCard label="Engagement Rate" value="5.8%" change={+1.2} icon={<TrendingUp className="w-4 h-4" />} color="purple" delay={0.05} />
        <MetricCard label="New Followers" value={8420} change={+8.7} icon={<Users className="w-4 h-4" />} color="green" delay={0.1} />
        <MetricCard label="Viral Score" value="87/100" change={+5} icon={<Zap className="w-4 h-4" />} color="pink" delay={0.15} />
      </div>

      {/* Main Chart */}
      <GlassCard padding="md">
        <div className="flex items-center justify-between mb-4 flex-wrap gap-3">
          <h2 className="text-white font-semibold">Performance Trends</h2>
          <div className="flex gap-2 flex-wrap">
            {[
              { key: "reach", label: "Reach", color: "#3B82F6" },
              { key: "engagement", label: "Engagement", color: "#8B5CF6" },
              { key: "impressions", label: "Impressions", color: "#10B981" },
              { key: "clicks", label: "Clicks", color: "#F59E0B" },
            ].map(m => (
              <button
                key={m.key}
                onClick={() => setActiveMetric(m.key)}
                className="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg border transition-all"
                style={{
                  background: activeMetric === m.key ? `${m.color}20` : "rgba(255,255,255,0.05)",
                  borderColor: activeMetric === m.key ? `${m.color}50` : "rgba(255,255,255,0.1)",
                  color: activeMetric === m.key ? m.color : "rgba(255,255,255,0.4)",
                }}
              >
                <div className="w-2 h-2 rounded-full" style={{ background: m.color }} />
                {m.label}
              </button>
            ))}
          </div>
        </div>
        <MetricsChart data={OVERVIEW_DATA} type="area" metrics={[
          { key: "reach", label: "Reach", color: "#3B82F6" },
          { key: "engagement", label: "Engagement", color: "#8B5CF6" },
        ]} height={260} />
      </GlassCard>

      {/* Platform Comparison + Sentiment */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Platform comparison */}
        <div className="lg:col-span-2">
          <GlassCard padding="md">
            <h2 className="text-white font-semibold mb-4">Platform Comparison</h2>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-white/40 text-xs border-b border-white/8">
                    <th className="text-left pb-3">Platform</th>
                    <th className="text-right pb-3">Followers</th>
                    <th className="text-right pb-3">Growth</th>
                    <th className="text-right pb-3">Eng. Rate</th>
                    <th className="text-right pb-3">Impressions</th>
                    <th className="text-right pb-3">Posts</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5">
                  {PLATFORM_DATA.map(p => (
                    <motion.tr key={p.platform} whileHover={{ backgroundColor: "rgba(255,255,255,0.03)" }} className="transition-colors">
                      <td className="py-3">
                        <PlatformIcon platform={p.platform} size="sm" showName />
                      </td>
                      <td className="text-right text-white py-3 font-semibold">{(p.followers / 1000).toFixed(1)}K</td>
                      <td className="text-right py-3">
                        <span className={`font-medium ${p.growth > 10 ? "text-neon-green" : p.growth > 5 ? "text-electric-blue" : "text-yellow-400"}`}>
                          +{p.growth}%
                        </span>
                      </td>
                      <td className="text-right py-3">
                        <span className={`font-medium ${p.engagement > 5 ? "text-neon-green" : p.engagement > 3 ? "text-electric-blue" : "text-yellow-400"}`}>
                          {p.engagement}%
                        </span>
                      </td>
                      <td className="text-right text-white/60 py-3">{(p.impressions / 1000).toFixed(0)}K</td>
                      <td className="text-right text-white/60 py-3">{p.posts}</td>
                    </motion.tr>
                  ))}
                </tbody>
              </table>
            </div>
          </GlassCard>
        </div>

        {/* Sentiment Chart */}
        <GlassCard padding="md">
          <h2 className="text-white font-semibold mb-4">Comment Sentiment</h2>
          <div className="flex justify-center mb-4">
            <ResponsiveContainer width={180} height={180}>
              <PieChart>
                <Pie data={SENTIMENT_DATA} cx="50%" cy="50%" innerRadius={55} outerRadius={75} paddingAngle={3} dataKey="value" startAngle={90} endAngle={-270}>
                  {SENTIMENT_DATA.map((entry, i) => (
                    <Cell key={i} fill={entry.fill} stroke="transparent" />
                  ))}
                </Pie>
                <Tooltip formatter={(value) => [`${value}%`, ""]} contentStyle={{ background: "rgba(10,11,26,0.95)", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 10 }} />
              </PieChart>
            </ResponsiveContainer>
          </div>
          <div className="space-y-2">
            {SENTIMENT_DATA.map(s => (
              <div key={s.name} className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full flex-shrink-0" style={{ background: s.fill }} />
                <span className="text-white/60 text-sm flex-1">{s.name}</span>
                <span className="text-white font-semibold text-sm">{s.value}%</span>
              </div>
            ))}
          </div>
          <div className="mt-4 p-3 rounded-xl bg-neon-green/10 border border-neon-green/20">
            <p className="text-neon-green text-xs font-medium">✓ Sentiment improved +12% this month</p>
          </div>
        </GlassCard>
      </div>

      {/* Competitor Analysis + Growth Predictions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Competitors */}
        <GlassCard padding="md">
          <h2 className="text-white font-semibold mb-4">Competitor Comparison</h2>
          <div className="space-y-3">
            {COMPETITORS.map(c => (
              <div key={c.name} className={`p-3 rounded-xl border transition-all ${c.vs === "you" ? "bg-electric-blue/10 border-electric-blue/30" : "bg-white/5 border-white/10"}`}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold text-white ${c.vs === "you" ? "bg-electric-blue" : "bg-white/20"}`}>
                      {c.name[0]}
                    </div>
                    <div>
                      <p className="text-white text-sm font-medium">{c.name}</p>
                      <p className="text-white/40 text-xs">{c.followers} followers</p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className={`text-sm font-semibold ${c.vs === "ahead" ? "text-neon-green" : c.vs === "you" ? "text-electric-blue" : "text-red-400"}`}>
                      {c.engRate}
                    </p>
                    <p className="text-white/30 text-xs">eng rate</p>
                  </div>
                  <div className="text-right">
                    <p className="text-neon-green text-sm font-semibold">{c.growth}</p>
                    <p className="text-white/30 text-xs">growth</p>
                  </div>
                  {c.vs !== "you" && (
                    <NeonBadge variant={c.vs === "ahead" ? "green" : "red"} size="sm">
                      {c.vs === "ahead" ? "Leading" : "Behind"}
                    </NeonBadge>
                  )}
                </div>
              </div>
            ))}
          </div>
        </GlassCard>

        {/* Growth Predictions */}
        <GlassCard padding="md">
          <div className="flex items-center gap-2 mb-4">
            <TrendingUp className="w-5 h-5 text-electric-blue" />
            <h2 className="text-white font-semibold">30-Day Growth Predictions</h2>
            <NeonBadge variant="blue" size="sm">AI</NeonBadge>
          </div>
          <MetricsChart
            data={[...OVERVIEW_DATA.slice(-3), ...PREDICTIONS.map(p => ({ date: p.date, reach: p.followers, engagement: p.engagement, impressions: 0, clicks: 0 }))]}
            type="line"
            metrics={[
              { key: "reach", label: "Followers (predicted)", color: "#3B82F6", strokeDasharray: "none" },
              { key: "engagement", label: "Engagement (predicted)", color: "#10B981" },
            ]}
            height={200}
            showLegend={false}
          />
          <div className="grid grid-cols-2 gap-3 mt-4">
            <div className="p-3 rounded-xl bg-electric-blue/10 border border-electric-blue/20 text-center">
              <p className="text-electric-blue text-xl font-bold">69.1K</p>
              <p className="text-white/40 text-xs">Projected followers in 30D</p>
            </div>
            <div className="p-3 rounded-xl bg-neon-green/10 border border-neon-green/20 text-center">
              <p className="text-neon-green text-xl font-bold">+26%</p>
              <p className="text-white/40 text-xs">Predicted growth rate</p>
            </div>
          </div>
        </GlassCard>
      </div>

      {/* AI Recommendations */}
      <GlassCard padding="md">
        <div className="flex items-center gap-2 mb-5">
          <Brain className="w-5 h-5 text-neon-purple-400" />
          <h2 className="text-white font-semibold">AI Recommendations</h2>
          <NeonBadge variant="purple" size="sm">4 insights</NeonBadge>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {AI_RECS.map((rec, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.07 }}
              className="p-4 rounded-xl bg-white/5 border border-white/10 hover:border-white/20 transition-all"
            >
              <span className="text-2xl mb-3 block">{rec.icon}</span>
              <h3 className="text-white font-semibold text-sm mb-1">{rec.title}</h3>
              <p className="text-white/50 text-xs leading-relaxed mb-3">{rec.description}</p>
              <div className="flex gap-2 flex-wrap">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${rec.impact === "High" ? "bg-red-500/20 text-red-400" : "bg-yellow-500/20 text-yellow-400"}`}>
                  {rec.impact} Impact
                </span>
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${rec.effort === "Low" ? "bg-neon-green/20 text-neon-green" : "bg-white/10 text-white/40"}`}>
                  {rec.effort} Effort
                </span>
              </div>
            </motion.div>
          ))}
        </div>
      </GlassCard>
    </div>
  );
}
