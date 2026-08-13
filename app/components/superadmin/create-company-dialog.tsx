import { useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Alert from "@mui/material/Alert";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Company } from "~/data/types";

interface CreateCompanyDialogProps {
  open: boolean;
  onClose: () => void;
  onCreated: (company: Company) => void;
}

export function CreateCompanyDialog({ open, onClose, onCreated }: CreateCompanyDialogProps) {
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function resetAndClose() {
    setName("");
    setError(null);
    onClose();
  }

  async function handleCreate() {
    if (!name.trim()) return;
    setError(null);
    setSubmitting(true);
    try {
      const company = await apiClient.createCompany({ name: name.trim() });
      onCreated(company);
      resetAndClose();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.errors?.name?.[0] ?? err.message);
      } else {
        setError("Something went wrong. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  const canCreate = name.trim().length > 0;

  return (
    <Dialog open={open} onClose={resetAndClose} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Create Company</DialogTitle>
      <DialogContent>
        <Stack spacing={2.5} sx={{ mt: 0.5 }}>
          {error ? <Alert severity="error">{error}</Alert> : null}
          <TextField
            label="Company name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="e.g. Acme Inc"
            fullWidth
            autoFocus
          />
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={resetAndClose} color="inherit">
          Cancel
        </Button>
        <Button variant="contained" onClick={handleCreate} disabled={!canCreate || submitting}>
          {submitting ? "Creating…" : "Create"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
