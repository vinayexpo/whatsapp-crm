import { useEffect, useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemText from "@mui/material/ListItemText";
import Typography from "@mui/material/Typography";
import CircularProgress from "@mui/material/CircularProgress";
import Box from "@mui/material/Box";
import { apiClient } from "~/utils/api-client";
import type { CampaignRecipient } from "~/data/types";

export function CampaignFailureDialog({
  campaignId,
  campaignName,
  open,
  onClose,
}: {
  campaignId: string | null;
  campaignName: string;
  open: boolean;
  onClose: () => void;
}) {
  const [recipients, setRecipients] = useState<CampaignRecipient[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!open || !campaignId) return;
    setLoading(true);
    apiClient
      .listCampaignRecipients(campaignId)
      .then((data) => setRecipients(data.filter((r) => r.status === "failed")))
      .finally(() => setLoading(false));
  }, [open, campaignId]);

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>Failed recipients — {campaignName}</DialogTitle>
      <DialogContent>
        {loading ? (
          <Box sx={{ display: "flex", justifyContent: "center", py: 4 }}>
            <CircularProgress size={24} />
          </Box>
        ) : recipients.length === 0 ? (
          <Typography variant="body2" sx={{ color: "text.secondary", py: 2 }}>
            No failed recipients found.
          </Typography>
        ) : (
          <List dense>
            {recipients.map((recipient) => (
              <ListItem key={recipient.id} divider>
                <ListItemText
                  primary={recipient.contactName ?? recipient.contactHandle ?? "Unknown contact"}
                  secondary={recipient.failureReason ?? "No reason recorded."}
                />
              </ListItem>
            ))}
          </List>
        )}
      </DialogContent>
    </Dialog>
  );
}
