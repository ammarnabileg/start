import { PlatformId, ContentStyle, AgentType } from "./types";

// ---- Platform Configurations ----
export const PLATFORMS: Record<PlatformId, {
  id: PlatformId;
  name: string;
  color: string;
  gradientFrom: string;
  gradientTo: string;
  bgColor: string;
  textColor: string;
  icon: string;
  maxCaptionLength: number;
  supportsVideo: boolean;
  supportsCarousel: boolean;
  supportsStories: boolean;
  oauthScopes: string[];
}> = {
  instagram: {
    id: "instagram",
    name: "Instagram",
    color: "#E1306C",
    gradientFrom: "#833AB4",
    gradientTo: "#FD1D1D",
    bgColor: "rgba(225,48,108,0.1)",
    textColor: "#E1306C",
    icon: "instagram",
    maxCaptionLength: 2200,
    supportsVideo: true,
    supportsCarousel: true,
    supportsStories: true,
    oauthScopes: ["instagram_basic", "instagram_content_publish"],
  },
  facebook: {
    id: "facebook",
    name: "Facebook",
    color: "#1877F2",
    gradientFrom: "#1877F2",
    gradientTo: "#0E5FC8",
    bgColor: "rgba(24,119,242,0.1)",
    textColor: "#1877F2",
    icon: "facebook",
    maxCaptionLength: 63206,
    supportsVideo: true,
    supportsCarousel: true,
    supportsStories: true,
    oauthScopes: ["pages_manage_posts", "pages_read_engagement"],
  },
  twitter: {
    id: "twitter",
    name: "X (Twitter)",
    color: "#000000",
    gradientFrom: "#1DA1F2",
    gradientTo: "#000000",
    bgColor: "rgba(29,161,242,0.1)",
    textColor: "#1DA1F2",
    icon: "twitter",
    maxCaptionLength: 280,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: false,
    oauthScopes: ["tweet.read", "tweet.write", "users.read"],
  },
  tiktok: {
    id: "tiktok",
    name: "TikTok",
    color: "#FF0050",
    gradientFrom: "#FF0050",
    gradientTo: "#00F2EA",
    bgColor: "rgba(255,0,80,0.1)",
    textColor: "#FF0050",
    icon: "tiktok",
    maxCaptionLength: 2200,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: false,
    oauthScopes: ["video.publish", "user.info.basic"],
  },
  youtube: {
    id: "youtube",
    name: "YouTube",
    color: "#FF0000",
    gradientFrom: "#FF0000",
    gradientTo: "#CC0000",
    bgColor: "rgba(255,0,0,0.1)",
    textColor: "#FF0000",
    icon: "youtube",
    maxCaptionLength: 5000,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: false,
    oauthScopes: ["https://www.googleapis.com/auth/youtube.upload"],
  },
  linkedin: {
    id: "linkedin",
    name: "LinkedIn",
    color: "#0A66C2",
    gradientFrom: "#0A66C2",
    gradientTo: "#004182",
    bgColor: "rgba(10,102,194,0.1)",
    textColor: "#0A66C2",
    icon: "linkedin",
    maxCaptionLength: 3000,
    supportsVideo: true,
    supportsCarousel: true,
    supportsStories: false,
    oauthScopes: ["r_liteprofile", "w_member_social"],
  },
  snapchat: {
    id: "snapchat",
    name: "Snapchat",
    color: "#FFFC00",
    gradientFrom: "#FFFC00",
    gradientTo: "#FFC300",
    bgColor: "rgba(255,252,0,0.1)",
    textColor: "#B8A900",
    icon: "snapchat",
    maxCaptionLength: 250,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: true,
    oauthScopes: ["snapchat-marketing-api"],
  },
  pinterest: {
    id: "pinterest",
    name: "Pinterest",
    color: "#E60023",
    gradientFrom: "#E60023",
    gradientTo: "#B60019",
    bgColor: "rgba(230,0,35,0.1)",
    textColor: "#E60023",
    icon: "pinterest",
    maxCaptionLength: 500,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: false,
    oauthScopes: ["boards:read", "pins:write"],
  },
  telegram: {
    id: "telegram",
    name: "Telegram",
    color: "#2AABEE",
    gradientFrom: "#2AABEE",
    gradientTo: "#1A84C4",
    bgColor: "rgba(42,171,238,0.1)",
    textColor: "#2AABEE",
    icon: "send",
    maxCaptionLength: 4096,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: false,
    oauthScopes: ["bot_api"],
  },
  whatsapp: {
    id: "whatsapp",
    name: "WhatsApp",
    color: "#25D366",
    gradientFrom: "#25D366",
    gradientTo: "#128C7E",
    bgColor: "rgba(37,211,102,0.1)",
    textColor: "#25D366",
    icon: "message-circle",
    maxCaptionLength: 1024,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: true,
    oauthScopes: ["whatsapp_business_api"],
  },
  threads: {
    id: "threads",
    name: "Threads",
    color: "#000000",
    gradientFrom: "#000000",
    gradientTo: "#333333",
    bgColor: "rgba(0,0,0,0.1)",
    textColor: "#FFFFFF",
    icon: "at-sign",
    maxCaptionLength: 500,
    supportsVideo: true,
    supportsCarousel: false,
    supportsStories: false,
    oauthScopes: ["threads_basic", "threads_content_publish"],
  },
};

export const PLATFORM_LIST = Object.values(PLATFORMS);

// ---- Content Styles ----
export const CONTENT_STYLES: Record<ContentStyle, {
  label: string;
  description: string;
  icon: string;
  color: string;
}> = {
  informative: { label: "Informative", description: "Facts and data-driven content", icon: "📊", color: "#3B82F6" },
  entertaining: { label: "Entertaining", description: "Fun, engaging content", icon: "🎉", color: "#F59E0B" },
  inspirational: { label: "Inspirational", description: "Motivating and uplifting", icon: "✨", color: "#8B5CF6" },
  promotional: { label: "Promotional", description: "Product/service promotion", icon: "🛍️", color: "#EC4899" },
  educational: { label: "Educational", description: "Teaching and tutorials", icon: "📚", color: "#10B981" },
  storytelling: { label: "Storytelling", description: "Narrative-driven content", icon: "📖", color: "#F97316" },
  humor: { label: "Humor", description: "Funny and witty content", icon: "😄", color: "#EAB308" },
  controversial: { label: "Controversial", description: "Thought-provoking opinions", icon: "🔥", color: "#EF4444" },
  "behind-scenes": { label: "Behind the Scenes", description: "Authentic BTS content", icon: "🎬", color: "#6366F1" },
  "user-generated": { label: "User Generated", description: "Community-first content", icon: "👥", color: "#14B8A6" },
};

// ---- Agent Configurations ----
export const AGENT_CONFIGS: Record<AgentType, {
  name: string;
  description: string;
  icon: string;
  color: string;
  gradientFrom: string;
  gradientTo: string;
  capabilities: string[];
}> = {
  "content-generator": {
    name: "Content Generator",
    description: "Creates platform-optimized content using your brand voice and strategy",
    icon: "Sparkles",
    color: "#3B82F6",
    gradientFrom: "#3B82F6",
    gradientTo: "#8B5CF6",
    capabilities: ["Multi-platform content", "Brand voice matching", "Hashtag research", "Optimal length"],
  },
  copywriter: {
    name: "AI Copywriter",
    description: "Crafts compelling copy for all content types with conversion focus",
    icon: "PenTool",
    color: "#8B5CF6",
    gradientFrom: "#8B5CF6",
    gradientTo: "#EC4899",
    capabilities: ["Ad copy", "Email sequences", "CTAs", "Headlines"],
  },
  "trend-hunter": {
    name: "Trend Hunter",
    description: "Monitors and analyzes viral trends across all platforms in real-time",
    icon: "TrendingUp",
    color: "#10B981",
    gradientFrom: "#10B981",
    gradientTo: "#3B82F6",
    capabilities: ["Real-time monitoring", "Viral prediction", "Hashtag analysis", "Competitor tracking"],
  },
  "analytics-analyst": {
    name: "Analytics Analyst",
    description: "Deep-dives into performance data and generates actionable insights",
    icon: "BarChart2",
    color: "#F59E0B",
    gradientFrom: "#F59E0B",
    gradientTo: "#EF4444",
    capabilities: ["Performance analysis", "Trend detection", "ROI calculation", "Growth forecasting"],
  },
  "community-manager": {
    name: "Community Manager",
    description: "Manages comments, DMs, and community interactions intelligently",
    icon: "Users",
    color: "#EC4899",
    gradientFrom: "#EC4899",
    gradientTo: "#8B5CF6",
    capabilities: ["Comment replies", "DM handling", "Sentiment analysis", "Lead qualification"],
  },
  "campaign-planner": {
    name: "Campaign Planner",
    description: "Designs comprehensive multi-platform campaign strategies",
    icon: "Calendar",
    color: "#6366F1",
    gradientFrom: "#6366F1",
    gradientTo: "#3B82F6",
    capabilities: ["Campaign design", "Timeline planning", "Budget allocation", "Goal setting"],
  },
  "strategy-advisor": {
    name: "Strategy Advisor",
    description: "Analyzes your brand strategy and provides high-level recommendations",
    icon: "Brain",
    color: "#14B8A6",
    gradientFrom: "#14B8A6",
    gradientTo: "#10B981",
    capabilities: ["Strategy analysis", "Competitor insights", "Market positioning", "Growth roadmap"],
  },
  scheduler: {
    name: "Smart Scheduler",
    description: "Optimizes posting schedule based on audience activity patterns",
    icon: "Clock",
    color: "#F97316",
    gradientFrom: "#F97316",
    gradientTo: "#EAB308",
    capabilities: ["Optimal timing", "Auto-scheduling", "Queue management", "Time zone handling"],
  },
};

// ---- Navigation Routes ----
export const ROUTES = {
  // Auth
  LOGIN: "/login",
  REGISTER: "/register",
  CONNECT_PLATFORMS: "/connect-platforms",
  
  // Dashboard
  DASHBOARD: "/dashboard",
  STRATEGY: "/strategy",
  CONTENT: "/content",
  CONTENT_GENERATE: "/content/generate",
  COPYWRITING: "/copywriting",
  ANALYTICS: "/analytics",
  CAMPAIGNS: "/campaigns",
  COMMUNITY: "/community",
  TRENDS: "/trends",
  AGENTS: "/agents",
  TEAM: "/team",
  SETTINGS: "/settings",
} as const;

// ---- Sidebar Navigation ----
export const NAV_ITEMS = [
  { href: ROUTES.DASHBOARD, label: "Dashboard", labelAr: "لوحة التحكم", icon: "LayoutDashboard" },
  { href: ROUTES.STRATEGY, label: "Strategy", labelAr: "الاستراتيجية", icon: "Target" },
  { href: ROUTES.CONTENT, label: "Content", labelAr: "المحتوى", icon: "FileText" },
  { href: ROUTES.COPYWRITING, label: "Copywriting", labelAr: "كتابة النصوص", icon: "PenTool" },
  { href: ROUTES.ANALYTICS, label: "Analytics", labelAr: "التحليلات", icon: "BarChart2" },
  { href: ROUTES.CAMPAIGNS, label: "Campaigns", labelAr: "الحملات", icon: "Megaphone" },
  { href: ROUTES.COMMUNITY, label: "Community", labelAr: "المجتمع", icon: "MessageSquare" },
  { href: ROUTES.TRENDS, label: "Trends", labelAr: "الاتجاهات", icon: "TrendingUp" },
  { href: ROUTES.AGENTS, label: "AI Agents", labelAr: "وكلاء الذكاء", icon: "Bot" },
  { href: ROUTES.TEAM, label: "Team", labelAr: "الفريق", icon: "Users" },
  { href: ROUTES.SETTINGS, label: "Settings", labelAr: "الإعدادات", icon: "Settings" },
] as const;

// ---- Color Maps ----
export const STATUS_COLORS = {
  draft: { bg: "rgba(107,114,128,0.2)", text: "#9CA3AF", border: "rgba(107,114,128,0.3)" },
  review: { bg: "rgba(245,158,11,0.2)", text: "#F59E0B", border: "rgba(245,158,11,0.3)" },
  approved: { bg: "rgba(59,130,246,0.2)", text: "#3B82F6", border: "rgba(59,130,246,0.3)" },
  scheduled: { bg: "rgba(139,92,246,0.2)", text: "#8B5CF6", border: "rgba(139,92,246,0.3)" },
  published: { bg: "rgba(16,185,129,0.2)", text: "#10B981", border: "rgba(16,185,129,0.3)" },
  failed: { bg: "rgba(239,68,68,0.2)", text: "#EF4444", border: "rgba(239,68,68,0.3)" },
} as const;

export const SENTIMENT_COLORS = {
  positive: "#10B981",
  neutral: "#6B7280",
  negative: "#EF4444",
  spam: "#F59E0B",
} as const;

export const AGENT_STATUS_COLORS = {
  idle: "#6B7280",
  running: "#3B82F6",
  complete: "#10B981",
  error: "#EF4444",
  paused: "#F59E0B",
} as const;

export const CAMPAIGN_STATUS_COLORS = {
  planning: { bg: "rgba(139,92,246,0.2)", text: "#8B5CF6" },
  active: { bg: "rgba(16,185,129,0.2)", text: "#10B981" },
  paused: { bg: "rgba(245,158,11,0.2)", text: "#F59E0B" },
  completed: { bg: "rgba(59,130,246,0.2)", text: "#3B82F6" },
  cancelled: { bg: "rgba(239,68,68,0.2)", text: "#EF4444" },
} as const;

// ---- Copy Types ----
export const COPY_TYPES = [
  { id: "caption", label: "Caption", labelAr: "تعليق", icon: "AlignLeft" },
  { id: "thread", label: "Thread", labelAr: "خيط", icon: "List" },
  { id: "script", label: "Video Script", labelAr: "نص فيديو", icon: "Film" },
  { id: "hook", label: "Hook", labelAr: "خطاف", icon: "Zap" },
  { id: "cta", label: "Call to Action", labelAr: "دعوة للعمل", icon: "MousePointer" },
  { id: "ad-copy", label: "Ad Copy", labelAr: "نص إعلاني", icon: "Megaphone" },
  { id: "carousel-text", label: "Carousel Text", labelAr: "نص كاروسيل", icon: "Images" },
  { id: "story-text", label: "Story Text", labelAr: "نص القصة", icon: "BookOpen" },
  { id: "comment-reply", label: "Comment Reply", labelAr: "رد التعليق", icon: "Reply" },
  { id: "dm-template", label: "DM Template", labelAr: "قالب رسالة", icon: "Mail" },
] as const;

// ---- Document Types ----
export const DOCUMENT_TYPES = [
  { id: "brand-guide", label: "Brand Guide", icon: "Palette", color: "#8B5CF6" },
  { id: "marketing-plan", label: "Marketing Plan", icon: "Map", color: "#3B82F6" },
  { id: "content-strategy", label: "Content Strategy", icon: "Target", color: "#10B981" },
  { id: "audience-research", label: "Audience Research", icon: "Users", color: "#F59E0B" },
  { id: "competitor-analysis", label: "Competitor Analysis", icon: "Crosshair", color: "#EF4444" },
  { id: "campaign-brief", label: "Campaign Brief", icon: "Megaphone", color: "#EC4899" },
  { id: "tone-of-voice", label: "Tone of Voice", icon: "Mic", color: "#6366F1" },
  { id: "content-pillars", label: "Content Pillars", icon: "Columns", color: "#14B8A6" },
  { id: "seo-keywords", label: "SEO Keywords", icon: "Search", color: "#F97316" },
  { id: "crisis-plan", label: "Crisis Plan", icon: "Shield", color: "#EAB308" },
  { id: "editorial-calendar", label: "Editorial Calendar", icon: "Calendar", color: "#A855F7" },
] as const;

// ---- Date Range Presets ----
export const DATE_PRESETS = [
  { label: "Last 7 days", value: 7 },
  { label: "Last 14 days", value: 14 },
  { label: "Last 30 days", value: 30 },
  { label: "Last 90 days", value: 90 },
  { label: "Last 6 months", value: 180 },
  { label: "Last year", value: 365 },
] as const;
