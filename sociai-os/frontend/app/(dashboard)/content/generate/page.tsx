"use client";
import { useState } from "react";
import { Sparkles, RefreshCw } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";

export default function GenerateContentPage() {
  const [isGenerating, setIsGenerating] = useState(false);
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-white">Generate Content</h1>
      <GlassCard className="p-8 text-center">
        <Sparkles size={32} className="text-electric-blue-400 mx-auto mb-4" />
        <p className="text-slate-400">AI Content Generator – Configure your brief to generate platform-optimized content</p>
        <button onClick={() => setIsGenerating(!isGenerating)} className="mt-4 px-6 py-3 rounded-xl bg-gradient-to-r from-electric-blue-600 to-neon-purple-600 text-white font-semibold hover:shadow-lg transition-all">
          {isGenerating ? <><RefreshCw size={15} className="inline animate-spin mr-2" />Generating...</> : "Start Generating"}
        </button>
      </GlassCard>
    </div>
  );
}
