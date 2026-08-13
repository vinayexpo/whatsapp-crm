import { useState } from "react";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Button from "@mui/material/Button";
import ContentCopyRoundedIcon from "@mui/icons-material/ContentCopyRounded";
import type { Chatbot } from "~/data/types";

interface ChatbotEmbedPanelProps {
  chatbot: Chatbot;
}

export function ChatbotEmbedPanel({ chatbot }: ChatbotEmbedPanelProps) {
  const [copied, setCopied] = useState(false);

  const origin = typeof window !== "undefined" ? window.location.origin : "";
  const snippet = `<script src="${origin}/widget.js?v=2" data-widget-key="${chatbot.widgetKey}"></script>`;

  async function handleCopy() {
    await navigator.clipboard.writeText(snippet);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  return (
    <Stack spacing={2.5}>
      <Typography variant="caption" sx={{ color: "text.secondary" }}>
        Paste this snippet before the closing <code>&lt;/body&gt;</code> tag of any website to embed this chatbot as
        a chat widget.
      </Typography>

      <Paper
        variant="outlined"
        sx={{
          borderRadius: 2,
          p: 2,
          bgcolor: "action.hover",
          fontFamily: "monospace",
          fontSize: "0.8rem",
          wordBreak: "break-all",
        }}
      >
        {snippet}
      </Paper>

      <Button
        variant="outlined"
        startIcon={<ContentCopyRoundedIcon />}
        onClick={handleCopy}
        sx={{ alignSelf: "flex-start" }}
      >
        {copied ? "Copied!" : "Copy snippet"}
      </Button>

      <Stack spacing={0.5}>
        <Typography variant="subtitle2">Widget key</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary", fontFamily: "monospace" }}>
          {chatbot.widgetKey}
        </Typography>
      </Stack>
    </Stack>
  );
}
