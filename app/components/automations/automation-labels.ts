import type { AutomationActionType, AutomationTriggerType, ConditionField } from "~/data/types";

export const TRIGGER_LABELS: Record<AutomationTriggerType, string> = {
  keyword: "Message contains keyword",
  "new-message": "New message received",
  "new-contact": "New contact created",
  "no-reply": "No reply within 24 hours",
  "story-mention": "Instagram story mention",
  "comment-received": "Instagram comment received",
};

export const ACTION_LABELS: Record<AutomationActionType, string> = {
  "send-message": "Send message",
  "ai-reply": "AI-generated reply",
  "add-tag": "Add tag",
  "move-pipeline-stage": "Move pipeline stage",
  "assign-agent": "Assign agent",
  "notify-team": "Notify team",
  "send-whatsapp-flow": "Send WhatsApp Flow",
  "send-instagram-dm": "Send Instagram DM",
};

export const CONDITION_FIELD_LABELS: Record<ConditionField, string> = {
  tag: "Contact tag",
  pipelineStage: "Pipeline stage",
  messageContains: "Message contains",
};
