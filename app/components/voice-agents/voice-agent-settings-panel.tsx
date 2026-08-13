import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Switch from "@mui/material/Switch";
import FormControlLabel from "@mui/material/FormControlLabel";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import MenuItem from "@mui/material/MenuItem";
import { apiClient, ApiError } from "~/utils/api-client";
import type { VoiceAgent, VoiceAgentVoiceMode } from "~/data/types";

interface VoiceAgentSettingsPanelProps {
  voiceAgent: VoiceAgent;
  onUpdated: (voiceAgent: VoiceAgent) => void;
  onDelete: () => Promise<void>;
}

export function VoiceAgentSettingsPanel({ voiceAgent, onUpdated, onDelete }: VoiceAgentSettingsPanelProps) {
  const [name, setName] = useState(voiceAgent.name);
  const [active, setActive] = useState(voiceAgent.status === "active");
  const [voiceMode, setVoiceMode] = useState<VoiceAgentVoiceMode>(voiceAgent.voiceMode);
  const [ttsVoiceId, setTtsVoiceId] = useState(voiceAgent.ttsVoiceId ?? "");
  const [greetingAudioUrl, setGreetingAudioUrl] = useState(voiceAgent.greetingAudioUrl ?? "");
  const [maxCallAttempts, setMaxCallAttempts] = useState(String(voiceAgent.maxCallAttempts));
  const [systemPrompt, setSystemPrompt] = useState(voiceAgent.systemPrompt ?? "");
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.updateVoiceAgent(voiceAgent.id, {
        name: name.trim(),
        status: active ? "active" : "paused",
        voiceMode,
        ttsVoiceId: ttsVoiceId.trim() || null,
        greetingAudioUrl: greetingAudioUrl.trim() || null,
        maxCallAttempts: Math.min(10, Math.max(1, Number(maxCallAttempts) || 1)),
        systemPrompt: systemPrompt.trim() || null,
      });
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save changes.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!window.confirm(`Delete "${voiceAgent.name}"? This cannot be undone.`)) return;
    setDeleting(true);
    setError(null);
    try {
      await onDelete();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete voice agent.");
      setDeleting(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <TextField fullWidth label="Voice agent name" value={name} onChange={(e) => setName(e.target.value)} />

      <FormControlLabel
        control={<Switch checked={active} onChange={(e) => setActive(e.target.checked)} />}
        label={active ? "Active" : "Paused"}
      />

      <TextField
        fullWidth
        select
        label="Voice mode"
        value={voiceMode}
        onChange={(e) => setVoiceMode(e.target.value as VoiceAgentVoiceMode)}
      >
        <MenuItem value="tts">Text-to-speech</MenuItem>
        <MenuItem value="recorded">Recorded audio</MenuItem>
      </TextField>

      {voiceMode === "tts" ? (
        <TextField
          fullWidth
          label="TTS voice ID"
          value={ttsVoiceId}
          onChange={(e) => setTtsVoiceId(e.target.value)}
          helperText="Provider-specific voice identifier used for speech synthesis."
        />
      ) : (
        <TextField
          fullWidth
          label="Greeting audio URL"
          value={greetingAudioUrl}
          onChange={(e) => setGreetingAudioUrl(e.target.value)}
          helperText="URL of the pre-recorded greeting audio file."
        />
      )}

      <TextField
        fullWidth
        type="number"
        label="Max call attempts"
        value={maxCallAttempts}
        onChange={(e) => setMaxCallAttempts(e.target.value)}
        slotProps={{ htmlInput: { min: 1, max: 10 } }}
      />

      <TextField
        fullWidth
        multiline
        minRows={3}
        label="System prompt"
        value={systemPrompt}
        onChange={(e) => setSystemPrompt(e.target.value)}
      />

      <Button variant="contained" onClick={handleSave} disabled={saving || !name.trim()}>
        Save changes
      </Button>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">Danger zone</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Deleting this voice agent permanently removes it and its configuration.
        </Typography>
        <Button color="error" variant="outlined" onClick={handleDelete} disabled={deleting} sx={{ alignSelf: "flex-start" }}>
          Delete voice agent
        </Button>
      </Stack>
    </Stack>
  );
}
