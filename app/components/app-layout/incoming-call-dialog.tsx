import { useEffect, useRef } from "react";
import Dialog from "@mui/material/Dialog";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import IconButton from "@mui/material/IconButton";
import Alert from "@mui/material/Alert";
import CallEndRoundedIcon from "@mui/icons-material/CallEndRounded";
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import MicRoundedIcon from "@mui/icons-material/MicRounded";
import MicOffRoundedIcon from "@mui/icons-material/MicOffRounded";
import classNames from "classnames";
import type { WhatsappCall } from "~/data/types";
import { useIncomingWhatsappCall } from "~/hooks/use-incoming-whatsapp-call";
import styles from "~/components/inbox/whatsapp-call-panel.module.css";

interface IncomingCallDialogProps {
  call: WhatsappCall;
  onDismiss: () => void;
}

const STATUS_LABEL: Record<string, string> = {
  ringing: "Incoming call",
  "requesting-mic": "Requesting microphone…",
  connecting: "Connecting…",
  connected: "Connected",
  ended: "Call ended",
  failed: "Call failed",
  rejected: "Call declined",
};

export function IncomingCallDialog({ call, onDismiss }: IncomingCallDialogProps) {
  const { callState, errorMessage, isMuted, acceptCall, rejectCall, toggleMute, endCall, reset } =
    useIncomingWhatsappCall();
  const dismissedRef = useRef(false);

  useEffect(() => {
    dismissedRef.current = false;
    reset();
    // Only re-run when a genuinely new call comes in.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [call.id]);

  useEffect(() => {
    if (["ended", "failed", "rejected"].includes(callState) && !dismissedRef.current) {
      const timer = setTimeout(() => {
        dismissedRef.current = true;
        onDismiss();
      }, 1500);
      return () => clearTimeout(timer);
    }
  }, [callState, onDismiss]);

  async function handleAccept() {
    await acceptCall(call);
  }

  async function handleReject() {
    await rejectCall(call);
  }

  async function handleEnd() {
    await endCall();
  }

  return (
    <Dialog open maxWidth="xs" fullWidth>
      <Box className={styles.container}>
        {errorMessage && (
          <Alert severity="error" sx={{ width: "100%" }}>
            {errorMessage}
          </Alert>
        )}

        <Avatar sx={{ width: 72, height: 72 }}>{call.contactName?.[0] ?? "?"}</Avatar>

        <Typography variant="h6">{call.contactName ?? "Unknown caller"}</Typography>

        <Box
          className={classNames(styles.orb, {
            [styles.orbConnecting]: ["ringing", "requesting-mic", "connecting"].includes(callState),
            [styles.orbConnected]: callState === "connected",
          })}
        >
          <MicRoundedIcon sx={{ fontSize: 44, color: "primary.main" }} />
        </Box>

        <Typography variant="body2" sx={{ fontWeight: 600, color: "text.secondary" }}>
          {STATUS_LABEL[callState] ?? callState}
        </Typography>

        <Stack direction="row" spacing={2}>
          {callState === "ringing" && (
            <>
              <IconButton
                size="large"
                onClick={handleReject}
                sx={{ bgcolor: "error.main", color: "error.contrastText", "&:hover": { bgcolor: "error.dark" } }}
                aria-label="Decline call"
              >
                <CallEndRoundedIcon />
              </IconButton>
              <IconButton
                size="large"
                onClick={handleAccept}
                sx={{ bgcolor: "success.main", color: "success.contrastText", "&:hover": { bgcolor: "success.dark" } }}
                aria-label="Accept call"
              >
                <CallRoundedIcon />
              </IconButton>
            </>
          )}

          {["requesting-mic", "connecting", "connected"].includes(callState) && (
            <>
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
              <IconButton
                size="large"
                color="error"
                onClick={handleEnd}
                sx={{ bgcolor: "error.main", color: "error.contrastText", "&:hover": { bgcolor: "error.dark" } }}
                aria-label="End call"
              >
                <CallEndRoundedIcon />
              </IconButton>
            </>
          )}
        </Stack>
      </Box>
    </Dialog>
  );
}
