import { Draggable } from "@hello-pangea/dnd";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Avatar from "@mui/material/Avatar";
import Chip from "@mui/material/Chip";
import Box from "@mui/material/Box";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { formatCurrency, formatRelativeTime } from "~/utils/format";
import type { Contact } from "~/data/types";

interface LeadCardProps {
  contact: Contact;
  index: number;
  onClick: () => void;
}

export function LeadCard({ contact, index, onClick }: LeadCardProps) {
  return (
    <Draggable draggableId={contact.id} index={index}>
      {(provided, snapshot) => (
        <Paper
          ref={provided.innerRef}
          {...provided.draggableProps}
          {...provided.dragHandleProps}
          onClick={onClick}
          variant="outlined"
          sx={{
            p: 1.5,
            mb: 1.25,
            borderRadius: 2.5,
            cursor: "pointer",
            bgcolor: snapshot.isDragging ? "background.paper" : "background.paper",
            boxShadow: snapshot.isDragging ? 4 : 0,
            ...provided.draggableProps.style,
          }}
        >
          <Stack direction="row" sx={{ alignItems: "center", gap: 1, mb: 1 }}>
            <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 32, height: 32 }} />
            <Box sx={{ flex: 1, minWidth: 0 }}>
              <Typography variant="body2" sx={{ fontWeight: 700 }} noWrap>
                {contact.name}
              </Typography>
              <Typography variant="caption" sx={{ color: "text.secondary" }} noWrap>
                {formatRelativeTime(contact.lastInteractionAt)}
              </Typography>
            </Box>
            <ChannelIcon channel={contact.channel} size={18} />
          </Stack>
          <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
            <Stack direction="row" sx={{ gap: 0.5, flexWrap: "wrap" }}>
              {contact.tags.slice(0, 2).map((tag) => (
                <Chip key={tag} label={tag} size="small" sx={{ height: 20, fontSize: "0.68rem" }} />
              ))}
            </Stack>
            <Typography variant="caption" sx={{ fontWeight: 700 }}>
              {formatCurrency(contact.dealValue)}
            </Typography>
          </Stack>
        </Paper>
      )}
    </Draggable>
  );
}
