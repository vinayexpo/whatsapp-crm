import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Chip from "@mui/material/Chip";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import type { CampaignPerformer } from "~/data/types";

export function CampaignPerformanceTable({ title, campaigns }: { title: string; campaigns: CampaignPerformer[] }) {
  return (
    <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3, height: "100%" }}>
      <Stack sx={{ mb: 2 }}>
        <Typography variant="h6" sx={{ fontSize: "1.05rem" }}>
          {title}
        </Typography>
      </Stack>
      {campaigns.length === 0 ? (
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          No campaigns with recipients in this range yet.
        </Typography>
      ) : (
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Campaign</TableCell>
              <TableCell align="right">Delivery</TableCell>
              <TableCell align="right">Read</TableCell>
              <TableCell align="right">Reply</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {campaigns.map((campaign) => (
              <TableRow key={campaign.id}>
                <TableCell>
                  <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                    {campaign.channel !== "both" && <ChannelIcon channel={campaign.channel} size={16} />}
                    <Typography variant="body2" sx={{ fontWeight: 500 }}>
                      {campaign.name}
                    </Typography>
                  </Stack>
                </TableCell>
                <TableCell align="right">
                  <Chip size="small" label={`${campaign.deliveryRate}%`} sx={{ bgcolor: "#00A88420", color: "#00734F" }} />
                </TableCell>
                <TableCell align="right">
                  <Chip size="small" label={`${campaign.readRate}%`} sx={{ bgcolor: "#3B82C420", color: "#255A87" }} />
                </TableCell>
                <TableCell align="right">
                  <Chip size="small" label={`${campaign.replyRate}%`} sx={{ bgcolor: "#F2A93B20", color: "#8A5E14" }} />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </Paper>
  );
}
