"use client";
import { useState } from "react";
import { motion } from "framer-motion";
import { Sparkles, Plus, Calendar, List, Filter, Search, Clock, CheckCircle2, FileEdit, Send, Zap } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";
import Link from "next/link";

const CONTENT_ITEMS = [
  { id: 1, title: "5 AI Trends Reshaping Marketing in 2025", platform: "linkedin", type: "post", status: "scheduled", scheduledAt: "Today, 9:00 AM", viralScore: 8.4, engagement_pred: "12.3%", pillar: "Industry Insights" },
  { id: 2, title: "Behind the Scenes: Our Creative Process", platform: "instagram", type: "carousel", status: "approved", scheduledAt: "Today, 2:00 PM", viralScore: 7.8, engagement_pred: "9.1%", pillar: "Brand Story" },
  { id: 3, title: "The Secret to Going Viral on TikTok", platform: "tiktok", type: "reel", status: "pending_review", scheduledAt: "Tomorrow, 7:00 PM", viralScore: 9.1, engagement_pred: "18.7%", pillar: "Educational" },
  { id: 4, title: "Customer Success: 300% ROI Story", platform: "twitter", type: "thread", status: "draft", scheduledAt: "-", viralScore: 7.2, engagement_pred: "8.4%", pillar: "Social Proof" },
  { id: 5, title: "Product Demo: AI Content Generator", platform: "youtube", type: "video", status: "published", scheduledAt: "Yesterday", viralScore: 8.7, engagement_pred: "6.2%", pillar: "Product" },
  { id: 6, title: "Top 10 Social Media Mistakes to Avoid", platform: "instagram", type: "carousel", status: "approved", scheduledAt: "May 18, 11:00 AM", viralScore: 8.0, engagement_pred: "10.4%", pillar: "Educational" },
];

const STATUS_COLORS: Record<string, string> = {
  published: "success", scheduled: "info", approved: "success", pending_review: "warning", draft: "default",
};

const PLATFORM_ICONS: Record<string, string> = {
  linkedin: "💼", instagram: "📸", tiktok: "🎵", twitter: "🐦", facebook: "📘", youtube: "▶️",
};

export default function ContentPage() {
  const [view, setView] = useState<"list" | "calendar">("list");
  const [statusFilter, setStatusFilter] = useState("all");
  const statuses = ["all", "draft", "pending_review", "approved", "scheduled", "published"];

  const filtered = statusFilter === "all" ? CONTENT_ITEMS : CONTENT_ITEMS.filter((c) => c.status === statusFilter);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Content Hub</h1>
          <p className="text-sm text-slate-400 mt-0.5">AI-generated content pipeline and publishing calendar</p>
        </div>
        <Link href="/content/generate">
          <button className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-electric-blue-600 to-neon-purple-600 text-white text-sm font-semibold hover:shadow-lg hover:shadow-electric-blue-500/30 transition-all">
            <Sparkles size={14} />
            Generate Content
          </button>
        </Link>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-5 gap-3">
        {[
          { label: "Total Posts", value: "234", icon: "📄" },
          { label: "Scheduled", value: "18", icon: "📅" },
          { label: "Pending Review", value: "7", icon: "⏳" },
          { label: "Published This Month", value: "64", icon: "✅" },
          { label: "Avg Viral Score", value: "8.1", icon: "🔥" },
        ].map((s) => (
          <GlassCard key={s.label} className="p-4 text-center">
            <p className="text-xl mb-1">{s.icon}</p>
            <p className="text-xl font-bold text-white">{s.value}</p>
            <p className="text-[11px] text-slate-500 mt-0.5">{s.label}</p>
          </GlassCard>
        ))}
      </div>

      {/* Filters & View Toggle */}
      <div className="flex items-center gap-3 flex-wrap">
        <div className="flex items-center gap-2 flex-1">
          {statuses.map((s) => (
            <button key={s} onClick={() => setStatusFilter(s)} className={`px-3 py-1.5 rounded-xl text-xs font-medium capitalize transition-all whitespace-nowrap ${statusFilter === s ? "bg-electric-blue-600 text-white" : "bg-white/5 text-slate-400 hover:text-white border border-white/8"}`}>
              {s.replace("_", " ")}
            </button>
          ))}
        </div>
        <div className="flex items-center bg-white/5 rounded-xl p-1 border border-white/8">
          <button onClick={() => setView("list")} className={`p-1.5 rounded-lg transition-all ${view === "list" ? "bg-white/10 text-white" : "text-slate-500"}`}><List size={14} /></button>
          <button onClick={() => setView("calendar")} className={`p-1.5 rounded-lg transition-all ${view === "calendar" ? "bg-white/10 text-white" : "text-slate-500"}`}><Calendar size={14} /></button>
        </div>
      </div>

      {/* Content List */}
      <div className="space-y-2">
        {filtered.map((item, i) => (
          <motion.div key={item.id} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}>
            <GlassCard className="p-4 hover:border-white/15 transition-all cursor-pointer group">
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-white/5 to-white/0 border border-white/10 flex items-center justify-center text-xl flex-shrink-0">
                  {PLATFORM_ICONS[item.platform] || "📱"}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <p className="text-sm font-semibold text-white truncate">{item.title}</p>
                    <NeonBadge variant={STATUS_COLORS[item.status] as any} size="sm">{item.status.replace("_", " ")}</NeonBadge>
                  </div>
                  <div className="flex items-center gap-3 text-xs text-slate-500">
                    <span className="capitalize">{item.platform}</span>
                    <span>•</span>
                    <span className="capitalize">{item.type}</span>
                    <span>•</span>
                    <span className="px-2 py-0.5 rounded-full bg-white/5 text-slate-400">{item.pillar}</span>
                  </div>
                </div>
                <div className="flex items-center gap-4 flex-shrink-0">
                  <div className="text-center hidden md:block">
                    <p className="text-xs text-slate-500">Viral Score</p>
                    <p className={`text-sm font-bold ${item.viralScore >= 8.5 ? "text-neon-green-400" : item.viralScore >= 7 ? "text-electric-blue-400" : "text-amber-400"}`}>
                      {item.viralScore}/10
                    </p>
                  </div>
                  <div className="text-center hidden lg:block">
                    <p className="text-xs text-slate-500">Predicted Eng.</p>
                    <p className="text-sm font-bold text-neon-purple-400">{item.engagement_pred}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs text-slate-500">
                      <Clock size={10} className="inline mr-1" />
                      {item.scheduledAt}
                    </p>
                  </div>
                  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-all"><FileEdit size={13} /></button>
                    <button className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-all"><Send size={13} /></button>
                  </div>
                </div>
              </div>
            </GlassCard>
          </motion.div>
        ))}
      </div>
    </div>
  );
}
