import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Badge from "@mui/material/Badge";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import Chip from "@mui/material/Chip";
import TextField from "@mui/material/TextField";
import InputAdornment from "@mui/material/InputAdornment";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import classNames from "classnames";
import Tooltip from "@mui/material/Tooltip";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { formatRelativeTime } from "~/utils/format";
import type { Contact, Conversation } from "~/data/types";
import styles from "./conversation-list.module.css";

type FilterChannel = "all" | "whatsapp" | "instagram" | "website";
type FilterAssignee = "all" | "mine" | "unassigned";

interface ConversationListProps {
  conversations: Conversation[];
  contactsById: Map<string, Contact>;
  activeConversationId: string | null;
  currentUserId?: string;
  onSelect: (conversationId: string) => void;
  onFilteredChange?: (filtered: Conversation[]) => void;
  hideAssigneeFilter?: boolean;
  searchValue?: string;
  onSearchChange?: (value: string) => void;
}

const STATUS_TABS: { label: string; value: Conversation["status"] | "all" }[] = [
  { label: "All", value: "all" },
  { label: "Open", value: "open" },
  { label: "Pending", value: "pending" },
  { label: "Resolved", value: "resolved" },
  { label: "Archived", value: "archived" },
];

export function ConversationList({
  conversations,
  contactsById,
  activeConversationId,
  currentUserId,
  onSelect,
  onFilteredChange,
  hideAssigneeFilter = false,
  searchValue = "",
  onSearchChange,
}: ConversationListProps) {
  const [statusFilter, setStatusFilter] = useState<Conversation["status"] | "all">("all");
  const [channelFilter, setChannelFilter] = useState<FilterChannel>("all");
  const [assigneeFilter, setAssigneeFilter] = useState<FilterAssignee>("all");

  const filtered = conversations
    .filter((c) => statusFilter === "all" || c.status === statusFilter)
    .filter((c) => channelFilter === "all" || c.channel === channelFilter)
    .filter((c) => {
      if (assigneeFilter === "all") return true;
      if (assigneeFilter === "unassigned") return !c.assignedTo;
      return c.assignedTo?.id === currentUserId;
    })
    .sort((a, b) => new Date(b.lastMessageAt).getTime() - new Date(a.lastMessageAt).getTime());

  useEffect(() => {
    onFilteredChange?.(filtered);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filtered.map((c) => c.id).join(","), onFilteredChange]);

  return (
    <Box className={styles.container}>
      <Box className={styles.header}>
        <Typography variant="h6" sx={{ fontSize: "1.05rem", mb: 1.5 }}>
          Conversations
        </Typography>
        {onSearchChange && (
          <TextField
            size="small"
            fullWidth
            placeholder="Search by name or phone"
            value={searchValue}
            onChange={(e) => onSearchChange(e.target.value)}
            sx={{ mb: 1.5 }}
            slotProps={{
              input: {
                startAdornment: (
                  <InputAdornment position="start">
                    <SearchRoundedIcon fontSize="small" sx={{ color: "text.secondary" }} />
                  </InputAdornment>
                ),
              },
            }}
          />
        )}
        <Stack direction="row" spacing={1} sx={{ mb: 1.5, flexWrap: "wrap", rowGap: 1 }}>
          <Chip
            label="All channels"
            size="small"
            onClick={() => setChannelFilter("all")}
            color={channelFilter === "all" ? "primary" : "default"}
            variant={channelFilter === "all" ? "filled" : "outlined"}
          />
          <Chip
            label="WhatsApp"
            size="small"
            icon={<ChannelIcon channel="whatsapp" size={14} />}
            onClick={() => setChannelFilter("whatsapp")}
            color={channelFilter === "whatsapp" ? "primary" : "default"}
            variant={channelFilter === "whatsapp" ? "filled" : "outlined"}
          />
          <Chip
            label="Instagram"
            size="small"
            icon={<ChannelIcon channel="instagram" size={14} />}
            onClick={() => setChannelFilter("instagram")}
            color={channelFilter === "instagram" ? "primary" : "default"}
            variant={channelFilter === "instagram" ? "filled" : "outlined"}
          />
          <Chip
            label="Website"
            size="small"
            icon={<ChannelIcon channel="website" size={14} />}
            onClick={() => setChannelFilter("website")}
            color={channelFilter === "website" ? "primary" : "default"}
            variant={channelFilter === "website" ? "filled" : "outlined"}
          />
        </Stack>
        {!hideAssigneeFilter && (
          <Stack direction="row" spacing={1} sx={{ mb: 1.5, flexWrap: "wrap", rowGap: 1 }}>
            <Chip
              label="All conversations"
              size="small"
              onClick={() => setAssigneeFilter("all")}
              color={assigneeFilter === "all" ? "primary" : "default"}
              variant={assigneeFilter === "all" ? "filled" : "outlined"}
            />
            <Chip
              label="Assigned to me"
              size="small"
              onClick={() => setAssigneeFilter("mine")}
              color={assigneeFilter === "mine" ? "primary" : "default"}
              variant={assigneeFilter === "mine" ? "filled" : "outlined"}
            />
            <Chip
              label="Unassigned"
              size="small"
              onClick={() => setAssigneeFilter("unassigned")}
              color={assigneeFilter === "unassigned" ? "primary" : "default"}
              variant={assigneeFilter === "unassigned" ? "filled" : "outlined"}
            />
          </Stack>
        )}
        <Tabs
          value={statusFilter}
          onChange={(_, value) => setStatusFilter(value)}
          variant="scrollable"
          scrollButtons={false}
          sx={{ minHeight: 32, "& .MuiTab-root": { minHeight: 32, py: 0.5, fontSize: "0.8rem" } }}
        >
          {STATUS_TABS.map((tab) => (
            <Tab key={tab.value} value={tab.value} label={tab.label} />
          ))}
        </Tabs>
      </Box>

      <Box className={styles.list}>
        {filtered.length === 0 && (
          <Typography variant="body2" sx={{ color: "text.secondary", p: 3, textAlign: "center" }}>
            No conversations match this filter.
          </Typography>
        )}
        {filtered.map((conversation) => {
          const contact = conversation.contact ?? contactsById.get(conversation.contactId);
          if (!contact) return null;
          const isActive = conversation.id === activeConversationId;
          return (
            <button
              key={conversation.id}
              className={classNames(styles.item, { [styles.itemActive]: isActive })}
              onClick={() => onSelect(conversation.id)}
              type="button"
            >
              <Badge
                overlap="circular"
                anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
                badgeContent={<ChannelIcon channel={conversation.channel} size={16} />}
              >
                <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 44, height: 44 }} />
              </Badge>
              <Box sx={{ flex: 1, minWidth: 0, ml: 1.5 }}>
                <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
                  <Typography variant="body2" sx={{ fontWeight: 700 }} noWrap>
                    {contact.name}
                  </Typography>
                  <Typography variant="caption" sx={{ color: "text.secondary", flexShrink: 0, ml: 1 }}>
                    {formatRelativeTime(conversation.lastMessageAt)}
                  </Typography>
                </Stack>
                <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
                  <Typography variant="caption" sx={{ color: "text.secondary" }} noWrap>
                    {conversation.lastMessagePreview}
                  </Typography>
                  <Stack direction="row" sx={{ alignItems: "center", gap: 0.5, flexShrink: 0, ml: 1 }}>
                    {conversation.assignedTo && (
                      <Tooltip title={`Assigned to ${conversation.assignedTo.name}`}>
                        <Avatar
                          src={conversation.assignedTo.avatarUrl}
                          alt={conversation.assignedTo.name}
                          sx={{ width: 18, height: 18, fontSize: "0.6rem" }}
                        />
                      </Tooltip>
                    )}
                    {conversation.unreadCount > 0 && (
                      <Badge badgeContent={conversation.unreadCount} color="primary" sx={{ mr: 0.5 }} />
                    )}
                  </Stack>
                </Stack>
              </Box>
            </button>
          );
        })}
      </Box>
    </Box>
  );
}
