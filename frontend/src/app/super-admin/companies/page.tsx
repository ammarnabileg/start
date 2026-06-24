"use client";

import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { superAdminApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Building2, Plus, Search, UserCog } from "lucide-react";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";
import { AuthProvider } from "@/contexts/AuthContext";
import Link from "next/link";
import { Zap } from "lucide-react";

interface Tenant {
  id: number; name: string; slug: string; domain?: string;
  status: string; plan: string; users_count: number; created_at: string;
}

function CompaniesContent() {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [showCreate, setShowCreate] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [form, setForm] = useState({ name: "", slug: "", email: "", admin_name: "", admin_password: "", plan: "starter" });

  useEffect(() => { loadTenants(); }, [search]);

  const loadTenants = async () => {
    try {
      const res = await superAdminApi.tenants({ search });
      setTenants(res.data.data || res.data);
    } catch { toast.error("خطأ في التحميل"); }
    finally { setLoading(false); }
  };

  const createTenant = async () => {
    setCreateLoading(true);
    try {
      await superAdminApi.createTenant(form);
      toast.success("تم إنشاء الشركة");
      setShowCreate(false);
      loadTenants();
    } catch { toast.error("خطأ في الإنشاء"); }
    finally { setCreateLoading(false); }
  };

  const toggleStatus = async (t: Tenant) => {
    try {
      await superAdminApi.updateTenant(t.id, { status: t.status === "active" ? "suspended" : "active" });
      setTenants((p) => p.map((x) => x.id === t.id ? { ...x, status: x.status === "active" ? "suspended" : "active" } : x));
      toast.success("تم تحديث الحالة");
    } catch { toast.error("خطأ في التحديث"); }
  };

  const impersonate = async (tenantId: number) => {
    try {
      const res = await superAdminApi.impersonate(tenantId);
      window.open(`/dashboard?token=${res.data.token}`, "_blank");
    } catch { toast.error("خطأ في الانتحال"); }
  };

  return (
    <div className="min-h-screen bg-[#0f0c29]" dir="rtl">
      <header className="h-14 bg-black/30 border-b border-white/10 flex items-center justify-between px-6">
        <div className="flex items-center gap-3">
          <Link href="/super-admin/dashboard" className="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center">
            <Zap className="w-4 h-4 text-white" />
          </Link>
          <span className="text-white font-bold">الشركات</span>
        </div>
        <Button icon={<Plus className="w-4 h-4" />} onClick={() => setShowCreate(true)}>شركة جديدة</Button>
      </header>

      <main className="p-6 space-y-5">
        <div className="flex gap-3">
          <div className="relative">
            <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" />
            <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="بحث..."
              className="pr-9 pl-4 py-2 text-sm bg-white/10 border border-white/10 rounded-lg text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500" />
          </div>
        </div>

        <div className="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-white/10">
                {["الشركة", "الخطة", "المستخدمون", "الحالة", "إنشاء", "إجراءات"].map((h) => (
                  <th key={h} className="px-4 py-3 text-right text-xs font-semibold text-white/40">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {loading ? [...Array(5)].map((_, i) => (
                <tr key={i}><td colSpan={6} className="px-4 py-3"><div className="h-8 bg-white/10 rounded animate-pulse" /></td></tr>
              )) : tenants.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-16 text-center text-white/30 text-sm">لا توجد شركات</td></tr>
              ) : tenants.map((t) => (
                <tr key={t.id} className="hover:bg-white/5 transition-colors">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-violet-600/30 rounded-lg flex items-center justify-center text-violet-400 font-bold text-xs">{t.name.charAt(0)}</div>
                      <div>
                        <p className="text-white text-sm font-medium">{t.name}</p>
                        <p className="text-white/40 text-xs">{t.slug}{t.domain ? ` • ${t.domain}` : ""}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3"><span className="text-xs text-violet-300 bg-violet-500/20 px-2 py-1 rounded-full">{t.plan}</span></td>
                  <td className="px-4 py-3 text-white/60 text-sm">{t.users_count}</td>
                  <td className="px-4 py-3">
                    <button onClick={() => toggleStatus(t)}
                      className={`text-xs px-2 py-0.5 rounded-full transition-colors ${t.status === "active" ? "bg-green-500/20 text-green-400 hover:bg-red-500/20 hover:text-red-400" : "bg-red-500/20 text-red-400 hover:bg-green-500/20 hover:text-green-400"}`}>
                      {t.status === "active" ? "نشطة" : "موقوفة"}
                    </button>
                  </td>
                  <td className="px-4 py-3 text-white/40 text-xs">{formatDate(t.created_at)}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <button onClick={() => impersonate(t.id)}
                        className="text-xs flex items-center gap-1 text-violet-400 hover:text-violet-300 transition-colors">
                        <UserCog className="w-3 h-3" /> انتحال
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </main>

      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="إنشاء شركة جديدة" size="lg"
        footer={<><Button variant="secondary" onClick={() => setShowCreate(false)}>إلغاء</Button><Button loading={createLoading} onClick={createTenant}>إنشاء</Button></>}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <Input label="اسم الشركة" value={form.name} onChange={(e) => setForm(p => ({ ...p, name: e.target.value }))} placeholder="شركة X للتقنية" />
            <Input label="Slug" value={form.slug} onChange={(e) => setForm(p => ({ ...p, slug: e.target.value }))} placeholder="company-x" />
          </div>
          <Input label="بريد المسؤول" type="email" value={form.email} onChange={(e) => setForm(p => ({ ...p, email: e.target.value }))} placeholder="admin@company.com" />
          <div className="grid grid-cols-2 gap-4">
            <Input label="اسم المسؤول" value={form.admin_name} onChange={(e) => setForm(p => ({ ...p, admin_name: e.target.value }))} placeholder="محمد أحمد" />
            <Input label="كلمة مرور المسؤول" type="password" value={form.admin_password} onChange={(e) => setForm(p => ({ ...p, admin_password: e.target.value }))} placeholder="••••••••" />
          </div>
        </div>
      </Modal>
    </div>
  );
}

export default function CompaniesPage() {
  return <AuthProvider><CompaniesContent /></AuthProvider>;
}
