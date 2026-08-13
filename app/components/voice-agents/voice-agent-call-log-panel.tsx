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
import type { VoiceAgent, VoiceCall } from "~/data/types";
import { formatTime } from "~/utils/format";

interface VoiceAgentCallLogPanelProps {
  voiceAgent: VoiceAgent;
}

const STATUS_COLOR: Record<VoiceCall["status"], "default" | "success" | "error" | "warning" | "info"> = {
  queued: "default",
  ringing: "info",
  in_progress: "info",
  completed: "success",
  failed: "error",
  no_answer: "warning",
  needs_human_followup: "warning",
};

function statusLabel(status: VoiceCall["status"]) {
  return status
    .split("_")
    .map((w) => w[0].toUpperCase() + w.slice(1))
    .join(" ");
}

export function VoiceAgentCallLogPanel({ voiceAgent }: VoiceAgentCallLogPanelProps) {
  const [calls, setCalls] = useState<VoiceCall[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    apiClient
      .listVoiceCalls({ voiceAgentId: voiceAgent.id })
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
  }, [voiceAgent.id]);

  const subscribedCallIds = useRef<Set<string>>(new Set());

  useEffect(() => {
    const echo = getEcho();

    for (const call of calls) {
      if (subscribedCallIds.current.has(call.id)) continue;
      subscribedCallIds.current.add(call.id);

      echo.private(`voice-call.${call.id}`).listen(
        ".voice-call.status-updated",
        (payload: { voiceCall: VoiceCall }) => {
          setCalls((prev) => prev.map((c) => (c.id === payload.voiceCall.id ? payload.voiceCall : c)));
        },
      );
    }
  }, [calls]);

  useEffect(() => {
    return () => {
      const echo = getEcho();
      subscribedCallIds.current.forEach((id) => echo.leave(`voice-call.${id}`));
      subscribedCallIds.current.clear();
    };
  }, [voiceAgent.id]);

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
        No calls logged yet for this voice agent.
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
                <Chip label={call.medium === "browser_simulation" ? "Simulated" : "Telephony"} size="small" variant="outlined" />
                {call.qualificationOutcome && (
                  <Chip
                    label={call.qualificationOutcome.replace("_", " ")}
                    size="small"
                    color={call.qualificationOutcome === "qualified" ? "success" : "default"}
                    variant="outlined"
                  />
                )}
                {call.needsHumanFollowup && <Chip label="Needs follow-up" size="small" color="warning" />}
              </Stack>
            </Stack>

            <Collapse in={expanded}>
              <Divider sx={{ my: 1.5 }} />
              <Stack spacing={1.5}>
                {call.qualificationSummary && (
                  <Box>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      Summary
                    </Typography>
                    <Typography variant="body2">{call.qualificationSummary}</Typography>
                  </Box>
                )}

                {call.qualificationFields && !Array.isArray(call.qualificationFields) && Object.keys(call.qualificationFields).length > 0 && (
                  <Box>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      Qualification fields
                    </Typography>
                    <Stack spacing={0.25}>
                      {Object.entries(call.qualificationFields).map(([key, value]) => (
                        <Typography key={key} variant="body2">
                          <strong>{key}:</strong> {String(value)}
                        </Typography>
                      ))}
                    </Stack>
                  </Box>
                )}

                {call.recordingUrl && (
                  <Box>
                    <Typography variant="caption" sx={{ color: "text.secondary", display: "block", mb: 0.5 }}>
                      Recording
                    </Typography>
                    <audio controls src={call.recordingUrl} style={{ width: "100%" }} />
                  </Box>
                )}

                {call.transcript.length > 0 && (
                  <Box>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      Transcript
                    </Typography>
                    <Stack spacing={0.75} sx={{ mt: 0.5 }}>
                      {call.transcript.map((turn, idx) => (
                        <Box key={idx}>
                          <Typography variant="caption" sx={{ fontWeight: 700, textTransform: "capitalize" }}>
                            {turn.role}:
                          </Typography>{" "}
                          <Typography variant="body2" component="span">
                            {turn.text}
                          </Typography>
                        </Box>
                      ))}
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
