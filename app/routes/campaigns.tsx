import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import TextField from "@mui/material/TextField";
import InputAdornment from "@mui/material/InputAdornment";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import { DatePicker } from "@mui/x-date-pickers/DatePicker";
import dayjs, { type Dayjs } from "dayjs";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CampaignList } from "~/components/campaigns/campaign-list";
import { CampaignBuilderDialog } from "~/components/campaigns/campaign-builder-dialog";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Campaign, ChannelType } from "~/data/types";
import type { Route } from "./+types/campaigns";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Campaigns — Creative Connects" },
    { name: "description", content: "Create, schedule, and monitor broadcast marketing campaigns." },
  ];
}

const STATUS_TABS: { label: string; value: Campaign["status"] | "all" }[] = [
  { label: "All", value: "all" },
  { label: "Draft", value: "draft" },
  { label: "Scheduled", value: "scheduled" },
  { label: "Active", value: "active" },
  { label: "Completed", value: "completed" },
];

export default function Campaigns() {
  const { campaigns, campaignsPagination, fetchCampaignsPage, allContacts, addCampaign, apiConnections } =
    useCrmStore();
  const [statusFilter, setStatusFilter] = useState<Campaign["status"] | "all">("all");
  const [channelFilter, setChannelFilter] = useState<ChannelType | "both" | "all">("all");
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [customStart, setCustomStart] = useState<Dayjs | null>(null);
  const [customEnd, setCustomEnd] = useState<Dayjs | null>(null);
  const [builderOpen, setBuilderOpen] = useState(false);

  useEffect(() => {
    const timeout = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timeout);
  }, [search]);

  useEffect(() => {
    fetchCampaignsPage(1, {
      status: statusFilter === "all" ? undefined : statusFilter,
      channel: channelFilter === "all" ? undefined : channelFilter,
      search: debouncedSearch || undefined,
      from: customStart ? customStart.format("YYYY-MM-DD") : undefined,
      to: customEnd ? customEnd.format("YYYY-MM-DD") : undefined,
    });
  }, [fetchCampaignsPage, statusFilter, channelFilter, debouncedSearch, customStart, customEnd]);

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager"]}>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0, overflowY: "auto" }}>
        <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}>
          <Stack>
            <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
              Campaigns
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
              {campaignsPagination.total} broadcast campaigns across WhatsApp and Instagram
            </Typography>
          </Stack>
          <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setBuilderOpen(true)}>
            New Campaign
          </Button>
        </Stack>

        <Tabs
          value={statusFilter}
          onChange={(_, value) => setStatusFilter(value)}
          variant="scrollable"
          scrollButtons={false}
          sx={{ mb: 2, minHeight: 36, "& .MuiTab-root": { minHeight: 36 } }}
        >
          {STATUS_TABS.map((tab) => (
            <Tab key={tab.value} value={tab.value} label={tab.label} />
          ))}
        </Tabs>

        <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2.5, alignItems: { sm: "center" }, flexWrap: "wrap" }}>
          <TextField
            placeholder="Search campaigns…"
            size="small"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            sx={{ flex: 1, maxWidth: { sm: 280 } }}
            slotProps={{
              input: {
                startAdornment: (
                  <InputAdornment position="start">
                    <SearchRoundedIcon fontSize="small" />
                  </InputAdornment>
                ),
              },
            }}
          />
          <Select
            size="small"
            value={channelFilter}
            onChange={(e) => setChannelFilter(e.target.value as ChannelType | "both" | "all")}
            sx={{ minWidth: 160 }}
          >
            <MenuItem value="all">All channels</MenuItem>
            <MenuItem value="whatsapp">WhatsApp</MenuItem>
            <MenuItem value="instagram">Instagram</MenuItem>
            <MenuItem value="both">Both</MenuItem>
          </Select>
          <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
            <DatePicker
              label="From"
              value={customStart}
              onChange={setCustomStart}
              maxDate={customEnd ?? dayjs()}
              slotProps={{ textField: { size: "small", sx: { width: 150 } } }}
            />
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              to
            </Typography>
            <DatePicker
              label="To"
              value={customEnd}
              onChange={setCustomEnd}
              minDate={customStart ?? undefined}
              maxDate={dayjs()}
              slotProps={{ textField: { size: "small", sx: { width: 150 } } }}
            />
          </Stack>
        </Stack>

        <CampaignList campaigns={campaigns} />
        <PaginatedListFooter
          page={campaignsPagination.currentPage}
          lastPage={campaignsPagination.lastPage}
          onPageChange={fetchCampaignsPage}
        />
      </Box>

      <CampaignBuilderDialog
        open={builderOpen}
        onClose={() => setBuilderOpen(false)}
        onCreate={addCampaign}
        contacts={allContacts}
        apiConnections={apiConnections}
      />
      </RoleGuard>
    </AppLayout>
  );
}
