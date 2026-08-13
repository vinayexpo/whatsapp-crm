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
import type { Company, TeamMember } from "~/data/types";

interface CreateAdminDialogProps {
  open: boolean;
  company: Company | null;
  onClose: () => void;
  onCreated: (admin: TeamMember) => void;
}

export function CreateAdminDialog({ open, company, onClose, onCreated }: CreateAdminDialogProps) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function resetAndClose() {
    setName("");
    setEmail("");
    setPassword("");
    setError(null);
    onClose();
  }

  async function handleCreate() {
    if (!company || !name.trim() || !email.trim() || password.length < 8) return;
    setError(null);
    setSubmitting(true);
    try {
      const admin = await apiClient.createCompanyAdmin(company.id, {
        name: name.trim(),
        email: email.trim(),
        password,
      });
      onCreated(admin);
      resetAndClose();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.errors?.email?.[0] ?? err.message);
      } else {
        setError("Something went wrong. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  const canCreate = name.trim().length > 0 && /\S+@\S+\.\S+/.test(email.trim()) && password.length >= 8;

  return (
    <Dialog open={open} onClose={resetAndClose} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>
        {company ? `Create Admin for ${company.name}` : "Create Admin"}
      </DialogTitle>
      <DialogContent>
        <Stack spacing={2.5} sx={{ mt: 0.5 }}>
          {error ? <Alert severity="error">{error}</Alert> : null}
          <TextField
            label="Full name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="e.g. Jordan Lee"
            fullWidth
            autoFocus
          />
          <TextField
            label="Email address"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="jordan.lee@company.com"
            fullWidth
          />
          <TextField
            label="Password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            helperText="Minimum 8 characters"
            fullWidth
          />
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={resetAndClose} color="inherit">
          Cancel
        </Button>
        <Button variant="contained" onClick={handleCreate} disabled={!canCreate || submitting}>
          {submitting ? "Creating…" : "Create Admin"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
