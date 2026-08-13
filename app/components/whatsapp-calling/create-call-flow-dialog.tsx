import { useEffect, useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import MenuItem from "@mui/material/MenuItem";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Alert from "@mui/material/Alert";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ApiConnection } from "~/data/types";

interface CreateCallFlowDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (input: { apiConnectionId: string; name: string; greetingMessage: string }) => Promise<void>;
}

export function CreateCallFlowDialog({ open, onClose, onCreate }: CreateCallFlowDialogProps) {
  const [connections, setConnections] = useState<ApiConnection[]>([]);
  const [apiConnectionId, setApiConnectionId] = useState("");
  const [name, setName] = useState("");
  const [greetingMessage, setGreetingMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    apiClient
      .listApiConnections()
      .then((list) => list.filter((c) => c.channel === "whatsapp" && c.callingEnabled))
      .then(setConnections)
      .catch(() => {
        // connection list stays empty on failure
      });
  }, [open]);

  function handleClose() {
    setApiConnectionId("");
    setName("");
    setGreetingMessage("");
    setError(null);
    onClose();
  }

  async function handleSubmit() {
    if (!name.trim() || !apiConnectionId || !greetingMessage.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await onCreate({ apiConnectionId, name: name.trim(), greetingMessage: greetingMessage.trim() });
      handleClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create call flow.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <DialogTitle>New Call Flow</DialogTitle>
      <DialogContent>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        <Stack spacing={2} sx={{ mt: 1 }}>
          <TextField
            fullWidth
            select
            label="WhatsApp connection"
            value={apiConnectionId}
            onChange={(e) => setApiConnectionId(e.target.value)}
            helperText={connections.length === 0 ? "No WhatsApp connections have calling enabled yet." : undefined}
          >
            {connections.map((connection) => (
              <MenuItem key={connection.id} value={connection.id}>
                {connection.label}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            autoFocus
            fullWidth
            label="Call flow name"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
          <TextField
            fullWidth
            multiline
            minRows={2}
            label="Greeting message"
            value={greetingMessage}
            onChange={(e) => setGreetingMessage(e.target.value)}
          />
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={handleClose}>Cancel</Button>
        <Button
          variant="contained"
          onClick={handleSubmit}
          disabled={submitting || !name.trim() || !apiConnectionId || !greetingMessage.trim()}
        >
          Create
        </Button>
      </DialogActions>
    </Dialog>
  );
}
