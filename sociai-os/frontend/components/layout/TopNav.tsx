"use client";

import { useState, useRef, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Bell, Search, User, ChevronDown, Settings, LogOut, Globe, Bot, X, Moon, Sun } from "lucide-react";
import { cn } from "@/lib/utils";
import Link from "next/link";

interface TopNavProps {
  sidebarCollapsed: boolean;
  language: "en" | "ar";
  onLanguageToggle: () => void;
  theme?: "dark" | "light";
  onThemeToggle?: () => void;
}

const MOCK_NOTIFICATIONS = [
  { id: "1", type: "trend", title: "Viral Trend Alert", message: "#AIArt is trending — 340% spike", time: "2m ago", read: false, icon: "📈" },
  { id: "2", type: "agent", title: "Content Generator Done", message: "5 posts created for Instagram", time: "8m ago", read: false, icon: "✨" },
  { id: "3", type: "comment", title: "Comment Spike", message: "Your last reel got 142 new comments", time: "15m ago", read: false, icon: "💬" },
  { id: "4", type: "publish", title: "Post Published", message: "LinkedIn article published successfully", time: "1h ago", read: true, icon: "✅" },
  { id: "5", type: "milestone", title: "Milestone Reached!", message: "10K followers on Instagram 🎉", time: "2h ago", read: true, icon: "🏆" },
];

export function TopNav({ sidebarCollapsed, language, onLanguageToggle, theme = "dark", onThemeToggle }: TopNavProps) {
  const [notifOpen, setNotifOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [notifications, setNotifications] = useState(MOCK_NOTIFICATIONS);
  const notifRef = useRef<HTMLDivElement>(null);
  const userRef = useRef<HTMLDivElement>(null);

  const unread = notifications.filter(n => !n.read).length;

  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (notifRef.current && !notifRef.current.contains(e.target as Node)) setNotifOpen(false);
      if (userRef.current && !userRef.current.contains(e.target as Node)) setUserMenuOpen(false);
    };
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  const markAllRead = () => setNotifications(n => n.map(item => ({ ...item, read: true })));

  return (
    <header
      className="fixed top-0 right-0 z-40 h-16 flex items-center px-6 gap-4 transition-all duration-300"
      style={{
        left: sidebarCollapsed ? 72 : 256,
        background: "rgba(10,11,26,0.92)",
        backdropFilter: "blur(20px)",
        borderBottom: "1px solid rgba(255,255,255,0.06)",
      }}
    >
      {/* Search */}
      <div className="flex-1 max-w-xl">
        <AnimatePresence mode="wait">
          {searchOpen ? (
            <motion.div
              key="search-open"
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="relative"
            >
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-electric-blue" />
              <input
                autoFocus
                type="text"
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                placeholder="Search posts, campaigns, trends..."
                className="w-full bg-white/8 border border-electric-blue/30 text-white rounded-xl pl-9 pr-9 py-2 text-sm outline-none focus:border-electric-blue/60 focus:shadow-neon-blue transition-all"
              />
              <button onClick={() => { setSearchOpen(false); setSearchQuery(""); }} className="absolute right-3 top-1/2 -translate-y-1/2 text-white/30 hover:text-white/60">
                <X className="w-4 h-4" />
              </button>
            </motion.div>
          ) : (
            <motion.button
              key="search-closed"
              onClick={() => setSearchOpen(true)}
              className="flex items-center gap-2 bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-white/30 hover:text-white/60 hover:bg-white/8 transition-all text-sm group"
            >
              <Search className="w-4 h-4 group-hover:text-electric-blue transition-colors" />
              <span className="hidden sm:inline">Search anything...</span>
              <kbd className="hidden sm:inline ml-auto text-xs bg-white/5 border border-white/10 rounded px-1.5 py-0.5">⌘K</kbd>
            </motion.button>
          )}
        </AnimatePresence>
      </div>

      <div className="flex items-center gap-2">
        {/* Language toggle */}
        <motion.button
          onClick={onLanguageToggle}
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all"
        >
          <Globe className="w-4 h-4 text-white/50" />
          <span className="text-white/60 text-sm font-medium">{language.toUpperCase()}</span>
        </motion.button>

        {/* Theme toggle */}
        {onThemeToggle && (
          <motion.button
            onClick={onThemeToggle}
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 flex items-center justify-center transition-all"
          >
            {theme === "dark" ? <Sun className="w-4 h-4 text-white/50" /> : <Moon className="w-4 h-4 text-white/50" />}
          </motion.button>
        )}

        {/* AI Assistant */}
        <motion.button
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-electric-blue/10 border border-electric-blue/25 hover:bg-electric-blue/20 transition-all"
        >
          <Bot className="w-4 h-4 text-electric-blue" />
          <span className="text-electric-blue text-sm font-medium hidden sm:inline">Ask AI</span>
        </motion.button>

        {/* Notifications */}
        <div ref={notifRef} className="relative">
          <motion.button
            onClick={() => setNotifOpen(!notifOpen)}
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 flex items-center justify-center transition-all relative"
          >
            <Bell className="w-4 h-4 text-white/60" />
            {unread > 0 && (
              <motion.span
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-white text-xs flex items-center justify-center font-bold border border-[#0A0B1A]"
              >
                {unread}
              </motion.span>
            )}
          </motion.button>

          <AnimatePresence>
            {notifOpen && (
              <motion.div
                initial={{ opacity: 0, y: 8, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: 8, scale: 0.95 }}
                transition={{ duration: 0.15 }}
                className="absolute right-0 top-12 w-80 glass-panel-dark rounded-2xl border border-white/10 shadow-glass-lg overflow-hidden z-50"
              >
                <div className="flex items-center justify-between p-4 border-b border-white/8">
                  <h3 className="text-white font-semibold text-sm">Notifications</h3>
                  {unread > 0 && (
                    <button onClick={markAllRead} className="text-electric-blue text-xs hover:underline">Mark all read</button>
                  )}
                </div>
                <div className="max-h-80 overflow-y-auto scrollbar-thin">
                  {notifications.map(n => (
                    <div
                      key={n.id}
                      className={cn(
                        "flex gap-3 px-4 py-3 hover:bg-white/5 transition-colors border-b border-white/5 last:border-0",
                        !n.read && "bg-electric-blue/5"
                      )}
                    >
                      <span className="text-xl flex-shrink-0">{n.icon}</span>
                      <div className="flex-1 min-w-0">
                        <p className={cn("text-sm font-medium", n.read ? "text-white/60" : "text-white")}>{n.title}</p>
                        <p className="text-white/40 text-xs mt-0.5 truncate">{n.message}</p>
                        <p className="text-white/25 text-xs mt-1">{n.time}</p>
                      </div>
                      {!n.read && <div className="w-2 h-2 rounded-full bg-electric-blue flex-shrink-0 mt-1" />}
                    </div>
                  ))}
                </div>
                <div className="p-3 border-t border-white/8 text-center">
                  <Link href="/notifications" className="text-electric-blue text-sm hover:underline" onClick={() => setNotifOpen(false)}>
                    View all notifications
                  </Link>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        {/* User Menu */}
        <div ref={userRef} className="relative">
          <motion.button
            onClick={() => setUserMenuOpen(!userMenuOpen)}
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
            className="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all"
          >
            <div className="w-6 h-6 rounded-full bg-gradient-to-br from-electric-blue to-neon-purple flex items-center justify-center text-white text-xs font-bold">
              JD
            </div>
            <span className="text-white/70 text-sm font-medium hidden sm:inline">John Doe</span>
            <ChevronDown className={cn("w-3.5 h-3.5 text-white/40 transition-transform", userMenuOpen && "rotate-180")} />
          </motion.button>

          <AnimatePresence>
            {userMenuOpen && (
              <motion.div
                initial={{ opacity: 0, y: 8, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: 8, scale: 0.95 }}
                transition={{ duration: 0.15 }}
                className="absolute right-0 top-12 w-52 glass-panel-dark rounded-xl border border-white/10 shadow-glass-lg overflow-hidden z-50"
              >
                <div className="p-3 border-b border-white/8">
                  <p className="text-white text-sm font-semibold">John Doe</p>
                  <p className="text-white/40 text-xs">john@acme.com</p>
                  <span className="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-electric-blue/15 text-electric-blue border border-electric-blue/25">Pro Plan</span>
                </div>
                <div className="py-1">
                  {[
                    { label: "Profile", icon: User, href: "/settings" },
                    { label: "Settings", icon: Settings, href: "/settings" },
                  ].map(item => (
                    <Link key={item.label} href={item.href} onClick={() => setUserMenuOpen(false)}>
                      <div className="flex items-center gap-2.5 px-3 py-2.5 hover:bg-white/8 text-white/60 hover:text-white transition-all text-sm">
                        <item.icon className="w-4 h-4" />
                        {item.label}
                      </div>
                    </Link>
                  ))}
                  <button className="w-full flex items-center gap-2.5 px-3 py-2.5 hover:bg-red-500/10 text-red-400 hover:text-red-300 transition-all text-sm">
                    <LogOut className="w-4 h-4" />
                    Sign Out
                  </button>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </header>
  );
}

export default TopNav;
