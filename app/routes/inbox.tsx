import { useEffect, useMemo, useRef, useState } from "react";
import { useSearchParams } from "react-router";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Drawer from "@mui/material/Drawer";
import InfoOutlinedIcon from "@mui/icons-material/InfoOutlined";
import { AppLayout } from "~/components/app-layout/app-layout";
import { ConversationList, type FilterAssignee, type FilterChannel } from "~/components/inbox/conversation-list";
import { ChatWindow } from "~/components/inbox/chat-window";
import { ContactDetailsPanel } from "~/components/inbox/contact-details-panel";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Conversation } from "~/data/types";
import type { Route } from "./+types/inbox";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Inbox — Creative Connects" },
    { name: "description", content: "Manage live WhatsApp and Instagram conversations in one unified inbox." },
  ];
}

export default function Inbox() {
  const {
    allContacts,
    conversations,
    conversationsPagination,
    fetchConversationsPage,
    findConversationByContact,
    messages,
    hasMoreOlderMessages,
    loadOlderMessages,
    assignableMembers,
    currentUser,
    sendMessage,
    markConversationRead,
    updateConversationStatus,
    assignConversation,
    moveContactToStage,
    addTagToContact,
    loadMessagesForConversation,
    aiAssistantSettings,
  } = useCrmStore();
  const [searchParams, setSearchParams] = useSearchParams();
  const [activeConversationId, setActiveConversationId] = useState<string | null>(null);
  const [detailsOpen, setDetailsOpen] = useState(false);
  const hasAutoSelected = useRef(false);
  const [searchInput, setSearchInput] = useState("");
  const isFirstSearchRender = useRef(true);
  const [statusFilter, setStatusFilter] = useState<Conversation["status"] | "all">("all");
  const [channelFilter, setChannelFilter] = useState<FilterChannel>("all");
  const [assigneeFilter, setAssigneeFilter] = useState<FilterAssignee>("all");

  const requestedContactId = searchParams.get("contactId");

  useEffect(() => {
    if (isFirstSearchRender.current) {
      isFirstSearchRender.current = false;
      return;
    }
    const handle = setTimeout(() => {
      fetchConversationsPage(
        1,
        assigneeFilter === "all" ? undefined : assigneeFilter,
        searchInput.trim() || undefined,
        statusFilter === "all" ? undefined : statusFilter,
        channelFilter === "all" ? undefined : channelFilter,
      );
    }, 300);
    return () => clearTimeout(handle);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput, statusFilter, channelFilter, assigneeFilter, fetchConversationsPage]);

  useEffect(() => {
    if (!requestedContactId) return;
    hasAutoSelected.current = true;
    let cancelled = false;
    findConversationByContact(requestedContactId).then((conversationId) => {
      if (cancelled || !conversationId) return;
      setActiveConversationId(conversationId);
      loadMessagesForConversation(conversationId);
      setSearchParams((prev) => {
        const next = new URLSearchParams(prev);
        next.delete("contactId");
        return next;
      }, { replace: true });
    });
    return () => {
      cancelled = true;
    };
  }, [requestedContactId, findConversationByContact, loadMessagesForConversation, setSearchParams]);

  useEffect(() => {
    if (activeConversationId === null && conversations.length > 0 && !hasAutoSelected.current) {
      hasAutoSelected.current = true;
      setActiveConversationId(conversations[0].id);
      loadMessagesForConversation(conversations[0].id);
    }
  }, [activeConversationId, conversations, loadMessagesForConversation]);

  useEffect(() => {
    if (activeConversationId !== null && !conversations.some((c) => c.id === activeConversationId)) {
      setActiveConversationId(null);
    }
  }, [conversations, activeConversationId]);

  const contactsById = useMemo(() => new Map(allContacts.map((c) => [c.id, c])), [allContacts]);

  const activeConversation = conversations.find((c) => c.id === activeConversationId) ?? null;
  const activeContact = activeConversation ? contactsById.get(activeConversation.contactId) : null;
  const activeMessages = messages
    .filter((m) => m.conversationId === activeConversationId)
    .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());

  function handleSelectConversation(id: string) {
    setActiveConversationId(id);
    loadMessagesForConversation(id);
    markConversationRead(id);
    setDetailsOpen(false);
  }

  const detailsContent = activeContact ? (
    <ContactDetailsPanel
      contact={activeContact}
      onStageChange={(stage) => moveContactToStage(activeContact.id, stage)}
      onAddTag={(tag) => addTagToContact(activeContact.id, tag)}
    />
  ) : null;

  return (
    <AppLayout>
      <Box sx={{ display: "flex", flex: 1, minHeight: 0 }}>
        <Box
          sx={{
            width: { xs: "100%", sm: 340 },
            flexShrink: 0,
            borderRight: "1px solid",
            borderColor: "divider",
            display: { xs: activeConversationId ? "flex" : "none", sm: "flex" },
            flexDirection: "column",
            minHeight: 0,
          }}
        >
          <Box sx={{ flex: 1, minHeight: 0 }}>
            <ConversationList
              conversations={conversations}
              contactsById={contactsById}
              activeConversationId={activeConversationId}
              currentUserId={currentUser?.id}
              onSelect={handleSelectConversation}
              hideAssigneeFilter={currentUser?.role === "agent"}
              searchValue={searchInput}
              onSearchChange={setSearchInput}
              statusFilter={statusFilter}
              onStatusFilterChange={setStatusFilter}
              channelFilter={channelFilter}
              onChannelFilterChange={setChannelFilter}
              assigneeFilter={assigneeFilter}
              onAssigneeFilterChange={setAssigneeFilter}
            />
          </Box>
          <PaginatedListFooter
            page={conversationsPagination.currentPage}
            lastPage={conversationsPagination.lastPage}
            onPageChange={(page) =>
              fetchConversationsPage(
                page,
                assigneeFilter === "all" ? undefined : assigneeFilter,
                searchInput.trim() || undefined,
                statusFilter === "all" ? undefined : statusFilter,
                channelFilter === "all" ? undefined : channelFilter,
              )
            }
          />
        </Box>

        <Box
          sx={{
            flex: 1,
            display: { xs: activeConversationId ? "flex" : "none", sm: "flex" },
            minWidth: 0,
          }}
        >
          {activeConversation && activeContact ? (
            <ChatWindow
              conversation={activeConversation}
              contact={activeContact}
              messages={activeMessages}
              hasMoreOlderMessages={hasMoreOlderMessages}
              onLoadOlderMessages={() => loadOlderMessages(activeConversation.id)}
              teamMembers={assignableMembers}
              onSendMessage={(text, attachmentFile) => sendMessage(activeConversation.id, text, attachmentFile)}
              onStatusChange={(status) => updateConversationStatus(activeConversation.id, status)}
              onAssign={(userId) => assignConversation(activeConversation.id, userId)}
              onToggleDetails={() => setDetailsOpen(true)}
              aiAssistantSettings={aiAssistantSettings}
            />
          ) : (
            <Box sx={{ m: "auto", textAlign: "center" }}>
              <Typography variant="body1" sx={{ color: "text.secondary" }}>
                Select a conversation to get started.
              </Typography>
            </Box>
          )}
        </Box>

        {activeContact && (
          <Box
            sx={{
              width: 300,
              flexShrink: 0,
              borderLeft: "1px solid",
              borderColor: "divider",
              display: { xs: "none", lg: "block" },
            }}
          >
            {detailsContent}
          </Box>
        )}

        {activeContact && (
          <IconButton
            onClick={() => setDetailsOpen(true)}
            sx={{ position: "fixed", top: 76, right: 16, display: { xs: "flex", lg: "none" }, bgcolor: "background.paper", boxShadow: 1 }}
          >
            <InfoOutlinedIcon fontSize="small" />
          </IconButton>
        )}

        <Drawer anchor="right" open={detailsOpen} onClose={() => setDetailsOpen(false)}>
          <Box sx={{ width: 300 }}>{detailsContent}</Box>
        </Drawer>
      </Box>
    </AppLayout>
  );
}
