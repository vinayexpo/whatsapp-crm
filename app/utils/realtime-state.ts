import type { Conversation, Message } from "~/data/types";

export function sortConversations(conversations: Conversation[]): Conversation[] {
  return [...conversations].sort((a, b) => (Date.parse(b.lastMessageAt) || 0) - (Date.parse(a.lastMessageAt) || 0));
}

const STATUS_RANK: Record<Message["status"], number> = { sent: 1, failed: 2, delivered: 3, read: 4 };

export function mergeMessages(current: Message[], incoming: Message[]): Message[] {
  const messages = new Map(current.map((message) => [message.id, message]));
  for (const message of incoming) {
    const previous = messages.get(message.id);
    // HTTP responses and duplicate broadcasts can arrive after a newer receipt.
    const status = previous && STATUS_RANK[previous.status] > STATUS_RANK[message.status]
      ? previous.status
      : message.status;
    messages.set(message.id, { ...previous, ...message, status });
  }
  return [...messages.values()];
}

export function updateConversation(conversations: Conversation[], conversation: Conversation): Conversation[] {
  return sortConversations(conversations.map((current) => current.id === conversation.id ? conversation : current));
}
