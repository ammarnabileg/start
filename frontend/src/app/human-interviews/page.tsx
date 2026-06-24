"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { hrInterviewApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Calendar, Clock, Plus, Star, User } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface HumanInterview {
  id: number;
  application_id: number;
  scheduled_at: string;
  duration_minutes: number;
  location?: string;
  interview_link?: string;
  status: string;
  overall_rating?: number;
  notes?: string;
  candidate?: { name: string; email: string };
  job?: { title: string };
  interviewer?: { name: string };
}

const STATUS_LABELS: Record<string, string> = { scheduled: "مجدولة", completed: "مكتملة", cancelled: "ملغية", no_show: "لم يحضر" };
const STATUS_COLORS: Record<string, "blue" | "green" | "red" | "gray"> = { scheduled: "blue", completed: "green", cancelled: "red", no_show: "gray" };

export default function HumanInterviewsPage() {
  const [interviews, setInterviews] = useState<HumanInterview[]>([]);
  const [loading, setLoading] = useState(true);
  const [showSchedule, setShowSchedule] = useState(false);
  const [showEval, setShowEval] = useState<HumanInterview | null>(null);
  const [scheduleLoading, setScheduleLoading] = useState(false);
  const [evalLoading, setEvalLoading] = useState(false);

  const [schedForm, setSchedForm] = useState({
    application_id: "", scheduled_at: "", duration_minutes: "60",
    location: "", interview_link: "", interviewer_id: "", notes: "",
  });

  const [evalForm, setEvalForm] = useState({
    technical_depth: "3", problem_solving: "3", communication: "3",
    culture_fit: "3", takes_ownership: "3", seniority_fit: "3",
    overall_rating: "3", recommendation: "proceed", notes: "",
  });

  useEffect(() => { loadInterviews(); }, []);

  const loadInterviews = async () => {
    try {
      const res = await hrInterviewApi.list();
      setInterviews(res.data.data || res.data);
    } catch { toast.error("خطأ في التحميل"); }
    finally { setLoading(false); }
  };

  const schedule = async () => {
    setScheduleLoading(true);
    try {
      await hrInterviewApi.schedule({ ...schedForm, duration_minutes: Number(schedForm.duration_minutes) });
      toast.success("تم جدولة المقابلة");
      setShowSchedule(false);
      loadInterviews();
    } catch { toast.error("خطأ في الجدولة"); }
    finally { setScheduleLoading(false); }
  };

  const submitEval = async () => {
    if (!showEval) return;
    setEvalLoading(true);
    try {
      await hrInterviewApi.evaluate(showEval.id, {
        ...evalForm,
        technical_depth: Number(evalForm.technical_depth),
        problem_solving: Number(evalForm.problem_solving),
        communication: Number(evalForm.communication),
        culture_fit: Number(evalForm.culture_fit),
        takes_ownership: Number(evalForm.takes_ownership),
        seniority_fit: Number(evalForm.seniority_fit),
        overall_rating: Number(evalForm.overall_rating),
      });
      toast.success("تم حفظ التقييم");
      setShowEval(null);
      loadInterviews();
    } catch { toast.error("خطأ في التقييم"); }
    finally { setEvalLoading(false); }
  };

  const EVAL_CRITERIA = [
    { key: "technical_depth", label: "العمق التقني" },
    { key: "problem_solving", label: "حل المشكلات" },
    { key: "communication", label: "التواصل" },
    { key: "culture_fit", label: "الملاءمة الثقافية" },
    { key: "takes_ownership", label: "تحمل المسؤولية" },
    { key: "seniority_fit", label: "التوافق مع المستوى" },
  ];

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">المقابلات البشرية</h1>
            <p className="text-sm text-gray-500 mt-0.5">{interviews.length} مقابلة</p>
          </div>
          <Button icon={<Plus className="w-4 h-4" />} onClick={() => setShowSchedule(true)}>جدولة مقابلة</Button>
        </div>

        {/* Cards */}
        {loading ? (
          <div className="grid grid-cols-2 gap-4">{[...Array(4)].map((_, i) => <div key={i} className="h-36 skeleton rounded-xl" />)}</div>
        ) : interviews.length === 0 ? (
          <div className="bg-white rounded-xl border border-gray-200 p-16 text-center">
            <Calendar className="w-10 h-10 text-gray-300 mx-auto mb-3" />
            <p className="text-gray-500 font-medium">لا توجد مقابلات مجدولة</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {interviews.map((iv) => (
              <div key={iv.id} className="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-all">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <p className="font-bold text-gray-900 text-sm">{iv.candidate?.name}</p>
                    <p className="text-xs text-gray-400">{iv.job?.title}</p>
                  </div>
                  <Badge variant={STATUS_COLORS[iv.status] || "gray"} size="sm">{STATUS_LABELS[iv.status] || iv.status}</Badge>
                </div>

                <div className="space-y-1.5 text-xs text-gray-500 mb-4">
                  <div className="flex items-center gap-1.5">
                    <Clock className="w-3 h-3" />
                    <span>{formatDate(iv.scheduled_at)} • {iv.duration_minutes} دقيقة</span>
                  </div>
                  {iv.interviewer && (
                    <div className="flex items-center gap-1.5">
                      <User className="w-3 h-3" />
                      <span>{iv.interviewer.name}</span>
                    </div>
                  )}
                  {iv.overall_rating && (
                    <div className="flex items-center gap-1.5">
                      <Star className="w-3 h-3 text-amber-400" />
                      <span className="font-medium">{iv.overall_rating}/5</span>
                    </div>
                  )}
                </div>

                <div className="flex gap-2">
                  <Link href={`/applications/${iv.application_id}`} className="btn-secondary btn-sm flex-1 text-center text-xs">عرض الطلب</Link>
                  {iv.status === "scheduled" && (
                    <Button variant="primary" size="sm" onClick={() => setShowEval(iv)}>تقييم</Button>
                  )}
                  {iv.interview_link && (
                    <a href={iv.interview_link} target="_blank" rel="noopener noreferrer"
                      className="text-xs px-3 py-1.5 bg-violet-50 text-violet-600 rounded-lg hover:bg-violet-100 transition-colors">
                      رابط
                    </a>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Schedule Modal */}
      <Modal open={showSchedule} onClose={() => setShowSchedule(false)} title="جدولة مقابلة بشرية" size="lg"
        footer={<><Button variant="secondary" onClick={() => setShowSchedule(false)}>إلغاء</Button><Button loading={scheduleLoading} onClick={schedule}>جدولة</Button></>}>
        <div className="space-y-4">
          <Input label="رقم طلب التوظيف" type="number" placeholder="ID" value={schedForm.application_id}
            onChange={(e) => setSchedForm(p => ({ ...p, application_id: e.target.value }))} />
          <div className="grid grid-cols-2 gap-4">
            <Input label="التاريخ والوقت" type="datetime-local" value={schedForm.scheduled_at}
              onChange={(e) => setSchedForm(p => ({ ...p, scheduled_at: e.target.value }))} />
            <Select label="المدة" value={schedForm.duration_minutes} onChange={(e) => setSchedForm(p => ({ ...p, duration_minutes: e.target.value }))}>
              {[30, 45, 60, 90, 120].map((n) => <option key={n} value={n}>{n} دقيقة</option>)}
            </Select>
          </div>
          <Input label="المكان (اختياري)" placeholder="مكتب الرياض / أونلاين" value={schedForm.location}
            onChange={(e) => setSchedForm(p => ({ ...p, location: e.target.value }))} />
          <Input label="رابط الاجتماع (اختياري)" placeholder="https://meet.google.com/..." value={schedForm.interview_link}
            onChange={(e) => setSchedForm(p => ({ ...p, interview_link: e.target.value }))} />
          <textarea value={schedForm.notes} onChange={(e) => setSchedForm(p => ({ ...p, notes: e.target.value }))}
            placeholder="ملاحظات..." rows={3}
            className="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
        </div>
      </Modal>

      {/* Evaluation Modal */}
      <Modal open={!!showEval} onClose={() => setShowEval(null)} title="تقييم المقابلة" size="lg"
        footer={<><Button variant="secondary" onClick={() => setShowEval(null)}>إلغاء</Button><Button loading={evalLoading} onClick={submitEval}>حفظ التقييم</Button></>}>
        <div className="space-y-5">
          <div className="space-y-4">
            {EVAL_CRITERIA.map((c) => (
              <div key={c.key}>
                <div className="flex justify-between items-center mb-1">
                  <label className="text-sm font-medium text-gray-700">{c.label}</label>
                  <span className="text-sm font-bold text-violet-600">{evalForm[c.key as keyof typeof evalForm]}/5</span>
                </div>
                <div className="flex gap-2">
                  {[1, 2, 3, 4, 5].map((n) => (
                    <button key={n} onClick={() => setEvalForm(p => ({ ...p, [c.key]: String(n) }))}
                      className={`flex-1 py-2 rounded-lg text-sm font-medium transition-colors ${
                        Number(evalForm[c.key as keyof typeof evalForm]) >= n
                          ? "bg-violet-600 text-white" : "bg-gray-100 text-gray-400 hover:bg-violet-50 hover:text-violet-600"
                      }`}>
                      {n}
                    </button>
                  ))}
                </div>
              </div>
            ))}
          </div>

          <div>
            <div className="flex justify-between items-center mb-2">
              <label className="text-sm font-bold text-gray-900">التقييم الإجمالي</label>
              <span className="text-lg font-bold text-violet-600">{evalForm.overall_rating}/5</span>
            </div>
            <div className="flex gap-2">
              {[1, 2, 3, 4, 5].map((n) => (
                <button key={n} onClick={() => setEvalForm(p => ({ ...p, overall_rating: String(n) }))}
                  className={`flex-1 py-3 rounded-xl text-sm font-bold transition-all ${
                    Number(evalForm.overall_rating) >= n ? "bg-violet-600 text-white shadow-md" : "bg-gray-100 text-gray-400 hover:bg-violet-50"
                  }`}>
                  <Star className={`w-4 h-4 mx-auto ${Number(evalForm.overall_rating) >= n ? "fill-white text-white" : ""}`} />
                </button>
              ))}
            </div>
          </div>

          <Select label="التوصية" value={evalForm.recommendation} onChange={(e) => setEvalForm(p => ({ ...p, recommendation: e.target.value }))}>
            <option value="proceed">المضي قدماً</option>
            <option value="hold">تأجيل</option>
            <option value="reject">رفض</option>
            <option value="strong_hire">توظيف بشدة</option>
          </Select>

          <textarea value={evalForm.notes} onChange={(e) => setEvalForm(p => ({ ...p, notes: e.target.value }))}
            placeholder="ملاحظات إضافية..." rows={4}
            className="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
        </div>
      </Modal>
    </DashboardLayout>
  );
}
