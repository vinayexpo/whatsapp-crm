import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Chip from "@mui/material/Chip";
import AccountTreeRoundedIcon from "@mui/icons-material/AccountTreeRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CreateChatMenuFlowDialog } from "~/components/chat-flows/create-chat-menu-flow-dialog";
import { ChatMenuFlowDetailDrawer } from "~/components/chat-flows/chat-menu-flow-detail-drawer";
import { apiClient } from "~/utils/api-client";
import type { ChatMenuFlow, ChatMenuFlowChannel, ChatMenuFlowNode } from "~/data/types";
import type { Route } from "./+types/chat-menus";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Chat Menus — Creative Connects" },
    { name: "description", content: "Build button-based service menus for WhatsApp and the web chat widget." },
  ];
}

export default function ChatMenus() {
  const [flows, setFlows] = useState<ChatMenuFlow[]>([]);
  const [selectedFlowId, setSelectedFlowId] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);

  useEffect(() => {
    apiClient.listChatMenuFlows().then(setFlows).catch(() => {
      // flow list stays empty on failure
    });
  }, []);

  const selectedFlow = flows.find((f) => f.id === selectedFlowId) ?? null;

  async function handleCreate(input: {
    name: string;
    channel: ChatMenuFlowChannel;
    entryNodeId?: string;
    nodes?: ChatMenuFlowNode[];
  }) {
    const entryNodeId = input.entryNodeId ?? "root";
    const nodes =
      input.nodes ??
      ([{ id: entryNodeId, type: "content", message: "Welcome! How can we help?", renderAs: "button", buttons: [] }] as ChatMenuFlowNode[]);
    const created = await apiClient.createChatMenuFlow({
      name: input.name,
      channel: input.channel,
      entryNodeId,
      nodes,
    });
    setFlows((prev) => [created, ...prev]);
    setSelectedFlowId(created.id);
  }

  function handleUpdated(updated: ChatMenuFlow) {
    setFlows((prev) => prev.map((f) => (f.id === updated.id ? updated : f)));
  }

  async function handleDelete() {
    if (!selectedFlow) return;
    await apiClient.deleteChatMenuFlow(selectedFlow.id);
    setFlows((prev) => prev.filter((f) => f.id !== selectedFlow.id));
    setSelectedFlowId(null);
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
                Chat Menus
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Build button-based service menus that customers tap through on WhatsApp and the web chat widget.
              </Typography>
            </Stack>
            <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
              New Chat Menu
            </Button>
          </Stack>

          <Grid container spacing={2}>
            {flows.map((flow) => (
              <Grid key={flow.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                <Paper
                  variant="outlined"
                  sx={{
                    borderRadius: 3,
                    p: 2.5,
                    cursor: "pointer",
                    transition: "border-color 0.15s",
                    "&:hover": { borderColor: "primary.main" },
                  }}
                  onClick={() => setSelectedFlowId(flow.id)}
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
                      <AccountTreeRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
                    </Box>
                    <Chip
                      label={flow.status === "active" ? "Active" : "Paused"}
                      size="small"
                      color={flow.status === "active" ? "success" : "default"}
                      variant={flow.status === "active" ? "filled" : "outlined"}
                    />
                  </Stack>
                  <Typography variant="subtitle1" sx={{ fontWeight: 700, mt: 1.5 }}>
                    {flow.name}
                  </Typography>
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    {flow.nodes.length} node{flow.nodes.length === 1 ? "" : "s"} ·{" "}
                    {flow.channel === "both" ? "WhatsApp + Web" : flow.channel === "whatsapp" ? "WhatsApp" : "Web"}
                    {flow.triggerKeyword ? ` · "${flow.triggerKeyword}"` : ""}
                  </Typography>
                </Paper>
              </Grid>
            ))}
            {flows.length === 0 && (
              <Grid size={12}>
                <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    No chat menus yet. Create one to build a button-based service menu.
                  </Typography>
                </Paper>
              </Grid>
            )}
          </Grid>
        </Box>

        <CreateChatMenuFlowDialog open={createOpen} onClose={() => setCreateOpen(false)} onCreate={handleCreate} />

        <ChatMenuFlowDetailDrawer
          flow={selectedFlow}
          onClose={() => setSelectedFlowId(null)}
          onUpdated={handleUpdated}
          onDelete={handleDelete}
        />
      </RoleGuard>
    </AppLayout>
  );
}
