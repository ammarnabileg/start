import { cn } from "@/lib/utils";

const PLATFORM_CONFIG: Record<string, { emoji: string; bg: string; label: string; gradientFrom: string; gradientTo: string }> = {
  linkedin:  { emoji: "💼", bg: "from-blue-600 to-blue-800", label: "LinkedIn", gradientFrom: "#0A66C2", gradientTo: "#004182" },
  instagram: { emoji: "📸", bg: "from-pink-600 to-purple-700", label: "Instagram", gradientFrom: "#833AB4", gradientTo: "#FD1D1D" },
  facebook:  { emoji: "📘", bg: "from-blue-500 to-blue-700", label: "Facebook", gradientFrom: "#1877F2", gradientTo: "#0E5FC8" },
  tiktok:    { emoji: "🎵", bg: "from-gray-900 to-black", label: "TikTok", gradientFrom: "#FF0050", gradientTo: "#00F2EA" },
  twitter:   { emoji: "🐦", bg: "from-sky-500 to-sky-700", label: "X (Twitter)", gradientFrom: "#1DA1F2", gradientTo: "#000000" },
  youtube:   { emoji: "▶️", bg: "from-red-600 to-red-800", label: "YouTube", gradientFrom: "#FF0000", gradientTo: "#CC0000" },
  snapchat:  { emoji: "👻", bg: "from-yellow-400 to-yellow-500", label: "Snapchat", gradientFrom: "#FFFC00", gradientTo: "#FFC300" },
  threads:   { emoji: "🧵", bg: "from-gray-700 to-black", label: "Threads", gradientFrom: "#333333", gradientTo: "#000000" },
  pinterest: { emoji: "📌", bg: "from-red-600 to-red-700", label: "Pinterest", gradientFrom: "#E60023", gradientTo: "#B60019" },
  whatsapp:  { emoji: "💬", bg: "from-green-500 to-green-700", label: "WhatsApp", gradientFrom: "#25D366", gradientTo: "#128C7E" },
  telegram:  { emoji: "✈️", bg: "from-sky-400 to-sky-600", label: "Telegram", gradientFrom: "#2AABEE", gradientTo: "#1A84C4" },
};

interface PlatformIconProps {
  platform: string;
  size?: "xs" | "sm" | "md" | "lg" | "xl";
  showLabel?: boolean;
  showName?: boolean;
  variant?: "circle" | "square" | "rounded";
  className?: string;
}

const sizeClasses = {
  xs: "w-5 h-5 text-xs",
  sm: "w-7 h-7 text-sm",
  md: "w-10 h-10 text-lg",
  lg: "w-12 h-12 text-xl",
  xl: "w-16 h-16 text-2xl",
};

const variantClasses = {
  circle: "rounded-full",
  square: "rounded-none",
  rounded: "rounded-xl",
};

const labelSizeClasses = {
  xs: "text-xs",
  sm: "text-xs",
  md: "text-sm",
  lg: "text-sm",
  xl: "text-base",
};

export function PlatformIcon({ platform, size = "md", showLabel = false, showName = false, variant = "rounded", className }: PlatformIconProps) {
  const config = PLATFORM_CONFIG[platform.toLowerCase()] || { emoji: "📱", bg: "from-gray-600 to-gray-800", label: platform, gradientFrom: "#666", gradientTo: "#888" };
  const showNameFinal = showLabel || showName;
  return (
    <div className={cn("flex items-center gap-2", className)}>
      <div
        className={cn(
          `bg-gradient-to-br ${config.bg} flex items-center justify-center shadow-lg flex-shrink-0`,
          sizeClasses[size],
          variantClasses[variant]
        )}
        style={{ background: `linear-gradient(135deg, ${config.gradientFrom}, ${config.gradientTo})` }}
      >
        <span style={{ fontSize: size === "xs" ? "10px" : size === "sm" ? "12px" : size === "md" ? "16px" : size === "lg" ? "18px" : "22px" }}>
          {config.emoji}
        </span>
      </div>
      {showNameFinal && <span className={cn("font-medium text-white/80", labelSizeClasses[size])}>{config.label}</span>}
    </div>
  );
}

export default PlatformIcon;
