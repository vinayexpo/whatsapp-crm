import { useEffect, useRef, useState } from "react";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Paper from "@mui/material/Paper";
import Chip from "@mui/material/Chip";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import CircularProgress from "@mui/material/CircularProgress";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import UploadFileRoundedIcon from "@mui/icons-material/UploadFileRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Chatbot, ChatbotTrainingCandidate, ChatbotTrainingEntry } from "~/data/types";

interface ChatbotTrainingPanelProps {
  chatbot: Chatbot;
}

export function ChatbotTrainingPanel({ chatbot }: ChatbotTrainingPanelProps) {
  const [entries, setEntries] = useState<ChatbotTrainingEntry[]>([]);
  const [question, setQuestion] = useState("");
  const [answer, setAnswer] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [sourceText, setSourceText] = useState("");
  const [sourceFile, setSourceFile] = useState<File | null>(null);
  const [generating, setGenerating] = useState(false);
  const [candidates, setCandidates] = useState<ChatbotTrainingCandidate[]>([]);
  const [savingCandidateIndex, setSavingCandidateIndex] = useState<number | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    apiClient
      .listTrainingEntries(chatbot.id)
      .then(setEntries)
      .catch(() => {
        // entries list stays empty on failure
      });
  }, [chatbot.id]);

  async function handleAdd() {
    if (!question.trim() || !answer.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      const created = await apiClient.createTrainingEntry(chatbot.id, {
        question: question.trim(),
        answer: answer.trim(),
      });
      setEntries((prev) => [created, ...prev]);
      setQuestion("");
      setAnswer("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to add training entry.");
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(entryId: string) {
    try {
      await apiClient.deleteTrainingEntry(chatbot.id, entryId);
      setEntries((prev) => prev.filter((e) => e.id !== entryId));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete training entry.");
    }
  }

  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null;
    setSourceFile(file);
    if (file) setSourceText("");
  }

  function handleSourceTextChange(value: string) {
    setSourceText(value);
    if (value) {
      setSourceFile(null);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  }

  async function handleGenerate() {
    if (!sourceFile && !sourceText.trim()) return;
    setGenerating(true);
    setError(null);
    try {
      const generated = await apiClient.generateTrainingEntries(chatbot.id, {
        file: sourceFile ?? undefined,
        text: sourceFile ? undefined : sourceText.trim(),
      });
      setCandidates(generated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to generate training entries.");
    } finally {
      setGenerating(false);
    }
  }

  async function handleAcceptCandidate(index: number) {
    const candidate = candidates[index];
    if (!candidate) return;
    setSavingCandidateIndex(index);
    setError(null);
    try {
      const created = await apiClient.createTrainingEntry(chatbot.id, {
        question: candidate.question,
        answer: candidate.answer,
        source: "ai",
      });
      setEntries((prev) => [created, ...prev]);
      setCandidates((prev) => prev.filter((_, i) => i !== index));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save training entry.");
    } finally {
      setSavingCandidateIndex(null);
    }
  }

  function handleDiscardCandidate(index: number) {
    setCandidates((prev) => prev.filter((_, i) => i !== index));
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <Typography variant="caption" sx={{ color: "text.secondary" }}>
        Add question &amp; answer pairs the AI will use as grounding context when replying to visitors.
      </Typography>

      <Stack spacing={1.5}>
        <Typography variant="subtitle2">Generate from document or text</Typography>
        <input
          ref={fileInputRef}
          type="file"
          accept=".txt,.md"
          hidden
          onChange={handleFileSelect}
        />
        <Button
          variant="outlined"
          component="label"
          startIcon={<UploadFileRoundedIcon />}
          sx={{ alignSelf: "flex-start" }}
          onClick={() => fileInputRef.current?.click()}
        >
          {sourceFile ? sourceFile.name : "Choose .txt or .md file"}
        </Button>
        <TextField
          fullWidth
          multiline
          minRows={3}
          label="Or paste text"
          value={sourceText}
          onChange={(e) => handleSourceTextChange(e.target.value)}
          disabled={Boolean(sourceFile)}
        />
        <Button
          variant="contained"
          onClick={handleGenerate}
          disabled={generating || (!sourceFile && !sourceText.trim())}
          startIcon={generating ? <CircularProgress size={16} /> : undefined}
          sx={{ alignSelf: "flex-start" }}
        >
          {generating ? "Generating…" : "Generate"}
        </Button>

        {candidates.length > 0 && (
          <Stack spacing={1.5}>
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              Review the generated pairs below and accept the ones you want to keep.
            </Typography>
            {candidates.map((candidate, index) => (
              <Paper key={index} variant="outlined" sx={{ borderRadius: 2, p: 1.75 }}>
                <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between", gap: 1 }}>
                  <Stack sx={{ minWidth: 0 }}>
                    <Typography variant="body2" sx={{ fontWeight: 700 }}>
                      {candidate.question}
                    </Typography>
                    <Typography variant="caption" sx={{ color: "text.secondary", whiteSpace: "pre-wrap" }}>
                      {candidate.answer}
                    </Typography>
                  </Stack>
                  <Stack direction="row" spacing={1}>
                    <Button
                      size="small"
                      variant="contained"
                      onClick={() => handleAcceptCandidate(index)}
                      disabled={savingCandidateIndex === index}
                    >
                      Accept
                    </Button>
                    <Button size="small" onClick={() => handleDiscardCandidate(index)}>
                      Discard
                    </Button>
                  </Stack>
                </Stack>
              </Paper>
            ))}
          </Stack>
        )}
      </Stack>

      <Divider />

      <Stack spacing={1.5}>
        <TextField fullWidth label="Question" value={question} onChange={(e) => setQuestion(e.target.value)} />
        <TextField
          fullWidth
          multiline
          minRows={2}
          label="Answer"
          value={answer}
          onChange={(e) => setAnswer(e.target.value)}
        />
        <Button
          variant="contained"
          onClick={handleAdd}
          disabled={submitting || !question.trim() || !answer.trim()}
          sx={{ alignSelf: "flex-start" }}
        >
          Add entry
        </Button>
      </Stack>

      <Stack spacing={1.5}>
        {entries.map((entry) => (
          <Paper key={entry.id} variant="outlined" sx={{ borderRadius: 2, p: 1.75 }}>
            <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between", gap: 1 }}>
              <Stack sx={{ minWidth: 0 }}>
                <Typography variant="body2" sx={{ fontWeight: 700 }}>
                  {entry.question}
                </Typography>
                <Typography variant="caption" sx={{ color: "text.secondary", whiteSpace: "pre-wrap" }}>
                  {entry.answer}
                </Typography>
                <Chip
                  label={entry.source === "ai" ? "AI-generated" : "Manual"}
                  size="small"
                  sx={{ mt: 0.75, alignSelf: "flex-start" }}
                  variant="outlined"
                />
              </Stack>
              <IconButton size="small" onClick={() => handleDelete(entry.id)}>
                <DeleteRoundedIcon fontSize="small" />
              </IconButton>
            </Stack>
          </Paper>
        ))}
        {entries.length === 0 && (
          <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 2 }}>
            No training entries yet.
          </Typography>
        )}
      </Stack>
    </Stack>
  );
}
