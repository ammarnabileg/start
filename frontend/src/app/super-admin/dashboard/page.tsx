"use client";

import { StatCard, StatsGrid } from "@/components/ui/Stats";
import { superAdminApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Building2, Terminal, TrendingUp, Users, Zap } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";
import { AuthProvider, useAuth } from "@/contexts/AuthContext";

function SuperAdminContent() {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const [data, setData] = useState<any>(null);

  useEffect(() => {
    if (!isLoading && (!user || user.user_type !== "super_admin")) router.push("/login");
  }, [user, isLoading, router]);

  useEffect(() => {
    if (!user || user.user_type !== "super_admin") return;
    superAdminApi.stats().then((res) => setData(res.data)).catch(() => toast.error("خطأ في التحميل"));
  }, [user]);

  if (isLoading || !user) return (
    <div className="min-h-screen bg-[#0f0c29] flex items-center justify-center">
      <div className="w-10 h-10 bg-violet-600 rounded-xl flex items-center justify-center animate-pulse">
        <Zap className="w-5 h-5 text-white" />
      </div>
    </div>
  );

  return (
    <div className="min-h-screen bg-[#0f0c29]" dir="rtl">
      {/* Header */}
      <header className="h-14 bg-black/30 border-b border-white/10 flex items-center justify-between px-6">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center">
            <Zap className="w-4 h-4 text-white" />
          </div>
          <span className="text-white font-bold">AI Recruit — Super Admin</span>
        </div>
        <div className="flex items-center gap-4">
          <Link href="/super-admin/companies" className="text-white/60 hover:text-white text-sm transition-colors">الشركات</Link>
          <Link href="/super-admin/terminal" className="text-white/60 hover:text-white text-sm transition-colors">Terminal</Link>
          <Link href="/super-admin/settings" className="text-white/60 hover:text-white text-sm transition-colors">الإعدادات</Link>
          <div className="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center text-white text-xs font-bold">{user.name?.charAt(0)}</div>
        </div>
      </header>

      <main className="p-6 space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-white">لوحة التحكم العليا</h1>
          <p className="text-white/40 text-sm mt-0.5">نظرة شاملة على المنصة</p>
        </div>

        {data && (
          <>
            <div className="grid grid-cols-4 gap-4">
              {[
                { label: "إجمالي الشركات", value: data.stats?.total_tenants ?? 0, icon: <Building2 className="w-5 h-5" />, color: "purple" as const },
                { label: "الشركات النشطة", value: data.stats?.active_tenants ?? 0, icon: <TrendingUp className="w-5 h-5" />, color: "green" as const },
                { label: "إجمالي المستخدمين", value: data.stats?.total_users ?? 0, icon: <Users className="w-5 h-5" />, color: "blue" as const },
                { label: "رموز AI اليوم", value: data.stats?.tokens_today ?? 0, icon: <Zap className="w-5 h-5" />, color: "orange" as const },
              ].map((s) => (
                <div key={s.label} className="bg-white/5 border border-white/10 rounded-xl p-4">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-white/40 text-xs">{s.label}</span>
                    <div className="w-7 h-7 bg-violet-500/20 rounded-lg flex items-center justify-center text-violet-400">{s.icon}</div>
                  </div>
                  <p className="text-2xl font-bold text-white">{s.value.toLocaleString()}</p>
                </div>
              ))}
            </div>

            {/* Recent tenants */}
            <div className="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
              <div className="flex items-center justify-between px-5 py-4 border-b border-white/10">
                <h2 className="font-bold text-white text-sm">آخر الشركات</h2>
                <Link href="/super-admin/companies" className="text-xs text-violet-400 hover:text-violet-300">عرض الكل</Link>
              </div>
              <div className="divide-y divide-white/5">
                {data.recent_tenants?.map((t: any) => (
                  <div key={t.id} className="flex items-center justify-between px-5 py-3.5">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-violet-600/30 rounded-lg flex items-center justify-center text-violet-400 font-bold text-xs">{t.name.charAt(0)}</div>
                      <div>
                        <p className="text-white text-sm font-medium">{t.name}</p>
                        <p className="text-white/40 text-xs">{t.slug}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className={`text-xs px-2 py-0.5 rounded-full ${t.status === "active" ? "bg-green-500/20 text-green-400" : "bg-gray-500/20 text-gray-400"}`}>
                        {t.status === "active" ? "نشطة" : "موقوفة"}
                      </span>
                      <Link href={`/super-admin/companies/${t.id}`} className="text-xs text-violet-400 hover:text-violet-300">إدارة</Link>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </>
        )}

        {!data && (
          <div className="grid grid-cols-4 gap-4">
            {[...Array(4)].map((_, i) => <div key={i} className="h-24 bg-white/5 rounded-xl animate-pulse" />)}
          </div>
        )}
      </main>
    </div>
  );
}

export default function SuperAdminDashboard() {
  return (
    <AuthProvider><SuperAdminContent /></AuthProvider>
  );
}
