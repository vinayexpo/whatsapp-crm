import { useEffect, useRef, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Chip from "@mui/material/Chip";
import Collapse from "@mui/material/Collapse";
import Divider from "@mui/material/Divider";
import TextField from "@mui/material/TextField";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient } from "~/utils/api-client";
import { getEcho } from "~/utils/echo-client";
import type { WhatsappCall, WhatsappCallStatus } from "~/data/types";
import { formatTime } from "~/utils/format";

const STATUS_COLOR: Record<WhatsappCallStatus, "default" | "success" | "error" | "warning" | "info"> = {
  ringing: "info",
  in_progress: "info",
  completed: "success",
  failed: "error",
  missed: "warning",
  needs_human_followup: "warning",
};

function statusLabel(status: WhatsappCallStatus) {
  return status
    .split("_")
    .map((w) => w[0].toUpperCase() + w.slice(1))
    .join(" ");
}

export function WhatsappCallHistoryPanel() {
  const [calls, setCalls] = useState<WhatsappCall[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<WhatsappCallStatus | "all">("all");

  useEffect(() => {
    setPage(1);
  }, [statusFilter]);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    apiClient
      .listWhatsappCalls({ page, status: statusFilter === "all" ? undefined : statusFilter })
      .then(({ data, meta }) => {
        if (!cancelled) {
          setCalls(data);
          setLastPage(meta.lastPage);
        }
      })
      .catch(() => {
        // call history stays empty on failure
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [page, statusFilter]);

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
  }, []);

  return (
    <Stack spacing={2}>
      <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5}>
        <TextField
          select
          size="small"
          label="Status"
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value as WhatsappCallStatus | "all")}
          sx={{ minWidth: 180 }}
        >
          <MenuItem value="all">All statuses</MenuItem>
          <MenuItem value="ringing">Ringing</MenuItem>
          <MenuItem value="in_progress">In progress</MenuItem>
          <MenuItem value="completed">Completed</MenuItem>
          <MenuItem value="missed">Missed</MenuItem>
          <MenuItem value="failed">Failed</MenuItem>
          <MenuItem value="needs_human_followup">Needs follow-up</MenuItem>
        </TextField>
      </Stack>

      {loading && (
        <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 2 }}>
          Loading call history…
        </Typography>
      )}

      {!loading && calls.length === 0 && (
        <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 2 }}>
          No calls yet.
        </Typography>
      )}

      {!loading &&
        calls.map((call) => {
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
                    {call.contactName ?? "Unknown contact"}
                  </Typography>
                  <Chip label={statusLabel(call.status)} size="small" color={STATUS_COLOR[call.status]} />
                </Stack>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  {call.startedAt ? formatTime(call.startedAt) : formatTime(call.createdAt)}
                  {call.callFlowName ? ` · ${call.callFlowName}` : ""}
                </Typography>
                <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap" }}>
                  <Chip label={call.direction} size="small" variant="outlined" />
                  {call.answeredBy && (
                    <Chip
                      label={call.answeredBy === "ai_sidecar" ? "AI voice" : "Human agent"}
                      size="small"
                      variant="outlined"
                    />
                  )}
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

                  {call.collectedVariables && Object.keys(call.collectedVariables).length === 0 && call.transcript.length === 0 && (
                    <Typography variant="body2" sx={{ color: "text.secondary" }}>
                      No transcript recorded for this call.
                    </Typography>
                  )}
                </Stack>
              </Collapse>
            </Paper>
          );
        })}

      <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
    </Stack>
  );
}
