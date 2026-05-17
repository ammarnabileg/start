"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Copy, Save, RefreshCw, Wand2, Check, Star } from "lucide-react";
import { GlassCard } from "@/components/ui/GlassCard";
import { NeonBadge } from "@/components/ui/NeonBadge";

const COPY_TYPES = [
  { id: "caption", label: "Caption", emoji: "💬" },
  { id: "thread", label: "Thread", emoji: "🧵" },
  { id: "script", label: "Video Script", emoji: "🎬" },
  { id: "hook", label: "Hook", emoji: "⚡" },
  { id: "cta", label: "Call to Action", emoji: "👆" },
  { id: "ad-copy", label: "Ad Copy", emoji: "📢" },
  { id: "carousel-text", label: "Carousel", emoji: "🃏" },
  { id: "story-text", label: "Story Text", emoji: "📖" },
  { id: "comment-reply", label: "Comment Reply", emoji: "💭" },
  { id: "dm-template", label: "DM Template", emoji: "✉️" },
];

const MOCK_OUTPUTS: Record<string, { main: string; variations: string[] }> = {
  caption: {
    main: "The secret to 10x growth isn't working harder — it's working smarter. 🧠\n\nAfter studying 500+ brands, here's what the top performers all have in common:\n\n✅ They batch-create content\n✅ They repurpose ruthlessly\n✅ They engage BEFORE they post\n✅ They track what works\n\nWich one are you missing? 👇",
    variations: [
      "Most brands struggle to grow because they're stuck in the 'post and pray' cycle. The ones that win? They have a system. Here's mine...",
      "Growth hack or strategy? The brands that 10x their following don't chase hacks. They build systems. Let me show you the difference...",
    ]
  },
  hook: {
    main: "I studied 100 viral posts and found the ONE thing they all had in common...",
    variations: [
      "Most people don't know this about the Instagram algorithm (and it's costing them thousands of views)...",
      "Stop posting without a strategy. Here's what 7-figure brands do instead:",
    ]
  },
  cta: {
    main: "Ready to transform your social media game? Drop a 🚀 below and I'll send you my free content calendar template!",
    variations: [
      "Comment 'GUIDE' below and I'll DM you our complete growth playbook — no cost, no catch.",
      "Save this post. You'll thank yourself later when you're implementing these strategies.",
    ]
  },
  thread: {
    main: "Thread: How I grew from 0 to 100K followers in 6 months (and what I'd do differently) 🧵\n\n1/ Most growth advice is garbage. Here's what actually works:\n\n2/ Forget virality. Focus on consistency. Posting 1x/day for 6 months beats going viral once.\n\n3/ Your first 10K followers are the hardest. After that, the algorithm works for you.\n\n4/ The comment section is where the real growth happens. Engage with EVERY reply.\n\n5/ Use the first 90 minutes after posting to boost engagement signals.",
    variations: [
      "I made $50K from social media last year. Here's the exact framework I used (a thread):\n\n1/ Built an audience first, monetized second...",
    ]
  },
  script: {
    main: "[HOOK - 0:00-0:05]\nStop scrolling! If you're struggling to grow on social media, this video is for you.\n\n[PROBLEM - 0:05-0:15]\nMost creators spend hours creating content that gets 10 views. Here's why—and how to fix it.\n\n[SOLUTION - 0:15-1:30]\nThere are 3 things every viral piece of content has in common...\n\n[CTA - 1:30-1:45]\nIf this helped, subscribe for weekly social media strategies. Comment your biggest struggle below!",
    variations: []
  },
};

export default function CopywritingPage() {
  const [activeType, setActiveType] = useState("caption");
  const [topic, setTopic] = useState("");
  const [brandVoice, setBrandVoice] = useState("");
  const [targetAudience, setTargetAudience] = useState("");
  const [language, setLanguage] = useState<"en" | "ar" | "mixed">("en");
  const [variations, setVariations] = useState(2);
  const [isGenerating, setIsGenerating] = useState(false);
  const [generated, setGenerated] = useState(false);
  const [copiedId, setCopiedId] = useState<string | null>(null);
  const [saved, setSaved] = useState<Set<string>>(new Set());

  const handleGenerate = async () => {
    if (!topic.trim()) return;
    setIsGenerating(true);
    await new Promise(r => setTimeout(r, 1800));
    setIsGenerating(false);
    setGenerated(true);
  };

  const handleCopy = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleSave = (id: string) => {
    setSaved(prev => new Set(Array.from(prev).concat(id)));
  };

  const output = MOCK_OUTPUTS[activeType] || MOCK_OUTPUTS.caption;

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
        <h1 className="text-2xl font-bold text-white">Copywriting Studio</h1>
        <p className="text-white/40 text-sm">AI-powered copy for every content type in your brand voice</p>
      </motion.div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {/* Left: Type selector + Config */}
        <div className="space-y-5">
          {/* Copy Type */}
          <GlassCard padding="md">
            <h3 className="text-white font-semibold mb-3">Content Type</h3>
            <div className="space-y-1">
              {COPY_TYPES.map(type => (
                <button
                  key={type.id}
                  onClick={() => { setActiveType(type.id); setGenerated(false); }}
                  className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all text-left ${activeType === type.id ? "bg-electric-blue/15 border border-electric-blue/30 text-white" : "hover:bg-white/5 text-white/50 hover:text-white/80"}`}
                >
                  <span className="text-lg">{type.emoji}</span>
                  <span className="font-medium text-sm">{type.label}</span>
                  {activeType === type.id && <span className="ml-auto w-2 h-2 rounded-full bg-electric-blue" />}
                </button>
              ))}
            </div>
          </GlassCard>
        </div>

        {/* Middle: Input */}
        <GlassCard padding="md">
          <h3 className="text-white font-semibold mb-4">Content Brief</h3>
          <div className="space-y-4">
            <div>
              <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Topic / Subject *</label>
              <textarea
                value={topic}
                onChange={e => setTopic(e.target.value)}
                placeholder={activeType === "hook" ? "E.g.: Morning productivity habits..." : activeType === "cta" ? "E.g.: Free content calendar template..." : "E.g.: 5 habits for better focus..."}
                className="input-glass resize-none h-20 text-sm"
              />
            </div>

            <div>
              <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Brand Voice</label>
              <input
                type="text"
                value={brandVoice}
                onChange={e => setBrandVoice(e.target.value)}
                placeholder="E.g.: Professional yet approachable, bold, inspiring..."
                className="input-glass text-sm"
              />
            </div>

            <div>
              <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Target Audience</label>
              <input
                type="text"
                value={targetAudience}
                onChange={e => setTargetAudience(e.target.value)}
                placeholder="E.g.: Entrepreneurs aged 25-40..."
                className="input-glass text-sm"
              />
            </div>

            <div>
              <label className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-1.5 block">Language</label>
              <div className="flex gap-2">
                {[{ id: "en", label: "EN" }, { id: "ar", label: "AR" }, { id: "mixed", label: "Mix" }].map(l => (
                  <button key={l.id} onClick={() => setLanguage(l.id as typeof language)} className={`flex-1 py-2 rounded-xl border text-sm font-medium transition-all ${language === l.id ? "bg-electric-blue/20 border-electric-blue/40 text-electric-blue" : "bg-white/5 border-white/10 text-white/50"}`}>
                    {l.label}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <div className="flex justify-between text-xs text-white/50 mb-1.5">
                <span>Variations</span>
                <span className="text-electric-blue">{variations}</span>
              </div>
              <input type="range" min="1" max="5" value={variations} onChange={e => setVariations(Number(e.target.value))} className="w-full accent-electric-blue cursor-pointer" />
            </div>

            <motion.button
              onClick={handleGenerate}
              disabled={!topic.trim() || isGenerating}
              whileHover={{ scale: 1.01 }}
              whileTap={{ scale: 0.99 }}
              className="w-full py-3 btn-primary rounded-xl font-bold text-white flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isGenerating ? (
                <><motion.div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full" animate={{ rotate: 360 }} transition={{ duration: 0.8, repeat: Infinity, ease: "linear" }} /> Generating...</>
              ) : (
                <><Wand2 className="w-4 h-4" /> Generate Copy</>
              )}
            </motion.button>
          </div>
        </GlassCard>

        {/* Right: Output */}
        <div className="space-y-4">
          <AnimatePresence>
            {generated && !isGenerating ? (
              <motion.div key="output" initial={{ opacity: 0, y: 15 }} animate={{ opacity: 1, y: 0 }}>
                {/* Main Output */}
                <GlassCard padding="md">
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <span className="text-lg">{COPY_TYPES.find(t => t.id === activeType)?.emoji}</span>
                      <span className="text-white font-semibold text-sm">{COPY_TYPES.find(t => t.id === activeType)?.label}</span>
                      <NeonBadge variant="green" size="sm">Ready</NeonBadge>
                    </div>
                    <div className="flex gap-2">
                      <button onClick={() => handleCopy(output.main, "main")} className="w-7 h-7 rounded-lg bg-white/5 hover:bg-electric-blue/20 flex items-center justify-center transition-all">
                        {copiedId === "main" ? <Check className="w-3.5 h-3.5 text-neon-green" /> : <Copy className="w-3.5 h-3.5 text-white/40" />}
                      </button>
                      <button onClick={() => handleSave("main")} className={`w-7 h-7 rounded-lg flex items-center justify-center transition-all ${saved.has("main") ? "bg-neon-green/20" : "bg-white/5 hover:bg-neon-green/20"}`}>
                        {saved.has("main") ? <Star className="w-3.5 h-3.5 text-neon-green fill-neon-green" /> : <Save className="w-3.5 h-3.5 text-white/40" />}
                      </button>
                    </div>
                  </div>
                  <div className="bg-white/5 rounded-xl p-4 max-h-60 overflow-y-auto scrollbar-thin">
                    <p className="text-white/80 text-sm whitespace-pre-line leading-relaxed">{output.main}</p>
                  </div>
                  <div className="flex items-center justify-between mt-3 text-xs text-white/30">
                    <span>{output.main.length} chars · {output.main.split(" ").length} words</span>
                    <button className="flex items-center gap-1 hover:text-white/50 transition-colors">
                      <RefreshCw className="w-3 h-3" /> Regenerate
                    </button>
                  </div>
                </GlassCard>

                {/* Variations */}
                {output.variations.length > 0 && (
                  <div className="space-y-3 mt-4">
                    <h4 className="text-white/50 text-xs font-semibold uppercase tracking-wider">Variations</h4>
                    {output.variations.map((v, i) => (
                      <GlassCard key={i} padding="sm">
                        <div className="flex items-start justify-between gap-3">
                          <p className="text-white/70 text-sm leading-relaxed flex-1">{v}</p>
                          <div className="flex gap-1 flex-shrink-0">
                            <button onClick={() => handleCopy(v, `var-${i}`)} className="w-6 h-6 rounded-lg bg-white/5 hover:bg-electric-blue/20 flex items-center justify-center transition-all">
                              {copiedId === `var-${i}` ? <Check className="w-3 h-3 text-neon-green" /> : <Copy className="w-3 h-3 text-white/30" />}
                            </button>
                          </div>
                        </div>
                      </GlassCard>
                    ))}
                  </div>
                )}
              </motion.div>
            ) : (
              <motion.div key="empty" className="glass-panel p-10 rounded-2xl text-center">
                <Wand2 className="w-10 h-10 text-white/20 mx-auto mb-3" />
                <h3 className="text-white/50 font-semibold mb-1">Your copy will appear here</h3>
                <p className="text-white/25 text-sm">Fill in the brief and click Generate</p>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </div>
  );
}
