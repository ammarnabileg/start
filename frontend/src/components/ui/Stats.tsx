import { cn } from "@/lib/utils";
import React from "react";

interface StatCardProps {
  label: string;
  value: string | number;
  icon?: React.ReactNode;
  trend?: { value: number; label: string };
  color?: "purple" | "green" | "blue" | "orange" | "red";
  className?: string;
}

const colorMap = {
  purple: { icon: "bg-violet-100 text-violet-600", trend: "text-violet-600" },
  green: { icon: "bg-green-100 text-green-600", trend: "text-green-600" },
  blue: { icon: "bg-blue-100 text-blue-600", trend: "text-blue-600" },
  orange: { icon: "bg-orange-100 text-orange-600", trend: "text-orange-600" },
  red: { icon: "bg-red-100 text-red-600", trend: "text-red-600" },
};

export function StatCard({ label, value, icon, trend, color = "purple", className }: StatCardProps) {
  const colors = colorMap[color];
  return (
    <div className={cn("bg-white rounded-xl border border-gray-200 shadow-sm p-5", className)}>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-gray-500 font-medium">{label}</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">{value}</p>
          {trend && (
            <p className={cn("text-xs font-medium mt-1", trend.value >= 0 ? "text-green-600" : "text-red-500")}>
              {trend.value >= 0 ? "+" : ""}{trend.value}% {trend.label}
            </p>
          )}
        </div>
        {icon && <div className={cn("p-3 rounded-xl", colors.icon)}>{icon}</div>}
      </div>
    </div>
  );
}

export function StatsGrid({ children, cols = 4 }: { children: React.ReactNode; cols?: 2 | 3 | 4 | 5 }) {
  const gridCols = { 2: "grid-cols-1 sm:grid-cols-2", 3: "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3", 4: "grid-cols-1 sm:grid-cols-2 lg:grid-cols-4", 5: "grid-cols-2 sm:grid-cols-3 lg:grid-cols-5" };
  return <div className={cn("grid gap-4", gridCols[cols])}>{children}</div>;
}
