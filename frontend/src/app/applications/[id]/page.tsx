"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge, ScoreBadge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { applicationsApi } from "@/lib/api";
import { formatDate, formatRelativeTime, getRecommendationLabel, getStageLabel } from "@/lib/utils";
import {
  AlertTriangle, Brain, CheckCircle, ChevronLeft, Clock, Download, FileText,
  MessageSquare, RefreshCw, Shield, Star, TrendingUp, User, XCircle
} from "lucide-react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

const PIPELINE_STAGES = [
  "applied", "ai_screening", "qualified", "disqualified",
  "tech_interview", "manager_interview", "final_review",
  "offer", "hired", "rejected", "withdrawn",
];

interface ApplicationDetail {
  id: number;
  pipeline_stage: string;
  overall_score: number;
  ai_recommendation: string;
  cv_match_score: number;
  created_at: string;
  candidate: {
    id: number; name: string; email: string; phone?: string;
    location?: string; linkedin_url?: string; current_position?: string;
  };
  job: { id: number; title: string; department?: { name: string }; seniority: string };
  interview_session?: {
    id: number; status: string; questions_asked: number; max_questions: number;
    started_at?: string; completed_at?: string;
  };
  ai_evaluation?: {
    id: number;
    executive_summary: string;
    key_strengths: string[];
    development_areas: string[];
    detailed_analysis: string;
    overall_score: number;
    created_at: string;
    skill_scores: Array<{ skill_name: string; score: number; weight: number; notes: string }>;
    behavioral_analysis?: {
      disc_profile: string; disc_scores: Record<string, number>;
      big_five: Record<string, number>; work_style: string; team_fit: string; leadership_potential: string;
    };
    risk_flags: Array<{ flag_type: string; severity: string; description: string; evidence: string }>;
  };
  transcript?: Array<{ role: string; content: string; created_at: string }>;
  timeline?: Array<{ action: string; created_at: string; user?: { name: string } }>;
  human_interviews?: Array<{ id: number; scheduled_at: string; status: string; interviewer?: { name: string }; overall_rating?: number }>;
  criteria_scores?: Array<{ criterion: string; score: number; notes: string }>;
  cv?: { file_url: string; ai_analysis?: string };
}

const TABS = [
  { id: "overview", label: "نظرة عامة", icon: Star },
  { id: "skills", label: "المهارات", icon: TrendingUp },
  { id: "behavioral", label: "التحليل السلوكي", icon: Brain },
  { id: "risks", label: "مؤشرات الخطر", icon: AlertTriangle },
  { id: "transcript", label: "النص الكامل", icon: MessageSquare },
  { id: "cv", label: "السيرة الذاتية", icon: FileText },
  { id: "timeline", label: "السجل", icon: Clock },
];

export default function ApplicationDetailPage() {
  const params = useParams();
  const id = params.id as string;
  const [app, setApp] = useState<ApplicationDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState("overview");
  const [movingStage, setMovingStage] = useState(false);
  const [reEvaluating, setReEvaluating] = useState(false);

  useEffect(() => {
    applicationsApi.get(Number(id)).then((res) => {
      setApp(res.data);
    }).catch(() => toast.error("خطأ في تحميل البيانات")).finally(() => setLoading(false));
  }, [id]);

  const moveStage = async (stage: string) => {
    if (!app) return;
    setMovingStage(true);
    try {
      await applicationsApi.updateStage(app.id, stage);
      setApp((p) => p ? { ...p, pipeline_stage: stage } : p);
      toast.success(`تم النقل إلى: ${getStageLabel(stage)}`);
    } catch { toast.error("خطأ في تغيير المرحلة"); }
    finally { setMovingStage(false); }
  };

  const reEvaluate = async () => {
    if (!app) return;
    setReEvaluating(true);
    try {
      await applicationsApi.reEvaluate(app.id);
      toast.success("جاري إعادة التقييم، سيستغرق بضع ثوان...");
      setTimeout(() => window.location.reload(), 5000);
    } catch { toast.error("خطأ في إعادة التقييم"); }
    finally { setReEvaluating(false); }
  };

  if (loading) {
    return (
      <DashboardLayout>
        <div className="space-y-4 animate-pulse">
          <div className="h-32 bg-white rounded-xl skeleton" />
          <div className="h-64 bg-white rounded-xl skeleton" />
        </div>
      </DashboardLayout>
    );
  }

  if (!app) return <DashboardLayout><div className="text-center py-20 text-gray-400">الطلب غير موجود</div></DashboardLayout>;

  const rec = app.ai_recommendation;
  const recColor = rec === "strong_recommendation" ? "text-green-600 bg-green-50 border-green-200" :
    rec === "suitable" ? "text-blue-600 bg-blue-50 border-blue-200" :
    rec === "possible_fit" ? "text-yellow-600 bg-yellow-50 border-yellow-200" :
    "text-red-600 bg-red-50 border-red-200";

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* Breadcrumb */}
        <div className="flex items-center gap-2 text-sm text-gray-400">
          <Link href="/applications" className="hover:text-violet-600 flex items-center gap-1">
            <ChevronLeft className="w-3.5 h-3.5" /> الطلبات
          </Link>
          <span>/</span>
          <span className="text-gray-700 font-medium">{app.candidate.name}</span>
        </div>

        {/* Hero Card */}
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div className="bg-gradient-to-r from-violet-600 to-indigo-600 px-6 py-5">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center text-white text-xl font-bold">
                  {app.candidate.name.charAt(0)}
                </div>
                <div>
                  <h1 className="text-xl font-bold text-white">{app.candidate.name}</h1>
                  <p className="text-violet-200 text-sm">{app.candidate.email}</p>
                  {app.candidate.current_position && <p className="text-violet-300 text-xs mt-0.5">{app.candidate.current_position}</p>}
                </div>
              </div>
              <div className="flex items-center gap-3">
                {app.overall_score > 0 && (
                  <div className="text-center">
                    <div className="text-3xl font-bold text-white">{app.overall_score}</div>
                    <div className="text-violet-200 text-xs">الدرجة الكلية</div>
                  </div>
                )}
              </div>
            </div>
            <div className="mt-4 flex items-center gap-3 flex-wrap">
              <span className="text-xs bg-white/10 text-white px-3 py-1 rounded-full">{app.job.title}</span>
              {app.job.department && <span className="text-xs bg-white/10 text-white px-3 py-1 rounded-full">{app.job.department.name}</span>}
              <span className="text-xs bg-white/10 text-white px-3 py-1 rounded-full">{formatDate(app.created_at)}</span>
            </div>
          </div>

          {/* Action bar */}
          <div className="px-6 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-3">
              <Badge variant="purple" size="sm">{getStageLabel(app.pipeline_stage)}</Badge>
              {rec && (
                <span className={`text-xs font-bold px-3 py-1 rounded-full border ${recColor}`}>
                  {getRecommendationLabel(rec)}
                </span>
              )}
              {app.cv_match_score > 0 && (
                <span className="text-xs text-gray-500">تطابق CV: <strong>{app.cv_match_score}%</strong></span>
              )}
            </div>
            <div className="flex items-center gap-2">
              <Button variant="ghost" size="sm" icon={<RefreshCw className={`w-3.5 h-3.5 ${reEvaluating ? "animate-spin" : ""}`} />} loading={reEvaluating} onClick={reEvaluate}>
                إعادة تقييم
              </Button>
              <Button variant="ghost" size="sm" icon={<Download className="w-3.5 h-3.5" />} onClick={() => applicationsApi.export(app.id)}>
                تصدير PDF
              </Button>
            </div>
          </div>

          {/* Pipeline stage selector */}
          <div className="px-6 py-3 flex items-center gap-2 overflow-x-auto">
            <span className="text-xs text-gray-400 flex-shrink-0">نقل إلى:</span>
            {PIPELINE_STAGES.filter((s) => s !== app.pipeline_stage).map((stage) => (
              <button key={stage} onClick={() => moveStage(stage)} disabled={movingStage}
                className="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-violet-100 hover:text-violet-700 text-gray-600 flex-shrink-0 transition-colors disabled:opacity-50">
                {getStageLabel(stage)}
              </button>
            ))}
          </div>
        </div>

        {/* Tabs + Content */}
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div className="flex border-b border-gray-200 overflow-x-auto">
            {TABS.map((tab) => (
              <button key={tab.id} onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-1.5 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors border-b-2 -mb-px ${
                  activeTab === tab.id ? "border-violet-600 text-violet-600" : "border-transparent text-gray-500 hover:text-gray-700"
                }`}>
                <tab.icon className="w-3.5 h-3.5" />
                {tab.label}
              </button>
            ))}
          </div>

          <div className="p-6">
            {/* Overview Tab */}
            {activeTab === "overview" && (
              <div className="space-y-5">
                {app.ai_evaluation?.executive_summary && (
                  <div>
                    <h3 className="text-sm font-bold text-gray-900 mb-2">الملخص التنفيذي</h3>
                    <p className="text-sm text-gray-600 leading-relaxed bg-violet-50 rounded-xl p-4">{app.ai_evaluation.executive_summary}</p>
                  </div>
                )}
                <div className="grid grid-cols-2 gap-4">
                  {app.ai_evaluation?.key_strengths && app.ai_evaluation.key_strengths.length > 0 && (
                    <div>
                      <h3 className="text-sm font-bold text-green-700 mb-2 flex items-center gap-1.5">
                        <CheckCircle className="w-4 h-4" /> نقاط القوة
                      </h3>
                      <ul className="space-y-1.5">
                        {app.ai_evaluation.key_strengths.map((s, i) => (
                          <li key={i} className="text-sm text-gray-600 flex items-start gap-2">
                            <span className="w-1.5 h-1.5 rounded-full bg-green-500 mt-1.5 flex-shrink-0" /> {s}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                  {app.ai_evaluation?.development_areas && app.ai_evaluation.development_areas.length > 0 && (
                    <div>
                      <h3 className="text-sm font-bold text-orange-700 mb-2 flex items-center gap-1.5">
                        <TrendingUp className="w-4 h-4" /> مجالات التطوير
                      </h3>
                      <ul className="space-y-1.5">
                        {app.ai_evaluation.development_areas.map((s, i) => (
                          <li key={i} className="text-sm text-gray-600 flex items-start gap-2">
                            <span className="w-1.5 h-1.5 rounded-full bg-orange-500 mt-1.5 flex-shrink-0" /> {s}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>

                {/* Interview info */}
                {app.interview_session && (
                  <div className="p-4 bg-gray-50 rounded-xl">
                    <h3 className="text-sm font-bold text-gray-900 mb-3">معلومات المقابلة</h3>
                    <div className="grid grid-cols-3 gap-4 text-sm">
                      <div><p className="text-gray-400 text-xs">الحالة</p><p className="font-medium text-gray-900">{app.interview_session.status === "completed" ? "مكتملة" : app.interview_session.status === "in_progress" ? "جارية" : "معلقة"}</p></div>
                      <div><p className="text-gray-400 text-xs">الأسئلة</p><p className="font-medium text-gray-900">{app.interview_session.questions_asked}/{app.interview_session.max_questions}</p></div>
                      {app.interview_session.completed_at && <div><p className="text-gray-400 text-xs">انتهت في</p><p className="font-medium text-gray-900">{formatDate(app.interview_session.completed_at)}</p></div>}
                    </div>
                  </div>
                )}

                {/* Human interviews */}
                {app.human_interviews && app.human_interviews.length > 0 && (
                  <div>
                    <h3 className="text-sm font-bold text-gray-900 mb-3">المقابلات البشرية</h3>
                    <div className="space-y-2">
                      {app.human_interviews.map((hi) => (
                        <div key={hi.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg text-sm">
                          <div>
                            <span className="font-medium text-gray-900">{hi.interviewer?.name || "غير محدد"}</span>
                            <span className="text-gray-400 text-xs mr-2">{formatDate(hi.scheduled_at)}</span>
                          </div>
                          <div className="flex items-center gap-2">
                            {hi.overall_rating && <ScoreBadge score={hi.overall_rating * 20} />}
                            <Badge variant={hi.status === "completed" ? "green" : hi.status === "scheduled" ? "blue" : "gray"} size="sm">
                              {hi.status === "completed" ? "مكتملة" : hi.status === "scheduled" ? "مجدولة" : hi.status}
                            </Badge>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Skills Tab */}
            {activeTab === "skills" && (
              <div className="space-y-4">
                {app.ai_evaluation?.skill_scores && app.ai_evaluation.skill_scores.length > 0 ? (
                  app.ai_evaluation.skill_scores.map((skill) => (
                    <div key={skill.skill_name}>
                      <div className="flex items-center justify-between mb-1.5">
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-medium text-gray-700">{translateSkill(skill.skill_name)}</span>
                          <span className="text-xs text-gray-400">وزن {Math.round(skill.weight * 100)}%</span>
                        </div>
                        <span className={`text-sm font-bold ${skill.score >= 80 ? "text-green-600" : skill.score >= 60 ? "text-blue-600" : skill.score >= 40 ? "text-yellow-600" : "text-red-600"}`}>
                          {skill.score}/100
                        </span>
                      </div>
                      <div className="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                        <div className={`h-full rounded-full transition-all duration-700 ${
                          skill.score >= 80 ? "bg-green-500" : skill.score >= 60 ? "bg-blue-500" : skill.score >= 40 ? "bg-yellow-500" : "bg-red-500"
                        }`} style={{ width: `${skill.score}%` }} />
                      </div>
                      {skill.notes && <p className="text-xs text-gray-500 mt-1">{skill.notes}</p>}
                    </div>
                  ))
                ) : (
                  <div className="text-center py-12 text-gray-400 text-sm">لا يوجد تقييم مهارات بعد</div>
                )}
              </div>
            )}

            {/* Behavioral Tab */}
            {activeTab === "behavioral" && (
              <div className="space-y-6">
                {app.ai_evaluation?.behavioral_analysis ? (
                  <>
                    {/* DISC */}
                    <div>
                      <h3 className="text-sm font-bold text-gray-900 mb-3">ملف DISC</h3>
                      <div className="flex items-center gap-2 mb-4">
                        <span className={`text-lg font-black px-4 py-2 rounded-xl ${
                          app.ai_evaluation.behavioral_analysis.disc_profile === "D" ? "bg-red-100 text-red-700" :
                          app.ai_evaluation.behavioral_analysis.disc_profile === "I" ? "bg-yellow-100 text-yellow-700" :
                          app.ai_evaluation.behavioral_analysis.disc_profile === "S" ? "bg-green-100 text-green-700" :
                          "bg-blue-100 text-blue-700"
                        }`}>
                          {app.ai_evaluation.behavioral_analysis.disc_profile}
                        </span>
                        <div>
                          <p className="text-sm font-medium text-gray-900">
                            {app.ai_evaluation.behavioral_analysis.disc_profile === "D" ? "مهيمن — قائد وحازم" :
                             app.ai_evaluation.behavioral_analysis.disc_profile === "I" ? "مؤثر — اجتماعي ومتحمس" :
                             app.ai_evaluation.behavioral_analysis.disc_profile === "S" ? "ثابت — متعاون ومستقر" : "ضميري — دقيق ومنهجي"}
                          </p>
                        </div>
                      </div>
                      <div className="grid grid-cols-4 gap-3">
                        {Object.entries(app.ai_evaluation.behavioral_analysis.disc_scores || {}).map(([k, v]) => (
                          <div key={k} className="text-center p-3 bg-gray-50 rounded-xl">
                            <div className="text-xl font-bold text-gray-900">{v}</div>
                            <div className="text-xs text-gray-400">{k === "D" ? "مهيمن" : k === "I" ? "مؤثر" : k === "S" ? "ثابت" : "ضميري"}</div>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* Big Five */}
                    <div>
                      <h3 className="text-sm font-bold text-gray-900 mb-3">Big Five — الشخصية</h3>
                      <div className="space-y-3">
                        {Object.entries(app.ai_evaluation.behavioral_analysis.big_five || {}).map(([trait, score]) => (
                          <div key={trait}>
                            <div className="flex justify-between text-xs mb-1">
                              <span className="text-gray-600">{translateTrait(trait)}</span>
                              <span className="font-medium">{score}/10</span>
                            </div>
                            <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                              <div className="h-full bg-violet-500 rounded-full" style={{ width: `${(score as number) * 10}%` }} />
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                      {[
                        { label: "أسلوب العمل", value: app.ai_evaluation.behavioral_analysis.work_style },
                        { label: "التوافق مع الفريق", value: app.ai_evaluation.behavioral_analysis.team_fit },
                        { label: "القيادية", value: app.ai_evaluation.behavioral_analysis.leadership_potential },
                      ].map((item) => item.value ? (
                        <div key={item.label} className="p-3 bg-violet-50 rounded-xl">
                          <p className="text-xs text-violet-500 mb-1">{item.label}</p>
                          <p className="text-sm font-medium text-violet-900">{item.value}</p>
                        </div>
                      ) : null)}
                    </div>
                  </>
                ) : (
                  <div className="text-center py-12 text-gray-400 text-sm">لا يوجد تحليل سلوكي بعد</div>
                )}
              </div>
            )}

            {/* Risks Tab */}
            {activeTab === "risks" && (
              <div className="space-y-3">
                {app.ai_evaluation?.risk_flags && app.ai_evaluation.risk_flags.length > 0 ? (
                  app.ai_evaluation.risk_flags.map((flag, i) => (
                    <div key={i} className={`p-4 rounded-xl border ${
                      flag.severity === "high" ? "bg-red-50 border-red-200" :
                      flag.severity === "medium" ? "bg-yellow-50 border-yellow-200" :
                      "bg-gray-50 border-gray-200"
                    }`}>
                      <div className="flex items-start gap-3">
                        <AlertTriangle className={`w-4 h-4 mt-0.5 flex-shrink-0 ${
                          flag.severity === "high" ? "text-red-500" : flag.severity === "medium" ? "text-yellow-500" : "text-gray-400"
                        }`} />
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <span className="text-sm font-bold text-gray-900">{flag.flag_type}</span>
                            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                              flag.severity === "high" ? "bg-red-100 text-red-700" :
                              flag.severity === "medium" ? "bg-yellow-100 text-yellow-700" :
                              "bg-gray-100 text-gray-600"
                            }`}>
                              {flag.severity === "high" ? "عالي" : flag.severity === "medium" ? "متوسط" : "منخفض"}
                            </span>
                          </div>
                          <p className="text-sm text-gray-600">{flag.description}</p>
                          {flag.evidence && <p className="text-xs text-gray-400 mt-1 italic">"{flag.evidence}"</p>}
                        </div>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="flex flex-col items-center py-12 gap-3">
                    <Shield className="w-10 h-10 text-green-400" />
                    <p className="text-sm text-gray-500">لم يتم اكتشاف أي مؤشرات خطر</p>
                  </div>
                )}
              </div>
            )}

            {/* Transcript Tab */}
            {activeTab === "transcript" && (
              <div className="space-y-3 max-h-[60vh] overflow-y-auto">
                {app.transcript && app.transcript.length > 0 ? (
                  app.transcript.map((msg, i) => (
                    <div key={i} className={`flex gap-3 ${msg.role === "user" ? "flex-row-reverse" : ""}`}>
                      <div className={`w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold ${
                        msg.role === "assistant" ? "bg-violet-100 text-violet-700" : "bg-gray-100 text-gray-600"
                      }`}>
                        {msg.role === "assistant" ? "AI" : "م"}
                      </div>
                      <div className={`max-w-[80%] px-4 py-2.5 rounded-2xl text-sm ${
                        msg.role === "assistant" ? "bg-violet-50 text-violet-900 rounded-tr-sm" : "bg-gray-100 text-gray-800 rounded-tl-sm"
                      }`}>
                        {msg.content}
                        <p className="text-xs opacity-40 mt-1">{formatRelativeTime(msg.created_at)}</p>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="text-center py-12 text-gray-400 text-sm">لا يوجد نص للمقابلة</div>
                )}
              </div>
            )}

            {/* CV Tab */}
            {activeTab === "cv" && (
              <div className="space-y-4">
                {app.cv ? (
                  <>
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-medium text-gray-700">السيرة الذاتية</span>
                      <a href={app.cv.file_url} target="_blank" rel="noopener noreferrer" className="text-xs text-violet-600 hover:text-violet-700 flex items-center gap-1">
                        <Download className="w-3 h-3" /> تحميل
                      </a>
                    </div>
                    {app.cv.ai_analysis && (
                      <div>
                        <h3 className="text-sm font-bold text-gray-900 mb-2">تحليل الذكاء الاصطناعي للسيرة الذاتية</h3>
                        <p className="text-sm text-gray-600 leading-relaxed bg-gray-50 rounded-xl p-4">{app.cv.ai_analysis}</p>
                      </div>
                    )}
                  </>
                ) : (
                  <div className="text-center py-12 text-gray-400 text-sm">لا توجد سيرة ذاتية</div>
                )}
              </div>
            )}

            {/* Timeline Tab */}
            {activeTab === "timeline" && (
              <div className="space-y-3">
                {app.timeline && app.timeline.length > 0 ? (
                  <div className="relative">
                    <div className="absolute right-3.5 top-0 bottom-0 w-px bg-gray-200" />
                    {app.timeline.map((event, i) => (
                      <div key={i} className="flex items-start gap-3 relative pb-4">
                        <div className="w-7 h-7 bg-violet-100 rounded-full flex items-center justify-center flex-shrink-0 z-10">
                          <Clock className="w-3.5 h-3.5 text-violet-600" />
                        </div>
                        <div className="flex-1 pt-1">
                          <p className="text-sm font-medium text-gray-800">{event.action}</p>
                          <p className="text-xs text-gray-400 mt-0.5">
                            {event.user?.name && <span className="font-medium text-gray-600">{event.user.name} • </span>}
                            {formatRelativeTime(event.created_at)}
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-12 text-gray-400 text-sm">لا يوجد سجل أحداث</div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

function translateSkill(skill: string): string {
  const map: Record<string, string> = {
    technical_competency: "الكفاءة التقنية",
    communication: "مهارات التواصل",
    problem_solving: "حل المشكلات",
    critical_thinking: "التفكير النقدي",
    self_confidence: "الثقة بالنفس",
    leadership: "القيادة",
    cultural_fit: "الملاءمة الثقافية",
    professionalism: "الاحترافية",
    ai_knowledge: "معرفة الذكاء الاصطناعي",
    english_proficiency: "الإنجليزية",
    learning_ability: "القدرة على التعلم",
  };
  return map[skill] || skill;
}

function translateTrait(trait: string): string {
  const map: Record<string, string> = {
    openness: "الانفتاح", conscientiousness: "الضمير", extraversion: "الانبساطية",
    agreeableness: "المقبولية", neuroticism: "العصابية",
  };
  return map[trait] || trait;
}
