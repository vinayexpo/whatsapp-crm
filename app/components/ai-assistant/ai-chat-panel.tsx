import { useEffect, useRef, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import IconButton from "@mui/material/IconButton";
import Alert from "@mui/material/Alert";
import CircularProgress from "@mui/material/CircularProgress";
import SendRoundedIcon from "@mui/icons-material/SendRounded";
import SmartToyRoundedIcon from "@mui/icons-material/SmartToyRounded";
import classNames from "classnames";
import type { AiAssistantSettings, AiChatMessage } from "~/data/types";
import { useAiChatCompletion } from "~/hooks/use-ai-chat-completion";
import styles from "./ai-chat-panel.module.css";

const SYSTEM_PROMPT =
  "You are a helpful AI assistant embedded in Creative Connects. Keep replies concise and helpful for a sales/support team.";

interface AiChatPanelProps {
  settings: AiAssistantSettings;
}

export function AiChatPanel({ settings }: AiChatPanelProps) {
  const [messages, setMessages] = useState<AiChatMessage[]>([]);
  const [draft, setDraft] = useState("");
  const { sendChatCompletion, isLoading, error, isConfigured } = useAiChatCompletion(settings);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" });
  }, [messages.length, isLoading]);

  async function handleSend() {
    const trimmed = draft.trim();
    if (!trimmed || isLoading) return;

    const userMessage: AiChatMessage = {
      id: `ai-u-${Date.now()}`,
      role: "user",
      content: trimmed,
      timestamp: new Date().toISOString(),
    };
    const nextMessages = [...messages, userMessage];
    setMessages(nextMessages);
    setDraft("");

    try {
      const reply = await sendChatCompletion([
        { role: "system", content: SYSTEM_PROMPT },
        ...nextMessages.map((m) => ({ role: m.role, content: m.content })),
      ]);
      setMessages((prev) => [
        ...prev,
        { id: `ai-a-${Date.now()}`, role: "assistant", content: reply, timestamp: new Date().toISOString() },
      ]);
    } catch {
      // error is surfaced via the `error` state from the hook
    }
  }

  return (
    <Box className={styles.container}>
      {!isConfigured && (
        <Box sx={{ p: 2, pb: 0 }}>
          <Alert severity="info">
            Add your Base API URL, API Key, and Model in the AI Assistant settings tab to start chatting.
          </Alert>
        </Box>
      )}

      {messages.length === 0 ? (
        <Box className={styles.emptyState}>
          <SmartToyRoundedIcon sx={{ fontSize: 44, color: "primary.main", opacity: 0.6 }} />
          <Typography variant="body1" sx={{ fontWeight: 700 }}>
            Ask the AI Assistant anything
          </Typography>
          <Typography variant="body2" sx={{ color: "text.secondary", maxWidth: 360 }}>
            Draft replies, summarize conversations, or get quick answers powered by your connected model.
          </Typography>
        </Box>
      ) : (
        <Box ref={scrollRef} className={styles.messages}>
          {messages.map((message) => (
            <Box
              key={message.id}
              className={classNames(styles.messageRow, { [styles.messageRowUser]: message.role === "user" })}
            >
              <Box className={classNames(styles.bubble, { [styles.bubbleUser]: message.role === "user" })}>
                <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>
                  {message.content}
                </Typography>
              </Box>
            </Box>
          ))}
          {isLoading && (
            <Stack direction="row" sx={{ alignItems: "center", gap: 1, pl: 0.5 }}>
              <CircularProgress size={16} />
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                Thinking…
              </Typography>
            </Stack>
          )}
        </Box>
      )}

      {error && (
        <Box sx={{ px: 2.5 }}>
          <Alert severity="error" sx={{ mb: 1 }}>
            {error}
          </Alert>
        </Box>
      )}

      <Box className={styles.composer}>
        <Stack direction="row" spacing={1} sx={{ alignItems: "flex-end" }}>
          <TextField
            fullWidth
            multiline
            maxRows={4}
            size="small"
            placeholder="Message the AI Assistant…"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            disabled={!isConfigured}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                handleSend();
              }
            }}
          />
          <IconButton color="primary" onClick={handleSend} disabled={!draft.trim() || isLoading || !isConfigured}>
            <SendRoundedIcon />
          </IconButton>
        </Stack>
      </Box>
    </Box>
  );
}
