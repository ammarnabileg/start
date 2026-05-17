"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Wand2, Copy, Save, RefreshCw, ChevronLeft, Hash, Smile, Check, Zap } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { PostPreview } from "@/components/content/PostPreview";
import { NeonBadge } from "@/components/ui/NeonBadge";
import { PlatformId } from "@/lib/types";
import Link from "next/link";

const PLATFORMS: PlatformId[] = ["instagram", "facebook", "twitter", "tiktok", "youtube", "linkedin", "threads", "pinterest"];

const STYLES = [
  { id: "informative", label: "Informative", emoji: "📊" },
  { id: "entertaining", label: "Entertaining", emoji: "🎉" },
  { id: "inspirational", label: "Inspirational", emoji: "✨" },
  { id: "promotional", label: "Promotional", emoji: "🛍️" },
  { id: "educational", label: "Educational", emoji: "📚" },
  { id: "storytelling", label: "Storytelling", emoji: "📖" },
  { id: "humor", label: "Humor", emoji: "😄" },
  { id: "controversial", label: "Controversial", emoji: "🔥" },
  { id: "behind-scenes", label: "Behind Scenes", emoji: "🎬" },
  { id: "user-generated", label: "UGC Style", emoji: "👥" },
];

const MOCK_GENERATED: Record<string, { caption: string; hashtags: string[]; estimatedReach: number; confidence: number }> = {
  instagram: {
    caption: "Transform your mornings with these 5 game-changing habits that top performers swear by! 🌅✨\n\nEvery successful person has a morning ritual. It's not magic—it's discipline, consistency, and intention.\n\nHere's what works:\n1️⃣ Wake up before 6 AM\n2️⃣ No phone for the first 30 mins\n3️⃣ Move your body for at least 20 mins\n4️⃣ Set 3 intentions for the day\n5️⃣ Read for 15 minutes\n\nWhich one will you start today? Drop it in the comments 👇",
    hashtags: ["MorningRoutine", "Productivity", "SuccessHabits", "SelfImprovement", "MindsetMatters"],
    estimatedReach: 24800,
    confidence: 92,
  },
  twitter: {
    caption: "5 morning habits that changed everything for me:\n\n• No phone first 30 mins\n• 20min movement\n• Cold shower\n• Set 3 priorities\n• Read 15 pages\n\nDiscipline > Motivation every single time.",
    hashtags: ["Productivity", "MorningRoutine"],
    estimatedReach: 8400,
    confidence: 88,
  },
  linkedin: {
    caption: "After studying 500+ high-performers, here are the 5 morning habits that consistently predict success:\n\nMost people check their phone the moment they wake up. The most successful people I know? They don't.\n\n1. Early rising (before 6 AM): 78% correlation with higher productivity scores\n2. Intentional movement: Increases cognitive function by 23%\n3. Digital detox (first 30 mins): Reduces cortisol by 19%\n4. Priority setting: 3x higher task completion rates\n5. Daily reading: Compounds knowledge at 100+ books/year\n\nThe compounding effect is real. Which habit are you implementing this week?",
    hashtags: ["Leadership", "Productivity", "MorningRoutine", "Success"],
    estimatedReach: 12200,
    confidence: 95,
  },
  tiktok: {
    caption: "POV: You woke up at 5am and now you're unstoppable 🔥 5 habits that completely changed my mornings #morningroutine #productivity",
    hashtags: ["morningroutine", "productivity", "5amclub", "selfimprovement"],
    estimatedReach: 45000,
    confidence: 85,
  },
  facebook: {
    caption: "Want to know the secret to having incredible mornings? It comes down to these 5 powerful habits that top performers use every single day...\n\n1. Wake up before 6 AM\n2. No screen time for the first 30 minutes\n3. Get your body moving\n4. Set 3 daily intentions\n5. Read for 15 minutes\n\nSmall habits, big results. Which one are you going to try this week?",
    hashtags: ["MorningRoutine", "Productivity", "Success", "PersonalDevelopment"],
    estimatedReach: 18600,
    confidence: 89,
  },
};

export default function ContentGeneratePage() {
  const [topic, setTopic] = useState("");
  const [selectedPlatforms, setSelectedPlatforms] = useState<PlatformId[]>(["instagram", "twitter"]);
  const [selectedStyle, setSelectedStyle] = useState("informative");
  const [language, setLanguage] = useState<"en" | "ar" | "mixed">("en");
  const [formality, setFormality] = useState(50);
  const [enthusiasm, setEnthusiasm] = useState(70);
  const [includeHashtags, setIncludeHashtags] = useState(true);
  const [includeEmojis, setIncludeEmojis] = useState(true);
  const [isGenerating, setIsGenerating] = useState(false);
  const [generated, setGenerated] = useState(false);
  const [previewPlatform, setPreviewPlatform] = useState<PlatformId>("instagram");
  const [copiedId, setCopiedId] = useState<string | null>(null);

  const togglePlatform = (p: PlatformId) => {
    setSelectedPlatforms(prev => prev.includes(p) ? prev.filter(x => x !== p) : [...prev, p]);
  };

  const handleGenerate = async () => {
    if (!topic.trim() || selectedPlatforms.length === 0) return;
    setIsGenerating(true);
    await new Promise(r => setTimeout(r, 2500));
    setIsGenerating(false);
    setGenerated(true);
    setPreviewPlatform(selectedPlatforms[0]);
  };

  const handleCopy = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  return (
    <div className="space-y-6">
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} className="flex items-center gap-4">
        <Link href="/content" className="w-9 h-9 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 flex items-center justify-center text-white/50 hover:text-white transition-all">
          <ChevronLeft className="w-4 h-4" />
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-white">AI Content Generator</h1>
          <p className="text-white/40 text-sm">Generate platform-optimized content from a single topic</p>
        </div>
      </motion.div>

      <div className="grid grid-cols-1 xl:grid-cols-5 gap-6">
        {/* Config Panel */}
        <div className="xl:col-span-2 space-y-5">
          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-4">Content Topic</h3>
            <textarea value={topic} onChange={e => setTopic(e.target.value)} placeholder="E.g.: 5 morning habits that successful entrepreneurs follow..." className="input-glass resize-none h-24 text-sm" />
            <div className="mt-4">
              <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-2 block">Language</label>
              <div className="flex gap-2">
                {[{ id: "en", label: "English" }, { id: "ar", label: "العربية" }, { id: "mixed", label: "Mixed" }].map(l => (
                  <button key={l.id} onClick={() => setLanguage(l.id as typeof language)} className={`flex-1 py-2 rounded-xl text-sm font-medium border transition-all ${language === l.id ? "bg-electric-blue/20 border-electric-blue/40 text-electric-blue" : "bg-white/5 border-white/10 text-white/50 hover:text-white/70"}`}>{l.label}</button>
                ))}
              </div>
            </div>
          </GlassCard>

          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-3">Target Platforms</h3>
            <div className="grid grid-cols-4 gap-2">
              {PLATFORMS.map(p => (
                <motion.button key={p} onClick={() => togglePlatform(p)} whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }} className={`flex flex-col items-center gap-1 p-2 rounded-xl border transition-all ${selectedPlatforms.includes(p) ? "border-electric-blue/50 bg-electric-blue/10" : "border-white/10 bg-white/5 hover:bg-white/8"}`}>
                  <PlatformIcon platform={p} size="sm" />
                  <span className="text-xs text-white/40 capitalize">{p.slice(0, 5)}</span>
                </motion.button>
              ))}
            </div>
          </GlassCard>

          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-3">Content Style</h3>
            <div className="grid grid-cols-2 gap-2">
              {STYLES.map(style => (
                <button key={style.id} onClick={() => setSelectedStyle(style.id)} className={`flex items-center gap-2 px-3 py-2 rounded-xl border text-sm transition-all ${selectedStyle === style.id ? "border-electric-blue/50 bg-electric-blue/10 text-electric-blue" : "border-white/10 bg-white/5 text-white/50 hover:text-white/70 hover:bg-white/8"}`}>
                  <span>{style.emoji}</span><span className="text-xs">{style.label}</span>
                </button>
              ))}
            </div>
          </GlassCard>

          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-4">Tone Settings</h3>
            <div className="space-y-4">
              {[
                { label: "Formality", value: formality, onChange: setFormality, low: "Casual", high: "Formal" },
                { label: "Enthusiasm", value: enthusiasm, onChange: setEnthusiasm, low: "Calm", high: "Energetic" },
              ].map(s => (
                <div key={s.label}>
                  <div className="flex justify-between text-xs text-white/50 mb-1.5">
                    <span>{s.label}</span><span className="text-electric-blue">{s.value}%</span>
                  </div>
                  <input type="range" min="0" max="100" value={s.value} onChange={e => s.onChange(Number(e.target.value))} className="w-full accent-electric-blue cursor-pointer" />
                  <div className="flex justify-between text-xs text-white/25 mt-1"><span>{s.low}</span><span>{s.high}</span></div>
                </div>
              ))}
            </div>
            <div className="flex gap-4 mt-4 pt-4 border-t border-white/8">
              <label className="flex items-center gap-2 cursor-pointer">
                <div onClick={() => setIncludeHashtags(!includeHashtags)} className={`w-4 h-4 rounded border transition-all flex items-center justify-center ${includeHashtags ? "bg-electric-blue border-electric-blue" : "border-white/20 bg-white/5"}`}>
                  {includeHashtags && <Hash className="w-3 h-3 text-white" />}
                </div>
                <span className="text-white/50 text-sm">Hashtags</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <div onClick={() => setIncludeEmojis(!includeEmojis)} className={`w-4 h-4 rounded border transition-all flex items-center justify-center ${includeEmojis ? "bg-electric-blue border-electric-blue" : "border-white/20 bg-white/5"}`}>
                  {includeEmojis && <Smile className="w-3 h-3 text-white" />}
                </div>
                <span className="text-white/50 text-sm">Emojis</span>
              </label>
            </div>
          </GlassCard>

          <motion.button onClick={handleGenerate} disabled={isGenerating || !topic.trim() || selectedPlatforms.length === 0} whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.98 }} className="w-full py-4 btn-primary rounded-2xl font-bold text-white flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed text-lg">
            {isGenerating ? (
              <><motion.div className="w-6 h-6 border-2 border-white/30 border-t-white rounded-full" animate={{ rotate: 360 }} transition={{ duration: 0.8, repeat: Infinity, ease: "linear" }} />Generating AI Content...</>
            ) : (
              <><Wand2 className="w-5 h-5" />Generate {selectedPlatforms.length} Post{selectedPlatforms.length > 1 ? "s" : ""}</>
            )}
          </motion.button>
        </div>

        {/* Generated Content */}
        <div className="xl:col-span-3 space-y-5">
          <AnimatePresence>
            {isGenerating && (
              <motion.div key="loading" initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0 }} className="glass-panel p-8 rounded-2xl text-center">
                <motion.div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-electric-blue to-neon-purple flex items-center justify-center mx-auto mb-6" animate={{ rotate: [0, 360], scale: [1, 1.1, 1] }} transition={{ duration: 2, repeat: Infinity }}>
                  <Zap className="w-8 h-8 text-white" />
                </motion.div>
                <h3 className="text-white font-bold text-xl mb-2">AI is crafting your content...</h3>
                <p className="text-white/40 text-sm mb-6">Analyzing brand voice, optimizing for each platform</p>
                <div className="space-y-2 max-w-xs mx-auto">
                  {["Analyzing topic context", "Adapting brand voice", "Optimizing for platforms", "Adding hashtags & emojis"].map((step, i) => (
                    <motion.div key={step} initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: i * 0.4 }} className="flex items-center gap-2 text-sm text-white/50">
                      <motion.div className="w-1.5 h-1.5 rounded-full bg-electric-blue" animate={{ scale: [1, 1.5, 1] }} transition={{ duration: 0.8, repeat: Infinity, delay: i * 0.2 }} />
                      {step}
                    </motion.div>
                  ))}
                </div>
              </motion.div>
            )}

            {generated && !isGenerating && (
              <motion.div key="results" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
                <div className="flex gap-2 mb-4 flex-wrap">
                  {selectedPlatforms.filter(p => MOCK_GENERATED[p]).map(p => (
                    <button key={p} onClick={() => setPreviewPlatform(p)} className={`flex items-center gap-2 px-3 py-2 rounded-xl border text-sm transition-all ${previewPlatform === p ? "border-electric-blue/50 bg-electric-blue/10 text-electric-blue" : "border-white/10 bg-white/5 text-white/50"}`}>
                      <PlatformIcon platform={p} size="xs" /><span className="capitalize">{p}</span>
                    </button>
                  ))}
                </div>

                {(() => {
                  const content = MOCK_GENERATED[previewPlatform];
                  if (!content) return null;
                  return (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                      <GlassCard padding="md">
                        <div className="flex items-center justify-between mb-3">
                          <div className="flex items-center gap-2">
                            <PlatformIcon platform={previewPlatform} size="sm" />
                            <span className="text-white font-semibold capitalize">{previewPlatform}</span>
                            <NeonBadge variant="green" size="sm" dot>{content.confidence}% confidence</NeonBadge>
                          </div>
                          <div className="flex gap-2">
                            <button onClick={() => handleCopy(content.caption, `cap-${previewPlatform}`)} className="w-7 h-7 rounded-lg bg-white/5 hover:bg-electric-blue/20 flex items-center justify-center transition-all">
                              {copiedId === `cap-${previewPlatform}` ? <Check className="w-3.5 h-3.5 text-neon-green" /> : <Copy className="w-3.5 h-3.5 text-white/40" />}
                            </button>
                            <button className="w-7 h-7 rounded-lg bg-white/5 hover:bg-neon-green/20 flex items-center justify-center transition-all">
                              <Save className="w-3.5 h-3.5 text-white/40" />
                            </button>
                          </div>
                        </div>
                        <div className="bg-white/5 rounded-xl p-3 mb-3 max-h-48 overflow-y-auto scrollbar-thin">
                          <p className="text-white/80 text-sm whitespace-pre-line leading-relaxed">{content.caption}</p>
                        </div>
                        {content.hashtags.length > 0 && (
                          <div className="flex flex-wrap gap-1.5">
                            {content.hashtags.map(tag => (
                              <span key={tag} className="text-electric-blue text-xs bg-electric-blue/10 border border-electric-blue/20 px-2 py-0.5 rounded-full">#{tag}</span>
                            ))}
                          </div>
                        )}
                        <div className="flex items-center justify-between mt-3 pt-3 border-t border-white/8">
                          <span className="text-white/30 text-xs">Est. reach: <span className="text-electric-blue">{content.estimatedReach.toLocaleString()}</span></span>
                          <button className="flex items-center gap-1 text-xs text-white/40 hover:text-white/60 transition-colors"><RefreshCw className="w-3 h-3" /> Regenerate</button>
                        </div>
                      </GlassCard>

                      <GlassCard padding="md">
                        <h3 className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-4">Live Preview</h3>
                        <div className="overflow-hidden">
                          <PostPreview platform={previewPlatform} content={content.caption} />
                        </div>
                      </GlassCard>
                    </div>
                  );
                })()}

                <div className="flex gap-3 mt-4">
                  <button className="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-neon-green/10 border border-neon-green/25 text-neon-green font-semibold hover:bg-neon-green/20 transition-all">
                    <Check className="w-4 h-4" /> Save All Posts
                  </button>
                  <button className="flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-white/5 border border-white/10 text-white/50 hover:bg-white/10 transition-all">
                    <RefreshCw className="w-4 h-4" /> Regenerate All
                  </button>
                </div>
              </motion.div>
            )}

            {!generated && !isGenerating && (
              <motion.div key="empty" initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="glass-panel p-12 rounded-2xl text-center">
                <Wand2 className="w-12 h-12 text-white/20 mx-auto mb-4" />
                <h3 className="text-white/50 font-semibold text-lg mb-2">Ready to Generate</h3>
                <p className="text-white/30 text-sm">Enter a topic, select platforms and style, then hit Generate</p>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </div>
  );
}
