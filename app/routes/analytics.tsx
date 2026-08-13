import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import { DatePicker } from "@mui/x-date-pickers/DatePicker";
import dayjs from "dayjs";
import type { Dayjs } from "dayjs";
import ScheduleRoundedIcon from "@mui/icons-material/ScheduleRounded";
import MarkEmailReadRoundedIcon from "@mui/icons-material/MarkEmailReadRounded";
import ForumRoundedIcon from "@mui/icons-material/ForumRounded";
import TrendingUpRoundedIcon from "@mui/icons-material/TrendingUpRounded";
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Funnel,
  FunnelChart,
  LabelList,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { useMemo, useState } from "react";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { useCrmStore } from "~/hooks/use-crm-store";
import { PIPELINE_STAGES } from "~/data/pipeline-stages";
import { formatDate } from "~/utils/format";
import { CampaignDashboardPanel } from "~/components/analytics/campaign-dashboard-panel";
import type { Route } from "./+types/analytics";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Creative Connects — Analytics & Reporting" },
    {
      name: "description",
      content: "Track campaign delivery rates, response times, and conversion metrics across WhatsApp and Instagram.",
    },
  ];
}

const CHART_MARGIN = { top: 8, right: 16, left: -12, bottom: 0 };

type RangePreset = "7d" | "14d" | "30d" | "custom";

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

export default function Analytics() {
  const { contacts, campaigns, dailyMetrics } = useCrmStore();
  const [channelFilter, setChannelFilter] = useState<"all" | "whatsapp" | "instagram">("all");
  const [rangePreset, setRangePreset] = useState<RangePreset>("14d");
  const [customStart, setCustomStart] = useState<Dayjs | null>(null);
  const [customEnd, setCustomEnd] = useState<Dayjs | null>(null);

  const latestMetricDate = useMemo(
    () => (dailyMetrics.length > 0 ? dayjs(dailyMetrics[dailyMetrics.length - 1].date) : dayjs()),
    [dailyMetrics],
  );
  const earliestMetricDate = useMemo(
    () => (dailyMetrics.length > 0 ? dayjs(dailyMetrics[0].date) : latestMetricDate),
    [dailyMetrics, latestMetricDate],
  );

  const { rangeStart, rangeEnd } = useMemo(() => {
    if (rangePreset === "custom") {
      const start = customStart ?? latestMetricDate.subtract(13, "day");
      const end = customEnd ?? latestMetricDate;
      return start.isAfter(end) ? { rangeStart: end, rangeEnd: start } : { rangeStart: start, rangeEnd: end };
    }
    const days = rangePreset === "7d" ? 7 : rangePreset === "14d" ? 14 : 30;
    return { rangeStart: latestMetricDate.subtract(days - 1, "day"), rangeEnd: latestMetricDate };
  }, [rangePreset, customStart, customEnd, latestMetricDate]);

  const rangeMetrics = useMemo(
    () =>
      dailyMetrics.filter((d) => {
        const date = dayjs(d.date);
        return (date.isAfter(rangeStart, "day") || date.isSame(rangeStart, "day")) &&
          (date.isBefore(rangeEnd, "day") || date.isSame(rangeEnd, "day"));
      }),
    [dailyMetrics, rangeStart, rangeEnd],
  );

  const rangeDayCount = rangeEnd.diff(rangeStart, "day") + 1;

  const chartData = useMemo(
    () =>
      rangeMetrics.map((d) => ({
        ...d,
        label: formatDate(d.date).replace(",", ""),
        sent:
          channelFilter === "all"
            ? d.whatsappSent + d.instagramSent
            : channelFilter === "whatsapp"
              ? d.whatsappSent
              : d.instagramSent,
      })),
    [channelFilter, rangeMetrics],
  );

  const totals = useMemo(() => {
    const metrics = rangeMetrics.length > 0 ? rangeMetrics : dailyMetrics;
    const sent = metrics.reduce((s, d) => s + d.whatsappSent + d.instagramSent, 0);
    const delivered = metrics.reduce((s, d) => s + d.delivered, 0);
    const read = metrics.reduce((s, d) => s + d.read, 0);
    const replied = metrics.reduce((s, d) => s + d.replied, 0);
    const avgResponse = metrics.length > 0
      ? Math.round((metrics.reduce((s, d) => s + d.avgResponseMinutes, 0) / metrics.length) * 10) / 10
      : 0;
    const deliveryRate = sent > 0 ? Math.round((delivered / sent) * 100) : 0;
    const readRate = delivered > 0 ? Math.round((read / delivered) * 100) : 0;
    const replyRate = read > 0 ? Math.round((replied / read) * 100) : 0;
    return { sent, delivered, read, replied, avgResponse, deliveryRate, readRate, replyRate };
  }, [rangeMetrics, dailyMetrics]);

  const campaignDeliveryData = useMemo(
    () =>
      campaigns
        .filter((c) => c.recipientCount > 0)
        .map((c) => ({
          name: c.name.length > 18 ? `${c.name.slice(0, 16)}…` : c.name,
          "Delivery rate": Math.round((c.deliveredCount / c.recipientCount) * 100),
          "Read rate": c.deliveredCount > 0 ? Math.round((c.readCount / c.deliveredCount) * 100) : 0,
          "Reply rate": c.deliveredCount > 0 ? Math.round((c.repliedCount / c.deliveredCount) * 100) : 0,
        })),
    [campaigns],
  );

  const funnelData = useMemo(() => {
    const stageOrder = PIPELINE_STAGES.filter((s) => s.id !== "lost");
    let remaining = contacts.filter((c) => c.pipelineStage !== "lost").length;
    const countsByStage = stageOrder.map((stage) => contacts.filter((c) => c.pipelineStage === stage.id).length);
    return stageOrder.map((stage, idx) => {
      const value = countsByStage.slice(idx).reduce((s, v) => s + v, 0);
      return { name: stage.name, value, fill: stage.color };
    });
  }, [contacts]);

  const summaryCards = [
    {
      label: "Delivery Rate",
      value: `${totals.deliveryRate}%`,
      helper: `${totals.delivered.toLocaleString()} of ${totals.sent.toLocaleString()} sent`,
      icon: MarkEmailReadRoundedIcon,
      color: "#00A884",
    },
    {
      label: "Avg. Response Time",
      value: `${totals.avgResponse}m`,
      helper: "Time to first agent reply",
      icon: ScheduleRoundedIcon,
      color: "#3B82C4",
    },
    {
      label: "Read Rate",
      value: `${totals.readRate}%`,
      helper: `${totals.read.toLocaleString()} messages read`,
      icon: ForumRoundedIcon,
      color: "#7C4DFF",
    },
    {
      label: "Conversion Rate",
      value: `${totals.replyRate}%`,
      helper: `${totals.replied.toLocaleString()} replies received`,
      icon: TrendingUpRoundedIcon,
      color: "#F2A93B",
    },
  ];

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager"]}>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1 }}>
        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 2 }}>
          <Stack>
            <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
              Analytics & Reporting
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
              Delivery, engagement, and conversion performance across{" "}
              {rangePreset === "custom"
                ? `${formatDate(rangeStart.toISOString())} – ${formatDate(rangeEnd.toISOString())}`
                : `the last ${rangeDayCount} days`}
              .
            </Typography>
          </Stack>
          <Stack direction="row" sx={{ alignItems: "center", flexWrap: "wrap", gap: 1.5 }}>
            <ToggleButtonGroup
              size="small"
              exclusive
              value={rangePreset}
              onChange={(_, value) => value && setRangePreset(value)}
            >
              <ToggleButton value="7d">7D</ToggleButton>
              <ToggleButton value="14d">14D</ToggleButton>
              <ToggleButton value="30d">30D</ToggleButton>
              <ToggleButton value="custom">Custom</ToggleButton>
            </ToggleButtonGroup>
            {rangePreset === "custom" && (
              <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                <DatePicker
                  label="Start"
                  value={customStart}
                  onChange={(value) => setCustomStart(value)}
                  maxDate={customEnd ?? latestMetricDate}
                  minDate={earliestMetricDate}
                  slotProps={{ textField: { size: "small", sx: { width: 150 } } }}
                />
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  to
                </Typography>
                <DatePicker
                  label="End"
                  value={customEnd}
                  onChange={(value) => setCustomEnd(value)}
                  minDate={customStart ?? earliestMetricDate}
                  maxDate={latestMetricDate}
                  slotProps={{ textField: { size: "small", sx: { width: 150 } } }}
                />
              </Stack>
            )}
            <ToggleButtonGroup
              size="small"
              exclusive
              value={channelFilter}
              onChange={(_, value) => value && setChannelFilter(value)}
            >
              <ToggleButton value="all">All Channels</ToggleButton>
              <ToggleButton value="whatsapp">WhatsApp</ToggleButton>
              <ToggleButton value="instagram">Instagram</ToggleButton>
            </ToggleButtonGroup>
          </Stack>
        </Stack>

        <Box sx={{ mb: 3 }}>
          <Typography variant="h6" sx={{ fontSize: "1.15rem", mb: 2 }}>
            Cross-Campaign Dashboard
          </Typography>
          <CampaignDashboardPanel
            from={rangePreset === "custom" ? rangeStart.format("YYYY-MM-DD") : undefined}
            to={rangePreset === "custom" ? rangeEnd.format("YYYY-MM-DD") : undefined}
          />
        </Box>

        <Grid container spacing={2.5} sx={{ mb: 3 }}>
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

        <Grid container spacing={2.5} sx={{ mb: 2.5 }}>
          <Grid size={{ xs: 12, lg: 8 }}>
            <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3 }}>
              <CardHeading title="Messages Sent & Delivered" subtitle="Daily volume across the last 14 days" />
              <Box sx={{ width: "100%", height: 280 }}>
                <ResponsiveContainer>
                  <AreaChart data={chartData} margin={CHART_MARGIN}>
                    <defs>
                      <linearGradient id="sentGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#00A884" stopOpacity={0.35} />
                        <stop offset="95%" stopColor="#00A884" stopOpacity={0} />
                      </linearGradient>
                      <linearGradient id="deliveredGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#3B82C4" stopOpacity={0.3} />
                        <stop offset="95%" stopColor="#3B82C4" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="label" tick={{ fontSize: 11 }} interval={1} />
                    <YAxis tick={{ fontSize: 11 }} />
                    <Tooltip />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    <Area type="monotone" dataKey="sent" name="Sent" stroke="#00A884" fill="url(#sentGradient)" strokeWidth={2} />
                    <Area
                      type="monotone"
                      dataKey="delivered"
                      name="Delivered"
                      stroke="#3B82C4"
                      fill="url(#deliveredGradient)"
                      strokeWidth={2}
                    />
                  </AreaChart>
                </ResponsiveContainer>
              </Box>
            </Paper>
          </Grid>

          <Grid size={{ xs: 12, lg: 4 }}>
            <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%" }}>
              <CardHeading title="Avg. Response Time" subtitle="Minutes to first reply" />
              <Box sx={{ width: "100%", height: 280 }}>
                <ResponsiveContainer>
                  <LineChart data={chartData} margin={CHART_MARGIN}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="label" tick={{ fontSize: 11 }} interval={2} />
                    <YAxis tick={{ fontSize: 11 }} unit="m" />
                    <Tooltip />
                    <Line
                      type="monotone"
                      dataKey="avgResponseMinutes"
                      name="Response time"
                      stroke="#EC6F56"
                      strokeWidth={2.5}
                      dot={false}
                    />
                  </LineChart>
                </ResponsiveContainer>
              </Box>
            </Paper>
          </Grid>
        </Grid>

        <Grid container spacing={2.5}>
          <Grid size={{ xs: 12, lg: 7 }}>
            <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3 }}>
              <CardHeading title="Campaign Delivery Rates" subtitle="Delivery, read, and reply rates by campaign" />
              <Box sx={{ width: "100%", height: 320 }}>
                <ResponsiveContainer>
                  <BarChart data={campaignDeliveryData} margin={CHART_MARGIN}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="name" tick={{ fontSize: 10 }} interval={0} angle={-15} textAnchor="end" height={60} />
                    <YAxis tick={{ fontSize: 11 }} unit="%" />
                    <Tooltip />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    <Bar dataKey="Delivery rate" fill="#00A884" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="Read rate" fill="#3B82C4" radius={[4, 4, 0, 0]} />
                    <Bar dataKey="Reply rate" fill="#F2A93B" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </Box>
            </Paper>
          </Grid>

          <Grid size={{ xs: 12, lg: 5 }}>
            <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%" }}>
              <CardHeading title="Lead Conversion Funnel" subtitle="Contacts by pipeline stage (excluding lost)" />
              <Box sx={{ width: "100%", height: 320 }}>
                <ResponsiveContainer>
                  <FunnelChart>
                    <Tooltip />
                    <Funnel dataKey="value" data={funnelData} isAnimationActive>
                      <LabelList position="right" dataKey="name" fill="#3D4F4A" stroke="none" fontSize={12} />
                      <LabelList position="left" dataKey="value" fill="#3D4F4A" stroke="none" fontSize={12} />
                      {funnelData.map((entry) => (
                        <Cell key={entry.name} fill={entry.fill} />
                      ))}
                    </Funnel>
                  </FunnelChart>
                </ResponsiveContainer>
              </Box>
            </Paper>
          </Grid>
        </Grid>
      </Box>
      </RoleGuard>
    </AppLayout>
  );
}
