import type { Metadata, Viewport } from "next";
import { Inter, Cairo } from "next/font/google";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
  display: "swap",
  weight: ["300", "400", "500", "600", "700", "800", "900"],
});

const cairo = Cairo({
  subsets: ["arabic", "latin"],
  variable: "--font-cairo",
  display: "swap",
  weight: ["300", "400", "500", "600", "700", "800", "900"],
});

export const metadata: Metadata = {
  title: {
    default: "SociAI OS — AI-Powered Social Media Operating System",
    template: "%s | SociAI OS",
  },
  description: "Enterprise-grade AI-powered social media management platform with intelligent agents, content generation, and deep analytics.",
  keywords: ["social media", "AI", "automation", "content generation", "analytics", "marketing"],
  authors: [{ name: "SociAI OS" }],
  creator: "SociAI OS",
  metadataBase: new URL(process.env.NEXT_PUBLIC_APP_URL || "http://localhost:3000"),
  openGraph: {
    type: "website",
    locale: "en_US",
    url: process.env.NEXT_PUBLIC_APP_URL,
    title: "SociAI OS — AI-Powered Social Media Operating System",
    description: "Enterprise-grade AI-powered social media management platform",
    siteName: "SociAI OS",
  },
  robots: {
    index: false,
    follow: false,
  },
  icons: {
    icon: "/favicon.ico",
    apple: "/apple-touch-icon.png",
  },
};

export const viewport: Viewport = {
  themeColor: "#0A0B1A",
  colorScheme: "dark",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" dir="ltr" className="dark" suppressHydrationWarning>
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
      </head>
      <body
        className={`${inter.variable} ${cairo.variable} font-inter antialiased bg-[#0A0B1A] text-white min-h-screen`}
        suppressHydrationWarning
      >
        {children}
      </body>
    </html>
  );
}
