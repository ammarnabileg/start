"use client";
import { useState } from "react";
import { motion } from "framer-motion";
import { MessageSquare, Heart, AlertTriangle, CheckCircle2, XCircle, Send, Filter, Zap, User } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";

const COMMENTS = [
  { id: 1, platform: "instagram", author: "sarah_m", text: "This is exactly what I needed! How much does it cost?", sentiment: "positive", isLead: true, aiReply: "Hey Sarah! 👋 So glad it resonated with you! We have flexible plans starting at $49/month. I'll DM you the full breakdown – check your DMs! 💙", time: "2m ago" },
  { id: 2, platform: "linkedin", author: "Ahmed Al-Rashidi", text: "Great insight but I disagree with point #3. The data suggests otherwise.", sentiment: "neutral", isLead: false, aiReply: "Thank you for the thoughtful pushback, Ahmed! You raise a valid point. The data variance actually comes from... [continue in DM]. Would love to share the full research.", time: "15m ago" },
  { id: 3, platform: "tiktok", author: "techbro_guy", text: "🔥🔥🔥 Bro this is INSANE. Sharing this with everyone I know", sentiment: "positive", isLead: false, aiReply: "You're the best!! 🙌 If you share it, tag us – we'd love to see it reach your network! 🔥", time: "32m ago" },
  { id: 4, platform: "instagram", author: "competitor_fan", text: "Why is this worse than [competitor]? They do it better", sentiment: "negative", isLead: false, aiReply: "We appreciate honest feedback! Every tool has its strengths. What specific feature would you love to see us improve? We're always listening and shipping fast 🚀", time: "1h ago" },
  { id: 5, platform: "twitter", author: "spam_bot_123", text: "Check out my profile for FREE followers!!! DM me NOW", sentiment: "spam", isLead: false, aiReply: null, time: "2h ago" },
];

const DMS = [
  { id: 1, platform: "instagram", author: "BusinessOwner_Kate", text: "Hi! I saw your post about content strategy. We run a fashion brand and we're struggling with engagement. Can we chat?", leadScore: 9, aiReply: "Hi Kate! 👋 Absolutely, I'd love to learn more about your fashion brand! Our AI has actually helped several fashion brands increase engagement by 3x. When are you free for a quick 15-min call this week?" },
  { id: 2, platform: "linkedin", author: "Marketing Director", text: "We have a team of 50 marketers and we're evaluating AI tools. Can you send pricing for enterprise?", leadScore: 10, aiReply: "Hi! Enterprise plans are our specialty. I'll have our solutions team send over a custom proposal within 2 hours. Could you share your company name and team size so we can tailor it?" },
];

export default function CommunityPage() {
  const [activeTab, setActiveTab] = useState("comments");
  const [approvedReplies, setApprovedReplies] = useState<Set<number>>(new Set());

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Community Management</h1>
          <p className="text-sm text-slate-400 mt-0.5">AI-powered engagement with human oversight</p>
        </div>
        <div className="flex items-center gap-3">
          <NeonBadge variant="info">Auto-Reply: ON</NeonBadge>
          <NeonBadge variant="success">Spam Filter: Active</NeonBadge>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-4 gap-4">
        {[
          { label: "Pending Replies", value: "23", color: "amber" },
          { label: "Auto-Replied Today", value: "142", color: "neon-green" },
          { label: "Leads Detected", value: "8", color: "electric-blue" },
          { label: "Spam Filtered", value: "34", color: "rose" },
        ].map((s) => (
          <GlassCard key={s.label} className="p-4 text-center">
            <p className="text-2xl font-bold text-white">{s.value}</p>
            <p className="text-xs text-slate-400 mt-1">{s.label}</p>
          </GlassCard>
        ))}
      </div>

      {/* Tabs */}
      <div className="flex gap-2">
        {["comments", "dms", "escalations"].map((tab) => (
          <button key={tab} onClick={() => setActiveTab(tab)} className={`px-4 py-2 rounded-xl text-xs font-semibold capitalize transition-all ${activeTab === tab ? "bg-electric-blue-600 text-white" : "bg-white/5 text-slate-400 hover:text-white border border-white/8"}`}>
            {tab === "dms" ? "DMs" : tab.charAt(0).toUpperCase() + tab.slice(1)}
          </button>
        ))}
      </div>

      {/* Comments Queue */}
      {activeTab === "comments" && (
        <div className="space-y-3">
          {COMMENTS.map((comment) => (
            <motion.div key={comment.id} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
              <GlassCard className={`p-4 ${comment.sentiment === "spam" ? "opacity-50 border-red-500/20" : ""}`}>
                <div className="flex items-start gap-3">
                  <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-electric-blue-500/30 to-neon-purple-500/30 border border-white/10 flex items-center justify-center flex-shrink-0">
                    <User size={15} className="text-slate-400" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-sm font-semibold text-white">{comment.author}</span>
                      <NeonBadge variant="default" size="sm">{comment.platform}</NeonBadge>
                      {comment.isLead && <NeonBadge variant="success" size="sm">🎯 Lead</NeonBadge>}
                      <NeonBadge variant={comment.sentiment === "positive" ? "success" : comment.sentiment === "negative" ? "error" : comment.sentiment === "spam" ? "error" : "default"} size="sm">
                        {comment.sentiment}
                      </NeonBadge>
                      <span className="text-xs text-slate-600 ml-auto">{comment.time}</span>
                    </div>
                    <p className="text-sm text-slate-300 mb-3">{comment.text}</p>

                    {comment.aiReply && !approvedReplies.has(comment.id) ? (
                      <div className="bg-electric-blue-500/8 border border-electric-blue-500/20 rounded-xl p-3">
                        <div className="flex items-center gap-1.5 mb-2">
                          <Zap size={11} className="text-electric-blue-400" />
                          <span className="text-[10px] font-semibold text-electric-blue-400">AI Suggested Reply</span>
                        </div>
                        <p className="text-sm text-slate-300 mb-3">{comment.aiReply}</p>
                        <div className="flex items-center gap-2">
                          <button onClick={() => setApprovedReplies(new Set([...approvedReplies, comment.id]))} className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-neon-green-600 text-white text-xs font-semibold">
                            <Send size={11} /> Approve & Send
                          </button>
                          <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 text-xs">
                            Edit Reply
                          </button>
                          <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 text-xs">
                            Skip
                          </button>
                        </div>
                      </div>
                    ) : comment.sentiment === "spam" ? (
                      <div className="flex items-center gap-2">
                        <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/20 border border-red-500/30 text-red-400 text-xs">
                          <XCircle size={11} /> Hide Comment
                        </button>
                      </div>
                    ) : approvedReplies.has(comment.id) ? (
                      <div className="flex items-center gap-1.5 text-neon-green-400 text-xs">
                        <CheckCircle2 size={13} /> Reply sent
                      </div>
                    ) : null}
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </div>
      )}

      {/* DMs */}
      {activeTab === "dms" && (
        <div className="space-y-3">
          {DMS.map((dm) => (
            <GlassCard key={dm.id} className="p-4">
              <div className="flex items-start gap-3">
                <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-neon-purple-500/30 to-electric-blue-500/30 border border-white/10 flex items-center justify-center flex-shrink-0">
                  <User size={15} className="text-slate-400" />
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-sm font-semibold text-white">{dm.author}</span>
                    <NeonBadge variant="default" size="sm">{dm.platform}</NeonBadge>
                    <div className="ml-auto flex items-center gap-1">
                      <span className="text-xs text-neon-green-400 font-bold">Lead Score: {dm.leadScore}/10</span>
                    </div>
                  </div>
                  <p className="text-sm text-slate-300 mb-3">{dm.text}</p>
                  <div className="bg-neon-purple-500/8 border border-neon-purple-500/20 rounded-xl p-3">
                    <div className="flex items-center gap-1.5 mb-2">
                      <Zap size={11} className="text-neon-purple-400" />
                      <span className="text-[10px] font-semibold text-neon-purple-400">AI Response</span>
                    </div>
                    <p className="text-sm text-slate-300 mb-3">{dm.aiReply}</p>
                    <div className="flex gap-2">
                      <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-neon-purple-600 text-white text-xs font-semibold">
                        <Send size={11} /> Send Response
                      </button>
                      <button className="text-xs px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300">Assign to Human</button>
                    </div>
                  </div>
                </div>
              </div>
            </GlassCard>
          ))}
        </div>
      )}
    </div>
  );
}
