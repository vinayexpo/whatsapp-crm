import { useMemo, useState } from "react";
import { useSearchParams } from "react-router";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import InputAdornment from "@mui/material/InputAdornment";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import Table from "@mui/material/Table";
import TableHead from "@mui/material/TableHead";
import TableBody from "@mui/material/TableBody";
import TableRow from "@mui/material/TableRow";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import Avatar from "@mui/material/Avatar";
import Chip from "@mui/material/Chip";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { ContactDetailDrawer } from "~/components/contacts/contact-detail-drawer";
import { PIPELINE_STAGES } from "~/data/pipeline-stages";
import { useCrmStore } from "~/hooks/use-crm-store";
import { formatCurrency, formatRelativeTime } from "~/utils/format";
import type { ChannelType, PipelineStageId } from "~/data/types";
import type { Route } from "./+types/contacts";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Contacts — Creative Connects" },
    { name: "description", content: "Search, filter, and manage your complete customer database." },
  ];
}

export default function Contacts() {
  const { contacts } = useCrmStore();
  const [searchParams, setSearchParams] = useSearchParams();
  const [search, setSearch] = useState("");
  const [channelFilter, setChannelFilter] = useState<ChannelType | "all">("all");
  const [stageFilter, setStageFilter] = useState<PipelineStageId | "all">("all");

  const selectedContactId = searchParams.get("contactId");
  const selectedContact = contacts.find((c) => c.id === selectedContactId) ?? null;

  const filteredContacts = useMemo(() => {
    return contacts.filter((contact) => {
      const matchesSearch =
        search.trim().length === 0 ||
        contact.name.toLowerCase().includes(search.toLowerCase()) ||
        contact.handle.toLowerCase().includes(search.toLowerCase()) ||
        contact.tags.some((tag) => tag.toLowerCase().includes(search.toLowerCase()));
      const matchesChannel = channelFilter === "all" || contact.channel === channelFilter;
      const matchesStage = stageFilter === "all" || contact.pipelineStage === stageFilter;
      return matchesSearch && matchesChannel && matchesStage;
    });
  }, [contacts, search, channelFilter, stageFilter]);

  return (
    <AppLayout>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0, overflowY: "auto" }}>
        <Stack sx={{ mb: 3 }}>
          <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
            Contacts
          </Typography>
          <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
            {contacts.length} customer profiles across WhatsApp and Instagram
          </Typography>
        </Stack>

        <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2.5 }}>
          <TextField
            placeholder="Search by name, handle, or tag…"
            size="small"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            sx={{ flex: 1, maxWidth: { sm: 340 } }}
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
          <Select size="small" value={channelFilter} onChange={(e) => setChannelFilter(e.target.value as ChannelType | "all")} sx={{ minWidth: 160 }}>
            <MenuItem value="all">All channels</MenuItem>
            <MenuItem value="whatsapp">WhatsApp</MenuItem>
            <MenuItem value="instagram">Instagram</MenuItem>
            <MenuItem value="website">Website</MenuItem>
          </Select>
          <Select size="small" value={stageFilter} onChange={(e) => setStageFilter(e.target.value as PipelineStageId | "all")} sx={{ minWidth: 160 }}>
            <MenuItem value="all">All stages</MenuItem>
            {PIPELINE_STAGES.map((stage) => (
              <MenuItem key={stage.id} value={stage.id}>
                {stage.name}
              </MenuItem>
            ))}
          </Select>
        </Stack>

        <Paper variant="outlined" sx={{ borderRadius: 3, overflow: "hidden" }}>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Contact</TableCell>
                  <TableCell>Channel</TableCell>
                  <TableCell>Tags</TableCell>
                  <TableCell>Stage</TableCell>
                  <TableCell align="right">Deal Value</TableCell>
                  <TableCell align="right">Last Interaction</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {filteredContacts.map((contact) => {
                  const stage = PIPELINE_STAGES.find((s) => s.id === contact.pipelineStage);
                  return (
                    <TableRow
                      key={contact.id}
                      hover
                      sx={{ cursor: "pointer" }}
                      onClick={() => setSearchParams({ contactId: contact.id })}
                    >
                      <TableCell>
                        <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
                          <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 36, height: 36 }} />
                          <Box>
                            <Typography variant="body2" sx={{ fontWeight: 700 }}>
                              {contact.name}
                            </Typography>
                            <Typography variant="caption" sx={{ color: "text.secondary" }}>
                              {contact.handle}
                            </Typography>
                          </Box>
                        </Stack>
                      </TableCell>
                      <TableCell>
                        <ChannelIcon channel={contact.channel} size={22} />
                      </TableCell>
                      <TableCell>
                        <Stack direction="row" sx={{ gap: 0.5, flexWrap: "wrap", maxWidth: 220 }}>
                          {contact.tags.map((tag) => (
                            <Chip key={tag} label={tag} size="small" />
                          ))}
                        </Stack>
                      </TableCell>
                      <TableCell>
                        {stage && (
                          <Chip
                            label={stage.name}
                            size="small"
                            sx={{ bgcolor: `${stage.color}22`, color: stage.color, fontWeight: 700 }}
                          />
                        )}
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                          {formatCurrency(contact.dealValue)}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="caption" sx={{ color: "text.secondary" }}>
                          {formatRelativeTime(contact.lastInteractionAt)}
                        </Typography>
                      </TableCell>
                    </TableRow>
                  );
                })}
                {filteredContacts.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} sx={{ textAlign: "center", py: 4 }}>
                      <Typography variant="body2" sx={{ color: "text.secondary" }}>
                        No contacts match your filters.
                      </Typography>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      </Box>

      <ContactDetailDrawer contact={selectedContact} onClose={() => setSearchParams({})} />
    </AppLayout>
  );
}
