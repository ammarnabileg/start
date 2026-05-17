"use client";

import { cn } from "@/lib/utils";
import { ReactNode } from "react";

type BadgeVariant = "blue" | "purple" | "green" | "red" | "yellow" | "pink" | "gray" | "orange"
  // Semantic aliases
  | "success" | "error" | "warning" | "info" | "default";

interface NeonBadgeProps {
  children: ReactNode;
  variant?: BadgeVariant;
  size?: "sm" | "md" | "lg";
  pulse?: boolean;
  dot?: boolean;
  className?: string;
}

const variants: Record<string, string> = {
  blue: "bg-blue-500/15 text-blue-400 border-blue-500/30",
  purple: "bg-purple-500/15 text-purple-400 border-purple-500/30",
  green: "bg-green-500/15 text-green-400 border-green-500/30",
  red: "bg-red-500/15 text-red-400 border-red-500/30",
  yellow: "bg-yellow-500/15 text-yellow-400 border-yellow-500/30",
  pink: "bg-pink-500/15 text-pink-400 border-pink-500/30",
  gray: "bg-white/8 text-white/50 border-white/15",
  orange: "bg-orange-500/15 text-orange-400 border-orange-500/30",
  // Semantic aliases
  success: "bg-green-500/15 text-green-400 border-green-500/30",
  error: "bg-red-500/15 text-red-400 border-red-500/30",
  warning: "bg-yellow-500/15 text-yellow-400 border-yellow-500/30",
  info: "bg-blue-500/15 text-blue-400 border-blue-500/30",
  default: "bg-white/8 text-white/50 border-white/15",
};

const dotColors: Record<string, string> = {
  blue: "bg-blue-400", purple: "bg-purple-400", green: "bg-green-400",
  red: "bg-red-400", yellow: "bg-yellow-400", pink: "bg-pink-400",
  gray: "bg-white/40", orange: "bg-orange-400",
  success: "bg-green-400", error: "bg-red-400", warning: "bg-yellow-400",
  info: "bg-blue-400", default: "bg-white/40",
};

const sizes = {
  sm: "text-xs px-2 py-0.5",
  md: "text-xs px-2.5 py-1",
  lg: "text-sm px-3 py-1.5",
};

export function NeonBadge({
  children,
  variant = "blue",
  size = "md",
  pulse = false,
  dot = false,
  className,
}: NeonBadgeProps) {
  const resolvedVariant = variant as string;
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border font-medium leading-none",
        variants[resolvedVariant] || variants.gray,
        sizes[size],
        className
      )}
    >
      {dot && (
        <span
          className={cn(
            "w-1.5 h-1.5 rounded-full flex-shrink-0",
            dotColors[resolvedVariant] || dotColors.gray,
            pulse && "animate-pulse"
          )}
        />
      )}
      {children}
    </span>
  );
}

// Status-specific badges
export const statusVariantMap: Record<string, BadgeVariant> = {
  draft: "gray",
  review: "yellow",
  approved: "blue",
  scheduled: "purple",
  published: "green",
  failed: "red",
  active: "green",
  paused: "yellow",
  completed: "blue",
  cancelled: "red",
  planning: "purple",
  running: "blue",
  idle: "gray",
  complete: "green",
  error: "red",
  positive: "green",
  neutral: "gray",
  negative: "red",
  spam: "orange",
  rising: "green",
  peak: "blue",
  declining: "yellow",
};

export function StatusBadge({ status }: { status: string }) {
  const variant = statusVariantMap[status] || "gray";
  return (
    <NeonBadge variant={variant} dot pulse={variant === "blue" && status === "running"}>
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </NeonBadge>
  );
}

export default NeonBadge;
