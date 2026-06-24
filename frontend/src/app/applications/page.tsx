"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge, ScoreBadge } from "@/components/ui/Badge";
import { Input, Select } from "@/components/ui/Input";
import { applicationsApi } from "@/lib/api";
import { formatDate, getRecommendationLabel, getStageLabel } from "@/lib/utils";
import { Search } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface App {
  id: number; pipeline_stage: string; overall_score: number; ai_recommendation: string;
  created_at: string; candidate: { name: string; email: string }; job: { title: string };
}

const REC_COLOR: Record<string, "green" | "blue" | "yellow" | "red"> = {
  strong_recommendation: "green", suitable: "blue", possible_fit: "yellow", not_recommended: "red",
};

export default function ApplicationsPage() {
  const [apps, setApps] = useState<App[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [stageFilter, setStageFilter] = useState("");
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  useEffect(() => { setPage(1); }, [search, stageFilter]);
  useEffect(() => { load(); }, [search, stageFilter, page]);

  const load = async () => {
    setLoading(true);
    try {
      const res = await applicationsApi.list({ search, stage: stageFilter, page });
      setApps(res.data.data || res.data);
      setTotal(res.data.total || 0);
    } catch { toast.error("خطأ في التحميل"); }
    finally { setLoading(false); }
  };

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">طلبات التوظيف</h1>
          <p className="text-sm text-gray-500 mt-0.5">{total} طلب</p>
        </div>
        <div className="flex gap-3">
          <Input placeholder="بحث..." leftIcon={<Search className="w-4 h-4" />} value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
          <Select value={stageFilter} onChange={(e) => setStageFilter(e.target.value)} className="max-w-[160px]">
            <option value="">كل المراحل</option>
            {["applied","ai_screening","qualified","tech_interview","manager_interview","final_review","offer","hired","disqualified","rejected"].map((s) => (
              <option key={s} value={s}>{getStageLabel(s)}</option>
            ))}
          </Select>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 bg-gray-50">
                {["المرشح", "الوظيفة", "المرحلة", "الدرجة", "التوصية", "تاريخ التقدم", ""].map((h) => (
                  <th key={h} className="px-4 py-3 text-right text-xs font-semibold text-gray-500">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? [...Array(5)].map((_, i) => (
                <tr key={i}><td colSpan={7} className="px-4 py-3"><div className="h-8 skeleton rounded" /></td></tr>
              )) : apps.map((app) => (
                <tr key={app.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-xs flex-shrink-0">{app.candidate.name.charAt(0)}</div>
                      <div><p className="text-sm font-semibold text-gray-900">{app.candidate.name}</p><p className="text-xs text-gray-400">{app.candidate.email}</p></div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600">{app.job.title}</td>
                  <td className="px-4 py-3"><Badge variant="purple" size="sm">{getStageLabel(app.pipeline_stage)}</Badge></td>
                  <td className="px-4 py-3">{app.overall_score > 0 ? <ScoreBadge score={app.overall_score} /> : <span className="text-gray-300 text-xs">—</span>}</td>
                  <td className="px-4 py-3">
                    {app.ai_recommendation ? <Badge variant={REC_COLOR[app.ai_recommendation] || "gray"} size="sm">{getRecommendationLabel(app.ai_recommendation)}</Badge> : <span className="text-gray-300 text-xs">—</span>}
                  </td>
                  <td className="px-4 py-3 text-xs text-gray-400">{formatDate(app.created_at)}</td>
                  <td className="px-4 py-3">
                    <Link href={`/applications/${app.id}`} className="text-xs text-violet-600 hover:text-violet-700 font-medium border border-violet-200 rounded-lg px-2.5 py-1 hover:bg-violet-50 transition-colors">عرض</Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {total > 20 && (
            <div className="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm">
              <span className="text-gray-500">عرض {(page-1)*20+1}–{Math.min(page*20,total)} من {total}</span>
              <div className="flex gap-2">
                <button onClick={() => setPage(p=>p-1)} disabled={page===1} className="px-3 py-1 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40">السابق</button>
                <button onClick={() => setPage(p=>p+1)} disabled={page*20>=total} className="px-3 py-1 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40">التالي</button>
              </div>
            </div>
          )}
        </div>
      </div>
    </DashboardLayout>
  );
}
