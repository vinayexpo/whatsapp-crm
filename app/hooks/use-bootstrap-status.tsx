import { useEffect, useState } from "react";
import { apiClient } from "~/utils/api-client";

export type BootstrapStatus = "loading" | "needs-superadmin" | "ready";

export function useBootstrapStatus() {
  const [status, setStatus] = useState<BootstrapStatus>("loading");

  useEffect(() => {
    let cancelled = false;

    apiClient
      .bootstrapStatus()
      .then((superadminExists) => {
        if (cancelled) return;
        setStatus(superadminExists ? "ready" : "needs-superadmin");
      })
      .catch(() => {
        if (cancelled) return;
        setStatus("ready");
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return status;
}
