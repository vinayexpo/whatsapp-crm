import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Button from "@mui/material/Button";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import PhoneInTalkRoundedIcon from "@mui/icons-material/PhoneInTalkRounded";
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import type { VoiceAgent } from "~/data/types";
import { VoiceAgentSettingsPanel } from "./voice-agent-settings-panel";
import { VoiceAgentQualificationPanel } from "./voice-agent-qualification-panel";
import { VoiceAgentCallLogPanel } from "./voice-agent-call-log-panel";
import { VoiceAgentTestCallDialog } from "./voice-agent-test-call-dialog";

interface VoiceAgentDetailDrawerProps {
  voiceAgent: VoiceAgent | null;
  onClose: () => void;
  onUpdated: (voiceAgent: VoiceAgent) => void;
  onDelete: () => Promise<void>;
}

export function VoiceAgentDetailDrawer({ voiceAgent, onClose, onUpdated, onDelete }: VoiceAgentDetailDrawerProps) {
  const [tab, setTab] = useState<"settings" | "qualification" | "calls">("settings");
  const [testCallOpen, setTestCallOpen] = useState(false);

  useEffect(() => {
    setTab("settings");
    setTestCallOpen(false);
  }, [voiceAgent?.id]);

  return (
    <Drawer anchor="right" open={Boolean(voiceAgent)} onClose={onClose}>
      {voiceAgent && (
        <Box sx={{ width: { xs: 340, sm: 460 }, height: "100%", display: "flex", flexDirection: "column" }}>
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
                <PhoneInTalkRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
              </Box>
              <Typography variant="h6" sx={{ fontSize: "1.1rem" }}>
                {voiceAgent.name}
              </Typography>
            </Stack>
            <Stack direction="row" sx={{ alignItems: "center", gap: 0.5 }}>
              <Button size="small" startIcon={<CallRoundedIcon />} onClick={() => setTestCallOpen(true)}>
                Test call
              </Button>
              <IconButton onClick={onClose} size="small">
                <CloseRoundedIcon fontSize="small" />
              </IconButton>
            </Stack>
          </Stack>

          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ px: 3, minHeight: 36 }}>
            <Tab value="settings" label="Settings" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="qualification" label="Qualification Criteria" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="calls" label="Call Log" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          <Box sx={{ flex: 1, overflowY: "auto", p: 3 }}>
            {tab === "settings" && (
              <VoiceAgentSettingsPanel voiceAgent={voiceAgent} onUpdated={onUpdated} onDelete={onDelete} />
            )}
            {tab === "qualification" && (
              <VoiceAgentQualificationPanel voiceAgent={voiceAgent} onUpdated={onUpdated} />
            )}
            {tab === "calls" && <VoiceAgentCallLogPanel voiceAgent={voiceAgent} />}
          </Box>

          <VoiceAgentTestCallDialog voiceAgent={voiceAgent} open={testCallOpen} onClose={() => setTestCallOpen(false)} />
        </Box>
      )}
    </Drawer>
  );
}
