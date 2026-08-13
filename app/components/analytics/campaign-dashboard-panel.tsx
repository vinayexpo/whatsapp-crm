import { useEffect, useMemo, useState } from "react";
import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import CircularProgress from "@mui/material/CircularProgress";
import Alert from "@mui/material/Alert";
import CampaignRoundedIcon from "@mui/icons-material/CampaignRounded";
import GroupsRoundedIcon from "@mui/icons-material/GroupsRounded";
import MarkEmailReadRoundedIcon from "@mui/icons-material/MarkEmailReadRounded";
import TrendingUpRoundedIcon from "@mui/icons-material/TrendingUpRounded";
import { AreaChart, Area, CartesianGrid, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";
import dayjs from "dayjs";
import { apiClient } from "~/utils/api-client";
import type { CampaignDashboardData } from "~/data/types";
import { CampaignPerformanceTable } from "./campaign-performance-table";

const CHART_MARGIN = { top: 8, right: 16, left: -12, bottom: 0 };

function CardHeading({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <Stack sx={{ mb: 2 }}>
      <Typography variant="h6" sx={{ fontSize: "1.05rem" }}>
        {title}
      </Typography>
      {subtitle && (
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          {subtitle}
        </Typography>
      )}
    </Stack>
  );
}

export function CampaignDashboardPanel({ from, to }: { from?: string; to?: string }) {
  const [dashboard, setDashboard] = useState<CampaignDashboardData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiClient
      .getCampaignDashboard({ from, to })
      .then((data) => {
        if (!cancelled) setDashboard(data);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load the campaign dashboard.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [from, to]);

  const chartData = useMemo(
    () =>
      (dashboard?.trend ?? []).map((point) => ({
        ...point,
        label: dayjs(point.date).format("MMM D"),
      })),
    [dashboard],
  );

  if (loading) {
    return (
      <Box sx={{ display: "flex", justifyContent: "center", py: 6 }}>
        <CircularProgress size={28} />
      </Box>
    );
  }

  if (error) {
    return <Alert severity="error">{error}</Alert>;
  }

  if (!dashboard) return null;

  const { totals } = dashboard;

  const summaryCards = [
    {
      label: "Total Campaigns",
      value: totals.campaignCount.toLocaleString(),
      helper: `${totals.recipientCount.toLocaleString()} recipients targeted`,
      icon: CampaignRoundedIcon,
      color: "#00A884",
    },
    {
      label: "Delivery Rate",
      value: `${totals.deliveryRate}%`,
      helper: `${totals.deliveredCount.toLocaleString()} of ${totals.recipientCount.toLocaleString()} delivered`,
      icon: GroupsRoundedIcon,
      color: "#3B82C4",
    },
    {
      label: "Read Rate",
      value: `${totals.readRate}%`,
      helper: `${totals.readCount.toLocaleString()} messages read`,
      icon: MarkEmailReadRoundedIcon,
      color: "#7C4DFF",
    },
    {
      label: "Reply Rate",
      value: `${totals.replyRate}%`,
      helper: `${totals.repliedCount.toLocaleString()} replies received`,
      icon: TrendingUpRoundedIcon,
      color: "#F2A93B",
    },
  ];

  return (
    <Stack spacing={2.5}>
      <Grid container spacing={2.5}>
        {summaryCards.map((card) => {
          const Icon = card.icon;
          return (
            <Grid key={card.label} size={{ xs: 12, sm: 6, lg: 3 }}>
              <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%" }}>
                <Box
                  sx={{
                    width: 42,
                    height: 42,
                    borderRadius: 2.5,
                    bgcolor: `${card.color}1f`,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    mb: 1.5,
                  }}
                >
                  <Icon sx={{ color: card.color, fontSize: 22 }} />
                </Box>
                <Typography variant="h4" sx={{ fontSize: "1.9rem" }}>
                  {card.value}
                </Typography>
                <Typography variant="body2" sx={{ color: "text.secondary", fontWeight: 600, mt: 0.25 }}>
                  {card.label}
                </Typography>
                <Typography variant="caption" sx={{ color: "text.secondary", mt: 0.75, display: "block" }}>
                  {card.helper}
                </Typography>
              </Paper>
            </Grid>
          );
        })}
      </Grid>

      <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3 }}>
        <CardHeading title="Cross-Campaign Volume" subtitle="Sent vs. delivered across all campaigns" />
        <Box sx={{ width: "100%", height: 260 }}>
          <ResponsiveContainer>
            <AreaChart data={chartData} margin={CHART_MARGIN}>
              <defs>
                <linearGradient id="dashSentGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#00A884" stopOpacity={0.35} />
                  <stop offset="95%" stopColor="#00A884" stopOpacity={0} />
                </linearGradient>
                <linearGradient id="dashDeliveredGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#3B82C4" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#3B82C4" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="label" tick={{ fontSize: 11 }} interval={1} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip />
              <Area type="monotone" dataKey="sent" name="Sent" stroke="#00A884" fill="url(#dashSentGradient)" strokeWidth={2} />
              <Area
                type="monotone"
                dataKey="delivered"
                name="Delivered"
                stroke="#3B82C4"
                fill="url(#dashDeliveredGradient)"
                strokeWidth={2}
              />
            </AreaChart>
          </ResponsiveContainer>
        </Box>
      </Paper>

      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 6 }}>
          <CampaignPerformanceTable title="Top Performing Campaigns" campaigns={dashboard.topPerformers} />
        </Grid>
        <Grid size={{ xs: 12, lg: 6 }}>
          <CampaignPerformanceTable title="Lowest Performing Campaigns" campaigns={dashboard.bottomPerformers} />
        </Grid>
      </Grid>
    </Stack>
  );
}
