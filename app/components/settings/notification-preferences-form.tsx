import { useEffect, useState } from "react";
import Divider from "@mui/material/Divider";
import Stack from "@mui/material/Stack";
import Switch from "@mui/material/Switch";
import Typography from "@mui/material/Typography";
import type { NotificationPreferences } from "~/data/types";
import {
  disablePushNotifications,
  enablePushNotifications,
  getPushSubscriptionState,
  isPushSupported,
} from "~/utils/push-client";

interface NotificationPreferencesFormProps {
  preferences: NotificationPreferences;
  onChange: (updates: Partial<NotificationPreferences>) => void;
}

const PREFERENCE_ITEMS: { key: keyof NotificationPreferences; label: string; helper: string }[] = [
  {
    key: "newMessageAlerts",
    label: "New message alerts",
    helper: "Get notified when a contact sends a new WhatsApp or Instagram message.",
  },
  {
    key: "campaignCompleted",
    label: "Campaign completed",
    helper: "Get notified when a broadcast campaign finishes sending.",
  },
  {
    key: "automationTriggered",
    label: "Automation triggered",
    helper: "Get notified whenever an automation flow runs.",
  },
  {
    key: "dailySummaryEmail",
    label: "Daily summary email",
    helper: "Receive a daily recap of conversations, campaigns, and pipeline activity.",
  },
  {
    key: "weeklyAnalyticsReport",
    label: "Weekly analytics report",
    helper: "Receive a weekly email with delivery, engagement, and conversion metrics.",
  },
  {
    key: "soundAlerts",
    label: "Sound alerts",
    helper: "Play a sound when a new message arrives in the Inbox.",
  },
  {
    key: "whatsappCallAlerts",
    label: "WhatsApp call alerts",
    helper: "Get notified when a WhatsApp call completes or is missed.",
  },
  {
    key: "voiceCallAlerts",
    label: "AI voice call alerts",
    helper: "Get notified when an AI voice call completes or goes unanswered.",
  },
  {
    key: "templateStatusAlerts",
    label: "Template status alerts",
    helper: "Get notified when a submitted WhatsApp template is approved or rejected.",
  },
];

export function NotificationPreferencesForm({ preferences, onChange }: NotificationPreferencesFormProps) {
  const [pushEnabled, setPushEnabled] = useState(false);
  const [pushBusy, setPushBusy] = useState(false);
  const pushSupported = isPushSupported();

  useEffect(() => {
    getPushSubscriptionState().then((state) => setPushEnabled(state === "granted"));
  }, []);

  async function handlePushToggle(checked: boolean) {
    setPushBusy(true);
    try {
      if (checked) {
        const enabled = await enablePushNotifications();
        setPushEnabled(enabled);
      } else {
        await disablePushNotifications();
        setPushEnabled(false);
      }
    } finally {
      setPushBusy(false);
    }
  }

  return (
    <Stack divider={<Divider />} spacing={1.75}>
      {pushSupported && (
        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", gap: 2, py: 0.5 }}>
          <Stack sx={{ maxWidth: 480 }}>
            <Typography variant="body2" sx={{ fontWeight: 600 }}>
              Browser push notifications
            </Typography>
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              Receive push notifications on this device, even when the tab isn't focused.
            </Typography>
          </Stack>
          <Switch
            checked={pushEnabled}
            disabled={pushBusy}
            onChange={(e) => handlePushToggle(e.target.checked)}
            slotProps={{ input: { "aria-label": "Browser push notifications" } }}
          />
        </Stack>
      )}
      {PREFERENCE_ITEMS.map((item) => (
        <Stack key={item.key} direction="row" sx={{ alignItems: "center", justifyContent: "space-between", gap: 2, py: 0.5 }}>
          <Stack sx={{ maxWidth: 480 }}>
            <Typography variant="body2" sx={{ fontWeight: 600 }}>
              {item.label}
            </Typography>
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              {item.helper}
            </Typography>
          </Stack>
          <Switch
            checked={preferences[item.key]}
            onChange={(e) => onChange({ [item.key]: e.target.checked })}
            slotProps={{ input: { "aria-label": item.label } }}
          />
        </Stack>
      ))}
    </Stack>
  );
}
