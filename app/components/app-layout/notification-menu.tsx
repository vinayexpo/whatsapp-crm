import { useState } from "react";
import { useNavigate } from "react-router";
import IconButton from "@mui/material/IconButton";
import Badge from "@mui/material/Badge";
import Menu from "@mui/material/Menu";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Divider from "@mui/material/Divider";
import MenuItem from "@mui/material/MenuItem";
import Button from "@mui/material/Button";
import NotificationsRoundedIcon from "@mui/icons-material/NotificationsRounded";
import { useNotifications } from "~/hooks/use-notifications";
import type { AppNotification } from "~/data/types";

function timeAgo(iso: string): string {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60) return "just now";
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

function notificationLink(notification: AppNotification): string | null {
  const data = notification.data;
  if (typeof data.conversationId === "string") return "/inbox";
  if (typeof data.campaignId === "string") return "/campaigns";
  if (typeof data.automationFlowId === "string") return "/automations";
  if (typeof data.whatsappCallId === "string") return "/whatsapp-calling";
  if (typeof data.voiceCallId === "string") return "/voice-agents";
  return null;
}

export function NotificationMenu() {
  const navigate = useNavigate();
  const { notifications, unreadCount, markRead, markAllRead } = useNotifications();
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
  const open = Boolean(anchorEl);

  function handleNotificationClick(notification: AppNotification) {
    if (!notification.readAt) markRead(notification.id);
    setAnchorEl(null);
    const link = notificationLink(notification);
    if (link) navigate(link);
  }

  return (
    <>
      <IconButton onClick={(e) => setAnchorEl(e.currentTarget)}>
        <Badge badgeContent={unreadCount} color="error" max={99}>
          <NotificationsRoundedIcon />
        </Badge>
      </IconButton>
      <Menu
        anchorEl={anchorEl}
        open={open}
        onClose={() => setAnchorEl(null)}
        anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
        transformOrigin={{ vertical: "top", horizontal: "right" }}
        slotProps={{ paper: { sx: { width: 360, maxHeight: 480 } } }}
      >
        <Box sx={{ display: "flex", alignItems: "center", justifyContent: "space-between", px: 2, py: 1 }}>
          <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
            Notifications
          </Typography>
          {unreadCount > 0 && (
            <Button size="small" onClick={markAllRead} sx={{ textTransform: "none" }}>
              Mark all read
            </Button>
          )}
        </Box>
        <Divider />
        {notifications.length === 0 && (
          <Box sx={{ px: 2, py: 3, textAlign: "center" }}>
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              No notifications yet
            </Typography>
          </Box>
        )}
        {notifications.map((notification) => (
          <MenuItem
            key={notification.id}
            onClick={() => handleNotificationClick(notification)}
            sx={{
              whiteSpace: "normal",
              alignItems: "flex-start",
              py: 1.25,
              bgcolor: notification.readAt ? "transparent" : "rgba(0, 168, 132, 0.06)",
            }}
          >
            <Box sx={{ display: "flex", flexDirection: "column", gap: 0.25, width: "100%" }}>
              <Typography variant="body2" sx={{ fontWeight: notification.readAt ? 500 : 700 }}>
                {notification.title}
              </Typography>
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                {notification.body}
              </Typography>
              <Typography variant="caption" sx={{ color: "text.disabled" }}>
                {timeAgo(notification.createdAt)}
              </Typography>
            </Box>
          </MenuItem>
        ))}
      </Menu>
    </>
  );
}
