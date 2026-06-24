"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge, ScoreBadge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input, Select } from "@/components/ui/Input";
import { applicationsApi } from "@/lib/api";
import { formatDate, getRecommendationLabel, getStageLabel } from "@/lib/utils";
import { BarChart2, Search, SlidersHorizontal, User } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface ApplicationRow {
  id: number;
  pipeline_stage: string;
  overall_score: number;
  ai_recommendation: string;
  created_at: string;
  candidate: { id: number; name: string; email: string; phone?: string };
  job: { id: number; title: string };
  interview_session?: { status: string };
}

export default function CandidatesPage() {
  const [apps, setApps] = useState<ApplicationRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [stageFilter, setStageFilter] = useState("");
  const [recFilter, setRecFilter] = useState("");
  const [jobFilter, setJobFilter] = useState("");
  const [comparing, setComparing] = useState<number[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    setPage(1);
  }, [search, stageFilter, recFilter, jobFilter]);

  useEffect(() => {
    loadApps();
  }, [search, stageFilter, recFilter, jobFilter, page]);

  const loadApps = async () => {
    setLoading(true);
    try {
      const res = await applicationsApi.list({ search, stage: stageFilter, recommendation: recFilter, job_id: jobFilter, page });
      setApps(res.data.data || res.data);
      setTotal(res.data.total || 0);
    } catch {
      toast.error("خطأ في تحميل البيانات");
    } finally {
      setLoading(false);
    }
  };

  const toggleCompare = (id: number) => {
    setComparing((p) => p.includes(id) ? p.filter((x) => x !== id) : p.length < 3 ? [...p, id] : p);
  };

  const recColor: Record<string, "green" | "blue" | "yellow" | "red"> = {
    strong_recommendation: "green", suitable: "blue", possible_fit: "yellow", not_recommended: "red",
  };

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">المرشحون</h1>
            <p className="text-sm text-gray-500 mt-0.5">{total} طلب</p>
          </div>
          {comparing.length >= 2 && (
            <Link href={`/candidates/compare?ids=${comparing.join(",")}`}>
              <Button icon={<BarChart2 className="w-4 h-4" />} variant="primary">
                مقارنة ({comparing.length})
              </Button>
            </Link>
          )}
        </div>

        {/* Filters */}
        <div className="flex gap-3 flex-wrap">
          <Input placeholder="بحث بالاسم أو البريد..." leftIcon={<Search className="w-4 h-4" />}
            value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
          <Select value={stageFilter} onChange={(e) => setStageFilter(e.target.value)} className="max-w-[160px]">
            <option value="">كل المراحل</option>
            {["applied", "ai_screening", "qualified", "tech_interview", "manager_interview", "final_review", "offer", "hired", "disqualified", "rejected"].map((s) => (
              <option key={s} value={s}>{getStageLabel(s)}</option>
            ))}
          </Select>
          <Select value={recFilter} onChange={(e) => setRecFilter(e.target.value)} className="max-w-[160px]">
            <option value="">كل التوصيات</option>
            <option value="strong_recommendation">موصى به بشدة</option>
            <option value="suitable">مناسب</option>
            <option value="possible_fit">محتمل</option>
            <option value="not_recommended">غير مناسب</option>
          </Select>
        </div>

        {comparing.length > 0 && (
          <div className="flex items-center gap-2 px-4 py-2.5 bg-violet-50 border border-violet-200 rounded-xl text-sm text-violet-700">
            <SlidersHorizontal className="w-4 h-4" />
            <span>اخترت {comparing.length}/3 مرشحين للمقارنة</span>
            <button onClick={() => setComparing([])} className="mr-auto text-xs text-violet-500 hover:text-violet-700">إلغاء</button>
          </div>
        )}

        {/* Table */}
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 bg-gray-50">
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500 w-8"></th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500">المرشح</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500">الوظيفة</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500">المرحلة</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500">الدرجة</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500">التوصية</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500">تاريخ التقدم</th>
                <th className="px-4 py-3 text-right text-xs font-semibold text-gray-500">إجراء</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                [...Array(5)].map((_, i) => (
                  <tr key={i}><td colSpan={8} className="px-4 py-3"><div className="h-8 skeleton rounded" /></td></tr>
                ))
              ) : apps.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-16 text-center">
                    <User className="w-8 h-8 text-gray-300 mx-auto mb-2" />
                    <p className="text-sm text-gray-400">لا يوجد مرشحون</p>
                  </td>
                </tr>
              ) : (
                apps.map((app) => (
                  <tr key={app.id} className={`hover:bg-gray-50 transition-colors ${comparing.includes(app.id) ? "bg-violet-50" : ""}`}>
                    <td className="px-4 py-3">
                      <input type="checkbox" checked={comparing.includes(app.id)} onChange={() => toggleCompare(app.id)}
                        className="w-4 h-4 text-violet-600 rounded border-gray-300" />
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-xs flex-shrink-0">
                          {app.candidate.name.charAt(0)}
                        </div>
                        <div>
                          <p className="text-sm font-semibold text-gray-900">{app.candidate.name}</p>
                          <p className="text-xs text-gray-400">{app.candidate.email}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-600">{app.job.title}</td>
                    <td className="px-4 py-3">
                      <Badge variant="purple" size="sm">{getStageLabel(app.pipeline_stage)}</Badge>
                    </td>
                    <td className="px-4 py-3">
                      {app.overall_score > 0 ? <ScoreBadge score={app.overall_score} /> : <span className="text-gray-300 text-xs">—</span>}
                    </td>
                    <td className="px-4 py-3">
                      {app.ai_recommendation ? (
                        <Badge variant={recColor[app.ai_recommendation] || "gray"} size="sm">
                          {getRecommendationLabel(app.ai_recommendation)}
                        </Badge>
                      ) : <span className="text-gray-300 text-xs">—</span>}
                    </td>
                    <td className="px-4 py-3 text-xs text-gray-400">{formatDate(app.created_at)}</td>
                    <td className="px-4 py-3">
                      <Link href={`/applications/${app.id}`}
                        className="text-xs text-violet-600 hover:text-violet-700 font-medium border border-violet-200 rounded-lg px-2.5 py-1 hover:bg-violet-50 transition-colors">
                        عرض
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>

          {/* Pagination */}
          {total > 20 && (
            <div className="px-4 py-3 border-t border-gray-200 flex items-center justify-between text-sm">
              <span className="text-gray-500">عرض {(page - 1) * 20 + 1}–{Math.min(page * 20, total)} من {total}</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => p - 1)} disabled={page === 1}
                  className="px-3 py-1 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40">السابق</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={page * 20 >= total}
                  className="px-3 py-1 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40">التالي</button>
              </div>
            </div>
          )}
        </div>
      </div>
    </DashboardLayout>
  );
}
