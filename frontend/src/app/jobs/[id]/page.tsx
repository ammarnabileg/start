"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { jobsApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { ChevronLeft, Link2, Plus, Sparkles, Trash2 } from "lucide-react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface Job {
  id: number; title: string; title_ar?: string; seniority: string; status: string;
  interview_type: string; max_questions: number; description?: string; requirements?: string;
  salary_min?: number; salary_max?: number; currency?: string;
  department?: { name: string };
  applications_count: number; created_at: string; published_at?: string;
  criteria?: Array<{ id: number; criterion: string; weight: number; description?: string }>;
  questions?: Array<{ id: number; question: string; category: string; difficulty: string }>;
}

const STATUS_COLORS: Record<string, "green" | "gray" | "yellow" | "red"> = {
  active: "green", draft: "gray", paused: "yellow", archived: "red",
};

export default function JobDetailPage() {
  const params = useParams();
  const id = Number(params.id);
  const [job, setJob] = useState<Job | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState("overview");
  const [showAddCriteria, setShowAddCriteria] = useState(false);
  const [showAddQuestion, setShowAddQuestion] = useState(false);
  const [genQuestionsLoading, setGenQuestionsLoading] = useState(false);
  const [newCriteria, setNewCriteria] = useState({ criterion: "", weight: "10", description: "" });
  const [newQuestion, setNewQuestion] = useState({ question: "", category: "technical", difficulty: "medium" });

  useEffect(() => {
    jobsApi.get(id).then((res) => setJob(res.data)).catch(() => toast.error("خطأ في التحميل")).finally(() => setLoading(false));
  }, [id]);

  const publish = async () => {
    try { await jobsApi.publish(id); setJob((p) => p ? { ...p, status: "active" } : p); toast.success("تم النشر"); }
    catch { toast.error("خطأ في النشر"); }
  };

  const generateLink = async () => {
    const email = prompt("أدخل بريد المرشح:");
    if (!email) return;
    try {
      const res = await jobsApi.generateLink(id, { candidate_email: email });
      navigator.clipboard.writeText(res.data.link);
      toast.success("تم نسخ الرابط!");
    } catch { toast.error("خطأ في إنشاء الرابط"); }
  };

  const addCriteria = async () => {
    try {
      await jobsApi.addCriteria(id, { ...newCriteria, weight: Number(newCriteria.weight) });
      toast.success("تمت الإضافة");
      setShowAddCriteria(false);
      const res = await jobsApi.get(id);
      setJob(res.data);
    } catch { toast.error("خطأ في الإضافة"); }
  };

  const deleteCriteria = async (criteriaId: number) => {
    try {
      await jobsApi.deleteCriteria(id, criteriaId);
      setJob((p) => p ? { ...p, criteria: p.criteria?.filter((c) => c.id !== criteriaId) } : p);
      toast.success("تم الحذف");
    } catch { toast.error("خطأ في الحذف"); }
  };

  const addQuestion = async () => {
    try {
      await jobsApi.addQuestion(id, newQuestion);
      toast.success("تمت الإضافة");
      setShowAddQuestion(false);
      const res = await jobsApi.get(id);
      setJob(res.data);
    } catch { toast.error("خطأ في الإضافة"); }
  };

  const generateQuestions = async () => {
    setGenQuestionsLoading(true);
    try {
      const res = await jobsApi.generateQuestions(id);
      toast.success(`تم توليد ${res.data.count || 0} سؤال`);
      const r = await jobsApi.get(id);
      setJob(r.data);
    } catch { toast.error("خطأ في التوليد"); }
    finally { setGenQuestionsLoading(false); }
  };

  const deleteQuestion = async (qId: number) => {
    try {
      await jobsApi.deleteQuestion(id, qId);
      setJob((p) => p ? { ...p, questions: p.questions?.filter((q) => q.id !== qId) } : p);
    } catch { toast.error("خطأ في الحذف"); }
  };

  if (loading) return <DashboardLayout><div className="h-64 skeleton rounded-xl" /></DashboardLayout>;
  if (!job) return <DashboardLayout><div className="text-center py-20 text-gray-400">الوظيفة غير موجودة</div></DashboardLayout>;

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* Breadcrumb */}
        <div className="flex items-center gap-2 text-sm text-gray-400">
          <Link href="/jobs" className="hover:text-violet-600 flex items-center gap-1"><ChevronLeft className="w-3.5 h-3.5" /> الوظائف</Link>
          <span>/</span>
          <span className="text-gray-700 font-medium">{job.title}</span>
        </div>

        {/* Header */}
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
          <div className="flex items-start justify-between mb-4">
            <div>
              <div className="flex items-center gap-3 mb-1">
                <h1 className="text-xl font-bold text-gray-900">{job.title}</h1>
                <Badge variant={STATUS_COLORS[job.status] || "gray"}>
                  {job.status === "active" ? "نشطة" : job.status === "draft" ? "مسودة" : job.status === "paused" ? "متوقفة" : "مؤرشفة"}
                </Badge>
              </div>
              <p className="text-sm text-gray-400">{job.department?.name} • {job.seniority} • {job.interview_type === "text" ? "مقابلة نصية" : job.interview_type === "voice" ? "مقابلة صوتية" : "مقابلة فيديو"}</p>
            </div>
            <div className="flex items-center gap-2">
              {job.status === "draft" && <Button variant="primary" size="sm" onClick={publish}>نشر الوظيفة</Button>}
              {job.status === "active" && (
                <Button variant="secondary" size="sm" icon={<Link2 className="w-3.5 h-3.5" />} onClick={generateLink}>إنشاء رابط</Button>
              )}
            </div>
          </div>

          <div className="grid grid-cols-4 gap-4">
            {[
              { label: "المتقدمون", value: job.applications_count || 0 },
              { label: "الأسئلة", value: job.max_questions },
              { label: "معايير التقييم", value: job.criteria?.length || 0 },
              { label: "بنك الأسئلة", value: job.questions?.length || 0 },
            ].map((s) => (
              <div key={s.label} className="p-3 bg-gray-50 rounded-xl text-center">
                <p className="text-xl font-bold text-gray-900">{s.value}</p>
                <p className="text-xs text-gray-400 mt-0.5">{s.label}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Tabs */}
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div className="flex border-b border-gray-200">
            {[
              { id: "overview", label: "نظرة عامة" }, { id: "criteria", label: "معايير التقييم" },
              { id: "questions", label: "بنك الأسئلة" }, { id: "applications", label: "الطلبات" },
            ].map((t) => (
              <button key={t.id} onClick={() => setActiveTab(t.id)}
                className={`px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors ${activeTab === t.id ? "border-violet-600 text-violet-600" : "border-transparent text-gray-500 hover:text-gray-700"}`}>
                {t.label}
              </button>
            ))}
          </div>

          <div className="p-6">
            {activeTab === "overview" && (
              <div className="space-y-4">
                {job.description && (
                  <div>
                    <h3 className="text-sm font-bold text-gray-900 mb-2">وصف الوظيفة</h3>
                    <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{job.description}</p>
                  </div>
                )}
                {job.requirements && (
                  <div>
                    <h3 className="text-sm font-bold text-gray-900 mb-2">المتطلبات</h3>
                    <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">{job.requirements}</p>
                  </div>
                )}
                {(job.salary_min || job.salary_max) && (
                  <div className="p-4 bg-violet-50 rounded-xl">
                    <p className="text-xs text-violet-500 mb-1">نطاق الراتب</p>
                    <p className="text-sm font-bold text-violet-900">
                      {job.salary_min?.toLocaleString()} – {job.salary_max?.toLocaleString()} {job.currency}/شهر
                    </p>
                  </div>
                )}
              </div>
            )}

            {activeTab === "criteria" && (
              <div className="space-y-3">
                <div className="flex justify-end">
                  <Button size="sm" icon={<Plus className="w-3.5 h-3.5" />} onClick={() => setShowAddCriteria(true)}>إضافة معيار</Button>
                </div>
                {job.criteria && job.criteria.length > 0 ? job.criteria.map((c) => (
                  <div key={c.id} className="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                    <div className="flex-1">
                      <p className="text-sm font-medium text-gray-900">{c.criterion}</p>
                      {c.description && <p className="text-xs text-gray-400 mt-0.5">{c.description}</p>}
                    </div>
                    <div className="text-sm font-bold text-violet-700 bg-violet-100 px-3 py-1 rounded-full">وزن {c.weight}%</div>
                    <button onClick={() => deleteCriteria(c.id)} className="text-gray-300 hover:text-red-500 transition-colors">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                )) : <div className="text-center py-8 text-gray-400 text-sm">لا توجد معايير</div>}
              </div>
            )}

            {activeTab === "questions" && (
              <div className="space-y-3">
                <div className="flex gap-2 justify-end">
                  <Button variant="ghost" size="sm" icon={<Sparkles className="w-3.5 h-3.5 text-violet-500" />} loading={genQuestionsLoading} onClick={generateQuestions}>
                    توليد بالذكاء الاصطناعي
                  </Button>
                  <Button size="sm" icon={<Plus className="w-3.5 h-3.5" />} onClick={() => setShowAddQuestion(true)}>إضافة سؤال</Button>
                </div>
                {job.questions && job.questions.length > 0 ? job.questions.map((q, i) => (
                  <div key={q.id} className="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                    <span className="text-xs font-bold text-gray-400 w-6 flex-shrink-0 mt-0.5">{i + 1}</span>
                    <div className="flex-1">
                      <p className="text-sm text-gray-800">{q.question}</p>
                      <div className="flex gap-2 mt-1.5">
                        <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{q.category}</span>
                        <span className={`text-xs px-2 py-0.5 rounded-full ${q.difficulty === "hard" ? "bg-red-100 text-red-700" : q.difficulty === "medium" ? "bg-yellow-100 text-yellow-700" : "bg-green-100 text-green-700"}`}>
                          {q.difficulty === "hard" ? "صعب" : q.difficulty === "medium" ? "متوسط" : "سهل"}
                        </span>
                      </div>
                    </div>
                    <button onClick={() => deleteQuestion(q.id)} className="text-gray-300 hover:text-red-500 transition-colors flex-shrink-0">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                )) : <div className="text-center py-8 text-gray-400 text-sm">لا توجد أسئلة</div>}
              </div>
            )}

            {activeTab === "applications" && (
              <div className="text-center py-8">
                <Link href={`/applications?job_id=${id}`} className="text-violet-600 hover:text-violet-700 text-sm font-medium">
                  عرض جميع الطلبات لهذه الوظيفة ←
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>

      <Modal open={showAddCriteria} onClose={() => setShowAddCriteria(false)} title="إضافة معيار تقييم" size="sm"
        footer={<><Button variant="secondary" onClick={() => setShowAddCriteria(false)}>إلغاء</Button><Button onClick={addCriteria}>إضافة</Button></>}>
        <div className="space-y-4">
          <Input label="المعيار" value={newCriteria.criterion} onChange={(e) => setNewCriteria(p => ({ ...p, criterion: e.target.value }))} placeholder="مثال: قيادة الفريق" />
          <Input label="الوزن %" type="number" value={newCriteria.weight} onChange={(e) => setNewCriteria(p => ({ ...p, weight: e.target.value }))} placeholder="10" />
          <Input label="وصف (اختياري)" value={newCriteria.description} onChange={(e) => setNewCriteria(p => ({ ...p, description: e.target.value }))} />
        </div>
      </Modal>

      <Modal open={showAddQuestion} onClose={() => setShowAddQuestion(false)} title="إضافة سؤال" size="md"
        footer={<><Button variant="secondary" onClick={() => setShowAddQuestion(false)}>إلغاء</Button><Button onClick={addQuestion}>إضافة</Button></>}>
        <div className="space-y-4">
          <textarea value={newQuestion.question} onChange={(e) => setNewQuestion(p => ({ ...p, question: e.target.value }))}
            placeholder="اكتب السؤال هنا..." rows={3}
            className="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
          <div className="grid grid-cols-2 gap-3">
            <Select label="الفئة" value={newQuestion.category} onChange={(e) => setNewQuestion(p => ({ ...p, category: e.target.value }))}>
              {["technical", "behavioral", "situational", "general"].map((c) => <option key={c} value={c}>{c}</option>)}
            </Select>
            <Select label="الصعوبة" value={newQuestion.difficulty} onChange={(e) => setNewQuestion(p => ({ ...p, difficulty: e.target.value }))}>
              <option value="easy">سهل</option><option value="medium">متوسط</option><option value="hard">صعب</option>
            </Select>
          </div>
        </div>
      </Modal>
    </DashboardLayout>
  );
}
