"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { StatCard, StatsGrid } from "@/components/ui/Stats";
import { Badge, ScoreBadge } from "@/components/ui/Badge";
import { dashboardApi } from "@/lib/api";
import { getStageLabel, getStageBadgeClass, formatRelativeTime } from "@/lib/utils";
import { Briefcase, MessageSquare, Users, Clock, TrendingUp, Zap, CheckCircle, Bot } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface DashboardData {
  stats: Record<string, number>;
  pipeline_overview: Record<string, number>;
  recent_applications: Array<{
    id: number;
    candidate: { name: string; email: string };
    job: { title: string };
    pipeline_stage: string;
    overall_score: number;
    created_at: string;
  }>;
  attention_needed: Array<{
    id: number;
    candidate: { name: string };
    job: { title: string };
    overall_score: number;
    ai_recommendation: string;
  }>;
}

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [copilotQ, setCopilotQ] = useState("");
  const [copilotAnswer, setCopilotAnswer] = useState("");
  const [copilotLoading, setCopilotLoading] = useState(false);

  useEffect(() => {
    dashboardApi.get().then((res) => setData(res.data)).catch(() => toast.error("خطأ في تحميل البيانات"));
  }, []);

  const askCopilot = async () => {
    if (!copilotQ.trim()) return;
    setCopilotLoading(true);
    setCopilotAnswer("");
    try {
      const res = await dashboardApi.copilot(copilotQ);
      setCopilotAnswer(res.data.answer);
    } catch {
      toast.error("خطأ في مساعد الذكاء الاصطناعي");
    } finally {
      setCopilotLoading(false);
    }
  };

  if (!data) {
    return (
      <DashboardLayout>
        <div className="space-y-4 animate-pulse">
          <div className="grid grid-cols-4 gap-4">{[...Array(4)].map((_, i) => <div key={i} className="h-28 bg-white rounded-xl skeleton" />)}</div>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Page Header */}
        <div>
          <h1 className="text-2xl font-bold text-gray-900">لوحة التحكم</h1>
          <p className="text-sm text-gray-500 mt-0.5">مرحباً! إليك ملخص النشاط اليوم.</p>
        </div>

        {/* Stats */}
        <StatsGrid cols={4}>
          <StatCard label="الوظائف النشطة" value={data.stats.active_jobs ?? 0} icon={<Briefcase className="w-5 h-5" />} color="purple" />
          <StatCard label="إجمالي المتقدمين" value={data.stats.total_applications ?? 0} icon={<Users className="w-5 h-5" />} color="blue" />
          <StatCard label="مقابلات اليوم" value={data.stats.interviews_today ?? 0} icon={<MessageSquare className="w-5 h-5" />} color="green" />
          <StatCard label="بانتظار المراجعة" value={data.stats.pending_review ?? 0} icon={<Clock className="w-5 h-5" />} color="orange" />
        </StatsGrid>

        <div className="grid grid-cols-3 gap-6">
          {/* Recent Applications */}
          <div className="col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200">
              <h2 className="font-bold text-gray-900 text-sm">آخر الطلبات</h2>
              <Link href="/applications" className="text-xs text-violet-600 hover:text-violet-700 font-medium">عرض الكل</Link>
            </div>
            <div className="divide-y divide-gray-100">
              {data.recent_applications?.length === 0 && (
                <div className="px-5 py-8 text-center text-sm text-gray-400">لا توجد طلبات حتى الآن</div>
              )}
              {data.recent_applications?.map((app) => (
                <Link key={app.id} href={`/applications/${app.id}`} className="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                  <div className="w-9 h-9 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-sm flex-shrink-0">
                    {app.candidate.name.charAt(0)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-900">{app.candidate.name}</p>
                    <p className="text-xs text-gray-400 truncate">{app.job.title}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    {app.overall_score > 0 && <ScoreBadge score={app.overall_score} />}
                    <Badge variant={(getStageBadgeClass(app.pipeline_stage).replace("badge-", "") as "purple" | "green" | "yellow" | "red" | "gray" | "blue") || "gray"} size="sm">
                      {getStageLabel(app.pipeline_stage)}
                    </Badge>
                  </div>
                  <span className="text-xs text-gray-400 flex-shrink-0">{formatRelativeTime(app.created_at)}</span>
                </Link>
              ))}
            </div>
          </div>

          {/* AI Copilot */}
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col">
            <div className="flex items-center gap-2.5 px-5 py-4 border-b border-gray-200">
              <div className="w-7 h-7 bg-violet-100 rounded-lg flex items-center justify-center">
                <Bot className="w-4 h-4 text-violet-600" />
              </div>
              <div>
                <h2 className="font-bold text-gray-900 text-sm">مساعد التوظيف الذكي</h2>
                <p className="text-xs text-gray-400">اسأل عن أي مرشح أو وظيفة</p>
              </div>
            </div>
            <div className="flex-1 p-4">
              {copilotAnswer && (
                <div className="mb-3 p-3 bg-violet-50 rounded-lg text-sm text-violet-900 leading-relaxed">{copilotAnswer}</div>
              )}
              <div className="space-y-2">
                {["من أفضل المرشحين للوظائف الإدارية؟", "من لديه أعلى مهارات التواصل؟", "قارن أفضل 3 مرشحين"].map((q) => (
                  <button key={q} onClick={() => { setCopilotQ(q); }} className="w-full text-right text-xs px-3 py-2 bg-gray-50 hover:bg-violet-50 hover:text-violet-700 rounded-lg text-gray-600 transition-colors">
                    {q}
                  </button>
                ))}
              </div>
            </div>
            <div className="px-4 pb-4">
              <div className="flex gap-2">
                <input value={copilotQ} onChange={(e) => setCopilotQ(e.target.value)} onKeyDown={(e) => e.key === "Enter" && askCopilot()} placeholder="اسأل سؤالاً..." className="flex-1 px-3 py-2 text-sm bg-gray-100 rounded-lg border-0 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                <button onClick={askCopilot} disabled={copilotLoading} className="px-3 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 disabled:opacity-50 transition-colors">
                  {copilotLoading ? "..." : "إرسال"}
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Pipeline Overview */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <h2 className="font-bold text-gray-900 text-sm mb-4">توزيع خط المرشحين</h2>
          <div className="grid grid-cols-5 gap-3">
            {Object.entries(data.pipeline_overview || {}).map(([stage, count]) => (
              <Link key={stage} href={`/pipeline?stage=${stage}`} className="flex flex-col items-center p-3 rounded-xl bg-gray-50 hover:bg-violet-50 transition-colors">
                <span className="text-xl font-bold text-gray-900">{count}</span>
                <span className="text-xs text-gray-500 mt-1 text-center">{getStageLabel(stage)}</span>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
