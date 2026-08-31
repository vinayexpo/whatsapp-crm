import type {
  ActivityItem,
  AiAssistantSettings,
  ApiConnection,
  ApiConnectionStatus,
  AppNotification,
  AutomationFlow,
  AutomationStatus,
  Campaign,
  CampaignDashboardData,
  CampaignRecipient,
  ChannelType,
  Chatbot,
  ChatbotChannel,
  ChatbotStatus,
  ChatbotTrainingCandidate,
  ChatbotTrainingEntry,
  ChatbotTrainingEntrySource,
  Company,
  Contact,
  Conversation,
  DailyMetric,
  ImportSummary,
  Message,
  NotificationPreferences,
  PaginatedResponse,
  PhonebookFolder,
  PipelineStage,
  PipelineStageId,
  TeamMember,
  TeamMemberRole,
  VoiceAgent,
  VoiceAgentQualificationCriterion,
  VoiceAgentStatus,
  VoiceAgentVoiceMode,
  VoiceCall,
  VoiceCallQualificationOutcome,
  VoiceCallTranscriptTurn,
  WhatsappTemplate,
  WhatsappTemplateComponent,
  WhatsappFlow,
  WhatsappCallFlow,
  WhatsappCallFlowNode,
  WhatsappCallFlowStatus,
  ChatMenuFlow,
  ChatMenuFlowNode,
  ChatMenuFlowChannel,
  ChatMenuFlowStatus,
  WhatsappCall,
} from "~/data/types";

// VITE_API_URL is a build-time Vite var — it must be set on the Railway
// frontend service and the service redeployed with a fresh build (a
// variable-only change reuses Railway's cached image) for changes to apply.
const API_BASE_URL = import.meta.env.VITE_API_URL ?? "https://mintcream-jellyfish-201700.hostingersite.com";

class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

const AUTH_TOKEN_STORAGE_KEY = "auth_token";

function getAuthToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);
}

function setAuthToken(token: string): void {
  window.localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token);
}

function clearAuthToken(): void {
  window.localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
}

async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body) {
    headers.set("Content-Type", "application/json");
  }
  const token = getAuthToken();
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(body?.message ?? "Request failed", response.status, body?.errors);
  }

  return body as T;
}

function buildQuery(params: Record<string, string | number | boolean | undefined>): string {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      query.set(key, String(value));
    }
  });
  const suffix = query.toString();
  return suffix ? `?${suffix}` : "";
}

interface RawPaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

function unwrapPaginated<T>(body: RawPaginatedResponse<T>): PaginatedResponse<T> {
  return {
    data: body.data,
    meta: {
      currentPage: body.meta.current_page,
      lastPage: body.meta.last_page,
      perPage: body.meta.per_page,
      total: body.meta.total,
    },
  };
}

async function login(email: string, password: string): Promise<TeamMember> {
  const { data, token } = await apiRequest<{ data: TeamMember; token: string }>("/api/v1/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  setAuthToken(token);
  return data;
}

async function logout(): Promise<void> {
  try {
    await apiRequest("/api/v1/auth/logout", { method: "POST" });
  } finally {
    clearAuthToken();
  }
}

async function bootstrapStatus(): Promise<boolean> {
  const { superadminExists } = await apiRequest<{ superadminExists: boolean }>("/api/v1/auth/bootstrap-status");
  return superadminExists;
}

async function setupSuperadmin(superadmin: { name: string; email: string; password: string }): Promise<TeamMember> {
  const { data, token } = await apiRequest<{ data: TeamMember; token: string }>("/api/v1/auth/setup-superadmin", {
    method: "POST",
    body: JSON.stringify(superadmin),
  });
  setAuthToken(token);
  return data;
}

async function listCompanies(): Promise<Company[]> {
  const { data } = await apiRequest<{ data: Company[] }>("/api/v1/companies");
  return data;
}

async function createCompany(company: { name: string }): Promise<Company> {
  const { data } = await apiRequest<{ data: Company }>("/api/v1/companies", {
    method: "POST",
    body: JSON.stringify(company),
  });
  return data;
}

async function listCompanyAdmins(companyId: string): Promise<TeamMember[]> {
  const { data } = await apiRequest<{ data: TeamMember[] }>(`/api/v1/companies/${companyId}/admins`);
  return data;
}

async function createCompanyAdmin(
  companyId: string,
  admin: { name: string; email: string; password: string },
): Promise<TeamMember> {
  const { data } = await apiRequest<{ data: TeamMember }>(`/api/v1/companies/${companyId}/admins`, {
    method: "POST",
    body: JSON.stringify(admin),
  });
  return data;
}

async function me(): Promise<TeamMember> {
  const { data } = await apiRequest<{ data: TeamMember }>("/api/v1/auth/me");
  return data;
}

async function updateProfile(updates: { name?: string; avatar?: File }): Promise<TeamMember> {
  const formData = new FormData();
  if (updates.name) {
    formData.append("name", updates.name);
  }
  if (updates.avatar) {
    formData.append("avatar", updates.avatar);
  }

  const headers = new Headers({ Accept: "application/json" });
  const token = getAuthToken();
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE_URL}/api/v1/auth/profile`, {
    method: "POST",
    body: formData,
    headers,
  });

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(body?.message ?? "Request failed", response.status, body?.errors);
  }

  return body.data as TeamMember;
}

async function apiUpload<T>(path: string, formData: FormData): Promise<T> {
  const headers = new Headers({ Accept: "application/json" });
  const token = getAuthToken();
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: "POST",
    body: formData,
    headers,
  });

  const body = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(body?.message ?? "Request failed", response.status, body?.errors);
  }

  return body as T;
}

async function apiDownload(path: string): Promise<Blob> {
  const headers = new Headers({ Accept: "*/*" });
  const token = getAuthToken();
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers,
  });

  if (!response.ok) {
    throw new ApiError("Request failed", response.status);
  }

  return response.blob();
}

function triggerBlobDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

async function listContacts(params?: {
  page?: number;
  perPage?: number;
  channel?: ChannelType;
  pipelineStage?: PipelineStageId;
  tag?: string;
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<Contact>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    channel: params?.channel,
    pipelineStage: params?.pipelineStage,
    tag: params?.tag,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<Contact>>(`/api/v1/contacts${query}`);
  return unwrapPaginated(body);
}

async function listPipelineStages(): Promise<PipelineStage[]> {
  const { data } = await apiRequest<{ data: PipelineStage[] }>("/api/v1/pipeline-stages");
  return data;
}

async function getPipelineFunnel(): Promise<{ stage: string; count: number }[]> {
  const { data } = await apiRequest<{ data: { stage: string; count: number }[] }>(
    "/api/v1/analytics/pipeline-funnel",
  );
  return data;
}

async function getContact(contactId: string): Promise<Contact> {
  const { data } = await apiRequest<{ data: Contact }>(`/api/v1/contacts/${contactId}`);
  return data;
}

async function updateContactPipelineStage(
  contactId: string,
  pipelineStage: PipelineStageId,
  updatedAt: string,
): Promise<Contact> {
  const { data } = await apiRequest<{ data: Contact }>(`/api/v1/contacts/${contactId}/pipeline-stage`, {
    method: "PATCH",
    body: JSON.stringify({ pipelineStage, updatedAt }),
  });
  return data;
}

async function addContactTag(contactId: string, tag: string): Promise<Contact> {
  const { data } = await apiRequest<{ data: Contact }>(`/api/v1/contacts/${contactId}/tags`, {
    method: "POST",
    body: JSON.stringify({ tag }),
  });
  return data;
}

async function updateContact(
  contactId: string,
  updates: Partial<Pick<Contact, "name" | "avatarUrl" | "phone" | "email" | "location" | "dealValue">>,
): Promise<Contact> {
  const { data } = await apiRequest<{ data: Contact }>(`/api/v1/contacts/${contactId}`, {
    method: "PATCH",
    body: JSON.stringify(updates),
  });
  return data;
}

async function listConversations(params?: {
  assignedTo?: string;
  contactId?: string;
  search?: string;
  status?: string;
  channel?: string;
  page?: number;
  perPage?: number;
}): Promise<PaginatedResponse<Conversation>> {
  const query = buildQuery({
    assignedTo: params?.assignedTo,
    contactId: params?.contactId,
    search: params?.search,
    status: params?.status,
    channel: params?.channel,
    page: params?.page,
    per_page: params?.perPage,
  });
  const body = await apiRequest<RawPaginatedResponse<Conversation>>(`/api/v1/conversations${query}`);
  return unwrapPaginated(body);
}

async function listMessages(
  conversationId: string,
  params?: { before?: string; limit?: number },
): Promise<{ data: Message[]; hasMore: boolean }> {
  const query = buildQuery({ before: params?.before, limit: params?.limit });
  const { data, meta } = await apiRequest<{ data: Message[]; meta: { hasMore: boolean } }>(
    `/api/v1/conversations/${conversationId}/messages${query}`,
  );
  return { data, hasMore: meta.hasMore };
}

async function sendMessage(conversationId: string, text: string, attachmentFile?: File | null): Promise<Message> {
  if (attachmentFile) {
    const formData = new FormData();
    formData.append("text", text);
    formData.append("attachmentFile", attachmentFile);
    const body = await apiUpload<{ data: Message }>(`/api/v1/conversations/${conversationId}/messages`, formData);
    return body.data;
  }

  const { data } = await apiRequest<{ data: Message }>(`/api/v1/conversations/${conversationId}/messages`, {
    method: "POST",
    body: JSON.stringify({ text }),
  });
  return data;
}

async function markConversationRead(conversationId: string): Promise<Conversation> {
  const { data } = await apiRequest<{ data: Conversation }>(`/api/v1/conversations/${conversationId}/read`, {
    method: "POST",
  });
  return data;
}

async function updateConversationStatus(
  conversationId: string,
  status: Conversation["status"],
): Promise<Conversation> {
  const { data } = await apiRequest<{ data: Conversation }>(`/api/v1/conversations/${conversationId}/status`, {
    method: "PATCH",
    body: JSON.stringify({ status }),
  });
  return data;
}

async function assignConversation(conversationId: string, userId: string | null): Promise<Conversation> {
  const { data } = await apiRequest<{ data: Conversation }>(`/api/v1/conversations/${conversationId}/assign`, {
    method: "PATCH",
    body: JSON.stringify({ userId }),
  });
  return data;
}

async function listAutomationFlows(params?: {
  page?: number;
  perPage?: number;
  status?: AutomationStatus;
  channel?: ChannelType | "both";
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<AutomationFlow>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    status: params?.status,
    channel: params?.channel,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<AutomationFlow>>(`/api/v1/automation-flows${query}`);
  return unwrapPaginated(body);
}

async function createAutomationFlow(
  flow: Pick<AutomationFlow, "name" | "description" | "status" | "trigger" | "conditions" | "actions">,
): Promise<AutomationFlow> {
  const { data } = await apiRequest<{ data: AutomationFlow }>("/api/v1/automation-flows", {
    method: "POST",
    body: JSON.stringify(flow),
  });
  return data;
}

async function updateAutomationFlowStatus(flowId: string, status: AutomationStatus): Promise<AutomationFlow> {
  const { data } = await apiRequest<{ data: AutomationFlow }>(`/api/v1/automation-flows/${flowId}/status`, {
    method: "PATCH",
    body: JSON.stringify({ status }),
  });
  return data;
}

async function deleteAutomationFlow(flowId: string): Promise<void> {
  await apiRequest(`/api/v1/automation-flows/${flowId}`, { method: "DELETE" });
}

async function listCampaigns(params?: {
  page?: number;
  perPage?: number;
  status?: string;
  channel?: ChannelType | "both";
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<Campaign>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    status: params?.status,
    channel: params?.channel,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<Campaign>>(`/api/v1/campaigns${query}`);
  return unwrapPaginated(body);
}

async function createCampaign(campaign: {
  name: string;
  channel?: ChannelType | "both";
  message?: string;
  attachmentUrl?: string | null;
  attachmentType?: "image" | "video" | null;
  attachmentFile?: File | null;
  audienceTag?: string | null;
  phonebookFolderId?: string | null;
  scheduledAt?: string | null;
  templateId?: string | null;
  templateVariables?: Record<string, string> | null;
  voiceAgentId?: string | null;
  whatsappCallFlowId?: string | null;
}): Promise<Campaign> {
  if (campaign.attachmentFile) {
    const formData = new FormData();
    formData.append("name", campaign.name);
    if (campaign.channel) formData.append("channel", campaign.channel);
    if (campaign.message) formData.append("message", campaign.message);
    formData.append("attachmentFile", campaign.attachmentFile);
    if (campaign.attachmentType) formData.append("attachmentType", campaign.attachmentType);
    if (campaign.audienceTag) formData.append("audienceTag", campaign.audienceTag);
    if (campaign.phonebookFolderId) formData.append("phonebookFolderId", campaign.phonebookFolderId);
    if (campaign.scheduledAt) formData.append("scheduledAt", campaign.scheduledAt);
    if (campaign.templateId) formData.append("templateId", campaign.templateId);
    if (campaign.templateVariables) {
      Object.entries(campaign.templateVariables).forEach(([key, value]) => {
        formData.append(`templateVariables[${key}]`, value);
      });
    }
    const body = await apiUpload<{ data: Campaign }>("/api/v1/campaigns", formData);
    return body.data;
  }

  const { attachmentFile, ...payload } = campaign;
  const { data } = await apiRequest<{ data: Campaign }>("/api/v1/campaigns", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return data;
}

async function deleteCampaign(campaignId: string): Promise<void> {
  await apiRequest(`/api/v1/campaigns/${campaignId}`, { method: "DELETE" });
}

async function listCampaignRecipients(campaignId: string): Promise<CampaignRecipient[]> {
  const { data } = await apiRequest<{ data: CampaignRecipient[] }>(`/api/v1/campaigns/${campaignId}/recipients`);
  return data;
}

async function getCampaignDashboard(params?: {
  from?: string;
  to?: string;
  channel?: ChannelType | "both";
}): Promise<CampaignDashboardData> {
  const query = buildQuery({ from: params?.from, to: params?.to, channel: params?.channel });
  const { data } = await apiRequest<{ data: CampaignDashboardData }>(`/api/v1/campaigns/dashboard${query}`);
  return data;
}

async function listDailyMetrics(): Promise<DailyMetric[]> {
  const { data } = await apiRequest<{ data: DailyMetric[] }>("/api/v1/daily-metrics");
  return data;
}

async function listTeamMembers(): Promise<TeamMember[]> {
  const { data } = await apiRequest<{ data: TeamMember[] }>("/api/v1/team-members");
  return data;
}

async function listAssignableMembers(): Promise<TeamMember[]> {
  const { data } = await apiRequest<{ data: TeamMember[] }>("/api/v1/assignable-members");
  return data;
}

async function inviteTeamMember(member: {
  name: string;
  email: string;
  password: string;
  role: Extract<TeamMemberRole, "manager" | "agent">;
}): Promise<TeamMember> {
  const { data } = await apiRequest<{ data: TeamMember }>("/api/v1/team-members", {
    method: "POST",
    body: JSON.stringify(member),
  });
  return data;
}

async function updateTeamMemberRole(memberId: string, role: TeamMemberRole): Promise<TeamMember> {
  const { data } = await apiRequest<{ data: TeamMember }>(`/api/v1/team-members/${memberId}/role`, {
    method: "PATCH",
    body: JSON.stringify({ role }),
  });
  return data;
}

async function removeTeamMember(memberId: string): Promise<void> {
  await apiRequest(`/api/v1/team-members/${memberId}`, { method: "DELETE" });
}

async function listApiConnections(): Promise<ApiConnection[]> {
  const { data } = await apiRequest<{ data: ApiConnection[] }>("/api/v1/api-connections");
  return data;
}

async function updateApiConnection(
  connectionId: string,
  status: ApiConnectionStatus,
  accessToken?: string,
  wabaId?: string,
  phoneNumberId?: string,
  instagramAccountId?: string,
  twilioAccountSid?: string,
  twilioPhoneNumber?: string,
  verifyToken?: string,
): Promise<ApiConnection> {
  const { data } = await apiRequest<{ data: ApiConnection }>(`/api/v1/api-connections/${connectionId}`, {
    method: "PATCH",
    body: JSON.stringify({
      status,
      accessToken,
      wabaId,
      phoneNumberId,
      instagramAccountId,
      twilioAccountSid,
      twilioPhoneNumber,
      verifyToken,
    }),
  });
  return data;
}

async function toggleWhatsappCalling(connectionId: string, callingEnabled: boolean): Promise<ApiConnection> {
  const { data } = await apiRequest<{ data: ApiConnection }>(`/api/v1/api-connections/${connectionId}/calling`, {
    method: "PATCH",
    body: JSON.stringify({ callingEnabled }),
  });
  return data;
}

async function listTemplates(
  connectionId: string,
  params?: { page?: number; perPage?: number; status?: string; search?: string; from?: string; to?: string },
): Promise<PaginatedResponse<WhatsappTemplate>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    status: params?.status,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<WhatsappTemplate>>(
    `/api/v1/api-connections/${connectionId}/templates${query}`,
  );
  return unwrapPaginated(body);
}

async function syncTemplates(connectionId: string): Promise<WhatsappTemplate[]> {
  const { data } = await apiRequest<{ data: WhatsappTemplate[] }>(
    `/api/v1/api-connections/${connectionId}/templates/sync`,
    { method: "POST" },
  );
  return data;
}

async function createTemplate(
  connectionId: string,
  input: {
    name: string;
    language: string;
    category: string;
    body: string;
    variables?: string[];
    components?: WhatsappTemplateComponent[];
  },
): Promise<WhatsappTemplate> {
  const { data } = await apiRequest<{ data: WhatsappTemplate }>(`/api/v1/api-connections/${connectionId}/templates`, {
    method: "POST",
    body: JSON.stringify(input),
  });
  return data;
}

async function updateTemplate(
  templateId: string,
  input: Partial<{
    name: string;
    language: string;
    category: string;
    body: string;
    variables: string[];
    components: WhatsappTemplateComponent[];
  }>,
): Promise<WhatsappTemplate> {
  const { data } = await apiRequest<{ data: WhatsappTemplate }>(`/api/v1/templates/${templateId}`, {
    method: "PATCH",
    body: JSON.stringify(input),
  });
  return data;
}

async function submitTemplate(templateId: string): Promise<WhatsappTemplate> {
  const { data } = await apiRequest<{ data: WhatsappTemplate }>(`/api/v1/templates/${templateId}/submit`, {
    method: "POST",
  });
  return data;
}

async function deleteTemplate(templateId: string): Promise<void> {
  await apiRequest<void>(`/api/v1/templates/${templateId}`, { method: "DELETE" });
}

async function listFlows(connectionId: string): Promise<WhatsappFlow[]> {
  const { data } = await apiRequest<{ data: WhatsappFlow[] }>(`/api/v1/api-connections/${connectionId}/flows`);
  return data;
}

async function syncFlows(connectionId: string): Promise<WhatsappFlow[]> {
  const { data } = await apiRequest<{ data: WhatsappFlow[] }>(
    `/api/v1/api-connections/${connectionId}/flows/sync`,
    { method: "POST" },
  );
  return data;
}

async function listAllWhatsappFlows(): Promise<WhatsappFlow[]> {
  const { data } = await apiRequest<{ data: WhatsappFlow[] }>(`/api/v1/whatsapp-flows`);
  return data;
}

async function getNotificationPreferences(): Promise<NotificationPreferences> {
  const { data } = await apiRequest<{ data: NotificationPreferences }>("/api/v1/notification-preferences");
  return data;
}

async function updateNotificationPreferences(
  updates: Partial<NotificationPreferences>,
): Promise<NotificationPreferences> {
  const { data } = await apiRequest<{ data: NotificationPreferences }>("/api/v1/notification-preferences", {
    method: "PATCH",
    body: JSON.stringify(updates),
  });
  return data;
}

async function getAiAssistantSettings(): Promise<AiAssistantSettings> {
  const { data } = await apiRequest<{ data: AiAssistantSettings }>("/api/v1/ai-assistant-settings");
  return data;
}

async function updateAiAssistantSettings(updates: Partial<AiAssistantSettings>): Promise<AiAssistantSettings> {
  const { data } = await apiRequest<{ data: AiAssistantSettings }>("/api/v1/ai-assistant-settings", {
    method: "PATCH",
    body: JSON.stringify(updates),
  });
  return data;
}

async function sendAiAssistantChat(messages: { role: "system" | "user" | "assistant"; content: string }[]): Promise<string> {
  const { data } = await apiRequest<{ data: { content: string } }>("/api/v1/ai-assistant/chat", {
    method: "POST",
    body: JSON.stringify({ messages }),
  });
  return data.content;
}

async function listActivityFeed(): Promise<ActivityItem[]> {
  const { data } = await apiRequest<{ data: ActivityItem[] }>("/api/v1/activity-logs");
  return data;
}

async function listPhonebookFolders(params?: {
  page?: number;
  perPage?: number;
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<PhonebookFolder>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<PhonebookFolder>>(`/api/v1/phonebook-folders${query}`);
  return unwrapPaginated(body);
}

async function getPhonebookFolderContacts(
  folderId: string,
  params?: { page?: number; perPage?: number },
): Promise<PaginatedResponse<Contact>> {
  const query = buildQuery({ page: params?.page, per_page: params?.perPage });
  const body = await apiRequest<RawPaginatedResponse<Contact>>(
    `/api/v1/phonebook-folders/${folderId}/contacts${query}`,
  );
  return unwrapPaginated(body);
}

async function getPhonebookFolder(folderId: string): Promise<PhonebookFolder> {
  const { data } = await apiRequest<{ data: PhonebookFolder }>(`/api/v1/phonebook-folders/${folderId}`);
  return data;
}

async function createPhonebookFolder(name: string): Promise<PhonebookFolder> {
  const { data } = await apiRequest<{ data: PhonebookFolder }>("/api/v1/phonebook-folders", {
    method: "POST",
    body: JSON.stringify({ name }),
  });
  return data;
}

async function renamePhonebookFolder(folderId: string, name: string): Promise<PhonebookFolder> {
  const { data } = await apiRequest<{ data: PhonebookFolder }>(`/api/v1/phonebook-folders/${folderId}`, {
    method: "PATCH",
    body: JSON.stringify({ name }),
  });
  return data;
}

async function deletePhonebookFolder(folderId: string): Promise<void> {
  await apiRequest(`/api/v1/phonebook-folders/${folderId}`, { method: "DELETE" });
}

async function addContactsToFolder(folderId: string, contactIds: string[]): Promise<PhonebookFolder> {
  const { data } = await apiRequest<{ data: PhonebookFolder }>(`/api/v1/phonebook-folders/${folderId}/contacts`, {
    method: "POST",
    body: JSON.stringify({ contactIds }),
  });
  return data;
}

async function removeContactFromFolder(folderId: string, contactId: string): Promise<void> {
  await apiRequest(`/api/v1/phonebook-folders/${folderId}/contacts/${contactId}`, { method: "DELETE" });
}

async function importContactsToFolder(params: {
  file: File;
  folderId?: string;
  folderName?: string;
}): Promise<{ folder: PhonebookFolder; summary: ImportSummary }> {
  const formData = new FormData();
  formData.append("file", params.file);
  if (params.folderId) {
    formData.append("folderId", params.folderId);
  }
  if (params.folderName) {
    formData.append("folderName", params.folderName);
  }

  const body = await apiUpload<{ data: PhonebookFolder; summary: ImportSummary }>(
    "/api/v1/phonebook-folders/import",
    formData,
  );
  return { folder: body.data, summary: body.summary };
}

async function downloadContactTemplate(): Promise<void> {
  const blob = await apiDownload("/api/v1/phonebook-folders/template");
  triggerBlobDownload(blob, "contact-template.xlsx");
}

async function exportPhonebookFolder(folderId: string, format: "csv" | "xlsx", filename: string): Promise<void> {
  const blob = await apiDownload(`/api/v1/phonebook-folders/${folderId}/export?format=${format}`);
  triggerBlobDownload(blob, `${filename}.${format}`);
}

async function exportAllContacts(format: "csv" | "xlsx"): Promise<void> {
  const blob = await apiDownload(`/api/v1/contacts/export?format=${format}`);
  triggerBlobDownload(blob, `contacts.${format}`);
}

async function listChatbots(params?: {
  page?: number;
  perPage?: number;
  status?: ChatbotStatus;
  channel?: ChatbotChannel;
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<Chatbot>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    status: params?.status,
    channel: params?.channel,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<Chatbot>>(`/api/v1/chatbots${query}`);
  return unwrapPaginated(body);
}

async function createChatbot(chatbot: { name: string; welcomeMessage?: string | null }): Promise<Chatbot> {
  const { data } = await apiRequest<{ data: Chatbot }>("/api/v1/chatbots", {
    method: "POST",
    body: JSON.stringify(chatbot),
  });
  return data;
}

async function updateChatbot(
  chatbotId: string,
  updates: Partial<{
    name: string;
    status: ChatbotStatus;
    welcomeMessage: string | null;
    allowedOrigins: string[];
    generalFallbackEnabled: boolean;
    channels: ChatbotChannel[];
  }>,
): Promise<Chatbot> {
  const { data } = await apiRequest<{ data: Chatbot }>(`/api/v1/chatbots/${chatbotId}`, {
    method: "PATCH",
    body: JSON.stringify(updates),
  });
  return data;
}

async function deleteChatbot(chatbotId: string): Promise<void> {
  await apiRequest(`/api/v1/chatbots/${chatbotId}`, { method: "DELETE" });
}

async function listTrainingEntries(chatbotId: string): Promise<ChatbotTrainingEntry[]> {
  const { data } = await apiRequest<{ data: ChatbotTrainingEntry[] }>(`/api/v1/chatbots/${chatbotId}/training-entries`);
  return data;
}

async function createTrainingEntry(
  chatbotId: string,
  entry: { question: string; answer: string; source?: ChatbotTrainingEntrySource },
): Promise<ChatbotTrainingEntry> {
  const { data } = await apiRequest<{ data: ChatbotTrainingEntry }>(
    `/api/v1/chatbots/${chatbotId}/training-entries`,
    { method: "POST", body: JSON.stringify(entry) },
  );
  return data;
}

async function deleteTrainingEntry(chatbotId: string, entryId: string): Promise<void> {
  await apiRequest(`/api/v1/chatbots/${chatbotId}/training-entries/${entryId}`, { method: "DELETE" });
}

async function generateTrainingEntries(
  chatbotId: string,
  input: { file?: File; text?: string },
): Promise<ChatbotTrainingCandidate[]> {
  if (input.file) {
    const formData = new FormData();
    formData.append("file", input.file);
    const { data } = await apiUpload<{ data: ChatbotTrainingCandidate[] }>(
      `/api/v1/chatbots/${chatbotId}/training-entries/generate`,
      formData,
    );
    return data;
  }

  const { data } = await apiRequest<{ data: ChatbotTrainingCandidate[] }>(
    `/api/v1/chatbots/${chatbotId}/training-entries/generate`,
    { method: "POST", body: JSON.stringify({ text: input.text }) },
  );
  return data;
}

async function listVoiceAgents(params?: {
  page?: number;
  perPage?: number;
  status?: VoiceAgentStatus;
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<VoiceAgent>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    status: params?.status,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<VoiceAgent>>(`/api/v1/voice-agents${query}`);
  return unwrapPaginated(body);
}

async function createVoiceAgent(voiceAgent: {
  name: string;
  status?: VoiceAgentStatus;
  voiceMode?: VoiceAgentVoiceMode;
  ttsVoiceId?: string | null;
  greetingAudioUrl?: string | null;
  qualificationCriteria?: VoiceAgentQualificationCriterion[];
  qualificationPassCriteria?: string | null;
  maxCallAttempts?: number;
  systemPrompt?: string | null;
}): Promise<VoiceAgent> {
  const { data } = await apiRequest<{ data: VoiceAgent }>("/api/v1/voice-agents", {
    method: "POST",
    body: JSON.stringify(voiceAgent),
  });
  return data;
}

async function getVoiceAgent(voiceAgentId: string): Promise<VoiceAgent> {
  const { data } = await apiRequest<{ data: VoiceAgent }>(`/api/v1/voice-agents/${voiceAgentId}`);
  return data;
}

async function updateVoiceAgent(
  voiceAgentId: string,
  updates: Partial<{
    name: string;
    status: VoiceAgentStatus;
    voiceMode: VoiceAgentVoiceMode;
    ttsVoiceId: string | null;
    greetingAudioUrl: string | null;
    qualificationCriteria: VoiceAgentQualificationCriterion[];
    qualificationPassCriteria: string | null;
    maxCallAttempts: number;
    systemPrompt: string | null;
  }>,
): Promise<VoiceAgent> {
  const { data } = await apiRequest<{ data: VoiceAgent }>(`/api/v1/voice-agents/${voiceAgentId}`, {
    method: "PATCH",
    body: JSON.stringify(updates),
  });
  return data;
}

async function deleteVoiceAgent(voiceAgentId: string): Promise<void> {
  await apiRequest(`/api/v1/voice-agents/${voiceAgentId}`, { method: "DELETE" });
}

async function listVoiceCalls(filters?: {
  voiceAgentId?: string;
  status?: string;
  needsHumanFollowup?: boolean;
}): Promise<VoiceCall[]> {
  const query = buildQuery({
    voiceAgentId: filters?.voiceAgentId,
    status: filters?.status,
    needsHumanFollowup: filters?.needsHumanFollowup ? "true" : undefined,
  });
  const { data } = await apiRequest<{ data: VoiceCall[] }>(`/api/v1/voice-calls${query}`);
  return data;
}

async function getVoiceCall(voiceCallId: string): Promise<VoiceCall> {
  const { data } = await apiRequest<{ data: VoiceCall }>(`/api/v1/voice-calls/${voiceCallId}`);
  return data;
}

async function logSimulatedVoiceCall(call: {
  contactId: string;
  voiceAgentId?: string | null;
  transcript: VoiceCallTranscriptTurn[];
  qualificationFields?: Record<string, unknown> | null;
  qualificationOutcome?: VoiceCallQualificationOutcome | null;
  qualificationSummary?: string | null;
}): Promise<VoiceCall> {
  const { data } = await apiRequest<{ data: VoiceCall }>("/api/v1/voice-calls/simulated", {
    method: "POST",
    body: JSON.stringify(call),
  });
  return data;
}

async function assignVoiceCallFollowup(voiceCallId: string, userId: string | null): Promise<VoiceCall> {
  const { data } = await apiRequest<{ data: VoiceCall }>(`/api/v1/voice-calls/${voiceCallId}/followup`, {
    method: "PATCH",
    body: JSON.stringify({ userId }),
  });
  return data;
}

async function completeVoiceCallFollowup(voiceCallId: string): Promise<VoiceCall> {
  const { data } = await apiRequest<{ data: VoiceCall }>(`/api/v1/voice-calls/${voiceCallId}/followup/complete`, {
    method: "PATCH",
  });
  return data;
}

async function listWhatsappCallFlows(params?: {
  page?: number;
  perPage?: number;
  status?: WhatsappCallFlowStatus;
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<WhatsappCallFlow>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    status: params?.status,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<WhatsappCallFlow>>(`/api/v1/whatsapp-call-flows${query}`);
  return unwrapPaginated(body);
}

async function createWhatsappCallFlow(callFlow: {
  apiConnectionId: string;
  name: string;
  status?: WhatsappCallFlowStatus;
  greetingMessage: string;
  nodes: WhatsappCallFlowNode[];
  fallbackMessage?: string | null;
  maxRetries?: number;
}): Promise<WhatsappCallFlow> {
  const { data } = await apiRequest<{ data: WhatsappCallFlow }>("/api/v1/whatsapp-call-flows", {
    method: "POST",
    body: JSON.stringify(callFlow),
  });
  return data;
}

async function getWhatsappCallFlow(callFlowId: string): Promise<WhatsappCallFlow> {
  const { data } = await apiRequest<{ data: WhatsappCallFlow }>(`/api/v1/whatsapp-call-flows/${callFlowId}`);
  return data;
}

async function updateWhatsappCallFlow(
  callFlowId: string,
  updates: Partial<{
    name: string;
    status: WhatsappCallFlowStatus;
    greetingMessage: string;
    nodes: WhatsappCallFlowNode[];
    fallbackMessage: string | null;
    maxRetries: number;
  }>,
): Promise<WhatsappCallFlow> {
  const { data } = await apiRequest<{ data: WhatsappCallFlow }>(`/api/v1/whatsapp-call-flows/${callFlowId}`, {
    method: "PATCH",
    body: JSON.stringify(updates),
  });
  return data;
}

async function deleteWhatsappCallFlow(callFlowId: string): Promise<void> {
  await apiRequest(`/api/v1/whatsapp-call-flows/${callFlowId}`, { method: "DELETE" });
}

async function listChatMenuFlows(params?: {
  page?: number;
  perPage?: number;
  status?: ChatMenuFlowStatus;
  channel?: ChatMenuFlowChannel;
  search?: string;
  from?: string;
  to?: string;
}): Promise<PaginatedResponse<ChatMenuFlow>> {
  const query = buildQuery({
    page: params?.page,
    per_page: params?.perPage,
    status: params?.status,
    channel: params?.channel,
    search: params?.search,
    from: params?.from,
    to: params?.to,
  });
  const body = await apiRequest<RawPaginatedResponse<ChatMenuFlow>>(`/api/v1/chat-menu-flows${query}`);
  return unwrapPaginated(body);
}

async function createChatMenuFlow(flow: {
  name: string;
  channel?: ChatMenuFlowChannel;
  status?: ChatMenuFlowStatus;
  triggerKeyword?: string | null;
  entryNodeId: string;
  nodes: ChatMenuFlowNode[];
}): Promise<ChatMenuFlow> {
  const { data } = await apiRequest<{ data: ChatMenuFlow }>("/api/v1/chat-menu-flows", {
    method: "POST",
    body: JSON.stringify(flow),
  });
  return data;
}

async function getChatMenuFlow(flowId: string): Promise<ChatMenuFlow> {
  const { data } = await apiRequest<{ data: ChatMenuFlow }>(`/api/v1/chat-menu-flows/${flowId}`);
  return data;
}

async function updateChatMenuFlow(
  flowId: string,
  updates: Partial<{
    name: string;
    channel: ChatMenuFlowChannel;
    status: ChatMenuFlowStatus;
    triggerKeyword: string | null;
    entryNodeId: string;
    nodes: ChatMenuFlowNode[];
  }>,
): Promise<ChatMenuFlow> {
  const { data } = await apiRequest<{ data: ChatMenuFlow }>(`/api/v1/chat-menu-flows/${flowId}`, {
    method: "PATCH",
    body: JSON.stringify(updates),
  });
  return data;
}

async function deleteChatMenuFlow(flowId: string): Promise<void> {
  await apiRequest(`/api/v1/chat-menu-flows/${flowId}`, { method: "DELETE" });
}

async function generateChatMenuFlow(prompt: string): Promise<{ entryNodeId: string; nodes: ChatMenuFlowNode[] }> {
  const { data } = await apiRequest<{ data: { entryNodeId: string; nodes: ChatMenuFlowNode[] } }>(
    "/api/v1/chat-menu-flows/generate",
    { method: "POST", body: JSON.stringify({ prompt }) },
  );
  return data;
}

async function listWhatsappCalls(filters?: {
  callFlowId?: string;
  contactId?: string;
  conversationId?: string;
  status?: string;
  needsHumanFollowup?: boolean;
  page?: number;
  perPage?: number;
}): Promise<PaginatedResponse<WhatsappCall>> {
  const query = buildQuery({
    callFlowId: filters?.callFlowId,
    contactId: filters?.contactId,
    conversationId: filters?.conversationId,
    status: filters?.status,
    needsHumanFollowup: filters?.needsHumanFollowup ? "true" : undefined,
    page: filters?.page,
    per_page: filters?.perPage,
  });
  const body = await apiRequest<RawPaginatedResponse<WhatsappCall>>(`/api/v1/whatsapp-calls${query}`);
  return unwrapPaginated(body);
}

async function getWhatsappCall(whatsappCallId: string): Promise<WhatsappCall> {
  const { data } = await apiRequest<{ data: WhatsappCall }>(`/api/v1/whatsapp-calls/${whatsappCallId}`);
  return data;
}

async function placeWhatsappCall(params: {
  contactId: string;
  conversationId?: string;
  callFlowId?: string;
}): Promise<WhatsappCall> {
  const { data } = await apiRequest<{ data: WhatsappCall }>("/api/v1/whatsapp-calls", {
    method: "POST",
    body: JSON.stringify(params),
  });
  return data;
}

async function submitWhatsappCallOffer(whatsappCallId: string, sdpOffer: string): Promise<WhatsappCall> {
  const { data } = await apiRequest<{ data: WhatsappCall }>(`/api/v1/whatsapp-calls/${whatsappCallId}/offer`, {
    method: "POST",
    body: JSON.stringify({ sdpOffer }),
  });
  return data;
}

async function hangupWhatsappCall(whatsappCallId: string): Promise<WhatsappCall> {
  const { data } = await apiRequest<{ data: WhatsappCall }>(`/api/v1/whatsapp-calls/${whatsappCallId}/hangup`, {
    method: "POST",
  });
  return data;
}

async function requestWhatsappCallPermission(whatsappCallId: string): Promise<void> {
  await apiRequest<{ data: { sent: boolean } }>(`/api/v1/whatsapp-calls/${whatsappCallId}/permission-request`, {
    method: "POST",
  });
}

async function getWhatsappCallIceServers(): Promise<RTCIceServer[]> {
  const { data } = await apiRequest<{ data: { iceServers: RTCIceServer[] } }>("/api/v1/whatsapp-calls/ice-servers");
  return data.iceServers;
}

async function assignWhatsappCallFollowup(whatsappCallId: string, userId: string | null): Promise<WhatsappCall> {
  const { data } = await apiRequest<{ data: WhatsappCall }>(`/api/v1/whatsapp-calls/${whatsappCallId}/followup`, {
    method: "PATCH",
    body: JSON.stringify({ userId }),
  });
  return data;
}

async function completeWhatsappCallFollowup(whatsappCallId: string): Promise<WhatsappCall> {
  const { data } = await apiRequest<{ data: WhatsappCall }>(
    `/api/v1/whatsapp-calls/${whatsappCallId}/followup/complete`,
    { method: "PATCH" },
  );
  return data;
}

async function listNotifications(): Promise<AppNotification[]> {
  const { data } = await apiRequest<{ data: AppNotification[] }>("/api/v1/notifications");
  return data;
}

async function getUnreadNotificationCount(): Promise<number> {
  const { data } = await apiRequest<{ data: { count: number } }>("/api/v1/notifications/unread-count");
  return data.count;
}

async function markNotificationRead(notificationId: string): Promise<AppNotification> {
  const { data } = await apiRequest<{ data: AppNotification }>(`/api/v1/notifications/${notificationId}/read`, {
    method: "PATCH",
  });
  return data;
}

async function markAllNotificationsRead(): Promise<void> {
  await apiRequest("/api/v1/notifications/mark-all-read", { method: "POST" });
}

async function getVapidPublicKey(): Promise<string | null> {
  const { data } = await apiRequest<{ data: { publicKey: string | null } }>("/api/v1/push/vapid-public-key");
  return data.publicKey;
}

async function subscribeToPush(subscription: {
  endpoint: string;
  keys: { p256dh: string; auth: string };
  contentEncoding?: string;
}): Promise<void> {
  await apiRequest("/api/v1/push-subscriptions", {
    method: "POST",
    body: JSON.stringify(subscription),
  });
}

async function unsubscribeFromPush(endpoint: string): Promise<void> {
  await apiRequest("/api/v1/push-subscriptions", {
    method: "DELETE",
    body: JSON.stringify({ endpoint }),
  });
}

export const apiClient = {
  login,
  logout,
  me,
  updateProfile,
  bootstrapStatus,
  setupSuperadmin,
  listCompanies,
  createCompany,
  listCompanyAdmins,
  createCompanyAdmin,
  listContacts,
  getContact,
  listPipelineStages,
  getPipelineFunnel,
  updateContactPipelineStage,
  addContactTag,
  updateContact,
  listConversations,
  listMessages,
  sendMessage,
  markConversationRead,
  updateConversationStatus,
  assignConversation,
  listAutomationFlows,
  createAutomationFlow,
  updateAutomationFlowStatus,
  deleteAutomationFlow,
  listCampaigns,
  createCampaign,
  deleteCampaign,
  getCampaignDashboard,
  listCampaignRecipients,
  listDailyMetrics,
  listTeamMembers,
  listAssignableMembers,
  inviteTeamMember,
  updateTeamMemberRole,
  removeTeamMember,
  listApiConnections,
  updateApiConnection,
  toggleWhatsappCalling,
  listTemplates,
  syncTemplates,
  createTemplate,
  updateTemplate,
  submitTemplate,
  deleteTemplate,
  listFlows,
  syncFlows,
  listAllWhatsappFlows,
  getNotificationPreferences,
  updateNotificationPreferences,
  getAiAssistantSettings,
  updateAiAssistantSettings,
  sendAiAssistantChat,
  listActivityFeed,
  listPhonebookFolders,
  getPhonebookFolder,
  createPhonebookFolder,
  renamePhonebookFolder,
  deletePhonebookFolder,
  addContactsToFolder,
  removeContactFromFolder,
  getPhonebookFolderContacts,
  importContactsToFolder,
  downloadContactTemplate,
  exportPhonebookFolder,
  exportAllContacts,
  listChatbots,
  createChatbot,
  updateChatbot,
  deleteChatbot,
  listTrainingEntries,
  createTrainingEntry,
  deleteTrainingEntry,
  generateTrainingEntries,
  listVoiceAgents,
  createVoiceAgent,
  getVoiceAgent,
  updateVoiceAgent,
  deleteVoiceAgent,
  listVoiceCalls,
  getVoiceCall,
  logSimulatedVoiceCall,
  assignVoiceCallFollowup,
  completeVoiceCallFollowup,
  listWhatsappCallFlows,
  createWhatsappCallFlow,
  getWhatsappCallFlow,
  updateWhatsappCallFlow,
  deleteWhatsappCallFlow,
  listChatMenuFlows,
  createChatMenuFlow,
  getChatMenuFlow,
  updateChatMenuFlow,
  deleteChatMenuFlow,
  generateChatMenuFlow,
  listWhatsappCalls,
  getWhatsappCall,
  placeWhatsappCall,
  submitWhatsappCallOffer,
  hangupWhatsappCall,
  requestWhatsappCallPermission,
  getWhatsappCallIceServers,
  assignWhatsappCallFollowup,
  completeWhatsappCallFollowup,
  listNotifications,
  getUnreadNotificationCount,
  markNotificationRead,
  markAllNotificationsRead,
  getVapidPublicKey,
  subscribeToPush,
  unsubscribeFromPush,
  request: apiRequest,
};

export { ApiError };
