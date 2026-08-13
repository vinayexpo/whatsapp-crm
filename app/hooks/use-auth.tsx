import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from "react";
import type { TeamMember } from "~/data/types";

interface AuthContextValue {
  user: TeamMember | null;
  status: "loading" | "authenticated" | "unauthenticated";
  setUser: (user: TeamMember | null) => void;
  setStatus: (status: AuthContextValue["status"]) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<TeamMember | null>(null);
  const [status, setStatus] = useState<AuthContextValue["status"]>("loading");

  const value = useMemo(() => ({ user, status, setUser, setStatus }), [user, status]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return ctx;
}
