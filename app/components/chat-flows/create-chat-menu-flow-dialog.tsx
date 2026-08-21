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
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import CircularProgress from "@mui/material/CircularProgress";
import AutoAwesomeRoundedIcon from "@mui/icons-material/AutoAwesomeRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ChatMenuFlowChannel, ChatMenuFlowNode } from "~/data/types";

const AI_PROMPT_MAX = 2000;

interface CreateChatMenuFlowDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (input: {
    name: string;
    channel: ChatMenuFlowChannel;
    entryNodeId?: string;
    nodes?: ChatMenuFlowNode[];
  }) => Promise<void>;
}

export function CreateChatMenuFlowDialog({ open, onClose, onCreate }: CreateChatMenuFlowDialogProps) {
  const [mode, setMode] = useState<"manual" | "ai">("manual");
  const [name, setName] = useState("");
  const [channel, setChannel] = useState<ChatMenuFlowChannel>("both");
  const [prompt, setPrompt] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function handleClose() {
    setMode("manual");
    setName("");
    setChannel("both");
    setPrompt("");
    setError(null);
    onClose();
  }

  async function handleSubmit() {
    if (!name.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      if (mode === "ai") {
        if (!prompt.trim()) {
          setError("Describe the menu you want to generate.");
          setSubmitting(false);
          return;
        }
        const draft = await apiClient.generateChatMenuFlow(prompt.trim());
        await onCreate({ name: name.trim(), channel, entryNodeId: draft.entryNodeId, nodes: draft.nodes });
      } else {
        await onCreate({ name: name.trim(), channel });
      }
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
          <ToggleButtonGroup
            exclusive
            fullWidth
            size="small"
            value={mode}
            onChange={(_, value) => value && setMode(value)}
          >
            <ToggleButton value="manual">Build manually</ToggleButton>
            <ToggleButton value="ai">
              <AutoAwesomeRoundedIcon fontSize="small" sx={{ mr: 0.75 }} />
              Generate with AI
            </ToggleButton>
          </ToggleButtonGroup>

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

          {mode === "ai" && (
            <TextField
              fullWidth
              multiline
              minRows={3}
              label="Describe the menu"
              placeholder="e.g. A menu for a catering business with options for menus, pricing, and booking a tasting"
              value={prompt}
              onChange={(e) => setPrompt(e.target.value.slice(0, AI_PROMPT_MAX))}
              error={prompt.length > AI_PROMPT_MAX}
              helperText={`${prompt.length}/${AI_PROMPT_MAX} characters. AI will draft a full set of menu steps and buttons, which you can review and edit before saving.`}
            />
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={handleClose}>Cancel</Button>
        <Button
          variant="contained"
          onClick={handleSubmit}
          disabled={submitting || !name.trim() || (mode === "ai" && !prompt.trim())}
          startIcon={submitting ? <CircularProgress size={16} /> : undefined}
        >
          {mode === "ai" ? "Generate & create" : "Create"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
