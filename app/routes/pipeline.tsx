import { useEffect, useState } from "react";
import { DragDropContext, type DropResult } from "@hello-pangea/dnd";
import Box from "@mui/material/Box";
import CircularProgress from "@mui/material/CircularProgress";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { AppLayout } from "~/components/app-layout/app-layout";
import { PipelineColumn } from "~/components/pipeline/pipeline-column";
import { ContactDetailDrawer } from "~/components/contacts/contact-detail-drawer";
import { PIPELINE_STAGES } from "~/data/pipeline-stages";
import { useCrmStore } from "~/hooks/use-crm-store";
import { apiClient } from "~/utils/api-client";
import type { Contact, PipelineStageId } from "~/data/types";
import type { Route } from "./+types/pipeline";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Pipeline — Creative Connects" },
    { name: "description", content: "Track lead progression through your sales pipeline with a Kanban board." },
  ];
}

export default function Pipeline() {
  const { currentUser } = useCrmStore();
  const [pipelineContacts, setPipelineContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedContact, setSelectedContact] = useState<Contact | null>(null);

  useEffect(() => {
    if (!currentUser) return;
    let cancelled = false;
    setLoading(true);

    async function loadAllContacts() {
      const perPage = 100;
      const first = await apiClient.listContacts({ page: 1, perPage });
      if (cancelled) return;
      const remainingPages = Array.from(
        { length: Math.max(0, first.meta.lastPage - 1) },
        (_, i) => i + 2,
      );
      const rest = await Promise.all(remainingPages.map((page) => apiClient.listContacts({ page, perPage })));
      if (cancelled) return;
      const all = first.data.concat(...rest.map((r) => r.data));
      setPipelineContacts(all);
      setLoading(false);
    }

    loadAllContacts().catch(() => {
      if (!cancelled) setLoading(false);
    });

    return () => {
      cancelled = true;
    };
  }, [currentUser]);

  function handleDragEnd(result: DropResult) {
    const { destination, draggableId } = result;
    if (!destination) return;
    const stage = destination.droppableId as PipelineStageId;
    const previous = pipelineContacts.find((c) => c.id === draggableId);
    if (!previous) return;

    setPipelineContacts((prev) => prev.map((c) => (c.id === draggableId ? { ...c, pipelineStage: stage } : c)));
    apiClient.updateContactPipelineStage(draggableId, stage, previous.updatedAt).then(
      (updated) => {
        setPipelineContacts((prev) => prev.map((c) => (c.id === draggableId ? updated : c)));
      },
      () => {
        setPipelineContacts((prev) => prev.map((c) => (c.id === draggableId ? previous : c)));
      },
    );
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

        {loading ? (
          <Box sx={{ m: "auto", display: "flex", flexDirection: "column", alignItems: "center", gap: 2, py: 8 }}>
            <CircularProgress size={28} />
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              Loading contacts…
            </Typography>
          </Box>
        ) : (
          <DragDropContext onDragEnd={handleDragEnd}>
            <Stack direction="row" spacing={2} sx={{ flex: 1, overflowX: "auto", pb: 2, alignItems: "flex-start" }}>
              {PIPELINE_STAGES.map((stage) => (
                <PipelineColumn
                  key={stage.id}
                  stage={stage}
                  contacts={pipelineContacts.filter((c) => c.pipelineStage === stage.id)}
                  onCardClick={setSelectedContact}
                />
              ))}
            </Stack>
          </DragDropContext>
        )}
      </Box>

      <ContactDetailDrawer contact={selectedContact} onClose={() => setSelectedContact(null)} />
    </AppLayout>
  );
}
