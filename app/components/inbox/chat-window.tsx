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
import DoneAllRoundedIcon from "@mui/icons-material/DoneAllRounded";
import DoneRoundedIcon from "@mui/icons-material/DoneRounded";
import ErrorOutlineRoundedIcon from "@mui/icons-material/ErrorOutlineRounded";
import SendRoundedIcon from "@mui/icons-material/SendRounded";
import AttachFileRoundedIcon from "@mui/icons-material/AttachFileRounded";
import BoltRoundedIcon from "@mui/icons-material/BoltRounded";
import SmartToyRoundedIcon from "@mui/icons-material/SmartToyRounded";
import InsertDriveFileRoundedIcon from "@mui/icons-material/InsertDriveFileRounded";
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { QUICK_REPLIES } from "~/data/types";
import type { AiAssistantSettings, Contact, Conversation, Message, TeamMember } from "~/data/types";
import { formatTime } from "~/utils/format";
import { useAiChatCompletion } from "~/hooks/use-ai-chat-completion";
import { apiClient } from "~/utils/api-client";
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
      <Stack direction="row" className={styles.header} sx={{ alignItems: "center", gap: 1.5 }}>
        <button type="button" className={styles.contactButton} onClick={onToggleDetails}>
          <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 40, height: 40 }} />
          <Box sx={{ textAlign: "start" }}>
            <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }}>
              <Typography variant="body1" sx={{ fontWeight: 700 }}>
                {contact.name}
              </Typography>
              <ChannelIcon channel={conversation.channel} size={15} />
            </Stack>
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              {contact.handle}
            </Typography>
          </Box>
        </button>
        <Box sx={{ flex: 1 }} />
        {conversation.channel === "whatsapp" && (
          <Tooltip title={`Call ${contact.name} on WhatsApp`}>
            <span>
              <IconButton size="small" onClick={handleQuickCall}>
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
          sx={{ minWidth: 150, fontSize: "0.85rem" }}
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
          sx={{ minWidth: 130, fontSize: "0.85rem" }}
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
                  alt="Attachment"
                  sx={{ display: "block", width: "100%", maxWidth: 260, borderRadius: 1.5, mb: 0.75 }}
                />
              )}
              {message.attachmentUrl && message.attachmentType === "video" && (
                <Box sx={{ mb: 0.75 }}>
                  <video controls src={message.attachmentUrl} style={{ width: "100%", maxWidth: 260, borderRadius: 6 }} />
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
                  <Typography variant="caption">Proposal.pdf</Typography>
                </Stack>
              )}
              <Typography variant="body2">{message.text}</Typography>
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
            accept="image/*,video/*"
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
    </Box>
  );
}
