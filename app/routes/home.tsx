import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import LinearProgress from "@mui/material/LinearProgress";
import TrendingUpRoundedIcon from "@mui/icons-material/TrendingUpRounded";
import ChatBubbleOutlineRoundedIcon from "@mui/icons-material/ChatBubbleOutlineRounded";
import PeopleAltRoundedIcon from "@mui/icons-material/PeopleAltRounded";
import CampaignRoundedIcon from "@mui/icons-material/CampaignRounded";
import MessageRoundedIcon from "@mui/icons-material/MessageRounded";
import PersonAddRoundedIcon from "@mui/icons-material/PersonAddRounded";
import SwapHorizRoundedIcon from "@mui/icons-material/SwapHorizRounded";
import CampaignIcon from "@mui/icons-material/Campaign";
import ChevronLeftRoundedIcon from "@mui/icons-material/ChevronLeftRounded";
import ChevronRightRoundedIcon from "@mui/icons-material/ChevronRightRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { apiClient } from "~/utils/api-client";
import { formatCurrency, formatRelativeTime } from "~/utils/format";
import type { ActivityItem, Campaign } from "~/data/types";
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

function getGreeting(hour: number): string {
  if (hour < 5) return "Good night";
  if (hour < 12) return "Good morning";
  if (hour < 17) return "Good afternoon";
  return "Good evening";
}

const CAMPAIGNS_PER_PAGE = 4;
const ACTIVITY_PER_PAGE = 5;

export default function Home() {
  const greeting = getGreeting(new Date().getHours());
  const [summary, setSummary] = useState({
    totalContacts: 0,
    activeLeads: 0,
    wonValue: 0,
    openChats: 0,
    unreadMessages: 0,
    activeCampaigns: 0,
    totalCampaigns: 0,
    conversionRate: 0,
  });

  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [campaignsPage, setCampaignsPage] = useState(1);
  const [campaignsLastPage, setCampaignsLastPage] = useState(1);

  const [activityFeed, setActivityFeed] = useState<ActivityItem[]>([]);
  const [activityPage, setActivityPage] = useState(1);
  const [activityLastPage, setActivityLastPage] = useState(1);

  useEffect(() => {
    let cancelled = false;
    apiClient.getDashboardSummary().then((data) => {
      if (!cancelled) setSummary(data);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    apiClient.listCampaigns({ page: campaignsPage, perPage: CAMPAIGNS_PER_PAGE }).then(({ data, meta }) => {
      if (!cancelled) {
        setCampaigns(data);
        setCampaignsLastPage(meta.lastPage);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [campaignsPage]);

  useEffect(() => {
    let cancelled = false;
    apiClient.listActivityFeed({ page: activityPage, perPage: ACTIVITY_PER_PAGE }).then(({ data, meta }) => {
      if (!cancelled) {
        setActivityFeed(data);
        setActivityLastPage(meta.lastPage);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [activityPage]);

  const metrics = [
    {
      label: "Active Leads",
      value: summary.activeLeads,
      icon: PeopleAltRoundedIcon,
      color: "#3B82C4",
      helper: `${summary.totalContacts} total contacts`,
    },
    {
      label: "Open Chats",
      value: summary.openChats,
      icon: ChatBubbleOutlineRoundedIcon,
      color: "#00A884",
      helper: `${summary.unreadMessages} unread messages`,
    },
    {
      label: "Active Campaigns",
      value: summary.activeCampaigns,
      icon: CampaignRoundedIcon,
      color: "#7C4DFF",
      helper: `${summary.totalCampaigns} campaigns total`,
    },
    {
      label: "Conversion Rate",
      value: `${summary.conversionRate}%`,
      icon: TrendingUpRoundedIcon,
      color: "#F2A93B",
      helper: `${formatCurrency(summary.wonValue)} won this quarter`,
    },
  ];

  return (
    <AppLayout>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, overflowY: "auto" }}>
        <Stack sx={{ mb: 3 }}>
          <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
            {greeting} 👋
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
              <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", mb: 2 }}>
                <Typography variant="h6" sx={{ fontSize: "1.05rem" }}>
                  Campaign Performance
                </Typography>
                {campaignsLastPage > 1 && (
                  <Stack direction="row" sx={{ alignItems: "center", gap: 0.5 }}>
                    <IconButton
                      size="small"
                      disabled={campaignsPage <= 1}
                      onClick={() => setCampaignsPage((p) => p - 1)}
                    >
                      <ChevronLeftRoundedIcon fontSize="small" />
                    </IconButton>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      {campaignsPage} / {campaignsLastPage}
                    </Typography>
                    <IconButton
                      size="small"
                      disabled={campaignsPage >= campaignsLastPage}
                      onClick={() => setCampaignsPage((p) => p + 1)}
                    >
                      <ChevronRightRoundedIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                )}
              </Stack>
              <Stack spacing={2.5}>
                {campaigns.map((campaign) => {
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
              <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", mb: 2 }}>
                <Typography variant="h6" sx={{ fontSize: "1.05rem" }}>
                  Recent Activity
                </Typography>
                {activityLastPage > 1 && (
                  <Stack direction="row" sx={{ alignItems: "center", gap: 0.5 }}>
                    <IconButton size="small" disabled={activityPage <= 1} onClick={() => setActivityPage((p) => p - 1)}>
                      <ChevronLeftRoundedIcon fontSize="small" />
                    </IconButton>
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      {activityPage} / {activityLastPage}
                    </Typography>
                    <IconButton
                      size="small"
                      disabled={activityPage >= activityLastPage}
                      onClick={() => setActivityPage((p) => p + 1)}
                    >
                      <ChevronRightRoundedIcon fontSize="small" />
                    </IconButton>
                  </Stack>
                )}
              </Stack>
              {activityFeed.length === 0 ? (
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  No recent activity yet.
                </Typography>
              ) : (
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
              )}
            </Paper>
          </Grid>
        </Grid>
      </Box>
    </AppLayout>
  );
}
