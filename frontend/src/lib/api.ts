import axios from "axios";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

const api = axios.create({
  baseURL: API_URL,
  headers: { "Content-Type": "application/json", Accept: "application/json" },
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    const token = localStorage.getItem("token");
    if (token) config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && typeof window !== "undefined") {
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      window.location.href = "/login";
    }
    return Promise.reject(error);
  }
);

export default api;

// Auth
export const authApi = {
  login: (email: string, password: string) => api.post("/auth/login", { email, password }),
  me: () => api.get("/auth/me"),
  logout: () => api.post("/auth/logout"),
  refresh: () => api.post("/auth/refresh"),
};

// Setup
export const setupApi = {
  status: () => api.get("/setup/status"),
  check: () => api.get("/setup/check"),
  settings: () => api.get("/setup/settings"),
  testDb: (data: Record<string, string>) => api.post("/setup/test-database", data),
  validateKeys: (data: Record<string, string>) => api.post("/setup/validate-keys", data),
  install: (data: Record<string, unknown>) => api.post("/setup/install", data),
  update: (data: Record<string, unknown>) => api.post("/setup/update", data),
  terminal: (command: string) => api.post("/setup/terminal", { command }),
  lock: () => api.post("/setup/lock"),
};

// Dashboard
export const dashboardApi = {
  get: () => api.get("/dashboard"),
  aiAnalytics: (period?: string) => api.get("/dashboard/ai-analytics", { params: { period } }),
  copilot: (question: string) => api.post("/dashboard/copilot", { question }),
};

// Jobs
export const jobsApi = {
  list: (params?: Record<string, string>) => api.get("/jobs", { params }),
  create: (data: Record<string, unknown>) => api.post("/jobs", data),
  get: (id: number) => api.get(`/jobs/${id}`),
  update: (id: number, data: Record<string, unknown>) => api.put(`/jobs/${id}`, data),
  delete: (id: number) => api.delete(`/jobs/${id}`),
  publish: (id: number) => api.post(`/jobs/${id}/publish`),
  duplicate: (id: number) => api.post(`/jobs/${id}/duplicate`),
  generateLink: (id: number, data: Record<string, string>) => api.post(`/jobs/${id}/generate-link`, data),
  generateQuestions: (id: number) => api.post(`/jobs/${id}/generate-questions`),
  aiGenerate: (data: Record<string, string>) => api.post("/jobs/ai-generate", data),
  getCriteria: (jobId: number) => api.get(`/jobs/${jobId}/criteria`),
  addCriteria: (jobId: number, data: Record<string, unknown>) => api.post(`/jobs/${jobId}/criteria`, data),
  deleteCriteria: (jobId: number, criteriaId: number) => api.delete(`/jobs/${jobId}/criteria/${criteriaId}`),
  addQuestion: (jobId: number, data: Record<string, unknown>) => api.post(`/jobs/${jobId}/questions`, data),
  deleteQuestion: (jobId: number, questionId: number) => api.delete(`/jobs/${jobId}/questions/${questionId}`),
};

// Applications / Candidates
export const applicationsApi = {
  list: (params?: Record<string, unknown>) => api.get("/applications", { params }),
  get: (id: number) => api.get(`/applications/${id}`),
  updateStage: (id: number, stage: string) => api.put(`/applications/${id}/stage`, { pipeline_stage: stage }),
  bulkUpdateStage: (ids: number[], stage: string) => api.post("/applications/bulk-stage", { application_ids: ids, pipeline_stage: stage }),
  addNote: (id: number, note: string) => api.post(`/applications/${id}/note`, { note }),
  compare: (ids: number[], question?: string) => api.post("/applications/compare", { application_ids: ids, question }),
  reEvaluate: (id: number) => api.post(`/applications/${id}/re-evaluate`),
  export: (id: number) => window.open(`${API_URL}/applications/${id}/export?token=${localStorage.getItem("token")}`, "_blank"),
  addToTalentPool: (candidateId: number, poolIds: number[], notes?: string) => api.post(`/candidates/${candidateId}/talent-pool`, { pool_ids: poolIds, notes }),
};

// Interviews
export const interviewApi = {
  validateToken: (token: string) => api.get(`/interview/validate/${token}`),
  start: (token: string) => api.post(`/interview/start/${token}`),
  sendMessage: (token: string, message: string) => api.post(`/interview/message/${token}`, { message }),
  transcribe: (token: string, formData: FormData) => api.post(`/interview/transcribe/${token}`, formData, { headers: { "Content-Type": "multipart/form-data" } }),
  heygenSession: (token: string) => api.post(`/interview/heygen-session/${token}`),
  feedback: (token: string, data: Record<string, unknown>) => api.post(`/interview/feedback/${token}`, data),
  // Legacy session-based (kept for compatibility)
  validate: (token: string) => api.get(`/interview/validate/${token}`),
  getSession: (sessionId: number) => api.get(`/interview/session/${sessionId}`),
  message: (sessionId: number, message: string) => api.post(`/interview/message/${sessionId}`, { message }),
};

// HR Interviews
export const hrInterviewApi = {
  list: (params?: Record<string, string>) => api.get("/human-interviews", { params }),
  schedule: (data: Record<string, unknown>) => api.post("/human-interviews", data),
  evaluate: (id: number, data: Record<string, unknown>) => api.post(`/human-interviews/${id}/evaluate`, data),
  cancel: (id: number) => api.post(`/human-interviews/${id}/cancel`),
};

// Offers
export const offersApi = {
  list: () => api.get("/offers"),
  create: (data: Record<string, unknown>) => api.post("/offers", data),
  send: (id: number) => api.post(`/offers/${id}/send`),
  pdf: (id: number) => api.get(`/offers/${id}/pdf`),
  generatePdf: (id: number) => api.get(`/offers/${id}/pdf`, { responseType: "blob" }),
  aiGenerate: (applicationId: number) => api.post("/offers/ai-generate", { application_id: applicationId }),
};

// Talent Pool
export const talentPoolApi = {
  list: () => api.get("/talent-pools"),
  create: (data: Record<string, unknown>) => api.post("/talent-pools", data),
  get: (id: number) => api.get(`/talent-pools/${id}`),
  delete: (id: number) => api.delete(`/talent-pools/${id}`),
  candidates: (poolId: number) => api.get(`/talent-pools/${poolId}/candidates`),
  addCandidate: (poolId: number, candidateId: number) => api.post(`/talent-pools/${poolId}/candidates`, { candidate_id: candidateId }),
  removeCandidate: (poolId: number, candidateId: number) => api.delete(`/talent-pools/${poolId}/candidates/${candidateId}`),
  search: (data: Record<string, string>) => api.post("/talent-pools/search", data),
};

// Avatars
export const avatarsApi = {
  list: () => api.get("/avatars"),
  create: (data: FormData) => api.post("/avatars", data, { headers: { "Content-Type": "multipart/form-data" } }),
  update: (id: number, data: FormData) => api.post(`/avatars/${id}`, data, { headers: { "Content-Type": "multipart/form-data" } }),
  delete: (id: number) => api.delete(`/avatars/${id}`),
  heygenList: () => api.get("/avatars/heygen/list"),
};

// Users
export const usersApi = {
  list: () => api.get("/users"),
  create: (data: Record<string, unknown>) => api.post("/users", data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/users/${id}`, data),
  delete: (id: number) => api.delete(`/users/${id}`),
};

// Departments
export const departmentsApi = {
  list: () => api.get("/departments"),
  create: (data: Record<string, unknown>) => api.post("/departments", data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/departments/${id}`, data),
  delete: (id: number) => api.delete(`/departments/${id}`),
};

// Questions
export const questionsApi = {
  list: (params?: Record<string, string>) => api.get("/questions", { params }),
  create: (data: Record<string, unknown>) => api.post("/questions", data),
  delete: (id: number) => api.delete(`/questions/${id}`),
};

// Super Admin
export const superAdminApi = {
  stats: () => api.get("/super-admin/stats"),
  dashboard: () => api.get("/super-admin/dashboard"),
  tenants: (params?: Record<string, string>) => api.get("/super-admin/tenants", { params }),
  createTenant: (data: Record<string, unknown>) => api.post("/super-admin/tenants", data),
  updateTenant: (id: number, data: Record<string, unknown>) => api.put(`/super-admin/tenants/${id}`, data),
  companies: (params?: Record<string, string>) => api.get("/super-admin/companies", { params }),
  createCompany: (data: Record<string, unknown>) => api.post("/super-admin/companies", data),
  updateCompany: (id: number, data: Record<string, unknown>) => api.put(`/super-admin/companies/${id}`, data),
  toggleStatus: (id: number) => api.post(`/super-admin/companies/${id}/toggle-status`),
  impersonate: (tenantId: number) => api.post(`/super-admin/impersonate/${tenantId}`),
  settings: () => api.get("/super-admin/settings"),
  saveSettings: (data: Record<string, unknown>) => api.post("/super-admin/settings", data),
  terminal: (command: string) => api.post("/super-admin/terminal", { command }),
  aiUsage: () => api.get("/super-admin/ai-usage"),
};

// Candidate Portal
export const candidateApi = {
  register: (data: Record<string, unknown>) => api.post("/candidate/register", data),
  jobs: (params?: Record<string, string>) => api.get("/candidate/jobs", { params }),
  portal: () => api.get("/candidate/portal"),
  profile: () => api.get("/candidate/profile"),
  updateProfile: (data: Record<string, unknown>) => api.put("/candidate/profile", data),
  uploadCv: (file: File) => { const fd = new FormData(); fd.append("cv", file); return api.post("/candidate/cv", fd, { headers: { "Content-Type": "multipart/form-data" } }); },
  applications: () => api.get("/candidate/applications"),
  apply: (jobId: number, cvId?: number) => api.post(`/candidate/apply/${jobId}`, { cv_id: cvId }),
  notifications: () => api.get("/candidate/notifications"),
  offers: () => api.get("/candidate/offers"),
};
