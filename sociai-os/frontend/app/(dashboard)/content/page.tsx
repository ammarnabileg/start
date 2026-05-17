"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Plus, Calendar, List, Filter, Search, Zap, Eye, Edit, Trash2, Clock, CheckCircle, FileText } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { StatusBadge } from "@/components/ui/NeonBadge";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { ContentCalendar } from "@/components/content/ContentCalendar";
import { PlatformId, ContentStatus } from "@/lib/types";
import Link from "next/link";

const POSTS = [
  { id: "1", title: "Morning Motivation Quote", caption: "Start your day with purpose...", platform: "instagram" as PlatformId, status: "published" as ContentStatus, scheduledAt: "Jun 30, 9:00 AM", engagement: "4.2K", aiGenerated: true },
  { id: "2", title: "Product Launch Teaser", caption: "Something big is coming...", platform: "twitter" as PlatformId, status: "scheduled" as ContentStatus, scheduledAt: "Jul 1, 2:00 PM", engagement: null, aiGenerated: true },
  { id: "3", title: "Behind the Scenes", caption: "Taking you inside our creative process...", platform: "facebook" as PlatformId, status: "review" as ContentStatus, scheduledAt: null, engagement: null, aiGenerated: false },
  { id: "4", title: "Industry Insights Thread", caption: "5 trends reshaping our industry in 2025...", platform: "linkedin" as PlatformId, status: "approved" as ContentStatus, scheduledAt: "Jul 2, 8:00 AM", engagement: null, aiGenerated: true },
  { id: "5", title: "Tutorial: Getting Started", caption: "Step-by-step guide to...", platform: "youtube" as PlatformId, status: "draft" as ContentStatus, scheduledAt: null, engagement: null, aiGenerated: false },
  { id: "6", title: "Dance Challenge Video", caption: "Try this trending dance...", platform: "tiktok" as PlatformId, status: "scheduled" as ContentStatus, scheduledAt: "Jul 1, 6:00 PM", engagement: null, aiGenerated: true },
  { id: "7", title: "Customer Success Story", caption: "How Brand X grew 200%...", platform: "instagram" as PlatformId, status: "draft" as ContentStatus, scheduledAt: null, engagement: null, aiGenerated: false },
  { id: "8", title: "Weekly Newsletter", caption: "This week in social media...", platform: "linkedin" as PlatformId, status: "published" as ContentStatus, scheduledAt: "Jun 28, 8:00 AM", engagement: "892", aiGenerated: false },
];

const PIPELINES: { status: ContentStatus; label: string; color: string }[] = [
  { status: "draft", label: "Draft", color: "#6B7280" },
  { status: "review", label: "In Review", color: "#F59E0B" },
  { status: "approved", label: "Approved", color: "#3B82F6" },
  { status: "scheduled", label: "Scheduled", color: "#8B5CF6" },
  { status: "published", label: "Published", color: "#10B981" },
];

const PLATFORM_FILTERS = ["all", "instagram", "facebook", "twitter", "tiktok", "youtube", "linkedin"] as const;

export default function ContentPage() {
  const [view, setView] = useState<"list" | "calendar" | "pipeline">("list");
  const [platformFilter, setPlatformFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const [search, setSearch] = useState("");

  const filtered = POSTS.filter(p => {
    if (platformFilter !== "all" && p.platform !== platformFilter) return false;
    if (statusFilter !== "all" && p.status !== statusFilter) return false;
    if (search && !p.title.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white">Content Hub</h1>
          <p className="text-white/40 text-sm">{POSTS.length} pieces of content · {POSTS.filter(p => p.status === "scheduled").length} scheduled</p>
        </div>
        <div className="flex gap-3">
          <Link href="/content/generate">
            <motion.button whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }} className="flex items-center gap-2 px-4 py-2.5 rounded-xl btn-primary text-white text-sm font-semibold">
              <Zap className="w-4 h-4" /> AI Generate
            </motion.button>
          </Link>
          <button className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/70 text-sm transition-all">
            <Plus className="w-4 h-4" /> New Post
          </button>
        </div>
      </motion.div>

      {/* Filters + View Toggle */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div className="flex items-center gap-2 flex-wrap">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
            <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search content..." className="input-glass pl-9 w-48 py-2" />
          </div>
          <div className="flex gap-1 bg-white/5 border border-white/10 rounded-xl p-1 overflow-x-auto">
            {PLATFORM_FILTERS.map(pf => (
              <button key={pf} onClick={() => setPlatformFilter(pf)} className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all capitalize whitespace-nowrap ${platformFilter === pf ? "bg-electric-blue/20 text-electric-blue" : "text-white/40 hover:text-white/60"}`}>
                {pf === "all" ? "All Platforms" : pf}
              </button>
            ))}
          </div>
          <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} className="input-glass py-2 text-sm w-36">
            <option value="all">All Status</option>
            <option value="draft">Draft</option>
            <option value="review">In Review</option>
            <option value="approved">Approved</option>
            <option value="scheduled">Scheduled</option>
            <option value="published">Published</option>
          </select>
        </div>

        <div className="flex bg-white/5 border border-white/10 rounded-xl p-1 gap-1">
          {[
            { id: "list", icon: List },
            { id: "calendar", icon: Calendar },
            { id: "pipeline", icon: Filter },
          ].map(v => {
            const Icon = v.icon;
            return (
              <button key={v.id} onClick={() => setView(v.id as typeof view)} className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all capitalize ${view === v.id ? "bg-electric-blue/20 text-electric-blue" : "text-white/40 hover:text-white/60"}`}>
                <Icon className="w-3.5 h-3.5" /> {v.id.charAt(0).toUpperCase() + v.id.slice(1)}
              </button>
            );
          })}
        </div>
      </div>

      <AnimatePresence mode="wait">
        {view === "list" && (
          <motion.div key="list" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <GlassCard padding="none">
              <div className="divide-y divide-white/5">
                {filtered.map((post, i) => (
                  <motion.div key={post.id} initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.04 }} className="flex items-center gap-4 p-4 hover:bg-white/3 transition-colors group">
                    <PlatformIcon platform={post.platform} size="sm" />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <h3 className="text-white font-medium text-sm truncate">{post.title}</h3>
                        {post.aiGenerated && <span className="flex-shrink-0 text-xs px-1.5 py-0.5 rounded bg-electric-blue/15 text-electric-blue border border-electric-blue/25">AI</span>}
                      </div>
                      <p className="text-white/40 text-xs mt-0.5 truncate">{post.caption}</p>
                    </div>
                    <StatusBadge status={post.status} />
                    {post.scheduledAt && <p className="text-white/50 text-xs hidden sm:block">{post.scheduledAt}</p>}
                    {post.engagement && (
                      <div className="text-right">
                        <p className="text-neon-green text-xs font-semibold">{post.engagement}</p>
                        <p className="text-white/30 text-xs">engaged</p>
                      </div>
                    )}
                    <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button className="w-7 h-7 rounded-lg bg-white/5 hover:bg-electric-blue/20 flex items-center justify-center text-white/40 hover:text-electric-blue transition-all"><Edit className="w-3.5 h-3.5" /></button>
                      <button className="w-7 h-7 rounded-lg bg-white/5 hover:bg-red-500/20 flex items-center justify-center text-white/40 hover:text-red-400 transition-all"><Trash2 className="w-3.5 h-3.5" /></button>
                    </div>
                  </motion.div>
                ))}
              </div>
            </GlassCard>
          </motion.div>
        )}

        {view === "calendar" && (
          <motion.div key="calendar" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <ContentCalendar />
          </motion.div>
        )}

        {view === "pipeline" && (
          <motion.div key="pipeline" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <div className="flex gap-4 overflow-x-auto pb-2 scrollbar-thin">
              {PIPELINES.map(stage => {
                const stagePosts = POSTS.filter(p => p.status === stage.status);
                return (
                  <div key={stage.status} className="flex-shrink-0 w-64">
                    <div className="flex items-center gap-2 mb-3 px-1">
                      <div className="w-2 h-2 rounded-full" style={{ background: stage.color }} />
                      <h3 className="text-white/70 font-semibold text-sm">{stage.label}</h3>
                      <span className="ml-auto text-white/30 text-xs bg-white/5 px-2 py-0.5 rounded-full">{stagePosts.length}</span>
                    </div>
                    <div className="space-y-3">
                      {stagePosts.map(post => (
                        <motion.div key={post.id} whileHover={{ scale: 1.01, y: -1 }} className="p-3 rounded-xl border cursor-pointer" style={{ background: "rgba(255,255,255,0.04)", borderColor: "rgba(255,255,255,0.08)" }}>
                          <div className="flex items-start justify-between gap-2 mb-2">
                            <h4 className="text-white text-sm font-medium leading-snug">{post.title}</h4>
                            <PlatformIcon platform={post.platform} size="xs" />
                          </div>
                          <p className="text-white/40 text-xs truncate">{post.caption}</p>
                          {post.scheduledAt && (
                            <div className="flex items-center gap-1 mt-2">
                              <Clock className="w-3 h-3 text-white/25" />
                              <span className="text-white/30 text-xs">{post.scheduledAt}</span>
                            </div>
                          )}
                          {post.aiGenerated && <span className="inline-block mt-2 text-xs px-1.5 py-0.5 rounded bg-electric-blue/15 text-electric-blue border border-electric-blue/25">AI</span>}
                        </motion.div>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
