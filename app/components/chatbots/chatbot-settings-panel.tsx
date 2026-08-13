import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Switch from "@mui/material/Switch";
import FormControlLabel from "@mui/material/FormControlLabel";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Chatbot, ChatbotChannel } from "~/data/types";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";

interface ChatbotSettingsPanelProps {
  chatbot: Chatbot;
  onUpdated: (chatbot: Chatbot) => void;
  onDelete: () => Promise<void>;
}

export function ChatbotSettingsPanel({ chatbot, onUpdated, onDelete }: ChatbotSettingsPanelProps) {
  const [name, setName] = useState(chatbot.name);
  const [welcomeMessage, setWelcomeMessage] = useState(chatbot.welcomeMessage ?? "");
  const [active, setActive] = useState(chatbot.status === "active");
  const [generalFallbackEnabled, setGeneralFallbackEnabled] = useState(chatbot.generalFallbackEnabled);
  const [channels, setChannels] = useState<ChatbotChannel[]>(chatbot.channels ?? ["website"]);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function handleChannelsChange(_event: unknown, next: ChatbotChannel[]) {
    if (next.length > 0) {
      setChannels(next);
    }
  }

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.updateChatbot(chatbot.id, {
        name: name.trim(),
        welcomeMessage: welcomeMessage.trim() || null,
        status: active ? "active" : "inactive",
        generalFallbackEnabled,
        channels,
      });
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save changes.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!window.confirm(`Delete "${chatbot.name}"? This cannot be undone.`)) return;
    setDeleting(true);
    setError(null);
    try {
      await onDelete();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete chatbot.");
      setDeleting(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <TextField fullWidth label="Chatbot name" value={name} onChange={(e) => setName(e.target.value)} />

      <TextField
        fullWidth
        multiline
        minRows={2}
        label="Welcome message"
        value={welcomeMessage}
        onChange={(e) => setWelcomeMessage(e.target.value)}
      />

      <FormControlLabel
        control={<Switch checked={active} onChange={(e) => setActive(e.target.checked)} />}
        label={active ? "Active" : "Inactive"}
      />

      <Stack spacing={0.75}>
        <Typography variant="subtitle2">Channels</Typography>
        <ToggleButtonGroup value={channels} onChange={handleChannelsChange} size="small" sx={{ alignSelf: "flex-start" }}>
          <ToggleButton value="website">
            <ChannelIcon channel="website" size={16} />
            <Typography component="span" variant="body2" sx={{ ml: 1 }}>
              Website widget
            </Typography>
          </ToggleButton>
          <ToggleButton value="instagram">
            <ChannelIcon channel="instagram" size={16} />
            <Typography component="span" variant="body2" sx={{ ml: 1 }}>
              Instagram
            </Typography>
          </ToggleButton>
          <ToggleButton value="whatsapp">
            <ChannelIcon channel="whatsapp" size={16} />
            <Typography component="span" variant="body2" sx={{ ml: 1 }}>
              WhatsApp
            </Typography>
          </ToggleButton>
        </ToggleButtonGroup>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          {channels.includes("instagram") || channels.includes("whatsapp")
            ? "This bot will also auto-reply to inbound messages on the selected channels using the same training data."
            : "Enable Instagram or WhatsApp to let this bot auto-reply to inbound messages on those channels."}
        </Typography>
      </Stack>

      <Stack spacing={0.5}>
        <FormControlLabel
          control={
            <Switch
              checked={generalFallbackEnabled}
              onChange={(e) => setGeneralFallbackEnabled(e.target.checked)}
            />
          }
          label="Allow general AI answers"
        />
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          {generalFallbackEnabled
            ? "The bot may answer questions outside the trained Q&A using its own knowledge."
            : "The bot will only answer from trained Q&A and decline questions it can't ground in that data."}
        </Typography>
      </Stack>

      <Button variant="contained" onClick={handleSave} disabled={saving || !name.trim()}>
        Save changes
      </Button>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">Danger zone</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Deleting this chatbot permanently removes it and disables its widget key.
        </Typography>
        <Button color="error" variant="outlined" onClick={handleDelete} disabled={deleting} sx={{ alignSelf: "flex-start" }}>
          Delete chatbot
        </Button>
      </Stack>
    </Stack>
  );
}
