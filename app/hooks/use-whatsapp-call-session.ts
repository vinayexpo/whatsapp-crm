import { useCallback, useEffect, useRef, useState } from "react";
import { apiClient, ApiError } from "~/utils/api-client";
import { getEcho } from "~/utils/echo-client";
import type { WhatsappCall } from "~/data/types";

export type WhatsappCallSessionState =
  | "idle"
  | "requesting-mic"
  | "ringing"
  | "connecting"
  | "connected"
  | "ended"
  | "failed";

interface UseWhatsappCallSessionResult {
  callState: WhatsappCallSessionState;
  errorMessage: string | null;
  needsCallPermission: boolean;
  isMuted: boolean;
  whatsappCall: WhatsappCall | null;
  startCall: (params: { contactId: string; conversationId?: string }) => Promise<void>;
  toggleMute: () => void;
  endCall: () => Promise<void>;
  requestCallPermission: () => Promise<void>;
}

const CALL_PERMISSION_ERROR_PATTERN = /138006|2593090/;

export function useWhatsappCallSession(): UseWhatsappCallSessionResult {
  const [callState, setCallState] = useState<WhatsappCallSessionState>("idle");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [needsCallPermission, setNeedsCallPermission] = useState(false);
  const [isMuted, setIsMuted] = useState(false);
  const [whatsappCall, setWhatsappCall] = useState<WhatsappCall | null>(null);

  const pcRef = useRef<RTCPeerConnection | null>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const remoteAudioRef = useRef<HTMLAudioElement | null>(null);
  const subscribedChannelRef = useRef<string | null>(null);

  const cleanup = useCallback(() => {
    if (subscribedChannelRef.current) {
      getEcho().leave(subscribedChannelRef.current);
      subscribedChannelRef.current = null;
    }
    pcRef.current?.close();
    pcRef.current = null;
    localStreamRef.current?.getTracks().forEach((track) => track.stop());
    localStreamRef.current = null;
    if (remoteAudioRef.current) {
      remoteAudioRef.current.srcObject = null;
      remoteAudioRef.current.remove();
      remoteAudioRef.current = null;
    }
  }, []);

  useEffect(() => cleanup, [cleanup]);

  const startCall = useCallback(
    async ({ contactId, conversationId }: { contactId: string; conversationId?: string }) => {
      setErrorMessage(null);
      setNeedsCallPermission(false);
      setWhatsappCall(null);
      setIsMuted(false);

      let call: WhatsappCall;
      try {
        call = await apiClient.placeWhatsappCall({ contactId, conversationId });
        setWhatsappCall(call);
        setCallState("ringing");
      } catch (error) {
        setCallState("failed");
        setErrorMessage(error instanceof Error ? error.message : "Couldn't start the call.");
        return;
      }

      const channelName = `whatsapp-call.${call.id}`;
      subscribedChannelRef.current = channelName;
      const echo = getEcho();
      echo.private(channelName).listen(".whatsapp-call.status-updated", (payload: { whatsappCall: WhatsappCall }) => {
        setWhatsappCall(payload.whatsappCall);
        if (payload.whatsappCall.status === "in_progress") {
          setCallState("connected");
        } else if (["completed", "missed"].includes(payload.whatsappCall.status)) {
          setCallState("ended");
        } else if (payload.whatsappCall.status === "failed") {
          setCallState("failed");
          setErrorMessage("The call failed.");
        }
      });
      echo.private(channelName).listen(".whatsapp-call.sdp-answer", async (payload: { sdp: string }) => {
        try {
          await pcRef.current?.setRemoteDescription({ type: "answer", sdp: payload.sdp });
        } catch {
          setCallState("failed");
          setErrorMessage("Couldn't establish the audio connection.");
        }
      });

      setCallState("requesting-mic");
      let stream: MediaStream;
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch {
        setCallState("failed");
        setErrorMessage("Microphone access was denied. Allow microphone access to place a call.");
        return;
      }
      localStreamRef.current = stream;

      setCallState("connecting");
      const pc = new RTCPeerConnection({
        iceServers: [{ urls: ["stun:stun.l.google.com:19302"] }],
      });
      pcRef.current = pc;
      stream.getTracks().forEach((track) => pc.addTrack(track, stream));

      pc.ontrack = (event) => {
        const audio = new Audio();
        audio.autoplay = true;
        audio.srcObject = event.streams[0];
        document.body.appendChild(audio);
        audio.play().catch(() => {
          // Autoplay may be blocked until a user gesture; the call UI's
          // own controls (mute/hangup) count as one for subsequent plays.
        });
        remoteAudioRef.current = audio;
      };

      try {
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await waitForIceGatheringComplete(pc);

        const sdpOffer = pc.localDescription?.sdp;
        if (!sdpOffer) throw new Error("Failed to generate a local SDP offer.");

        const updatedCall = await apiClient.submitWhatsappCallOffer(call.id, sdpOffer);
        setWhatsappCall(updatedCall);
      } catch (error) {
        const message =
          error instanceof ApiError ? (error.errors?.sdpOffer?.[0] ?? error.message) : "Couldn't place the call.";
        setCallState("failed");
        setErrorMessage(message);
        setNeedsCallPermission(CALL_PERMISSION_ERROR_PATTERN.test(message));
        cleanup();
      }
    },
    [cleanup],
  );

  const requestCallPermission = useCallback(async () => {
    const call = whatsappCall;
    if (!call) return;
    try {
      await apiClient.requestWhatsappCallPermission(call.id);
      setNeedsCallPermission(false);
      setErrorMessage(
        "Call permission request sent. Ask the contact to accept it in WhatsApp, then try calling again.",
      );
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : "Couldn't send the call permission request.");
    }
  }, [whatsappCall]);

  const toggleMute = useCallback(() => {
    const stream = localStreamRef.current;
    if (!stream) return;
    const nextMuted = !isMuted;
    stream.getAudioTracks().forEach((track) => {
      track.enabled = !nextMuted;
    });
    setIsMuted(nextMuted);
  }, [isMuted]);

  const endCall = useCallback(async () => {
    const call = whatsappCall;
    cleanup();
    setCallState("ended");
    if (call) {
      try {
        await apiClient.hangupWhatsappCall(call.id);
      } catch {
        // best-effort: the local call state is already ended
      }
    }
  }, [cleanup, whatsappCall]);

  return {
    callState,
    errorMessage,
    needsCallPermission,
    isMuted,
    whatsappCall,
    startCall,
    toggleMute,
    endCall,
    requestCallPermission,
  };
}

function waitForIceGatheringComplete(pc: RTCPeerConnection): Promise<void> {
  if (pc.iceGatheringState === "complete") return Promise.resolve();
  return new Promise((resolve) => {
    function check() {
      if (pc.iceGatheringState === "complete") {
        pc.removeEventListener("icegatheringstatechange", check);
        resolve();
      }
    }
    pc.addEventListener("icegatheringstatechange", check);
  });
}
