<?php

header("Content-Type: text/plain");

// backend lives at public_html/backend, this script at public_html/backend/public
// site root (where widget.js must be served from) is two levels up.
$target = __DIR__ . "/../../widget.js";

$content = <<<'JSEOF'
(function () {
  var storageKey = 'omnichat_widget_visitor_id';

  function getCurrentScript() {
    var script = document.currentScript;

    if (script) {
      return script;
    }

    var scripts = document.getElementsByTagName('script');

    return scripts[scripts.length - 1];
  }

  function getVisitorId() {
    var visitorId = window.localStorage.getItem(storageKey);

    if (!visitorId) {
      visitorId = crypto.randomUUID();
      window.localStorage.setItem(storageKey, visitorId);
    }

    return visitorId;
  }

  async function request(widgetKey, visitorId, path, options) {
    var response = await fetch('https://mintcream-jellyfish-201700.hostingersite.com/api/widget' + path, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-Widget-Key': widgetKey,
        'X-Visitor-Id': visitorId,
        ...(options && options.headers ? options.headers : {}),
      },
    });

    if (!response.ok) {
      throw new Error('Widget request failed: ' + response.status);
    }

    return response.json();
  }

  function injectStyles() {
    var style = document.createElement('style');
    style.textContent = [
      '.omnichat-widget-launcher {',
      '  position: fixed; bottom: 20px; right: 20px; width: 56px; height: 56px;',
      '  border-radius: 50%; background: #5B6EF5; color: #fff; border: none;',
      '  cursor: pointer; box-shadow: 0 4px 16px rgba(0,0,0,0.2); font-size: 24px;',
      '  z-index: 999999; display: flex; align-items: center; justify-content: center;',
      '}',
      '.omnichat-widget-panel {',
      '  position: fixed; bottom: 88px; right: 20px; width: 340px; max-width: calc(100vw - 40px);',
      '  height: 480px; max-height: calc(100vh - 120px); background: #fff; border-radius: 12px;',
      '  box-shadow: 0 8px 32px rgba(0,0,0,0.25); z-index: 999999; display: none;',
      '  flex-direction: column; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, sans-serif;',
      '}',
      '.omnichat-widget-panel.open { display: flex; }',
      '.omnichat-widget-header {',
      '  background: #5B6EF5; color: #fff; padding: 14px 16px; font-weight: 600; font-size: 15px;',
      '}',
      '.omnichat-widget-messages {',
      '  flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px;',
      '}',
      '.omnichat-widget-bubble {',
      '  max-width: 80%; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.4;',
      '  white-space: pre-wrap; word-break: break-word;',
      '}',
      '.omnichat-widget-bubble.contact { align-self: flex-end; background: #5B6EF5; color: #fff; }',
      '.omnichat-widget-bubble.bot { align-self: flex-start; background: #f0f1f5; color: #1a1a1a; }',
      '.omnichat-widget-input-row {',
      '  display: flex; gap: 8px; padding: 10px; border-top: 1px solid #eee;',
      '}',
      '.omnichat-widget-input {',
      '  flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 8px 10px; font-size: 13px; outline: none;',
      '}',
      '.omnichat-widget-send {',
      '  background: #5B6EF5; color: #fff; border: none; border-radius: 8px; padding: 0 14px; cursor: pointer; font-size: 13px;',
      '}',
    ].join('\n');
    document.head.appendChild(style);
  }

  function appendBubble(container, message) {
    var bubble = document.createElement('div');
    bubble.className = 'omnichat-widget-bubble ' + (message.direction === 'inbound' ? 'contact' : 'bot');
    bubble.textContent = message.text;
    bubble.dataset.messageId = message.id;
    container.appendChild(bubble);
    container.scrollTop = container.scrollHeight;

    return bubble;
  }

  async function init() {
    var widgetKey = getCurrentScript().dataset.widgetKey;

    if (!widgetKey) {
      console.error('Creative Connects widget: missing data-widget-key attribute');
      return;
    }

    injectStyles();

    var visitorId = getVisitorId();
    var renderedMessageIds = new Set();
    var pendingInbound = [];
    var lastMessageId = null;

    var launcher = document.createElement('button');
    launcher.className = 'omnichat-widget-launcher';
    launcher.setAttribute('aria-label', 'Open chat');
    launcher.textContent = '💬';

    var panel = document.createElement('div');
    panel.className = 'omnichat-widget-panel';

    var header = document.createElement('div');
    header.className = 'omnichat-widget-header';
    header.textContent = 'Chat with us';

    var messages = document.createElement('div');
    messages.className = 'omnichat-widget-messages';

    var inputRow = document.createElement('div');
    inputRow.className = 'omnichat-widget-input-row';

    var input = document.createElement('input');
    input.className = 'omnichat-widget-input';
    input.placeholder = 'Type a message...';

    var sendButton = document.createElement('button');
    sendButton.className = 'omnichat-widget-send';
    sendButton.textContent = 'Send';

    inputRow.appendChild(input);
    inputRow.appendChild(sendButton);
    panel.appendChild(header);
    panel.appendChild(messages);
    panel.appendChild(inputRow);
    document.body.appendChild(launcher);
    document.body.appendChild(panel);

    function removePendingInboundIfMatched(message) {
      if (message.direction !== 'inbound') {
        return;
      }

      var pendingIndex = pendingInbound.findIndex(function (entry) {
        return entry.text === message.text;
      });

      if (pendingIndex === -1) {
        return;
      }

      pendingInbound[pendingIndex].element.remove();
      pendingInbound.splice(pendingIndex, 1);
    }

    function handleMessage(message) {
      if (renderedMessageIds.has(message.id)) {
        return;
      }

      removePendingInboundIfMatched(message);
      renderedMessageIds.add(message.id);
      lastMessageId = message.id;
      appendBubble(messages, message);
    }

    async function poll() {
      if (!lastMessageId) {
        return;
      }

      try {
        var response = await request(widgetKey, visitorId, '/messages?since=' + encodeURIComponent(lastMessageId));
        response.data.forEach(handleMessage);
      } catch (error) {
      }
    }

    var bootstrapped = false;

    async function bootstrap() {
      if (bootstrapped) {
        return;
      }

      bootstrapped = true;

      try {
        var response = await request(widgetKey, visitorId, '/bootstrap');
        var data = response.data;

        if (data.messages.length === 0 && data.welcomeMessage) {
          appendBubble(messages, {
            id: 'welcome',
            direction: 'outbound',
            text: data.welcomeMessage,
            timestamp: new Date().toISOString(),
          });
        }

        data.messages.forEach(handleMessage);
        setInterval(poll, 4000);
      } catch (error) {
        bootstrapped = false;
      }
    }

    var sending = false;

    async function send() {
      if (sending) {
        return;
      }

      var text = input.value.trim();

      if (!text) {
        return;
      }

      sending = true;
      input.value = '';
      input.disabled = true;
      sendButton.disabled = true;

      var pendingElement = appendBubble(messages, {
        id: 'pending-' + Date.now(),
        direction: 'inbound',
        text: text,
        timestamp: new Date().toISOString(),
      });

      pendingInbound.push({ text: text, element: pendingElement });

      try {
        var response = await request(widgetKey, visitorId, '/messages', {
          method: 'POST',
          body: JSON.stringify({ text: text }),
        });

        handleMessage(response.data.message);
      } catch (error) {
      } finally {
        sending = false;
        input.disabled = false;
        sendButton.disabled = false;
        input.focus();
      }
    }

    sendButton.addEventListener('click', send);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        send();
      }
    });
    launcher.addEventListener('click', function () {
      if (panel.classList.toggle('open')) {
        bootstrap();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
JSEOF;

file_put_contents($target, $content);

echo "Written " . strlen($content) . " bytes to " . realpath($target) . "\n\n";
echo "Verify - first 300 chars read back:\n";
echo substr(file_get_contents($target), 0, 300);
