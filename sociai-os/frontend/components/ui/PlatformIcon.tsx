import { cn } from "@/lib/utils";

const PLATFORM_CONFIG: Record<string, { emoji: string; bg: string; label: string }> = {
  linkedin:  { emoji: "💼", bg: "from-blue-600 to-blue-800", label: "LinkedIn" },
  instagram: { emoji: "📸", bg: "from-pink-600 to-purple-700", label: "Instagram" },
  facebook:  { emoji: "📘", bg: "from-blue-500 to-blue-700", label: "Facebook" },
  tiktok:    { emoji: "🎵", bg: "from-gray-900 to-black", label: "TikTok" },
  twitter:   { emoji: "🐦", bg: "from-sky-500 to-sky-700", label: "Twitter/X" },
  youtube:   { emoji: "▶️", bg: "from-red-600 to-red-800", label: "YouTube" },
  snapchat:  { emoji: "👻", bg: "from-yellow-400 to-yellow-500", label: "Snapchat" },
  threads:   { emoji: "🧵", bg: "from-gray-700 to-black", label: "Threads" },
  pinterest: { emoji: "📌", bg: "from-red-600 to-red-700", label: "Pinterest" },
  whatsapp:  { emoji: "💬", bg: "from-green-500 to-green-700", label: "WhatsApp" },
  telegram:  { emoji: "✈️", bg: "from-sky-400 to-sky-600", label: "Telegram" },
};

interface PlatformIconProps {
  platform: string;
  size?: "sm" | "md" | "lg";
  showLabel?: boolean;
  className?: string;
}

export function PlatformIcon({ platform, size = "md", showLabel = false, className }: PlatformIconProps) {
  const config = PLATFORM_CONFIG[platform.toLowerCase()] || { emoji: "📱", bg: "from-gray-600 to-gray-800", label: platform };
  const sizeClasses = { sm: "w-7 h-7 text-sm", md: "w-10 h-10 text-lg", lg: "w-14 h-14 text-2xl" };
  return (
    <div className={cn("flex items-center gap-2", className)}>
      <div className={cn(`rounded-xl bg-gradient-to-br ${config.bg} flex items-center justify-center shadow-lg flex-shrink-0`, sizeClasses[size])}>
        {config.emoji}
      </div>
      {showLabel && <span className="text-sm font-medium text-white">{config.label}</span>}
    </div>
  );
}
