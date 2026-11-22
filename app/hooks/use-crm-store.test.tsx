import { act, cleanup, renderHook, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { Conversation, Message } from "~/data/types";
import { CrmStoreProvider, useCrmStore } from "./use-crm-store";

const { api, channels, echo, auth } = vi.hoisted(() => {
  type Listener = (payload?: any) => void;
  type Channel = { listeners: Map<string, Listener>; listen: ReturnType<typeof vi.fn>; subscribed: ReturnType<typeof vi.fn>; reconnect?: Listener };
  const channels = new Map<string, Channel>();
  const echo = {
    private: vi.fn((name: string) => {
      if (!channels.has(name)) {
        const channel: Channel = { listeners: new Map(), listen: vi.fn(), subscribed: vi.fn() };
        channel.listen.mockImplementation((event: string, listener: Listener) => { channel.listeners.set(event, listener); return channel; });
        channel.subscribed.mockImplementation((listener: Listener) => { channel.reconnect = listener; return channel; });
        channels.set(name, channel);
      }
      return channels.get(name)!;
    }),
    leave: vi.fn((name: string) => { channels.delete(name); }),
  };
  const api = new Proxy({} as Record<string, ReturnType<typeof vi.fn>>, {
    get(target, name: string) { return target[name] ??= vi.fn().mockResolvedValue([]); },
  });
  return { api, channels, echo, auth: { status: "authenticated", user: { id: "user", companyId: "company", role: "admin" } } };
});

vi.mock("~/utils/api-client", () => ({ apiClient: api }));
vi.mock("~/utils/echo-client", () => ({ getEcho: () => echo }));
vi.mock("~/hooks/use-auth", () => ({ useAuth: () => auth }));

const meta = { currentPage: 1, lastPage: 1, perPage: 20, total: 2 };
function conversation(id: string, time: string): Conversation {
  return { id, contactId: id, channel: "whatsapp", status: "open", lastMessageAt: time, lastMessagePreview: id, unreadCount: 1, assignedTo: null };
}
const newest = conversation("newest", "2026-11-22T10:00:00Z");
const older = conversation("older", "2026-11-22T09:00:00Z");

beforeEach(() => {
  channels.clear();
  echo.private.mockClear();
  echo.leave.mockClear();
  for (const mock of Object.values(api)) mock.mockReset().mockResolvedValue([]);
  api.listContacts.mockResolvedValue({ data: [], meta });
  api.listConversations.mockResolvedValue({ data: [newest, older], meta });
  api.listCampaigns.mockResolvedValue({ data: [], meta });
  api.listAutomationFlows.mockResolvedValue({ data: [], meta });
  api.listMessages.mockResolvedValue({ data: [], hasMore: false });
});
afterEach(() => { cleanup(); vi.useRealTimers(); });

function emit(name: string, event: string, payload?: unknown) {
  const listener = channels.get(name)?.listeners.get(event);
  expect(listener).toBeTruthy();
  act(() => listener!(payload));
}

async function setup() {
  const hook = renderHook(() => useCrmStore(), { wrapper: CrmStoreProvider });
  await waitFor(() => expect(hook.result.current.conversations).toHaveLength(2));
  return hook;
}

describe("CRM realtime subscriptions", () => {
  it("receives a first message over the company channel before subscribing to its conversation", async () => {
    const { result } = await setup();
    const message: Message = { id: "first", conversationId: "new", text: "Hello", direction: "inbound", status: "delivered", timestamp: "2026-11-22T11:00:00Z" };
    expect(channels.has("conversation.new")).toBe(false);
    emit("company.company.conversations", ".message.received", { message });
    expect(result.current.messages).toEqual([message]);
    emit("company.company.conversations", ".message.received", { message });
    expect(result.current.messages).toHaveLength(1);
  });

  it("reorders a loaded conversation immediately without duplicating it", async () => {
    const { result } = await setup();
    emit("company.company.conversations", ".conversation.updated", {
      conversation: { ...older, lastMessageAt: "2026-11-22T11:00:00Z", lastMessagePreview: "New reply" },
    });
    expect(result.current.conversations.map((c) => c.id)).toEqual(["older", "newest"]);
    expect(result.current.conversations[0].lastMessagePreview).toBe("New reply");
  });

  it("reconciles the current filtered server page and totals after new conversation events", async () => {
    const { result } = await setup();
    act(() => result.current.fetchConversationsPage(2, "me", "Jane", "open", "whatsapp"));
    await waitFor(() => expect(api.listConversations).toHaveBeenCalledWith({ page: 2, assignedTo: "me", search: "Jane", status: "open", channel: "whatsapp" }));
    api.listConversations.mockClear();
    vi.useFakeTimers();
    emit("company.company.conversations", ".conversation.created", { conversation: conversation("unmatched", "2026-11-22T11:00:00Z") });
    expect(result.current.conversations.some((c) => c.id === "unmatched")).toBe(false);
    await act(async () => { vi.advanceTimersByTime(500); });
    expect(api.listConversations).toHaveBeenCalledWith({ page: 2, assignedTo: "me", search: "Jane", status: "open", channel: "whatsapp" });
    expect(result.current.conversationsPagination.total).toBe(2);
  });

  it("refreshes campaigns and dashboard in batches, and templates on approval events", async () => {
    const { result } = await setup();
    api.listCampaigns.mockClear();
    vi.useFakeTimers();
    for (let i = 0; i < 10; i++) emit("company.company.updates", ".company.data-updated", { resource: "campaigns" });
    await act(async () => { vi.advanceTimersByTime(500); });
    expect(result.current.campaignsRevision).toBe(1);
    expect(result.current.dashboardRevision).toBe(1);
    expect(api.listCampaigns).toHaveBeenCalledTimes(2);
    emit("company.company.updates", ".company.data-updated", { resource: "templates" });
    await act(async () => { vi.advanceTimersByTime(500); });
    expect(result.current.templatesRevision).toBe(1);
  });

  it("keeps the selected conversation when new activity pushes it off the current page", async () => {
    const { result } = await setup();
    act(() => result.current.loadMessagesForConversation("older"));
    await waitFor(() => expect(api.listMessages).toHaveBeenCalled());
    api.listConversations.mockResolvedValue({ data: [newest], meta });
    vi.useFakeTimers();
    emit("company.company.conversations", ".conversation.created", { conversation: conversation("new", "2026-11-22T11:00:00Z") });
    await act(async () => { vi.advanceTimersByTime(500); });
    expect(result.current.conversations.some((c) => c.id === "older")).toBe(true);
    expect(channels.has("conversation.older")).toBe(true);
  });

  it("reconciles after reconnect and leaves company and conversation channels on unmount", async () => {
    const { result, unmount } = await setup();
    vi.useFakeTimers();
    act(() => {
      channels.get("company.company.conversations")!.reconnect!();
      channels.get("company.company.updates")!.reconnect!();
    });
    await act(async () => { vi.advanceTimersByTime(500); });
    expect(result.current.campaignsRevision).toBe(1);
    expect(result.current.templatesRevision).toBe(1);
    unmount();
    expect(channels.size).toBe(0);
    expect(vi.getTimerCount()).toBe(0);
  });
});
