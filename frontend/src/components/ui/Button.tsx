"use client";

import { cn } from "@/lib/utils";
import { Loader2 } from "lucide-react";
import React from "react";

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "ghost" | "danger" | "outline";
  size?: "sm" | "md" | "lg";
  loading?: boolean;
  icon?: React.ReactNode;
}

export function Button({ variant = "primary", size = "md", loading, icon, children, className, disabled, ...props }: ButtonProps) {
  const base = "inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed";
  const variants = {
    primary: "bg-violet-600 text-white hover:bg-violet-700 active:bg-violet-800 shadow-sm",
    secondary: "bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 active:bg-gray-100",
    ghost: "text-gray-600 hover:bg-gray-100 hover:text-gray-900",
    danger: "bg-red-600 text-white hover:bg-red-700",
    outline: "border-2 border-violet-600 text-violet-600 hover:bg-violet-50",
  };
  const sizes = { sm: "px-3 py-1.5 text-xs", md: "px-4 py-2.5 text-sm", lg: "px-6 py-3 text-base" };

  return (
    <button className={cn(base, variants[variant], sizes[size], className)} disabled={disabled || loading} {...props}>
      {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : icon}
      {children}
    </button>
  );
}
