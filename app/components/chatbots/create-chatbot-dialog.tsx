import { useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Alert from "@mui/material/Alert";
import { ApiError } from "~/utils/api-client";

interface CreateChatbotDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (input: { name: string; welcomeMessage?: string }) => Promise<void>;
}

export function CreateChatbotDialog({ open, onClose, onCreate }: CreateChatbotDialogProps) {
  const [name, setName] = useState("");
  const [welcomeMessage, setWelcomeMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function handleClose() {
    setName("");
    setWelcomeMessage("");
    setError(null);
    onClose();
  }

  async function handleSubmit() {
    if (!name.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await onCreate({ name: name.trim(), welcomeMessage: welcomeMessage.trim() || undefined });
      handleClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create chatbot.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <DialogTitle>New Chatbot</DialogTitle>
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
            label="Chatbot name"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
          <TextField
            fullWidth
            multiline
            minRows={2}
            label="Welcome message (optional)"
            value={welcomeMessage}
            onChange={(e) => setWelcomeMessage(e.target.value)}
          />
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
