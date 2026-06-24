import { cn } from "@/lib/utils";
import React from "react";

interface BadgeProps {
  children: React.ReactNode;
  variant?: "purple" | "green" | "yellow" | "red" | "gray" | "blue" | "orange";
  size?: "sm" | "md";
  className?: string;
}

const variants = {
  purple: "bg-violet-100 text-violet-700",
  green: "bg-green-100 text-green-700",
  yellow: "bg-yellow-100 text-yellow-700",
  red: "bg-red-100 text-red-700",
  gray: "bg-gray-100 text-gray-600",
  blue: "bg-blue-100 text-blue-700",
  orange: "bg-orange-100 text-orange-700",
};

export function Badge({ children, variant = "gray", size = "md", className }: BadgeProps) {
  return (
    <span className={cn(
      "inline-flex items-center gap-1 rounded-full font-semibold",
      variants[variant],
      size === "sm" ? "px-2 py-0.5 text-xs" : "px-2.5 py-1 text-xs",
      className
    )}>
      {children}
    </span>
  );
}

export function ScoreBadge({ score }: { score: number }) {
  const variant = score >= 82 ? "green" : score >= 68 ? "blue" : score >= 50 ? "yellow" : "red";
  return <Badge variant={variant} size="sm">{Math.round(score)}%</Badge>;
}
