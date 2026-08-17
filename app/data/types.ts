export type ChannelType = "whatsapp" | "instagram" | "website" | "voice";

export type PipelineStageId = "new-lead" | "contacted" | "qualified" | "negotiation" | "won" | "lost";

export interface PipelineStage {
  id: PipelineStageId;
  name: string;
  color: string;
}

export interface Contact {
  id: string;
  name: string;
  avatarUrl: string;
  channel: ChannelType;
  handle: string;
  phone?: string;
  email?: string;
  location?: string;
  tags: string[];
  pipelineStage: PipelineStageId;
  dealValue: number;
  lastInteractionAt: string;
  createdAt: string;
  updatedAt: string;
  notes: string[];
  purchases: { id: string; item: string; amount: number; date: string }[];
}

export type MessageDirection = "inbound" | "outbound";

export type MessageStatus = "sent" | "delivered" | "read" | "failed";

export interface Message {
  id: string;
  conversationId: string;
  direction: MessageDirection;
  text: string;
  timestamp: string;
  status: MessageStatus;
  attachmentUrl?: string;
  attachmentType?: "image" | "document" | "audio" | "video";
}

export type ConversationStatus = "open" | "pending" | "resolved" | "archived";

export interface ConversationAssignee {
  id: string;
  name: string;
  avatarUrl: string;
}

export interface Conversation {
  id: string;
  contactId: string;
  channel: ChannelType;
  status: ConversationStatus;
  unreadCount: number;
  lastMessagePreview: string;
  lastMessageAt: string;
  assignedTo: ConversationAssignee | null;
}

export type CampaignStatus = "draft" | "scheduled" | "active" | "completed";

export interface Campaign {
  id: string;
  name: string;
  channel: ChannelType | "both";
  status: CampaignStatus;
  message: string;
  attachmentUrl: string | null;
  attachmentType: "image" | "video" | null;
  templateId: string | null;
  templateVariables: Record<string, string> | null;
  audienceTag: string | null;
  phonebookFolderId: string | null;
  phonebookFolderName: string | null;
  recipientCount: number;
  deliveredCount: number;
  readCount: number;
  repliedCount: number;
  failedCount: number;
  scheduledAt: string | null;
  createdAt: string;
  voiceAgentId: string | null;
  voiceAgentName: string | null;
  whatsappCallFlowId: string | null;
  whatsappCallFlowName: string | null;
}

export interface CampaignRecipient {
  id: string;
  contactName: string | null;
  contactHandle: string | null;
  status: "pending" | "sent" | "failed";
  failureReason: string | null;
  sentAt: string | null;
  deliveredAt: string | null;
  readAt: string | null;
  repliedAt: string | null;
}

export interface PhonebookFolder {
  id: string;
  name: string;
  contactCount: number;
  contacts: Contact[];
  createdAt: string;
  updatedAt: string;
}

export interface ImportError {
  row: number;
  message: string;
}

export interface ImportSummary {
  created: number;
  attached: number;
  skipped: number;
  errors: ImportError[];
}

export type WhatsappTemplateStatus = "draft" | "pending" | "approved" | "rejected";

export interface WhatsappTemplateComponent {
  type: "HEADER" | "BODY" | "FOOTER" | "BUTTONS";
  format?: "TEXT" | "IMAGE" | "VIDEO" | "DOCUMENT";
  text?: string;
  buttons?: { type: string; text: string; url?: string; phone_number?: string }[];
}

export interface WhatsappTemplate {
  id: string;
  apiConnectionId: string;
  name: string;
  language: string;
  category: string;
  status: WhatsappTemplateStatus;
  body: string;
  variables: string[];
  components: WhatsappTemplateComponent[];
  createdByUserId: string | null;
  rejectionReason: string | null;
  submittedAt: string | null;
  syncedAt: string | null;
}

export type WhatsappFlowStatus = "draft" | "published" | "deprecated";

export interface WhatsappFlow {
  id: string;
  apiConnectionId: string;
  name: string;
  status: WhatsappFlowStatus;
  categories: string[];
  syncedAt: string;
}

export interface ActivityItem {
  id: string;
  type: "message" | "campaign" | "pipeline" | "contact";
  description: string;
  timestamp: string;
  channel?: ChannelType;
}

export interface DailyMetric {
  date: string;
  whatsappSent: number;
  instagramSent: number;
  delivered: number;
  read: number;
  replied: number;
  avgResponseMinutes: number;
}

export interface CampaignDashboardTotals {
  campaignCount: number;
  recipientCount: number;
  deliveredCount: number;
  readCount: number;
  repliedCount: number;
  deliveryRate: number;
  readRate: number;
  replyRate: number;
}

export interface CampaignDashboardTrendPoint {
  date: string;
  sent: number;
  delivered: number;
  read: number;
  replied: number;
}

export interface CampaignPerformer {
  id: string;
  name: string;
  channel: ChannelType | "both";
  recipientCount: number;
  deliveredCount: number;
  readCount: number;
  repliedCount: number;
  deliveryRate: number;
  readRate: number;
  replyRate: number;
}

export interface CampaignDashboardData {
  totals: CampaignDashboardTotals;
  trend: CampaignDashboardTrendPoint[];
  topPerformers: CampaignPerformer[];
  bottomPerformers: CampaignPerformer[];
}

export type AutomationTriggerType =
  | "keyword"
  | "new-message"
  | "new-contact"
  | "no-reply"
  | "story-mention"
  | "comment-received";

export interface AutomationTrigger {
  type: AutomationTriggerType;
  keyword?: string;
  channel: ChannelType | "both";
}

export type ConditionField = "tag" | "pipelineStage" | "messageContains";
export type ConditionOperator = "is" | "contains";

export interface AutomationCondition {
  id: string;
  field: ConditionField;
  operator: ConditionOperator;
  value: string;
}

export type AutomationActionType =
  | "send-message"
  | "ai-reply"
  | "add-tag"
  | "move-pipeline-stage"
  | "assign-agent"
  | "notify-team"
  | "send-whatsapp-flow"
  | "send-instagram-dm";

export interface AutomationAction {
  id: string;
  type: AutomationActionType;
  value: string;
}

export type AutomationStatus = "active" | "paused" | "draft";

export interface AutomationFlow {
  id: string;
  name: string;
  description: string;
  status: AutomationStatus;
  trigger: AutomationTrigger;
  conditions: AutomationCondition[];
  actions: AutomationAction[];
  triggeredCount: number;
  lastTriggeredAt: string | null;
  createdAt: string;
}

export type ApiConnectionStatus = "connected" | "disconnected";

export interface ApiConnection {
  id: string;
  channel: ChannelType;
  label: string;
  accountName: string;
  identifier: string;
  status: ApiConnectionStatus;
  wabaId: string | null;
  phoneNumberId: string | null;
  instagramAccountId: string | null;
  twilioAccountSid: string | null;
  twilioPhoneNumber: string | null;
  callingEnabled: boolean;
  callingStatus: "disabled" | "pending" | "active";
  callingVerifiedAt: string | null;
  connectedAt: string | null;
  webhooks: ApiConnectionWebhook[];
}

export interface ApiConnectionWebhook {
  label: string;
  url: string;
  verifyToken?: string;
}

export type WhatsappCallFlowStatus = "active" | "paused";
export type WhatsappCallFlowNodeType = "question" | "menu" | "transfer_human" | "end_call";

export interface WhatsappCallFlowNode {
  id: string;
  type: WhatsappCallFlowNodeType;
  prompt: string;
  options?: string[] | null;
  variable_key?: string | null;
  input_type?: string | null;
}

export interface WhatsappCallFlow {
  id: string;
  apiConnectionId: string | null;
  name: string;
  status: WhatsappCallFlowStatus;
  greetingMessage: string;
  nodes: WhatsappCallFlowNode[];
  fallbackMessage: string | null;
  maxRetries: number;
  createdAt: string;
}

export type WhatsappCallDirection = "outbound" | "inbound";
export type WhatsappCallStatus =
  | "ringing"
  | "in_progress"
  | "completed"
  | "failed"
  | "missed"
  | "needs_human_followup";

export interface WhatsappCallFollowupAssignee {
  id: string;
  name: string;
}

export interface WhatsappCall {
  id: string;
  callFlowId: string | null;
  contactId: string | null;
  conversationId: string | null;
  direction: WhatsappCallDirection;
  status: WhatsappCallStatus;
  metaCallId: string | null;
  sdpExchangeStatus: "pending_offer" | "offer_sent" | "answer_received" | "connected" | "failed";
  permissionRequestStatus: "sent" | "delivered" | "read" | "failed" | null;
  permissionRequestFailureReason: string | null;
  transcript: unknown[];
  collectedVariables: Record<string, unknown>;
  needsHumanFollowup: boolean;
  humanFollowupAssignedTo: WhatsappCallFollowupAssignee | null;
  humanFollowupCompletedAt: string | null;
  startedAt: string | null;
  endedAt: string | null;
  createdAt: string;
}

export type TeamMemberRole = "superadmin" | "admin" | "manager" | "agent";
export type TeamMemberStatus = "active" | "invited";

export interface TeamMember {
  id: string;
  numericId: number;
  name: string;
  email: string;
  avatarUrl: string;
  role: TeamMemberRole;
  status: TeamMemberStatus;
  addedAt: string;
  companyId: string | null;
}

export type CompanyStatus = "active" | "suspended";

export interface Company {
  id: string;
  name: string;
  status: CompanyStatus;
  createdAt: string;
}

export interface NotificationPreferences {
  newMessageAlerts: boolean;
  campaignCompleted: boolean;
  automationTriggered: boolean;
  dailySummaryEmail: boolean;
  weeklyAnalyticsReport: boolean;
  soundAlerts: boolean;
  whatsappCallAlerts: boolean;
  voiceCallAlerts: boolean;
  templateStatusAlerts: boolean;
}

export type NotificationType =
  | "new_message"
  | "campaign_completed"
  | "campaign_failed"
  | "automation_triggered"
  | "whatsapp_call_missed"
  | "whatsapp_call_completed"
  | "whatsapp_call_ringing"
  | "voice_call_completed"
  | "voice_call_missed"
  | "voice_call_ringing"
  | "template_approved"
  | "template_rejected"
  | "chatbot_handoff"
  | "job_failed";

export interface AppNotification {
  id: string;
  type: NotificationType;
  title: string;
  body: string;
  data: Record<string, unknown>;
  readAt: string | null;
  createdAt: string;
}

export interface AiAssistantSettings {
  baseUrl: string;
  apiKey: string | null;
  model: string;
}

export interface AiChatMessage {
  id: string;
  role: "user" | "assistant";
  content: string;
  timestamp: string;
}

export type ChatbotStatus = "active" | "inactive";

export type ChatbotChannel = "website" | "instagram" | "whatsapp";

export interface Chatbot {
  id: string;
  name: string;
  status: ChatbotStatus;
  widgetKey: string;
  allowedOrigins: string[];
  welcomeMessage: string | null;
  generalFallbackEnabled: boolean;
  channels: ChatbotChannel[];
  createdAt: string;
}

export type ChatbotTrainingEntrySource = "manual" | "ai";

export interface ChatbotTrainingEntry {
  id: string;
  question: string;
  answer: string;
  source: ChatbotTrainingEntrySource;
  createdAt: string;
}

export interface ChatbotTrainingCandidate {
  question: string;
  answer: string;
}

export type VoiceAgentStatus = "active" | "paused";
export type VoiceAgentVoiceMode = "tts" | "recorded";
export type QualificationFieldType = "text" | "number" | "boolean" | "choice";

export interface VoiceAgentQualificationCriterion {
  key: string;
  label: string;
  question: string;
  type: QualificationFieldType | string;
}

export interface VoiceAgent {
  id: string;
  name: string;
  status: VoiceAgentStatus;
  voiceMode: VoiceAgentVoiceMode;
  ttsVoiceId: string | null;
  greetingAudioUrl: string | null;
  qualificationCriteria: VoiceAgentQualificationCriterion[];
  qualificationPassCriteria: string | null;
  maxCallAttempts: number;
  systemPrompt: string | null;
  createdAt: string;
}

export type VoiceCallDirection = "inbound" | "outbound";
export type VoiceCallMedium = "browser_simulation" | "telephony";
export type VoiceCallStatus =
  | "queued"
  | "ringing"
  | "in_progress"
  | "completed"
  | "failed"
  | "no_answer"
  | "needs_human_followup";
export type VoiceCallQualificationOutcome = "qualified" | "not_qualified" | "uncertain";

export interface VoiceCallTranscriptTurn {
  role: "agent" | "lead";
  text: string;
  at?: string;
}

export interface VoiceCallFollowupAssignee {
  id: string;
  name: string;
}

export interface VoiceCall {
  id: string;
  voiceAgentId: string | null;
  contactId: string | null;
  conversationId: string | null;
  direction: VoiceCallDirection;
  medium: VoiceCallMedium;
  status: VoiceCallStatus;
  recordingUrl: string | null;
  transcript: VoiceCallTranscriptTurn[];
  qualificationFields: Record<string, unknown> | unknown[];
  qualificationOutcome: VoiceCallQualificationOutcome | null;
  qualificationSummary: string | null;
  needsHumanFollowup: boolean;
  humanFollowupAssignedTo: VoiceCallFollowupAssignee | null;
  humanFollowupCompletedAt: string | null;
  startedAt: string | null;
  endedAt: string | null;
  createdAt: string;
}

export const QUICK_REPLIES: string[] = [
  "Thanks for reaching out! How can I help you today?",
  "Our team will get back to you within a few hours.",
  "You can find our pricing details on our website's pricing page.",
  "Would you like to schedule a call with our sales team?",
];
