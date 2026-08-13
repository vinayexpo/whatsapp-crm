import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Chip from "@mui/material/Chip";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import PhoneInTalkRoundedIcon from "@mui/icons-material/PhoneInTalkRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CreateVoiceAgentDialog } from "~/components/voice-agents/create-voice-agent-dialog";
import { VoiceAgentDetailDrawer } from "~/components/voice-agents/voice-agent-detail-drawer";
import { VoiceAgentFollowupQueue } from "~/components/voice-agents/voice-agent-followup-queue";
import { apiClient } from "~/utils/api-client";
import type { VoiceAgent } from "~/data/types";
import type { Route } from "./+types/voice-agents";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Voice Agents — Creative Connects" },
    { name: "description", content: "Configure AI voice agents that call and qualify leads." },
  ];
}

export default function VoiceAgents() {
  const [tab, setTab] = useState<"agents" | "followups">("agents");
  const [voiceAgents, setVoiceAgents] = useState<VoiceAgent[]>([]);
  const [selectedVoiceAgentId, setSelectedVoiceAgentId] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);

  useEffect(() => {
    apiClient.listVoiceAgents().then(setVoiceAgents).catch(() => {
      // voice agents list stays empty on failure
    });
  }, []);

  const selectedVoiceAgent = voiceAgents.find((v) => v.id === selectedVoiceAgentId) ?? null;

  async function handleCreate(input: { name: string; systemPrompt?: string }) {
    const created = await apiClient.createVoiceAgent(input);
    setVoiceAgents((prev) => [created, ...prev]);
    setSelectedVoiceAgentId(created.id);
  }

  function handleUpdated(updated: VoiceAgent) {
    setVoiceAgents((prev) => prev.map((v) => (v.id === updated.id ? updated : v)));
  }

  async function handleDelete() {
    if (!selectedVoiceAgent) return;
    await apiClient.deleteVoiceAgent(selectedVoiceAgent.id);
    setVoiceAgents((prev) => prev.filter((v) => v.id !== selectedVoiceAgent.id));
    setSelectedVoiceAgentId(null);
  }

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager"]}>
        <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0 }}>
          <Stack
            direction="row"
            sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}
          >
            <Stack>
              <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
                Voice Agents
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Configure AI voice agents that call, qualify, and follow up with leads.
              </Typography>
            </Stack>
            {tab === "agents" && (
              <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
                New Voice Agent
              </Button>
            )}
          </Stack>

          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 3, minHeight: 36 }}>
            <Tab value="agents" label="Agents" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="followups" label="Needs Follow-up" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          {tab === "agents" && (
            <Grid container spacing={2}>
              {voiceAgents.map((voiceAgent) => (
                <Grid key={voiceAgent.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                  <Paper
                    variant="outlined"
                    sx={{
                      borderRadius: 3,
                      p: 2.5,
                      cursor: "pointer",
                      transition: "border-color 0.15s",
                      "&:hover": { borderColor: "primary.main" },
                    }}
                    onClick={() => setSelectedVoiceAgentId(voiceAgent.id)}
                  >
                    <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between" }}>
                      <Box
                        sx={{
                          width: 40,
                          height: 40,
                          borderRadius: 2,
                          bgcolor: "rgba(91, 110, 245, 0.12)",
                          display: "flex",
                          alignItems: "center",
                          justifyContent: "center",
                        }}
                      >
                        <PhoneInTalkRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
                      </Box>
                      <Chip
                        label={voiceAgent.status === "active" ? "Active" : "Paused"}
                        size="small"
                        color={voiceAgent.status === "active" ? "success" : "default"}
                        variant={voiceAgent.status === "active" ? "filled" : "outlined"}
                      />
                    </Stack>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mt: 1.5 }}>
                      {voiceAgent.name}
                    </Typography>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      {voiceAgent.voiceMode === "tts" ? "Text-to-speech" : "Recorded audio"} · Max{" "}
                      {voiceAgent.maxCallAttempts} attempts
                    </Typography>
                  </Paper>
                </Grid>
              ))}
              {voiceAgents.length === 0 && (
                <Grid size={12}>
                  <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                    <Typography variant="body2" sx={{ color: "text.secondary" }}>
                      No voice agents yet. Create one to start calling and qualifying leads.
                    </Typography>
                  </Paper>
                </Grid>
              )}
            </Grid>
          )}

          {tab === "followups" && <VoiceAgentFollowupQueue />}
        </Box>

        <CreateVoiceAgentDialog open={createOpen} onClose={() => setCreateOpen(false)} onCreate={handleCreate} />

        <VoiceAgentDetailDrawer
          voiceAgent={selectedVoiceAgent}
          onClose={() => setSelectedVoiceAgentId(null)}
          onUpdated={handleUpdated}
          onDelete={handleDelete}
        />
      </RoleGuard>
    </AppLayout>
  );
}
