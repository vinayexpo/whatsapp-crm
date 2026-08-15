import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { apiClient } from "~/utils/api-client";
import { getEcho } from "~/utils/echo-client";
import type {
  ActivityItem,
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
  PipelineStageId,
  TeamMember,
  TeamMemberRole,
} from "~/data/types";
import { useAuth } from "~/hooks/use-auth";

interface CrmStoreValue {
  contacts: Contact[];
  conversations: Conversation[];
  messages: Message[];
  campaigns: Campaign[];
  dailyMetrics: DailyMetric[];
  automationFlows: AutomationFlow[];
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
    },
  ) => Promise<void>;
  saveWebhookVerifyToken: (connectionId: string, verifyToken: string) => Promise<void>;
  teamMembers: TeamMember[];
  assignableMembers: TeamMember[];
  inviteTeamMember: (member: {
    name: string;
    email: string;
    password: string;
    role: Extract<TeamMemberRole, "manager" | "agent">;
  }) => void;
  updateTeamMemberRole: (memberId: string, role: TeamMemberRole) => void;
  removeTeamMember: (memberId: string) => void;
  notificationPreferences: NotificationPreferences;
  updateNotificationPreferences: (updates: Partial<NotificationPreferences>) => void;
  currentUser: TeamMember | null;
  isCurrentUserAdmin: boolean;
  aiAssistantSettings: AiAssistantSettings;
  updateAiAssistantSettings: (updates: Partial<AiAssistantSettings>) => void;
  activityFeed: ActivityItem[];
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
};

export function CrmStoreProvider({ children }: { children: ReactNode }) {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [messages, setMessages] = useState<Message[]>([]);
  const [activeConversationId, setActiveConversationId] = useState<string | null>(null);
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [dailyMetrics, setDailyMetrics] = useState<DailyMetric[]>([]);
  const [automationFlows, setAutomationFlows] = useState<AutomationFlow[]>([]);
  const [apiConnections, setApiConnections] = useState<ApiConnection[]>([]);
  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [assignableMembers, setAssignableMembers] = useState<TeamMember[]>([]);
  const [notificationPreferences, setNotificationPreferences] = useState<NotificationPreferences>(
    DEFAULT_NOTIFICATION_PREFERENCES,
  );
  const { user: currentUser, status: authStatus } = useAuth();
  const [aiAssistantSettings, setAiAssistantSettings] = useState<AiAssistantSettings>(DEFAULT_AI_ASSISTANT_SETTINGS);
  const [activityFeed, setActivityFeed] = useState<ActivityItem[]>([]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listContacts()
      .then((data) => {
        if (!cancelled) setContacts(data);
      })
      .catch(() => {
        // contacts list stays empty on failure; UI shows "no contacts"
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listConversations()
      .then((data) => {
        if (!cancelled) setConversations(data);
      })
      .catch(() => {
        // conversations list stays as-is on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus, currentUser?.role]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    if (activeConversationId) {
      apiClient
        .listMessages(activeConversationId)
        .then((data) => {
          if (cancelled) return;
          setMessages((prev) => [...prev.filter((m) => m.conversationId !== activeConversationId), ...data]);
        })
        .catch(() => {
          // messages stay as-is on failure
        });
    }
    return () => {
      cancelled = true;
    };
  }, [authStatus, activeConversationId]);

  useEffect(() => {
    if (authStatus !== "authenticated" || !currentUser?.companyId) return;

    const echo = getEcho();
    const channelName = `company.${currentUser.companyId}.conversations`;

    echo.private(channelName).listen(".conversation.created", (payload: { conversation: Conversation }) => {
      setConversations((prev) =>
        prev.some((c) => c.id === payload.conversation.id) ? prev : [payload.conversation, ...prev],
      );
    });

    return () => {
      echo.leave(channelName);
    };
  }, [authStatus, currentUser?.companyId]);

  const subscribedConversationIds = useRef<Set<string>>(new Set());

  useEffect(() => {
    if (authStatus !== "authenticated") return;

    const echo = getEcho();

    for (const c of conversations) {
      if (subscribedConversationIds.current.has(c.id)) continue;
      subscribedConversationIds.current.add(c.id);

      echo
        .private(`conversation.${c.id}`)
        .listen(".message.received", (payload: { message: Message }) => {
          setMessages((prev) =>
            prev.some((m) => m.id === payload.message.id) ? prev : [...prev, payload.message],
          );
        })
        .listen(".message.status-updated", (payload: { messageId: string; status: Message["status"] }) => {
          setMessages((prev) =>
            prev.map((m) => (m.id === payload.messageId ? { ...m, status: payload.status } : m)),
          );
        })
        .listen(".conversation.updated", (payload: { conversation: Conversation }) => {
          setConversations((prev) => {
            const exists = prev.some((c) => c.id === payload.conversation.id);
            return exists
              ? prev.map((c) => (c.id === payload.conversation.id ? payload.conversation : c))
              : [payload.conversation, ...prev];
          });
        });
    }
  }, [authStatus, conversations]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    return () => {
      const echo = getEcho();
      subscribedConversationIds.current.forEach((id) => echo.leave(`conversation.${id}`));
      subscribedConversationIds.current.clear();
    };
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listCampaigns()
      .then((data) => {
        if (!cancelled) setCampaigns(data);
      })
      .catch(() => {
        // campaigns list stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

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
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listAutomationFlows()
      .then((data) => {
        if (!cancelled) setAutomationFlows(data);
      })
      .catch(() => {
        // automation flows list stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

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

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listActivityFeed()
      .then((data) => {
        if (!cancelled) setActivityFeed(data);
      })
      .catch(() => {
        // activity feed stays empty on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  const moveContactToStage = useCallback(
    (contactId: string, stage: PipelineStageId) => {
      const previous = contacts.find((c) => c.id === contactId);
      if (!previous) return;
      setContacts((prev) => prev.map((c) => (c.id === contactId ? { ...c, pipelineStage: stage } : c)));
      apiClient.updateContactPipelineStage(contactId, stage, previous.updatedAt).then(
        (updated) => {
          setContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
        },
        () => {
          setContacts((prev) => prev.map((c) => (c.id === contactId ? previous : c)));
        },
      );
    },
    [contacts],
  );

  const loadMessagesForConversation = useCallback((conversationId: string) => {
    setActiveConversationId(conversationId);
  }, []);

  const sendMessage = useCallback((conversationId: string, text: string, attachmentFile?: File | null) => {
    apiClient.sendMessage(conversationId, text, attachmentFile).then((sent) => {
      setMessages((prev) => [...prev, sent]);
      setConversations((prev) =>
        prev.map((c) =>
          c.id === conversationId
            ? { ...c, lastMessagePreview: text || "Attachment", lastMessageAt: sent.timestamp }
            : c,
        ),
      );
    });
  }, []);

  const markConversationRead = useCallback((conversationId: string) => {
    setConversations((prev) => prev.map((c) => (c.id === conversationId ? { ...c, unreadCount: 0 } : c)));
    apiClient.markConversationRead(conversationId).catch(() => {
      // best-effort; next poll will reconcile
    });
  }, []);

  const updateConversationStatus = useCallback((conversationId: string, status: Conversation["status"]) => {
    setConversations((prev) => prev.map((c) => (c.id === conversationId ? { ...c, status } : c)));
    apiClient.updateConversationStatus(conversationId, status).catch(() => {
      // best-effort; next poll will reconcile
    });
  }, []);

  const assignConversation = useCallback((conversationId: string, userId: string | null) => {
    apiClient.assignConversation(conversationId, userId).then((updated) => {
      setConversations((prev) => prev.map((c) => (c.id === conversationId ? updated : c)));
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
        setCampaigns((prev) => [created, ...prev]);
      });
    },
    [],
  );

  const addTagToContact = useCallback((contactId: string, tag: string) => {
    apiClient.addContactTag(contactId, tag).then((updated) => {
      setContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
    });
  }, []);

  const updateContact = useCallback(
    (
      contactId: string,
      updates: Partial<Pick<Contact, "name" | "avatarUrl" | "phone" | "email" | "location" | "dealValue">>,
    ) => {
      return apiClient.updateContact(contactId, updates).then((updated) => {
        setContacts((prev) => prev.map((c) => (c.id === contactId ? updated : c)));
        return updated;
      });
    },
    [],
  );

  const addAutomationFlow = useCallback((flow: AutomationFlow) => {
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
        setAutomationFlows((prev) => [created, ...prev]);
      });
  }, []);

  const setAutomationFlowStatus = useCallback((flowId: string, status: AutomationStatus) => {
    setAutomationFlows((prev) => prev.map((f) => (f.id === flowId ? { ...f, status } : f)));
    apiClient.updateAutomationFlowStatus(flowId, status).catch(() => {
      // best-effort; next load will reconcile
    });
  }, []);

  const deleteAutomationFlow = useCallback((flowId: string) => {
    setAutomationFlows((prev) => prev.filter((f) => f.id !== flowId));
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

  const inviteTeamMember = useCallback(
    (member: {
      name: string;
      email: string;
      password: string;
      role: Extract<TeamMemberRole, "manager" | "agent">;
    }) => {
      apiClient.inviteTeamMember(member).then((created) => {
        setTeamMembers((prev) => [...prev, created]);
      });
    },
    [],
  );

  const updateTeamMemberRole = useCallback((memberId: string, role: TeamMemberRole) => {
    apiClient.updateTeamMemberRole(memberId, role).then((updated) => {
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
      contacts,
      conversations,
      messages,
      campaigns,
      dailyMetrics,
      automationFlows,
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
      activityFeed,
    }),
    [
      contacts,
      conversations,
      messages,
      campaigns,
      dailyMetrics,
      automationFlows,
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
      activityFeed,
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
