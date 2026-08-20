import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Alert from "@mui/material/Alert";
import TextField from "@mui/material/TextField";
import InputAdornment from "@mui/material/InputAdornment";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import { Link } from "react-router";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import BoltRoundedIcon from "@mui/icons-material/BoltRounded";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { AutomationFlowCard } from "~/components/automations/automation-flow-card";
import { AutomationBuilderDialog } from "~/components/automations/automation-builder-dialog";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { AutomationStatus, ChannelType } from "~/data/types";
import type { Route } from "./+types/automations";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Automated Chat Flows — Creative Connects" },
    { name: "description", content: "Build rule-based triggers and automated responses across WhatsApp and Instagram." },
  ];
}

export default function Automations() {
  const {
    automationFlows,
    automationFlowsPagination,
    fetchAutomationFlowsPage,
    addAutomationFlow,
    setAutomationFlowStatus,
    deleteAutomationFlow,
    aiAssistantSettings,
  } = useCrmStore();
  const [builderOpen, setBuilderOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<AutomationStatus | "all">("all");
  const [channelFilter, setChannelFilter] = useState<ChannelType | "both" | "all">("all");

  useEffect(() => {
    const timeout = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timeout);
  }, [search]);

  useEffect(() => {
    fetchAutomationFlowsPage(1, {
      search: debouncedSearch || undefined,
      status: statusFilter === "all" ? undefined : statusFilter,
      channel: channelFilter === "all" ? undefined : channelFilter,
    });
  }, [fetchAutomationFlowsPage, debouncedSearch, statusFilter, channelFilter]);

  const activeCount = automationFlows.filter((f) => f.status === "active").length;
  const isAiConfigured = Boolean(
    aiAssistantSettings.baseUrl.trim() && aiAssistantSettings.apiKey?.trim() && aiAssistantSettings.model.trim(),
  );
  const hasUnconfiguredAiFlow =
    !isAiConfigured &&
    automationFlows.some((f) => f.status === "active" && f.actions.some((a) => a.type === "ai-reply"));

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager"]}>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0, overflowY: "auto" }}>
        <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}>
          <Stack>
            <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
              Automated Chat Flows
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
              {activeCount} of {automationFlowsPagination.total} flows active across WhatsApp and Instagram
            </Typography>
          </Stack>
          <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setBuilderOpen(true)}>
            New Automation
          </Button>
        </Stack>

        {hasUnconfiguredAiFlow && (
          <Alert severity="warning" sx={{ mb: 3 }}>
            One or more active automations use an AI-generated reply action, but the AI Assistant isn't configured
            yet. Add your Base API URL, API key, and model in{" "}
            <Link to="/settings">Settings</Link> so those replies can be generated.
          </Alert>
        )}

        <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2.5 }}>
          <TextField
            placeholder="Search automations…"
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
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value as AutomationStatus | "all")}
            sx={{ minWidth: 150 }}
          >
            <MenuItem value="all">All statuses</MenuItem>
            <MenuItem value="active">Active</MenuItem>
            <MenuItem value="paused">Paused</MenuItem>
            <MenuItem value="draft">Draft</MenuItem>
          </Select>
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
        </Stack>

        {automationFlows.length === 0 ? (
          <Paper variant="outlined" sx={{ p: 5, borderRadius: 3, textAlign: "center" }}>
            <BoltRoundedIcon sx={{ fontSize: 36, color: "text.secondary", mb: 1 }} />
            <Typography variant="body1" sx={{ fontWeight: 600 }}>
              No automations yet
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
              Create a rule-based flow to auto-reply, tag, or route contacts.
            </Typography>
          </Paper>
        ) : (
          <Grid container spacing={2.5}>
            {automationFlows.map((flow) => (
              <Grid key={flow.id} size={{ xs: 12, md: 6, lg: 4 }}>
                <AutomationFlowCard
                  flow={flow}
                  onToggle={(checked) => setAutomationFlowStatus(flow.id, checked ? "active" : "paused")}
                  onDelete={() => deleteAutomationFlow(flow.id)}
                />
              </Grid>
            ))}
          </Grid>
        )}
        <PaginatedListFooter
          page={automationFlowsPagination.currentPage}
          lastPage={automationFlowsPagination.lastPage}
          onPageChange={fetchAutomationFlowsPage}
        />
      </Box>

      <AutomationBuilderDialog open={builderOpen} onClose={() => setBuilderOpen(false)} onCreate={addAutomationFlow} />
      </RoleGuard>
    </AppLayout>
  );
}
