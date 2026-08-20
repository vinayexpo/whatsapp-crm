import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Paper from "@mui/material/Paper";
import MenuItem from "@mui/material/MenuItem";
import Alert from "@mui/material/Alert";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import CircularProgress from "@mui/material/CircularProgress";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import AutoAwesomeRoundedIcon from "@mui/icons-material/AutoAwesomeRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ChatMenuFlow, ChatMenuFlowButton, ChatMenuFlowNode, ChatMenuFlowNodeType } from "~/data/types";

interface ChatMenuFlowBuilderProps {
  flow: ChatMenuFlow;
  onUpdated: (flow: ChatMenuFlow) => void;
}

function newId(prefix: string) {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
}

function emptyNode(): ChatMenuFlowNode {
  return { id: newId("node"), type: "content", message: "", renderAs: "button", buttons: [] };
}

function emptyButton(): ChatMenuFlowButton {
  return { id: newId("btn"), label: "", nextNodeId: "" };
}

export function ChatMenuFlowBuilder({ flow, onUpdated }: ChatMenuFlowBuilderProps) {
  const [nodes, setNodes] = useState<ChatMenuFlowNode[]>(flow.nodes.length > 0 ? flow.nodes : [emptyNode()]);
  const [entryNodeId, setEntryNodeId] = useState(flow.entryNodeId || nodes[0]?.id || "");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [aiDialogOpen, setAiDialogOpen] = useState(false);
  const [aiMode, setAiMode] = useState<"replace" | "expand">("replace");
  const [aiPrompt, setAiPrompt] = useState("");
  const [generating, setGenerating] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);

  function openAiDialog() {
    setAiMode("replace");
    setAiPrompt("");
    setAiError(null);
    setAiDialogOpen(true);
  }

  async function handleGenerate() {
    if (!aiPrompt.trim()) return;
    setGenerating(true);
    setAiError(null);
    try {
      const draft = await apiClient.generateChatMenuFlow(aiPrompt.trim());
      if (aiMode === "replace") {
        setNodes(draft.nodes);
        setEntryNodeId(draft.entryNodeId);
      } else {
        setNodes((prev) => [...prev, ...draft.nodes]);
      }
      setAiDialogOpen(false);
    } catch (err) {
      setAiError(err instanceof ApiError ? err.message : "Failed to generate a chat menu.");
    } finally {
      setGenerating(false);
    }
  }

  function updateNode(index: number, patch: Partial<ChatMenuFlowNode>) {
    setNodes((prev) => prev.map((node, i) => (i === index ? { ...node, ...patch } : node)));
  }

  function addNode() {
    setNodes((prev) => [...prev, emptyNode()]);
  }

  function removeNode(index: number) {
    const removedId = nodes[index]?.id;
    setNodes((prev) => prev.filter((_, i) => i !== index));
    if (removedId === entryNodeId) {
      setEntryNodeId(nodes.find((_, i) => i !== index)?.id ?? "");
    }
  }

  function addButton(nodeIndex: number) {
    setNodes((prev) =>
      prev.map((node, i) => (i === nodeIndex ? { ...node, type: "menu", buttons: [...node.buttons, emptyButton()] } : node)),
    );
  }

  function updateButton(nodeIndex: number, buttonIndex: number, patch: Partial<ChatMenuFlowButton>) {
    setNodes((prev) =>
      prev.map((node, i) =>
        i === nodeIndex
          ? { ...node, buttons: node.buttons.map((b, bi) => (bi === buttonIndex ? { ...b, ...patch } : b)) }
          : node,
      ),
    );
  }

  function removeButton(nodeIndex: number, buttonIndex: number) {
    setNodes((prev) =>
      prev.map((node, i) => {
        if (i !== nodeIndex) return node;
        const buttons = node.buttons.filter((_, bi) => bi !== buttonIndex);
        return { ...node, buttons, type: buttons.length > 0 ? "menu" : node.type };
      }),
    );
  }

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const cleanedNodes = nodes
        .map((node) => ({
          id: node.id.trim(),
          type: node.buttons.length > 0 ? ("menu" as ChatMenuFlowNodeType) : ("content" as ChatMenuFlowNodeType),
          message: node.message.trim(),
          renderAs: node.buttons.length > 3 ? ("list" as const) : ("button" as const),
          buttons: node.buttons
            .map((b) => ({ id: b.id.trim(), label: b.label.trim(), nextNodeId: b.nextNodeId.trim() }))
            .filter((b) => b.id && b.label && b.nextNodeId),
        }))
        .filter((node) => node.id && node.message);

      if (cleanedNodes.length === 0) {
        setError("Add at least one node with a message.");
        setSaving(false);
        return;
      }

      const resolvedEntryId = cleanedNodes.some((n) => n.id === entryNodeId) ? entryNodeId : cleanedNodes[0].id;

      const updated = await apiClient.updateChatMenuFlow(flow.id, {
        nodes: cleanedNodes,
        entryNodeId: resolvedEntryId,
      });
      setNodes(updated.nodes);
      setEntryNodeId(updated.entryNodeId);
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save chat menu flow.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "flex-start", gap: 1.5 }}>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Build a tree of menu nodes. A node with buttons shows those buttons to the customer; each button leads to
          another node. A node with no buttons is a final reply that ends the flow.
        </Typography>
        <Button
          size="small"
          variant="outlined"
          startIcon={<AutoAwesomeRoundedIcon fontSize="small" />}
          onClick={openAiDialog}
          sx={{ flexShrink: 0 }}
        >
          Generate with AI
        </Button>
      </Stack>

      <TextField
        fullWidth
        select
        size="small"
        label="Entry node (shown when the flow starts)"
        value={entryNodeId}
        onChange={(e) => setEntryNodeId(e.target.value)}
      >
        {nodes.map((node) => (
          <MenuItem key={node.id} value={node.id}>
            {node.message.trim() || node.id}
          </MenuItem>
        ))}
      </TextField>

      <Stack spacing={1.5}>
        {nodes.map((node, index) => (
          <Paper key={node.id} variant="outlined" sx={{ borderRadius: 2, p: 1.75 }}>
            <Stack spacing={1.25}>
              <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "center" }}>
                <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    Node
                  </Typography>
                  {node.id === entryNodeId && <Chip label="Entry" size="small" color="primary" />}
                  <Chip
                    label={node.buttons.length > 0 ? "Menu" : "Final reply"}
                    size="small"
                    variant="outlined"
                  />
                </Stack>
                <IconButton size="small" onClick={() => removeNode(index)}>
                  <DeleteRoundedIcon fontSize="small" />
                </IconButton>
              </Stack>

              <TextField
                fullWidth
                size="small"
                label="Node ID"
                value={node.id}
                onChange={(e) => updateNode(index, { id: e.target.value })}
              />

              <TextField
                fullWidth
                size="small"
                multiline
                minRows={2}
                label="Message"
                value={node.message}
                onChange={(e) => updateNode(index, { message: e.target.value })}
              />

              <Stack spacing={1}>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  Buttons ({node.buttons.length}/10)
                </Typography>
                {node.buttons.map((button, buttonIndex) => (
                  <Stack key={button.id} direction={{ xs: "column", sm: "row" }} spacing={1}>
                    <TextField
                      size="small"
                      label="Button label"
                      value={button.label}
                      onChange={(e) => updateButton(index, buttonIndex, { label: e.target.value })}
                      sx={{ flex: 1 }}
                    />
                    <TextField
                      size="small"
                      select
                      label="Leads to"
                      value={button.nextNodeId}
                      onChange={(e) => updateButton(index, buttonIndex, { nextNodeId: e.target.value })}
                      sx={{ minWidth: 180 }}
                    >
                      {nodes
                        .filter((n) => n.id !== node.id)
                        .map((n) => (
                          <MenuItem key={n.id} value={n.id}>
                            {n.message.trim() || n.id}
                          </MenuItem>
                        ))}
                    </TextField>
                    <IconButton size="small" onClick={() => removeButton(index, buttonIndex)}>
                      <DeleteRoundedIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                ))}
                {node.buttons.length < 10 && (
                  <Button
                    size="small"
                    startIcon={<AddRoundedIcon />}
                    onClick={() => addButton(index)}
                    sx={{ alignSelf: "flex-start" }}
                  >
                    Add button
                  </Button>
                )}
              </Stack>
            </Stack>
          </Paper>
        ))}
      </Stack>

      <Button startIcon={<AddRoundedIcon />} onClick={addNode} sx={{ alignSelf: "flex-start" }}>
        Add node
      </Button>

      <Button variant="contained" onClick={handleSave} disabled={saving} sx={{ alignSelf: "flex-start" }}>
        Save chat menu flow
      </Button>

      <Dialog open={aiDialogOpen} onClose={() => setAiDialogOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle>Generate with AI</DialogTitle>
        <DialogContent>
          {aiError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {aiError}
            </Alert>
          )}
          <Stack spacing={2} sx={{ mt: 1 }}>
            <ToggleButtonGroup
              exclusive
              fullWidth
              size="small"
              value={aiMode}
              onChange={(_, value) => value && setAiMode(value)}
            >
              <ToggleButton value="replace">Replace flow</ToggleButton>
              <ToggleButton value="expand">Add to flow</ToggleButton>
            </ToggleButtonGroup>
            <TextField
              autoFocus
              fullWidth
              multiline
              minRows={3}
              label="Describe the menu"
              placeholder="e.g. Add a branch for shipping and returns questions"
              value={aiPrompt}
              onChange={(e) => setAiPrompt(e.target.value)}
              helperText={
                aiMode === "replace"
                  ? "Replaces all nodes below with a freshly generated tree. Nothing is saved until you click Save."
                  : "Appends newly generated nodes to the existing tree. Nothing is saved until you click Save."
              }
            />
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2.5 }}>
          <Button onClick={() => setAiDialogOpen(false)}>Cancel</Button>
          <Button
            variant="contained"
            onClick={handleGenerate}
            disabled={generating || !aiPrompt.trim()}
            startIcon={generating ? <CircularProgress size={16} /> : undefined}
          >
            Generate
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
