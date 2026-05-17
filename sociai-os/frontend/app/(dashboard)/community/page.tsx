"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { MessageSquare, Mail, Send, Archive, AlertTriangle, Star, Filter, Wand2, ThumbsUp } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { PlatformId } from "@/lib/types";

const COMMENTS = [
  { id: "cm1", platform: "instagram" as PlatformId, author: "Sarah M.", handle: "@sarahm", content: "This is exactly what I needed! Amazing content as always 🙌", sentiment: "positive" as const, status: "pending", likes: 12, suggestions: ["Thank you so much Sarah! Really glad it helped! 💙", "So happy to hear that! More coming soon!", "You made our day Sarah! 🌟"] },
  { id: "cm2", platform: "facebook" as PlatformId, author: "Ahmed K.", handle: "@ahmedk", content: "Can you explain more about the pricing? Interested in the Pro plan.", sentiment: "neutral" as const, status: "pending", likes: 3, suggestions: ["Hi Ahmed! Our Pro plan starts at $49/mo. I'll DM you the full details!", "Thanks for your interest Ahmed! Check your DMs for pricing info 📩"] },
  { id: "cm3", platform: "twitter" as PlatformId, author: "DisgruntledUser", handle: "@angry_user", content: "This product is terrible. Worst experience ever. DO NOT BUY.", sentiment: "negative" as const, status: "pending", likes: 1, suggestions: ["We're so sorry to hear about your experience. Can you DM us with details so we can make this right?", "Hi there, we'd love to help resolve this. Please reach out to support@brand.com"] },
  { id: "cm4", platform: "tiktok" as PlatformId, author: "TikTokFan23", handle: "@tikfan23", content: "Can you make a tutorial on this? This would go viral fr", sentiment: "positive" as const, status: "replied", likes: 89, suggestions: ["Great idea! Tutorial dropping this week 🎬", "YES! That tutorial is in the works 👀"] },
  { id: "cm5", platform: "instagram" as PlatformId, author: "BusinessOwner", handle: "@bizowner", content: "We'd love to partner with you for our product launch. How can we get in touch?", sentiment: "positive" as const, status: "pending", likes: 7, suggestions: ["Hi! We'd love to explore a partnership. Please email us at partnerships@brand.com 🤝"] },
  { id: "cm6", platform: "linkedin" as PlatformId, author: "Marketing Pro", handle: "@mktpro", content: "Fantastic insights! The data here is very relevant to our strategy.", sentiment: "positive" as const, status: "pending", likes: 34, suggestions: ["Thank you! Happy to share more details. Let's connect!", "Glad you found it useful! Feel free to share with your team."] },
];

const DMS = [
  { id: "dm1", platform: "instagram" as PlatformId, contact: "Sarah Designer", handle: "@sarahd", lastMsg: "Hi! I saw your post about...", unread: 3, sentiment: "positive" as const, isLead: true, leadScore: 85 },
  { id: "dm2", platform: "twitter" as PlatformId, contact: "Tech Startup", handle: "@techstart", lastMsg: "We'd like to discuss a collaboration...", unread: 1, sentiment: "positive" as const, isLead: true, leadScore: 92 },
  { id: "dm3", platform: "facebook" as PlatformId, contact: "Random User", handle: "@ruser", lastMsg: "What's the price for enterprise?", unread: 0, sentiment: "neutral" as const, isLead: false, leadScore: 30 },
];

const SENTIMENT_COLORS = {
  positive: { bg: "bg-neon-green/15", text: "text-neon-green", border: "border-neon-green/25" },
  neutral: { bg: "bg-white/8", text: "text-white/50", border: "border-white/15" },
  negative: { bg: "bg-red-500/15", text: "text-red-400", border: "border-red-500/25" },
  spam: { bg: "bg-yellow-500/15", text: "text-yellow-400", border: "border-yellow-500/25" },
};

export default function CommunityPage() {
  const [activeTab, setActiveTab] = useState<"comments" | "dms">("comments");
  const [replyText, setReplyText] = useState<Record<string, string>>({});
  const [replied, setReplied] = useState<Set<string>>(new Set());
  const [selectedSuggestion, setSelectedSuggestion] = useState<Record<string, string>>({});
  const [filterSentiment, setFilterSentiment] = useState("all");

  const handleSendReply = (id: string) => {
    if (!replyText[id]?.trim()) return;
    setReplied(prev => new Set(Array.from(prev).concat(id)));
    setReplyText(prev => ({ ...prev, [id]: "" }));
  };

  const filteredComments = COMMENTS.filter(c => {
    if (filterSentiment !== "all" && c.sentiment !== filterSentiment) return false;
    return true;
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Community Hub</h1>
          <p className="text-white/40 text-sm">{COMMENTS.filter(c => c.status === "pending").length} comments pending · {DMS.filter(d => d.unread > 0).length} unread DMs</p>
        </div>
      </motion.div>

      {/* Stats */}
      <div className="grid grid-cols-4 gap-4">
        {[
          { label: "Pending", value: COMMENTS.filter(c => c.status === "pending").length, color: "yellow" as const },
          { label: "Positive", value: COMMENTS.filter(c => c.sentiment === "positive").length, color: "green" as const },
          { label: "Negative", value: COMMENTS.filter(c => c.sentiment === "negative").length, color: "red" as const },
          { label: "Leads", value: DMS.filter(d => d.isLead).length, color: "blue" as const },
        ].map(s => (
          <GlassCard key={s.label} padding="sm">
            <p className="text-white/40 text-xs">{s.label}</p>
            <p className="text-white text-2xl font-bold">{s.value}</p>
          </GlassCard>
        ))}
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-white/8 pb-3">
        {[
          { id: "comments", label: "Comments", icon: MessageSquare, count: COMMENTS.filter(c => c.status === "pending").length },
          { id: "dms", label: "Direct Messages", icon: Mail, count: DMS.filter(d => d.unread > 0).length },
        ].map(tab => {
          const Icon = tab.icon;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as typeof activeTab)}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all ${activeTab === tab.id ? "bg-electric-blue/15 text-electric-blue border border-electric-blue/30" : "text-white/40 hover:text-white/60"}`}
            >
              <Icon className="w-4 h-4" />
              {tab.label}
              {tab.count > 0 && <span className="w-5 h-5 rounded-full bg-red-500 text-white text-xs flex items-center justify-center">{tab.count}</span>}
            </button>
          );
        })}
      </div>

      <AnimatePresence mode="wait">
        {activeTab === "comments" && (
          <motion.div key="comments" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
            {/* Filters */}
            <div className="flex gap-2 mb-5 flex-wrap">
              <span className="flex items-center gap-1.5 text-white/40 text-sm"><Filter className="w-3.5 h-3.5" />Filter:</span>
              {["all", "positive", "neutral", "negative"].map(s => (
                <button
                  key={s}
                  onClick={() => setFilterSentiment(s)}
                  className={`px-3 py-1 rounded-lg text-xs font-medium capitalize transition-all border ${filterSentiment === s ? "bg-electric-blue/20 border-electric-blue/40 text-electric-blue" : "border-white/10 bg-white/5 text-white/40 hover:text-white/60"}`}
                >
                  {s}
                </button>
              ))}
            </div>

            <div className="space-y-4">
              {filteredComments.map((comment, i) => {
                const sentColors = SENTIMENT_COLORS[comment.sentiment];
                const isReplied = replied.has(comment.id);

                return (
                  <motion.div
                    key={comment.id}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: i * 0.05 }}
                    className={`glass-panel p-4 rounded-2xl border transition-all ${isReplied ? "opacity-60" : ""}`}
                  >
                    <div className="flex items-start gap-3">
                      {/* Platform + Avatar */}
                      <div className="relative flex-shrink-0">
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-electric-blue to-neon-purple flex items-center justify-center text-white font-bold text-sm">
                          {comment.author[0]}
                        </div>
                        <div className="absolute -bottom-1 -right-1">
                          <PlatformIcon platform={comment.platform} size="xs" variant="circle" />
                        </div>
                      </div>

                      <div className="flex-1 min-w-0">
                        {/* Author info */}
                        <div className="flex items-center gap-2 flex-wrap mb-2">
                          <span className="text-white font-semibold text-sm">{comment.author}</span>
                          <span className="text-white/30 text-xs">{comment.handle}</span>
                          <span className={`text-xs px-2 py-0.5 rounded-full border ${sentColors.bg} ${sentColors.text} ${sentColors.border}`}>
                            {comment.sentiment}
                          </span>
                          {comment.status === "replied" && <NeonBadge variant="green" size="sm">Replied</NeonBadge>}
                          <ThumbsUp className="w-3 h-3 text-white/20 ml-auto" />
                          <span className="text-white/25 text-xs">{comment.likes}</span>
                        </div>

                        {/* Content */}
                        <p className="text-white/70 text-sm leading-relaxed mb-3">{comment.content}</p>

                        {/* AI Suggestions */}
                        {!isReplied && comment.suggestions.length > 0 && (
                          <div className="mb-3">
                            <div className="flex items-center gap-1.5 mb-2">
                              <Wand2 className="w-3 h-3 text-electric-blue" />
                              <span className="text-white/40 text-xs font-medium">AI Reply Suggestions</span>
                            </div>
                            <div className="space-y-1.5">
                              {comment.suggestions.map((s, si) => (
                                <button
                                  key={si}
                                  onClick={() => {
                                    setReplyText(prev => ({ ...prev, [comment.id]: s }));
                                    setSelectedSuggestion(prev => ({ ...prev, [comment.id]: s }));
                                  }}
                                  className={`w-full text-left text-xs px-3 py-2 rounded-lg border transition-all ${selectedSuggestion[comment.id] === s ? "bg-electric-blue/15 border-electric-blue/30 text-electric-blue" : "bg-white/5 border-white/10 text-white/50 hover:bg-white/8 hover:text-white/70"}`}
                                >
                                  {s}
                                </button>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* Reply Input */}
                        {!isReplied && (
                          <div className="flex gap-2">
                            <input
                              type="text"
                              value={replyText[comment.id] || ""}
                              onChange={e => setReplyText(prev => ({ ...prev, [comment.id]: e.target.value }))}
                              placeholder="Write a reply..."
                              className="input-glass flex-1 py-2 text-sm"
                            />
                            <button onClick={() => handleSendReply(comment.id)} disabled={!replyText[comment.id]?.trim()} className="px-4 py-2 rounded-xl btn-primary text-white text-sm font-medium disabled:opacity-50 flex items-center gap-1.5">
                              <Send className="w-3.5 h-3.5" /> Reply
                            </button>
                            <button className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 hover:bg-yellow-500/20 hover:border-yellow-500/30 flex items-center justify-center text-white/30 hover:text-yellow-400 transition-all">
                              <AlertTriangle className="w-4 h-4" />
                            </button>
                            <button className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 flex items-center justify-center text-white/30 hover:text-white/50 transition-all">
                              <Archive className="w-4 h-4" />
                            </button>
                          </div>
                        )}

                        {isReplied && (
                          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="flex items-center gap-2 text-neon-green text-sm">
                            <div className="w-4 h-4 rounded-full bg-neon-green flex items-center justify-center">
                              <svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3" className="w-3 h-3"><path d="M5 13l4 4L19 7" /></svg>
                            </div>
                            Reply sent successfully
                          </motion.div>
                        )}
                      </div>
                    </div>
                  </motion.div>
                );
              })}
            </div>
          </motion.div>
        )}

        {activeTab === "dms" && (
          <motion.div key="dms" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
              {/* DM List */}
              <div className="space-y-3">
                {DMS.map(dm => (
                  <motion.div key={dm.id} whileHover={{ scale: 1.01 }} className="glass-panel p-4 rounded-xl border border-white/10 cursor-pointer hover:border-white/20 transition-all">
                    <div className="flex items-start gap-3">
                      <div className="relative">
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-neon-purple to-electric-blue flex items-center justify-center text-white font-bold text-sm">
                          {dm.contact[0]}
                        </div>
                        <div className="absolute -bottom-1 -right-1">
                          <PlatformIcon platform={dm.platform} size="xs" variant="circle" />
                        </div>
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-0.5">
                          <span className="text-white font-medium text-sm">{dm.contact}</span>
                          {dm.isLead && <Star className="w-3 h-3 text-yellow-400" />}
                          {dm.unread > 0 && <span className="ml-auto w-5 h-5 bg-electric-blue rounded-full text-white text-xs flex items-center justify-center">{dm.unread}</span>}
                        </div>
                        <p className="text-white/40 text-xs truncate">{dm.lastMsg}</p>
                        {dm.isLead && <div className="mt-1.5 flex items-center gap-1.5"><span className="text-xs text-yellow-400">Lead Score:</span><span className="text-xs font-bold text-white">{dm.leadScore}/100</span></div>}
                      </div>
                    </div>
                  </motion.div>
                ))}
              </div>

              {/* DM Detail */}
              <div className="lg:col-span-2">
                <GlassCard padding="md">
                  <div className="flex items-center gap-3 mb-5 pb-3 border-b border-white/8">
                    <div className="w-10 h-10 rounded-full bg-gradient-to-br from-neon-purple to-electric-blue flex items-center justify-center text-white font-bold">S</div>
                    <div>
                      <p className="text-white font-semibold">Sarah Designer</p>
                      <p className="text-white/40 text-sm">@sarahd · Lead Score: 85/100</p>
                    </div>
                    <NeonBadge variant="yellow" className="ml-auto">Hot Lead</NeonBadge>
                  </div>

                  <div className="space-y-3 mb-5 max-h-64 overflow-y-auto scrollbar-thin">
                    {[
                      { from: "contact", msg: "Hi! I saw your post about AI tools for social media. I manage accounts for 5 brands.", time: "2h ago" },
                      { from: "contact", msg: "Would love to know more about your enterprise plan.", time: "2h ago" },
                      { from: "you", msg: "Hi Sarah! Thanks for reaching out. Our enterprise plan covers unlimited platforms...", time: "1h ago" },
                      { from: "contact", msg: "This sounds perfect! Can we schedule a demo call?", time: "30m ago" },
                    ].map((msg, i) => (
                      <div key={i} className={`flex ${msg.from === "you" ? "justify-end" : "justify-start"}`}>
                        <div className={`max-w-xs px-3 py-2 rounded-xl text-sm ${msg.from === "you" ? "bg-electric-blue text-white" : "bg-white/10 text-white/80"}`}>
                          <p>{msg.msg}</p>
                          <p className="text-xs opacity-50 mt-1">{msg.time}</p>
                        </div>
                      </div>
                    ))}
                  </div>

                  <div className="flex gap-2">
                    <input type="text" placeholder="Type a message..." className="input-glass flex-1 py-2 text-sm" />
                    <button className="px-4 py-2 btn-primary rounded-xl text-white text-sm font-medium flex items-center gap-1.5">
                      <Send className="w-3.5 h-3.5" /> Send
                    </button>
                  </div>
                </GlassCard>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
