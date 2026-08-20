const API_BASE_URL = import.meta.env.VITE_API_URL ?? "https://mintcream-jellyfish-201700.hostingersite.com";
const POLL_INTERVAL_MS = 4000;
const VISITOR_ID_STORAGE_KEY = "omnichat_widget_visitor_id";

interface WidgetMessageButton {
  id: string;
  label: string;
  nextNodeId: string;
}

interface WidgetMessage {
  id: string;
  direction: "inbound" | "outbound";
  text: string;
  timestamp: string;
  buttons?: WidgetMessageButton[] | null;
}

function getScriptTag(): HTMLScriptElement {
  const current = document.currentScript as HTMLScriptElement | null;
  if (current) return current;
  const scripts = document.getElementsByTagName("script");
  return scripts[scripts.length - 1] as HTMLScriptElement;
}

function getVisitorId(): string {
  let visitorId = window.localStorage.getItem(VISITOR_ID_STORAGE_KEY);
  if (!visitorId) {
    visitorId = crypto.randomUUID();
    window.localStorage.setItem(VISITOR_ID_STORAGE_KEY, visitorId);
  }
  return visitorId;
}

async function widgetRequest<T>(widgetKey: string, visitorId: string, path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE_URL}/api/widget${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      "X-Widget-Key": widgetKey,
      "X-Visitor-Id": visitorId,
      ...init?.headers,
    },
  });
  if (!response.ok) throw new Error(`Widget request failed: ${response.status}`);
  return response.json();
}

function injectStyles() {
  const style = document.createElement("style");
  style.textContent = `
    .omnichat-widget-launcher {
      position: fixed; bottom: 20px; right: 20px; width: 56px; height: 56px;
      border-radius: 50%; background: #5B6EF5; color: #fff; border: none;
      cursor: pointer; box-shadow: 0 4px 16px rgba(0,0,0,0.2); font-size: 24px;
      z-index: 999999; display: flex; align-items: center; justify-content: center;
    }
    .omnichat-widget-panel {
      position: fixed; bottom: 88px; right: 20px; width: 340px; max-width: calc(100vw - 40px);
      height: 480px; max-height: calc(100vh - 120px); background: #fff; border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.25); z-index: 999999; display: none;
      flex-direction: column; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    }
    .omnichat-widget-panel.open { display: flex; }
    .omnichat-widget-header {
      background: #5B6EF5; color: #fff; padding: 14px 16px; font-weight: 600; font-size: 15px;
    }
    .omnichat-widget-messages {
      flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px;
    }
    .omnichat-widget-bubble {
      max-width: 80%; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.4;
      white-space: pre-wrap; word-break: break-word;
    }
    .omnichat-widget-bubble.contact { align-self: flex-end; background: #5B6EF5; color: #fff; }
    .omnichat-widget-bubble.bot { align-self: flex-start; background: #f0f1f5; color: #1a1a1a; }
    .omnichat-widget-input-row {
      display: flex; gap: 8px; padding: 10px; border-top: 1px solid #eee;
    }
    .omnichat-widget-input {
      flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 8px 10px; font-size: 13px; outline: none;
    }
    .omnichat-widget-send {
      background: #5B6EF5; color: #fff; border: none; border-radius: 8px; padding: 0 14px; cursor: pointer; font-size: 13px;
    }
    .omnichat-widget-buttons {
      display: flex; flex-direction: column; gap: 6px; margin-top: 6px; align-self: flex-start; max-width: 80%;
    }
    .omnichat-widget-option {
      background: #fff; color: #5B6EF5; border: 1px solid #5B6EF5; border-radius: 8px;
      padding: 6px 10px; font-size: 13px; cursor: pointer; text-align: left;
    }
    .omnichat-widget-option:hover { background: #eef0ff; }
    .omnichat-widget-option:disabled { opacity: 0.5; cursor: default; }
  `;
  document.head.appendChild(style);
}

function renderBubble(
  container: HTMLElement,
  message: WidgetMessage,
  onButtonClick?: (button: WidgetMessageButton) => void,
) {
  const bubble = document.createElement("div");
  bubble.className = `omnichat-widget-bubble ${message.direction === "inbound" ? "contact" : "bot"}`;
  bubble.textContent = message.text;
  bubble.dataset.messageId = message.id;
  container.appendChild(bubble);

  if (message.buttons && message.buttons.length > 0 && onButtonClick) {
    const buttonsEl = document.createElement("div");
    buttonsEl.className = "omnichat-widget-buttons";
    message.buttons.forEach((button) => {
      const optionButton = document.createElement("button");
      optionButton.className = "omnichat-widget-option";
      optionButton.textContent = button.label;
      optionButton.addEventListener("click", () => {
        Array.from(buttonsEl.querySelectorAll("button")).forEach((el) => (el.disabled = true));
        onButtonClick(button);
      });
      buttonsEl.appendChild(optionButton);
    });
    container.appendChild(buttonsEl);
  }

  container.scrollTop = container.scrollHeight;

  return bubble;
}

async function initWidget() {
  const script = getScriptTag();
  const widgetKey = script.dataset.widgetKey;
  if (!widgetKey) {
    console.error("Creative Connects widget: missing data-widget-key attribute");
    return;
  }

  injectStyles();

  const visitorId = getVisitorId();
  const seenMessageIds = new Set<string>();
  const pendingInboundBubbles: Array<{ text: string; element: HTMLDivElement }> = [];
  let lastMessageId: string | null = null;

  const launcher = document.createElement("button");
  launcher.className = "omnichat-widget-launcher";
  launcher.setAttribute("aria-label", "Open chat");
  launcher.textContent = "💬";

  const panel = document.createElement("div");
  panel.className = "omnichat-widget-panel";

  const header = document.createElement("div");
  header.className = "omnichat-widget-header";
  header.textContent = "Chat with us";

  const messagesEl = document.createElement("div");
  messagesEl.className = "omnichat-widget-messages";

  const inputRow = document.createElement("div");
  inputRow.className = "omnichat-widget-input-row";

  const input = document.createElement("input");
  input.className = "omnichat-widget-input";
  input.placeholder = "Type a message…";

  const sendButton = document.createElement("button");
  sendButton.className = "omnichat-widget-send";
  sendButton.textContent = "Send";

  inputRow.appendChild(input);
  inputRow.appendChild(sendButton);
  panel.appendChild(header);
  panel.appendChild(messagesEl);
  panel.appendChild(inputRow);
  document.body.appendChild(launcher);
  document.body.appendChild(panel);

  let bootstrapped = false;
  let pollTimer: ReturnType<typeof setInterval> | null = null;

  function trackMessage(message: WidgetMessage) {
    if (seenMessageIds.has(message.id)) return;

    if (message.direction === "inbound" && pendingInboundBubbles.length > 0) {
      const pending = pendingInboundBubbles.shift()!;
      pending.element.remove();
    }

    seenMessageIds.add(message.id);
    lastMessageId = message.id;
    renderBubble(messagesEl, message, (button) => sendText(button.id, button.label));
  }

  async function pollForReplies() {
    if (!lastMessageId) return;
    try {
      const { data } = await widgetRequest<{ data: WidgetMessage[] }>(
        widgetKey!,
        visitorId,
        `/messages?since=${encodeURIComponent(lastMessageId)}`,
      );
      data.forEach(trackMessage);
    } catch {
      // ignore transient polling failures
    }
  }

  async function bootstrap() {
    if (bootstrapped) return;
    bootstrapped = true;
    try {
      const { data } = await widgetRequest<{
        data: { welcomeMessage: string | null; conversationId: string; messages: WidgetMessage[] };
      }>(widgetKey!, visitorId, "/bootstrap");

      if (data.messages.length === 0 && data.welcomeMessage) {
        renderBubble(messagesEl, {
          id: "welcome",
          direction: "outbound",
          text: data.welcomeMessage,
          timestamp: new Date().toISOString(),
        });
      }
      data.messages.forEach(trackMessage);

      pollTimer = setInterval(pollForReplies, POLL_INTERVAL_MS);
    } catch {
      bootstrapped = false;
    }
  }

  let sending = false;

  async function sendText(text: string, displayText?: string) {
    if (sending || !text) return;
    sending = true;
    input.disabled = true;
    sendButton.disabled = true;
    const pendingBubble = renderBubble(messagesEl, {
      id: `pending-${Date.now()}`,
      direction: "inbound",
      text: displayText ?? text,
      timestamp: new Date().toISOString(),
    });
    pendingInboundBubbles.push({ text: displayText ?? text, element: pendingBubble });
    try {
      const { data } = await widgetRequest<{ data: { reply: string; message: WidgetMessage } }>(
        widgetKey!,
        visitorId,
        "/messages",
        { method: "POST", body: JSON.stringify({ text }) },
      );
      trackMessage(data.message);
    } catch {
      // best-effort: leave the composer usable for retry
    } finally {
      sending = false;
      input.disabled = false;
      sendButton.disabled = false;
      input.focus();
    }
  }

  async function handleSend() {
    const text = input.value.trim();
    if (!text) return;
    input.value = "";
    await sendText(text);
  }

  sendButton.addEventListener("click", handleSend);
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      handleSend();
    }
  });

  launcher.addEventListener("click", () => {
    const isOpen = panel.classList.toggle("open");
    if (isOpen) bootstrap();
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initWidget);
} else {
  initWidget();
}
