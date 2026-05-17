"use client";

import { motion } from "framer-motion";
import {
  LineChart, Line, AreaChart, Area, BarChart, Bar,
  XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend
} from "recharts";
import { cn } from "@/lib/utils";

interface ChartProps {
  data: Record<string, string | number>[];
  type?: "line" | "area" | "bar";
  metrics: {
    key: string;
    label: string;
    color: string;
    strokeDasharray?: string;
  }[];
  height?: number;
  showGrid?: boolean;
  showLegend?: boolean;
  className?: string;
  loading?: boolean;
  animate?: boolean;
}

const CustomTooltip = ({ active, payload, label }: { active?: boolean; payload?: { color: string; name: string; value: number }[]; label?: string }) => {
  if (!active || !payload?.length) return null;
  return (
    <div className="glass-panel-dark border border-white/10 rounded-xl px-3 py-2.5 shadow-glass">
      <p className="text-white/50 text-xs mb-2">{label}</p>
      {payload.map((p) => (
        <div key={p.name} className="flex items-center gap-2 text-xs">
          <div className="w-2 h-2 rounded-full" style={{ background: p.color }} />
          <span className="text-white/60">{p.name}:</span>
          <span className="text-white font-semibold">{p.value.toLocaleString()}</span>
        </div>
      ))}
    </div>
  );
};

export function MetricsChart({
  data, type = "area", metrics, height = 240, showGrid = true,
  showLegend = true, className, loading = false, animate = true
}: ChartProps) {
  if (loading) {
    return (
      <div className={cn("skeleton rounded-xl", className)} style={{ height }} />
    );
  }

  const chartProps = {
    data,
    margin: { top: 5, right: 20, left: 0, bottom: 5 },
  };

  const commonAxisProps = {
    tick: { fill: "rgba(255,255,255,0.35)", fontSize: 11 },
    axisLine: { stroke: "rgba(255,255,255,0.06)" },
    tickLine: false,
  };

  const renderChart = () => {
    if (type === "bar") {
      return (
        <BarChart {...chartProps}>
          {showGrid && <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />}
          <XAxis dataKey="date" {...commonAxisProps} />
          <YAxis {...commonAxisProps} width={45} />
          <Tooltip content={<CustomTooltip />} />
          {showLegend && <Legend wrapperStyle={{ color: "rgba(255,255,255,0.5)", fontSize: 12 }} />}
          {metrics.map((m) => (
            <Bar key={m.key} dataKey={m.key} name={m.label} fill={m.color} radius={[4, 4, 0, 0]} />
          ))}
        </BarChart>
      );
    }

    if (type === "line") {
      return (
        <LineChart {...chartProps}>
          {showGrid && <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />}
          <XAxis dataKey="date" {...commonAxisProps} />
          <YAxis {...commonAxisProps} width={45} />
          <Tooltip content={<CustomTooltip />} />
          {showLegend && <Legend wrapperStyle={{ color: "rgba(255,255,255,0.5)", fontSize: 12 }} />}
          {metrics.map((m) => (
            <Line
              key={m.key}
              type="monotone"
              dataKey={m.key}
              name={m.label}
              stroke={m.color}
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4, fill: m.color, stroke: "#0A0B1A", strokeWidth: 2 }}
              strokeDasharray={m.strokeDasharray}
              animationBegin={0}
              animationDuration={animate ? 1000 : 0}
            />
          ))}
        </LineChart>
      );
    }

    // Default: area
    return (
      <AreaChart {...chartProps}>
        <defs>
          {metrics.map((m) => (
            <linearGradient key={m.key} id={`gradient-${m.key}`} x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor={m.color} stopOpacity={0.3} />
              <stop offset="95%" stopColor={m.color} stopOpacity={0.02} />
            </linearGradient>
          ))}
        </defs>
        {showGrid && <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />}
        <XAxis dataKey="date" {...commonAxisProps} />
        <YAxis {...commonAxisProps} width={45} />
        <Tooltip content={<CustomTooltip />} />
        {showLegend && <Legend wrapperStyle={{ color: "rgba(255,255,255,0.5)", fontSize: 12 }} />}
        {metrics.map((m) => (
          <Area
            key={m.key}
            type="monotone"
            dataKey={m.key}
            name={m.label}
            stroke={m.color}
            strokeWidth={2}
            fill={`url(#gradient-${m.key})`}
            dot={false}
            activeDot={{ r: 4, fill: m.color, stroke: "#0A0B1A", strokeWidth: 2 }}
            animationBegin={0}
            animationDuration={animate ? 1000 : 0}
          />
        ))}
      </AreaChart>
    );
  };

  return (
    <motion.div
      initial={animate ? { opacity: 0 } : undefined}
      animate={animate ? { opacity: 1 } : undefined}
      transition={{ duration: 0.5 }}
      className={className}
    >
      <ResponsiveContainer width="100%" height={height}>
        {renderChart()}
      </ResponsiveContainer>
    </motion.div>
  );
}

export default MetricsChart;
