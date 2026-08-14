import Paper from "@mui/material/Paper";
import Table from "@mui/material/Table";
import TableHead from "@mui/material/TableHead";
import TableBody from "@mui/material/TableBody";
import TableRow from "@mui/material/TableRow";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import Box from "@mui/material/Box";
import PhoneRoundedIcon from "@mui/icons-material/PhoneRounded";
import ChatRoundedIcon from "@mui/icons-material/ChatRounded";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import type { Campaign } from "~/data/types";
import { formatDate } from "~/utils/format";

const STATUS_COLOR: Record<Campaign["status"], "success" | "info" | "default" | "warning"> = {
  active: "success",
  scheduled: "info",
  completed: "default",
  draft: "warning",
};

export function CampaignList({ campaigns }: { campaigns: Campaign[] }) {
  return (
    <Paper variant="outlined" sx={{ borderRadius: 3, overflow: "hidden" }}>
      <TableContainer>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Campaign</TableCell>
              <TableCell>Channel</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Audience</TableCell>
              <TableCell align="right">Recipients</TableCell>
              <TableCell align="right">Read Rate</TableCell>
              <TableCell align="right">Replies</TableCell>
              <TableCell align="right">Date</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {campaigns.map((campaign) => {
              const readRate =
                campaign.deliveredCount > 0 ? Math.round((campaign.readCount / campaign.deliveredCount) * 100) : 0;
              return (
                <TableRow key={campaign.id} hover>
                  <TableCell>
                    <Typography variant="body2" sx={{ fontWeight: 700 }}>
                      {campaign.name}
                    </Typography>
                    <Typography variant="caption" sx={{ color: "text.secondary" }} noWrap>
                      {campaign.voiceAgentId
                        ? campaign.voiceAgentName
                        : campaign.whatsappCallFlowId
                          ? campaign.whatsappCallFlowName
                          : campaign.message}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    {campaign.voiceAgentId ? (
                      <Chip
                        icon={<PhoneRoundedIcon sx={{ fontSize: 16 }} />}
                        label="Voice Call"
                        size="small"
                        variant="outlined"
                      />
                    ) : campaign.whatsappCallFlowId ? (
                      <Chip
                        icon={<ChatRoundedIcon sx={{ fontSize: 16 }} />}
                        label="WhatsApp Call"
                        size="small"
                        variant="outlined"
                      />
                    ) : campaign.channel === "both" ? (
                      <Stack direction="row" sx={{ gap: 0.5 }}>
                        <ChannelIcon channel="whatsapp" size={20} />
                        <ChannelIcon channel="instagram" size={20} />
                      </Stack>
                    ) : (
                      <ChannelIcon channel={campaign.channel} size={20} />
                    )}
                  </TableCell>
                  <TableCell>
                    <Stack direction="row" sx={{ gap: 0.5, flexWrap: "wrap" }}>
                      <Chip
                        label={campaign.status}
                        size="small"
                        color={STATUS_COLOR[campaign.status]}
                        sx={{ textTransform: "capitalize" }}
                      />
                      {campaign.status === "completed" && campaign.failedCount > 0 && (
                        <Chip
                          label={
                            campaign.failedCount >= campaign.recipientCount
                              ? "All failed"
                              : `${campaign.failedCount} failed`
                          }
                          size="small"
                          color="error"
                        />
                      )}
                    </Stack>
                  </TableCell>
                  <TableCell>
                    <Chip
                      label={campaign.audienceTag ?? campaign.phonebookFolderName ?? "—"}
                      size="small"
                      variant="outlined"
                    />
                  </TableCell>
                  <TableCell align="right">{campaign.recipientCount.toLocaleString()}</TableCell>
                  <TableCell align="right">
                    <Box sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}>
                      <Typography variant="body2">{readRate}%</Typography>
                    </Box>
                  </TableCell>
                  <TableCell align="right">{campaign.repliedCount.toLocaleString()}</TableCell>
                  <TableCell align="right">
                    <Typography variant="caption" sx={{ color: "text.secondary" }}>
                      {campaign.scheduledAt ? formatDate(campaign.scheduledAt) : "—"}
                    </Typography>
                  </TableCell>
                </TableRow>
              );
            })}
            {campaigns.length === 0 && (
              <TableRow>
                <TableCell colSpan={8} sx={{ textAlign: "center", py: 4 }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    No campaigns match this filter.
                  </Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}
