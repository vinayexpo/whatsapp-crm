import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import StorefrontRoundedIcon from "@mui/icons-material/StorefrontRounded";
import Divider from "@mui/material/Divider";
import type { Branch } from "~/data/types";
import { BranchSettingsPanel } from "./branch-settings-panel";
import { DeliveryZonesPanel } from "./delivery-zones-panel";

interface BranchDetailDrawerProps {
  branch: Branch | null;
  onClose: () => void;
  onUpdated: (branch: Branch) => void;
  onDeleted: (branchId: string) => void;
  onDelete: () => Promise<void>;
}

export function BranchDetailDrawer({ branch, onClose, onUpdated, onDelete }: BranchDetailDrawerProps) {
  return (
    <Drawer anchor="right" open={Boolean(branch)} onClose={onClose}>
      {branch && (
        <Box sx={{ width: { xs: 340, sm: 440 }, height: "100%", display: "flex", flexDirection: "column" }}>
          <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", p: 3, pb: 2 }}>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1.25 }}>
              <Box
                sx={{
                  width: 36,
                  height: 36,
                  borderRadius: 2,
                  bgcolor: "rgba(91, 110, 245, 0.12)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <StorefrontRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
              </Box>
              <Typography variant="h6" sx={{ fontSize: "1.1rem" }}>
                {branch.name}
              </Typography>
            </Stack>
            <IconButton onClick={onClose} size="small">
              <CloseRoundedIcon fontSize="small" />
            </IconButton>
          </Stack>

          <Box sx={{ flex: 1, overflowY: "auto", p: 3 }}>
            <BranchSettingsPanel branch={branch} onUpdated={onUpdated} onDelete={onDelete} />
            <Divider sx={{ my: 3 }} />
            <DeliveryZonesPanel branch={branch} />
          </Box>
        </Box>
      )}
    </Drawer>
  );
}
