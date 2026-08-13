import type { PipelineStage } from "./types";

export const PIPELINE_STAGES: PipelineStage[] = [
  { id: "new-lead", name: "New Lead", color: "#3B82C4" },
  { id: "contacted", name: "Contacted", color: "#7C4DFF" },
  { id: "qualified", name: "Qualified", color: "#F2A93B" },
  { id: "negotiation", name: "Negotiation", color: "#EC6F56" },
  { id: "won", name: "Won", color: "#2FB673" },
  { id: "lost", name: "Lost", color: "#9AA7A4" },
];
