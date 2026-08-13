import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Chip from "@mui/material/Chip";
import SmartToyRoundedIcon from "@mui/icons-material/SmartToyRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CreateChatbotDialog } from "~/components/chatbots/create-chatbot-dialog";
import { ChatbotDetailDrawer } from "~/components/chatbots/chatbot-detail-drawer";
import { apiClient } from "~/utils/api-client";
import type { Chatbot } from "~/data/types";
import type { Route } from "./+types/chatbots";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Chatbots — Creative Connects" },
    { name: "description", content: "Train AI chatbots and embed them as widgets on external websites." },
  ];
}

export default function Chatbots() {
  const [chatbots, setChatbots] = useState<Chatbot[]>([]);
  const [selectedChatbotId, setSelectedChatbotId] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);

  useEffect(() => {
    apiClient.listChatbots().then(setChatbots).catch(() => {
      // chatbots list stays empty on failure
    });
  }, []);

  const selectedChatbot = chatbots.find((c) => c.id === selectedChatbotId) ?? null;

  async function handleCreate(input: { name: string; welcomeMessage?: string }) {
    const created = await apiClient.createChatbot(input);
    setChatbots((prev) => [created, ...prev]);
    setSelectedChatbotId(created.id);
  }

  function handleUpdated(updated: Chatbot) {
    setChatbots((prev) => prev.map((c) => (c.id === updated.id ? updated : c)));
  }

  function handleDeleted(chatbotId: string) {
    setChatbots((prev) => prev.filter((c) => c.id !== chatbotId));
    if (selectedChatbotId === chatbotId) setSelectedChatbotId(null);
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
              Chatbots
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
              Train an AI chatbot and embed it as a widget on any external website.
            </Typography>
          </Stack>
          <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
            New Chatbot
          </Button>
        </Stack>

        <Grid container spacing={2}>
          {chatbots.map((chatbot) => (
            <Grid key={chatbot.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
              <Paper
                variant="outlined"
                sx={{
                  borderRadius: 3,
                  p: 2.5,
                  cursor: "pointer",
                  transition: "border-color 0.15s",
                  "&:hover": { borderColor: "primary.main" },
                }}
                onClick={() => setSelectedChatbotId(chatbot.id)}
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
                    <SmartToyRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
                  </Box>
                  <Chip
                    label={chatbot.status === "active" ? "Active" : "Inactive"}
                    size="small"
                    color={chatbot.status === "active" ? "success" : "default"}
                    variant={chatbot.status === "active" ? "filled" : "outlined"}
                  />
                </Stack>
                <Typography variant="subtitle1" sx={{ fontWeight: 700, mt: 1.5 }}>
                  {chatbot.name}
                </Typography>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  Widget key: {chatbot.widgetKey.slice(0, 10)}…
                </Typography>
              </Paper>
            </Grid>
          ))}
          {chatbots.length === 0 && (
            <Grid size={12}>
              <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  No chatbots yet. Create one to start training and embedding it on your website.
                </Typography>
              </Paper>
            </Grid>
          )}
        </Grid>
      </Box>

      <CreateChatbotDialog open={createOpen} onClose={() => setCreateOpen(false)} onCreate={handleCreate} />

      <ChatbotDetailDrawer
        chatbot={selectedChatbot}
        onClose={() => setSelectedChatbotId(null)}
        onUpdated={handleUpdated}
        onDeleted={handleDeleted}
      />
      </RoleGuard>
    </AppLayout>
  );
}
