import { useState } from "react";
import Box from "@mui/material/Box";
import Grid from "@mui/material/Grid";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Alert from "@mui/material/Alert";
import { Link } from "react-router";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import BoltRoundedIcon from "@mui/icons-material/BoltRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { AutomationFlowCard } from "~/components/automations/automation-flow-card";
import { AutomationBuilderDialog } from "~/components/automations/automation-builder-dialog";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Route } from "./+types/automations";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Automated Chat Flows — Creative Connects" },
    { name: "description", content: "Build rule-based triggers and automated responses across WhatsApp and Instagram." },
  ];
}

export default function Automations() {
  const { automationFlows, addAutomationFlow, setAutomationFlowStatus, deleteAutomationFlow, aiAssistantSettings } =
    useCrmStore();
  const [builderOpen, setBuilderOpen] = useState(false);

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
              {activeCount} of {automationFlows.length} flows active across WhatsApp and Instagram
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
      </Box>

      <AutomationBuilderDialog open={builderOpen} onClose={() => setBuilderOpen(false)} onCreate={addAutomationFlow} />
      </RoleGuard>
    </AppLayout>
  );
}
