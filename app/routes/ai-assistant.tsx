import { useState, type SyntheticEvent } from "react";
import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import SettingsRoundedIcon from "@mui/icons-material/SettingsRounded";
import { useNavigate } from "react-router";
import { AppLayout } from "~/components/app-layout/app-layout";
import { AiChatPanel } from "~/components/ai-assistant/ai-chat-panel";
import { AiCallPanel } from "~/components/ai-assistant/ai-call-panel";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Route } from "./+types/ai-assistant";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Creative Connects — AI Assistant" },
    {
      name: "description",
      content: "Chat with your AI Assistant or start a live AI voice call powered by your connected model.",
    },
  ];
}

type AiAssistantTab = "chat" | "call";

export default function AiAssistant() {
  const { aiAssistantSettings } = useCrmStore();
  const [tab, setTab] = useState<AiAssistantTab>("chat");
  const navigate = useNavigate();

  function handleTabChange(_: SyntheticEvent, value: AiAssistantTab) {
    setTab(value);
  }

  return (
    <AppLayout>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, display: "flex", flexDirection: "column", minHeight: 0 }}>
        <Stack
          direction="row"
          sx={{ mb: 3, alignItems: { xs: "flex-start", sm: "center" }, justifyContent: "space-between", flexWrap: "wrap", gap: 2 }}
        >
          <Box>
            <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
              AI Assistant
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
              Chat with your AI Assistant or start a live voice call powered by your connected model.
            </Typography>
          </Box>
          <Button
            variant="outlined"
            startIcon={<SettingsRoundedIcon />}
            onClick={() => navigate("/settings")}
          >
            Configure API
          </Button>
        </Stack>

        <Paper
          variant="outlined"
          sx={{ borderRadius: 3, overflow: "hidden", flex: 1, display: "flex", flexDirection: "column", minHeight: 0 }}
        >
          <Tabs value={tab} onChange={handleTabChange} sx={{ px: 2, borderBottom: "1px solid", borderColor: "divider" }}>
            <Tab value="chat" label="Chat" />
            <Tab value="call" label="AI Calling" />
          </Tabs>

          <Box sx={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column" }}>
            {tab === "chat" && <AiChatPanel settings={aiAssistantSettings} />}
            {tab === "call" && <AiCallPanel settings={aiAssistantSettings} />}
          </Box>
        </Paper>
      </Box>
    </AppLayout>
  );
}
