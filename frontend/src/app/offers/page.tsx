"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { offersApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { CheckCircle, Download, FileText, Plus, Send, Sparkles, XCircle } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface Offer {
  id: number;
  application_id: number;
  job_title: string;
  base_salary: number;
  currency: string;
  joining_date: string;
  status: string;
  expires_at?: string;
  sent_at?: string;
  candidate?: { name: string; email: string };
  job?: { title: string };
}

const STATUS_LABELS: Record<string, string> = { draft: "مسودة", sent: "مُرسل", accepted: "مقبول", rejected: "مرفوض", expired: "منتهي" };
const STATUS_COLORS: Record<string, "gray" | "blue" | "green" | "red" | "yellow"> = {
  draft: "gray", sent: "blue", accepted: "green", rejected: "red", expired: "yellow",
};

export default function OffersPage() {
  const [offers, setOffers] = useState<Offer[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [aiLoading, setAiLoading] = useState(false);

  const [form, setForm] = useState({
    application_id: "", job_title: "", base_salary: "", currency: "SAR",
    benefits: "", joining_date: "", expiry_days: "7", notes: "",
  });

  useEffect(() => { loadOffers(); }, []);

  const loadOffers = async () => {
    try {
      const res = await offersApi.list();
      setOffers(res.data.data || res.data);
    } catch { toast.error("خطأ في التحميل"); }
    finally { setLoading(false); }
  };

  const createOffer = async () => {
    setCreateLoading(true);
    try {
      await offersApi.create({ ...form, base_salary: Number(form.base_salary), expiry_days: Number(form.expiry_days) });
      toast.success("تم إنشاء العرض");
      setShowCreate(false);
      loadOffers();
    } catch { toast.error("خطأ في الإنشاء"); }
    finally { setCreateLoading(false); }
  };

  const aiGenerate = async () => {
    if (!form.application_id) { toast.error("أدخل رقم الطلب أولاً"); return; }
    setAiLoading(true);
    try {
      const res = await offersApi.aiGenerate(Number(form.application_id));
      setForm(p => ({
        ...p,
        job_title: res.data.job_title || p.job_title,
        base_salary: String(res.data.suggested_salary || p.base_salary),
        benefits: res.data.benefits || p.benefits,
        notes: res.data.notes || p.notes,
      }));
      toast.success("تم توليد العرض بالذكاء الاصطناعي");
    } catch { toast.error("خطأ في الذكاء الاصطناعي"); }
    finally { setAiLoading(false); }
  };

  const sendOffer = async (id: number) => {
    try {
      await offersApi.send(id);
      toast.success("تم إرسال العرض");
      loadOffers();
    } catch { toast.error("خطأ في الإرسال"); }
  };

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">عروض التوظيف</h1>
            <p className="text-sm text-gray-500 mt-0.5">{offers.length} عرض</p>
          </div>
          <Button icon={<Plus className="w-4 h-4" />} onClick={() => setShowCreate(true)}>عرض جديد</Button>
        </div>

        {loading ? (
          <div className="grid grid-cols-2 gap-4">{[...Array(4)].map((_, i) => <div key={i} className="h-36 skeleton rounded-xl" />)}</div>
        ) : offers.length === 0 ? (
          <div className="bg-white rounded-xl border border-gray-200 p-16 text-center">
            <FileText className="w-10 h-10 text-gray-300 mx-auto mb-3" />
            <p className="text-gray-500 font-medium">لا توجد عروض توظيف</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {offers.map((offer) => (
              <div key={offer.id} className="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-all">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <p className="font-bold text-gray-900 text-sm">{offer.candidate?.name}</p>
                    <p className="text-xs text-gray-400">{offer.job?.title || offer.job_title}</p>
                  </div>
                  <Badge variant={STATUS_COLORS[offer.status] || "gray"} size="sm">{STATUS_LABELS[offer.status] || offer.status}</Badge>
                </div>

                <div className="flex items-center gap-3 mb-4">
                  <div className="flex-1 p-3 bg-violet-50 rounded-lg text-center">
                    <p className="text-lg font-bold text-violet-700">{offer.base_salary?.toLocaleString()}</p>
                    <p className="text-xs text-violet-400">{offer.currency}/شهر</p>
                  </div>
                  <div className="text-xs text-gray-500">
                    <p>انضمام: {formatDate(offer.joining_date)}</p>
                    {offer.expires_at && <p>ينتهي: {formatDate(offer.expires_at)}</p>}
                  </div>
                </div>

                <div className="flex gap-2">
                  <Link href={`/applications/${offer.application_id}`} className="btn-secondary btn-sm flex-1 text-center text-xs">الطلب</Link>
                  {offer.status === "draft" && (
                    <Button variant="primary" size="sm" icon={<Send className="w-3 h-3" />} onClick={() => sendOffer(offer.id)}>إرسال</Button>
                  )}
                  <Button variant="ghost" size="sm" icon={<Download className="w-3 h-3" />}
                    onClick={() => offersApi.pdf(offer.id).then((r) => window.open(r.data.url))} />
                </div>

                {(offer.status === "accepted" || offer.status === "rejected") && (
                  <div className={`mt-3 flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg ${
                    offer.status === "accepted" ? "bg-green-50 text-green-700" : "bg-red-50 text-red-700"
                  }`}>
                    {offer.status === "accepted" ? <CheckCircle className="w-3.5 h-3.5" /> : <XCircle className="w-3.5 h-3.5" />}
                    {offer.status === "accepted" ? "تم قبول العرض" : "تم رفض العرض"}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Create Offer Modal */}
      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="إنشاء عرض توظيف" size="lg"
        footer={
          <><Button variant="secondary" onClick={() => setShowCreate(false)}>إلغاء</Button>
            <Button loading={createLoading} onClick={createOffer}>إنشاء العرض</Button></>
        }>
        <div className="space-y-4">
          <div className="flex gap-3 items-end">
            <Input label="رقم الطلب (Application ID)" type="number" value={form.application_id}
              onChange={(e) => setForm(p => ({ ...p, application_id: e.target.value }))} className="flex-1" />
            <Button variant="ghost" size="sm" icon={<Sparkles className="w-3.5 h-3.5 text-violet-500" />} loading={aiLoading} onClick={aiGenerate}>
              توليد تلقائي
            </Button>
          </div>
          <Input label="المسمى الوظيفي" value={form.job_title} onChange={(e) => setForm(p => ({ ...p, job_title: e.target.value }))} />
          <div className="grid grid-cols-3 gap-3">
            <Input label="الراتب الأساسي" type="number" value={form.base_salary}
              onChange={(e) => setForm(p => ({ ...p, base_salary: e.target.value }))} className="col-span-2" />
            <Select label="العملة" value={form.currency} onChange={(e) => setForm(p => ({ ...p, currency: e.target.value }))}>
              <option value="SAR">SAR</option><option value="AED">AED</option>
              <option value="USD">USD</option><option value="EGP">EGP</option>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input label="تاريخ الانضمام" type="date" value={form.joining_date}
              onChange={(e) => setForm(p => ({ ...p, joining_date: e.target.value }))} />
            <Select label="صلاحية العرض" value={form.expiry_days} onChange={(e) => setForm(p => ({ ...p, expiry_days: e.target.value }))}>
              {[3, 5, 7, 10, 14, 30].map((n) => <option key={n} value={n}>{n} أيام</option>)}
            </Select>
          </div>
          <textarea value={form.benefits} onChange={(e) => setForm(p => ({ ...p, benefits: e.target.value }))}
            placeholder="المزايا والبدلات..." rows={3}
            className="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
          <textarea value={form.notes} onChange={(e) => setForm(p => ({ ...p, notes: e.target.value }))}
            placeholder="ملاحظات إضافية..." rows={2}
            className="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
        </div>
      </Modal>
    </DashboardLayout>
  );
}
