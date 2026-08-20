import { useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CampaignList } from "~/components/campaigns/campaign-list";
import { CampaignBuilderDialog } from "~/components/campaigns/campaign-builder-dialog";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Campaign } from "~/data/types";
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
  const { campaigns, contacts, addCampaign, apiConnections } = useCrmStore();
  const [statusFilter, setStatusFilter] = useState<Campaign["status"] | "all">("all");
  const [builderOpen, setBuilderOpen] = useState(false);

  const filteredCampaigns = campaigns.filter((c) => statusFilter === "all" || c.status === statusFilter);

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
              {campaigns.length} broadcast campaigns across WhatsApp and Instagram
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
          sx={{ mb: 2.5, minHeight: 36, "& .MuiTab-root": { minHeight: 36 } }}
        >
          {STATUS_TABS.map((tab) => (
            <Tab key={tab.value} value={tab.value} label={tab.label} />
          ))}
        </Tabs>

        <CampaignList campaigns={filteredCampaigns} />
      </Box>

      <CampaignBuilderDialog
        open={builderOpen}
        onClose={() => setBuilderOpen(false)}
        onCreate={addCampaign}
        contacts={contacts}
        apiConnections={apiConnections}
      />
      </RoleGuard>
    </AppLayout>
  );
}
