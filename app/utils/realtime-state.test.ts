import { describe, expect, it } from "vitest";
import type { Conversation, Message } from "~/data/types";
import { mergeMessages, sortConversations, updateConversation } from "./realtime-state";

const message: Message = {
  id: "m1", conversationId: "new", direction: "inbound", text: "First message", timestamp: "2026-11-22T10:00:00Z", status: "delivered",
};

function conversation(id: string, lastMessageAt: string): Conversation {
  return { id, contactId: id, channel: "whatsapp", status: "open", unreadCount: 1, lastMessagePreview: id, lastMessageAt, assignedTo: null };
}

describe("realtime state reconciliation", () => {
  it("keeps the first message before a conversation is loaded and deduplicates the later HTTP response", () => {
    const received = mergeMessages([], [message]);
    expect(mergeMessages(received, [message])).toEqual([message]);
  });

  it("does not regress read receipts when delayed sent or delivered events arrive", () => {
    const read = { ...message, status: "read" as const };
    expect(mergeMessages([read], [{ ...message, status: "sent" }])[0].status).toBe("read");
    expect(mergeMessages([read], [message])[0].status).toBe("read");
    expect(mergeMessages([{ ...message, status: "sent" }], [{ ...message, status: "failed" }])[0].status).toBe("failed");
  });

  it("moves an updated card to its timestamp position without changing the input or losing other cards", () => {
    const newer = conversation("newer", "2026-11-22T10:00:00Z");
    const older = conversation("older", "2026-11-22T09:00:00Z");
    const original = [newer, older];
    expect(updateConversation(original, { ...older, lastMessageAt: "2026-11-22T11:00:00Z" }).map((c) => c.id)).toEqual(["older", "newer"]);
    expect(original).toEqual([newer, older]);
    expect(sortConversations([older, newer])).toEqual([newer, older]);
  });
});
