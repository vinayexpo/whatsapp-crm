import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import AccountTreeRoundedIcon from "@mui/icons-material/AccountTreeRounded";
import type { ChatMenuFlow } from "~/data/types";
import { ChatMenuFlowSettingsPanel } from "./chat-menu-flow-settings-panel";
import { ChatMenuFlowBuilder } from "./chat-menu-flow-builder";

interface ChatMenuFlowDetailDrawerProps {
  flow: ChatMenuFlow | null;
  onClose: () => void;
  onUpdated: (flow: ChatMenuFlow) => void;
  onDelete: () => Promise<void>;
}

export function ChatMenuFlowDetailDrawer({ flow, onClose, onUpdated, onDelete }: ChatMenuFlowDetailDrawerProps) {
  const [tab, setTab] = useState<"settings" | "flow">("settings");

  useEffect(() => {
    setTab("settings");
  }, [flow?.id]);

  return (
    <Drawer anchor="right" open={Boolean(flow)} onClose={onClose}>
      {flow && (
        <Box
          sx={{
            width: { xs: "100vw", sm: tab === "flow" ? 880 : 480 },
            maxWidth: "100vw",
            height: "100%",
            display: "flex",
            flexDirection: "column",
            transition: "width 0.15s ease",
          }}
        >
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
                <AccountTreeRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
              </Box>
              <Typography variant="h6" sx={{ fontSize: "1.1rem" }}>
                {flow.name}
              </Typography>
            </Stack>
            <IconButton onClick={onClose} size="small">
              <CloseRoundedIcon fontSize="small" />
            </IconButton>
          </Stack>

          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ px: 3, minHeight: 36 }}>
            <Tab value="settings" label="Settings" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="flow" label="Menu Builder" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          <Box sx={{ flex: 1, overflowY: "auto", p: 3 }}>
            {tab === "settings" && (
              <ChatMenuFlowSettingsPanel flow={flow} onUpdated={onUpdated} onDelete={onDelete} />
            )}
            {tab === "flow" && <ChatMenuFlowBuilder flow={flow} onUpdated={onUpdated} />}
          </Box>
        </Box>
      )}
    </Drawer>
  );
}
