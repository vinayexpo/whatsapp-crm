import { useCallback, useEffect, useRef, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import CallEndRoundedIcon from "@mui/icons-material/CallEndRounded";
import MicRoundedIcon from "@mui/icons-material/MicRounded";
import GraphicEqRoundedIcon from "@mui/icons-material/GraphicEqRounded";
import classNames from "classnames";
import type { AiAssistantSettings, AiChatMessage, VoiceAgent, VoiceCallTranscriptTurn } from "~/data/types";
import { useAiChatCompletion } from "~/hooks/use-ai-chat-completion";
import { apiClient } from "~/utils/api-client";
import styles from "./ai-call-panel.module.css";

const SYSTEM_PROMPT =
  "You are a friendly AI phone assistant for Creative Connects. Respond conversationally in 1-3 short sentences, as if speaking on a call.";

type CallState = "idle" | "listening" | "thinking" | "speaking";

interface AiCallPanelProps {
  settings: AiAssistantSettings;
  /** When provided (together with contactId), the call is run in "linked" mode: it uses the
   * voice agent's own system prompt / qualification criteria and persists the call to the
   * backend when it ends. Without these, the panel behaves exactly as the generic AI Assistant
   * demo call (browser-only, nothing persisted) — zero behavior change for that path. */
  voiceAgent?: VoiceAgent;
  contactId?: string;
}

function buildSystemPrompt(voiceAgent?: VoiceAgent): string {
  if (!voiceAgent) return SYSTEM_PROMPT;
  const basePrompt = voiceAgent.systemPrompt?.trim() || SYSTEM_PROMPT;
  if (voiceAgent.qualificationCriteria.length === 0) return basePrompt;
  const questions = voiceAgent.qualificationCriteria
    .map((c, i) => `${i + 1}. (${c.key}) ${c.question}`)
    .join("\n");
  return `${basePrompt}\n\nDuring this call, naturally work through these qualification questions and listen for the answers:\n${questions}${
    voiceAgent.qualificationPassCriteria ? `\n\nA lead is considered qualified when: ${voiceAgent.qualificationPassCriteria}` : ""
  }`;
}

export function AiCallPanel({ settings, voiceAgent, contactId }: AiCallPanelProps) {
  const [callState, setCallState] = useState<CallState>("idle");
  const [transcript, setTranscript] = useState<AiChatMessage[]>([]);
  const [speechSupported, setSpeechSupported] = useState(true);
  const { sendChatCompletion, error, isConfigured } = useAiChatCompletion(settings);
  const systemPrompt = buildSystemPrompt(voiceAgent);

  const recognitionRef = useRef<any>(null);
  const isCallActiveRef = useRef(false);
  const transcriptRef = useRef<AiChatMessage[]>([]);
  const transcriptEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const SpeechRecognitionCtor =
      typeof window !== "undefined" && ((window as any).SpeechRecognition || (window as any).webkitSpeechRecognition);
    setSpeechSupported(Boolean(SpeechRecognitionCtor && "speechSynthesis" in window));
  }, []);

  useEffect(() => {
    transcriptEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [transcript.length]);

  const stopCall = useCallback(() => {
    isCallActiveRef.current = false;
    recognitionRef.current?.stop();
    window.speechSynthesis?.cancel();
    setCallState("idle");
  }, []);

  const speak = useCallback(
    (text: string) =>
      new Promise<void>((resolve) => {
        if (!("speechSynthesis" in window)) {
          resolve();
          return;
        }
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.onend = () => resolve();
        utterance.onerror = () => resolve();
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
      }),
    [],
  );

  const startListening = useCallback(() => {
    if (!isCallActiveRef.current || !recognitionRef.current) return;
    setCallState("listening");
    try {
      recognitionRef.current.start();
    } catch {
      // recognition may already be running; ignore
    }
  }, []);

  const appendTranscriptMessage = useCallback((message: AiChatMessage) => {
    transcriptRef.current = [...transcriptRef.current, message];
    setTranscript(transcriptRef.current);
  }, []);

  const handleUserUtterance = useCallback(
    async (text: string) => {
      appendTranscriptMessage({
        id: `call-u-${Date.now()}`,
        role: "user",
        content: text,
        timestamp: new Date().toISOString(),
      });
      setCallState("thinking");

      try {
        const reply = await sendChatCompletion([
          { role: "system", content: systemPrompt },
          ...transcriptRef.current.map((m) => ({ role: m.role, content: m.content })),
        ]);
        appendTranscriptMessage({
          id: `call-a-${Date.now()}`,
          role: "assistant",
          content: reply,
          timestamp: new Date().toISOString(),
        });
        setCallState("speaking");
        await speak(reply);
      } catch {
        // error surfaced via hook state
      } finally {
        if (isCallActiveRef.current) {
          startListening();
        }
      }
    },
    [appendTranscriptMessage, sendChatCompletion, speak, startListening, systemPrompt],
  );

  const handleUserUtteranceRef = useRef(handleUserUtterance);
  handleUserUtteranceRef.current = handleUserUtterance;

  const startListeningRef = useRef(startListening);
  startListeningRef.current = startListening;

  useEffect(() => {
    if (!speechSupported) return;
    const SpeechRecognitionCtor = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
    const recognition = new SpeechRecognitionCtor();
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = "en-US";

    recognition.onresult = (event: any) => {
      const text = event.results?.[0]?.[0]?.transcript?.trim();
      if (text) {
        handleUserUtteranceRef.current(text);
      } else if (isCallActiveRef.current) {
        startListeningRef.current();
      }
    };

    recognition.onerror = () => {
      // Swallow recognition errors (e.g. no-speech); the call flow restarts listening
      // explicitly once thinking/speaking completes.
    };

    recognitionRef.current = recognition;

    return () => {
      recognition.abort?.();
    };
  }, [speechSupported]);

  function handleStartCall() {
    if (!isConfigured) return;
    transcriptRef.current = [];
    setTranscript([]);
    isCallActiveRef.current = true;
    startListening();
  }

  const persistCallIfLinked = useCallback(async () => {
    if (!voiceAgent || !contactId || transcriptRef.current.length === 0) return;

    const callTranscript: VoiceCallTranscriptTurn[] = transcriptRef.current.map((m) => ({
      role: m.role === "user" ? "lead" : "agent",
      text: m.content,
      at: m.timestamp,
    }));

    let qualificationOutcome: "qualified" | "not_qualified" | "uncertain" | null = null;
    let qualificationSummary: string | null = null;

    if (voiceAgent.qualificationCriteria.length > 0) {
      try {
        const evaluation = await sendChatCompletion([
          {
            role: "system",
            content:
              "You evaluate a completed sales call transcript against qualification criteria. Reply with strict JSON only, no prose, in the shape " +
              '{"outcome":"qualified"|"not_qualified"|"uncertain","summary":"one or two sentence summary"}.',
          },
          {
            role: "user",
            content: `Qualification pass criteria: ${voiceAgent.qualificationPassCriteria ?? "Use your best judgement."}\n\nTranscript:\n${callTranscript
              .map((t) => `${t.role}: ${t.text}`)
              .join("\n")}`,
          },
        ]);
        const parsed = JSON.parse(evaluation.trim());
        if (parsed.outcome === "qualified" || parsed.outcome === "not_qualified" || parsed.outcome === "uncertain") {
          qualificationOutcome = parsed.outcome;
        }
        if (typeof parsed.summary === "string") {
          qualificationSummary = parsed.summary;
        }
      } catch {
        // If evaluation fails or isn't parseable JSON, the call is still logged without an outcome.
      }
    }

    try {
      await apiClient.logSimulatedVoiceCall({
        contactId,
        voiceAgentId: voiceAgent.id,
        transcript: callTranscript,
        qualificationOutcome,
        qualificationSummary,
      });
    } catch {
      // Persisting the simulated call is best-effort; the in-panel transcript is unaffected.
    }
  }, [voiceAgent, contactId, sendChatCompletion]);

  function handleEndCall() {
    stopCall();
    // Live "telephony" call mode (Twilio broadcast-channel subscription) is not implemented yet;
    // this only persists the browser-simulated call when linked to a CRM contact + voice agent.
    void persistCallIfLinked();
  }

  useEffect(() => stopCall, [stopCall]);

  const statusLabel =
    callState === "listening"
      ? "Listening…"
      : callState === "thinking"
        ? "Thinking…"
        : callState === "speaking"
          ? "Speaking…"
          : "Ready to call";

  return (
    <Box className={styles.container}>
      {!speechSupported && (
        <Alert severity="warning" sx={{ width: "100%", maxWidth: 560 }}>
          Voice calling requires a browser with Web Speech API support (e.g. Chrome or Edge).
        </Alert>
      )}
      {!isConfigured && (
        <Alert severity="info" sx={{ width: "100%", maxWidth: 560 }}>
          Add your Base API URL, API Key, and Model in the AI Assistant settings tab to start a call.
        </Alert>
      )}
      {error && (
        <Alert severity="error" sx={{ width: "100%", maxWidth: 560 }}>
          {error}
        </Alert>
      )}

      <Box
        className={classNames(styles.orb, {
          [styles.orbListening]: callState === "listening",
          [styles.orbSpeaking]: callState === "speaking",
        })}
      >
        {callState === "speaking" ? (
          <GraphicEqRoundedIcon sx={{ fontSize: 52, color: "secondary.main" }} />
        ) : (
          <MicRoundedIcon sx={{ fontSize: 52, color: "primary.main" }} />
        )}
      </Box>

      <Typography variant="body2" sx={{ fontWeight: 600, color: "text.secondary" }}>
        {statusLabel}
      </Typography>

      <Box className={styles.transcript}>
        {transcript.map((message) => (
          <Box
            key={message.id}
            className={classNames(styles.transcriptRow, { [styles.transcriptRowUser]: message.role === "user" })}
          >
            <Box
              className={classNames(styles.transcriptBubble, {
                [styles.transcriptBubbleUser]: message.role === "user",
              })}
            >
              {message.content}
            </Box>
          </Box>
        ))}
        <div ref={transcriptEndRef} />
      </Box>

      <Stack direction="row" spacing={1.5}>
        {callState === "idle" ? (
          <Button
            variant="contained"
            color="success"
            startIcon={<CallRoundedIcon />}
            onClick={handleStartCall}
            disabled={!speechSupported || !isConfigured}
          >
            Start Call
          </Button>
        ) : (
          <Button variant="contained" color="error" startIcon={<CallEndRoundedIcon />} onClick={handleEndCall}>
            End Call
          </Button>
        )}
      </Stack>
    </Box>
  );
}
