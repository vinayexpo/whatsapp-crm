import { useEffect, useRef, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import IconButton from "@mui/material/IconButton";
import TextField from "@mui/material/TextField";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import Chip from "@mui/material/Chip";
import Select from "@mui/material/Select";
import Paper from "@mui/material/Paper";
import Tooltip from "@mui/material/Tooltip";
import CircularProgress from "@mui/material/CircularProgress";
import Snackbar from "@mui/material/Snackbar";
import Alert from "@mui/material/Alert";
import Dialog from "@mui/material/Dialog";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import DoneAllRoundedIcon from "@mui/icons-material/DoneAllRounded";
import DoneRoundedIcon from "@mui/icons-material/DoneRounded";
import ErrorOutlineRoundedIcon from "@mui/icons-material/ErrorOutlineRounded";
import SendRoundedIcon from "@mui/icons-material/SendRounded";
import AttachFileRoundedIcon from "@mui/icons-material/AttachFileRounded";
import BoltRoundedIcon from "@mui/icons-material/BoltRounded";
import SmartToyRoundedIcon from "@mui/icons-material/SmartToyRounded";
import InsertDriveFileRoundedIcon from "@mui/icons-material/InsertDriveFileRounded";
import DownloadRoundedIcon from "@mui/icons-material/DownloadRounded";
import PermMediaRoundedIcon from "@mui/icons-material/PermMediaRounded";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { QUICK_REPLIES } from "~/data/types";
import type { AiAssistantSettings, Contact, Conversation, Message, TeamMember } from "~/data/types";
import { formatTime } from "~/utils/format";
import { useAiChatCompletion } from "~/hooks/use-ai-chat-completion";
import { apiClient } from "~/utils/api-client";
import { WhatsappText } from "~/utils/whatsapp-text";
import { WhatsappCallSummary } from "./whatsapp-call-summary";
import { WhatsappCallPanel } from "./whatsapp-call-panel";
import classNames from "classnames";
import styles from "./chat-window.module.css";

interface ChatWindowProps {
  conversation: Conversation;
  contact: Contact;
  messages: Message[];
  hasMoreOlderMessages?: boolean;
  onLoadOlderMessages?: () => void;
  teamMembers: TeamMember[];
  onSendMessage: (text: string, attachmentFile?: File | null) => void;
  onStatusChange: (status: Conversation["status"]) => void;
  onAssign: (userId: string | null) => void;
  onToggleDetails: () => void;
  onBack?: () => void;
  aiAssistantSettings: AiAssistantSettings;
}

export function ChatWindow({
  conversation,
  contact,
  messages,
  hasMoreOlderMessages = false,
  onLoadOlderMessages,
  teamMembers,
  onSendMessage,
  onStatusChange,
  onAssign,
  onToggleDetails,
  onBack,
  aiAssistantSettings,
}: ChatWindowProps) {
  const [draft, setDraft] = useState("");
  const [quickReplyAnchor, setQuickReplyAnchor] = useState<HTMLElement | null>(null);
  const [attachmentFile, setAttachmentFile] = useState<File | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const {
    sendChatCompletion,
    isLoading: isAiLoading,
    error: aiError,
    isConfigured: isAiConfigured,
  } = useAiChatCompletion(aiAssistantSettings);
  const [aiErrorOpen, setAiErrorOpen] = useState(false);
  const [callSnackbar, setCallSnackbar] = useState<{ severity: "success" | "error"; message: string } | null>(null);
  const [callPanelOpen, setCallPanelOpen] = useState(false);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const prevScrollHeightRef = useRef<number | null>(null);
  const [lightbox, setLightbox] = useState<{ url: string; type: "image" | "video" } | null>(null);
  const [mediaOpen, setMediaOpen] = useState(false);
  const [mediaItems, setMediaItems] = useState<Message[]>([]);
  const [mediaLoading, setMediaLoading] = useState(false);
  const [mediaTab, setMediaTab] = useState<"image" | "video" | "document">("image");
  const [downloadingId, setDownloadingId] = useState<string | null>(null);

  useEffect(() => {
    if (!mediaOpen) return;
    let cancelled = false;
    setMediaLoading(true);
    apiClient
      .listConversationMedia(conversation.id)
      .then((items) => {
        if (!cancelled) setMediaItems(items);
      })
      .catch(() => {
        if (!cancelled) setMediaItems([]);
      })
      .finally(() => {
        if (!cancelled) setMediaLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [mediaOpen, conversation.id]);

  async function handleDownloadAttachment(message: Message) {
    const name = message.attachmentName || message.text || "attachment";
    setDownloadingId(message.id);
    try {
      await apiClient.downloadMessageAttachment(message.id, name);
    } catch {
      setCallSnackbar({ severity: "error", message: "Download failed. Please try again." });
    } finally {
      setDownloadingId(null);
    }
  }

  useEffect(() => {
    if (prevScrollHeightRef.current !== null && scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight - prevScrollHeightRef.current;
      prevScrollHeightRef.current = null;
      setLoadingOlder(false);
      return;
    }
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" });
  }, [messages.length, conversation.id]);

  function handleScroll() {
    const el = scrollRef.current;
    if (!el || !onLoadOlderMessages || !hasMoreOlderMessages || loadingOlder) return;
    if (el.scrollTop < 80) {
      prevScrollHeightRef.current = el.scrollHeight;
      setLoadingOlder(true);
      onLoadOlderMessages();
    }
  }

  function handleSend() {
    const trimmed = draft.trim();
    if (!trimmed && !attachmentFile) return;
    onSendMessage(trimmed, attachmentFile);
    setDraft("");
    setAttachmentFile(null);
    if (fileInputRef.current) fileInputRef.current.value = "";
  }

  function handleAttachmentSelected(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (file) setAttachmentFile(file);
  }

  function handleQuickCall() {
    setCallPanelOpen(true);
  }

  async function handleAiAssist() {
    if (!isAiConfigured || isAiLoading) return;
    try {
      const reply = await sendChatCompletion([
        {
          role: "system",
          content: `You are a helpful support agent replying to ${contact.name} on ${conversation.channel}. Write a concise, friendly reply to their latest message. Only output the reply text, no preamble.`,
        },
        ...messages.slice(-10).map((m) => ({
          role: m.direction === "inbound" ? ("user" as const) : ("assistant" as const),
          content: m.text,
        })),
      ]);
      setDraft(reply);
    } catch {
      setAiErrorOpen(true);
    }
  }

  return (
    <Box className={styles.container}>
      <Stack
        direction="row"
        className={styles.header}
        sx={{
          alignItems: "center",
          gap: 1.5,
          overflowX: { xs: "auto", sm: "visible" },
          flexWrap: { xs: "nowrap", sm: "wrap" },
          "&::-webkit-scrollbar": { display: "none" },
          scrollbarWidth: "none",
        }}
      >
        {onBack && (
          <IconButton
            size="small"
            onClick={onBack}
            sx={{ display: { xs: "inline-flex", sm: "none" }, flexShrink: 0 }}
            aria-label="Back to conversations"
          >
            <ArrowBackRoundedIcon fontSize="small" />
          </IconButton>
        )}
        <button type="button" className={styles.contactButton} style={{ flexShrink: 0 }} onClick={onToggleDetails}>
          <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 40, height: 40 }} />
          <Box sx={{ textAlign: "start" }}>
            <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }}>
              <Typography variant="body1" sx={{ fontWeight: 700, whiteSpace: "nowrap" }}>
                {contact.name}
              </Typography>
              <ChannelIcon channel={conversation.channel} size={15} />
            </Stack>
            <Typography variant="caption" sx={{ color: "text.secondary", whiteSpace: "nowrap" }}>
              {contact.handle}
            </Typography>
          </Box>
        </button>
        <Box sx={{ flex: 1, minWidth: { xs: 0, sm: "auto" } }} />
        <Tooltip title="Media, links and docs">
          <IconButton size="small" onClick={() => setMediaOpen(true)} sx={{ flexShrink: 0 }}>
            <PermMediaRoundedIcon fontSize="small" />
          </IconButton>
        </Tooltip>
        {conversation.channel === "whatsapp" && (
          <Tooltip title={`Call ${contact.name} on WhatsApp`}>
            <span>
              <IconButton size="small" onClick={handleQuickCall} sx={{ flexShrink: 0 }}>
                <CallRoundedIcon fontSize="small" />
              </IconButton>
            </span>
          </Tooltip>
        )}
        <Select
          size="small"
          displayEmpty
          value={conversation.assignedTo?.id ?? ""}
          onChange={(e) => onAssign(e.target.value === "" ? null : (e.target.value as string))}
          renderValue={(value) =>
            value === "" ? (
              "Unassigned"
            ) : (
              <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }}>
                <Avatar src={conversation.assignedTo?.avatarUrl} alt={conversation.assignedTo?.name} sx={{ width: 20, height: 20 }} />
                <Typography variant="body2" noWrap sx={{ maxWidth: 100 }}>
                  {conversation.assignedTo?.name}
                </Typography>
              </Stack>
            )
          }
          sx={{ minWidth: 150, fontSize: "0.85rem", flexShrink: 0 }}
        >
          <MenuItem value="">Unassigned</MenuItem>
          {teamMembers.map((member) => (
            <MenuItem key={member.id} value={member.id}>
              <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                <Avatar src={member.avatarUrl} alt={member.name} sx={{ width: 22, height: 22 }} />
                <Typography variant="body2">{member.name}</Typography>
              </Stack>
            </MenuItem>
          ))}
        </Select>
        <Select
          size="small"
          value={conversation.status}
          onChange={(e) => onStatusChange(e.target.value as Conversation["status"])}
          sx={{ minWidth: 130, fontSize: "0.85rem", flexShrink: 0 }}
        >
          <MenuItem value="open">Open</MenuItem>
          <MenuItem value="pending">Pending</MenuItem>
          <MenuItem value="resolved">Resolved</MenuItem>
          <MenuItem value="archived">Archived</MenuItem>
        </Select>
      </Stack>

      {conversation.channel === "whatsapp" && <WhatsappCallSummary key={contact.id} contactId={contact.id} />}
      {conversation.channel === "whatsapp" && (
        <WhatsappCallPanel
          open={callPanelOpen}
          contact={contact}
          conversationId={conversation.id}
          onClose={() => setCallPanelOpen(false)}
        />
      )}

      <Box ref={scrollRef} className={styles.messages} onScroll={handleScroll}>
        {loadingOlder && (
          <Stack sx={{ alignItems: "center", py: 1 }}>
            <CircularProgress size={18} />
          </Stack>
        )}
        {messages.map((message) => (
          <Box
            key={message.id}
            className={classNames(styles.messageRow, {
              [styles.messageRowOutbound]: message.direction === "outbound",
            })}
          >
            <Paper
              elevation={0}
              className={classNames(styles.bubble, {
                [styles.bubbleOutbound]: message.direction === "outbound",
              })}
              sx={{
                backgroundColor: message.direction === "outbound" ? "#d9fdd3" : "#ffffff",
              }}
            >
              {message.attachmentUrl && message.attachmentType === "image" && (
                <Box
                  component="img"
                  src={message.attachmentUrl}
                  alt={message.attachmentName ?? "Attachment"}
                  onClick={() => setLightbox({ url: message.attachmentUrl as string, type: "image" })}
                  sx={{
                    display: "block",
                    width: "100%",
                    maxWidth: 260,
                    borderRadius: 1.5,
                    mb: 0.75,
                    cursor: "zoom-in",
                  }}
                />
              )}
              {message.attachmentUrl && message.attachmentType === "video" && (
                <Box sx={{ mb: 0.75, position: "relative" }}>
                  <video controls src={message.attachmentUrl} style={{ width: "100%", maxWidth: 260, borderRadius: 6 }} />
                  <Tooltip title="Open video">
                    <IconButton
                      size="small"
                      onClick={() => setLightbox({ url: message.attachmentUrl as string, type: "video" })}
                      sx={{
                        position: "absolute",
                        top: 4,
                        right: 4,
                        bgcolor: "rgba(0,0,0,0.55)",
                        color: "#fff",
                        "&:hover": { bgcolor: "rgba(0,0,0,0.75)" },
                      }}
                    >
                      <PermMediaRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                </Box>
              )}
              {message.attachmentUrl && message.attachmentType === "audio" && (
                <Box sx={{ mb: 0.75 }}>
                  <audio controls src={message.attachmentUrl} style={{ width: "100%", maxWidth: 260 }} />
                </Box>
              )}
              {message.attachmentUrl && message.attachmentType === "document" && (
                <Stack
                  direction="row"
                  sx={{
                    alignItems: "center",
                    gap: 1,
                    bgcolor: "rgba(0,0,0,0.06)",
                    borderRadius: 1.5,
                    p: 1,
                    mb: 0.75,
                  }}
                >
                  <InsertDriveFileRoundedIcon fontSize="small" />
                  <Box sx={{ minWidth: 0, flex: 1 }}>
                    <Typography variant="caption" sx={{ display: "block", fontWeight: 600, wordBreak: "break-all" }}>
                      {message.attachmentName || message.text || "Document"}
                    </Typography>
                    {message.attachmentName && message.text && (
                      <Typography variant="caption" sx={{ display: "block", opacity: 0.7, wordBreak: "break-all" }}>
                        {message.text}
                      </Typography>
                    )}
                  </Box>
                  <Tooltip title="Download">
                    <span>
                      <IconButton
                        size="small"
                        onClick={() => handleDownloadAttachment(message)}
                        disabled={downloadingId === message.id}
                      >
                        {downloadingId === message.id ? (
                          <CircularProgress size={16} />
                        ) : (
                          <DownloadRoundedIcon fontSize="small" />
                        )}
                      </IconButton>
                    </span>
                  </Tooltip>
                </Stack>
              )}
              <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>
                <WhatsappText text={message.text} />
              </Typography>
              <Stack direction="row" sx={{ alignItems: "center", justifyContent: "flex-end", gap: 0.4, mt: 0.5 }}>
                <Typography variant="caption" sx={{ opacity: 0.7, fontSize: "0.68rem" }}>
                  {formatTime(message.timestamp)}
                </Typography>
                {message.direction === "outbound" &&
                  (message.status === "failed" ? (
                    <Tooltip title="Not delivered">
                      <ErrorOutlineRoundedIcon sx={{ fontSize: 14, color: "error.main" }} />
                    </Tooltip>
                  ) : message.status === "read" ? (
                    <DoneAllRoundedIcon sx={{ fontSize: 14, color: "#53BDEB" }} />
                  ) : message.status === "delivered" ? (
                    <DoneAllRoundedIcon sx={{ fontSize: 14, opacity: 0.7 }} />
                  ) : (
                    <DoneRoundedIcon sx={{ fontSize: 14, opacity: 0.7 }} />
                  ))}
              </Stack>
            </Paper>
          </Box>
        ))}
      </Box>

      <Box className={styles.composer}>
        {attachmentFile && (
          <Stack direction="row" spacing={0.75} sx={{ mb: 1 }}>
            <Chip
              size="small"
              icon={<InsertDriveFileRoundedIcon fontSize="small" />}
              label={attachmentFile.name}
              onDelete={() => {
                setAttachmentFile(null);
                if (fileInputRef.current) fileInputRef.current.value = "";
              }}
            />
          </Stack>
        )}
        <Stack direction="row" spacing={1} sx={{ alignItems: "flex-end" }}>
          <input
            ref={fileInputRef}
            type="file"
            hidden
            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
            onChange={handleAttachmentSelected}
          />
          <IconButton size="small" onClick={() => fileInputRef.current?.click()}>
            <AttachFileRoundedIcon fontSize="small" />
          </IconButton>
          <IconButton size="small" onClick={(e) => setQuickReplyAnchor(e.currentTarget)}>
            <BoltRoundedIcon fontSize="small" />
          </IconButton>
          <Tooltip title={isAiConfigured ? "AI Assist: draft a reply" : "Configure AI Assistant in Settings"}>
            <span>
              <IconButton size="small" onClick={handleAiAssist} disabled={!isAiConfigured || isAiLoading}>
                {isAiLoading ? <CircularProgress size={16} /> : <SmartToyRoundedIcon fontSize="small" />}
              </IconButton>
            </span>
          </Tooltip>
          <Menu anchorEl={quickReplyAnchor} open={Boolean(quickReplyAnchor)} onClose={() => setQuickReplyAnchor(null)}>
            {QUICK_REPLIES.map((reply) => (
              <MenuItem
                key={reply}
                onClick={() => {
                  setDraft(reply);
                  setQuickReplyAnchor(null);
                }}
                sx={{ maxWidth: 320, whiteSpace: "normal" }}
              >
                {reply}
              </MenuItem>
            ))}
          </Menu>
          <TextField
            fullWidth
            multiline
            maxRows={4}
            size="small"
            placeholder="Type a message…"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                handleSend();
              }
            }}
          />
          <IconButton color="primary" onClick={handleSend} disabled={!draft.trim() && !attachmentFile}>
            <SendRoundedIcon />
          </IconButton>
        </Stack>
        <Stack direction="row" spacing={0.75} sx={{ mt: 1, flexWrap: "wrap" }}>
          {QUICK_REPLIES.slice(0, 2).map((reply) => (
            <Chip key={reply} label={reply} size="small" variant="outlined" onClick={() => setDraft(reply)} />
          ))}
        </Stack>
      </Box>

      <Snackbar open={aiErrorOpen} autoHideDuration={4000} onClose={() => setAiErrorOpen(false)}>
        <Alert severity="error" onClose={() => setAiErrorOpen(false)}>
          {aiError ?? "Couldn't generate an AI reply."}
        </Alert>
      </Snackbar>

      <Snackbar open={Boolean(callSnackbar)} autoHideDuration={4000} onClose={() => setCallSnackbar(null)}>
        {callSnackbar ? (
          <Alert severity={callSnackbar.severity} onClose={() => setCallSnackbar(null)}>
            {callSnackbar.message}
          </Alert>
        ) : undefined}
      </Snackbar>

      <Dialog open={Boolean(lightbox)} onClose={() => setLightbox(null)} maxWidth="lg" fullWidth>
        <DialogTitle sx={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 2 }}>
          {lightbox?.type === "video" ? "Video" : "Photo"}
          <IconButton size="small" onClick={() => setLightbox(null)} aria-label="Close preview">
            <CloseRoundedIcon fontSize="small" />
          </IconButton>
        </DialogTitle>
        <DialogContent sx={{ display: "flex", justifyContent: "center", bgcolor: "#111", p: 1 }}>
          {lightbox?.type === "image" && (
            <Box
              component="img"
              src={lightbox.url}
              alt="Attachment preview"
              sx={{ maxWidth: "100%", maxHeight: "80vh", objectFit: "contain" }}
            />
          )}
          {lightbox?.type === "video" && (
            <video
              key={lightbox.url}
              controls
              autoPlay
              src={lightbox.url}
              style={{ maxWidth: "100%", maxHeight: "80vh" }}
            />
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={mediaOpen} onClose={() => setMediaOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle sx={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 2 }}>
          Media, links and docs
          <IconButton size="small" onClick={() => setMediaOpen(false)} aria-label="Close media">
            <CloseRoundedIcon fontSize="small" />
          </IconButton>
        </DialogTitle>
        <DialogContent>
          <Tabs value={mediaTab} onChange={(_, value) => setMediaTab(value)} sx={{ mb: 2 }}>
            <Tab value="image" label={`Photos (${mediaItems.filter((m) => m.attachmentType === "image").length})`} />
            <Tab value="video" label={`Videos (${mediaItems.filter((m) => m.attachmentType === "video").length})`} />
            <Tab value="document" label={`Docs (${mediaItems.filter((m) => m.attachmentType === "document").length})`} />
          </Tabs>

          {mediaLoading && (
            <Stack sx={{ alignItems: "center", py: 4 }}>
              <CircularProgress size={24} />
            </Stack>
          )}

          {!mediaLoading && mediaItems.filter((m) => m.attachmentType === mediaTab).length === 0 && (
            <Typography variant="body2" sx={{ color: "text.secondary", py: 3, textAlign: "center" }}>
              Nothing here yet.
            </Typography>
          )}

          {!mediaLoading && mediaTab === "document" ? (
            <Stack spacing={1}>
              {mediaItems
                .filter((m) => m.attachmentType === "document")
                .map((message) => (
                  <Stack
                    key={message.id}
                    direction="row"
                    sx={{
                      alignItems: "center",
                      gap: 1,
                      border: "1px solid",
                      borderColor: "divider",
                      borderRadius: 1.5,
                      p: 1,
                    }}
                  >
                    <InsertDriveFileRoundedIcon fontSize="small" sx={{ color: "primary.main" }} />
                    <Box sx={{ minWidth: 0, flex: 1 }}>
                      <Typography variant="body2" sx={{ fontWeight: 600, wordBreak: "break-all" }}>
                        {message.attachmentName || message.text || "Document"}
                      </Typography>
                      <Typography variant="caption" sx={{ color: "text.secondary" }}>
                        {formatTime(message.timestamp)}
                      </Typography>
                    </Box>
                    <IconButton
                      size="small"
                      onClick={() => handleDownloadAttachment(message)}
                      disabled={downloadingId === message.id}
                      aria-label="Download document"
                    >
                      {downloadingId === message.id ? (
                        <CircularProgress size={16} />
                      ) : (
                        <DownloadRoundedIcon fontSize="small" />
                      )}
                    </IconButton>
                  </Stack>
                ))}
            </Stack>
          ) : (
            !mediaLoading && (
              <Box
                sx={{
                  display: "grid",
                  gridTemplateColumns: "repeat(auto-fill, minmax(120px, 1fr))",
                  gap: 1,
                }}
              >
                {mediaItems
                  .filter((m) => m.attachmentType === mediaTab && m.attachmentUrl)
                  .map((message) => (
                    <Box
                      key={message.id}
                      onClick={() =>
                        setLightbox({
                          url: message.attachmentUrl as string,
                          type: mediaTab === "video" ? "video" : "image",
                        })
                      }
                      sx={{
                        position: "relative",
                        paddingTop: "100%",
                        borderRadius: 1.5,
                        overflow: "hidden",
                        cursor: "pointer",
                        bgcolor: "action.hover",
                      }}
                    >
                      {mediaTab === "image" ? (
                        <Box
                          component="img"
                          src={message.attachmentUrl}
                          alt={message.attachmentName ?? "Photo"}
                          sx={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover" }}
                        />
                      ) : (
                        <Box
                          component="video"
                          src={message.attachmentUrl}
                          sx={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover" }}
                        />
                      )}
                    </Box>
                  ))}
              </Box>
            )
          )}
        </DialogContent>
      </Dialog>
    </Box>
  );
}
