import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse, InternalAxiosRequestConfig } from "axios";
import {
  ApiResponse,
  ApiError,
  User,
  Post,
  Campaign,
  Comment,
  DMMessage,
  Trend,
  Agent,
  AgentTask,
  TeamMember,
  ApprovalRequest,
  Notification,
  AnalyticsData,
  PlatformMetrics,
  StrategyDocument,
  StrategyInsights,
  GeneratedContent,
  GeneratedCopy,
  ApiKey,
  ContentGenerationRequest,
  CopyRequest,
} from "./types";

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

// ---- Axios Instance ----
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,
  headers: {
    "Content-Type": "application/json",
  },
});

// ---- Auth Token Management ----
let authToken: string | null = null;

export const setAuthToken = (token: string | null) => {
  authToken = token;
  if (token) {
    localStorage.setItem("sociai_token", token);
  } else {
    localStorage.removeItem("sociai_token");
  }
};

export const getAuthToken = (): string | null => {
  if (authToken) return authToken;
  if (typeof window !== "undefined") {
    return localStorage.getItem("sociai_token");
  }
  return null;
};

// ---- Request Interceptor ----
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getAuthToken();
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// ---- Response Interceptor ----
apiClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error) => {
    const apiError: ApiError = {
      message: error.response?.data?.message || "An unexpected error occurred",
      code: error.response?.data?.code || "UNKNOWN_ERROR",
      details: error.response?.data?.details,
      status: error.response?.status || 500,
    };

    if (error.response?.status === 401) {
      setAuthToken(null);
      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }
    }

    return Promise.reject(apiError);
  }
);

// ---- Generic Request Helper ----
async function request<T>(config: AxiosRequestConfig): Promise<ApiResponse<T>> {
  const response = await apiClient.request<ApiResponse<T>>(config);
  return response.data;
}

// ============================================================
// AUTH API
// ============================================================
export const authApi = {
  login: (email: string, password: string) =>
    request<{ user: User; token: string }>({
      method: "POST",
      url: "/auth/login",
      data: { email, password },
    }),

  register: (data: { name: string; email: string; password: string; businessName?: string }) =>
    request<{ user: User; token: string }>({
      method: "POST",
      url: "/auth/register",
      data,
    }),

  logout: () =>
    request<void>({ method: "POST", url: "/auth/logout" }),

  me: () =>
    request<User>({ method: "GET", url: "/auth/me" }),

  refreshToken: () =>
    request<{ token: string }>({ method: "POST", url: "/auth/refresh" }),

  forgotPassword: (email: string) =>
    request<void>({ method: "POST", url: "/auth/forgot-password", data: { email } }),

  resetPassword: (token: string, password: string) =>
    request<void>({ method: "POST", url: "/auth/reset-password", data: { token, password } }),

  connectPlatform: (platformId: string, code: string) =>
    request<{ connected: boolean }>({
      method: "POST",
      url: `/auth/platforms/${platformId}/connect`,
      data: { code },
    }),

  disconnectPlatform: (platformId: string) =>
    request<void>({ method: "DELETE", url: `/auth/platforms/${platformId}` }),
};

// ============================================================
// POSTS API
// ============================================================
export const postsApi = {
  list: (params?: { status?: string; platform?: string; page?: number; perPage?: number }) =>
    request<Post[]>({ method: "GET", url: "/posts", params }),

  get: (id: string) =>
    request<Post>({ method: "GET", url: `/posts/${id}` }),

  create: (data: Partial<Post>) =>
    request<Post>({ method: "POST", url: "/posts", data }),

  update: (id: string, data: Partial<Post>) =>
    request<Post>({ method: "PATCH", url: `/posts/${id}`, data }),

  delete: (id: string) =>
    request<void>({ method: "DELETE", url: `/posts/${id}` }),

  schedule: (id: string, scheduledAt: string) =>
    request<Post>({ method: "POST", url: `/posts/${id}/schedule`, data: { scheduledAt } }),

  publish: (id: string) =>
    request<Post>({ method: "POST", url: `/posts/${id}/publish` }),

  approve: (id: string) =>
    request<Post>({ method: "POST", url: `/posts/${id}/approve` }),

  getCalendar: (startDate: string, endDate: string) =>
    request<Post[]>({ method: "GET", url: "/posts/calendar", params: { startDate, endDate } }),
};

// ============================================================
// CONTENT GENERATION API
// ============================================================
export const contentApi = {
  generate: (data: ContentGenerationRequest) =>
    request<GeneratedContent[]>({ method: "POST", url: "/content/generate", data }),

  generateCopy: (data: CopyRequest) =>
    request<GeneratedCopy>({ method: "POST", url: "/content/copy", data }),

  getTemplates: (type?: string) =>
    request<{ id: string; name: string; content: string }[]>({
      method: "GET",
      url: "/content/templates",
      params: { type },
    }),

  saveTemplate: (data: { name: string; content: string; type: string }) =>
    request<{ id: string }>({ method: "POST", url: "/content/templates", data }),
};

// ============================================================
// ANALYTICS API
// ============================================================
export const analyticsApi = {
  overview: (startDate: string, endDate: string, platforms?: string[]) =>
    request<AnalyticsData[]>({
      method: "GET",
      url: "/analytics/overview",
      params: { startDate, endDate, platforms: platforms?.join(",") },
    }),

  platforms: (startDate: string, endDate: string) =>
    request<PlatformMetrics[]>({
      method: "GET",
      url: "/analytics/platforms",
      params: { startDate, endDate },
    }),

  viralScore: () =>
    request<{ score: number; trend: string; history: { date: string; score: number }[] }>({
      method: "GET",
      url: "/analytics/viral-score",
    }),

  sentiment: (startDate: string, endDate: string) =>
    request<{ positive: number; neutral: number; negative: number }>({
      method: "GET",
      url: "/analytics/sentiment",
      params: { startDate, endDate },
    }),

  competitors: () =>
    request<{ name: string; followers: number; engagementRate: number }[]>({
      method: "GET",
      url: "/analytics/competitors",
    }),

  predictions: () =>
    request<{ date: string; followers: number; engagement: number }[]>({
      method: "GET",
      url: "/analytics/predictions",
    }),
};

// ============================================================
// STRATEGY API
// ============================================================
export const strategyApi = {
  upload: (file: File, type: string) => {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("type", type);
    return request<StrategyDocument>({
      method: "POST",
      url: "/strategy/upload",
      data: formData,
      headers: { "Content-Type": "multipart/form-data" },
    });
  },

  getDocuments: () =>
    request<StrategyDocument[]>({ method: "GET", url: "/strategy/documents" }),

  getInsights: () =>
    request<StrategyInsights>({ method: "GET", url: "/strategy/insights" }),

  analyzeDocument: (id: string) =>
    request<StrategyInsights>({ method: "POST", url: `/strategy/documents/${id}/analyze` }),

  deleteDocument: (id: string) =>
    request<void>({ method: "DELETE", url: `/strategy/documents/${id}` }),
};

// ============================================================
// CAMPAIGNS API
// ============================================================
export const campaignsApi = {
  list: (params?: { status?: string; page?: number }) =>
    request<Campaign[]>({ method: "GET", url: "/campaigns", params }),

  get: (id: string) =>
    request<Campaign>({ method: "GET", url: `/campaigns/${id}` }),

  create: (data: Partial<Campaign>) =>
    request<Campaign>({ method: "POST", url: "/campaigns", data }),

  update: (id: string, data: Partial<Campaign>) =>
    request<Campaign>({ method: "PATCH", url: `/campaigns/${id}`, data }),

  delete: (id: string) =>
    request<void>({ method: "DELETE", url: `/campaigns/${id}` }),

  generateBrief: (description: string, goals: string[]) =>
    request<{ brief: string; objectives: string[]; timeline: string }>({
      method: "POST",
      url: "/campaigns/generate-brief",
      data: { description, goals },
    }),
};

// ============================================================
// COMMUNITY API
// ============================================================
export const communityApi = {
  getComments: (params?: { status?: string; sentiment?: string; platform?: string; page?: number }) =>
    request<Comment[]>({ method: "GET", url: "/community/comments", params }),

  replyToComment: (id: string, reply: string) =>
    request<Comment>({ method: "POST", url: `/community/comments/${id}/reply`, data: { reply } }),

  archiveComment: (id: string) =>
    request<Comment>({ method: "POST", url: `/community/comments/${id}/archive` }),

  escalateComment: (id: string, note?: string) =>
    request<Comment>({ method: "POST", url: `/community/comments/${id}/escalate`, data: { note } }),

  getDMs: (params?: { status?: string; page?: number }) =>
    request<DMMessage[]>({ method: "GET", url: "/community/dms", params }),

  replyToDM: (id: string, message: string) =>
    request<DMMessage>({ method: "POST", url: `/community/dms/${id}/reply`, data: { message } }),

  qualifyLead: (id: string, score: number, notes?: string) =>
    request<void>({ method: "POST", url: `/community/dms/${id}/qualify`, data: { score, notes } }),
};

// ============================================================
// TRENDS API
// ============================================================
export const trendsApi = {
  list: (params?: { platform?: string; category?: string }) =>
    request<Trend[]>({ method: "GET", url: "/trends", params }),

  get: (id: string) =>
    request<Trend>({ method: "GET", url: `/trends/${id}` }),

  useTrend: (id: string, platforms: string[]) =>
    request<GeneratedContent[]>({
      method: "POST",
      url: `/trends/${id}/use`,
      data: { platforms },
    }),

  getHashtags: (query: string, platform?: string) =>
    request<{ tag: string; count: number; trend: string }[]>({
      method: "GET",
      url: "/trends/hashtags",
      params: { query, platform },
    }),
};

// ============================================================
// AGENTS API
// ============================================================
export const agentsApi = {
  list: () =>
    request<Agent[]>({ method: "GET", url: "/agents" }),

  get: (id: string) =>
    request<Agent>({ method: "GET", url: `/agents/${id}` }),

  runAgent: (id: string, taskConfig?: Record<string, unknown>) =>
    request<AgentTask>({ method: "POST", url: `/agents/${id}/run`, data: taskConfig }),

  stopAgent: (id: string) =>
    request<Agent>({ method: "POST", url: `/agents/${id}/stop` }),

  getTasks: (params?: { agentId?: string; status?: string }) =>
    request<AgentTask[]>({ method: "GET", url: "/agents/tasks", params }),

  getTaskHistory: (agentId: string) =>
    request<AgentTask[]>({ method: "GET", url: `/agents/${agentId}/history` }),
};

// ============================================================
// TEAM API
// ============================================================
export const teamApi = {
  list: () =>
    request<TeamMember[]>({ method: "GET", url: "/team" }),

  invite: (email: string, role: string, platforms?: string[]) =>
    request<TeamMember>({ method: "POST", url: "/team/invite", data: { email, role, platforms } }),

  updateMember: (id: string, data: Partial<TeamMember>) =>
    request<TeamMember>({ method: "PATCH", url: `/team/${id}`, data }),

  removeMember: (id: string) =>
    request<void>({ method: "DELETE", url: `/team/${id}` }),

  getApprovals: () =>
    request<ApprovalRequest[]>({ method: "GET", url: "/team/approvals" }),

  reviewApproval: (id: string, approved: boolean, notes?: string) =>
    request<ApprovalRequest>({
      method: "POST",
      url: `/team/approvals/${id}/review`,
      data: { approved, notes },
    }),
};

// ============================================================
// NOTIFICATIONS API
// ============================================================
export const notificationsApi = {
  list: (params?: { read?: boolean; page?: number }) =>
    request<Notification[]>({ method: "GET", url: "/notifications", params }),

  markRead: (id: string) =>
    request<void>({ method: "PATCH", url: `/notifications/${id}/read` }),

  markAllRead: () =>
    request<void>({ method: "POST", url: "/notifications/mark-all-read" }),

  delete: (id: string) =>
    request<void>({ method: "DELETE", url: `/notifications/${id}` }),
};

// ============================================================
// SETTINGS API
// ============================================================
export const settingsApi = {
  getProfile: () =>
    request<User>({ method: "GET", url: "/settings/profile" }),

  updateProfile: (data: Partial<User>) =>
    request<User>({ method: "PATCH", url: "/settings/profile", data }),

  changePassword: (currentPassword: string, newPassword: string) =>
    request<void>({
      method: "POST",
      url: "/settings/change-password",
      data: { currentPassword, newPassword },
    }),

  setup2FA: () =>
    request<{ qrCode: string; secret: string }>({ method: "POST", url: "/settings/2fa/setup" }),

  verify2FA: (code: string) =>
    request<{ backupCodes: string[] }>({ method: "POST", url: "/settings/2fa/verify", data: { code } }),

  getApiKeys: () =>
    request<ApiKey[]>({ method: "GET", url: "/settings/api-keys" }),

  createApiKey: (name: string, scopes: string[]) =>
    request<ApiKey>({ method: "POST", url: "/settings/api-keys", data: { name, scopes } }),

  deleteApiKey: (id: string) =>
    request<void>({ method: "DELETE", url: `/settings/api-keys/${id}` }),
};

export default apiClient;
