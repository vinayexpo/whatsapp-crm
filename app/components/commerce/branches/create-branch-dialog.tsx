import { useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Alert from "@mui/material/Alert";
import { ApiError } from "~/utils/api-client";

interface CreateBranchDialogProps {
  open: boolean;
  onClose: () => void;
  onCreate: (input: { name: string; slug: string; address?: string; phone?: string }) => Promise<void>;
}

function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "");
}

export function CreateBranchDialog({ open, onClose, onCreate }: CreateBranchDialogProps) {
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [slugTouched, setSlugTouched] = useState(false);
  const [address, setAddress] = useState("");
  const [phone, setPhone] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function handleClose() {
    setName("");
    setSlug("");
    setSlugTouched(false);
    setAddress("");
    setPhone("");
    setError(null);
    onClose();
  }

  function handleNameChange(value: string) {
    setName(value);
    if (!slugTouched) setSlug(slugify(value));
  }

  async function handleSubmit() {
    if (!name.trim() || !slug.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await onCreate({
        name: name.trim(),
        slug: slug.trim(),
        address: address.trim() || undefined,
        phone: phone.trim() || undefined,
      });
      handleClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create branch.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <DialogTitle>New Branch</DialogTitle>
      <DialogContent>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        <Stack spacing={2} sx={{ mt: 1 }}>
          <TextField
            autoFocus
            fullWidth
            label="Branch name"
            value={name}
            onChange={(e) => handleNameChange(e.target.value)}
          />
          <TextField
            fullWidth
            label="Slug"
            value={slug}
            onChange={(e) => {
              setSlugTouched(true);
              setSlug(e.target.value);
            }}
            helperText="Used in URLs and integrations, must be unique."
          />
          <TextField fullWidth label="Address (optional)" value={address} onChange={(e) => setAddress(e.target.value)} />
          <TextField fullWidth label="Phone (optional)" value={phone} onChange={(e) => setPhone(e.target.value)} />
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={handleClose}>Cancel</Button>
        <Button variant="contained" onClick={handleSubmit} disabled={submitting || !name.trim() || !slug.trim()}>
          Create
        </Button>
      </DialogActions>
    </Dialog>
  );
}
