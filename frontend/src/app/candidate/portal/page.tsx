"use client";

import { candidateApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Briefcase, CheckCircle, Clock, FileText, Loader2, LogOut, Zap } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";
import { AuthProvider, useAuth } from "@/contexts/AuthContext";

function PortalContent() {
  const { user, logout, isLoading } = useAuth();
  const router = useRouter();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!isLoading && !user) router.push("/login");
  }, [user, isLoading, router]);

  useEffect(() => {
    if (!user) return;
    candidateApi.portal().then((res) => setData(res.data)).catch(() => toast.error("خطأ في التحميل")).finally(() => setLoading(false));
  }, [user]);

  const STATUS_LABELS: Record<string, string> = {
    applied: "تم التقدم", ai_screening: "مراجعة الذكاء الاصطناعي", qualified: "مؤهل",
    tech_interview: "مقابلة تقنية", manager_interview: "مقابلة المدير", final_review: "مراجعة نهائية",
    offer: "عرض توظيف", hired: "تم التوظيف", rejected: "مرفوض", disqualified: "غير مؤهل",
  };

  if (isLoading || loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-violet-50 to-indigo-50 flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-violet-500 animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-violet-50 to-indigo-50" dir="rtl">
      {/* Header */}
      <header className="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 bg-violet-600 rounded-xl flex items-center justify-center">
            <Zap className="w-4 h-4 text-white" />
          </div>
          <span className="font-bold text-gray-900">AI Recruit</span>
        </div>
        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-600">مرحباً، {user?.name}</span>
          <button onClick={logout} className="flex items-center gap-1 text-xs text-gray-400 hover:text-red-500 transition-colors">
            <LogOut className="w-3.5 h-3.5" /> خروج
          </button>
        </div>
      </header>

      <main className="max-w-3xl mx-auto px-6 py-8 space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">بوابة المرشح</h1>

        {/* Profile card */}
        {data?.profile && (
          <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 bg-violet-100 rounded-2xl flex items-center justify-center text-violet-700 font-bold text-xl">
                  {user?.name.charAt(0)}
                </div>
                <div>
                  <h2 className="text-lg font-bold text-gray-900">{user?.name}</h2>
                  <p className="text-sm text-gray-400">{user?.email}</p>
                  {data.profile.current_position && <p className="text-sm text-gray-600 mt-1">{data.profile.current_position}</p>}
                </div>
              </div>
              <Link href="/candidate/profile" className="text-xs text-violet-600 border border-violet-200 px-3 py-1.5 rounded-lg hover:bg-violet-50 transition-colors">
                تعديل الملف
              </Link>
            </div>

            {data.profile.cvs && data.profile.cvs.length > 0 && (
              <div className="mt-4 pt-4 border-t border-gray-100">
                <p className="text-xs font-medium text-gray-500 mb-2">السير الذاتية</p>
                <div className="space-y-2">
                  {data.profile.cvs.map((cv: any) => (
                    <div key={cv.id} className="flex items-center gap-2 text-sm text-gray-600">
                      <FileText className="w-3.5 h-3.5 text-violet-500" />
                      <span>{cv.file_name}</span>
                      {cv.is_default && <span className="text-xs bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full">افتراضي</span>}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Applications */}
        <div>
          <div className="flex items-center justify-between mb-3">
            <h2 className="font-bold text-gray-900">طلباتي</h2>
            <Link href="/candidate/apply" className="text-xs text-violet-600 border border-violet-200 px-3 py-1.5 rounded-lg hover:bg-violet-50 transition-colors">
              تقدم لوظيفة
            </Link>
          </div>

          {data?.applications && data.applications.length > 0 ? (
            <div className="space-y-3">
              {data.applications.map((app: any) => (
                <div key={app.id} className="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                  <div className="flex items-start justify-between">
                    <div>
                      <p className="font-bold text-gray-900 text-sm">{app.job?.title}</p>
                      <p className="text-xs text-gray-400 mt-0.5">{app.job?.department?.name}</p>
                    </div>
                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                      app.pipeline_stage === "hired" ? "bg-green-100 text-green-700" :
                      app.pipeline_stage === "rejected" || app.pipeline_stage === "disqualified" ? "bg-red-100 text-red-700" :
                      app.pipeline_stage === "offer" ? "bg-orange-100 text-orange-700" :
                      "bg-violet-100 text-violet-700"
                    }`}>
                      {STATUS_LABELS[app.pipeline_stage] || app.pipeline_stage}
                    </span>
                  </div>

                  {/* Pipeline progress */}
                  <div className="mt-3 flex items-center gap-1">
                    {["applied", "ai_screening", "qualified", "tech_interview", "manager_interview", "final_review", "offer", "hired"].map((stage, i, arr) => {
                      const stageIdx = arr.indexOf(app.pipeline_stage);
                      const isActive = i <= stageIdx;
                      return (
                        <div key={stage} className="flex-1 flex items-center">
                          <div className={`w-2 h-2 rounded-full flex-shrink-0 ${isActive ? "bg-violet-500" : "bg-gray-200"}`} />
                          {i < arr.length - 1 && <div className={`flex-1 h-0.5 ${isActive && i < stageIdx ? "bg-violet-500" : "bg-gray-200"}`} />}
                        </div>
                      );
                    })}
                  </div>

                  <div className="mt-3 flex items-center gap-3">
                    <span className="text-xs text-gray-400 flex items-center gap-1"><Clock className="w-3 h-3" />{formatDate(app.created_at)}</span>
                    {app.interview_session && app.interview_session.status === "pending" && (
                      <Link href={`/interview/${app.interview_session.token}`}
                        className="text-xs bg-violet-600 text-white px-3 py-1 rounded-lg hover:bg-violet-700 transition-colors">
                        بدء المقابلة
                      </Link>
                    )}
                    {app.offer && app.offer.status === "sent" && (
                      <Link href="/candidate/offers" className="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 transition-colors">
                        عرض توظيف جديد!
                      </Link>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
              <Briefcase className="w-8 h-8 text-gray-300 mx-auto mb-2" />
              <p className="text-sm text-gray-400">لم تتقدم لأي وظيفة بعد</p>
              <Link href="/candidate/apply" className="mt-3 inline-block text-xs text-violet-600 hover:text-violet-700">
                تصفح الوظائف المتاحة
              </Link>
            </div>
          )}
        </div>
      </main>
    </div>
  );
}

export default function CandidatePortalPage() {
  return <AuthProvider><PortalContent /></AuthProvider>;
}
