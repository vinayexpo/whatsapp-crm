import { useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import MenuItem from "@mui/material/MenuItem";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Alert from "@mui/material/Alert";
import { ApiError } from "~/utils/api-client";
import type { ChatMenuFlowChannel } from "~/data/types";

interface CreateChatMenuFlowDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (input: { name: string; channel: ChatMenuFlowChannel }) => Promise<void>;
}

export function CreateChatMenuFlowDialog({ open, onClose, onCreate }: CreateChatMenuFlowDialogProps) {
  const [name, setName] = useState("");
  const [channel, setChannel] = useState<ChatMenuFlowChannel>("both");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function handleClose() {
    setName("");
    setChannel("both");
    setError(null);
    onClose();
  }

  async function handleSubmit() {
    if (!name.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await onCreate({ name: name.trim(), channel });
      handleClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create chat menu flow.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <DialogTitle>New Chat Menu Flow</DialogTitle>
      <DialogContent>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        <Stack spacing={2} sx={{ mt: 1 }}>
          <TextField
            autoFocus
            fullWidth
            label="Flow name"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
          <TextField
            fullWidth
            select
            label="Channel"
            value={channel}
            onChange={(e) => setChannel(e.target.value as ChatMenuFlowChannel)}
          >
            <MenuItem value="both">WhatsApp + Web widget</MenuItem>
            <MenuItem value="whatsapp">WhatsApp only</MenuItem>
            <MenuItem value="web">Web widget only</MenuItem>
          </TextField>
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={handleClose}>Cancel</Button>
        <Button variant="contained" onClick={handleSubmit} disabled={submitting || !name.trim()}>
          Create
        </Button>
      </DialogActions>
    </Dialog>
  );
}
