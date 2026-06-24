"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { dashboardApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { BarChart3, Brain, DollarSign, Zap } from "lucide-react";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface AnalyticsData {
  summary: { total_tokens: number; total_cost: number; total_requests: number; avg_cost_per_request: number };
  by_feature: Array<{ feature: string; tokens: number; cost: number; requests: number }>;
  by_day: Array<{ date: string; tokens: number; cost: number; requests: number }>;
  by_model: Array<{ model: string; tokens: number; cost: number }>;
}

const FEATURE_LABELS: Record<string, string> = {
  interview: "المقابلات", evaluation: "التقييم", job_description: "وصف الوظائف",
  cv_analysis: "تحليل السيرة", copilot: "المساعد الذكي", offer_generation: "توليد العروض",
  matching: "مطابقة المرشحين",
};

export default function AIAnalyticsPage() {
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState("30");

  useEffect(() => {
    dashboardApi.aiAnalytics(period).then((res) => setData(res.data))
      .catch(() => toast.error("خطأ في التحميل"))
      .finally(() => setLoading(false));
  }, [period]);

  const maxTokens = data ? Math.max(...(data.by_feature.map((f) => f.tokens) || [1])) : 1;
  const maxDaily = data ? Math.max(...(data.by_day.map((d) => d.tokens) || [1])) : 1;

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">تحليلات الذكاء الاصطناعي</h1>
            <p className="text-sm text-gray-500 mt-0.5">استخدام الرموز والتكاليف</p>
          </div>
          <select value={period} onChange={(e) => setPeriod(e.target.value)}
            className="px-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
            <option value="7">آخر 7 أيام</option>
            <option value="30">آخر 30 يوم</option>
            <option value="90">آخر 90 يوم</option>
          </select>
        </div>

        {loading ? (
          <div className="grid grid-cols-4 gap-4">{[...Array(4)].map((_, i) => <div key={i} className="h-28 skeleton rounded-xl" />)}</div>
        ) : data ? (
          <>
            {/* Summary stats */}
            <div className="grid grid-cols-4 gap-4">
              {[
                { label: "إجمالي الرموز", value: data.summary.total_tokens.toLocaleString(), icon: <Zap className="w-5 h-5" />, color: "bg-violet-100 text-violet-600" },
                { label: "التكلفة الإجمالية", value: `$${data.summary.total_cost.toFixed(2)}`, icon: <DollarSign className="w-5 h-5" />, color: "bg-green-100 text-green-600" },
                { label: "الطلبات", value: data.summary.total_requests.toLocaleString(), icon: <BarChart3 className="w-5 h-5" />, color: "bg-blue-100 text-blue-600" },
                { label: "متوسط التكلفة/طلب", value: `$${data.summary.avg_cost_per_request.toFixed(4)}`, icon: <Brain className="w-5 h-5" />, color: "bg-orange-100 text-orange-600" },
              ].map((s) => (
                <div key={s.label} className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                  <div className={`w-10 h-10 rounded-xl ${s.color} flex items-center justify-center mb-3`}>{s.icon}</div>
                  <p className="text-xs text-gray-500 mb-1">{s.label}</p>
                  <p className="text-2xl font-bold text-gray-900">{s.value}</p>
                </div>
              ))}
            </div>

            <div className="grid grid-cols-2 gap-5">
              {/* By feature */}
              <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 className="font-bold text-gray-900 text-sm mb-4">الاستخدام حسب الميزة</h3>
                <div className="space-y-3">
                  {data.by_feature.sort((a, b) => b.tokens - a.tokens).map((f) => (
                    <div key={f.feature}>
                      <div className="flex justify-between text-xs mb-1">
                        <span className="text-gray-600 font-medium">{FEATURE_LABELS[f.feature] || f.feature}</span>
                        <span className="text-gray-400">{f.tokens.toLocaleString()} رمز • ${f.cost.toFixed(3)}</span>
                      </div>
                      <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div className="h-full bg-violet-500 rounded-full transition-all" style={{ width: `${(f.tokens / maxTokens) * 100}%` }} />
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* By model */}
              <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 className="font-bold text-gray-900 text-sm mb-4">الاستخدام حسب النموذج</h3>
                <div className="space-y-3">
                  {data.by_model.map((m) => (
                    <div key={m.model} className="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                      <div>
                        <p className="text-sm font-medium text-gray-900">{m.model}</p>
                        <p className="text-xs text-gray-400">{m.tokens.toLocaleString()} رمز</p>
                      </div>
                      <div className="text-right">
                        <p className="text-sm font-bold text-gray-900">${m.cost.toFixed(2)}</p>
                        <p className="text-xs text-gray-400">تكلفة</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Daily usage */}
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
              <h3 className="font-bold text-gray-900 text-sm mb-4">الاستخدام اليومي (الرموز)</h3>
              <div className="flex items-end gap-1 h-32">
                {data.by_day.map((d) => (
                  <div key={d.date} className="flex-1 flex flex-col items-center gap-1 group relative">
                    <div className="absolute bottom-full mb-1 hidden group-hover:block bg-gray-900 text-white text-xs px-2 py-1 rounded whitespace-nowrap z-10">
                      {d.tokens.toLocaleString()} رمز • ${d.cost.toFixed(3)}
                    </div>
                    <div className="w-full bg-violet-200 rounded-t-sm hover:bg-violet-400 transition-colors cursor-pointer"
                      style={{ height: `${Math.max((d.tokens / maxDaily) * 100, 4)}%` }} />
                    {data.by_day.length <= 14 && (
                      <span className="text-xs text-gray-400" style={{ fontSize: "9px" }}>{d.date.slice(5)}</span>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </>
        ) : null}
      </div>
    </DashboardLayout>
  );
}
