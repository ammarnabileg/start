// ============================================================
// SOCIAI OS — Complete TypeScript Type Definitions
// ============================================================

// ---- Platform Types ----
export type PlatformId =
  | "instagram"
  | "facebook"
  | "twitter"
  | "tiktok"
  | "youtube"
  | "linkedin"
  | "snapchat"
  | "pinterest"
  | "telegram"
  | "whatsapp"
  | "threads";

export interface Platform {
  id: PlatformId;
  name: string;
  color: string;
  gradientFrom: string;
  gradientTo: string;
  icon: string;
  connected: boolean;
  username?: string;
  followers?: number;
  profileUrl?: string;
  accessToken?: string;
  expiresAt?: string;
  scopes?: string[];
}

// ---- User / Auth ----
export interface User {
  id: string;
  email: string;
  name: string;
  avatar?: string;
  role: "owner" | "admin" | "editor" | "viewer";
  language: "ar" | "en";
  timezone: string;
  plan: "free" | "starter" | "pro" | "enterprise";
  onboardingComplete: boolean;
  createdAt: string;
  lastLogin?: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

// ---- Dashboard / Analytics ----
export interface KPIMetric {
  id: string;
  label: string;
  value: number | string;
  previousValue?: number | string;
  change: number;
  changeType: "increase" | "decrease" | "neutral";
  icon: string;
  color: string;
  suffix?: string;
  prefix?: string;
}

export interface AnalyticsData {
  date: string;
  impressions: number;
  reach: number;
  engagement: number;
  followers: number;
  clicks: number;
  conversions: number;
}

export interface PlatformMetrics {
  platformId: PlatformId;
  followers: number;
  followersGrowth: number;
  impressions: number;
  engagement: number;
  engagementRate: number;
  posts: number;
  topPost?: Post;
}

export interface ViralScore {
  score: number;
  trend: "up" | "down" | "stable";
  components: {
    shareability: number;
    engagement: number;
    timing: number;
    reach: number;
  };
}

export interface SentimentData {
  positive: number;
  neutral: number;
  negative: number;
  total: number;
}

// ---- Content ----
export type ContentStatus = "draft" | "review" | "approved" | "scheduled" | "published" | "failed";
export type ContentType = "post" | "story" | "reel" | "video" | "carousel" | "thread" | "article";

export interface Post {
  id: string;
  title?: string;
  caption: string;
  mediaUrls: string[];
  mediaType: "image" | "video" | "carousel" | "text";
  platforms: PlatformId[];
  status: ContentStatus;
  scheduledAt?: string;
  publishedAt?: string;
  contentType: ContentType;
  hashtags: string[];
  mentions: string[];
  language: "ar" | "en" | "mixed";
  aiGenerated: boolean;
  campaignId?: string;
  authorId: string;
  metrics?: {
    impressions: number;
    reach: number;
    engagement: number;
    likes: number;
    comments: number;
    shares: number;
    saves: number;
    clicks: number;
    viralScore: number;
  };
  createdAt: string;
  updatedAt: string;
}

// ---- Content Generation ----
export type ContentStyle =
  | "informative"
  | "entertaining"
  | "inspirational"
  | "promotional"
  | "educational"
  | "storytelling"
  | "humor"
  | "controversial"
  | "behind-scenes"
  | "user-generated";

export interface ContentGenerationRequest {
  topic: string;
  platforms: PlatformId[];
  style: ContentStyle;
  language: "ar" | "en" | "mixed";
  tone: {
    formality: number; // 0-100
    enthusiasm: number; // 0-100
    humor: number; // 0-100
  };
  includeHashtags: boolean;
  includeEmojis: boolean;
  callToAction?: string;
  brandVoice?: string;
  contextPillars?: string[];
}

export interface GeneratedContent {
  id: string;
  platform: PlatformId;
  caption: string;
  hashtags: string[];
  suggestions: string[];
  confidence: number;
  style: ContentStyle;
  estimatedReach: number;
  estimatedEngagement: number;
  generatedAt: string;
}

// ---- Copywriting ----
export type CopyType =
  | "caption"
  | "thread"
  | "script"
  | "hook"
  | "cta"
  | "ad-copy"
  | "carousel-text"
  | "story-text"
  | "comment-reply"
  | "dm-template";

export interface CopyRequest {
  type: CopyType;
  topic: string;
  platform?: PlatformId;
  style?: ContentStyle;
  language: "ar" | "en" | "mixed";
  brandVoice?: string;
  targetAudience?: string;
  goal?: string;
  length?: "short" | "medium" | "long";
  variations?: number;
}

export interface GeneratedCopy {
  id: string;
  type: CopyType;
  content: string;
  variations: string[];
  platform?: PlatformId;
  tone: string;
  wordCount: number;
  characterCount: number;
  readabilityScore: number;
  generatedAt: string;
}

// ---- Strategy ----
export type DocumentType =
  | "brand-guide"
  | "marketing-plan"
  | "content-strategy"
  | "audience-research"
  | "competitor-analysis"
  | "campaign-brief"
  | "tone-of-voice"
  | "content-pillars"
  | "seo-keywords"
  | "crisis-plan"
  | "editorial-calendar";

export interface StrategyDocument {
  id: string;
  name: string;
  type: DocumentType;
  fileUrl: string;
  fileSize: number;
  mimeType: string;
  status: "uploading" | "processing" | "analyzed" | "failed";
  analysisProgress?: number;
  uploadedAt: string;
  analyzedAt?: string;
}

export interface StrategyInsights {
  brandTone: string[];
  contentPillars: ContentPillar[];
  audienceSegments: AudienceSegment[];
  keyMessages: string[];
  competitorInsights: string[];
  recommendations: string[];
  keywords: string[];
  postingFrequency: Record<PlatformId, number>;
  bestTimes: Record<PlatformId, string[]>;
}

export interface ContentPillar {
  id: string;
  name: string;
  description: string;
  percentage: number;
  color: string;
  topics: string[];
}

export interface AudienceSegment {
  id: string;
  name: string;
  description: string;
  percentage: number;
  demographics: {
    ageRange: string;
    gender: string;
    location: string[];
    interests: string[];
  };
  platforms: PlatformId[];
}

// ---- Campaigns ----
export type CampaignStatus = "planning" | "active" | "paused" | "completed" | "cancelled";

export interface Campaign {
  id: string;
  name: string;
  description?: string;
  status: CampaignStatus;
  startDate: string;
  endDate: string;
  budget?: number;
  platforms: PlatformId[];
  goals: CampaignGoal[];
  posts: Post[];
  metrics?: CampaignMetrics;
  aiGenerated: boolean;
  color: string;
  createdAt: string;
  updatedAt: string;
}

export interface CampaignGoal {
  type: "reach" | "engagement" | "followers" | "conversions" | "awareness" | "traffic";
  target: number;
  current: number;
}

export interface CampaignMetrics {
  totalReach: number;
  totalImpressions: number;
  totalEngagement: number;
  totalClicks: number;
  conversionRate: number;
  roi: number;
  topPost?: Post;
}

// ---- Community ----
export type CommentSentiment = "positive" | "neutral" | "negative" | "spam";
export type CommentStatus = "pending" | "replied" | "archived" | "escalated";

export interface Comment {
  id: string;
  platformId: PlatformId;
  postId?: string;
  authorName: string;
  authorAvatar?: string;
  authorHandle: string;
  content: string;
  sentiment: CommentSentiment;
  status: CommentStatus;
  isLead: boolean;
  aiReplySuggestions: string[];
  threadId?: string;
  parentId?: string;
  likes: number;
  createdAt: string;
  repliedAt?: string;
}

export interface DMMessage {
  id: string;
  platformId: PlatformId;
  contactName: string;
  contactAvatar?: string;
  contactHandle: string;
  lastMessage: string;
  unreadCount: number;
  sentiment: CommentSentiment;
  isLead: boolean;
  leadScore?: number;
  status: "open" | "in-progress" | "resolved" | "escalated";
  messages: DMMessageItem[];
  createdAt: string;
  updatedAt: string;
}

export interface DMMessageItem {
  id: string;
  content: string;
  fromUser: boolean;
  aiGenerated?: boolean;
  sentAt: string;
}

// ---- Trends ----
export interface Trend {
  id: string;
  title: string;
  description?: string;
  hashtags: string[];
  platforms: PlatformId[];
  viralScore: number;
  momentum: "rising" | "peak" | "declining";
  category: string;
  usageCount: number;
  growthRate: number;
  relatedTopics: string[];
  createdAt: string;
  peakAt?: string;
}

// ---- AI Agents ----
export type AgentType =
  | "content-generator"
  | "copywriter"
  | "trend-hunter"
  | "analytics-analyst"
  | "community-manager"
  | "campaign-planner"
  | "strategy-advisor"
  | "scheduler";

export type AgentStatus = "idle" | "running" | "complete" | "error" | "paused";

export interface Agent {
  id: string;
  type: AgentType;
  name: string;
  description: string;
  status: AgentStatus;
  currentTask?: string;
  tasksCompleted: number;
  tasksTotal?: number;
  progress?: number;
  lastRun?: string;
  nextRun?: string;
  icon: string;
  color: string;
  capabilities: string[];
}

export interface AgentTask {
  id: string;
  agentId: string;
  agentType: AgentType;
  title: string;
  description?: string;
  status: "queued" | "running" | "complete" | "failed";
  progress?: number;
  result?: string;
  startedAt?: string;
  completedAt?: string;
  createdAt: string;
}

// ---- Team ----
export type TeamRole = "owner" | "admin" | "editor" | "analyst" | "viewer";

export interface TeamMember {
  id: string;
  name: string;
  email: string;
  avatar?: string;
  role: TeamRole;
  platforms: PlatformId[];
  status: "active" | "invited" | "suspended";
  lastActive?: string;
  permissions: Permission[];
  invitedAt?: string;
  joinedAt?: string;
}

export interface Permission {
  resource: string;
  actions: ("create" | "read" | "update" | "delete" | "publish" | "approve")[];
}

export interface ApprovalRequest {
  id: string;
  type: "post" | "campaign" | "budget";
  title: string;
  requestedBy: TeamMember;
  requestedAt: string;
  status: "pending" | "approved" | "rejected";
  reviewedBy?: TeamMember;
  reviewedAt?: string;
  notes?: string;
  item: Post | Campaign;
}

// ---- Notifications ----
export type NotificationType =
  | "content-published"
  | "content-failed"
  | "agent-complete"
  | "trend-alert"
  | "comment-spike"
  | "follower-milestone"
  | "mention"
  | "approval-required"
  | "team-activity"
  | "billing";

export interface Notification {
  id: string;
  type: NotificationType;
  title: string;
  message: string;
  read: boolean;
  priority: "low" | "medium" | "high" | "urgent";
  icon?: string;
  link?: string;
  metadata?: Record<string, unknown>;
  createdAt: string;
}

// ---- Settings ----
export interface BrandAssets {
  logo?: string;
  colors: string[];
  fonts: string[];
  voiceKeywords: string[];
  prohibitedWords: string[];
  tagline?: string;
}

export interface NotificationSettings {
  email: boolean;
  push: boolean;
  sms: boolean;
  types: Record<NotificationType, boolean>;
  digest: "realtime" | "hourly" | "daily" | "weekly";
}

export interface ApiKey {
  id: string;
  name: string;
  key: string;
  maskedKey: string;
  scopes: string[];
  lastUsed?: string;
  expiresAt?: string;
  createdAt: string;
}

// ---- UI State ----
export interface UIState {
  sidebarCollapsed: boolean;
  language: "ar" | "en";
  direction: "rtl" | "ltr";
  theme: "dark" | "light";
  activeNotifications: Notification[];
}

// ---- API Responses ----
export interface ApiResponse<T> {
  data: T;
  message?: string;
  success: boolean;
  pagination?: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
}

export interface ApiError {
  message: string;
  code: string;
  details?: Record<string, string[]>;
  status: number;
}

// ---- Form Types ----
export interface LoginFormData {
  email: string;
  password: string;
  rememberMe?: boolean;
}

export interface RegisterFormData {
  name: string;
  email: string;
  password: string;
  confirmPassword: string;
  businessName?: string;
  industry?: string;
  agreeToTerms: boolean;
}

// ---- Chart Types ----
export interface ChartDataPoint {
  date: string;
  [key: string]: string | number;
}

export interface CompetitorData {
  name: string;
  handle: string;
  platform: PlatformId;
  followers: number;
  engagementRate: number;
  postsPerWeek: number;
  avgLikes: number;
  avgComments: number;
  growthRate: number;
}
