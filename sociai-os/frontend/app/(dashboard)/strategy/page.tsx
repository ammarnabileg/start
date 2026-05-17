"use client";

import { useState, useCallback } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { useDropzone } from "react-dropzone";
import { Upload, FileText, Check, AlertCircle, X, Brain, Target, Users, Tag } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";

const DOCUMENT_TYPES = [
  { id: "brand-guide", label: "Brand Guide", icon: "🎨", color: "#8B5CF6" },
  { id: "marketing-plan", label: "Marketing Plan", icon: "📋", color: "#3B82F6" },
  { id: "content-strategy", label: "Content Strategy", icon: "🎯", color: "#10B981" },
  { id: "audience-research", label: "Audience Research", icon: "👥", color: "#F59E0B" },
  { id: "competitor-analysis", label: "Competitor Analysis", icon: "⚔️", color: "#EF4444" },
  { id: "campaign-brief", label: "Campaign Brief", icon: "📢", color: "#EC4899" },
  { id: "tone-of-voice", label: "Tone of Voice", icon: "🎤", color: "#6366F1" },
  { id: "content-pillars", label: "Content Pillars", icon: "🏛️", color: "#14B8A6" },
  { id: "seo-keywords", label: "SEO Keywords", icon: "🔍", color: "#F97316" },
  { id: "crisis-plan", label: "Crisis Plan", icon: "🛡️", color: "#EAB308" },
  { id: "editorial-calendar", label: "Editorial Calendar", icon: "📅", color: "#A855F7" },
];

interface UploadedDoc {
  id: string;
  name: string;
  type: string;
  size: number;
  status: "uploading" | "processing" | "analyzed" | "failed";
  progress: number;
}

const MOCK_INSIGHTS = {
  brandTone: ["Professional", "Inspiring", "Authentic", "Bold", "Community-focused"],
  contentPillars: [
    { name: "Education", pct: 35, color: "#3B82F6", topics: ["How-to guides", "Industry trends", "Tutorials"] },
    { name: "Inspiration", pct: 25, color: "#8B5CF6", topics: ["Success stories", "Motivational", "Milestones"] },
    { name: "Entertainment", pct: 20, color: "#EC4899", topics: ["Behind scenes", "Team culture", "Fun facts"] },
    { name: "Promotion", pct: 20, color: "#10B981", topics: ["Products", "Offers", "Services"] },
  ],
  audienceSegments: [
    { name: "Young Professionals", pct: 42, demo: "25-35 · Urban · Tech-savvy", platforms: ["instagram", "linkedin"] },
    { name: "Entrepreneurs", pct: 28, demo: "30-45 · Global · Decision makers", platforms: ["linkedin", "twitter"] },
    { name: "Creative Millennials", pct: 30, demo: "22-30 · Digital-native", platforms: ["instagram", "tiktok"] },
  ],
  keywords: ["digital transformation", "innovation", "AI tools", "growth hacking", "community building", "sustainability"],
  recommendations: [
    "Post 1x daily on Instagram with high-quality visuals",
    "Engage with LinkedIn articles 3x per week",
    "Use stories format for behind-scenes content",
    "Reply to 100% of comments within 2 hours",
  ],
};

export default function StrategyPage() {
  const [docs, setDocs] = useState<UploadedDoc[]>([]);
  const [selectedType, setSelectedType] = useState("brand-guide");
  const [analyzed, setAnalyzed] = useState(false);

  const onDrop = useCallback(async (files: File[]) => {
    for (const file of files) {
      const doc: UploadedDoc = {
        id: Math.random().toString(36).slice(2),
        name: file.name,
        type: selectedType,
        size: file.size,
        status: "uploading",
        progress: 0,
      };
      setDocs(prev => [...prev, doc]);

      // Simulate upload progress
      for (let p = 0; p <= 100; p += 10) {
        await new Promise(r => setTimeout(r, 80));
        setDocs(prev => prev.map(d => d.id === doc.id ? { ...d, progress: p, status: p === 100 ? "processing" : "uploading" } : d));
      }
      
      await new Promise(r => setTimeout(r, 1200));
      setDocs(prev => prev.map(d => d.id === doc.id ? { ...d, status: "analyzed", progress: 100 } : d));
      setAnalyzed(true);
    }
  }, [selectedType]);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      "application/pdf": [".pdf"],
      "application/msword": [".doc"],
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document": [".docx"],
      "text/plain": [".txt"],
    },
  });

  return (
    <div className="space-y-6">
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
        <h1 className="text-2xl font-bold text-white">Strategy Hub</h1>
        <p className="text-white/40 text-sm">Upload your strategy documents and let AI extract powerful insights</p>
      </motion.div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {/* Upload Panel */}
        <div className="space-y-5">
          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-3">Document Type</h3>
            <div className="grid grid-cols-2 gap-2">
              {DOCUMENT_TYPES.map(dt => (
                <button
                  key={dt.id}
                  onClick={() => setSelectedType(dt.id)}
                  className={`flex items-center gap-2 px-3 py-2 rounded-xl border text-sm transition-all text-left ${selectedType === dt.id ? "border-electric-blue/50 bg-electric-blue/10 text-white" : "border-white/10 bg-white/5 text-white/50 hover:text-white/70"}`}
                  style={selectedType === dt.id ? { borderColor: dt.color + "50", background: dt.color + "15" } : {}}
                >
                  <span>{dt.icon}</span>
                  <span className="text-xs font-medium">{dt.label}</span>
                </button>
              ))}
            </div>
          </GlassCard>

          {/* Dropzone */}
          <div
            {...getRootProps()}
            className={`glass-panel p-8 rounded-2xl border-2 border-dashed transition-all cursor-pointer text-center ${isDragActive ? "dropzone-active border-electric-blue/60" : "border-white/15 hover:border-white/25"}`}
          >
            <input {...getInputProps()} />
            <motion.div animate={isDragActive ? { scale: 1.05 } : { scale: 1 }}>
              <Upload className={`w-10 h-10 mx-auto mb-4 ${isDragActive ? "text-electric-blue" : "text-white/30"}`} />
              <h3 className="text-white font-semibold mb-1">
                {isDragActive ? "Drop your files here!" : "Drag & drop or click to upload"}
              </h3>
              <p className="text-white/40 text-sm">Supports PDF, DOC, DOCX, TXT</p>
              <p className="text-white/25 text-xs mt-1">Max 50MB per file</p>
            </motion.div>
          </div>

          {/* Uploaded Files */}
          {docs.length > 0 && (
            <GlassCard padding="md">
              <h3 className="text-white font-semibold mb-3">Uploaded Documents</h3>
              <div className="space-y-3">
                {docs.map(doc => (
                  <div key={doc.id} className="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/8">
                    <FileText className="w-8 h-8 text-electric-blue flex-shrink-0" />
                    <div className="flex-1 min-w-0">
                      <p className="text-white text-sm font-medium truncate">{doc.name}</p>
                      <p className="text-white/40 text-xs">{(doc.size / 1024).toFixed(0)} KB</p>
                      {doc.status !== "analyzed" && (
                        <div className="mt-1.5 h-1 bg-white/10 rounded-full">
                          <motion.div
                            className="h-full rounded-full bg-electric-blue"
                            animate={{ width: `${doc.progress}%` }}
                          />
                        </div>
                      )}
                    </div>
                    <div className="flex-shrink-0">
                      {doc.status === "uploading" && (
                        <motion.div className="w-5 h-5 border-2 border-white/20 border-t-electric-blue rounded-full" animate={{ rotate: 360 }} transition={{ duration: 0.8, repeat: Infinity, ease: "linear" }} />
                      )}
                      {doc.status === "processing" && <NeonBadge variant="yellow" size="sm">Processing</NeonBadge>}
                      {doc.status === "analyzed" && <Check className="w-5 h-5 text-neon-green" />}
                      {doc.status === "failed" && <AlertCircle className="w-5 h-5 text-red-400" />}
                    </div>
                    <button onClick={() => setDocs(d => d.filter(x => x.id !== doc.id))} className="text-white/20 hover:text-red-400 transition-colors">
                      <X className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>
            </GlassCard>
          )}
        </div>

        {/* Insights Panel */}
        <div className="space-y-5">
          <AnimatePresence>
            {!analyzed ? (
              <motion.div key="empty" initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="glass-panel p-12 rounded-2xl text-center">
                <Brain className="w-12 h-12 text-white/20 mx-auto mb-4" />
                <h3 className="text-white/50 font-semibold text-lg mb-2">Awaiting Documents</h3>
                <p className="text-white/30 text-sm">Upload your strategy documents to unlock AI-powered insights</p>
              </motion.div>
            ) : (
              <motion.div key="insights" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="space-y-5">
                {/* Brand Tone */}
                <GlassCard padding="md">
                  <div className="flex items-center gap-2 mb-4">
                    <span className="text-lg">🎤</span>
                    <h3 className="text-white font-semibold">Brand Tone</h3>
                    <NeonBadge variant="blue" size="sm">AI Detected</NeonBadge>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {MOCK_INSIGHTS.brandTone.map(tone => (
                      <span key={tone} className="px-3 py-1.5 rounded-full bg-electric-blue/15 border border-electric-blue/30 text-electric-blue text-sm font-medium">{tone}</span>
                    ))}
                  </div>
                </GlassCard>

                {/* Content Pillars */}
                <GlassCard padding="md">
                  <div className="flex items-center gap-2 mb-4">
                    <Target className="w-5 h-5 text-white/60" />
                    <h3 className="text-white font-semibold">Content Pillars</h3>
                  </div>
                  <div className="space-y-3">
                    {MOCK_INSIGHTS.contentPillars.map(pillar => (
                      <div key={pillar.name}>
                        <div className="flex justify-between text-sm mb-1">
                          <span className="text-white font-medium">{pillar.name}</span>
                          <span style={{ color: pillar.color }}>{pillar.pct}%</span>
                        </div>
                        <div className="h-2 bg-white/10 rounded-full overflow-hidden">
                          <motion.div
                            initial={{ width: 0 }}
                            animate={{ width: `${pillar.pct}%` }}
                            transition={{ delay: 0.3, duration: 0.8 }}
                            className="h-full rounded-full"
                            style={{ background: pillar.color }}
                          />
                        </div>
                        <div className="flex gap-2 mt-1 flex-wrap">
                          {pillar.topics.map(t => (
                            <span key={t} className="text-xs text-white/30">{t}</span>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </GlassCard>

                {/* Audience Segments */}
                <GlassCard padding="md">
                  <div className="flex items-center gap-2 mb-4">
                    <Users className="w-5 h-5 text-white/60" />
                    <h3 className="text-white font-semibold">Audience Segments</h3>
                  </div>
                  <div className="space-y-3">
                    {MOCK_INSIGHTS.audienceSegments.map(seg => (
                      <div key={seg.name} className="p-3 rounded-xl bg-white/5 border border-white/10">
                        <div className="flex items-center justify-between mb-1">
                          <h4 className="text-white font-medium text-sm">{seg.name}</h4>
                          <span className="text-electric-blue text-sm font-bold">{seg.pct}%</span>
                        </div>
                        <p className="text-white/40 text-xs">{seg.demo}</p>
                      </div>
                    ))}
                  </div>
                </GlassCard>

                {/* Keywords */}
                <GlassCard padding="md">
                  <div className="flex items-center gap-2 mb-4">
                    <Tag className="w-5 h-5 text-white/60" />
                    <h3 className="text-white font-semibold">Key Topics</h3>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {MOCK_INSIGHTS.keywords.map(kw => (
                      <span key={kw} className="px-2.5 py-1 rounded-full bg-white/8 border border-white/15 text-white/60 text-sm hover:bg-white/12 cursor-pointer transition-all">
                        #{kw}
                      </span>
                    ))}
                  </div>
                </GlassCard>

                {/* Recommendations */}
                <GlassCard padding="md">
                  <div className="flex items-center gap-2 mb-4">
                    <Brain className="w-5 h-5 text-neon-purple-400" />
                    <h3 className="text-white font-semibold">AI Recommendations</h3>
                  </div>
                  <div className="space-y-2">
                    {MOCK_INSIGHTS.recommendations.map((rec, i) => (
                      <div key={i} className="flex items-start gap-2">
                        <div className="w-5 h-5 rounded-full bg-neon-green/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                          <Check className="w-3 h-3 text-neon-green" />
                        </div>
                        <p className="text-white/70 text-sm">{rec}</p>
                      </div>
                    ))}
                  </div>
                </GlassCard>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </div>
  );
}
