import { useMemo, useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Paper from "@mui/material/Paper";
import MenuItem from "@mui/material/MenuItem";
import Alert from "@mui/material/Alert";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import Box from "@mui/material/Box";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import CircularProgress from "@mui/material/CircularProgress";
import Tooltip from "@mui/material/Tooltip";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import AutoAwesomeRoundedIcon from "@mui/icons-material/AutoAwesomeRounded";
import ChatBubbleRoundedIcon from "@mui/icons-material/ChatBubbleRounded";
import WarningAmberRoundedIcon from "@mui/icons-material/WarningAmberRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ChatMenuFlow, ChatMenuFlowButton, ChatMenuFlowNode, ChatMenuFlowNodeType } from "~/data/types";

interface ChatMenuFlowBuilderProps {
  flow: ChatMenuFlow;
  onUpdated: (flow: ChatMenuFlow) => void;
}

const MESSAGE_MAX = 1024;
const BUTTON_LABEL_MAX = 20;
const AI_PROMPT_MAX = 2000;
const MAX_BUTTONS_PER_NODE = 10;

function newId(prefix: string) {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
}

function emptyNode(): ChatMenuFlowNode {
  return { id: newId("node"), type: "content", message: "", renderAs: "button", buttons: [] };
}

function emptyButton(): ChatMenuFlowButton {
  return { id: newId("btn"), label: "", nextNodeId: "" };
}

function nodeLabel(node: ChatMenuFlowNode, index: number) {
  const text = node.message.trim();
  if (text) return text.length > 40 ? `${text.slice(0, 40)}…` : text;
  return `Step ${index + 1}`;
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
  const [previewNodeId, setPreviewNodeId] = useState<string | null>(null);

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

  // Surfaced inline per-node/button instead of failing silently at save time,
  // since incomplete buttons used to be dropped without any visible warning.
  const nodeIssues = useMemo(() => {
    const issues = new Map<string, string[]>();
    for (const node of nodes) {
      const nodeProblems: string[] = [];
      if (!node.message.trim()) nodeProblems.push("Message is empty.");
      if (node.message.length > MESSAGE_MAX) nodeProblems.push(`Message exceeds ${MESSAGE_MAX} characters.`);
      for (const button of node.buttons) {
        if (!button.label.trim()) nodeProblems.push("A button is missing a label.");
        else if (button.label.length > BUTTON_LABEL_MAX) {
          nodeProblems.push(`Button "${button.label.slice(0, 12)}…" exceeds ${BUTTON_LABEL_MAX} characters.`);
        }
        if (!button.nextNodeId) nodeProblems.push(`Button "${button.label || "(unlabeled)"}" has no destination step.`);
      }
      issues.set(node.id, nodeProblems);
    }
    return issues;
  }, [nodes]);

  const hasBlockingIssues = useMemo(
    () => Array.from(nodeIssues.values()).some((problems) => problems.length > 0),
    [nodeIssues],
  );

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      if (hasBlockingIssues) {
        setError("Fix the highlighted steps below before saving — invalid buttons would otherwise be dropped silently.");
        setSaving(false);
        return;
      }

      const cleanedNodes = nodes
        .map((node) => ({
          id: node.id.trim(),
          type: node.buttons.length > 0 ? ("menu" as ChatMenuFlowNodeType) : ("content" as ChatMenuFlowNodeType),
          message: node.message.trim(),
          renderAs: node.buttons.length > 3 ? ("list" as const) : ("button" as const),
          buttons: node.buttons.map((b) => ({ id: b.id.trim(), label: b.label.trim(), nextNodeId: b.nextNodeId.trim() })),
        }))
        .filter((node) => node.id && node.message);

      if (cleanedNodes.length === 0) {
        setError("Add at least one step with a message.");
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

  const previewNode = nodes.find((n) => n.id === previewNodeId) ?? null;

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "flex-start", gap: 1.5 }}>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Build your menu as a series of steps. A step with reply buttons shows those buttons to the customer;
          tapping one jumps to the step it points to. A step with no buttons is a final reply that ends the flow.
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
        label="First step (shown when the flow starts)"
        value={entryNodeId}
        onChange={(e) => setEntryNodeId(e.target.value)}
      >
        {nodes.map((node, index) => (
          <MenuItem key={node.id} value={node.id}>
            {nodeLabel(node, index)}
          </MenuItem>
        ))}
      </TextField>

      <Stack spacing={1.5}>
        {nodes.map((node, index) => {
          const problems = nodeIssues.get(node.id) ?? [];
          const messageLen = node.message.length;
          return (
            <Paper
              key={node.id}
              variant="outlined"
              sx={{
                borderRadius: 2,
                p: 1.75,
                borderColor: problems.length > 0 ? "warning.main" : undefined,
              }}
            >
              <Stack spacing={1.25}>
                <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "center" }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
                    <Chip label={`Step ${index + 1}`} size="small" />
                    {node.id === entryNodeId && <Chip label="First step" size="small" color="primary" />}
                    <Chip
                      label={node.buttons.length > 0 ? "Menu with buttons" : "Final reply"}
                      size="small"
                      variant="outlined"
                    />
                    {problems.length > 0 && (
                      <Tooltip title={problems.join(" ")}>
                        <WarningAmberRoundedIcon fontSize="small" color="warning" />
                      </Tooltip>
                    )}
                  </Stack>
                  <Stack direction="row" spacing={0.5}>
                    <Tooltip title="Preview how this looks on WhatsApp">
                      <IconButton size="small" onClick={() => setPreviewNodeId(node.id)}>
                        <ChatBubbleRoundedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                    <IconButton size="small" onClick={() => removeNode(index)}>
                      <DeleteRoundedIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                </Stack>

                <TextField
                  fullWidth
                  size="small"
                  multiline
                  minRows={2}
                  label="Message"
                  value={node.message}
                  onChange={(e) => updateNode(index, { message: e.target.value })}
                  error={messageLen > MESSAGE_MAX}
                  helperText={`${messageLen}/${MESSAGE_MAX} characters — this is what WhatsApp shows the customer.`}
                />

                <Stack spacing={1}>
                  <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "center" }}>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      Reply buttons ({node.buttons.length}/{MAX_BUTTONS_PER_NODE})
                    </Typography>
                    {node.buttons.length > 3 && (
                      <Typography variant="caption" sx={{ color: "text.secondary" }}>
                        Shown as a list (WhatsApp only allows 3 inline buttons)
                      </Typography>
                    )}
                  </Stack>
                  {node.buttons.map((button, buttonIndex) => {
                    const labelLen = button.label.length;
                    const labelTooLong = labelLen > BUTTON_LABEL_MAX;
                    return (
                      <Stack key={button.id} direction={{ xs: "column", sm: "row" }} spacing={1} sx={{ alignItems: { sm: "flex-start" } }}>
                        <TextField
                          size="small"
                          label="Button label"
                          value={button.label}
                          onChange={(e) => updateButton(index, buttonIndex, { label: e.target.value })}
                          error={labelTooLong || (button.label === "" && node.buttons.length > 0)}
                          helperText={`${labelLen}/${BUTTON_LABEL_MAX}${labelTooLong ? " — too long for WhatsApp" : ""}`}
                          sx={{ flex: 1 }}
                        />
                        <TextField
                          size="small"
                          select
                          label="Goes to step"
                          value={button.nextNodeId}
                          onChange={(e) => updateButton(index, buttonIndex, { nextNodeId: e.target.value })}
                          error={!button.nextNodeId}
                          helperText={!button.nextNodeId ? "Required" : " "}
                          sx={{ minWidth: 200 }}
                        >
                          {nodes
                            .filter((n) => n.id !== node.id)
                            .map((n) => {
                              const i2 = nodes.findIndex((x) => x.id === n.id);
                              return (
                                <MenuItem key={n.id} value={n.id}>
                                  {nodeLabel(n, i2)}
                                </MenuItem>
                              );
                            })}
                        </TextField>
                        <IconButton size="small" onClick={() => removeButton(index, buttonIndex)} sx={{ mt: { sm: 0.5 } }}>
                          <DeleteRoundedIcon fontSize="small" />
                        </IconButton>
                      </Stack>
                    );
                  })}
                  {node.buttons.length < MAX_BUTTONS_PER_NODE && (
                    <Button
                      size="small"
                      startIcon={<AddRoundedIcon />}
                      onClick={() => addButton(index)}
                      sx={{ alignSelf: "flex-start" }}
                    >
                      Add reply button
                    </Button>
                  )}
                </Stack>
              </Stack>
            </Paper>
          );
        })}
      </Stack>

      <Button startIcon={<AddRoundedIcon />} onClick={addNode} sx={{ alignSelf: "flex-start" }}>
        Add step
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
              onChange={(e) => setAiPrompt(e.target.value.slice(0, AI_PROMPT_MAX))}
              error={aiPrompt.length > AI_PROMPT_MAX}
              helperText={
                <>
                  {aiPrompt.length}/{AI_PROMPT_MAX} characters.{" "}
                  {aiMode === "replace"
                    ? "Replaces all steps below with a freshly generated tree. Nothing is saved until you click Save."
                    : "Appends newly generated steps to the existing tree. Nothing is saved until you click Save."}
                </>
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

      <Dialog open={Boolean(previewNode)} onClose={() => setPreviewNodeId(null)} maxWidth="xs" fullWidth>
        <DialogTitle>WhatsApp preview</DialogTitle>
        <DialogContent>
          {previewNode && (
            <Box
              sx={{
                bgcolor: "#e5ddd5",
                borderRadius: 2,
                p: 2,
                mt: 1,
              }}
            >
              <Box
                sx={{
                  bgcolor: "#fff",
                  borderRadius: "8px 8px 8px 0px",
                  p: 1.5,
                  maxWidth: "85%",
                  boxShadow: "0 1px 0.5px rgba(0,0,0,0.13)",
                }}
              >
                <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>
                  {previewNode.message.trim() || "(empty message)"}
                </Typography>
              </Box>
              {previewNode.buttons.length > 0 && (
                <Stack spacing={0.75} sx={{ mt: 1, maxWidth: "85%" }}>
                  {previewNode.buttons.length > 3 ? (
                    <Box sx={{ bgcolor: "#fff", borderRadius: 1, p: 1.25, textAlign: "center" }}>
                      <Typography variant="body2" sx={{ color: "#00a5f4", fontWeight: 600 }}>
                        Choose an option ▾
                      </Typography>
                    </Box>
                  ) : (
                    previewNode.buttons.map((button) => (
                      <Box key={button.id} sx={{ bgcolor: "#fff", borderRadius: 1, p: 1.25, textAlign: "center" }}>
                        <Typography variant="body2" sx={{ color: "#00a5f4", fontWeight: 600 }}>
                          {button.label.trim() || "(empty label)"}
                        </Typography>
                      </Box>
                    ))
                  )}
                </Stack>
              )}
            </Box>
          )}
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2.5 }}>
          <Button onClick={() => setPreviewNodeId(null)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
