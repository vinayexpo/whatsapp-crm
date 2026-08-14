import { useEffect, useRef } from "react";
import Dialog from "@mui/material/Dialog";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import IconButton from "@mui/material/IconButton";
import Alert from "@mui/material/Alert";
import CallEndRoundedIcon from "@mui/icons-material/CallEndRounded";
import MicRoundedIcon from "@mui/icons-material/MicRounded";
import MicOffRoundedIcon from "@mui/icons-material/MicOffRounded";
import classNames from "classnames";
import type { Contact } from "~/data/types";
import { useWhatsappCallSession } from "~/hooks/use-whatsapp-call-session";
import styles from "./whatsapp-call-panel.module.css";

interface WhatsappCallPanelProps {
  open: boolean;
  contact: Contact;
  conversationId?: string;
  onClose: () => void;
}

const STATUS_LABEL: Record<string, string> = {
  idle: "Ready to call",
  "requesting-mic": "Requesting microphone…",
  ringing: "Ringing…",
  connecting: "Connecting…",
  connected: "Connected",
  ended: "Call ended",
  failed: "Call failed",
};

export function WhatsappCallPanel({ open, contact, conversationId, onClose }: WhatsappCallPanelProps) {
  const { callState, errorMessage, isMuted, startCall, toggleMute, endCall } = useWhatsappCallSession();
  const startedRef = useRef(false);

  useEffect(() => {
    if (open && !startedRef.current) {
      startedRef.current = true;
      void startCall({ contactId: contact.id, conversationId });
    }
    if (!open) {
      startedRef.current = false;
    }
  }, [open, contact.id, conversationId, startCall]);

  async function handleClose() {
    if (callState !== "ended" && callState !== "failed" && callState !== "idle") {
      await endCall();
    }
    onClose();
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <Box className={styles.container}>
        {errorMessage && (
          <Alert severity="error" sx={{ width: "100%" }}>
            {errorMessage}
          </Alert>
        )}

        <Avatar src={contact.avatarUrl} sx={{ width: 72, height: 72 }}>
          {contact.name?.[0]}
        </Avatar>

        <Typography variant="h6">{contact.name}</Typography>

        <Box
          className={classNames(styles.orb, {
            [styles.orbConnecting]: ["requesting-mic", "ringing", "connecting"].includes(callState),
            [styles.orbConnected]: callState === "connected",
          })}
        >
          <MicRoundedIcon sx={{ fontSize: 44, color: "primary.main" }} />
        </Box>

        <Typography variant="body2" sx={{ fontWeight: 600, color: "text.secondary" }}>
          {STATUS_LABEL[callState] ?? callState}
        </Typography>

        <Stack direction="row" spacing={2}>
          {callState === "connected" && (
            <IconButton
              size="large"
              onClick={toggleMute}
              sx={{ border: "1px solid", borderColor: "divider" }}
              aria-label={isMuted ? "Unmute" : "Mute"}
            >
              {isMuted ? <MicOffRoundedIcon /> : <MicRoundedIcon />}
            </IconButton>
          )}
          {callState !== "ended" && callState !== "failed" && (
            <IconButton
              size="large"
              color="error"
              onClick={handleClose}
              sx={{ bgcolor: "error.main", color: "error.contrastText", "&:hover": { bgcolor: "error.dark" } }}
              aria-label="End call"
            >
              <CallEndRoundedIcon />
            </IconButton>
          )}
        </Stack>

        {(callState === "ended" || callState === "failed") && (
          <Typography
            variant="body2"
            sx={{ color: "primary.main", cursor: "pointer", fontWeight: 600 }}
            onClick={onClose}
          >
            Close
          </Typography>
        )}
      </Box>
    </Dialog>
  );
}
