"use client";

import { useState } from "react";
import { Sidebar } from "@/components/layout/Sidebar";
import { TopNav } from "@/components/layout/TopNav";

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [language, setLanguage] = useState<"en" | "ar">("en");

  return (
    <div className="min-h-screen bg-[#0A0B1A]" dir={language === "ar" ? "rtl" : "ltr"}>
      <Sidebar
        collapsed={sidebarCollapsed}
        onToggle={() => setSidebarCollapsed(c => !c)}
        language={language}
      />
      <TopNav
        sidebarCollapsed={sidebarCollapsed}
        language={language}
        onLanguageToggle={() => setLanguage(l => l === "en" ? "ar" : "en")}
      />
      <main
        className="transition-all duration-300 pt-16 min-h-screen"
        style={{ marginLeft: sidebarCollapsed ? 72 : 256 }}
      >
        <div className="p-6 max-w-[1600px] mx-auto">
          {children}
        </div>
      </main>
    </div>
  );
}
