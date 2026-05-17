"use client";

import { motion } from "framer-motion";
import { TrendingUp, TrendingDown, Minus } from "lucide-react";
import { cn } from "@/lib/utils";
import { formatNumber } from "@/lib/utils";
import { ReactNode } from "react";

interface MetricCardProps {
  label: string;
  value: string | number;
  change?: number;
  changeLabel?: string;
  icon?: ReactNode;
  color?: "blue" | "purple" | "green" | "pink" | "orange" | "yellow";
  loading?: boolean;
  suffix?: string;
  prefix?: string;
  delay?: number;
  sparkline?: number[];
}

const colorMap = {
  blue: { bg: "from-electric-blue/20 to-electric-blue/5", icon: "bg-electric-blue/20 text-electric-blue", text: "text-electric-blue", border: "border-electric-blue/20" },
  purple: { bg: "from-neon-purple/20 to-neon-purple/5", icon: "bg-neon-purple/20 text-purple-400", text: "text-purple-400", border: "border-purple-500/20" },
  green: { bg: "from-neon-green/20 to-neon-green/5", icon: "bg-neon-green/20 text-neon-green", text: "text-neon-green", border: "border-neon-green/20" },
  pink: { bg: "from-pink-500/20 to-pink-500/5", icon: "bg-pink-500/20 text-pink-400", text: "text-pink-400", border: "border-pink-500/20" },
  orange: { bg: "from-orange-500/20 to-orange-500/5", icon: "bg-orange-500/20 text-orange-400", text: "text-orange-400", border: "border-orange-500/20" },
  yellow: { bg: "from-yellow-500/20 to-yellow-500/5", icon: "bg-yellow-500/20 text-yellow-400", text: "text-yellow-400", border: "border-yellow-500/20" },
};

// Mini sparkline renderer
function Sparkline({ data, color }: { data: number[]; color: string }) {
  if (!data || data.length < 2) return null;
  const min = Math.min(...data);
  const max = Math.max(...data);
  const range = max - min || 1;
  const width = 80;
  const height = 24;
  const points = data.map((v, i) => ({
    x: (i / (data.length - 1)) * width,
    y: height - ((v - min) / range) * height,
  }));
  const path = points.map((p, i) => `${i === 0 ? "M" : "L"} ${p.x} ${p.y}`).join(" ");

  return (
    <svg width={width} height={height} className="opacity-60">
      <path d={path} fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function MetricCard({ label, value, change, changeLabel, icon, color = "blue", loading = false, suffix, prefix, delay = 0, sparkline }: MetricCardProps) {
  const colors = colorMap[color];
  const isPositive = (change ?? 0) > 0;
  const isNegative = (change ?? 0) < 0;

  if (loading) {
    return (
      <div className="glass-panel p-5 rounded-2xl border border-white/10">
        <div className="skeleton h-4 w-24 mb-3 rounded" />
        <div className="skeleton h-8 w-32 mb-2 rounded" />
        <div className="skeleton h-3 w-20 rounded" />
      </div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay, ease: "easeOut" }}
      whileHover={{ y: -3, transition: { duration: 0.2 } }}
      className={cn(
        "glass-panel p-5 rounded-2xl border transition-all duration-300 relative overflow-hidden",
        colors.border,
        "hover:shadow-lg cursor-default group"
      )}
    >
      {/* Background gradient */}
      <div className={cn("absolute inset-0 bg-gradient-to-br opacity-30 rounded-2xl pointer-events-none", colors.bg)} />

      <div className="relative z-10">
        {/* Header */}
        <div className="flex items-start justify-between mb-3">
          <div>
            <p className="text-white/50 text-xs font-medium uppercase tracking-wider">{label}</p>
          </div>
          {icon && (
            <div className={cn("w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0", colors.icon)}>
              {icon}
            </div>
          )}
        </div>

        {/* Value */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: delay + 0.1 }}
          className="mb-3"
        >
          <span className="text-white text-2xl font-bold tracking-tight">
            {prefix}{typeof value === "number" ? formatNumber(value) : value}{suffix}
          </span>
        </motion.div>

        {/* Change indicator + Sparkline */}
        <div className="flex items-center justify-between">
          {change !== undefined && (
            <div className={cn("flex items-center gap-1 text-xs font-semibold", isPositive ? "text-neon-green" : isNegative ? "text-red-400" : "text-white/40")}>
              {isPositive ? <TrendingUp className="w-3 h-3" /> : isNegative ? <TrendingDown className="w-3 h-3" /> : <Minus className="w-3 h-3" />}
              {Math.abs(change).toFixed(1)}%
              {changeLabel && <span className="text-white/30 font-normal ml-0.5">{changeLabel}</span>}
            </div>
          )}
          {sparkline && (
            <Sparkline data={sparkline} color={color === "blue" ? "#3B82F6" : color === "green" ? "#10B981" : color === "purple" ? "#8B5CF6" : "#EC4899"} />
          )}
        </div>
      </div>
    </motion.div>
  );
}

export default MetricCard;
