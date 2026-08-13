import { useEffect, type ReactNode } from "react";
import { useNavigate } from "react-router";
import Box from "@mui/material/Box";
import CircularProgress from "@mui/material/CircularProgress";
import { useAuth } from "~/hooks/use-auth";
import { apiClient } from "~/utils/api-client";

export function AuthGuard({ children }: { children: ReactNode }) {
  const navigate = useNavigate();
  const { user, status, setUser, setStatus } = useAuth();

  useEffect(() => {
    if (status !== "loading") {
      return;
    }
    let cancelled = false;

    apiClient
      .me()
      .then((me) => {
        if (cancelled) return;
        setUser(me);
        setStatus("authenticated");
      })
      .catch(() => {
        if (cancelled) return;
        setStatus("unauthenticated");
      });

    return () => {
      cancelled = true;
    };
  }, [status, setUser, setStatus]);

  useEffect(() => {
    if (status !== "unauthenticated") {
      return;
    }
    let cancelled = false;

    apiClient
      .bootstrapStatus()
      .then((superadminExists) => {
        if (cancelled) return;
        navigate(superadminExists ? "/login" : "/setup", { replace: true });
      })
      .catch(() => {
        if (cancelled) return;
        navigate("/login", { replace: true });
      });

    return () => {
      cancelled = true;
    };
  }, [status, navigate]);

  useEffect(() => {
    if (status === "authenticated" && user?.role === "superadmin") {
      navigate("/superadmin", { replace: true });
    }
  }, [status, user, navigate]);

  if (status === "loading" || status === "unauthenticated") {
    return (
      <Box sx={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: "100vh" }}>
        <CircularProgress />
      </Box>
    );
  }

  if (!user) {
    return null;
  }

  return <>{children}</>;
}
