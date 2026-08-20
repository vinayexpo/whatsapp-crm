import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Chip from "@mui/material/Chip";
import LinearProgress from "@mui/material/LinearProgress";
import TrendingUpRoundedIcon from "@mui/icons-material/TrendingUpRounded";
import ChatBubbleOutlineRoundedIcon from "@mui/icons-material/ChatBubbleOutlineRounded";
import PeopleAltRoundedIcon from "@mui/icons-material/PeopleAltRounded";
import CampaignRoundedIcon from "@mui/icons-material/CampaignRounded";
import MessageRoundedIcon from "@mui/icons-material/MessageRounded";
import PersonAddRoundedIcon from "@mui/icons-material/PersonAddRounded";
import SwapHorizRoundedIcon from "@mui/icons-material/SwapHorizRounded";
import CampaignIcon from "@mui/icons-material/Campaign";
import { AppLayout } from "~/components/app-layout/app-layout";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { useCrmStore } from "~/hooks/use-crm-store";
import { formatCurrency, formatRelativeTime } from "~/utils/format";
import type { Route } from "./+types/home";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Creative Connects — Dashboard" },
    {
      name: "description",
      content: "Overview of leads, active conversations, and campaign performance across WhatsApp and Instagram.",
    },
  ];
}

const ACTIVITY_ICONS = {
  message: MessageRoundedIcon,
  campaign: CampaignIcon,
  pipeline: SwapHorizRoundedIcon,
  contact: PersonAddRoundedIcon,
};

export default function Home() {
  const { contacts, conversations, campaigns, activityFeed } = useCrmStore();

  const activeLeads = contacts.filter((c) => !["won", "lost"].includes(c.pipelineStage)).length;
  const openChats = conversations.filter((c) => c.status === "open" || c.status === "pending").length;
  const activeCampaigns = campaigns.filter((c) => c.status === "active" || c.status === "scheduled").length;
  const totalReplied = campaigns.reduce((sum, c) => sum + c.repliedCount, 0);
  const totalRecipients = campaigns.reduce((sum, c) => sum + c.recipientCount, 0);
  const conversionRate = totalRecipients > 0 ? Math.round((totalReplied / totalRecipients) * 100) : 0;
  const wonDeals = contacts.filter((c) => c.pipelineStage === "won");
  const wonValue = wonDeals.reduce((sum, c) => sum + c.dealValue, 0);

  const metrics = [
    {
      label: "Active Leads",
      value: activeLeads,
      icon: PeopleAltRoundedIcon,
      color: "#3B82C4",
      helper: `${contacts.length} total contacts`,
    },
    {
      label: "Open Chats",
      value: openChats,
      icon: ChatBubbleOutlineRoundedIcon,
      color: "#00A884",
      helper: `${conversations.reduce((s, c) => s + c.unreadCount, 0)} unread messages`,
    },
    {
      label: "Active Campaigns",
      value: activeCampaigns,
      icon: CampaignRoundedIcon,
      color: "#7C4DFF",
      helper: `${campaigns.length} campaigns total`,
    },
    {
      label: "Conversion Rate",
      value: `${conversionRate}%`,
      icon: TrendingUpRoundedIcon,
      color: "#F2A93B",
      helper: `${formatCurrency(wonValue)} won this quarter`,
    },
  ];

  return (
    <AppLayout>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, overflowY: "auto" }}>
        <Stack sx={{ mb: 3 }}>
          <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
            Good afternoon 👋
          </Typography>
          <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
            Here's what's happening across your WhatsApp and Instagram channels today.
          </Typography>
        </Stack>

        <Grid container spacing={2.5} sx={{ mb: 3 }}>
          {metrics.map((metric) => {
            const Icon = metric.icon;
            return (
              <Grid key={metric.label} size={{ xs: 12, sm: 6, lg: 3 }}>
                <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%" }}>
                  <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", mb: 1.5 }}>
                    <Box
                      sx={{
                        width: 42,
                        height: 42,
                        borderRadius: 2.5,
                        bgcolor: `${metric.color}1f`,
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                      }}
                    >
                      <Icon sx={{ color: metric.color, fontSize: 22 }} />
                    </Box>
                  </Stack>
                  <Typography variant="h4" sx={{ fontSize: "1.9rem" }}>
                    {metric.value}
                  </Typography>
                  <Typography variant="body2" sx={{ color: "text.secondary", fontWeight: 600, mt: 0.25 }}>
                    {metric.label}
                  </Typography>
                  <Typography variant="caption" sx={{ color: "text.secondary", mt: 0.75, display: "block" }}>
                    {metric.helper}
                  </Typography>
                </Paper>
              </Grid>
            );
          })}
        </Grid>

        <Grid container spacing={2.5}>
          <Grid size={{ xs: 12, lg: 7 }}>
            <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3 }}>
              <Typography variant="h6" sx={{ fontSize: "1.05rem", mb: 2 }}>
                Campaign Performance
              </Typography>
              <Stack spacing={2.5}>
                {campaigns.slice(0, 4).map((campaign) => {
                  const readRate =
                    campaign.deliveredCount > 0 ? Math.round((campaign.readCount / campaign.deliveredCount) * 100) : 0;
                  return (
                    <Box key={campaign.id}>
                      <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", mb: 0.75 }}>
                        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                          {campaign.channel !== "both" ? (
                            <ChannelIcon channel={campaign.channel} size={18} />
                          ) : (
                            <Stack direction="row" sx={{ gap: 0.5 }}>
                              <ChannelIcon channel="whatsapp" size={18} />
                              <ChannelIcon channel="instagram" size={18} />
                            </Stack>
                          )}
                          <Typography variant="body2" sx={{ fontWeight: 600 }}>
                            {campaign.name}
                          </Typography>
                        </Stack>
                        <Chip
                          size="small"
                          label={campaign.status}
                          sx={{ textTransform: "capitalize" }}
                          color={
                            campaign.status === "active"
                              ? "success"
                              : campaign.status === "scheduled"
                                ? "info"
                                : campaign.status === "completed"
                                  ? "default"
                                  : "warning"
                          }
                        />
                      </Stack>
                      <LinearProgress
                        variant="determinate"
                        value={readRate}
                        sx={{ height: 8, borderRadius: 4, bgcolor: "action.hover" }}
                      />
                      <Typography variant="caption" sx={{ color: "text.secondary", mt: 0.5, display: "block" }}>
                        {campaign.readCount.toLocaleString()} reads · {campaign.repliedCount.toLocaleString()} replies
                        of {campaign.recipientCount.toLocaleString()} recipients
                      </Typography>
                    </Box>
                  );
                })}
              </Stack>
            </Paper>
          </Grid>

          <Grid size={{ xs: 12, lg: 5 }}>
            <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%" }}>
              <Typography variant="h6" sx={{ fontSize: "1.05rem", mb: 2 }}>
                Recent Activity
              </Typography>
              <Stack spacing={2}>
                {activityFeed.map((activity) => {
                  const Icon = ACTIVITY_ICONS[activity.type];
                  return (
                    <Stack key={activity.id} direction="row" sx={{ gap: 1.5, alignItems: "flex-start" }}>
                      <Avatar sx={{ width: 32, height: 32, bgcolor: "action.hover" }}>
                        <Icon sx={{ fontSize: 17, color: "text.secondary" }} />
                      </Avatar>
                      <Box sx={{ flex: 1, minWidth: 0 }}>
                        <Typography variant="body2" sx={{ lineHeight: 1.4 }}>
                          {activity.description}
                        </Typography>
                        <Typography variant="caption" sx={{ color: "text.secondary" }}>
                          {formatRelativeTime(activity.timestamp)}
                        </Typography>
                      </Box>
                    </Stack>
                  );
                })}
              </Stack>
            </Paper>
          </Grid>
        </Grid>
      </Box>
    </AppLayout>
  );
}
