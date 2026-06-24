'use client';

import { useState, useEffect, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { applicationsApi } from '@/lib/api';

interface Application {
  id: number;
  overall_score?: number;
  ai_recommendation?: string;
  candidate?: { name: string; email: string };
  job?: { title: string };
  ai_evaluation?: {
    executive_summary?: string;
    strengths?: string[];
    weaknesses?: string[];
    skill_scores?: { skill_key: string; score: number }[];
    disc_profile?: Record<string, number>;
  };
}

interface ComparisonResult {
  comparison_table?: { criterion: string; values: Record<string, string> }[];
  best_candidate_id?: number;
  reasoning?: string;
  recommendation?: string;
}

const recColors: Record<string, string> = {
  strong_recommendation: 'bg-emerald-100 text-emerald-700',
  suitable: 'bg-blue-100 text-blue-700',
  possible_fit: 'bg-yellow-100 text-yellow-700',
  not_recommended: 'bg-red-100 text-red-700',
};

const recLabels: Record<string, string> = {
  strong_recommendation: 'توصية قوية',
  suitable: 'مناسب',
  possible_fit: 'محتمل',
  not_recommended: 'غير مناسب',
};

function CompareContent() {
  const searchParams = useSearchParams();
  const ids = searchParams.get('ids')?.split(',').map(Number).filter(Boolean) || [];

  const [applications, setApplications] = useState<Application[]>([]);
  const [comparison, setComparison] = useState<ComparisonResult | null>(null);
  const [question, setQuestion] = useState('');
  const [loading, setLoading] = useState(true);
  const [comparing, setComparing] = useState(false);

  useEffect(() => {
    if (ids.length >= 2) load();
  }, []);

  const load = async () => {
    try {
      const res = await applicationsApi.compare(ids);
      setApplications(res.data.applications || []);
      setComparison(res.data.ai_comparison);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const runComparison = async () => {
    setComparing(true);
    try {
      const res = await applicationsApi.compare(ids, question);
      setComparison(res.data.ai_comparison);
    } catch {
      alert('Comparison failed');
    } finally {
      setComparing(false);
    }
  };

  if (loading) return (
    <DashboardLayout>
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-4 border-violet-600 border-t-transparent rounded-full animate-spin" />
      </div>
    </DashboardLayout>
  );

  return (
    <DashboardLayout>
      <div className="p-6 max-w-7xl mx-auto">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-800">مقارنة المرشحين</h1>
          <p className="text-sm text-gray-500 mt-1">Candidate Comparison — {applications.length} candidates</p>
        </div>

        {/* Score Grid */}
        <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: `repeat(${applications.length}, 1fr)` }}>
          {applications.map(app => (
            <div key={app.id} className={`bg-white rounded-2xl border-2 p-5 text-center ${comparison?.best_candidate_id === app.id ? 'border-violet-500 shadow-lg' : 'border-gray-200'}`}>
              {comparison?.best_candidate_id === app.id && (
                <div className="text-xs text-violet-700 bg-violet-100 rounded-full px-2 py-0.5 inline-block mb-2">⭐ Top Pick</div>
              )}
              <div className="w-12 h-12 rounded-full bg-violet-100 text-violet-700 font-bold text-lg flex items-center justify-center mx-auto mb-2">
                {app.candidate?.name?.[0]?.toUpperCase() || '?'}
              </div>
              <h3 className="font-semibold text-gray-800 text-sm">{app.candidate?.name}</h3>
              <p className="text-xs text-gray-400">{app.job?.title}</p>
              <div className="mt-3">
                <div className="text-3xl font-bold text-violet-600">{app.overall_score ?? '—'}</div>
                <div className="text-xs text-gray-400">Overall Score</div>
              </div>
              {app.ai_recommendation && (
                <span className={`inline-block mt-2 text-xs px-2 py-0.5 rounded-full ${recColors[app.ai_recommendation] || 'bg-gray-100 text-gray-600'}`}>
                  {recLabels[app.ai_recommendation] || app.ai_recommendation}
                </span>
              )}
            </div>
          ))}
        </div>

        {/* Skills comparison */}
        <div className="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
          <h2 className="font-semibold text-gray-700 mb-4">Skills Comparison</h2>
          <div className="space-y-3">
            {['technical_skills', 'problem_solving', 'communication', 'leadership', 'team_work'].map(skill => (
              <div key={skill}>
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-xs text-gray-500 w-32 shrink-0">{skill.replace(/_/g, ' ')}</span>
                  <div className="flex-1 flex gap-2">
                    {applications.map(app => {
                      const s = app.ai_evaluation?.skill_scores?.find(sc => sc.skill_key === skill);
                      const pct = s ? s.score : 0;
                      return (
                        <div key={app.id} className="flex-1">
                          <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div className="h-2 bg-violet-500 rounded-full" style={{ width: `${pct}%` }} />
                          </div>
                          <span className="text-xs text-gray-400">{pct || '—'}</span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* AI Question */}
        <div className="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
          <h2 className="font-semibold text-gray-700 mb-3">Ask AI a Specific Question</h2>
          <div className="flex gap-3">
            <input
              type="text"
              value={question}
              onChange={e => setQuestion(e.target.value)}
              placeholder="e.g. Who has better leadership potential? Who is more suitable for a remote role?"
              className="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
            <button
              onClick={runComparison}
              disabled={comparing}
              className="bg-violet-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 disabled:opacity-50 transition-colors"
            >
              {comparing ? 'Analyzing...' : 'Ask AI'}
            </button>
          </div>
        </div>

        {/* AI Comparison Result */}
        {comparison && (
          <div className="bg-gradient-to-br from-violet-50 to-purple-50 rounded-2xl border border-violet-200 p-6">
            <h2 className="font-semibold text-gray-700 mb-4">AI Analysis</h2>
            {comparison.reasoning && (
              <p className="text-sm text-gray-600 mb-4 leading-relaxed">{comparison.reasoning}</p>
            )}
            {comparison.recommendation && (
              <div className="bg-white rounded-xl p-4 text-sm text-gray-700">
                <span className="font-medium text-violet-700">Recommendation: </span>
                {comparison.recommendation}
              </div>
            )}
            {comparison.comparison_table && comparison.comparison_table.length > 0 && (
              <div className="mt-4 overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-violet-200">
                      <th className="text-left py-2 pr-4 text-gray-600 font-medium">Criterion</th>
                      {applications.map(app => (
                        <th key={app.id} className="text-left py-2 pr-4 text-gray-600 font-medium">{app.candidate?.name}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {comparison.comparison_table.map((row, i) => (
                      <tr key={i} className="border-b border-violet-100">
                        <td className="py-2 pr-4 font-medium text-gray-700">{row.criterion}</td>
                        {applications.map(app => (
                          <td key={app.id} className="py-2 pr-4 text-gray-600">
                            {row.values?.[app.id] || row.values?.[String(app.id)] || '—'}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}

export default function ComparePage() {
  return (
    <Suspense fallback={<DashboardLayout><div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-4 border-violet-600 border-t-transparent rounded-full animate-spin" /></div></DashboardLayout>}>
      <CompareContent />
    </Suspense>
  );
}
