"use client";

import { superAdminApi } from "@/lib/api";
import { AlertTriangle, ChevronLeft, Terminal, Zap } from "lucide-react";
import Link from "next/link";
import { useRef, useState } from "react";
import toast from "react-hot-toast";
import { AuthProvider } from "@/contexts/AuthContext";

const ALLOWED_COMMANDS = [
  "cache:clear", "config:clear", "route:clear", "view:clear",
  "optimize", "optimize:clear", "queue:restart",
  "migrate --force", "storage:link",
];

interface OutputLine { cmd?: string; output: string; type: "command" | "success" | "error" | "info" }

function TerminalContent() {
  const [history, setHistory] = useState<OutputLine[]>([
    { output: "AI Recruit Artisan Terminal — أوامر Artisan المصرح بها فقط", type: "info" },
    { output: `الأوامر المتاحة: ${ALLOWED_COMMANDS.join(", ")}`, type: "info" },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [cmdHistory, setCmdHistory] = useState<string[]>([]);
  const [histIdx, setHistIdx] = useState(-1);
  const bottomRef = useRef<HTMLDivElement>(null);

  const run = async () => {
    const cmd = input.trim();
    if (!cmd) return;
    setInput("");
    setCmdHistory((p) => [cmd, ...p]);
    setHistIdx(-1);
    setHistory((p) => [...p, { cmd, output: `$ php artisan ${cmd}`, type: "command" }]);
    setLoading(true);
    try {
      const res = await superAdminApi.terminal(cmd);
      setHistory((p) => [...p, { output: res.data.output || "تم التنفيذ", type: "success" }]);
    } catch (err: any) {
      setHistory((p) => [...p, { output: err?.response?.data?.message || "خطأ في التنفيذ", type: "error" }]);
    } finally {
      setLoading(false);
      setTimeout(() => bottomRef.current?.scrollIntoView({ behavior: "smooth" }), 50);
    }
  };

  const handleKey = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") { run(); return; }
    if (e.key === "ArrowUp") { const idx = Math.min(histIdx + 1, cmdHistory.length - 1); setHistIdx(idx); setInput(cmdHistory[idx] || ""); }
    if (e.key === "ArrowDown") { const idx = Math.max(histIdx - 1, -1); setHistIdx(idx); setInput(idx === -1 ? "" : cmdHistory[idx] || ""); }
  };

  return (
    <div className="min-h-screen bg-[#0d1117] flex flex-col" dir="ltr">
      {/* Header */}
      <header className="h-12 bg-[#161b22] border-b border-[#30363d] flex items-center justify-between px-4">
        <div className="flex items-center gap-3">
          <Link href="/super-admin/dashboard" className="w-7 h-7 bg-violet-600 rounded-md flex items-center justify-center" dir="rtl">
            <Zap className="w-3.5 h-3.5 text-white" />
          </Link>
          <div className="flex items-center gap-2 text-[#8b949e] text-sm">
            <Terminal className="w-4 h-4" />
            <span>Artisan Terminal</span>
          </div>
        </div>
        <div className="flex items-center gap-2 text-[#f97316] text-xs">
          <AlertTriangle className="w-3.5 h-3.5" />
          <span>Production Environment — Whitelisted Commands Only</span>
        </div>
      </header>

      {/* Terminal output */}
      <div className="flex-1 overflow-y-auto p-4 font-mono text-sm space-y-1">
        {history.map((line, i) => (
          <div key={i} className={`leading-relaxed ${
            line.type === "command" ? "text-[#79c0ff]" :
            line.type === "success" ? "text-[#3fb950]" :
            line.type === "error" ? "text-[#f85149]" :
            "text-[#8b949e]"
          }`}>
            {line.output}
          </div>
        ))}
        {loading && (
          <div className="flex items-center gap-2 text-[#8b949e]">
            <div className="flex gap-1">
              {[0, 1, 2].map((i) => <span key={i} className="animate-pulse" style={{ animationDelay: `${i * 0.2}s` }}>.</span>)}
            </div>
            <span>Running...</span>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="border-t border-[#30363d] bg-[#161b22] p-4">
        <div className="flex items-center gap-3">
          <span className="text-[#3fb950] font-mono text-sm flex-shrink-0">$ php artisan</span>
          <input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={handleKey}
            disabled={loading}
            placeholder={ALLOWED_COMMANDS[0]}
            className="flex-1 bg-transparent text-[#c9d1d9] font-mono text-sm focus:outline-none placeholder:text-[#30363d]"
            autoFocus
          />
          <button onClick={run} disabled={loading || !input.trim()}
            className="px-4 py-1.5 bg-violet-600 hover:bg-violet-500 disabled:opacity-40 text-white text-xs font-medium rounded transition-colors">
            Run
          </button>
        </div>
        <div className="mt-3 flex flex-wrap gap-2">
          {ALLOWED_COMMANDS.map((cmd) => (
            <button key={cmd} onClick={() => setInput(cmd)}
              className="text-xs text-[#8b949e] hover:text-[#c9d1d9] bg-[#21262d] hover:bg-[#30363d] px-2 py-1 rounded border border-[#30363d] transition-colors font-mono">
              {cmd}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

export default function SuperAdminTerminalPage() {
  return <AuthProvider><TerminalContent /></AuthProvider>;
}
