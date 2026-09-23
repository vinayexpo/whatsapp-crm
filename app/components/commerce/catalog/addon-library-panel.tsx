import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import Alert from "@mui/material/Alert";
import CircularProgress from "@mui/material/CircularProgress";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { AddonDefinition } from "~/data/types";

export function AddonLibraryPanel() {
  const [addons, setAddons] = useState<AddonDefinition[]>([]);
  const [name, setName] = useState("");
  const [price, setPrice] = useState("0");
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  function refresh() {
    setLoading(true);
    apiClient
      .listAddonDefinitions({ perPage: 100 })
      .then(({ data }) => setAddons(data))
      .catch(() => setError("Could not load add-ons."))
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    refresh();
  }, []);

  async function handleCreate() {
    if (!name.trim()) return;
    setCreating(true);
    setError(null);
    try {
      await apiClient.createAddonDefinition({ name: name.trim(), price: price.trim() ? Number(price) : 0 });
      setName("");
      setPrice("0");
      refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create add-on.");
    } finally {
      setCreating(false);
    }
  }

  async function handleDelete(addonId: string) {
    if (!window.confirm("Delete this add-on? It will be removed from all products using it.")) return;
    setError(null);
    try {
      await apiClient.deleteAddonDefinition(addonId);
      refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete add-on.");
    }
  }

  return (
    <Stack spacing={2.5}>
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        Add-ons defined here are shared across your catalog — attach any add-on to any number of products from the
        product's Add-ons tab.
      </Typography>

      {error && <Alert severity="error">{error}</Alert>}

      <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5}>
        <TextField
          size="small"
          label="Add-on name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          sx={{ flex: 2 }}
        />
        <TextField
          size="small"
          type="number"
          label="Price"
          value={price}
          onChange={(e) => setPrice(e.target.value)}
          sx={{ flex: 1 }}
        />
        <Button
          variant="contained"
          startIcon={<AddRoundedIcon />}
          onClick={handleCreate}
          disabled={creating || !name.trim()}
        >
          Add
        </Button>
      </Stack>

      {loading ? (
        <Box sx={{ display: "flex", justifyContent: "center", py: 3 }}>
          <CircularProgress size={24} />
        </Box>
      ) : (
        <Stack spacing={1}>
          {addons.map((addon) => (
            <Paper key={addon.id} variant="outlined" sx={{ p: 1.5, borderRadius: 2 }}>
              <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
                <Stack direction="row" spacing={1} sx={{ alignItems: "center", flexWrap: "wrap" }}>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {addon.name}
                  </Typography>
                  <Chip label={`+${addon.price}`} size="small" variant="outlined" />
                  {!addon.isActive && <Chip label="Inactive" size="small" />}
                </Stack>
                <IconButton size="small" onClick={() => handleDelete(addon.id)}>
                  <DeleteRoundedIcon fontSize="small" />
                </IconButton>
              </Stack>
            </Paper>
          ))}
          {addons.length === 0 && (
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              No add-ons yet. Create one above to start building your shared add-on library.
            </Typography>
          )}
        </Stack>
      )}
    </Stack>
  );
}
