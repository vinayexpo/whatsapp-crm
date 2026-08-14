import { describe, expect, it, vi, beforeEach } from "vitest";
import { act, renderHook, waitFor } from "@testing-library/react";
import { useWhatsappCallSession } from "./use-whatsapp-call-session";
import type { WhatsappCall } from "~/data/types";

const { placeWhatsappCall, submitWhatsappCallOffer, hangupWhatsappCall } = vi.hoisted(() => ({
  placeWhatsappCall: vi.fn(),
  submitWhatsappCallOffer: vi.fn(),
  hangupWhatsappCall: vi.fn(),
}));

vi.mock("~/utils/api-client", async () => {
  const actual = await vi.importActual<typeof import("~/utils/api-client")>("~/utils/api-client");
  return {
    ...actual,
    apiClient: { placeWhatsappCall, submitWhatsappCallOffer, hangupWhatsappCall },
  };
});

const listenMock = vi.fn().mockReturnThis();
const privateMock = vi.fn(() => ({ listen: listenMock }));
const leaveMock = vi.fn();

vi.mock("~/utils/echo-client", () => ({
  getEcho: () => ({ private: privateMock, leave: leaveMock }),
}));

function baseCall(overrides: Partial<WhatsappCall> = {}): WhatsappCall {
  return {
    id: "call-1",
    callFlowId: null,
    contactId: "contact-1",
    conversationId: null,
    direction: "outbound",
    status: "ringing",
    metaCallId: null,
    sdpExchangeStatus: "pending_offer",
    permissionRequestStatus: null,
    permissionRequestFailureReason: null,
    transcript: [],
    collectedVariables: {},
    needsHumanFollowup: false,
    humanFollowupAssignedTo: null,
    humanFollowupCompletedAt: null,
    startedAt: null,
    endedAt: null,
    createdAt: new Date().toISOString(),
    ...overrides,
  };
}

class FakeRTCPeerConnection {
  static instances: FakeRTCPeerConnection[] = [];
  localDescription: { type: string; sdp: string } | null = null;
  iceGatheringState = "complete";
  ontrack: ((event: { streams: MediaStream[] }) => void) | null = null;
  addTrack = vi.fn();
  close = vi.fn();
  addEventListener = vi.fn();
  removeEventListener = vi.fn();
  setRemoteDescription = vi.fn().mockResolvedValue(undefined);

  constructor() {
    FakeRTCPeerConnection.instances.push(this);
  }

  async createOffer() {
    return { type: "offer", sdp: "v=0...fake-offer-sdp" };
  }

  async setLocalDescription(desc: { type: string; sdp: string }) {
    this.localDescription = desc;
  }
}

function fakeStream() {
  const track = { stop: vi.fn(), enabled: true, kind: "audio" };
  return {
    getTracks: () => [track],
    getAudioTracks: () => [track],
  } as unknown as MediaStream;
}

beforeEach(() => {
  vi.clearAllMocks();
  FakeRTCPeerConnection.instances = [];
  // @ts-expect-error test stub, not a full RTCPeerConnection implementation
  global.RTCPeerConnection = FakeRTCPeerConnection;
  Object.defineProperty(global.navigator, "mediaDevices", {
    configurable: true,
    value: { getUserMedia: vi.fn().mockResolvedValue(fakeStream()) } as unknown as MediaDevices,
  });
  global.Audio = vi.fn().mockImplementation(() => ({ autoplay: false, srcObject: null })) as unknown as typeof Audio;
});

describe("useWhatsappCallSession", () => {
  it("walks through ringing -> connecting -> offer submitted on the happy path", async () => {
    placeWhatsappCall.mockResolvedValue(baseCall());
    submitWhatsappCallOffer.mockResolvedValue(baseCall({ sdpExchangeStatus: "offer_sent" }));

    const { result } = renderHook(() => useWhatsappCallSession());

    await act(async () => {
      await result.current.startCall({ contactId: "contact-1" });
    });

    expect(placeWhatsappCall).toHaveBeenCalledWith({ contactId: "contact-1", conversationId: undefined });
    expect(submitWhatsappCallOffer).toHaveBeenCalledWith("call-1", "v=0...fake-offer-sdp");
    expect(result.current.whatsappCall?.sdpExchangeStatus).toBe("offer_sent");
    expect(result.current.errorMessage).toBeNull();
  });

  it("surfaces mic-permission-denied distinctly and never calls submitWhatsappCallOffer", async () => {
    placeWhatsappCall.mockResolvedValue(baseCall());
    global.navigator.mediaDevices.getUserMedia = vi.fn().mockRejectedValue(new DOMException("Denied", "NotAllowedError"));

    const { result } = renderHook(() => useWhatsappCallSession());

    await act(async () => {
      await result.current.startCall({ contactId: "contact-1" });
    });

    expect(result.current.callState).toBe("failed");
    expect(result.current.errorMessage).toMatch(/microphone/i);
    expect(submitWhatsappCallOffer).not.toHaveBeenCalled();
  });

  it("surfaces the granular Meta error from submitWhatsappCallOffer", async () => {
    placeWhatsappCall.mockResolvedValue(baseCall());
    const { ApiError } = await import("~/utils/api-client");
    submitWhatsappCallOffer.mockRejectedValue(
      new ApiError("The given data was invalid.", 422, { sdpOffer: ["Missing session parameter (code 131009/2494010)"] }),
    );

    const { result } = renderHook(() => useWhatsappCallSession());

    await act(async () => {
      await result.current.startCall({ contactId: "contact-1" });
    });

    expect(result.current.callState).toBe("failed");
    expect(result.current.errorMessage).toContain("131009/2494010");
  });

  it("ends the call by tearing down the peer connection and calling hangupWhatsappCall", async () => {
    placeWhatsappCall.mockResolvedValue(baseCall());
    submitWhatsappCallOffer.mockResolvedValue(baseCall({ sdpExchangeStatus: "offer_sent" }));
    hangupWhatsappCall.mockResolvedValue(baseCall({ status: "completed" }));

    const { result } = renderHook(() => useWhatsappCallSession());

    await act(async () => {
      await result.current.startCall({ contactId: "contact-1" });
    });

    await act(async () => {
      await result.current.endCall();
    });

    expect(hangupWhatsappCall).toHaveBeenCalledWith("call-1");
    expect(FakeRTCPeerConnection.instances[0]?.close).toHaveBeenCalled();
    expect(result.current.callState).toBe("ended");
  });

  it("toggles mute by disabling local audio tracks without ending the call", async () => {
    placeWhatsappCall.mockResolvedValue(baseCall());
    submitWhatsappCallOffer.mockResolvedValue(baseCall({ sdpExchangeStatus: "offer_sent" }));

    const { result } = renderHook(() => useWhatsappCallSession());

    await act(async () => {
      await result.current.startCall({ contactId: "contact-1" });
    });

    expect(result.current.isMuted).toBe(false);

    await act(async () => {
      result.current.toggleMute();
    });

    await waitFor(() => expect(result.current.isMuted).toBe(true));
  });
});
