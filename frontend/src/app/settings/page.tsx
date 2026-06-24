"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";
import api from "@/lib/api";
import { Building2, Key, Palette, Shield } from "lucide-react";

interface Settings {
  company_name?: string; company_logo?: string; timezone?: string; language?: string;
  openai_api_key?: string; heygen_api_key?: string;
  smtp_host?: string; smtp_port?: string; smtp_user?: string; smtp_pass?: string; smtp_from?: string;
}

export default function SettingsPage() {
  const [settings, setSettings] = useState<Settings>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState("general");

  useEffect(() => {
    api.get("/settings").then((res) => setSettings(res.data)).catch(() => toast.error("خطأ في التحميل")).finally(() => setLoading(false));
  }, []);

  const save = async () => {
    setSaving(true);
    try {
      await api.put("/settings", settings);
      toast.success("تم حفظ الإعدادات");
    } catch { toast.error("خطأ في الحفظ"); }
    finally { setSaving(false); }
  };

  const set = (key: keyof Settings, value: string) => setSettings((p) => ({ ...p, [key]: value }));

  const TABS = [
    { id: "general", label: "عام", icon: Building2 },
    { id: "ai", label: "الذكاء الاصطناعي", icon: Key },
    { id: "email", label: "البريد الإلكتروني", icon: Palette },
    { id: "security", label: "الأمان", icon: Shield },
  ];

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">الإعدادات</h1>
          <p className="text-sm text-gray-500 mt-0.5">إعدادات الشركة والنظام</p>
        </div>

        <div className="grid grid-cols-4 gap-5">
          {/* Sidebar */}
          <div className="col-span-1 space-y-1">
            {TABS.map((t) => (
              <button key={t.id} onClick={() => setActiveTab(t.id)}
                className={`w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                  activeTab === t.id ? "bg-violet-600 text-white shadow-md" : "text-gray-600 hover:bg-gray-100"
                }`}>
                <t.icon className="w-4 h-4" />
                {t.label}
              </button>
            ))}
          </div>

          {/* Content */}
          <div className="col-span-3 bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            {loading ? (
              <div className="space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-12 skeleton rounded-xl" />)}</div>
            ) : (
              <>
                {activeTab === "general" && (
                  <div className="space-y-4">
                    <h3 className="font-bold text-gray-900 mb-4">إعدادات الشركة</h3>
                    <Input label="اسم الشركة" value={settings.company_name || ""} onChange={(e) => set("company_name", e.target.value)} />
                    <Select label="المنطقة الزمنية" value={settings.timezone || "Asia/Riyadh"} onChange={(e) => set("timezone", e.target.value)}>
                      <option value="Asia/Riyadh">الرياض (UTC+3)</option>
                      <option value="Asia/Dubai">دبي (UTC+4)</option>
                      <option value="Africa/Cairo">القاهرة (UTC+2)</option>
                      <option value="UTC">UTC</option>
                    </Select>
                    <Select label="اللغة الافتراضية" value={settings.language || "ar"} onChange={(e) => set("language", e.target.value)}>
                      <option value="ar">العربية</option>
                      <option value="en">English</option>
                    </Select>
                  </div>
                )}

                {activeTab === "ai" && (
                  <div className="space-y-4">
                    <h3 className="font-bold text-gray-900 mb-4">مفاتيح الذكاء الاصطناعي</h3>
                    <div>
                      <Input label="مفتاح OpenAI API" type="password"
                        value={settings.openai_api_key || ""} onChange={(e) => set("openai_api_key", e.target.value)}
                        placeholder="sk-..." />
                      <p className="text-xs text-gray-400 mt-1">مطلوب لجميع ميزات الذكاء الاصطناعي (المقابلات، التقييم، المساعد)</p>
                    </div>
                    <div>
                      <Input label="مفتاح HeyGen API" type="password"
                        value={settings.heygen_api_key || ""} onChange={(e) => set("heygen_api_key", e.target.value)}
                        placeholder="..." />
                      <p className="text-xs text-gray-400 mt-1">مطلوب فقط لمقابلات الفيديو بالأفاتار</p>
                    </div>
                  </div>
                )}

                {activeTab === "email" && (
                  <div className="space-y-4">
                    <h3 className="font-bold text-gray-900 mb-4">إعدادات البريد الإلكتروني (SMTP)</h3>
                    <div className="grid grid-cols-2 gap-4">
                      <Input label="SMTP Host" value={settings.smtp_host || ""} onChange={(e) => set("smtp_host", e.target.value)} placeholder="smtp.gmail.com" />
                      <Input label="SMTP Port" value={settings.smtp_port || ""} onChange={(e) => set("smtp_port", e.target.value)} placeholder="587" />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <Input label="المستخدم" value={settings.smtp_user || ""} onChange={(e) => set("smtp_user", e.target.value)} placeholder="user@company.com" />
                      <Input label="كلمة المرور" type="password" value={settings.smtp_pass || ""} onChange={(e) => set("smtp_pass", e.target.value)} placeholder="••••••••" />
                    </div>
                    <Input label="بريد المرسل" value={settings.smtp_from || ""} onChange={(e) => set("smtp_from", e.target.value)} placeholder="noreply@company.com" />
                  </div>
                )}

                {activeTab === "security" && (
                  <div className="space-y-4">
                    <h3 className="font-bold text-gray-900 mb-4">إعدادات الأمان</h3>
                    <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-700">
                      إعدادات الأمان متاحة للمسؤول الأعلى فقط من خلال لوحة التحكم العليا.
                    </div>
                  </div>
                )}

                <div className="mt-6 pt-4 border-t border-gray-200 flex justify-end">
                  <Button loading={saving} onClick={save}>حفظ الإعدادات</Button>
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
