import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import MenuItem from "@mui/material/MenuItem";
import Button from "@mui/material/Button";
import Switch from "@mui/material/Switch";
import FormControlLabel from "@mui/material/FormControlLabel";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ChatMenuFlow, ChatMenuFlowChannel } from "~/data/types";

interface ChatMenuFlowSettingsPanelProps {
  flow: ChatMenuFlow;
  onUpdated: (flow: ChatMenuFlow) => void;
  onDelete: () => Promise<void>;
}

export function ChatMenuFlowSettingsPanel({ flow, onUpdated, onDelete }: ChatMenuFlowSettingsPanelProps) {
  const [name, setName] = useState(flow.name);
  const [channel, setChannel] = useState<ChatMenuFlowChannel>(flow.channel);
  const [active, setActive] = useState(flow.status === "active");
  const [triggerKeyword, setTriggerKeyword] = useState(flow.triggerKeyword ?? "");
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.updateChatMenuFlow(flow.id, {
        name: name.trim(),
        channel,
        status: active ? "active" : "paused",
        triggerKeyword: triggerKeyword.trim() || null,
      });
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save changes.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!window.confirm(`Delete "${flow.name}"? This cannot be undone.`)) return;
    setDeleting(true);
    setError(null);
    try {
      await onDelete();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete chat menu flow.");
      setDeleting(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <TextField fullWidth label="Flow name" value={name} onChange={(e) => setName(e.target.value)} />

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

      <TextField
        fullWidth
        label="Trigger keyword"
        value={triggerKeyword}
        onChange={(e) => setTriggerKeyword(e.target.value)}
        helperText="When a customer's message matches this word exactly (case-insensitive), this flow starts."
      />

      <FormControlLabel
        control={<Switch checked={active} onChange={(e) => setActive(e.target.checked)} />}
        label={active ? "Active" : "Paused"}
      />

      <Button variant="contained" onClick={handleSave} disabled={saving || !name.trim()}>
        Save changes
      </Button>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">Danger zone</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Deleting this chat menu flow permanently removes it and its configuration.
        </Typography>
        <Button color="error" variant="outlined" onClick={handleDelete} disabled={deleting} sx={{ alignSelf: "flex-start" }}>
          Delete chat menu flow
        </Button>
      </Stack>
    </Stack>
  );
}
