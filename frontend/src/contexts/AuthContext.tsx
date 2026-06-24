"use client";

import React, { createContext, useContext, useEffect, useState } from "react";
import { authApi } from "@/lib/api";
import { clearToken, getToken, setToken } from "@/lib/utils";

interface User {
  id: number;
  name: string;
  email: string;
  user_type: "super_admin" | "hr" | "candidate";
  tenant_id: number | null;
  avatar: string | null;
  locale: string;
  tenant?: { id: number; name: string; logo: string; primary_color: string };
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<{ redirect: string }>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({} as AuthContextType);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setTokenState] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const savedToken = getToken();
    if (savedToken) {
      setTokenState(savedToken);
      authApi.me()
        .then((res) => setUser(res.data.user))
        .catch(() => clearToken())
        .finally(() => setIsLoading(false));
    } else {
      setIsLoading(false);
    }
  }, []);

  const login = async (email: string, password: string) => {
    const res = await authApi.login(email, password);
    const { access_token, user: userData, redirect } = res.data;
    setToken(access_token);
    setTokenState(access_token);
    setUser(userData);
    return { redirect };
  };

  const logout = async () => {
    try { await authApi.logout(); } catch {}
    clearToken();
    setUser(null);
    setTokenState(null);
    window.location.href = "/login";
  };

  const refreshUser = async () => {
    const res = await authApi.me();
    setUser(res.data.user);
  };

  return (
    <AuthContext.Provider value={{ user, token, isLoading, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
