"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Plus, Calendar, Target, TrendingUp, DollarSign, Eye, X, Wand2, Check } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { StatusBadge, NeonBadge } from "@/components/ui/NeonBadge";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { PlatformId } from "@/lib/types";

const CAMPAIGNS = [
  {
    id: "c1", name: "Summer Sale 2025", status: "active", startDate: "Jun 15", endDate: "Jul 15",
    platforms: ["instagram", "facebook", "tiktok"] as PlatformId[],
    budget: 5000, spent: 3200, color: "#3B82F6",
    goals: [
      { type: "Reach", target: 500000, current: 342000 },
      { type: "Conversions", target: 1000, current: 678 },
    ],
    posts: 24, reach: 342000, engagement: 18400
  },
  {
    id: "c2", name: "Product Launch Q3", status: "planning", startDate: "Jul 1", endDate: "Jul 31",
    platforms: ["instagram", "linkedin", "twitter"] as PlatformId[],
    budget: 10000, spent: 0, color: "#8B5CF6",
    goals: [
      { type: "Awareness", target: 1000000, current: 0 },
      { type: "Followers", target: 5000, current: 0 },
    ],
    posts: 0, reach: 0, engagement: 0
  },
  {
    id: "c3", name: "Ramadan Campaign", status: "completed", startDate: "Mar 1", endDate: "Apr 10",
    platforms: ["instagram", "tiktok", "snapchat"] as PlatformId[],
    budget: 8000, spent: 7840, color: "#10B981",
    goals: [
      { type: "Reach", target: 800000, current: 924000 },
      { type: "Engagement", target: 50000, current: 67200 },
    ],
    posts: 48, reach: 924000, engagement: 67200
  },
  {
    id: "c4", name: "Brand Awareness H2", status: "active", startDate: "Jul 1", endDate: "Dec 31",
    platforms: ["youtube", "linkedin"] as PlatformId[],
    budget: 20000, spent: 4100, color: "#F59E0B",
    goals: [
      { type: "Views", target: 5000000, current: 892000 },
    ],
    posts: 8, reach: 892000, engagement: 45600
  },
];

const TIMELINE_MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const CURRENT_MONTH = 6; // July

function GanttBar({ campaign }: { campaign: typeof CAMPAIGNS[0] }) {
  const startMonth = parseInt(campaign.startDate.split(" ")[1] || "1") - 1;
  const endMonth = parseInt(campaign.endDate.split(" ")[1] || "12") - 1;
  const startPct = ((startMonth) / 12) * 100;
  const widthPct = ((endMonth - startMonth + 1) / 12) * 100;

  return (
    <div className="relative h-8 mb-2">
      <motion.div
        initial={{ width: 0, opacity: 0 }}
        animate={{ width: `${widthPct}%`, opacity: 1 }}
        transition={{ duration: 0.6, delay: 0.2 }}
        style={{ left: `${startPct}%`, background: campaign.color + "80", borderLeft: `3px solid ${campaign.color}` }}
        className="absolute top-0 h-full rounded-r-lg flex items-center px-2"
      >
        <span className="text-white text-xs font-medium truncate">{campaign.name}</span>
      </motion.div>
    </div>
  );
}

export default function CampaignsPage() {
  const [view, setView] = useState<"cards" | "gantt">("cards");
  const [showCreate, setShowCreate] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [briefGenerated, setBriefGenerated] = useState(false);
  const [newCampaign, setNewCampaign] = useState({ name: "", description: "", goal: "" });

  const handleGenerateBrief = async () => {
    setGenerating(true);
    await new Promise(r => setTimeout(r, 1500));
    setGenerating(false);
    setBriefGenerated(true);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Campaigns</h1>
          <p className="text-white/40 text-sm">{CAMPAIGNS.filter(c => c.status === "active").length} active · {CAMPAIGNS.length} total</p>
        </div>
        <div className="flex gap-3">
          <div className="flex bg-white/5 border border-white/10 rounded-xl p-1">
            {[{ id: "cards", label: "Cards" }, { id: "gantt", label: "Timeline" }].map(v => (
              <button key={v.id} onClick={() => setView(v.id as typeof view)} className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${view === v.id ? "bg-electric-blue/20 text-electric-blue" : "text-white/40 hover:text-white/60"}`}>
                {v.label}
              </button>
            ))}
          </div>
          <button onClick={() => setShowCreate(true)} className="flex items-center gap-2 px-4 py-2.5 rounded-xl btn-primary text-white text-sm font-semibold">
            <Plus className="w-4 h-4" /> New Campaign
          </button>
        </div>
      </motion.div>

      <AnimatePresence mode="wait">
        {view === "cards" ? (
          <motion.div key="cards" initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {CAMPAIGNS.map((c, i) => (
              <motion.div
                key={c.id}
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.07 }}
                whileHover={{ y: -2 }}
                className="glass-panel p-5 rounded-2xl border border-white/10 hover:border-white/20 transition-all cursor-pointer"
              >
                {/* Header */}
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <div className="w-3 h-3 rounded-full" style={{ background: c.color }} />
                      <h3 className="text-white font-semibold">{c.name}</h3>
                    </div>
                    <div className="flex items-center gap-2 text-white/40 text-xs">
                      <Calendar className="w-3 h-3" />
                      {c.startDate} — {c.endDate}
                    </div>
                  </div>
                  <StatusBadge status={c.status} />
                </div>

                {/* Platforms */}
                <div className="flex items-center gap-1.5 mb-4">
                  {c.platforms.map(p => <PlatformIcon key={p} platform={p} size="xs" />)}
                </div>

                {/* Goals */}
                <div className="space-y-2.5 mb-4">
                  {c.goals.map(g => (
                    <div key={g.type}>
                      <div className="flex justify-between text-xs mb-1">
                        <span className="text-white/50">{g.type}</span>
                        <span className="text-white">{g.current.toLocaleString()} / {g.target.toLocaleString()}</span>
                      </div>
                      <div className="h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <motion.div
                          initial={{ width: 0 }}
                          animate={{ width: `${Math.min((g.current / g.target) * 100, 100)}%` }}
                          transition={{ delay: 0.3 + i * 0.07 }}
                          className="h-full rounded-full"
                          style={{ background: g.current >= g.target ? "#10B981" : c.color }}
                        />
                      </div>
                    </div>
                  ))}
                </div>

                {/* Budget */}
                {c.budget > 0 && (
                  <div className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-1.5 text-white/40">
                      <DollarSign className="w-3.5 h-3.5" />
                      <span>${c.spent.toLocaleString()} / ${c.budget.toLocaleString()} spent</span>
                    </div>
                    <span className="text-white/30">{c.posts} posts</span>
                  </div>
                )}

                {/* Metrics */}
                {c.reach > 0 && (
                  <div className="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-white/8">
                    <div>
                      <p className="text-white font-semibold text-sm">{(c.reach / 1000).toFixed(0)}K</p>
                      <p className="text-white/30 text-xs">Reach</p>
                    </div>
                    <div>
                      <p className="text-white font-semibold text-sm">{(c.engagement / 1000).toFixed(1)}K</p>
                      <p className="text-white/30 text-xs">Engagement</p>
                    </div>
                    <div>
                      <p className="text-white font-semibold text-sm">{((c.engagement / c.reach) * 100).toFixed(1)}%</p>
                      <p className="text-white/30 text-xs">Eng Rate</p>
                    </div>
                  </div>
                )}
              </motion.div>
            ))}
          </motion.div>
        ) : (
          <motion.div key="gantt" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
            <GlassCard padding="md">
              <h3 className="text-white font-semibold mb-5">Campaign Timeline — 2025</h3>
              {/* Month headers */}
              <div className="grid grid-cols-12 gap-0 mb-3 text-white/30 text-xs text-center border-b border-white/8 pb-2">
                {TIMELINE_MONTHS.map((m, i) => (
                  <div key={m} className={`${i === CURRENT_MONTH - 1 ? "text-electric-blue font-semibold" : ""}`}>{m}</div>
                ))}
              </div>
              <div className="relative">
                {/* Current month indicator */}
                <div
                  className="absolute top-0 bottom-0 w-px bg-electric-blue/30"
                  style={{ left: `${((CURRENT_MONTH - 0.5) / 12) * 100}%` }}
                />
                {CAMPAIGNS.map(c => (
                  <div key={c.id} className="mb-3">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-white/50 text-xs w-40 truncate">{c.name}</span>
                      <StatusBadge status={c.status} />
                    </div>
                    <GanttBar campaign={c} />
                  </div>
                ))}
              </div>
            </GlassCard>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Create Campaign Modal */}
      <AnimatePresence>
        {showCreate && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            style={{ background: "rgba(0,0,0,0.7)", backdropFilter: "blur(8px)" }}
            onClick={e => { if (e.target === e.currentTarget) setShowCreate(false); }}
          >
            <motion.div
              initial={{ scale: 0.9, y: 20 }}
              animate={{ scale: 1, y: 0 }}
              exit={{ scale: 0.9, y: 20 }}
              className="glass-panel rounded-2xl p-6 w-full max-w-lg"
            >
              <div className="flex items-center justify-between mb-5">
                <h2 className="text-white font-bold text-xl">New Campaign</h2>
                <button onClick={() => setShowCreate(false)} className="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/50 hover:text-white transition-all">
                  <X className="w-4 h-4" />
                </button>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Campaign Name</label>
                  <input type="text" value={newCampaign.name} onChange={e => setNewCampaign(p => ({ ...p, name: e.target.value }))} placeholder="E.g.: Summer Sale 2025" className="input-glass" />
                </div>
                <div>
                  <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Goal / Objective</label>
                  <input type="text" value={newCampaign.goal} onChange={e => setNewCampaign(p => ({ ...p, goal: e.target.value }))} placeholder="E.g.: Increase brand awareness by 50%" className="input-glass" />
                </div>
                <div>
                  <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Brief Description</label>
                  <textarea value={newCampaign.description} onChange={e => setNewCampaign(p => ({ ...p, description: e.target.value }))} placeholder="Describe your campaign..." className="input-glass resize-none h-20" />
                </div>

                {briefGenerated && (
                  <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="p-4 rounded-xl bg-electric-blue/10 border border-electric-blue/25">
                    <div className="flex items-center gap-2 mb-2">
                      <Check className="w-4 h-4 text-neon-green" />
                      <span className="text-neon-green font-semibold text-sm">AI Brief Generated</span>
                    </div>
                    <p className="text-white/70 text-xs leading-relaxed">A comprehensive campaign brief has been created with objectives, KPIs, content recommendations, posting schedule, and budget allocation based on your goals.</p>
                  </motion.div>
                )}

                <div className="flex gap-3">
                  <button
                    onClick={handleGenerateBrief}
                    disabled={!newCampaign.goal || generating}
                    className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/60 text-sm font-medium transition-all disabled:opacity-50"
                  >
                    {generating ? <motion.div className="w-4 h-4 border-2 border-white/20 border-t-white/60 rounded-full" animate={{ rotate: 360 }} transition={{ duration: 0.8, repeat: Infinity, ease: "linear" }} /> : <Wand2 className="w-4 h-4" />}
                    AI Brief
                  </button>
                  <button
                    onClick={() => setShowCreate(false)}
                    disabled={!newCampaign.name}
                    className="flex-1 btn-primary py-2.5 rounded-xl font-semibold text-white text-sm disabled:opacity-50"
                  >
                    Create Campaign
                  </button>
                </div>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
