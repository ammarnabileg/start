"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Users, Plus, Mail, Shield, Check, X, Clock, CheckCircle, XCircle, MoreVertical } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { PlatformId } from "@/lib/types";

const TEAM_MEMBERS = [
  { id: "m1", name: "John Doe", email: "john@brand.com", role: "owner", avatar: "JD", status: "active", lastActive: "Online", platforms: ["instagram", "facebook", "tiktok"] as PlatformId[], joinedAt: "Jan 2024" },
  { id: "m2", name: "Sarah Ahmed", email: "sarah@brand.com", role: "admin", avatar: "SA", status: "active", lastActive: "5m ago", platforms: ["instagram", "linkedin"] as PlatformId[], joinedAt: "Feb 2024" },
  { id: "m3", name: "Mike Chen", email: "mike@brand.com", role: "editor", avatar: "MC", status: "active", lastActive: "1h ago", platforms: ["twitter", "tiktok", "youtube"] as PlatformId[], joinedAt: "Mar 2024" },
  { id: "m4", name: "Fatima Ali", email: "fatima@brand.com", role: "analyst", avatar: "FA", status: "active", lastActive: "2h ago", platforms: ["instagram", "facebook"] as PlatformId[], joinedAt: "Apr 2024" },
  { id: "m5", name: "Alex Kim", email: "alex@brand.com", role: "viewer", avatar: "AK", status: "invited", lastActive: null, platforms: [] as PlatformId[], joinedAt: null },
];

const APPROVALS = [
  { id: "ap1", type: "post", title: "Summer Sale Campaign Post", requestedBy: "Mike Chen", time: "30m ago", platform: "instagram" as PlatformId },
  { id: "ap2", type: "campaign", title: "Q3 Product Launch Campaign", requestedBy: "Sarah Ahmed", time: "2h ago", platform: "facebook" as PlatformId },
  { id: "ap3", type: "post", title: "LinkedIn Article: Industry Trends", requestedBy: "Fatima Ali", time: "3h ago", platform: "linkedin" as PlatformId },
];

const ROLE_COLORS: Record<string, "blue" | "purple" | "green" | "yellow" | "gray"> = {
  owner: "purple",
  admin: "blue",
  editor: "green",
  analyst: "yellow",
  viewer: "gray",
};

const PERMISSIONS = [
  { resource: "Content", actions: ["Create", "Edit", "Delete", "Publish", "Approve"] },
  { resource: "Campaigns", actions: ["Create", "Edit", "Delete", "Approve"] },
  { resource: "Analytics", actions: ["View", "Export"] },
  { resource: "Community", actions: ["Reply", "Archive", "Escalate"] },
  { resource: "Settings", actions: ["View", "Edit"] },
  { resource: "Team", actions: ["Invite", "Remove", "Manage"] },
];

const ROLE_PERMISSIONS: Record<string, Record<string, string[]>> = {
  owner: { Content: ["Create", "Edit", "Delete", "Publish", "Approve"], Campaigns: ["Create", "Edit", "Delete", "Approve"], Analytics: ["View", "Export"], Community: ["Reply", "Archive", "Escalate"], Settings: ["View", "Edit"], Team: ["Invite", "Remove", "Manage"] },
  admin: { Content: ["Create", "Edit", "Delete", "Publish", "Approve"], Campaigns: ["Create", "Edit", "Approve"], Analytics: ["View", "Export"], Community: ["Reply", "Archive", "Escalate"], Settings: ["View"], Team: ["Invite"] },
  editor: { Content: ["Create", "Edit", "Publish"], Campaigns: ["Create", "Edit"], Analytics: ["View"], Community: ["Reply"], Settings: [], Team: [] },
  analyst: { Content: [], Campaigns: [], Analytics: ["View", "Export"], Community: [], Settings: [], Team: [] },
  viewer: { Content: [], Campaigns: [], Analytics: ["View"], Community: [], Settings: [], Team: [] },
};

export default function TeamPage() {
  const [activeTab, setActiveTab] = useState<"members" | "permissions" | "approvals">("members");
  const [showInvite, setShowInvite] = useState(false);
  const [inviteEmail, setInviteEmail] = useState("");
  const [inviteRole, setInviteRole] = useState("editor");
  const [approvals, setApprovals] = useState(APPROVALS);
  const [selectedMemberRole, setSelectedMemberRole] = useState("owner");

  const handleApprove = (id: string) => setApprovals(prev => prev.filter(a => a.id !== id));
  const handleReject = (id: string) => setApprovals(prev => prev.filter(a => a.id !== id));

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Team Management</h1>
          <p className="text-white/40 text-sm">{TEAM_MEMBERS.filter(m => m.status === "active").length} active members</p>
        </div>
        <button onClick={() => setShowInvite(true)} className="flex items-center gap-2 px-4 py-2.5 rounded-xl btn-primary text-white text-sm font-semibold">
          <Plus className="w-4 h-4" /> Invite Member
        </button>
      </motion.div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-white/8 pb-3">
        {[
          { id: "members", label: "Members", count: TEAM_MEMBERS.length },
          { id: "permissions", label: "Permissions Matrix", count: null },
          { id: "approvals", label: "Approval Queue", count: approvals.length },
        ].map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id as typeof activeTab)}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all ${activeTab === tab.id ? "bg-electric-blue/15 text-electric-blue border border-electric-blue/30" : "text-white/40 hover:text-white/60"}`}
          >
            {tab.label}
            {tab.count !== null && <span className={`w-5 h-5 rounded-full text-xs flex items-center justify-center ${activeTab === tab.id ? "bg-electric-blue text-white" : "bg-white/10 text-white/40"}`}>{tab.count}</span>}
          </button>
        ))}
      </div>

      <AnimatePresence mode="wait">
        {activeTab === "members" && (
          <motion.div key="members" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
            <GlassCard padding="none">
              <div className="divide-y divide-white/5">
                {TEAM_MEMBERS.map((member, i) => (
                  <motion.div
                    key={member.id}
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: i * 0.05 }}
                    className="flex items-center gap-4 p-4 hover:bg-white/3 transition-colors"
                  >
                    {/* Avatar */}
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0 ${member.status === "invited" ? "bg-white/10 border-2 border-dashed border-white/20" : "bg-gradient-to-br from-electric-blue to-neon-purple"}`}>
                      {member.avatar}
                    </div>

                    {/* Info */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-white font-semibold text-sm">{member.name}</span>
                        <NeonBadge variant={ROLE_COLORS[member.role]} size="sm">{member.role}</NeonBadge>
                        {member.status === "invited" && <NeonBadge variant="yellow" size="sm">Invited</NeonBadge>}
                      </div>
                      <p className="text-white/40 text-xs mt-0.5">{member.email}</p>
                    </div>

                    {/* Platforms */}
                    <div className="hidden sm:flex gap-1">
                      {member.platforms.map(p => <PlatformIcon key={p} platform={p} size="xs" />)}
                    </div>

                    {/* Last active */}
                    <div className="text-right hidden md:block">
                      <div className="flex items-center gap-1.5 justify-end">
                        {member.lastActive === "Online" && <span className="w-2 h-2 rounded-full bg-neon-green" />}
                        <span className="text-white/40 text-xs">{member.lastActive || "Pending"}</span>
                      </div>
                      {member.joinedAt && <p className="text-white/25 text-xs">Since {member.joinedAt}</p>}
                    </div>

                    <button className="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/30 hover:text-white/60 transition-all">
                      <MoreVertical className="w-4 h-4" />
                    </button>
                  </motion.div>
                ))}
              </div>
            </GlassCard>
          </motion.div>
        )}

        {activeTab === "permissions" && (
          <motion.div key="permissions" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
            <GlassCard padding="md">
              <div className="flex items-center gap-3 mb-5">
                <h3 className="text-white font-semibold">Permission Matrix</h3>
                <div className="flex gap-2">
                  {["owner", "admin", "editor", "analyst", "viewer"].map(role => (
                    <button key={role} onClick={() => setSelectedMemberRole(role)} className={`px-2.5 py-1 rounded-lg text-xs font-medium capitalize transition-all ${selectedMemberRole === role ? "bg-electric-blue/20 text-electric-blue" : "bg-white/5 text-white/40"}`}>
                      {role}
                    </button>
                  ))}
                </div>
              </div>
              <div className="space-y-4">
                {PERMISSIONS.map(resource => {
                  const perms = ROLE_PERMISSIONS[selectedMemberRole]?.[resource.resource] || [];
                  return (
                    <div key={resource.resource}>
                      <h4 className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-2">{resource.resource}</h4>
                      <div className="flex gap-2 flex-wrap">
                        {resource.actions.map(action => {
                          const hasPermission = perms.includes(action);
                          return (
                            <div key={action} className={`flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs border transition-all ${hasPermission ? "bg-neon-green/15 border-neon-green/30 text-neon-green" : "bg-white/5 border-white/10 text-white/30"}`}>
                              {hasPermission ? <Check className="w-3 h-3" /> : <X className="w-3 h-3" />}
                              {action}
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  );
                })}
              </div>
            </GlassCard>
          </motion.div>
        )}

        {activeTab === "approvals" && (
          <motion.div key="approvals" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
            {approvals.length === 0 ? (
              <div className="glass-panel p-12 rounded-2xl text-center">
                <CheckCircle className="w-12 h-12 text-neon-green mx-auto mb-3" />
                <p className="text-white/50 font-semibold">All caught up!</p>
                <p className="text-white/30 text-sm">No pending approvals</p>
              </div>
            ) : (
              <div className="space-y-4">
                {approvals.map(ap => (
                  <GlassCard key={ap.id} padding="md">
                    <div className="flex items-center gap-4">
                      <PlatformIcon platform={ap.platform} size="md" />
                      <div className="flex-1 min-w-0">
                        <h3 className="text-white font-semibold text-sm">{ap.title}</h3>
                        <p className="text-white/40 text-xs mt-0.5">Requested by {ap.requestedBy} · {ap.time}</p>
                      </div>
                      <NeonBadge variant="yellow" size="sm">{ap.type}</NeonBadge>
                      <div className="flex gap-2">
                        <motion.button
                          onClick={() => handleApprove(ap.id)}
                          whileHover={{ scale: 1.05 }}
                          whileTap={{ scale: 0.95 }}
                          className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-neon-green/15 border border-neon-green/30 text-neon-green text-sm font-medium hover:bg-neon-green/25 transition-all"
                        >
                          <CheckCircle className="w-4 h-4" /> Approve
                        </motion.button>
                        <motion.button
                          onClick={() => handleReject(ap.id)}
                          whileHover={{ scale: 1.05 }}
                          whileTap={{ scale: 0.95 }}
                          className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/25 transition-all"
                        >
                          <XCircle className="w-4 h-4" /> Reject
                        </motion.button>
                      </div>
                    </div>
                  </GlassCard>
                ))}
              </div>
            )}
          </motion.div>
        )}
      </AnimatePresence>

      {/* Invite Modal */}
      <AnimatePresence>
        {showInvite && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
            style={{ background: "rgba(0,0,0,0.7)", backdropFilter: "blur(8px)" }}
            onClick={e => { if (e.target === e.currentTarget) setShowInvite(false); }}
          >
            <motion.div initial={{ scale: 0.9, y: 20 }} animate={{ scale: 1, y: 0 }} exit={{ scale: 0.9, y: 20 }} className="glass-panel rounded-2xl p-6 w-full max-w-md">
              <div className="flex items-center justify-between mb-5">
                <h2 className="text-white font-bold text-xl">Invite Team Member</h2>
                <button onClick={() => setShowInvite(false)} className="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center text-white/50 transition-all">
                  <X className="w-4 h-4" />
                </button>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Email Address</label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
                    <input type="email" value={inviteEmail} onChange={e => setInviteEmail(e.target.value)} placeholder="team@company.com" className="input-glass pl-10" />
                  </div>
                </div>
                <div>
                  <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Role</label>
                  <div className="grid grid-cols-3 gap-2">
                    {["admin", "editor", "analyst", "viewer"].map(role => (
                      <button key={role} onClick={() => setInviteRole(role)} className={`py-2 rounded-xl border text-sm font-medium capitalize transition-all ${inviteRole === role ? "bg-electric-blue/20 border-electric-blue/40 text-electric-blue" : "bg-white/5 border-white/10 text-white/50"}`}>
                        {role}
                      </button>
                    ))}
                  </div>
                </div>
                <button
                  onClick={() => { setShowInvite(false); setInviteEmail(""); }}
                  disabled={!inviteEmail.trim()}
                  className="w-full btn-primary py-3 rounded-xl font-semibold text-white flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  <Mail className="w-4 h-4" /> Send Invitation
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
