import type { Config } from "tailwindcss";

const config: Config = {
  darkMode: ["class"],
  content: [
    "./pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        "deep-navy": {
          DEFAULT: "#0A0B1A",
          50: "#0D0F22",
          100: "#10122A",
          200: "#131532",
          300: "#16193B",
          400: "#1A1D45",
          500: "#1E214E",
          600: "#252858",
          700: "#2C3062",
          800: "#33376C",
          900: "#3A3F76",
        },
        "electric-blue": {
          DEFAULT: "#3B82F6",
          50: "#EBF3FF",
          100: "#DBEAFE",
          200: "#BFDBFE",
          300: "#93C5FD",
          400: "#60A5FA",
          500: "#3B82F6",
          600: "#2563EB",
          700: "#1D4ED8",
          800: "#1E40AF",
          900: "#1E3A8A",
        },
        "neon-purple": {
          DEFAULT: "#8B5CF6",
          50: "#F5F3FF",
          100: "#EDE9FE",
          200: "#DDD6FE",
          300: "#C4B5FD",
          400: "#A78BFA",
          500: "#8B5CF6",
          600: "#7C3AED",
          700: "#6D28D9",
          800: "#5B21B6",
          900: "#4C1D95",
        },
        "neon-green": {
          DEFAULT: "#10B981",
          50: "#ECFDF5",
          100: "#D1FAE5",
          200: "#A7F3D0",
          300: "#6EE7B7",
          400: "#34D399",
          500: "#10B981",
          600: "#059669",
          700: "#047857",
          800: "#065F46",
          900: "#064E3B",
        },
        "neon-pink": {
          DEFAULT: "#EC4899",
          500: "#EC4899",
          600: "#DB2777",
        },
        "cyber-yellow": {
          DEFAULT: "#F59E0B",
          500: "#F59E0B",
        },
        glass: {
          DEFAULT: "rgba(255, 255, 255, 0.05)",
          border: "rgba(255, 255, 255, 0.1)",
          hover: "rgba(255, 255, 255, 0.08)",
        },
      },
      backgroundImage: {
        "gradient-radial": "radial-gradient(var(--tw-gradient-stops))",
        "gradient-conic":
          "conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))",
        "blue-purple-gradient":
          "linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%)",
        "dark-gradient":
          "linear-gradient(135deg, #0A0B1A 0%, #13152E 50%, #0A0B1A 100%)",
        "glass-gradient":
          "linear-gradient(135deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%)",
        "neon-glow-blue":
          "radial-gradient(ellipse at center, rgba(59,130,246,0.3) 0%, transparent 70%)",
        "neon-glow-purple":
          "radial-gradient(ellipse at center, rgba(139,92,246,0.3) 0%, transparent 70%)",
      },
      animation: {
        "fade-in": "fadeIn 0.5s ease-in-out",
        "fade-up": "fadeUp 0.5s ease-out",
        "slide-in-right": "slideInRight 0.3s ease-out",
        "slide-in-left": "slideInLeft 0.3s ease-out",
        "scale-in": "scaleIn 0.3s ease-out",
        pulse: "pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite",
        "pulse-slow": "pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite",
        glow: "glow 2s ease-in-out infinite alternate",
        "glow-blue": "glowBlue 2s ease-in-out infinite alternate",
        "glow-purple": "glowPurple 2s ease-in-out infinite alternate",
        float: "float 3s ease-in-out infinite",
        "spin-slow": "spin 8s linear infinite",
        shimmer: "shimmer 2s linear infinite",
        "bounce-subtle": "bounceSubtle 1s ease-in-out infinite",
        "border-glow": "borderGlow 2s ease-in-out infinite alternate",
        particle: "particle 8s ease-in-out infinite",
        "gradient-shift": "gradientShift 4s ease-in-out infinite",
      },
      keyframes: {
        fadeIn: {
          "0%": { opacity: "0" },
          "100%": { opacity: "1" },
        },
        fadeUp: {
          "0%": { opacity: "0", transform: "translateY(20px)" },
          "100%": { opacity: "1", transform: "translateY(0)" },
        },
        slideInRight: {
          "0%": { opacity: "0", transform: "translateX(20px)" },
          "100%": { opacity: "1", transform: "translateX(0)" },
        },
        slideInLeft: {
          "0%": { opacity: "0", transform: "translateX(-20px)" },
          "100%": { opacity: "1", transform: "translateX(0)" },
        },
        scaleIn: {
          "0%": { opacity: "0", transform: "scale(0.9)" },
          "100%": { opacity: "1", transform: "scale(1)" },
        },
        glow: {
          "0%": { boxShadow: "0 0 5px rgba(59,130,246,0.5)" },
          "100%": { boxShadow: "0 0 20px rgba(59,130,246,0.8), 0 0 40px rgba(59,130,246,0.3)" },
        },
        glowBlue: {
          "0%": { boxShadow: "0 0 5px rgba(59,130,246,0.3), 0 0 10px rgba(59,130,246,0.2)" },
          "100%": { boxShadow: "0 0 20px rgba(59,130,246,0.6), 0 0 40px rgba(59,130,246,0.3), 0 0 60px rgba(59,130,246,0.1)" },
        },
        glowPurple: {
          "0%": { boxShadow: "0 0 5px rgba(139,92,246,0.3), 0 0 10px rgba(139,92,246,0.2)" },
          "100%": { boxShadow: "0 0 20px rgba(139,92,246,0.6), 0 0 40px rgba(139,92,246,0.3), 0 0 60px rgba(139,92,246,0.1)" },
        },
        float: {
          "0%, 100%": { transform: "translateY(0px)" },
          "50%": { transform: "translateY(-10px)" },
        },
        shimmer: {
          "0%": { backgroundPosition: "-200% 0" },
          "100%": { backgroundPosition: "200% 0" },
        },
        bounceSubtle: {
          "0%, 100%": { transform: "translateY(0)" },
          "50%": { transform: "translateY(-4px)" },
        },
        borderGlow: {
          "0%": { borderColor: "rgba(59,130,246,0.3)" },
          "100%": { borderColor: "rgba(139,92,246,0.8)" },
        },
        particle: {
          "0%": { transform: "translateY(100vh) translateX(0px)", opacity: "0" },
          "10%": { opacity: "1" },
          "90%": { opacity: "1" },
          "100%": { transform: "translateY(-100vh) translateX(50px)", opacity: "0" },
        },
        gradientShift: {
          "0%, 100%": { backgroundPosition: "0% 50%" },
          "50%": { backgroundPosition: "100% 50%" },
        },
      },
      backdropBlur: {
        xs: "2px",
        "4xl": "72px",
      },
      boxShadow: {
        glass: "0 8px 32px rgba(0, 0, 0, 0.37)",
        "glass-sm": "0 4px 16px rgba(0, 0, 0, 0.3)",
        "glass-lg": "0 16px 64px rgba(0, 0, 0, 0.5)",
        "neon-blue": "0 0 20px rgba(59,130,246,0.5), 0 0 40px rgba(59,130,246,0.25)",
        "neon-purple": "0 0 20px rgba(139,92,246,0.5), 0 0 40px rgba(139,92,246,0.25)",
        "neon-green": "0 0 20px rgba(16,185,129,0.5), 0 0 40px rgba(16,185,129,0.25)",
        "neon-pink": "0 0 20px rgba(236,72,153,0.5), 0 0 40px rgba(236,72,153,0.25)",
        "inner-glow": "inset 0 0 30px rgba(59,130,246,0.1)",
      },
      fontFamily: {
        inter: ["var(--font-inter)", "Inter", "sans-serif"],
        cairo: ["var(--font-cairo)", "Cairo", "sans-serif"],
      },
      fontSize: {
        "2xs": "0.625rem",
      },
      spacing: {
        "18": "4.5rem",
        "88": "22rem",
        "100": "25rem",
        "112": "28rem",
        "128": "32rem",
      },
      borderRadius: {
        "4xl": "2rem",
        "5xl": "2.5rem",
      },
      transitionDuration: {
        "400": "400ms",
      },
      zIndex: {
        "60": "60",
        "70": "70",
        "80": "80",
        "90": "90",
        "100": "100",
      },
    },
  },
  plugins: [
    function ({ addUtilities }: { addUtilities: (utils: Record<string, Record<string, string>>) => void }) {
      addUtilities({
        ".glass": {
          background: "rgba(255, 255, 255, 0.05)",
          "backdrop-filter": "blur(12px)",
          "-webkit-backdrop-filter": "blur(12px)",
          border: "1px solid rgba(255, 255, 255, 0.1)",
        },
        ".glass-sm": {
          background: "rgba(255, 255, 255, 0.03)",
          "backdrop-filter": "blur(8px)",
          "-webkit-backdrop-filter": "blur(8px)",
          border: "1px solid rgba(255, 255, 255, 0.07)",
        },
        ".glass-dark": {
          background: "rgba(10, 11, 26, 0.8)",
          "backdrop-filter": "blur(20px)",
          "-webkit-backdrop-filter": "blur(20px)",
          border: "1px solid rgba(255, 255, 255, 0.08)",
        },
        ".glass-hover": {
          transition: "all 0.3s ease",
        },
        ".glass-hover:hover": {
          background: "rgba(255, 255, 255, 0.08)",
          border: "1px solid rgba(255, 255, 255, 0.15)",
        },
        ".gradient-border": {
          position: "relative",
          background: "linear-gradient(135deg, #3B82F6, #8B5CF6)",
          padding: "1px",
          "border-radius": "12px",
        },
        ".gradient-border-inner": {
          background: "#0A0B1A",
          "border-radius": "11px",
        },
        ".text-gradient": {
          background: "linear-gradient(135deg, #3B82F6, #8B5CF6)",
          "-webkit-background-clip": "text",
          "-webkit-text-fill-color": "transparent",
          "background-clip": "text",
        },
        ".text-gradient-green": {
          background: "linear-gradient(135deg, #10B981, #3B82F6)",
          "-webkit-background-clip": "text",
          "-webkit-text-fill-color": "transparent",
          "background-clip": "text",
        },
        ".scrollbar-thin": {
          "scrollbar-width": "thin",
          "scrollbar-color": "rgba(59,130,246,0.3) transparent",
        },
        ".scrollbar-thin::-webkit-scrollbar": {
          width: "4px",
          height: "4px",
        },
        ".scrollbar-thin::-webkit-scrollbar-track": {
          background: "transparent",
        },
        ".scrollbar-thin::-webkit-scrollbar-thumb": {
          background: "rgba(59,130,246,0.3)",
          "border-radius": "2px",
        },
        ".scrollbar-thin::-webkit-scrollbar-thumb:hover": {
          background: "rgba(59,130,246,0.5)",
        },
      });
    },
  ],
};

export default config;
