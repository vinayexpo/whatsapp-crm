import { useEffect, useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Alert from "@mui/material/Alert";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import { ApiError, apiClient } from "~/utils/api-client";
import type { ApiConnection } from "~/data/types";

interface CreateChatbotDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (input: { name: string; welcomeMessage?: string; apiConnectionId?: string | null }) => Promise<void>;
}

export function CreateChatbotDialog({ open, onClose, onCreate }: CreateChatbotDialogProps) {
  const [name, setName] = useState("");
  const [welcomeMessage, setWelcomeMessage] = useState("");
  const [apiConnectionId, setApiConnectionId] = useState<string | null>(null);
  const [whatsappConnections, setWhatsappConnections] = useState<ApiConnection[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    apiClient
      .listApiConnections()
      .then((connections) => {
        setWhatsappConnections(connections.filter((c) => c.channel === "whatsapp" && c.status === "connected"));
      })
      .catch(() => {
        // selector stays empty on failure
      });
  }, [open]);

  function handleClose() {
    setName("");
    setWelcomeMessage("");
    setApiConnectionId(null);
    setError(null);
    onClose();
  }

  async function handleSubmit() {
    if (!name.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await onCreate({
        name: name.trim(),
        welcomeMessage: welcomeMessage.trim() || undefined,
        apiConnectionId: apiConnectionId ?? undefined,
      });
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
          {whatsappConnections.length > 0 && (
            <Stack spacing={0.5}>
              <Select
                size="small"
                value={apiConnectionId ?? ""}
                onChange={(e) => setApiConnectionId(e.target.value === "" ? null : e.target.value)}
                displayEmpty
              >
                <MenuItem value="">
                  <em>All accounts (default)</em>
                </MenuItem>
                {whatsappConnections.map((connection) => (
                  <MenuItem key={connection.id} value={connection.id}>
                    {connection.accountName} ({connection.identifier})
                  </MenuItem>
                ))}
              </Select>
              <Alert severity="info" icon={false} sx={{ py: 0.5 }}>
                Leave empty to use this bot as the default, or pick one WhatsApp account to give it a dedicated chatbot.
              </Alert>
            </Stack>
          )}
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
