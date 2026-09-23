import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Switch from "@mui/material/Switch";
import FormControlLabel from "@mui/material/FormControlLabel";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import InputLabel from "@mui/material/InputLabel";
import FormControl from "@mui/material/FormControl";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Category, Product } from "~/data/types";

interface ProductSettingsPanelProps {
  product: Product;
  categories: Category[];
  onUpdated: (product: Product) => void;
  onDelete: () => Promise<void>;
}

export function ProductSettingsPanel({ product, categories, onUpdated, onDelete }: ProductSettingsPanelProps) {
  const [name, setName] = useState(product.name);
  const [description, setDescription] = useState(product.description ?? "");
  const [basePrice, setBasePrice] = useState(String(product.basePrice));
  const [salePrice, setSalePrice] = useState(product.salePrice != null ? String(product.salePrice) : "");
  const [categoryId, setCategoryId] = useState(product.categoryId ?? "");
  const [active, setActive] = useState(product.isActive);
  const [deliveryAvailable, setDeliveryAvailable] = useState(product.deliveryAvailable);
  const [pickupAvailable, setPickupAvailable] = useState(product.pickupAvailable);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.updateProduct(product.id, {
        name: name.trim(),
        description: description.trim() || null,
        basePrice: Number(basePrice),
        salePrice: salePrice.trim() ? Number(salePrice) : null,
        categoryId: categoryId || null,
        isActive: active,
        deliveryAvailable,
        pickupAvailable,
      });
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save changes.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!window.confirm(`Delete "${product.name}"? This cannot be undone.`)) return;
    setDeleting(true);
    setError(null);
    try {
      await onDelete();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete product.");
      setDeleting(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <TextField fullWidth label="Product name" value={name} onChange={(e) => setName(e.target.value)} />
      <TextField
        fullWidth
        multiline
        minRows={2}
        label="Description"
        value={description}
        onChange={(e) => setDescription(e.target.value)}
      />
      <TextField
        fullWidth
        type="number"
        label="Base price"
        value={basePrice}
        onChange={(e) => setBasePrice(e.target.value)}
      />
      <TextField
        fullWidth
        type="number"
        label="Sale price (optional)"
        value={salePrice}
        onChange={(e) => setSalePrice(e.target.value)}
      />
      <FormControl fullWidth size="small">
        <InputLabel>Category</InputLabel>
        <Select value={categoryId} label="Category" onChange={(e) => setCategoryId(e.target.value)}>
          <MenuItem value="">None</MenuItem>
          {categories.map((category) => (
            <MenuItem key={category.id} value={category.id}>
              {category.name}
            </MenuItem>
          ))}
        </Select>
      </FormControl>

      <FormControlLabel
        control={<Switch checked={active} onChange={(e) => setActive(e.target.checked)} />}
        label={active ? "Active" : "Inactive"}
      />
      <FormControlLabel
        control={<Switch checked={deliveryAvailable} onChange={(e) => setDeliveryAvailable(e.target.checked)} />}
        label="Available for delivery"
      />
      <FormControlLabel
        control={<Switch checked={pickupAvailable} onChange={(e) => setPickupAvailable(e.target.checked)} />}
        label="Available for pickup"
      />

      <Button variant="contained" onClick={handleSave} disabled={saving || !name.trim() || !basePrice.trim()}>
        Save changes
      </Button>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">Danger zone</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Deleting this product permanently removes it, its variants, and its add-on attachments.
        </Typography>
        <Button color="error" variant="outlined" onClick={handleDelete} disabled={deleting} sx={{ alignSelf: "flex-start" }}>
          Delete product
        </Button>
      </Stack>
    </Stack>
  );
}
