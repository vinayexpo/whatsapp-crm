import { useEffect, useRef, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Chip from "@mui/material/Chip";
import Collapse from "@mui/material/Collapse";
import Divider from "@mui/material/Divider";
import { apiClient } from "~/utils/api-client";
import { getEcho } from "~/utils/echo-client";
import type { WhatsappCall, WhatsappCallFlow } from "~/data/types";
import { formatTime } from "~/utils/format";

interface WhatsappCallLogPanelProps {
  callFlow: WhatsappCallFlow;
}

const STATUS_COLOR: Record<WhatsappCall["status"], "default" | "success" | "error" | "warning" | "info"> = {
  ringing: "info",
  in_progress: "info",
  completed: "success",
  failed: "error",
  missed: "warning",
  needs_human_followup: "warning",
};

function statusLabel(status: WhatsappCall["status"]) {
  return status
    .split("_")
    .map((w) => w[0].toUpperCase() + w.slice(1))
    .join(" ");
}

export function WhatsappCallLogPanel({ callFlow }: WhatsappCallLogPanelProps) {
  const [calls, setCalls] = useState<WhatsappCall[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    apiClient
      .listWhatsappCalls({ callFlowId: callFlow.id })
      .then((data) => {
        if (!cancelled) setCalls(data);
      })
      .catch(() => {
        // call log stays empty on failure
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [callFlow.id]);

  const subscribedCallIds = useRef<Set<string>>(new Set());

  useEffect(() => {
    const echo = getEcho();

    for (const call of calls) {
      if (subscribedCallIds.current.has(call.id)) continue;
      subscribedCallIds.current.add(call.id);

      echo.private(`whatsapp-call.${call.id}`).listen(
        ".whatsapp-call.status-updated",
        (payload: { whatsappCall: WhatsappCall }) => {
          setCalls((prev) =>
            prev.map((c) => (c.id === payload.whatsappCall.id ? payload.whatsappCall : c)),
          );
        },
      );
    }
  }, [calls]);

  useEffect(() => {
    return () => {
      const echo = getEcho();
      subscribedCallIds.current.forEach((id) => echo.leave(`whatsapp-call.${id}`));
      subscribedCallIds.current.clear();
    };
  }, [callFlow.id]);

  if (loading) {
    return (
      <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 2 }}>
        Loading call log…
      </Typography>
    );
  }

  if (calls.length === 0) {
    return (
      <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 2 }}>
        No calls logged yet for this call flow.
      </Typography>
    );
  }

  return (
    <Stack spacing={1.5}>
      {calls.map((call) => {
        const expanded = expandedId === call.id;
        return (
          <Paper key={call.id} variant="outlined" sx={{ borderRadius: 2, p: 1.75 }}>
            <Stack
              spacing={0.5}
              sx={{ cursor: "pointer" }}
              onClick={() => setExpandedId(expanded ? null : call.id)}
            >
              <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", gap: 1 }}>
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  {call.startedAt ? formatTime(call.startedAt) : formatTime(call.createdAt)}
                </Typography>
                <Chip label={statusLabel(call.status)} size="small" color={STATUS_COLOR[call.status]} />
              </Stack>
              <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap" }}>
                <Chip label={call.direction} size="small" variant="outlined" />
                {call.needsHumanFollowup && <Chip label="Needs follow-up" size="small" color="warning" />}
              </Stack>
            </Stack>

            <Collapse in={expanded}>
              <Divider sx={{ my: 1.5 }} />
              <Stack spacing={1.5}>
                {Object.keys(call.collectedVariables).length > 0 && (
                  <Box>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      Collected variables
                    </Typography>
                    <Stack spacing={0.25}>
                      {Object.entries(call.collectedVariables).map(([key, value]) => (
                        <Typography key={key} variant="body2">
                          <strong>{key}:</strong> {String(value)}
                        </Typography>
                      ))}
                    </Stack>
                  </Box>
                )}

                {call.transcript.length > 0 && (
                  <Box>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      Transcript
                    </Typography>
                    <Stack spacing={0.75} sx={{ mt: 0.5 }}>
                      {call.transcript.map((turn, idx) => {
                        const t = turn as { role?: string; text?: string };
                        return (
                          <Box key={idx}>
                            <Typography variant="caption" sx={{ fontWeight: 700, textTransform: "capitalize" }}>
                              {t.role ?? "unknown"}:
                            </Typography>{" "}
                            <Typography variant="body2" component="span">
                              {t.text ?? ""}
                            </Typography>
                          </Box>
                        );
                      })}
                    </Stack>
                  </Box>
                )}
              </Stack>
            </Collapse>
          </Paper>
        );
      })}
    </Stack>
  );
}
