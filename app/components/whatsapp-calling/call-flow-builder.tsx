import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Paper from "@mui/material/Paper";
import MenuItem from "@mui/material/MenuItem";
import Alert from "@mui/material/Alert";
import Typography from "@mui/material/Typography";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { WhatsappCallFlow, WhatsappCallFlowNode, WhatsappCallFlowNodeType } from "~/data/types";

interface CallFlowBuilderProps {
  callFlow: WhatsappCallFlow;
  onUpdated: (callFlow: WhatsappCallFlow) => void;
}

const NODE_TYPES: WhatsappCallFlowNodeType[] = ["question", "menu", "transfer_human", "end_call"];

function emptyNode(): WhatsappCallFlowNode {
  return { id: `node-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, type: "question", prompt: "" };
}

export function CallFlowBuilder({ callFlow, onUpdated }: CallFlowBuilderProps) {
  const [nodes, setNodes] = useState<WhatsappCallFlowNode[]>(callFlow.nodes.length > 0 ? callFlow.nodes : []);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function updateNode(index: number, patch: Partial<WhatsappCallFlowNode>) {
    setNodes((prev) => prev.map((node, i) => (i === index ? { ...node, ...patch } : node)));
  }

  function addNode() {
    setNodes((prev) => [...prev, emptyNode()]);
  }

  function removeNode(index: number) {
    setNodes((prev) => prev.filter((_, i) => i !== index));
  }

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const cleanedNodes = nodes
        .map((node) => ({
          id: node.id.trim(),
          type: node.type,
          prompt: node.prompt.trim(),
          options:
            node.type === "menu" && node.options
              ? node.options.filter((opt) => opt.trim().length > 0)
              : undefined,
          variable_key: node.variable_key?.trim() || undefined,
        }))
        .filter((node) => node.id && node.prompt);

      const updated = await apiClient.updateWhatsappCallFlow(callFlow.id, { nodes: cleanedNodes });
      setNodes(updated.nodes);
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save call flow.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <Typography variant="caption" sx={{ color: "text.secondary" }}>
        Build the ordered sequence of steps the caller is guided through during a WhatsApp call.
      </Typography>

      <Stack spacing={1.5}>
        {nodes.map((node, index) => (
          <Paper key={index} variant="outlined" sx={{ borderRadius: 2, p: 1.75 }}>
            <Stack spacing={1.25}>
              <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "center" }}>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  Step {index + 1}
                </Typography>
                <IconButton size="small" onClick={() => removeNode(index)}>
                  <DeleteRoundedIcon fontSize="small" />
                </IconButton>
              </Stack>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={1.25}>
                <TextField
                  fullWidth
                  size="small"
                  label="Node ID"
                  value={node.id}
                  onChange={(e) => updateNode(index, { id: e.target.value })}
                />
                <TextField
                  fullWidth
                  select
                  size="small"
                  label="Type"
                  value={node.type}
                  onChange={(e) => updateNode(index, { type: e.target.value as WhatsappCallFlowNodeType })}
                  sx={{ minWidth: 160 }}
                >
                  {NODE_TYPES.map((t) => (
                    <MenuItem key={t} value={t}>
                      {t.replace("_", " ")}
                    </MenuItem>
                  ))}
                </TextField>
              </Stack>
              <TextField
                fullWidth
                size="small"
                multiline
                minRows={2}
                label="Prompt"
                value={node.prompt}
                onChange={(e) => updateNode(index, { prompt: e.target.value })}
              />
              {node.type === "question" && (
                <TextField
                  fullWidth
                  size="small"
                  label="Variable key"
                  value={node.variable_key ?? ""}
                  onChange={(e) => updateNode(index, { variable_key: e.target.value })}
                  helperText="The response is stored under this key in collected variables."
                />
              )}
              {node.type === "menu" && (
                <TextField
                  fullWidth
                  size="small"
                  label="Options (comma separated)"
                  value={(node.options ?? []).join(", ")}
                  onChange={(e) =>
                    updateNode(index, { options: e.target.value.split(",").map((o) => o.trim()) })
                  }
                />
              )}
            </Stack>
          </Paper>
        ))}
        {nodes.length === 0 && (
          <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 2 }}>
            No steps yet. Add a step to start building the call flow.
          </Typography>
        )}
      </Stack>

      <Button startIcon={<AddRoundedIcon />} onClick={addNode} sx={{ alignSelf: "flex-start" }}>
        Add step
      </Button>

      <Button variant="contained" onClick={handleSave} disabled={saving} sx={{ alignSelf: "flex-start" }}>
        Save call flow
      </Button>
    </Stack>
  );
}
