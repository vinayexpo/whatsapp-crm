import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import IconButton from "@mui/material/IconButton";
import Stack from "@mui/material/Stack";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import { useCrmStore } from "~/hooks/use-crm-store";
import { AiCallPanel } from "~/components/ai-assistant/ai-call-panel";
import type { VoiceAgent } from "~/data/types";

interface VoiceAgentTestCallDialogProps {
  voiceAgent: VoiceAgent | null;
  open: boolean;
  onClose: () => void;
}

/**
 * Standalone browser-based test call using the voice agent's own system prompt and
 * qualification questions. Not linked to a CRM contact, so nothing is persisted — this is a
 * quick way to hear/verify how the agent behaves before wiring it to real telephony.
 */
export function VoiceAgentTestCallDialog({ voiceAgent, open, onClose }: VoiceAgentTestCallDialogProps) {
  const { aiAssistantSettings } = useCrmStore();

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>
        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
          <span>Test call — {voiceAgent?.name}</span>
          <IconButton size="small" onClick={onClose}>
            <CloseRoundedIcon fontSize="small" />
          </IconButton>
        </Stack>
      </DialogTitle>
      <DialogContent>
        {voiceAgent && <AiCallPanel settings={aiAssistantSettings} voiceAgent={voiceAgent} />}
      </DialogContent>
    </Dialog>
  );
}
