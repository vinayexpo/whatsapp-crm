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
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CreateCallFlowDialog } from "~/components/whatsapp-calling/create-call-flow-dialog";
import { CallFlowDetailDrawer } from "~/components/whatsapp-calling/call-flow-detail-drawer";
import { WhatsappCallFollowupQueue } from "~/components/whatsapp-calling/whatsapp-call-followup-queue";
import { CallSetupPanel } from "~/components/whatsapp-calling/call-setup-panel";
import { apiClient } from "~/utils/api-client";
import type { WhatsappCallFlow } from "~/data/types";
import type { Route } from "./+types/whatsapp-calling";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "WhatsApp Calling — Creative Connects" },
    { name: "description", content: "Build AI-guided WhatsApp call flows and review call logs." },
  ];
}

export default function WhatsappCalling() {
  const [tab, setTab] = useState<"flows" | "followups" | "setup">("flows");
  const [callFlows, setCallFlows] = useState<WhatsappCallFlow[]>([]);
  const [selectedCallFlowId, setSelectedCallFlowId] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);

  useEffect(() => {
    apiClient.listWhatsappCallFlows().then(setCallFlows).catch(() => {
      // call flows list stays empty on failure
    });
  }, []);

  const selectedCallFlow = callFlows.find((f) => f.id === selectedCallFlowId) ?? null;

  async function handleCreate(input: { apiConnectionId: string; name: string; greetingMessage: string }) {
    const created = await apiClient.createWhatsappCallFlow({
      ...input,
      nodes: [{ id: "end", type: "end_call", prompt: "Thanks for calling, goodbye!" }],
    });
    setCallFlows((prev) => [created, ...prev]);
    setSelectedCallFlowId(created.id);
  }

  function handleUpdated(updated: WhatsappCallFlow) {
    setCallFlows((prev) => prev.map((f) => (f.id === updated.id ? updated : f)));
  }

  async function handleDelete() {
    if (!selectedCallFlow) return;
    await apiClient.deleteWhatsappCallFlow(selectedCallFlow.id);
    setCallFlows((prev) => prev.filter((f) => f.id !== selectedCallFlow.id));
    setSelectedCallFlowId(null);
  }

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager"]}>
        <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0, overflowY: "auto" }}>
          <Stack
            direction="row"
            sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}
          >
            <Stack>
              <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
                WhatsApp Calling
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Build AI-guided call flows over Meta's WhatsApp Business Calling API and review call logs.
              </Typography>
            </Stack>
            {tab === "flows" && (
              <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
                New Call Flow
              </Button>
            )}
          </Stack>

          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 3, minHeight: 36 }}>
            <Tab value="flows" label="Call Flows" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="followups" label="Needs Follow-up" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="setup" label="Setup" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          {tab === "flows" && (
            <Grid container spacing={2}>
              {callFlows.map((callFlow) => (
                <Grid key={callFlow.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                  <Paper
                    variant="outlined"
                    sx={{
                      borderRadius: 3,
                      p: 2.5,
                      cursor: "pointer",
                      transition: "border-color 0.15s",
                      "&:hover": { borderColor: "primary.main" },
                    }}
                    onClick={() => setSelectedCallFlowId(callFlow.id)}
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
                        <CallRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
                      </Box>
                      <Chip
                        label={callFlow.status === "active" ? "Active" : "Paused"}
                        size="small"
                        color={callFlow.status === "active" ? "success" : "default"}
                        variant={callFlow.status === "active" ? "filled" : "outlined"}
                      />
                    </Stack>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mt: 1.5 }}>
                      {callFlow.name}
                    </Typography>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      {callFlow.nodes.length} step{callFlow.nodes.length === 1 ? "" : "s"} · Max {callFlow.maxRetries} retries
                    </Typography>
                  </Paper>
                </Grid>
              ))}
              {callFlows.length === 0 && (
                <Grid size={12}>
                  <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                    <Typography variant="body2" sx={{ color: "text.secondary" }}>
                      No call flows yet. Enable calling on a WhatsApp connection in Setup, then create a flow.
                    </Typography>
                  </Paper>
                </Grid>
              )}
            </Grid>
          )}

          {tab === "followups" && <WhatsappCallFollowupQueue />}

          {tab === "setup" && <CallSetupPanel />}
        </Box>

        <CreateCallFlowDialog open={createOpen} onClose={() => setCreateOpen(false)} onCreate={handleCreate} />

        <CallFlowDetailDrawer
          callFlow={selectedCallFlow}
          onClose={() => setSelectedCallFlowId(null)}
          onUpdated={handleUpdated}
          onDelete={handleDelete}
        />
      </RoleGuard>
    </AppLayout>
  );
}
