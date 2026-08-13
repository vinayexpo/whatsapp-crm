import { useEffect, useState } from "react";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Chip from "@mui/material/Chip";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import Avatar from "@mui/material/Avatar";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import { apiClient } from "~/utils/api-client";
import type { TeamMember, WhatsappCall } from "~/data/types";
import { formatDate, formatTime } from "~/utils/format";

export function WhatsappCallFollowupQueue() {
  const [calls, setCalls] = useState<WhatsappCall[]>([]);
  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [updatingId, setUpdatingId] = useState<string | null>(null);

  function reload() {
    setLoading(true);
    return apiClient
      .listWhatsappCalls({ needsHumanFollowup: true })
      .then(setCalls)
      .catch(() => {
        // followup queue stays empty on failure
      })
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    reload();
    apiClient.listAssignableMembers().then(setTeamMembers).catch(() => {
      // assignable members stays empty on failure
    });
  }, []);

  async function handleAssign(callId: string, userId: string | null) {
    setUpdatingId(callId);
    try {
      const updated = await apiClient.assignWhatsappCallFollowup(callId, userId);
      setCalls((prev) => prev.map((c) => (c.id === callId ? updated : c)));
    } finally {
      setUpdatingId(null);
    }
  }

  async function handleComplete(callId: string) {
    setUpdatingId(callId);
    try {
      await apiClient.completeWhatsappCallFollowup(callId);
      setCalls((prev) => prev.filter((c) => c.id !== callId));
    } finally {
      setUpdatingId(null);
    }
  }

  if (loading) {
    return (
      <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 4 }}>
        Loading follow-up queue…
      </Typography>
    );
  }

  if (calls.length === 0) {
    return (
      <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          No calls currently need human follow-up.
        </Typography>
      </Paper>
    );
  }

  return (
    <Stack spacing={1.5}>
      {calls.map((call) => (
        <Paper key={call.id} variant="outlined" sx={{ borderRadius: 3, p: 2.5 }}>
          <Stack direction={{ xs: "column", sm: "row" }} sx={{ justifyContent: "space-between", gap: 2 }}>
            <Stack spacing={0.5} sx={{ flex: 1, minWidth: 0 }}>
              <Stack direction="row" sx={{ alignItems: "center", gap: 1, flexWrap: "wrap" }}>
                <Typography variant="body2" sx={{ fontWeight: 700 }}>
                  {call.contactId ? `Contact ${call.contactId}` : "Unknown contact"}
                </Typography>
                <Chip label={call.status.replace("_", " ")} size="small" variant="outlined" />
              </Stack>
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                {call.startedAt ? `${formatDate(call.startedAt)} · ${formatTime(call.startedAt)}` : formatDate(call.createdAt)}
              </Typography>
              {Object.keys(call.collectedVariables).length > 0 && (
                <Stack spacing={0.25} sx={{ mt: 0.5 }}>
                  {Object.entries(call.collectedVariables).map(([key, value]) => (
                    <Typography key={key} variant="body2">
                      <strong>{key}:</strong> {String(value)}
                    </Typography>
                  ))}
                </Stack>
              )}
            </Stack>

            <Divider orientation="vertical" flexItem sx={{ display: { xs: "none", sm: "block" } }} />

            <Stack spacing={1} sx={{ minWidth: 220 }}>
              <Select
                size="small"
                displayEmpty
                value={call.humanFollowupAssignedTo?.id ?? ""}
                disabled={updatingId === call.id}
                onChange={(e) => handleAssign(call.id, e.target.value === "" ? null : (e.target.value as string))}
                renderValue={(value) =>
                  value === "" ? (
                    "Unassigned"
                  ) : (
                    <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }}>
                      <Avatar sx={{ width: 20, height: 20 }}>{call.humanFollowupAssignedTo?.name?.[0]}</Avatar>
                      <Typography variant="body2" noWrap sx={{ maxWidth: 130 }}>
                        {call.humanFollowupAssignedTo?.name}
                      </Typography>
                    </Stack>
                  )
                }
              >
                <MenuItem value="">Unassigned</MenuItem>
                {teamMembers.map((member) => (
                  <MenuItem key={member.id} value={member.id}>
                    <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                      <Avatar src={member.avatarUrl} alt={member.name} sx={{ width: 22, height: 22 }} />
                      <Typography variant="body2">{member.name}</Typography>
                    </Stack>
                  </MenuItem>
                ))}
              </Select>

              <Button
                size="small"
                variant="outlined"
                color="success"
                disabled={updatingId === call.id}
                onClick={() => handleComplete(call.id)}
              >
                Mark complete
              </Button>
            </Stack>
          </Stack>
        </Paper>
      ))}
    </Stack>
  );
}
