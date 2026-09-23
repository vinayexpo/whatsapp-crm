import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import Typography from "@mui/material/Typography";
import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import CircularProgress from "@mui/material/CircularProgress";
import { apiClient, ApiError } from "~/utils/api-client";
import type { AddonDefinition, Product } from "~/data/types";

interface ProductAddonsPanelProps {
  product: Product;
  onUpdated: (product: Product) => void;
}

export function ProductAddonsPanel({ product, onUpdated }: ProductAddonsPanelProps) {
  const [allAddons, setAllAddons] = useState<AddonDefinition[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set(product.addons.map((a) => a.id)));
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setSelectedIds(new Set(product.addons.map((a) => a.id)));
  }, [product.id, product.addons]);

  useEffect(() => {
    setLoading(true);
    apiClient
      .listAddonDefinitions({ perPage: 100, isActive: true })
      .then(({ data }) => setAllAddons(data))
      .catch(() => setError("Could not load the add-on library."))
      .finally(() => setLoading(false));
  }, []);

  function toggle(id: string) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.syncProductAddons(product.id, Array.from(selectedIds));
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to update add-ons.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Stack spacing={2}>
      {error && <Alert severity="error">{error}</Alert>}

      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        Attach shared add-ons from your add-on library to this product.
      </Typography>

      {loading ? (
        <Box sx={{ display: "flex", justifyContent: "center", py: 3 }}>
          <CircularProgress size={24} />
        </Box>
      ) : (
        <>
          {allAddons.length === 0 && (
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              No add-ons in your library yet. Create some from the Add-on Library tab.
            </Typography>
          )}

          <Stack>
            {allAddons.map((addon) => (
              <FormControlLabel
                key={addon.id}
                control={<Checkbox checked={selectedIds.has(addon.id)} onChange={() => toggle(addon.id)} />}
                label={`${addon.name} (+${addon.price})`}
              />
            ))}
          </Stack>

          <Button variant="contained" onClick={handleSave} disabled={saving} sx={{ alignSelf: "flex-start" }}>
            Save add-ons
          </Button>
        </>
      )}
    </Stack>
  );
}
