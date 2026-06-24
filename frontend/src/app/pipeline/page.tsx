"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { ScoreBadge } from "@/components/ui/Badge";
import { applicationsApi } from "@/lib/api";
import { getStageLabel } from "@/lib/utils";
import { GripVertical, Loader2 } from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import toast from "react-hot-toast";

const STAGES = [
  "applied", "ai_screening", "qualified", "tech_interview",
  "manager_interview", "final_review", "offer", "hired",
];
const REJECT_STAGES = ["disqualified", "rejected", "withdrawn"];

const STAGE_COLORS: Record<string, string> = {
  applied: "bg-gray-100 border-gray-200",
  ai_screening: "bg-violet-50 border-violet-200",
  qualified: "bg-blue-50 border-blue-200",
  tech_interview: "bg-indigo-50 border-indigo-200",
  manager_interview: "bg-purple-50 border-purple-200",
  final_review: "bg-amber-50 border-amber-200",
  offer: "bg-orange-50 border-orange-200",
  hired: "bg-green-50 border-green-200",
  disqualified: "bg-red-50 border-red-200",
  rejected: "bg-red-50 border-red-200",
  withdrawn: "bg-gray-50 border-gray-200",
};
const STAGE_HEADER_COLORS: Record<string, string> = {
  applied: "bg-gray-500",
  ai_screening: "bg-violet-500",
  qualified: "bg-blue-500",
  tech_interview: "bg-indigo-500",
  manager_interview: "bg-purple-500",
  final_review: "bg-amber-500",
  offer: "bg-orange-500",
  hired: "bg-green-500",
  disqualified: "bg-red-500",
  rejected: "bg-red-400",
  withdrawn: "bg-gray-400",
};

interface Application {
  id: number;
  candidate: { name: string; email: string };
  job: { title: string };
  pipeline_stage: string;
  overall_score: number;
  ai_recommendation: string;
  created_at: string;
}

export default function PipelinePage() {
  const [apps, setApps] = useState<Application[]>([]);
  const [loading, setLoading] = useState(true);
  const [showRejected, setShowRejected] = useState(false);
  const [dragging, setDragging] = useState<Application | null>(null);
  const [dragOver, setDragOver] = useState<string | null>(null);
  const dragItem = useRef<Application | null>(null);

  useEffect(() => {
    loadApps();
  }, []);

  const loadApps = async () => {
    try {
      const res = await applicationsApi.list({ per_page: 200 });
      setApps(res.data.data || res.data);
    } catch {
      toast.error("خطأ في تحميل البيانات");
    } finally {
      setLoading(false);
    }
  };

  const moveApp = async (app: Application, toStage: string) => {
    if (app.pipeline_stage === toStage) return;
    setApps((prev) => prev.map((a) => a.id === app.id ? { ...a, pipeline_stage: toStage } : a));
    try {
      await applicationsApi.updateStage(app.id, toStage);
    } catch {
      toast.error("خطأ في تحريك البطاقة");
      setApps((prev) => prev.map((a) => a.id === app.id ? { ...a, pipeline_stage: app.pipeline_stage } : a));
    }
  };

  const onDragStart = (app: Application) => {
    dragItem.current = app;
    setDragging(app);
  };

  const onDrop = (stage: string) => {
    if (dragItem.current && dragItem.current.pipeline_stage !== stage) {
      moveApp(dragItem.current, stage);
    }
    setDragging(null);
    setDragOver(null);
    dragItem.current = null;
  };

  const visibleStages = [...STAGES, ...(showRejected ? REJECT_STAGES : [])];

  const grouped = visibleStages.reduce<Record<string, Application[]>>((acc, s) => {
    acc[s] = apps.filter((a) => a.pipeline_stage === s);
    return acc;
  }, {});

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-64">
          <Loader2 className="w-8 h-8 text-violet-500 animate-spin" />
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">خط المرشحين</h1>
            <p className="text-sm text-gray-500 mt-0.5">{apps.length} طلب • اسحب البطاقات للتنقل بين المراحل</p>
          </div>
          <button onClick={() => setShowRejected(!showRejected)}
            className={`text-xs px-3 py-1.5 rounded-lg font-medium border transition-colors ${
              showRejected ? "bg-red-50 text-red-600 border-red-200" : "bg-white text-gray-500 border-gray-200 hover:border-gray-300"
            }`}>
            {showRejected ? "إخفاء المرفوضين" : "إظهار المرفوضين"}
          </button>
        </div>

        {/* Kanban */}
        <div className="overflow-x-auto pb-4">
          <div className="flex gap-3 min-w-max">
            {visibleStages.map((stage) => (
              <div key={stage} className="w-64 flex-shrink-0">
                {/* Column header */}
                <div className={`flex items-center justify-between px-3 py-2 rounded-t-xl ${STAGE_HEADER_COLORS[stage]} mb-0`}>
                  <span className="text-white text-xs font-bold">{getStageLabel(stage)}</span>
                  <span className="text-white/70 text-xs bg-black/20 px-1.5 py-0.5 rounded-full">{grouped[stage]?.length || 0}</span>
                </div>

                {/* Drop zone */}
                <div
                  onDragOver={(e) => { e.preventDefault(); setDragOver(stage); }}
                  onDragLeave={() => setDragOver(null)}
                  onDrop={() => onDrop(stage)}
                  className={`min-h-[500px] p-2 rounded-b-xl border-2 transition-all ${STAGE_COLORS[stage]} ${
                    dragOver === stage ? "border-dashed border-violet-400 bg-violet-50/50" : ""
                  }`}
                >
                  <div className="space-y-2">
                    {grouped[stage]?.map((app) => (
                      <KanbanCard
                        key={app.id}
                        app={app}
                        isDragging={dragging?.id === app.id}
                        onDragStart={() => onDragStart(app)}
                        onDragEnd={() => { setDragging(null); dragItem.current = null; }}
                      />
                    ))}
                    {grouped[stage]?.length === 0 && (
                      <div className="flex items-center justify-center h-20 text-gray-300 text-xs">
                        اسحب هنا
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

function KanbanCard({ app, isDragging, onDragStart, onDragEnd }: {
  app: Application;
  isDragging: boolean;
  onDragStart: () => void;
  onDragEnd: () => void;
}) {
  return (
    <div
      draggable
      onDragStart={onDragStart}
      onDragEnd={onDragEnd}
      className={`bg-white rounded-lg border border-gray-200 shadow-sm p-3 cursor-grab active:cursor-grabbing transition-all ${
        isDragging ? "opacity-40 scale-95" : "hover:shadow-md hover:-translate-y-0.5"
      }`}
    >
      <div className="flex items-start justify-between gap-2 mb-2">
        <div className="flex-1 min-w-0">
          <p className="text-xs font-bold text-gray-900 truncate">{app.candidate.name}</p>
          <p className="text-xs text-gray-400 truncate">{app.job.title}</p>
        </div>
        <div className="flex items-center gap-1 flex-shrink-0">
          {app.overall_score > 0 && <ScoreBadge score={app.overall_score} />}
          <GripVertical className="w-3.5 h-3.5 text-gray-300" />
        </div>
      </div>

      {app.ai_recommendation && (
        <div className={`text-xs px-2 py-0.5 rounded-full inline-block mb-2 ${
          app.ai_recommendation === "strong_recommendation" ? "bg-green-100 text-green-700" :
          app.ai_recommendation === "suitable" ? "bg-blue-100 text-blue-700" :
          app.ai_recommendation === "possible_fit" ? "bg-yellow-100 text-yellow-700" :
          "bg-red-100 text-red-700"
        }`}>
          {app.ai_recommendation === "strong_recommendation" ? "موصى به بشدة" :
           app.ai_recommendation === "suitable" ? "مناسب" :
           app.ai_recommendation === "possible_fit" ? "محتمل" : "غير مناسب"}
        </div>
      )}

      <Link href={`/applications/${app.id}`}
        className="block text-center text-xs text-violet-600 hover:text-violet-700 font-medium border border-violet-200 rounded-md py-1 hover:bg-violet-50 transition-colors">
        عرض التفاصيل
      </Link>
    </div>
  );
}
