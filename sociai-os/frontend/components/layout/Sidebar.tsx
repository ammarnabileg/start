"use client";

import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard, Target, FileText, PenTool, BarChart2, Megaphone,
  MessageSquare, TrendingUp, Bot, Users, Settings, ChevronLeft,
  ChevronRight, Zap, Bell
} from "lucide-react";
import { cn } from "@/lib/utils";

const NAV_ITEMS = [
  { href: "/dashboard", label: "Dashboard", labelAr: "لوحة التحكم", icon: LayoutDashboard, badge: null },
  { href: "/strategy", label: "Strategy", labelAr: "الاستراتيجية", icon: Target, badge: null },
  { href: "/content", label: "Content", labelAr: "المحتوى", icon: FileText, badge: "12" },
  { href: "/copywriting", label: "Copywriting", labelAr: "كتابة النصوص", icon: PenTool, badge: null },
  { href: "/analytics", label: "Analytics", labelAr: "التحليلات", icon: BarChart2, badge: null },
  { href: "/campaigns", label: "Campaigns", labelAr: "الحملات", icon: Megaphone, badge: "3" },
  { href: "/community", label: "Community", labelAr: "المجتمع", icon: MessageSquare, badge: "47" },
  { href: "/trends", label: "Trends", labelAr: "الاتجاهات", icon: TrendingUp, badge: null },
  { href: "/agents", label: "AI Agents", labelAr: "وكلاء الذكاء", icon: Bot, badge: null },
  { href: "/team", label: "Team", labelAr: "الفريق", icon: Users, badge: null },
];

interface SidebarProps {
  collapsed: boolean;
  onToggle: () => void;
  language?: "en" | "ar";
}

export function Sidebar({ collapsed, onToggle, language = "en" }: SidebarProps) {
  const pathname = usePathname();

  return (
    <motion.aside
      animate={{ width: collapsed ? 72 : 256 }}
      transition={{ duration: 0.3, ease: "easeInOut" }}
      className="fixed left-0 top-0 bottom-0 z-50 flex flex-col overflow-hidden"
      style={{
        background: "rgba(10,11,26,0.95)",
        backdropFilter: "blur(20px)",
        borderRight: "1px solid rgba(255,255,255,0.08)",
      }}
    >
      {/* Logo */}
      <div className="flex items-center h-16 px-4 flex-shrink-0 border-b border-white/5">
        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-electric-blue to-neon-purple flex items-center justify-center flex-shrink-0 shadow-neon-blue">
          <Zap className="w-5 h-5 text-white" />
        </div>
        <AnimatePresence>
          {!collapsed && (
            <motion.div
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: -10 }}
              transition={{ duration: 0.2 }}
              className="ml-3 overflow-hidden"
            >
              <span className="text-white font-bold text-lg whitespace-nowrap text-gradient">SociAI OS</span>
            </motion.div>
          )}
        </AnimatePresence>
        <div className="flex-1" />
        <motion.button
          onClick={onToggle}
          whileHover={{ scale: 1.1 }}
          whileTap={{ scale: 0.9 }}
          className="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 flex items-center justify-center text-white/40 hover:text-white/70 transition-all flex-shrink-0"
        >
          {collapsed ? <ChevronRight className="w-3.5 h-3.5" /> : <ChevronLeft className="w-3.5 h-3.5" />}
        </motion.button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto overflow-x-hidden py-4 scrollbar-thin">
        <div className="px-3 space-y-1">
          {NAV_ITEMS.map((item) => {
            const Icon = item.icon;
            const isActive = pathname === item.href || pathname.startsWith(item.href + "/");
            const label = language === "ar" ? item.labelAr : item.label;

            return (
              <Link key={item.href} href={item.href}>
                <motion.div
                  whileHover={{ x: collapsed ? 0 : 2 }}
                  className={cn(
                    "flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 relative group",
                    isActive
                      ? "bg-electric-blue/15 text-electric-blue border border-electric-blue/25"
                      : "text-white/50 hover:text-white/80 hover:bg-white/5 border border-transparent"
                  )}
                >
                  {/* Active indicator */}
                  {isActive && (
                    <motion.div
                      layoutId="activeIndicator"
                      className="absolute inset-0 rounded-xl bg-electric-blue/10"
                      transition={{ type: "spring", bounce: 0.2 }}
                    />
                  )}

                  <div className="relative z-10 flex-shrink-0">
                    <Icon className={cn("w-5 h-5 transition-all", isActive && "text-electric-blue")} />
                  </div>

                  <AnimatePresence>
                    {!collapsed && (
                      <motion.span
                        initial={{ opacity: 0, width: 0 }}
                        animate={{ opacity: 1, width: "auto" }}
                        exit={{ opacity: 0, width: 0 }}
                        transition={{ duration: 0.2 }}
                        className={cn(
                          "text-sm font-medium whitespace-nowrap overflow-hidden relative z-10",
                          language === "ar" && "font-cairo"
                        )}
                      >
                        {label}
                      </motion.span>
                    )}
                  </AnimatePresence>

                  {/* Badge */}
                  {item.badge && !collapsed && (
                    <motion.span
                      initial={{ opacity: 0, scale: 0 }}
                      animate={{ opacity: 1, scale: 1 }}
                      className="ml-auto bg-electric-blue/20 text-electric-blue text-xs font-bold px-1.5 py-0.5 rounded-full border border-electric-blue/30 relative z-10"
                    >
                      {item.badge}
                    </motion.span>
                  )}

                  {/* Tooltip when collapsed */}
                  {collapsed && (
                    <div className="absolute left-full ml-3 px-2.5 py-1.5 bg-[#13152E] border border-white/10 rounded-lg text-white text-xs font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-all z-50 shadow-glass">
                      {label}
                      {item.badge && <span className="ml-1.5 text-electric-blue">({item.badge})</span>}
                    </div>
                  )}
                </motion.div>
              </Link>
            );
          })}
        </div>
      </nav>

      {/* Bottom: Settings */}
      <div className="p-3 border-t border-white/5">
        <Link href="/settings">
          <motion.div
            whileHover={{ x: collapsed ? 0 : 2 }}
            className={cn(
              "flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group",
              pathname === "/settings"
                ? "bg-electric-blue/15 text-electric-blue border border-electric-blue/25"
                : "text-white/50 hover:text-white/80 hover:bg-white/5 border border-transparent"
            )}
          >
            <Settings className="w-5 h-5 flex-shrink-0" />
            <AnimatePresence>
              {!collapsed && (
                <motion.span
                  initial={{ opacity: 0, width: 0 }}
                  animate={{ opacity: 1, width: "auto" }}
                  exit={{ opacity: 0, width: 0 }}
                  className="text-sm font-medium whitespace-nowrap overflow-hidden"
                >
                  {language === "ar" ? "الإعدادات" : "Settings"}
                </motion.span>
              )}
            </AnimatePresence>
            {collapsed && (
              <div className="absolute left-full ml-3 px-2.5 py-1.5 bg-[#13152E] border border-white/10 rounded-lg text-white text-xs font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-all z-50">
                Settings
              </div>
            )}
          </motion.div>
        </Link>

        {/* User compact */}
        {!collapsed && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mt-3 flex items-center gap-2 px-2 py-2 rounded-xl bg-white/5 border border-white/8"
          >
            <div className="w-7 h-7 rounded-full bg-gradient-to-br from-electric-blue to-neon-purple flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
              JD
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-white text-xs font-semibold truncate">John Doe</p>
              <p className="text-white/30 text-xs truncate">Pro Plan</p>
            </div>
            <Bell className="w-4 h-4 text-white/30 flex-shrink-0" />
          </motion.div>
        )}
      </div>
    </motion.aside>
  );
}

export default Sidebar;
