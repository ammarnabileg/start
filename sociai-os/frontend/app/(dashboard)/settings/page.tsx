"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { User, Shield, Link, Bell, Key, CreditCard, Check, Copy, Eye, EyeOff, Plus, Trash2, RefreshCw, QrCode } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { PlatformId } from "@/lib/types";

const SECTIONS = [
  { id: "account", label: "Account", icon: User },
  { id: "security", label: "Security & 2FA", icon: Shield },
  { id: "platforms", label: "Connected Platforms", icon: Link },
  { id: "notifications", label: "Notifications", icon: Bell },
  { id: "api", label: "API Keys", icon: Key },
  { id: "billing", label: "Billing", icon: CreditCard },
];

const CONNECTED_PLATFORMS: { id: PlatformId; connected: boolean; username?: string; followers?: number }[] = [
  { id: "instagram", connected: true, username: "@brand_official", followers: 54800 },
  { id: "facebook", connected: true, username: "Brand Page", followers: 28300 },
  { id: "twitter", connected: true, username: "@brand_x", followers: 12900 },
  { id: "tiktok", connected: true, username: "@brandtiktok", followers: 89200 },
  { id: "youtube", connected: true, username: "Brand Channel", followers: 34100 },
  { id: "linkedin", connected: false },
  { id: "snapchat", connected: false },
  { id: "pinterest", connected: false },
  { id: "telegram", connected: false },
  { id: "whatsapp", connected: false },
  { id: "threads", connected: false },
];

const API_KEYS = [
  { id: "k1", name: "Production API", maskedKey: "sk-••••••••••••••••KXYZ", scopes: ["read", "write", "publish"], lastUsed: "2h ago", createdAt: "Jan 2024" },
  { id: "k2", name: "Development API", maskedKey: "sk-••••••••••••••••ABCD", scopes: ["read"], lastUsed: "5d ago", createdAt: "Mar 2024" },
];

export default function SettingsPage() {
  const [activeSection, setActiveSection] = useState("account");
  const [name, setName] = useState("John Doe");
  const [email, setEmail] = useState("john@acme.com");
  const [twoFAEnabled, setTwoFAEnabled] = useState(false);
  const [showQR, setShowQR] = useState(false);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [notifications, setNotifications] = useState({
    email: true, push: true, sms: false,
    trendAlerts: true, contentPublished: true, agentComplete: true, billing: true,
  });

  const handleCopy = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(id);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  return (
    <div className="space-y-6">
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
        <h1 className="text-2xl font-bold text-white">Settings</h1>
        <p className="text-white/40 text-sm">Manage your account and preferences</p>
      </motion.div>

      <div className="flex gap-6">
        {/* Sidebar */}
        <div className="w-56 flex-shrink-0">
          <div className="space-y-1 sticky top-20">
            {SECTIONS.map(s => {
              const Icon = s.icon;
              return (
                <button
                  key={s.id}
                  onClick={() => setActiveSection(s.id)}
                  className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all text-left ${activeSection === s.id ? "bg-electric-blue/15 text-electric-blue border border-electric-blue/25" : "text-white/50 hover:text-white/80 hover:bg-white/5"}`}
                >
                  <Icon className="w-4 h-4 flex-shrink-0" />
                  <span className="text-sm font-medium">{s.label}</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          <AnimatePresence mode="wait">
            {/* Account */}
            {activeSection === "account" && (
              <motion.div key="account" initial={{ opacity: 0, x: 10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -10 }}>
                <GlassCard padding="md">
                  <h2 className="text-white font-bold text-lg mb-6">Account Profile</h2>
                  <div className="flex items-start gap-6 mb-6">
                    <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-electric-blue to-neon-purple flex items-center justify-center text-white text-3xl font-bold">JD</div>
                    <div>
                      <button className="px-4 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/70 text-sm transition-all">Change Avatar</button>
                      <p className="text-white/30 text-xs mt-2">JPG, PNG up to 2MB</p>
                    </div>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Full Name</label>
                      <input type="text" value={name} onChange={e => setName(e.target.value)} className="input-glass" />
                    </div>
                    <div>
                      <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Email</label>
                      <input type="email" value={email} onChange={e => setEmail(e.target.value)} className="input-glass" />
                    </div>
                    <div>
                      <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Business Name</label>
                      <input type="text" defaultValue="Acme Corporation" className="input-glass" />
                    </div>
                    <div>
                      <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Timezone</label>
                      <select className="input-glass">
                        <option>UTC+3 (Arabia Standard Time)</option>
                        <option>UTC+0 (GMT)</option>
                        <option>UTC-5 (EST)</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Default Language</label>
                      <select className="input-glass">
                        <option>English</option>
                        <option>العربية</option>
                      </select>
                    </div>
                  </div>
                  <div className="flex gap-3 mt-6">
                    <button className="px-6 py-2.5 btn-primary rounded-xl font-semibold text-white text-sm">Save Changes</button>
                    <button className="px-6 py-2.5 bg-white/5 border border-white/10 hover:bg-white/10 rounded-xl text-white/60 text-sm transition-all">Cancel</button>
                  </div>
                </GlassCard>
              </motion.div>
            )}

            {/* Security */}
            {activeSection === "security" && (
              <motion.div key="security" initial={{ opacity: 0, x: 10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -10 }} className="space-y-5">
                <GlassCard padding="md">
                  <h2 className="text-white font-bold text-lg mb-4">Change Password</h2>
                  <div className="space-y-3 max-w-sm">
                    {["Current Password", "New Password", "Confirm Password"].map(label => (
                      <div key={label}>
                        <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">{label}</label>
                        <input type="password" placeholder="••••••••" className="input-glass" />
                      </div>
                    ))}
                    <button className="px-6 py-2.5 btn-primary rounded-xl font-semibold text-white text-sm mt-2">Update Password</button>
                  </div>
                </GlassCard>

                <GlassCard padding="md">
                  <div className="flex items-center justify-between mb-4">
                    <div>
                      <h2 className="text-white font-bold text-lg">Two-Factor Authentication</h2>
                      <p className="text-white/40 text-sm mt-0.5">Add an extra layer of security to your account</p>
                    </div>
                    <div
                      onClick={() => { setTwoFAEnabled(!twoFAEnabled); setShowQR(!twoFAEnabled); }}
                      className={`w-12 h-6 rounded-full transition-all cursor-pointer ${twoFAEnabled ? "bg-electric-blue" : "bg-white/20"}`}
                    >
                      <div className={`w-5 h-5 rounded-full bg-white shadow transition-all m-0.5 ${twoFAEnabled ? "translate-x-6" : "translate-x-0"}`} />
                    </div>
                  </div>

                  <AnimatePresence>
                    {showQR && !twoFAEnabled === false && (
                      <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: "auto" }} exit={{ opacity: 0, height: 0 }}>
                        <div className="flex items-start gap-6 p-4 bg-white/5 rounded-xl border border-white/10">
                          <div className="w-32 h-32 bg-white rounded-xl flex items-center justify-center flex-shrink-0">
                            <QrCode className="w-24 h-24 text-black" />
                          </div>
                          <div>
                            <h3 className="text-white font-semibold mb-2">Scan with your authenticator app</h3>
                            <p className="text-white/40 text-sm mb-3">Use Google Authenticator, Authy, or any TOTP app</p>
                            <div className="bg-white/10 rounded-lg px-3 py-2 font-mono text-white/60 text-sm">ABCD EFGH IJKL MNOP</div>
                            <div className="mt-3">
                              <label className="text-white/60 text-xs block mb-1">Enter 6-digit code</label>
                              <div className="flex gap-2">
                                <input type="text" placeholder="000000" maxLength={6} className="input-glass w-32 text-center text-lg tracking-widest" />
                                <button className="px-4 py-2 btn-primary rounded-xl text-white text-sm font-medium">Verify</button>
                              </div>
                            </div>
                          </div>
                        </div>
                      </motion.div>
                    )}

                    {twoFAEnabled && (
                      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="flex items-center gap-2 text-neon-green text-sm">
                        <Check className="w-4 h-4" /> 2FA is enabled on your account
                      </motion.div>
                    )}
                  </AnimatePresence>
                </GlassCard>
              </motion.div>
            )}

            {/* Platforms */}
            {activeSection === "platforms" && (
              <motion.div key="platforms" initial={{ opacity: 0, x: 10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -10 }}>
                <GlassCard padding="md">
                  <h2 className="text-white font-bold text-lg mb-5">Connected Platforms</h2>
                  <div className="space-y-3">
                    {CONNECTED_PLATFORMS.map(p => (
                      <div key={p.id} className={`flex items-center gap-4 p-3 rounded-xl border transition-all ${p.connected ? "border-white/10 bg-white/3" : "border-white/5 bg-white/[0.02]"}`}>
                        <PlatformIcon platform={p.id} size="md" />
                        <div className="flex-1 min-w-0">
                          {p.connected ? (
                            <>
                              <p className="text-white font-medium text-sm">{p.username}</p>
                              <p className="text-white/40 text-xs">{p.followers?.toLocaleString()} followers</p>
                            </>
                          ) : (
                            <p className="text-white/40 text-sm">Not connected</p>
                          )}
                        </div>
                        {p.connected ? (
                          <div className="flex gap-2">
                            <NeonBadge variant="green" size="sm" dot>Connected</NeonBadge>
                            <button className="px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 text-white/40 text-xs transition-all flex items-center gap-1">
                              <RefreshCw className="w-3 h-3" /> Refresh
                            </button>
                          </div>
                        ) : (
                          <button className="px-3 py-1.5 rounded-xl btn-primary text-white text-xs font-medium">Connect</button>
                        )}
                      </div>
                    ))}
                  </div>
                </GlassCard>
              </motion.div>
            )}

            {/* Notifications */}
            {activeSection === "notifications" && (
              <motion.div key="notifications" initial={{ opacity: 0, x: 10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -10 }}>
                <GlassCard padding="md">
                  <h2 className="text-white font-bold text-lg mb-6">Notification Preferences</h2>
                  <div className="space-y-6">
                    <div>
                      <h3 className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-3">Delivery Channels</h3>
                      <div className="space-y-3">
                        {[
                          { id: "email", label: "Email Notifications", desc: "Get notified via email" },
                          { id: "push", label: "Push Notifications", desc: "Browser push notifications" },
                          { id: "sms", label: "SMS Notifications", desc: "Text message alerts" },
                        ].map(channel => (
                          <div key={channel.id} className="flex items-center justify-between p-3 rounded-xl bg-white/5 border border-white/8">
                            <div>
                              <p className="text-white text-sm font-medium">{channel.label}</p>
                              <p className="text-white/40 text-xs">{channel.desc}</p>
                            </div>
                            <div
                              onClick={() => setNotifications(n => ({ ...n, [channel.id]: !n[channel.id as keyof typeof n] }))}
                              className={`w-11 h-6 rounded-full transition-all cursor-pointer ${notifications[channel.id as keyof typeof notifications] ? "bg-electric-blue" : "bg-white/20"}`}
                            >
                              <div className={`w-5 h-5 rounded-full bg-white shadow transition-all m-0.5 ${notifications[channel.id as keyof typeof notifications] ? "translate-x-5" : "translate-x-0"}`} />
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                    <div>
                      <h3 className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-3">Alert Types</h3>
                      <div className="space-y-2">
                        {[
                          { id: "trendAlerts", label: "Trend Alerts", icon: "📈" },
                          { id: "contentPublished", label: "Content Published", icon: "✅" },
                          { id: "agentComplete", label: "Agent Complete", icon: "🤖" },
                          { id: "billing", label: "Billing Alerts", icon: "💳" },
                        ].map(alert => (
                          <div key={alert.id} className="flex items-center justify-between py-2">
                            <div className="flex items-center gap-2">
                              <span>{alert.icon}</span>
                              <span className="text-white/70 text-sm">{alert.label}</span>
                            </div>
                            <div
                              onClick={() => setNotifications(n => ({ ...n, [alert.id]: !n[alert.id as keyof typeof n] }))}
                              className={`w-9 h-5 rounded-full transition-all cursor-pointer ${notifications[alert.id as keyof typeof notifications] ? "bg-electric-blue" : "bg-white/20"}`}
                            >
                              <div className={`w-4 h-4 rounded-full bg-white shadow transition-all m-0.5 ${notifications[alert.id as keyof typeof notifications] ? "translate-x-4" : "translate-x-0"}`} />
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </GlassCard>
              </motion.div>
            )}

            {/* API Keys */}
            {activeSection === "api" && (
              <motion.div key="api" initial={{ opacity: 0, x: 10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -10 }}>
                <GlassCard padding="md">
                  <div className="flex items-center justify-between mb-5">
                    <h2 className="text-white font-bold text-lg">API Keys</h2>
                    <button className="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white/60 text-sm transition-all">
                      <Plus className="w-4 h-4" /> New Key
                    </button>
                  </div>
                  <div className="space-y-4">
                    {API_KEYS.map(key => (
                      <div key={key.id} className="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div className="flex items-start justify-between mb-3">
                          <div>
                            <h3 className="text-white font-semibold text-sm">{key.name}</h3>
                            <p className="text-white/30 text-xs">Created {key.createdAt} · Last used {key.lastUsed}</p>
                          </div>
                          <button className="w-7 h-7 rounded-lg bg-red-500/10 hover:bg-red-500/20 flex items-center justify-center text-red-400 transition-all">
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                        <div className="flex items-center gap-2 bg-black/30 rounded-lg px-3 py-2 mb-3">
                          <code className="text-white/60 text-sm font-mono flex-1">{key.maskedKey}</code>
                          <button onClick={() => handleCopy(key.maskedKey, key.id)} className="text-white/30 hover:text-white/60 transition-colors">
                            {copiedKey === key.id ? <Check className="w-4 h-4 text-neon-green" /> : <Copy className="w-4 h-4" />}
                          </button>
                        </div>
                        <div className="flex gap-2">
                          {key.scopes.map(scope => (
                            <span key={scope} className="text-xs px-2 py-0.5 rounded-full bg-electric-blue/10 text-electric-blue border border-electric-blue/20">{scope}</span>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </GlassCard>
              </motion.div>
            )}

            {/* Billing */}
            {activeSection === "billing" && (
              <motion.div key="billing" initial={{ opacity: 0, x: 10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -10 }} className="space-y-5">
                <GlassCard padding="md">
                  <h2 className="text-white font-bold text-lg mb-5">Current Plan</h2>
                  <div className="flex items-center justify-between p-4 rounded-xl bg-electric-blue/10 border border-electric-blue/25 mb-4">
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        <h3 className="text-white font-bold text-xl">Pro Plan</h3>
                        <NeonBadge variant="blue">Active</NeonBadge>
                      </div>
                      <p className="text-white/50 text-sm">$99/month · Billed monthly</p>
                      <p className="text-white/30 text-xs mt-1">Next billing: July 30, 2025</p>
                    </div>
                    <div className="text-right">
                      <p className="text-electric-blue text-3xl font-bold">$99</p>
                      <p className="text-white/30 text-sm">/month</p>
                    </div>
                  </div>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                      { label: "Platforms", value: "11 / 11" },
                      { label: "AI Posts", value: "∞" },
                      { label: "Team Members", value: "10" },
                      { label: "Analytics", value: "Advanced" },
                    ].map(feat => (
                      <div key={feat.label} className="text-center p-3 rounded-xl bg-white/5 border border-white/8">
                        <p className="text-white font-bold">{feat.value}</p>
                        <p className="text-white/40 text-xs">{feat.label}</p>
                      </div>
                    ))}
                  </div>
                  <div className="flex gap-3 mt-4">
                    <button className="px-5 py-2.5 btn-primary rounded-xl font-semibold text-white text-sm">Upgrade to Enterprise</button>
                    <button className="px-5 py-2.5 bg-white/5 border border-white/10 hover:bg-white/10 rounded-xl text-white/60 text-sm transition-all">Cancel Plan</button>
                  </div>
                </GlassCard>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </div>
  );
}
