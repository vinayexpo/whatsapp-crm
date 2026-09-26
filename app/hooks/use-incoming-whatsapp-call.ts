import { useCallback, useRef, useState } from "react";
import { apiClient } from "~/utils/api-client";
import { getEcho } from "~/utils/echo-client";
import type { WhatsappCall } from "~/data/types";

export type IncomingCallState =
  | "ringing"
  | "requesting-mic"
  | "connecting"
  | "connected"
  | "ended"
  | "failed"
  | "rejected";

interface UseIncomingWhatsappCallResult {
  callState: IncomingCallState;
  errorMessage: string | null;
  isMuted: boolean;
  acceptCall: (call: WhatsappCall) => Promise<void>;
  rejectCall: (call: WhatsappCall) => Promise<void>;
  toggleMute: () => void;
  endCall: () => Promise<void>;
  reset: () => void;
}

export function useIncomingWhatsappCall(): UseIncomingWhatsappCallResult {
  const [callState, setCallState] = useState<IncomingCallState>("ringing");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isMuted, setIsMuted] = useState(false);

  const pcRef = useRef<RTCPeerConnection | null>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const remoteAudioRef = useRef<HTMLAudioElement | null>(null);
  const subscribedChannelRef = useRef<string | null>(null);
  const activeCallIdRef = useRef<string | null>(null);

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

  const reset = useCallback(() => {
    cleanup();
    activeCallIdRef.current = null;
    setCallState("ringing");
    setErrorMessage(null);
    setIsMuted(false);
  }, [cleanup]);

  const acceptCall = useCallback(
    async (call: WhatsappCall) => {
      if (!call.remoteSdpOffer) {
        setCallState("failed");
        setErrorMessage("This call has no audio offer to answer.");
        return;
      }

      activeCallIdRef.current = call.id;
      setErrorMessage(null);
      setCallState("requesting-mic");

      let stream: MediaStream;
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch {
        setCallState("failed");
        setErrorMessage("Microphone access was denied. Allow microphone access to answer the call.");
        return;
      }
      localStreamRef.current = stream;

      setCallState("connecting");
      let iceServers: RTCIceServer[];
      try {
        iceServers = await apiClient.getWhatsappCallIceServers();
      } catch {
        iceServers = [{ urls: ["stun:stun.l.google.com:19302"] }];
      }

      const pc = new RTCPeerConnection({ iceServers });
      pcRef.current = pc;
      stream.getTracks().forEach((track) => pc.addTrack(track, stream));

      pc.ontrack = (event) => {
        const audio = new Audio();
        audio.autoplay = true;
        audio.srcObject = event.streams[0];
        document.body.appendChild(audio);
        audio.play().catch(() => {
          // Autoplay may be blocked until a user gesture; already had one to get here.
        });
        remoteAudioRef.current = audio;
      };

      pc.onconnectionstatechange = () => {
        if (pc.connectionState === "connected") {
          setCallState((current) => (current === "ended" || current === "failed" ? current : "connected"));
        } else if (pc.connectionState === "failed") {
          setCallState("failed");
          setErrorMessage("The audio connection failed. This can happen on restrictive networks — try again or switch networks.");
        }
      };

      const channelName = `whatsapp-call.${call.id}`;
      subscribedChannelRef.current = channelName;
      getEcho()
        .private(channelName)
        .listen(".whatsapp-call.status-updated", (payload: { whatsappCall: WhatsappCall }) => {
          if (activeCallIdRef.current !== call.id) return;
          if (["completed", "missed"].includes(payload.whatsappCall.status)) {
            setCallState((current) => (current === "failed" ? current : "ended"));
          } else if (payload.whatsappCall.status === "failed") {
            setCallState("failed");
            setErrorMessage("The call failed.");
          }
        });

      try {
        // Meta's webhook payload strips the trailing line terminator from
        // the last SDP line, which Chrome's parser rejects outright.
        const offerSdp = call.remoteSdpOffer.endsWith("\r\n")
          ? call.remoteSdpOffer
          : `${call.remoteSdpOffer.replace(/\r?\n?$/, "")}\r\n`;
        await pc.setRemoteDescription({ type: "offer", sdp: offerSdp });

        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await waitForIceGatheringComplete(pc);

        const sdpAnswer = pc.localDescription?.sdp;
        if (!sdpAnswer) throw new Error("Failed to generate a local SDP answer.");

        await apiClient.acceptWhatsappCall(call.id, sdpAnswer);
      } catch (error) {
        setCallState("failed");
        setErrorMessage(error instanceof Error ? error.message : "Couldn't answer the call.");
        cleanup();
      }
    },
    [cleanup],
  );

  const rejectCall = useCallback(async (call: WhatsappCall) => {
    activeCallIdRef.current = call.id;
    setCallState("rejected");
    try {
      await apiClient.rejectWhatsappCall(call.id);
    } catch {
      // best-effort: the local UI has already dismissed the call
    }
  }, []);

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
    const callId = activeCallIdRef.current;
    cleanup();
    setCallState("ended");
    if (callId) {
      try {
        await apiClient.hangupWhatsappCall(callId);
      } catch {
        // best-effort: the local call state is already ended
      }
    }
  }, [cleanup]);

  return {
    callState,
    errorMessage,
    isMuted,
    acceptCall,
    rejectCall,
    toggleMute,
    endCall,
    reset,
  };
}

const ICE_GATHERING_TIMEOUT_MS = 5000;

function waitForIceGatheringComplete(pc: RTCPeerConnection): Promise<void> {
  if (pc.iceGatheringState === "complete") return Promise.resolve();
  return new Promise((resolve) => {
    const timer = setTimeout(() => {
      pc.removeEventListener("icegatheringstatechange", check);
      resolve();
    }, ICE_GATHERING_TIMEOUT_MS);
    function check() {
      if (pc.iceGatheringState === "complete") {
        clearTimeout(timer);
        pc.removeEventListener("icegatheringstatechange", check);
        resolve();
      }
    }
    pc.addEventListener("icegatheringstatechange", check);
  });
}
