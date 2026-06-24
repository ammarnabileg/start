"use client";

import { DashboardLayout } from "@/components/layout/DashboardLayout";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { talentPoolApi } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import { Plus, Search, Sparkles, Users } from "lucide-react";
import { useEffect, useState } from "react";
import toast from "react-hot-toast";

interface Pool { id: number; name: string; description?: string; candidates_count: number; created_at: string }
interface PoolCandidate { id: number; name: string; email: string; overall_score?: number; ai_recommendation?: string; pools: number[] }

export default function TalentPoolPage() {
  const [pools, setPools] = useState<Pool[]>([]);
  const [candidates, setCandidates] = useState<PoolCandidate[]>([]);
  const [activePool, setActivePool] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [showCreate, setShowCreate] = useState(false);
  const [search, setSearch] = useState("");
  const [aiSearch, setAiSearch] = useState("");
  const [aiResults, setAiResults] = useState<PoolCandidate[]>([]);
  const [aiLoading, setAiLoading] = useState(false);
  const [newPool, setNewPool] = useState({ name: "", description: "" });

  useEffect(() => { loadPools(); }, []);
  useEffect(() => {
    if (activePool) loadPoolCandidates(activePool);
  }, [activePool]);

  const loadPools = async () => {
    try {
      const res = await talentPoolApi.list();
      const poolList = res.data.data || res.data;
      setPools(poolList);
      if (poolList.length > 0) setActivePool(poolList[0].id);
    } catch { toast.error("خطأ في التحميل"); }
    finally { setLoading(false); }
  };

  const loadPoolCandidates = async (poolId: number) => {
    try {
      const res = await talentPoolApi.candidates(poolId);
      setCandidates(res.data.data || res.data);
    } catch { toast.error("خطأ في تحميل المرشحين"); }
  };

  const createPool = async () => {
    try {
      await talentPoolApi.create(newPool);
      toast.success("تم إنشاء المجموعة");
      setShowCreate(false);
      setNewPool({ name: "", description: "" });
      loadPools();
    } catch { toast.error("خطأ في الإنشاء"); }
  };

  const aiSemanticSearch = async () => {
    if (!aiSearch.trim()) return;
    setAiLoading(true);
    try {
      const res = await talentPoolApi.search({ query: aiSearch });
      setAiResults(res.data.results || res.data);
    } catch { toast.error("خطأ في البحث"); }
    finally { setAiLoading(false); }
  };

  const removeFromPool = async (candidateId: number) => {
    if (!activePool) return;
    try {
      await talentPoolApi.removeCandidate(activePool, candidateId);
      setCandidates((p) => p.filter((c) => c.id !== candidateId));
      toast.success("تم الحذف من المجموعة");
    } catch { toast.error("خطأ في الحذف"); }
  };

  const addToPool = async (candidateId: number, poolId: number) => {
    try {
      await talentPoolApi.addCandidate(poolId, candidateId);
      toast.success("تمت الإضافة للمجموعة");
      if (poolId === activePool) loadPoolCandidates(poolId);
    } catch { toast.error("خطأ في الإضافة"); }
  };

  const filteredCandidates = candidates.filter((c) =>
    c.name.toLowerCase().includes(search.toLowerCase()) ||
    c.email.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <DashboardLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">مجموعات المواهب</h1>
            <p className="text-sm text-gray-500 mt-0.5">{pools.length} مجموعة</p>
          </div>
          <Button icon={<Plus className="w-4 h-4" />} onClick={() => setShowCreate(true)}>مجموعة جديدة</Button>
        </div>

        {/* AI Semantic Search */}
        <div className="bg-gradient-to-r from-violet-50 to-indigo-50 border border-violet-200 rounded-xl p-4">
          <div className="flex items-center gap-2 mb-3">
            <Sparkles className="w-4 h-4 text-violet-500" />
            <h3 className="text-sm font-bold text-violet-900">البحث الدلالي بالذكاء الاصطناعي</h3>
          </div>
          <div className="flex gap-3">
            <input value={aiSearch} onChange={(e) => setAiSearch(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && aiSemanticSearch()}
              placeholder="مثال: مطور React بخبرة 3 سنوات وخلفية في fintech..."
              className="flex-1 px-3 py-2 text-sm bg-white border border-violet-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500" />
            <Button loading={aiLoading} onClick={aiSemanticSearch} icon={<Search className="w-4 h-4" />}>بحث</Button>
          </div>
          {aiResults.length > 0 && (
            <div className="mt-3 space-y-2">
              <p className="text-xs text-violet-600 font-medium">{aiResults.length} نتيجة</p>
              {aiResults.map((c) => (
                <div key={c.id} className="flex items-center justify-between bg-white rounded-lg p-3">
                  <div>
                    <p className="text-sm font-semibold text-gray-900">{c.name}</p>
                    <p className="text-xs text-gray-400">{c.email}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    {c.overall_score && <span className="text-sm font-bold text-violet-700">{c.overall_score}</span>}
                    {pools.map((pool) => (
                      <button key={pool.id} onClick={() => addToPool(c.id, pool.id)}
                        className="text-xs px-2 py-1 bg-violet-100 text-violet-700 rounded hover:bg-violet-200 transition-colors">
                        + {pool.name}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="grid grid-cols-4 gap-5">
          {/* Pool list */}
          <div className="col-span-1 space-y-2">
            {loading ? (
              [...Array(3)].map((_, i) => <div key={i} className="h-16 skeleton rounded-xl" />)
            ) : (
              pools.map((pool) => (
                <button key={pool.id} onClick={() => setActivePool(pool.id)}
                  className={`w-full text-right p-4 rounded-xl border transition-all ${
                    activePool === pool.id ? "bg-violet-600 border-violet-600 text-white shadow-md" : "bg-white border-gray-200 hover:border-violet-300 hover:bg-violet-50"
                  }`}>
                  <p className={`font-bold text-sm ${activePool === pool.id ? "text-white" : "text-gray-900"}`}>{pool.name}</p>
                  <p className={`text-xs mt-0.5 ${activePool === pool.id ? "text-violet-200" : "text-gray-400"}`}>
                    <Users className="w-3 h-3 inline ml-1" />{pool.candidates_count} مرشح
                  </p>
                </button>
              ))
            )}
          </div>

          {/* Candidates */}
          <div className="col-span-3">
            {activePool && (
              <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div className="p-4 border-b border-gray-200 flex items-center gap-3">
                  <Input placeholder="بحث في المجموعة..." leftIcon={<Search className="w-4 h-4" />}
                    value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs" />
                  <span className="text-xs text-gray-400 mr-auto">{filteredCandidates.length} مرشح</span>
                </div>
                <div className="divide-y divide-gray-100">
                  {filteredCandidates.length === 0 ? (
                    <div className="p-12 text-center">
                      <Users className="w-8 h-8 text-gray-300 mx-auto mb-2" />
                      <p className="text-sm text-gray-400">لا يوجد مرشحون في هذه المجموعة</p>
                    </div>
                  ) : (
                    filteredCandidates.map((c) => (
                      <div key={c.id} className="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div className="w-9 h-9 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-sm flex-shrink-0">
                          {c.name.charAt(0)}
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-semibold text-gray-900">{c.name}</p>
                          <p className="text-xs text-gray-400">{c.email}</p>
                        </div>
                        {c.overall_score && (
                          <div className={`text-sm font-bold px-2 py-0.5 rounded-lg ${
                            c.overall_score >= 80 ? "bg-green-100 text-green-700" :
                            c.overall_score >= 60 ? "bg-blue-100 text-blue-700" :
                            "bg-yellow-100 text-yellow-700"
                          }`}>
                            {c.overall_score}
                          </div>
                        )}
                        <button onClick={() => removeFromPool(c.id)}
                          className="text-xs text-red-400 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50 transition-colors">
                          إزالة
                        </button>
                      </div>
                    ))
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="مجموعة مواهب جديدة" size="sm"
        footer={<><Button variant="secondary" onClick={() => setShowCreate(false)}>إلغاء</Button><Button onClick={createPool}>إنشاء</Button></>}>
        <div className="space-y-4">
          <Input label="اسم المجموعة" placeholder="مثال: مطورون Backend" value={newPool.name} onChange={(e) => setNewPool(p => ({ ...p, name: e.target.value }))} />
          <textarea value={newPool.description} onChange={(e) => setNewPool(p => ({ ...p, description: e.target.value }))}
            placeholder="وصف المجموعة (اختياري)..." rows={3}
            className="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none" />
        </div>
      </Modal>
    </DashboardLayout>
  );
}
