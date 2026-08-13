# OmniChat CRM — Laravel 13 Backend Architecture Guide

This document describes how to build the Laravel 13 (PHP 8.4) backend that powers the OmniChat CRM React frontend already implemented in this project. It covers system architecture, data models, WhatsApp/Instagram integration, campaign infrastructure, best practices, and known challenges.

## 1. High-Level Architecture

```
┌────────────────────┐      HTTPS/JSON       ┌──────────────────────────┐
│  React Frontend     │ <───────────────────> │  Laravel 13 API (PHP 8.4)│
│  (this project)     │      REST + WS        │                          │
└────────────────────┘                        └───────────┬──────────────┘
                                                            │
                        ┌───────────────────────────────────┼───────────────────────────────┐
                        │                                    │                               │
                ┌───────▼────────┐               ┌───────────▼──────────┐         ┌──────────▼─────────┐
                │ MySQL/Postgres │               │ Queue Workers (Redis) │         │ WebSocket Server    │
                │ (primary DB)   │               │ Laravel Horizon       │         │ Laravel Reverb /    │
                └────────────────┘               └───────────┬──────────┘         │ Pusher              │
                                                              │                    └─────────────────────┘
                                              ┌───────────────┴────────────────┐
                                              │                                 │
                                   ┌──────────▼─────────┐         ┌────────────▼────────────┐
                                   │ WhatsApp Cloud API   │         │ Instagram Graph API      │
                                   │ (Meta Business)      │         │ (Meta Business)          │
                                   └──────────────────────┘         └──────────────────────────┘
```

**Core principle:** Laravel is the system of record and orchestration layer. All inbound messages arrive via Meta webhooks, get normalized into a unified schema, and are pushed to the frontend in real time via WebSockets. Outbound messages (replies, campaigns) are queued and sent through the respective Graph API.

## 2. Recommended Stack

- **Framework:** Laravel 13, PHP 8.4 (take advantage of native property hooks, asymmetric visibility, and improved performance).
- **Database:** PostgreSQL (preferred for JSONB support with custom fields/tags) or MySQL 8.
- **Cache/Queue:** Redis, managed with **Laravel Horizon** for queue monitoring.
- **Real-time:** **Laravel Reverb** (first-party WebSocket server) or Pusher for broadcasting new messages/status updates to the Inbox.
- **Auth:** Laravel Sanctum for SPA token/cookie auth between React and Laravel.
- **Authorization:** Laravel Policies + Spatie `laravel-permission` for role-based access (Admin, Agent, Marketer).
- **Media storage:** S3-compatible object storage for attachments (images, documents, audio notes).
- **Search:** Laravel Scout + Meilisearch/Typesense for fast contact/conversation search at scale.
- **Job scheduling:** Laravel's scheduler (`schedule:run` via cron) for scheduled campaign dispatch.

## 3. Core Data Model

| Table | Key Columns | Notes |
|---|---|---|
| `users` | id, name, email, role | CRM agents/admins |
| `contacts` | id, name, phone, instagram_handle, email, avatar_url, pipeline_stage_id, deal_value, custom_fields (JSON) | Unified customer profile |
| `tags` / `contact_tag` | id, name / contact_id, tag_id | Many-to-many segmentation |
| `pipeline_stages` | id, name, position, color | Configurable Kanban stages |
| `conversations` | id, contact_id, channel (enum: whatsapp/instagram), status, assigned_agent_id, last_message_at | One thread per contact per channel |
| `messages` | id, conversation_id, direction, body, media_url, external_message_id, status, sent_at | Status: queued/sent/delivered/read/failed |
| `chat_flows` | id, name, trigger_type, trigger_value, is_active | Automation rule definitions |
| `chat_flow_steps` | id, chat_flow_id, order, condition, response_template | Rule-based branching |
| `campaigns` | id, name, channel, message_template, audience_filter (JSON), status, scheduled_at | Broadcast definitions |
| `campaign_recipients` | id, campaign_id, contact_id, status, delivered_at, read_at, replied_at | Per-recipient delivery tracking |
| `activity_logs` | id, subject_type, subject_id, description, created_at | Unified activity feed |
| `webhook_events` | id, provider, payload (JSON), processed_at | Raw event audit trail (critical for debugging Meta webhooks) |

Use **UUIDs** for any ID that may be referenced externally (e.g., campaign IDs shared with Meta), and enable **soft deletes** on `contacts` and `conversations` for compliance/audit purposes.

## 4. WhatsApp Integration (Meta Cloud API)

1. Register a Meta Business App, connect a WhatsApp Business Account (WABA), and obtain a permanent access token.
2. Implement a **webhook endpoint** (`POST /api/webhooks/whatsapp`) to receive inbound messages and status updates. Verify the webhook using the `hub.verify_token` challenge on `GET`.
3. Normalize the incoming payload into your unified `messages`/`conversations` schema inside a queued job (`ProcessInboundWhatsAppMessage`) — never do heavy processing synchronously in the webhook controller; just validate signature and dispatch a job, then return `200` immediately (Meta retries aggressively on non-200/timeouts).
4. Outbound sends go through the **WhatsApp Cloud API** `POST /messages` endpoint. Respect the **24-hour customer service window** — outside this window you can only send pre-approved **message templates** (required for campaigns).
5. Submit and manage **message templates** via the Graph API for use in broadcast campaigns; template approval can take hours, so build an async status-tracking flow in the Campaign Builder.
6. Use a dedicated **rate limiter** (Laravel's built-in rate limiting + queue throttling) to stay within Meta's per-number messaging tier limits.

## 5. Instagram Integration (Meta Graph API)

1. Instagram messaging requires an **Instagram Professional account linked to a Facebook Page**, connected via the same Meta Business app.
2. Subscribe to the `messages`, `messaging_postbacks`, and `messaging_seen` webhook fields on the Page.
3. Instagram DMs have their own **24-hour messaging window** rule (human agent tag exceptions exist for limited cases) — enforce this in the campaign/broadcast layer to avoid failed sends.
4. Normalize Instagram payloads into the same `messages` table using a `channel = instagram` discriminator so the Inbox UI can render both channels uniformly.
5. Media (images, story replies) should be fetched and re-uploaded to your own storage promptly, since Meta's CDN URLs expire.

## 6. Unified Inbox & Real-Time Delivery

- On inbound webhook processing, broadcast a Laravel Event (e.g., `MessageReceived`) over a private channel (`private-conversation.{id}`) using Reverb/Pusher.
- Frontend subscribes via Laravel Echo and appends messages/updates the conversation list without polling.
- Broadcast delivery/read receipts (`MessageStatusUpdated`) the same way to update ticks in the chat window in real time.
- Use **database transactions** when updating conversation `last_message_at` and unread counters to avoid race conditions under concurrent webhook delivery.

## 7. Automated Chat Flows (Rule-Based Automation)

- Model flows as a simple trigger → condition → action pipeline (`chat_flows`, `chat_flow_steps`).
- Triggers: keyword match, first message from new contact, business-hours fallback, no-reply timeout.
- Execute flow evaluation inside the same inbound-message job, before/after human routing logic.
- Keep the rule engine simple (JSON-based conditions) initially; avoid building a full workflow engine unless usage demands it — this is the most common over-engineering trap in CRM projects.

## 8. Campaign & Broadcast Infrastructure

1. **Audience resolution:** Translate saved tag/filter combinations into a contact query at send time (not at creation time) so lists stay fresh.
2. **Dispatch:** A scheduled command (`campaigns:dispatch`) run every minute checks for campaigns whose `scheduled_at` has passed and dispatches one queued job per recipient (`SendCampaignMessageJob`), respecting per-channel rate limits and the messaging-window rule.
3. **Tracking:** Update `campaign_recipients.status` from delivery/read webhook events by matching `external_message_id`.
4. **Analytics:** Aggregate delivered/read/replied counts via scheduled jobs or database views feeding the Analytics & Reporting page.
5. Use **idempotency keys** per recipient to avoid double-sends if a job retries after a transient failure.

## 9. API Design for the React Frontend

- Expose a versioned REST API (`/api/v1/...`) with resources: `contacts`, `conversations`, `messages`, `pipeline-stages`, `campaigns`, `chat-flows`, `analytics`.
- Use **Laravel API Resources** for consistent JSON shaping matching the frontend's TypeScript types.
- Paginate list endpoints (contacts, messages) with cursor pagination for the Inbox's infinite scroll.
- Provide a `PATCH /contacts/{id}/pipeline-stage` endpoint dedicated to Kanban drag-and-drop updates, plus optimistic-concurrency handling (`updated_at` version check) to avoid overwriting concurrent stage changes.

## 10. Security & Compliance

- Store Meta access tokens encrypted (Laravel's built-in `encrypted` cast) and rotate them before expiry.
- Verify every incoming webhook's `X-Hub-Signature-256` against your app secret — reject unsigned/invalid requests.
- Apply strict **PII handling**: mask phone numbers/emails in logs, encrypt sensitive custom fields at rest.
- Respect WhatsApp/Instagram **opt-out** requests immediately (STOP keyword handling) and suppress future campaign sends for that contact.
- Rate-limit your own public API endpoints (Sanctum + Laravel throttle middleware) separately from Meta's outbound limits.

## 11. Testing & Observability

- Use Laravel's HTTP fake (`Http::fake()`) to simulate Meta API responses in tests; never hit real endpoints in CI.
- Record every webhook payload in `webhook_events` before processing — this is invaluable for replaying/debugging integration issues.
- Instrument with Laravel Telescope (dev) and a production APM (e.g., Sentry) to catch webhook failures and job exceptions early.
- Track queue health via Horizon dashboards; alert on job backlog growth (a strong signal of Meta API throttling or outages).

## 12. Key Challenges to Plan For

- **Template approval latency:** WhatsApp marketing messages require pre-approved templates; build campaign workflows assuming multi-hour/day approval delays.
- **Messaging window restrictions:** Both channels block free-form messages outside a 24-hour customer-initiated window — campaigns must gracefully fall back to templates or be blocked with clear UI messaging.
- **Webhook reliability:** Meta retries failed webhooks aggressively; ensure your endpoint is fast, idempotent, and queues heavy work instead of processing inline.
- **Rate limits & throughput tiers:** WhatsApp Business accounts start on lower messaging tiers (e.g., 250 unique contacts/24h) that scale with quality rating — factor this into campaign sizing and rollout plans.
- **Unified data modeling:** Keeping WhatsApp and Instagram message/contact shapes normalized without losing channel-specific metadata requires careful schema design (recommend a `channel_metadata` JSON column per message/contact).
- **Real-time scaling:** As conversation volume grows, a single WebSocket server can become a bottleneck — plan for horizontal scaling of Reverb/Pusher channels early.
- **Multi-tenant readiness:** If OmniChat CRM will serve multiple businesses, design tenant isolation (separate WABA/IG credentials per tenant) from day one rather than retrofitting later.

## 13. Suggested Build Order

1. Auth (Sanctum) + Contacts + Pipeline Stages CRUD.
2. WhatsApp webhook ingestion + unified Conversations/Messages + Reverb broadcasting.
3. Inbox reply sending (outbound API) + delivery/read receipt handling.
4. Instagram webhook ingestion (reuse unified schema).
5. Chat Flows automation engine.
6. Campaigns: audience builder, template management, scheduler, dispatch jobs, analytics rollups.
7. Reporting/analytics endpoints and dashboards.
