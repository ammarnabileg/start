"use client";

import { setupApi } from "@/lib/api";
import { getToken } from "@/lib/utils";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function Home() {
  const router = useRouter();

  useEffect(() => {
    async function check() {
      try {
        const res = await setupApi.status();
        if (!res.data.installed) {
          router.replace("/setup");
          return;
        }
        const token = getToken();
        if (token) {
          router.replace("/dashboard");
        } else {
          router.replace("/login");
        }
      } catch {
        router.replace("/setup");
      }
    }
    check();
  }, [router]);

  return (
    <div className="min-h-screen bg-[#f9fafb] flex items-center justify-center">
      <div className="flex flex-col items-center gap-4">
        <div className="w-12 h-12 bg-violet-600 rounded-2xl flex items-center justify-center animate-pulse">
          <span className="text-white font-bold text-xl">AI</span>
        </div>
        <p className="text-sm text-gray-500 font-medium">جاري التحقق...</p>
      </div>
    </div>
  );
}
