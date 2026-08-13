import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Paper from "@mui/material/Paper";
import MenuItem from "@mui/material/MenuItem";
import Alert from "@mui/material/Alert";
import Typography from "@mui/material/Typography";
import Divider from "@mui/material/Divider";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { QualificationFieldType, VoiceAgent, VoiceAgentQualificationCriterion } from "~/data/types";

interface VoiceAgentQualificationPanelProps {
  voiceAgent: VoiceAgent;
  onUpdated: (voiceAgent: VoiceAgent) => void;
}

const FIELD_TYPES: QualificationFieldType[] = ["text", "number", "boolean", "choice"];

function emptyRow(): VoiceAgentQualificationCriterion {
  return { key: "", label: "", question: "", type: "text" };
}

export function VoiceAgentQualificationPanel({ voiceAgent, onUpdated }: VoiceAgentQualificationPanelProps) {
  const [rows, setRows] = useState<VoiceAgentQualificationCriterion[]>(
    voiceAgent.qualificationCriteria.length > 0 ? voiceAgent.qualificationCriteria : [],
  );
  const [passCriteria, setPassCriteria] = useState(voiceAgent.qualificationPassCriteria ?? "");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function updateRow(index: number, patch: Partial<VoiceAgentQualificationCriterion>) {
    setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));
  }

  function addRow() {
    setRows((prev) => [...prev, emptyRow()]);
  }

  function removeRow(index: number) {
    setRows((prev) => prev.filter((_, i) => i !== index));
  }

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const cleanedRows = rows
        .map((row) => ({
          key: row.key.trim(),
          label: row.label.trim(),
          question: row.question.trim(),
          type: row.type,
        }))
        .filter((row) => row.key && row.label && row.question);

      const updated = await apiClient.updateVoiceAgent(voiceAgent.id, {
        qualificationCriteria: cleanedRows,
        qualificationPassCriteria: passCriteria.trim() || null,
      });
      setRows(updated.qualificationCriteria);
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save qualification criteria.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <Typography variant="caption" sx={{ color: "text.secondary" }}>
        Define the questions the voice agent asks to qualify a lead during a call.
      </Typography>

      <Stack spacing={1.5}>
        {rows.map((row, index) => (
          <Paper key={index} variant="outlined" sx={{ borderRadius: 2, p: 1.75 }}>
            <Stack spacing={1.25}>
              <Stack direction="row" sx={{ justifyContent: "flex-end" }}>
                <IconButton size="small" onClick={() => removeRow(index)}>
                  <DeleteRoundedIcon fontSize="small" />
                </IconButton>
              </Stack>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={1.25}>
                <TextField
                  fullWidth
                  size="small"
                  label="Key"
                  value={row.key}
                  onChange={(e) => updateRow(index, { key: e.target.value })}
                />
                <TextField
                  fullWidth
                  size="small"
                  label="Label"
                  value={row.label}
                  onChange={(e) => updateRow(index, { label: e.target.value })}
                />
                <TextField
                  fullWidth
                  select
                  size="small"
                  label="Type"
                  value={row.type}
                  onChange={(e) => updateRow(index, { type: e.target.value })}
                  sx={{ minWidth: 130 }}
                >
                  {FIELD_TYPES.map((t) => (
                    <MenuItem key={t} value={t}>
                      {t}
                    </MenuItem>
                  ))}
                </TextField>
              </Stack>
              <TextField
                fullWidth
                size="small"
                label="Question to ask"
                value={row.question}
                onChange={(e) => updateRow(index, { question: e.target.value })}
              />
            </Stack>
          </Paper>
        ))}
        {rows.length === 0 && (
          <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 2 }}>
            No qualification criteria yet.
          </Typography>
        )}
      </Stack>

      <Button startIcon={<AddRoundedIcon />} onClick={addRow} sx={{ alignSelf: "flex-start" }}>
        Add criterion
      </Button>

      <Divider />

      <TextField
        fullWidth
        multiline
        minRows={2}
        label="Qualification pass criteria"
        value={passCriteria}
        onChange={(e) => setPassCriteria(e.target.value)}
        helperText="Describe what makes a lead 'qualified' based on the answers collected."
      />

      <Button variant="contained" onClick={handleSave} disabled={saving} sx={{ alignSelf: "flex-start" }}>
        Save criteria
      </Button>
    </Stack>
  );
}
