"use client";

import { useAuth } from "@/contexts/AuthContext";
import { cn } from "@/lib/utils";
import {
  BarChart3, Briefcase, Building2, ChevronDown, ChevronRight,
  Grid3x3, LayoutDashboard, LogOut, MessageSquare, Settings,
  Star, Users, Video, Zap, FileText, Target, Award, Bell, Search
} from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import React, { useState } from "react";

interface NavItem {
  label: string;
  labelAr: string;
  href?: string;
  icon: React.ReactNode;
  children?: NavItem[];
  badge?: string | number;
}

function hrNavItems(): NavItem[] {
  return [
    { label: "Dashboard", labelAr: "الرئيسية", href: "/dashboard", icon: <LayoutDashboard className="w-4 h-4" /> },
    { label: "Jobs", labelAr: "الوظائف", href: "/jobs", icon: <Briefcase className="w-4 h-4" /> },
    { label: "Pipeline", labelAr: "خط المرشحين", href: "/pipeline", icon: <Grid3x3 className="w-4 h-4" /> },
    { label: "AI Interviews", labelAr: "مقابلات الذكاء الاصطناعي", href: "/interviews", icon: <MessageSquare className="w-4 h-4" /> },
    { label: "Candidates", labelAr: "المرشحون", href: "/candidates", icon: <Users className="w-4 h-4" /> },
    { label: "Human Interviews", labelAr: "المقابلات البشرية", href: "/human-interviews", icon: <Video className="w-4 h-4" /> },
    { label: "Offers", labelAr: "العروض الوظيفية", href: "/offers", icon: <FileText className="w-4 h-4" /> },
    { label: "Talent Pool", labelAr: "بنك المواهب", href: "/talent-pool", icon: <Star className="w-4 h-4" /> },
    { label: "Avatars", labelAr: "المحاورون", href: "/avatars", icon: <Award className="w-4 h-4" /> },
    { label: "AI Analytics", labelAr: "تحليلات الذكاء", href: "/ai-analytics", icon: <BarChart3 className="w-4 h-4" /> },
    { label: "Users", labelAr: "المستخدمون", href: "/users", icon: <Users className="w-4 h-4" /> },
    { label: "Settings", labelAr: "الإعدادات", href: "/settings", icon: <Settings className="w-4 h-4" /> },
  ];
}

function superAdminNavItems(): NavItem[] {
  return [
    { label: "Dashboard", labelAr: "الرئيسية", href: "/super-admin/dashboard", icon: <LayoutDashboard className="w-4 h-4" /> },
    { label: "Companies", labelAr: "الشركات", href: "/super-admin/companies", icon: <Building2 className="w-4 h-4" /> },
    { label: "Global Analytics", labelAr: "الإحصاءات العامة", href: "/super-admin/analytics", icon: <BarChart3 className="w-4 h-4" /> },
    { label: "AI Usage", labelAr: "استخدام الذكاء", href: "/super-admin/ai-usage", icon: <Zap className="w-4 h-4" /> },
    { label: "Terminal", labelAr: "طرفية النظام", href: "/super-admin/terminal", icon: <Settings className="w-4 h-4" /> },
    { label: "Settings", labelAr: "الإعدادات", href: "/super-admin/settings", icon: <Settings className="w-4 h-4" /> },
  ];
}

interface NavLinkProps { item: NavItem; locale?: "ar" | "en"; }

function NavLink({ item, locale = "ar" }: NavLinkProps) {
  const pathname = usePathname();
  const isActive = item.href ? pathname === item.href || pathname.startsWith(item.href + "/") : false;
  const [open, setOpen] = useState(false);

  if (item.children) {
    return (
      <div>
        <button onClick={() => setOpen(!open)} className={cn("nav-item w-full", isActive && "active")}>
          {item.icon}
          <span className="flex-1 text-right">{locale === "ar" ? item.labelAr : item.label}</span>
          <ChevronDown className={cn("w-4 h-4 transition-transform", open && "rotate-180")} />
        </button>
        {open && <div className="ml-4 mt-1 space-y-1 border-r-2 border-violet-200 pl-3 mr-3">{item.children.map((c, i) => <NavLink key={i} item={c} locale={locale} />)}</div>}
      </div>
    );
  }

  return (
    <Link href={item.href!} className={cn("nav-item", isActive && "active")}>
      {item.icon}
      <span>{locale === "ar" ? item.labelAr : item.label}</span>
      {item.badge && <span className="mr-auto text-xs bg-violet-600 text-white rounded-full px-1.5 py-0.5 min-w-[18px] text-center">{item.badge}</span>}
      {!item.badge && isActive && <ChevronRight className="w-3 h-3 mr-auto text-violet-600" />}
    </Link>
  );
}

export function Sidebar() {
  const { user, logout } = useAuth();
  const navItems = user?.user_type === "super_admin" ? superAdminNavItems() : hrNavItems();
  const locale: "ar" | "en" = "ar";

  const sectionBreaks: Record<number, string> = {
    1: locale === "ar" ? "إدارة التوظيف" : "RECRUITMENT",
    5: locale === "ar" ? "الأدوات" : "TOOLS",
    8: locale === "ar" ? "الإعدادات" : "SETTINGS",
  };

  return (
    <aside className="w-64 h-full bg-white border-l border-gray-200 flex flex-col fixed right-0 top-0 z-30">
      {/* Logo */}
      <div className="h-16 flex items-center px-5 border-b border-gray-200">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center">
            <Zap className="w-4 h-4 text-white" />
          </div>
          <div>
            <p className="text-sm font-bold text-gray-900">{user?.tenant?.name || "AI Recruit"}</p>
            <p className="text-xs text-gray-400">منصة التوظيف</p>
          </div>
        </div>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
        {navItems.map((item, index) => (
          <React.Fragment key={index}>
            {sectionBreaks[index] && (
              <p className="px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                {sectionBreaks[index]}
              </p>
            )}
            <NavLink item={item} locale={locale} />
          </React.Fragment>
        ))}
      </nav>

      {/* User */}
      <div className="border-t border-gray-200 p-3">
        <div className="flex items-center gap-3 px-2 py-2">
          <div className="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
            {user?.name?.charAt(0).toUpperCase()}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-gray-900 truncate">{user?.name}</p>
            <p className="text-xs text-gray-400 truncate">{user?.email}</p>
          </div>
          <button onClick={logout} className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="تسجيل الخروج">
            <LogOut className="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>
  );
}
