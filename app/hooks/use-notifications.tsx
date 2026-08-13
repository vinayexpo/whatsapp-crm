import { useCallback, useEffect, useState } from "react";
import { apiClient } from "~/utils/api-client";
import { getEcho } from "~/utils/echo-client";
import type { AppNotification } from "~/data/types";
import { useAuth } from "~/hooks/use-auth";

export function useNotifications() {
  const { user, status: authStatus } = useAuth();
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);

  useEffect(() => {
    if (authStatus !== "authenticated") return;
    let cancelled = false;
    apiClient
      .listNotifications()
      .then((data) => {
        if (!cancelled) setNotifications(data);
      })
      .catch(() => {
        // notifications list stays empty on failure
      });
    apiClient
      .getUnreadNotificationCount()
      .then((count) => {
        if (!cancelled) setUnreadCount(count);
      })
      .catch(() => {
        // unread count stays at 0 on failure
      });
    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  useEffect(() => {
    if (authStatus !== "authenticated" || !user?.numericId) return;

    const echo = getEcho();
    const channel = echo.private(`App.Models.User.${user.numericId}`);

    channel.listen(".notification.created", (payload: { notification: AppNotification }) => {
      setNotifications((prev) => {
        if (prev.some((n) => n.id === payload.notification.id)) return prev;
        setUnreadCount((count) => count + 1);
        return [payload.notification, ...prev];
      });
    });

    return () => {
      echo.leave(`App.Models.User.${user.numericId}`);
    };
  }, [authStatus, user?.numericId]);

  const markRead = useCallback((notificationId: string) => {
    setNotifications((prev) =>
      prev.map((n) => (n.id === notificationId ? { ...n, readAt: new Date().toISOString() } : n)),
    );
    setUnreadCount((prev) => Math.max(0, prev - 1));
    apiClient.markNotificationRead(notificationId).catch(() => {
      // best-effort; next load will reconcile
    });
  }, []);

  const markAllRead = useCallback(() => {
    setNotifications((prev) => prev.map((n) => (n.readAt ? n : { ...n, readAt: new Date().toISOString() })));
    setUnreadCount(0);
    apiClient.markAllNotificationsRead().catch(() => {
      // best-effort; next load will reconcile
    });
  }, []);

  return { notifications, unreadCount, markRead, markAllRead };
}
