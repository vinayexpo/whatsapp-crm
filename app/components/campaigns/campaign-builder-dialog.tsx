import { useEffect, useMemo, useState } from "react";
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
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import Chip from "@mui/material/Chip";
import Paper from "@mui/material/Paper";
import Radio from "@mui/material/Radio";
import RadioGroup from "@mui/material/RadioGroup";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import CircularProgress from "@mui/material/CircularProgress";
import { DateTimePicker } from "@mui/x-date-pickers/DateTimePicker";
import dayjs from "dayjs";
import type { Dayjs } from "dayjs";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import PhoneRoundedIcon from "@mui/icons-material/PhoneRounded";
import ChatRoundedIcon from "@mui/icons-material/ChatRounded";
import type {
  ApiConnection,
  ChannelType,
  Contact,
  PhonebookFolder,
  VoiceAgent,
  WhatsappCallFlow,
  WhatsappTemplate,
} from "~/data/types";
import { apiClient } from "~/utils/api-client";

interface CampaignBuilderDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (campaign: {
    name: string;
    channel?: ChannelType | "both";
    message?: string;
    attachmentUrl?: string | null;
    attachmentType?: "image" | "video" | null;
    attachmentFile?: File | null;
    audienceTag?: string | null;
    phonebookFolderId?: string | null;
    scheduledAt?: string | null;
    templateId?: string | null;
    templateVariables?: Record<string, string> | null;
    voiceAgentId?: string | null;
    whatsappCallFlowId?: string | null;
  }) => void;
  contacts: Contact[];
  apiConnections: ApiConnection[];
}

const STEPS = ["Audience", "Message", "Review & Schedule"];
const AVAILABLE_TAGS = ["VIP", "New", "Enterprise", "Wholesale", "Design", "Repeat Customer", "Retail", "Fashion", "Influencer"];

export function CampaignBuilderDialog({ open, onClose, onCreate, contacts, apiConnections }: CampaignBuilderDialogProps) {
  const [activeStep, setActiveStep] = useState(0);
  const [name, setName] = useState("");
  const [campaignMode, setCampaignMode] = useState<"message" | "voiceCall" | "whatsappCall">("message");
  const [channel, setChannel] = useState<ChannelType | "both">("whatsapp");
  const [voiceAgents, setVoiceAgents] = useState<VoiceAgent[]>([]);
  const [selectedVoiceAgentId, setSelectedVoiceAgentId] = useState("");
  const [callFlows, setCallFlows] = useState<WhatsappCallFlow[]>([]);
  const [selectedCallFlowId, setSelectedCallFlowId] = useState("");
  const [audienceMode, setAudienceMode] = useState<"tag" | "folder">("tag");
  const [audienceTag, setAudienceTag] = useState("VIP");
  const [folders, setFolders] = useState<PhonebookFolder[]>([]);
  const [phonebookFolderId, setPhonebookFolderId] = useState("");
  const [messageMode, setMessageMode] = useState<"custom" | "template">("custom");
  const [message, setMessage] = useState("");
  const [attachmentEnabled, setAttachmentEnabled] = useState(false);
  const [attachmentType, setAttachmentType] = useState<"image" | "video">("image");
  const [attachmentSource, setAttachmentSource] = useState<"url" | "upload">("url");
  const [attachmentUrl, setAttachmentUrl] = useState("");
  const [attachmentFile, setAttachmentFile] = useState<File | null>(null);
  const [templates, setTemplates] = useState<WhatsappTemplate[]>([]);
  const [templatesLoading, setTemplatesLoading] = useState(false);
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>("");
  const [templateVariableValues, setTemplateVariableValues] = useState<Record<string, string>>({});
  const [sendOption, setSendOption] = useState<"now" | "schedule">("now");
  const [scheduledAt, setScheduledAt] = useState<Dayjs | null>(dayjs().add(1, "day").hour(9).minute(0));

  const whatsappConnection = useMemo(
    () => apiConnections.find((c) => c.channel === "whatsapp" && c.status === "connected"),
    [apiConnections],
  );

  useEffect(() => {
    if (!open || channel === "instagram" || !whatsappConnection) {
      setTemplates([]);
      return;
    }
    let cancelled = false;
    setTemplatesLoading(true);
    apiClient
      .listTemplates(whatsappConnection.id, { perPage: 100 })
      .then(({ data }) => {
        if (!cancelled) setTemplates(data.filter((t) => t.status === "approved"));
      })
      .catch(() => {
        if (!cancelled) setTemplates([]);
      })
      .finally(() => {
        if (!cancelled) setTemplatesLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [open, channel, whatsappConnection]);

  const selectedTemplate = templates.find((t) => t.id === selectedTemplateId) ?? null;
  const selectedFolderSummary = folders.find((f) => f.id === phonebookFolderId) ?? null;
  const [selectedFolderDetail, setSelectedFolderDetail] = useState<PhonebookFolder | null>(null);

  useEffect(() => {
    if (!open) return;
    apiClient
      .listPhonebookFolders({ perPage: 100 })
      .then(({ data }) => setFolders(data))
      .catch(() => setFolders([]));
  }, [open]);

  useEffect(() => {
    if (!open) return;
    apiClient
      .listVoiceAgents({ perPage: 100 })
      .then(({ data }) => setVoiceAgents(data.filter((a) => a.status === "active")))
      .catch(() => setVoiceAgents([]));
    apiClient
      .listWhatsappCallFlows({ perPage: 100 })
      .then(({ data }) => setCallFlows(data.filter((f) => f.status === "active")))
      .catch(() => setCallFlows([]));
  }, [open]);

  useEffect(() => {
    if (!phonebookFolderId) {
      setSelectedFolderDetail(null);
      return;
    }
    let cancelled = false;
    apiClient
      .getPhonebookFolder(phonebookFolderId)
      .then((folder) => {
        if (!cancelled) setSelectedFolderDetail(folder);
      })
      .catch(() => {
        if (!cancelled) setSelectedFolderDetail(null);
      });
    return () => {
      cancelled = true;
    };
  }, [phonebookFolderId]);

  const isCallMode = campaignMode !== "message";

  const recipientCount = useMemo(() => {
    if (audienceMode === "folder") {
      if (!selectedFolderDetail) return 0;
      return (selectedFolderDetail.contacts ?? []).filter(
        (c) => isCallMode || channel === "both" || c.channel === channel,
      ).length;
    }
    return contacts.filter(
      (c) => c.tags.includes(audienceTag) && (isCallMode || channel === "both" || c.channel === channel),
    ).length;
  }, [audienceMode, selectedFolderDetail, contacts, audienceTag, channel, isCallMode]);

  function resetAndClose() {
    setActiveStep(0);
    setName("");
    setCampaignMode("message");
    setChannel("whatsapp");
    setSelectedVoiceAgentId("");
    setSelectedCallFlowId("");
    setAudienceMode("tag");
    setAudienceTag("VIP");
    setPhonebookFolderId("");
    setMessageMode("custom");
    setMessage("");
    setAttachmentEnabled(false);
    setAttachmentType("image");
    setAttachmentSource("url");
    setAttachmentUrl("");
    setAttachmentFile(null);
    setSelectedTemplateId("");
    setTemplateVariableValues({});
    setSendOption("now");
    onClose();
  }

  function handleCreate() {
    const effectiveMessage =
      messageMode === "template" && selectedTemplate ? selectedTemplate.body : message.trim();

    onCreate({
      name: name.trim() || "Untitled Campaign",
      channel: isCallMode ? undefined : channel,
      message: isCallMode ? undefined : effectiveMessage,
      attachmentType: !isCallMode && attachmentEnabled ? attachmentType : null,
      attachmentUrl: !isCallMode && attachmentEnabled && attachmentSource === "url" ? attachmentUrl.trim() : null,
      attachmentFile: !isCallMode && attachmentEnabled && attachmentSource === "upload" ? attachmentFile : null,
      audienceTag: audienceMode === "tag" ? audienceTag : null,
      phonebookFolderId: audienceMode === "folder" ? phonebookFolderId : null,
      voiceAgentId: campaignMode === "voiceCall" ? selectedVoiceAgentId : null,
      whatsappCallFlowId: campaignMode === "whatsappCall" ? selectedCallFlowId : null,
      scheduledAt: sendOption === "schedule" ? scheduledAt?.toISOString() ?? null : null,
      templateId: messageMode === "template" ? selectedTemplate?.id ?? null : null,
      templateVariables: messageMode === "template" ? templateVariableValues : null,
    });
    resetAndClose();
  }

  const steps = isCallMode ? STEPS.filter((s) => s !== "Message") : STEPS;
  const reviewStepIndex = steps.length - 1;

  const canProceedStep0 =
    name.trim().length > 0 &&
    (audienceMode === "tag" ? audienceTag.length > 0 : phonebookFolderId.length > 0) &&
    (campaignMode === "voiceCall"
      ? selectedVoiceAgentId.length > 0
      : campaignMode === "whatsappCall"
        ? selectedCallFlowId.length > 0
        : true);
  const canProceedStep1 =
    isCallMode ||
    ((messageMode === "template"
      ? Boolean(selectedTemplate) &&
        (selectedTemplate?.variables ?? []).every((v) => templateVariableValues[v]?.trim())
      : message.trim().length > 0) &&
      (!attachmentEnabled ||
        (attachmentSource === "url" ? attachmentUrl.trim().length > 0 : attachmentFile !== null)));

  return (
    <Dialog open={open} onClose={resetAndClose} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Create Broadcast Campaign</DialogTitle>
      <DialogContent>
        <Stepper activeStep={activeStep} sx={{ mb: 3, mt: 1 }}>
          {steps.map((label) => (
            <Step key={label}>
              <StepLabel>{label}</StepLabel>
            </Step>
          ))}
        </Stepper>

        {activeStep === 0 && (
          <Stack spacing={2.5}>
            <TextField
              label="Campaign name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. August Flash Sale"
              fullWidth
              autoFocus
            />
            <Box>
              <Typography variant="body2" sx={{ fontWeight: 600, mb: 1 }}>
                Campaign type
              </Typography>
              <ToggleButtonGroup
                value={campaignMode}
                exclusive
                onChange={(_, value) => value && setCampaignMode(value)}
                size="small"
              >
                <ToggleButton value="message">Message broadcast</ToggleButton>
                <ToggleButton value="voiceCall">
                  <PhoneRoundedIcon sx={{ fontSize: 16, mr: 1 }} />
                  Voice call
                </ToggleButton>
                <ToggleButton value="whatsappCall">
                  <ChatRoundedIcon sx={{ fontSize: 16, mr: 1 }} />
                  WhatsApp call
                </ToggleButton>
              </ToggleButtonGroup>
            </Box>

            {campaignMode === "voiceCall" && (
              <Box>
                <Typography variant="body2" sx={{ fontWeight: 600, mb: 1 }}>
                  Voice agent
                </Typography>
                {voiceAgents.length === 0 ? (
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    No active voice agents found. Create and activate one in the Voice Agent section first.
                  </Typography>
                ) : (
                  <TextField
                    select
                    label="Voice agent"
                    value={selectedVoiceAgentId}
                    onChange={(e) => setSelectedVoiceAgentId(e.target.value)}
                    fullWidth
                  >
                    {voiceAgents.map((agent) => (
                      <MenuItem key={agent.id} value={agent.id}>
                        {agent.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              </Box>
            )}

            {campaignMode === "whatsappCall" && (
              <Box>
                <Typography variant="body2" sx={{ fontWeight: 600, mb: 1 }}>
                  WhatsApp call flow
                </Typography>
                {callFlows.length === 0 ? (
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    No active call flows found. Create and activate one in the WhatsApp Calling section first.
                  </Typography>
                ) : (
                  <TextField
                    select
                    label="Call flow"
                    value={selectedCallFlowId}
                    onChange={(e) => setSelectedCallFlowId(e.target.value)}
                    fullWidth
                  >
                    {callFlows.map((flow) => (
                      <MenuItem key={flow.id} value={flow.id}>
                        {flow.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              </Box>
            )}

            {!isCallMode && (
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
            )}
            <Box>
              <Typography variant="body2" sx={{ fontWeight: 600, mb: 1 }}>
                Target audience
              </Typography>
              <ToggleButtonGroup
                value={audienceMode}
                exclusive
                onChange={(_, value) => value && setAudienceMode(value)}
                size="small"
                sx={{ mb: 1.5 }}
              >
                <ToggleButton value="tag">By tag</ToggleButton>
                <ToggleButton value="folder">By phonebook folder</ToggleButton>
              </ToggleButtonGroup>

              {audienceMode === "tag" ? (
                <Stack direction="row" sx={{ flexWrap: "wrap", gap: 1 }}>
                  {AVAILABLE_TAGS.map((tag) => (
                    <Chip
                      key={tag}
                      label={tag}
                      onClick={() => setAudienceTag(tag)}
                      color={audienceTag === tag ? "primary" : "default"}
                      variant={audienceTag === tag ? "filled" : "outlined"}
                    />
                  ))}
                </Stack>
              ) : folders.length === 0 ? (
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  No phonebook folders yet. Create one in the Phonebook section first.
                </Typography>
              ) : (
                <TextField
                  select
                  label="Phonebook folder"
                  value={phonebookFolderId}
                  onChange={(e) => setPhonebookFolderId(e.target.value)}
                  fullWidth
                >
                  {folders.map((folder) => (
                    <MenuItem key={folder.id} value={folder.id}>
                      {folder.name} ({folder.contactCount})
                    </MenuItem>
                  ))}
                </TextField>
              )}

              <Typography variant="caption" sx={{ color: "text.secondary", mt: 1, display: "block" }}>
                {recipientCount} contact{recipientCount === 1 ? "" : "s"} match this segment.
              </Typography>
            </Box>
          </Stack>
        )}

        {activeStep === 1 && !isCallMode && (
          <Stack spacing={2}>
            {channel !== "instagram" && (
              <ToggleButtonGroup
                value={messageMode}
                exclusive
                onChange={(_, value) => value && setMessageMode(value)}
                size="small"
              >
                <ToggleButton value="custom">Custom message</ToggleButton>
                <ToggleButton value="template">Use Meta template</ToggleButton>
              </ToggleButtonGroup>
            )}

            {messageMode === "custom" || channel === "instagram" ? (
              <>
                <TextField
                  label="Message content"
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  placeholder="Write your broadcast message… Use {{first_name}} to personalize."
                  multiline
                  minRows={5}
                  fullWidth
                  autoFocus
                />
                <Paper variant="outlined" sx={{ p: 1.5, borderRadius: 2, bgcolor: "action.hover" }}>
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    Preview
                  </Typography>
                  <Typography variant="body2" sx={{ mt: 0.5 }}>
                    {message.replace("{{first_name}}", "Amara") || "Your message preview will appear here."}
                  </Typography>
                </Paper>
              </>
            ) : (
              <Stack spacing={2}>
                {templatesLoading ? (
                  <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                    <CircularProgress size={18} />
                    <Typography variant="body2" sx={{ color: "text.secondary" }}>
                      Loading approved templates…
                    </Typography>
                  </Stack>
                ) : templates.length === 0 ? (
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    No approved WhatsApp templates found. Sync templates from Meta in Settings → API
                    Connections first.
                  </Typography>
                ) : (
                  <TextField
                    select
                    label="Approved template"
                    value={selectedTemplateId}
                    onChange={(e) => {
                      setSelectedTemplateId(e.target.value);
                      setTemplateVariableValues({});
                    }}
                    fullWidth
                  >
                    {templates.map((template) => (
                      <MenuItem key={template.id} value={template.id}>
                        {template.name} ({template.category})
                      </MenuItem>
                    ))}
                  </TextField>
                )}

                {selectedTemplate && (
                  <>
                    <Paper variant="outlined" sx={{ p: 1.5, borderRadius: 2, bgcolor: "action.hover" }}>
                      <Typography variant="caption" sx={{ color: "text.secondary" }}>
                        Template body
                      </Typography>
                      <Typography variant="body2" sx={{ mt: 0.5 }}>
                        {selectedTemplate.body}
                      </Typography>
                    </Paper>
                    {selectedTemplate.variables.length > 0 && (
                      <Stack spacing={1.5}>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                          Fill in template variables
                        </Typography>
                        {selectedTemplate.variables.map((variable) => (
                          <TextField
                            key={variable}
                            label={`Variable {{${variable}}}`}
                            value={templateVariableValues[variable] ?? ""}
                            onChange={(e) =>
                              setTemplateVariableValues((prev) => ({ ...prev, [variable]: e.target.value }))
                            }
                            fullWidth
                            size="small"
                          />
                        ))}
                      </Stack>
                    )}
                  </>
                )}
              </Stack>
            )}

            <Box>
              <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  Image / video attachment
                </Typography>
                <ToggleButtonGroup
                  value={attachmentEnabled ? "on" : "off"}
                  exclusive
                  size="small"
                  onChange={(_, value) => value && setAttachmentEnabled(value === "on")}
                >
                  <ToggleButton value="off">None</ToggleButton>
                  <ToggleButton value="on">Add media</ToggleButton>
                </ToggleButtonGroup>
              </Stack>

              {attachmentEnabled && (
                <Stack spacing={1.5} sx={{ mt: 1.5 }}>
                  <ToggleButtonGroup
                    value={attachmentType}
                    exclusive
                    size="small"
                    onChange={(_, value) => value && setAttachmentType(value)}
                  >
                    <ToggleButton value="image">Image</ToggleButton>
                    <ToggleButton value="video">Video</ToggleButton>
                  </ToggleButtonGroup>

                  <ToggleButtonGroup
                    value={attachmentSource}
                    exclusive
                    size="small"
                    onChange={(_, value) => value && setAttachmentSource(value)}
                  >
                    <ToggleButton value="url">Paste URL</ToggleButton>
                    <ToggleButton value="upload">Upload file</ToggleButton>
                  </ToggleButtonGroup>

                  {attachmentSource === "url" ? (
                    <TextField
                      label={attachmentType === "image" ? "Image URL" : "Video URL"}
                      value={attachmentUrl}
                      onChange={(e) => setAttachmentUrl(e.target.value)}
                      placeholder="https://example.com/media.jpg"
                      fullWidth
                    />
                  ) : (
                    <Button variant="outlined" component="label">
                      {attachmentFile ? attachmentFile.name : `Choose ${attachmentType} file`}
                      <input
                        type="file"
                        hidden
                        accept={attachmentType === "image" ? "image/*" : "video/*"}
                        onChange={(e) => setAttachmentFile(e.target.files?.[0] ?? null)}
                      />
                    </Button>
                  )}

                  {attachmentSource === "url" && attachmentUrl.trim() && attachmentType === "image" && (
                    <Box
                      component="img"
                      src={attachmentUrl}
                      sx={{ maxHeight: 160, borderRadius: 2, objectFit: "contain" }}
                    />
                  )}
                  {attachmentSource === "upload" && attachmentFile && attachmentType === "image" && (
                    <Box
                      component="img"
                      src={URL.createObjectURL(attachmentFile)}
                      sx={{ maxHeight: 160, borderRadius: 2, objectFit: "contain" }}
                    />
                  )}
                </Stack>
              )}
            </Box>
          </Stack>
        )}

        {activeStep === reviewStepIndex && (
          <Stack spacing={2.5}>
            <Paper variant="outlined" sx={{ p: 2, borderRadius: 2 }}>
              <Stack spacing={1}>
                <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    Campaign
                  </Typography>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {name || "Untitled Campaign"}
                  </Typography>
                </Stack>
                <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    Audience
                  </Typography>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {audienceMode === "tag" ? audienceTag : selectedFolderSummary?.name ?? "—"} ({recipientCount}{" "}
                    recipients)
                  </Typography>
                </Stack>
                <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    {campaignMode === "voiceCall"
                      ? "Voice agent"
                      : campaignMode === "whatsappCall"
                        ? "WhatsApp call flow"
                        : "Channel"}
                  </Typography>
                  <Typography variant="body2" sx={{ fontWeight: 600, textTransform: "capitalize" }}>
                    {campaignMode === "voiceCall"
                      ? (voiceAgents.find((a) => a.id === selectedVoiceAgentId)?.name ?? "—")
                      : campaignMode === "whatsappCall"
                        ? (callFlows.find((f) => f.id === selectedCallFlowId)?.name ?? "—")
                        : channel}
                  </Typography>
                </Stack>
                {!isCallMode && messageMode === "template" && selectedTemplate && (
                  <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                    <Typography variant="body2" sx={{ color: "text.secondary" }}>
                      Template
                    </Typography>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                      {selectedTemplate.name}
                    </Typography>
                  </Stack>
                )}
              </Stack>
            </Paper>

            <RadioGroup value={sendOption} onChange={(e) => setSendOption(e.target.value as "now" | "schedule")}>
              <FormControlLabel value="now" control={<Radio />} label="Send immediately" />
              <FormControlLabel value="schedule" control={<Radio />} label="Schedule for later" />
            </RadioGroup>

            {sendOption === "schedule" && (
              <DateTimePicker
                label="Scheduled date & time"
                value={scheduledAt}
                onChange={(value) => setScheduledAt(value)}
                sx={{ width: "100%" }}
              />
            )}
          </Stack>
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={resetAndClose} color="inherit">
          Cancel
        </Button>
        <Box sx={{ flex: 1 }} />
        {activeStep > 0 && <Button onClick={() => setActiveStep((s) => s - 1)}>Back</Button>}
        {activeStep < steps.length - 1 ? (
          <Button
            variant="contained"
            onClick={() => setActiveStep((s) => s + 1)}
            disabled={(activeStep === 0 && !canProceedStep0) || (activeStep === 1 && !canProceedStep1)}
          >
            Next
          </Button>
        ) : (
          <Button variant="contained" onClick={handleCreate}>
            {sendOption === "now" ? "Send Broadcast" : "Schedule Broadcast"}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
