import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function getScoreColor(score: number): string {
  if (score >= 82) return "text-green-600";
  if (score >= 68) return "text-blue-600";
  if (score >= 50) return "text-yellow-600";
  return "text-red-600";
}

export function getScoreBg(score: number): string {
  if (score >= 82) return "bg-green-100";
  if (score >= 68) return "bg-blue-100";
  if (score >= 50) return "bg-yellow-100";
  return "bg-red-100";
}

export function getRecommendationLabel(rec: string, lang: "ar" | "en" = "ar"): string {
  const labels: Record<string, Record<string, string>> = {
    ar: {
      strong_recommendation: "توصية قوية",
      suitable: "مناسب",
      possible_fit: "قد يكون مناسباً",
      not_recommended: "غير مناسب",
    },
    en: {
      strong_recommendation: "Strong Recommendation",
      suitable: "Suitable",
      possible_fit: "Possible Fit",
      not_recommended: "Not Recommended",
    },
  };
  return labels[lang][rec] ?? rec;
}

export function getStageLabel(stage: string, lang: "ar" | "en" = "ar"): string {
  const labels: Record<string, Record<string, string>> = {
    ar: {
      applied: "تقدّم",
      ai_screening: "فحص الذكاء الاصطناعي",
      qualified: "مؤهل",
      disqualified: "غير مؤهل",
      tech_interview: "مقابلة تقنية",
      manager_interview: "مقابلة المدير",
      final_review: "مراجعة نهائية",
      offer: "عرض وظيفي",
      hired: "تم التعيين",
      rejected: "مرفوض",
      withdrawn: "انسحب",
    },
    en: {
      applied: "Applied",
      ai_screening: "AI Screening",
      qualified: "Qualified",
      disqualified: "Disqualified",
      tech_interview: "Tech Interview",
      manager_interview: "Manager Interview",
      final_review: "Final Review",
      offer: "Offer",
      hired: "Hired",
      rejected: "Rejected",
      withdrawn: "Withdrawn",
    },
  };
  return labels[lang][stage] ?? stage;
}

export function getStageBadgeClass(stage: string): string {
  const classes: Record<string, string> = {
    applied: "badge-gray",
    ai_screening: "badge-purple",
    qualified: "badge-green",
    disqualified: "badge-red",
    tech_interview: "badge-blue",
    manager_interview: "badge-blue",
    final_review: "badge-yellow",
    offer: "badge-purple",
    hired: "badge-green",
    rejected: "badge-red",
    withdrawn: "badge-gray",
  };
  return classes[stage] ?? "badge-gray";
}

export function formatDate(date: string, lang: "ar" | "en" = "ar"): string {
  return new Intl.DateTimeFormat(lang === "ar" ? "ar-SA" : "en-US", {
    year: "numeric", month: "short", day: "numeric",
  }).format(new Date(date));
}

export function formatRelativeTime(date: string, lang: "ar" | "en" = "ar"): string {
  const rtf = new Intl.RelativeTimeFormat(lang === "ar" ? "ar" : "en", { numeric: "auto" });
  const diff = (new Date(date).getTime() - Date.now()) / 1000;
  if (Math.abs(diff) < 60) return rtf.format(Math.round(diff), "second");
  if (Math.abs(diff) < 3600) return rtf.format(Math.round(diff / 60), "minute");
  if (Math.abs(diff) < 86400) return rtf.format(Math.round(diff / 3600), "hour");
  return rtf.format(Math.round(diff / 86400), "day");
}

export function initials(name: string): string {
  return name.split(" ").map((n) => n[0]).slice(0, 2).join("").toUpperCase();
}

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("token");
}

export function setToken(token: string): void {
  localStorage.setItem("token", token);
}

export function clearToken(): void {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
}
