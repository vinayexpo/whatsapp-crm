import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Alert from "@mui/material/Alert";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import CircularProgress from "@mui/material/CircularProgress";
import { apiClient, ApiError } from "~/utils/api-client";
import type { WhatsappCallFlow, WhatsappCallFlowNode } from "~/data/types";

interface CallFlowAiChatPanelProps {
  callFlow: WhatsappCallFlow;
  onUpdated: (callFlow: WhatsappCallFlow) => void;
}

export function CallFlowAiChatPanel({ callFlow, onUpdated }: CallFlowAiChatPanelProps) {
  const [instruction, setInstruction] = useState("");
  const [proposedNodes, setProposedNodes] = useState<WhatsappCallFlowNode[] | null>(null);
  const [generating, setGenerating] = useState(false);
  const [applying, setApplying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleGenerate() {
    if (!instruction.trim()) return;
    setGenerating(true);
    setError(null);
    try {
      const result = await apiClient.generateWhatsappCallFlowNodes(callFlow.id, {
        instruction: instruction.trim(),
        applyDirectly: false,
      });
      setProposedNodes((result as { nodes: WhatsappCallFlowNode[] }).nodes);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to generate a call flow.");
    } finally {
      setGenerating(false);
    }
  }

  async function handleApply() {
    if (!proposedNodes) return;
    setApplying(true);
    setError(null);
    try {
      const updated = await apiClient.updateWhatsappCallFlow(callFlow.id, { nodes: proposedNodes });
      onUpdated(updated);
      setProposedNodes(null);
      setInstruction("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save the generated call flow.");
    } finally {
      setApplying(false);
    }
  }

  function handleDiscard() {
    setProposedNodes(null);
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <Typography variant="caption" sx={{ color: "text.secondary" }}>
        Describe the call flow you want in plain language. The AI will propose steps you can review before
        saving.
      </Typography>

      <TextField
        fullWidth
        multiline
        minRows={3}
        label="Instruction"
        placeholder='e.g. "Greet the caller, ask if they want pickup or delivery, take their order, confirm it, then end the call."'
        value={instruction}
        onChange={(e) => setInstruction(e.target.value)}
        disabled={generating}
      />

      <Button
        variant="contained"
        onClick={handleGenerate}
        disabled={generating || !instruction.trim()}
        sx={{ alignSelf: "flex-start" }}
        startIcon={generating ? <CircularProgress size={16} color="inherit" /> : undefined}
      >
        {generating ? "Generating…" : callFlow.nodes.length > 0 ? "Generate / update steps" : "Generate steps"}
      </Button>

      {proposedNodes && (
        <Stack spacing={1.5}>
          <Typography variant="subtitle2">Proposed steps</Typography>
          <Stack spacing={1}>
            {proposedNodes.map((node, index) => (
              <Paper key={`${node.id}-${index}`} variant="outlined" sx={{ borderRadius: 2, p: 1.5 }}>
                <Stack spacing={0.5}>
                  <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                    <Chip size="small" label={node.type.replace("_", " ")} />
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      {node.id}
                    </Typography>
                  </Stack>
                  <Typography variant="body2">{node.prompt}</Typography>
                  {node.options && node.options.length > 0 && (
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      Options: {node.options.join(", ")}
                    </Typography>
                  )}
                </Stack>
              </Paper>
            ))}
          </Stack>

          <Stack direction="row" spacing={1.5}>
            <Button variant="contained" onClick={handleApply} disabled={applying}>
              {applying ? "Applying…" : "Apply"}
            </Button>
            <Button variant="outlined" onClick={handleDiscard} disabled={applying}>
              Discard
            </Button>
          </Stack>
        </Stack>
      )}
    </Stack>
  );
}
