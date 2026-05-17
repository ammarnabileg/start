"use client";

import { motion } from "framer-motion";
import { cn } from "@/lib/utils";
import { ReactNode } from "react";

interface GlassCardProps {
  children: ReactNode;
  className?: string;
  hover?: boolean;
  glow?: "blue" | "purple" | "green" | "pink" | "none";
  gradient?: boolean;
  onClick?: () => void;
  animate?: boolean;
  delay?: number;
  padding?: "sm" | "md" | "lg" | "none";
}

const glowStyles = {
  blue: "hover:shadow-neon-blue hover:border-electric-blue/30",
  purple: "hover:shadow-neon-purple hover:border-neon-purple/30",
  green: "hover:shadow-neon-green hover:border-neon-green/30",
  pink: "hover:shadow-neon-pink hover:border-neon-pink/30",
  none: "",
};

const paddingStyles = {
  sm: "p-4",
  md: "p-6",
  lg: "p-8",
  none: "",
};

export function GlassCard({
  children,
  className,
  hover = true,
  glow = "none",
  gradient = false,
  onClick,
  animate = true,
  delay = 0,
  padding = "md",
}: GlassCardProps) {
  const content = (
    <div
      className={cn(
        "glass-panel rounded-2xl border border-white/10 transition-all duration-300",
        hover && "hover:bg-white/8 hover:border-white/15 cursor-pointer",
        hover && glow !== "none" && glowStyles[glow],
        gradient && "bg-glass-gradient",
        paddingStyles[padding],
        onClick && "cursor-pointer",
        className
      )}
      onClick={onClick}
    >
      {children}
    </div>
  );

  if (!animate) return content;

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay, ease: "easeOut" }}
    >
      {hover ? (
        <motion.div whileHover={{ y: -2 }} transition={{ duration: 0.2 }}>
          {content}
        </motion.div>
      ) : content}
    </motion.div>
  );
}

// Gradient border variant
export function GradientBorderCard({
  children,
  className,
  padding = "md",
}: {
  children: ReactNode;
  className?: string;
  padding?: "sm" | "md" | "lg";
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="gradient-border"
    >
      <div className={cn("gradient-border-inner bg-[#0A0B1A]", paddingStyles[padding], className)}>
        {children}
      </div>
    </motion.div>
  );
}

export default GlassCard;
