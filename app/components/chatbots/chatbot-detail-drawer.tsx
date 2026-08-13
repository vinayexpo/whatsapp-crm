import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import SmartToyRoundedIcon from "@mui/icons-material/SmartToyRounded";
import { apiClient } from "~/utils/api-client";
import type { Chatbot } from "~/data/types";
import { ChatbotSettingsPanel } from "./chatbot-settings-panel";
import { ChatbotTrainingPanel } from "./chatbot-training-panel";
import { ChatbotEmbedPanel } from "./chatbot-embed-panel";

interface ChatbotDetailDrawerProps {
  chatbot: Chatbot | null;
  onClose: () => void;
  onUpdated: (chatbot: Chatbot) => void;
  onDeleted: (chatbotId: string) => void;
}

export function ChatbotDetailDrawer({ chatbot, onClose, onUpdated, onDeleted }: ChatbotDetailDrawerProps) {
  const [tab, setTab] = useState<"settings" | "training" | "embed">("settings");

  useEffect(() => {
    setTab("settings");
  }, [chatbot?.id]);

  async function handleDelete() {
    if (!chatbot) return;
    await apiClient.deleteChatbot(chatbot.id);
    onDeleted(chatbot.id);
  }

  return (
    <Drawer anchor="right" open={Boolean(chatbot)} onClose={onClose}>
      {chatbot && (
        <Box sx={{ width: { xs: 340, sm: 440 }, height: "100%", display: "flex", flexDirection: "column" }}>
          <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", p: 3, pb: 2 }}>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1.25 }}>
              <Box
                sx={{
                  width: 36,
                  height: 36,
                  borderRadius: 2,
                  bgcolor: "rgba(91, 110, 245, 0.12)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <SmartToyRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
              </Box>
              <Typography variant="h6" sx={{ fontSize: "1.1rem" }}>
                {chatbot.name}
              </Typography>
            </Stack>
            <IconButton onClick={onClose} size="small">
              <CloseRoundedIcon fontSize="small" />
            </IconButton>
          </Stack>

          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ px: 3, minHeight: 36 }}>
            <Tab value="settings" label="Settings" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="training" label="Training" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="embed" label="Embed" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          <Box sx={{ flex: 1, overflowY: "auto", p: 3 }}>
            {tab === "settings" && (
              <ChatbotSettingsPanel chatbot={chatbot} onUpdated={onUpdated} onDelete={handleDelete} />
            )}
            {tab === "training" && <ChatbotTrainingPanel chatbot={chatbot} />}
            {tab === "embed" && <ChatbotEmbedPanel chatbot={chatbot} />}
          </Box>
        </Box>
      )}
    </Drawer>
  );
}
