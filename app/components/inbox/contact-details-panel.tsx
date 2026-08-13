import { useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Chip from "@mui/material/Chip";
import Divider from "@mui/material/Divider";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import IconButton from "@mui/material/IconButton";
import PhoneRoundedIcon from "@mui/icons-material/PhoneRounded";
import EmailRoundedIcon from "@mui/icons-material/EmailRounded";
import PlaceRoundedIcon from "@mui/icons-material/PlaceRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { Link } from "react-router";
import { PIPELINE_STAGES } from "~/data/pipeline-stages";
import type { Contact, PipelineStageId } from "~/data/types";

interface ContactDetailsPanelProps {
  contact: Contact;
  onStageChange: (stage: PipelineStageId) => void;
  onAddTag: (tag: string) => void;
}

export function ContactDetailsPanel({ contact, onStageChange, onAddTag }: ContactDetailsPanelProps) {
  const [newTag, setNewTag] = useState("");

  return (
    <Box sx={{ p: 2.5, height: "100%", overflowY: "auto" }}>
      <Stack sx={{ alignItems: "center", textAlign: "center", mb: 2.5 }}>
        <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 72, height: 72, mb: 1.5 }} />
        <Typography variant="h6" sx={{ fontSize: "1.05rem" }}>
          {contact.name}
        </Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          {contact.handle}
        </Typography>
      </Stack>

      <Stack spacing={1.25} sx={{ mb: 2.5 }}>
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
      </Stack>

      <Divider sx={{ mb: 2.5 }} />

      <Typography variant="caption" sx={{ fontWeight: 700, color: "text.secondary", textTransform: "uppercase", letterSpacing: "0.05em" }}>
        Pipeline Stage
      </Typography>
      <Select
        fullWidth
        size="small"
        value={contact.pipelineStage}
        onChange={(e) => onStageChange(e.target.value as PipelineStageId)}
        sx={{ mt: 1, mb: 2.5 }}
      >
        {PIPELINE_STAGES.map((stage) => (
          <MenuItem key={stage.id} value={stage.id}>
            {stage.name}
          </MenuItem>
        ))}
      </Select>

      <Typography variant="caption" sx={{ fontWeight: 700, color: "text.secondary", textTransform: "uppercase", letterSpacing: "0.05em" }}>
        Tags
      </Typography>
      <Stack direction="row" sx={{ flexWrap: "wrap", gap: 0.75, mt: 1, mb: 1.25 }}>
        {contact.tags.map((tag) => (
          <Chip key={tag} label={tag} size="small" />
        ))}
      </Stack>
      <Stack direction="row" spacing={1} sx={{ mb: 2.5 }}>
        <TextField
          size="small"
          placeholder="Add tag"
          value={newTag}
          onChange={(e) => setNewTag(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" && newTag.trim()) {
              onAddTag(newTag.trim());
              setNewTag("");
            }
          }}
          fullWidth
        />
        <IconButton
          size="small"
          onClick={() => {
            if (newTag.trim()) {
              onAddTag(newTag.trim());
              setNewTag("");
            }
          }}
        >
          <AddRoundedIcon fontSize="small" />
        </IconButton>
      </Stack>

      <Divider sx={{ mb: 2.5 }} />

      <Typography variant="caption" sx={{ fontWeight: 700, color: "text.secondary", textTransform: "uppercase", letterSpacing: "0.05em" }}>
        Notes
      </Typography>
      <Stack spacing={1} sx={{ mt: 1, mb: 2.5 }}>
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

      <Link to={`/contacts?contactId=${contact.id}`} style={{ display: "block" }}>
        <Typography variant="body2" sx={{ color: "primary.main", fontWeight: 700, textAlign: "center" }}>
          View full contact profile
        </Typography>
      </Link>
    </Box>
  );
}
