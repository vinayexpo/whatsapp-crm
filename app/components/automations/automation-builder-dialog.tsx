import { useEffect, useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import Button from "@mui/material/Button";
import Stepper from "@mui/material/Stepper";
import Step from "@mui/material/Step";
import StepLabel from "@mui/material/StepLabel";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import MenuItem from "@mui/material/MenuItem";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import Paper from "@mui/material/Paper";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import ArrowForwardRoundedIcon from "@mui/icons-material/ArrowForwardRounded";
import AutoAwesomeRoundedIcon from "@mui/icons-material/AutoAwesomeRounded";
import Alert from "@mui/material/Alert";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { PIPELINE_STAGES } from "~/data/pipeline-stages";
import { useCrmStore } from "~/hooks/use-crm-store";
import { apiClient } from "~/utils/api-client";
import { ACTION_LABELS, CONDITION_FIELD_LABELS, TRIGGER_LABELS } from "./automation-labels";
import type {
  AutomationAction,
  AutomationActionType,
  AutomationCondition,
  AutomationFlow,
  AutomationTriggerType,
  ChannelType,
  ConditionField,
  WhatsappFlow,
} from "~/data/types";

interface AutomationBuilderDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (flow: AutomationFlow) => void;
}

const STEPS = ["Trigger", "Conditions", "Actions", "Review"];
const TAG_OPTIONS = ["VIP", "New", "Enterprise", "Wholesale", "Design", "Repeat Customer", "Retail", "Fashion", "Influencer"];

function emptyCondition(): AutomationCondition {
  return { id: `cond-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, field: "tag", operator: "is", value: TAG_OPTIONS[0] };
}

function emptyAction(): AutomationAction {
  return { id: `act-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, type: "send-message", value: "" };
}

export function AutomationBuilderDialog({ open, onClose, onCreate }: AutomationBuilderDialogProps) {
  const { aiAssistantSettings } = useCrmStore();
  const isAiConfigured = Boolean(
    aiAssistantSettings.baseUrl.trim() && aiAssistantSettings.apiKey?.trim() && aiAssistantSettings.model.trim(),
  );
  const [activeStep, setActiveStep] = useState(0);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [channel, setChannel] = useState<ChannelType | "both">("whatsapp");
  const [triggerType, setTriggerType] = useState<AutomationTriggerType>("new-message");
  const [keyword, setKeyword] = useState("");
  const [conditions, setConditions] = useState<AutomationCondition[]>([]);
  const [actions, setActions] = useState<AutomationAction[]>([emptyAction()]);
  const [whatsappFlows, setWhatsappFlows] = useState<WhatsappFlow[]>([]);

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    apiClient
      .listAllWhatsappFlows()
      .then((flows) => {
        if (!cancelled) setWhatsappFlows(flows);
      })
      .catch(() => {
        // flow picker stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [open]);

  function resetAndClose() {
    setActiveStep(0);
    setName("");
    setDescription("");
    setChannel("whatsapp");
    setTriggerType("new-message");
    setKeyword("");
    setConditions([]);
    setActions([emptyAction()]);
    onClose();
  }

  function handleCreate() {
    const flow: AutomationFlow = {
      id: `flow-${Date.now()}`,
      name: name.trim() || "Untitled Automation",
      description: description.trim() || "No description provided.",
      status: "active",
      trigger: { type: triggerType, channel, keyword: triggerType === "keyword" ? keyword.trim() : undefined },
      conditions,
      actions: actions.filter((a) => a.value.trim().length > 0),
      triggeredCount: 0,
      lastTriggeredAt: null,
      createdAt: new Date().toISOString(),
    };
    onCreate(flow);
    resetAndClose();
  }

  function updateCondition(id: string, patch: Partial<AutomationCondition>) {
    setConditions((prev) => prev.map((c) => (c.id === id ? { ...c, ...patch } : c)));
  }

  function updateAction(id: string, patch: Partial<AutomationAction>) {
    setActions((prev) => prev.map((a) => (a.id === id ? { ...a, ...patch } : a)));
  }

  const canProceedStep0 = name.trim().length > 0 && (triggerType !== "keyword" || keyword.trim().length > 0);
  const canProceedStep2 = actions.some((a) => a.value.trim().length > 0);

  return (
    <Dialog open={open} onClose={resetAndClose} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Create Automated Chat Flow</DialogTitle>
      <DialogContent>
        <Stepper activeStep={activeStep} sx={{ mb: 3, mt: 1 }}>
          {STEPS.map((label) => (
            <Step key={label}>
              <StepLabel>{label}</StepLabel>
            </Step>
          ))}
        </Stepper>

        {activeStep === 0 && (
          <Stack spacing={2.5}>
            <TextField
              label="Automation name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Pricing Keyword Auto-Reply"
              fullWidth
              autoFocus
            />
            <TextField
              label="Description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="What does this automation do?"
              fullWidth
              multiline
              minRows={2}
            />
            <Box>
              <Typography variant="body2" sx={{ fontWeight: 600, mb: 1 }}>
                Channel
              </Typography>
              <ToggleButtonGroup value={channel} exclusive onChange={(_, value) => value && setChannel(value)} size="small">
                <ToggleButton value="whatsapp">
                  <ChannelIcon channel="whatsapp" size={16} />
                  <Box component="span" sx={{ ml: 1 }}>
                    WhatsApp
                  </Box>
                </ToggleButton>
                <ToggleButton value="instagram">
                  <ChannelIcon channel="instagram" size={16} />
                  <Box component="span" sx={{ ml: 1 }}>
                    Instagram
                  </Box>
                </ToggleButton>
                <ToggleButton value="both">Both</ToggleButton>
              </ToggleButtonGroup>
            </Box>
            <TextField
              select
              label="Trigger event"
              value={triggerType}
              onChange={(e) => setTriggerType(e.target.value as AutomationTriggerType)}
              fullWidth
            >
              {Object.entries(TRIGGER_LABELS).map(([value, label]) => (
                <MenuItem key={value} value={value}>
                  {label}
                </MenuItem>
              ))}
            </TextField>
            {(triggerType === "keyword" || triggerType === "comment-received") && (
              <TextField
                label={triggerType === "comment-received" ? "Keyword to match (optional)" : "Keyword to match"}
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                placeholder="e.g. price"
                helperText={
                  triggerType === "comment-received"
                    ? "Leave blank to reply to every comment, or require a keyword like \"price\" to be present."
                    : undefined
                }
                fullWidth
              />
            )}
          </Stack>
        )}

        {activeStep === 1 && (
          <Stack spacing={2}>
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              Optionally narrow this automation to contacts matching these conditions. Leave empty to run on every trigger.
            </Typography>
            {conditions.map((condition) => (
              <Paper key={condition.id} variant="outlined" sx={{ p: 1.5, borderRadius: 2 }}>
                <Stack direction="row" sx={{ gap: 1.5, alignItems: "center", flexWrap: "wrap" }}>
                  <TextField
                    select
                    size="small"
                    label="Field"
                    value={condition.field}
                    onChange={(e) => updateCondition(condition.id, { field: e.target.value as ConditionField })}
                    sx={{ minWidth: 160 }}
                  >
                    {Object.entries(CONDITION_FIELD_LABELS).map(([value, label]) => (
                      <MenuItem key={value} value={value}>
                        {label}
                      </MenuItem>
                    ))}
                  </TextField>
                  {condition.field === "tag" && (
                    <TextField
                      select
                      size="small"
                      label="Tag"
                      value={condition.value}
                      onChange={(e) => updateCondition(condition.id, { value: e.target.value })}
                      sx={{ minWidth: 160 }}
                    >
                      {TAG_OPTIONS.map((tag) => (
                        <MenuItem key={tag} value={tag}>
                          {tag}
                        </MenuItem>
                      ))}
                    </TextField>
                  )}
                  {condition.field === "pipelineStage" && (
                    <TextField
                      select
                      size="small"
                      label="Stage"
                      value={condition.value}
                      onChange={(e) => updateCondition(condition.id, { value: e.target.value })}
                      sx={{ minWidth: 160 }}
                    >
                      {PIPELINE_STAGES.map((stage) => (
                        <MenuItem key={stage.id} value={stage.id}>
                          {stage.name}
                        </MenuItem>
                      ))}
                    </TextField>
                  )}
                  {condition.field === "messageContains" && (
                    <TextField
                      size="small"
                      label="Text"
                      value={condition.value}
                      onChange={(e) => updateCondition(condition.id, { value: e.target.value })}
                      sx={{ minWidth: 160 }}
                    />
                  )}
                  <Box sx={{ flex: 1 }} />
                  <IconButton
                    size="small"
                    onClick={() => setConditions((prev) => prev.filter((c) => c.id !== condition.id))}
                    aria-label="Remove condition"
                  >
                    <DeleteOutlineRoundedIcon fontSize="small" />
                  </IconButton>
                </Stack>
              </Paper>
            ))}
            <Button
              startIcon={<AddRoundedIcon />}
              onClick={() => setConditions((prev) => [...prev, emptyCondition()])}
              sx={{ alignSelf: "flex-start" }}
            >
              Add condition
            </Button>
          </Stack>
        )}

        {activeStep === 2 && (
          <Stack spacing={2}>
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              Choose what happens automatically when this flow triggers.
            </Typography>
            {actions.map((action) => (
              <Paper key={action.id} variant="outlined" sx={{ p: 1.5, borderRadius: 2 }}>
                <Stack spacing={1.5}>
                  <Stack direction="row" sx={{ gap: 1.5, alignItems: "center" }}>
                    <TextField
                      select
                      size="small"
                      label="Action"
                      value={action.type}
                      onChange={(e) => updateAction(action.id, { type: e.target.value as AutomationActionType, value: "" })}
                      sx={{ minWidth: 200 }}
                    >
                      {Object.entries(ACTION_LABELS).map(([value, label]) => (
                        <MenuItem key={value} value={value}>
                          {label}
                        </MenuItem>
                      ))}
                    </TextField>
                    <Box sx={{ flex: 1 }} />
                    {actions.length > 1 && (
                      <IconButton
                        size="small"
                        onClick={() => setActions((prev) => prev.filter((a) => a.id !== action.id))}
                        aria-label="Remove action"
                      >
                        <DeleteOutlineRoundedIcon fontSize="small" />
                      </IconButton>
                    )}
                  </Stack>
                  {action.type === "send-message" && (
                    <TextField
                      label="Message"
                      value={action.value}
                      onChange={(e) => updateAction(action.id, { value: e.target.value })}
                      placeholder="Write the automatic reply…"
                      fullWidth
                      multiline
                      minRows={2}
                    />
                  )}
                  {action.type === "ai-reply" && (
                    <Stack spacing={1.5}>
                      {!isAiConfigured && (
                        <Alert severity="warning" icon={<AutoAwesomeRoundedIcon fontSize="small" />}>
                          AI Assistant isn't configured yet. Add your Base API URL, API key, and model in Settings
                          before turning this automation on.
                        </Alert>
                      )}
                      <TextField
                        label="AI instructions"
                        value={action.value}
                        onChange={(e) => updateAction(action.id, { value: e.target.value })}
                        placeholder="e.g. Draft a friendly reply answering the contact's pricing question using our catalog."
                        helperText="The configured AI Assistant model will generate a reply from these instructions and the conversation context."
                        fullWidth
                        multiline
                        minRows={2}
                      />
                    </Stack>
                  )}
                  {action.type === "add-tag" && (
                    <TextField
                      select
                      label="Tag to add"
                      value={action.value}
                      onChange={(e) => updateAction(action.id, { value: e.target.value })}
                      fullWidth
                    >
                      {TAG_OPTIONS.map((tag) => (
                        <MenuItem key={tag} value={tag}>
                          {tag}
                        </MenuItem>
                      ))}
                    </TextField>
                  )}
                  {action.type === "move-pipeline-stage" && (
                    <TextField
                      select
                      label="Move to stage"
                      value={action.value}
                      onChange={(e) => updateAction(action.id, { value: e.target.value })}
                      fullWidth
                    >
                      {PIPELINE_STAGES.map((stage) => (
                        <MenuItem key={stage.id} value={stage.id}>
                          {stage.name}
                        </MenuItem>
                      ))}
                    </TextField>
                  )}
                  {action.type === "send-whatsapp-flow" && (
                    <TextField
                      select
                      label="WhatsApp Flow to send"
                      value={action.value}
                      onChange={(e) => updateAction(action.id, { value: e.target.value })}
                      helperText={
                        whatsappFlows.length === 0
                          ? "No synced WhatsApp Flows found. Sync flows from Settings → API Connections first."
                          : undefined
                      }
                      disabled={whatsappFlows.length === 0}
                      fullWidth
                    >
                      {whatsappFlows.map((flow) => (
                        <MenuItem key={flow.id} value={flow.id}>
                          {flow.name}
                        </MenuItem>
                      ))}
                    </TextField>
                  )}
                  {(action.type === "assign-agent" || action.type === "notify-team") && (
                    <TextField
                      label={action.type === "assign-agent" ? "Agent name" : "Team to notify"}
                      value={action.value}
                      onChange={(e) => updateAction(action.id, { value: e.target.value })}
                      fullWidth
                    />
                  )}
                  {action.type === "send-instagram-dm" && (
                    <TextField
                      label="DM message"
                      value={action.value}
                      onChange={(e) => updateAction(action.id, { value: e.target.value })}
                      placeholder="Write the automatic Instagram DM…"
                      fullWidth
                      multiline
                      minRows={2}
                    />
                  )}
                </Stack>
              </Paper>
            ))}
            <Button startIcon={<AddRoundedIcon />} onClick={() => setActions((prev) => [...prev, emptyAction()])} sx={{ alignSelf: "flex-start" }}>
              Add another action
            </Button>
          </Stack>
        )}

        {activeStep === 3 && (
          <Stack spacing={2.5}>
            <Paper variant="outlined" sx={{ p: 2, borderRadius: 2 }}>
              <Stack spacing={1}>
                <Typography variant="body2" sx={{ fontWeight: 700 }}>
                  {name || "Untitled Automation"}
                </Typography>
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  {description || "No description provided."}
                </Typography>
              </Stack>
            </Paper>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1, flexWrap: "wrap" }}>
              <Chip
                size="small"
                variant="outlined"
                label={triggerType === "keyword" && keyword ? `${TRIGGER_LABELS[triggerType]}: "${keyword}"` : TRIGGER_LABELS[triggerType]}
              />
              <ArrowForwardRoundedIcon sx={{ fontSize: 16, color: "text.secondary" }} />
              <Stack direction="row" sx={{ gap: 0.75, flexWrap: "wrap" }}>
                {actions
                  .filter((a) => a.value.trim().length > 0)
                  .map((action) => (
                    <Chip
                      key={action.id}
                      size="small"
                      color="secondary"
                      variant="outlined"
                      label={
                        action.type === "send-whatsapp-flow"
                          ? `${ACTION_LABELS[action.type]}: ${whatsappFlows.find((f) => f.id === action.value)?.name ?? action.value}`
                          : ACTION_LABELS[action.type]
                      }
                    />
                  ))}
              </Stack>
            </Stack>
            {conditions.length > 0 && (
              <Box>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  Conditions
                </Typography>
                <Stack direction="row" sx={{ gap: 0.75, flexWrap: "wrap", mt: 0.5 }}>
                  {conditions.map((condition) => (
                    <Chip key={condition.id} size="small" label={`${CONDITION_FIELD_LABELS[condition.field]}: ${condition.value}`} />
                  ))}
                </Stack>
              </Box>
            )}
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              This automation will be created and turned on immediately.
            </Typography>
          </Stack>
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={resetAndClose} color="inherit">
          Cancel
        </Button>
        <Box sx={{ flex: 1 }} />
        {activeStep > 0 && <Button onClick={() => setActiveStep((s) => s - 1)}>Back</Button>}
        {activeStep < STEPS.length - 1 ? (
          <Button
            variant="contained"
            onClick={() => setActiveStep((s) => s + 1)}
            disabled={(activeStep === 0 && !canProceedStep0) || (activeStep === 2 && !canProceedStep2)}
          >
            Next
          </Button>
        ) : (
          <Button variant="contained" onClick={handleCreate}>
            Create Automation
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
