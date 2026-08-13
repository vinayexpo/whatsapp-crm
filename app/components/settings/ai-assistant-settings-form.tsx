import { useEffect, useState } from "react";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import InputAdornment from "@mui/material/InputAdornment";
import IconButton from "@mui/material/IconButton";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import VisibilityRoundedIcon from "@mui/icons-material/VisibilityRounded";
import VisibilityOffRoundedIcon from "@mui/icons-material/VisibilityOffRounded";
import CheckCircleRoundedIcon from "@mui/icons-material/CheckCircleRounded";
import type { AiAssistantSettings } from "~/data/types";

interface AiAssistantSettingsFormProps {
  settings: AiAssistantSettings;
  onChange: (updates: Partial<AiAssistantSettings>) => void;
  readOnly?: boolean;
}

export function AiAssistantSettingsForm({ settings, onChange, readOnly = false }: AiAssistantSettingsFormProps) {
  const [baseUrl, setBaseUrl] = useState(settings.baseUrl);
  const [apiKey, setApiKey] = useState(settings.apiKey ?? "");
  const [model, setModel] = useState(settings.model);
  const [showApiKey, setShowApiKey] = useState(false);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    setBaseUrl(settings.baseUrl);
    setApiKey(settings.apiKey ?? "");
    setModel(settings.model);
  }, [settings.baseUrl, settings.apiKey, settings.model]);

  const isDirty = baseUrl !== settings.baseUrl || apiKey !== (settings.apiKey ?? "") || model !== settings.model;
  const canSave = baseUrl.trim().length > 0 && apiKey.trim().length > 0 && model.trim().length > 0;

  function handleSave() {
    onChange({ baseUrl: baseUrl.trim(), apiKey: apiKey.trim(), model: model.trim() });
    setSaved(true);
    window.setTimeout(() => setSaved(false), 2500);
  }

  return (
    <Stack spacing={2.5} sx={{ maxWidth: 520 }}>
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        Connect any OpenAI-compatible provider to power the AI Assistant's chat replies and voice calls.
      </Typography>

      <TextField
        label="Base API URL"
        value={baseUrl}
        onChange={(e) => setBaseUrl(e.target.value)}
        placeholder="https://api.openai.com/v1"
        fullWidth
        disabled={readOnly}
      />
      <TextField
        label="API Key"
        type={showApiKey ? "text" : "password"}
        value={apiKey}
        onChange={(e) => setApiKey(e.target.value)}
        placeholder="sk-…"
        fullWidth
        disabled={readOnly}
        slotProps={{
          input: {
            endAdornment: (
              <InputAdornment position="end">
                <IconButton size="small" onClick={() => setShowApiKey((prev) => !prev)} edge="end">
                  {showApiKey ? <VisibilityOffRoundedIcon fontSize="small" /> : <VisibilityRoundedIcon fontSize="small" />}
                </IconButton>
              </InputAdornment>
            ),
          },
        }}
      />
      <TextField
        label="Model"
        value={model}
        onChange={(e) => setModel(e.target.value)}
        placeholder="gpt-4o-mini"
        fullWidth
        disabled={readOnly}
      />

      {saved && (
        <Alert severity="success" icon={<CheckCircleRoundedIcon fontSize="small" />}>
          AI Assistant settings saved.
        </Alert>
      )}

      {!readOnly && (
        <Stack direction="row" sx={{ justifyContent: "flex-end" }}>
          <Button variant="contained" onClick={handleSave} disabled={!canSave || !isDirty}>
            Save Settings
          </Button>
        </Stack>
      )}
    </Stack>
  );
}
