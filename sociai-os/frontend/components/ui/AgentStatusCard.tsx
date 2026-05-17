"use client";

import { motion, AnimatePresence } from "framer-motion";
import { Play, Square, Clock, CheckCircle, AlertCircle, Pause } from "lucide-react";
import { AgentStatus } from "@/lib/types";
import { cn } from "@/lib/utils";

interface AgentStatusCardProps {
  name: string;
  description: string;
  status: AgentStatus;
  icon: string;
  color: string;
  gradientFrom: string;
  gradientTo: string;
  currentTask?: string;
  progress?: number;
  tasksCompleted: number;
  lastRun?: string;
  capabilities: string[];
  onRun?: () => void;
  onStop?: () => void;
  delay?: number;
}

const statusConfig: Record<AgentStatus, { label: string; icon: React.ComponentType<{ className?: string }>; textColor: string; bgColor: string }> = {
  idle: { label: "Idle", icon: Clock, textColor: "text-white/40", bgColor: "bg-white/10" },
  running: { label: "Running", icon: Play, textColor: "text-electric-blue", bgColor: "bg-electric-blue/20" },
  complete: { label: "Complete", icon: CheckCircle, textColor: "text-neon-green", bgColor: "bg-neon-green/20" },
  error: { label: "Error", icon: AlertCircle, textColor: "text-red-400", bgColor: "bg-red-500/20" },
  paused: { label: "Paused", icon: Pause, textColor: "text-yellow-400", bgColor: "bg-yellow-500/20" },
};

// Dynamic icon map
const ICON_EMOJIS: Record<string, string> = {
  Sparkles: "✨",
  PenTool: "🖊️",
  TrendingUp: "📈",
  BarChart2: "📊",
  Users: "👥",
  Calendar: "📅",
  Brain: "🧠",
  Clock: "⏰",
};

export function AgentStatusCard({
  name, description, status, icon, color, gradientFrom, gradientTo,
  currentTask, progress, tasksCompleted, lastRun, capabilities, onRun, onStop, delay = 0
}: AgentStatusCardProps) {
  const config = statusConfig[status];
  const StatusIcon = config.icon;
  const isRunning = status === "running";

  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay }}
      className="glass-panel rounded-2xl border border-white/10 overflow-hidden hover:border-white/20 transition-all duration-300 group"
    >
      {/* Progress bar - top */}
      {isRunning && progress !== undefined && (
        <div className="h-0.5 bg-white/10">
          <motion.div
            className="h-full rounded-full"
            style={{ background: `linear-gradient(90deg, ${gradientFrom}, ${gradientTo})` }}
            animate={{ width: `${progress}%` }}
            transition={{ duration: 0.5 }}
          />
        </div>
      )}

      <div className="p-5">
        {/* Header */}
        <div className="flex items-start gap-3 mb-4">
          {/* Agent Icon */}
          <div className="relative flex-shrink-0">
            <div
              className="w-12 h-12 rounded-xl flex items-center justify-center text-2xl font-bold"
              style={{ background: `linear-gradient(135deg, ${gradientFrom}30, ${gradientTo}20)`, border: `1px solid ${gradientFrom}40` }}
            >
              {ICON_EMOJIS[icon] || "🤖"}
            </div>
            {/* Pulse ring when running */}
            {isRunning && (
              <motion.div
                className="absolute inset-0 rounded-xl border-2"
                style={{ borderColor: gradientFrom }}
                animate={{ scale: [1, 1.2, 1], opacity: [0.8, 0, 0.8] }}
                transition={{ duration: 2, repeat: Infinity }}
              />
            )}
          </div>

          <div className="flex-1 min-w-0">
            <h3 className="text-white font-semibold text-sm">{name}</h3>
            <p className="text-white/40 text-xs mt-0.5 truncate">{description}</p>
          </div>

          {/* Status Badge */}
          <span className={cn("flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full", config.textColor, config.bgColor)}>
            <StatusIcon className="w-3 h-3" />
            {config.label}
          </span>
        </div>

        {/* Current Task */}
        <AnimatePresence>
          {currentTask && isRunning && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              exit={{ opacity: 0, height: 0 }}
              className="mb-3"
            >
              <div className="flex items-center gap-2 bg-electric-blue/10 border border-electric-blue/20 rounded-lg px-3 py-2">
                <motion.div
                  className="w-1.5 h-1.5 rounded-full bg-electric-blue"
                  animate={{ scale: [1, 1.5, 1] }}
                  transition={{ duration: 1, repeat: Infinity }}
                />
                <span className="text-electric-blue text-xs truncate">{currentTask}</span>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Progress bar inline */}
        {isRunning && progress !== undefined && (
          <div className="mb-3">
            <div className="flex justify-between text-xs text-white/40 mb-1">
              <span>Progress</span>
              <span>{progress}%</span>
            </div>
            <div className="h-1.5 bg-white/10 rounded-full overflow-hidden">
              <motion.div
                className="h-full rounded-full"
                style={{ background: `linear-gradient(90deg, ${gradientFrom}, ${gradientTo})` }}
                animate={{ width: `${progress}%` }}
              />
            </div>
          </div>
        )}

        {/* Capabilities */}
        <div className="flex flex-wrap gap-1 mb-4">
          {capabilities.slice(0, 3).map(cap => (
            <span key={cap} className="text-xs px-2 py-0.5 rounded-full bg-white/5 border border-white/10 text-white/40">
              {cap}
            </span>
          ))}
        </div>

        {/* Stats */}
        <div className="flex items-center justify-between text-xs text-white/30 mb-4">
          <span>{tasksCompleted} tasks completed</span>
          {lastRun && <span>Last: {lastRun}</span>}
        </div>

        {/* Action */}
        <button
          onClick={isRunning ? onStop : onRun}
          className={cn(
            "w-full flex items-center justify-center gap-2 py-2 rounded-xl text-sm font-semibold transition-all",
            isRunning
              ? "bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20"
              : "text-white hover:shadow-lg"
          )}
          style={!isRunning ? { background: `linear-gradient(135deg, ${gradientFrom}, ${gradientTo})` } : undefined}
        >
          {isRunning ? (
            <><Square className="w-3.5 h-3.5" /> Stop Agent</>
          ) : (
            <><Play className="w-3.5 h-3.5" /> Run Agent</>
          )}
        </button>
      </div>
    </motion.div>
  );
}

export default AgentStatusCard;
