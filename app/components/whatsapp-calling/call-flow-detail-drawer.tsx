import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import type { WhatsappCallFlow } from "~/data/types";
import { CallFlowSettingsPanel } from "./call-flow-settings-panel";
import { CallFlowBuilder } from "./call-flow-builder";
import { WhatsappCallLogPanel } from "./whatsapp-call-log-panel";

interface CallFlowDetailDrawerProps {
  callFlow: WhatsappCallFlow | null;
  onClose: () => void;
  onUpdated: (callFlow: WhatsappCallFlow) => void;
  onDelete: () => Promise<void>;
}

export function CallFlowDetailDrawer({ callFlow, onClose, onUpdated, onDelete }: CallFlowDetailDrawerProps) {
  const [tab, setTab] = useState<"settings" | "flow" | "calls">("settings");

  useEffect(() => {
    setTab("settings");
  }, [callFlow?.id]);

  return (
    <Drawer anchor="right" open={Boolean(callFlow)} onClose={onClose}>
      {callFlow && (
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
                <CallRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
              </Box>
              <Typography variant="h6" sx={{ fontSize: "1.1rem" }}>
                {callFlow.name}
              </Typography>
            </Stack>
            <IconButton onClick={onClose} size="small">
              <CloseRoundedIcon fontSize="small" />
            </IconButton>
          </Stack>

          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ px: 3, minHeight: 36 }}>
            <Tab value="settings" label="Settings" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="flow" label="Call Flow" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="calls" label="Call Log" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          <Box sx={{ flex: 1, overflowY: "auto", p: 3 }}>
            {tab === "settings" && (
              <CallFlowSettingsPanel callFlow={callFlow} onUpdated={onUpdated} onDelete={onDelete} />
            )}
            {tab === "flow" && <CallFlowBuilder callFlow={callFlow} onUpdated={onUpdated} />}
            {tab === "calls" && <WhatsappCallLogPanel callFlow={callFlow} />}
          </Box>
        </Box>
      )}
    </Drawer>
  );
}
