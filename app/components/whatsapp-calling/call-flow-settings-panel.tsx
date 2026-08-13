import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Switch from "@mui/material/Switch";
import FormControlLabel from "@mui/material/FormControlLabel";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import { apiClient, ApiError } from "~/utils/api-client";
import type { WhatsappCallFlow } from "~/data/types";

interface CallFlowSettingsPanelProps {
  callFlow: WhatsappCallFlow;
  onUpdated: (callFlow: WhatsappCallFlow) => void;
  onDelete: () => Promise<void>;
}

export function CallFlowSettingsPanel({ callFlow, onUpdated, onDelete }: CallFlowSettingsPanelProps) {
  const [name, setName] = useState(callFlow.name);
  const [active, setActive] = useState(callFlow.status === "active");
  const [greetingMessage, setGreetingMessage] = useState(callFlow.greetingMessage);
  const [fallbackMessage, setFallbackMessage] = useState(callFlow.fallbackMessage ?? "");
  const [maxRetries, setMaxRetries] = useState(String(callFlow.maxRetries));
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.updateWhatsappCallFlow(callFlow.id, {
        name: name.trim(),
        status: active ? "active" : "paused",
        greetingMessage: greetingMessage.trim(),
        fallbackMessage: fallbackMessage.trim() || null,
        maxRetries: Math.min(10, Math.max(0, Number(maxRetries) || 0)),
      });
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save changes.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!window.confirm(`Delete "${callFlow.name}"? This cannot be undone.`)) return;
    setDeleting(true);
    setError(null);
    try {
      await onDelete();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete call flow.");
      setDeleting(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <TextField fullWidth label="Call flow name" value={name} onChange={(e) => setName(e.target.value)} />

      <FormControlLabel
        control={<Switch checked={active} onChange={(e) => setActive(e.target.checked)} />}
        label={active ? "Active" : "Paused"}
      />

      <TextField
        fullWidth
        multiline
        minRows={2}
        label="Greeting message"
        value={greetingMessage}
        onChange={(e) => setGreetingMessage(e.target.value)}
      />

      <TextField
        fullWidth
        multiline
        minRows={2}
        label="Fallback message"
        value={fallbackMessage}
        onChange={(e) => setFallbackMessage(e.target.value)}
        helperText="Shown when the caller's response isn't understood."
      />

      <TextField
        fullWidth
        type="number"
        label="Max retries"
        value={maxRetries}
        onChange={(e) => setMaxRetries(e.target.value)}
        slotProps={{ htmlInput: { min: 0, max: 10 } }}
      />

      <Button variant="contained" onClick={handleSave} disabled={saving || !name.trim()}>
        Save changes
      </Button>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">Danger zone</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Deleting this call flow permanently removes it and its configuration.
        </Typography>
        <Button color="error" variant="outlined" onClick={handleDelete} disabled={deleting} sx={{ alignSelf: "flex-start" }}>
          Delete call flow
        </Button>
      </Stack>
    </Stack>
  );
}
