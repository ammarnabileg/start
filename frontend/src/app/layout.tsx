import type { Metadata } from "next";
import { Toaster } from "react-hot-toast";
import "./globals.css";

export const metadata: Metadata = {
  title: "AI Recruitment Platform | منصة التوظيف الذكية",
  description: "منصة التوظيف الذكية - Automated AI-Powered Recruitment SaaS",
  icons: { icon: "/favicon.ico" },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar" dir="rtl" className="h-full">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
      </head>
      <body className="h-full antialiased bg-[#f9fafb]">
        {children}
        <Toaster
          position="top-center"
          toastOptions={{
            duration: 4000,
            style: {
              fontFamily: "Cairo, Inter, sans-serif",
              fontSize: "14px",
              borderRadius: "10px",
              padding: "12px 16px",
              boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
            },
            success: { iconTheme: { primary: "#7c3aed", secondary: "#fff" } },
          }}
        />
      </body>
    </html>
  );
}
