import { useEffect, useMemo, useState } from "react";
import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import CircularProgress from "@mui/material/CircularProgress";
import Alert from "@mui/material/Alert";
import dayjs from "dayjs";
import type { Dayjs } from "dayjs";
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import PaidRoundedIcon from "@mui/icons-material/PaidRounded";
import ReceiptLongRoundedIcon from "@mui/icons-material/ReceiptLongRounded";
import StorefrontRoundedIcon from "@mui/icons-material/StorefrontRounded";
import TrendingUpRoundedIcon from "@mui/icons-material/TrendingUpRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { DateRangeFilter, type DateRangePreset } from "~/components/common/date-range-filter";
import { apiClient } from "~/utils/api-client";
import { formatCurrency } from "~/utils/format";
import type {
  Branch,
  CommerceSalesByBranchRow,
  CommerceSalesReportRow,
  CommerceTopProductRow,
} from "~/data/types";
import type { Route } from "./+types/commerce-reports";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Commerce Reports — Creative Connects" },
    { name: "description", content: "Sales, branch performance, and top products across WhatsApp commerce orders." },
  ];
}

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

export default function CommerceReports() {
  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchFilter, setBranchFilter] = useState<string | "all">("all");
  const [rangePreset, setRangePreset] = useState<DateRangePreset>("30d");
  const [customStart, setCustomStart] = useState<Dayjs | null>(null);
  const [customEnd, setCustomEnd] = useState<Dayjs | null>(null);

  const [sales, setSales] = useState<CommerceSalesReportRow[]>([]);
  const [salesByBranch, setSalesByBranch] = useState<CommerceSalesByBranchRow[]>([]);
  const [topProducts, setTopProducts] = useState<CommerceTopProductRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiClient
      .listBranches({ perPage: 100 })
      .then(({ data }) => setBranches(data))
      .catch(() => {
        // branch filter stays empty on failure
      });
  }, []);

  const today = dayjs();
  const { rangeStart, rangeEnd } = useMemo(() => {
    if (rangePreset === "custom") {
      const start = customStart ?? today.subtract(29, "day");
      const end = customEnd ?? today;
      return start.isAfter(end) ? { rangeStart: end, rangeEnd: start } : { rangeStart: start, rangeEnd: end };
    }
    const days = rangePreset === "7d" ? 7 : rangePreset === "14d" ? 14 : 30;
    return { rangeStart: today.subtract(days - 1, "day"), rangeEnd: today };
  }, [rangePreset, customStart, customEnd]);

  const rangeDayCount = rangeEnd.diff(rangeStart, "day") + 1;
  const groupBy = rangeDayCount > 60 ? "month" : rangeDayCount > 21 ? "week" : "day";

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    const params = {
      from: rangeStart.format("YYYY-MM-DD"),
      to: rangeEnd.format("YYYY-MM-DD"),
    };
    const branchId = branchFilter === "all" ? undefined : branchFilter;

    Promise.all([
      apiClient.getCommerceSalesReport({ ...params, branchId, groupBy }),
      apiClient.getCommerceSalesByBranchReport(params),
      apiClient.getCommerceTopProductsReport({ ...params, branchId, limit: 10 }),
    ])
      .then(([salesData, byBranchData, topProductsData]) => {
        if (cancelled) return;
        setSales(salesData);
        setSalesByBranch(byBranchData);
        setTopProducts(topProductsData);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load commerce reports.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [branchFilter, rangeStart, rangeEnd, groupBy]);

  const chartData = useMemo(
    () =>
      sales.map((row) => ({
        ...row,
        label: dayjs(row.period).isValid() ? dayjs(row.period).format("MMM D") : row.period,
      })),
    [sales],
  );

  const totals = useMemo(() => {
    const orderCount = sales.reduce((s, row) => s + row.orderCount, 0);
    const revenue = sales.reduce((s, row) => s + row.revenue, 0);
    const avgOrderValue = orderCount > 0 ? Math.round(revenue / orderCount) : 0;
    const branchesWithSales = salesByBranch.filter((row) => row.orderCount > 0).length;
    return { orderCount, revenue, avgOrderValue, branchesWithSales };
  }, [sales, salesByBranch]);

  const summaryCards = [
    {
      label: "Total Revenue",
      value: formatCurrency(totals.revenue),
      helper: `Across ${totals.orderCount.toLocaleString()} orders`,
      icon: PaidRoundedIcon,
      color: "#00A884",
    },
    {
      label: "Orders",
      value: totals.orderCount.toLocaleString(),
      helper: `Avg. ${formatCurrency(totals.avgOrderValue)} per order`,
      icon: ReceiptLongRoundedIcon,
      color: "#3B82C4",
    },
    {
      label: "Active Branches",
      value: totals.branchesWithSales.toLocaleString(),
      helper: `${branches.length.toLocaleString()} branches total`,
      icon: StorefrontRoundedIcon,
      color: "#7C4DFF",
    },
    {
      label: "Top Product",
      value: topProducts[0]?.name ?? "—",
      helper: topProducts[0] ? `${topProducts[0].totalQuantity.toLocaleString()} sold` : "No sales yet",
      icon: TrendingUpRoundedIcon,
      color: "#F2A93B",
    },
  ];

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager", "branch_manager"]}>
        <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, overflowY: "auto" }}>
          <Stack
            direction="row"
            sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}
          >
            <Stack>
              <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
                Commerce Reports
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Sales, branch performance, and top products across{" "}
                {rangePreset === "custom"
                  ? `${rangeStart.format("MMM D")} – ${rangeEnd.format("MMM D")}`
                  : `the last ${rangeDayCount} days`}
                .
              </Typography>
            </Stack>
            <Stack direction="row" sx={{ alignItems: "center", flexWrap: "wrap", gap: 1.5 }}>
              <Select
                size="small"
                value={branchFilter}
                onChange={(e) => setBranchFilter(e.target.value)}
                sx={{ minWidth: 180 }}
              >
                <MenuItem value="all">All branches</MenuItem>
                {branches.map((branch) => (
                  <MenuItem key={branch.id} value={branch.id}>
                    {branch.name}
                  </MenuItem>
                ))}
              </Select>
              <DateRangeFilter
                preset={rangePreset}
                onPresetChange={setRangePreset}
                start={customStart}
                end={customEnd}
                onStartChange={setCustomStart}
                onEndChange={setCustomEnd}
                maxDate={today}
              />
            </Stack>
          </Stack>

          {error && (
            <Alert severity="error" sx={{ mb: 2.5 }}>
              {error}
            </Alert>
          )}

          {loading ? (
            <Box sx={{ display: "flex", justifyContent: "center", py: 6 }}>
              <CircularProgress size={28} />
            </Box>
          ) : (
            <>
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
                        <Typography
                          variant="h4"
                          sx={{ fontSize: card.label === "Top Product" ? "1.15rem" : "1.9rem" }}
                          noWrap
                        >
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
                <Grid size={{ xs: 12, lg: 7 }}>
                  <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3 }}>
                    <CardHeading title="Revenue Over Time" subtitle="Grouped by day, week, or month depending on range" />
                    {chartData.length === 0 ? (
                      <Box sx={{ py: 6, textAlign: "center" }}>
                        <Typography variant="body2" sx={{ color: "text.secondary" }}>
                          No orders in this period.
                        </Typography>
                      </Box>
                    ) : (
                      <Box sx={{ width: "100%", height: 280 }}>
                        <ResponsiveContainer>
                          <AreaChart data={chartData} margin={CHART_MARGIN}>
                            <defs>
                              <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#00A884" stopOpacity={0.35} />
                                <stop offset="95%" stopColor="#00A884" stopOpacity={0} />
                              </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                            <XAxis dataKey="label" tick={{ fontSize: 11 }} interval={Math.ceil(chartData.length / 8)} />
                            <YAxis tick={{ fontSize: 11 }} />
                            <Tooltip formatter={(value: unknown) => formatCurrency(Number(value ?? 0))} />
                            <Area
                              type="monotone"
                              dataKey="revenue"
                              name="Revenue"
                              stroke="#00A884"
                              fill="url(#revenueGradient)"
                              strokeWidth={2}
                            />
                          </AreaChart>
                        </ResponsiveContainer>
                      </Box>
                    )}
                  </Paper>
                </Grid>

                <Grid size={{ xs: 12, lg: 5 }}>
                  <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%" }}>
                    <CardHeading title="Sales by Branch" subtitle="Revenue split across branches" />
                    {salesByBranch.length === 0 ? (
                      <Box sx={{ py: 6, textAlign: "center" }}>
                        <Typography variant="body2" sx={{ color: "text.secondary" }}>
                          No branch sales in this period.
                        </Typography>
                      </Box>
                    ) : (
                      <Box sx={{ width: "100%", height: 280 }}>
                        <ResponsiveContainer>
                          <BarChart data={salesByBranch} margin={CHART_MARGIN} layout="vertical">
                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                            <XAxis type="number" tick={{ fontSize: 11 }} />
                            <YAxis
                              type="category"
                              dataKey="branchName"
                              tick={{ fontSize: 11 }}
                              width={100}
                            />
                            <Tooltip formatter={(value: unknown) => formatCurrency(Number(value ?? 0))} />
                            <Bar dataKey="revenue" name="Revenue" fill="#3B82C4" radius={[0, 4, 4, 0]} />
                          </BarChart>
                        </ResponsiveContainer>
                      </Box>
                    )}
                  </Paper>
                </Grid>
              </Grid>

              <Grid container spacing={2.5}>
                <Grid size={{ xs: 12 }}>
                  <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3 }}>
                    <CardHeading title="Top Products" subtitle="Ranked by quantity sold in this period" />
                    {topProducts.length === 0 ? (
                      <Box sx={{ py: 6, textAlign: "center" }}>
                        <Typography variant="body2" sx={{ color: "text.secondary" }}>
                          No product sales in this period.
                        </Typography>
                      </Box>
                    ) : (
                      <Stack spacing={0}>
                        {topProducts.map((product, index) => (
                          <Stack
                            key={`${product.productId ?? product.name}-${index}`}
                            direction="row"
                            sx={{
                              alignItems: "center",
                              justifyContent: "space-between",
                              py: 1.25,
                              borderBottom: index < topProducts.length - 1 ? "1px solid" : "none",
                              borderColor: "divider",
                            }}
                          >
                            <Stack direction="row" sx={{ alignItems: "center", gap: 1.5, minWidth: 0 }}>
                              <Typography variant="body2" sx={{ color: "text.secondary", width: 24 }}>
                                #{index + 1}
                              </Typography>
                              <Typography variant="body2" noWrap sx={{ fontWeight: 500 }}>
                                {product.name}
                              </Typography>
                            </Stack>
                            <Stack direction="row" sx={{ alignItems: "center", gap: 3, flexShrink: 0 }}>
                              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                                {product.totalQuantity.toLocaleString()} sold
                              </Typography>
                              <Typography variant="body2" sx={{ fontWeight: 600, minWidth: 90, textAlign: "right" }}>
                                {formatCurrency(product.totalRevenue)}
                              </Typography>
                            </Stack>
                          </Stack>
                        ))}
                      </Stack>
                    )}
                  </Paper>
                </Grid>
              </Grid>
            </>
          )}
        </Box>
      </RoleGuard>
    </AppLayout>
  );
}
