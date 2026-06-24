"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";
import { Input, Select } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { jobsApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Briefcase, Copy, Eye, Link2, MoreVertical, Plus, Search, Sparkles, Trash2 } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface Job {
  id: number;
  title: string;
  department?: { name: string };
  seniority: string;
  status: string;
  interview_type: string;
  applications_count: number;
  created_at: string;
  published_at?: string;
}

const statusColors: Record<string, "purple" | "green" | "gray" | "yellow" | "red"> = {
  active: "green", draft: "gray", paused: "yellow", archived: "red",
};
const statusLabels: Record<string, string> = {
  active: "نشطة", draft: "مسودة", paused: "متوقفة", archived: "مؤرشفة",
};
const seniorityLabels: Record<string, string> = {
  intern: "متدرب", junior: "مبتدئ", mid: "متوسط", senior: "خبير",
  lead: "قائد", manager: "مدير", director: "مدير تنفيذي", executive: "تنفيذي",
};

export default function JobsPage() {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [showCreate, setShowCreate] = useState(false);
  const [aiGenerate, setAiGenerate] = useState(false);
  const [aiLoading, setAiLoading] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [linkModal, setLinkModal] = useState<{ open: boolean; jobId: number | null; link: string }>({ open: false, jobId: null, link: "" });

  const [form, setForm] = useState({
    title: "", title_ar: "", seniority: "mid", department_id: "", salary_min: "", salary_max: "",
    currency: "SAR", description: "", requirements: "", interview_type: "text", max_questions: "12",
  });

  useEffect(() => {
    loadJobs();
  }, [search, statusFilter]);

  const loadJobs = async () => {
    try {
      const res = await jobsApi.list({ search, status: statusFilter });
      setJobs(res.data.data || res.data);
    } catch {
      toast.error("خطأ في تحميل الوظائف");
    } finally {
      setLoading(false);
    }
  };

  const createJob = async () => {
    setCreateLoading(true);
    try {
      await jobsApi.create({ ...form, salary_min: form.salary_min || null, salary_max: form.salary_max || null });
      toast.success("تم إنشاء الوظيفة بنجاح");
      setShowCreate(false);
      loadJobs();
    } catch {
      toast.error("خطأ في إنشاء الوظيفة");
    } finally {
      setCreateLoading(false);
    }
  };

  const aiGenerateJob = async () => {
    if (!form.title) { toast.error("أدخل مسمى الوظيفة أولاً"); return; }
    setAiLoading(true);
    try {
      const res = await jobsApi.aiGenerate({ title: form.title, seniority: form.seniority });
      setForm(prev => ({ ...prev, description: res.data.description || prev.description, requirements: res.data.requirements || prev.requirements }));
      toast.success("تم توليد المحتوى بالذكاء الاصطناعي!");
    } catch {
      toast.error("خطأ في الذكاء الاصطناعي");
    } finally {
      setAiLoading(false);
    }
  };

  const publishJob = async (id: number) => {
    try {
      await jobsApi.publish(id);
      toast.success("تم نشر الوظيفة");
      loadJobs();
    } catch {
      toast.error("خطأ في النشر");
    }
  };

  const generateLink = async (jobId: number) => {
    const email = prompt("أدخل بريد المرشح:");
    if (!email) return;
    try {
      const res = await jobsApi.generateLink(jobId, { candidate_email: email });
      setLinkModal({ open: true, jobId, link: res.data.link });
    } catch {
      toast.error("خطأ في إنشاء الرابط");
    }
  };

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">الوظائف</h1>
            <p className="text-sm text-gray-500 mt-0.5">{jobs.length} وظيفة</p>
          </div>
          <Button icon={<Plus className="w-4 h-4" />} onClick={() => setShowCreate(true)}>وظيفة جديدة</Button>
        </div>

        {/* Filters */}
        <div className="flex gap-3">
          <Input placeholder="بحث عن وظيفة..." leftIcon={<Search className="w-4 h-4" />} value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
          <Select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="max-w-[150px]">
            <option value="">كل الحالات</option>
            <option value="active">نشطة</option>
            <option value="draft">مسودة</option>
            <option value="paused">متوقفة</option>
            <option value="archived">مؤرشفة</option>
          </Select>
        </div>

        {/* Jobs Grid */}
        {loading ? (
          <div className="grid grid-cols-2 gap-4">{[...Array(4)].map((_, i) => <div key={i} className="h-40 skeleton rounded-xl" />)}</div>
        ) : jobs.length === 0 ? (
          <div className="bg-white rounded-xl border border-gray-200 p-16 text-center">
            <Briefcase className="w-10 h-10 text-gray-300 mx-auto mb-3" />
            <p className="text-gray-500 font-medium">لا توجد وظائف</p>
            <p className="text-sm text-gray-400 mt-1">أنشئ أول وظيفة للبدء</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {jobs.map((job) => (
              <div key={job.id} className="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all duration-200 p-5">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex-1 min-w-0">
                    <Link href={`/jobs/${job.id}`} className="text-base font-bold text-gray-900 hover:text-violet-600 truncate block">{job.title}</Link>
                    <p className="text-xs text-gray-400 mt-0.5">{job.department?.name || "بدون قسم"} • {seniorityLabels[job.seniority]}</p>
                  </div>
                  <Badge variant={statusColors[job.status] || "gray"}>{statusLabels[job.status]}</Badge>
                </div>

                <div className="flex items-center gap-4 text-xs text-gray-500 mb-4">
                  <span className="flex items-center gap-1"><span className="w-1.5 h-1.5 rounded-full bg-violet-400" />{job.applications_count || 0} متقدم</span>
                  <span>{job.interview_type === "text" ? "نصية" : job.interview_type === "voice" ? "صوتية" : "فيديو"}</span>
                  <span>{formatDate(job.created_at)}</span>
                </div>

                <div className="flex items-center gap-2">
                  <Link href={`/jobs/${job.id}`} className="btn-secondary btn-sm flex-1 text-center">عرض</Link>
                  {job.status === "draft" && (
                    <Button variant="primary" size="sm" onClick={() => publishJob(job.id)}>نشر</Button>
                  )}
                  {job.status === "active" && (
                    <Button variant="secondary" size="sm" icon={<Link2 className="w-3 h-3" />} onClick={() => generateLink(job.id)}>رابط</Button>
                  )}
                  <Button variant="ghost" size="sm" icon={<Copy className="w-3 h-3" />} onClick={async () => { await jobsApi.duplicate(job.id); loadJobs(); toast.success("تم النسخ"); }} />
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Create Job Modal */}
      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="إنشاء وظيفة جديدة" size="xl"
        footer={<><Button variant="secondary" onClick={() => setShowCreate(false)}>إلغاء</Button><Button loading={createLoading} onClick={createJob}>إنشاء الوظيفة</Button></>}>
        <div className="space-y-4">
          <div className="flex gap-3">
            <Input label="مسمى الوظيفة (عربي)" placeholder="مثال: مطور Full Stack" value={form.title} onChange={(e) => setForm(p => ({ ...p, title: e.target.value }))} className="flex-1" />
            <Input label="مسمى الوظيفة (إنجليزي)" placeholder="e.g. Full Stack Developer" value={form.title_ar} onChange={(e) => setForm(p => ({ ...p, title_ar: e.target.value }))} className="flex-1" />
          </div>
          <div className="flex gap-3">
            <Select label="المستوى الوظيفي" value={form.seniority} onChange={(e) => setForm(p => ({ ...p, seniority: e.target.value }))} className="flex-1">
              {Object.entries(seniorityLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </Select>
            <Select label="نوع المقابلة" value={form.interview_type} onChange={(e) => setForm(p => ({ ...p, interview_type: e.target.value }))} className="flex-1">
              <option value="text">نصية</option>
              <option value="voice">صوتية</option>
              <option value="video">فيديو (HeyGen)</option>
            </Select>
            <Select label="عدد الأسئلة" value={form.max_questions} onChange={(e) => setForm(p => ({ ...p, max_questions: e.target.value }))} className="flex-1">
              {[5, 8, 10, 12, 15, 20].map((n) => <option key={n} value={n}>{n} أسئلة</option>)}
            </Select>
          </div>
          <div className="flex gap-3">
            <Input label="الحد الأدنى للراتب" type="number" value={form.salary_min} onChange={(e) => setForm(p => ({ ...p, salary_min: e.target.value }))} className="flex-1" />
            <Input label="الحد الأقصى للراتب" type="number" value={form.salary_max} onChange={(e) => setForm(p => ({ ...p, salary_max: e.target.value }))} className="flex-1" />
            <Select label="العملة" value={form.currency} onChange={(e) => setForm(p => ({ ...p, currency: e.target.value }))} className="w-28">
              <option value="SAR">SAR</option><option value="AED">AED</option><option value="USD">USD</option><option value="EGP">EGP</option>
            </Select>
          </div>
          <div className="flex items-center justify-between">
            <label className="block text-sm font-medium text-gray-700">وصف الوظيفة</label>
            <Button variant="ghost" size="sm" icon={<Sparkles className="w-3.5 h-3.5 text-violet-500" />} loading={aiLoading} onClick={aiGenerateJob}>توليد بالذكاء الاصطناعي</Button>
          </div>
          <textarea value={form.description} onChange={(e) => setForm(p => ({ ...p, description: e.target.value }))} placeholder="اكتب وصف الوظيفة أو استخدم الذكاء الاصطناعي لتوليده..." rows={4} className="w-full px-3 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
          <textarea value={form.requirements} onChange={(e) => setForm(p => ({ ...p, requirements: e.target.value }))} placeholder="المتطلبات والمهارات المطلوبة..." rows={3} className="w-full px-3 py-2.5 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
        </div>
      </Modal>

      {/* Link Modal */}
      <Modal open={linkModal.open} onClose={() => setLinkModal({ open: false, jobId: null, link: "" })} title="رابط المقابلة" size="md">
        <div className="space-y-4">
          <p className="text-sm text-gray-500">انسخ هذا الرابط وأرسله للمرشح. صالح لمدة 14 يوماً.</p>
          <div className="flex gap-2">
            <input readOnly value={linkModal.link} className="flex-1 px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg" />
            <Button variant="secondary" size="sm" onClick={() => { navigator.clipboard.writeText(linkModal.link); toast.success("تم النسخ!"); }}>نسخ</Button>
          </div>
        </div>
      </Modal>
    </DashboardLayout>
  );
}
