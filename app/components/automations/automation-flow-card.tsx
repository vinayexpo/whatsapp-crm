import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Switch from "@mui/material/Switch";
import Chip from "@mui/material/Chip";
import Box from "@mui/material/Box";
import IconButton from "@mui/material/IconButton";
import Divider from "@mui/material/Divider";
import BoltRoundedIcon from "@mui/icons-material/BoltRounded";
import ArrowForwardRoundedIcon from "@mui/icons-material/ArrowForwardRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import AutoAwesomeRoundedIcon from "@mui/icons-material/AutoAwesomeRounded";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { ACTION_LABELS, TRIGGER_LABELS } from "./automation-labels";
import type { AutomationFlow } from "~/data/types";
import { formatDate } from "~/utils/format";

const STATUS_COLOR: Record<AutomationFlow["status"], "success" | "default" | "warning"> = {
  active: "success",
  paused: "default",
  draft: "warning",
};

interface AutomationFlowCardProps {
  flow: AutomationFlow;
  onToggle: (checked: boolean) => void;
  onDelete: () => void;
}

export function AutomationFlowCard({ flow, onToggle, onDelete }: AutomationFlowCardProps) {
  return (
    <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%", display: "flex", flexDirection: "column" }}>
      <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between", gap: 1 }}>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <Box
            sx={{
              width: 34,
              height: 34,
              borderRadius: 2,
              bgcolor: "rgba(124, 77, 255, 0.12)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              flexShrink: 0,
            }}
          >
            <BoltRoundedIcon sx={{ color: "secondary.main", fontSize: 18 }} />
          </Box>
          <Typography variant="subtitle1" sx={{ fontWeight: 700, fontSize: "0.98rem" }}>
            {flow.name}
          </Typography>
        </Stack>
        <Stack direction="row" sx={{ alignItems: "center", gap: 0.5 }}>
          <Switch
            checked={flow.status === "active"}
            onChange={(e) => onToggle(e.target.checked)}
            size="small"
            color="success"
          />
          <IconButton size="small" onClick={onDelete} aria-label="Delete automation">
            <DeleteOutlineRoundedIcon fontSize="small" />
          </IconButton>
        </Stack>
      </Stack>

      <Typography variant="body2" sx={{ color: "text.secondary", mt: 1, mb: 2 }}>
        {flow.description}
      </Typography>

      <Stack direction="row" sx={{ alignItems: "center", gap: 0.75, flexWrap: "wrap", mb: 1.5 }}>
        <Chip label={flow.status} size="small" color={STATUS_COLOR[flow.status]} sx={{ textTransform: "capitalize" }} />
        {flow.trigger.channel === "both" ? (
          <Stack direction="row" sx={{ gap: 0.5 }}>
            <ChannelIcon channel="whatsapp" size={18} />
            <ChannelIcon channel="instagram" size={18} />
          </Stack>
        ) : (
          <ChannelIcon channel={flow.trigger.channel} size={18} />
        )}
      </Stack>

      <Divider sx={{ mb: 1.5 }} />

      <Stack direction="row" sx={{ alignItems: "center", gap: 1, flexWrap: "wrap" }}>
        <Chip
          size="small"
          variant="outlined"
          label={
            flow.trigger.type === "keyword" && flow.trigger.keyword
              ? `${TRIGGER_LABELS[flow.trigger.type]}: "${flow.trigger.keyword}"`
              : TRIGGER_LABELS[flow.trigger.type]
          }
        />
        <ArrowForwardRoundedIcon sx={{ fontSize: 16, color: "text.secondary" }} />
        <Stack direction="row" sx={{ gap: 0.75, flexWrap: "wrap" }}>
          {flow.actions.map((action) => (
            <Chip
              key={action.id}
              size="small"
              color="secondary"
              variant="outlined"
              icon={action.type === "ai-reply" ? <AutoAwesomeRoundedIcon /> : undefined}
              label={ACTION_LABELS[action.type]}
            />
          ))}
        </Stack>
      </Stack>

      <Box sx={{ flex: 1 }} />

      <Stack direction="row" sx={{ justifyContent: "space-between", mt: 2 }}>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Triggered {(flow.triggeredCount ?? 0).toLocaleString()} times
        </Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          {flow.lastTriggeredAt ? `Last: ${formatDate(flow.lastTriggeredAt)}` : "Never triggered"}
        </Typography>
      </Stack>
    </Paper>
  );
}
