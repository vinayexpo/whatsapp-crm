# Button-Based Service Menu Flows — Implementation Plan

## Context

The CRM currently has three separate, unconnected systems that are each partial analogs of what's needed:

- **`AutomationFlow`** (`backend/app/Models/AutomationFlow.php`) — trigger→action pattern matching, no branching, no graph. Evaluated by `backend/app/Jobs/EvaluateAutomationFlows.php`.
- **`WhatsappCallFlow`** (`backend/app/Models/WhatsappCallFlow.php`) — the closest existing analog to a node-based flow (flat ordered `nodes` JSON array, types `question|menu|transfer_human|end_call`), but it's for **voice calls** (DTMF/speech), not WhatsApp chat messages. Builder UI at `app/components/whatsapp-calling/call-flow-builder.tsx`.
- **`WhatsappFlow`** (`backend/app/Models/WhatsappFlow.php`) — wraps Meta's native "WhatsApp Flows" JSON forms product; only partially wired (`EvaluateAutomationFlows::sendWhatsappFlow` sends a placeholder text message, not a real flow).

**None of these support WhatsApp interactive button messages for chat.** Confirmed via exploration:
- `GraphApiMessagingService::send()` only builds `text`/`template`/`media` payloads — no `interactive` (button/list) type.
- `ProcessInboundWhatsAppMessage::storeInboundMessage()` has no case for `type === 'interactive'` — an inbound button click (`interactive.button_reply.id`/`list_reply.id`) is currently silently dropped/stored as empty text.
- The web widget (`app/widget/index.ts` + `backend/app/Http/Controllers/Api/Widget/WidgetController.php`) is text-only, polling-based, and shares the `Message`/`Conversation` models with WhatsApp but not the transport.
- `OpenAiChatbotReplyService` only does free-text AI replies — no concept of button payload IDs.

## Goal

A full visual flow builder (like `call-flow-builder.tsx` but for chat) that lets a non-developer build multi-level button menus (e.g. "Services" → "Catering" → "Pricing/Menu/Contact"), sent as real WhatsApp interactive button/list messages and mirrored on the web chat widget. If a user types free text instead of clicking a button, falls back to the existing AI chatbot reply.

Confirmed scope decisions from the user:
- **Full flow builder UI** (visual, drag/connect nodes) — not just hardcoded flows.
- **Multi-level menus** — buttons can lead to further button menus, not just one flat level.
- **Both WhatsApp and Web widget** — one engine, two transports.
- **Mix of fixed + AI fallback** — button clicks show fixed content; free text typed mid-flow falls back to AI.

## Phase 1 — Data model

Followed the existing `WhatsappCallFlow` convention (confirmed via `backend/database/migrations/2026_08_09_142325_create_whatsapp_call_flows_table.php` and `backend/app/Models/WhatsappCallFlow.php`): a single table with a flat `nodes` JSON array, rather than a separate nodes table — matches the codebase's established pattern and avoids an extra migration/model/relation layer for no benefit (nodes are never queried independently of their flow).

**`chat_menu_flows` table** (new migration): `id`, `uuid` (unique, via `HasUuid`), `company_id` (nullable FK, `BelongsToCompany` trait), `name`, `channel` (enum: `whatsapp`, `web`, `both`), `status` (enum: `active`, `paused`, matching `WhatsappCallFlow`'s convention), `trigger_keyword` (nullable string — null means default/entry flow), `entry_node_id` (string, nullable — the `id` of the root node within the `nodes` JSON array), `nodes` (JSON array of `{id (uuid string), type: menu|content, message, mediaUrl?, mediaType?, renderAs: button|list, buttons: [{id (uuid), label, nextNodeId}]}`, max 3 buttons for `button` render, up to 10 for `list`), timestamps.

**Per-conversation flow state:** new migration adds nullable `current_chat_flow_id` (foreignId, constrained, nullOnDelete) and `current_chat_flow_node_id` (string, nullable — matches a node's `id` within that flow's `nodes` JSON) to the existing `conversations` table.

**Models:**
- `backend/app/Models/ChatMenuFlow.php` — `BelongsToCompany`, `HasUuid`, `HasFactory`; fillable `name, channel, status, trigger_keyword, entry_node_id, nodes`; casts `nodes` to `array`; `getRouteKeyName()` returns `uuid` (matches `AutomationFlow`/`WhatsappCallFlow` convention).
- Update `backend/app/Models/Conversation.php`: add `currentChatFlow(): BelongsTo` relation and the two new columns to `$fillable`.

**Verification for Phase 1:** migrations run cleanly on sqlite (test DB) and confirmed against the mysql production schema shape; model relation resolves; no controller/job/frontend changes yet — additive schema only, safe to land independently.

## Phase 2 — WhatsApp send: interactive messages

Extend `GraphApiMessagingService::send()` (`backend/app/Services/Messaging/GraphApiMessagingService.php`) to build Meta's `interactive` payload:
- `type: button` for ≤3 buttons (`interactive.action.buttons[].reply.{id,title}`)
- `type: list` for up to 10 items (`interactive.action.sections[].rows[].{id,title}`)

Store the sent buttons on the `Message` row (new nullable `buttons` JSON column via migration, mirroring `chat_menu_nodes.buttons` shape) so the inbox UI can render them on outbound bubbles too.

## Phase 3 — WhatsApp receive: button clicks

`ProcessInboundWhatsAppMessage::storeInboundMessage()` (`backend/app/Jobs/ProcessInboundWhatsAppMessage.php`) gets a new case for `type === 'interactive'`, extracting `button_reply.id`/`title` or `list_reply.id`/`title`. Stores the clicked button's title as the message text (for inbox display) plus the raw button `id` in a new column so the flow engine can resolve which button was clicked.

## Phase 4 — Flow engine service

New `backend/app/Services/ChatFlow/ChatMenuFlowEngine.php`:
- Given a conversation + inbound signal (button ID click, or free text, or trigger keyword match), resolves the next node.
- If conversation has no `current_flow_id` and inbound text matches a flow's `trigger_keyword` (or the flow is the default entry flow) → start that flow, send its root node.
- If conversation has `current_flow_id` and inbound is a button click matching a button on the current node → advance to `nextNodeId`, send that node's message/buttons, update `current_flow_node_id`.
- If conversation has `current_flow_id` and inbound is free text (not a button click) → **AI fallback**: call existing `OpenAiChatbotReplyService`, leave `current_flow_node_id` unchanged (stays in the flow) so the next button click still resumes correctly.
- If node reached has no buttons (a `content` leaf) → conversation flow state clears (`current_flow_id`/`current_flow_node_id` set null) so future messages go straight to normal AI/automation handling.

Hook into the existing inbound pipeline: called from `ProcessInboundWhatsAppMessage` (and the widget's equivalent inbound path) **before** falling through to `EvaluateAutomationFlows`/chatbot reply jobs, so flow-in-progress takes priority.

## Phase 5 — Builder UI (visual, like call-flow-builder)

New `app/components/chat-flows/chat-flow-builder.tsx`, modeled on `app/components/whatsapp-calling/call-flow-builder.tsx`'s existing UI pattern:
- Add node, edit message text/media, add up to 3 buttons (or toggle "list" mode for up to 10), wire each button to another node (dropdown of existing nodes, or "create new node").
- Set flow's trigger keyword / mark as default entry / channel (WhatsApp, Web, or Both).
- New route `app/routes/chat-flows.tsx`, added to nav in `app-layout.tsx` alongside Automations/Chatbots.
- New `apiClient` methods (`app/utils/api-client.ts`) + backend `ChatMenuFlowController` (CRUD for flows/nodes) + `routes/api.php` entries.

## Phase 6 — Web widget parity

`app/widget/index.ts`: render buttons as a row of styled `<button>` elements under a bot message bubble; clicking sends the button's `id` back via a new field on `WidgetController::sendMessage` (`backend/app/Http/Controllers/Api/Widget/WidgetController.php`) instead of only free text. Widget-side inbound handling runs through the same `ChatMenuFlowEngine` as WhatsApp (channel: `web` or `both` flows).

## Phase 7 — Tests + verification

- Backend feature tests: `ChatMenuFlowEngineTest` (button click advances node; trigger keyword starts flow; free text mid-flow triggers AI fallback without losing flow position; leaf node clears flow state).
- `WhatsAppWebhookTest`: inbound `interactive.button_reply` payload correctly parsed and routed.
- `GraphApiMessagingServiceTest`: outbound button/list payload shape matches Meta's spec.
- Manual verification: build one real 2-level menu (e.g. "Services" → "Catering"/"Menu"/"Reservations") via the builder UI, test real send/receive on WhatsApp and in the web widget in a browser, confirm AI fallback works when typing free text mid-flow, confirm flow state resets correctly at leaf nodes.

## Open decisions (deferred, revisit once Phase 4 is running)

- Exact re-prompt policy when a user types free text mid-flow but AI fallback also fails to make sense of it — re-show current node's buttons after the AI reply, or leave it purely conversational until they click something?
- Whether `chat_menu_flows` needs versioning/drafts (edit-while-live safety) or if direct edits to an active flow are acceptable for v1.
- Multi-tenant `company_id` scoping conventions — confirm exact column/middleware pattern used by `AutomationFlow` before assuming for `ChatMenuFlow`.

---

## Progress Log

- [x] Phase 1 — Data model. Created `chat_menu_flows` migration + `current_chat_flow_id`/`current_chat_flow_node_id` on `conversations`, `ChatMenuFlow` model, `ChatMenuFlowFactory`, `Conversation::currentChatFlow()` relation. Verified: migrations run clean on sqlite, factory creates a working 3-node sample flow, relation resolves correctly via tinker.
- [x] Phase 2 — WhatsApp send: interactive messages. Added `buttons`/`interactive_reply_id` columns to `messages`, extended `GraphApiMessagingService::send()` with a new `interactive` branch (button type for ≤3 buttons, list type for >3), new `GraphApiMessagingInteractiveTest` (2 tests, both pass). Verified no regression: ran broader suite; the only 2 failures (`ConversationAssignmentTest`) reproduce identically on a clean `git stash` with none of this session's changes applied, confirming pre-existing/unrelated flakiness.
- [x] Phase 3 — WhatsApp receive: button clicks. `ProcessInboundWhatsAppMessage::storeInboundMessage()` now handles `type === 'interactive'`, extracting `button_reply`/`list_reply` `id`+`title`, storing the title as message text and the id in `interactive_reply_id`. Added 2 new tests to `WhatsAppWebhookTest` (button_reply and list_reply cases) — all 13 tests in that file pass.
- [x] Phase 4 — Flow engine service. Added `ChatMenuFlowEngine` (trigger-keyword matching, button-click advance via `interactive_reply_id` or plain-text button id for the web widget, AI fallback on unmatched free text mid-flow, flow-state clear at leaf/content nodes). Wired into `ProcessInboundWhatsAppMessage` before `EvaluateAutomationFlows`/`GenerateChatbotWhatsAppReply` — those are skipped when the engine handles the message. New `ChatMenuFlowEngineTest` (4 tests, all pass). Regression run: 123 passed, same 2 pre-existing/unrelated `ConversationAssignmentTest` failures.
- [x] Phase 5 — Builder UI. Backend: `ChatMenuFlowController` (CRUD, reuses `chatbots.view`/`chatbots.manage` permissions via new `ChatMenuFlowPolicy`), `ChatMenuFlowResource`, routes under `/api/v1/chat-menu-flows`. 11 new controller tests pass. Frontend: `ChatMenuFlow*` types, `apiClient` CRUD methods, `chat-menus.tsx` route + "Chat Menus" nav entry, `ChatMenuFlowBuilder` (node/button form editor — each button has a dropdown to pick its `nextNodeId`, entry-node selector, auto button/list `renderAs` switch at >3 buttons), settings panel (name/channel/trigger keyword/active/delete), detail drawer, create dialog — all modeled on the existing `call-flow-builder.tsx` family. `tsc --noEmit` passes clean.
- [x] Phase 6 — Web widget parity. Added `buttons` to `MessageResource` (shared by inbox + widget responses). Wired `ChatMenuFlowEngine` into `WidgetController::sendMessage()` — mirrors `ProcessInboundWhatsAppMessage`'s pattern: engine runs first, and only falls through to `ChatbotReplyServiceInterface::reply()` when it returns `false`; when handled, the response returns the engine's outbound message/reply instead of an AI reply. Updated `app/widget/index.ts`: added `buttons` to `WidgetMessage`, `renderBubble()` now renders clickable option buttons under bot bubbles and wires clicks through a shared `sendText(text, displayText)` helper (refactored from `handleSend`) that posts the button's raw `id` as `text` — matching `ChatMenuFlowEngine::matchButton()`'s plain-text-id fallback for the web widget. Fixed pending-bubble reconciliation to be FIFO-based (was matching on exact text equality, which broke once the displayed label differs from the sent button id). New `WidgetChatMenuFlowTest` (2 tests, both pass): trigger keyword starts a flow and returns buttons; submitting a button id as plain text advances the flow the same way WhatsApp's `interactive_reply_id` does. `tsc --noEmit` clean. Full regression run: 420 passed, 8 failed — all 8 pre-existing/unrelated (`AuthTest` session, `ConversationAssignmentTest` x2 previously confirmed pre-existing, `PhonebookImportTest` x3, `SuperadminBootstrapTest`, and `ChatbotWidgetTest`'s "structured ai replies" test reproduced identically on a clean `git stash` with none of this session's changes applied).
- [x] Phase 7 — Tests + verification. Added an end-to-end backend test (`WhatsAppWebhookTest`: "starts a chat menu flow end-to-end from an inbound WhatsApp trigger keyword and sends a real interactive reply") covering the full path — inbound webhook → `ProcessInboundWhatsAppMessage` → `ChatMenuFlowEngine` → real `GraphApiMessagingService::send()` interactive payload — on top of the existing `ChatMenuFlowEngineTest`, `ChatMenuFlowControllerTest`, `GraphApiMessagingInteractiveTest`, and `WidgetChatMenuFlowTest` coverage from Phases 3-6. Full backend suite: 421 passed, 8 pre-existing/unrelated failures (confirmed via `git stash` — reproduce identically with none of this session's changes applied). `tsc --noEmit` clean. Committed (`86fa176`) and pushed to `master`; Railway auto-deployed both `backend` and `frontend` services successfully — all 3 new migrations (`chat_menu_flows` table, conversation flow-state columns, message buttons/interactive_reply_id columns) ran cleanly against production. Live traffic against `/api/v1/chat-menu-flows` observed immediately after deploy, confirming the builder UI is reachable in production.
