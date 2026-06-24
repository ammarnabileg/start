"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { usersApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Plus, Trash2, UserCircle } from "lucide-react";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface User {
  id: number; name: string; email: string; user_type: string; is_active: boolean;
  roles: Array<{ name: string }>; created_at: string;
}

const ROLES = ["admin", "hr_manager", "recruiter", "hiring_manager", "viewer"];
const ROLE_LABELS: Record<string, string> = {
  admin: "مسؤول", hr_manager: "مدير HR", recruiter: "موظف توظيف",
  hiring_manager: "مدير التوظيف", viewer: "مشاهد",
};

export default function UsersPage() {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [form, setForm] = useState({ name: "", email: "", password: "", role: "recruiter" });

  useEffect(() => { load(); }, []);

  const load = async () => {
    try {
      const res = await usersApi.list();
      setUsers(res.data.data || res.data);
    } catch { toast.error("خطأ في التحميل"); }
    finally { setLoading(false); }
  };

  const createUser = async () => {
    setCreateLoading(true);
    try {
      await usersApi.create(form);
      toast.success("تم إنشاء المستخدم");
      setShowCreate(false);
      setForm({ name: "", email: "", password: "", role: "recruiter" });
      load();
    } catch { toast.error("خطأ في الإنشاء"); }
    finally { setCreateLoading(false); }
  };

  const toggleActive = async (user: User) => {
    try {
      await usersApi.update(user.id, { is_active: !user.is_active });
      setUsers((p) => p.map((u) => u.id === user.id ? { ...u, is_active: !u.is_active } : u));
    } catch { toast.error("خطأ في التحديث"); }
  };

  const deleteUser = async (id: number) => {
    if (!confirm("هل تريد حذف هذا المستخدم؟")) return;
    try {
      await usersApi.delete(id);
      setUsers((p) => p.filter((u) => u.id !== id));
      toast.success("تم الحذف");
    } catch { toast.error("خطأ في الحذف"); }
  };

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">المستخدمون والصلاحيات</h1>
            <p className="text-sm text-gray-500 mt-0.5">{users.length} مستخدم</p>
          </div>
          <Button icon={<Plus className="w-4 h-4" />} onClick={() => setShowCreate(true)}>مستخدم جديد</Button>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 bg-gray-50">
                {["المستخدم", "الدور", "الحالة", "تاريخ الإنشاء", "إجراءات"].map((h) => (
                  <th key={h} className="px-4 py-3 text-right text-xs font-semibold text-gray-500">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? [...Array(3)].map((_, i) => (
                <tr key={i}><td colSpan={5} className="px-4 py-3"><div className="h-8 skeleton rounded" /></td></tr>
              )) : users.map((user) => (
                <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-xs">{user.name.charAt(0)}</div>
                      <div>
                        <p className="text-sm font-semibold text-gray-900">{user.name}</p>
                        <p className="text-xs text-gray-400">{user.email}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1 flex-wrap">
                      {user.roles?.map((r) => (
                        <span key={r.name} className="text-xs bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full">{ROLE_LABELS[r.name] || r.name}</span>
                      ))}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <button onClick={() => toggleActive(user)}
                      className={`text-xs px-2 py-0.5 rounded-full transition-colors ${user.is_active ? "bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-700" : "bg-gray-100 text-gray-500 hover:bg-green-100 hover:text-green-700"}`}>
                      {user.is_active ? "نشط" : "موقوف"}
                    </button>
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-400">{formatDate(user.created_at)}</td>
                  <td className="px-4 py-3">
                    <button onClick={() => deleteUser(user.id)} className="text-gray-300 hover:text-red-500 transition-colors">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="إنشاء مستخدم جديد" size="sm"
        footer={<><Button variant="secondary" onClick={() => setShowCreate(false)}>إلغاء</Button><Button loading={createLoading} onClick={createUser}>إنشاء</Button></>}>
        <div className="space-y-4">
          <Input label="الاسم الكامل" value={form.name} onChange={(e) => setForm(p => ({ ...p, name: e.target.value }))} placeholder="محمد أحمد" />
          <Input label="البريد الإلكتروني" type="email" value={form.email} onChange={(e) => setForm(p => ({ ...p, email: e.target.value }))} placeholder="user@company.com" />
          <Input label="كلمة المرور" type="password" value={form.password} onChange={(e) => setForm(p => ({ ...p, password: e.target.value }))} placeholder="••••••••" />
          <Select label="الدور" value={form.role} onChange={(e) => setForm(p => ({ ...p, role: e.target.value }))}>
            {ROLES.map((r) => <option key={r} value={r}>{ROLE_LABELS[r]}</option>)}
          </Select>
        </div>
      </Modal>
    </DashboardLayout>
  );
}
