import { useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Alert from "@mui/material/Alert";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import InputLabel from "@mui/material/InputLabel";
import FormControl from "@mui/material/FormControl";
import { ApiError } from "~/utils/api-client";
import type { Category } from "~/data/types";

interface CreateProductDialogProps {
  open: boolean;
  categories: Category[];
  onClose: () => void;
  onCreate: (input: { name: string; basePrice: number; categoryId?: string | null }) => Promise<void>;
}

export function CreateProductDialog({ open, categories, onClose, onCreate }: CreateProductDialogProps) {
  const [name, setName] = useState("");
  const [basePrice, setBasePrice] = useState("");
  const [categoryId, setCategoryId] = useState<string>("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function handleClose() {
    setName("");
    setBasePrice("");
    setCategoryId("");
    setError(null);
    onClose();
  }

  async function handleSubmit() {
    if (!name.trim() || !basePrice.trim()) return;
    setSubmitting(true);
    setError(null);
    try {
      await onCreate({ name: name.trim(), basePrice: Number(basePrice), categoryId: categoryId || null });
      handleClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create product.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="xs" fullWidth>
      <DialogTitle>New Product</DialogTitle>
      <DialogContent>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        <Stack spacing={2} sx={{ mt: 1 }}>
          <TextField autoFocus fullWidth label="Product name" value={name} onChange={(e) => setName(e.target.value)} />
          <TextField
            fullWidth
            type="number"
            label="Base price"
            value={basePrice}
            onChange={(e) => setBasePrice(e.target.value)}
            helperText="In your smallest currency unit (e.g. paise/cents)."
          />
          <FormControl fullWidth size="small">
            <InputLabel>Category (optional)</InputLabel>
            <Select value={categoryId} label="Category (optional)" onChange={(e) => setCategoryId(e.target.value)}>
              <MenuItem value="">None</MenuItem>
              {categories.map((category) => (
                <MenuItem key={category.id} value={category.id}>
                  {category.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={handleClose}>Cancel</Button>
        <Button
          variant="contained"
          onClick={handleSubmit}
          disabled={submitting || !name.trim() || !basePrice.trim()}
        >
          Create
        </Button>
      </DialogActions>
    </Dialog>
  );
}
