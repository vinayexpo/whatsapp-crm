import { useState, type SyntheticEvent } from "react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Tab from "@mui/material/Tab";
import Tabs from "@mui/material/Tabs";
import Typography from "@mui/material/Typography";
import Alert from "@mui/material/Alert";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import LockRoundedIcon from "@mui/icons-material/LockRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { ConnectionCard } from "~/components/settings/connection-card";
import { TeamMemberRow } from "~/components/settings/team-member-row";
import { InviteMemberDialog } from "~/components/settings/invite-member-dialog";
import { NotificationPreferencesForm } from "~/components/settings/notification-preferences-form";
import { AiAssistantSettingsForm } from "~/components/settings/ai-assistant-settings-form";
import { MyProfileForm } from "~/components/settings/my-profile-form";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Route } from "./+types/settings";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Creative Connects — Settings" },
    {
      name: "description",
      content: "Manage WhatsApp/Instagram API connections, team members, and notification preferences.",
    },
  ];
}

type SettingsTab = "profile" | "connections" | "team" | "notifications" | "ai-assistant";

export default function Settings() {
  const {
    apiConnections,
    toggleApiConnection,
    connectApiConnection,
    saveWebhookVerifyToken,
    teamMembers,
    inviteTeamMember,
    updateTeamMemberRole,
    removeTeamMember,
    notificationPreferences,
    updateNotificationPreferences,
    isCurrentUserAdmin,
    aiAssistantSettings,
    updateAiAssistantSettings,
  } = useCrmStore();
  const [tab, setTab] = useState<SettingsTab>("profile");
  const [inviteOpen, setInviteOpen] = useState(false);

  function handleTabChange(_: SyntheticEvent, value: SettingsTab) {
    setTab(value);
  }

  return (
    <AppLayout>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1 }}>
        <Box sx={{ mb: 3 }}>
          <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
            Settings
          </Typography>
          <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
            Manage API connections, team access, and notification preferences.
          </Typography>
        </Box>

        <Paper variant="outlined" sx={{ borderRadius: 3, overflow: "hidden" }}>
          <Tabs value={tab} onChange={handleTabChange} sx={{ px: 2, borderBottom: "1px solid", borderColor: "divider" }}>
            <Tab value="profile" label="My Profile" />
            <Tab value="connections" label="API Connections" />
            <Tab value="team" label="Team Members" />
            <Tab value="notifications" label="Notifications" />
            <Tab value="ai-assistant" label="AI Assistant" />
          </Tabs>

          <Box sx={{ p: { xs: 2, md: 3 } }}>
            {tab === "profile" && <MyProfileForm />}

            {tab === "connections" && (
              <Stack spacing={2}>
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  Connect your WhatsApp Business and Instagram accounts to send and receive messages through Creative Connects.
                </Typography>
                {!isCurrentUserAdmin && (
                  <Alert severity="info" icon={<LockRoundedIcon fontSize="small" />}>
                    Only Admins can connect or disconnect API accounts. Contact an admin to make changes.
                  </Alert>
                )}
                {apiConnections.map((connection) => (
                  <ConnectionCard
                    key={connection.id}
                    connection={connection}
                    onToggle={toggleApiConnection}
                    onConnect={connectApiConnection}
                    onSaveVerifyToken={saveWebhookVerifyToken}
                    disabled={!isCurrentUserAdmin}
                  />
                ))}
              </Stack>
            )}

            {tab === "team" && (
              <Stack spacing={2}>
                {!isCurrentUserAdmin && (
                  <Alert severity="info" icon={<LockRoundedIcon fontSize="small" />}>
                    Only Admins can invite, change roles, or remove team members. You have read-only access to this list.
                  </Alert>
                )}
                <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 1.5 }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    {teamMembers.length} member{teamMembers.length === 1 ? "" : "s"} with access to this workspace.
                  </Typography>
                  <Button
                    variant="contained"
                    startIcon={<AddRoundedIcon />}
                    onClick={() => setInviteOpen(true)}
                    disabled={!isCurrentUserAdmin}
                  >
                    Invite Member
                  </Button>
                </Stack>
                <Paper variant="outlined" sx={{ borderRadius: 2.5, px: 2 }}>
                  <Stack divider={<Box sx={{ borderBottom: "1px solid", borderColor: "divider" }} />}>
                    {teamMembers.map((member) => (
                      <TeamMemberRow
                        key={member.id}
                        member={member}
                        onRoleChange={updateTeamMemberRole}
                        onRemove={removeTeamMember}
                        readOnly={!isCurrentUserAdmin}
                      />
                    ))}
                    {teamMembers.length === 0 && (
                      <Typography variant="body2" sx={{ color: "text.secondary", py: 2, textAlign: "center" }}>
                        No team members yet.
                      </Typography>
                    )}
                  </Stack>
                </Paper>
              </Stack>
            )}

            {tab === "notifications" && (
              <NotificationPreferencesForm preferences={notificationPreferences} onChange={updateNotificationPreferences} />
            )}

            {tab === "ai-assistant" && (
              <Stack spacing={2}>
                {!isCurrentUserAdmin && (
                  <Alert severity="info" icon={<LockRoundedIcon fontSize="small" />}>
                    Only Admins can configure the AI Assistant provider. Contact an admin to make changes.
                  </Alert>
                )}
                <AiAssistantSettingsForm
                  settings={aiAssistantSettings}
                  onChange={updateAiAssistantSettings}
                  readOnly={!isCurrentUserAdmin}
                />
              </Stack>
            )}
          </Box>
        </Paper>
      </Box>

      <InviteMemberDialog open={inviteOpen} onClose={() => setInviteOpen(false)} onInvite={inviteTeamMember} />
    </AppLayout>
  );
}
