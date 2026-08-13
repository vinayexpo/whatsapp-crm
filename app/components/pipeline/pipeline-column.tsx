import { Droppable } from "@hello-pangea/dnd";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import { LeadCard } from "./lead-card";
import type { Contact, PipelineStage } from "~/data/types";
import { formatCurrency } from "~/utils/format";
import styles from "./pipeline-column.module.css";

interface PipelineColumnProps {
  stage: PipelineStage;
  contacts: Contact[];
  onCardClick: (contact: Contact) => void;
}

export function PipelineColumn({ stage, contacts, onCardClick }: PipelineColumnProps) {
  const totalValue = contacts.reduce((sum, c) => sum + c.dealValue, 0);

  return (
    <Box className={styles.column}>
      <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", mb: 1.5 }}>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <Box sx={{ width: 8, height: 8, borderRadius: "50%", bgcolor: stage.color }} />
          <Typography variant="body2" sx={{ fontWeight: 700 }}>
            {stage.name}
          </Typography>
          <Chip label={contacts.length} size="small" sx={{ height: 20, fontSize: "0.7rem" }} />
        </Stack>
      </Stack>
      <Typography variant="caption" sx={{ color: "text.secondary", mb: 1.5, display: "block" }}>
        {formatCurrency(totalValue)} total value
      </Typography>
      <Droppable droppableId={stage.id}>
        {(provided, snapshot) => (
          <Box
            ref={provided.innerRef}
            {...provided.droppableProps}
            className={styles.dropZone}
            sx={{ bgcolor: snapshot.isDraggingOver ? "rgba(0, 168, 132, 0.08)" : "transparent" }}
          >
            {contacts.map((contact, index) => (
              <LeadCard key={contact.id} contact={contact} index={index} onClick={() => onCardClick(contact)} />
            ))}
            {provided.placeholder}
            {contacts.length === 0 && (
              <Typography variant="caption" sx={{ color: "text.secondary", textAlign: "center", display: "block", mt: 2 }}>
                No leads in this stage.
              </Typography>
            )}
          </Box>
        )}
      </Droppable>
    </Box>
  );
}
