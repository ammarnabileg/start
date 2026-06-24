"use client";

import { AuthProvider, useAuth } from "@/contexts/AuthContext";
import { Bell, Menu, Search } from "lucide-react";
import { useRouter } from "next/navigation";
import React, { useEffect } from "react";
import { Sidebar } from "./Sidebar";

function DashboardContent({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !user) router.push("/login");
  }, [user, isLoading, router]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-[#f9fafb]">
        <div className="flex flex-col items-center gap-3">
          <div className="w-10 h-10 bg-violet-600 rounded-xl flex items-center justify-center animate-pulse">
            <span className="text-white font-bold text-lg">AI</span>
          </div>
          <div className="text-sm text-gray-500">جاري التحميل...</div>
        </div>
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="min-h-screen bg-[#f3f4f6] flex" dir="rtl">
      <Sidebar />
      <div className="flex-1 mr-64 flex flex-col min-h-screen">
        {/* Top Header */}
        <header className="h-16 bg-white border-b border-gray-200 sticky top-0 z-20 flex items-center justify-between px-6">
          <div className="flex items-center gap-3">
            <div className="relative">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                type="text"
                placeholder="بحث..."
                className="pr-9 pl-4 py-2 text-sm bg-gray-100 border-0 rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 w-64"
              />
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button className="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors">
              <Bell className="w-5 h-5" />
              <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-violet-600 rounded-full" />
            </button>
            <div className="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center text-white text-xs font-bold cursor-pointer">
              {user.name?.charAt(0).toUpperCase()}
            </div>
          </div>
        </header>

        {/* Main Content */}
        <main className="flex-1 p-6 animate-fade-in">
          {children}
        </main>
      </div>
    </div>
  );
}

export function DashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <AuthProvider>
      <DashboardContent>{children}</DashboardContent>
    </AuthProvider>
  );
}
