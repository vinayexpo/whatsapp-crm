import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { apiClient } from "~/utils/api-client";
import { getEcho } from "~/utils/echo-client";
import type {
  AiAssistantSettings,
  ApiConnection,
  AutomationFlow,
  AutomationStatus,
  Campaign,
  ChannelType,
  Contact,
  Conversation,
  DailyMetric,
  Message,
  NotificationPreferences,
  PaginationMeta,
  PipelineStageId,
  TeamMember,
  TeamMemberRole,
} from "~/data/types";
import { useAuth } from "~/hooks/use-auth";
import { useBatchedRefresh } from "~/hooks/use-batched-refresh";
import { mergeMessages, sortConversations, updateConversation } from "~/utils/realtime-state";

const AGGREGATE_PER_PAGE = 100;

interface ContactFilters {
  search?: string;
  channel?: ChannelType;
  pipelineStage?: PipelineStageId;
}

interface CampaignFilters {
  search?: string;
  status?: string;
  channel?: ChannelType | "both";
  from?: string;
  to?: string;
}

interface AutomationFlowFilters {
  search?: string;
  status?: AutomationStatus;
  channel?: ChannelType | "both";
}

interface CrmStoreValue {
  campaignsRevision: number;
  templatesRevision: number;
  dashboardRevision: number;
  contacts: Contact[];
  contactsPagination: PaginationMeta;
  fetchContactsPage: (page: number, filters?: ContactFilters, perPage?: number) => void;
  allContacts: Contact[];
  conversations: Conversation[];
  conversationsPagination: PaginationMeta;
  fetchConversationsPage: (
    page: number,
    assignedTo?: string,
    search?: string,
    status?: string,
    channel?: string,
  ) => void;
  findConversationByContact: (contactId: string) => Promise<string | null>;
  allConversations: Conversation[];
  messages: Message[];
  hasMoreOlderMessages: boolean;
  loadOlderMessages: (conversationId: string) => void;
  campaigns: Campaign[];
  campaignsPagination: PaginationMeta;
  fetchCampaignsPage: (page: number, filters?: CampaignFilters) => void;
  allCampaigns: Campaign[];
  dailyMetrics: DailyMetric[];
  automationFlows: AutomationFlow[];
  automationFlowsPagination: PaginationMeta;
  fetchAutomationFlowsPage: (page: number, filters?: AutomationFlowFilters) => void;
  moveContactToStage: (contactId: string, stage: PipelineStageId) => void;
  updateContact: (
    contactId: string,
    updates: Partial<Pick<Contact, "name" | "avatarUrl" | "phone" | "email" | "location" | "dealValue">>,
  ) => Promise<Contact>;
  sendMessage: (conversationId: string, text: string, attachmentFile?: File | null) => void;
  markConversationRead: (conversationId: string) => void;
  updateConversationStatus: (conversationId: string, status: Conversation["status"]) => void;
  assignConversation: (conversationId: string, userId: string | null) => void;
  loadMessagesForConversation: (conversationId: string) => void;
  addCampaign: (campaign: {
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
  }) => void;
  addTagToContact: (contactId: string, tag: string) => void;
  addAutomationFlow: (flow: AutomationFlow) => void;
  setAutomationFlowStatus: (flowId: string, status: AutomationStatus) => void;
  deleteAutomationFlow: (flowId: string) => void;
  apiConnections: ApiConnection[];
  toggleApiConnection: (connectionId: string) => void;
  connectApiConnection: (
    connectionId: string,
    credentials: {
      accessToken: string;
      wabaId?: string;
      phoneNumberId?: string;
      instagramAccountId?: string;
      twilioAccountSid?: string;
      twilioPhoneNumber?: string;
      verifyToken?: string;
      appSecret?: string;
    },
  ) => Promise<void>;
  saveWebhookVerifyToken: (connectionId: string, verifyToken: string) => Promise<void>;
  saveConnectionAppSecret: (connectionId: string, appSecret: string) => Promise<void>;
  addWhatsAppEmbeddedSignupConnection: (payload: {
    code: string;
    wabaId: string;
    phoneNumberId: string;
    waBusinessAppPhoneNumber?: string;
  }) => Promise<ApiConnection>;
  teamMembers: TeamMember[];
  assignableMembers: TeamMember[];
  inviteTeamMember: (member: {
    name: string;
    email: string;
    password: string;
    role: Extract<TeamMemberRole, "manager" | "agent" | "branch_manager" | "staff">;
    staffBranchId?: string | null;
  }) => void;
  updateTeamMemberRole: (memberId: string, role: TeamMemberRole, staffBranchId?: string | null) => void;
  removeTeamMember: (memberId: string) => void;
  notificationPreferences: NotificationPreferences;
  updateNotificationPreferences: (updates: Partial<NotificationPreferences>) => void;
  currentUser: TeamMember | null;
  isCurrentUserAdmin: boolean;
  aiAssistantSettings: AiAssistantSettings;
  updateAiAssistantSettings: (updates: Partial<AiAssistantSettings>) => void;
}

const CrmStoreContext = createContext<CrmStoreValue | null>(null);

const DEFAULT_NOTIFICATION_PREFERENCES: NotificationPreferences = {
  newMessageAlerts: true,
  campaignCompleted: true,
  automationTriggered: false,
  dailySummaryEmail: true,
  weeklyAnalyticsReport: true,
  soundAlerts: false,
  whatsappCallAlerts: true,
  voiceCallAlerts: true,
  templateStatusAlerts: true,
};

const DEFAULT_AI_ASSISTANT_SETTINGS: AiAssistantSettings = {
  baseUrl: "https://api.openai.com/v1",
  apiKey: "",
  model: "gpt-4o-mini",
  voiceBaseUrl: "",
  voiceApiKey: "",
  sttModel: "whisper-1",
  sttUrl: "",
  ttsModel: "tts-1",
  ttsVoice: "alloy",
  ttsUrl: "",
};

const DEFAULT_PAGINATION: PaginationMeta = { currentPage: 1, lastPage: 1, perPage: 20, total: 0 };

export function CrmStoreProvider({ children }: { children: ReactNode }) {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [contactsPagination, setContactsPagination] = useState<PaginationMeta>(DEFAULT_PAGINATION);
  const [contactsPage, setContactsPage] = useState(1);
  const [contactsFilters, setContactsFilters] = useState<ContactFilters>({});
  const [contactsPerPage, setContactsPerPage] = useState(20);
  const [allContacts, setAllContacts] = useState<Contact[]>([]);

  const [conversations, setConversations] = useState<Conversation[]>([]);
  const conversationsRef = useRef<Conversation[]>([]);
  useEffect(() => {
    conversationsRef.current = conversations;
  }, [conversations]);
  const pinnedConversationRef = useRef<Conversation | null>(null);
  const [conversationsPagination, setConversationsPagination] = useState<PaginationMeta>(DEFAULT_PAGINATION);
  const [conversationsPage, setConversationsPage] = useState(1);
  const [conversationsAssignedTo, setConversationsAssignedTo] = useState<string | undefined>(undefined);
  const [conversationsSearch, setConversationsSearch] = useState<string | undefined>(undefined);
  const [conversationsStatus, setConversationsStatus] = useState<string | undefined>(undefined);
  const [conversationsChannel, setConversationsChannel] = useState<string | undefined>(undefined);
  const [allConversations, setAllConversations] = useState<Conversation[]>([]);

  const [messages, setMessages] = useState<Message[]>([]);
  const [activeConversationId, setActiveConversationId] = useState<string | null>(null);
  const [hasMoreOlderByConversation, setHasMoreOlderByConversation] = useState<Record<string, boolean>>({});

  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [campaignsPagination, setCampaignsPagination] = useState<PaginationMeta>(DEFAULT_PAGINATION);
  const [campaignsPage, setCampaignsPage] = useState(1);
  const [campaignsFilters, setCampaignsFilters] = useState<CampaignFilters>({});
  const [allCampaigns, setAllCampaigns] = useState<Campaign[]>([]);

  const [dailyMetrics, setDailyMetrics] = useState<DailyMetric[]>([]);

  const [automationFlows, setAutomationFlows] = useState<AutomationFlow[]>([]);
  const [automationFlowsPagination, setAutomationFlowsPagination] = useState<PaginationMeta>(DEFAULT_PAGINATION);
  const [automationFlowsPage, setAutomationFlowsPage] = useState(1);
  const [automationFlowsFilters, setAutomationFlowsFilters] = useState<AutomationFlowFilters>({});

  const [apiConnections, setApiConnections] = useState<ApiConnection[]>([]);
  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [assignableMembers, setAssignableMembers] = useState<TeamMember[]>([]);
  const [notificationPreferences, setNotificationPreferences] = useState<NotificationPreferences>(
    DEFAULT_NOTIFICATION_PREFERENCES,
  );
  const { user: currentUser, status: sessionStatus } = useAuth();
  // Platform administrators use their own API and must not load tenant CRM data.
  const authStatus = currentUser?.role === "superadmin" ? "unauthenticated" : sessionStatus;
  const [inboxRevision, refreshInbox] = useBatchedRefresh(currentUser?.id);
  const [campaignsRevision, refreshCampaigns] = useBatchedRefresh(currentUser?.id);
  const [templatesRevision, refreshTemplates] = useBatchedRefresh(currentUser?.id);
  const [dashboardRevision, refreshDashboard] = useBatchedRefresh(currentUser?.id);
  const activeConversationRef = useRef(activeConversationId);
  activeConversationRef.current = activeConversationId;
  const [aiAssistantSettings, setAiAssistantSettings] = useState<AiAssistantSettings>(DEFAULT_AI_ASSISTANT_SETTINGS);

  const fetchContactsPage = useCallback((page: number, filters?: ContactFilters, perPage?: number) => {
    setContactsPage(page);
    if (filters) setContactsFilters(filters);
    if (perPage) setContactsPerPage(perPage);
  }, []);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listContacts({ page: contactsPage, perPage: contactsPerPage, ...contactsFilters })
      .then(({ data, meta }) => {
        if (!cancelled) {
          setContacts(data);
          setContactsPagination(meta);
        }
      })
      .catch(() => {
        // contacts list stays empty on failure; UI shows "no contacts"
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus, contactsPage, contactsPerPage, contactsFilters]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listContacts({ perPage: AGGREGATE_PER_PAGE })
      .then(({ data }) => {
        if (!cancelled) setAllContacts(data);
      })
      .catch(() => {
        // full contacts list stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  const fetchConversationsPage = useCallback(
    (page: number, assignedTo?: string, search?: string, status?: string, channel?: string) => {
      pinnedConversationRef.current = null;
      setConversationsPage(page);
      setConversationsAssignedTo(assignedTo);
      setConversationsSearch(search);
      setConversationsStatus(status);
      setConversationsChannel(channel);
    },
    [],
  );

  const findConversationByContact = useCallback(async (contactId: string): Promise<string | null> => {
    const existing = conversationsRef.current.find((c) => c.contactId === contactId);
    if (existing) {
      pinnedConversationRef.current = existing;
      return existing.id;
    }

    const { data } = await apiClient.listConversations({ contactId, perPage: 1 });
    const found = data[0] ?? null;
    if (found) {
      pinnedConversationRef.current = found;
      setConversations((prev) => (prev.some((c) => c.id === found.id) ? prev : [found, ...prev]));
    }
    return found?.id ?? null;
  }, []);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listConversations({
        page: conversationsPage,
        assignedTo: conversationsAssignedTo,
        search: conversationsSearch,
        status: conversationsStatus,
        channel: conversationsChannel,
      })
      .then(({ data, meta }) => {
        if (!cancelled) {
          const pinned = pinnedConversationRef.current;
          const active = conversationsRef.current.find((conversation) => conversation.id === activeConversationRef.current);
          const assignee = conversationsAssignedTo === "me" ? currentUser?.id : conversationsAssignedTo;
          const activeMatchesFilters = active
            && !conversationsSearch
            && (!conversationsStatus || active.status === conversationsStatus)
            && (!conversationsChannel || active.channel === conversationsChannel)
            && (currentUser?.role !== "agent" || active.assignedTo?.id === currentUser.id)
            && (!assignee || (assignee === "unassigned" ? !active.assignedTo : active.assignedTo?.id === assignee));
          const kept = pinned ?? (activeMatchesFilters ? active : null);
          const merged = kept && !data.some((c) => c.id === kept.id) ? [kept, ...data] : data;
          setConversations(sortConversations(merged));
          setConversationsPagination(meta);
        }
      })
      .catch(() => {
        // conversations list stays as-is on failure
      });
    return () => {
      cancelled = true;
    };
  }, [
    authStatus,
    currentUser?.role,
    conversationsPage,
    conversationsAssignedTo,
    conversationsSearch,
    conversationsStatus,
    conversationsChannel,
    inboxRevision,
  ]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listConversations({ perPage: AGGREGATE_PER_PAGE })
      .then(({ data }) => {
        if (!cancelled) setAllConversations(data);
      })
      .catch(() => {
        // full conversations list stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus, currentUser?.role, inboxRevision]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    if (activeConversationId) {
      apiClient
        .listMessages(activeConversationId, { limit: 30 })
        .then(({ data, hasMore }) => {
          if (cancelled) return;
          setMessages((prev) => mergeMessages(prev, data));
          setHasMoreOlderByConversation((prev) => ({ ...prev, [activeConversationId]: hasMore }));
        })
        .catch(() => {
          // messages stay as-is on failure
        });
    }
    return () => {
      cancelled = true;
    };
  }, [authStatus, activeConversationId, inboxRevision]);

  useEffect(() => {
    if (authStatus !== "authenticated" || !currentUser?.companyId) return;

    const echo = getEcho();
    const channelName = `company.${currentUser.companyId}.conversations`;

    const onConversation = ({ conversation }: { conversation: Conversation }) => {
      // Update known cards immediately. Re-fetch the current server page to
      // reconcile new cards, filters, permissions, totals and off-page ordering.
      setConversations((prev) => updateConversation(prev, conversation));
      setAllConversations((prev) => updateConversation(prev, conversation));
      refreshInbox();
      refreshDashboard();
    };
    const channel = echo.private(channelName)
      .listen(".conversation.created", onConversation)
      .listen(".conversation.updated", onConversation)
      .listen(".message.received", ({ message }: { message: Message }) => {
        setMessages((prev) => mergeMessages(prev, [message]));
        refreshInbox();
        refreshDashboard();
      });
    channel.subscribed(() => {
      // Subscription success also runs after a reconnect: recover missed events.
      refreshInbox();
      refreshDashboard();
    });

    const updatesName = `company.${currentUser.companyId}.updates`;
    echo.private(updatesName)
      .listen(".company.data-updated", ({ resource }: { resource: string }) => {
        if (resource === "campaigns") refreshCampaigns();
        if (resource === "templates") refreshTemplates();
        if (resource !== "templates") refreshDashboard();
      })
      .subscribed(() => {
        refreshCampaigns();
        refreshTemplates();
        refreshDashboard();
      });

    return () => {
      echo.leave(channelName);
      echo.leave(updatesName);
    };
  }, [authStatus, currentUser?.id, currentUser?.companyId, refreshInbox, refreshCampaigns, refreshTemplates, refreshDashboard]);

  const subscribedConversationIds = useRef<Set<string>>(new Set());

  useEffect(() => {
    if (authStatus !== "authenticated") return;

    const echo = getEcho();

    const desired = new Set(conversations.map((conversation) => conversation.id));
    if (activeConversationId) desired.add(activeConversationId);
    for (const id of subscribedConversationIds.current) {
      if (!desired.has(id)) {
        echo.leave(`conversation.${id}`);
        subscribedConversationIds.current.delete(id);
      }
    }

    for (const id of desired) {
      if (subscribedConversationIds.current.has(id)) continue;
      subscribedConversationIds.current.add(id);

      echo
        .private(`conversation.${id}`)
        .listen(".message.received", (payload: { message: Message }) => {
          setMessages((prev) => mergeMessages(prev, [payload.message]));
        })
        .listen(".message.status-updated", (payload: { messageId: string; status: Message["status"] }) => {
          setMessages((prev) => mergeMessages(prev, prev.filter((m) => m.id === payload.messageId).map((m) => ({ ...m, status: payload.status }))));
        })
        .listen(".conversation.updated", (payload: { conversation: Conversation }) => {
          setConversations((prev) => updateConversation(prev, payload.conversation));
          setAllConversations((prev) => updateConversation(prev, payload.conversation));
        });
    }
  }, [authStatus, currentUser?.id, conversations, activeConversationId]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    return () => {
      const echo = getEcho();
      subscribedConversationIds.current.forEach((id) => echo.leave(`conversation.${id}`));
      subscribedConversationIds.current.clear();
    };
  }, [authStatus, currentUser?.id]);

  const fetchCampaignsPage = useCallback((page: number, filters?: CampaignFilters) => {
    setCampaignsPage(page);
    if (filters) setCampaignsFilters(filters);
  }, []);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listCampaigns({ page: campaignsPage, ...campaignsFilters })
      .then(({ data, meta }) => {
        if (!cancelled) {
          setCampaigns(data);
          setCampaignsPagination(meta);
        }
      })
      .catch(() => {
        // campaigns list stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus, campaignsPage, campaignsFilters, campaignsRevision]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listCampaigns({ perPage: AGGREGATE_PER_PAGE })
      .then(({ data }) => {
        if (!cancelled) setAllCampaigns(data);
      })
      .catch(() => {
        // full campaigns list stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus, campaignsRevision]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listDailyMetrics()
      .then((data) => {
        if (!cancelled) setDailyMetrics(data);
      })
      .catch(() => {
        // daily metrics stay empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus, dashboardRevision]);

  const fetchAutomationFlowsPage = useCallback((page: number, filters?: AutomationFlowFilters) => {
    setAutomationFlowsPage(page);
    if (filters) setAutomationFlowsFilters(filters);
  }, []);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listAutomationFlows({ page: automationFlowsPage, perPage: AGGREGATE_PER_PAGE, ...automationFlowsFilters })
      .then(({ data, meta }) => {
        if (!cancelled) {
          setAutomationFlows(data);
          setAutomationFlowsPagination(meta);
        }
      })
      .catch(() => {
        // automation flows list stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus, automationFlowsPage, automationFlowsFilters]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listTeamMembers()
      .then((data) => {
        if (!cancelled) setTeamMembers(data);
      })
      .catch(() => {
        // non-admins are forbidden from listing team members; leave list empty
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listAssignableMembers()
      .then((data) => {
        if (!cancelled) setAssignableMembers(data);
      })
      .catch(() => {
        // leave list empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listApiConnections()
      .then((data) => {
        if (!cancelled) setApiConnections(data);
      })
      .catch(() => {
        // non-admins are forbidden from listing api connections; leave list empty
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .getNotificationPreferences()
      .then((data) => {
        if (!cancelled) setNotificationPreferences(data);
      })
      .catch(() => {
        // notification preferences stay at defaults on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .getAiAssistantSettings()
      .then((data) => {
        if (!cancelled) setAiAssistantSettings(data);
      })
      .catch(() => {
        // non-admins are forbidden from viewing ai assistant settings; stay at defaults
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  const moveContactToStage = useCallback(
    (contactId: string, stage: PipelineStageId) => {
      const previous = allContacts.find((c) => c.id === contactId) ?? contacts.find((c) => c.id === contactId);
      if (!previous) return;
      setContacts((prev) => prev.map((c) => (c.id === contactId ? { ...c, pipelineStage: stage } : c)));
      setAllContacts((prev) => prev.map((c) => (c.id === contactId ? { ...c, pipelineStage: stage } : c)));
      apiClient.updateContactPipelineStage(contactId, stage, previous.updatedAt).then(
        (updated) => {
          setContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
          setAllContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
        },
        () => {
          setContacts((prev) => prev.map((c) => (c.id === contactId ? previous : c)));
          setAllContacts((prev) => prev.map((c) => (c.id === contactId ? previous : c)));
        },
      );
    },
    [contacts, allContacts],
  );

  const loadMessagesForConversation = useCallback((conversationId: string) => {
    setActiveConversationId(conversationId);
  }, []);

  const loadOlderMessages = useCallback(
    (conversationId: string) => {
      if (hasMoreOlderByConversation[conversationId] === false) return;
      const oldest = messages
        .filter((m) => m.conversationId === conversationId)
        .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime())[0];
      if (!oldest) return;
      apiClient
        .listMessages(conversationId, { before: oldest.id, limit: 30 })
        .then(({ data, hasMore }) => {
          setMessages((prev) => [...data, ...prev]);
          setHasMoreOlderByConversation((prev) => ({ ...prev, [conversationId]: hasMore }));
        })
        .catch(() => {
          // older messages stay unloaded on failure
        });
    },
    [messages, hasMoreOlderByConversation],
  );

  const sendMessage = useCallback((conversationId: string, text: string, attachmentFile?: File | null) => {
    apiClient.sendMessage(conversationId, text, attachmentFile).then((sent) => {
      setMessages((prev) => mergeMessages(prev, [sent]));
      const patch = (c: Conversation) =>
        c.id === conversationId ? { ...c, lastMessagePreview: text || "Attachment", lastMessageAt: sent.timestamp } : c;
      setConversations((prev) => sortConversations(prev.map(patch)));
      setAllConversations((prev) => sortConversations(prev.map(patch)));
    });
  }, []);

  const markConversationRead = useCallback((conversationId: string) => {
    const patch = (c: Conversation) => (c.id === conversationId ? { ...c, unreadCount: 0 } : c);
    setConversations((prev) => prev.map(patch));
    setAllConversations((prev) => prev.map(patch));
    apiClient.markConversationRead(conversationId).catch(() => {
      // best-effort; next poll will reconcile
    });
  }, []);

  const updateConversationStatus = useCallback((conversationId: string, status: Conversation["status"]) => {
    const patch = (c: Conversation) => (c.id === conversationId ? { ...c, status } : c);
    setConversations((prev) => prev.map(patch));
    setAllConversations((prev) => prev.map(patch));
    apiClient.updateConversationStatus(conversationId, status).catch(() => {
      // best-effort; next poll will reconcile
    });
  }, []);

  const assignConversation = useCallback((conversationId: string, userId: string | null) => {
    apiClient.assignConversation(conversationId, userId).then((updated) => {
      setConversations((prev) => prev.map((c) => (c.id === conversationId ? updated : c)));
      setAllConversations((prev) => prev.map((c) => (c.id === conversationId ? updated : c)));
    });
  }, []);

  const addCampaign = useCallback(
    (campaign: {
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
    }) => {
      apiClient.createCampaign(campaign).then((created) => {
        setAllCampaigns((prev) => [created, ...prev]);
        if (campaignsPagination.currentPage === 1) {
          setCampaigns((prev) => [created, ...prev].slice(0, campaignsPagination.perPage || undefined));
        } else {
          setCampaignsPage(1);
        }
        setCampaignsPagination((prev) => ({ ...prev, total: prev.total + 1 }));
      });
    },
    [campaignsPagination],
  );

  const addTagToContact = useCallback((contactId: string, tag: string) => {
    apiClient.addContactTag(contactId, tag).then((updated) => {
      setContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
      setAllContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
    });
  }, []);

  const updateContact = useCallback(
    (
      contactId: string,
      updates: Partial<Pick<Contact, "name" | "avatarUrl" | "phone" | "email" | "location" | "dealValue">>,
    ) => {
      return apiClient.updateContact(contactId, updates).then((updated) => {
        setContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
        setAllContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
        return updated;
      });
    },
    [],
  );

  const addAutomationFlow = useCallback(
    (flow: AutomationFlow) => {
      apiClient
        .createAutomationFlow({
          name: flow.name,
          description: flow.description,
          status: flow.status,
          trigger: flow.trigger,
          conditions: flow.conditions,
          actions: flow.actions,
        })
        .then((created) => {
          if (automationFlowsPagination.currentPage === 1) {
            setAutomationFlows((prev) => [created, ...prev].slice(0, automationFlowsPagination.perPage || undefined));
          } else {
            setAutomationFlowsPage(1);
          }
          setAutomationFlowsPagination((prev) => ({ ...prev, total: prev.total + 1 }));
        });
    },
    [automationFlowsPagination],
  );

  const setAutomationFlowStatus = useCallback((flowId: string, status: AutomationStatus) => {
    setAutomationFlows((prev) => prev.map((f) => (f.id === flowId ? { ...f, status } : f)));
    apiClient.updateAutomationFlowStatus(flowId, status).catch(() => {
      // best-effort; next load will reconcile
    });
  }, []);

  const deleteAutomationFlow = useCallback((flowId: string) => {
    setAutomationFlows((prev) => prev.filter((f) => f.id !== flowId));
    setAutomationFlowsPagination((prev) => ({ ...prev, total: Math.max(0, prev.total - 1) }));
    apiClient.deleteAutomationFlow(flowId).catch(() => {
      // best-effort; next load will reconcile
    });
  }, []);

  const toggleApiConnection = useCallback(
    (connectionId: string) => {
      const current = apiConnections.find((c) => c.id === connectionId);
      if (!current) return;
      const nextStatus = current.status === "connected" ? "disconnected" : "connected";
      apiClient.updateApiConnection(connectionId, nextStatus).then((updated) => {
        setApiConnections((prev) => prev.map((c) => (c.id === connectionId ? updated : c)));
      });
    },
    [apiConnections],
  );

  const connectApiConnection = useCallback(
    async (
      connectionId: string,
      credentials: {
        accessToken: string;
        wabaId?: string;
        phoneNumberId?: string;
        instagramAccountId?: string;
        twilioAccountSid?: string;
        twilioPhoneNumber?: string;
        verifyToken?: string;
        appSecret?: string;
      },
    ) => {
      const updated = await apiClient.updateApiConnection(
        connectionId,
        "connected",
        credentials.accessToken,
        credentials.wabaId,
        credentials.phoneNumberId,
        credentials.instagramAccountId,
        credentials.twilioAccountSid,
        credentials.twilioPhoneNumber,
        credentials.verifyToken,
        credentials.appSecret,
      );
      setApiConnections((prev) => prev.map((c) => (c.id === connectionId ? updated : c)));
    },
    [],
  );

  const saveWebhookVerifyToken = useCallback(
    async (connectionId: string, verifyToken: string) => {
      const current = apiConnections.find((c) => c.id === connectionId);
      if (!current) return;
      const updated = await apiClient.updateApiConnection(
        connectionId,
        current.status,
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        verifyToken,
      );
      setApiConnections((prev) => prev.map((c) => (c.id === connectionId ? updated : c)));
    },
    [apiConnections],
  );

  const saveConnectionAppSecret = useCallback(
    async (connectionId: string, appSecret: string) => {
      const current = apiConnections.find((c) => c.id === connectionId);
      if (!current) return;
      const updated = await apiClient.updateApiConnection(
        connectionId,
        current.status,
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        appSecret,
      );
      setApiConnections((prev) => prev.map((c) => (c.id === connectionId ? updated : c)));
    },
    [apiConnections],
  );

  const addWhatsAppEmbeddedSignupConnection = useCallback(
    async (payload: {
      code: string;
      wabaId: string;
      phoneNumberId: string;
      waBusinessAppPhoneNumber?: string;
    }) => {
      const created = await apiClient.createWhatsAppEmbeddedSignupConnection(payload);
      setApiConnections((prev) => [...prev, created]);
      return created;
    },
    [],
  );

  const inviteTeamMember = useCallback(
    (member: {
      name: string;
      email: string;
      password: string;
      role: Extract<TeamMemberRole, "manager" | "agent" | "branch_manager" | "staff">;
      staffBranchId?: string | null;
    }) => {
      apiClient.inviteTeamMember(member).then((created) => {
        setTeamMembers((prev) => [...prev, created]);
      });
    },
    [],
  );

  const updateTeamMemberRole = useCallback((memberId: string, role: TeamMemberRole, staffBranchId?: string | null) => {
    apiClient.updateTeamMemberRole(memberId, role, staffBranchId).then((updated) => {
      setTeamMembers((prev) => prev.map((m) => (m.id === memberId ? updated : m)));
    });
  }, []);

  const removeTeamMember = useCallback((memberId: string) => {
    apiClient.removeTeamMember(memberId).then(() => {
      setTeamMembers((prev) => prev.filter((m) => m.id !== memberId));
    });
  }, []);

  const updateNotificationPreferences = useCallback((updates: Partial<NotificationPreferences>) => {
    apiClient.updateNotificationPreferences(updates).then((updated) => {
      setNotificationPreferences(updated);
    });
  }, []);

  const updateAiAssistantSettings = useCallback((updates: Partial<AiAssistantSettings>) => {
    apiClient.updateAiAssistantSettings(updates).then((updated) => {
      setAiAssistantSettings(updated);
    });
  }, []);

  const isCurrentUserAdmin = currentUser?.role === "admin";

  const value = useMemo(
    () => ({
      campaignsRevision,
      templatesRevision,
      dashboardRevision,
      contacts,
      contactsPagination,
      fetchContactsPage,
      allContacts,
      conversations,
      conversationsPagination,
      fetchConversationsPage,
      findConversationByContact,
      allConversations,
      messages,
      hasMoreOlderMessages: activeConversationId ? (hasMoreOlderByConversation[activeConversationId] ?? true) : false,
      loadOlderMessages,
      campaigns,
      campaignsPagination,
      fetchCampaignsPage,
      allCampaigns,
      dailyMetrics,
      automationFlows,
      automationFlowsPagination,
      fetchAutomationFlowsPage,
      moveContactToStage,
      updateContact,
      sendMessage,
      markConversationRead,
      updateConversationStatus,
      assignConversation,
      loadMessagesForConversation,
      addCampaign,
      addTagToContact,
      addAutomationFlow,
      setAutomationFlowStatus,
      deleteAutomationFlow,
      apiConnections,
      toggleApiConnection,
      connectApiConnection,
      saveWebhookVerifyToken,
      saveConnectionAppSecret,
      addWhatsAppEmbeddedSignupConnection,
      teamMembers,
      assignableMembers,
      inviteTeamMember,
      updateTeamMemberRole,
      removeTeamMember,
      notificationPreferences,
      updateNotificationPreferences,
      currentUser,
      isCurrentUserAdmin,
      aiAssistantSettings,
      updateAiAssistantSettings,
    }),
    [
      campaignsRevision,
      templatesRevision,
      dashboardRevision,
      contacts,
      contactsPagination,
      fetchContactsPage,
      allContacts,
      conversations,
      conversationsPagination,
      fetchConversationsPage,
      findConversationByContact,
      allConversations,
      messages,
      activeConversationId,
      hasMoreOlderByConversation,
      loadOlderMessages,
      campaigns,
      campaignsPagination,
      fetchCampaignsPage,
      allCampaigns,
      dailyMetrics,
      automationFlows,
      automationFlowsPagination,
      fetchAutomationFlowsPage,
      moveContactToStage,
      updateContact,
      sendMessage,
      markConversationRead,
      updateConversationStatus,
      assignConversation,
      loadMessagesForConversation,
      addCampaign,
      addTagToContact,
      addAutomationFlow,
      setAutomationFlowStatus,
      deleteAutomationFlow,
      apiConnections,
      toggleApiConnection,
      connectApiConnection,
      saveWebhookVerifyToken,
      saveConnectionAppSecret,
      addWhatsAppEmbeddedSignupConnection,
      teamMembers,
      assignableMembers,
      inviteTeamMember,
      updateTeamMemberRole,
      removeTeamMember,
      notificationPreferences,
      updateNotificationPreferences,
      currentUser,
      isCurrentUserAdmin,
      aiAssistantSettings,
      updateAiAssistantSettings,
    ],
  );

  return <CrmStoreContext.Provider value={value}>{children}</CrmStoreContext.Provider>;
}

export function useCrmStore() {
  const ctx = useContext(CrmStoreContext);
  if (!ctx) {
    throw new Error("useCrmStore must be used within a CrmStoreProvider");
  }
  return ctx;
}
