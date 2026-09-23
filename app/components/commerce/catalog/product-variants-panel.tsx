import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Alert from "@mui/material/Alert";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Product } from "~/data/types";

interface ProductVariantsPanelProps {
  product: Product;
  onUpdated: (product: Product) => void;
}

export function ProductVariantsPanel({ product, onUpdated }: ProductVariantsPanelProps) {
  const [name, setName] = useState("");
  const [priceDelta, setPriceDelta] = useState("0");
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleAdd() {
    if (!name.trim()) return;
    setAdding(true);
    setError(null);
    try {
      await apiClient.createProductVariant(product.id, {
        name: name.trim(),
        priceDelta: priceDelta.trim() ? Number(priceDelta) : 0,
      });
      const refreshed = await apiClient.getProduct(product.id);
      onUpdated(refreshed);
      setName("");
      setPriceDelta("0");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to add variant.");
    } finally {
      setAdding(false);
    }
  }

  async function handleDelete(variantId: string) {
    if (!window.confirm("Delete this variant?")) return;
    setError(null);
    try {
      await apiClient.deleteProductVariant(product.id, variantId);
      const refreshed = await apiClient.getProduct(product.id);
      onUpdated(refreshed);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete variant.");
    }
  }

  return (
    <Stack spacing={2}>
      {error && <Alert severity="error">{error}</Alert>}

      {product.variants.length === 0 && (
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          No variants yet. Add sizes, colors, or other options below.
        </Typography>
      )}

      {product.variants.map((variant) => (
        <Paper key={variant.id} variant="outlined" sx={{ p: 1.5, borderRadius: 2 }}>
          <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
            <Stack>
              <Typography variant="body2" sx={{ fontWeight: 600 }}>
                {variant.name}
              </Typography>
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                Price delta: {variant.priceDelta}
              </Typography>
            </Stack>
            <IconButton size="small" onClick={() => handleDelete(variant.id)}>
              <DeleteRoundedIcon fontSize="small" />
            </IconButton>
          </Stack>
        </Paper>
      ))}

      <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
        <TextField
          size="small"
          label="Variant name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          sx={{ flex: 2 }}
        />
        <TextField
          size="small"
          type="number"
          label="Price delta"
          value={priceDelta}
          onChange={(e) => setPriceDelta(e.target.value)}
          sx={{ flex: 1 }}
        />
        <Button variant="outlined" startIcon={<AddRoundedIcon />} onClick={handleAdd} disabled={adding || !name.trim()}>
          Add
        </Button>
      </Stack>
    </Stack>
  );
}
