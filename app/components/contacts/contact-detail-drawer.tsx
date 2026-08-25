import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Chip from "@mui/material/Chip";
import Divider from "@mui/material/Divider";
import IconButton from "@mui/material/IconButton";
import Button from "@mui/material/Button";
import TextField from "@mui/material/TextField";
import InputAdornment from "@mui/material/InputAdornment";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import PhoneRoundedIcon from "@mui/icons-material/PhoneRounded";
import EmailRoundedIcon from "@mui/icons-material/EmailRounded";
import PlaceRoundedIcon from "@mui/icons-material/PlaceRounded";
import ChatBubbleOutlineRoundedIcon from "@mui/icons-material/ChatBubbleOutlineRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import { Link } from "react-router";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { PIPELINE_STAGES } from "~/data/pipeline-stages";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Contact } from "~/data/types";
import { formatCurrency, formatDate } from "~/utils/format";

interface ContactDetailDrawerProps {
  contact: Contact | null;
  onClose: () => void;
}

export function ContactDetailDrawer({ contact, onClose }: ContactDetailDrawerProps) {
  const { updateContact } = useCrmStore();
  const stage = contact ? PIPELINE_STAGES.find((s) => s.id === contact.pipelineStage) : null;

  const [isEditing, setIsEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ phone: "", email: "", location: "", dealValue: "0" });

  useEffect(() => {
    setIsEditing(false);
    if (contact) {
      setForm({
        phone: contact.phone ?? "",
        email: contact.email ?? "",
        location: contact.location ?? "",
        dealValue: String(contact.dealValue),
      });
    }
  }, [contact?.id]);

  const handleSave = () => {
    if (!contact) return;
    setSaving(true);
    updateContact(contact.id, {
      phone: form.phone || undefined,
      email: form.email || undefined,
      location: form.location || undefined,
      dealValue: Math.max(0, Math.round(Number(form.dealValue) || 0)),
    }).finally(() => {
      setSaving(false);
      setIsEditing(false);
    });
  };

  return (
    <Drawer anchor="right" open={Boolean(contact)} onClose={onClose}>
      {contact && (
        <Box sx={{ width: { xs: 320, sm: 400 }, p: 3, height: "100%", overflowY: "auto" }}>
          <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "center" }}>
            {!isEditing ? (
              <IconButton onClick={() => setIsEditing(true)} size="small" aria-label="Edit contact">
                <EditRoundedIcon fontSize="small" />
              </IconButton>
            ) : (
              <Box />
            )}
            <IconButton onClick={onClose} size="small">
              <CloseRoundedIcon fontSize="small" />
            </IconButton>
          </Stack>

          <Stack sx={{ alignItems: "center", textAlign: "center", mb: 3 }}>
            <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 84, height: 84, mb: 1.5 }} />
            <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }}>
              <Typography variant="h6">{contact.name}</Typography>
              <ChannelIcon channel={contact.channel} size={18} />
            </Stack>
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              {contact.handle}
            </Typography>
            {stage && (
              <Chip
                label={stage.name}
                size="small"
                sx={{ mt: 1.5, bgcolor: `${stage.color}22`, color: stage.color, fontWeight: 700 }}
              />
            )}
          </Stack>

          <Button
            component={Link}
            to={`/inbox?contactId=${contact.id}`}
            fullWidth
            variant="contained"
            startIcon={<ChatBubbleOutlineRoundedIcon />}
            sx={{ mb: 3 }}
          >
            Open Conversation
          </Button>

          {isEditing ? (
            <Stack spacing={1.5} sx={{ mb: 3 }}>
              <TextField
                label="Phone"
                size="small"
                fullWidth
                value={form.phone}
                onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                slotProps={{
                  input: {
                    startAdornment: (
                      <InputAdornment position="start">
                        <PhoneRoundedIcon fontSize="small" />
                      </InputAdornment>
                    ),
                  },
                }}
              />
              <TextField
                label="Email"
                size="small"
                fullWidth
                value={form.email}
                onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                slotProps={{
                  input: {
                    startAdornment: (
                      <InputAdornment position="start">
                        <EmailRoundedIcon fontSize="small" />
                      </InputAdornment>
                    ),
                  },
                }}
              />
              <TextField
                label="Location"
                size="small"
                fullWidth
                value={form.location}
                onChange={(e) => setForm((f) => ({ ...f, location: e.target.value }))}
                slotProps={{
                  input: {
                    startAdornment: (
                      <InputAdornment position="start">
                        <PlaceRoundedIcon fontSize="small" />
                      </InputAdornment>
                    ),
                  },
                }}
              />
              <TextField
                label="Deal Value"
                size="small"
                fullWidth
                type="number"
                value={form.dealValue}
                onChange={(e) => setForm((f) => ({ ...f, dealValue: e.target.value }))}
                slotProps={{
                  input: {
                    startAdornment: <InputAdornment position="start">₹</InputAdornment>,
                  },
                }}
              />
              <Stack direction="row" spacing={1}>
                <Button variant="contained" size="small" onClick={handleSave} disabled={saving} fullWidth>
                  {saving ? "Saving…" : "Save"}
                </Button>
                <Button variant="outlined" size="small" onClick={() => setIsEditing(false)} disabled={saving} fullWidth>
                  Cancel
                </Button>
              </Stack>
            </Stack>
          ) : (
            <Stack spacing={1.25} sx={{ mb: 3 }}>
              {contact.phone && (
                <Stack direction="row" sx={{ gap: 1, alignItems: "center" }}>
                  <PhoneRoundedIcon fontSize="small" sx={{ color: "text.secondary" }} />
                  <Typography variant="body2">{contact.phone}</Typography>
                </Stack>
              )}
              {contact.email && (
                <Stack direction="row" sx={{ gap: 1, alignItems: "center" }}>
                  <EmailRoundedIcon fontSize="small" sx={{ color: "text.secondary" }} />
                  <Typography variant="body2" sx={{ wordBreak: "break-all" }}>
                    {contact.email}
                  </Typography>
                </Stack>
              )}
              {contact.location && (
                <Stack direction="row" sx={{ gap: 1, alignItems: "center" }}>
                  <PlaceRoundedIcon fontSize="small" sx={{ color: "text.secondary" }} />
                  <Typography variant="body2">{contact.location}</Typography>
                </Stack>
              )}
              <Stack direction="row" sx={{ gap: 1, alignItems: "center" }}>
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  Deal Value
                </Typography>
                <Typography variant="body2" sx={{ fontWeight: 700 }}>
                  {formatCurrency(contact.dealValue)}
                </Typography>
              </Stack>
            </Stack>
          )}

          <Typography variant="caption" sx={{ fontWeight: 700, color: "text.secondary", textTransform: "uppercase", letterSpacing: "0.05em" }}>
            Tags
          </Typography>
          <Stack direction="row" sx={{ flexWrap: "wrap", gap: 0.75, mt: 1, mb: 3 }}>
            {contact.tags.map((tag) => (
              <Chip key={tag} label={tag} size="small" />
            ))}
          </Stack>

          <Divider sx={{ mb: 3 }} />

          <Typography variant="caption" sx={{ fontWeight: 700, color: "text.secondary", textTransform: "uppercase", letterSpacing: "0.05em" }}>
            Purchase History
          </Typography>
          <Stack spacing={1} sx={{ mt: 1, mb: 3 }}>
            {contact.purchases.length === 0 && (
              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                No purchases recorded yet.
              </Typography>
            )}
            {contact.purchases.map((purchase) => (
              <Stack key={purchase.id} direction="row" sx={{ justifyContent: "space-between", bgcolor: "action.hover", borderRadius: 1.5, p: 1 }}>
                <Box>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {purchase.item}
                  </Typography>
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    {formatDate(purchase.date)}
                  </Typography>
                </Box>
                <Typography variant="body2" sx={{ fontWeight: 700 }}>
                  {formatCurrency(purchase.amount)}
                </Typography>
              </Stack>
            ))}
          </Stack>

          <Divider sx={{ mb: 3 }} />

          <Typography variant="caption" sx={{ fontWeight: 700, color: "text.secondary", textTransform: "uppercase", letterSpacing: "0.05em" }}>
            Notes
          </Typography>
          <Stack spacing={1} sx={{ mt: 1 }}>
            {contact.notes.length === 0 && (
              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                No notes yet.
              </Typography>
            )}
            {contact.notes.map((note, idx) => (
              <Box key={idx} sx={{ bgcolor: "action.hover", borderRadius: 1.5, p: 1 }}>
                <Typography variant="body2">{note}</Typography>
              </Box>
            ))}
          </Stack>
        </Box>
      )}
    </Drawer>
  );
}
