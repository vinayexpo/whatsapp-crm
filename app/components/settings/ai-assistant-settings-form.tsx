import { useCallback, useEffect, useRef, useState } from "react";
import Alert from "@mui/material/Alert";
import Autocomplete from "@mui/material/Autocomplete";
import Button from "@mui/material/Button";
import CircularProgress from "@mui/material/CircularProgress";
import Divider from "@mui/material/Divider";
import InputAdornment from "@mui/material/InputAdornment";
import IconButton from "@mui/material/IconButton";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import VisibilityRoundedIcon from "@mui/icons-material/VisibilityRounded";
import VisibilityOffRoundedIcon from "@mui/icons-material/VisibilityOffRounded";
import CheckCircleRoundedIcon from "@mui/icons-material/CheckCircleRounded";
import type { AiAssistantSettings } from "~/data/types";
import { apiClient } from "~/utils/api-client";

interface AiAssistantSettingsFormProps {
  settings: AiAssistantSettings;
  onChange: (updates: Partial<AiAssistantSettings>) => void;
  readOnly?: boolean;
}

function useModelOptions(baseUrl: string, apiKey: string) {
  const [options, setOptions] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const lastFetchedKey = useRef<string>("");

  const fetchModels = useCallback(async () => {
    const trimmedUrl = baseUrl.trim();
    if (!trimmedUrl) {
      setOptions([]);
      return;
    }
    const key = `${trimmedUrl}::${apiKey.trim()}`;
    if (key === lastFetchedKey.current) {
      return;
    }
    lastFetchedKey.current = key;
    setLoading(true);
    setError(false);
    try {
      const models = await apiClient.listAiAssistantModels({ baseUrl: trimmedUrl, apiKey: apiKey.trim() || null });
      setOptions(models);
    } catch {
      setError(true);
      setOptions([]);
    } finally {
      setLoading(false);
    }
  }, [baseUrl, apiKey]);

  return { options, loading, error, fetchModels };
}

export function AiAssistantSettingsForm({ settings, onChange, readOnly = false }: AiAssistantSettingsFormProps) {
  const [baseUrl, setBaseUrl] = useState(settings.baseUrl);
  const [apiKey, setApiKey] = useState(settings.apiKey ?? "");
  const [model, setModel] = useState(settings.model);
  const [voiceBaseUrl, setVoiceBaseUrl] = useState(settings.voiceBaseUrl ?? "");
  const [voiceApiKey, setVoiceApiKey] = useState(settings.voiceApiKey ?? "");
  const [sttModel, setSttModel] = useState(settings.sttModel);
  const [ttsModel, setTtsModel] = useState(settings.ttsModel);
  const [ttsVoice, setTtsVoice] = useState(settings.ttsVoice);
  const [showApiKey, setShowApiKey] = useState(false);
  const [showVoiceApiKey, setShowVoiceApiKey] = useState(false);
  const [saved, setSaved] = useState(false);

  const chatModels = useModelOptions(baseUrl, apiKey);
  const voiceModels = useModelOptions(voiceBaseUrl, voiceApiKey);

  useEffect(() => {
    setBaseUrl(settings.baseUrl);
    setApiKey(settings.apiKey ?? "");
    setModel(settings.model);
    setVoiceBaseUrl(settings.voiceBaseUrl ?? "");
    setVoiceApiKey(settings.voiceApiKey ?? "");
    setSttModel(settings.sttModel);
    setTtsModel(settings.ttsModel);
    setTtsVoice(settings.ttsVoice);
  }, [
    settings.baseUrl,
    settings.apiKey,
    settings.model,
    settings.voiceBaseUrl,
    settings.voiceApiKey,
    settings.sttModel,
    settings.ttsModel,
    settings.ttsVoice,
  ]);

  // Fetch the model list for whatever base_url/api_key is already saved, so
  // existing settings show their current model as a selectable option
  // instead of only accepting it as free text.
  useEffect(() => {
    chatModels.fetchModels();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    voiceModels.fetchModels();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const isDirty =
    baseUrl !== settings.baseUrl ||
    apiKey !== (settings.apiKey ?? "") ||
    model !== settings.model ||
    voiceBaseUrl !== (settings.voiceBaseUrl ?? "") ||
    voiceApiKey !== (settings.voiceApiKey ?? "") ||
    sttModel !== settings.sttModel ||
    ttsModel !== settings.ttsModel ||
    ttsVoice !== settings.ttsVoice;
  const canSave =
    baseUrl.trim().length > 0 &&
    apiKey.trim().length > 0 &&
    model.trim().length > 0 &&
    sttModel.trim().length > 0 &&
    ttsModel.trim().length > 0 &&
    ttsVoice.trim().length > 0;

  function handleSave() {
    onChange({
      baseUrl: baseUrl.trim(),
      apiKey: apiKey.trim(),
      model: model.trim(),
      voiceBaseUrl: voiceBaseUrl.trim() || null,
      voiceApiKey: voiceApiKey.trim() || null,
      sttModel: sttModel.trim(),
      ttsModel: ttsModel.trim(),
      ttsVoice: ttsVoice.trim(),
    });
    setSaved(true);
    window.setTimeout(() => setSaved(false), 2500);
  }

  return (
    <Stack spacing={2.5} sx={{ maxWidth: 520 }}>
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        Connect any OpenAI-compatible provider to power the AI Assistant's chat replies and voice calls.
      </Typography>

      <Typography variant="subtitle2">Chat</Typography>
      <TextField
        label="Base API URL"
        value={baseUrl}
        onChange={(e) => setBaseUrl(e.target.value)}
        onBlur={() => chatModels.fetchModels()}
        placeholder="https://api.openai.com/v1"
        fullWidth
        disabled={readOnly}
      />
      <TextField
        label="API Key"
        type={showApiKey ? "text" : "password"}
        value={apiKey}
        onChange={(e) => setApiKey(e.target.value)}
        onBlur={() => chatModels.fetchModels()}
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
      <Autocomplete
        freeSolo
        options={chatModels.options}
        loading={chatModels.loading}
        value={model}
        onInputChange={(_e, value) => setModel(value)}
        disabled={readOnly}
        renderInput={(params) => (
          <TextField
            {...params}
            label="Model"
            placeholder="gpt-4o-mini"
            helperText={chatModels.error ? "Couldn't fetch models from this provider — you can still type a model name." : undefined}
            slotProps={{
              ...params.slotProps,
              input: {
                ...params.slotProps.input,
                endAdornment: (
                  <>
                    {chatModels.loading ? <CircularProgress color="inherit" size={16} /> : null}
                    {params.slotProps.input.endAdornment}
                  </>
                ),
              },
            }}
          />
        )}
      />

      <Divider />

      <Typography variant="subtitle2">Voice calls (speech-to-text &amp; text-to-speech)</Typography>
      <Typography variant="caption" sx={{ color: "text.secondary" }}>
        Optionally use a different provider for AI voice calls than for text chat.
      </Typography>
      <TextField
        label="Voice Base API URL"
        value={voiceBaseUrl}
        onChange={(e) => setVoiceBaseUrl(e.target.value)}
        onBlur={() => voiceModels.fetchModels()}
        placeholder="https://api.openai.com/v1"
        fullWidth
        disabled={readOnly}
      />
      <TextField
        label="Voice API Key"
        type={showVoiceApiKey ? "text" : "password"}
        value={voiceApiKey}
        onChange={(e) => setVoiceApiKey(e.target.value)}
        onBlur={() => voiceModels.fetchModels()}
        placeholder="sk-…"
        fullWidth
        disabled={readOnly}
        slotProps={{
          input: {
            endAdornment: (
              <InputAdornment position="end">
                <IconButton size="small" onClick={() => setShowVoiceApiKey((prev) => !prev)} edge="end">
                  {showVoiceApiKey ? <VisibilityOffRoundedIcon fontSize="small" /> : <VisibilityRoundedIcon fontSize="small" />}
                </IconButton>
              </InputAdornment>
            ),
          },
        }}
      />
      <Autocomplete
        freeSolo
        options={voiceModels.options}
        loading={voiceModels.loading}
        value={sttModel}
        onInputChange={(_e, value) => setSttModel(value)}
        disabled={readOnly}
        renderInput={(params) => (
          <TextField
            {...params}
            label="Speech-to-Text Model"
            placeholder="whisper-1"
            helperText={
              voiceModels.error
                ? "Couldn't fetch models from this provider — you can still type a model name."
                : "Used to transcribe caller speech during AI voice calls."
            }
            slotProps={{
              ...params.slotProps,
              input: {
                ...params.slotProps.input,
                endAdornment: (
                  <>
                    {voiceModels.loading ? <CircularProgress color="inherit" size={16} /> : null}
                    {params.slotProps.input.endAdornment}
                  </>
                ),
              },
            }}
          />
        )}
      />
      <Autocomplete
        freeSolo
        options={voiceModels.options}
        loading={voiceModels.loading}
        value={ttsModel}
        onInputChange={(_e, value) => setTtsModel(value)}
        disabled={readOnly}
        renderInput={(params) => (
          <TextField
            {...params}
            label="Text-to-Speech Model"
            placeholder="tts-1"
            helperText="Used to synthesize the AI's spoken replies during voice calls."
            slotProps={{
              ...params.slotProps,
              input: {
                ...params.slotProps.input,
                endAdornment: (
                  <>
                    {voiceModels.loading ? <CircularProgress color="inherit" size={16} /> : null}
                    {params.slotProps.input.endAdornment}
                  </>
                ),
              },
            }}
          />
        )}
      />
      <TextField
        label="Text-to-Speech Voice"
        value={ttsVoice}
        onChange={(e) => setTtsVoice(e.target.value)}
        placeholder="alloy"
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
