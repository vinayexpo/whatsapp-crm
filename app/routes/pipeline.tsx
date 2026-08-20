import { useState } from "react";
import { DragDropContext, type DropResult } from "@hello-pangea/dnd";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { AppLayout } from "~/components/app-layout/app-layout";
import { PipelineColumn } from "~/components/pipeline/pipeline-column";
import { ContactDetailDrawer } from "~/components/contacts/contact-detail-drawer";
import { PIPELINE_STAGES } from "~/data/pipeline-stages";
import { useCrmStore } from "~/hooks/use-crm-store";
import type { Contact, PipelineStageId } from "~/data/types";
import type { Route } from "./+types/pipeline";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Pipeline — Creative Connects" },
    { name: "description", content: "Track lead progression through your sales pipeline with a Kanban board." },
  ];
}

export default function Pipeline() {
  const { allContacts, moveContactToStage } = useCrmStore();
  const [selectedContact, setSelectedContact] = useState<Contact | null>(null);

  function handleDragEnd(result: DropResult) {
    const { destination, draggableId } = result;
    if (!destination) return;
    moveContactToStage(draggableId, destination.droppableId as PipelineStageId);
  }

  return (
    <AppLayout>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, display: "flex", flexDirection: "column", minHeight: 0 }}>
        <Stack sx={{ mb: 3, flexShrink: 0 }}>
          <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
            Pipeline
          </Typography>
          <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
            Drag leads across stages to track their journey to close.
          </Typography>
        </Stack>

        <DragDropContext onDragEnd={handleDragEnd}>
          <Stack direction="row" spacing={2} sx={{ flex: 1, overflowX: "auto", pb: 2, alignItems: "flex-start" }}>
            {PIPELINE_STAGES.map((stage) => (
              <PipelineColumn
                key={stage.id}
                stage={stage}
                contacts={allContacts.filter((c) => c.pipelineStage === stage.id)}
                onCardClick={setSelectedContact}
              />
            ))}
          </Stack>
        </DragDropContext>
      </Box>

      <ContactDetailDrawer contact={selectedContact} onClose={() => setSelectedContact(null)} />
    </AppLayout>
  );
}
