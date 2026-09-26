import { useEffect, useState } from "react";
import { getEcho } from "~/utils/echo-client";
import { useAuth } from "~/hooks/use-auth";
import type { WhatsappCall } from "~/data/types";

export function useIncomingCallListener() {
  const { user, status: authStatus } = useAuth();
  const [ringingCall, setRingingCall] = useState<WhatsappCall | null>(null);

  useEffect(() => {
    if (authStatus !== "authenticated" || !user?.companyId) return;

    const channelName = `company.${user.companyId}.whatsapp-calls`;
    const echo = getEcho();
    const channel = echo.private(channelName);

    channel.listen(".whatsapp-call.status-updated", (payload: { whatsappCall: WhatsappCall }) => {
      const call = payload.whatsappCall;
      if (call.direction !== "inbound" || call.answeredBy !== "human_agent") return;

      setRingingCall((current) => {
        if (call.status === "ringing") return call;
        if (current?.id === call.id) return null;
        return current;
      });
    });

    return () => {
      echo.leave(channelName);
    };
  }, [authStatus, user?.companyId]);

  function dismiss() {
    setRingingCall(null);
  }

  return { ringingCall, dismiss };
}
